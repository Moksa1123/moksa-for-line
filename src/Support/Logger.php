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
	 * Every channel that has ever logged a row, for the filter on the Logs screen.
	 *
	 * @return string[]
	 */
	public static function channels(): array {
		global $wpdb;

		return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT channel FROM %i ORDER BY channel ASC', self::table() ) ) );
	}

	/**
	 * How many rows the table holds in all.
	 */
	public static function count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
	}

	/**
	 * One page of the log, newest first.
	 *
	 * @param array $args {
	 *     @type string $level    One level, "problems" for warnings and errors, or "all".
	 *     @type string $channel  Limit to one channel; empty for every channel.
	 *     @type int    $page     Page number, from 1.
	 *     @type int    $per_page Rows per page.
	 * }
	 * @return array{rows: object[], total: int, pages: int, page: int}
	 */
	public static function paginate( array $args = array() ): array {
		global $wpdb;
		$table = self::table();

		$per_page = max( 1, min( 500, (int) ( $args['per_page'] ?? 100 ) ) );
		$level    = (string) ( $args['level'] ?? 'problems' );
		$channel  = (string) ( $args['channel'] ?? '' );

		// Every filter is always in the statement and switched off by its own
		// value, so the statement is one literal with a fixed set of
		// placeholders rather than something assembled at run time. "problems"
		// is the two levels that matter; one level is that level twice.
		$every  = 'all' === $level ? 1 : 0;
		$levels = 'problems' === $level ? array( self::WARNING, self::ERROR ) : array( $level, $level );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i
				 WHERE ( %d = 1 OR level IN ( %s, %s ) )
				   AND ( %s = '' OR channel = %s )",
				$table,
				$every,
				$levels[0],
				$levels[1],
				$channel,
				$channel
			)
		);

		$pages  = max( 1, (int) ceil( $total / $per_page ) );
		$page   = min( max( 1, (int) ( $args['page'] ?? 1 ) ), $pages );
		$offset = ( $page - 1 ) * $per_page;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				 WHERE ( %d = 1 OR level IN ( %s, %s ) )
				   AND ( %s = '' OR channel = %s )
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d",
				$table,
				$every,
				$levels[0],
				$levels[1],
				$channel,
				$channel,
				$per_page,
				$offset
			)
		);

		return array(
			'rows'  => $rows,
			'total' => $total,
			'pages' => $pages,
			'page'  => $page,
		);
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

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE created_at < %s",
				$table,
				gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) )
			)
		);
	}
}
