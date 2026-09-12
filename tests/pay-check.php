<?php
/**
 * LINE Pay, as WooCommerce sees it.
 *
 *   wp eval-file tests/pay-check.php
 *
 * None of this needs LINE Pay credentials, and none of it contacts LINE. It
 * checks the parts of the integration that fail silently when they are wrong:
 * a gateway the block checkout cannot see, a compatibility flag WooCommerce
 * reads as "incompatible", an order screen with no box on it, a second LINE
 * Pay appearing beside moksa-for-woocommerce's.
 *
 * Temporarily pretends moksa-for-woocommerce's gateway is enabled to prove
 * ours stands down, and puts that option back exactly as it was.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Pay\BlocksSupport;
use Moksa\Line\Pay\WooGateway;
use Moksa\Line\Support\Options;

$GLOBALS['pay_fail'] = 0;

function pay_check( $ok, $label, $detail = '' ) {
	if ( $ok ) {
		echo "  ok    {$label}\n";
		return;
	}

	++$GLOBALS['pay_fail'];
	echo "  FAIL  {$label}" . ( '' !== $detail ? " -- {$detail}" : '' ) . "\n";
}

if ( ! class_exists( 'WooCommerce' ) ) {
	echo "REFUSED: WooCommerce is not active.\n";
	return;
}

echo "LINE Pay\n";

// --- WooCommerce's own compatibility ledger ---------------------------------

$plugin_file = 'moksa-line/moksa-line.php';

if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
	foreach ( array( 'custom_order_tables' => 'orders in custom tables (HPOS)', 'cart_checkout_blocks' => 'the block checkout' ) as $feature => $label ) {
		$compatible = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_plugins_for_feature( $feature, false );
		$listed     = isset( $compatible['compatible'] ) && in_array( $plugin_file, (array) $compatible['compatible'], true );

		pay_check( $listed, 'WooCommerce lists the plugin as compatible with ' . $label );
	}
}

// --- the gateway, and the block checkout's view of it ------------------------

$enabled_before = (bool) Options::get( 'pay_enabled' );
$gateways       = WC()->payment_gateways()->payment_gateways();

pay_check( isset( $gateways['moksa_line_pay'] ), 'the gateway is registered with WooCommerce' );

if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
	pay_check( class_exists( BlocksSupport::class ), 'a block payment method type exists' );

	$method = new BlocksSupport();
	$method->initialize();

	pay_check( 'moksa_line_pay' === $method->get_name(), 'the block method carries the gateway id', $method->get_name() );

	$handles = $method->get_payment_method_script_handles();
	pay_check( in_array( 'moksa-line-pay-blocks', (array) $handles, true ), 'it registers its checkout script' );
	pay_check( wp_script_is( 'moksa-line-pay-blocks', 'registered' ), 'the script is registered with WordPress' );

	$data = $method->get_payment_method_data();
	pay_check( isset( $data['title'], $data['description'], $data['icon'], $data['supports'] ), 'it hands the block a title, description, icon and supports' );
	pay_check( is_array( $data['supports'] ) && in_array( 'products', $data['supports'], true ), 'supports includes products', wp_json_encode( $data['supports'] ) );

	$js = MOKSA_LINE_DIR . 'assets/js/pay-blocks.js';
	pay_check( is_readable( $js ), 'the checkout script ships' );

	if ( is_readable( $js ) ) {
		$source = file_get_contents( $js );
		pay_check( false !== strpos( $source, "name: 'moksa_line_pay'" ), 'the script registers the same id the block method uses' );
		pay_check( false !== strpos( $source, "getSetting('moksa_line_pay_data'" ), 'the script reads the data key WooCommerce derives from that id' );
	}

	// is_active() must agree with the gateway: the two checkouts show the same thing.
	$gateway = isset( $gateways['moksa_line_pay'] ) ? $gateways['moksa_line_pay'] : null;

	if ( $gateway ) {
		pay_check( $method->is_active() === $gateway->is_available(), 'the block agrees with the gateway about availability' );
	}
}

// --- not two LINE Pays ----------------------------------------------------------

echo "  beside moksa-for-woocommerce\n";

$other_key = 'woocommerce_moksafowo-linepay_settings';
$other_was = get_option( $other_key, null );

update_option( $other_key, array( 'enabled' => 'yes' ), false );
pay_check( WooGateway::another_line_pay_is_active(), 'sees the other gateway when it is enabled' );

if ( isset( $gateways['moksa_line_pay'] ) ) {
	pay_check( ! $gateways['moksa_line_pay']->is_available(), 'and stands down at checkout' );
}

update_option( $other_key, array( 'enabled' => 'no' ), false );
pay_check( ! WooGateway::another_line_pay_is_active(), 'ignores the other gateway when it is disabled' );

if ( null === $other_was ) {
	delete_option( $other_key );
} else {
	update_option( $other_key, $other_was, false );
}

pay_check( get_option( $other_key, null ) === $other_was, 'the other plugin\'s setting was put back' );

// --- the order screen -------------------------------------------------------

echo "  the order screen\n";

$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
do_action( 'add_meta_boxes', $screen, null );

global $wp_meta_boxes;
$found = false;

foreach ( array( 'side', 'normal', 'advanced' ) as $context ) {
	foreach ( array( 'high', 'core', 'default', 'low' ) as $priority ) {
		if ( ! empty( $wp_meta_boxes[ $screen ][ $context ][ $priority ]['moksa-line-pay'] ) ) {
			$found = true;
		}
	}
}

pay_check( $found, 'a LINE Pay box is on the order screen (' . $screen . ')' );

// Render it for an order that was not paid with LINE Pay: it has to say so,
// not crash on a missing payment row.
$any = wc_get_orders( array( 'limit' => 1, 'return' => 'ids' ) );

if ( ! empty( $any ) ) {
	$order  = wc_get_order( $any[0] );
	$module = new \Moksa\Line\Pay\PayModule();

	ob_start();
	$module->render_order_meta_box( $order );
	$html = ob_get_clean();

	// Compared against the translated strings, not English fragments: on a
	// zh_TW site the box says 這筆訂單不是用 LINE Pay 付款的, and a test that only
	// knows the English would fail the moment the catalogue was compiled.
	$is_line_pay = 'moksa_line_pay' === $order->get_payment_method();
	$expected    = array(
		__( 'This order was not paid with LINE Pay.', 'moksa-line' ),
		__( 'No LINE Pay record exists for this order. The payment may never have been started.', 'moksa-line' ),
	);
	$says_so     = false;

	foreach ( $expected as $sentence ) {
		$says_so = $says_so || false !== strpos( $html, $sentence );
	}

	pay_check(
		'' !== trim( $html ) && ( $is_line_pay || $says_so ),
		'the box renders for an existing order without error',
		substr( wp_strip_all_tags( $html ), 0, 60 )
	);
}

// --- what the customer is told --------------------------------------------------

echo "  the customer\n";

$module = isset( $module ) ? $module : new \Moksa\Line\Pay\PayModule();
$text   = $module->thankyou_text( 'Thank you.', null );
pay_check( 'Thank you.' === $text, 'the thank-you text is left alone when there is no order' );

echo "\n" . ( 0 === $GLOBALS['pay_fail'] ? "LINE PAY OK\n" : $GLOBALS['pay_fail'] . " checks failed\n" );
