<?php
/**
 * Routes a single webhook event to whatever cares about it.
 *
 * Reply composition is a filter chain rather than a switch statement, so the
 * scenario bot, the keyword rules and the AI provider can each take a turn in
 * a defined order, and a site can insert its own step without editing this file.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Webhook;

use Moksa\Line\Data\Users;
use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;

defined( 'ABSPATH' ) || exit;

class Dispatcher {

	/**
	 * Handle one event.
	 *
	 * @param array $event Decoded webhook event.
	 */
	public static function handle( array $event ): void {
		$type = isset( $event['type'] ) ? (string) $event['type'] : '';

		/**
		 * Fires for every webhook event, before type-specific handling.
		 *
		 * @param array $event Decoded event.
		 */
		do_action( 'moksa_line_event', $event );

		switch ( $type ) {
			case 'follow':
				self::on_follow( $event );
				break;

			case 'unfollow':
				self::on_unfollow( $event );
				break;

			case 'message':
				self::on_message( $event );
				break;

			case 'postback':
				self::on_postback( $event );
				break;

			case 'join':
			case 'leave':
			case 'memberJoined':
			case 'memberLeft':
			case 'accountLink':
			case 'unsend':
			case 'videoPlayComplete':
				/**
				 * Fires for the less common event types.
				 *
				 * @param array $event Decoded event.
				 */
				do_action( 'moksa_line_event_' . $type, $event );
				break;

			default:
				Logger::debug( 'Unhandled webhook event type', array( 'type' => $type ), 'webhook' );
		}
	}

	/**
	 * Someone added the bot as a friend, or unblocked it.
	 *
	 * @param array $event Decoded event.
	 */
	private static function on_follow( array $event ): void {
		$line_user_id = self::user_id( $event );

		if ( '' === $line_user_id ) {
			return;
		}

		Users::set_friend_state( $line_user_id, true );
		self::refresh_profile( $line_user_id );

		do_action( 'moksa_line_event_follow', $event, $line_user_id );

		$greeting = trim( (string) Options::get( 'greeting_message' ) );

		if ( '' === $greeting || empty( $event['replyToken'] ) ) {
			return;
		}

		$messages = self::personalise( $greeting, $line_user_id );

		/**
		 * Filter the greeting sent when someone adds the bot.
		 *
		 * @param array  $messages     Message objects.
		 * @param string $line_user_id LINE user id.
		 */
		$messages = apply_filters( 'moksa_line_greeting_messages', $messages, $line_user_id );

		$result = MessagingClient::reply( (string) $event['replyToken'], $messages );

		Logger::capture( $result, 'Could not send the greeting message', 'bot' );
	}

	/**
	 * Someone blocked the bot or deleted the friendship.
	 *
	 * @param array $event Decoded event.
	 */
	private static function on_unfollow( array $event ): void {
		$line_user_id = self::user_id( $event );

		if ( '' === $line_user_id ) {
			return;
		}

		Users::set_friend_state( $line_user_id, false );

		do_action( 'moksa_line_event_unfollow', $event, $line_user_id );
	}

	/**
	 * An inbound message.
	 *
	 * @param array $event Decoded event.
	 */
	private static function on_message( array $event ): void {
		$line_user_id = self::user_id( $event );
		$message      = isset( $event['message'] ) && is_array( $event['message'] ) ? $event['message'] : array();
		$message_type = isset( $message['type'] ) ? (string) $message['type'] : '';
		$text         = 'text' === $message_type && isset( $message['text'] ) ? (string) $message['text'] : '';

		/**
		 * Fires for every inbound message, before any reply is composed.
		 * The inbox records the message here.
		 *
		 * @param array  $event        Decoded event.
		 * @param string $line_user_id LINE user id.
		 */
		do_action( 'moksa_line_inbound_message', $event, $line_user_id );

		if ( '' === $line_user_id ) {
			return;
		}

		// Group and room events have no per-user profile to speak of, and 1:1
		// automation would be wrong there, so bot replies stay in direct chats
		// unless a site explicitly opts in.
		$source_type = isset( $event['source']['type'] ) ? (string) $event['source']['type'] : 'user';

		/**
		 * Filter whether the bot may answer in this source type.
		 *
		 * @param bool   $allowed     Whether to reply.
		 * @param string $source_type user, group or room.
		 * @param array  $event       Decoded event.
		 */
		if ( ! apply_filters( 'moksa_line_should_auto_reply', 'user' === $source_type, $source_type, $event ) ) {
			return;
		}

		/**
		 * Compose the reply. Handlers return an array of LINE message objects
		 * or null to pass. The first non-null result wins, so priority order
		 * is: live scenario flow (10), keyword rules (20), AI (30).
		 *
		 * @param array|null $messages     Reply messages so far.
		 * @param string     $text         Message text (empty for non-text).
		 * @param array      $event        Decoded event.
		 * @param string     $line_user_id LINE user id.
		 */
		$messages = apply_filters( 'moksa_line_compose_reply', null, $text, $event, $line_user_id );

		if ( empty( $messages ) || empty( $event['replyToken'] ) ) {
			return;
		}

		$result = MessagingClient::reply( (string) $event['replyToken'], $messages );

		if ( Logger::capture( $result, 'Could not send an automatic reply', 'bot' ) ) {
			return;
		}

		/**
		 * Fires after the bot replies, so the inbox can record what was said.
		 *
		 * @param array  $messages     Sent messages.
		 * @param string $line_user_id LINE user id.
		 * @param array  $event        Originating event.
		 */
		do_action( 'moksa_line_replied', $messages, $line_user_id, $event );
	}

	/**
	 * A postback from a rich menu, button, or datetime picker.
	 *
	 * @param array $event Decoded event.
	 */
	private static function on_postback( array $event ): void {
		$line_user_id = self::user_id( $event );
		$data         = isset( $event['postback']['data'] ) ? (string) $event['postback']['data'] : '';

		/**
		 * Fires for every postback.
		 *
		 * @param string $data         Postback data string.
		 * @param array  $event        Decoded event.
		 * @param string $line_user_id LINE user id.
		 */
		do_action( 'moksa_line_postback', $data, $event, $line_user_id );

		/**
		 * Compose a reply to a postback.
		 *
		 * @param array|null $messages     Reply messages.
		 * @param string     $data         Postback data.
		 * @param array      $event        Decoded event.
		 * @param string     $line_user_id LINE user id.
		 */
		$messages = apply_filters( 'moksa_line_compose_postback_reply', null, $data, $event, $line_user_id );

		if ( empty( $messages ) || empty( $event['replyToken'] ) ) {
			return;
		}

		$result = MessagingClient::reply( (string) $event['replyToken'], $messages );

		if ( ! Logger::capture( $result, 'Could not reply to a postback', 'bot' ) ) {
			do_action( 'moksa_line_replied', $messages, $line_user_id, $event );
		}
	}

	/**
	 * Pull a fresh profile for a user we have just met.
	 *
	 * @param string $line_user_id LINE user id.
	 */
	public static function refresh_profile( string $line_user_id ): void {
		if ( '' === $line_user_id ) {
			return;
		}

		// Record that this person exists before asking LINE anything about
		// them. They have just interacted with the bot, so the id is a fact;
		// the display name is merely a nicety. Doing this the other way round
		// meant a failed profile lookup left the LINE users screen empty and
		// the inbox showing raw U... ids, with nothing explaining why.
		Users::upsert( $line_user_id );

		$profile = MessagingClient::profile( $line_user_id );

		if ( is_wp_error( $profile ) ) {
			// Worth a warning rather than silence: the usual cause is a
			// missing or wrong channel access token, and the visible symptom
			// (no names anywhere) does not point at it. Throttled to once an
			// hour, because when the token is wrong this fails on every single
			// inbound message and would otherwise bury the log.
			if ( false === get_transient( 'moksa_line_profile_warned' ) ) {
				set_transient( 'moksa_line_profile_warned', 1, HOUR_IN_SECONDS );

				Logger::warning(
					'Could not fetch a LINE profile, so contacts are recorded without display names. Check the Messaging API channel access token.',
					array(
						'line_user_id' => $line_user_id,
						'detail'       => $profile->get_error_message(),
					),
					'webhook'
				);
			}

			return;
		}

		Users::upsert(
			$line_user_id,
			array(
				'display_name'   => isset( $profile['displayName'] ) ? sanitize_text_field( (string) $profile['displayName'] ) : '',
				'picture_url'    => isset( $profile['pictureUrl'] ) ? esc_url_raw( (string) $profile['pictureUrl'] ) : '',
				'status_message' => isset( $profile['statusMessage'] ) ? sanitize_text_field( (string) $profile['statusMessage'] ) : '',
				'language'       => isset( $profile['language'] ) ? sanitize_text_field( (string) $profile['language'] ) : '',
			)
		);
	}

	/**
	 * Expand the placeholders an administrator can use in canned text.
	 *
	 * @param string $template     Raw text with {display_name} and {site_name}.
	 * @param string $line_user_id LINE user id.
	 * @return array Message objects.
	 */
	public static function personalise( string $template, string $line_user_id ): array {
		$record = Users::by_line_id( $line_user_id );

		$replacements = array(
			'{display_name}' => $record ? (string) $record->display_name : '',
			'{site_name}'    => (string) get_bloginfo( 'name' ),
			'{site_url}'     => home_url(),
		);

		$text = strtr( $template, $replacements );

		return array( MessagingClient::text( $text ) );
	}

	/**
	 * The LINE user id behind an event, if there is one.
	 *
	 * @param array $event Decoded event.
	 */
	public static function user_id( array $event ): string {
		return isset( $event['source']['userId'] ) ? (string) $event['source']['userId'] : '';
	}
}
