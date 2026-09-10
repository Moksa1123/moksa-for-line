<?php
/**
 * Scenario bot: multi-turn conversations that collect answers.
 *
 * A flow is a list of steps; a session remembers where one user is in one
 * flow. Sessions are keyed by LINE user id with a UNIQUE index, so a user can
 * only ever be inside one flow -- which is what stops a second trigger word
 * mid-booking from quietly forking the conversation.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Bot;

use Moksa\Line\Data\Repository;
use Moksa\Line\Data\Users;
use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Migrator;

defined( 'ABSPATH' ) || exit;

class Flow extends Repository {

	/** An abandoned flow forgets itself after half an hour. */
	const SESSION_TTL = 1800;

	protected static function key(): string {
		return 'flows';
	}

	protected static function columns(): array {
		return array(
			'name'          => '%s',
			'trigger_type'  => '%s',
			'trigger_value' => '%s',
			'definition'    => '%s',
			'on_complete'   => '%s',
			'notify_email'  => '%s',
			'is_active'     => '%d',
		);
	}

	protected static function order(): string {
		return 'name ASC';
	}

	private static function sessions_table(): string {
		return Migrator::table( 'flow_sessions' );
	}

	private static function submissions_table(): string {
		return Migrator::table( 'flow_submissions' );
	}

	// --- Session state ---------------------------------------------------------

	/**
	 * The live session for a user, if it has not expired.
	 *
	 * @param string $line_user_id LINE user id.
	 * @return object|null
	 */
	public static function session( string $line_user_id ) {
		global $wpdb;
		$table = self::sessions_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE line_user_id = %s AND expires_at > %s",
				$line_user_id,
				current_time( 'mysql', true )
			)
		);

		return $row ? $row : null;
	}

	/**
	 * Start a flow for a user, replacing any session already in progress.
	 *
	 * @param int    $flow_id      Flow id.
	 * @param string $line_user_id LINE user id.
	 * @return array|null Messages asking the first question.
	 */
	public static function start( int $flow_id, string $line_user_id ) {
		$flow  = self::find( $flow_id );
		$steps = self::steps( $flow );

		if ( ! $flow || empty( $steps ) ) {
			return null;
		}

		global $wpdb;

		$now     = current_time( 'mysql', true );
		$expires = gmdate( 'Y-m-d H:i:s', time() + self::SESSION_TTL );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . self::sessions_table() . '
					(line_user_id, flow_id, step_index, answers, expires_at, created_at, updated_at)
				 VALUES (%s, %d, 0, %s, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE
					flow_id = VALUES(flow_id),
					step_index = 0,
					answers = VALUES(answers),
					expires_at = VALUES(expires_at),
					updated_at = VALUES(updated_at)',
				$line_user_id,
				$flow_id,
				wp_json_encode( array() ),
				$expires,
				$now,
				$now
			)
		);

		return self::ask( $steps[0] );
	}

	/**
	 * Feed a message into the live session.
	 *
	 * @param object $session      Session row.
	 * @param string $text         Inbound text.
	 * @param string $line_user_id LINE user id.
	 * @return array|null Messages to send back.
	 */
	public static function advance( $session, string $text, string $line_user_id ) {
		$flow  = self::find( (int) $session->flow_id );
		$steps = self::steps( $flow );

		if ( ! $flow || empty( $steps ) ) {
			self::end( $line_user_id );

			return null;
		}

		$text = trim( $text );

		if ( self::is_cancel( $text ) ) {
			self::end( $line_user_id );

			return array( MessagingClient::text( __( 'No problem, that has been cancelled.', 'moksa-line' ) ) );
		}

		$index = (int) $session->step_index;
		$step  = isset( $steps[ $index ] ) ? $steps[ $index ] : null;

		if ( ! $step ) {
			self::end( $line_user_id );

			return null;
		}

		$validation = self::validate( $step, $text );

		if ( is_string( $validation ) ) {
			// Re-ask rather than advancing, so a typo does not lose the answer
			// the user was part-way through giving.
			return array_merge(
				array( MessagingClient::text( $validation ) ),
				self::ask( $step )
			);
		}

		$answers                              = self::answers( $session );
		$answers[ (string) $step['key'] ]     = $validation;
		$next                                 = $index + 1;

		if ( isset( $steps[ $next ] ) ) {
			self::save_session( $line_user_id, $next, $answers );

			return self::ask( $steps[ $next ] );
		}

		return self::complete( $flow, $line_user_id, $answers );
	}

	/**
	 * Finish a flow: record the answers, notify, and thank the user.
	 *
	 * @param object $flow         Flow row.
	 * @param string $line_user_id LINE user id.
	 * @param array  $answers      Collected answers.
	 * @return array Messages to send back.
	 */
	private static function complete( $flow, string $line_user_id, array $answers ): array {
		self::end( $line_user_id );

		$record  = Users::by_line_id( $line_user_id );
		$display = $record ? (string) $record->display_name : '';

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->insert(
			self::submissions_table(),
			array(
				'flow_id'      => (int) $flow->id,
				'line_user_id' => $line_user_id,
				'display_name' => $display,
				'answers'      => wp_json_encode( $answers ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		$submission_id = (int) $wpdb->insert_id;

		self::notify( $flow, $display, $line_user_id, $answers );

		/**
		 * Fires when a scenario flow is completed.
		 *
		 * @param array  $answers       Collected answers, keyed by step key.
		 * @param object $flow          Flow row.
		 * @param string $line_user_id  LINE user id.
		 * @param int    $submission_id Stored submission id.
		 */
		do_action( 'moksa_line_flow_completed', $answers, $flow, $line_user_id, $submission_id );

		$definition = self::definition( $flow );
		$message    = isset( $definition['complete_message'] ) && '' !== trim( (string) $definition['complete_message'] )
			? (string) $definition['complete_message']
			: __( 'Thank you, we have received your details.', 'moksa-line' );

		return array( MessagingClient::text( AutoReply::expand( $message, $line_user_id ) ) );
	}

	/**
	 * Email the site about a completed flow.
	 *
	 * @param object $flow         Flow row.
	 * @param string $display      Display name.
	 * @param string $line_user_id LINE user id.
	 * @param array  $answers      Collected answers.
	 */
	private static function notify( $flow, string $display, string $line_user_id, array $answers ): void {
		$to = trim( (string) $flow->notify_email );

		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}

		$lines = array(
			sprintf(
				/* translators: %s: flow name. */
				__( 'A visitor completed "%s" on LINE.', 'moksa-line' ),
				(string) $flow->name
			),
			'',
			sprintf( '%s: %s', __( 'Display name', 'moksa-line' ), $display ),
			sprintf( '%s: %s', __( 'LINE user id', 'moksa-line' ), $line_user_id ),
			'',
		);

		foreach ( $answers as $key => $value ) {
			$lines[] = sprintf( '%s: %s', $key, is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
		}

		wp_mail(
			$to,
			sprintf(
				/* translators: 1: site name, 2: flow name. */
				__( '[%1$s] New LINE submission: %2$s', 'moksa-line' ),
				get_bloginfo( 'name' ),
				(string) $flow->name
			),
			implode( "\n", $lines )
		);
	}

	/**
	 * Persist session progress.
	 *
	 * @param string $line_user_id LINE user id.
	 * @param int    $step_index   Next step.
	 * @param array  $answers      Answers so far.
	 */
	private static function save_session( string $line_user_id, int $step_index, array $answers ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->update(
			self::sessions_table(),
			array(
				'step_index' => $step_index,
				'answers'    => wp_json_encode( $answers ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::SESSION_TTL ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'line_user_id' => $line_user_id ),
			array( '%d', '%s', '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Drop a user's session.
	 *
	 * @param string $line_user_id LINE user id.
	 */
	public static function end( string $line_user_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- internal table.
		$wpdb->delete( self::sessions_table(), array( 'line_user_id' => $line_user_id ), array( '%s' ) );
	}

	// --- Steps ------------------------------------------------------------------

	/**
	 * Build the question message for a step, with choices as quick replies.
	 *
	 * @param array $step Step definition.
	 * @return array Message objects.
	 */
	private static function ask( array $step ): array {
		$prompt = isset( $step['prompt'] ) ? (string) $step['prompt'] : '';

		if ( '' === $prompt ) {
			$prompt = __( 'Please reply with your answer.', 'moksa-line' );
		}

		$quick = array();

		if ( ! empty( $step['choices'] ) && is_array( $step['choices'] ) ) {
			foreach ( array_slice( $step['choices'], 0, 12 ) as $choice ) {
				$label = mb_substr( (string) $choice, 0, 20 );

				$quick[] = array(
					'type'   => 'action',
					'action' => array(
						'type'  => 'message',
						'label' => $label,
						'text'  => (string) $choice,
					),
				);
			}
		}

		// Always offer a way out: a scenario the user cannot escape is worse
		// than no scenario at all.
		$quick[] = array(
			'type'   => 'action',
			'action' => array(
				'type'  => 'message',
				'label' => __( 'Cancel', 'moksa-line' ),
				'text'  => __( 'Cancel', 'moksa-line' ),
			),
		);

		return array( MessagingClient::text( $prompt, $quick ) );
	}

	/**
	 * Validate one answer.
	 *
	 * @param array  $step Step definition.
	 * @param string $text Raw answer.
	 * @return string|mixed The cleaned value, or a string error message.
	 */
	private static function validate( array $step, string $text ) {
		$type = isset( $step['type'] ) ? (string) $step['type'] : 'text';

		if ( '' === $text ) {
			return __( 'That looked empty. Please try again.', 'moksa-line' );
		}

		switch ( $type ) {
			case 'email':
				return is_email( $text )
					? sanitize_email( $text )
					: __( 'That does not look like an email address. Please try again.', 'moksa-line' );

			case 'phone':
				$digits = preg_replace( '/[^0-9+]/', '', $text );

				return strlen( (string) $digits ) >= 8
					? $digits
					: __( 'That does not look like a phone number. Please try again.', 'moksa-line' );

			case 'number':
				return is_numeric( $text )
					? $text + 0
					: __( 'Please reply with a number.', 'moksa-line' );

			case 'date':
				$timestamp = strtotime( $text );

				return false !== $timestamp
					? gmdate( 'Y-m-d', $timestamp )
					: __( 'Please reply with a date, for example 2026-03-15.', 'moksa-line' );

			case 'choice':
				$choices = isset( $step['choices'] ) ? array_map( 'strval', (array) $step['choices'] ) : array();

				foreach ( $choices as $choice ) {
					if ( 0 === strcasecmp( $choice, $text ) ) {
						return $choice;
					}
				}

				return sprintf(
					/* translators: %s: list of choices, joined with the separator below. */
					__( 'Please choose one of: %s', 'moksa-line' ),
					/* translators: separator between items in a list, including any trailing space. Chinese uses a full-width enumeration comma with no space. */
					implode( _x( ', ', 'list separator', 'moksa-line' ), $choices )
				);

			default:
				$max = isset( $step['max_length'] ) ? (int) $step['max_length'] : 500;

				return mb_substr( sanitize_textarea_field( $text ), 0, $max );
		}
	}

	/**
	 * Whether a message means "get me out of here".
	 *
	 * @param string $text Inbound text.
	 */
	private static function is_cancel( string $text ): bool {
		$words = array( 'cancel', 'quit', 'stop', 'exit' );

		/**
		 * Filter the words that abandon a running flow. Sites should add their
		 * own language's equivalents here.
		 *
		 * @param string[] $words Cancel words, compared case-insensitively.
		 */
		$words = apply_filters( 'moksa_line_flow_cancel_words', $words );

		// The translated "Cancel" label is always accepted, since it is what
		// the quick reply button sends.
		$words[] = __( 'Cancel', 'moksa-line' );

		foreach ( $words as $word ) {
			if ( 0 === strcasecmp( trim( $text ), trim( (string) $word ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Decoded definition for a flow.
	 *
	 * @param object|null $flow Flow row.
	 * @return array
	 */
	public static function definition( $flow ): array {
		if ( ! $flow ) {
			return array();
		}

		$decoded = json_decode( (string) $flow->definition, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Steps of a flow, with keys guaranteed present.
	 *
	 * @param object|null $flow Flow row.
	 * @return array
	 */
	public static function steps( $flow ): array {
		$definition = self::definition( $flow );
		$steps      = isset( $definition['steps'] ) && is_array( $definition['steps'] ) ? $definition['steps'] : array();
		$clean      = array();

		foreach ( $steps as $index => $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			if ( empty( $step['key'] ) ) {
				$step['key'] = 'field_' . ( $index + 1 );
			}

			$clean[] = $step;
		}

		return $clean;
	}

	/**
	 * Answers recorded on a session.
	 *
	 * @param object $session Session row.
	 * @return array
	 */
	private static function answers( $session ): array {
		$decoded = json_decode( (string) $session->answers, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Find an active flow whose trigger matches a message.
	 *
	 * @param string $text Inbound text.
	 * @return object|null
	 */
	public static function match_trigger( string $text ) {
		$text = trim( $text );

		if ( '' === $text ) {
			return null;
		}

		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$flows = (array) $wpdb->get_results( "SELECT * FROM {$table} WHERE is_active = 1" );

		foreach ( $flows as $flow ) {
			$trigger = trim( (string) $flow->trigger_value );

			if ( '' === $trigger ) {
				continue;
			}

			if ( 'keyword' === $flow->trigger_type && 0 === strcasecmp( $text, $trigger ) ) {
				return $flow;
			}

			if ( 'contains' === $flow->trigger_type && false !== stripos( $text, $trigger ) ) {
				return $flow;
			}
		}

		return null;
	}

	/**
	 * Submissions for a flow, newest first.
	 *
	 * @param int $flow_id Flow id.
	 * @param int $limit   Rows to return.
	 * @return array
	 */
	public static function submissions( int $flow_id, int $limit = 100 ): array {
		global $wpdb;
		$table = self::submissions_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE flow_id = %d ORDER BY id DESC LIMIT %d", $flow_id, $limit )
		);
	}
}
