<?php
/**
 * Customer-service inbox: capture, and reply from wp-admin.
 *
 * Replies from the inbox are push messages, which are billed and count
 * against the channel's monthly quota. That is unavoidable -- reply tokens
 * live for one minute and belong to the webhook request -- but it is worth
 * knowing before turning the inbox into the primary support channel.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Inbox;

use Moksa\Line\Data\Users;
use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;
use Moksa\Line\Webhook\Dispatcher;
use Moksa\Line\Admin\Ajax;

defined( 'ABSPATH' ) || exit;

class InboxModule {

	/**
	 * Capability required to read and answer conversations.
	 */
	const CAPABILITY = 'moksa_line_manage_inbox';

	public function register(): void {
		if ( ! Options::get( 'inbox_enabled' ) ) {
			return;
		}

		add_action( 'moksa_line_inbound_message', array( $this, 'record_inbound' ), 10, 2 );
		add_action( 'moksa_line_replied', array( $this, 'record_bot_reply' ), 10, 3 );
		add_action( 'moksa_line_pushed', array( $this, 'record_push' ), 10, 2 );
		add_action( 'moksa_line_event_follow', array( $this, 'on_follow' ), 10, 2 );

		add_action( 'wp_ajax_moksa_line_inbox_send', array( $this, 'ajax_send' ) );
		add_action( 'wp_ajax_moksa_line_inbox_thread', array( $this, 'ajax_thread' ) );
		add_action( 'wp_ajax_moksa_line_inbox_status', array( $this, 'ajax_set_status' ) );

		// Live updates ride WordPress's own heartbeat: no extra endpoint to
		// guard, no timer of ours, and it already backs off when the tab is
		// hidden.
		add_filter( 'heartbeat_received', array( $this, 'heartbeat' ), 10, 2 );
		add_filter( 'heartbeat_settings', array( $this, 'heartbeat_interval' ) );
	}

	// --- Live updates ------------------------------------------------------------

	/**
	 * Poll faster on the inbox screen. Elsewhere the default stands.
	 *
	 * @param array $settings Heartbeat settings.
	 * @return array
	 */
	public function heartbeat_interval( $settings ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && false !== strpos( (string) $screen->id, 'moksa-line-inbox' ) ) {
			$settings['interval'] = 15;
		}

		return $settings;
	}

	/**
	 * Answer the inbox's heartbeat: has anything arrived since it last looked?
	 *
	 * The list is re-rendered from the same partial the page uses, with the
	 * same filter, so what arrives is exactly what a reload would have shown.
	 * The open thread is only flagged; the client fetches it through the
	 * existing thread endpoint rather than a second copy of that rendering.
	 *
	 * @param array $response What goes back to the browser.
	 * @param array $data     What the browser sent.
	 * @return array
	 */
	public function heartbeat( $response, $data ) {
		if ( empty( $data['moksa_line_inbox'] ) || ! is_array( $data['moksa_line_inbox'] ) || ! self::can_manage() ) {
			return $response;
		}

		$asked        = $data['moksa_line_inbox'];
		$since        = isset( $asked['since'] ) ? (int) $asked['since'] : 0;
		$open         = isset( $asked['conversation'] ) ? (int) $asked['conversation'] : 0;
		$status       = isset( $asked['status'] ) ? sanitize_key( (string) $asked['status'] ) : '';
		$search       = isset( $asked['search'] ) ? sanitize_text_field( (string) $asked['search'] ) : '';
		$latest       = Messages::latest_id();
		$new_inbound  = Messages::inbound_since( $since );
		$answer       = array(
			'latest'  => $latest,
			'inbound' => $new_inbound,
		);

		// Nothing at all since the last look, inbound or outbound: say so and
		// send nothing else. This is the common case fifteen seconds apart.
		if ( $latest <= $since ) {
			$response['moksa_line_inbox'] = $answer;

			return $response;
		}

		$list          = Conversations::paginate( array( 'status' => $status, 'search' => $search, 'per_page' => 50 ) );
		$status_labels = array(
			'bot'    => __( 'bot', 'moksa-line' ),
			'human'  => __( 'human', 'moksa-line' ),
			'closed' => __( 'closed', 'moksa-line' ),
		);

		ob_start();
		require MOKSA_LINE_DIR . 'views/inbox-list.php';
		$answer['list'] = ob_get_clean();

		$answer['thread_changed'] = $open > 0 && Messages::inbound_since( $since, $open ) > 0;

		if ( $new_inbound > 0 ) {
			// The newest conversation with something unread, for the notification.
			$top = isset( $list['rows'][0] ) ? $list['rows'][0] : null;

			if ( $top ) {
				$answer['notice'] = array(
					'name'    => '' !== (string) $top->display_name ? (string) $top->display_name : (string) $top->line_user_id,
					'preview' => (string) $top->last_message_preview,
				);
			}
		}

		$response['moksa_line_inbox'] = $answer;

		return $response;
	}

	/**
	 * Whether the current user may work the inbox.
	 */
	public static function can_manage(): bool {
		return current_user_can( self::CAPABILITY ) || current_user_can( 'manage_options' );
	}

	/**
	 * Store an inbound message.
	 *
	 * @param array  $event        Webhook event.
	 * @param string $line_user_id LINE user id.
	 */
	public function record_inbound( array $event, string $line_user_id ): void {
		if ( '' === $line_user_id ) {
			return;
		}

		$message = isset( $event['message'] ) && is_array( $event['message'] ) ? $event['message'] : array();

		// A user we have never seen has no display name yet, and an inbox full
		// of raw U... ids is useless.
		$record = Users::by_line_id( $line_user_id );

		if ( ! $record || '' === (string) $record->display_name ) {
			Dispatcher::refresh_profile( $line_user_id );
		}

		$conversation_id = Conversations::ensure( $line_user_id );
		$body            = Messages::describe( $message );

		Messages::record(
			array(
				'conversation_id' => $conversation_id,
				'line_user_id'    => $line_user_id,
				'direction'       => 'in',
				'message_type'    => isset( $message['type'] ) ? (string) $message['type'] : 'text',
				'body'            => $body,
				'payload'         => $message,
				'line_message_id' => isset( $message['id'] ) ? (string) $message['id'] : '',
				'sender_kind'     => 'user',
			)
		);

		Conversations::touch( $line_user_id, $body, true );
	}

	/**
	 * Store what the bot said.
	 *
	 * @param array  $messages     Sent message objects.
	 * @param string $line_user_id LINE user id.
	 * @param array  $event        Originating event.
	 */
	public function record_bot_reply( array $messages, string $line_user_id, array $event ): void {
		if ( '' === $line_user_id ) {
			return;
		}

		$conversation_id = Conversations::ensure( $line_user_id );
		$body            = Messages::describe_outbound( $messages );

		Messages::record(
			array(
				'conversation_id' => $conversation_id,
				'line_user_id'    => $line_user_id,
				'direction'       => 'out',
				'message_type'    => isset( $messages[0]['type'] ) ? (string) $messages[0]['type'] : 'text',
				'body'            => $body,
				'payload'         => $messages,
				'sender_kind'     => 'bot',
			)
		);

		Conversations::touch( $line_user_id, $body, false );
	}

	/**
	 * Store anything pushed to one customer.
	 *
	 * An order notification or a payment receipt is part of that conversation
	 * as far as the customer is concerned, so it belongs in the thread an agent
	 * reads before replying. Recorded only for people already known to the
	 * inbox -- a push to an id this site has never seen should not conjure a
	 * conversation out of nothing.
	 *
	 * @param array  $messages     Sent message objects.
	 * @param string $line_user_id Recipient.
	 */
	public function record_push( array $messages, string $line_user_id ): void {
		if ( '' === $line_user_id || ! Options::get( 'inbox_enabled' ) ) {
			return;
		}

		$conversation = Conversations::find( $line_user_id );

		if ( ! $conversation ) {
			return;
		}

		$body = Messages::describe_outbound( $messages );

		Messages::record(
			array(
				'conversation_id' => (int) $conversation->id,
				'line_user_id'    => $line_user_id,
				'direction'       => 'out',
				'message_type'    => isset( $messages[0]['type'] ) ? (string) $messages[0]['type'] : 'text',
				'body'            => $body,
				'payload'         => $messages,
				'sender_kind'     => 'bot',
			)
		);

		Conversations::touch( $line_user_id, $body, false );
	}

	/**
	 * Open a conversation as soon as someone adds the bot.
	 *
	 * @param array  $event        Webhook event.
	 * @param string $line_user_id LINE user id.
	 */
	public function on_follow( array $event, string $line_user_id ): void {
		Conversations::ensure( $line_user_id );
	}

	// --- Admin AJAX -------------------------------------------------------------

	/**
	 * Send an agent's reply.
	 */
	public function ajax_send(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'You cannot reply to conversations.', 'moksa-line' ) ), 403 );
		}

		$conversation_id = Ajax::int( 'conversation_id' );
		$text            = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$package_id      = Ajax::text( 'sticker_package' );
		$sticker_id      = Ajax::text( 'sticker_id' );
		$is_sticker      = '' !== $package_id && '' !== $sticker_id;

		if ( ! $is_sticker && '' === trim( $text ) ) {
			wp_send_json_error( array( 'message' => __( 'Write something first.', 'moksa-line' ) ) );
		}

		$conversation = Conversations::find_by_id( $conversation_id );

		if ( ! $conversation ) {
			wp_send_json_error( array( 'message' => __( 'That conversation no longer exists.', 'moksa-line' ) ), 404 );
		}

		$line_user_id = (string) $conversation->line_user_id;

		// A sticker goes on its own: LINE has no notion of a caption, and
		// sending both would be two billed messages rather than one.
		$messages = $is_sticker
			? array( MessagingClient::sticker( $package_id, $sticker_id ) )
			: array( MessagingClient::text( $text ) );

		$result = MessagingClient::push( $line_user_id, $messages, array( 'record' => false ) );

		$failed = is_wp_error( $result );

		Messages::record(
			array(
				'conversation_id'   => $conversation_id,
				'line_user_id'      => $line_user_id,
				'direction'         => 'out',
				'message_type'      => $is_sticker ? 'sticker' : 'text',
				'body'              => $is_sticker ? Messages::describe_outbound( $messages ) : $text,
				'payload'           => $messages,
				'sender_wp_user_id' => get_current_user_id(),
				'sender_kind'       => 'agent',
				'status'            => $failed ? 'failed' : 'sent',
				'error'             => $failed ? $result->get_error_message() : '',
			)
		);

		if ( $failed ) {
			Logger::capture( $result, 'An agent reply could not be delivered', 'inbox' );

			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error detail from LINE. */
						__( 'LINE would not deliver that message: %s', 'moksa-line' ),
						$result->get_error_message()
					),
				)
			);
		}

		Conversations::touch( $line_user_id, $is_sticker ? Messages::describe_outbound( $messages ) : $text, false );

		// Answering means taking ownership; the bot must not talk over an agent.
		if ( 'human' !== $conversation->status ) {
			Conversations::set_status( $line_user_id, 'human', get_current_user_id() );
		}

		Conversations::mark_read( $conversation_id );

		wp_send_json_success(
			array(
				'message' => __( 'Sent.', 'moksa-line' ),
				'thread'  => $this->render_thread( $conversation_id ),
			)
		);
	}

	/**
	 * Return a conversation thread, and mark it read.
	 */
	public function ajax_thread(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'You cannot read conversations.', 'moksa-line' ) ), 403 );
		}

		$conversation_id = Ajax::int( 'conversation_id' );
		$conversation    = Conversations::find_by_id( $conversation_id );

		if ( ! $conversation ) {
			wp_send_json_error( array( 'message' => __( 'That conversation no longer exists.', 'moksa-line' ) ), 404 );
		}

		Conversations::mark_read( $conversation_id );

		wp_send_json_success(
			array(
				'thread'  => $this->render_thread( $conversation_id ),
				'status'  => (string) $conversation->status,
				'name'    => (string) $conversation->display_name,
				'picture' => (string) $conversation->picture_url,
			)
		);
	}

	/**
	 * Hand a conversation to a human, or back to the bot.
	 */
	public function ajax_set_status(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'You cannot change conversations.', 'moksa-line' ) ), 403 );
		}

		$conversation_id = Ajax::int( 'conversation_id' );
		$status          = Ajax::key( 'status' );

		$conversation = Conversations::find_by_id( $conversation_id );

		if ( ! $conversation || ! in_array( $status, Conversations::STATUSES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'That change is not valid.', 'moksa-line' ) ) );
		}

		Conversations::set_status(
			(string) $conversation->line_user_id,
			$status,
			'human' === $status ? get_current_user_id() : 0
		);

		wp_send_json_success( array( 'status' => $status ) );
	}

	/**
	 * Render the thread as HTML for the admin panel.
	 *
	 * @param int $conversation_id Conversation id.
	 */
	private function render_thread( int $conversation_id ): string {
		$rows = Messages::thread( $conversation_id, 200 );

		ob_start();

		if ( empty( $rows ) ) {
			echo '<p class="moksa-inbox-empty">' . esc_html__( 'No messages yet. Only messages received after this plugin was installed appear here -- LINE does not provide access to earlier chat history.', 'moksa-line' ) . '</p>';
		}

		foreach ( $rows as $row ) {
			$classes = 'moksa-msg moksa-msg--' . sanitize_html_class( (string) $row->direction );

			if ( 'failed' === $row->status ) {
				$classes .= ' moksa-msg--failed';
			}

			$who = __( 'Visitor', 'moksa-line' );

			if ( 'out' === $row->direction ) {
				if ( 'agent' === $row->sender_kind ) {
					$user = get_userdata( (int) $row->sender_wp_user_id );
					$who  = $user ? $user->display_name : __( 'Agent', 'moksa-line' );
				} else {
					$who = __( 'Bot', 'moksa-line' );
				}
			}

			printf(
				'<div class="%1$s"><div class="moksa-msg__meta">%2$s &middot; %3$s</div><div class="moksa-msg__body">%4$s</div>%5$s</div>',
				esc_attr( $classes ),
				esc_html( $who ),
				esc_html( mysql2date( 'Y-m-d H:i', get_date_from_gmt( (string) $row->created_at ) ) ),
				nl2br( esc_html( (string) $row->body ) ),
				'failed' === $row->status
					? '<div class="moksa-msg__error">' . esc_html( (string) $row->error ) . '</div>'
					: ''
			);
		}

		return (string) ob_get_clean();
	}
}
