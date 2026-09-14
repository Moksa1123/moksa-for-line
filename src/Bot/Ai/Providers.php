<?php
/**
 * Which AI provider the site is set to use.
 *
 * Three places need to answer this -- the responder that asks a question, the
 * setup checklist, and the settings screen -- and they were each constructing
 * a provider by hand. That is how the settings screen ended up reporting on
 * AI Engine while the bot was configured to use something else.
 *
 * @package Mofoline
 */

namespace Mofoline\Bot\Ai;

use Mofoline\Support\Options;

defined( 'ABSPATH' ) || exit;

class Providers {

	/**
	 * The selectable providers, as key => label.
	 *
	 * The WordPress AI Client is first because it is core from WordPress 7.0
	 * and needs nothing installed; AI Engine is for sites already using it or
	 * still on an older WordPress.
	 *
	 * @return array<string,string>
	 */
	public static function choices(): array {
		return array(
			'core'      => __( 'WordPress AI (built into WordPress 7.0 and later)', 'moksa-for-line' ),
			'ai_engine' => __( 'AI Engine (Meow Apps)', 'moksa-for-line' ),
			'none'      => __( 'None', 'moksa-for-line' ),
		);
	}

	/**
	 * Build one provider by key.
	 *
	 * @param string|null $key Provider key, or null for the configured one.
	 * @return ProviderInterface|null Null when set to none, or unknown.
	 */
	public static function make( ?string $key = null ): ?ProviderInterface {
		if ( null === $key ) {
			$key = (string) Options::get( 'ai_provider' );
		}

		switch ( $key ) {
			case 'core':
				return new CoreAiProvider();

			case 'ai_engine':
				return new AiEngineProvider();
		}

		return null;
	}
}
