<?php
/**
 * The membership code behind a customer's card.
 *
 * One secret per customer, shown two ways: as a QR the staff scan with a phone
 * camera, and as a short code they can read out when the camera will not
 * focus. Both are the same twenty characters, so there is nothing to keep in
 * sync and nothing extra to leak.
 *
 * The alphabet is Crockford's base32 -- no I, L, O or U -- because a code that
 * gets read aloud across a counter has to survive being heard as well as seen.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Member;

use Moksa\Line\Support\Db;

defined( 'ABSPATH' ) || exit;

class MemberCard {

	/** Where the code lives. Underscored, so it stays out of custom-field UIs. */
	const META = '_moksa_line_member_code';

	/** Characters a code is built from. */
	const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

	/** Code length. Twenty base32 characters is a hundred bits. */
	const LENGTH = 20;

	/**
	 * This customer's code, minting one the first time it is asked for.
	 *
	 * @param int $user_id WordPress user id.
	 * @return string The code, or '' when there is no such user.
	 */
	public static function code_for( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		$code = (string) get_user_meta( $user_id, self::META, true );

		if ( self::is_well_formed( $code ) ) {
			return $code;
		}

		return self::issue( $user_id );
	}

	/**
	 * Give this customer a new code, retiring whatever they had.
	 *
	 * Anyone holding a photograph of the old card stops being able to use it,
	 * which is the point: the code is a bearer token, and a customer who has
	 * shared a screenshot needs a way out that does not involve support.
	 *
	 * @param int $user_id WordPress user id.
	 * @return string The new code.
	 */
	public static function issue( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		// A collision would hand one customer another's card, so check rather
		// than trust the odds. At a hundred bits this loop runs once.
		do {
			$code = self::random_code();
		} while ( 0 !== self::user_for_code( $code ) );

		update_user_meta( $user_id, self::META, $code );

		return $code;
	}

	/**
	 * Which customer a code belongs to.
	 *
	 * @param string $code A scanned or typed code.
	 * @return int User id, or 0 when nothing matches.
	 */
	public static function user_for_code( string $code ): int {
		$code = self::normalise( $code );

		if ( ! self::is_well_formed( $code ) ) {
			return 0;
		}

		// The code lives in user meta, and the lookup is by value. It runs
		// once, when a member of staff scans a card -- never in a page render
		// or a loop -- so an index would buy nothing that is felt.
		$users = Db::get_col(
			Db::prepare(
				'SELECT user_id FROM %i WHERE meta_key = %s AND meta_value = %s LIMIT 2',
				Db::core_table( 'usermeta' ),
				self::META,
				$code
			)
		);

		// Two users on one code should be impossible; if it ever happens,
		// refusing is better than picking one of them.
		if ( 1 !== count( $users ) ) {
			return 0;
		}

		return (int) $users[0];
	}

	/**
	 * Clean up a code that was typed rather than scanned.
	 *
	 * Spaces and dashes come from the grouping we print; I, L and O come from
	 * reading a 1 or a 0 as a letter, which is exactly what the alphabet was
	 * chosen to make recoverable.
	 *
	 * @param string $code Raw input.
	 */
	public static function normalise( string $code ): string {
		$code = strtoupper( trim( $code ) );
		$code = preg_replace( '/[^0-9A-Z]/', '', $code );

		return strtr( (string) $code, array( 'I' => '1', 'L' => '1', 'O' => '0', 'U' => 'V' ) );
	}

	/**
	 * Whether a string is shaped like a code.
	 *
	 * @param string $code Candidate.
	 */
	public static function is_well_formed( string $code ): bool {
		return (bool) preg_match( '/^[' . self::ALPHABET . ']{' . self::LENGTH . '}$/', $code );
	}

	/**
	 * The code in groups of five, for printing and for reading aloud.
	 *
	 * @param string $code A code.
	 */
	public static function grouped( string $code ): string {
		return trim( chunk_split( $code, 5, ' ' ) );
	}

	/**
	 * The URL a scan opens.
	 *
	 * @param string $code A code.
	 */
	public static function url( string $code ): string {
		return home_url( '/' . MemberModule::SLUG . '/' . rawurlencode( $code ) . '/' );
	}

	/**
	 * A fresh random code.
	 */
	private static function random_code(): string {
		$code  = '';
		$bytes = random_bytes( self::LENGTH );

		for ( $i = 0; $i < self::LENGTH; $i++ ) {
			// 32 characters divides 256 evenly, so the modulo is unbiased.
			$code .= self::ALPHABET[ ord( $bytes[ $i ] ) % 32 ];
		}

		return $code;
	}
}
