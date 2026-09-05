<?php
/**
 * LINE Pay Online API v3 client.
 *
 * Two things about this API trip people up, and both are handled here:
 *
 * 1. It always answers HTTP 200. Success and failure are distinguished by the
 *    JSON `returnCode`, where "0000" means success. Checking the status code
 *    alone will happily treat a declined payment as a completed one.
 * 2. The signature is computed over the exact bytes of the request body, so
 *    the body must be serialised once and both signed and sent -- re-encoding
 *    between signing and sending changes the signature.
 *
 * v3 is used rather than v4 because v3's signature construction is documented
 * and in wide production use; the v4 string-to-sign is not publicly specified
 * in the same detail.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Pay;

use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class LinePayClient {

	const HOST_SANDBOX    = 'https://sandbox-api-pay.line.me';
	const HOST_PRODUCTION = 'https://api-pay.line.me';
	const SUCCESS_CODE    = '0000';

	/**
	 * The API host for the configured mode.
	 */
	public static function host(): string {
		return Options::get( 'pay_sandbox' ) ? self::HOST_SANDBOX : self::HOST_PRODUCTION;
	}

	/**
	 * Whether credentials are present.
	 */
	public static function is_configured(): bool {
		return '' !== (string) Options::get( 'pay_channel_id' )
			&& '' !== (string) Options::get( 'pay_channel_secret' );
	}

	// --- Operations --------------------------------------------------------------

	/**
	 * Reserve a payment and get the URL to send the customer to.
	 *
	 * @param array $payload Request body per the v3 Request API.
	 * @return array|WP_Error The `info` object on success.
	 */
	public static function request_payment( array $payload ) {
		return self::post( '/v3/payments/request', $payload );
	}

	/**
	 * Capture the authorised payment after the customer returns.
	 *
	 * The amount and currency must match the original request exactly, which
	 * is why the stored payment row is the source of both rather than the
	 * order total at confirm time.
	 *
	 * @param string $transaction_id LINE Pay transaction id.
	 * @param float  $amount         Amount to confirm.
	 * @param string $currency       Currency code.
	 * @return array|WP_Error
	 */
	public static function confirm( string $transaction_id, float $amount, string $currency ) {
		return self::post(
			'/v3/payments/' . rawurlencode( $transaction_id ) . '/confirm',
			array(
				'amount'   => self::format_amount( $amount, $currency ),
				'currency' => $currency,
			)
		);
	}

	/**
	 * Refund all or part of a captured payment.
	 *
	 * @param string     $transaction_id LINE Pay transaction id.
	 * @param float|null $amount         Amount, or null for a full refund.
	 * @param string     $currency       Currency code, used for rounding.
	 * @return array|WP_Error
	 */
	public static function refund( string $transaction_id, ?float $amount = null, string $currency = 'TWD' ) {
		$body = array();

		if ( null !== $amount ) {
			$body['refundAmount'] = self::format_amount( $amount, $currency );
		}

		return self::post( '/v3/payments/' . rawurlencode( $transaction_id ) . '/refund', $body );
	}

	/**
	 * Capture a payment that was authorised but not captured.
	 *
	 * @param string $transaction_id LINE Pay transaction id.
	 * @param float  $amount         Amount to capture.
	 * @param string $currency       Currency code.
	 * @return array|WP_Error
	 */
	public static function capture( string $transaction_id, float $amount, string $currency ) {
		return self::post(
			'/v3/payments/authorizations/' . rawurlencode( $transaction_id ) . '/capture',
			array(
				'amount'   => self::format_amount( $amount, $currency ),
				'currency' => $currency,
			)
		);
	}

	/**
	 * Release an authorisation without capturing it.
	 *
	 * @param string $transaction_id LINE Pay transaction id.
	 * @return array|WP_Error
	 */
	public static function void( string $transaction_id ) {
		return self::post( '/v3/payments/authorizations/' . rawurlencode( $transaction_id ) . '/void', array() );
	}

	/**
	 * Look a payment up by transaction id or order id.
	 *
	 * This is the authoritative check: if the browser never comes back from
	 * LINE Pay, this is how the site finds out whether the customer paid.
	 *
	 * @param string $transaction_id Transaction id, or ''.
	 * @param string $order_id       Merchant order id, or ''.
	 * @return array|WP_Error
	 */
	public static function details( string $transaction_id = '', string $order_id = '' ) {
		$query = array();

		if ( '' !== $transaction_id ) {
			$query['transactionId'] = $transaction_id;
		}

		if ( '' !== $order_id ) {
			$query['orderId'] = $order_id;
		}

		if ( empty( $query ) ) {
			return new WP_Error(
				'moksa_line_pay_no_reference',
				__( 'A transaction id or order id is required.', 'moksa-line' )
			);
		}

		return self::get( '/v3/payments', $query );
	}

	/**
	 * Check whether a reserved payment is ready to be confirmed.
	 *
	 * @param string $transaction_id Transaction id.
	 * @return array|WP_Error
	 */
	public static function check_status( string $transaction_id ) {
		return self::get( '/v3/payments/requests/' . rawurlencode( $transaction_id ) . '/check', array() );
	}

	// --- Transport ----------------------------------------------------------------

	/**
	 * Signed POST.
	 *
	 * @param string $path Endpoint path.
	 * @param array  $body Request body.
	 * @return array|WP_Error
	 */
	private static function post( string $path, array $body ) {
		if ( ! self::is_configured() ) {
			return new WP_Error(
				'moksa_line_pay_unconfigured',
				__( 'LINE Pay is not configured. Add the Channel ID and Channel Secret first.', 'moksa-line' )
			);
		}

		$secret = (string) Options::get( 'pay_channel_secret' );
		$nonce  = wp_generate_uuid4();

		// Serialise once: this exact string is both signed and sent.
		$json = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false === $json ) {
			return new WP_Error( 'moksa_line_pay_encode_failed', __( 'The payment request could not be encoded.', 'moksa-line' ) );
		}

		$signature = base64_encode( hash_hmac( 'sha256', $secret . $path . $json . $nonce, $secret, true ) );

		$response = wp_remote_post(
			self::host() . $path,
			array(
				'timeout' => 30,
				'headers' => self::headers( $nonce, $signature ),
				'body'    => $json,
			)
		);

		return self::interpret( $response, $path );
	}

	/**
	 * Signed GET.
	 *
	 * @param string $path  Endpoint path.
	 * @param array  $query Query parameters.
	 * @return array|WP_Error
	 */
	private static function get( string $path, array $query ) {
		if ( ! self::is_configured() ) {
			return new WP_Error(
				'moksa_line_pay_unconfigured',
				__( 'LINE Pay is not configured. Add the Channel ID and Channel Secret first.', 'moksa-line' )
			);
		}

		$secret = (string) Options::get( 'pay_channel_secret' );
		$nonce  = wp_generate_uuid4();

		// The signed query string and the sent one must be byte-identical, so
		// the URL is assembled from this exact string.
		$query_string = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );

		$signature = base64_encode( hash_hmac( 'sha256', $secret . $path . $query_string . $nonce, $secret, true ) );

		$url = self::host() . $path . ( '' !== $query_string ? '?' . $query_string : '' );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => self::headers( $nonce, $signature ),
			)
		);

		return self::interpret( $response, $path );
	}

	/**
	 * Common headers.
	 *
	 * @param string $nonce     Request nonce.
	 * @param string $signature Computed signature.
	 * @return array<string,string>
	 */
	private static function headers( string $nonce, string $signature ): array {
		return array(
			'Content-Type'                => 'application/json',
			'X-LINE-ChannelId'            => (string) Options::get( 'pay_channel_id' ),
			'X-LINE-Authorization-Nonce'  => $nonce,
			'X-LINE-Authorization'        => $signature,
		);
	}

	/**
	 * Turn a response into data or a WP_Error, using returnCode rather than
	 * the HTTP status, which is always 200.
	 *
	 * @param array|WP_Error $response wp_remote_* result.
	 * @param string         $path     Endpoint path, for logging.
	 * @return array|WP_Error
	 */
	private static function interpret( $response, string $path ) {
		if ( is_wp_error( $response ) ) {
			Logger::capture( $response, 'Could not reach LINE Pay', 'pay' );

			return new WP_Error(
				'moksa_line_pay_unreachable',
				__( 'Could not reach LINE Pay. Please try again.', 'moksa-line' )
			);
		}

		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			Logger::error( 'LINE Pay returned an unreadable response', array( 'path' => $path, 'body' => substr( $raw, 0, 500 ) ), 'pay' );

			return new WP_Error(
				'moksa_line_pay_bad_response',
				__( 'LINE Pay returned a response this site could not read.', 'moksa-line' )
			);
		}

		$code = isset( $data['returnCode'] ) ? (string) $data['returnCode'] : '';

		if ( self::SUCCESS_CODE !== $code ) {
			$message = isset( $data['returnMessage'] ) ? (string) $data['returnMessage'] : '';

			Logger::error(
				'LINE Pay rejected a request',
				array( 'path' => $path, 'returnCode' => $code, 'returnMessage' => $message ),
				'pay'
			);

			return new WP_Error(
				'moksa_line_pay_' . ( '' !== $code ? $code : 'error' ),
				self::explain( $code, $message ),
				array( 'returnCode' => $code, 'body' => $data )
			);
		}

		return isset( $data['info'] ) && is_array( $data['info'] ) ? $data['info'] : $data;
	}

	/**
	 * Turn the return codes merchants actually hit into plain language.
	 *
	 * @param string $code    LINE Pay return code.
	 * @param string $message LINE Pay message.
	 */
	private static function explain( string $code, string $message ): string {
		$known = array(
			'1104' => __( 'This merchant is not registered with LINE Pay. Check the Channel ID and whether you are pointed at sandbox or production.', 'moksa-line' ),
			'1105' => __( 'This merchant cannot use LINE Pay right now. Contact LINE Pay support.', 'moksa-line' ),
			'1106' => __( 'The request headers were not accepted. Check the Channel Secret.', 'moksa-line' ),
			'1124' => __( 'The amount is not valid for this currency.', 'moksa-line' ),
			'1141' => __( 'The payment account status does not allow this operation.', 'moksa-line' ),
			'1145' => __( 'This payment is already being processed.', 'moksa-line' ),
			'1150' => __( 'No such transaction exists.', 'moksa-line' ),
			'1155' => __( 'That transaction id does not belong to this merchant.', 'moksa-line' ),
			'1163' => __( 'This payment has already been refunded, or the refund window has closed.', 'moksa-line' ),
			'1165' => __( 'This payment has already been captured.', 'moksa-line' ),
			'1170' => __( 'The customer has insufficient balance.', 'moksa-line' ),
			'1172' => __( 'A payment for this order id already exists.', 'moksa-line' ),
			'1177' => __( 'The customer has not completed authentication.', 'moksa-line' ),
			'1198' => __( 'LINE Pay is processing a duplicate request. Try again shortly.', 'moksa-line' ),
			'9000' => __( 'LINE Pay had an internal error. Try again shortly.', 'moksa-line' ),
		);

		if ( isset( $known[ $code ] ) ) {
			return $known[ $code ];
		}

		return sprintf(
			/* translators: 1: LINE Pay return code, 2: LINE Pay message. */
			__( 'LINE Pay returned %1$s: %2$s', 'moksa-line' ),
			'' !== $code ? $code : '?',
			'' !== $message ? $message : __( 'no detail given', 'moksa-line' )
		);
	}

	/**
	 * Format an amount for a currency.
	 *
	 * TWD, JPY and KRW have no minor unit, and sending 100.00 for a TWD order
	 * is rejected. Everything else is sent with two decimals.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return int|float
	 */
	public static function format_amount( float $amount, string $currency ) {
		if ( in_array( strtoupper( $currency ), array( 'TWD', 'JPY', 'KRW' ), true ) ) {
			return (int) round( $amount );
		}

		return round( $amount, 2 );
	}
}
