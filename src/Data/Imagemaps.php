<?php
/**
 * Stored imagemap messages.
 *
 * @package Mofoline
 */

namespace Mofoline\Data;

defined( 'ABSPATH' ) || exit;

class Imagemaps extends Repository {

	protected static function key(): string {
		return 'imagemaps';
	}

	protected static function columns(): array {
		return array(
			'name'                => '%s',
			'alt_text'            => '%s',
			'image_attachment_id' => '%d',
			'base_url'            => '%s',
			'base_width'          => '%d',
			'base_height'         => '%d',
			'actions'             => '%s',
		);
	}

	protected static function order(): string {
		return 'updated_at DESC';
	}
}
