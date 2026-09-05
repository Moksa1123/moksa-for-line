<?php
/**
 * Leveled logging into a dedicated table.
 *
 * The 1.x plugin swallowed every API failure silently, which is why broken
 * rich menus looked like "nothing happened". Everything that talks to LINE
 * now records why it failed, and the admin can read it without SSH.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Support;

defined( 'ABSPATH' ) || exit;

class Logger {

	const DEBUG   = 'debug';
	const INFO    = 'info';
	const WARNING = 'warning';
	const ERROR   = 'error';

	/**
	 * Numeric severity used to compare against the configured threshold.
	 *
	 * @var array<string,int>
	 */
	private static $weights = array(
		'debug'   => 10,
		'info'    => 20,
		'warning' => 30,
		'error'   => 40,
		'off'     => 100,
	);

	/**
	 * Keys whose values must never reach the log table.
	 *
	 * @var string[]
	 */
	private static $redact = array(
		'access_token',
		'channel_secret',
		'client_secret',
		'id_token',
		'refresh_token',
		'authorization',
		'x-line-authorization',
		'password',
		'code',
	);

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'moksa_line_logs';
	}

	public static function debug( string $message, array $context = array(), string $channel = 'general' ): void {
		self::log( self::DEBUG, $message, $context, $channel );
	}

	public static function info( string $message, array $context = array(), string $channel = 'general' ): void {
		self::log( self::INFO, $message, $context, $channel );
	}

	public static function warning( string $message, array $context = array(), string $channel = 'general' ): void {
		self::log( self::WARNING, $message, $context, $channel );
	}

	public static function error( string $message, array $context = array(), string $channel = 'general' ): void {
		self::log( self::ERROR, $message, $context, $channel );
	}

	/**
	 * Record a WP_Error (or pass through when it is not one).
	 *
	 * @param mixed  $maybe_error Result to inspect.
	 * @param string $message     Human context.
	 * @param string $channel     Subsystem name.
	 * @return bool True when the value was an error.
	 */
	public static function capture( $maybe_error, string $message, string $channel = 'general' ): bool {
		if ( ! is_wp_error( $maybe_error ) ) {
			return false;
		}

		self::error(
			$message,
			array(
				'code'    => $maybe_error->get_error_code(),
				'detail'  => $maybe_error->get_error_message(),
				'data'    => $maybe_error->get_error_data(),
			),
			$channel
		);

		return true;
	}

	/**
	 * Write one log row, subject to the configured level.
	 *
	 * @param string $level   One of the level constants.
	 * @param string $message Message.
	 * @param array  $context Structured context, redacted before storage.
	 * @param string $channel Subsystem name (login, webhook, pay, richmenu...).
	 */
	public static function log( string $level, string $message, array $context = array(), string $channel = 'general' ): void {
		$threshold = (string) Options::get( 'log_level' );

		if ( 'off' === $threshold ) {
			return;
		}

		$level_weight     = isset( self::$weights[ $level ] ) ? self::$weights[ $level ] : 40;
		$threshold_weight = isset( self::$weights[ $threshold ] ) ? self::$weights[ $threshold ] : 40;

		if ( $level_weight < $threshold_weight ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- dedicated plugin table.
		$wpdb->insert(
			self::table(),
			array(
				'level'      => $level,
				'channel'    => substr( $channel, 0, 40 ),
				'message'    => substr( $message, 0, 500 ),
				'context'    => wp_json_encode( self::redact( $context ) ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Recursively replace credential-shaped values with a placeholder.
	 *
	 * @param mixed $data Arbitrary context.
	 * @return mixed
	 */
	public static function redact( $data ) {
		if ( is_array( $data ) ) {
			$out = array();

			foreach ( $data as $key => $value ) {
				if ( is_string( $key ) && in_array( strtolower( $key ), self::$redact, true ) ) {
					$out[ $key ] = '[redacted]';
					continue;
				}

				$out[ $key ] = self::redact( $value );
			}

			return $out;
		}

		if ( is_string( $data ) && strlen( $data ) > 2000 ) {
			return substr( $data, 0, 2000 ) . '...[truncated]';
		}

		return $data;
	}

	/**
	 * Delete rows older than the retention window. Called from the daily cron.
	 *
	 * @return int Rows removed.
	 */
	public static function purge(): int {
		$days = max( 1, (int) Options::get( 'log_retention_days' ) );

		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) )
			)
		);
	}
}
