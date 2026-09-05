<?php
/**
 * Shared HTTP client for every LINE Messaging API call.
 *
 * Handles the three things 1.x got wrong: it picks the right host (content
 * endpoints live on api-data.line.me, and calling them on api.line.me 404s),
 * it turns LINE's error envelope into a real WP_Error instead of guessing from
 * the presence of a "message" key, and it retries once on 401 with a fresh
 * token rather than failing the user's action.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Api;

use Moksa\Line\Support\Logger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class Client {

	const API_HOST  = 'https://api.line.me';
	const DATA_HOST = 'https://api-data.line.me';

	/**
	 * Endpoint path fragments that must be sent to api-data.line.me.
	 *
	 * @var string[]
	 */
	private static $data_paths = array(
		'/content',
		'/richmenu/',
		'/audienceGroup/upload/byFile',
	);

	/**
	 * Perform a JSON request.
	 *
	 * @param string     $method    HTTP verb.
	 * @param string     $path      Path below /v2/bot, e.g. '/message/push'.
	 * @param array|null $body      Decoded body; encoded as JSON when present.
	 * @param array      $options   retry_key (string), timeout (int), raw_path (bool).
	 * @return array|WP_Error Decoded response body (empty array for 200 with no body).
	 */
	public static function request( string $method, string $path, ?array $body = null, array $options = array() ) {
		$token = TokenManager::get();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$result = self::dispatch( $method, $path, $body, $options, $token );

		// A 401 means the cached token died early (revoked, or the channel
		// secret was rotated). Mint a new one and try exactly once more.
		if ( is_wp_error( $result ) && 'moksa_line_unauthorized' === $result->get_error_code() ) {
			TokenManager::forget();
			$token = TokenManager::get();

			if ( is_wp_error( $token ) ) {
				return $token;
			}

			$result = self::dispatch( $method, $path, $body, $options, $token );
		}

		return $result;
	}

	/**
	 * Single HTTP attempt.
	 *
	 * @param string     $method  HTTP verb.
	 * @param string     $path    Endpoint path.
	 * @param array|null $body    Request body.
	 * @param array      $options Request options.
	 * @param string     $token   Bearer token.
	 * @return array|WP_Error
	 */
	private static function dispatch( string $method, string $path, ?array $body, array $options, string $token ) {
		$url = self::url( $path, ! empty( $options['raw_path'] ) );

		$headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json; charset=utf-8',
		);

		// LINE deduplicates by this key for 24 hours, which is what makes a
		// webhook retry safe to replay: the same order never gets two pushes.
		if ( ! empty( $options['retry_key'] ) ) {
			$headers['X-Line-Retry-Key'] = (string) $options['retry_key'];
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => isset( $options['timeout'] ) ? (int) $options['timeout'] : 20,
			'headers' => $headers,
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::error(
				'LINE request failed at the transport layer',
				array( 'path' => $path, 'detail' => $response->get_error_message() ),
				'api'
			);

			return $response;
		}

		return self::interpret( $response, $path );
	}

	/**
	 * Turn a raw response into decoded data or a WP_Error.
	 *
	 * @param array  $response wp_remote_* response.
	 * @param string $path     Endpoint path, for logging.
	 * @return array|WP_Error
	 */
	private static function interpret( array $response, string $path ) {
		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( $status >= 200 && $status < 300 ) {
			return $data;
		}

		$message = isset( $data['message'] ) ? (string) $data['message'] : 'HTTP ' . $status;

		// LINE puts per-field validation errors in details[]; surfacing them is
		// the difference between "400 Bad Request" and "body.contents[0].weight
		// must be regular or bold".
		if ( ! empty( $data['details'] ) && is_array( $data['details'] ) ) {
			$parts = array();

			foreach ( $data['details'] as $detail ) {
				$parts[] = trim(
					( isset( $detail['property'] ) ? $detail['property'] . ': ' : '' )
					. ( isset( $detail['message'] ) ? $detail['message'] : '' )
				);
			}

			$message .= ' (' . implode( '; ', array_filter( $parts ) ) . ')';
		}

		$code = 'moksa_line_api_error';

		if ( 401 === $status ) {
			$code = 'moksa_line_unauthorized';
		} elseif ( 429 === $status ) {
			$code    = 'moksa_line_rate_limited';
			$message = __( 'LINE is rate limiting this channel. Try again shortly.', 'moksa-line' );
		} elseif ( 403 === $status ) {
			$code    = 'moksa_line_forbidden';
			$message = $message . ' ' . __( '(Check the channel plan and that the feature is enabled for this channel.)', 'moksa-line' );
		}

		Logger::error(
			'LINE API returned an error',
			array( 'path' => $path, 'status' => $status, 'detail' => $message ),
			'api'
		);

		return new WP_Error( $code, $message, array( 'status' => $status, 'body' => $data ) );
	}

	/**
	 * Send raw bytes (rich menu images, and nothing else so far).
	 *
	 * @param string $path         Path below /v2/bot.
	 * @param string $bytes        Binary payload.
	 * @param string $content_type image/jpeg or image/png.
	 * @return array|WP_Error
	 */
	public static function upload( string $path, string $bytes, string $content_type ) {
		$token = TokenManager::get();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_post(
			self::DATA_HOST . '/v2/bot' . $path,
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => $content_type,
				),
				'body'    => $bytes,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return self::interpret( $response, $path );
	}

	/**
	 * Fetch binary content (a user's uploaded image, video or audio).
	 *
	 * @param string $message_id LINE message id.
	 * @return array{body:string,mime:string}|WP_Error
	 */
	public static function download_content( string $message_id ) {
		$token = TokenManager::get();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_get(
			self::DATA_HOST . '/v2/bot/message/' . rawurlencode( $message_id ) . '/content',
			array(
				'timeout' => 45,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			return new WP_Error(
				'moksa_line_content_failed',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Could not download the message content (HTTP %d).', 'moksa-line' ),
					$status
				)
			);
		}

		return array(
			'body' => (string) wp_remote_retrieve_body( $response ),
			'mime' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
		);
	}

	/**
	 * Resolve the full URL, choosing api.line.me or api-data.line.me.
	 *
	 * @param string $path     Endpoint path.
	 * @param bool   $raw_path When true the path is used as-is (no /v2/bot prefix).
	 */
	private static function url( string $path, bool $raw_path = false ): string {
		$host = self::API_HOST;

		foreach ( self::$data_paths as $fragment ) {
			// Only richmenu *content* lives on the data host; the CRUD calls do not.
			if ( '/richmenu/' === $fragment ) {
				if ( false !== strpos( $path, '/richmenu/' ) && false !== strpos( $path, '/content' ) ) {
					$host = self::DATA_HOST;
					break;
				}

				continue;
			}

			if ( false !== strpos( $path, $fragment ) ) {
				$host = self::DATA_HOST;
				break;
			}
		}

		return $raw_path ? $host . $path : $host . '/v2/bot' . $path;
	}
}
