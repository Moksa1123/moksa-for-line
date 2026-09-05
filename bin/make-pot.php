#!/usr/bin/env php
<?php
/**
 * Generate languages/moksa-line.pot from the source.
 *
 * WP-CLI's i18n command is the usual tool for this, but it is a heavy
 * dependency for a job this size and it is not installed everywhere. This
 * walks the PHP with the tokenizer -- not a regular expression -- so a string
 * containing a bracket or a nested call cannot throw the extraction off.
 *
 * Usage: php bin/make-pot.php
 *
 * @package Moksa\Line
 */

declare( strict_types=1 );

const DOMAIN = 'moksa-line';

/**
 * Gettext functions, mapped to the argument positions that hold translatable
 * text and the position of the text domain.
 */
const FUNCTIONS = array(
	'__'              => array( 'text' => array( 0 ), 'domain' => 1 ),
	'_e'              => array( 'text' => array( 0 ), 'domain' => 1 ),
	'esc_html__'      => array( 'text' => array( 0 ), 'domain' => 1 ),
	'esc_html_e'      => array( 'text' => array( 0 ), 'domain' => 1 ),
	'esc_attr__'      => array( 'text' => array( 0 ), 'domain' => 1 ),
	'esc_attr_e'      => array( 'text' => array( 0 ), 'domain' => 1 ),
	'_x'              => array( 'text' => array( 0 ), 'context' => 1, 'domain' => 2 ),
	'esc_html_x'      => array( 'text' => array( 0 ), 'context' => 1, 'domain' => 2 ),
	'esc_attr_x'      => array( 'text' => array( 0 ), 'context' => 1, 'domain' => 2 ),
	'_n'              => array( 'text' => array( 0, 1 ), 'domain' => 3 ),
	'_nx'             => array( 'text' => array( 0, 1 ), 'context' => 3, 'domain' => 4 ),
);

$root = dirname( __DIR__ );

$entries = array();

foreach ( source_files( $root ) as $file ) {
	extract_from( $file, $root, $entries );
}

ksort( $entries );

write_pot( $root . '/languages/' . DOMAIN . '.pot', $entries );

printf( "Wrote languages/%s.pot with %d strings from %d files.\n", DOMAIN, count( $entries ), count( source_files( $root ) ) );

/**
 * Every PHP file that may contain translatable strings.
 *
 * @param string $root Plugin root.
 * @return string[]
 */
function source_files( string $root ): array {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$files    = array();
	$skip     = array( '/dist/', '/.git/', '/node_modules/', '/vendor/', '/tests/', '/bin/' );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $iterator as $item ) {
		$path = str_replace( '\\', '/', $item->getPathname() );

		if ( 'php' !== strtolower( $item->getExtension() ) ) {
			continue;
		}

		foreach ( $skip as $fragment ) {
			if ( false !== strpos( $path, $fragment ) ) {
				continue 2;
			}
		}

		$files[] = $path;
	}

	sort( $files );

	$cache = $files;

	return $files;
}

/**
 * Pull translatable strings out of one file.
 *
 * @param string $file    Absolute path.
 * @param string $root    Plugin root, for relative references.
 * @param array  $entries Accumulator, keyed by context and msgid.
 */
function extract_from( string $file, string $root, array &$entries ): void {
	$code   = (string) file_get_contents( $file );
	$tokens = token_get_all( $code );
	$count  = count( $tokens );

	$relative = ltrim( str_replace( array( str_replace( '\\', '/', $root ), '\\' ), array( '', '/' ), str_replace( '\\', '/', $file ) ), '/' );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
			continue;
		}

		$name = $token[1];

		if ( ! isset( FUNCTIONS[ $name ] ) ) {
			continue;
		}

		// Skip method calls and definitions: only the bare function counts.
		$previous = previous_meaningful( $tokens, $i );

		if ( is_array( $previous ) && in_array( $previous[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
			continue;
		}

		$arguments = read_arguments( $tokens, $i, $count );

		if ( null === $arguments ) {
			continue;
		}

		$spec   = FUNCTIONS[ $name ];
		$domain = $arguments[ $spec['domain'] ] ?? null;

		// Only our own domain, and only when every part is a literal string.
		if ( DOMAIN !== $domain ) {
			continue;
		}

		$texts = array();

		foreach ( $spec['text'] as $position ) {
			if ( ! isset( $arguments[ $position ] ) || null === $arguments[ $position ] ) {
				continue 2;
			}

			$texts[] = $arguments[ $position ];
		}

		$context = isset( $spec['context'] ) ? ( $arguments[ $spec['context'] ] ?? null ) : null;
		$key     = ( null !== $context ? $context . "\4" : '' ) . implode( "\0", $texts );

		if ( ! isset( $entries[ $key ] ) ) {
			$entries[ $key ] = array(
				'msgid'        => $texts[0],
				'msgid_plural' => $texts[1] ?? null,
				'context'      => $context,
				'references'   => array(),
				'comments'     => array(),
			);
		}

		$entries[ $key ]['references'][] = $relative . ':' . $token[2];

		$comment = preceding_translator_comment( $tokens, $i );

		if ( null !== $comment && ! in_array( $comment, $entries[ $key ]['comments'], true ) ) {
			$entries[ $key ]['comments'][] = $comment;
		}
	}
}

/**
 * The previous token that is not whitespace or a comment.
 *
 * @param array $tokens Token stream.
 * @param int   $index  Current position.
 * @return array|string|null
 */
function previous_meaningful( array $tokens, int $index ) {
	for ( $i = $index - 1; $i >= 0; $i-- ) {
		if ( is_array( $tokens[ $i ] ) && in_array( $tokens[ $i ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}

		return $tokens[ $i ];
	}

	return null;
}

/**
 * A `translators:` comment immediately preceding the call, if any.
 *
 * @param array $tokens Token stream.
 * @param int   $index  Position of the function name.
 */
function preceding_translator_comment( array $tokens, int $index ): ?string {
	for ( $i = $index - 1; $i >= 0 && $i > $index - 12; $i-- ) {
		if ( ! is_array( $tokens[ $i ] ) ) {
			continue;
		}

		if ( T_WHITESPACE === $tokens[ $i ][0] ) {
			continue;
		}

		if ( in_array( $tokens[ $i ][0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			$text = trim( $tokens[ $i ][1] );
			$text = trim( preg_replace( '#^(/\*+|//|\#)|(\*+/)$#', '', $text ) ?? '' );
			$text = trim( preg_replace( '#^\s*\*\s?#m', '', $text ) ?? '' );

			return 0 === stripos( $text, 'translators:' ) ? $text : null;
		}

		return null;
	}

	return null;
}

/**
 * Read the literal string arguments of a call, or null when it is not a call.
 *
 * Any argument that is not a plain literal comes back as null, which is how
 * concatenations and variables are excluded from the catalogue.
 *
 * @param array $tokens Token stream.
 * @param int   $index  Position of the function name.
 * @param int   $count  Token count.
 * @return array<int,string|null>|null
 */
function read_arguments( array $tokens, int $index, int $count ): ?array {
	$i = $index + 1;

	while ( $i < $count && is_array( $tokens[ $i ] ) && T_WHITESPACE === $tokens[ $i ][0] ) {
		$i++;
	}

	if ( $i >= $count || '(' !== $tokens[ $i ] ) {
		return null;
	}

	$depth     = 0;
	$arguments = array();
	$current   = array();

	for ( ; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( '(' === $token ) {
			$depth++;

			if ( 1 === $depth ) {
				continue;
			}
		}

		if ( ')' === $token ) {
			$depth--;

			if ( 0 === $depth ) {
				$arguments[] = literal( $current );
				break;
			}
		}

		if ( 1 === $depth && ',' === $token ) {
			$arguments[] = literal( $current );
			$current     = array();
			continue;
		}

		$current[] = $token;
	}

	return $arguments;
}

/**
 * Collapse a run of tokens to its string value, or null when it is not a
 * single literal.
 *
 * @param array $tokens Tokens making up one argument.
 */
function literal( array $tokens ): ?string {
	$meaningful = array();

	foreach ( $tokens as $token ) {
		if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}

		$meaningful[] = $token;
	}

	if ( 1 !== count( $meaningful ) ) {
		return null;
	}

	$token = $meaningful[0];

	if ( ! is_array( $token ) || T_CONSTANT_ENCAPSED_STRING !== $token[0] ) {
		return null;
	}

	$raw   = $token[1];
	$quote = $raw[0];
	$body  = substr( $raw, 1, -1 );

	if ( "'" === $quote ) {
		return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
	}

	return stripcslashes( $body );
}

/**
 * Write the catalogue.
 *
 * @param string $path    Destination.
 * @param array  $entries Extracted entries.
 */
function write_pot( string $path, array $entries ): void {
	$now = gmdate( 'Y-m-d H:iO' );

	$out = <<<HEADER
# Copyright (C) Moksa
# This file is distributed under the GPL v2 or later.
msgid ""
msgstr ""
"Project-Id-Version: Moksa LINE Suite 2.0.0\\n"
"Report-Msgid-Bugs-To: https://moksaweb.com/\\n"
"POT-Creation-Date: {$now}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Domain: moksa-line\\n"

HEADER;

	foreach ( $entries as $entry ) {
		$out .= "\n";

		foreach ( $entry['comments'] as $comment ) {
			$out .= '#. ' . str_replace( "\n", ' ', $comment ) . "\n";
		}

		foreach ( array_unique( $entry['references'] ) as $reference ) {
			$out .= '#: ' . $reference . "\n";
		}

		if ( null !== $entry['context'] ) {
			$out .= 'msgctxt ' . escape_po( $entry['context'] ) . "\n";
		}

		$out .= 'msgid ' . escape_po( $entry['msgid'] ) . "\n";

		if ( null !== $entry['msgid_plural'] ) {
			$out .= 'msgid_plural ' . escape_po( $entry['msgid_plural'] ) . "\n";
			$out .= "msgstr[0] \"\"\n";
			$out .= "msgstr[1] \"\"\n";
			continue;
		}

		$out .= "msgstr \"\"\n";
	}

	file_put_contents( $path, $out );
}

/**
 * Quote a string for a PO file.
 *
 * @param string $value Raw text.
 */
function escape_po( string $value ): string {
	$value = str_replace(
		array( '\\', '"', "\t", "\r" ),
		array( '\\\\', '\"', '\t', '' ),
		$value
	);

	if ( false === strpos( $value, "\n" ) ) {
		return '"' . $value . '"';
	}

	// Multi-line strings are written as an empty first line followed by one
	// quoted line per newline, which is what the PO format expects.
	$lines = explode( "\n", $value );
	$out   = "\"\"\n";
	$last  = count( $lines ) - 1;

	foreach ( $lines as $index => $line ) {
		$out .= '"' . $line . ( $index === $last ? '' : '\n' ) . "\"\n";
	}

	return rtrim( $out, "\n" );
}
