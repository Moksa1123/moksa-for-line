<?php
/**
 * Rich menu and rich menu alias operations.
 *
 * Tabbed rich menus (the "switch between menu pages inside LINE" behaviour)
 * are built from aliases: each tab is its own rich menu, each gets an alias,
 * and the tab buttons use a richmenuswitch action pointing at the sibling
 * alias. Re-uploading a whole menu per tap -- which is what a naive build
 * does -- is both slow and visibly janky.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Api;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class RichMenuClient {

	/** LINE only accepts these two heights at 2500px wide. */
	const WIDTH       = 2500;
	const HEIGHT_FULL = 1686;
	const HEIGHT_HALF = 843;
	/** Rich menu images must be at most 1 MB. */
	const MAX_IMAGE_BYTES = 1048576;

	/**
	 * Create a rich menu.
	 *
	 * @param array $definition Rich menu object.
	 * @return array|WP_Error Response containing richMenuId.
	 */
	public static function create( array $definition ) {
		return Client::request( 'POST', '/richmenu', $definition );
	}

	/**
	 * Upload the menu image.
	 *
	 * @param string $rich_menu_id  Rich menu id.
	 * @param string $bytes         Raw image data.
	 * @param string $content_type  image/jpeg or image/png.
	 * @return array|WP_Error
	 */
	public static function upload_image( string $rich_menu_id, string $bytes, string $content_type ) {
		if ( ! in_array( $content_type, array( 'image/jpeg', 'image/png' ), true ) ) {
			return new WP_Error(
				'moksa_line_bad_image_type',
				__( 'Rich menu images must be JPEG or PNG.', 'moksa-line' )
			);
		}

		if ( strlen( $bytes ) > self::MAX_IMAGE_BYTES ) {
			return new WP_Error(
				'moksa_line_image_too_large',
				__( 'Rich menu images must be 1 MB or smaller.', 'moksa-line' )
			);
		}

		return Client::upload( '/richmenu/' . rawurlencode( $rich_menu_id ) . '/content', $bytes, $content_type );
	}

	/**
	 * Delete a rich menu.
	 *
	 * @param string $rich_menu_id Rich menu id.
	 * @return array|WP_Error
	 */
	public static function delete( string $rich_menu_id ) {
		return Client::request( 'DELETE', '/richmenu/' . rawurlencode( $rich_menu_id ) );
	}

	/**
	 * All rich menus registered on the channel.
	 *
	 * @return array|WP_Error
	 */
	public static function all() {
		return Client::request( 'GET', '/richmenu/list' );
	}

	/**
	 * One rich menu.
	 *
	 * @param string $rich_menu_id Rich menu id.
	 * @return array|WP_Error
	 */
	public static function get( string $rich_menu_id ) {
		return Client::request( 'GET', '/richmenu/' . rawurlencode( $rich_menu_id ) );
	}

	/**
	 * The menu LINE currently serves as the channel-wide default.
	 *
	 * LINE answers 404 when there is none, which is an ordinary answer here and
	 * not a failure, so that reads back as an empty string.
	 *
	 * @return string Rich menu id, or '' when the channel has no default.
	 */
	public static function default_id(): string {
		$result = Client::request( 'GET', '/user/all/richmenu' );

		if ( is_wp_error( $result ) || ! isset( $result['richMenuId'] ) ) {
			return '';
		}

		return (string) $result['richMenuId'];
	}

	/**
	 * Make a menu the default for every user who has no per-user menu.
	 *
	 * @param string $rich_menu_id Rich menu id.
	 * @return array|WP_Error
	 */
	public static function set_default( string $rich_menu_id ) {
		return Client::request( 'POST', '/user/all/richmenu/' . rawurlencode( $rich_menu_id ) );
	}

	/**
	 * Remove the channel-wide default.
	 *
	 * @return array|WP_Error
	 */
	public static function clear_default() {
		return Client::request( 'DELETE', '/user/all/richmenu' );
	}

	/**
	 * Attach a menu to one user.
	 *
	 * @param string $line_user_id LINE user id.
	 * @param string $rich_menu_id Rich menu id.
	 * @return array|WP_Error
	 */
	public static function link_user( string $line_user_id, string $rich_menu_id ) {
		return Client::request(
			'POST',
			'/user/' . rawurlencode( $line_user_id ) . '/richmenu/' . rawurlencode( $rich_menu_id )
		);
	}

	/**
	 * Detach the per-user menu.
	 *
	 * @param string $line_user_id LINE user id.
	 * @return array|WP_Error
	 */
	public static function unlink_user( string $line_user_id ) {
		return Client::request( 'DELETE', '/user/' . rawurlencode( $line_user_id ) . '/richmenu' );
	}

	/**
	 * Attach a menu to up to 500 users in one call.
	 *
	 * @param string[] $line_user_ids LINE user ids.
	 * @param string   $rich_menu_id  Rich menu id.
	 * @return array|WP_Error
	 */
	public static function bulk_link( array $line_user_ids, string $rich_menu_id ) {
		return Client::request(
			'POST',
			'/richmenu/bulk/link',
			array(
				'richMenuId' => $rich_menu_id,
				'userIds'    => array_slice( array_values( $line_user_ids ), 0, 500 ),
			)
		);
	}

	/**
	 * Detach per-user menus in bulk.
	 *
	 * @param string[] $line_user_ids LINE user ids.
	 * @return array|WP_Error
	 */
	public static function bulk_unlink( array $line_user_ids ) {
		return Client::request(
			'POST',
			'/richmenu/bulk/unlink',
			array( 'userIds' => array_slice( array_values( $line_user_ids ), 0, 500 ) )
		);
	}

	// --- Aliases: the mechanism behind tabbed menus --------------------------

	/**
	 * Create an alias pointing at a rich menu.
	 *
	 * @param string $alias_id     Alias id: lowercase letters, digits, _ and -.
	 * @param string $rich_menu_id Target rich menu id.
	 * @return array|WP_Error
	 */
	public static function create_alias( string $alias_id, string $rich_menu_id ) {
		return Client::request(
			'POST',
			'/richmenu/alias',
			array(
				'richMenuAliasId' => $alias_id,
				'richMenuId'      => $rich_menu_id,
			)
		);
	}

	/**
	 * Point an existing alias at a different rich menu. This is how a tab set
	 * is updated without users losing the menu mid-session.
	 *
	 * @param string $alias_id     Alias id.
	 * @param string $rich_menu_id New target.
	 * @return array|WP_Error
	 */
	public static function update_alias( string $alias_id, string $rich_menu_id ) {
		return Client::request(
			'POST',
			'/richmenu/alias/' . rawurlencode( $alias_id ),
			array( 'richMenuId' => $rich_menu_id )
		);
	}

	/**
	 * Delete an alias. LINE rate limits this to 100 per hour, so the admin UI
	 * warns before a bulk teardown.
	 *
	 * @param string $alias_id Alias id.
	 * @return array|WP_Error
	 */
	public static function delete_alias( string $alias_id ) {
		return Client::request( 'DELETE', '/richmenu/alias/' . rawurlencode( $alias_id ) );
	}

	/**
	 * Every alias on the channel.
	 *
	 * @return array|WP_Error
	 */
	public static function all_aliases() {
		return Client::request( 'GET', '/richmenu/alias/list' );
	}

	/**
	 * Create or update an alias, whichever the channel needs.
	 *
	 * @param string $alias_id     Alias id.
	 * @param string $rich_menu_id Target rich menu id.
	 * @return array|WP_Error
	 */
	public static function upsert_alias( string $alias_id, string $rich_menu_id ) {
		$result = self::create_alias( $alias_id, $rich_menu_id );

		if ( ! is_wp_error( $result ) ) {
			return $result;
		}

		$data   = $result->get_error_data();
		$status = isset( $data['status'] ) ? (int) $data['status'] : 0;

		// 409 means the alias already exists; repoint it instead.
		if ( 400 === $status || 409 === $status ) {
			return self::update_alias( $alias_id, $rich_menu_id );
		}

		return $result;
	}

	/**
	 * Normalise an alias id to LINE's allowed character set.
	 *
	 * @param string $value Proposed alias id.
	 */
	public static function sanitize_alias_id( string $value ): string {
		$value = strtolower( remove_accents( $value ) );
		$value = preg_replace( '/[^a-z0-9_-]/', '-', $value );
		$value = trim( (string) preg_replace( '/-+/', '-', $value ), '-' );

		if ( '' === $value ) {
			// A name written entirely in Chinese, Japanese or Korean survives
			// none of the above, which for this plugin's audience is the normal
			// case rather than the edge one. The fallback has to be lowercase:
			// wp_generate_password() returns mixed case, and LINE allows only
			// a-z, 0-9, underscore and hyphen -- it rejected "menu-UL0l8y".
			$value = 'menu-' . strtolower( wp_generate_password( 8, false, false ) );
		}

		// Truncating can leave a trailing hyphen, which LINE also refuses.
		return trim( substr( $value, 0, 32 ), '-_' );
	}

	/**
	 * Build the size object for a menu.
	 *
	 * @param string $size full or half.
	 * @return array{width:int,height:int}
	 */
	public static function size( string $size ): array {
		return array(
			'width'  => self::WIDTH,
			'height' => 'half' === $size ? self::HEIGHT_HALF : self::HEIGHT_FULL,
		);
	}
}
