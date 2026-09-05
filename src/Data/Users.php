<?php
/**
 * Access to the LINE users table.
 *
 * A row here exists for every LINE user we have ever seen, including friends
 * of the bot who have never logged into the site. wp_user_id is 0 until the
 * account is actually linked, which is why the login flow must never assume a
 * row implies a WordPress user.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Data;

use Moksa\Line\Support\Migrator;

defined( 'ABSPATH' ) || exit;

class Users {

	public static function table(): string {
		return Migrator::table( 'users' );
	}

	/**
	 * Look a LINE user up by their LINE id.
	 *
	 * @param string $line_user_id LINE user id.
	 * @return object|null
	 */
	public static function by_line_id( string $line_user_id ) {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE line_user_id = %s", $line_user_id ) );
	}

	/**
	 * Look a LINE user up by the WordPress account they are linked to.
	 *
	 * @param int $wp_user_id WordPress user id.
	 * @return object|null
	 */
	public static function by_wp_id( int $wp_user_id ) {
		if ( $wp_user_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE wp_user_id = %d", $wp_user_id ) );
	}

	/**
	 * Insert or update a LINE user by LINE id.
	 *
	 * @param string $line_user_id LINE user id.
	 * @param array  $fields       Any subset of the writable columns.
	 * @return int Row id, or 0 on failure.
	 */
	public static function upsert( string $line_user_id, array $fields = array() ): int {
		global $wpdb;

		$now      = current_time( 'mysql', true );
		$existing = self::by_line_id( $line_user_id );

		$writable = array(
			'wp_user_id'     => '%d',
			'display_name'   => '%s',
			'picture_url'    => '%s',
			'status_message' => '%s',
			'email'          => '%s',
			'language'       => '%s',
			'is_friend'      => '%d',
			'followed_at'    => '%s',
			'unfollowed_at'  => '%s',
			'last_login_at'  => '%s',
		);

		$data   = array();
		$format = array();

		foreach ( $writable as $column => $spec ) {
			if ( array_key_exists( $column, $fields ) ) {
				$data[ $column ]  = $fields[ $column ];
				$format[]         = $spec;
			}
		}

		$data['updated_at'] = $now;
		$format[]           = '%s';

		if ( $existing ) {
			if ( ! empty( $data ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
				$wpdb->update( self::table(), $data, array( 'id' => (int) $existing->id ), $format, array( '%d' ) );
			}

			return (int) $existing->id;
		}

		$data['line_user_id'] = $line_user_id;
		$format[]             = '%s';
		$data['created_at']   = $now;
		$format[]             = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$inserted = $wpdb->insert( self::table(), $data, $format );

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Bind a LINE identity to a WordPress account.
	 *
	 * @param string $line_user_id LINE user id.
	 * @param int    $wp_user_id   WordPress user id.
	 */
	public static function link( string $line_user_id, int $wp_user_id ): void {
		self::upsert( $line_user_id, array( 'wp_user_id' => $wp_user_id ) );
		update_user_meta( $wp_user_id, 'moksa_line_user_id', $line_user_id );
	}

	/**
	 * Remove the binding without deleting the LINE record, so bot history and
	 * friend state survive an unlink.
	 *
	 * @param int $wp_user_id WordPress user id.
	 */
	public static function unlink( int $wp_user_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->update(
			self::table(),
			array( 'wp_user_id' => 0, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'wp_user_id' => $wp_user_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		delete_user_meta( $wp_user_id, 'moksa_line_user_id' );
		delete_user_meta( $wp_user_id, 'moksa_line_avatar' );
	}

	/**
	 * Mark a user as having added (or blocked) the bot.
	 *
	 * @param string $line_user_id LINE user id.
	 * @param bool   $is_friend    Follow state.
	 */
	public static function set_friend_state( string $line_user_id, bool $is_friend ): void {
		$now = current_time( 'mysql', true );

		self::upsert(
			$line_user_id,
			$is_friend
				? array( 'is_friend' => 1, 'followed_at' => $now )
				: array( 'is_friend' => 0, 'unfollowed_at' => $now )
		);
	}

	/**
	 * LINE ids of everyone currently following the bot.
	 *
	 * @param int $limit Maximum ids to return.
	 * @return string[]
	 */
	public static function friend_ids( int $limit = 5000 ): array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT line_user_id FROM {$table} WHERE is_friend = 1 LIMIT %d", $limit ) );

		return array_map( 'strval', (array) $ids );
	}

	/**
	 * Paginated listing for the admin screen.
	 *
	 * @param array $args per_page, page, search, linked.
	 * @return array{rows:array,total:int}
	 */
	public static function paginate( array $args = array() ): array {
		global $wpdb;
		$table = self::table();

		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(display_name LIKE %s OR line_user_id LIKE %s OR email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( isset( $args['linked'] ) ) {
			$where[] = $args['linked'] ? 'wp_user_id > 0' : 'wp_user_id = 0';
		}

		if ( ! empty( $args['friends_only'] ) ) {
			$where[] = 'is_friend = 1';
		}

		$clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table, params prepared.
		$total = (int) $wpdb->get_var(
			$params
				? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$clause}", $params )
				: "SELECT COUNT(*) FROM {$table} WHERE {$clause}"
		);

		$query_params   = $params;
		$query_params[] = $per_page;
		$query_params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table, params prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$clause} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
				$query_params
			)
		);

		return array(
			'rows'  => (array) $rows,
			'total' => $total,
		);
	}

	/**
	 * Headline counts for the dashboard.
	 *
	 * @return array{total:int,linked:int,friends:int}
	 */
	public static function stats(): array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$row = $wpdb->get_row(
			"SELECT COUNT(*) AS total,
				SUM(CASE WHEN wp_user_id > 0 THEN 1 ELSE 0 END) AS linked,
				SUM(CASE WHEN is_friend = 1 THEN 1 ELSE 0 END) AS friends
			 FROM {$table}"
		);

		return array(
			'total'   => $row ? (int) $row->total : 0,
			'linked'  => $row ? (int) $row->linked : 0,
			'friends' => $row ? (int) $row->friends : 0,
		);
	}
}
