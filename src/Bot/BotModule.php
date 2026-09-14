<?php
/**
 * Bot reply pipeline.
 *
 * Ordering is deliberate and is expressed as filter priorities rather than
 * nested conditionals:
 *
 *   10  a flow already in progress -- answering the question that was asked
 *       always beats matching a keyword inside the answer
 *   20  a flow trigger word
 *   30  keyword rules
 *   40  AI
 *
 * @package Mofoline
 */

namespace Mofoline\Bot;

use Mofoline\Bot\Ai\AiResponder;
use Mofoline\Data\QuickReplies;
use Mofoline\Inbox\Conversations;
use Mofoline\Api\MessagingClient;
use Mofoline\Support\Options;
use Mofoline\Admin\Ajax;

defined( 'ABSPATH' ) || exit;

class BotModule {

	/**
	 * @var AiResponder
	 */
	private $ai;

	public function __construct() {
		$this->ai = new AiResponder();
	}

	public function register(): void {
		add_filter( 'mofoline_compose_reply', array( $this, 'continue_flow' ), 10, 4 );
		add_filter( 'mofoline_compose_reply', array( $this, 'start_flow' ), 20, 4 );
		add_filter( 'mofoline_compose_reply', array( $this, 'keyword_reply' ), 30, 4 );
		add_filter( 'mofoline_compose_reply', array( $this, 'ai_reply' ), 40, 4 );

		add_filter( 'mofoline_compose_postback_reply', array( $this, 'postback_reply' ), 10, 4 );

		add_action( 'wp_ajax_mofoline_rule_save', array( $this, 'ajax_save_rule' ) );
		add_action( 'wp_ajax_mofoline_rule_delete', array( $this, 'ajax_delete_rule' ) );
		add_action( 'wp_ajax_mofoline_flow_save', array( $this, 'ajax_save_flow' ) );
		add_action( 'wp_ajax_mofoline_flow_delete', array( $this, 'ajax_delete_flow' ) );
		add_action( 'wp_ajax_mofoline_quick_reply_save', array( $this, 'ajax_save_quick_reply' ) );
		add_action( 'wp_ajax_mofoline_quick_reply_delete', array( $this, 'ajax_delete_quick_reply' ) );
	}

	// --- Admin AJAX -------------------------------------------------------------

	/**
	 * Save a keyword rule.
	 */
	public function ajax_save_rule(): void {
		$this->guard();

		$reply_type = Ajax::key( 'reply_type', 'text' );

		if ( in_array( $reply_type, array( 'raw', 'flex' ), true ) ) {
			// Raw and Flex payloads are JSON and must survive intact, so they are
			// validated rather than sanitised into uselessness. A bare number is
			// the id of a saved message instead.
			$decoded    = Ajax::json_verbatim( 'reply_data' );
			$reply_data = Ajax::text( 'reply_data' );

			if ( null !== $decoded ) {
				$reply_data = wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			} elseif ( '' !== $reply_data && ! ctype_digit( $reply_data ) ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %s: JSON parser message. */
							__( 'That is not valid JSON: %s', 'moksa-for-line' ),
							json_last_error_msg()
						),
					)
				);
			}
		} elseif ( 'text' === $reply_type ) {
			$reply_data = Ajax::textarea( 'reply_data' );
		} else {
			$reply_data = Ajax::text( 'reply_data' );
		}

		$keyword    = Ajax::text( 'keyword' );
		$match_type = Ajax::key( 'match_type', 'exact' );

		if ( 'any' !== $match_type && '' === trim( $keyword ) ) {
			wp_send_json_error( array( 'message' => __( 'Give the rule something to match on.', 'moksa-for-line' ) ) );
		}

		// A broken pattern would otherwise silently match nothing forever.
		if ( 'regex' === $match_type && null === AutoReply::matches_pattern( $keyword, '' ) ) {
			wp_send_json_error( array( 'message' => __( 'That pattern is not valid.', 'moksa-for-line' ) ) );
		}

		$id = AutoReply::save(
			array(
				'id'         => Ajax::int( 'id' ),
				'name'       => Ajax::text( 'name' ),
				'keyword'    => $keyword,
				'match_type' => $match_type,
				'reply_type' => $reply_type,
				'reply_data' => $reply_data,
				'priority'   => Ajax::int( 'priority', 10 ),
				'is_active'  => Ajax::flag( 'is_active' ),
			)
		);

		wp_send_json_success( array( 'id' => $id, 'message' => __( 'Rule saved.', 'moksa-for-line' ) ) );
	}

	/**
	 * Delete a keyword rule.
	 */
	public function ajax_delete_rule(): void {
		$this->guard();

		$id = Ajax::int( 'id' );

		if ( $id <= 0 || ! AutoReply::delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That rule no longer exists.', 'moksa-for-line' ) ), 404 );
		}

		wp_send_json_success( array( 'message' => __( 'Rule deleted.', 'moksa-for-line' ) ) );
	}

	/**
	 * Save a conversation flow.
	 */
	public function ajax_save_flow(): void {
		$this->guard();

		$definition = Ajax::json_verbatim( 'definition' );

		if ( ! is_array( $definition ) ) {
			wp_send_json_error( array( 'message' => __( 'The flow definition is not valid JSON.', 'moksa-for-line' ) ) );
		}

		if ( empty( $definition['steps'] ) || ! is_array( $definition['steps'] ) ) {
			wp_send_json_error( array( 'message' => __( 'A flow needs at least one step.', 'moksa-for-line' ) ) );
		}

		foreach ( $definition['steps'] as $index => $step ) {
			if ( empty( $step['prompt'] ) ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %d: step number. */
							__( 'Step %d has no question to ask.', 'moksa-for-line' ),
							(int) $index + 1
						),
					)
				);
			}
		}

		$email = Ajax::email( 'notify_email' );

		if ( '' !== $email && ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'That notification address is not valid.', 'moksa-for-line' ) ) );
		}

		$id = Flow::save(
			array(
				'id'            => Ajax::int( 'id' ),
				'name'          => Ajax::text( 'name' ),
				'trigger_type'  => Ajax::is( 'trigger_type', 'contains' ) ? 'contains' : 'keyword',
				'trigger_value' => Ajax::text( 'trigger_value' ),
				'definition'    => wp_json_encode( $definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'notify_email'  => $email,
				'is_active'     => Ajax::flag( 'is_active' ) ? 1 : 0,
			)
		);

		wp_send_json_success( array( 'id' => $id, 'message' => __( 'Flow saved.', 'moksa-for-line' ) ) );
	}

	/**
	 * Delete a conversation flow.
	 */
	public function ajax_delete_flow(): void {
		$this->guard();

		$id = Ajax::int( 'id' );

		// "Deleted." for a row that was never there tells somebody looking at
		// a stale list that they have just fixed something.
		if ( $id <= 0 || ! Flow::delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That flow no longer exists.', 'moksa-for-line' ) ), 404 );
		}

		wp_send_json_success( array( 'message' => __( 'Flow deleted.', 'moksa-for-line' ) ) );
	}

	/**
	 * Save a quick reply set.
	 */
	public function ajax_save_quick_reply(): void {
		$this->guard();

		$items = Ajax::json_verbatim( 'items' );

		if ( ! is_array( $items ) || empty( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'Add at least one quick reply button.', 'moksa-for-line' ) ) );
		}

		if ( count( $items ) > 13 ) {
			wp_send_json_error( array( 'message' => __( 'LINE allows at most 13 quick reply buttons.', 'moksa-for-line' ) ) );
		}

		$id = QuickReplies::save(
			array(
				'id'        => Ajax::int( 'id' ),
				'name'      => Ajax::text( 'name' ),
				'items'     => wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'is_active' => 1,
			)
		);

		wp_send_json_success( array( 'id' => $id, 'message' => __( 'Quick reply set saved.', 'moksa-for-line' ) ) );
	}

	/**
	 * Delete a quick reply set.
	 */
	public function ajax_delete_quick_reply(): void {
		$this->guard();

		$id = Ajax::int( 'id' );

		if ( $id <= 0 || ! QuickReplies::delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That quick reply set no longer exists.', 'moksa-for-line' ) ), 404 );
		}

		wp_send_json_success( array( 'message' => __( 'Quick reply set deleted.', 'moksa-for-line' ) ) );
	}

	/**
	 * Shared nonce and capability check.
	 */
	private function guard(): void {
		Ajax::guard( __( 'You do not have permission to change bot settings.', 'moksa-for-line' ) );
	}

	/**
	 * Step 10: feed the message into a running scenario.
	 *
	 * @param array|null $messages     Reply so far.
	 * @param string     $text         Inbound text.
	 * @param array      $event        Webhook event.
	 * @param string     $line_user_id LINE user id.
	 * @return array|null
	 */
	public function continue_flow( $messages, $text, $event, $line_user_id ) {
		if ( null !== $messages || '' === $text ) {
			return $messages;
		}

		$session = Flow::session( $line_user_id );

		if ( ! $session ) {
			return $messages;
		}

		return Flow::advance( $session, $text, $line_user_id );
	}

	/**
	 * Step 20: a trigger word starts a scenario.
	 *
	 * @param array|null $messages     Reply so far.
	 * @param string     $text         Inbound text.
	 * @param array      $event        Webhook event.
	 * @param string     $line_user_id LINE user id.
	 * @return array|null
	 */
	public function start_flow( $messages, $text, $event, $line_user_id ) {
		if ( null !== $messages || '' === $text ) {
			return $messages;
		}

		if ( $this->human_handled( $line_user_id ) ) {
			return $messages;
		}

		$flow = Flow::match_trigger( $text );

		if ( ! $flow ) {
			return $messages;
		}

		return Flow::start( (int) $flow->id, $line_user_id );
	}

	/**
	 * Step 30: keyword rules.
	 *
	 * @param array|null $messages     Reply so far.
	 * @param string     $text         Inbound text.
	 * @param array      $event        Webhook event.
	 * @param string     $line_user_id LINE user id.
	 * @return array|null
	 */
	public function keyword_reply( $messages, $text, $event, $line_user_id ) {
		if ( null !== $messages || '' === $text ) {
			return $messages;
		}

		if ( $this->human_handled( $line_user_id ) ) {
			return $messages;
		}

		$rule = AutoReply::match( $text );

		if ( ! $rule ) {
			return $messages;
		}

		AutoReply::record_hit( (int) $rule->id );

		// A rule can hand off to a scenario instead of replying directly.
		if ( 'flow' === $rule->reply_type ) {
			return Flow::start( (int) $rule->reply_data, $line_user_id );
		}

		return AutoReply::build_messages( $rule, $line_user_id );
	}

	/**
	 * Step 40: AI.
	 *
	 * When ai_fallback_only is off, the AI answers everything the earlier
	 * steps did not claim; when it is on, that is the same thing -- the
	 * distinction exists so a site can turn the AI into the *first* responder
	 * by filtering this method to an earlier priority.
	 *
	 * @param array|null $messages     Reply so far.
	 * @param string     $text         Inbound text.
	 * @param array      $event        Webhook event.
	 * @param string     $line_user_id LINE user id.
	 * @return array|null
	 */
	public function ai_reply( $messages, $text, $event, $line_user_id ) {
		if ( null !== $messages || '' === $text ) {
			return $messages;
		}

		return $this->ai->respond( $text, $line_user_id );
	}

	/**
	 * Postbacks the plugin issues itself.
	 *
	 * Data is a query string, so a site's own postbacks pass through
	 * untouched: only keys under the moksa_ prefix are claimed here.
	 *
	 * @param array|null $messages     Reply so far.
	 * @param string     $data         Postback data.
	 * @param array      $event        Webhook event.
	 * @param string     $line_user_id LINE user id.
	 * @return array|null
	 */
	public function postback_reply( $messages, $data, $event, $line_user_id ) {
		if ( null !== $messages || '' === $data ) {
			return $messages;
		}

		parse_str( $data, $parsed );

		if ( ! empty( $parsed['moksa_flow'] ) ) {
			return Flow::start( (int) $parsed['moksa_flow'], $line_user_id );
		}

		if ( ! empty( $parsed['moksa_rule'] ) ) {
			$rule = AutoReply::find( (int) $parsed['moksa_rule'] );

			if ( $rule ) {
				AutoReply::record_hit( (int) $rule->id );

				return AutoReply::build_messages( $rule, $line_user_id );
			}
		}

		if ( ! empty( $parsed['moksa_action'] ) && 'handoff' === $parsed['moksa_action'] ) {
			Conversations::set_status( $line_user_id, 'human' );

			return array(
				MessagingClient::text(
					__( 'A member of our team will reply here shortly.', 'moksa-for-line' )
				),
			);
		}

		return $messages;
	}

	/**
	 * Whether an agent has taken this conversation over.
	 *
	 * @param string $line_user_id LINE user id.
	 */
	private function human_handled( string $line_user_id ): bool {
		return Options::get( 'inbox_enabled' ) && Conversations::is_human_handled( $line_user_id );
	}
}
