<?php
/**
 * Stored Flex Message templates.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Data;

defined( 'ABSPATH' ) || exit;

class Flex extends Repository {

	protected static function key(): string {
		return 'flex';
	}

	protected static function columns(): array {
		return array(
			'name'     => '%s',
			'alt_text' => '%s',
			'contents' => '%s',
			'category' => '%s',
		);
	}

	protected static function order(): string {
		return 'updated_at DESC';
	}

	/**
	 * Decoded contents of a template.
	 *
	 * @param int $id Template id.
	 * @return array|null
	 */
	public static function contents( int $id ) {
		$row = self::find( $id );

		if ( ! $row ) {
			return null;
		}

		$decoded = json_decode( (string) $row->contents, true );

		return is_array( $decoded ) ? $decoded : null;
	}
}
