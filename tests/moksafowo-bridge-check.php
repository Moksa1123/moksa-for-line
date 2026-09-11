<?php
/**
 * Prove the Moksa for WooCommerce bridge is wired to the real class names.
 *
 *   wp eval-file tests/moksafowo-bridge-check.php
 *
 * The plugin is not installed here, so its two entry points are stubbed under
 * exactly the namespaces it uses. If the bridge picks these up, it will pick
 * up the real ones; if a name is wrong, nothing comes back.
 */

namespace Moksafowo\Modules\OrderLookup;

final class SearchableKeys {
	public static function field_value( $order, string $field ): string {
		return array(
			'invoice'  => 'LA25029547',
			'shipping' => '900112233445',
			'payment'  => 'TXN-0001',
		)[ $field ] ?? '';
	}
}

namespace Moksafowo\Order\Meta;

final class Keys {
	public const SHIPPING_CVS_STORE_NAME    = '_moksafowo_shipping_cvs_store_name';
	public const SHIPPING_CVS_STORE_ADDRESS = '_moksafowo_shipping_cvs_store_address';
}

namespace Moksa\Line\Test;

use Moksa\Line\Woo\OrderContext;

if ( 'production' === \wp_get_environment_type() && ! \defined( 'MOKSA_LINE_ALLOW_DESTRUCTIVE_TESTS' ) ) {
	echo "REFUSED: this site reports WP_ENVIRONMENT_TYPE=production.
";
	echo "It writes and removes two meta values on your most recent order.
";
	echo "If this really is a throwaway site, set WP_ENVIRONMENT_TYPE, or define
";
	echo "MOKSA_LINE_ALLOW_DESTRUCTIVE_TESTS in wp-config.php, and run it again.
";
	return;
}

$orders = wc_get_orders( array( 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );

if ( empty( $orders ) ) {
	echo "no orders to test with\n";
	return;
}

$order = $orders[0];
$order->update_meta_data( '_moksafowo_shipping_cvs_store_name', '7-ELEVEN 測試門市' );
$order->update_meta_data( '_moksafowo_shipping_cvs_store_address', '台北市測試路 1 號' );

$values = OrderContext::placeholders( $order, (string) $order->get_status() );
$failed = 0;

foreach ( array(
	'{invoice_number}'     => 'LA25029547',
	'{transaction_number}' => 'TXN-0001',
	'{tracking_number}'    => '900112233445',
	'{store_name}'         => '7-ELEVEN 測試門市',
	'{store_address}'      => '台北市測試路 1 號',
) as $placeholder => $expected ) {
	$actual = $values[ $placeholder ] ?? '(missing)';

	if ( $actual === $expected ) {
		echo "  ok    {$placeholder} = {$actual}\n";
	} else {
		++$failed;
		echo "  FAIL  {$placeholder} expected {$expected}, got {$actual}\n";
	}
}

// Do not leave test meta on a real order.
$order->delete_meta_data( '_moksafowo_shipping_cvs_store_name' );
$order->delete_meta_data( '_moksafowo_shipping_cvs_store_address' );

echo $failed > 0 ? "\n{$failed} failed\n" : "\nbridge wired correctly\n";
