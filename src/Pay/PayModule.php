<?php
/**
 * LINE Pay wiring: WooCommerce gateway, standalone payment links, and the
 * return endpoints both share.
 *
 * The return endpoint is the only place a payment is completed, and it is
 * written to be safe when the customer double-clicks, refreshes, or never
 * comes back at all -- in which case the reconciliation sweep finishes the
 * job from LINE Pay's own record.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Pay;

use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class PayModule {

	const NAMESPACE_V1 = 'moksa-line/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'void_on_cancel' ) );

		add_action( 'wp_ajax_moksa_line_pay_link', array( $this, 'ajax_create_link' ) );

		add_action( 'moksa_line_reconcile_payments', array( $this, 'reconcile' ) );

		if ( ! wp_next_scheduled( 'moksa_line_reconcile_payments' ) ) {
			wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'hourly', 'moksa_line_reconcile_payments' );
		}
	}

	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @param array $gateways Registered gateways.
	 * @return array
	 */
	public function register_gateway( $gateways ) {
		if ( ! class_exists( '\WC_Payment_Gateway' ) ) {
			return $gateways;
		}

		$gateways[] = WooGateway::class;

		return $gateways;
	}

	// --- Return endpoints ---------------------------------------------------------

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/pay/return',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'handle_return' ),
				// The order reference is unguessable and the payment is
				// verified against LINE Pay, so no site session is required --
				// which matters, because the customer may return inside the
				// LINE in-app browser without cookies.
				'permission_callback' => '__return_true',
				'args'                => array(
					'order_ref' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/pay/cancel',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'handle_cancel' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'order_ref' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);
	}

	/**
	 * The customer came back from LINE Pay.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle_return( WP_REST_Request $request ) {
		$order_ref      = (string) $request->get_param( 'order_ref' );
		$transaction_id = (string) $request->get_param( 'transactionId' );

		$payment = Payments::by_order_ref( $order_ref );

		if ( ! $payment ) {
			return $this->redirect( home_url(), __( 'That payment could not be found.', 'moksa-line' ) );
		}

		$result = self::complete( (int) $payment->id, $transaction_id );

		$payment = Payments::by_order_ref( $order_ref );

		if ( is_wp_error( $result ) ) {
			return $this->redirect( self::failure_url( $payment ), $result->get_error_message() );
		}

		return $this->redirect( self::success_url( $payment ) );
	}

	/**
	 * The customer backed out on LINE Pay's side.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle_cancel( WP_REST_Request $request ) {
		$payment = Payments::by_order_ref( (string) $request->get_param( 'order_ref' ) );

		if ( $payment ) {
			Payments::update( (int) $payment->id, array( 'status' => 'cancelled' ) );

			if ( (int) $payment->wc_order_id && function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( (int) $payment->wc_order_id );

				if ( $order ) {
					$order->add_order_note( __( 'The customer cancelled the LINE Pay payment.', 'moksa-line' ) );
				}
			}
		}

		return $this->redirect( self::failure_url( $payment ), __( 'The payment was cancelled.', 'moksa-line' ) );
	}

	// --- Completion ---------------------------------------------------------------

	/**
	 * Confirm a reserved payment and settle whatever it belongs to.
	 *
	 * Safe to call more than once: an already-completed payment returns
	 * success without contacting LINE Pay again.
	 *
	 * @param int    $payment_id     Local payment row id.
	 * @param string $transaction_id Transaction id from LINE Pay, if supplied.
	 * @return true|WP_Error
	 */
	public static function complete( int $payment_id, string $transaction_id = '' ) {
		global $wpdb;

		$table = Payments::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $payment_id ) );

		if ( ! $payment ) {
			return new WP_Error( 'moksa_line_pay_missing', __( 'That payment could not be found.', 'moksa-line' ) );
		}

		if ( in_array( $payment->status, array( 'captured', 'authorized' ), true ) ) {
			return true;
		}

		if ( '' === $transaction_id ) {
			$transaction_id = (string) $payment->transaction_id;
		}

		if ( '' === $transaction_id ) {
			return new WP_Error(
				'moksa_line_pay_no_transaction',
				__( 'LINE Pay did not identify this transaction.', 'moksa-line' )
			);
		}

		if ( ! Payments::claim_for_confirm( $payment_id ) ) {
			// Someone else is confirming, or already has.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
			$fresh = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $payment_id ) );

			if ( $fresh && in_array( $fresh->status, array( 'captured', 'authorized' ), true ) ) {
				return true;
			}

			return new WP_Error(
				'moksa_line_pay_in_progress',
				__( 'This payment is already being confirmed. Please wait a moment and refresh.', 'moksa-line' )
			);
		}

		$result = LinePayClient::confirm(
			$transaction_id,
			(float) $payment->amount,
			(string) $payment->currency
		);

		if ( is_wp_error( $result ) ) {
			// 1165 means LINE Pay already captured it; that is a success from
			// the shop's point of view, not a failure to retry.
			if ( 'moksa_line_pay_1165' === $result->get_error_code() ) {
				Payments::update( $payment_id, array( 'status' => 'captured', 'transaction_id' => $transaction_id ) );
				self::settle( $payment_id );

				return true;
			}

			Payments::release( $payment_id );

			return $result;
		}

		$captured = Options::get( 'pay_capture' );

		Payments::update(
			$payment_id,
			array(
				'transaction_id' => $transaction_id,
				'status'         => $captured ? 'captured' : 'authorized',
				'raw'            => $result,
			)
		);

		self::settle( $payment_id );

		return true;
	}

	/**
	 * Mark whatever the payment belongs to as paid.
	 *
	 * @param int $payment_id Payment row id.
	 */
	private static function settle( int $payment_id ): void {
		$payment = self::row( $payment_id );

		if ( ! $payment ) {
			return;
		}

		if ( (int) $payment->wc_order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( (int) $payment->wc_order_id );

			if ( $order && ! $order->is_paid() ) {
				$order->payment_complete( (string) $payment->transaction_id );
				$order->add_order_note(
					sprintf(
						/* translators: %s: LINE Pay transaction id. */
						__( 'LINE Pay confirmed. Transaction %s.', 'moksa-line' ),
						(string) $payment->transaction_id
					)
				);
			}
		}

		// Tell the customer in LINE when we know who they are.
		if ( '' !== (string) $payment->line_user_id ) {
			$receipt = MessagingClient::text(
				sprintf(
					/* translators: 1: amount, 2: currency, 3: order reference. */
					__( 'Payment received: %1$s %2$s (order %3$s). Thank you!', 'moksa-line' ),
					number_format_i18n( (float) $payment->amount, in_array( $payment->currency, array( 'TWD', 'JPY', 'KRW' ), true ) ? 0 : 2 ),
					(string) $payment->currency,
					(string) $payment->order_ref
				)
			);

			$sent = MessagingClient::push(
				(string) $payment->line_user_id,
				array( $receipt ),
				array( 'retry_key' => 'receipt-' . $payment->order_ref )
			);

			Logger::capture( $sent, 'Could not send the LINE Pay receipt', 'pay' );
		}

		/**
		 * Fires when a LINE Pay payment is settled.
		 *
		 * @param object $payment Payment row.
		 */
		do_action( 'moksa_line_payment_completed', $payment );
	}

	/**
	 * Reload a payment row.
	 *
	 * @param int $payment_id Row id.
	 * @return object|null
	 */
	private static function row( int $payment_id ) {
		global $wpdb;
		$table = Payments::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $payment_id ) );
	}

	// --- Reconciliation --------------------------------------------------------------

	/**
	 * Ask LINE Pay about reservations that never came back.
	 *
	 * Customers close the tab, lose signal, or get interrupted mid-payment.
	 * Without this, money is taken and the order stays unpaid.
	 */
	public function reconcile(): void {
		foreach ( Payments::stale() as $payment ) {
			$details = LinePayClient::details( (string) $payment->transaction_id, (string) $payment->order_ref );

			if ( is_wp_error( $details ) ) {
				// 1150 means LINE Pay has no such transaction: the customer
				// never paid, so the reservation can be retired.
				if ( 'moksa_line_pay_1150' === $details->get_error_code() ) {
					Payments::update( (int) $payment->id, array( 'status' => 'expired' ) );
				}

				continue;
			}

			// details returns a list; the first entry is this order.
			$entry = isset( $details[0] ) && is_array( $details[0] ) ? $details[0] : $details;

			$transaction_id = isset( $entry['transactionId'] ) ? (string) $entry['transactionId'] : (string) $payment->transaction_id;
			$status         = isset( $entry['payStatus'] ) ? (string) $entry['payStatus'] : '';

			if ( in_array( $status, array( 'CAPTURE', 'AUTHORIZATION' ), true ) ) {
				Payments::update(
					(int) $payment->id,
					array(
						'transaction_id' => $transaction_id,
						'status'         => 'CAPTURE' === $status ? 'captured' : 'authorized',
						'raw'            => $entry,
					)
				);

				self::settle( (int) $payment->id );

				Logger::info(
					'Recovered a payment the customer never returned from',
					array( 'order_ref' => (string) $payment->order_ref ),
					'pay'
				);

				continue;
			}

			if ( 'VOID' === $status || 'EXPIRED' === $status ) {
				Payments::update( (int) $payment->id, array( 'status' => strtolower( $status ) ) );
			}
		}
	}

	/**
	 * Release the authorisation when an unpaid order is cancelled.
	 *
	 * @param int $order_id WooCommerce order id.
	 */
	public function void_on_cancel( $order_id ): void {
		$payment = Payments::by_wc_order( (int) $order_id );

		if ( ! $payment || 'authorized' !== $payment->status || '' === (string) $payment->transaction_id ) {
			return;
		}

		$result = LinePayClient::void( (string) $payment->transaction_id );

		if ( is_wp_error( $result ) ) {
			Logger::capture( $result, 'Could not void a LINE Pay authorisation', 'pay' );

			return;
		}

		Payments::update( (int) $payment->id, array( 'status' => 'void' ) );
	}

	// --- Standalone payment links ------------------------------------------------

	/**
	 * Reserve a payment that is not attached to a WooCommerce order, and
	 * return a URL that can be pasted into a chat, a rich menu or a Flex button.
	 *
	 * @param float  $amount       Amount to charge.
	 * @param string $title        What the customer is paying for.
	 * @param string $line_user_id Optional: who to send the receipt to.
	 * @return array{url:string,order_ref:string}|WP_Error
	 */
	public static function create_link( float $amount, string $title, string $line_user_id = '' ) {
		$currency = (string) Options::get( 'pay_currency' );
		$amount_i = LinePayClient::format_amount( $amount, $currency );

		if ( $amount_i <= 0 ) {
			return new WP_Error( 'moksa_line_pay_bad_amount', __( 'Enter an amount greater than zero.', 'moksa-line' ) );
		}

		$order_ref  = Payments::new_reference( 'LINK' );
		$payment_id = Payments::create(
			array(
				'order_ref'    => $order_ref,
				'source'       => 'link',
				'amount'       => (float) $amount_i,
				'currency'     => $currency,
				'line_user_id' => $line_user_id,
			)
		);

		if ( ! $payment_id ) {
			return new WP_Error( 'moksa_line_pay_store_failed', __( 'The payment could not be recorded.', 'moksa-line' ) );
		}

		$title = '' !== trim( $title ) ? $title : get_bloginfo( 'name' );

		$result = LinePayClient::request_payment(
			array(
				'amount'       => $amount_i,
				'currency'     => $currency,
				'orderId'      => $order_ref,
				'packages'     => array(
					array(
						'id'       => 'link',
						'amount'   => $amount_i,
						'name'     => mb_substr( $title, 0, 100 ),
						'products' => array(
							array(
								'name'     => mb_substr( $title, 0, 100 ),
								'quantity' => 1,
								'price'    => $amount_i,
							),
						),
					),
				),
				'redirectUrls' => array(
					'confirmUrl' => self::return_url( $order_ref ),
					'cancelUrl'  => self::cancel_url( $order_ref ),
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			Payments::update( $payment_id, array( 'status' => 'failed' ) );

			return $result;
		}

		$url = isset( $result['paymentUrl']['web'] ) ? (string) $result['paymentUrl']['web'] : '';

		Payments::update(
			$payment_id,
			array(
				'transaction_id' => isset( $result['transactionId'] ) ? (string) $result['transactionId'] : '',
				'payment_url'    => $url,
				'status'         => 'pending',
				'raw'            => $result,
			)
		);

		if ( '' === $url ) {
			return new WP_Error( 'moksa_line_pay_no_url', __( 'LINE Pay did not return a payment URL.', 'moksa-line' ) );
		}

		return array( 'url' => $url, 'order_ref' => $order_ref );
	}

	/**
	 * Create a payment link from the admin screen, optionally pushing it to a
	 * customer as a Flex message.
	 */
	public function ajax_create_link(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot create payment links.', 'moksa-line' ) ), 403 );
		}

		$amount  = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$send_to = isset( $_POST['line_user_id'] ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';

		$link = self::create_link( $amount, $title, $send_to );

		if ( is_wp_error( $link ) ) {
			wp_send_json_error( array( 'message' => $link->get_error_message() ) );
		}

		if ( '' !== $send_to ) {
			$sent = MessagingClient::push( $send_to, array( self::payment_bubble( $title, $amount, $link['url'] ) ) );

			if ( is_wp_error( $sent ) ) {
				wp_send_json_success(
					array(
						'url'     => $link['url'],
						'message' => sprintf(
							/* translators: %s: error detail. */
							__( 'The link was created, but LINE would not deliver it: %s', 'moksa-line' ),
							$sent->get_error_message()
						),
					)
				);
			}
		}

		wp_send_json_success(
			array(
				'url'     => $link['url'],
				'message' => __( 'Payment link created.', 'moksa-line' ),
			)
		);
	}

	/**
	 * A small Flex bubble carrying a payment button.
	 *
	 * @param string $title  What is being paid for.
	 * @param float  $amount Amount.
	 * @param string $url    Payment URL.
	 * @return array
	 */
	public static function payment_bubble( string $title, float $amount, string $url ): array {
		$currency = (string) Options::get( 'pay_currency' );
		$display  = sprintf(
			'%s %s',
			$currency,
			number_format_i18n( $amount, in_array( $currency, array( 'TWD', 'JPY', 'KRW' ), true ) ? 0 : 2 )
		);

		return MessagingClient::flex(
			sprintf(
				/* translators: 1: item title, 2: formatted amount. */
				__( 'Payment request: %1$s %2$s', 'moksa-line' ),
				$title,
				$display
			),
			array(
				'type' => 'bubble',
				'body' => array(
					'type'     => 'box',
					'layout'   => 'vertical',
					'spacing'  => 'md',
					'contents' => array(
						array(
							'type'   => 'text',
							'text'   => __( 'Payment request', 'moksa-line' ),
							'size'   => 'sm',
							'color'  => '#888888',
						),
						array(
							'type'   => 'text',
							'text'   => mb_substr( '' !== $title ? $title : get_bloginfo( 'name' ), 0, 60 ),
							'weight' => 'bold',
							'size'   => 'lg',
							'wrap'   => true,
						),
						array(
							'type'   => 'text',
							'text'   => $display,
							'size'   => 'xl',
							'weight' => 'bold',
						),
					),
				),
				'footer' => array(
					'type'     => 'box',
					'layout'   => 'vertical',
					'contents' => array(
						array(
							'type'   => 'button',
							'style'  => 'primary',
							'color'  => '#06C755',
							'action' => array(
								'type'  => 'uri',
								'label' => __( 'Pay with LINE Pay', 'moksa-line' ),
								'uri'   => $url,
							),
						),
					),
				),
			)
		);
	}

	// --- URLs ------------------------------------------------------------------------

	/**
	 * Where LINE Pay sends a customer who paid.
	 *
	 * @param string $order_ref Order reference.
	 */
	public static function return_url( string $order_ref ): string {
		return add_query_arg( 'order_ref', rawurlencode( $order_ref ), rest_url( self::NAMESPACE_V1 . '/pay/return' ) );
	}

	/**
	 * Where LINE Pay sends a customer who cancelled.
	 *
	 * @param string $order_ref Order reference.
	 */
	public static function cancel_url( string $order_ref ): string {
		return add_query_arg( 'order_ref', rawurlencode( $order_ref ), rest_url( self::NAMESPACE_V1 . '/pay/cancel' ) );
	}

	/**
	 * Where to land a customer after a successful payment.
	 *
	 * @param object|null $payment Payment row.
	 */
	private static function success_url( $payment ): string {
		if ( $payment && (int) $payment->wc_order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( (int) $payment->wc_order_id );

			if ( $order ) {
				return $order->get_checkout_order_received_url();
			}
		}

		/**
		 * Filter where a standalone payment link lands after success.
		 *
		 * @param string      $url     Destination.
		 * @param object|null $payment Payment row.
		 */
		return apply_filters( 'moksa_line_pay_success_url', home_url(), $payment );
	}

	/**
	 * Where to land a customer whose payment failed or was cancelled.
	 *
	 * @param object|null $payment Payment row.
	 */
	private static function failure_url( $payment ): string {
		if ( $payment && (int) $payment->wc_order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( (int) $payment->wc_order_id );

			if ( $order ) {
				return $order->get_checkout_payment_url();
			}
		}

		/**
		 * Filter where a standalone payment link lands after failure.
		 *
		 * @param string      $url     Destination.
		 * @param object|null $payment Payment row.
		 */
		return apply_filters( 'moksa_line_pay_failure_url', home_url(), $payment );
	}

	/**
	 * Redirect out of a REST callback.
	 *
	 * @param string $url     Destination.
	 * @param string $message Optional notice to carry through.
	 * @return WP_REST_Response
	 */
	private function redirect( string $url, string $message = '' ): WP_REST_Response {
		if ( '' !== $message ) {
			$url = add_query_arg( 'moksa_pay_message', rawurlencode( $message ), $url );
		}

		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', wp_sanitize_redirect( $url ) );

		return $response;
	}
}
