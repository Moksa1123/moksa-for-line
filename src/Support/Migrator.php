<?php
/**
 * Versioned database schema.
 *
 * The plugin this one replaces -- Moksa LINE Login -- created four tables
 * while its code wrote to nine, and the column names it created did not match
 * the ones it read. This class owns the whole schema in one place and runs on
 * every load when the stored version is behind, so schema changes never depend
 * on the activation hook firing.
 *
 * It also imports that plugin's data. That plugin kept everything under the
 * moksa_line_ prefix; this one uses mofoline_, so the import begins by
 * copying options, tables and user meta across, and then upgrades the copies
 * in place.
 *
 * @package Mofoline
 */

namespace Mofoline\Support;

defined( 'ABSPATH' ) || exit;

class Migrator {

	/**
	 * Schema version. Deliberately independent of the plugin version: the
	 * plugin can ship many releases without the tables changing, and this only
	 * moves when they do.
	 */
	const DB_VERSION = '1.0.4';

	/** The option, table and user-meta prefix of the plugin this one replaces. */
	const LEGACY_PREFIX = 'moksa_line_';

	/**
	 * Run dbDelta when the stored version is behind the code version.
	 *
	 * @param bool $force Run regardless of the stored version.
	 */
	public static function maybe_upgrade( bool $force = false ): void {
		$installed = (string) Options::get( 'db_version' );

		// The version check is the fast path, but it only protects against
		// schema changes that were remembered to bump DB_VERSION. Checking that
		// the tables actually exist turns "someone forgot to bump it" from a
		// feature that silently does nothing into a self-healing no-op.
		if ( ! $force && version_compare( $installed, self::DB_VERSION, '>=' ) && ! self::tables_missing() ) {
			return;
		}

		// Copies first, so that dbDelta upgrades the copied tables the same way
		// it upgrades everything else.
		if ( '' === $installed || '0' === $installed ) {
			self::copy_from_legacy();
		}

		self::install();
		self::migrate_from_legacy( $installed );

		Options::set( 'db_version', self::DB_VERSION );
	}

	/**
	 * Every logical table this plugin owns.
	 *
	 * @return string[]
	 */
	public static function table_keys(): array {
		return array(
			'users', 'events', 'conversations', 'messages', 'auto_replies',
			'quick_replies', 'flex', 'richmenus', 'flows', 'flow_sessions',
			'flow_submissions', 'payments', 'imagemaps', 'templates', 'notify_history', 'logs',
		);
	}

	/**
	 * Whether any table is absent.
	 *
	 * Cached, because this runs on every load and the answer is almost always
	 * "no". The cache is short enough that a dropped table heals within the
	 * hour without anyone reinstalling the plugin.
	 */
	private static function tables_missing(): bool {
		$cached = get_transient( 'mofoline_schema_ok' );

		if ( '1' === $cached ) {
			return false;
		}

		$prefix = Db::prefix() . 'mofoline_';

		$found = (array) Db::get_col( Db::prepare( 'SHOW TABLES LIKE %s', Db::esc_like( $prefix ) . '%' ) );

		$expected = array();

		foreach ( self::table_keys() as $key ) {
			$expected[] = $prefix . $key;
		}

		$missing = array_diff( $expected, $found );

		if ( empty( $missing ) ) {
			set_transient( 'mofoline_schema_ok', '1', HOUR_IN_SECONDS );

			return false;
		}

		return true;
	}

	/**
	 * Create or update every table.
	 */
	public static function install(): void {
		delete_transient( 'mofoline_schema_ok' );

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( self::schemas() as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Fully qualified table name for a logical table key.
	 */
	public static function table( string $key ): string {
		return Db::prefix() . 'mofoline_' . $key;
	}

	/**
	 * Every CREATE TABLE statement, in dbDelta's required formatting
	 * (two spaces after PRIMARY KEY, lowercase types, no backticks).
	 *
	 * @return string[]
	 */
	public static function schemas(): array {
		$collate = Db::charset_collate();

		$t = function ( $key ) {
			return self::table( $key );
		};

		return array(

			// Identity: one row per LINE user we have ever seen, whether or not
			// they are bound to a WordPress account.
			"CREATE TABLE {$t( 'users' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				line_user_id varchar(64) NOT NULL,
				wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				display_name varchar(255) NOT NULL DEFAULT '',
				picture_url varchar(512) NOT NULL DEFAULT '',
				status_message varchar(512) NOT NULL DEFAULT '',
				email varchar(255) NOT NULL DEFAULT '',
				language varchar(16) NOT NULL DEFAULT '',
				is_friend tinyint(1) NOT NULL DEFAULT 0,
				followed_at datetime DEFAULT NULL,
				unfollowed_at datetime DEFAULT NULL,
				last_login_at datetime DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY line_user_id (line_user_id),
				KEY wp_user_id (wp_user_id),
				KEY is_friend (is_friend)
			) {$collate};",

			// Raw webhook events. webhook_event_id is unique so LINE's retries
			// cannot make us process the same event twice.
			"CREATE TABLE {$t( 'events' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				webhook_event_id varchar(64) NOT NULL DEFAULT '',
				event_type varchar(40) NOT NULL DEFAULT '',
				source_type varchar(20) NOT NULL DEFAULT '',
				source_id varchar(64) NOT NULL DEFAULT '',
				reply_token varchar(64) NOT NULL DEFAULT '',
				payload longtext NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				attempts smallint(5) unsigned NOT NULL DEFAULT 0,
				error text NULL,
				received_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				processed_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY webhook_event_id (webhook_event_id),
				KEY status_received (status,received_at),
				KEY source_id (source_id)
			) {$collate};",

			// Customer-service inbox: one conversation per LINE user.
			"CREATE TABLE {$t( 'conversations' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				line_user_id varchar(64) NOT NULL,
				display_name varchar(255) NOT NULL DEFAULT '',
				picture_url varchar(512) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'bot',
				assignee_id bigint(20) unsigned NOT NULL DEFAULT 0,
				unread_count int(10) unsigned NOT NULL DEFAULT 0,
				last_message_preview varchar(255) NOT NULL DEFAULT '',
				last_message_at datetime DEFAULT NULL,
				last_inbound_at datetime DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY line_user_id (line_user_id),
				KEY status_last (status,last_message_at),
				KEY assignee_id (assignee_id)
			) {$collate};",

			"CREATE TABLE {$t( 'messages' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				conversation_id bigint(20) unsigned NOT NULL DEFAULT 0,
				line_user_id varchar(64) NOT NULL DEFAULT '',
				direction varchar(8) NOT NULL DEFAULT 'in',
				message_type varchar(24) NOT NULL DEFAULT 'text',
				body text NULL,
				payload longtext NULL,
				line_message_id varchar(64) NOT NULL DEFAULT '',
				sender_wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				sender_kind varchar(16) NOT NULL DEFAULT 'user',
				status varchar(16) NOT NULL DEFAULT 'sent',
				error text NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY conversation_created (conversation_id,created_at),
				KEY line_message_id (line_message_id)
			) {$collate};",

			"CREATE TABLE {$t( 'auto_replies' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL DEFAULT '',
				keyword varchar(255) NOT NULL DEFAULT '',
				match_type varchar(20) NOT NULL DEFAULT 'exact',
				reply_type varchar(24) NOT NULL DEFAULT 'text',
				reply_data longtext NULL,
				priority int(11) NOT NULL DEFAULT 10,
				is_active tinyint(1) NOT NULL DEFAULT 1,
				hit_count bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY active_priority (is_active,priority)
			) {$collate};",

			"CREATE TABLE {$t( 'quick_replies' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL DEFAULT '',
				items longtext NULL,
				is_active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id)
			) {$collate};",

			"CREATE TABLE {$t( 'flex' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL DEFAULT '',
				alt_text varchar(1500) NOT NULL DEFAULT '',
				contents longtext NULL,
				category varchar(60) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY category (category)
			) {$collate};",

			// Rich menus, including the alias id that powers tabbed menus.
			"CREATE TABLE {$t( 'richmenus' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				richmenu_id varchar(64) NOT NULL DEFAULT '',
				alias_id varchar(64) NOT NULL DEFAULT '',
				name varchar(191) NOT NULL DEFAULT '',
				chat_bar_text varchar(14) NOT NULL DEFAULT '',
				size varchar(10) NOT NULL DEFAULT 'full',
				selected tinyint(1) NOT NULL DEFAULT 0,
				areas longtext NULL,
				image_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
				tab_group varchar(64) NOT NULL DEFAULT '',
				tab_order int(11) NOT NULL DEFAULT 0,
				is_default tinyint(1) NOT NULL DEFAULT 0,
				synced_at datetime DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY richmenu_id (richmenu_id),
				KEY tab_group_order (tab_group,tab_order)
			) {$collate};",

			// Scenario bot: definition plus one live session row per user.
			"CREATE TABLE {$t( 'flows' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL DEFAULT '',
				trigger_type varchar(20) NOT NULL DEFAULT 'keyword',
				trigger_value varchar(255) NOT NULL DEFAULT '',
				definition longtext NULL,
				on_complete varchar(24) NOT NULL DEFAULT 'store',
				notify_email varchar(255) NOT NULL DEFAULT '',
				is_active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY trigger_lookup (is_active,trigger_type)
			) {$collate};",

			"CREATE TABLE {$t( 'flow_sessions' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				line_user_id varchar(64) NOT NULL,
				flow_id bigint(20) unsigned NOT NULL DEFAULT 0,
				step_index int(11) NOT NULL DEFAULT 0,
				answers longtext NULL,
				expires_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY line_user_id (line_user_id),
				KEY expires_at (expires_at)
			) {$collate};",

			"CREATE TABLE {$t( 'flow_submissions' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				flow_id bigint(20) unsigned NOT NULL DEFAULT 0,
				line_user_id varchar(64) NOT NULL DEFAULT '',
				display_name varchar(255) NOT NULL DEFAULT '',
				answers longtext NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY flow_created (flow_id,created_at)
			) {$collate};",

			// LINE Pay transactions. transaction_id is a 19-digit integer, so it
			// is stored as a string to survive 32-bit PHP and JSON round-trips.
			"CREATE TABLE {$t( 'payments' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				order_ref varchar(120) NOT NULL DEFAULT '',
				wc_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source varchar(20) NOT NULL DEFAULT 'woocommerce',
				transaction_id varchar(32) NOT NULL DEFAULT '',
				amount decimal(18,4) NOT NULL DEFAULT 0,
				refunded decimal(18,4) NOT NULL DEFAULT 0,
				currency varchar(8) NOT NULL DEFAULT 'TWD',
				status varchar(24) NOT NULL DEFAULT 'created',
				payment_url text NULL,
				line_user_id varchar(64) NOT NULL DEFAULT '',
				reg_key varchar(255) NOT NULL DEFAULT '',
				raw longtext NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY order_ref (order_ref),
				KEY transaction_id (transaction_id),
				KEY wc_order_id (wc_order_id),
				KEY status (status)
			) {$collate};",

			"CREATE TABLE {$t( 'templates' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL DEFAULT '',
				alt_text varchar(1500) NOT NULL DEFAULT '',
				kind varchar(20) NOT NULL DEFAULT 'buttons',
				definition longtext NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY kind (kind)
			) {$collate};",

			"CREATE TABLE {$t( 'imagemaps' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL DEFAULT '',
				alt_text varchar(1500) NOT NULL DEFAULT '',
				base_url varchar(512) NOT NULL DEFAULT '',
				image_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
				base_width int(11) NOT NULL DEFAULT 1040,
				base_height int(11) NOT NULL DEFAULT 1040,
				actions longtext NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id)
			) {$collate};",

			// What was sent to which customer about which order, and whether
			// LINE accepted it.
			"CREATE TABLE {$t( 'notify_history' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				line_user_id varchar(64) NOT NULL DEFAULT '',
				recipient varchar(255) NOT NULL DEFAULT '',
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				template_id bigint(20) unsigned NOT NULL DEFAULT 0,
				order_status varchar(40) NOT NULL DEFAULT '',
				channel varchar(20) NOT NULL DEFAULT 'line',
				content longtext NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				error text NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				settled_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY order_id (order_id),
				KEY template_id (template_id),
				KEY status_created (status,created_at),
				KEY line_user_id (line_user_id)
			) {$collate};",

			"CREATE TABLE {$t( 'logs' )} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				level varchar(10) NOT NULL DEFAULT 'error',
				channel varchar(40) NOT NULL DEFAULT 'general',
				message varchar(500) NOT NULL DEFAULT '',
				context longtext NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY level_created (level,created_at),
				KEY channel (channel)
			) {$collate};",
		);
	}

	/**
	 * Bring the old plugin's options, tables and user meta under this prefix.
	 *
	 * Runs once, before the schema is installed, and only on a site that has
	 * never recorded a schema version. Nothing is moved: the old rows stay
	 * where they were, so the old plugin keeps working until it is removed,
	 * and running this again finds every target already present and does
	 * nothing.
	 */
	private static function copy_from_legacy(): void {
		$legacy_prefix = Db::prefix() . self::LEGACY_PREFIX;

		// Tables: a copy with the old plugin's own columns; dbDelta then brings
		// each up to the current schema and migrate_from_legacy() fills the
		// columns that were renamed.
		foreach ( self::table_keys() as $key ) {
			$legacy = $legacy_prefix . $key;
			$target = self::table( $key );

			if ( ! self::table_exists( $legacy ) || self::table_exists( $target ) ) {
				continue;
			}

			Db::query( Db::prepare( 'CREATE TABLE %i LIKE %i', $target, $legacy ) );
			Db::query( Db::prepare( 'INSERT INTO %i SELECT * FROM %i', $target, $legacy ) );
		}

		// Options: every moksa_line_* option that has no mofoline_* counterpart
		// yet. The schema version is left behind on purpose, so the upgrade
		// steps still run against the copied tables.
		$options = Db::core_table( 'options' );
		$rows    = Db::get_results(
			Db::prepare(
				'SELECT option_name, option_value FROM %i WHERE option_name LIKE %s',
				$options,
				Db::esc_like( self::LEGACY_PREFIX ) . '%'
			)
		);

		foreach ( $rows as $row ) {
			$key = substr( (string) $row->option_name, strlen( self::LEGACY_PREFIX ) );

			if ( 'db_version' === $key || false !== get_option( Options::PREFIX . $key, false ) ) {
				continue;
			}

			add_option( Options::PREFIX . $key, maybe_unserialize( $row->option_value ), '', false );
		}

		// User meta: the account binding, the cached avatar and the member code.
		$usermeta = Db::core_table( 'usermeta' );

		foreach ( array( 'user_id', 'avatar', 'member_code' ) as $suffix ) {
			$old = ( 'member_code' === $suffix ? '_' : '' ) . self::LEGACY_PREFIX . $suffix;
			$new = ( 'member_code' === $suffix ? '_' : '' ) . Options::PREFIX . $suffix;

			Db::query(
				Db::prepare(
					'INSERT INTO %i (user_id, meta_key, meta_value)
					 SELECT m.user_id, %s, m.meta_value FROM %i m
					 WHERE m.meta_key = %s
					   AND NOT EXISTS ( SELECT 1 FROM %i n WHERE n.user_id = m.user_id AND n.meta_key = %s )',
					$usermeta,
					$new,
					$usermeta,
					$old,
					$usermeta,
					$new
				)
			);
		}

		// The old plugin's daily job is not ours to run.
		wp_clear_scheduled_hook( self::LEGACY_PREFIX . 'daily_maintenance' );
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $table Fully qualified table name.
	 */
	private static function table_exists( string $table ): bool {
		return (string) Db::get_var( Db::prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Import data left behind by the Moksa LINE Login plugin this one replaces.
	 *
	 * By the time this runs, copy_from_legacy() has brought that plugin's
	 * options and tables under this prefix and dbDelta has added the columns
	 * the current schema needs. What is left is the data inside: secrets in
	 * the clear, settings under old names, columns that were renamed. The
	 * import is guarded by the stored schema version and every step is
	 * written to be safe to repeat, because a half-finished upgrade must be
	 * resumable.
	 *
	 * @param string $from Previously installed db_version ('0' when there is none).
	 */
	private static function migrate_from_legacy( string $from ): void {
		// Anything already on the current schema has nothing legacy to import.
		if ( version_compare( $from, self::DB_VERSION, '>=' ) ) {
			return;
		}

		// 1. Secrets were stored in the clear and must be encrypted.
		foreach ( array( 'channel_secret', 'messaging_secret', 'messaging_token', 'pay_channel_secret' ) as $key ) {
			$raw = get_option( Options::PREFIX . $key, '' );

			if ( is_string( $raw ) && '' !== $raw && ! Crypto::is_encrypted( $raw ) ) {
				update_option( Options::PREFIX . $key, Crypto::encrypt( $raw ), false );
			}
		}

		// 2. Settings the old plugin kept under different names. Copied only
		//    when the new key is still at its default, so a deliberate choice
		//    made here is never overwritten by an old one.
		$renamed = array(
			Options::PREFIX . 'n8n_webhook_url'             => 'webhook_forward_url',
			Options::PREFIX . 'order_delay'                 => 'woo_notify_delay',
			Options::PREFIX . 'order_processing_delay'      => 'woo_tracking_delay',
			Options::PREFIX . 'order_processing_max_retries' => 'woo_tracking_retries',
		);

		$schema = Options::schema();

		foreach ( $renamed as $legacy_key => $new_key ) {
			$legacy_value = get_option( $legacy_key, null );

			if ( null === $legacy_value || '' === $legacy_value ) {
				continue;
			}

			$default = isset( $schema[ $new_key ] ) ? $schema[ $new_key ]['default'] : '';

			if ( Options::get( $new_key ) === $default ) {
				Options::set( $new_key, $legacy_value );
			}
		}

		// 2b. The login button's shape is the theme's business now: corner
		//     radius, width and height are gone as settings. A shop that had
		//     stretched the button to the full width meant something by it, so
		//     that one choice carries over to the alignment setting that
		//     replaced it. The rest simply stop applying.
		$legacy_width = get_option( Options::PREFIX . 'button_width', '' );

		if ( is_string( $legacy_width ) && '100%' === trim( $legacy_width ) && 'start' === Options::get( 'button_align' ) ) {
			Options::set( 'button_align', 'full' );
		}

		foreach ( array( 'button_border_radius', 'button_width', 'button_height' ) as $retired ) {
			delete_option( Options::PREFIX . $retired );
		}

		// 3. The old auto-reply table used reply_content; the code wrote
		//    reply_data. Copy whichever column actually holds data.
		$auto = self::table( 'auto_replies' );

		if ( self::column_exists( $auto, 'reply_content' ) ) {
			Db::query( Db::prepare( "UPDATE %i SET reply_data = reply_content WHERE (reply_data IS NULL OR reply_data = '') AND reply_content <> ''", $auto ) );
		}

		if ( self::column_exists( $auto, 'status' ) ) {
			Db::query( Db::prepare( "UPDATE %i SET is_active = status", $auto ) );
		}

		// 4. The old quick-reply table stored a single keyword/reply pair.
		//    Fold those into the new items JSON so nothing is silently lost.
		$quick = self::table( 'quick_replies' );

		if ( self::column_exists( $quick, 'reply_message' ) && self::column_exists( $quick, 'keyword' ) ) {
			$rows = Db::get_results( Db::prepare( "SELECT id, keyword, reply_message FROM %i WHERE (items IS NULL OR items = '')", $quick ) );

			foreach ( (array) $rows as $row ) {
				$items = array(
					array(
						'type'   => 'action',
						'action' => array(
							'type'  => 'message',
							'label' => mb_substr( (string) $row->keyword, 0, 20 ),
							'text'  => (string) $row->reply_message,
						),
					),
				);

				Db::update(
					$quick,
					array(
						'name'  => (string) $row->keyword,
						'items' => wp_json_encode( $items ),
					),
					array( 'id' => (int) $row->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		}

		// 5. Backfill created_at/updated_at on rows migrated from tables whose
		//    defaults were CURRENT_TIMESTAMP.
		$now = current_time( 'mysql', true );

		foreach ( array( 'users', 'auto_replies', 'quick_replies', 'imagemaps', 'richmenus' ) as $key ) {
			$table = self::table( $key );
			Db::query( Db::prepare( "UPDATE %i SET updated_at = %s WHERE updated_at = '0000-00-00 00:00:00'", $table, $now ) );
			Db::query( Db::prepare( "UPDATE %i SET created_at = %s WHERE created_at = '0000-00-00 00:00:00'", $table, $now ) );
		}

		// 6. Seed the inbox from any LINE users we already know about, so the
		//    conversation list is not empty on first open.
		$users         = self::table( 'users' );
		$conversations = self::table( 'conversations' );

		Db::query(
			Db::prepare(
				"INSERT IGNORE INTO %i
					(line_user_id, display_name, picture_url, status, created_at, updated_at)
				 SELECT line_user_id, display_name, picture_url, 'bot', %s, %s FROM %i",
				$conversations,
				$now,
				$now,
				$users
			)
		);

		// 7. The old plugin kept notification history in its own table, under a
		//    different prefix, created outside its installer. Copy it across so
		//    the record of what customers were told is not lost, and so
		//    uninstall can actually clean it up.
		$legacy_history = Db::prefix() . 'moksa_notify_history';

		$exists = Db::get_var( Db::prepare( 'SHOW TABLES LIKE %s', $legacy_history ) );

		if ( $exists ) {
			$history = self::table( 'notify_history' );

			// The old rows recorded a status of 'failed' even for successful
			// sends, because the code checked for a 'status' key the LINE API
			// does not return. That cannot be reconstructed after the fact, so
			// they are imported as 'unknown' rather than as lies.
			Db::query(
				Db::prepare(
				"INSERT INTO %i
					(wp_user_id, recipient, order_id, template_id, order_status, channel, content, status, error, created_at)
				 SELECT
					COALESCE(user_id, 0),
					COALESCE(user_info, ''),
					COALESCE(order_id, 0),
					COALESCE(notify_id, 0),
					'',
					COALESCE(notify_type, 'line'),
					notify_content,
					'unknown',
					error_message,
					COALESCE(notify_time, UTC_TIMESTAMP())
				 FROM %i",
				$history,
				$legacy_history
				)
			);

			$imported = (int) Db::rows_affected();

			if ( $imported > 0 ) {
				Logger::info(
					'Imported notification history from the previous plugin',
					array( 'rows' => $imported ),
					'migrator'
				);
			}
		}

		Logger::info( 'Upgraded schema from ' . $from . ' to ' . self::DB_VERSION, array(), 'migrator' );
	}

	/**
	 * Whether a column exists, used to make v1 migrations idempotent.
	 */
	private static function column_exists( string $table, string $column ): bool {
		$found = Db::get_var( Db::prepare( "SHOW COLUMNS FROM %i LIKE %s", $table, $column ) );

		return ! empty( $found );
	}

	/**
	 * Drop every plugin table. Only ever called from uninstall.php.
	 */
	public static function drop_all(): void {
		foreach ( self::table_keys() as $key ) {
			$table = self::table( $key );
			Db::query( Db::prepare( "DROP TABLE IF EXISTS %i", $table ) );
		}
	}
}
