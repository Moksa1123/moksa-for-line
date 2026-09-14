<?php
/**
 * Imagemap messages: one picture, up to 50 tappable regions.
 *
 * An imagemap is a banner the customer taps, and the parts they tap are
 * invisible -- there is nothing on screen to say where a region begins or
 * ends. That makes it the message type where drawing the regions on the image
 * matters most, so it reuses the rich menu's area editor rather than asking
 * anyone to type coordinates in a 1040-wide space.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Imagemap;

use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Data\Imagemaps;
use Moksa\Line\Admin\Ajax;

defined( 'ABSPATH' ) || exit;

class ImagemapModule {

	const NAMESPACE_V1 = 'moksa-line/v1';

	/** LINE's limit on tappable regions. */
	const MAX_ACTIONS = 50;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_ajax_moksa_line_imagemap_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_moksa_line_imagemap_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_moksa_line_imagemap_send_test', array( $this, 'ajax_send_test' ) );
	}

	/**
	 * The route LINE fetches the base image from.
	 *
	 * The width is a path segment rather than a query argument because LINE
	 * builds the URL itself by appending /{width} to whatever baseUrl it was
	 * given. It also refuses a URL with a file extension, which is the other
	 * reason this cannot simply be an uploads URL.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/imagemap/(?P<id>\d+)/(?P<width>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'serve_image' ),
				// Deliberately public: LINE fetches this unauthenticated, and
				// it only ever exposes an image the shop chose to broadcast.
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'    => array( 'sanitize_callback' => 'absint' ),
					'width' => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	/**
	 * The base URL to hand LINE, with no width and no extension on it.
	 *
	 * @param int $id Imagemap row id.
	 */
	public static function base_url( int $id ): string {
		return rest_url( self::NAMESPACE_V1 . '/imagemap/' . $id );
	}

	/**
	 * Stream one rendition.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function serve_image( $request ) {
		ImagemapImages::serve( (int) $request['id'], (int) $request['width'] );
	}

	/**
	 * Build the message object for one stored imagemap.
	 *
	 * @param object $row Imagemap row.
	 * @return array|\WP_Error
	 */
	public static function message( $row ) {
		$actions = json_decode( (string) $row->actions, true );
		$actions = is_array( $actions ) ? $actions : array();

		if ( empty( $actions ) ) {
			return new \WP_Error(
				'moksa_line_imagemap_no_actions',
				__( 'This imagemap has no tappable areas yet.', 'moksa-line' )
			);
		}

		if ( ! ImagemapImages::complete( (int) $row->id ) ) {
			return new \WP_Error(
				'moksa_line_imagemap_incomplete',
				__( 'The image sizes for this imagemap are missing. Choose the image again and save.', 'moksa-line' )
			);
		}

		$base = self::base_url( (int) $row->id );

		if ( 0 !== stripos( $base, 'https://' ) ) {
			return new \WP_Error(
				'moksa_line_imagemap_not_https',
				__( 'LINE only accepts an imagemap image over HTTPS, and this site is served over plain HTTP.', 'moksa-line' )
			);
		}

		return array(
			'type'     => 'imagemap',
			'baseUrl'  => $base,
			'altText'  => mb_substr( (string) $row->alt_text, 0, 1500 ),
			'baseSize' => array(
				'width'  => (int) $row->base_width,
				'height' => (int) $row->base_height,
			),
			'actions'  => self::clean_actions( $actions ),
		);
	}

	/**
	 * Turn the editor's areas into imagemap action objects.
	 *
	 * The editor stores what the rich menu editor stores -- a bounds rectangle
	 * plus an action -- so the shapes are translated here rather than making
	 * the editor learn a second format.
	 *
	 * @param array $areas Areas from the editor.
	 * @return array
	 */
	public static function clean_actions( array $areas ): array {
		$actions = array();

		foreach ( array_slice( $areas, 0, self::MAX_ACTIONS ) as $area ) {
			if ( ! is_array( $area ) || empty( $area['bounds'] ) ) {
				continue;
			}

			$bounds = $area['bounds'];
			$type   = isset( $area['action']['type'] ) ? (string) $area['action']['type'] : 'uri';

			$built = array(
				'area' => array(
					'x'      => max( 0, (int) ( $bounds['x'] ?? 0 ) ),
					'y'      => max( 0, (int) ( $bounds['y'] ?? 0 ) ),
					'width'  => max( 1, (int) ( $bounds['width'] ?? 1 ) ),
					'height' => max( 1, (int) ( $bounds['height'] ?? 1 ) ),
				),
			);

			// An imagemap label is only read out by a screen reader, so it is
			// worth carrying but never worth failing over.
			$label = isset( $area['action']['label'] ) ? trim( (string) $area['action']['label'] ) : '';

			if ( '' !== $label ) {
				$built['label'] = mb_substr( $label, 0, 100 );
			}

			if ( 'message' === $type ) {
				$text = isset( $area['action']['text'] ) ? (string) $area['action']['text'] : '';

				if ( '' === trim( $text ) ) {
					continue;
				}

				$built['type'] = 'message';
				$built['text'] = mb_substr( $text, 0, 400 );
			} else {
				$uri = isset( $area['action']['uri'] ) ? (string) $area['action']['uri'] : '';

				if ( '' === trim( $uri ) ) {
					continue;
				}

				$built['type']    = 'uri';
				$built['linkUri'] = mb_substr( $uri, 0, 1000 );
			}

			$actions[] = $built;
		}

		return $actions;
	}

	/**
	 * Save an imagemap and cut its image sizes.
	 */
	public function ajax_save(): void {
		$this->guard();

		$id      = Ajax::int( 'id' );
		$name    = Ajax::text( 'name' );
		$alt     = Ajax::text( 'alt_text' );
		$image   = Ajax::int( 'image_attachment_id' );
		$decoded = '' === Ajax::text( 'areas' ) ? array() : Ajax::json_verbatim( 'areas' );

		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => __( 'The tappable areas could not be read.', 'moksa-line' ) ) );
		}

		if ( '' === trim( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Give this imagemap a name.', 'moksa-line' ) ) );
		}

		if ( '' === trim( $alt ) ) {
			wp_send_json_error( array( 'message' => __( 'Fallback text is required: it is all the customer sees in the chat list.', 'moksa-line' ) ) );
		}

		if ( $image <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Choose the banner image first.', 'moksa-line' ) ) );
		}

		$fields = array(
			'name'                => $name,
			'alt_text'            => $alt,
			'image_attachment_id' => $image,
			'actions'             => (string) wp_json_encode( array_values( $decoded ) ),
			'base_width'          => 1040,
			'base_height'         => 1040,
		);

		if ( $id > 0 ) {
			$fields['id'] = $id;
		}

		$saved = Imagemaps::save( $fields );

		if ( $saved <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'The imagemap could not be saved.', 'moksa-line' ) ) );
		}

		// Cutting happens after the row exists, because the files are named
		// after its id -- and the real height is only known once the image has
		// been measured, so the row is corrected afterwards.
		$built = ImagemapImages::build( $saved, $image );

		if ( is_wp_error( $built ) ) {
			wp_send_json_error(
				array(
					'message' => $built->get_error_message(),
					'id'      => $saved,
				)
			);
		}

		Imagemaps::save(
			array(
				'id'          => $saved,
				'base_width'  => $built['base_width'],
				'base_height' => $built['base_height'],
			)
		);

		wp_send_json_success(
			array(
				'id'          => $saved,
				'base_height' => $built['base_height'],
				'message'     => __( 'Saved.', 'moksa-line' ),
			)
		);
	}

	/**
	 * Delete an imagemap and its cut sizes.
	 */
	public function ajax_delete(): void {
		$this->guard();

		$id = Ajax::int( 'id' );

		// The derived images go first either way -- they are worth clearing up
		// even for a row that has already gone -- but the row is what decides
		// whether anything was actually deleted.
		if ( $id > 0 ) {
			ImagemapImages::delete( $id );
		}

		if ( $id <= 0 || ! Imagemaps::delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That imagemap no longer exists.', 'moksa-line' ) ), 404 );
		}

		wp_send_json_success( array( 'message' => __( 'Imagemap deleted.', 'moksa-line' ) ) );
	}

	/**
	 * Push one imagemap to a single LINE user.
	 */
	public function ajax_send_test(): void {
		$this->guard();

		$id     = Ajax::int( 'id' );
		$target = Ajax::text( 'line_user_id' );

		if ( '' === $target ) {
			wp_send_json_error( array( 'message' => __( 'Enter the LINE user id to send the test to.', 'moksa-line' ) ) );
		}

		$row = Imagemaps::find( $id );

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'That imagemap no longer exists.', 'moksa-line' ) ) );
		}

		$message = self::message( $row );

		Ajax::bail( $message );

		$result = MessagingClient::push( $target, array( $message ) );

		Ajax::bail( $result, 'Could not send an imagemap test', 'imagemap' );

		wp_send_json_success( array( 'message' => __( 'Sent. Check the chat on your phone.', 'moksa-line' ) ) );
	}

	private function guard(): void {
		Ajax::guard( __( 'You do not have permission to manage imagemaps.', 'moksa-line' ) );
	}
}
