<?php
/**
 * Payment records.
 *
 * The amount to confirm is read back from this table, never recomputed from
 * the cart or order at confirm time: LINE Pay rejects a confirm whose amount
 * differs from the reservation, and a cart that changed in another tab would
 * otherwise break checkout in a way that is very hard to diagnose.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Pay;

use Moksa\Line\Support\Migrator;

defined( 'ABSPATH' ) || exit;

class Payments {

	public static function table(): string {
		return Migrator::table( 'payments' );
	}

	/**
	 * Create a payment row.
	 *
	 * @param array $fields order_ref, wc_order_id, source, amount, currency,
	 *                      line_user_id.
	 * @return int Row id.
	 */
	public static function create( array $fields ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->insert(
			self::table(),
			array(
				'order_ref'    => (string) ( $fields['order_ref'] ?? '' ),
				'wc_order_id'  => (int) ( $fields['wc_order_id'] ?? 0 ),
				'source'       => (string) ( $fields['source'] ?? 'woocommerce' ),
				'amount'       => (float) ( $fields['amount'] ?? 0 ),
				'currency'     => (string) ( $fields['currency'] ?? 'TWD' ),
				'status'       => 'created',
				'line_user_id' => (string) ( $fields['line_user_id'] ?? '' ),
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a payment row.
	 *
	 * @param int   $id     Row id.
	 * @param array $fields Columns to change.
	 */
	public static function update( int $id, array $fields ): void {
		global $wpdb;

		$writable = array(
			'transaction_id' => '%s',
			'status'         => '%s',
			'payment_url'    => '%s',
			'reg_key'        => '%s',
			'refunded'       => '%f',
			'raw'            => '%s',
			'line_user_id'   => '%s',
		);

		$data   = array();
		$format = array();

		foreach ( $writable as $column => $spec ) {
			if ( array_key_exists( $column, $fields ) ) {
				$data[ $column ] = 'raw' === $column && is_array( $fields[ $column ] )
					? wp_json_encode( $fields[ $column ] )
					: $fields[ $column ];
				$format[]        = $spec;
			}
		}

		if ( empty( $data ) ) {
			return;
		}

		$data['updated_at'] = current_time( 'mysql', true );
		$format[]           = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->update( self::table(), $data, array( 'id' => $id ), $format, array( '%d' ) );
	}

	/**
	 * Find by merchant order reference.
	 *
	 * @param string $order_ref Order reference.
	 * @return object|null
	 */
	public static function by_order_ref( string $order_ref ) {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_ref = %s", $order_ref ) );
	}

	/**
	 * Find by WooCommerce order id, newest attempt first.
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return object|null
	 */
	public static function by_wc_order( int $order_id ) {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE wc_order_id = %d ORDER BY id DESC", $order_id ) );
	}

	/**
	 * Claim a payment for confirmation.
	 *
	 * The customer's browser and a background status check can arrive at the
	 * same moment; whichever gets the row from 'created'/'pending' to
	 * 'confirming' does the work, and the other waits.
	 *
	 * @param int $id Row id.
	 * @return bool True when this caller owns the confirmation.
	 */
	public static function claim_for_confirm( int $id ): bool {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'confirming', updated_at = %s
				 WHERE id = %d AND status IN ('created','pending')",
				current_time( 'mysql', true ),
				$id
			)
		);

		return (bool) $claimed;
	}

	/**
	 * Release a claim that could not be completed, so a retry is possible.
	 *
	 * @param int $id Row id.
	 */
	public static function release( int $id ): void {
		self::update( $id, array( 'status' => 'pending' ) );
	}

	/**
	 * Generate a unique merchant order reference.
	 *
	 * LINE Pay refuses a second reservation for an order id that already
	 * exists, so a retried checkout needs a fresh reference while remaining
	 * traceable to the same order.
	 *
	 * @param string $prefix Short prefix, e.g. the order number.
	 */
	public static function new_reference( string $prefix ): string {
		$prefix = preg_replace( '/[^A-Za-z0-9_-]/', '', $prefix );

		return substr( $prefix, 0, 32 ) . '-' . strtoupper( wp_generate_password( 8, false, false ) );
	}

	/**
	 * Recent payments for the admin list.
	 *
	 * @param int $limit Rows to return.
	 * @return array
	 */
	public static function recent( int $limit = 50 ): array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ) );
	}

	/**
	 * Reservations that were never completed, for the reconciliation sweep.
	 *
	 * @param int $older_than_minutes Only rows untouched for this long.
	 * @return array
	 */
	public static function stale( int $older_than_minutes = 15 ): array {
		global $wpdb;
		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $older_than_minutes * MINUTE_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE status IN ('created','pending','confirming')
				   AND updated_at < %s
				 ORDER BY id ASC LIMIT 50",
				$cutoff
			)
		);
	}
}
