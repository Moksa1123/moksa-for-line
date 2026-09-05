<?php
/**
 * At-rest encryption for channel secrets and access tokens.
 *
 * Keys are derived from WordPress salts, so a database dump alone is not
 * enough to recover credentials -- wp-config.php is required as well.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Support;

defined( 'ABSPATH' ) || exit;

class Crypto {

	const CIPHER = 'aes-256-gcm';
	const PREFIX = 'mlx1:';

	/**
	 * Derive the encryption key from WordPress salts.
	 *
	 * Falls back to AUTH_KEY when the dedicated constant is absent so the
	 * plugin still works on hosts with a minimal wp-config.php.
	 */
	private static function key(): string {
		$material = '';

		if ( defined( 'MOKSA_LINE_ENCRYPTION_KEY' ) && MOKSA_LINE_ENCRYPTION_KEY ) {
			$material = (string) MOKSA_LINE_ENCRYPTION_KEY;
		} elseif ( defined( 'LOGGED_IN_KEY' ) && defined( 'LOGGED_IN_SALT' ) ) {
			$material = LOGGED_IN_KEY . LOGGED_IN_SALT;
		} elseif ( defined( 'AUTH_KEY' ) ) {
			$material = AUTH_KEY;
		}

		if ( '' === $material ) {
			// Last resort: still deterministic per-site, but weak. Surfaced in the health check.
			$material = get_option( 'siteurl' ) . ABSPATH;
		}

		return hash( 'sha256', 'moksa-line|' . $material, true );
	}

	/**
	 * Whether the runtime can encrypt at all.
	 */
	public static function available(): bool {
		return function_exists( 'openssl_encrypt' )
			&& in_array( self::CIPHER, (array) openssl_get_cipher_methods(), true );
	}

	/**
	 * Encrypt a plaintext value. Returns the plaintext untouched when OpenSSL
	 * is unavailable, so a misconfigured host degrades instead of losing data.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext || ! self::available() ) {
			return $plaintext;
		}

		$iv  = random_bytes( 12 );
		$tag = '';

		$ciphertext = openssl_encrypt(
			$plaintext,
			self::CIPHER,
			self::key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( false === $ciphertext ) {
			return $plaintext;
		}

		return self::PREFIX . base64_encode( $iv . $tag . $ciphertext );
	}

	/**
	 * Decrypt a value produced by encrypt(). Values without our prefix are
	 * returned as-is, which is what makes the 1.x -> 2.0 migration lazy-safe.
	 */
	public static function decrypt( string $value ): string {
		if ( '' === $value || 0 !== strpos( $value, self::PREFIX ) ) {
			return $value;
		}

		if ( ! self::available() ) {
			return '';
		}

		$raw = base64_decode( substr( $value, strlen( self::PREFIX ) ), true );

		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}

		$iv         = substr( $raw, 0, 12 );
		$tag        = substr( $raw, 12, 16 );
		$ciphertext = substr( $raw, 28 );

		$plaintext = openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			self::key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		return false === $plaintext ? '' : $plaintext;
	}

	/**
	 * Whether a stored value is already encrypted.
	 */
	public static function is_encrypted( string $value ): bool {
		return 0 === strpos( $value, self::PREFIX );
	}
}
