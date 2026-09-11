<?php
/**
 * Typed settings registry.
 *
 * Every option the plugin reads or writes is declared here exactly once, with
 * its type, default and whether it holds a credential. Credentials are stored
 * encrypted (see Crypto) and never returned by the settings AJAX surface.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Support;

defined( 'ABSPATH' ) || exit;

class Options {

	const PREFIX = 'moksa_line_';

	/**
	 * Runtime cache so a single request never hits get_option() twice for the
	 * same key -- the webhook path reads a dozen of these per event.
	 *
	 * @var array<string,mixed>
	 */
	private static $cache = array();

	/**
	 * Setting schema: key => [ type, default, secret ].
	 *
	 * Types: string, text, bool, int, url, json, enum.
	 *
	 * @return array<string,array>
	 */
	public static function schema(): array {
		return array(
			// --- LINE Login channel ---------------------------------------------
			'channel_id'           => array( 'type' => 'string', 'default' => '' ),
			'channel_secret'       => array( 'type' => 'string', 'default' => '', 'secret' => true ),
			'auto_register'        => array( 'type' => 'bool', 'default' => true ),
			'sync_profile'         => array( 'type' => 'bool', 'default' => true ),
			'new_user_role'        => array( 'type' => 'string', 'default' => 'subscriber' ),
			'login_redirect'       => array( 'type' => 'url', 'default' => '' ),
			'bot_prompt'           => array( 'type' => 'enum', 'default' => 'none', 'enum' => array( 'none', 'normal', 'aggressive' ) ),

			// --- Login button appearance ------------------------------------------
			// These keys match the ones the previous plugin used, so a site that
			// had styled its button keeps that styling without touching anything.
			'button_text'          => array( 'type' => 'string', 'default' => '' ),
			'button_bg_color'      => array( 'type' => 'string', 'default' => '#06C755' ),
			'button_text_color'    => array( 'type' => 'string', 'default' => '#FFFFFF' ),
			'button_border_radius' => array( 'type' => 'int', 'default' => 6 ),
			'button_width'         => array( 'type' => 'string', 'default' => '' ),
			'button_height'        => array( 'type' => 'int', 'default' => 0 ),
			'request_email'        => array( 'type' => 'bool', 'default' => false ),
			// Merging by email lets anyone who controls a LINE account with a
			// matching address take over the WordPress account, so it is off
			// unless an administrator deliberately turns it on.
			'link_by_email'        => array( 'type' => 'bool', 'default' => false ),

			// --- Messaging API channel -------------------------------------------
			'messaging_channel_id' => array( 'type' => 'string', 'default' => '' ),
			'messaging_secret'     => array( 'type' => 'string', 'default' => '', 'secret' => true ),
			// Long-lived token: only kept for sites that have not moved to stateless.
			'messaging_token'      => array( 'type' => 'string', 'default' => '', 'secret' => true ),
			'token_mode'           => array( 'type' => 'enum', 'default' => 'stateless', 'enum' => array( 'stateless', 'long_lived' ) ),
			'webhook_forward_url'  => array( 'type' => 'url', 'default' => '' ),
			'greeting_message'     => array( 'type' => 'text', 'default' => '' ),
			'bot_basic_id'         => array( 'type' => 'string', 'default' => '' ),

			// --- LIFF --------------------------------------------------------------
			'liff_id'              => array( 'type' => 'string', 'default' => '' ),
			'liff_chat_id'         => array( 'type' => 'string', 'default' => '' ),
			'liff_pay_id'          => array( 'type' => 'string', 'default' => '' ),

			// --- AI ----------------------------------------------------------------
			'ai_enabled'           => array( 'type' => 'bool', 'default' => false ),
			'ai_provider'          => array( 'type' => 'enum', 'default' => 'core', 'enum' => array( 'core', 'ai_engine', 'none' ) ),
			'ai_bot_id'            => array( 'type' => 'string', 'default' => 'default' ),
			'ai_fallback_only'     => array( 'type' => 'bool', 'default' => true ),
			'ai_max_chars'         => array( 'type' => 'int', 'default' => 1800 ),
			'ai_daily_cap'         => array( 'type' => 'int', 'default' => 300 ),
			'ai_handoff_keyword'   => array( 'type' => 'string', 'default' => '人工客服' ),

			// --- LINE Pay -----------------------------------------------------------
			'pay_enabled'          => array( 'type' => 'bool', 'default' => false ),
			'pay_channel_id'       => array( 'type' => 'string', 'default' => '' ),
			'pay_channel_secret'   => array( 'type' => 'string', 'default' => '', 'secret' => true ),
			'pay_sandbox'          => array( 'type' => 'bool', 'default' => true ),
			'pay_currency'         => array( 'type' => 'enum', 'default' => 'TWD', 'enum' => array( 'TWD', 'JPY', 'USD', 'THB' ) ),
			'pay_capture'          => array( 'type' => 'bool', 'default' => true ),

			// --- WooCommerce ----------------------------------------------------------
			'woo_notify'           => array( 'type' => 'bool', 'default' => false ),
			// Which order statuses trigger a LINE message, as a JSON array of
			// status slugs without the wc- prefix.
			'woo_notify_statuses'  => array( 'type' => 'json', 'default' => array( 'processing', 'completed' ) ),
			'woo_login_buttons'    => array( 'type' => 'bool', 'default' => true ),
			'woo_account_tab'      => array( 'type' => 'bool', 'default' => true ),
			'member_card'          => array( 'type' => 'bool', 'default' => true ),
			// Seconds to wait before notifying, so a status set by an
			// automation has settled before the customer hears about it.
			'woo_notify_delay'     => array( 'type' => 'int', 'default' => 0 ),
			// Shipping notifications are worth delaying until the logistics
			// plugin has written the tracking number, which it usually does a
			// moment after the status changes.
			'woo_wait_for_tracking' => array( 'type' => 'bool', 'default' => true ),
			'woo_tracking_status'  => array( 'type' => 'string', 'default' => 'processing' ),
			'woo_tracking_delay'   => array( 'type' => 'int', 'default' => 60 ),
			'woo_tracking_retries' => array( 'type' => 'int', 'default' => 3 ),
			'woo_history_days'     => array( 'type' => 'int', 'default' => 180 ),

			// --- Inbox ---------------------------------------------------------------
			'inbox_enabled'        => array( 'type' => 'bool', 'default' => true ),
			'inbox_retention_days' => array( 'type' => 'int', 'default' => 180 ),

			// --- Housekeeping ---------------------------------------------------------
			// Warning, not error: the failures that actually strand an
			// administrator during setup -- a rejected webhook signature, a
			// profile that cannot be fetched, a rate limit -- are warnings.
			// Defaulting to error hid exactly the messages worth reading.
			'log_level'            => array( 'type' => 'enum', 'default' => 'warning', 'enum' => array( 'debug', 'info', 'warning', 'error', 'off' ) ),
			'log_retention_days'   => array( 'type' => 'int', 'default' => 30 ),
			'db_version'           => array( 'type' => 'string', 'default' => '0' ),
		);
	}

	/**
	 * Read a setting, decrypting it when it is a credential.
	 *
	 * @param string $key      Key without the moksa_line_ prefix.
	 * @param mixed  $fallback Overrides the schema default when provided.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		if ( array_key_exists( $key, self::$cache ) ) {
			return self::$cache[ $key ];
		}

		$schema  = self::schema();
		$spec    = isset( $schema[ $key ] ) ? $schema[ $key ] : array( 'type' => 'string', 'default' => '' );
		$default = null !== $fallback ? $fallback : $spec['default'];

		$raw = get_option( self::PREFIX . $key, null );

		if ( null === $raw || '' === $raw ) {
			self::$cache[ $key ] = $default;
			return $default;
		}

		if ( ! empty( $spec['secret'] ) ) {
			$raw = Crypto::decrypt( (string) $raw );
		}

		$value               = self::cast( $raw, $spec );
		self::$cache[ $key ] = $value;

		return $value;
	}

	/**
	 * Write a setting, encrypting it when it is a credential.
	 *
	 * @param string $key   Key without the prefix.
	 * @param mixed  $value Raw value.
	 */
	public static function set( string $key, $value ): bool {
		$schema = self::schema();
		$spec   = isset( $schema[ $key ] ) ? $schema[ $key ] : array( 'type' => 'string', 'default' => '' );

		$clean = self::sanitize( $value, $spec );

		unset( self::$cache[ $key ] );

		$stored = ! empty( $spec['secret'] ) && is_string( $clean ) && '' !== $clean
			? Crypto::encrypt( $clean )
			: $clean;

		return (bool) update_option( self::PREFIX . $key, $stored, false );
	}

	/**
	 * Delete a setting.
	 */
	public static function delete( string $key ): bool {
		unset( self::$cache[ $key ] );
		return (bool) delete_option( self::PREFIX . $key );
	}

	/**
	 * Whether a credential is present, without revealing it.
	 */
	public static function has_secret( string $key ): bool {
		return '' !== (string) self::get( $key );
	}

	/**
	 * Masked representation for admin screens: never echo a real secret.
	 */
	public static function mask( string $key ): string {
		$value = (string) self::get( $key );

		if ( '' === $value ) {
			return '';
		}

		return str_repeat( "\xE2\x80\xA2", 8 ) . substr( $value, -4 );
	}

	/**
	 * Drop the request cache (used by tests and after bulk imports).
	 */
	public static function flush_cache(): void {
		self::$cache = array();
	}

	/**
	 * Coerce a stored value to its declared type.
	 *
	 * @param mixed $raw  Stored value.
	 * @param array $spec Schema entry.
	 * @return mixed
	 */
	private static function cast( $raw, array $spec ) {
		switch ( $spec['type'] ) {
			case 'bool':
				return in_array( $raw, array( true, 1, '1', 'yes', 'true', 'on' ), true );
			case 'int':
				return (int) $raw;
			case 'json':
				$decoded = json_decode( (string) $raw, true );
				return is_array( $decoded ) ? $decoded : $spec['default'];
			case 'enum':
				$allowed = isset( $spec['enum'] ) ? $spec['enum'] : array();
				return in_array( $raw, $allowed, true ) ? $raw : $spec['default'];
			default:
				return (string) $raw;
		}
	}

	/**
	 * Sanitize an incoming value before it is stored.
	 *
	 * @param mixed $value Incoming value.
	 * @param array $spec  Schema entry.
	 * @return mixed
	 */
	private static function sanitize( $value, array $spec ) {
		switch ( $spec['type'] ) {
			case 'bool':
				return in_array( $value, array( true, 1, '1', 'yes', 'true', 'on' ), true ) ? '1' : '0';
			case 'int':
				return (int) $value;
			case 'url':
				return esc_url_raw( trim( (string) $value ) );
			case 'text':
				return sanitize_textarea_field( (string) $value );
			case 'json':
				return is_array( $value ) ? wp_json_encode( $value ) : (string) $value;
			case 'enum':
				$allowed = isset( $spec['enum'] ) ? $spec['enum'] : array();
				$value   = sanitize_text_field( (string) $value );
				return in_array( $value, $allowed, true ) ? $value : $spec['default'];
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
