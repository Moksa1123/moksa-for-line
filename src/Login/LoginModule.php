<?php
/**
 * LINE Login: authorization request, callback, account linking.
 *
 * What changed from 1.x, and why:
 *
 * - The transient key used to be smuggled through the OIDC `nonce` parameter,
 *   which meant the nonce could not do its actual job (replay protection) and
 *   an attacker could hand a victim a link with any nonce they liked. State
 *   and nonce are now separate, both random, and the transient is keyed by a
 *   hash of the state.
 * - The ID token was never verified; identity came from /v2/profile. Identity
 *   now comes from the `sub` claim of a token LINE has verified.
 * - The post-login redirect was taken from the transient unvalidated. It now
 *   goes through wp_validate_redirect, so a stored off-site URL cannot turn
 *   the callback into an open redirect.
 * - PKCE is used, so an intercepted authorization code is not enough on its own.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Login;

use Moksa\Line\Data\Users;
use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;
use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || exit;

class LoginModule {

	const AUTHORIZE_URL = 'https://access.line.me/oauth2/v2.1/authorize';
	const TOKEN_URL     = 'https://api.line.me/oauth2/v2.1/token';
	const PROFILE_URL   = 'https://api.line.me/v2/profile';
	const REVOKE_URL    = 'https://api.line.me/oauth2/v2.1/revoke';

	/** Login attempts are abandoned after ten minutes. */
	const STATE_TTL = 600;

	/**
	 * Hook the module into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_ajax_nopriv_moksa_line_callback', array( $this, 'handle_callback' ) );
		add_action( 'wp_ajax_moksa_line_callback', array( $this, 'handle_callback' ) );

		add_action( 'wp_ajax_nopriv_moksa_line_start', array( $this, 'handle_start' ) );
		add_action( 'wp_ajax_moksa_line_start', array( $this, 'handle_start' ) );

		add_action( 'wp_ajax_moksa_line_unlink', array( $this, 'handle_unlink' ) );

		add_filter( 'get_avatar_url', array( $this, 'filter_avatar_url' ), 10, 2 );
		add_action( 'delete_user', array( $this, 'on_user_deleted' ) );
	}

	// --- Authorization request ------------------------------------------------

	/**
	 * The URL LINE redirects back to. Unchanged from 1.x on purpose: it is
	 * registered in the LINE Console and changing it would break every
	 * existing install until an administrator edited the channel.
	 */
	public static function callback_url(): string {
		return admin_url( 'admin-ajax.php?action=moksa_line_callback' );
	}

	/**
	 * Build an authorization URL and stash the matching state server-side.
	 *
	 * @param string $redirect_to Where to send the user afterwards.
	 * @param array  $args        link (bool) to bind rather than log in.
	 * @return string Authorization URL, or '#' when the channel is unconfigured.
	 */
	public static function authorize_url( string $redirect_to = '', array $args = array() ): string {
		$channel_id = (string) Options::get( 'channel_id' );

		if ( '' === $channel_id ) {
			return '#';
		}

		$state         = wp_generate_password( 40, false, false );
		$nonce         = wp_generate_password( 40, false, false );
		$code_verifier = self::random_verifier();

		if ( '' === $redirect_to ) {
			$configured  = (string) Options::get( 'login_redirect' );
			$redirect_to = '' !== $configured ? $configured : self::current_url();
		}

		set_transient(
			self::state_key( $state ),
			array(
				'nonce'         => $nonce,
				'code_verifier' => $code_verifier,
				'redirect'      => $redirect_to,
				'link_to'       => ! empty( $args['link'] ) ? get_current_user_id() : 0,
				'created'       => time(),
			),
			self::STATE_TTL
		);

		$scope = array( 'openid', 'profile' );

		if ( Options::get( 'request_email' ) ) {
			$scope[] = 'email';
		}

		$params = array(
			'response_type'         => 'code',
			'client_id'             => $channel_id,
			'redirect_uri'          => self::callback_url(),
			'state'                 => $state,
			'scope'                 => implode( ' ', $scope ),
			'nonce'                 => $nonce,
			'code_challenge'        => self::code_challenge( $code_verifier ),
			'code_challenge_method' => 'S256',
		);

		$bot_prompt = (string) Options::get( 'bot_prompt' );

		if ( 'none' !== $bot_prompt ) {
			$params['bot_prompt'] = $bot_prompt;
		}

		return self::AUTHORIZE_URL . '?' . http_build_query( $params );
	}

	/**
	 * Front-end entry point: /wp-admin/admin-ajax.php?action=moksa_line_start
	 * Sends the visitor to LINE. Having a server-side starter means the state
	 * is created at click time, not at page render time, so a cached page can
	 * still produce a valid login.
	 */
	public function handle_start(): void {
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
		$link     = ! empty( $_GET['link'] ) && is_user_logged_in();

		if ( $link && ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'moksa_line_link' ) ) {
			wp_die( esc_html__( 'This link expired. Please go back and try again.', 'moksa-line' ), 403 );
		}

		$url = self::authorize_url( $redirect, array( 'link' => $link ) );

		if ( '#' === $url ) {
			wp_die( esc_html__( 'LINE Login is not configured on this site yet.', 'moksa-line' ) );
		}

		wp_redirect( $url );
		exit;
	}

	// --- Callback ---------------------------------------------------------------

	/**
	 * Handle LINE's redirect back to the site.
	 */
	public function handle_callback(): void {
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( '' === $state ) {
			$this->fail( __( 'This login request is missing its state. Please start again.', 'moksa-line' ) );
		}

		$stored = get_transient( self::state_key( $state ) );

		// Consuming the state immediately makes the code single-use even if the
		// user reloads the callback URL.
		delete_transient( self::state_key( $state ) );

		if ( ! is_array( $stored ) ) {
			$this->fail( __( 'This login link has expired. Please try again.', 'moksa-line' ) );
		}

		// LINE reports user-side cancellation as an error parameter.
		if ( isset( $_GET['error'] ) ) {
			$description = isset( $_GET['error_description'] )
				? sanitize_text_field( wp_unslash( $_GET['error_description'] ) )
				: sanitize_text_field( wp_unslash( $_GET['error'] ) );

			Logger::info( 'LINE login was not completed', array( 'detail' => $description ), 'login' );

			wp_safe_redirect( $this->safe_redirect( $stored['redirect'] ?? '' ) );
			exit;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		if ( '' === $code ) {
			$this->fail( __( 'LINE did not return an authorization code.', 'moksa-line' ) );
		}

		$tokens = $this->exchange_code( $code, (string) ( $stored['code_verifier'] ?? '' ) );

		if ( is_wp_error( $tokens ) ) {
			$this->fail( $tokens->get_error_message() );
		}

		$claims = IdToken::verify(
			(string) ( $tokens['id_token'] ?? '' ),
			(string) ( $stored['nonce'] ?? '' )
		);

		if ( is_wp_error( $claims ) ) {
			$this->fail( $claims->get_error_message() );
		}

		$line_user_id = (string) $claims['sub'];

		// The ID token carries name/picture only for some scope combinations,
		// so top up from the profile endpoint when they are missing.
		$profile = $this->build_profile( $claims, (string) ( $tokens['access_token'] ?? '' ) );

		$link_to = (int) ( $stored['link_to'] ?? 0 );

		$user_id = $link_to > 0
			? $this->link_existing_account( $link_to, $line_user_id, $profile )
			: $this->resolve_user( $line_user_id, $profile );

		if ( is_wp_error( $user_id ) ) {
			$this->fail( $user_id->get_error_message() );
		}

		Users::upsert(
			$line_user_id,
			array(
				'wp_user_id'     => $user_id,
				'display_name'   => $profile['displayName'],
				'picture_url'    => $profile['pictureUrl'],
				'status_message' => $profile['statusMessage'],
				'email'          => $profile['email'],
				'last_login_at'  => current_time( 'mysql', true ),
			)
		);

		if ( '' !== $profile['pictureUrl'] ) {
			update_user_meta( $user_id, 'moksa_line_avatar', $profile['pictureUrl'] );
		}

		update_user_meta( $user_id, 'moksa_line_user_id', $line_user_id );

		if ( get_current_user_id() !== $user_id ) {
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );

			$user = get_userdata( $user_id );

			if ( $user instanceof WP_User ) {
				do_action( 'wp_login', $user->user_login, $user );
			}
		}

		/**
		 * Fires after a successful LINE login or account link.
		 *
		 * @param int    $user_id      WordPress user id.
		 * @param string $line_user_id LINE user id.
		 * @param array  $profile      Normalised profile data.
		 */
		do_action( 'moksa_line_logged_in', $user_id, $line_user_id, $profile );

		wp_safe_redirect( $this->safe_redirect( $stored['redirect'] ?? '' ) );
		exit;
	}

	/**
	 * Trade the authorization code for tokens.
	 *
	 * @param string $code          Authorization code.
	 * @param string $code_verifier PKCE verifier.
	 * @return array|WP_Error
	 */
	private function exchange_code( string $code, string $code_verifier ) {
		$body = array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => self::callback_url(),
			'client_id'     => (string) Options::get( 'channel_id' ),
			'client_secret' => (string) Options::get( 'channel_secret' ),
		);

		if ( '' !== $code_verifier ) {
			$body['code_verifier'] = $code_verifier;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::capture( $response, 'Token exchange could not reach LINE', 'login' );

			return new WP_Error(
				'moksa_line_token_unreachable',
				__( 'Could not reach LINE to complete the login. Please try again.', 'moksa-line' )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || empty( $data['access_token'] ) ) {
			$detail = is_array( $data ) && isset( $data['error_description'] )
				? (string) $data['error_description']
				: 'HTTP ' . $status;

			Logger::error( 'Token exchange rejected', array( 'detail' => $detail ), 'login' );

			// The page this lands on is the one a customer is looking at, and
			// telling them to check a Channel Secret is both useless to them
			// and a description of our own setup. The detail is in the log
			// above, which is where the person who can act on it will look.
			return new WP_Error(
				'moksa_line_token_rejected',
				__( 'LINE could not complete this login. Please try again.', 'moksa-line' )
			);
		}

		return $data;
	}

	/**
	 * Merge ID token claims with the profile endpoint.
	 *
	 * @param array  $claims       Verified claims.
	 * @param string $access_token User access token.
	 * @return array{displayName:string,pictureUrl:string,statusMessage:string,email:string}
	 */
	private function build_profile( array $claims, string $access_token ): array {
		$profile = array(
			'displayName'   => isset( $claims['name'] ) ? (string) $claims['name'] : '',
			'pictureUrl'    => isset( $claims['picture'] ) ? (string) $claims['picture'] : '',
			'statusMessage' => '',
			'email'         => isset( $claims['email'] ) ? sanitize_email( (string) $claims['email'] ) : '',
		);

		if ( '' !== $profile['displayName'] && '' !== $profile['pictureUrl'] ) {
			return $profile;
		}

		if ( '' === $access_token ) {
			return $profile;
		}

		$response = wp_remote_get(
			self::PROFILE_URL,
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $profile;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return $profile;
		}

		foreach ( array( 'displayName', 'pictureUrl', 'statusMessage' ) as $key ) {
			if ( '' === $profile[ $key ] && ! empty( $data[ $key ] ) ) {
				$profile[ $key ] = sanitize_text_field( (string) $data[ $key ] );
			}
		}

		return $profile;
	}

	// --- Account resolution --------------------------------------------------

	/**
	 * Find or create the WordPress account for a LINE identity.
	 *
	 * @param string $line_user_id LINE user id.
	 * @param array  $profile      Normalised profile.
	 * @return int|WP_Error
	 */
	private function resolve_user( string $line_user_id, array $profile ) {
		$record = Users::by_line_id( $line_user_id );

		if ( $record && (int) $record->wp_user_id > 0 ) {
			$existing = get_userdata( (int) $record->wp_user_id );

			if ( $existing instanceof WP_User ) {
				if ( Options::get( 'sync_profile' ) ) {
					$this->sync_profile( $existing->ID, $profile );
				}

				return $existing->ID;
			}

			// The WordPress account was deleted underneath us; 1.x would have
			// called wp_set_auth_cookie() with a dead id and logged nobody in.
			Users::upsert( $line_user_id, array( 'wp_user_id' => 0 ) );
		}

		// Optional, off by default: match an existing account by email. Anyone
		// who controls a LINE account with that address would otherwise inherit
		// the WordPress account.
		if ( Options::get( 'link_by_email' ) && '' !== $profile['email'] ) {
			$by_email = get_user_by( 'email', $profile['email'] );

			if ( $by_email instanceof WP_User ) {
				Users::link( $line_user_id, $by_email->ID );

				return $by_email->ID;
			}
		}

		if ( ! Options::get( 'auto_register' ) ) {
			return new WP_Error(
				'moksa_line_registration_closed',
				__( 'This site is not accepting new registrations through LINE. Please sign in with your existing account first, then link LINE from your profile.', 'moksa-line' )
			);
		}

		return $this->create_user( $line_user_id, $profile );
	}

	/**
	 * Create a WordPress account for a first-time LINE user.
	 *
	 * @param string $line_user_id LINE user id.
	 * @param array  $profile      Normalised profile.
	 * @return int|WP_Error
	 */
	private function create_user( string $line_user_id, array $profile ) {
		$username = 'line_' . substr( preg_replace( '/[^A-Za-z0-9]/', '', $line_user_id ), 0, 24 );
		$suffix   = 1;
		$base     = $username;

		while ( username_exists( $username ) ) {
			$username = $base . '_' . $suffix;
			++$suffix;
		}

		$email = $profile['email'];

		if ( '' === $email || email_exists( $email ) ) {
			// A unique placeholder on the site's own domain: @line.local looks
			// deliverable and is not, which breaks password resets silently.
			$host  = wp_parse_url( home_url(), PHP_URL_HOST );
			$email = $username . '@line-user.' . ( $host ? $host : 'invalid' );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'user_email'   => $email,
				'display_name' => '' !== $profile['displayName'] ? $profile['displayName'] : $username,
				'nickname'     => '' !== $profile['displayName'] ? $profile['displayName'] : $username,
				'first_name'   => $profile['displayName'],
				'role'         => $this->new_user_role(),
			)
		);

		if ( is_wp_error( $user_id ) ) {
			Logger::capture( $user_id, 'Could not create a WordPress account for a LINE user', 'login' );

			return $user_id;
		}

		Users::link( $line_user_id, (int) $user_id );

		/**
		 * Fires after a WordPress account is created from a LINE login.
		 *
		 * @param int    $user_id      New user id.
		 * @param string $line_user_id LINE user id.
		 * @param array  $profile      Normalised profile.
		 */
		do_action( 'moksa_line_user_registered', (int) $user_id, $line_user_id, $profile );

		return (int) $user_id;
	}

	/**
	 * Bind a LINE identity to the account the visitor is already signed into.
	 *
	 * @param int    $wp_user_id   Signed-in user.
	 * @param string $line_user_id LINE user id.
	 * @param array  $profile      Normalised profile.
	 * @return int|WP_Error
	 */
	private function link_existing_account( int $wp_user_id, string $line_user_id, array $profile ) {
		if ( ! get_userdata( $wp_user_id ) ) {
			return new WP_Error(
				'moksa_line_link_target_missing',
				__( 'The account to link to no longer exists.', 'moksa-line' )
			);
		}

		$record = Users::by_line_id( $line_user_id );

		if ( $record && (int) $record->wp_user_id > 0 && (int) $record->wp_user_id !== $wp_user_id ) {
			return new WP_Error(
				'moksa_line_already_linked',
				__( 'This LINE account is already linked to another user on this site.', 'moksa-line' )
			);
		}

		Users::link( $line_user_id, $wp_user_id );

		if ( Options::get( 'sync_profile' ) ) {
			$this->sync_profile( $wp_user_id, $profile );
		}

		return $wp_user_id;
	}

	/**
	 * Copy the LINE display name onto the WordPress profile.
	 *
	 * The email is deliberately not synced: changing a user's email address
	 * as a side effect of signing in is a foothold for account takeover.
	 *
	 * @param int   $user_id WordPress user id.
	 * @param array $profile Normalised profile.
	 */
	private function sync_profile( int $user_id, array $profile ): void {
		if ( '' === $profile['displayName'] ) {
			return;
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $profile['displayName'],
				'nickname'     => $profile['displayName'],
			)
		);
	}

	/**
	 * Role for newly registered users, falling back to the site default.
	 */
	private function new_user_role(): string {
		$role  = (string) Options::get( 'new_user_role' );
		$roles = wp_roles()->get_names();

		if ( isset( $roles[ $role ] ) && 'administrator' !== $role ) {
			return $role;
		}

		$default = (string) get_option( 'default_role', 'subscriber' );

		return isset( $roles[ $default ] ) ? $default : 'subscriber';
	}

	// --- Unlink and cleanup ---------------------------------------------------

	/**
	 * Let a signed-in user detach their LINE account.
	 */
	public function handle_unlink(): void {
		check_ajax_referer( 'moksa_line_link', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You are not signed in.', 'moksa-line' ) ), 403 );
		}

		$target = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : $user_id;

		if ( $target !== $user_id && ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot unlink another user.', 'moksa-line' ) ), 403 );
		}

		Users::unlink( $target );

		wp_send_json_success( array( 'message' => __( 'LINE account unlinked.', 'moksa-line' ) ) );
	}

	/**
	 * Release the binding when a WordPress user is deleted, so the LINE id can
	 * be reused rather than pointing at a dead account forever.
	 *
	 * @param int $user_id Deleted user.
	 */
	public function on_user_deleted( $user_id ): void {
		Users::unlink( (int) $user_id );
	}

	/**
	 * Serve the LINE profile picture as the user's avatar.
	 *
	 * @param string $url  Existing avatar URL.
	 * @param mixed  $id_or_email User identifier.
	 * @return string
	 */
	public function filter_avatar_url( $url, $id_or_email ) {
		$user_id = 0;

		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_id = $id_or_email->ID;
		} elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) {
			$user_id = (int) $id_or_email->user_id;
		} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user  = get_user_by( 'email', $id_or_email );
			$user_id = $user ? $user->ID : 0;
		}

		if ( ! $user_id ) {
			return $url;
		}

		$avatar = get_user_meta( $user_id, 'moksa_line_avatar', true );

		return $avatar ? esc_url_raw( $avatar ) : $url;
	}

	// --- Helpers ---------------------------------------------------------------

	/**
	 * Transient key for a state value. The state itself is never used as the
	 * key so a very long state cannot overflow the option name column.
	 *
	 * @param string $state State value.
	 */
	private static function state_key( string $state ): string {
		return 'mlline_' . hash( 'sha256', $state );
	}

	/**
	 * PKCE verifier: 43-128 characters from the unreserved set.
	 */
	private static function random_verifier(): string {
		return rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' );
	}

	/**
	 * S256 challenge for a verifier.
	 *
	 * @param string $verifier PKCE verifier.
	 */
	private static function code_challenge( string $verifier ): string {
		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	}

	/**
	 * Constrain a stored redirect to this site.
	 *
	 * @param string $url Requested destination.
	 */
	private function safe_redirect( string $url ): string {
		return wp_validate_redirect( $url, home_url() );
	}

	/**
	 * Best guess at the page the visitor came from.
	 */
	private static function current_url(): string {
		$referer = wp_get_referer();

		return $referer ? $referer : home_url();
	}

	/**
	 * Abort with a message the visitor can act on, and a link back.
	 *
	 * @param string $message Reason.
	 */
	private function fail( string $message ): void {
		wp_die(
			esc_html( $message ),
			esc_html__( 'LINE Login', 'moksa-line' ),
			array(
				'response'  => 400,
				'back_link' => true,
			)
		);
	}
}
