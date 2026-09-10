<?php
/**
 * Serving an imagemap's base image at the five widths LINE asks for.
 *
 * This is the whole reason imagemap is awkward on WordPress. LINE does not
 * fetch the URL you give it. It treats baseUrl as a prefix and requests
 * baseUrl/240, /300, /460, /700 and /1040, picking the size that suits the
 * device -- and it refuses a URL with a file extension in it. So a media
 * library URL cannot be a baseUrl: LINE would ask for
 * .../promo.png/700, which is not a file, and the message would arrive blank.
 *
 * The plugin therefore serves the sizes itself, from a REST route whose URL
 * has no extension. The five copies are cut once when the image is chosen
 * rather than per request, because a single broadcast has LINE fetching these
 * once per recipient device class and resizing on the fly would be paid for
 * over and over.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Imagemap;

use Moksa\Line\Support\Logger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class ImagemapImages {

	/** The widths LINE requests. Not ours to choose. */
	const WIDTHS = array( 240, 300, 460, 700, 1040 );

	/** LINE's own limit on each rendition. */
	const MAX_BYTES = 1048576;

	/**
	 * Where the cut copies live.
	 *
	 * Kept out of the media library on purpose: these are five near-identical
	 * renditions of one picture and would be noise in a shop's uploads screen,
	 * and deleting one by accident would break a live message.
	 *
	 * @return array{path:string,url:string}
	 */
	public static function dir(): array {
		$uploads = wp_upload_dir();

		return array(
			'path' => trailingslashit( $uploads['basedir'] ) . 'moksa-line/imagemap',
			'url'  => trailingslashit( $uploads['baseurl'] ) . 'moksa-line/imagemap',
		);
	}

	/**
	 * The file for one imagemap at one width.
	 *
	 * @param int $imagemap_id Imagemap row id.
	 * @param int $width       One of WIDTHS.
	 */
	public static function file( int $imagemap_id, int $width ): string {
		return self::dir()['path'] . '/' . $imagemap_id . '-' . $width . '.jpg';
	}

	/**
	 * Cut the five sizes from an attachment.
	 *
	 * @param int $imagemap_id   Imagemap row id.
	 * @param int $attachment_id Source image in the media library.
	 * @return array|WP_Error The base height at 1040 wide, or why not.
	 */
	public static function build( int $imagemap_id, int $attachment_id ) {
		$source = get_attached_file( $attachment_id );

		if ( ! $source || ! file_exists( $source ) ) {
			return new WP_Error(
				'moksa_line_imagemap_no_file',
				__( 'That image could not be read from the media library.', 'moksa-line' )
			);
		}

		$size = @getimagesize( $source );

		if ( ! $size || empty( $size[0] ) ) {
			return new WP_Error(
				'moksa_line_imagemap_no_size',
				__( 'Could not read the image dimensions.', 'moksa-line' )
			);
		}

		list( $source_width, $source_height ) = $size;

		if ( $source_width < 1040 ) {
			return new WP_Error(
				'moksa_line_imagemap_too_small',
				sprintf(
					/* translators: %d: the image's width in pixels. */
					__( 'The image is only %d pixels wide. An imagemap needs at least 1040, because LINE renders it at that width.', 'moksa-line' ),
					(int) $source_width
				)
			);
		}

		$dir = self::dir();

		if ( ! wp_mkdir_p( $dir['path'] ) ) {
			return new WP_Error(
				'moksa_line_imagemap_no_dir',
				__( 'Could not create the folder for the imagemap images.', 'moksa-line' )
			);
		}

		// The height LINE is told about is the height at 1040 wide, whatever
		// the original was.
		$base_height = (int) round( $source_height * ( 1040 / $source_width ) );

		foreach ( self::WIDTHS as $width ) {
			$editor = wp_get_image_editor( $source );

			if ( is_wp_error( $editor ) ) {
				return $editor;
			}

			$editor->resize( $width, null, false );

			// JPEG rather than the source format: these are photographic
			// banners, and a 1040-wide PNG routinely passes LINE's 1 MB limit
			// while the same picture as JPEG does not.
			$editor->set_quality( 82 );

			$saved = $editor->save( self::file( $imagemap_id, $width ), 'image/jpeg' );

			if ( is_wp_error( $saved ) ) {
				return $saved;
			}

			$bytes = (int) @filesize( $saved['path'] );

			if ( $bytes > self::MAX_BYTES ) {
				return new WP_Error(
					'moksa_line_imagemap_too_big',
					sprintf(
						/* translators: 1: width in pixels, 2: file size. */
						__( 'The %1$dpx copy came out at %2$s, over the 1 MB LINE allows. Use a simpler or smaller image.', 'moksa-line' ),
						$width,
						size_format( $bytes )
					)
				);
			}
		}

		return array(
			'base_width'  => 1040,
			'base_height' => $base_height,
		);
	}

	/**
	 * Remove the cut copies for one imagemap.
	 *
	 * @param int $imagemap_id Imagemap row id.
	 */
	public static function delete( int $imagemap_id ): void {
		foreach ( self::WIDTHS as $width ) {
			$file = self::file( $imagemap_id, $width );

			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Whether every width is present on disk.
	 *
	 * @param int $imagemap_id Imagemap row id.
	 */
	public static function complete( int $imagemap_id ): bool {
		foreach ( self::WIDTHS as $width ) {
			if ( ! file_exists( self::file( $imagemap_id, $width ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Send one rendition to LINE.
	 *
	 * @param int $imagemap_id Imagemap row id.
	 * @param int $width       Requested width.
	 */
	public static function serve( int $imagemap_id, int $width ): void {
		if ( ! in_array( $width, self::WIDTHS, true ) ) {
			status_header( 404 );
			exit;
		}

		$file = self::file( $imagemap_id, $width );

		if ( ! file_exists( $file ) ) {
			Logger::warning(
				'LINE asked for an imagemap size that has not been cut',
				array( 'imagemap' => $imagemap_id, 'width' => $width ),
				'imagemap'
			);

			status_header( 404 );
			exit;
		}

		$modified = (int) filemtime( $file );
		$etag     = '"' . md5( $imagemap_id . '-' . $width . '-' . $modified ) . '"';

		// LINE fetches these repeatedly across a broadcast, so a conditional
		// request should cost a header and nothing else.
		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] )
			&& trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) ) === $etag ) {
			status_header( 304 );
			exit;
		}

		nocache_headers();
		header_remove( 'Cache-Control' );
		header_remove( 'Expires' );

		header( 'Content-Type: image/jpeg' );
		header( 'Content-Length: ' . (int) filesize( $file ) );
		header( 'Cache-Control: public, max-age=86400' );
		header( 'ETag: ' . $etag );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT' );

		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a file is the point.
		exit;
	}
}
