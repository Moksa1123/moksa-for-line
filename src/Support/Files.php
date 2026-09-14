<?php
/**
 * Reading local files the WordPress way.
 *
 * Two places read a file this plugin wrote itself -- a rich menu image on its
 * way to LINE, an imagemap tile on its way to a browser. Both go through
 * WP_Filesystem so a host that routes file access through FTP or a
 * restricted layer still works.
 *
 * @package Mofoline
 */

namespace Mofoline\Support;

defined( 'ABSPATH' ) || exit;

final class Files {

	/**
	 * The contents of a local file, or false when it cannot be read.
	 *
	 * @param string $path Absolute path.
	 * @return string|false
	 */
	public static function read( string $path ) {
		$filesystem = self::filesystem();

		if ( null === $filesystem || ! $filesystem->exists( $path ) ) {
			return false;
		}

		return $filesystem->get_contents( $path );
	}

	/**
	 * The WordPress filesystem, set up on first use.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private static function filesystem() {
		global $wp_filesystem;

		if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			if ( ! WP_Filesystem() ) {
				return null;
			}
		}

		return $wp_filesystem instanceof \WP_Filesystem_Base ? $wp_filesystem : null;
	}
}
