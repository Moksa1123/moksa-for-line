<?php
/**
 * Webhook signature verification.
 *
 * LINE does not publish the source IPs of its webhook senders, so the HMAC is
 * the only thing standing between the endpoint and anyone who knows the URL.
 * It must be computed over the *raw* request body: re-encoding the decoded
 * JSON changes key order and whitespace, and the signature never matches.
 *
 * @package Mofoline
 */

namespace Mofoline\Api;

use Mofoline\Support\Options;

defined( 'ABSPATH' ) || exit;

class Signature {

	/**
	 * Verify the x-line-signature header against the raw body.
	 *
	 * @param string $raw_body  Exact bytes received.
	 * @param string $signature Header value.
	 * @param string $secret    Channel secret; falls back to the configured one.
	 */
	public static function verify( string $raw_body, string $signature, string $secret = '' ): bool {
		if ( '' === $secret ) {
			$secret = self::channel_secret();
		}

		if ( '' === $secret || '' === $signature ) {
			return false;
		}

		$expected = base64_encode( hash_hmac( 'sha256', $raw_body, $secret, true ) );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Sign a body the way LINE would. Used by the self-test tool so an admin
	 * can prove the endpoint works without waiting for a real message.
	 *
	 * @param string $raw_body Body to sign.
	 * @param string $secret   Channel secret; falls back to the configured one.
	 */
	public static function sign( string $raw_body, string $secret = '' ): string {
		if ( '' === $secret ) {
			$secret = self::channel_secret();
		}

		return base64_encode( hash_hmac( 'sha256', $raw_body, $secret, true ) );
	}

	/**
	 * The secret webhooks are signed with.
	 *
	 * Webhooks come from the Messaging API channel. 1.x fell back to the LINE
	 * Login channel secret, which silently fails whenever the two channels are
	 * separate -- the usual setup. The fallback is kept only because existing
	 * installs may have configured just the one field, and the health check
	 * flags it.
	 */
	public static function channel_secret(): string {
		$secret = (string) Options::get( 'messaging_secret' );

		if ( '' !== $secret ) {
			return $secret;
		}

		return (string) Options::get( 'channel_secret' );
	}

	/**
	 * Whether the messaging channel secret is properly configured, as opposed
	 * to leaning on the login channel's.
	 */
	public static function using_fallback_secret(): bool {
		return '' === (string) Options::get( 'messaging_secret' )
			&& '' !== (string) Options::get( 'channel_secret' );
	}
}
