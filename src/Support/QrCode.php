<?php
/**
 * A QR encoder, because the alternatives are worse.
 *
 * The membership card needs a QR on a customer-facing page. Fetching a library
 * from a CDN would put every member's code on someone else's domain and break
 * the card whenever that host is unreachable; Composer would add a dependency
 * to a plugin that has none. So it is here: byte mode, error correction level
 * M, versions 1 to 10, which covers a URL of up to 216 bytes.
 *
 * Verified against an independent encoder (segno) and an independent decoder
 * (OpenCV) -- see tests/qr-check.php and the notes there.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Support;

defined( 'ABSPATH' ) || exit;

class QrCode {

	/** Error correction level M: recovers about 15%. */
	const EC_LEVEL_BITS = 0b00;

	/**
	 * Per version: EC codewords per block, then the block groups as
	 * [block count, data codewords per block].
	 */
	const VERSIONS = array(
		1  => array( 'ec' => 10, 'blocks' => array( array( 1, 16 ) ) ),
		2  => array( 'ec' => 16, 'blocks' => array( array( 1, 28 ) ) ),
		3  => array( 'ec' => 26, 'blocks' => array( array( 1, 44 ) ) ),
		4  => array( 'ec' => 18, 'blocks' => array( array( 2, 32 ) ) ),
		5  => array( 'ec' => 24, 'blocks' => array( array( 2, 43 ) ) ),
		6  => array( 'ec' => 16, 'blocks' => array( array( 4, 27 ) ) ),
		7  => array( 'ec' => 18, 'blocks' => array( array( 4, 31 ) ) ),
		8  => array( 'ec' => 22, 'blocks' => array( array( 2, 38 ), array( 2, 39 ) ) ),
		9  => array( 'ec' => 22, 'blocks' => array( array( 3, 36 ), array( 2, 37 ) ) ),
		10 => array( 'ec' => 26, 'blocks' => array( array( 4, 43 ), array( 1, 44 ) ) ),
	);

	/** Alignment pattern centres per version. */
	const ALIGNMENT = array(
		1  => array(),
		2  => array( 6, 18 ),
		3  => array( 6, 22 ),
		4  => array( 6, 26 ),
		5  => array( 6, 30 ),
		6  => array( 6, 34 ),
		7  => array( 6, 22, 38 ),
		8  => array( 6, 24, 42 ),
		9  => array( 6, 26, 46 ),
		10 => array( 6, 28, 50 ),
	);

	/** @var int[] GF(256) exponent table. */
	private static $exp = array();

	/** @var int[] GF(256) log table. */
	private static $log = array();

	/**
	 * The module grid for a string.
	 *
	 * @param string   $text       Data to encode, treated as bytes.
	 * @param int|null $force_mask Pin the mask instead of choosing one. Only for
	 *                             tests, which compare against a reference
	 *                             encoder mask by mask.
	 * @return array<int,array<int,bool>>|null Rows of modules, or null when it does not fit.
	 */
	public static function matrix( string $text, ?int $force_mask = null ): ?array {
		$length  = strlen( $text );
		$version = self::version_for( $length );

		if ( null === $version ) {
			return null;
		}

		$codewords = self::codewords( $text, $version );
		$size      = 17 + 4 * $version;

		// Build the fixed patterns once; the mask must not disturb them, so
		// they are tracked separately from the data.
		$grid     = array_fill( 0, $size, array_fill( 0, $size, false ) );
		$reserved = array_fill( 0, $size, array_fill( 0, $size, false ) );

		self::place_finders( $grid, $reserved, $size );
		self::place_alignment( $grid, $reserved, $version, $size );
		self::place_timing( $grid, $reserved, $size );
		self::reserve_format( $reserved, $size, $version );

		// The dark module, which is always set.
		$grid[ 4 * $version + 9 ][8] = true;
		$reserved[ 4 * $version + 9 ][8] = true;

		self::place_data( $grid, $reserved, $codewords, $size );

		$mask = null !== $force_mask ? $force_mask : self::best_mask( $grid, $reserved, $size );

		self::apply_mask( $grid, $reserved, $size, $mask );
		self::place_format( $grid, $size, $mask );

		if ( $version >= 7 ) {
			self::place_version( $grid, $size, $version );
		}

		return $grid;
	}

	/**
	 * The elements and attributes svg() emits, in the shape wp_kses() takes.
	 *
	 * @return array
	 */
	public static function allowed_html(): array {
		return array(
			'svg'  => array(
				'xmlns'   => true,
				'viewbox' => true,
				'width'   => true,
				'height'  => true,
				'role'    => true,
			),
			'rect' => array(
				'width'  => true,
				'height' => true,
				'fill'   => true,
			),
			'path' => array(
				'd'    => true,
				'fill' => true,
			),
		);
	}

	/**
	 * The code as an SVG, ready to drop into a page.
	 *
	 * @param string $text   Data to encode.
	 * @param int    $module Pixel size of one module.
	 * @param int    $quiet  Quiet zone in modules; the spec asks for 4.
	 * @return string SVG markup, or '' when the data does not fit.
	 */
	public static function svg( string $text, int $module = 6, int $quiet = 4 ): string {
		$grid = self::matrix( $text );

		if ( null === $grid ) {
			return '';
		}

		$size  = count( $grid );
		$total = ( $size + 2 * $quiet ) * $module;
		$path  = '';

		foreach ( $grid as $y => $row ) {
			foreach ( $row as $x => $on ) {
				if ( $on ) {
					$path .= sprintf(
						'M%d %dh%dv%dh-%dz',
						( $x + $quiet ) * $module,
						( $y + $quiet ) * $module,
						$module,
						$module,
						$module
					);
				}
			}
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d" role="img">'
			. '<rect width="%1$d" height="%1$d" fill="#fff"/><path d="%2$s" fill="#000"/></svg>',
			$total,
			$path
		);
	}

	// --- Encoding -------------------------------------------------------------

	/**
	 * Smallest version that holds this many bytes.
	 *
	 * @param int $length Byte count.
	 */
	private static function version_for( int $length ): ?int {
		foreach ( self::VERSIONS as $version => $spec ) {
			// 4 bits of mode, then the character count, then the data.
			$count_bits = $version >= 10 ? 16 : 8;
			$capacity   = self::data_codewords( $version ) * 8 - 4 - $count_bits;

			if ( $length * 8 <= $capacity ) {
				return $version;
			}
		}

		return null;
	}

	/**
	 * Total data codewords for a version.
	 *
	 * @param int $version Version.
	 */
	private static function data_codewords( int $version ): int {
		$total = 0;

		foreach ( self::VERSIONS[ $version ]['blocks'] as $group ) {
			$total += $group[0] * $group[1];
		}

		return $total;
	}

	/**
	 * Data and error correction codewords, interleaved as the spec requires.
	 *
	 * @param string $text    Data.
	 * @param int    $version Version.
	 * @return int[] Codewords.
	 */
	private static function codewords( string $text, int $version ): array {
		$count_bits = $version >= 10 ? 16 : 8;
		$bits       = '0100' . str_pad( decbin( strlen( $text ) ), $count_bits, '0', STR_PAD_LEFT );

		for ( $i = 0; $i < strlen( $text ); $i++ ) {
			$bits .= str_pad( decbin( ord( $text[ $i ] ) ), 8, '0', STR_PAD_LEFT );
		}

		$capacity = self::data_codewords( $version ) * 8;
		$bits    .= str_repeat( '0', min( 4, $capacity - strlen( $bits ) ) );
		$bits    .= str_repeat( '0', ( 8 - strlen( $bits ) % 8 ) % 8 );

		$data = array();

		foreach ( str_split( $bits, 8 ) as $byte ) {
			$data[] = bindec( $byte );
		}

		// Pad alternately with the two codewords the spec names.
		$pad = array( 0xEC, 0x11 );

		for ( $i = 0; count( $data ) < self::data_codewords( $version ); $i++ ) {
			$data[] = $pad[ $i % 2 ];
		}

		// Split into blocks, compute each block's EC, then interleave.
		$ec_length = self::VERSIONS[ $version ]['ec'];
		$blocks    = array();
		$ec_blocks = array();
		$offset    = 0;

		foreach ( self::VERSIONS[ $version ]['blocks'] as $group ) {
			for ( $b = 0; $b < $group[0]; $b++ ) {
				$block       = array_slice( $data, $offset, $group[1] );
				$offset     += $group[1];
				$blocks[]    = $block;
				$ec_blocks[] = self::error_correction( $block, $ec_length );
			}
		}

		$out     = array();
		$longest = max( array_map( 'count', $blocks ) );

		for ( $i = 0; $i < $longest; $i++ ) {
			foreach ( $blocks as $block ) {
				if ( isset( $block[ $i ] ) ) {
					$out[] = $block[ $i ];
				}
			}
		}

		for ( $i = 0; $i < $ec_length; $i++ ) {
			foreach ( $ec_blocks as $block ) {
				$out[] = $block[ $i ];
			}
		}

		return $out;
	}

	/** Build the GF(256) log tables once. */
	private static function init_gf(): void {
		if ( self::$exp ) {
			return;
		}

		$x = 1;

		for ( $i = 0; $i < 256; $i++ ) {
			self::$exp[ $i ] = $x;
			self::$log[ $x ] = $i;
			$x <<= 1;

			if ( $x & 0x100 ) {
				// The field's primitive polynomial.
				$x ^= 0x11D;
			}
		}
	}

	/**
	 * Reed-Solomon error correction codewords for one block.
	 *
	 * @param int[] $block  Data codewords.
	 * @param int   $length How many EC codewords to produce.
	 * @return int[]
	 */
	private static function error_correction( array $block, int $length ): array {
		self::init_gf();

		// The generator polynomial for this length.
		$generator = array( 1 );

		for ( $i = 0; $i < $length; $i++ ) {
			$next = array_fill( 0, count( $generator ) + 1, 0 );

			foreach ( $generator as $j => $coefficient ) {
				$next[ $j ]     ^= $coefficient;
				$next[ $j + 1 ] ^= self::gf_multiply( $coefficient, self::$exp[ $i ] );
			}

			$generator = $next;
		}

		$remainder = array_merge( $block, array_fill( 0, $length, 0 ) );

		for ( $i = 0; $i < count( $block ); $i++ ) {
			$factor = $remainder[ $i ];

			if ( 0 === $factor ) {
				continue;
			}

			foreach ( $generator as $j => $coefficient ) {
				$remainder[ $i + $j ] ^= self::gf_multiply( $coefficient, $factor );
			}
		}

		return array_slice( $remainder, count( $block ) );
	}

	/**
	 * Multiply in GF(256).
	 *
	 * @param int $a Left.
	 * @param int $b Right.
	 */
	private static function gf_multiply( int $a, int $b ): int {
		if ( 0 === $a || 0 === $b ) {
			return 0;
		}

		return self::$exp[ ( self::$log[ $a ] + self::$log[ $b ] ) % 255 ];
	}

	// --- Layout ---------------------------------------------------------------

	/**
	 * Finder patterns and their separators, in the three corners.
	 *
	 * @param array $grid     Modules, by reference.
	 * @param array $reserved Reserved map, by reference.
	 * @param int   $size     Grid size.
	 */
	private static function place_finders( array &$grid, array &$reserved, int $size ): void {
		foreach ( array( array( 0, 0 ), array( $size - 7, 0 ), array( 0, $size - 7 ) ) as $corner ) {
			list( $left, $top ) = $corner;

			// The separator is one module wider on every side than the finder.
			for ( $y = -1; $y <= 7; $y++ ) {
				for ( $x = -1; $x <= 7; $x++ ) {
					$gx = $left + $x;
					$gy = $top + $y;

					if ( $gx < 0 || $gy < 0 || $gx >= $size || $gy >= $size ) {
						continue;
					}

					$in_ring   = 0 === $x || 6 === $x || 0 === $y || 6 === $y;
					$in_centre = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
					$inside    = $x >= 0 && $x <= 6 && $y >= 0 && $y <= 6;

					$grid[ $gy ][ $gx ]     = $inside && ( $in_ring || $in_centre );
					$reserved[ $gy ][ $gx ] = true;
				}
			}
		}
	}

	/**
	 * Alignment patterns, skipping the ones that would sit on a finder.
	 *
	 * @param array $grid     Modules, by reference.
	 * @param array $reserved Reserved map, by reference.
	 * @param int   $version  Version.
	 * @param int   $size     Grid size.
	 */
	private static function place_alignment( array &$grid, array &$reserved, int $version, int $size ): void {
		$centres = self::ALIGNMENT[ $version ];

		foreach ( $centres as $cy ) {
			foreach ( $centres as $cx ) {
				$on_finder = ( 6 === $cx && 6 === $cy )
					|| ( 6 === $cx && $cy === $size - 7 )
					|| ( $cx === $size - 7 && 6 === $cy );

				if ( $on_finder ) {
					continue;
				}

				for ( $y = -2; $y <= 2; $y++ ) {
					for ( $x = -2; $x <= 2; $x++ ) {
						$grid[ $cy + $y ][ $cx + $x ]     = 2 === max( abs( $x ), abs( $y ) ) || ( 0 === $x && 0 === $y );
						$reserved[ $cy + $y ][ $cx + $x ] = true;
					}
				}
			}
		}
	}

	/**
	 * The two timing lines.
	 *
	 * @param array $grid     Modules, by reference.
	 * @param array $reserved Reserved map, by reference.
	 * @param int   $size     Grid size.
	 */
	private static function place_timing( array &$grid, array &$reserved, int $size ): void {
		for ( $i = 8; $i < $size - 8; $i++ ) {
			$on = 0 === $i % 2;

			if ( ! $reserved[6][ $i ] ) {
				$grid[6][ $i ]     = $on;
				$reserved[6][ $i ] = true;
			}

			if ( ! $reserved[ $i ][6] ) {
				$grid[ $i ][6]     = $on;
				$reserved[ $i ][6] = true;
			}
		}
	}

	/**
	 * Keep the format and version areas clear of data.
	 *
	 * @param array $reserved Reserved map, by reference.
	 * @param int   $size     Grid size.
	 * @param int   $version  Version.
	 */
	private static function reserve_format( array &$reserved, int $size, int $version ): void {
		for ( $i = 0; $i < 9; $i++ ) {
			$reserved[8][ $i ]     = true;
			$reserved[ $i ][8]     = true;
		}

		for ( $i = 0; $i < 8; $i++ ) {
			$reserved[8][ $size - 1 - $i ]     = true;
			$reserved[ $size - 1 - $i ][8]     = true;
		}

		if ( $version >= 7 ) {
			for ( $i = 0; $i < 6; $i++ ) {
				for ( $j = 0; $j < 3; $j++ ) {
					$reserved[ $i ][ $size - 11 + $j ] = true;
					$reserved[ $size - 11 + $j ][ $i ] = true;
				}
			}
		}
	}

	/**
	 * Walk the codewords into the grid, two columns at a time, upwards then down.
	 *
	 * @param array $grid      Modules, by reference.
	 * @param array $reserved  Reserved map.
	 * @param int[] $codewords Codewords.
	 * @param int   $size      Grid size.
	 */
	private static function place_data( array &$grid, array $reserved, array $codewords, int $size ): void {
		$bits = '';

		foreach ( $codewords as $codeword ) {
			$bits .= str_pad( decbin( $codeword ), 8, '0', STR_PAD_LEFT );
		}

		$index = 0;
		$up    = true;

		for ( $right = $size - 1; $right > 0; $right -= 2 ) {
			// Column 6 is the vertical timing line; the pairs step over it.
			if ( 6 === $right ) {
				--$right;
			}

			for ( $step = 0; $step < $size; $step++ ) {
				$y = $up ? $size - 1 - $step : $step;

				foreach ( array( $right, $right - 1 ) as $x ) {
					if ( $reserved[ $y ][ $x ] ) {
						continue;
					}

					$grid[ $y ][ $x ] = isset( $bits[ $index ] ) && '1' === $bits[ $index ];
					++$index;
				}
			}

			$up = ! $up;
		}
	}

	// --- Masking --------------------------------------------------------------

	/**
	 * Whether a mask flips this module.
	 *
	 * @param int $mask Mask pattern 0-7.
	 * @param int $x    Column.
	 * @param int $y    Row.
	 */
	private static function mask_at( int $mask, int $x, int $y ): bool {
		switch ( $mask ) {
			case 0:
				return 0 === ( $y + $x ) % 2;
			case 1:
				return 0 === $y % 2;
			case 2:
				return 0 === $x % 3;
			case 3:
				return 0 === ( $y + $x ) % 3;
			case 4:
				return 0 === ( intdiv( $y, 2 ) + intdiv( $x, 3 ) ) % 2;
			case 5:
				return 0 === ( $y * $x ) % 2 + ( $y * $x ) % 3;
			case 6:
				return 0 === ( ( $y * $x ) % 2 + ( $y * $x ) % 3 ) % 2;
			default:
				return 0 === ( ( $y + $x ) % 2 + ( $y * $x ) % 3 ) % 2;
		}
	}

	/**
	 * Apply a mask to every module that is not part of a fixed pattern.
	 *
	 * @param array $grid     Modules, by reference.
	 * @param array $reserved Reserved map.
	 * @param int   $size     Grid size.
	 * @param int   $mask     Mask pattern.
	 */
	private static function apply_mask( array &$grid, array $reserved, int $size, int $mask ): void {
		for ( $y = 0; $y < $size; $y++ ) {
			for ( $x = 0; $x < $size; $x++ ) {
				if ( ! $reserved[ $y ][ $x ] && self::mask_at( $mask, $x, $y ) ) {
					$grid[ $y ][ $x ] = ! $grid[ $y ][ $x ];
				}
			}
		}
	}

	/**
	 * The mask with the lowest penalty, as the spec defines it.
	 *
	 * @param array $grid     Modules.
	 * @param array $reserved Reserved map.
	 * @param int   $size     Grid size.
	 */
	private static function best_mask( array $grid, array $reserved, int $size ): int {
		$best    = 0;
		$lowest  = PHP_INT_MAX;

		for ( $mask = 0; $mask < 8; $mask++ ) {
			$candidate = $grid;

			self::apply_mask( $candidate, $reserved, $size, $mask );
			self::place_format( $candidate, $size, $mask );

			$penalty = self::penalty( $candidate, $size );

			if ( $penalty < $lowest ) {
				$lowest = $penalty;
				$best   = $mask;
			}
		}

		return $best;
	}

	/**
	 * The four penalty rules.
	 *
	 * @param array $grid Modules.
	 * @param int   $size Grid size.
	 */
	private static function penalty( array $grid, int $size ): int {
		$score = 0;

		// 1: runs of five or more of the same colour, in both directions.
		for ( $i = 0; $i < $size; $i++ ) {
			foreach ( array( 'row', 'column' ) as $direction ) {
				$run  = 1;
				$last = null;

				for ( $j = 0; $j < $size; $j++ ) {
					$value = 'row' === $direction ? $grid[ $i ][ $j ] : $grid[ $j ][ $i ];

					if ( $value === $last ) {
						++$run;
					} else {
						if ( $run >= 5 ) {
							$score += 3 + ( $run - 5 );
						}

						$run  = 1;
						$last = $value;
					}
				}

				if ( $run >= 5 ) {
					$score += 3 + ( $run - 5 );
				}
			}
		}

		// 2: every 2x2 block of one colour.
		for ( $y = 0; $y < $size - 1; $y++ ) {
			for ( $x = 0; $x < $size - 1; $x++ ) {
				$v = $grid[ $y ][ $x ];

				if ( $v === $grid[ $y ][ $x + 1 ] && $v === $grid[ $y + 1 ][ $x ] && $v === $grid[ $y + 1 ][ $x + 1 ] ) {
					$score += 3;
				}
			}
		}

		// 3: the finder-like 1:1:3:1:1 sequence with four light modules beside it.
		$patterns = array(
			array( true, false, true, true, true, false, true, false, false, false, false ),
			array( false, false, false, false, true, false, true, true, true, false, true ),
		);

		for ( $y = 0; $y < $size; $y++ ) {
			for ( $x = 0; $x <= $size - 11; $x++ ) {
				foreach ( $patterns as $pattern ) {
					$row = true;
					$col = true;

					for ( $k = 0; $k < 11; $k++ ) {
						$row = $row && $grid[ $y ][ $x + $k ] === $pattern[ $k ];
						$col = $col && $grid[ $x + $k ][ $y ] === $pattern[ $k ];
					}

					$score += ( $row ? 40 : 0 ) + ( $col ? 40 : 0 );
				}
			}
		}

		// 4: how far the proportion of dark modules is from half.
		$dark = 0;

		foreach ( $grid as $row ) {
			foreach ( $row as $value ) {
				$dark += $value ? 1 : 0;
			}
		}

		$percent = ( $dark * 100 ) / ( $size * $size );
		$score  += intdiv( (int) abs( $percent - 50 ), 5 ) * 10;

		return $score;
	}

	// --- Format and version information --------------------------------------

	/**
	 * The 15-bit format information, written twice.
	 *
	 * @param array $grid Modules, by reference.
	 * @param int   $size Grid size.
	 * @param int   $mask Mask pattern.
	 */
	private static function place_format( array &$grid, int $size, int $mask ): void {
		$value     = ( self::EC_LEVEL_BITS << 3 ) | $mask;
		$remainder = $value << 10;

		for ( $i = 4; $i >= 0; $i-- ) {
			if ( $remainder & ( 1 << ( $i + 10 ) ) ) {
				$remainder ^= 0x537 << $i;
			}
		}

		// The spec's mask, so an all-zero format is not all-zero on the grid.
		$bits = ( ( $value << 10 ) | $remainder ) ^ 0x5412;

		// The two copies run in opposite directions, and every index below is
		// [row][column]. Writing them the other way round is the mirror image
		// of a valid code: it still looks like a QR, and nothing reads it.
		for ( $i = 0; $i < 15; $i++ ) {
			$on = (bool) ( ( $bits >> $i ) & 1 );

			// First copy: down column 8, then left along row 8.
			if ( $i < 6 ) {
				$grid[ $i ][8] = $on;
			} elseif ( 6 === $i ) {
				$grid[7][8] = $on;
			} elseif ( 7 === $i ) {
				$grid[8][8] = $on;
			} elseif ( 8 === $i ) {
				$grid[8][7] = $on;
			} else {
				$grid[8][ 14 - $i ] = $on;
			}

			// Second copy: along row 8 by the top-right finder, then up
			// column 8 by the bottom-left one.
			if ( $i < 8 ) {
				$grid[8][ $size - 1 - $i ] = $on;
			} else {
				$grid[ $size - 15 + $i ][8] = $on;
			}
		}
	}

	/**
	 * The 18-bit version information, for version 7 and up.
	 *
	 * @param array $grid    Modules, by reference.
	 * @param int   $size    Grid size.
	 * @param int   $version Version.
	 */
	private static function place_version( array &$grid, int $size, int $version ): void {
		$remainder = $version << 12;

		for ( $i = 5; $i >= 0; $i-- ) {
			if ( $remainder & ( 1 << ( $i + 12 ) ) ) {
				$remainder ^= 0x1F25 << $i;
			}
		}

		$bits = ( $version << 12 ) | $remainder;

		for ( $i = 0; $i < 18; $i++ ) {
			$on = (bool) ( ( $bits >> $i ) & 1 );
			$x  = intdiv( $i, 3 );
			$y  = $size - 11 + ( $i % 3 );

			$grid[ $y ][ $x ] = $on;
			$grid[ $x ][ $y ] = $on;
		}
	}
}
