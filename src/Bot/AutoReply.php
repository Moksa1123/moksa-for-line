<?php
/**
 * Keyword rules.
 *
 * 1.x loaded every rule on every message and returned the first row the
 * database happened to hand back, so which rule won depended on insertion
 * order. Rules now carry an explicit priority, exact matches beat partial
 * ones regardless of priority, and the matcher is tested against the whole
 * ruleset rather than short-circuiting on the first row.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Bot;

use Moksa\Line\Data\Flex;
use Moksa\Line\Data\QuickReplies;
use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Migrator;

defined( 'ABSPATH' ) || exit;

class AutoReply {

	/**
	 * Match strength, used to break ties between rules.
	 *
	 * @var array<string,int>
	 */
	private static $strength = array(
		'exact'  => 400,
		'prefix' => 300,
		'regex'  => 200,
		'partial' => 100,
		'any'    => 10,
	);

	public static function table(): string {
		return Migrator::table( 'auto_replies' );
	}

	/**
	 * Every active rule, best candidates first.
	 *
	 * @return array
	 */
	public static function active_rules(): array {
		$cached = wp_cache_get( 'moksa_line_active_rules', 'moksa_line' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$rules = (array) $wpdb->get_results( "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY priority ASC, id ASC" );

		wp_cache_set( 'moksa_line_active_rules', $rules, 'moksa_line', 300 );

		return $rules;
	}

	/**
	 * Drop the rule cache after an edit.
	 */
	public static function flush_cache(): void {
		wp_cache_delete( 'moksa_line_active_rules', 'moksa_line' );
	}

	/**
	 * Find the best rule for a message.
	 *
	 * @param string $text Inbound message text.
	 * @return object|null
	 */
	public static function match( string $text ) {
		$text = trim( $text );

		if ( '' === $text ) {
			return null;
		}

		$best       = null;
		$best_score = -1;

		foreach ( self::active_rules() as $rule ) {
			$score = self::score( $rule, $text );

			if ( $score > $best_score ) {
				$best       = $rule;
				$best_score = $score;
			}
		}

		return $best_score > 0 ? $best : null;
	}

	/**
	 * How well a rule matches, or 0 when it does not.
	 *
	 * @param object $rule Rule row.
	 * @param string $text Inbound text.
	 */
	private static function score( $rule, string $text ): int {
		$keyword = (string) $rule->keyword;
		$type    = (string) $rule->match_type;

		// Priority is ascending (1 runs before 10), so invert it into the score.
		$base = isset( self::$strength[ $type ] ) ? self::$strength[ $type ] : 0;
		$tie  = max( 0, 100 - (int) $rule->priority );

		switch ( $type ) {
			case 'any':
				// A catch-all rule: matches anything, ranks below everything.
				return $base + $tie;

			case 'exact':
				return 0 === strcasecmp( $text, $keyword ) ? $base + $tie : 0;

			case 'prefix':
				return '' !== $keyword && 0 === stripos( $text, $keyword ) ? $base + $tie : 0;

			case 'regex':
				if ( '' === $keyword ) {
					return 0;
				}

				// Administrator-supplied patterns are delimited here rather
				// than trusted verbatim, so a stray delimiter cannot smuggle
				// in the /e-style modifiers.
				$pattern = '/' . str_replace( '/', '\\/', $keyword ) . '/iu';
				$result  = @preg_match( $pattern, $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- invalid patterns are reported below.

				if ( false === $result ) {
					Logger::warning(
						'A keyword rule has an invalid regular expression',
						array( 'rule_id' => (int) $rule->id, 'pattern' => $keyword ),
						'bot'
					);

					return 0;
				}

				return $result ? $base + $tie : 0;

			case 'partial':
			default:
				return '' !== $keyword && false !== stripos( $text, $keyword ) ? $base + $tie : 0;
		}
	}

	/**
	 * Turn a rule into LINE message objects.
	 *
	 * @param object $rule         Rule row.
	 * @param string $line_user_id LINE user id, for placeholder expansion.
	 * @return array|null Message objects, or null when the rule cannot be built.
	 */
	public static function build_messages( $rule, string $line_user_id = '' ) {
		$type = (string) $rule->reply_type;
		$data = (string) $rule->reply_data;

		switch ( $type ) {
			case 'text':
				$text = self::expand( $data, $line_user_id );

				return '' === trim( $text ) ? null : array( MessagingClient::text( $text ) );

			case 'flex':
				return self::build_flex( $data, $line_user_id );

			case 'quick_reply':
				return self::build_quick_reply( $data, $line_user_id );

			case 'sticker':
				$parts = array_map( 'trim', explode( ',', $data ) );

				if ( count( $parts ) < 2 || '' === $parts[0] || '' === $parts[1] ) {
					return null;
				}

				return array( MessagingClient::sticker( $parts[0], $parts[1] ) );

			case 'image':
				$url = esc_url_raw( $data );

				if ( '' === $url ) {
					return null;
				}

				return array(
					array(
						'type'               => 'image',
						'originalContentUrl' => $url,
						'previewImageUrl'    => $url,
					),
				);

			case 'raw':
				// An administrator pasted a full message array.
				$decoded = json_decode( $data, true );

				if ( ! is_array( $decoded ) ) {
					return null;
				}

				return isset( $decoded['type'] ) ? array( $decoded ) : $decoded;

			case 'flow':
				// Handled by the Flow module, which starts a scenario instead
				// of sending a canned reply.
				return null;
		}

		return null;
	}

	/**
	 * Build a Flex reply from either a stored template id or inline JSON.
	 *
	 * @param string $data         Template id or JSON.
	 * @param string $line_user_id LINE user id.
	 * @return array|null
	 */
	private static function build_flex( string $data, string $line_user_id ) {
		$data = trim( $data );

		if ( '' === $data ) {
			return null;
		}

		if ( ctype_digit( $data ) ) {
			$template = Flex::find( (int) $data );

			if ( ! $template ) {
				Logger::warning( 'A keyword rule points at a Flex template that no longer exists', array( 'template_id' => (int) $data ), 'bot' );

				return null;
			}

			$contents = json_decode( (string) $template->contents, true );

			if ( ! is_array( $contents ) ) {
				return null;
			}

			return array(
				MessagingClient::flex(
					self::expand( (string) $template->alt_text, $line_user_id ),
					$contents
				),
			);
		}

		$decoded = json_decode( self::expand( $data, $line_user_id ), true );

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		// Accept either a bare container or a complete flex message object.
		if ( isset( $decoded['type'] ) && 'flex' === $decoded['type'] ) {
			return array( $decoded );
		}

		return array( MessagingClient::flex( get_bloginfo( 'name' ), $decoded ) );
	}

	/**
	 * Build a text message carrying a quick reply set.
	 *
	 * @param string $data         Set id, optionally "id|prompt text".
	 * @param string $line_user_id LINE user id.
	 * @return array|null
	 */
	private static function build_quick_reply( string $data, string $line_user_id ) {
		$prompt = '';
		$id     = $data;

		if ( false !== strpos( $data, '|' ) ) {
			list( $id, $prompt ) = array_map( 'trim', explode( '|', $data, 2 ) );
		}

		$set = QuickReplies::find( (int) $id );

		if ( ! $set ) {
			return null;
		}

		$items = json_decode( (string) $set->items, true );

		if ( ! is_array( $items ) || empty( $items ) ) {
			return null;
		}

		if ( '' === $prompt ) {
			$prompt = __( 'Please choose an option:', 'moksa-line' );
		}

		return array( MessagingClient::text( self::expand( $prompt, $line_user_id ), $items ) );
	}

	/**
	 * Expand placeholders in administrator-authored text.
	 *
	 * @param string $text         Raw text.
	 * @param string $line_user_id LINE user id.
	 */
	public static function expand( string $text, string $line_user_id ): string {
		if ( false === strpos( $text, '{' ) ) {
			return $text;
		}

		$display_name = '';

		if ( '' !== $line_user_id ) {
			$record = \Moksa\Line\Data\Users::by_line_id( $line_user_id );

			if ( $record ) {
				$display_name = (string) $record->display_name;
			}
		}

		return strtr(
			$text,
			array(
				'{display_name}' => $display_name,
				'{site_name}'    => (string) get_bloginfo( 'name' ),
				'{site_url}'     => home_url(),
			)
		);
	}

	/**
	 * Record that a rule fired, for the admin's "which rules earn their keep"
	 * column.
	 *
	 * @param int $rule_id Rule id.
	 */
	public static function record_hit( int $rule_id ): void {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hit_count = hit_count + 1 WHERE id = %d", $rule_id ) );
	}

	/**
	 * Insert or update a rule.
	 *
	 * @param array $fields Rule fields; id triggers an update.
	 * @return int Rule id, or 0 on failure.
	 */
	public static function save( array $fields ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$id  = isset( $fields['id'] ) ? (int) $fields['id'] : 0;

		$data = array(
			'name'       => sanitize_text_field( (string) ( $fields['name'] ?? '' ) ),
			'keyword'    => sanitize_text_field( (string) ( $fields['keyword'] ?? '' ) ),
			'match_type' => in_array( $fields['match_type'] ?? '', array( 'exact', 'partial', 'prefix', 'regex', 'any' ), true )
				? $fields['match_type']
				: 'exact',
			'reply_type' => in_array( $fields['reply_type'] ?? '', array( 'text', 'flex', 'quick_reply', 'sticker', 'image', 'raw', 'flow' ), true )
				? $fields['reply_type']
				: 'text',
			'reply_data' => (string) ( $fields['reply_data'] ?? '' ),
			'priority'   => isset( $fields['priority'] ) ? (int) $fields['priority'] : 10,
			'is_active'  => empty( $fields['is_active'] ) ? 0 : 1,
			'updated_at' => $now,
		);

		$format = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );

		// Flush AFTER the write. Flushing first leaves a window in which an
		// inbound webhook repopulates the cache from the pre-edit rows, and the
		// bot then answers with the old rule for the next five minutes.
		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
			$wpdb->update( self::table(), $data, array( 'id' => $id ), $format, array( '%d' ) );

			self::flush_cache();

			return $id;
		}

		$data['created_at'] = $now;
		$format[]           = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->insert( self::table(), $data, $format );

		self::flush_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a rule.
	 *
	 * @param int $id Rule id.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$deleted = (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );

		self::flush_cache();

		return $deleted;
	}

	/**
	 * All rules for the admin list.
	 *
	 * @return array
	 */
	public static function all(): array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY priority ASC, id DESC" );
	}

	/**
	 * One rule.
	 *
	 * @param int $id Rule id.
	 * @return object|null
	 */
	public static function find( int $id ) {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}
}
