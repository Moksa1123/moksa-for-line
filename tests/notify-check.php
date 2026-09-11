<?php
/**
 * Exercise the order notification pipeline end to end.
 *
 * Needs WooCommerce active. Creates products and orders and truncates the
 * notification history, so run it only against a throwaway site.
 *
 *   wp eval-file tests/notify-check.php
 *
 * @package Moksa\Line
 */

use Moksa\Line\Data\Users;
use Moksa\Line\Support\Options;
use Moksa\Line\Woo\NotifyHistory;
use Moksa\Line\Woo\NotifyTemplates;
use Moksa\Line\Woo\OrderContext;
use Moksa\Line\Woo\TriggerRules;

global $wpdb;

$GLOBALS['nt_failures'] = 0;

function check( $condition, $label, $detail = '' ) {
	if ( $condition ) {
		echo "  ok    {$label}\n";
		return;
	}

	++$GLOBALS['nt_failures'];
	echo "  FAIL  {$label}" . ( '' !== $detail ? " -- {$detail}" : '' ) . "\n";
}

// --- Setup ------------------------------------------------------------------

/*
 * This script truncates the notification history and the log, and overwrites
 * the site's own notification settings. The docblock has always said to run it
 * only on a throwaway site, but a comment does not stop anyone: pasted into the
 * wrong terminal it silently destroys a shop's delivery record and rewrites
 * when their customers get notified. So it asks first.
 */
if ( 'production' === wp_get_environment_type() && ! defined( 'MOKSA_LINE_ALLOW_DESTRUCTIVE_TESTS' ) ) {
	echo "REFUSED: this site reports WP_ENVIRONMENT_TYPE=production.
";
	echo "It truncates the notification history and log and overwrites notification settings.
";
	echo "If this really is a throwaway site, set WP_ENVIRONMENT_TYPE, or define
";
	echo "MOKSA_LINE_ALLOW_DESTRUCTIVE_TESTS in wp-config.php, and run it again.
";
	return;
}

$wpdb->query( 'TRUNCATE ' . NotifyHistory::table() );
$wpdb->query( 'TRUNCATE ' . \Moksa\Line\Support\Logger::table() );

Options::set( 'woo_notify', true );
Options::set( 'woo_notify_statuses', array( 'completed' ) );
Options::set( 'woo_notify_delay', 0 );
Options::set( 'woo_wait_for_tracking', true );
Options::set( 'woo_tracking_status', 'processing' );
Options::set( 'woo_tracking_retries', 2 );
Options::set( 'woo_tracking_delay', 60 );
Options::flush_cache();

Users::upsert( 'Ubuyer9001', array( 'wp_user_id' => 1, 'display_name' => 'Notify Buyer' ) );

// Remove templates from earlier runs.
foreach ( get_posts( array( 'post_type' => NotifyTemplates::POST_TYPE, 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) ) as $old ) {
	wp_delete_post( $old, true );
}

/**
 * Build an order with one product.
 */
function make_order( $total_each = 500, $qty = 2 ) {
	$product = new WC_Product_Simple();
	$product->set_name( 'Notify Widget' );
	$product->set_regular_price( $total_each );
	$product->save();

	$order = wc_create_order( array( 'customer_id' => 1 ) );
	$order->set_currency( 'TWD' );
	$order->set_payment_method( 'cod' );
	$order->add_product( $product, $qty );
	$order->set_billing_first_name( 'Notify' );
	$order->set_billing_last_name( 'Buyer' );
	$order->set_billing_email( 'buyer@example.com' );
	$order->update_meta_data( '_moksa_line_user_id', 'Ubuyer9001' );
	$order->calculate_totals();
	$order->save();

	return $order;
}

echo "== Placeholders and tracking ==\n";

$order = make_order();
$order->update_meta_data( '_ecpay_logistics_id', 'ECP123456789' );
$order->update_meta_data( '_ecpay_receiver_store_name', 'Test Store' );
$order->save();

$values = OrderContext::placeholders( $order, 'completed' );

check( 'ECP123456789' === $values['{tracking_number}'], 'tracking number found in the ECPay meta key', $values['{tracking_number}'] );
check( 'Test Store' === $values['{store_name}'], 'pickup store name resolved' );
check( (string) $order->get_order_number() === $values['{order_number}'], 'order number resolved' );
check( false !== strpos( $values['{order_items}'], 'Notify Widget x2' ), 'item list rendered', $values['{order_items}'] );
check( 'Notify Buyer' === $values['{customer_full_name}'], 'customer name resolved' );
check( '#06C755' === $values['{status_color}'], 'completed uses the green' );

// Money went out to customers as "&#78;&#84;&#36;1,000": wp_strip_all_tags()
// removes wc_price()'s markup but leaves its HTML entities behind.
check(
	false === strpos( $values['{total}'], '&#' ),
	'the total is readable rather than HTML entities',
	$values['{total}']
);
check(
	false === strpos( $values['{order_subtotal}'], '&#' ),
	'and so is the subtotal',
	$values['{order_subtotal}']
);
check(
	false !== strpos( $values['{total}'], '1,000' ),
	'the total still carries the amount',
	$values['{total}']
);

// A general sweep rather than only the two fields known to have been wrong:
// anything that reaches a LINE message and still carries HTML entities shows
// them to the customer literally.
$entity_values = array();

foreach ( $values as $name => $value ) {
	if ( preg_match( '/&#[0-9]+;|&[a-z]+;/i', (string) $value ) ) {
		$entity_values[] = $name;
	}
}

check(
	array() === $entity_values,
	'no placeholder value carries HTML entities',
	implode( ', ', $entity_values )
);
check( '#FF334B' === OrderContext::placeholders( $order, 'cancelled' )['{status_color}'], 'cancelled uses the red' );

echo "\n== JSON safety ==\n";

$order->set_billing_first_name( 'Quote"Name' );
$order->save();

$rendered = OrderContext::render( '{"type":"text","text":"{customer_full_name}"}', $order, 'completed' );
check( is_array( $rendered ), 'a name containing a quote still produces valid JSON' );
check( is_array( $rendered ) && false !== strpos( $rendered['text'], 'Quote"Name' ), 'the quote survives into the value' );

$order->set_billing_first_name( 'Notify' );
$order->save();

echo "\n== Trigger rules ==\n";

check( TriggerRules::match( array(), $order ), 'no rules matches everything' );
check( TriggerRules::match( array( array( 'type' => 'payment_method', 'operator' => 'is', 'value' => 'cod' ) ), $order ), 'payment method is cod' );
check( ! TriggerRules::match( array( array( 'type' => 'payment_method', 'operator' => 'is', 'value' => 'stripe' ) ), $order ), 'payment method is not stripe' );
check( TriggerRules::match( array( array( 'type' => 'order_total', 'operator' => 'gte', 'value' => '1000' ) ), $order ), 'total >= 1000' );
check( ! TriggerRules::match( array( array( 'type' => 'order_total', 'operator' => 'gt', 'value' => '1000' ) ), $order ), 'total not > 1000' );
check( TriggerRules::match( array( array( 'type' => 'order_total', 'operator' => 'eq', 'value' => '1000' ) ), $order ), 'total == 1000 despite float representation' );
check(
	! TriggerRules::match(
		array(
			array( 'type' => 'payment_method', 'operator' => 'is', 'value' => 'cod' ),
			array( 'type' => 'order_total', 'operator' => 'lt', 'value' => '100' ),
		),
		$order
	),
	'rules combine with AND'
);

echo "\n== Template selection ==\n";

$template_id = wp_insert_post(
	array(
		'post_type'   => NotifyTemplates::POST_TYPE,
		'post_title'  => 'Completed notice',
		'post_status' => 'publish',
	)
);

update_post_meta( $template_id, NotifyTemplates::META_STATUSES, array( 'completed' ) );
update_post_meta( $template_id, NotifyTemplates::META_RULES, array( array( 'type' => 'payment_method', 'operator' => 'is', 'value' => 'cod' ) ) );
update_post_meta(
	$template_id,
	NotifyTemplates::META_CONTENT,
	(string) wp_json_encode( NotifyTemplates::starter_template() )
);

check( array( $template_id ) === NotifyTemplates::for_status( 'completed', $order ), 'template selected for a matching order' );
check( array() === NotifyTemplates::for_status( 'refunded', $order ), 'not selected for another status' );

// A template stored with the wc- prefix, as the previous plugin wrote them.
$legacy_id = wp_insert_post( array( 'post_type' => NotifyTemplates::POST_TYPE, 'post_title' => 'Legacy', 'post_status' => 'publish' ) );
update_post_meta( $legacy_id, NotifyTemplates::META_STATUSES, array( 'wc-refunded' ) );
update_post_meta( $legacy_id, NotifyTemplates::META_CONTENT, (string) wp_json_encode( NotifyTemplates::starter_template() ) );

check( in_array( $legacy_id, NotifyTemplates::for_status( 'refunded', $order ), true ), 'a wc- prefixed status from the old plugin still matches' );

$message = NotifyTemplates::render( $template_id, $order, 'completed' );
check( is_array( $message ) && 'flex' === $message['type'], 'template renders to a flex message' );
check( is_array( $message ) && array() === \Moksa\Line\Flex\Validator::check_message( $message ), 'rendered message passes validation' );

echo "\n== Dispatch, history and the duplicate guard ==\n";

$order->update_status( 'completed', 'test' );

$history = NotifyHistory::paginate( array( 'order_id' => $order->get_id() ) );

check( 1 === $history['total'], 'one history row written', (string) $history['total'] );
check( ! empty( $history['rows'] ) && 'failed' === $history['rows'][0]->status, 'recorded as failed, since there is no real token' );
check( ! empty( $history['rows'] ) && (int) $history['rows'][0]->template_id === $template_id, 'history names the template used' );
check( ! empty( $history['rows'] ) && '' !== (string) $history['rows'][0]->error, 'the failure reason is stored' );

// Setting the same status again must not notify twice.
$before = NotifyHistory::paginate( array( 'order_id' => $order->get_id() ) )['total'];
do_action( 'woocommerce_order_status_changed', $order->get_id(), 'processing', 'completed', $order );
$after = NotifyHistory::paginate( array( 'order_id' => $order->get_id() ) )['total'];

// The guard only records after a successful delivery, so a failed send is
// allowed to be retried. That is deliberate; assert the behaviour explicitly.
check( $after >= $before, 'a failed notification may be attempted again', "before {$before}, after {$after}" );

echo "\n== Waiting for a tracking number ==\n";

// processing must be notifiable before the deferral can be observed at all.
Options::set( 'woo_notify_statuses', array( 'completed', 'processing' ) );
Options::flush_cache();

$untracked = make_order();
$untracked->save();

wp_clear_scheduled_hook( 'moksa_line_order_notify' );

$untracked->update_status( 'processing', 'test' );

$scheduled = wp_next_scheduled( 'moksa_line_order_notify', array( $untracked->get_id(), 'processing' ) );
check( false !== $scheduled, 'a processing order with no tracking number is deferred rather than sent' );
check( 0 === NotifyHistory::paginate( array( 'order_id' => $untracked->get_id() ) )['total'], 'nothing was sent while waiting' );

// Once the number arrives, dispatch proceeds.
$untracked->update_meta_data( '_shipping_tracking_number', 'TRACK999' );
$untracked->save();

$module = new \Moksa\Line\Woo\WooModule();
$module->dispatch( $untracked->get_id(), 'processing' );

check( 1 === NotifyHistory::paginate( array( 'order_id' => $untracked->get_id() ) )['total'], 'sent once the tracking number exists' );

echo "\n== Retry budget ==\n";

$stubborn = make_order();
$stubborn->save();
wp_clear_scheduled_hook( 'moksa_line_order_notify' );

$module->dispatch( $stubborn->get_id(), 'processing' );
check( 1 === (int) wc_get_order( $stubborn->get_id() )->get_meta( '_moksa_line_notify_attempts' ), 'first wait recorded' );

wp_clear_scheduled_hook( 'moksa_line_order_notify' );
$module->dispatch( $stubborn->get_id(), 'processing' );
check( 2 === (int) wc_get_order( $stubborn->get_id() )->get_meta( '_moksa_line_notify_attempts' ), 'second wait recorded' );

wp_clear_scheduled_hook( 'moksa_line_order_notify' );
$module->dispatch( $stubborn->get_id(), 'processing' );

check(
	1 === NotifyHistory::paginate( array( 'order_id' => $stubborn->get_id() ) )['total'],
	'after the retry budget is spent, it sends without a tracking number'
);

echo "\n";

$failures = (int) $GLOBALS['nt_failures'];

if ( $failures > 0 ) {
	echo "{$failures} FAILURE(S)\n";

	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::error( sprintf( '%d notification check(s) failed.', $failures ) );
	}

	exit( 1 );
}

echo "NOTIFICATIONS OK\n";
