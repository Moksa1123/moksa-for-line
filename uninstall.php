<?php
/**
 * Uninstall: remove plugin data only when the operator has opted in.
 *
 * Deleting conversation history and payment records by default would destroy
 * evidence a shop may legally need, so nothing is removed unless
 * MOKSA_LINE_REMOVE_DATA is defined as true in wp-config.php.
 *
 * @package Moksa\Line
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'MOKSA_LINE_REMOVE_DATA' ) || ! MOKSA_LINE_REMOVE_DATA ) {
	return;
}

require_once __DIR__ . '/src/Support/Migrator.php';
require_once __DIR__ . '/src/Support/Options.php';
require_once __DIR__ . '/src/Support/Crypto.php';

Moksa\Line\Support\Migrator::drop_all();

global $wpdb;

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'moksa_line_%'" );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('moksa_line_user_id', 'moksa_line_avatar')" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mlline_%' OR option_name LIKE '_transient_timeout_mlline_%'" );

wp_clear_scheduled_hook( 'moksa_line_daily_maintenance' );
wp_clear_scheduled_hook( 'moksa_line_process_events' );
wp_clear_scheduled_hook( 'moksa_line_reconcile_payments' );
