<?php
/**
 * AI replies via the AI Engine plugin (Meow Apps).
 *
 * AI Engine exposes a PHP API on the `$mwai` global -- an instance of
 * Meow_MWAI_API -- whose simpleChatbotQuery() runs a configured chatbot,
 * including whatever site knowledge base that chatbot is wired to. Passing a
 * stable chatId per LINE user is what gives the conversation memory between
 * messages rather than answering each one cold.
 *
 * Nothing here fails hard when AI Engine is absent: the bot simply falls
 * through to its other reply paths.
 *
 * @package Mofoline
 */

namespace Mofoline\Bot\Ai;

use Mofoline\Support\Logger;
use Mofoline\Support\Options;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class AiEngineProvider implements ProviderInterface {

	/**
	 * Whether AI Engine can actually answer a question.
	 *
	 * An active AI Engine with no API key is the common half-configured state,
	 * and it is indistinguishable from a working one until a customer asks
	 * something and the reply fails. hasAI() is how AI Engine reports that, so
	 * "available" here means ready, not merely installed -- otherwise the setup
	 * checklist shows a tick over a bot that cannot say a word.
	 */
	public function is_available(): bool {
		if ( ! $this->installed() ) {
			return false;
		}

		$api = $this->api();

		// hasAI() arrived in AI Engine 2.x; on anything older, being installed
		// is the most that can be established.
		if ( ! method_exists( $api, 'hasAI' ) ) {
			return true;
		}

		try {
			return (bool) $api->hasAI();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Whether AI Engine is present and exposing the API this uses at all.
	 */
	public function installed(): bool {
		if ( ! defined( 'MWAI_VERSION' ) ) {
			return false;
		}

		$api = $this->api();

		return is_object( $api ) && method_exists( $api, 'simpleChatbotQuery' );
	}

	public function label(): string {
		return defined( 'MWAI_VERSION' )
			? sprintf( 'AI Engine %s', MWAI_VERSION )
			: 'AI Engine';
	}

	/**
	 * Ask the configured chatbot.
	 *
	 * @param string $message      Visitor message.
	 * @param string $conversation Stable conversation id.
	 * @param array  $context      Extra context.
	 * @return string|WP_Error
	 */
	public function ask( string $message, string $conversation, array $context = array() ) {
		$api = $this->api();

		if ( ! is_object( $api ) ) {
			return new WP_Error(
				'mofoline_ai_unavailable',
				__( 'AI Engine is not active on this site.', 'moksa-for-line' )
			);
		}

		// Without a key AI Engine answers "The environment is required.", which
		// is true and tells a shop owner nothing. Say what is actually missing.
		if ( ! $this->is_available() ) {
			return new WP_Error(
				'mofoline_ai_no_key',
				__( 'AI Engine has no AI service with an API key yet. Add one under Meow Apps > AI Engine > Settings.', 'moksa-for-line' )
			);
		}

		$bot_id = (string) Options::get( 'ai_bot_id' );

		if ( '' === $bot_id ) {
			$bot_id = 'default';
		}

		$params = array(
			// AI Engine keeps discussion history against this id when the
			// Discussions feature is enabled.
			'chatId'   => $conversation,
			'newMessage' => $message,
		);

		if ( ! empty( $context['display_name'] ) ) {
			$params['userName'] = (string) $context['display_name'];
		}

		/**
		 * Filter the parameters passed to AI Engine.
		 *
		 * @param array  $params  Query parameters.
		 * @param string $message Visitor message.
		 * @param array  $context Conversation context.
		 */
		$params = apply_filters( 'mofoline_ai_engine_params', $params, $message, $context );

		try {
			$reply = $api->simpleChatbotQuery( $bot_id, $message, $params, true );
		} catch ( \Throwable $e ) {
			Logger::error(
				'AI Engine threw while answering',
				array( 'detail' => $e->getMessage(), 'bot_id' => $bot_id ),
				'ai'
			);

			return new WP_Error( 'mofoline_ai_failed', $e->getMessage() );
		}

		if ( is_wp_error( $reply ) ) {
			return $reply;
		}

		// simpleChatbotQuery returns the reply string when $onlyReply is true,
		// but a misconfigured bot can hand back an array instead.
		if ( is_array( $reply ) ) {
			$reply = isset( $reply['reply'] ) ? $reply['reply'] : '';
		}

		$reply = trim( (string) $reply );

		if ( '' === $reply ) {
			return new WP_Error(
				'mofoline_ai_empty',
				__( 'The AI returned an empty answer.', 'moksa-for-line' )
			);
		}

		return $reply;
	}

	/**
	 * Chatbots AI Engine knows about, for the settings dropdown.
	 *
	 * @return array<string,string> Bot id => label.
	 */
	public function chatbots(): array {
		$bots = array();

		global $mwai_core;

		if ( is_object( $mwai_core ) && method_exists( $mwai_core, 'get_chatbots' ) ) {
			try {
				foreach ( (array) $mwai_core->get_chatbots() as $bot ) {
					$id = '';

					if ( is_array( $bot ) ) {
						$id    = isset( $bot['botId'] ) ? (string) $bot['botId'] : ( isset( $bot['id'] ) ? (string) $bot['id'] : '' );
						$name  = isset( $bot['name'] ) ? (string) $bot['name'] : $id;
					} else {
						continue;
					}

					if ( '' !== $id ) {
						$bots[ $id ] = $name;
					}
				}
			} catch ( \Throwable $e ) {
				Logger::debug( 'Could not list AI Engine chatbots', array( 'detail' => $e->getMessage() ), 'ai' );
			}
		}

		if ( empty( $bots ) ) {
			$bots['default'] = __( 'Default chatbot', 'moksa-for-line' );
		}

		return $bots;
	}

	/**
	 * The AI Engine API object, if the plugin has finished booting.
	 *
	 * @return object|null
	 */
	private function api() {
		global $mwai;

		return is_object( $mwai ) ? $mwai : null;
	}
}
