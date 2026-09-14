<?php
/**
 * The things every admin AJAX handler does.
 *
 * Fifteen handlers checked the same nonce against the same capability, five of
 * them through a private guard() copied word for word; fourteen forwarded a
 * WP_Error to the browser with the same three lines. All of it was correct and
 * none of it was interesting, which is the kind of code that quietly drifts:
 * one handler gets a fix and the other fourteen do not.
 *
 * The reading helpers exist for the same reason. The request is read in
 * sixty-odd places, and the only thing that matters at each is that the value
 * is sanitised for what it is about to be used as -- which is easy to get
 * wrong once, somewhere, and never notice.
 *
 * Every reader goes through filter_input(). That reads the request as the web
 * server delivered it, before WordPress added slashes to the superglobals, so
 * there is nothing to unslash and no way to forget to.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Admin;

use Moksa\Line\Support\Logger;

defined( 'ABSPATH' ) || exit;

final class Ajax {

	/** The nonce action every admin screen signs its requests with. */
	const NONCE = 'moksa_line_admin';

	/**
	 * Stop unless this is a signed request from somebody allowed to make it.
	 *
	 * Sends a 403 and exits when it is not, so a caller can treat everything
	 * after this line as trusted.
	 *
	 * @param string $refused    What to tell somebody who may not do this.
	 * @param string $capability Capability to require.
	 */
	public static function guard( string $refused, string $capability = 'manage_options' ): void {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => $refused ), 403 );
		}
	}

	/**
	 * Forward a WP_Error to the browser and stop; do nothing otherwise.
	 *
	 * Passing a context logs it as well. That used to be a separate decision at
	 * each call site, which is why thirteen of the fourteen never logged.
	 *
	 * @param mixed  $result  Whatever an API call returned.
	 * @param string $context What was being attempted, for the log.
	 * @param string $channel Log channel.
	 */
	public static function bail( $result, string $context = '', string $channel = 'admin' ): void {
		if ( ! is_wp_error( $result ) ) {
			return;
		}

		if ( '' !== $context ) {
			Logger::capture( $result, $context, $channel );
		}

		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	// --- The posted form ---------------------------------------------------------
	//
	// Every one of these expects guard() -- or check_admin_referer() on a
	// classic form -- to have run first. They read; they do not authorise.

	/**
	 * A whole number from the request.
	 *
	 * @param string $key      Field name.
	 * @param int    $fallback Value when the field is absent.
	 */
	public static function int( string $key, int $fallback = 0 ): int {
		$value = self::scalar( INPUT_POST, $key );

		return null === $value ? $fallback : (int) $value;
	}

	/**
	 * A decimal number from the request.
	 *
	 * @param string $key      Field name.
	 * @param float  $fallback Value when the field is absent.
	 */
	public static function number( string $key, float $fallback = 0.0 ): float {
		$value = self::scalar( INPUT_POST, $key );

		return null === $value ? $fallback : (float) $value;
	}

	/**
	 * A line of text from the request.
	 *
	 * @param string $key      Field name.
	 * @param string $fallback Value when the field is absent.
	 */
	public static function text( string $key, string $fallback = '' ): string {
		$value = self::scalar( INPUT_POST, $key );

		return null === $value ? $fallback : sanitize_text_field( $value );
	}

	/**
	 * A paragraph or more from the request: line breaks kept, tags not.
	 *
	 * @param string $key      Field name.
	 * @param string $fallback Value when the field is absent.
	 */
	public static function textarea( string $key, string $fallback = '' ): string {
		$value = self::scalar( INPUT_POST, $key );

		return null === $value ? $fallback : sanitize_textarea_field( $value );
	}

	/**
	 * A slug from the request -- lowercase, no spaces.
	 *
	 * @param string $key      Field name.
	 * @param string $fallback Value when the field is absent.
	 */
	public static function key( string $key, string $fallback = '' ): string {
		$value = self::scalar( INPUT_POST, $key );

		return null === $value ? $fallback : sanitize_key( $value );
	}

	/**
	 * A checkbox or flag from the request.
	 *
	 * Absent, empty and "0" are off; anything else is on. A browser posts a
	 * checked box as "1" or "on" and this reads both.
	 *
	 * @param string $key Field name.
	 */
	public static function flag( string $key ): bool {
		$value = self::scalar( INPUT_POST, $key );

		return null !== $value && '' !== $value && '0' !== $value;
	}

	/**
	 * Whether a field holds exactly this value.
	 *
	 * @param string $key   Field name.
	 * @param string $value Value to compare with, strictly.
	 */
	public static function is( string $key, string $value ): bool {
		return $value === self::scalar( INPUT_POST, $key );
	}

	/**
	 * An email address from the request.
	 *
	 * @param string $key Field name.
	 */
	public static function email( string $key ): string {
		$value = self::scalar( INPUT_POST, $key );

		return null === $value ? '' : sanitize_email( $value );
	}

	/**
	 * A list of ids from the request.
	 *
	 * @param string $key Field name.
	 * @return int[]
	 */
	public static function ints( string $key ): array {
		return array_map( 'intval', self::flat( INPUT_POST, $key ) );
	}

	/**
	 * A list of slugs from the request.
	 *
	 * @param string $key Field name.
	 * @return string[]
	 */
	public static function keys( string $key ): array {
		return array_values( array_filter( array_map( 'sanitize_key', self::flat( INPUT_POST, $key ) ) ) );
	}

	/**
	 * A list of short texts from the request.
	 *
	 * @param string $key Field name.
	 * @return string[]
	 */
	public static function texts( string $key ): array {
		return array_map( 'sanitize_text_field', self::flat( INPUT_POST, $key ) );
	}

	/**
	 * A whole form from the request, every leaf a plain text field.
	 *
	 * For the settings screens, where each field is then checked again against
	 * its declared type. Nested arrays are kept; keys are kept as posted.
	 *
	 * @param string $key Field name.
	 * @return array
	 */
	public static function fields( string $key ): array {
		$form = filter_input( INPUT_POST, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		return is_array( $form ) ? map_deep( $form, 'sanitize_text_field' ) : array();
	}

	/**
	 * A whole form, each field sanitised for what its own key says it is.
	 *
	 * For the settings screen, where the schema knows a field is a URL or a
	 * paragraph and the generic text cleaner would damage both -- it drops
	 * percent-encoded characters from a URL and the line breaks from a
	 * message.
	 *
	 * @param string   $key  Field name.
	 * @param callable $kind Given a field's key, returns 'text', 'textarea' or 'url'.
	 * @return array<string,string> Field key => clean value; nested values are dropped.
	 */
	public static function fields_typed( string $key, callable $kind ): array {
		$form = filter_input( INPUT_POST, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		if ( ! is_array( $form ) ) {
			return array();
		}

		$clean = array();

		foreach ( $form as $field => $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			$field = sanitize_key( (string) $field );

			switch ( $kind( $field ) ) {
				case 'url':
					$clean[ $field ] = esc_url_raw( $value );
					break;
				case 'textarea':
					$clean[ $field ] = sanitize_textarea_field( $value );
					break;
				default:
					$clean[ $field ] = sanitize_text_field( $value );
			}
		}

		return $clean;
	}

	/**
	 * A field that carries JSON, decoded.
	 *
	 * Sanitising the string before decoding would corrupt it, so the JSON is
	 * parsed first and every string inside is then cleaned as text. The caller
	 * gets an array or nothing.
	 *
	 * @param string $key Field name.
	 * @return array|null Null when absent or not valid JSON.
	 */
	public static function json( string $key ): ?array {
		$raw = self::scalar( INPUT_POST, $key );

		if ( null === $raw || '' === $raw ) {
			return null;
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? map_deep( $decoded, 'sanitize_text_field' ) : null;
	}

	/**
	 * A field that carries JSON, decoded, with strings left as sent.
	 *
	 * For Flex and template definitions, where a string may legitimately hold
	 * a newline or a leading space that sanitize_text_field() would remove,
	 * and where the JSON goes to LINE's API rather than to a browser. Every
	 * string is still checked to be valid UTF-8 without control characters.
	 *
	 * @param string $key Field name.
	 * @return array|null Null when absent or not valid JSON.
	 */
	public static function json_verbatim( string $key ): ?array {
		$raw = self::scalar( INPUT_POST, $key );

		if ( null === $raw || '' === $raw ) {
			return null;
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? map_deep( $decoded, array( __CLASS__, 'clean_string' ) ) : null;
	}

	/**
	 * Keep a string's content; drop what cannot be text.
	 *
	 * @param mixed $value One leaf of a decoded document.
	 * @return mixed
	 */
	public static function clean_string( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$value = wp_check_invalid_utf8( $value );

		return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
	}

	// --- The query string ------------------------------------------------------------
	//
	// These read the query string on admin screens that only list and filter.
	// Nothing changes as a result of them, so there is no nonce to check; the
	// capability check on the screen is what matters, and that is done by
	// whoever registered the screen.

	/**
	 * A line of text from the query string.
	 *
	 * @param string $key      Parameter name.
	 * @param string $fallback Value when absent.
	 */
	public static function query_text( string $key, string $fallback = '' ): string {
		$value = self::scalar( INPUT_GET, $key );

		return null === $value ? $fallback : sanitize_text_field( $value );
	}

	/**
	 * A slug from the query string.
	 *
	 * @param string $key      Parameter name.
	 * @param string $fallback Value when absent.
	 */
	public static function query_key( string $key, string $fallback = '' ): string {
		$value = self::scalar( INPUT_GET, $key );

		return null === $value ? $fallback : sanitize_key( $value );
	}

	/**
	 * A whole number from the query string.
	 *
	 * @param string $key      Parameter name.
	 * @param int    $fallback Value when absent.
	 */
	public static function query_int( string $key, int $fallback = 0 ): int {
		$value = self::scalar( INPUT_GET, $key );

		return null === $value ? $fallback : (int) $value;
	}

	/**
	 * A page number from the query string: never below one.
	 *
	 * @param string $key Parameter name.
	 */
	public static function query_page( string $key = 'paged' ): int {
		return max( 1, self::query_int( $key, 1 ) );
	}

	/**
	 * A URL from the query string, or nothing when it is not one.
	 *
	 * @param string $key Parameter name.
	 */
	public static function query_url( string $key ): string {
		$value = self::scalar( INPUT_GET, $key );

		return null === $value ? '' : esc_url_raw( $value );
	}

	/**
	 * Whether a query-string parameter is present at all.
	 *
	 * @param string $key Parameter name.
	 */
	public static function query_has( string $key ): bool {
		// Absent is null; anything present -- a string, or false for an
		// array that the default filter would not take -- is not.
		return null !== filter_input( INPUT_GET, $key );
	}

	/**
	 * Whether a query-string parameter is present and not empty.
	 *
	 * @param string $key Parameter name.
	 */
	public static function query_flag( string $key ): bool {
		$value = self::scalar( INPUT_GET, $key );

		return null !== $value && '' !== $value && '0' !== $value;
	}

	// --- Reading -----------------------------------------------------------------

	/**
	 * One scalar from the request, or null when absent or not a scalar.
	 *
	 * @param int    $source INPUT_POST or INPUT_GET.
	 * @param string $key    Field name.
	 */
	private static function scalar( int $source, string $key ): ?string {
		$value = filter_input( $source, $key );

		return is_string( $value ) ? $value : null;
	}

	/**
	 * A one-dimensional list from the request.
	 *
	 * A single value posted under the name is a list of one, which is what
	 * (array) $_POST[ $key ] used to give. Nested arrays are flattened out
	 * to their scalar leaves.
	 *
	 * @param int    $source INPUT_POST or INPUT_GET.
	 * @param string $key    Field name.
	 * @return string[]
	 */
	private static function flat( int $source, string $key ): array {
		$value = filter_input( $source, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		if ( ! is_array( $value ) ) {
			$one = self::scalar( $source, $key );

			return null === $one ? array() : array( $one );
		}

		$out = array();

		array_walk_recursive(
			$value,
			static function ( $leaf ) use ( &$out ) {
				if ( is_string( $leaf ) ) {
					$out[] = $leaf;
				}
			}
		);

		return $out;
	}
}
