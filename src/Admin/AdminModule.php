<?php
/**
 * Admin screens.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Admin;

use Moksa\Line\Bot\Ai\AiEngineProvider;
use Moksa\Line\Inbox\Conversations;
use Moksa\Line\Support\Migrator;
use Moksa\Line\Support\Options;

defined( 'ABSPATH' ) || exit;

class AdminModule {

	const SLUG = 'moksa-line';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_moksa_line_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );
		add_action( 'wp_ajax_moksa_line_broadcast', array( $this, 'ajax_broadcast' ) );
		add_filter( 'plugin_action_links_' . MOKSA_LINE_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Build the menu.
	 */
	public function register_menu(): void {
		$capability = 'manage_options';
		$unread     = Options::get( 'inbox_enabled' ) ? Conversations::unread_total() : 0;

		$inbox_label = __( 'Inbox', 'moksa-line-login' );

		if ( $unread > 0 ) {
			$inbox_label .= sprintf(
				' <span class="awaiting-mod"><span class="pending-count">%d</span></span>',
				$unread
			);
		}

		add_menu_page(
			__( 'LINE', 'moksa-line-login' ),
			__( 'LINE', 'moksa-line-login' ),
			$capability,
			self::SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-format-chat',
			58
		);

		$pages = array(
			array( self::SLUG, __( 'Dashboard', 'moksa-line-login' ), 'render_dashboard' ),
			array( self::SLUG . '-inbox', $inbox_label, 'render_inbox' ),
			array( self::SLUG . '-replies', __( 'Auto replies', 'moksa-line-login' ), 'render_replies' ),
			array( self::SLUG . '-flows', __( 'Conversation flows', 'moksa-line-login' ), 'render_flows' ),
			array( self::SLUG . '-flex', __( 'Flex messages', 'moksa-line-login' ), 'render_flex' ),
			array( self::SLUG . '-richmenus', __( 'Rich menus', 'moksa-line-login' ), 'render_richmenus' ),
			array( self::SLUG . '-broadcast', __( 'Broadcast', 'moksa-line-login' ), 'render_broadcast' ),
			array( self::SLUG . '-users', __( 'LINE users', 'moksa-line-login' ), 'render_users' ),
			array( self::SLUG . '-payments', __( 'Payments', 'moksa-line-login' ), 'render_payments' ),
			array( self::SLUG . '-logs', __( 'Logs', 'moksa-line-login' ), 'render_logs' ),
			array( self::SLUG . '-settings', __( 'Settings', 'moksa-line-login' ), 'render_settings' ),
		);

		foreach ( $pages as $page ) {
			list( $slug, $label, $callback ) = $page;

			add_submenu_page(
				self::SLUG,
				wp_strip_all_tags( $label ),
				$label,
				$capability,
				$slug,
				array( $this, $callback )
			);
		}
	}

	/**
	 * Load admin assets only on this plugin's screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ): void {
		// The profile screens need the script too, for the unlink button.
		$profile_screens = array( 'profile.php', 'user-edit.php' );

		if ( false === strpos( (string) $hook, self::SLUG ) && ! in_array( $hook, $profile_screens, true ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'moksa-line-admin',
			MOKSA_LINE_URL . 'assets/css/admin.css',
			array(),
			MOKSA_LINE_VERSION
		);

		wp_enqueue_script(
			'moksa-line-flex-renderer',
			MOKSA_LINE_URL . 'assets/js/moksa-flex-renderer.js',
			array( 'jquery' ),
			MOKSA_LINE_VERSION,
			true
		);

		wp_enqueue_script(
			'moksa-line-admin',
			MOKSA_LINE_URL . 'assets/js/admin.js',
			array( 'jquery', 'moksa-line-flex-renderer' ),
			MOKSA_LINE_VERSION,
			true
		);

		wp_localize_script(
			'moksa-line-admin',
			'moksaLine',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'moksa_line_admin' ),
				'strings' => array(
					'saved'        => __( 'Saved.', 'moksa-line-login' ),
					'failed'       => __( 'That did not work.', 'moksa-line-login' ),
					'confirmDelete' => __( 'Delete this permanently?', 'moksa-line-login' ),
					'publishing'   => __( 'Publishing to LINE...', 'moksa-line-login' ),
					'working'      => __( 'Working...', 'moksa-line-login' ),
				),
			)
		);
	}

	/**
	 * Quick links on the plugins list.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-settings' ) ),
				esc_html__( 'Settings', 'moksa-line-login' )
			)
		);

		return $links;
	}

	// --- Setup state --------------------------------------------------------------

	/**
	 * What still needs doing before the plugin is useful.
	 *
	 * @return array<int,array{done:bool,label:string,hint:string}>
	 */
	public static function checklist(): array {
		$items = array();

		$items[] = array(
			'done'  => '' !== (string) Options::get( 'channel_id' ) && '' !== (string) Options::get( 'channel_secret' ),
			'label' => __( 'LINE Login channel connected', 'moksa-line-login' ),
			'hint'  => __( 'Add the Channel ID and Channel Secret from the LINE Developers Console.', 'moksa-line-login' ),
		);

		$items[] = array(
			'done'  => \Moksa\Line\Line\TokenManager::is_configured(),
			'label' => __( 'Messaging API channel connected', 'moksa-line-login' ),
			'hint'  => __( 'The bot cannot send or receive anything until this is set.', 'moksa-line-login' ),
		);

		$items[] = array(
			'done'  => ! \Moksa\Line\Line\Signature::using_fallback_secret(),
			'label' => __( 'Webhook signing secret is the Messaging API one', 'moksa-line-login' ),
			'hint'  => __( 'Webhooks are signed with the Messaging API channel secret. Falling back to the Login channel secret only works when both channels are the same, which is unusual.', 'moksa-line-login' ),
		);

		$items[] = array(
			'done'  => \Moksa\Line\Webhook\EventQueue::has_any(),
			'label' => __( 'Webhook has received an event', 'moksa-line-login' ),
			'hint'  => __( 'Paste the webhook URL into the Console, enable "Use webhook", then press Verify.', 'moksa-line-login' ),
		);

		if ( Options::get( 'ai_enabled' ) ) {
			$provider = new AiEngineProvider();

			$items[] = array(
				'done'  => $provider->is_available(),
				'label' => __( 'AI provider available', 'moksa-line-login' ),
				'hint'  => __( 'AI replies are switched on, but AI Engine is not active on this site.', 'moksa-line-login' ),
			);
		}

		if ( Options::get( 'pay_enabled' ) ) {
			$items[] = array(
				'done'  => \Moksa\Line\Pay\LinePayClient::is_configured(),
				'label' => __( 'LINE Pay credentials present', 'moksa-line-login' ),
				'hint'  => __( 'Add the LINE Pay Channel ID and Channel Secret.', 'moksa-line-login' ),
			);

			$items[] = array(
				'done'  => ! Options::get( 'pay_sandbox' ),
				'label' => __( 'LINE Pay is in production mode', 'moksa-line-login' ),
				'hint'  => __( 'Sandbox mode is on, so no real money moves. Turn it off when you go live.', 'moksa-line-login' ),
			);
		}

		if ( ! \Moksa\Line\Support\Crypto::available() ) {
			$items[] = array(
				'done'  => false,
				'label' => __( 'Credentials are encrypted at rest', 'moksa-line-login' ),
				'hint'  => __( 'OpenSSL with AES-256-GCM is not available on this server, so channel secrets are stored as plain text.', 'moksa-line-login' ),
			);
		}

		return $items;
	}

	/**
	 * Nudge on other admin screens while setup is incomplete.
	 */
	public function setup_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( $screen && false !== strpos( (string) $screen->id, self::SLUG ) ) {
			return;
		}

		// Only nag about the two things that make the plugin do nothing at all.
		$login     = '' !== (string) Options::get( 'channel_id' );
		$messaging = \Moksa\Line\Line\TokenManager::is_configured();

		if ( $login || $messaging ) {
			return;
		}

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Moksa LINE Suite is installed but not connected to a LINE channel yet.', 'moksa-line-login' ),
			esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-settings' ) ),
			esc_html__( 'Finish setup', 'moksa-line-login' )
		);
	}

	// --- Settings ---------------------------------------------------------------------

	/**
	 * Persist the settings form.
	 */
	public function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'moksa-line-login' ), 403 );
		}

		check_admin_referer( 'moksa_line_settings' );

		$tab      = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';
		$schema   = Options::schema();
		$submitted = isset( $_POST['moksa_line'] ) && is_array( $_POST['moksa_line'] )
			? wp_unslash( $_POST['moksa_line'] )
			: array();

		foreach ( $submitted as $key => $value ) {
			if ( ! isset( $schema[ $key ] ) || 'db_version' === $key ) {
				continue;
			}

			// An untouched secret field posts back the mask, which must not
			// overwrite the real credential.
			if ( ! empty( $schema[ $key ]['secret'] ) && ( '' === $value || false !== strpos( (string) $value, "\xE2\x80\xA2" ) ) ) {
				continue;
			}

			Options::set( $key, $value );
		}

		// Unchecked checkboxes are absent from the POST, so every boolean on
		// the submitted tab is explicitly set to false when missing.
		$booleans = isset( $_POST['moksa_line_booleans'] ) ? (array) wp_unslash( $_POST['moksa_line_booleans'] ) : array();

		foreach ( $booleans as $key ) {
			$key = sanitize_key( $key );

			if ( isset( $schema[ $key ] ) && 'bool' === $schema[ $key ]['type'] && ! isset( $submitted[ $key ] ) ) {
				Options::set( $key, false );
			}
		}

		Options::flush_cache();

		// A changed messaging secret invalidates any cached token.
		\Moksa\Line\Line\TokenManager::forget();

		Migrator::maybe_upgrade();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::SLUG . '-settings',
					'tab'     => $tab,
					'updated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// --- Broadcast -------------------------------------------------------------------------

	/**
	 * Send a message to everyone, a segment, or a single test recipient.
	 *
	 * Broadcasting is billed per recipient and cannot be recalled, so the
	 * only unguarded path here is the test send.
	 */
	public function ajax_broadcast(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot send broadcasts.', 'moksa-line-login' ) ), 403 );
		}

		$mode     = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'test';
		$body     = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$flex_id  = isset( $_POST['flex_id'] ) ? (int) $_POST['flex_id'] : 0;
		$target   = isset( $_POST['line_user_id'] ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';

		$messages = array();

		if ( $flex_id > 0 ) {
			$template = \Moksa\Line\Data\Flex::find( $flex_id );
			$contents = \Moksa\Line\Data\Flex::contents( $flex_id );

			if ( ! $template || ! $contents ) {
				wp_send_json_error( array( 'message' => __( 'That template could not be loaded.', 'moksa-line-login' ) ) );
			}

			$messages[] = \Moksa\Line\Line\MessagingClient::flex( (string) $template->alt_text, $contents );
		}

		if ( '' !== trim( $body ) ) {
			array_unshift( $messages, \Moksa\Line\Line\MessagingClient::text( $body ) );
		}

		if ( empty( $messages ) ) {
			wp_send_json_error( array( 'message' => __( 'Write a message or choose a template first.', 'moksa-line-login' ) ) );
		}

		if ( 'test' === $mode ) {
			if ( '' === $target ) {
				wp_send_json_error( array( 'message' => __( 'Enter a LINE user id to send the test to.', 'moksa-line-login' ) ) );
			}

			$result = \Moksa\Line\Line\MessagingClient::push( $target, $messages );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( array( 'message' => __( 'Test sent.', 'moksa-line-login' ) ) );
		}

		// Sending to everyone needs the operator to type the confirmation word,
		// because there is no undo and it costs one message per friend.
		$confirmation = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';

		if ( 'SEND' !== strtoupper( $confirmation ) ) {
			wp_send_json_error( array( 'message' => __( 'Type SEND in the confirmation box to go ahead.', 'moksa-line-login' ) ) );
		}

		if ( 'all' === $mode ) {
			$result = \Moksa\Line\Line\MessagingClient::broadcast( $messages );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( array( 'message' => __( 'Broadcast sent to every friend of the account.', 'moksa-line-login' ) ) );
		}

		// 'known' sends only to the friends this site has actually recorded,
		// which is a smaller and more predictable bill than a full broadcast.
		$recipients = \Moksa\Line\Data\Users::friend_ids();

		if ( empty( $recipients ) ) {
			wp_send_json_error( array( 'message' => __( 'No friends have been recorded on this site yet.', 'moksa-line-login' ) ) );
		}

		$report = \Moksa\Line\Line\MessagingClient::multicast( $recipients, $messages );

		if ( $report['failed'] > 0 ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: delivered count, 2: failed count, 3: first error. */
						__( 'Delivered to %1$d, failed for %2$d. %3$s', 'moksa-line-login' ),
						$report['sent'],
						$report['failed'],
						isset( $report['errors'][0] ) ? $report['errors'][0] : ''
					),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of recipients. */
					__( 'Sent to %d people.', 'moksa-line-login' ),
					$report['sent']
				),
			)
		);
	}

	// --- Rendering ----------------------------------------------------------------------

	public function render_dashboard(): void {
		$this->view( 'dashboard' );
	}

	public function render_inbox(): void {
		$this->view( 'inbox' );
	}

	public function render_replies(): void {
		$this->view( 'replies' );
	}

	public function render_flows(): void {
		$this->view( 'flows' );
	}

	public function render_flex(): void {
		$this->view( 'flex' );
	}

	public function render_richmenus(): void {
		$this->view( 'richmenus' );
	}

	public function render_broadcast(): void {
		$this->view( 'broadcast' );
	}

	public function render_users(): void {
		$this->view( 'users' );
	}

	public function render_payments(): void {
		$this->view( 'payments' );
	}

	public function render_logs(): void {
		$this->view( 'logs' );
	}

	public function render_settings(): void {
		$this->view( 'settings' );
	}

	/**
	 * Include a view file.
	 *
	 * @param string $name View name under views/.
	 */
	private function view( string $name ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'moksa-line-login' ), 403 );
		}

		$path = MOKSA_LINE_DIR . 'views/' . $name . '.php';

		if ( ! is_readable( $path ) ) {
			printf( '<div class="wrap"><p>%s</p></div>', esc_html__( 'This screen is missing.', 'moksa-line-login' ) );

			return;
		}

		include $path;
	}
}
