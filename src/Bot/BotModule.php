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
 * @package Moksa\Line
 */

namespace Moksa\Line\Bot;

use Moksa\Line\Bot\Ai\AiResponder;
use Moksa\Line\Inbox\Conversations;
use Moksa\Line\Support\Options;

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
		add_filter( 'moksa_line_compose_reply', array( $this, 'continue_flow' ), 10, 4 );
		add_filter( 'moksa_line_compose_reply', array( $this, 'start_flow' ), 20, 4 );
		add_filter( 'moksa_line_compose_reply', array( $this, 'keyword_reply' ), 30, 4 );
		add_filter( 'moksa_line_compose_reply', array( $this, 'ai_reply' ), 40, 4 );

		add_filter( 'moksa_line_compose_postback_reply', array( $this, 'postback_reply' ), 10, 4 );
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
				\Moksa\Line\Line\MessagingClient::text(
					__( 'A member of our team will reply here shortly.', 'moksa-line-login' )
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
