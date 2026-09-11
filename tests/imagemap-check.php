<?php
/**
 * Cut an imagemap into the five widths LINE asks for and check every one.
 *
 *   wp eval-file tests/imagemap-check.php
 *
 * LINE treats baseUrl as a prefix and fetches /240 /300 /460 /700 /1040 from
 * it, refuses a URL with a file extension, and rejects a rendition over 1 MB.
 * Nothing in the admin shows whether those five files were actually written,
 * so a broken cut is invisible until a customer taps a dead image.
 *
 * Creates an attachment and the derived files, and deletes both again.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Imagemap\ImagemapImages;

if ( 'production' === wp_get_environment_type() && ! defined( 'MOKSA_LINE_ALLOW_DESTRUCTIVE_TESTS' ) ) {
	echo "REFUSED: this site reports WP_ENVIRONMENT_TYPE=production.\n";
	echo "It creates and deletes an attachment and its imagemap renditions.\n";
	echo "If this really is a throwaway site, set WP_ENVIRONMENT_TYPE, or define\n";
	echo "MOKSA_LINE_ALLOW_DESTRUCTIVE_TESTS in wp-config.php, and run it again.\n";
	return;
}

$GLOBALS['im_fail'] = 0;

function im_check( $ok, $label, $detail = '' ) {
	if ( $ok ) {
		echo "  ok    {$label}\n";
		return;
	}

	++$GLOBALS['im_fail'];
	echo "  FAIL  {$label}" . ( '' !== $detail ? " -- {$detail}" : '' ) . "\n";
}

// A wide source image, the shape a real imagemap uses.
$width  = 2000;
$height = 1000;
$canvas = imagecreatetruecolor( $width, $height );

imagefilledrectangle( $canvas, 0, 0, $width, $height, imagecolorallocate( $canvas, 6, 199, 85 ) );
imagefilledrectangle( $canvas, 0, 0, $width / 2, $height, imagecolorallocate( $canvas, 20, 40, 80 ) );

$uploads = wp_upload_dir();
$source  = trailingslashit( $uploads['path'] ) . 'moksa-imagemap-test.jpg';

imagejpeg( $canvas, $source, 90 );
imagedestroy( $canvas );

$attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => 'Imagemap test',
		'post_status'    => 'inherit',
	),
	$source
);

require_once ABSPATH . 'wp-admin/includes/image.php';
wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $source ) );

$imagemap_id = 999001;
$built       = ImagemapImages::build( $imagemap_id, (int) $attachment_id );

echo "Building\n";
im_check( ! is_wp_error( $built ), 'the five renditions are cut', is_wp_error( $built ) ? $built->get_error_message() : '' );
im_check( ImagemapImages::complete( $imagemap_id ), 'all five files exist' );

echo "\nEach width\n";

foreach ( ImagemapImages::WIDTHS as $w ) {
	$file = ImagemapImages::file( $imagemap_id, $w );

	if ( ! file_exists( $file ) ) {
		im_check( false, "{$w} was written" );
		continue;
	}

	$size = getimagesize( $file );
	$bytes = filesize( $file );

	im_check( $size && (int) $size[0] === $w, "{$w} is exactly {$w}px wide", $size ? $size[0] . 'px' : 'unreadable' );
	im_check( $size && 'image/jpeg' === $size['mime'], "{$w} is a JPEG", $size ? $size['mime'] : '' );
	im_check( $bytes < 1024 * 1024, "{$w} is under LINE's 1 MB limit", size_format( $bytes ) );
	im_check(
		$size && abs( ( $size[1] / $size[0] ) - ( $height / $width ) ) < 0.01,
		"{$w} keeps the aspect ratio",
		$size ? $size[0] . 'x' . $size[1] : ''
	);
}

echo "\nThe URL LINE is given\n";
$base = \Moksa\Line\Imagemap\ImagemapModule::base_url( $imagemap_id );
im_check( 0 === strpos( $base, 'https://' ), 'is https, which LINE requires', $base );
im_check( ! preg_match( '/\.(jpg|jpeg|png)$/i', $base ), 'carries no file extension, which LINE refuses', $base );
im_check( false === strpos( $base, '?' ), 'is a path rather than a query string', $base );

echo "\nCleaning up\n";
ImagemapImages::delete( $imagemap_id );
im_check( ! ImagemapImages::complete( $imagemap_id ), 'deleting removes the renditions' );

wp_delete_attachment( (int) $attachment_id, true );

echo $GLOBALS['im_fail'] > 0 ? "\n{$GLOBALS['im_fail']} failed\n" : "\nIMAGEMAPS OK\n";
