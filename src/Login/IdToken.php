<?php
/**
 * ID token handling.
 *
 * 1.x called /v2/profile with the access token and trusted whatever came back.
 * That is enough to read a profile, but it is not an authentication proof: the
 * value that may be bound to a WordPress account is the `sub` claim of a
 * verified ID token. Verification is delegated to LINE's own endpoint, with a
 * local claim check as a second gate.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Login;

use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class IdToken {

	const VERIFY_ENDPOINT = 'https://api.line.me/oauth2/v2.1/verify';
	const ISSUER          = 'https://access.line.me';
	/** Tolerance for clock drift between this server and LINE, in seconds. */
	const LEEWAY = 300;

	/**
	 * Verify an ID token and return its claims.
	 *
	 * @param string $id_token Raw JWT from the token response.
	 * @param string $nonce    The nonce sent on the authorize request.
	 * @return array|WP_Error Claims on success.
	 */
	public static function verify( string $id_token, string $nonce ) {
		$channel_id = (string) Options::get( 'channel_id' );

		if ( '' === $id_token ) {
			return new WP_Error(
				'moksa_line_no_id_token',
				__( 'LINE did not return an ID token. Make sure the openid scope is enabled for this channel.', 'moksa-line-login' )
			);
		}

		$response = wp_remote_post(
			self::VERIFY_ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'id_token'  => $id_token,
					'client_id' => $channel_id,
					'nonce'     => $nonce,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::capture( $response, 'Could not reach the ID token verification endpoint', 'login' );

			return new WP_Error(
				'moksa_line_verify_unreachable',
				__( 'Could not reach LINE to verify the login. Please try again.', 'moksa-line-login' )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$claims = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || ! is_array( $claims ) ) {
			$detail = is_array( $claims ) && isset( $claims['error_description'] )
				? (string) $claims['error_description']
				: 'HTTP ' . $status;

			Logger::error( 'ID token rejected by LINE', array( 'detail' => $detail ), 'login' );

			return new WP_Error(
				'moksa_line_invalid_id_token',
				__( 'This login could not be verified. Please try again.', 'moksa-line-login' )
			);
		}

		// LINE verified the signature; check the claims that bind the token to
		// *this* login attempt rather than a replayed one.
		$problem = self::check_claims( $claims, $channel_id, $nonce );

		if ( $problem ) {
			Logger::error( 'ID token claims failed local checks', array( 'detail' => $problem ), 'login' );

			return new WP_Error( 'moksa_line_bad_claims', $problem );
		}

		return $claims;
	}

	/**
	 * Local claim checks, run after LINE has verified the signature.
	 *
	 * @param array  $claims     Decoded claims.
	 * @param string $channel_id Expected audience.
	 * @param string $nonce      Expected nonce.
	 * @return string Empty when the claims are acceptable, otherwise the reason.
	 */
	private static function check_claims( array $claims, string $channel_id, string $nonce ): string {
		if ( empty( $claims['sub'] ) ) {
			return __( 'The login response did not identify a user.', 'moksa-line-login' );
		}

		if ( ! isset( $claims['iss'] ) || self::ISSUER !== $claims['iss'] ) {
			return __( 'The login response came from an unexpected issuer.', 'moksa-line-login' );
		}

		$audience = isset( $claims['aud'] ) ? (array) $claims['aud'] : array();

		if ( '' !== $channel_id && ! in_array( $channel_id, $audience, true ) ) {
			return __( 'The login response was issued for a different channel.', 'moksa-line-login' );
		}

		if ( isset( $claims['exp'] ) && ( (int) $claims['exp'] + self::LEEWAY ) < time() ) {
			return __( 'The login response has expired. Please try again.', 'moksa-line-login' );
		}

		// A missing nonce in the response when one was requested means the
		// token could be replayed from an earlier session.
		if ( '' !== $nonce ) {
			$returned = isset( $claims['nonce'] ) ? (string) $claims['nonce'] : '';

			if ( ! hash_equals( $nonce, $returned ) ) {
				return __( 'The login response did not match this login attempt.', 'moksa-line-login' );
			}
		}

		return '';
	}
}
