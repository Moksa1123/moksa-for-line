<?php
/**
 * Read the QR encoder's output back the way a scanner would.
 *
 *   wp eval-file tests/qr-check.php
 *
 * QrCode has no library behind it, so "it renders" proves nothing -- a mirrored
 * format field produces a grid that looks exactly like a QR code and that no
 * phone will read. The only useful test is a decoder that was written from the
 * spec rather than from the encoder: it finds the format bits, checks their BCH
 * code, unmasks, walks the data region, verifies the Reed-Solomon syndromes and
 * reads the string back out. Nothing here calls into QrCode except matrix().
 *
 * Verified once against segno (encoder) and OpenCV (decoder) as well; that pair
 * cannot live in a PHP test, which is why this decoder exists.
 *
 * Touches no database and writes no files.
 *
 * @package Mofoline
 */

use Mofoline\Support\QrCode;

$GLOBALS['qr_fail'] = 0;

function qr_check( $ok, $label, $detail = '' ) {
	if ( $ok ) {
		echo "  ok    {$label}\n";
		return;
	}

	++$GLOBALS['qr_fail'];
	echo "  FAIL  {$label}" . ( '' !== $detail ? " -- {$detail}" : '' ) . "\n";
}

// --- GF(256), built here rather than borrowed from the encoder --------------

/**
 * The log and antilog tables, built once.
 *
 * A static rather than a global: wp eval-file runs this file inside a function,
 * so a variable at the top level is not global and `global $x` in a helper
 * quietly reads null -- which made every Reed-Solomon syndrome come out zero
 * and the check pass without doing anything.
 *
 * @return array{exp:int[],log:int[]}
 */
function qr_gf() {
	static $tables = null;

	if ( null !== $tables ) {
		return $tables;
	}

	$exp = array();
	$log = array();
	$x   = 1;

	for ( $i = 0; $i < 255; $i++ ) {
		$exp[ $i ]  = $x;
		$log[ $x ]  = $i;
		$x        <<= 1;

		if ( $x & 0x100 ) {
			$x ^= 0x11D;
		}
	}

	$tables = array(
		'exp' => $exp,
		'log' => $log,
	);

	return $tables;
}

/**
 * Multiply in GF(256).
 *
 * @param int $a First factor.
 * @param int $b Second factor.
 */
function qr_mul( $a, $b ) {
	if ( 0 === $a || 0 === $b ) {
		return 0;
	}

	$gf = qr_gf();

	return $gf['exp'][ ( $gf['log'][ $a ] + $gf['log'][ $b ] ) % 255 ];
}

// --- The decoder ------------------------------------------------------------

/**
 * Which modules carry no data, for this size.
 *
 * Derived from the size alone, so a mistake in the encoder's own reserved map
 * cannot hide here.
 *
 * @param int $size Grid size in modules.
 * @return array<int,array<int,bool>> Reserved map.
 */
function qr_reserved_map( $size ) {
	$version  = (int) ( ( $size - 17 ) / 4 );
	$reserved = array_fill( 0, $size, array_fill( 0, $size, false ) );

	// Finders, with their separators.
	foreach ( array( array( 0, 0 ), array( $size - 7, 0 ), array( 0, $size - 7 ) ) as $corner ) {
		for ( $y = -1; $y <= 7; $y++ ) {
			for ( $x = -1; $x <= 7; $x++ ) {
				$gx = $corner[0] + $x;
				$gy = $corner[1] + $y;

				if ( $gx >= 0 && $gx < $size && $gy >= 0 && $gy < $size ) {
					$reserved[ $gy ][ $gx ] = true;
				}
			}
		}
	}

	// Timing.
	for ( $i = 0; $i < $size; $i++ ) {
		$reserved[6][ $i ] = true;
		$reserved[ $i ][6] = true;
	}

	// Format, both copies, plus the dark module.
	for ( $i = 0; $i < 9; $i++ ) {
		$reserved[8][ $i ] = true;
		$reserved[ $i ][8] = true;
	}

	for ( $i = 0; $i < 8; $i++ ) {
		$reserved[8][ $size - 1 - $i ] = true;
		$reserved[ $size - 1 - $i ][8] = true;
	}

	// Alignment patterns.
	$centres = qr_alignment_centres( $version );

	foreach ( $centres as $cy ) {
		foreach ( $centres as $cx ) {
			$corner = ( $cx < 8 && $cy < 8 ) || ( $cx > $size - 9 && $cy < 8 ) || ( $cx < 8 && $cy > $size - 9 );

			if ( $corner ) {
				continue;
			}

			for ( $y = -2; $y <= 2; $y++ ) {
				for ( $x = -2; $x <= 2; $x++ ) {
					$reserved[ $cy + $y ][ $cx + $x ] = true;
				}
			}
		}
	}

	// Version information, from version 7 up.
	if ( $version >= 7 ) {
		for ( $i = 0; $i < 6; $i++ ) {
			for ( $j = 0; $j < 3; $j++ ) {
				$reserved[ $i ][ $size - 11 + $j ] = true;
				$reserved[ $size - 11 + $j ][ $i ] = true;
			}
		}
	}

	return $reserved;
}

/**
 * Alignment pattern centres for a version, per table E.1.
 *
 * @param int $version Version 1-40.
 * @return int[] Centre coordinates.
 */
function qr_alignment_centres( $version ) {
	if ( 1 === $version ) {
		return array();
	}

	$size  = 17 + 4 * $version;
	$count = (int) floor( $version / 7 ) + 2;
	$step  = ( 2 === $count ) ? 0 : (int) ( ceil( ( $size - 13 ) / ( 2 * $count - 2 ) ) * 2 );
	$out   = array( 6 );

	for ( $i = $count - 1; $i > 0; $i-- ) {
		$out[] = $size - 7 - ( $i - 1 ) * $step;
	}

	return $out;
}

/**
 * Whether mask $mask flips the module at ($x, $y). Written from the table in
 * section 8.8.1, not copied from the encoder.
 *
 * @param int $mask Mask 0-7.
 * @param int $x    Column.
 * @param int $y    Row.
 */
function qr_mask_at( $mask, $x, $y ) {
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
			return 0 === ( (int) ( $y / 2 ) + (int) ( $x / 3 ) ) % 2;
		case 5:
			return 0 === ( $y * $x ) % 2 + ( $y * $x ) % 3;
		case 6:
			return 0 === ( ( $y * $x ) % 2 + ( $y * $x ) % 3 ) % 2;
		default:
			return 0 === ( ( $y + $x ) % 2 + ( $y * $x ) % 3 ) % 2;
	}
}

/**
 * Read the 15-bit format field, check its BCH code, and return the EC level
 * and mask it names.
 *
 * @param array $grid Modules.
 * @param int   $size Grid size.
 * @return array{ec:int,mask:int}|string The field, or why it could not be read.
 */
function qr_read_format( array $grid, $size ) {
	$bits = 0;

	// The first copy, in the same order the spec numbers the bits: down
	// column 8, then left along row 8.
	for ( $i = 0; $i < 15; $i++ ) {
		if ( $i < 6 ) {
			$on = $grid[ $i ][8];
		} elseif ( 6 === $i ) {
			$on = $grid[7][8];
		} elseif ( 7 === $i ) {
			$on = $grid[8][8];
		} elseif ( 8 === $i ) {
			$on = $grid[8][7];
		} else {
			$on = $grid[8][ 14 - $i ];
		}

		if ( $on ) {
			$bits |= 1 << $i;
		}
	}

	// The second copy has to agree, or a scanner would have to guess.
	$second = 0;

	for ( $i = 0; $i < 15; $i++ ) {
		$on = $i < 8 ? $grid[8][ $size - 1 - $i ] : $grid[ $size - 15 + $i ][8];

		if ( $on ) {
			$second |= 1 << $i;
		}
	}

	if ( $bits !== $second ) {
		return sprintf( 'the two copies disagree (%015b vs %015b)', $bits, $second );
	}

	$unmasked = $bits ^ 0x5412;

	// Divide by the BCH generator; a valid field leaves nothing.
	$remainder = $unmasked;

	for ( $i = 4; $i >= 0; $i-- ) {
		if ( $remainder & ( 1 << ( $i + 10 ) ) ) {
			$remainder ^= 0x537 << $i;
		}
	}

	if ( 0 !== $remainder ) {
		return sprintf( 'BCH check failed on %015b', $unmasked );
	}

	return array(
		'ec'   => ( $unmasked >> 13 ) & 0x3,
		'mask' => ( $unmasked >> 10 ) & 0x7,
	);
}

/**
 * Walk the data region and return the codewords, in placement order.
 *
 * @param array $grid     Modules, already unmasked.
 * @param array $reserved Reserved map.
 * @param int   $size     Grid size.
 * @return int[] Codewords.
 */
function qr_read_codewords( array $grid, array $reserved, $size ) {
	$bits = '';
	$up   = true;

	for ( $right = $size - 1; $right > 0; $right -= 2 ) {
		if ( 6 === $right ) {
			--$right;
		}

		for ( $step = 0; $step < $size; $step++ ) {
			$y = $up ? $size - 1 - $step : $step;

			foreach ( array( $right, $right - 1 ) as $x ) {
				if ( $reserved[ $y ][ $x ] ) {
					continue;
				}

				$bits .= $grid[ $y ][ $x ] ? '1' : '0';
			}
		}

		$up = ! $up;
	}

	$out = array();

	for ( $i = 0; $i + 8 <= strlen( $bits ); $i += 8 ) {
		$out[] = (int) bindec( substr( $bits, $i, 8 ) );
	}

	return $out;
}

/**
 * Whether a block's Reed-Solomon syndromes are all zero, which is what an
 * undamaged block looks like.
 *
 * @param int[] $block Data codewords followed by EC codewords.
 * @param int   $ec    Number of EC codewords.
 */
function qr_syndromes_clear( array $block, $ec ) {
	$gf = qr_gf();

	for ( $i = 0; $i < $ec; $i++ ) {
		$sum = 0;

		foreach ( $block as $position => $codeword ) {
			$power = ( count( $block ) - 1 - $position ) * $i;
			$sum  ^= qr_mul( $codeword, $gf['exp'][ $power % 255 ] );
		}

		if ( 0 !== $sum ) {
			return false;
		}
	}

	return true;
}

/**
 * Decode a grid back to its string, or say why it cannot be read.
 *
 * @param array $grid Modules as QrCode::matrix() returned them.
 * @return array{text:string,mask:int,version:int}|string
 */
function qr_decode( array $grid ) {
	$size = count( $grid );

	if ( 0 !== ( $size - 17 ) % 4 ) {
		return "size {$size} is not 17 + 4v";
	}

	$version = (int) ( ( $size - 17 ) / 4 );
	$format  = qr_read_format( $grid, $size );

	if ( is_string( $format ) ) {
		return $format;
	}

	// 0b00 is level M, the level this encoder writes.
	if ( 0 !== $format['ec'] ) {
		return sprintf( 'format names EC level %02b, expected 00 (M)', $format['ec'] );
	}

	$reserved = qr_reserved_map( $size );

	// The dark module is the one reserved cell whose value the spec fixes.
	if ( true !== $grid[ $size - 8 ][8] ) {
		return 'the dark module at (8, size-8) is light';
	}

	// Undo the mask the format field named.
	for ( $y = 0; $y < $size; $y++ ) {
		for ( $x = 0; $x < $size; $x++ ) {
			if ( ! $reserved[ $y ][ $x ] && qr_mask_at( $format['mask'], $x, $y ) ) {
				$grid[ $y ][ $x ] = ! $grid[ $y ][ $x ];
			}
		}
	}

	$codewords = qr_read_codewords( $grid, $reserved, $size );

	// From version 4 up, level M uses several blocks and the codewords are
	// interleaved, so they have to be dealt back out before anything can be
	// checked. This table is table 9 of the spec, typed out here rather than
	// read from the encoder.
	$structure = array(
		1  => array( 10, array( array( 1, 16 ) ) ),
		2  => array( 16, array( array( 1, 28 ) ) ),
		3  => array( 26, array( array( 1, 44 ) ) ),
		4  => array( 18, array( array( 2, 32 ) ) ),
		5  => array( 24, array( array( 2, 43 ) ) ),
		6  => array( 16, array( array( 4, 27 ) ) ),
		7  => array( 18, array( array( 4, 31 ) ) ),
		8  => array( 22, array( array( 2, 38 ), array( 2, 39 ) ) ),
		9  => array( 22, array( array( 3, 36 ), array( 2, 37 ) ) ),
		10 => array( 26, array( array( 4, 43 ), array( 1, 44 ) ) ),
	);

	if ( ! isset( $structure[ $version ] ) ) {
		return "version {$version} is outside the range this test knows";
	}

	list( $ec_length, $groups ) = $structure[ $version ];

	$lengths = array();

	foreach ( $groups as $group ) {
		for ( $b = 0; $b < $group[0]; $b++ ) {
			$lengths[] = $group[1];
		}
	}

	$expected = array_sum( $lengths ) + $ec_length * count( $lengths );

	if ( count( $codewords ) !== $expected ) {
		return sprintf( 'read %d codewords, version %d holds %d', count( $codewords ), $version, $expected );
	}

	// Deal the data codewords back into blocks, then the EC codewords.
	$blocks = array_fill( 0, count( $lengths ), array() );
	$index  = 0;

	for ( $i = 0; $i < max( $lengths ); $i++ ) {
		foreach ( $lengths as $block => $length ) {
			if ( $i < $length ) {
				$blocks[ $block ][] = $codewords[ $index++ ];
			}
		}
	}

	$ec_parts = array_fill( 0, count( $lengths ), array() );

	for ( $i = 0; $i < $ec_length; $i++ ) {
		foreach ( array_keys( $lengths ) as $block ) {
			$ec_parts[ $block ][] = $codewords[ $index++ ];
		}
	}

	foreach ( $blocks as $block => $data ) {
		if ( ! qr_syndromes_clear( array_merge( $data, $ec_parts[ $block ] ), $ec_length ) ) {
			return sprintf( 'Reed-Solomon syndromes are not zero in block %d', $block + 1 );
		}
	}

	// Byte mode: 4 bits of mode, 8 or 16 bits of length, then the bytes.
	$bits = '';

	foreach ( $blocks as $data ) {
		foreach ( $data as $codeword ) {
			$bits .= str_pad( decbin( $codeword ), 8, '0', STR_PAD_LEFT );
		}
	}

	$mode = bindec( substr( $bits, 0, 4 ) );

	if ( 4 !== $mode ) {
		return sprintf( 'mode is %04b, expected 0100 (byte)', $mode );
	}

	$count_bits = $version < 10 ? 8 : 16;
	$length     = (int) bindec( substr( $bits, 4, $count_bits ) );
	$text       = '';

	for ( $i = 0; $i < $length; $i++ ) {
		$text .= chr( bindec( substr( $bits, 4 + $count_bits + $i * 8, 8 ) ) );
	}

	return array(
		'text'    => $text,
		'mask'    => $format['mask'],
		'version' => $version,
	);
}

// --- The cases --------------------------------------------------------------

echo "QR encoder\n";

$cases = array(
	'HELLO',
	'https://example.com/x',
	home_url( '/mofoline-member/?t=' . str_repeat( 'a', 32 ) ),
	'https://moksaweb.com/?q=' . rawurlencode( '會員卡' ),
	str_repeat( 'A', 60 ),
	'~!@#$%^&*()_+-={}[]|:;"<>,.?/' . "\x00\x01\xff",
);

foreach ( $cases as $text ) {
	$label = strlen( $text ) > 40 ? substr( $text, 0, 37 ) . '...' : $text;
	echo '  ' . str_replace( "\n", ' ', $label ) . ' (' . strlen( $text ) . " bytes)\n";

	$grid = QrCode::matrix( $text );

	if ( null === $grid ) {
		qr_check( false, 'fits in a supported version' );
		continue;
	}

	$read = qr_decode( $grid );

	if ( is_string( $read ) ) {
		qr_check( false, 'decodes back to the original', $read );
		continue;
	}

	qr_check( $read['text'] === $text, 'decodes back to the original', 'got ' . substr( $read['text'], 0, 40 ) );
	qr_check( $read['mask'] >= 0 && $read['mask'] <= 7, 'names a mask in range', 'mask ' . $read['mask'] );
}

// Every mask has to survive the round trip, not just the one the penalty rules
// happen to pick.
echo "  every mask, pinned\n";

for ( $mask = 0; $mask < 8; $mask++ ) {
	$grid = QrCode::matrix( 'https://example.com/pinned', $mask );
	$read = is_array( $grid ) ? qr_decode( $grid ) : 'no matrix';

	qr_check(
		is_array( $read ) && 'https://example.com/pinned' === $read['text'] && $mask === $read['mask'],
		"mask {$mask} round trips",
		is_string( $read ) ? $read : 'read mask ' . $read['mask']
	);
}

// A check that always says yes is worse than no check. Damage the grid in the
// two ways that matter and confirm the decoder objects.
echo "  the decoder notices damage
";

$grid  = QrCode::matrix( 'https://example.com/damage' );
$size  = count( $grid );
$clean = qr_decode( $grid );

qr_check( is_array( $clean ) && 'https://example.com/damage' === $clean['text'], 'reads the undamaged grid' );

// One flipped data module: too little to change the text, enough to leave a
// non-zero syndrome.
$bent = $grid;
$bent[ $size - 1 ][ $size - 1 ] = ! $bent[ $size - 1 ][ $size - 1 ];
$read = qr_decode( $bent );

qr_check(
	is_string( $read ) && false !== strpos( $read, 'syndromes' ),
	'one flipped data module fails the Reed-Solomon check',
	is_string( $read ) ? $read : 'decoded anyway'
);

// One flipped format module: the two copies stop agreeing.
$bent    = $grid;
$bent[0][8] = ! $bent[0][8];
$read    = qr_decode( $bent );

qr_check(
	is_string( $read ),
	'one flipped format module is rejected',
	is_string( $read ) ? $read : 'decoded anyway'
);

// The dark module is not decoration.
$bent = $grid;
$bent[ $size - 8 ][8] = false;
$read = qr_decode( $bent );

qr_check( is_string( $read ), 'a light dark module is rejected', is_string( $read ) ? $read : 'decoded anyway' );

// Fixed patterns, checked directly: a scanner locates the code by these before
// it reads a single data module.
echo "  fixed patterns\n";

$grid = QrCode::matrix( 'https://example.com/x' );
$size = count( $grid );
$ok   = true;

foreach ( array( array( 0, 0 ), array( $size - 7, 0 ), array( 0, $size - 7 ) ) as $corner ) {
	for ( $y = 0; $y < 7; $y++ ) {
		for ( $x = 0; $x < 7; $x++ ) {
			$ring     = 0 === $y || 6 === $y || 0 === $x || 6 === $x;
			$centre   = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
			$expected = $ring || $centre;

			if ( $grid[ $corner[1] + $y ][ $corner[0] + $x ] !== $expected ) {
				$ok = false;
			}
		}
	}
}

qr_check( $ok, 'all three finder patterns are exact' );

$ok = true;

for ( $i = 8; $i < $size - 8; $i++ ) {
	if ( $grid[6][ $i ] !== ( 0 === $i % 2 ) || $grid[ $i ][6] !== ( 0 === $i % 2 ) ) {
		$ok = false;
	}
}

qr_check( $ok, 'both timing lines alternate' );

// Separators: the light ring that keeps a finder from touching data.
$ok = true;

for ( $i = 0; $i < 8; $i++ ) {
	if ( $grid[7][ $i ] || $grid[ $i ][7] || $grid[7][ $size - 1 - $i ] || $grid[ $size - 8 ][ $i ] ) {
		$ok = false;
	}
}

qr_check( $ok, 'separators are light' );

// Oversized input has to be refused rather than silently truncated.
echo "  limits\n";
qr_check( null === QrCode::matrix( str_repeat( 'x', 5000 ) ), 'refuses data that does not fit' );
qr_check( '' === QrCode::svg( str_repeat( 'x', 5000 ) ), 'svg() returns empty for the same' );

$svg = QrCode::svg( 'https://example.com/x' );
qr_check( 0 === strpos( $svg, '<svg' ) && false !== strpos( $svg, '</svg>' ), 'svg() is a complete element' );
qr_check( false === strpos( $svg, '<script' ), 'svg() carries no script' );

echo "\n" . ( 0 === $GLOBALS['qr_fail'] ? "QR ENCODER OK\n" : $GLOBALS['qr_fail'] . " checks failed\n" );
