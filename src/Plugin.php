<?php
/**
 * Plugin bootstrap.
 *
 * Modules are registered here and booted on `plugins_loaded`. 1.x instantiated
 * every class inside the main file's constructor, which meant hooks were added
 * before WordPress had loaded translations or WooCommerce, and the activation
 * hook was registered from inside a constructor where it never reliably fired.
 *
 * @package Mofoline
 */

namespace Mofoline;

use Mofoline\Support\Db;
use Mofoline\Support\Logger;
use Mofoline\Support\Migrator;
use Mofoline\Support\Options;

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
		add_action( 'init', array( Migrator::class, 'maybe_upgrade' ), 1 );

		add_action( 'mofoline_daily_maintenance', array( $this, 'run_maintenance' ) );
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
		$modules = apply_filters( 'mofoline_modules', $modules );

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
		do_action( 'mofoline_loaded', $this );
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
		Logger::purge();

		$sessions = Migrator::table( 'flow_sessions' );
		Db::query( Db::prepare( "DELETE FROM %i WHERE expires_at < %s", $sessions, current_time( 'mysql', true ) ) );

		if ( class_exists( Woo\NotifyHistory::class ) ) {
			Woo\NotifyHistory::purge( (int) Options::get( 'woo_history_days' ) );
		}

		$retention = (int) Options::get( 'inbox_retention_days' );

		if ( $retention > 0 ) {
			$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $retention * DAY_IN_SECONDS ) );
			$messages = Migrator::table( 'messages' );
			$events   = Migrator::table( 'events' );

			Db::query( Db::prepare( "DELETE FROM %i WHERE created_at < %s", $messages, $cutoff ) );
			Db::query( Db::prepare( "DELETE FROM %i WHERE received_at < %s AND status = 'done'", $events, $cutoff ) );
		}
	}

	/**
	 * Activation: build the schema and schedule maintenance.
	 */
	public static function activate(): void {
		Migrator::maybe_upgrade( true );

		if ( ! wp_next_scheduled( 'mofoline_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mofoline_daily_maintenance' );
		}

		flush_rewrite_rules();
	}

	/**
	 * Deactivation: stop cron, leave data alone.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'mofoline_daily_maintenance' );
		wp_clear_scheduled_hook( 'mofoline_process_events' );

		// So that re-activating rebuilds the account endpoint's rule rather
		// than trusting a flag from a previous install.
		delete_option( 'mofoline_account_endpoint' );

		flush_rewrite_rules();
	}
}
