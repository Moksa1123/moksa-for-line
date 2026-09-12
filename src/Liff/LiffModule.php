<?php
/**
 * LIFF: pages that run inside the LINE in-app browser.
 *
 * The single rule that matters here: the browser tells us who it claims to
 * be, and that claim is worthless on its own. Every endpoint verifies the
 * LIFF ID token with LINE before believing any user id, so a crafted request
 * cannot post into someone else's conversation.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Liff;

use Moksa\Line\Data\Users;
use Moksa\Line\Admin\AdminModule;
use Moksa\Line\Inbox\Conversations;
use Moksa\Line\Inbox\Messages;
use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class LiffModule {

	const NAMESPACE_V1 = 'moksa-line/v1';
	const SDK_URL      = 'https://static.line-scdn.net/liff/edge/2/sdk.js';
	const VERIFY_URL   = 'https://api.line.me/oauth2/v2.1/verify';

	/** The shortcodes a page has to carry to be a LIFF endpoint. */
	const SHORTCODES = array( 'moksa_liff_profile', 'moksa_line_chat' );

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_shortcode( 'moksa_liff_profile', array( $this, 'shortcode_profile' ) );
		add_shortcode( 'moksa_line_chat', array( $this, 'shortcode_chat' ) );

		add_action( 'wp_ajax_moksa_line_liff_create_page', array( $this, 'ajax_create_page' ) );
	}

	// --- The endpoint page ----------------------------------------------------------

	/**
	 * The published page that carries a LIFF shortcode, if there is one.
	 *
	 * The LINE console asks for an "Endpoint URL" and nothing in this plugin
	 * used to say what that was: the answer is "whatever page you put the
	 * shortcode on", which is only obvious once you know it.
	 *
	 * @return int Page id, or 0.
	 */
	public static function endpoint_page_id(): int {
		$cached = get_transient( 'moksa_line_liff_page' );

		if ( is_numeric( $cached ) && ( 0 === (int) $cached || 'publish' === get_post_status( (int) $cached ) ) ) {
			return (int) $cached;
		}

		global $wpdb;

		$like = array();
		$args = array();

		foreach ( self::SHORTCODES as $tag ) {
			$like[] = 'post_content LIKE %s';
			$args[] = '%' . $wpdb->esc_like( '[' . $tag ) . '%';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the OR list is built from literals above; cached in a transient.
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND (" . implode( ' OR ', $like ) . ') ORDER BY ID ASC LIMIT 1',
				$args
			)
		);

		set_transient( 'moksa_line_liff_page', $id, HOUR_IN_SECONDS );

		return $id;
	}

	/**
	 * The URL to paste into the LINE console, or '' when there is no page yet.
	 */
	public static function endpoint_url(): string {
		$id = self::endpoint_page_id();

		return $id > 0 ? (string) get_permalink( $id ) : '';
	}

	/**
	 * Create the endpoint page, from the settings screen.
	 */
	public function ajax_create_page(): void {
		\Moksa\Line\Admin\Ajax::guard( __( 'You cannot create pages.', 'moksa-line' ), 'publish_pages' );

		$existing = self::endpoint_page_id();

		if ( $existing > 0 ) {
			wp_send_json_success( array( 'url' => get_permalink( $existing ), 'created' => false ) );
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'LINE', 'moksa-line' ),
				'post_name'    => 'line-app',
				'post_content' => "<!-- wp:shortcode -->\n[moksa_liff_profile]\n<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->\n[moksa_line_chat]\n<!-- /wp:shortcode -->",
			),
			true
		);

		\Moksa\Line\Admin\Ajax::bail( $id, 'Could not create the LIFF page', 'liff' );

		delete_transient( 'moksa_line_liff_page' );

		wp_send_json_success( array( 'url' => get_permalink( (int) $id ), 'created' => true ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/liff/session',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_session' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id_token' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/liff/message',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_message' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id_token' => array( 'required' => true, 'type' => 'string' ),
					'message'  => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);
	}

	// --- Endpoints ---------------------------------------------------------------

	/**
	 * Exchange a verified LIFF ID token for a profile the page can display,
	 * and record the visitor.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_session( WP_REST_Request $request ) {
		$claims = self::verify_id_token( (string) $request->get_param( 'id_token' ) );

		if ( is_wp_error( $claims ) ) {
			return $claims;
		}

		$line_user_id = (string) $claims['sub'];

		Users::upsert(
			$line_user_id,
			array(
				'display_name' => isset( $claims['name'] ) ? sanitize_text_field( (string) $claims['name'] ) : '',
				'picture_url'  => isset( $claims['picture'] ) ? esc_url_raw( (string) $claims['picture'] ) : '',
			)
		);

		$record = Users::by_line_id( $line_user_id );

		return new WP_REST_Response(
			array(
				'display_name' => $record ? (string) $record->display_name : '',
				'picture_url'  => $record ? (string) $record->picture_url : '',
				'linked'       => $record ? ( (int) $record->wp_user_id > 0 ) : false,
			),
			200
		);
	}

	/**
	 * Receive a message typed into a LIFF chat page.
	 *
	 * The message lands in the customer-service inbox. An agent's reply goes
	 * out as a LINE push, so the customer sees it in their normal LINE chat
	 * rather than only inside the web page they may already have closed.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_message( WP_REST_Request $request ) {
		if ( ! Options::get( 'inbox_enabled' ) ) {
			return new WP_Error(
				'moksa_line_inbox_off',
				__( 'Messaging is not available right now.', 'moksa-line' ),
				array( 'status' => 503 )
			);
		}

		$claims = self::verify_id_token( (string) $request->get_param( 'id_token' ) );

		if ( is_wp_error( $claims ) ) {
			return $claims;
		}

		$line_user_id = (string) $claims['sub'];
		$text         = trim( sanitize_textarea_field( (string) $request->get_param( 'message' ) ) );

		if ( '' === $text ) {
			return new WP_Error(
				'moksa_line_empty_message',
				__( 'Write something first.', 'moksa-line' ),
				array( 'status' => 400 )
			);
		}

		// A chat widget is an obvious spam target, so cap what one identity can
		// send before an agent has had a chance to look at it.
		if ( ! self::within_rate_limit( $line_user_id ) ) {
			return new WP_Error(
				'moksa_line_too_fast',
				__( 'That is a lot of messages at once. Please wait a moment.', 'moksa-line' ),
				array( 'status' => 429 )
			);
		}

		$text = mb_substr( $text, 0, 2000 );

		$conversation_id = Conversations::ensure( $line_user_id );

		Messages::record(
			array(
				'conversation_id' => $conversation_id,
				'line_user_id'    => $line_user_id,
				'direction'       => 'in',
				'message_type'    => 'text',
				'body'            => $text,
				'sender_kind'     => 'user',
			)
		);

		Conversations::touch( $line_user_id, $text, true );

		/**
		 * Fires when a message arrives through a LIFF chat page rather than
		 * the LINE chat itself.
		 *
		 * @param string $text         Message text.
		 * @param string $line_user_id LINE user id.
		 */
		do_action( 'moksa_line_liff_message', $text, $line_user_id );

		return new WP_REST_Response( array( 'status' => 'received' ), 200 );
	}

	// --- Verification -------------------------------------------------------------

	/**
	 * Verify a LIFF ID token against LINE and return its claims.
	 *
	 * @param string $id_token JWT from liff.getIDToken().
	 * @return array|WP_Error
	 */
	public static function verify_id_token( string $id_token ) {
		if ( '' === $id_token ) {
			return new WP_Error(
				'moksa_line_liff_no_token',
				__( 'This page could not identify you. Reopen it from LINE.', 'moksa-line' ),
				array( 'status' => 401 )
			);
		}

		// The LIFF app belongs to the LINE Login channel, so that channel id is
		// the audience -- not the Messaging API one.
		$channel_id = (string) Options::get( 'channel_id' );

		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'id_token'  => $id_token,
					'client_id' => $channel_id,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::capture( $response, 'Could not verify a LIFF ID token', 'liff' );

			return new WP_Error(
				'moksa_line_liff_unreachable',
				__( 'Could not reach LINE to verify this session.', 'moksa-line' ),
				array( 'status' => 503 )
			);
		}

		$claims = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || empty( $claims['sub'] ) ) {
			return new WP_Error(
				'moksa_line_liff_invalid',
				__( 'This session could not be verified. Reopen the page from LINE.', 'moksa-line' ),
				array( 'status' => 401 )
			);
		}

		if ( '' !== $channel_id ) {
			$audience = isset( $claims['aud'] ) ? (array) $claims['aud'] : array();

			if ( ! in_array( $channel_id, $audience, true ) ) {
				return new WP_Error(
					'moksa_line_liff_wrong_channel',
					__( 'This session belongs to a different channel.', 'moksa-line' ),
					array( 'status' => 401 )
				);
			}
		}

		return $claims;
	}

	/**
	 * Simple per-identity throttle for LIFF chat.
	 *
	 * @param string $line_user_id LINE user id.
	 */
	private static function within_rate_limit( string $line_user_id ): bool {
		$key   = 'moksa_liff_rate_' . substr( hash( 'sha256', $line_user_id ), 0, 24 );
		$count = (int) get_transient( $key );

		if ( $count >= 20 ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	// --- Shortcodes ------------------------------------------------------------------

	/**
	 * A LIFF page that greets the visitor by name.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function shortcode_profile( $atts ): string {
		$atts = shortcode_atts(
			array(
				'liff_id' => (string) Options::get( 'liff_id' ),
			),
			(array) $atts,
			'moksa_liff_profile'
		);

		if ( '' === $atts['liff_id'] ) {
			return $this->notice( __( 'No LIFF ID has been configured yet.', 'moksa-line' ) );
		}

		$this->enqueue( $atts['liff_id'] );

		return '<div class="moksa-liff moksa-liff--profile" data-moksa-liff-profile>'
			. '<p class="moksa-liff__status">' . esc_html__( 'Connecting to LINE...', 'moksa-line' ) . '</p>'
			. '</div>';
	}

	/**
	 * A chat box that posts into the customer-service inbox.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function shortcode_chat( $atts ): string {
		$atts = shortcode_atts(
			array(
				'liff_id'     => (string) Options::get( 'liff_chat_id' ),
				'placeholder' => __( 'Type your message', 'moksa-line' ),
				'intro'       => __( 'Send us a message and we will reply in your LINE chat.', 'moksa-line' ),
			),
			(array) $atts,
			'moksa_line_chat'
		);

		if ( '' === $atts['liff_id'] ) {
			$atts['liff_id'] = (string) Options::get( 'liff_id' );
		}

		if ( '' === $atts['liff_id'] ) {
			return $this->notice( __( 'No LIFF ID has been configured yet.', 'moksa-line' ) );
		}

		$this->enqueue( $atts['liff_id'] );

		ob_start();
		?>
		<div class="moksa-liff moksa-liff--chat" data-moksa-liff-chat>
			<p class="moksa-liff__intro"><?php echo esc_html( $atts['intro'] ); ?></p>
			<p class="moksa-liff__status" data-moksa-chat-status><?php esc_html_e( 'Connecting to LINE...', 'moksa-line' ); ?></p>
			<form class="moksa-liff__form" data-moksa-chat-form hidden>
				<label class="screen-reader-text" for="moksa-chat-message"><?php esc_html_e( 'Your message', 'moksa-line' ); ?></label>
				<textarea id="moksa-chat-message" name="message" rows="3" required
					placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"></textarea>
				<button type="submit" class="moksa-liff__send"><?php esc_html_e( 'Send', 'moksa-line' ); ?></button>
			</form>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Load the LIFF SDK and the plugin's front-end script.
	 *
	 * @param string $liff_id LIFF app id.
	 */
	private function enqueue( string $liff_id ): void {
		// The LIFF SDK must come from LINE's own CDN -- a bundled copy is not
		// supported and would break whenever LINE changes the runtime. The
		// version is the plugin's rather than the SDK's, since LINE serves an
		// unversioned edge build; it exists only to make the URL cacheable.
		wp_enqueue_script( 'moksa-line-liff-sdk', self::SDK_URL, array(), MOKSA_LINE_VERSION, true );

		// Versioned by file time, like every other asset of this plugin. These
		// two carried the bare plugin version, which never changes between
		// releases -- so a browser that had front.css once kept it, and a fix
		// to the avatar's size shipped to nobody who had opened the page before.
		wp_enqueue_script(
			'moksa-line-liff',
			MOKSA_LINE_URL . 'assets/js/liff.js',
			array( 'moksa-line-liff-sdk' ),
			AdminModule::asset_version( 'assets/js/liff.js' ),
			true
		);

		// The front stylesheet is registered once, by the shortcode module, with
		// its own version. Enqueuing by handle uses that registration rather
		// than racing it with a second one under the same name.
		if ( ! wp_style_is( 'moksa-line-front', 'registered' ) ) {
			wp_register_style(
				'moksa-line-front',
				MOKSA_LINE_URL . 'assets/css/front.css',
				array(),
				AdminModule::asset_version( 'assets/css/front.css' )
			);
		}

		wp_enqueue_style( 'moksa-line-front' );

		wp_localize_script(
			'moksa-line-liff',
			'moksaLineLiff',
			array(
				'liffId'     => $liff_id,
				'sessionUrl' => rest_url( self::NAMESPACE_V1 . '/liff/session' ),
				'messageUrl' => rest_url( self::NAMESPACE_V1 . '/liff/message' ),
				'strings'    => array(
					/* translators: %s: the visitor's LINE display name. */
					'greeting'  => __( 'Hello, %s', 'moksa-line' ),
					'sending'   => __( 'Sending...', 'moksa-line' ),
					'sent'      => __( 'Sent. We will reply in your LINE chat.', 'moksa-line' ),
					'failed'    => __( 'That did not send. Please try again.', 'moksa-line' ),
					'notInLine' => __( 'Open this page from LINE to continue.', 'moksa-line' ),
				),
			)
		);
	}

	/**
	 * An admin-only notice rendered in place of a broken shortcode.
	 *
	 * @param string $message What is missing.
	 */
	private function notice( string $message ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return '<p class="moksa-liff__notice">' . esc_html( $message ) . '</p>';
	}
}
