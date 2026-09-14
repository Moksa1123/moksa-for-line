<?php
/**
 * Small shared base for the plugin's simple CRUD tables.
 *
 * Only the tables whose access is genuinely uniform use this. Anything with
 * real query logic (users, events, conversations) keeps its own class rather
 * than being bent into a generic shape.
 *
 * @package Mofoline
 */

namespace Mofoline\Data;

use Mofoline\Support\Db;
use Mofoline\Support\Migrator;

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

		$table = static::table();

		return Db::get_row( Db::prepare( "SELECT * FROM %i WHERE id = %d", $table, $id ) );
	}

	/**
	 * Every row.
	 *
	 * @return array
	 */
	public static function all(): array {
		$table = static::table();
		$order = static::order();

		// order() is "column DIRECTION" from a subclass constant. The column
		// goes through prepare() as an identifier; the direction can only be
		// one of two words, so it is chosen, not interpolated.
		list( $column, $direction ) = array_pad( explode( ' ', trim( $order ), 2 ), 2, 'DESC' );

		// Two complete statements, chosen between -- not one statement with the
		// direction pasted in.
		if ( 'ASC' === strtoupper( $direction ) ) {
			return (array) Db::get_results( Db::prepare( 'SELECT * FROM %i ORDER BY %i ASC', $table, $column ) );
		}

		return (array) Db::get_results( Db::prepare( 'SELECT * FROM %i ORDER BY %i DESC', $table, $column ) );
	}

	/**
	 * Insert or update.
	 *
	 * @param array $fields Column values; an id key triggers an update.
	 * @return int Row id, or 0 on failure.
	 */
	public static function save( array $fields ): int {
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
			Db::update( static::table(), $data, array( 'id' => $id ), $fmt, array( '%d' ) );

			return $id;
		}

		$data['created_at'] = $now;
		$fmt[]              = '%s';

		$inserted = Db::insert( static::table(), $data, $fmt );

		return $inserted ? (int) Db::insert_id() : 0;
	}

	/**
	 * Delete one row.
	 *
	 * @param int $id Row id.
	 */
	public static function delete( int $id ): bool {
		return (bool) Db::delete( static::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Row count.
	 */
	public static function count(): int {
		$table = static::table();

		return (int) Db::get_var( Db::prepare( "SELECT COUNT(*) FROM %i", $table ) );
	}
}
