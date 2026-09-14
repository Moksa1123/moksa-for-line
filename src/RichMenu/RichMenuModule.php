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
 * @package Mofoline
 */

namespace Mofoline\RichMenu;

use Mofoline\Support\Db;
use Mofoline\Data\Repository;
use Mofoline\Api\RichMenuClient;
use Mofoline\Support\Files;
use Mofoline\Support\Logger;
use WP_Error;
use Mofoline\Admin\Ajax;

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
		add_action( 'wp_ajax_mofoline_richmenu_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_mofoline_richmenu_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_mofoline_richmenu_publish', array( $this, 'ajax_publish' ) );
		add_action( 'wp_ajax_mofoline_richmenu_default', array( $this, 'ajax_set_default' ) );
		add_action( 'wp_ajax_mofoline_richmenu_sync', array( $this, 'ajax_sync' ) );
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
			return new WP_Error( 'mofoline_menu_missing', __( 'That rich menu no longer exists.', 'moksa-for-line' ) );
		}

		$image = self::image_bytes( (int) $row->image_attachment_id );

		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$areas = json_decode( (string) $row->areas, true );

		if ( ! is_array( $areas ) || empty( $areas ) ) {
			return new WP_Error(
				'mofoline_menu_no_areas',
				__( 'This rich menu has no tappable areas yet.', 'moksa-for-line' )
			);
		}

		$definition = array(
			'size'        => RichMenuClient::size( (string) $row->size ),
			'selected'    => (bool) $row->selected,
			'name'        => mb_substr( (string) $row->name, 0, 300 ),
			'chatBarText' => mb_substr( '' !== $row->chat_bar_text ? (string) $row->chat_bar_text : __( 'Menu', 'moksa-for-line' ), 0, 14 ),
			'areas'       => self::normalize_areas( $areas, (string) $row->size ),
		);

		$created = RichMenuClient::create( $definition );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$new_id = isset( $created['richMenuId'] ) ? (string) $created['richMenuId'] : '';

		if ( '' === $new_id ) {
			return new WP_Error( 'mofoline_menu_no_id', __( 'LINE accepted the menu but did not return an id.', 'moksa-for-line' ) );
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
				$table = self::table();

				Db::query( Db::prepare( "UPDATE %i SET is_default = 0 WHERE id <> %d", $table, $id ) );
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
		$table = self::table();

		return (array) Db::get_results(
			Db::prepare( "SELECT * FROM %i WHERE tab_group = %s ORDER BY tab_order ASC, id ASC", $table, $group )
		);
	}

	/**
	 * All distinct tab group names.
	 *
	 * @return string[]
	 */
	public static function groups(): array {
		$table = self::table();

		$groups = Db::get_col( Db::prepare( "SELECT DISTINCT tab_group FROM %i WHERE tab_group <> '' ORDER BY tab_group ASC", $table ) );

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
				'mofoline_menu_no_image',
				__( 'Choose a menu image first. LINE requires one, 2500px wide and either 1686px or 843px tall.', 'moksa-for-line' )
			);
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! is_readable( $path ) ) {
			return new WP_Error(
				'mofoline_menu_image_unreadable',
				__( 'The selected image could not be read from the media library.', 'moksa-for-line' )
			);
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		if ( 'image/jpg' === $mime ) {
			$mime = 'image/jpeg';
		}

		$bytes = Files::read( $path );

		if ( false === $bytes ) {
			return new WP_Error(
				'mofoline_menu_image_unreadable',
				__( 'The selected image could not be read from disk.', 'moksa-for-line' )
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
			return array( __( 'Could not read the image dimensions.', 'moksa-for-line' ) );
		}

		if ( (int) $meta['width'] !== $expected['width'] || (int) $meta['height'] !== $expected['height'] ) {
			$problems[] = sprintf(
				/* translators: 1: required width, 2: required height, 3: actual width, 4: actual height. */
				__( 'LINE expects %1$d x %2$d pixels for this menu size; this image is %3$d x %4$d.', 'moksa-for-line' ),
				$expected['width'],
				$expected['height'],
				(int) $meta['width'],
				(int) $meta['height']
			);
		}

		$path = get_attached_file( $attachment_id );

		if ( $path && file_exists( $path ) && filesize( $path ) > RichMenuClient::MAX_IMAGE_BYTES ) {
			$problems[] = __( 'The image is larger than 1 MB, which LINE will reject.', 'moksa-for-line' );
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		if ( ! in_array( $mime, array( 'image/jpeg', 'image/jpg', 'image/png' ), true ) ) {
			$problems[] = __( 'Rich menu images must be JPEG or PNG.', 'moksa-for-line' );
		}

		return $problems;
	}

	// --- AJAX -------------------------------------------------------------------

	/**
	 * Save a menu definition locally. Publishing is a separate, explicit step.
	 */
	public function ajax_save(): void {
		$this->guard();

		$areas = '' === Ajax::text( 'areas' ) ? array() : Ajax::json_verbatim( 'areas' );

		if ( ! is_array( $areas ) ) {
			wp_send_json_error( array( 'message' => __( 'The tappable areas could not be read.', 'moksa-for-line' ) ) );
		}

		$size  = Ajax::is( 'size', 'half' ) ? 'half' : 'full';
		$group = Ajax::text( 'tab_group' );
		$name  = Ajax::text( 'name' );

		// The editor marks this field required, which is not the same as it
		// being required: posting nothing at all saved a nameless menu that
		// then sat in the list with a blank row where its name should be.
		if ( '' === trim( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Give the rich menu a name.', 'moksa-for-line' ) ) );
		}

		$id = self::save(
			array(
				'id'                  => Ajax::int( 'id' ),
				'name'                => $name,
				'chat_bar_text'       => Ajax::text( 'chat_bar_text' ),
				'size'                => $size,
				'selected'            => Ajax::flag( 'selected' ) ? 1 : 0,
				'areas'               => wp_json_encode( self::normalize_areas( $areas, $size ) ),
				'image_attachment_id' => Ajax::int( 'image_attachment_id' ),
				'tab_group'           => $group,
				'tab_order'           => Ajax::int( 'tab_order' ),
				'is_default'          => Ajax::flag( 'is_default' ) ? 1 : 0,
				'alias_id'            => RichMenuClient::sanitize_alias_id( Ajax::text( 'alias_id' ) ),
			)
		);

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'The rich menu could not be saved.', 'moksa-for-line' ) ) );
		}

		// Only one menu can be the channel default.
		if ( Ajax::flag( 'is_default' ) ) {
			$table = self::table();

			Db::query( Db::prepare( "UPDATE %i SET is_default = 0 WHERE id <> %d", $table, $id ) );
		}

		$warnings = array();

		if ( Ajax::int( 'image_attachment_id' ) > 0 ) {
			$warnings = self::check_image( Ajax::int( 'image_attachment_id' ), $size );
		}

		wp_send_json_success(
			array(
				'id'       => $id,
				'message'  => __( 'Saved. Publish it to push the change to LINE.', 'moksa-for-line' ),
				'warnings' => $warnings,
			)
		);
	}

	/**
	 * Delete a menu locally and on LINE.
	 */
	public function ajax_delete(): void {
		$this->guard();

		$id  = Ajax::int( 'id' );
		$row = self::find( $id );

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'That rich menu no longer exists.', 'moksa-for-line' ) ), 404 );
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
					'message' => __( 'Rich menu deleted. That was the default, so until you make another menu the default, customers see whatever the LINE Official Account Manager holds -- or no menu at all.', 'moksa-for-line' ),
					'warning' => true,
				)
			);
		}

		wp_send_json_success( array( 'message' => __( 'Rich menu deleted.', 'moksa-for-line' ) ) );
	}

	/**
	 * Publish one menu, or a whole tab group.
	 */
	public function ajax_publish(): void {
		$this->guard();

		$group = Ajax::text( 'tab_group' );

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
						_n( 'Published %d menu to LINE.', 'Published %d menus to LINE.', $report['published'], 'moksa-for-line' ),
						$report['published']
					),
				)
			);
		}

		$result = self::publish( Ajax::int( 'id' ) );

		Ajax::bail( $result );

		wp_send_json_success( array( 'message' => __( 'Published to LINE.', 'moksa-for-line' ) ) );
	}

	/**
	 * Make a published menu the channel default.
	 */
	public function ajax_set_default(): void {
		$this->guard();

		$id  = Ajax::int( 'id' );
		$row = self::find( $id );

		if ( ! $row || '' === (string) $row->richmenu_id ) {
			wp_send_json_error( array( 'message' => __( 'Publish this menu to LINE before making it the default.', 'moksa-for-line' ) ) );
		}

		$result = RichMenuClient::set_default( (string) $row->richmenu_id );

		Ajax::bail( $result );

		$table = self::table();

		Db::query( Db::prepare( "UPDATE %i SET is_default = 0", $table ) );

		self::save( array( 'id' => $id, 'is_default' => 1 ) );

		wp_send_json_success( array( 'message' => __( 'This menu is now the default for everyone.', 'moksa-for-line' ) ) );
	}

	/**
	 * Report what LINE actually has, so drift between the two is visible.
	 */
	public function ajax_sync(): void {
		$this->guard();

		$remote = RichMenuClient::all();

		Ajax::bail( $remote );

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
			$default_note = __( 'LINE has no default menu, so everyone falls back to whatever the Official Account Manager holds.', 'moksa-for-line' );
		} elseif ( $line_default === $local_default ) {
			$default_note = sprintf(
				/* translators: %s: rich menu name. */
				__( 'Default menu: %s, which matches this site.', 'moksa-for-line' ),
				isset( $known[ $line_default ] ) ? $known[ $line_default ] : $line_default
			);
		} else {
			$default_note = sprintf(
				/* translators: %s: rich menu name or id. */
				__( 'LINE serves %s as the default, which is not what this site has marked. Set the default again here to line them up.', 'moksa-for-line' ),
				isset( $known[ $line_default ] ) ? $known[ $line_default ] : $line_default
			);
		}

		// Built here rather than in JavaScript: assembled there, these lines were
		// concatenated English and never reached a translator, so the panel came
		// out half Chinese and half English on a site running in Chinese.
		$remote_count = count( (array) ( $remote['richmenus'] ?? array() ) );
		$alias_count  = is_wp_error( $aliases ) ? 0 : count( (array) ( $aliases['aliases'] ?? array() ) );

		$lines = array(
			sprintf(
				/* translators: 1: number of rich menus, 2: number of aliases. */
				_n( 'LINE has %1$s rich menu and %2$s aliases.', 'LINE has %1$s rich menus and %2$s aliases.', $remote_count, 'moksa-for-line' ),
				number_format_i18n( $remote_count ),
				number_format_i18n( $alias_count )
			),
			sprintf(
				/* translators: %s: number of rich menus tracked by this site. */
				_n( 'This site tracks %s.', 'This site tracks %s.', count( $known ), 'moksa-for-line' ),
				number_format_i18n( count( $known ) )
			),
			$default_note,
		);

		// Orphans routinely share a name -- five menus all called "Moksa Default
		// Menu" is what an older plugin leaves behind -- so the id has to be
		// there, or the list cannot be acted on at all.
		foreach ( $orphans as $orphan ) {
			$lines[] = '' !== $orphan['name']
				? sprintf(
					/* translators: 1: rich menu name, 2: rich menu id. */
					__( 'Only on LINE: %1$s (%2$s)', 'moksa-for-line' ),
					$orphan['name'],
					$orphan['id']
				)
				: sprintf(
					/* translators: %s: rich menu id. */
					__( 'Only on LINE: %s', 'moksa-for-line' ),
					$orphan['id']
				);
		}

		wp_send_json_success(
			array(
				'remote_count'  => $remote_count,
				'local_count'   => count( $known ),
				'alias_count'   => $alias_count,
				'orphans'       => $orphans,
				'default_note'  => $default_note,
				'default_ok'    => $line_default === $local_default,
				'lines'         => $lines,
				'message'       => __( 'Comparison complete.', 'moksa-for-line' ),
			)
		);
	}

	/**
	 * Shared nonce and capability check for the AJAX handlers.
	 */
	private function guard(): void {
		Ajax::guard( __( 'You do not have permission to manage rich menus.', 'moksa-for-line' ) );
	}
}
