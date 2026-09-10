<?php
/**
 * Webhook event storage and dispatch.
 *
 * LINE retries a webhook it did not get a prompt 200 for, so the endpoint must
 * store and acknowledge first and think afterwards. webhook_event_id carries a
 * UNIQUE index, which makes a retry a no-op rather than a second reply to the
 * customer.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Webhook;

use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Migrator;

defined( 'ABSPATH' ) || exit;

class EventQueue {

	/** Give up on an event after this many failed attempts. */
	const MAX_ATTEMPTS = 3;

	public static function table(): string {
		return Migrator::table( 'events' );
	}

	/**
	 * Persist one event, ignoring duplicates.
	 *
	 * @param array $event Decoded webhook event.
	 * @return int Row id, or 0 when this event was already stored.
	 */
	public static function store( array $event ): int {
		global $wpdb;

		// Events without an id (older payloads, and the console's verify ping)
		// get a deterministic one so replays still collapse.
		$event_id = isset( $event['webhookEventId'] )
			? (string) $event['webhookEventId']
			: 'synthetic-' . hash( 'sha256', wp_json_encode( $event ) );

		$source = isset( $event['source'] ) && is_array( $event['source'] ) ? $event['source'] : array();

		$source_id = '';

		foreach ( array( 'userId', 'groupId', 'roomId' ) as $key ) {
			if ( ! empty( $source[ $key ] ) ) {
				$source_id = (string) $source[ $key ];
				break;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::table() . '
					(webhook_event_id, event_type, source_type, source_id, reply_token, payload, status, received_at)
				 VALUES (%s, %s, %s, %s, %s, %s, %s, %s)',
				$event_id,
				isset( $event['type'] ) ? (string) $event['type'] : '',
				isset( $source['type'] ) ? (string) $source['type'] : '',
				$source_id,
				isset( $event['replyToken'] ) ? (string) $event['replyToken'] : '',
				wp_json_encode( $event ),
				'pending',
				current_time( 'mysql', true )
			)
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Claim and run the pending events.
	 *
	 * Reply tokens expire one minute after the event, so this runs in the same
	 * request as the webhook (after the response is flushed) and only falls
	 * back to cron if that request died.
	 *
	 * @param int $limit Maximum events to handle in one pass.
	 * @return int Events processed.
	 */
	public static function drain( int $limit = 25 ): int {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'pending' AND attempts < %d ORDER BY id ASC LIMIT %d",
				self::MAX_ATTEMPTS,
				$limit
			)
		);

		$handled = 0;

		foreach ( (array) $rows as $row ) {
			// Claim the row first: two overlapping drains (a webhook burst plus
			// the cron fallback) must not both handle the same event.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
			$claimed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET status = 'running', attempts = attempts + 1 WHERE id = %d AND status = 'pending'",
					(int) $row->id
				)
			);

			if ( ! $claimed ) {
				continue;
			}

			$event = json_decode( (string) $row->payload, true );

			if ( ! is_array( $event ) ) {
				self::finish( (int) $row->id, 'failed', 'Stored payload was not valid JSON.' );
				continue;
			}

			try {
				Dispatcher::handle( $event );
				self::finish( (int) $row->id, 'done' );
				++$handled;
			} catch ( \Throwable $e ) {
				$attempts = (int) $row->attempts + 1;
				$status   = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';

				self::finish( (int) $row->id, $status, $e->getMessage() );

				Logger::error(
					'Webhook event handler threw',
					array(
						'event_id' => $row->webhook_event_id,
						'type'     => $row->event_type,
						'detail'   => $e->getMessage(),
					),
					'webhook'
				);
			}
		}

		return $handled;
	}

	/**
	 * Mark an event finished.
	 *
	 * @param int    $id     Row id.
	 * @param string $status done, failed or pending.
	 * @param string $error  Failure detail.
	 */
	private static function finish( int $id, string $status, string $error = '' ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->update(
			self::table(),
			array(
				'status'       => $status,
				'error'        => '' !== $error ? substr( $error, 0, 1000 ) : null,
				'processed_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Recent events for the admin log screen.
	 *
	 * @param int    $limit Rows to return.
	 * @param string $status Optional status filter.
	 * @return array
	 */
	public static function recent( int $limit = 50, string $status = '' ): array {
		global $wpdb;
		$table = self::table();

		if ( '' !== $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
			return (array) $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d", $status, $limit )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ) );
	}

	/**
	 * A one-line description of what an event actually carried.
	 *
	 * The Detail column showed only the error, so on a working site it was an
	 * empty column on every row -- present, headed, and never saying anything.
	 * What the message said is the thing that makes the list readable: it is
	 * how you tell which delivery produced which reply.
	 *
	 * @param object $event Row from this table.
	 * @return string Plain text, already trimmed for a table cell.
	 */
	public static function summary( $event ): string {
		if ( '' !== (string) $event->error ) {
			return (string) $event->error;
		}

		$payload = json_decode( (string) $event->payload, true );

		if ( ! is_array( $payload ) ) {
			return '';
		}

		$type = isset( $payload['type'] ) ? (string) $payload['type'] : '';

		if ( 'message' === $type && isset( $payload['message']['type'] ) ) {
			$kind = (string) $payload['message']['type'];

			if ( 'text' === $kind ) {
				return mb_strimwidth( (string) ( $payload['message']['text'] ?? '' ), 0, 80, '...' );
			}

			if ( 'sticker' === $kind ) {
				return sprintf(
					/* translators: 1: sticker package id, 2: sticker id. */
					__( 'Sticker %1$s / %2$s', 'moksa-line' ),
					(string) ( $payload['message']['packageId'] ?? '?' ),
					(string) ( $payload['message']['stickerId'] ?? '?' )
				);
			}

			/* translators: %s: message type, such as image or location. */
			return sprintf( __( '%s message', 'moksa-line' ), $kind );
		}

		if ( 'postback' === $type ) {
			return mb_strimwidth( (string) ( $payload['postback']['data'] ?? '' ), 0, 80, '...' );
		}

		return '';
	}

	/**
	 * Whether any event has ever arrived, used by the setup checklist.
	 */
	public static function has_any(): bool {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return (bool) $wpdb->get_var( "SELECT id FROM {$table} LIMIT 1" );
	}
}
