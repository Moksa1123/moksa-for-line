<?php
/**
 * Rebuild a 1.4.0-shaped install, then run the v2 migrator over it and assert
 * that nothing was lost or corrupted.
 *
 * This is destructive: it drops and recreates every plugin table. Run it only
 * against a throwaway site.
 *
 *   wp eval-file tests/migration-check.php
 *
 * @package Moksa\Line
 */

global $wpdb;

$prefix  = $wpdb->prefix . 'moksa_line_';
$collate = $wpdb->get_charset_collate();

/*
 * This drops every table this plugin owns. The docblock has always said to run
 * it only on a throwaway site, and that was not enough: the identical warning
 * sat on notify-check, which was given a real guard, while this one -- the more
 * destructive of the two -- was left with only a comment. Run against a site
 * holding real conversations it destroys every message, and LINE offers no way
 * to read chat history back.
 */
if ( 'production' === wp_get_environment_type() && ! defined( 'MOKSA_LINE_ALLOW_DESTRUCTIVE_TESTS' ) ) {
	echo "REFUSED: this site reports WP_ENVIRONMENT_TYPE=production.\n";
	echo "It DROPS every moksa_line_* table, including recorded conversations and\n";
	echo "messages, which cannot be recovered -- LINE does not expose chat history.\n";
	echo "If this really is a throwaway site, set WP_ENVIRONMENT_TYPE, or define\n";
	echo "MOKSA_LINE_ALLOW_DESTRUCTIVE_TESTS in wp-config.php, and run it again.\n";
	return;
}

echo "== Tearing down and recreating a 1.4.0 install ==\n";

foreach ( Moksa\Line\Support\Migrator::table_keys() as $t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$t}" );
}

// The exact four tables 1.4.0 created, with its column names.
$wpdb->query(
	"CREATE TABLE {$prefix}users (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		wp_user_id bigint(20) NOT NULL DEFAULT 0,
		line_user_id varchar(255) NOT NULL,
		display_name varchar(255) NOT NULL,
		picture_url varchar(255) NOT NULL,
		status_message text NOT NULL,
		email varchar(255) NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY line_user_id (line_user_id)
	) {$collate}"
);

$wpdb->query(
	"CREATE TABLE {$prefix}quick_replies (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		keyword varchar(255) NOT NULL,
		reply_message text NOT NULL,
		status tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id)
	) {$collate}"
);

$wpdb->query(
	"CREATE TABLE {$prefix}auto_replies (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		keyword varchar(255) NOT NULL,
		reply_type varchar(50) NOT NULL DEFAULT 'text',
		reply_content text NOT NULL,
		match_type varchar(20) NOT NULL DEFAULT 'exact',
		status tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id)
	) {$collate}"
);

$wpdb->query(
	"CREATE TABLE {$prefix}imagemaps (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		title varchar(255) NOT NULL,
		alt_text varchar(255) NOT NULL,
		base_url varchar(255) NOT NULL,
		actions text NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id)
	) {$collate}"
);

// v1 data.
$wpdb->query( "INSERT INTO {$prefix}users (wp_user_id, line_user_id, display_name, picture_url, status_message, email) VALUES (1, 'Ulegacy0001', 'Legacy Person', 'https://example.com/a.jpg', 'hi', 'legacy@example.com')" );
$wpdb->query( "INSERT INTO {$prefix}users (wp_user_id, line_user_id, display_name, picture_url, status_message, email) VALUES (0, 'Ulegacy0002', 'Second Person', '', '', '')" );
$wpdb->query( "INSERT INTO {$prefix}auto_replies (keyword, reply_type, reply_content, match_type, status) VALUES ('hours', 'text', 'We open at 9am.', 'exact', 1)" );
$wpdb->query( "INSERT INTO {$prefix}auto_replies (keyword, reply_type, reply_content, match_type, status) VALUES ('price', 'text', 'From NT\$500.', 'partial', 0)" );
$wpdb->query( "INSERT INTO {$prefix}quick_replies (keyword, reply_message, status) VALUES ('Book now', 'I would like to book', 1)" );

// v1 options: plaintext secrets, n8n-specific forwarding key.
update_option( 'moksa_line_channel_id', '1234567890' );
update_option( 'moksa_line_channel_secret', 'PLAINTEXT-login-secret' );
update_option( 'moksa_line_messaging_secret', 'PLAINTEXT-messaging-secret' );
update_option( 'moksa_line_messaging_token', 'PLAINTEXT-long-lived-token' );
update_option( 'moksa_line_n8n_webhook_url', 'https://n8n.example.com/webhook/line' );
// Clear the new-format keys so this exercises the import path rather than
// the guard that refuses to overwrite a deliberate setting.
foreach ( array( 'webhook_forward_url', 'woo_notify_delay', 'woo_tracking_delay', 'woo_tracking_retries' ) as $reset ) {
	Moksa\Line\Support\Options::delete( $reset );
}

update_option( 'moksa_line_order_delay', '30' );
update_option( 'moksa_line_order_processing_delay', '90' );
update_option( 'moksa_line_order_processing_max_retries', '5' );
delete_option( 'moksa_line_db_version' );
delete_option( 'moksa_line_webhook_forward_url' );

Moksa\Line\Support\Options::flush_cache();

echo "1.4.0 state built.\n\n";

echo "== Running the migrator ==\n";
Moksa\Line\Support\Migrator::maybe_upgrade( true );
Moksa\Line\Support\Options::flush_cache();
echo "Done.\n\n";

// ---------------------------------------------------------------- assertions

// wp-cli includes this inside a function, so a file-scope variable is not
// actually global here. $GLOBALS is explicit and works either way -- without
// it the summary reported success while individual checks were failing.
$GLOBALS['moksa_migration_failures'] = 0;

function check( $condition, $label, $detail = '' ) {

	if ( $condition ) {
		echo "  ok    {$label}\n";
		return;
	}

	++$GLOBALS['moksa_migration_failures'];
	echo "  FAIL  {$label}" . ( '' !== $detail ? " -- {$detail}" : '' ) . "\n";
}

echo "== Schema ==\n";
$tables   = $wpdb->get_col( "SHOW TABLES LIKE '{$prefix}%'" );
$expected = Moksa\Line\Support\Migrator::table_keys();

// Counted against the migrator's own list rather than a number typed here,
// which drifts the moment a table is added.
check(
	count( $expected ) === count( $tables ),
	sprintf( 'all %d tables present', count( $expected ) ),
	count( $tables ) . ' found'
);

$missing = array();

foreach ( $expected as $key ) {
	if ( ! in_array( $prefix . $key, $tables, true ) ) {
		$missing[] = $key;
	}
}

check( empty( $missing ), 'no table is missing', implode( ', ', $missing ) );

echo "\n== Auto replies: reply_content -> reply_data ==\n";
$rules = $wpdb->get_results( "SELECT * FROM {$prefix}auto_replies ORDER BY id" );
check( 2 === count( $rules ), 'both rules survived' );
check( 'We open at 9am.' === $rules[0]->reply_data, 'text carried into reply_data', $rules[0]->reply_data );
check( 1 === (int) $rules[0]->is_active, 'status carried into is_active' );
check( 0 === (int) $rules[1]->is_active, 'a disabled rule stays disabled' );
check( 'partial' === $rules[1]->match_type, 'match_type preserved' );

echo "\n== Quick replies: keyword/reply_message -> items JSON ==\n";
$sets = $wpdb->get_results( "SELECT * FROM {$prefix}quick_replies ORDER BY id" );
check( 1 === count( $sets ), 'the set survived' );
check( 'Book now' === $sets[0]->name, 'keyword became the set name', (string) $sets[0]->name );
$items = json_decode( (string) $sets[0]->items, true );
check( is_array( $items ) && 1 === count( $items ), 'items is a one-entry array' );
check( isset( $items[0]['action']['text'] ) && 'I would like to book' === $items[0]['action']['text'], 'the reply text became the action text' );
check( isset( $items[0]['action']['label'] ) && 'Book now' === $items[0]['action']['label'], 'the keyword became the button label' );

echo "\n== Credentials encrypted in place ==\n";
foreach ( array( 'channel_secret', 'messaging_secret', 'messaging_token' ) as $key ) {
	$stored = get_option( 'moksa_line_' . $key );
	check( Moksa\Line\Support\Crypto::is_encrypted( (string) $stored ), "{$key} is now ciphertext" );
}
check( 'PLAINTEXT-login-secret' === Moksa\Line\Support\Options::get( 'channel_secret' ), 'channel_secret still reads back correctly' );
check( 'PLAINTEXT-messaging-secret' === Moksa\Line\Support\Options::get( 'messaging_secret' ), 'messaging_secret still reads back correctly' );
check( '1234567890' === Moksa\Line\Support\Options::get( 'channel_id' ), 'non-secret settings untouched' );

echo "\n== Forwarding URL moved off the n8n-specific key ==\n";
check(
	'https://n8n.example.com/webhook/line' === Moksa\Line\Support\Options::get( 'webhook_forward_url' ),
	'n8n url carried into webhook_forward_url',
	(string) Moksa\Line\Support\Options::get( 'webhook_forward_url' )
);

echo "
== Renamed settings carried over ==
";
check( 30 === Moksa\Line\Support\Options::get( 'woo_notify_delay' ), 'order delay carried over', (string) Moksa\Line\Support\Options::get( 'woo_notify_delay' ) );
check( 90 === Moksa\Line\Support\Options::get( 'woo_tracking_delay' ), 'processing delay carried over', (string) Moksa\Line\Support\Options::get( 'woo_tracking_delay' ) );
check( 5 === Moksa\Line\Support\Options::get( 'woo_tracking_retries' ), 'retry budget carried over', (string) Moksa\Line\Support\Options::get( 'woo_tracking_retries' ) );

echo "\n== Users preserved and inbox seeded ==\n";
$users = $wpdb->get_results( "SELECT * FROM {$prefix}users ORDER BY id" );
check( 2 === count( $users ), 'both LINE users survived' );
check( 'Legacy Person' === $users[0]->display_name, 'display name preserved' );
check( 1 === (int) $users[0]->wp_user_id, 'account binding preserved' );
check( '0000-00-00 00:00:00' !== $users[0]->created_at && '' !== $users[0]->created_at, 'created_at backfilled', (string) $users[0]->created_at );

$conversations = $wpdb->get_results( "SELECT * FROM {$prefix}conversations ORDER BY id" );
check( 2 === count( $conversations ), 'a conversation was seeded per known user', count( $conversations ) . ' found' );
check( 'Legacy Person' === $conversations[0]->display_name, 'seeded conversation carries the name' );

echo "\n== Version recorded ==\n";
check(
	Moksa\Line\Support\Migrator::DB_VERSION === Moksa\Line\Support\Options::get( 'db_version' ),
	'db_version matches Migrator::DB_VERSION',
	(string) Moksa\Line\Support\Options::get( 'db_version' )
);

echo "\n== Re-running the migrator must be harmless ==\n";
Moksa\Line\Support\Migrator::maybe_upgrade( true );
Moksa\Line\Support\Options::flush_cache();
$conversations_again = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}conversations" );
check( 2 === $conversations_again, 'conversations not duplicated on a second run', $conversations_again . ' found' );
check( 'PLAINTEXT-messaging-secret' === Moksa\Line\Support\Options::get( 'messaging_secret' ), 'secrets not double-encrypted' );

$failures = (int) $GLOBALS['moksa_migration_failures'];

echo "\n";

// A plain exit() is swallowed by wp-cli's eval-file, so a failing run would
// still report success to anything checking the status code. WP_CLI::error()
// is the only thing that exits non-zero here, which is what makes this usable
// as a gate rather than something a human has to remember to read.
if ( $failures > 0 ) {
	echo "{$failures} FAILURE(S)\n";

	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::error( sprintf( '%d migration check(s) failed.', $failures ) );
	}

	exit( 1 );
}

echo "MIGRATION OK\n";
