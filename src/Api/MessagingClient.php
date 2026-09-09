<?php
/**
 * Messaging API operations.
 *
 * Every send path normalises its messages first, so a caller can pass a plain
 * string, one message array, or a list, and always get a valid request body.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Api;

use Moksa\Line\Flex\Validator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class MessagingClient {

	/** LINE rejects more than five message objects per send. */
	const MAX_MESSAGES = 5;
	/** multicast accepts at most 500 recipients per call. */
	const MAX_MULTICAST = 500;

	/**
	 * Reply to an inbound event.
	 *
	 * Reply tokens are single-use and expire after a minute; when one has
	 * already been spent the caller should fall back to push().
	 *
	 * @param string $reply_token Token from the webhook event.
	 * @param mixed  $messages    String, message array, or list of them.
	 * @param array  $options     notification_disabled (bool).
	 * @return array|WP_Error
	 */
	public static function reply( string $reply_token, $messages, array $options = array() ) {
		$messages = self::normalize( $messages );

		if ( is_wp_error( $messages ) ) {
			return $messages;
		}

		$body = array(
			'replyToken' => $reply_token,
			'messages'   => $messages,
		);

		if ( ! empty( $options['notification_disabled'] ) ) {
			$body['notificationDisabled'] = true;
		}

		return Client::request( 'POST', '/message/reply', $body );
	}

	/**
	 * Push to a single user, group or room. Billed.
	 *
	 * @param string $to        userId, groupId or roomId.
	 * @param mixed  $messages  String, message array, or list of them.
	 * @param array  $options   retry_key (string), notification_disabled (bool).
	 * @return array|WP_Error
	 */
	public static function push( string $to, $messages, array $options = array() ) {
		$messages = self::normalize( $messages );

		if ( is_wp_error( $messages ) ) {
			return $messages;
		}

		$body = array(
			'to'       => $to,
			'messages' => $messages,
		);

		if ( ! empty( $options['notification_disabled'] ) ) {
			$body['notificationDisabled'] = true;
		}

		return Client::request(
			'POST',
			'/message/push',
			$body,
			array( 'retry_key' => isset( $options['retry_key'] ) ? $options['retry_key'] : wp_generate_uuid4() )
		);
	}

	/**
	 * Push to many users, chunked to LINE's 500-per-call ceiling.
	 *
	 * @param string[] $recipients LINE user ids.
	 * @param mixed    $messages   Messages to send.
	 * @return array{sent:int,failed:int,errors:string[]}
	 */
	public static function multicast( array $recipients, $messages ): array {
		$recipients = array_values( array_unique( array_filter( $recipients ) ) );
		$normalized = self::normalize( $messages );

		$report = array( 'sent' => 0, 'failed' => 0, 'errors' => array() );

		if ( is_wp_error( $normalized ) ) {
			$report['errors'][] = $normalized->get_error_message();
			$report['failed']   = count( $recipients );

			return $report;
		}

		foreach ( array_chunk( $recipients, self::MAX_MULTICAST ) as $chunk ) {
			$result = Client::request(
				'POST',
				'/message/multicast',
				array(
					'to'       => $chunk,
					'messages' => $normalized,
				),
				array( 'retry_key' => wp_generate_uuid4() )
			);

			if ( is_wp_error( $result ) ) {
				$report['failed']  += count( $chunk );
				$report['errors'][] = $result->get_error_message();
				continue;
			}

			$report['sent'] += count( $chunk );
		}

		return $report;
	}

	/**
	 * Send to every friend of the account.
	 *
	 * @param mixed $messages Messages to send.
	 * @return array|WP_Error
	 */
	public static function broadcast( $messages ) {
		$normalized = self::normalize( $messages );

		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		return Client::request(
			'POST',
			'/message/broadcast',
			array( 'messages' => $normalized ),
			array( 'retry_key' => wp_generate_uuid4() )
		);
	}

	/**
	 * Segmented send.
	 *
	 * @param mixed      $messages  Messages to send.
	 * @param array|null $recipient Audience selector object.
	 * @param array|null $filter    Demographic filter object.
	 * @param array|null $limit     Delivery limit object.
	 * @return array|WP_Error
	 */
	public static function narrowcast( $messages, ?array $recipient = null, ?array $filter = null, ?array $limit = null ) {
		$normalized = self::normalize( $messages );

		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$body = array( 'messages' => $normalized );

		if ( $recipient ) {
			$body['recipient'] = $recipient;
		}

		if ( $filter ) {
			$body['filter'] = $filter;
		}

		if ( $limit ) {
			$body['limit'] = $limit;
		}

		return Client::request(
			'POST',
			'/message/narrowcast',
			$body,
			array( 'retry_key' => wp_generate_uuid4() )
		);
	}

	/**
	 * Ask LINE to validate a message body without delivering it. Used by the
	 * Flex editor's "Test" button so a broken template is caught before it is
	 * ever sent to a customer.
	 *
	 * @param mixed  $messages Messages to check.
	 * @param string $as       push, reply, broadcast, multicast or narrowcast.
	 * @return true|WP_Error
	 */
	public static function validate( $messages, string $as = 'push' ) {
		$normalized = self::normalize( $messages );

		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$allowed = array( 'push', 'reply', 'broadcast', 'multicast', 'narrowcast' );
		$as      = in_array( $as, $allowed, true ) ? $as : 'push';

		$result = Client::request( 'POST', '/message/validate/' . $as, array( 'messages' => $normalized ) );

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Profile of a user who has added the bot as a friend.
	 *
	 * @param string $line_user_id LINE user id.
	 * @return array|WP_Error
	 */
	public static function profile( string $line_user_id ) {
		return Client::request( 'GET', '/profile/' . rawurlencode( $line_user_id ) );
	}

	/**
	 * Show the typing indicator in a 1:1 chat while a slow AI reply is generated.
	 *
	 * @param string $line_user_id LINE user id.
	 * @param int    $seconds      5-60, rounded to the nearest five.
	 * @return array|WP_Error
	 */
	public static function show_loading( string $line_user_id, int $seconds = 20 ) {
		$seconds = min( 60, max( 5, (int) ( round( $seconds / 5 ) * 5 ) ) );

		return Client::request(
			'POST',
			'/chat/loading/start',
			array(
				'chatId'         => $line_user_id,
				'loadingSeconds' => $seconds,
			)
		);
	}

	/**
	 * Monthly message quota.
	 *
	 * @return array|WP_Error
	 */
	public static function quota() {
		return Client::request( 'GET', '/message/quota' );
	}

	/**
	 * Messages consumed this month.
	 *
	 * @return array|WP_Error
	 */
	public static function quota_consumption() {
		return Client::request( 'GET', '/message/quota/consumption' );
	}

	/**
	 * How many people a broadcast would actually reach.
	 *
	 * Reports targetedReaches rather than followers: the follower count is a
	 * cumulative tally of first-time adds that never goes down, so it overstates
	 * the audience of anything sent today, sometimes badly. LINE only computes
	 * this up to the previous day, and answers "unready" for a date it has not
	 * finished, so the caller has to cope with not knowing.
	 *
	 * The endpoint allows 60 requests an hour, hence the cache; a number that is
	 * a day old anyway loses nothing by being an hour stale.
	 *
	 * @return int|null Reachable people, or null when LINE cannot say.
	 */
	public static function reachable(): ?int {
		$cached = get_transient( 'moksa_line_reachable' );

		if ( false !== $cached ) {
			return '' === $cached ? null : (int) $cached;
		}

		$response = Client::request( 'GET', '/insight/followers?date=' . gmdate( 'Ymd', time() - DAY_IN_SECONDS ) );
		$reach    = null;

		if ( ! is_wp_error( $response ) && isset( $response['status'] ) && 'ready' === $response['status'] ) {
			$reach = isset( $response['targetedReaches'] ) ? (int) $response['targetedReaches'] : null;
		}

		set_transient( 'moksa_line_reachable', null === $reach ? '' : (string) $reach, HOUR_IN_SECONDS );

		return $reach;
	}

	/**
	 * Bot metadata, used by the settings screen to confirm the token works.
	 *
	 * @return array|WP_Error
	 */
	public static function bot_info() {
		return Client::request( 'GET', '/info' );
	}

	/**
	 * Number of friends the account currently has.
	 *
	 * @return array|WP_Error
	 */
	public static function follower_ids( string $start = '' ) {
		$path = '/followers/ids?limit=1000';

		if ( '' !== $start ) {
			$path .= '&start=' . rawurlencode( $start );
		}

		return Client::request( 'GET', $path );
	}

	/**
	 * Coerce whatever the caller passed into a valid messages array.
	 *
	 * @param mixed $messages String, message array, or list of messages.
	 * @return array|WP_Error
	 */
	public static function normalize( $messages ) {
		if ( is_string( $messages ) ) {
			$messages = array( self::text( $messages ) );
		} elseif ( is_array( $messages ) && isset( $messages['type'] ) ) {
			// A single message object rather than a list.
			$messages = array( $messages );
		}

		if ( ! is_array( $messages ) || empty( $messages ) ) {
			return new WP_Error(
				'moksa_line_empty_message',
				__( 'There is nothing to send.', 'moksa-line' )
			);
		}

		$messages = array_values( $messages );

		if ( count( $messages ) > self::MAX_MESSAGES ) {
			return new WP_Error(
				'moksa_line_too_many_messages',
				sprintf(
					/* translators: %d: maximum number of messages. */
					__( 'LINE accepts at most %d messages per send.', 'moksa-line' ),
					self::MAX_MESSAGES
				)
			);
		}

		foreach ( $messages as $index => $message ) {
			if ( ! is_array( $message ) || empty( $message['type'] ) ) {
				return new WP_Error(
					'moksa_line_invalid_message',
					sprintf(
						/* translators: %d: zero-based message index. */
						__( 'Message %d is missing its type.', 'moksa-line' ),
						$index
					)
				);
			}

			// Catch the common Flex mistakes locally: a round trip to LINE just
			// to be told "contents is required" is a slow way to learn.
			if ( 'flex' === $message['type'] ) {
				$problems = Validator::check_message( $message );

				if ( ! empty( $problems ) ) {
					return new WP_Error(
						'moksa_line_invalid_flex',
						implode( ' ', array_slice( $problems, 0, 3 ) )
					);
				}
			}
		}

		return $messages;
	}

	/**
	 * Build a text message, truncated to LINE's 5000 character ceiling.
	 *
	 * @param string     $text        Body text.
	 * @param array|null $quick_reply Quick reply items.
	 * @return array
	 */
	public static function text( string $text, ?array $quick_reply = null ): array {
		$message = array(
			'type' => 'text',
			'text' => mb_substr( $text, 0, 5000 ),
		);

		if ( ! empty( $quick_reply ) ) {
			$message['quickReply'] = array( 'items' => array_slice( $quick_reply, 0, 13 ) );
		}

		return $message;
	}

	/**
	 * Build a Flex message.
	 *
	 * @param string $alt_text Fallback shown in the chat list and notifications.
	 * @param array  $contents Bubble or carousel container.
	 * @return array
	 */
	public static function flex( string $alt_text, array $contents ): array {
		return array(
			'type'     => 'flex',
			'altText'  => mb_substr( '' !== $alt_text ? $alt_text : 'Message', 0, 1500 ),
			'contents' => $contents,
		);
	}
}
