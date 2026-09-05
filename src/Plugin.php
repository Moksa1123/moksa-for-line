<?php
/**
 * Plugin bootstrap.
 *
 * Modules are registered here and booted on `plugins_loaded`. 1.x instantiated
 * every class inside the main file's constructor, which meant hooks were added
 * before WordPress had loaded translations or WooCommerce, and the activation
 * hook was registered from inside a constructor where it never reliably fired.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line;

use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Migrator;
use Moksa\Line\Support\Options;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Booted modules, keyed by their short name.
	 *
	 * @var array<string,object>
	 */
	private $modules = array();

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Wire the plugin into WordPress.
	 */
	public function boot(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 1 );
		add_action( 'plugins_loaded', array( $this, 'load_modules' ), 5 );
		add_action( 'init', array( Migrator::class, 'maybe_upgrade' ), 1 );

		add_action( 'moksa_line_daily_maintenance', array( $this, 'run_maintenance' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'moksa-line-login',
			false,
			dirname( MOKSA_LINE_BASENAME ) . '/languages'
		);
	}

	/**
	 * Instantiate every module that is switched on.
	 */
	public function load_modules(): void {
		$modules = array(
			'login'    => Login\LoginModule::class,
			'webhook'  => Webhook\WebhookModule::class,
			'bot'      => Bot\BotModule::class,
			'inbox'    => Inbox\InboxModule::class,
			'richmenu' => RichMenu\RichMenuModule::class,
			'flex'     => Flex\FlexModule::class,
			'liff'     => Liff\LiffModule::class,
			'shortcode' => Frontend\ShortcodeModule::class,
			'admin'    => Admin\AdminModule::class,
		);

		if ( Options::get( 'pay_enabled' ) ) {
			$modules['pay'] = Pay\PayModule::class;
		}

		/**
		 * Filter the module map before anything is instantiated.
		 *
		 * @param array<string,string> $modules Module name => class name.
		 */
		$modules = apply_filters( 'moksa_line_modules', $modules );

		foreach ( $modules as $name => $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$module = new $class();

			if ( method_exists( $module, 'register' ) ) {
				$module->register();
			}

			$this->modules[ $name ] = $module;
		}

		/**
		 * Fires once every module is registered.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'moksa_line_loaded', $this );
	}

	/**
	 * Retrieve a booted module.
	 *
	 * @param string $name Module key.
	 * @return object|null
	 */
	public function module( string $name ) {
		return isset( $this->modules[ $name ] ) ? $this->modules[ $name ] : null;
	}

	/**
	 * Daily housekeeping: trim logs, expire abandoned flow sessions, and drop
	 * inbox history past the retention window.
	 */
	public function run_maintenance(): void {
		global $wpdb;

		Logger::purge();

		$sessions = Migrator::table( 'flow_sessions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$sessions} WHERE expires_at < %s", current_time( 'mysql', true ) ) );

		$retention = (int) Options::get( 'inbox_retention_days' );

		if ( $retention > 0 ) {
			$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $retention * DAY_IN_SECONDS ) );
			$messages = Migrator::table( 'messages' );
			$events   = Migrator::table( 'events' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$messages} WHERE created_at < %s", $cutoff ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$events} WHERE received_at < %s AND status = 'done'", $cutoff ) );
		}
	}

	/**
	 * Activation: build the schema and schedule maintenance.
	 */
	public static function activate(): void {
		Migrator::maybe_upgrade( true );

		if ( ! wp_next_scheduled( 'moksa_line_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'moksa_line_daily_maintenance' );
		}

		flush_rewrite_rules();
	}

	/**
	 * Deactivation: stop cron, leave data alone.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'moksa_line_daily_maintenance' );
		wp_clear_scheduled_hook( 'moksa_line_process_events' );

		flush_rewrite_rules();
	}
}
