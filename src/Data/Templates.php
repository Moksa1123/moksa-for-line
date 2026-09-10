<?php
/**
 * Stored template messages.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Data;

defined( 'ABSPATH' ) || exit;

class Templates extends Repository {

	protected static function key(): string {
		return 'templates';
	}

	protected static function columns(): array {
		return array(
			'name'       => '%s',
			'alt_text'   => '%s',
			'kind'       => '%s',
			'definition' => '%s',
		);
	}

	protected static function order(): string {
		return 'updated_at DESC';
	}

	/**
	 * The decoded template object for one row.
	 *
	 * @param int $id Row id.
	 * @return array|null
	 */
	public static function definition( int $id ): ?array {
		$row = self::find( $id );

		if ( ! $row ) {
			return null;
		}

		$decoded = json_decode( (string) $row->definition, true );

		return is_array( $decoded ) ? $decoded : null;
	}
}
