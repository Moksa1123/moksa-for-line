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

require_once __DIR__ . '/src/Support/Statement.php';
require_once __DIR__ . '/src/Support/Db.php';
require_once __DIR__ . '/src/Support/Migrator.php';
require_once __DIR__ . '/src/Support/Options.php';
require_once __DIR__ . '/src/Support/Crypto.php';

use Moksa\Line\Support\Db;
use Moksa\Line\Support\Migrator;

Migrator::drop_all();

Db::query( Db::prepare( 'DELETE FROM %i WHERE option_name LIKE %s', Db::core_table( 'options' ), 'moksa_line_%' ) );
Db::query( Db::prepare( 'DELETE FROM %i WHERE meta_key IN (%s, %s, %s)', Db::core_table( 'usermeta' ), 'moksa_line_user_id', 'moksa_line_avatar', '_moksa_line_member_code' ) );
Db::query( Db::prepare( 'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s', Db::core_table( 'options' ), '_transient_mlline_%', '_transient_timeout_mlline_%' ) );

wp_clear_scheduled_hook( 'moksa_line_daily_maintenance' );
wp_clear_scheduled_hook( 'moksa_line_process_events' );
wp_clear_scheduled_hook( 'moksa_line_reconcile_payments' );
