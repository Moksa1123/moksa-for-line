<?php
/**
 * Channel access token supply.
 *
 * 1.x stored a long-lived token in wp_options in the clear. Long-lived tokens
 * never expire and there is only one per channel, so a leak is unrecoverable
 * without rotating the channel. The default here is the stateless token
 * (POST /oauth2/v3/token, 15 minutes, no issuance cap), cached in a transient
 * and re-minted on demand.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Api;

use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class TokenManager {

	const ENDPOINT      = 'https://api.line.me/oauth2/v3/token';
	const CACHE_KEY     = 'moksa_line_stateless_token';
	/** Refresh a minute before LINE expires it, to survive clock skew. */
	const SAFETY_MARGIN = 60;

	/**
	 * Current channel access token.
	 *
	 * @return string|WP_Error
	 */
	public static function get() {
		if ( 'long_lived' === Options::get( 'token_mode' ) ) {
			$token = (string) Options::get( 'messaging_token' );

			if ( '' === $token ) {
				return new WP_Error(
					'moksa_line_no_token',
					__( 'No channel access token is configured. Add one under LINE > Settings > Messaging API.', 'moksa-line-login' )
				);
			}

			return $token;
		}

		$cached = get_transient( self::CACHE_KEY );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		return self::issue();
	}

	/**
	 * Mint a fresh stateless token.
	 *
	 * @return string|WP_Error
	 */
	public static function issue() {
		$channel_id = (string) Options::get( 'messaging_channel_id' );
		$secret     = (string) Options::get( 'messaging_secret' );

		if ( '' === $channel_id || '' === $secret ) {
			return new WP_Error(
				'moksa_line_no_credentials',
				__( 'Messaging API Channel ID and Channel Secret are required to issue a token.', 'moksa-line-login' )
			);
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $channel_id,
					'client_secret' => $secret,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::capture( $response, 'Could not reach the LINE token endpoint', 'token' );
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || empty( $body['access_token'] ) ) {
			$message = isset( $body['error_description'] ) ? (string) $body['error_description'] : 'HTTP ' . $status;

			Logger::error(
				'Stateless token request rejected',
				array( 'status' => $status, 'detail' => $message ),
				'token'
			);

			return new WP_Error( 'moksa_line_token_failed', $message );
		}

		$token   = (string) $body['access_token'];
		$expires = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 900;

		set_transient( self::CACHE_KEY, $token, max( 60, $expires - self::SAFETY_MARGIN ) );

		return $token;
	}

	/**
	 * Drop the cached token. Called when the API answers 401 so the next call
	 * mints a fresh one instead of looping on a stale credential.
	 */
	public static function forget(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Whether the plugin has everything it needs to talk to the Messaging API.
	 */
	public static function is_configured(): bool {
		if ( 'long_lived' === Options::get( 'token_mode' ) ) {
			return '' !== (string) Options::get( 'messaging_token' );
		}

		return '' !== (string) Options::get( 'messaging_channel_id' )
			&& '' !== (string) Options::get( 'messaging_secret' );
	}
}
