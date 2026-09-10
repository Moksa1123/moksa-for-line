<?php
/**
 * Admin screens.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Admin;

use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Bot\Ai\Providers;
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
		add_action( 'wp_ajax_moksa_line_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_moksa_line_webhook_check', array( $this, 'ajax_webhook_check' ) );
		add_action( 'wp_ajax_moksa_line_webhook_set', array( $this, 'ajax_webhook_set' ) );
		add_action( 'wp_ajax_moksa_line_resend_notification', array( $this, 'ajax_resend_notification' ) );

		add_filter( 'parent_file', array( $this, 'keep_menu_open' ) );
		add_filter( 'plugin_action_links_' . MOKSA_LINE_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Build the menu.
	 */
	public function register_menu(): void {
		$capability = 'manage_options';
		$unread     = Options::get( 'inbox_enabled' ) ? Conversations::unread_total() : 0;

		$inbox_label = __( 'Inbox', 'moksa-line' );

		if ( $unread > 0 ) {
			$inbox_label .= sprintf(
				' <span class="awaiting-mod"><span class="pending-count">%d</span></span>',
				$unread
			);
		}

		add_menu_page(
			__( 'LINE', 'moksa-line' ),
			__( 'LINE', 'moksa-line' ),
			$capability,
			self::SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-format-chat',
			58
		);

		$pages = array(
			array( self::SLUG, __( 'Dashboard', 'moksa-line' ), 'render_dashboard' ),
			array( self::SLUG . '-inbox', $inbox_label, 'render_inbox' ),
			array( self::SLUG . '-replies', __( 'Auto replies', 'moksa-line' ), 'render_replies' ),
			array( self::SLUG . '-flows', __( 'Conversation flows', 'moksa-line' ), 'render_flows' ),
			array( self::SLUG . '-flex', __( 'Flex messages', 'moksa-line' ), 'render_flex' ),
			array( self::SLUG . '-richmenus', __( 'Rich menus', 'moksa-line' ), 'render_richmenus' ),
			array( self::SLUG . '-imagemaps', __( 'Imagemaps', 'moksa-line' ), 'render_imagemaps' ),
			array( self::SLUG . '-templates', __( 'Template messages', 'moksa-line' ), 'render_templates' ),
			array( self::SLUG . '-broadcast', __( 'Broadcast', 'moksa-line' ), 'render_broadcast' ),
			array( self::SLUG . '-users', __( 'LINE users', 'moksa-line' ), 'render_users' ),
			array( self::SLUG . '-notifications', __( 'Order notifications', 'moksa-line' ), 'render_notifications' ),
			array( self::SLUG . '-payments', __( 'Payments', 'moksa-line' ), 'render_payments' ),
			array( self::SLUG . '-logs', __( 'Logs', 'moksa-line' ), 'render_logs' ),
			array( self::SLUG . '-settings', __( 'Settings', 'moksa-line' ), 'render_settings' ),
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

		// The notification template list is a post type screen, placed by hand
		// so it sits next to the history screen rather than wherever the post
		// type happened to be registered, and named distinctly from it.
		if ( class_exists( 'WooCommerce' ) ) {
			add_submenu_page(
				self::SLUG,
				__( 'Notification templates', 'moksa-line' ),
				__( 'Notification templates', 'moksa-line' ),
				'manage_woocommerce',
				'edit.php?post_type=' . \Moksa\Line\Woo\NotifyTemplates::POST_TYPE
			);
		}
	}

	/**
	 * Keep the LINE menu highlighted while editing a notification template.
	 *
	 * Without this, editing a template collapses the LINE menu and highlights
	 * Posts, because the screen is technically a post type editor.
	 *
	 * @param string $parent Current parent menu slug.
	 * @return string
	 */
	public function keep_menu_open( $parent ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && \Moksa\Line\Woo\NotifyTemplates::POST_TYPE === $screen->post_type ) {
			return self::SLUG;
		}

		return $parent;
	}

	/**
	 * Load admin assets only on this plugin's screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ): void {
		// The profile screens need the script too, for the unlink button, and
		// so does the notification template editor, which is a post type screen
		// rather than one of this plugin's own pages.
		$profile_screens = array( 'profile.php', 'user-edit.php' );
		$screen          = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_template     = $screen && \Moksa\Line\Woo\NotifyTemplates::POST_TYPE === $screen->post_type;

		if ( false === strpos( (string) $hook, self::SLUG )
			&& ! in_array( $hook, $profile_screens, true )
			&& ! $is_template ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'moksa-line-admin',
			MOKSA_LINE_URL . 'assets/css/admin.css',
			array(),
			self::asset_version( 'assets/css/admin.css' )
		);

		// The front-end sheet is registered on wp_enqueue_scripts, which never
		// fires in admin, so enqueuing it by handle here silently did nothing
		// and the login button preview rendered as unstyled text. Register it
		// again for admin rather than relying on a handle from another context.
		if ( ! wp_style_is( 'moksa-line-front', 'registered' ) ) {
			wp_register_style(
				'moksa-line-front',
				MOKSA_LINE_URL . 'assets/css/front.css',
				array(),
				self::asset_version( 'assets/css/front.css' )
			);
		}

		wp_enqueue_script(
			'moksa-line-flex-renderer',
			MOKSA_LINE_URL . 'assets/js/moksa-flex-renderer.js',
			array( 'jquery' ),
			self::asset_version( 'assets/js/moksa-flex-renderer.js' ),
			true
		);

		wp_enqueue_script(
			'moksa-line-richmenu-editor',
			MOKSA_LINE_URL . 'assets/js/richmenu-editor.js',
			array( 'jquery' ),
			self::asset_version( 'assets/js/richmenu-editor.js' ),
			true
		);

		wp_enqueue_script(
			'moksa-line-card-editor',
			MOKSA_LINE_URL . 'assets/js/card-editor.js',
			array( 'jquery' ),
			self::asset_version( 'assets/js/card-editor.js' ),
			true
		);

		wp_enqueue_script(
			'moksa-line-template-editor',
			MOKSA_LINE_URL . 'assets/js/template-editor.js',
			array( 'jquery' ),
			self::asset_version( 'assets/js/template-editor.js' ),
			true
		);

		wp_enqueue_script(
			'moksa-line-flow-editor',
			MOKSA_LINE_URL . 'assets/js/flow-editor.js',
			array( 'jquery' ),
			self::asset_version( 'assets/js/flow-editor.js' ),
			true
		);

		wp_enqueue_script(
			'moksa-line-admin',
			MOKSA_LINE_URL . 'assets/js/admin.js',
			array( 'jquery', 'moksa-line-flex-renderer', 'moksa-line-richmenu-editor', 'moksa-line-flow-editor', 'moksa-line-card-editor', 'moksa-line-template-editor' ),
			self::asset_version( 'assets/js/admin.js' ),
			true
		);

		wp_localize_script(
			'moksa-line-admin',
			'moksaLine',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'siteName' => get_bloginfo( 'name' ),
				'siteUrl'  => home_url(),
				'nonce'   => wp_create_nonce( 'moksa_line_admin' ),
				'strings' => array(
					'saved'        => __( 'Saved.', 'moksa-line' ),
					'copied'       => __( 'Copied', 'moksa-line' ),
					'templateButton' => __( 'Button', 'moksa-line' ),
					'templateYes'  => __( 'Yes', 'moksa-line' ),
					'templateNo'   => __( 'No', 'moksa-line' ),
					'templateQuestion' => __( 'The question', 'moksa-line' ),
					'templateText' => __( 'Text', 'moksa-line' ),
					'templateAddButton' => __( '+ Add a button', 'moksa-line' ),
					'templateTooMany' => __( 'A carousel holds at most 10 cards.', 'moksa-line' ),
					'templateLastCard' => __( 'A template needs at least one card.', 'moksa-line' ),
					'confirmSendSticker' => __( 'Send this sticker? It cannot be unsent, and it is billed like any other message.', 'moksa-line' ),
					// The inbox updates a conversation's state without reloading,
					// so it needs the same words the server-rendered pill uses.
					'conversationStatus' => array(
						'bot'    => __( 'bot', 'moksa-line' ),
						'human'  => __( 'human', 'moksa-line' ),
						'closed' => __( 'closed', 'moksa-line' ),
					),
					/* translators: %d: number of cards in the carousel. */
					'carouselCount' => __( '%d cards. The customer swipes sideways to reach the rest.', 'moksa-line' ),
					'cardTitle'    => __( 'Headline', 'moksa-line' ),
					'productsNone' => __( 'No products found.', 'moksa-line' ),
					'productsOutOfStock' => __( 'Out of stock', 'moksa-line' ),
					/* translators: 1: how many chosen, 2: the limit. */
					'productsChosen' => __( '%1$d of %2$d chosen', 'moksa-line' ),
					'productsReplace' => __( 'Replace the current cards with these products?', 'moksa-line' ),
					'cardBody'     => __( 'Text under it', 'moksa-line' ),
					'cardHero'     => __( 'Image URL', 'moksa-line' ),
					'cardButtonLabel' => __( 'Button label', 'moksa-line' ),
					'cardButtonUri' => __( 'Button link', 'moksa-line' ),
					'cardButton'   => __( 'Find out more', 'moksa-line' ),
					'cardNewTitle' => __( 'New card', 'moksa-line' ),
					/* translators: %d: card position in the carousel. */
					'cardUntitled' => __( 'Card %d', 'moksa-line' ),
					'cardMoveLeft' => __( 'Move left', 'moksa-line' ),
					'cardMoveRight' => __( 'Move right', 'moksa-line' ),
					'cardDuplicate' => __( 'Duplicate', 'moksa-line' ),
					'cardRemove'   => __( 'Remove this card?', 'moksa-line' ),
					'cardTooMany'  => __( 'A carousel holds at most 12 cards.', 'moksa-line' ),
					'cardDropOthers' => __( 'Keep only the card you are editing and remove the rest?', 'moksa-line' ),
					'cardNone'     => __( 'No cards yet. Add the first one below.', 'moksa-line' ),
					'cardUnreadable' => __( 'This message is not a card or a carousel, so it can only be edited as JSON.', 'moksa-line' ),
					'cardCustom'   => __( 'This card has a layout these fields cannot describe, so it is edited as JSON below. Nothing here will change it.', 'moksa-line' ),
					'cardFieldsNote' => __( 'These cover the common card. Anything else -- extra rows, colours, more buttons -- is edited in the JSON.', 'moksa-line' ),
					'flowPrompt'   => __( 'What the bot asks', 'moksa-line' ),
					'flowAnswerType' => __( 'Answer', 'moksa-line' ),
					'flowKey'      => __( 'Stored as', 'moksa-line' ),
					'flowChoices'  => __( 'Buttons, one per line', 'moksa-line' ),
					'flowMoveUp'   => __( 'Move up', 'moksa-line' ),
					'flowMoveDown' => __( 'Move down', 'moksa-line' ),
					'flowDuplicate' => __( 'Duplicate', 'moksa-line' ),
					'flowDeleteStep' => __( 'Remove this question?', 'moksa-line' ),
					'flowNoSteps'  => __( 'No questions yet. Add the first one below.', 'moksa-line' ),
					'flowPromptMissing' => __( '(no question yet)', 'moksa-line' ),
					'flowSampleText' => __( 'Their answer', 'moksa-line' ),
					'flowCancel'   => __( 'Cancel', 'moksa-line' ),
					'flowBadJson'  => __( 'That JSON cannot be read, so the questions below are not showing it.', 'moksa-line' ),
					/* translators: %d: question number. */
					'flowNoPrompt' => __( 'Question %d has nothing to ask.', 'moksa-line' ),
					/* translators: 1: first question number, 2: second question number, 3: the key. */
					'flowDuplicateKey' => __( 'Questions %1$d and %2$d both store their answer as "%3$s", so the second overwrites the first.', 'moksa-line' ),
					/* translators: %d: question number. */
					'flowNoChoices' => __( 'Question %d offers buttons but none are listed.', 'moksa-line' ),
					/* translators: 1: question number, 2: how many buttons, 3: how many are sent. */
					'flowTooManyChoices' => __( 'Question %1$d has %2$d buttons. LINE allows 13 including the Cancel button, so only the first %3$d are sent.', 'moksa-line' ),
					/* translators: 1: the button label, 2: question number, 3: the limit. */
					'flowLongChoice' => __( 'Button "%1$s" on question %2$d is longer than the %3$d characters LINE shows, and is cut.', 'moksa-line' ),
					'sampleName'   => _x( 'Ming', 'sample customer name shown in previews', 'moksa-line' ),
					'replyEmpty'   => __( 'Nothing to reply with yet.', 'moksa-line' ),
					/* translators: 1: reply type, 2: the chosen item. */
					'replyReference' => __( '%1$s: %2$s', 'moksa-line' ),
					/* translators: 1: characters used, 2: the limit. */
					'charactersUsed' => __( '%1$s of %2$s characters', 'moksa-line' ),
					/* translators: %s: Flex template name. */
					'flexAttached' => __( 'Plus the Flex card "%s", drawn below this message.', 'moksa-line' ),
					'broadcastEmpty' => __( 'Nothing to send yet.', 'moksa-line' ),
					'sendAgain'    => __( 'Send again', 'moksa-line' ),
					'confirmResend' => __( 'Send this notification to the customer again?', 'moksa-line' ),
					'copyFailed'   => __( 'Could not copy -- select it and copy by hand', 'moksa-line' ),
					'confirmClearLogs' => __( 'Delete every entry in the plugin log? The log is only used for diagnosis, so nothing else is lost.', 'moksa-line' ),
					'altTextEmpty' => __( '(no fallback text -- the notification would be blank)', 'moksa-line' ),
					'altTextMissing' => __( 'Fallback text is empty. The chat list and the push notification would show nothing.', 'moksa-line' ),
					/* translators: %d: character count. */
					'altTextTooLong' => __( 'Fallback text is %d characters. LINE allows 1500 and cuts the rest.', 'moksa-line' ),
					/* translators: 1: the text, 2: measured contrast ratio, 3: required ratio. */
					'contrastWarning' => __( '"%1$s" has a contrast ratio of %2$s against its background; %3$s is the readable minimum.', 'moksa-line' ),
					'failed'       => __( 'That did not work.', 'moksa-line' ),
					'confirmDelete' => __( 'Delete this permanently?', 'moksa-line' ),
					'confirmDeleteDefault' => __( 'This is the default rich menu. Deleting it takes the menu away from every customer at once, until you make another one the default. Delete it anyway?', 'moksa-line' ),
					'publishing'   => __( 'Publishing to LINE...', 'moksa-line' ),
					'working'      => __( 'Working...', 'moksa-line' ),
					'chooseImage'  => __( 'Rich menu image', 'moksa-line' ),
					'editing'      => __( 'Editing', 'moksa-line' ),
					'menu'         => __( 'Menu', 'moksa-line' ),
					'area'         => __( 'Area', 'moksa-line' ),
					'pickAnArea'   => __( 'Drag on the image to add an area, or click one to edit it.', 'moksa-line' ),
					'actionType'   => __( 'When tapped', 'moksa-line' ),
					'actionLabel'  => __( 'Label', 'moksa-line' ),
					'actionMessage' => __( 'Send a message', 'moksa-line' ),
					'actionUri'    => __( 'Open a link', 'moksa-line' ),
					'actionPostback' => __( 'Postback', 'moksa-line' ),
					'actionSwitch' => __( 'Switch to another tab', 'moksa-line' ),
					'actionUriValue' => __( 'Link', 'moksa-line' ),
					'actionText'   => __( 'Message the customer sends', 'moksa-line' ),
					'actionAlias'  => __( 'Tab to switch to', 'moksa-line' ),
					'actionData'   => __( 'Postback data', 'moksa-line' ),
					'noTabs'       => __( 'No other menus in this tab group yet', 'moksa-line' ),
					'deleteArea'   => __( 'Delete this area', 'moksa-line' ),
					'nudgeHint'    => __( 'Arrow keys nudge by 1 pixel, with Shift by 10.', 'moksa-line' ),
					'replaceAreas' => __( 'Replace the current areas with this layout?', 'moksa-line' ),
					/* translators: %s: list of overlapping area numbers. */
					'areasOverlap' => __( 'Areas %s overlap. LINE uses whichever comes first, which is rarely what you want.', 'moksa-line' ),
					/* translators: %d: maximum number of areas. */
					'tooManyAreas' => __( 'LINE allows at most %d areas; the extra ones will be dropped.', 'moksa-line' ),
					/* translators: %d: number of areas without a destination. */
					'areasIncomplete' => __( '%d area(s) have no destination set yet.', 'moksa-line' ),
				),
			)
		);
	}

	/**
	 * Cache-busting version for a bundled asset.
	 *
	 * The plugin version alone is not enough: a file edited without a release
	 * -- during development, or by a site that patched a stylesheet over SFTP --
	 * keeps the old query string, so browsers and page caches keep serving the
	 * old copy and the change appears not to have happened. The file's own
	 * modification time changes exactly when its contents do.
	 *
	 * @param string $relative Path below the plugin directory.
	 */
	public static function asset_version( string $relative ): string {
		$path = MOKSA_LINE_DIR . $relative;
		$time = is_readable( $path ) ? filemtime( $path ) : false;

		return false === $time ? MOKSA_LINE_VERSION : MOKSA_LINE_VERSION . '.' . $time;
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
				esc_html__( 'Settings', 'moksa-line' )
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

		/**
		 * The settings screen for one tab.
		 *
		 * Every failing check carries where it is fixed. A single "Open
		 * settings" button at the bottom of the list leaves the reader to work
		 * out which of seven tabs the failure lives on, which is the part they
		 * did not know in the first place.
		 *
		 * @param string $tab Settings tab slug.
		 * @return string
		 */
		$tab = static function ( string $tab ): string {
			return admin_url( 'admin.php?page=' . self::SLUG . '-settings&tab=' . $tab );
		};

		$items[] = array(
			'done'  => '' !== (string) Options::get( 'channel_id' ) && '' !== (string) Options::get( 'channel_secret' ),
			'label' => __( 'LINE Login channel connected', 'moksa-line' ),
			'hint'  => __( 'Add the Channel ID and Channel Secret from the LINE Developers Console.', 'moksa-line' ),
			'fix'   => $tab( 'general' ),
		);

		$items[] = array(
			'done'  => \Moksa\Line\Api\TokenManager::is_configured(),
			'label' => __( 'Messaging API channel connected', 'moksa-line' ),
			'hint'  => __( 'The bot cannot send or receive anything until this is set.', 'moksa-line' ),
			'fix'   => $tab( 'messaging' ),
		);

		$items[] = array(
			'done'  => ! \Moksa\Line\Api\Signature::using_fallback_secret(),
			'label' => __( 'Webhook signing secret is the Messaging API one', 'moksa-line' ),
			'hint'  => __( 'Webhooks are signed with the Messaging API channel secret. Falling back to the Login channel secret only works when both channels are the same, which is unusual.', 'moksa-line' ),
			'fix'   => $tab( 'messaging' ),
		);

		$items[] = array(
			'done'  => \Moksa\Line\Webhook\EventQueue::has_any(),
			'label' => __( 'Webhook has received an event', 'moksa-line' ),
			'hint'  => __( 'Paste the webhook URL into the Console, enable "Use webhook", then press Verify. "Check what LINE has" on the Messaging API tab reports whether that worked.', 'moksa-line' ),
			'fix'   => $tab( 'messaging' ),
		);

		if ( Options::get( 'ai_enabled' ) ) {
			$provider = Providers::make();

			// Three outcomes with three different fixes: no provider chosen, the
			// provider is missing, or it is present but has no credentials. One
			// message for all three sends most readers to the wrong place.
			if ( ! $provider ) {
				$hint = __( 'AI replies are switched on but no AI service is selected.', 'moksa-line' );
			} elseif ( ! $provider->installed() ) {
				$hint = __( 'AI replies are switched on, but the selected AI service is not available on this site.', 'moksa-line' );
			} else {
				$hint = __( 'The AI service is present but has no provider connected yet, so it cannot answer anything.', 'moksa-line' );
			}

			$items[] = array(
				'done'  => $provider && $provider->is_available(),
				'label' => __( 'AI provider available', 'moksa-line' ),
				'hint'  => $hint,
				'fix'   => $tab( 'ai' ),
			);
		}

		if ( Options::get( 'pay_enabled' ) ) {
			$items[] = array(
				'done'  => \Moksa\Line\Pay\LinePayClient::is_configured(),
				'label' => __( 'LINE Pay credentials present', 'moksa-line' ),
				'hint'  => __( 'Add the LINE Pay Channel ID and Channel Secret.', 'moksa-line' ),
			'fix'   => $tab( 'pay' ),
			);

			$items[] = array(
				'done'  => ! Options::get( 'pay_sandbox' ),
				'label' => __( 'LINE Pay is in production mode', 'moksa-line' ),
				'hint'  => __( 'Sandbox mode is on, so no real money moves. Turn it off when you go live.', 'moksa-line' ),
			'fix'   => $tab( 'pay' ),
			);
		}

		if ( ! \Moksa\Line\Support\Crypto::available() ) {
			$items[] = array(
				'done'  => false,
				'label' => __( 'Credentials are encrypted at rest', 'moksa-line' ),
				'hint'  => __( 'OpenSSL with AES-256-GCM is not available on this server, so channel secrets are stored as plain text.', 'moksa-line' ),
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
		$messaging = \Moksa\Line\Api\TokenManager::is_configured();

		if ( $login || $messaging ) {
			return;
		}

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Moksa LINE Suite is installed but not connected to a LINE channel yet.', 'moksa-line' ),
			esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-settings' ) ),
			esc_html__( 'Finish setup', 'moksa-line' )
		);
	}

	// --- Settings ---------------------------------------------------------------------

	/**
	 * Persist the settings form.
	 */
	public function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'moksa-line' ), 403 );
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

		// Order status checkboxes are a list rather than a single value, so
		// they are handled outside the schema loop. An empty submission on the
		// WooCommerce tab means "notify on nothing", which is a real choice.
		if ( 'woo' === $tab ) {
			$statuses = isset( $_POST['moksa_line_woo_statuses'] )
				? array_map( 'sanitize_key', (array) wp_unslash( $_POST['moksa_line_woo_statuses'] ) )
				: array();

			Options::set( 'woo_notify_statuses', array_values( $statuses ) );
		}

		// A LINE channel id is a ten digit number. Anything else is a mistake --
		// most often a browser autofilling an email address into the field
		// because it sits next to a password input. Storing it would produce a
		// login failure whose message points nowhere near the cause.
		$warnings = array();

		foreach ( array( 'channel_id', 'messaging_channel_id', 'pay_channel_id' ) as $id_key ) {
			$value = (string) Options::get( $id_key );

			if ( '' !== $value && ! preg_match( '/^\d{7,15}$/', $value ) ) {
				$warnings[] = $id_key;
			}
		}

		if ( $warnings ) {
			set_transient( 'moksa_line_settings_warning', $warnings, 60 );
		} else {
			delete_transient( 'moksa_line_settings_warning' );
		}

		Options::flush_cache();

		// A changed messaging secret invalidates any cached token.
		\Moksa\Line\Api\TokenManager::forget();

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
	/**
	 * Report the webhook LINE actually holds, and whether it is this site.
	 *
	 * Until now the settings screen could only print the URL and ask someone to
	 * paste it into the Console. Nothing here could tell whether that was ever
	 * done, whether "Use webhook" is switched on, or whether the channel is
	 * pointed at a staging copy of the site -- and a bot pointed elsewhere goes
	 * quiet without a single error appearing anywhere in wp-admin.
	 */
	public function ajax_webhook_check(): void {
		$this->webhook_guard();

		$current = MessagingClient::webhook_endpoint();

		if ( is_wp_error( $current ) ) {
			wp_send_json_error( array( 'message' => $current->get_error_message() ) );
		}

		$ours    = \Moksa\Line\Webhook\WebhookModule::endpoint_url();
		$matches = untrailingslashit( $current['endpoint'] ) === untrailingslashit( $ours );
		$lines   = array();

		if ( '' === $current['endpoint'] ) {
			$lines[] = __( 'This channel has no webhook URL set, so nothing anyone sends the bot reaches this site.', 'moksa-line' );
		} else {
			$lines[] = sprintf(
				/* translators: %s: webhook URL. */
				__( 'LINE is set to deliver to %s', 'moksa-line' ),
				$current['endpoint']
			);

			if ( ! $matches ) {
				$lines[] = sprintf(
					/* translators: %s: webhook URL. */
					__( 'That is not this site, which expects %s. Events are going somewhere else.', 'moksa-line' ),
					$ours
				);
			}

			$lines[] = $current['active']
				? __( 'Webhook delivery is switched on.', 'moksa-line' )
				: __( 'Webhook delivery is switched OFF in the Console, so LINE holds the URL but sends nothing to it.', 'moksa-line' );
		}

		// LINE's own delivery attempt, which is the only thing that proves the
		// endpoint is reachable from outside -- a URL can be correct and still
		// be blocked by a firewall, a maintenance page or basic auth.
		$test = MessagingClient::test_webhook_endpoint();

		if ( ! is_wp_error( $test ) ) {
			$reason = isset( $test['reason'] ) ? (string) $test['reason'] : '';
			$status = isset( $test['statusCode'] ) ? (int) $test['statusCode'] : 0;

			if ( ! empty( $test['success'] ) ) {
				$lines[] = sprintf(
					/* translators: %d: HTTP status code. */
					__( 'LINE delivered a test event and this site answered %d.', 'moksa-line' ),
					$status
				);
			} else {
				$lines[] = sprintf(
					/* translators: 1: LINE's reason code, 2: HTTP status code. */
					__( 'LINE could not deliver a test event: %1$s (HTTP %2$d).', 'moksa-line' ),
					$reason,
					$status
				);
			}
		}

		wp_send_json_success(
			array(
				'endpoint' => $current['endpoint'],
				'active'   => $current['active'],
				'matches'  => $matches,
				'ok'       => $matches && $current['active'],
				'message'  => $matches && $current['active']
					? __( 'The channel is pointed at this site and delivery is on.', 'moksa-line' )
					: __( 'The channel is not delivering to this site.', 'moksa-line' ),
				'lines'    => $lines,
			)
		);
	}

	/**
	 * Point the channel at this site.
	 *
	 * This only sets the URL. "Use webhook" is a separate switch that lives in
	 * the Console and has no API, so the check above still has to report it.
	 */
	public function ajax_webhook_set(): void {
		$this->webhook_guard();

		$ours   = \Moksa\Line\Webhook\WebhookModule::endpoint_url();
		$result = MessagingClient::set_webhook_endpoint( $ours );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'LINE now delivers to this site. If delivery is still off, turn on "Use webhook" in the Console -- that switch has no API.', 'moksa-line' ),
			)
		);
	}

	private function webhook_guard(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot change the webhook settings.', 'moksa-line' ) ), 403 );
		}

		if ( ! \Moksa\Line\Api\TokenManager::is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Fill in the Messaging API channel first.', 'moksa-line' ) ) );
		}
	}

	/**
	 * Empty the plugin log.
	 *
	 * The log is diagnostic only -- nothing reads it back -- so clearing it
	 * loses no state. It is offered because the retention window is measured in
	 * days, and someone who has just fixed a misconfiguration wants to watch a
	 * clean log rather than hunt for new lines among the old failures.
	 */
	public function ajax_clear_logs(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot clear the log.', 'moksa-line' ) ), 403 );
		}

		global $wpdb;
		$table = \Moksa\Line\Support\Logger::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$removed = (int) $wpdb->query( "DELETE FROM {$table}" );

		wp_send_json_success(
			array(
				'removed' => $removed,
				'message' => sprintf(
					/* translators: %s: number of entries removed. */
					__( 'Cleared %s entries.', 'moksa-line' ),
					number_format_i18n( $removed )
				),
			)
		);
	}

	/**
	 * Send an order notification again.
	 *
	 * Almost every failure in that history is one cause -- credentials that were
	 * not filled in yet -- affecting every order that passed through in the
	 * meantime. Without this the history is a read-only list of customers who
	 * never heard anything, and the only remedy is to nudge each order's status
	 * back and forth in WooCommerce.
	 */
	public function ajax_resend_notification(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot resend notifications.', 'moksa-line' ) ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		if ( $order_id <= 0 || '' === $status ) {
			wp_send_json_error( array( 'message' => __( 'That row does not name an order and a status to resend.', 'moksa-line' ) ) );
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'moksa-line' ) ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'That order no longer exists.', 'moksa-line' ) ) );
		}

		// The duplicate guard is what stops a second send, so it has to be
		// cleared for this status before dispatch will do anything at all.
		\Moksa\Line\Woo\WooModule::reset_notified( $order, $status );

		$module = new \Moksa\Line\Woo\WooModule();
		$module->dispatch( $order_id, $status );

		wp_send_json_success( array( 'message' => __( 'Sent again. The result is in the list below.', 'moksa-line' ) ) );
	}

	public function ajax_broadcast(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot send broadcasts.', 'moksa-line' ) ), 403 );
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
				wp_send_json_error( array( 'message' => __( 'That template could not be loaded.', 'moksa-line' ) ) );
			}

			$messages[] = \Moksa\Line\Api\MessagingClient::flex( (string) $template->alt_text, $contents );
		}

		if ( '' !== trim( $body ) ) {
			array_unshift( $messages, \Moksa\Line\Api\MessagingClient::text( $body ) );
		}

		if ( empty( $messages ) ) {
			wp_send_json_error( array( 'message' => __( 'Write a message or choose a template first.', 'moksa-line' ) ) );
		}

		if ( 'test' === $mode ) {
			if ( '' === $target ) {
				wp_send_json_error( array( 'message' => __( 'Enter a LINE user id to send the test to.', 'moksa-line' ) ) );
			}

			$result = \Moksa\Line\Api\MessagingClient::push( $target, $messages );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( array( 'message' => __( 'Test sent.', 'moksa-line' ) ) );
		}

		// Sending to everyone needs the operator to type the confirmation word,
		// because there is no undo and it costs one message per friend.
		$confirmation = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';

		if ( 'SEND' !== strtoupper( $confirmation ) ) {
			wp_send_json_error( array( 'message' => __( 'Type SEND in the confirmation box to go ahead.', 'moksa-line' ) ) );
		}

		if ( 'all' === $mode ) {
			$result = \Moksa\Line\Api\MessagingClient::broadcast( $messages );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( array( 'message' => __( 'Broadcast sent to every friend of the account.', 'moksa-line' ) ) );
		}

		// 'known' sends only to the friends this site has actually recorded,
		// which is a smaller and more predictable bill than a full broadcast.
		$recipients = \Moksa\Line\Data\Users::friend_ids();

		if ( empty( $recipients ) ) {
			wp_send_json_error( array( 'message' => __( 'No friends have been recorded on this site yet.', 'moksa-line' ) ) );
		}

		$report = \Moksa\Line\Api\MessagingClient::multicast( $recipients, $messages );

		if ( $report['failed'] > 0 ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: delivered count, 2: failed count, 3: first error. */
						__( 'Delivered to %1$d, failed for %2$d. %3$s', 'moksa-line' ),
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
					__( 'Sent to %d people.', 'moksa-line' ),
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

	public function render_imagemaps(): void {
		$this->view( 'imagemaps' );
	}

	public function render_templates(): void {
		$this->view( 'templates' );
	}

	public function render_broadcast(): void {
		$this->view( 'broadcast' );
	}

	public function render_users(): void {
		$this->view( 'users' );
	}

	public function render_notifications(): void {
		$this->view( 'notifications' );
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
			wp_die( esc_html__( 'You do not have permission to view this page.', 'moksa-line' ), 403 );
		}

		$path = MOKSA_LINE_DIR . 'views/' . $name . '.php';

		if ( ! is_readable( $path ) ) {
			printf( '<div class="wrap"><p>%s</p></div>', esc_html__( 'This screen is missing.', 'moksa-line' ) );

			return;
		}

		include $path;
	}
}
