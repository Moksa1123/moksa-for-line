<?php
/**
 * WooCommerce payment gateway for LINE Pay.
 *
 * Reservation happens in process_payment and the order is only marked paid in
 * the return endpoint, after LINE Pay confirms. Nothing here ever trusts the
 * browser's word that a payment succeeded.
 *
 * @package Mofoline
 */

namespace Mofoline\Pay;

use Mofoline\Data\Users;
use Mofoline\Support\Logger;
use Mofoline\Support\Options;
use WC_Payment_Gateway;

defined( 'ABSPATH' ) || exit;

class WooGateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'mofoline_pay';
		$this->method_title       = __( 'LINE Pay', 'moksa-for-line' );
		$this->method_description = __( 'Take payments through LINE Pay. Credentials are configured under LINE > Settings > LINE Pay.', 'moksa-for-line' );
		$this->has_fields         = false;
		$this->supports           = array( 'products', 'refunds' );

		$this->icon = MOFOLINE_URL . 'assets/img/linepay.svg';

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'LINE Pay', 'moksa-for-line' ) );
		$this->description = $this->get_option( 'description', __( 'Pay with LINE Pay, LINE Points or a registered card.', 'moksa-for-line' ) );
		$this->enabled     = $this->get_option( 'enabled', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable', 'moksa-for-line' ),
				'type'    => 'checkbox',
				'label'   => __( 'Offer LINE Pay at checkout', 'moksa-for-line' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'moksa-for-line' ),
				'type'        => 'text',
				'description' => __( 'What the customer sees at checkout.', 'moksa-for-line' ),
				'default'     => __( 'LINE Pay', 'moksa-for-line' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'   => __( 'Description', 'moksa-for-line' ),
				'type'    => 'textarea',
				'default' => __( 'Pay with LINE Pay, LINE Points or a registered card.', 'moksa-for-line' ),
			),
			'credentials' => array(
				'title'       => __( 'Credentials', 'moksa-for-line' ),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'The Channel ID, Channel Secret and sandbox switch live on the <a href="%s">LINE Pay settings screen</a>, so they are stored encrypted and shared with payment links.', 'moksa-for-line' ),
					esc_url( admin_url( 'admin.php?page=mofoline-settings&tab=pay' ) )
				),
			),
		);
	}

	/**
	 * Whether another plugin's LINE Pay gateway is enabled on this site.
	 *
	 * Checked by settings rather than by class, so it holds whether or not the
	 * other plugin has loaded its gateway yet on this request.
	 */
	public static function another_line_pay_is_active(): bool {
		foreach ( array( 'moksafowo-linepay' ) as $other ) {
			$settings = get_option( 'woocommerce_' . $other . '_settings', array() );

			if ( is_array( $settings ) && isset( $settings['enabled'] ) && 'yes' === $settings['enabled'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the gateway can be offered.
	 */
	public function is_available(): bool {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( ! Options::get( 'pay_enabled' ) || ! LinePayClient::is_configured() ) {
			return false;
		}

		// moksa-for-woocommerce carries its own LINE Pay gateway. A store with
		// both plugins would otherwise offer LINE Pay twice at checkout, and
		// the customer has no way to know they are the same thing. That one
		// wins: it is the commerce plugin, and it was there first.
		if ( self::another_line_pay_is_active() ) {
			return false;
		}

		// LINE Pay settles in one currency per merchant; offering it for an
		// order in another currency produces a confusing failure at confirm.
		$store_currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		if ( $store_currency && $store_currency !== (string) Options::get( 'pay_currency' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Reserve the payment and send the customer to LINE Pay.
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return array
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'That order could not be found.', 'moksa-for-line' ), 'error' );

			return array( 'result' => 'failure' );
		}

		$currency = (string) Options::get( 'pay_currency' );
		$amount   = LinePayClient::format_amount( (float) $order->get_total(), $currency );

		if ( $amount <= 0 ) {
			wc_add_notice( __( 'This order has nothing to pay.', 'moksa-for-line' ), 'error' );

			return array( 'result' => 'failure' );
		}

		$order_ref = Payments::new_reference( (string) $order->get_order_number() );

		$line_user_id = '';
		$customer_id  = (int) $order->get_customer_id();

		if ( $customer_id > 0 ) {
			$record = Users::by_wp_id( $customer_id );

			if ( $record ) {
				$line_user_id = (string) $record->line_user_id;
			}
		}

		$payment_id = Payments::create(
			array(
				'order_ref'    => $order_ref,
				'wc_order_id'  => (int) $order_id,
				'source'       => 'woocommerce',
				'amount'       => (float) $amount,
				'currency'     => $currency,
				'line_user_id' => $line_user_id,
			)
		);

		if ( ! $payment_id ) {
			wc_add_notice( __( 'The payment could not be started. Please try again.', 'moksa-for-line' ), 'error' );

			return array( 'result' => 'failure' );
		}

		$result = LinePayClient::request_payment(
			array(
				'amount'       => $amount,
				'currency'     => $currency,
				'orderId'      => $order_ref,
				'packages'     => array( $this->build_package( $order, $amount ) ),
				'redirectUrls' => array(
					'confirmUrl' => PayModule::return_url( $order_ref ),
					'cancelUrl'  => PayModule::cancel_url( $order_ref ),
				),
				'options'      => array(
					'payment' => array(
						// When capture is off the payment is only authorised,
						// and must be captured (or voided) later.
						'capture' => (bool) Options::get( 'pay_capture' ),
					),
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			Payments::update( $payment_id, array( 'status' => 'failed' ) );

			Logger::capture( $result, 'Could not reserve a LINE Pay payment', 'pay' );

			wc_add_notice( $result->get_error_message(), 'error' );

			return array( 'result' => 'failure' );
		}

		$transaction_id = isset( $result['transactionId'] ) ? (string) $result['transactionId'] : '';
		$redirect       = $this->pick_payment_url( $result );

		Payments::update(
			$payment_id,
			array(
				'transaction_id' => $transaction_id,
				'payment_url'    => $redirect,
				'status'         => 'pending',
				'raw'            => $result,
			)
		);

		$order->update_meta_data( '_mofoline_pay_order_ref', $order_ref );
		$order->update_meta_data( '_mofoline_pay_transaction_id', $transaction_id );
		$order->update_status( 'pending', __( 'Waiting for the customer to complete payment on LINE Pay.', 'moksa-for-line' ) );
		$order->save();

		if ( '' === $redirect ) {
			wc_add_notice( __( 'LINE Pay did not return a payment URL. Please try again.', 'moksa-for-line' ), 'error' );

			return array( 'result' => 'failure' );
		}

		return array(
			'result'   => 'success',
			'redirect' => $redirect,
		);
	}

	/**
	 * Refund through LINE Pay.
	 *
	 * @param int    $order_id WooCommerce order id.
	 * @param float  $amount   Amount to refund, or null for the full amount.
	 * @param string $reason   Refund reason.
	 * @return bool|\WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$payment = Payments::by_wc_order( (int) $order_id );

		if ( ! $payment || '' === (string) $payment->transaction_id ) {
			return new \WP_Error(
				'mofoline_pay_no_transaction',
				__( 'This order has no LINE Pay transaction to refund.', 'moksa-for-line' )
			);
		}

		if ( ! in_array( $payment->status, array( 'captured', 'partially_refunded' ), true ) ) {
			return new \WP_Error(
				'mofoline_pay_not_captured',
				__( 'Only a captured payment can be refunded.', 'moksa-for-line' )
			);
		}

		$currency = (string) $payment->currency;
		$refund   = null === $amount ? (float) $payment->amount : (float) $amount;

		$remaining = (float) $payment->amount - (float) $payment->refunded;

		if ( $refund > $remaining + 0.001 ) {
			return new \WP_Error(
				'mofoline_pay_refund_too_large',
				sprintf(
					/* translators: %s: remaining refundable amount. */
					__( 'Only %s remains refundable on this payment.', 'moksa-for-line' ),
					number_format_i18n( $remaining, in_array( $currency, array( 'TWD', 'JPY', 'KRW' ), true ) ? 0 : 2 )
				)
			);
		}

		$result = LinePayClient::refund( (string) $payment->transaction_id, $refund, $currency );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$refunded_total = (float) $payment->refunded + $refund;

		Payments::update(
			(int) $payment->id,
			array(
				'refunded' => $refunded_total,
				'status'   => $refunded_total >= (float) $payment->amount - 0.001 ? 'refunded' : 'partially_refunded',
				'raw'      => $result,
			)
		);

		$order = wc_get_order( $order_id );

		if ( $order ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: refunded amount, 2: reason. */
					__( 'LINE Pay refunded %1$s. %2$s', 'moksa-for-line' ),
					number_format_i18n( $refund, in_array( $currency, array( 'TWD', 'JPY', 'KRW' ), true ) ? 0 : 2 ),
					$reason
				)
			);
		}

		return true;
	}

	/**
	 * Build the package describing what the customer is buying.
	 *
	 * LINE Pay requires the package amount to equal the sum of its products,
	 * and the order total to equal the sum of the packages. Shipping, fees and
	 * tax are folded into a single adjustment line rather than being dropped,
	 * because a mismatch here is rejected at reservation time.
	 *
	 * @param \WC_Order  $order  Order.
	 * @param int|float  $amount Order total as sent to LINE Pay.
	 * @return array
	 */
	private function build_package( $order, $amount ): array {
		$currency = (string) Options::get( 'pay_currency' );
		$products = array();
		$subtotal = 0;

		foreach ( $order->get_items() as $item ) {
			$line  = LinePayClient::format_amount( (float) $order->get_line_total( $item, true ), $currency );
			$count = max( 1, (int) $item->get_quantity() );

			// LINE Pay multiplies price by quantity, so the price must be the
			// unit price in the same rounding as the total.
			$unit = LinePayClient::format_amount( $line / $count, $currency );

			// Rounding the unit price can drift from the line total; carry the
			// line total instead and keep quantity at one when they disagree.
			if ( LinePayClient::format_amount( $unit * $count, $currency ) !== $line ) {
				$unit  = $line;
				$count = 1;
			}

			$products[] = array(
				'name'     => mb_substr( wp_strip_all_tags( (string) $item->get_name() ), 0, 100 ),
				'quantity' => $count,
				'price'    => $unit,
			);

			$subtotal += $unit * $count;
		}

		$difference = $amount - $subtotal;

		if ( abs( $difference ) > 0.001 ) {
			$products[] = array(
				'name'     => $difference > 0
					? __( 'Shipping, fees and tax', 'moksa-for-line' )
					: __( 'Discount', 'moksa-for-line' ),
				'quantity' => 1,
				'price'    => LinePayClient::format_amount( (float) $difference, $currency ),
			);
		}

		if ( empty( $products ) ) {
			$products[] = array(
				'name'     => sprintf(
					/* translators: %s: order number. */
					__( 'Order %s', 'moksa-for-line' ),
					(string) $order->get_order_number()
				),
				'quantity' => 1,
				'price'    => $amount,
			);
		}

		return array(
			'id'       => (string) $order->get_order_number(),
			'amount'   => $amount,
			'name'     => mb_substr( (string) get_bloginfo( 'name' ), 0, 100 ),
			'products' => $products,
		);
	}

	/**
	 * Choose the web or in-app payment URL.
	 *
	 * Sending a customer who is already inside the LINE app to the web URL
	 * bounces them out to a browser and loses the session.
	 *
	 * @param array $info LINE Pay `info` object.
	 */
	private function pick_payment_url( array $info ): string {
		$web = isset( $info['paymentUrl']['web'] ) ? (string) $info['paymentUrl']['web'] : '';
		$app = isset( $info['paymentUrl']['app'] ) ? (string) $info['paymentUrl']['app'] : '';

		$agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		if ( '' !== $app && false !== stripos( $agent, ' Line/' ) ) {
			return $app;
		}

		return '' !== $web ? $web : $app;
	}
}
