<?php
/**
 * Contract for an AI reply provider.
 *
 * The bot does not care which plugin or API answers; it needs a string back,
 * or a WP_Error it can log and fall through from.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Bot\Ai;

defined( 'ABSPATH' ) || exit;

interface ProviderInterface {

	/**
	 * Whether this provider can currently answer.
	 *
	 * Ready, not merely present. A provider that is installed but has no
	 * credentials must report false, or callers will offer a bot that cannot
	 * say a word.
	 */
	public function is_available(): bool;

	/**
	 * Whether the underlying plugin or service is present at all.
	 *
	 * Separate from is_available() so a caller can tell "not installed" from
	 * "installed but not configured" and point at the right fix.
	 */
	public function installed(): bool;

	/**
	 * Human-readable name for the settings screen.
	 */
	public function label(): string;

	/**
	 * Answer a message.
	 *
	 * @param string $message      The visitor's message.
	 * @param string $conversation Stable id so the provider can keep context.
	 * @param array  $context      line_user_id, display_name and similar.
	 * @return string|\WP_Error
	 */
	public function ask( string $message, string $conversation, array $context = array() );
}
