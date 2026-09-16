<?php
/**
 * The webhook endpoint.
 *
 * The response must be fast and unconditional: anything slow here makes LINE
 * retry, and a retry that is not deduplicated sends the customer a second
 * reply. So the endpoint verifies the signature, stores the events, answers
 * 200, and only then -- after the response is flushed -- does the work.
 *
 * @package Mofoline
 */

namespace Mofoline\Webhook;

use Mofoline\Api\Signature;
use Mofoline\Support\Logger;
use Mofoline\Support\Options;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class WebhookModule {

	const NAMESPACE_V1 = 'mofoline/v1';

	/**
	 * Events staged for post-response processing.
	 *
	 * @var bool
	 */
	private $has_work = false;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'mofoline_process_events', array( EventQueue::class, 'drain' ) );
	}

	/**
	 * The URL to paste into the LINE Developers Console.
	 */
	public static function endpoint_url(): string {
		return rest_url( self::NAMESPACE_V1 . '/webhook' );
	}

	/**
	 * The namespace the plugin this one replaces answered under. A LINE
	 * channel that still points at it keeps delivering, so nothing is lost
	 * between installing this plugin and updating the console.
	 */
	const LEGACY_NAMESPACE = 'moksa-line/v1';

	public function register_routes(): void {
		foreach ( array( self::NAMESPACE_V1, self::LEGACY_NAMESPACE ) as $namespace ) {
			register_rest_route(
				$namespace,
				'/webhook',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle' ),
					// The HMAC over the raw body is the authentication: LINE
					// signs every delivery with the channel secret. LINE does
					// not publish sender IPs, so there is nothing else to check.
					'permission_callback' => array( $this, 'verify_delivery' ),
				)
			);
		}
	}

	/**
	 * Whether a delivery carries LINE's signature over its body.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error
	 */
	public function verify_delivery( WP_REST_Request $request ) {
		$raw       = (string) $request->get_body();
		$signature = (string) $request->get_header( 'x-line-signature' );

		if ( Signature::verify( $raw, $signature ) ) {
			return true;
		}

		Logger::warning(
			'Rejected a webhook delivery with a bad signature',
			array( 'length' => strlen( $raw ) ),
			'webhook'
		);

		// 403 rather than 401: there is no credential to re-present.
		return new WP_Error( 'mofoline_bad_signature', 'invalid signature', array( 'status' => 403 ) );
	}

	/**
	 * Receive a webhook delivery that verify_delivery() has already accepted.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$raw       = (string) $request->get_body();
		$signature = (string) $request->get_header( 'x-line-signature' );
		$payload   = json_decode( $raw, true );
		$events  = is_array( $payload ) && ! empty( $payload['events'] ) ? $payload['events'] : array();

		// The Console's "Verify" button sends an empty events array. Answering
		// 200 is what makes that button turn green.
		if ( empty( $events ) ) {
			$this->forward( $raw, $signature );

			return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
		}

		$stored = 0;

		foreach ( $events as $event ) {
			if ( is_array( $event ) && EventQueue::store( $event ) ) {
				++$stored;
			}
		}

		$this->forward( $raw, $signature );

		if ( $stored > 0 ) {
			$this->has_work = true;

			// Primary path: finish the work in this request once the response
			// has gone out, so reply tokens are still fresh.
			add_action( 'shutdown', array( $this, 'drain_after_response' ), 1 );

			// Fallback: if this process is killed before shutdown runs, cron
			// picks the events up. Scheduling is idempotent.
			if ( ! wp_next_scheduled( 'mofoline_process_events' ) ) {
				wp_schedule_single_event( time() + 30, 'mofoline_process_events' );
			}
		}

		return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	/**
	 * Run the queue after the HTTP response has been delivered.
	 */
	public function drain_after_response(): void {
		if ( ! $this->has_work ) {
			return;
		}

		$this->has_work = false;

		// Flush the response so LINE sees its 200 immediately, then keep going.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}

		ignore_user_abort( true );

		EventQueue::drain();
	}

	/**
	 * Relay the untouched delivery to an external automation endpoint (n8n,
	 * Make, a bespoke service). The signature header goes with it so the
	 * receiver can verify the payload itself.
	 *
	 * @param string $raw       Raw request body.
	 * @param string $signature Signature header.
	 */
	private function forward( string $raw, string $signature ): void {
		$url = (string) Options::get( 'webhook_forward_url' );

		if ( '' === $url ) {
			return;
		}

		// The safe variant refuses private and loopback addresses. An n8n on
		// the same machine is a real setup; a site that wants it can allow
		// its host through WordPress's own http_request_host_is_external
		// filter, which is the documented way and keeps the choice explicit.
		wp_safe_remote_post(
			$url,
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array(
					'Content-Type'     => 'application/json',
					'x-line-signature' => $signature,
				),
				'body'     => $raw,
			)
		);
	}
}
