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
		add_action( 'plugins_loaded', array( $this, 'load_modules' ), 5 );
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'init', array( Migrator::class, 'maybe_upgrade' ), 1 );

		add_action( 'moksa_line_daily_maintenance', array( $this, 'run_maintenance' ) );
	}

	/**
	 * Load translations bundled with the plugin.
	 *
	 * On `init`, not `plugins_loaded`: WordPress 6.7 warns when a translation
	 * is requested before init, and loading the domain early is what invites
	 * that. Translations from wordpress.org load automatically regardless;
	 * this call only covers the .mo files shipped in /languages.
	 */
	public function load_textdomain(): void {
		// Deliberately empty of a load_plugin_textdomain() call. WordPress has
		// loaded a plugin's translations by itself since 4.6, from both
		// wp-content/languages/plugins and the plugin's own Domain Path, at the
		// moment the first string is asked for. Calling it here did nothing
		// except force that work to happen earlier than it was needed.
		//
		// The hook stays so the timing remains obvious to the next reader, and
		// so anything that wants to add a language pack has somewhere to do it.
		do_action( 'moksa_line_load_textdomain' );
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
			'imagemap' => Imagemap\ImagemapModule::class,
			'template' => Template\TemplateModule::class,
			'liff'     => Liff\LiffModule::class,
			'shortcode' => Frontend\ShortcodeModule::class,
			'member'   => Member\MemberModule::class,
			'admin'    => Admin\AdminModule::class,
		);

		// The WooCommerce module checks for WooCommerce itself and does nothing
		// when it is absent, so it is always registered.
		$modules['woo'] = Woo\WooModule::class;

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

		if ( class_exists( Woo\NotifyHistory::class ) ) {
			Woo\NotifyHistory::purge( (int) Options::get( 'woo_history_days' ) );
		}

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

		// So that re-activating rebuilds the account endpoint's rule rather
		// than trusting a flag from a previous install.
		delete_option( 'moksa_line_account_endpoint' );

		flush_rewrite_rules();
	}
}
