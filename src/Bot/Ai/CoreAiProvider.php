<?php
/**
 * AI replies through the WordPress AI Client.
 *
 * This is WordPress core, not a plugin: wp_ai_client_prompt() ships in
 * wp-includes/ai-client.php from WordPress 7.0, so on a current site nothing
 * needs installing to reach a model. What the official "AI" plugin
 * (wordpress.org/plugins/ai) adds is the admin screen for connecting a
 * provider and storing its credentials -- and any other plugin that registers
 * a provider works just as well.
 *
 * The core wrapper is a good citizen about failure: every generating method
 * returns string|WP_Error rather than throwing, and it uses snake_case names
 * that proxy to the underlying SDK. Calling the SDK's camelCase names through
 * it silently returns the builder instead of a result, which looks like
 * success and is not.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Bot\Ai;

use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class CoreAiProvider implements ProviderInterface {

	/**
	 * Whether this WordPress has the AI Client at all.
	 */
	public function installed(): bool {
		return function_exists( 'wp_ai_client_prompt' ) && function_exists( 'wp_supports_ai' );
	}

	/**
	 * Whether a model is actually reachable.
	 *
	 * Two separate gates. wp_supports_ai() is the site's own switch, which an
	 * administrator or a filter can turn off outright. Past that, a registered
	 * provider still needs credentials before any model supports text
	 * generation, and without them the only symptom is a failed reply to a real
	 * customer -- so it is asked here instead.
	 */
	public function is_available(): bool {
		if ( ! $this->installed() || ! wp_supports_ai() ) {
			return false;
		}

		try {
			return (bool) wp_ai_client_prompt( 'ping' )->is_supported_for_text_generation();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	public function label(): string {
		return get_bloginfo( 'version' )
			? sprintf(
				/* translators: %s: WordPress version. */
				__( 'WordPress AI Client (WordPress %s)', 'moksa-line' ),
				get_bloginfo( 'version' )
			)
			: __( 'WordPress AI Client', 'moksa-line' );
	}

	/**
	 * Ask the model.
	 *
	 * @param string $message      Visitor message.
	 * @param string $conversation Stable conversation id.
	 * @param array  $context      Extra context.
	 * @return string|WP_Error
	 */
	public function ask( string $message, string $conversation, array $context = array() ) {
		if ( ! $this->installed() ) {
			return new WP_Error(
				'moksa_line_ai_unavailable',
				__( 'This WordPress does not have the AI Client. It ships with WordPress 7.0 and later.', 'moksa-line' )
			);
		}

		if ( ! wp_supports_ai() ) {
			return new WP_Error(
				'moksa_line_ai_disabled',
				__( 'AI features are switched off for this site.', 'moksa-line' )
			);
		}

		if ( ! $this->is_available() ) {
			return new WP_Error(
				'moksa_line_ai_no_provider',
				__( 'No AI provider is connected yet. Install the AI plugin from WordPress.org and connect a provider, or add one another way.', 'moksa-line' )
			);
		}

		$builder = wp_ai_client_prompt( $message )
			->using_system_instruction( $this->system_instruction( $context ) );

		$max_chars = (int) Options::get( 'ai_max_chars' );

		if ( $max_chars > 0 ) {
			// Tokens are not characters, and the ratio is far worse for Chinese
			// than for English. A quarter of the character budget is a
			// deliberately loose ceiling: the reply is truncated to the exact
			// character limit afterwards anyway, and the point here is only to
			// stop the model writing an essay we pay for and then discard.
			$builder = $builder->using_max_tokens( max( 64, (int) ceil( $max_chars / 4 ) ) );
		}

		/**
		 * Adjust the prompt before it is sent.
		 *
		 * @param \WP_AI_Client_Prompt_Builder $builder Prompt builder.
		 * @param string                       $message Visitor message.
		 * @param array                        $context Conversation context.
		 */
		$builder = apply_filters( 'moksa_line_core_ai_prompt', $builder, $message, $context );

		$reply = $builder->generate_text();

		if ( is_wp_error( $reply ) ) {
			Logger::warning(
				'The WordPress AI Client could not answer',
				array( 'code' => $reply->get_error_code(), 'detail' => $reply->get_error_message() ),
				'ai'
			);

			return $reply;
		}

		$reply = trim( (string) $reply );

		if ( '' === $reply ) {
			return new WP_Error(
				'moksa_line_ai_empty',
				__( 'The AI returned an empty answer.', 'moksa-line' )
			);
		}

		return $reply;
	}

	/**
	 * The standing instruction the model answers under.
	 *
	 * The core client has no notion of a configured chatbot with a persona and
	 * a knowledge base, which is what AI Engine supplies. So the persona is
	 * assembled here from what the site already knows, and a filter is the seam
	 * for anyone who wants to feed in their own product data.
	 *
	 * @param array $context Conversation context.
	 */
	private function system_instruction( array $context ): string {
		$parts = array(
			sprintf(
				/* translators: %s: site name. */
				__( 'You are a customer service assistant for %s, replying inside LINE.', 'moksa-line' ),
				get_bloginfo( 'name' )
			),
			__( 'Answer in the language the customer used. Keep it short: this is a chat message, not an article.', 'moksa-line' ),
			__( 'If you do not know something, say so and offer to pass it to a person. Never invent prices, stock or delivery dates.', 'moksa-line' ),
		);

		if ( ! empty( $context['display_name'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: the customer's LINE display name. */
				__( 'The customer is called %s.', 'moksa-line' ),
				(string) $context['display_name']
			);
		}

		/**
		 * Filter the system instruction sent with every AI reply.
		 *
		 * @param string $instruction Assembled instruction.
		 * @param array  $context     Conversation context.
		 */
		return (string) apply_filters(
			'moksa_line_core_ai_system_instruction',
			implode( ' ', $parts ),
			$context
		);
	}
}
