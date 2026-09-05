<?php
/**
 * Small shared base for the plugin's simple CRUD tables.
 *
 * Only the tables whose access is genuinely uniform use this. Anything with
 * real query logic (users, events, conversations) keeps its own class rather
 * than being bent into a generic shape.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Data;

use Moksa\Line\Support\Migrator;

defined( 'ABSPATH' ) || exit;

abstract class Repository {

	/**
	 * Logical table key, as understood by Migrator::table().
	 */
	protected static function key(): string {
		return '';
	}

	/**
	 * Writable columns, mapped to their wpdb format specifier.
	 *
	 * @return array<string,string>
	 */
	protected static function columns(): array {
		return array();
	}

	/**
	 * Default ORDER BY clause for all().
	 */
	protected static function order(): string {
		return 'id DESC';
	}

	public static function table(): string {
		return Migrator::table( static::key() );
	}

	/**
	 * One row by id.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function find( int $id ) {
		if ( $id <= 0 ) {
			return null;
		}

		global $wpdb;
		$table = static::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Every row.
	 *
	 * @return array
	 */
	public static function all(): array {
		global $wpdb;
		$table = static::table();
		$order = static::order();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table and a fixed order clause.
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY {$order}" );
	}

	/**
	 * Insert or update.
	 *
	 * @param array $fields Column values; an id key triggers an update.
	 * @return int Row id, or 0 on failure.
	 */
	public static function save( array $fields ): int {
		global $wpdb;

		$now  = current_time( 'mysql', true );
		$id   = isset( $fields['id'] ) ? (int) $fields['id'] : 0;
		$data = array();
		$fmt  = array();

		foreach ( static::columns() as $column => $spec ) {
			if ( array_key_exists( $column, $fields ) ) {
				$data[ $column ] = $fields[ $column ];
				$fmt[]           = $spec;
			}
		}

		if ( empty( $data ) ) {
			return $id;
		}

		$data['updated_at'] = $now;
		$fmt[]              = '%s';

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
			$wpdb->update( static::table(), $data, array( 'id' => $id ), $fmt, array( '%d' ) );

			return $id;
		}

		$data['created_at'] = $now;
		$fmt[]              = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$inserted = $wpdb->insert( static::table(), $data, $fmt );

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Delete one row.
	 *
	 * @param int $id Row id.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		return (bool) $wpdb->delete( static::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Row count.
	 */
	public static function count(): int {
		global $wpdb;
		$table = static::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
}
