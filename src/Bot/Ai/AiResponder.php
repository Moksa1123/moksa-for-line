<?php
/**
 * The AI reply step.
 *
 * Guard rails matter more here than anywhere else in the plugin: an AI reply
 * costs money per message and answers strangers on the internet. So there is a
 * daily cap, a per-message length limit, a hand-off keyword that takes the bot
 * out of the conversation entirely, and a hard rule that a conversation a
 * human has taken over never gets an AI reply.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Bot\Ai;

use Moksa\Line\Data\Users;
use Moksa\Line\Inbox\Conversations;
use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;

defined( 'ABSPATH' ) || exit;

class AiResponder {

	const USAGE_PREFIX = 'moksa_line_ai_usage_';

	/**
	 * @var ProviderInterface|null
	 */
	private $provider = null;

	/**
	 * The configured provider, or null when AI is off or unavailable.
	 */
	public function provider(): ?ProviderInterface {
		if ( null !== $this->provider ) {
			return $this->provider;
		}

		if ( ! Options::get( 'ai_enabled' ) ) {
			return null;
		}

		$provider = Providers::make();

		/**
		 * Swap in a different AI provider.
		 *
		 * @param ProviderInterface|null $provider Selected provider.
		 */
		$provider = apply_filters( 'moksa_line_ai_provider', $provider );

		if ( ! $provider instanceof ProviderInterface || ! $provider->is_available() ) {
			return null;
		}

		$this->provider = $provider;

		return $this->provider;
	}

	/**
	 * Compose an AI reply, or null to pass.
	 *
	 * @param string $text         Inbound text.
	 * @param string $line_user_id LINE user id.
	 * @return array|null Message objects.
	 */
	public function respond( string $text, string $line_user_id ) {
		$provider = $this->provider();

		if ( ! $provider || '' === trim( $text ) ) {
			return null;
		}

		// A conversation a human has claimed belongs to the human.
		if ( Conversations::is_human_handled( $line_user_id ) ) {
			return null;
		}

		$handoff = trim( (string) Options::get( 'ai_handoff_keyword' ) );

		// stripos rather than mb_stripos: WordPress polyfills mb_substr and
		// mb_strlen on hosts without mbstring, but not mb_stripos. UTF-8 is
		// self-synchronising, so a byte-wise substring search is correct here;
		// only case folding of non-ASCII differs, which does not matter for a
		// keyword an administrator typed.
		if ( '' !== $handoff && false !== stripos( $text, $handoff ) ) {
			Conversations::set_status( $line_user_id, 'human' );

			return array(
				MessagingClient::text(
					__( 'Sure -- I have passed this to a member of our team. They will reply here shortly.', 'moksa-line' )
				),
			);
		}

		if ( ! $this->within_daily_cap() ) {
			Logger::warning( 'The daily AI reply cap has been reached', array(), 'ai' );

			return null;
		}

		// LINE shows nothing while the model thinks, so a slow answer looks
		// like a broken bot. The typing indicator costs one cheap API call.
		MessagingClient::show_loading( $line_user_id, 20 );

		$record  = Users::by_line_id( $line_user_id );
		$context = array(
			'line_user_id' => $line_user_id,
			'display_name' => $record ? (string) $record->display_name : '',
		);

		$answer = $provider->ask( $text, $this->conversation_id( $line_user_id ), $context );

		if ( is_wp_error( $answer ) ) {
			Logger::capture( $answer, 'The AI provider could not answer', 'ai' );

			return null;
		}

		$this->count_usage();

		$max = max( 100, (int) Options::get( 'ai_max_chars' ) );

		/**
		 * Filter the AI answer before it is sent to LINE.
		 *
		 * @param string $answer       Generated answer.
		 * @param string $text         Visitor message.
		 * @param string $line_user_id LINE user id.
		 */
		$answer = (string) apply_filters( 'moksa_line_ai_answer', $answer, $text, $line_user_id );

		if ( '' === trim( $answer ) ) {
			return null;
		}

		return array( MessagingClient::text( mb_substr( $answer, 0, $max ) ) );
	}

	/**
	 * Whether today's AI budget still has room.
	 */
	private function within_daily_cap(): bool {
		$cap = (int) Options::get( 'ai_daily_cap' );

		if ( $cap <= 0 ) {
			return true;
		}

		return $this->usage_today() < $cap;
	}

	/**
	 * Replies generated today.
	 */
	public function usage_today(): int {
		return (int) get_transient( self::USAGE_PREFIX . gmdate( 'Ymd' ) );
	}

	/**
	 * Record one generated reply.
	 */
	private function count_usage(): void {
		$key   = self::USAGE_PREFIX . gmdate( 'Ymd' );
		$count = (int) get_transient( $key );

		set_transient( $key, $count + 1, 2 * DAY_IN_SECONDS );
	}

	/**
	 * A stable conversation id, so the provider can keep context per person
	 * without ever being handed the raw LINE user id.
	 *
	 * @param string $line_user_id LINE user id.
	 */
	private function conversation_id( string $line_user_id ): string {
		return 'line-' . substr( hash( 'sha256', $line_user_id . wp_salt( 'nonce' ) ), 0, 24 );
	}
}
