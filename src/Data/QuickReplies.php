<?php
/**
 * Stored quick reply sets.
 *
 * A quick reply is a property of a message rather than a message type, so a
 * set is always attached to something else when it is sent.
 *
 * @package Mofoline
 */

namespace Mofoline\Data;

defined( 'ABSPATH' ) || exit;

class QuickReplies extends Repository {

	protected static function key(): string {
		return 'quick_replies';
	}

	protected static function columns(): array {
		return array(
			'name'      => '%s',
			'items'     => '%s',
			'is_active' => '%d',
		);
	}

	protected static function order(): string {
		return 'name ASC';
	}

	/**
	 * Decoded items for a set, capped at LINE's limit of 13.
	 *
	 * @param int $id Set id.
	 * @return array
	 */
	public static function items( int $id ): array {
		$row = self::find( $id );

		if ( ! $row ) {
			return array();
		}

		$items = json_decode( (string) $row->items, true );

		return is_array( $items ) ? array_slice( $items, 0, 13 ) : array();
	}
}
