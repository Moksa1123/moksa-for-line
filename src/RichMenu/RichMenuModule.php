<?php
/**
 * Rich menus, including tabbed menu groups.
 *
 * A "tab group" is several rich menus that share a group name. Each is
 * published with an alias, and any area whose action is a tab switch uses a
 * richmenuswitch action pointing at a sibling's alias. That is what makes the
 * tab change instantly inside LINE, with no round trip and no visible reload.
 *
 * The local rows are the source of truth; publishing reconciles LINE to match
 * them, so a half-finished publish can simply be run again.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\RichMenu;

use Moksa\Line\Data\Repository;
use Moksa\Line\Api\RichMenuClient;
use Moksa\Line\Support\Logger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class RichMenuModule extends Repository {

	protected static function key(): string {
		return 'richmenus';
	}

	protected static function columns(): array {
		return array(
			'richmenu_id'         => '%s',
			'alias_id'            => '%s',
			'name'                => '%s',
			'chat_bar_text'       => '%s',
			'size'                => '%s',
			'selected'            => '%d',
			'areas'               => '%s',
			'image_attachment_id' => '%d',
			'tab_group'           => '%s',
			'tab_order'           => '%d',
			'is_default'          => '%d',
			'synced_at'           => '%s',
		);
	}

	protected static function order(): string {
		return 'tab_group ASC, tab_order ASC, id ASC';
	}

	public function register(): void {
		add_action( 'wp_ajax_moksa_line_richmenu_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_moksa_line_richmenu_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_moksa_line_richmenu_publish', array( $this, 'ajax_publish' ) );
		add_action( 'wp_ajax_moksa_line_richmenu_default', array( $this, 'ajax_set_default' ) );
		add_action( 'wp_ajax_moksa_line_richmenu_sync', array( $this, 'ajax_sync' ) );
	}

	// --- Publishing ------------------------------------------------------------

	/**
	 * Publish one menu to LINE: create it, upload the image, point its alias
	 * at the new id.
	 *
	 * LINE rich menus are immutable, so "editing" one means creating a
	 * replacement and repointing the alias. The old menu is deleted only after
	 * the alias moves, so a tap during publishing still lands somewhere.
	 *
	 * @param int $id Local row id.
	 * @return true|WP_Error
	 */
	public static function publish( int $id ) {
		$row = self::find( $id );

		if ( ! $row ) {
			return new WP_Error( 'moksa_line_menu_missing', __( 'That rich menu no longer exists.', 'moksa-line' ) );
		}

		$image = self::image_bytes( (int) $row->image_attachment_id );

		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$areas = json_decode( (string) $row->areas, true );

		if ( ! is_array( $areas ) || empty( $areas ) ) {
			return new WP_Error(
				'moksa_line_menu_no_areas',
				__( 'This rich menu has no tappable areas yet.', 'moksa-line' )
			);
		}

		$definition = array(
			'size'        => RichMenuClient::size( (string) $row->size ),
			'selected'    => (bool) $row->selected,
			'name'        => mb_substr( (string) $row->name, 0, 300 ),
			'chatBarText' => mb_substr( '' !== $row->chat_bar_text ? (string) $row->chat_bar_text : __( 'Menu', 'moksa-line' ), 0, 14 ),
			'areas'       => self::normalize_areas( $areas, (string) $row->size ),
		);

		$created = RichMenuClient::create( $definition );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$new_id = isset( $created['richMenuId'] ) ? (string) $created['richMenuId'] : '';

		if ( '' === $new_id ) {
			return new WP_Error( 'moksa_line_menu_no_id', __( 'LINE accepted the menu but did not return an id.', 'moksa-line' ) );
		}

		$uploaded = RichMenuClient::upload_image( $new_id, $image['bytes'], $image['mime'] );

		if ( is_wp_error( $uploaded ) ) {
			// A menu with no image is unusable, so do not leave it behind.
			RichMenuClient::delete( $new_id );

			return $uploaded;
		}

		$previous = (string) $row->richmenu_id;
		$alias    = (string) $row->alias_id;

		if ( '' === $alias ) {
			$alias = RichMenuClient::sanitize_alias_id(
				( '' !== $row->tab_group ? $row->tab_group . '-' : '' ) . ( $row->name ? $row->name : 'menu-' . $row->id )
			);
		}

		$alias_result = RichMenuClient::upsert_alias( $alias, $new_id );

		if ( is_wp_error( $alias_result ) ) {
			Logger::capture( $alias_result, 'Could not point the rich menu alias at the new menu', 'richmenu' );
		}

		// Ask LINE what it is actually serving rather than trusting the local
		// flag. A default set from anywhere else -- the LINE console, an earlier
		// version of this plugin, a direct API call -- leaves the flag at 0, and
		// republishing would then delete the menu LINE was serving and leave the
		// account with no default at all: every customer would silently drop
		// back to the Official Account Manager menu, or to none.
		$was_default = '' !== $previous && RichMenuClient::default_id() === $previous;

		self::save(
			array(
				'id'          => $id,
				'richmenu_id' => $new_id,
				'alias_id'    => $alias,
				'is_default'  => ( (bool) $row->is_default || $was_default ) ? 1 : 0,
				'synced_at'   => current_time( 'mysql', true ),
			)
		);

		if ( (bool) $row->is_default || $was_default ) {
			if ( $was_default ) {
				// Only one menu can hold the flag, or the screen would show two.
				global $wpdb;
				$table = self::table();

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_default = 0 WHERE id <> %d", $id ) );
			}

			$default = RichMenuClient::set_default( $new_id );

			Logger::capture( $default, 'Could not set the default rich menu', 'richmenu' );
		}

		// Now that nothing points at it, retire the old menu. A failure here
		// costs nothing at the time, but it leaves a menu on LINE that the sync
		// screen will later report as an orphan of unknown origin -- so say so
		// now, while the cause is still obvious.
		if ( '' !== $previous && $previous !== $new_id ) {
			$retired = RichMenuClient::delete( $previous );

			Logger::capture( $retired, 'Could not remove the rich menu this one replaced', 'richmenu' );
		}

		return true;
	}

	/**
	 * Publish an entire tab group, in tab order.
	 *
	 * @param string $group Tab group name.
	 * @return array{published:int,errors:string[]}
	 */
	public static function publish_group( string $group ): array {
		$report = array( 'published' => 0, 'errors' => array() );

		foreach ( self::in_group( $group ) as $row ) {
			$result = self::publish( (int) $row->id );

			if ( is_wp_error( $result ) ) {
				$report['errors'][] = sprintf( '%s: %s', (string) $row->name, $result->get_error_message() );
				continue;
			}

			++$report['published'];
		}

		return $report;
	}

	/**
	 * Menus in one tab group, in order.
	 *
	 * @param string $group Tab group name.
	 * @return array
	 */
	public static function in_group( string $group ): array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE tab_group = %s ORDER BY tab_order ASC, id ASC", $group )
		);
	}

	/**
	 * All distinct tab group names.
	 *
	 * @return string[]
	 */
	public static function groups(): array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$groups = $wpdb->get_col( "SELECT DISTINCT tab_group FROM {$table} WHERE tab_group <> '' ORDER BY tab_group ASC" );

		return array_map( 'strval', (array) $groups );
	}

	/**
	 * Alias ids available as tab switch targets, for the editor's dropdown.
	 *
	 * @param string $group   Tab group.
	 * @param int    $exclude Row id to leave out (the menu being edited).
	 * @return array<string,string> alias id => menu name.
	 */
	public static function switch_targets( string $group, int $exclude = 0 ): array {
		$targets = array();

		foreach ( self::in_group( $group ) as $row ) {
			if ( (int) $row->id === $exclude ) {
				continue;
			}

			$alias = (string) $row->alias_id;

			if ( '' === $alias ) {
				$alias = RichMenuClient::sanitize_alias_id( $group . '-' . $row->name );
			}

			$targets[ $alias ] = (string) $row->name;
		}

		return $targets;
	}

	// --- Area handling ---------------------------------------------------------

	/**
	 * Clamp areas to the canvas and drop malformed ones.
	 *
	 * LINE rejects the whole menu if a single area runs past the edge, and its
	 * error message does not say which one, so the bounds are enforced here
	 * where the offending area can be named.
	 *
	 * @param array  $areas Area definitions.
	 * @param string $size  full or half.
	 * @return array
	 */
	public static function normalize_areas( array $areas, string $size ): array {
		$canvas = RichMenuClient::size( $size );
		$clean  = array();

		foreach ( $areas as $area ) {
			if ( ! is_array( $area ) || empty( $area['bounds'] ) || empty( $area['action'] ) ) {
				continue;
			}

			$bounds = $area['bounds'];

			$x = max( 0, min( $canvas['width'], (int) ( $bounds['x'] ?? 0 ) ) );
			$y = max( 0, min( $canvas['height'], (int) ( $bounds['y'] ?? 0 ) ) );
			$w = max( 1, min( $canvas['width'] - $x, (int) ( $bounds['width'] ?? 0 ) ) );
			$h = max( 1, min( $canvas['height'] - $y, (int) ( $bounds['height'] ?? 0 ) ) );

			$clean[] = array(
				'bounds' => array(
					'x'      => $x,
					'y'      => $y,
					'width'  => $w,
					'height' => $h,
				),
				'action' => self::normalize_action( $area['action'] ),
			);
		}

		// LINE allows at most 20 areas per menu.
		return array_slice( $clean, 0, 20 );
	}

	/**
	 * Normalise one area action.
	 *
	 * @param array $action Action definition.
	 * @return array
	 */
	private static function normalize_action( array $action ): array {
		$type = isset( $action['type'] ) ? (string) $action['type'] : 'postback';

		$clean = array( 'type' => $type );

		if ( ! empty( $action['label'] ) ) {
			$clean['label'] = mb_substr( (string) $action['label'], 0, 20 );
		}

		switch ( $type ) {
			case 'uri':
				$clean['uri'] = esc_url_raw( (string) ( $action['uri'] ?? '' ) );
				break;

			case 'message':
				$clean['text'] = mb_substr( (string) ( $action['text'] ?? '' ), 0, 300 );
				break;

			case 'richmenuswitch':
				// The alias the tab points at, plus a postback so the switch is
				// still visible in the event log.
				$clean['richMenuAliasId'] = RichMenuClient::sanitize_alias_id( (string) ( $action['richMenuAliasId'] ?? '' ) );
				$clean['data']            = (string) ( $action['data'] ?? 'moksa_tab=' . $clean['richMenuAliasId'] );
				break;

			case 'datetimepicker':
				$clean['data'] = (string) ( $action['data'] ?? '' );
				$clean['mode'] = in_array( $action['mode'] ?? '', array( 'date', 'time', 'datetime' ), true )
					? $action['mode']
					: 'date';
				break;

			case 'postback':
			default:
				$clean['type'] = 'postback';
				$clean['data'] = (string) ( $action['data'] ?? '' );

				if ( ! empty( $action['displayText'] ) ) {
					$clean['displayText'] = mb_substr( (string) $action['displayText'], 0, 300 );
				}
				break;
		}

		return $clean;
	}

	/**
	 * Read a menu image from the media library.
	 *
	 * @param int $attachment_id Attachment id.
	 * @return array{bytes:string,mime:string}|WP_Error
	 */
	private static function image_bytes( int $attachment_id ) {
		if ( $attachment_id <= 0 ) {
			return new WP_Error(
				'moksa_line_menu_no_image',
				__( 'Choose a menu image first. LINE requires one, 2500px wide and either 1686px or 843px tall.', 'moksa-line' )
			);
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! is_readable( $path ) ) {
			return new WP_Error(
				'moksa_line_menu_image_unreadable',
				__( 'The selected image could not be read from the media library.', 'moksa-line' )
			);
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		if ( 'image/jpg' === $mime ) {
			$mime = 'image/jpeg';
		}

		$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- reading a local file, not a remote request.

		if ( false === $bytes ) {
			return new WP_Error(
				'moksa_line_menu_image_unreadable',
				__( 'The selected image could not be read from disk.', 'moksa-line' )
			);
		}

		return array( 'bytes' => $bytes, 'mime' => $mime );
	}

	/**
	 * Check an image against LINE's requirements, for the editor's warnings.
	 *
	 * @param int    $attachment_id Attachment id.
	 * @param string $size          full or half.
	 * @return string[] Problems, empty when the image is usable.
	 */
	public static function check_image( int $attachment_id, string $size ): array {
		$problems = array();
		$meta     = wp_get_attachment_metadata( $attachment_id );
		$expected = RichMenuClient::size( $size );

		if ( ! is_array( $meta ) || empty( $meta['width'] ) ) {
			return array( __( 'Could not read the image dimensions.', 'moksa-line' ) );
		}

		if ( (int) $meta['width'] !== $expected['width'] || (int) $meta['height'] !== $expected['height'] ) {
			$problems[] = sprintf(
				/* translators: 1: required width, 2: required height, 3: actual width, 4: actual height. */
				__( 'LINE expects %1$d x %2$d pixels for this menu size; this image is %3$d x %4$d.', 'moksa-line' ),
				$expected['width'],
				$expected['height'],
				(int) $meta['width'],
				(int) $meta['height']
			);
		}

		$path = get_attached_file( $attachment_id );

		if ( $path && file_exists( $path ) && filesize( $path ) > RichMenuClient::MAX_IMAGE_BYTES ) {
			$problems[] = __( 'The image is larger than 1 MB, which LINE will reject.', 'moksa-line' );
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		if ( ! in_array( $mime, array( 'image/jpeg', 'image/jpg', 'image/png' ), true ) ) {
			$problems[] = __( 'Rich menu images must be JPEG or PNG.', 'moksa-line' );
		}

		return $problems;
	}

	// --- AJAX -------------------------------------------------------------------

	/**
	 * Save a menu definition locally. Publishing is a separate, explicit step.
	 */
	public function ajax_save(): void {
		$this->guard();

		$areas_raw = isset( $_POST['areas'] ) ? wp_unslash( $_POST['areas'] ) : '[]';
		$areas     = json_decode( (string) $areas_raw, true );

		if ( ! is_array( $areas ) ) {
			wp_send_json_error( array( 'message' => __( 'The tappable areas could not be read.', 'moksa-line' ) ) );
		}

		$size  = isset( $_POST['size'] ) && 'half' === $_POST['size'] ? 'half' : 'full';
		$group = isset( $_POST['tab_group'] ) ? sanitize_text_field( wp_unslash( $_POST['tab_group'] ) ) : '';

		$id = self::save(
			array(
				'id'                  => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
				'name'                => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'chat_bar_text'       => isset( $_POST['chat_bar_text'] ) ? sanitize_text_field( wp_unslash( $_POST['chat_bar_text'] ) ) : '',
				'size'                => $size,
				'selected'            => empty( $_POST['selected'] ) ? 0 : 1,
				'areas'               => wp_json_encode( self::normalize_areas( $areas, $size ) ),
				'image_attachment_id' => isset( $_POST['image_attachment_id'] ) ? (int) $_POST['image_attachment_id'] : 0,
				'tab_group'           => $group,
				'tab_order'           => isset( $_POST['tab_order'] ) ? (int) $_POST['tab_order'] : 0,
				'is_default'          => empty( $_POST['is_default'] ) ? 0 : 1,
				'alias_id'            => isset( $_POST['alias_id'] )
					? RichMenuClient::sanitize_alias_id( sanitize_text_field( wp_unslash( $_POST['alias_id'] ) ) )
					: '',
			)
		);

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'The rich menu could not be saved.', 'moksa-line' ) ) );
		}

		// Only one menu can be the channel default.
		if ( ! empty( $_POST['is_default'] ) ) {
			global $wpdb;
			$table = self::table();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_default = 0 WHERE id <> %d", $id ) );
		}

		$warnings = array();

		if ( ! empty( $_POST['image_attachment_id'] ) ) {
			$warnings = self::check_image( (int) $_POST['image_attachment_id'], $size );
		}

		wp_send_json_success(
			array(
				'id'       => $id,
				'message'  => __( 'Saved. Publish it to push the change to LINE.', 'moksa-line' ),
				'warnings' => $warnings,
			)
		);
	}

	/**
	 * Delete a menu locally and on LINE.
	 */
	public function ajax_delete(): void {
		$this->guard();

		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$row = self::find( $id );

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'That rich menu no longer exists.', 'moksa-line' ) ), 404 );
		}

		if ( '' !== (string) $row->alias_id ) {
			$dropped = RichMenuClient::delete_alias( (string) $row->alias_id );

			Logger::capture( $dropped, 'Could not remove the rich menu alias', 'richmenu' );
		}

		// Deleting whatever LINE is serving as the default takes the menu away
		// from every customer at once, and LINE reports nothing back when it
		// happens. Worth checking before, and saying so after.
		$was_default = '' !== (string) $row->richmenu_id
			&& RichMenuClient::default_id() === (string) $row->richmenu_id;

		if ( '' !== (string) $row->richmenu_id ) {
			$deleted = RichMenuClient::delete( (string) $row->richmenu_id );

			Logger::capture( $deleted, 'Could not delete the rich menu on LINE', 'richmenu' );
		}

		self::delete( $id );

		if ( $was_default ) {
			wp_send_json_success(
				array(
					'message' => __( 'Rich menu deleted. That was the default, so until you make another menu the default, customers see whatever the LINE Official Account Manager holds -- or no menu at all.', 'moksa-line' ),
					'warning' => true,
				)
			);
		}

		wp_send_json_success( array( 'message' => __( 'Rich menu deleted.', 'moksa-line' ) ) );
	}

	/**
	 * Publish one menu, or a whole tab group.
	 */
	public function ajax_publish(): void {
		$this->guard();

		$group = isset( $_POST['tab_group'] ) ? sanitize_text_field( wp_unslash( $_POST['tab_group'] ) ) : '';

		if ( '' !== $group ) {
			$report = self::publish_group( $group );

			if ( ! empty( $report['errors'] ) ) {
				wp_send_json_error(
					array(
						'message' => implode( ' | ', $report['errors'] ),
						'published' => $report['published'],
					)
				);
			}

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d: number of menus published. */
						_n( 'Published %d menu to LINE.', 'Published %d menus to LINE.', $report['published'], 'moksa-line' ),
						$report['published']
					),
				)
			);
		}

		$result = self::publish( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Published to LINE.', 'moksa-line' ) ) );
	}

	/**
	 * Make a published menu the channel default.
	 */
	public function ajax_set_default(): void {
		$this->guard();

		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$row = self::find( $id );

		if ( ! $row || '' === (string) $row->richmenu_id ) {
			wp_send_json_error( array( 'message' => __( 'Publish this menu to LINE before making it the default.', 'moksa-line' ) ) );
		}

		$result = RichMenuClient::set_default( (string) $row->richmenu_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$wpdb->query( "UPDATE {$table} SET is_default = 0" );

		self::save( array( 'id' => $id, 'is_default' => 1 ) );

		wp_send_json_success( array( 'message' => __( 'This menu is now the default for everyone.', 'moksa-line' ) ) );
	}

	/**
	 * Report what LINE actually has, so drift between the two is visible.
	 */
	public function ajax_sync(): void {
		$this->guard();

		$remote = RichMenuClient::all();

		if ( is_wp_error( $remote ) ) {
			wp_send_json_error( array( 'message' => $remote->get_error_message() ) );
		}

		$aliases = RichMenuClient::all_aliases();
		$known   = array();

		foreach ( self::all() as $row ) {
			if ( '' !== (string) $row->richmenu_id ) {
				$known[ (string) $row->richmenu_id ] = (string) $row->name;
			}
		}

		$orphans = array();

		foreach ( (array) ( $remote['richmenus'] ?? array() ) as $menu ) {
			$id = isset( $menu['richMenuId'] ) ? (string) $menu['richMenuId'] : '';

			if ( '' !== $id && ! isset( $known[ $id ] ) ) {
				$orphans[] = array(
					'id'   => $id,
					'name' => isset( $menu['name'] ) ? (string) $menu['name'] : '',
				);
			}
		}

		// Which menu LINE actually serves, and whether this site agrees. Drift
		// here is what makes a shop's menu quietly disappear, so it is worth
		// naming rather than leaving to be discovered on someone's phone.
		$line_default  = RichMenuClient::default_id();
		$local_default = '';

		foreach ( self::all() as $row ) {
			if ( (bool) $row->is_default ) {
				$local_default = (string) $row->richmenu_id;
			}
		}

		if ( '' === $line_default ) {
			$default_note = __( 'LINE has no default menu, so everyone falls back to whatever the Official Account Manager holds.', 'moksa-line' );
		} elseif ( $line_default === $local_default ) {
			$default_note = sprintf(
				/* translators: %s: rich menu name. */
				__( 'Default menu: %s, which matches this site.', 'moksa-line' ),
				isset( $known[ $line_default ] ) ? $known[ $line_default ] : $line_default
			);
		} else {
			$default_note = sprintf(
				/* translators: %s: rich menu name or id. */
				__( 'LINE serves %s as the default, which is not what this site has marked. Set the default again here to line them up.', 'moksa-line' ),
				isset( $known[ $line_default ] ) ? $known[ $line_default ] : $line_default
			);
		}

		wp_send_json_success(
			array(
				'remote_count'  => count( (array) ( $remote['richmenus'] ?? array() ) ),
				'local_count'   => count( $known ),
				'alias_count'   => is_wp_error( $aliases ) ? 0 : count( (array) ( $aliases['aliases'] ?? array() ) ),
				'orphans'       => $orphans,
				'default_note'  => $default_note,
				'default_ok'    => $line_default === $local_default,
			)
		);
	}

	/**
	 * Shared nonce and capability check for the AJAX handlers.
	 */
	private function guard(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage rich menus.', 'moksa-line' ) ), 403 );
		}
	}
}
