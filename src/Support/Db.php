<?php
/**
 * The one door to the database.
 *
 * Every statement this plugin runs against its own tables comes through
 * here, and a statement can only be run once it is a Statement -- which
 * only prepare() hands out. So a query that was never prepared cannot be
 * executed: not by convention, by type. That is a stronger promise than a
 * reviewer reading eighty call sites can make, and it is checked on every
 * request rather than once.
 *
 * Writes go through the same insert/update/delete WordPress offers, which
 * take a column map and a format list and never see raw SQL.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Support;

use wpdb;

defined( 'ABSPATH' ) || exit;

final class Db {

	/**
	 * A prepared statement.
	 *
	 * Placeholders are wpdb's: %s, %d, %f, and %i for an identifier.
	 *
	 * @param string $query   The statement, with placeholders.
	 * @param mixed  ...$args One value per placeholder, or a single array of them.
	 */
	public static function prepare( string $query, ...$args ): Statement {
		return new Statement( (string) self::connection()->prepare( $query, ...$args ) );
	}

	/**
	 * One value.
	 *
	 * @param Statement $statement A prepared statement.
	 * @return string|null
	 */
	public static function get_var( Statement $statement ) {
		return self::connection()->get_var( $statement->sql() );
	}

	/**
	 * One row, as an object.
	 *
	 * @param Statement $statement A prepared statement.
	 * @return object|null
	 */
	public static function get_row( Statement $statement ) {
		return self::connection()->get_row( $statement->sql() );
	}

	/**
	 * One column, as a list.
	 *
	 * @param Statement $statement A prepared statement.
	 * @return array
	 */
	public static function get_col( Statement $statement ): array {
		return (array) self::connection()->get_col( $statement->sql() );
	}

	/**
	 * Every row, as objects.
	 *
	 * @param Statement $statement A prepared statement.
	 * @return object[]
	 */
	public static function get_results( Statement $statement ): array {
		return (array) self::connection()->get_results( $statement->sql() );
	}

	/**
	 * Run a statement that returns no rows.
	 *
	 * @param Statement $statement A prepared statement.
	 * @return int|bool Rows affected, or false on failure.
	 */
	public static function query( Statement $statement ) {
		return self::connection()->query( $statement->sql() );
	}

	/**
	 * Insert one row.
	 *
	 * @param string $table  Table name.
	 * @param array  $data   Column => value.
	 * @param array  $format One wpdb format per column.
	 * @return int|false Rows inserted, or false.
	 */
	public static function insert( string $table, array $data, array $format = array() ) {
		return self::connection()->insert( $table, $data, $format ?: null );
	}

	/**
	 * Update rows.
	 *
	 * @param string $table        Table name.
	 * @param array  $data         Column => value.
	 * @param array  $where        Column => value.
	 * @param array  $format       One wpdb format per data column.
	 * @param array  $where_format One wpdb format per where column.
	 * @return int|false Rows updated, or false.
	 */
	public static function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ) {
		return self::connection()->update( $table, $data, $where, $format ?: null, $where_format ?: null );
	}

	/**
	 * Delete rows.
	 *
	 * @param string $table        Table name.
	 * @param array  $where        Column => value.
	 * @param array  $where_format One wpdb format per where column.
	 * @return int|false Rows deleted, or false.
	 */
	public static function delete( string $table, array $where, array $where_format = array() ) {
		return self::connection()->delete( $table, $where, $where_format ?: null );
	}

	/** The id of the row insert() just wrote. */
	public static function insert_id(): int {
		return (int) self::connection()->insert_id;
	}

	/** How many rows the last statement touched. */
	public static function rows_affected(): int {
		return (int) self::connection()->rows_affected;
	}

	/** The last error MySQL reported, or an empty string. */
	public static function last_error(): string {
		return (string) self::connection()->last_error;
	}

	/**
	 * A value made safe for a LIKE pattern.
	 *
	 * @param string $text Text the user typed.
	 */
	public static function esc_like( string $text ): string {
		return self::connection()->esc_like( $text );
	}

	/** This site's table prefix. */
	public static function prefix(): string {
		return (string) self::connection()->prefix;
	}

	/** The CHARACTER SET / COLLATE clause for new tables. */
	public static function charset_collate(): string {
		return (string) self::connection()->get_charset_collate();
	}

	/**
	 * The name of one of WordPress's own tables.
	 *
	 * @param string $name posts, options, usermeta and so on.
	 */
	public static function core_table( string $name ): string {
		return (string) self::connection()->{$name};
	}

	/** The connection WordPress opened. */
	private static function connection(): wpdb {
		return $GLOBALS['wpdb'];
	}
}
