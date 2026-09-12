<?php
/**
 * Conversation records for the customer-service inbox.
 *
 * Worth stating plainly, because it surprises people: the Messaging API has no
 * endpoint for reading past chats. Everything the inbox can ever show is what
 * arrived by webhook after this plugin was installed and the webhook was
 * switched on. History from before that is not recoverable.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Inbox;

use Moksa\Line\Data\Users;
use Moksa\Line\Support\Migrator;

defined( 'ABSPATH' ) || exit;

class Conversations {

	/** bot: automation answers. human: an agent has taken over. closed: done. */
	const STATUSES = array( 'bot', 'human', 'closed' );

	public static function table(): string {
		return Migrator::table( 'conversations' );
	}

	/**
	 * Fetch a conversation by LINE user id.
	 *
	 * @param string $line_user_id LINE user id.
	 * @return object|null
	 */
	public static function find( string $line_user_id ) {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE line_user_id = %s", $line_user_id ) );
	}

	/**
	 * Fetch a conversation by row id.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function find_by_id( int $id ) {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Create the conversation if it does not exist, and return its id.
	 *
	 * @param string $line_user_id LINE user id.
	 * @return int
	 */
	public static function ensure( string $line_user_id ): int {
		$existing = self::find( $line_user_id );

		if ( $existing ) {
			return (int) $existing->id;
		}

		$record = Users::by_line_id( $line_user_id );
		$now    = current_time( 'mysql', true );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i
					(line_user_id, display_name, picture_url, status, created_at, updated_at)
				 VALUES (%s, %s, %s, %s, %s, %s)',
				self::table(),
				$line_user_id,
				$record ? (string) $record->display_name : '',
				$record ? (string) $record->picture_url : '',
				'bot',
				$now,
				$now
			)
		);

		if ( $wpdb->insert_id ) {
			return (int) $wpdb->insert_id;
		}

		$existing = self::find( $line_user_id );

		return $existing ? (int) $existing->id : 0;
	}

	/**
	 * Update the preview shown in the conversation list.
	 *
	 * @param string $line_user_id LINE user id.
	 * @param string $preview      Short summary of the last message.
	 * @param bool   $inbound      Whether the message came from the visitor.
	 */
	public static function touch( string $line_user_id, string $preview, bool $inbound ): void {
		$id = self::ensure( $line_user_id );

		if ( ! $id ) {
			return;
		}

		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );

		$record  = Users::by_line_id( $line_user_id );
		$preview = mb_substr( trim( preg_replace( '/\s+/u', ' ', $preview ) ), 0, 120 );

		if ( $inbound ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					 SET last_message_preview = %s,
						 last_message_at = %s,
						 last_inbound_at = %s,
						 unread_count = unread_count + 1,
						 display_name = %s,
						 picture_url = %s,
						 updated_at = %s
					 WHERE id = %d",
					$preview,
					$now,
					$now,
					$record ? (string) $record->display_name : '',
					$record ? (string) $record->picture_url : '',
					$now,
					$id
				)
			);

			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET last_message_preview = %s, last_message_at = %s, updated_at = %s
				 WHERE id = %d",
				$preview,
				$now,
				$now,
				$id
			)
		);
	}

	/**
	 * Set the handling status.
	 *
	 * @param string $line_user_id LINE user id.
	 * @param string $status       bot, human or closed.
	 * @param int    $assignee_id  Agent taking the conversation, 0 to clear.
	 */
	public static function set_status( string $line_user_id, string $status, int $assignee_id = 0 ): void {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return;
		}

		$id = self::ensure( $line_user_id );

		if ( ! $id ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->update(
			self::table(),
			array(
				'status'      => $status,
				'assignee_id' => $assignee_id,
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		/**
		 * Fires when a conversation changes hands.
		 *
		 * @param string $line_user_id LINE user id.
		 * @param string $status       New status.
		 * @param int    $assignee_id  Assigned agent.
		 */
		do_action( 'moksa_line_conversation_status', $line_user_id, $status, $assignee_id );
	}

	/**
	 * Whether an agent currently owns this conversation.
	 *
	 * @param string $line_user_id LINE user id.
	 */
	public static function is_human_handled( string $line_user_id ): bool {
		$conversation = self::find( $line_user_id );

		return $conversation && 'human' === $conversation->status;
	}

	/**
	 * Clear the unread badge.
	 *
	 * @param int $id Conversation id.
	 */
	public static function mark_read( int $id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->update(
			self::table(),
			array( 'unread_count' => 0 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Conversation list for the admin screen.
	 *
	 * @param array $args status, search, per_page, page.
	 * @return array{rows:array,total:int}
	 */
	public static function paginate( array $args = array() ): array {
		global $wpdb;
		$table = self::table();

		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 25 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['unread'] ) ) {
			$where[] = 'unread_count > 0';
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '(display_name LIKE %s OR line_user_id LIKE %s OR last_message_preview LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$clause = implode( ' AND ', $where );

		// The WHERE clause is assembled from the literal fragments above; every
		// value in it is a placeholder, and the table name is one too.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table, paged admin list.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE {$clause}",
				array_merge( array( $table ), $params )
			)
		);

		$query_params   = array_merge( array( $table ), $params );
		$query_params[] = $per_page;
		$query_params[] = $offset;

		// The WHERE clause is assembled from the literal fragments above; every
		// value in it is a placeholder, and the table name is one too.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table, paged admin list.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$clause}
				 ORDER BY (last_message_at IS NULL), last_message_at DESC
				 LIMIT %d OFFSET %d",
				$query_params
			)
		);

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * How many conversations an agent currently holds.
	 *
	 * The bot deliberately stays quiet on these, so this is what turns "my auto
	 * replies do nothing" into an answer.
	 */
	public static function human_handled_total(): int {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'human'" );
	}

	/**
	 * Number of conversations waiting on a human.
	 */
	public static function unread_total(): int {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE unread_count > 0 AND status <> 'closed'" );
	}
}
