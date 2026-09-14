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

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, $id ) );
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

		// order() is "column DIRECTION" from a subclass constant. The column
		// goes through prepare() as an identifier; the direction can only be
		// one of two words, so it is chosen, not interpolated.
		list( $column, $direction ) = array_pad( explode( ' ', trim( $order ), 2 ), 2, 'DESC' );

		// Two complete statements, chosen between -- not one statement with the
		// direction pasted in.
		if ( 'ASC' === strtoupper( $direction ) ) {
			return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY %i ASC', $table, $column ) );
		}

		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY %i DESC', $table, $column ) );
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
			$wpdb->update( static::table(), $data, array( 'id' => $id ), $fmt, array( '%d' ) );

			return $id;
		}

		$data['created_at'] = $now;
		$fmt[]              = '%s';

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

		return (bool) $wpdb->delete( static::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Row count.
	 */
	public static function count(): int {
		global $wpdb;
		$table = static::table();

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table ) );
	}
}
