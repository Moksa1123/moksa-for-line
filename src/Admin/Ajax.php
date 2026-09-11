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
 * The reading helpers exist for the same reason. $_POST is read in forty-odd
 * places, and the only thing that matters at each is that the value is
 * unslashed before it is sanitised -- which is easy to get the wrong way round
 * once, somewhere, and never notice.
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

	/**
	 * A whole number from the request.
	 *
	 * @param string $key      Field name.
	 * @param int    $fallback Value when the field is absent.
	 */
	public static function int( string $key, int $fallback = 0 ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() runs first.
		return isset( $_POST[ $key ] ) ? (int) $_POST[ $key ] : $fallback;
	}

	/**
	 * A line of text from the request: unslashed, then sanitised.
	 *
	 * @param string $key      Field name.
	 * @param string $fallback Value when the field is absent.
	 */
	public static function text( string $key, string $fallback = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() runs first.
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() runs first.
		return sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
	}

	/**
	 * A slug from the request -- lowercase, no spaces.
	 *
	 * @param string $key      Field name.
	 * @param string $fallback Value when the field is absent.
	 */
	public static function key( string $key, string $fallback = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() runs first.
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() runs first.
		return sanitize_key( wp_unslash( (string) $_POST[ $key ] ) );
	}

	/**
	 * A checkbox or flag from the request.
	 *
	 * @param string $key Field name.
	 */
	public static function flag( string $key ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() runs first.
		return isset( $_POST[ $key ] ) && in_array( (string) $_POST[ $key ], array( '1', 'true', 'on', 'yes' ), true );
	}

	/**
	 * A field that carries JSON or markup, unslashed but not sanitised.
	 *
	 * Sanitising here would corrupt what the caller is about to parse, so this
	 * is deliberately the raw value and every caller has to say what it is.
	 *
	 * @param string $key Field name.
	 */
	public static function payload( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() runs first.
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the caller parses it.
		return (string) wp_unslash( (string) $_POST[ $key ] );
	}
}
