<?php
/**
 * Delivery history for order notifications.
 *
 * This is the record of what was actually sent to which customer about which
 * order, and whether LINE accepted it. It exists because "did the customer get
 * told their order shipped?" is a question a shop has to be able to answer
 * after the fact, and a log that only records failures cannot answer it.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Woo;

use Moksa\Line\Support\Db;
use Moksa\Line\Support\Migrator;

defined( 'ABSPATH' ) || exit;

class NotifyHistory {

	public static function table(): string {
		return Migrator::table( 'notify_history' );
	}

	/**
	 * Record an attempt, before it is made.
	 *
	 * The row is written first with status 'pending' and settled afterwards, so
	 * a send that fatals mid-flight still leaves evidence it was attempted.
	 *
	 * @param array $fields wp_user_id, line_user_id, recipient, order_id,
	 *                      template_id, content.
	 * @return int Row id, or 0 on failure.
	 */
	public static function begin( array $fields ): int {
		Db::insert(
			self::table(),
			array(
				'wp_user_id'   => (int) ( $fields['wp_user_id'] ?? 0 ),
				'line_user_id' => (string) ( $fields['line_user_id'] ?? '' ),
				'recipient'    => (string) ( $fields['recipient'] ?? '' ),
				'order_id'     => (int) ( $fields['order_id'] ?? 0 ),
				'template_id'  => (int) ( $fields['template_id'] ?? 0 ),
				'order_status' => (string) ( $fields['order_status'] ?? '' ),
				'channel'      => 'line',
				'content'      => (string) ( $fields['content'] ?? '' ),
				'status'       => 'pending',
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) Db::insert_id();
	}

	/**
	 * Settle an attempt.
	 *
	 * @param int    $id      Row id from begin().
	 * @param bool   $success Whether LINE accepted the message.
	 * @param string $error   Failure detail.
	 */
	public static function settle( int $id, bool $success, string $error = '' ): void {
		if ( $id <= 0 ) {
			return;
		}

		Db::update(
			self::table(),
			array(
				'status'     => $success ? 'sent' : 'failed',
				'error'      => '' !== $error ? substr( $error, 0, 1000 ) : null,
				'settled_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Recent rows for the admin screen.
	 *
	 * @param array $args order_id, status, per_page, page.
	 * @return array{rows:array,total:int}
	 */
	public static function paginate( array $args = array() ): array {
		$table = self::table();

		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 30 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Every filter is always in the statement and switched off by its own
		// value, so the statement is one literal with a fixed set of
		// placeholders rather than something assembled at run time.
		$order_id = (int) ( $args['order_id'] ?? 0 );
		$status   = (string) ( $args['status'] ?? '' );

		$total = (int) Db::get_var(
			Db::prepare(
				"SELECT COUNT(*) FROM %i
				 WHERE ( %d = 0 OR order_id = %d )
				   AND ( %s = '' OR status = %s )",
				$table,
				$order_id,
				$order_id,
				$status,
				$status
			)
		);

		$rows = (array) Db::get_results(
			Db::prepare(
				"SELECT * FROM %i
				 WHERE ( %d = 0 OR order_id = %d )
				   AND ( %s = '' OR status = %s )
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d",
				$table,
				$order_id,
				$order_id,
				$status,
				$status,
				$per_page,
				$offset
			)
		);

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * How many notifications were sent and how many failed, for the dashboard.
	 *
	 * @param int $days Window to count over.
	 * @return array{sent:int,failed:int}
	 */
	public static function tally( int $days = 30 ): array {
		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $days ) * DAY_IN_SECONDS ) );

		$row = Db::get_row(
			Db::prepare(
				"SELECT
					SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
					SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
				 FROM %i WHERE created_at >= %s",
				$table,
				$cutoff
			)
		);

		return array(
			'sent'   => $row ? (int) $row->sent : 0,
			'failed' => $row ? (int) $row->failed : 0,
		);
	}

	/**
	 * Trim rows past the retention window. Called from the daily maintenance.
	 *
	 * @param int $days Retention in days.
	 * @return int Rows removed.
	 */
	public static function purge( int $days ): int {
		if ( $days <= 0 ) {
			return 0;
		}

		$table = self::table();

		return (int) Db::query(
			Db::prepare(
				"DELETE FROM %i WHERE created_at < %s",
				$table,
				gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) )
			)
		);
	}
}
