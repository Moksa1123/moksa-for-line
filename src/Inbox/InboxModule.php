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
		add_action( 'moksa_line_event_follow', array( $this, 'on_follow' ), 10, 2 );

		add_action( 'wp_ajax_moksa_line_inbox_send', array( $this, 'ajax_send' ) );
		add_action( 'wp_ajax_moksa_line_inbox_thread', array( $this, 'ajax_thread' ) );
		add_action( 'wp_ajax_moksa_line_inbox_status', array( $this, 'ajax_set_status' ) );
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
			wp_send_json_error( array( 'message' => __( 'You cannot reply to conversations.', 'moksa-line-login' ) ), 403 );
		}

		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		$text            = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( '' === trim( $text ) ) {
			wp_send_json_error( array( 'message' => __( 'Write something first.', 'moksa-line-login' ) ) );
		}

		$conversation = Conversations::find_by_id( $conversation_id );

		if ( ! $conversation ) {
			wp_send_json_error( array( 'message' => __( 'That conversation no longer exists.', 'moksa-line-login' ) ), 404 );
		}

		$line_user_id = (string) $conversation->line_user_id;
		$messages     = array( MessagingClient::text( $text ) );

		$result = MessagingClient::push( $line_user_id, $messages );

		$failed = is_wp_error( $result );

		Messages::record(
			array(
				'conversation_id'   => $conversation_id,
				'line_user_id'      => $line_user_id,
				'direction'         => 'out',
				'message_type'      => 'text',
				'body'              => $text,
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
						__( 'LINE would not deliver that message: %s', 'moksa-line-login' ),
						$result->get_error_message()
					),
				)
			);
		}

		Conversations::touch( $line_user_id, $text, false );

		// Answering means taking ownership; the bot must not talk over an agent.
		if ( 'human' !== $conversation->status ) {
			Conversations::set_status( $line_user_id, 'human', get_current_user_id() );
		}

		Conversations::mark_read( $conversation_id );

		wp_send_json_success(
			array(
				'message' => __( 'Sent.', 'moksa-line-login' ),
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
			wp_send_json_error( array( 'message' => __( 'You cannot read conversations.', 'moksa-line-login' ) ), 403 );
		}

		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		$conversation    = Conversations::find_by_id( $conversation_id );

		if ( ! $conversation ) {
			wp_send_json_error( array( 'message' => __( 'That conversation no longer exists.', 'moksa-line-login' ) ), 404 );
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
			wp_send_json_error( array( 'message' => __( 'You cannot change conversations.', 'moksa-line-login' ) ), 403 );
		}

		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		$status          = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		$conversation = Conversations::find_by_id( $conversation_id );

		if ( ! $conversation || ! in_array( $status, Conversations::STATUSES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'That change is not valid.', 'moksa-line-login' ) ) );
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
			echo '<p class="moksa-inbox-empty">' . esc_html__( 'No messages yet. Only messages received after this plugin was installed appear here -- LINE does not provide access to earlier chat history.', 'moksa-line-login' ) . '</p>';
		}

		foreach ( $rows as $row ) {
			$classes = 'moksa-msg moksa-msg--' . sanitize_html_class( (string) $row->direction );

			if ( 'failed' === $row->status ) {
				$classes .= ' moksa-msg--failed';
			}

			$who = __( 'Visitor', 'moksa-line-login' );

			if ( 'out' === $row->direction ) {
				if ( 'agent' === $row->sender_kind ) {
					$user = get_userdata( (int) $row->sender_wp_user_id );
					$who  = $user ? $user->display_name : __( 'Agent', 'moksa-line-login' );
				} else {
					$who = __( 'Bot', 'moksa-line-login' );
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
