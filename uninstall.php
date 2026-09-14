<?php
/**
 * Uninstall: remove plugin data only when the operator has opted in.
 *
 * Deleting conversation history and payment records by default would destroy
 * evidence a shop may legally need, so nothing is removed unless
 * MOFOLINE_REMOVE_DATA is defined as true in wp-config.php.
 *
 * @package Mofoline
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'MOFOLINE_REMOVE_DATA' ) || ! MOFOLINE_REMOVE_DATA ) {
	return;
}

require_once __DIR__ . '/src/Support/Statement.php';
require_once __DIR__ . '/src/Support/Db.php';
require_once __DIR__ . '/src/Support/Migrator.php';
require_once __DIR__ . '/src/Support/Options.php';
require_once __DIR__ . '/src/Support/Crypto.php';

use Mofoline\Support\Db;
use Mofoline\Support\Migrator;

Migrator::drop_all();

Db::query( Db::prepare( 'DELETE FROM %i WHERE option_name LIKE %s', Db::core_table( 'options' ), 'mofoline_%' ) );
Db::query( Db::prepare( 'DELETE FROM %i WHERE meta_key IN (%s, %s, %s)', Db::core_table( 'usermeta' ), 'mofoline_user_id', 'mofoline_avatar', '_mofoline_member_code' ) );
Db::query( Db::prepare( 'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s', Db::core_table( 'options' ), '_transient_mofoline_%', '_transient_timeout_mofoline_%' ) );

wp_clear_scheduled_hook( 'mofoline_daily_maintenance' );
wp_clear_scheduled_hook( 'mofoline_process_events' );
wp_clear_scheduled_hook( 'mofoline_reconcile_payments' );
