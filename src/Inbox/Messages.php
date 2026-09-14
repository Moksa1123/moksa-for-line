<?php
/**
 * Message history for the inbox.
 *
 * @package Mofoline
 */

namespace Mofoline\Inbox;

use Mofoline\Support\Db;
use Mofoline\Support\Migrator;

defined( 'ABSPATH' ) || exit;

class Messages {

	public static function table(): string {
		return Migrator::table( 'messages' );
	}

	/**
	 * Record a message.
	 *
	 * @param array $fields conversation_id, line_user_id, direction,
	 *                      message_type, body, payload, line_message_id,
	 *                      sender_wp_user_id, sender_kind, status, error.
	 * @return int Row id.
	 */
	public static function record( array $fields ): int {
		$data = array(
			'conversation_id'   => (int) ( $fields['conversation_id'] ?? 0 ),
			'line_user_id'      => (string) ( $fields['line_user_id'] ?? '' ),
			'direction'         => 'out' === ( $fields['direction'] ?? 'in' ) ? 'out' : 'in',
			'message_type'      => substr( (string) ( $fields['message_type'] ?? 'text' ), 0, 24 ),
			'body'              => (string) ( $fields['body'] ?? '' ),
			'payload'           => isset( $fields['payload'] ) ? wp_json_encode( $fields['payload'] ) : null,
			'line_message_id'   => (string) ( $fields['line_message_id'] ?? '' ),
			'sender_wp_user_id' => (int) ( $fields['sender_wp_user_id'] ?? 0 ),
			'sender_kind'       => substr( (string) ( $fields['sender_kind'] ?? 'user' ), 0, 16 ),
			'status'            => substr( (string) ( $fields['status'] ?? 'sent' ), 0, 16 ),
			'error'             => isset( $fields['error'] ) ? substr( (string) $fields['error'], 0, 1000 ) : null,
			'created_at'        => current_time( 'mysql', true ),
		);

		Db::insert(
			self::table(),
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) Db::insert_id();
	}

	/**
	 * The thread for one conversation, oldest first.
	 *
	 * @param int $conversation_id Conversation id.
	 * @param int $limit           Rows to return.
	 * @return array
	 */
	public static function thread( int $conversation_id, int $limit = 100 ): array {
		$table = self::table();

		$rows = (array) Db::get_results(
			Db::prepare(
				"SELECT * FROM %i WHERE conversation_id = %d ORDER BY id DESC LIMIT %d",
				$table,
				$conversation_id,
				$limit
			)
		);

		return array_reverse( $rows );
	}

	/**
	 * The id of the most recent message of any kind.
	 *
	 * What the inbox screen remembers, so the next heartbeat can ask "anything
	 * after this?" rather than "anything at all?".
	 */
	public static function latest_id(): int {
		return (int) Db::get_var( Db::prepare( 'SELECT MAX(id) FROM %i', self::table() ) );
	}

	/**
	 * How many messages have come in from customers since a given id.
	 *
	 * Inbound only: the agent's own replies going out should not ring the bell.
	 *
	 * @param int $since           Message id the caller already knows about.
	 * @param int $conversation_id Limit to one conversation, or 0 for all.
	 */
	public static function inbound_since( int $since, int $conversation_id = 0 ): int {
		if ( $conversation_id > 0 ) {
			return (int) Db::get_var(
				Db::prepare(
					"SELECT COUNT(*) FROM %i WHERE id > %d AND direction = 'in' AND conversation_id = %d",
					self::table(),
					$since,
					$conversation_id
				)
			);
		}

		return (int) Db::get_var(
			Db::prepare( "SELECT COUNT(*) FROM %i WHERE id > %d AND direction = 'in'", self::table(), $since )
		);
	}

	/**
	 * Turn an inbound LINE message object into something readable in a list.
	 *
	 * @param array $message LINE message object from the webhook.
	 * @return string
	 */
	public static function describe( array $message ): string {
		$type = isset( $message['type'] ) ? (string) $message['type'] : '';

		switch ( $type ) {
			case 'text':
				return isset( $message['text'] ) ? (string) $message['text'] : '';

			case 'image':
				return __( '[Image]', 'moksa-for-line' );

			case 'video':
				return __( '[Video]', 'moksa-for-line' );

			case 'audio':
				return __( '[Audio]', 'moksa-for-line' );

			case 'file':
				return isset( $message['fileName'] )
					? sprintf( '[%s] %s', __( 'File', 'moksa-for-line' ), (string) $message['fileName'] )
					: __( '[File]', 'moksa-for-line' );

			case 'location':
				$title = isset( $message['title'] ) ? (string) $message['title'] : '';
				$addr  = isset( $message['address'] ) ? (string) $message['address'] : '';

				return trim( sprintf( '[%s] %s %s', __( 'Location', 'moksa-for-line' ), $title, $addr ) );

			case 'sticker':
				return __( '[Sticker]', 'moksa-for-line' );

			default:
				return sprintf( '[%s]', $type ? $type : __( 'Message', 'moksa-for-line' ) );
		}
	}

	/**
	 * Summarise an outbound message array for the preview column.
	 *
	 * @param array $messages Message objects that were sent.
	 * @return string
	 */
	public static function describe_outbound( array $messages ): string {
		$parts = array();

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			if ( isset( $message['type'] ) && 'flex' === $message['type'] ) {
				$parts[] = isset( $message['altText'] )
					? sprintf( '[%s] %s', __( 'Flex', 'moksa-for-line' ), (string) $message['altText'] )
					: __( '[Flex]', 'moksa-for-line' );
				continue;
			}

			// A template message is only identifiable by its altText too, and
			// without this the thread showed a bare "[template]".
			if ( isset( $message['type'] ) && 'template' === $message['type'] ) {
				$parts[] = isset( $message['altText'] )
					? sprintf( '[%s] %s', __( 'Template', 'moksa-for-line' ), (string) $message['altText'] )
					: __( '[Template]', 'moksa-for-line' );
				continue;
			}

			$parts[] = self::describe( $message );
		}

		return implode( ' ', array_filter( $parts ) );
	}
}
