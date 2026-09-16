<?php
/**
 * Admin screens.
 *
 * @package Mofoline
 */

namespace Mofoline\Admin;

use Mofoline\Support\Db;
use Mofoline\Api\MessagingClient;
use Mofoline\Bot\Ai\Providers;
use Mofoline\Inbox\Conversations;
use Mofoline\Support\Migrator;
use Mofoline\Support\Options;
use Mofoline\Admin\Ajax;

defined( 'ABSPATH' ) || exit;

class AdminModule {

	const SLUG = 'moksa-for-line';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_mofoline_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );
		add_action( 'wp_ajax_mofoline_broadcast', array( $this, 'ajax_broadcast' ) );
		add_action( 'wp_ajax_mofoline_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_mofoline_webhook_check', array( $this, 'ajax_webhook_check' ) );
		add_action( 'wp_ajax_mofoline_webhook_set', array( $this, 'ajax_webhook_set' ) );
		add_action( 'wp_ajax_mofoline_resend_notification', array( $this, 'ajax_resend_notification' ) );

		add_filter( 'parent_file', array( $this, 'keep_menu_open' ) );
		add_filter( 'plugin_action_links_' . MOFOLINE_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Build the menu.
	 */
	public function register_menu(): void {
		$capability = 'manage_options';
		$unread     = Options::get( 'inbox_enabled' ) ? Conversations::unread_total() : 0;

		$inbox_label = __( 'Inbox', 'moksa-for-line' );

		if ( $unread > 0 ) {
			$inbox_label .= sprintf(
				' <span class="awaiting-mod"><span class="pending-count">%d</span></span>',
				$unread
			);
		}

		add_menu_page(
			__( 'LINE', 'moksa-for-line' ),
			__( 'LINE', 'moksa-for-line' ),
			$capability,
			self::SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-format-chat',
			58
		);

		$pages = array(
			array( self::SLUG, __( 'Dashboard', 'moksa-for-line' ), 'render_dashboard' ),
			array( self::SLUG . '-inbox', $inbox_label, 'render_inbox' ),
			array( self::SLUG . '-replies', __( 'Auto replies', 'moksa-for-line' ), 'render_replies' ),
			array( self::SLUG . '-flows', __( 'Conversation flows', 'moksa-for-line' ), 'render_flows' ),
			array( self::SLUG . '-flex', __( 'Flex messages', 'moksa-for-line' ), 'render_flex' ),
			array( self::SLUG . '-richmenus', __( 'Rich menus', 'moksa-for-line' ), 'render_richmenus' ),
			array( self::SLUG . '-imagemaps', __( 'Imagemaps', 'moksa-for-line' ), 'render_imagemaps' ),
			array( self::SLUG . '-templates', __( 'Template messages', 'moksa-for-line' ), 'render_templates' ),
			array( self::SLUG . '-broadcast', __( 'Broadcast', 'moksa-for-line' ), 'render_broadcast' ),
			array( self::SLUG . '-users', __( 'LINE users', 'moksa-for-line' ), 'render_users' ),
			array( self::SLUG . '-notifications', __( 'Order notifications', 'moksa-for-line' ), 'render_notifications' ),
			array( self::SLUG . '-payments', __( 'Payments', 'moksa-for-line' ), 'render_payments' ),
			array( self::SLUG . '-logs', __( 'Logs', 'moksa-for-line' ), 'render_logs' ),
			array( self::SLUG . '-settings', __( 'Settings', 'moksa-for-line' ), 'render_settings' ),
		);

		foreach ( $pages as $page ) {
			list( $slug, $label, $callback ) = $page;

			// The unread bubble belongs on the menu, not in the browser tab:
			// stripping only its tags left "Inbox 1" as the page title, a number
			// frozen at page load next to the live count the inbox screen keeps
			// in the title itself.
			$page_title = wp_strip_all_tags( preg_replace( '#\s*<span class="awaiting-mod">.*?</span></span>#', '', $label ) );

			add_submenu_page(
				self::SLUG,
				$page_title,
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
				__( 'Notification templates', 'moksa-for-line' ),
				__( 'Notification templates', 'moksa-for-line' ),
				'manage_woocommerce',
				'edit.php?post_type=' . \Mofoline\Woo\NotifyTemplates::POST_TYPE
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

		if ( $screen && \Mofoline\Woo\NotifyTemplates::POST_TYPE === $screen->post_type ) {
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
		$is_template     = $screen && \Mofoline\Woo\NotifyTemplates::POST_TYPE === $screen->post_type;

		if ( false === strpos( (string) $hook, self::SLUG )
			&& ! in_array( $hook, $profile_screens, true )
			&& ! $is_template ) {
			return;
		}

		wp_enqueue_media();

		// The dialog's styles moved out of admin.css so the account page can use
		// them too; admin.css depends on them rather than carrying them.
		wp_enqueue_style(
			'mofoline-confirm',
			MOFOLINE_URL . 'assets/css/confirm.css',
			array(),
			self::asset_version( 'assets/css/confirm.css' )
		);

		wp_enqueue_style(
			'mofoline-admin',
			MOFOLINE_URL . 'assets/css/admin.css',
			array( 'mofoline-confirm' ),
			self::asset_version( 'assets/css/admin.css' )
		);

		// The front-end sheet is registered on wp_enqueue_scripts, which never
		// fires in admin, so enqueuing it by handle here silently did nothing
		// and the login button preview rendered as unstyled text. Register it
		// again for admin rather than relying on a handle from another context.
		if ( ! wp_style_is( 'mofoline-front', 'registered' ) ) {
			wp_register_style(
				'mofoline-front',
				MOFOLINE_URL . 'assets/css/front.css',
				array(),
				self::asset_version( 'assets/css/front.css' )
			);
		}

		// Every editor asks a confirmation question, and they all load before
		// admin.js, so this has to come first and be a dependency of each.
		wp_enqueue_script(
			'mofoline-confirm',
			MOFOLINE_URL . 'assets/js/confirm.js',
			array( 'jquery' ),
			self::asset_version( 'assets/js/confirm.js' ),
			true
		);

		wp_enqueue_script(
			'mofoline-flex-renderer',
			MOFOLINE_URL . 'assets/js/moksa-flex-renderer.js',
			array( 'jquery' ),
			self::asset_version( 'assets/js/moksa-flex-renderer.js' ),
			true
		);

		wp_enqueue_script(
			'mofoline-richmenu-editor',
			MOFOLINE_URL . 'assets/js/richmenu-editor.js',
			array( 'jquery', 'mofoline-confirm' ),
			self::asset_version( 'assets/js/richmenu-editor.js' ),
			true
		);

		wp_enqueue_script(
			'mofoline-card-editor',
			MOFOLINE_URL . 'assets/js/card-editor.js',
			array( 'jquery', 'mofoline-confirm' ),
			self::asset_version( 'assets/js/card-editor.js' ),
			true
		);

		wp_enqueue_script(
			'mofoline-template-editor',
			MOFOLINE_URL . 'assets/js/template-editor.js',
			array( 'jquery', 'mofoline-confirm' ),
			self::asset_version( 'assets/js/template-editor.js' ),
			true
		);

		wp_enqueue_script(
			'mofoline-flow-editor',
			MOFOLINE_URL . 'assets/js/flow-editor.js',
			array( 'jquery', 'mofoline-confirm' ),
			self::asset_version( 'assets/js/flow-editor.js' ),
			true
		);

		wp_enqueue_script(
			'mofoline-admin',
			MOFOLINE_URL . 'assets/js/admin.js',
			array( 'jquery', 'heartbeat', 'mofoline-confirm', 'mofoline-flex-renderer', 'mofoline-richmenu-editor', 'mofoline-flow-editor', 'mofoline-card-editor', 'mofoline-template-editor' ),
			self::asset_version( 'assets/js/admin.js' ),
			true
		);

		wp_localize_script(
			'mofoline-admin',
			'mofoline',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'siteName' => get_bloginfo( 'name' ),
				'siteUrl'  => home_url(),
				'nonce'   => wp_create_nonce( 'mofoline_admin' ),
				'strings' => array(
					'saved'        => __( 'Saved.', 'moksa-for-line' ),
					'copied'       => __( 'Copied', 'moksa-for-line' ),
					'templateButton' => __( 'Button', 'moksa-for-line' ),
					'templateYes'  => __( 'Yes', 'moksa-for-line' ),
					'templateNo'   => __( 'No', 'moksa-for-line' ),
					'templateQuestion' => __( 'The question', 'moksa-for-line' ),
					'templateText' => __( 'Text', 'moksa-for-line' ),
					'templateAddButton' => __( '+ Add a button', 'moksa-for-line' ),
					'templateTooMany' => __( 'A carousel holds at most 10 cards.', 'moksa-for-line' ),
					'templateLastCard' => __( 'A template needs at least one card.', 'moksa-for-line' ),
					// The inbox updates a conversation's state without reloading,
					// so it needs the same words the server-rendered pill uses.
					'conversationStatus' => array(
						'bot'    => __( 'bot', 'moksa-for-line' ),
						'human'  => __( 'human', 'moksa-for-line' ),
						'closed' => __( 'closed', 'moksa-for-line' ),
					),
					/* translators: %d: number of cards in the carousel. */
					'carouselCount' => __( '%d cards. The customer swipes sideways to reach the rest.', 'moksa-for-line' ),
					'cardTitle'    => __( 'Headline', 'moksa-for-line' ),
					'productsNone' => __( 'No products found.', 'moksa-for-line' ),
					'productsOutOfStock' => __( 'Out of stock', 'moksa-for-line' ),
					/* translators: 1: how many chosen, 2: the limit. */
					'productsChosen' => __( '%1$d of %2$d chosen', 'moksa-for-line' ),
					'productsReplace' => __( 'Replace the current cards with these products?', 'moksa-for-line' ),
					'cardBody'     => __( 'Text under it', 'moksa-for-line' ),
					'cardHero'     => __( 'Image URL', 'moksa-for-line' ),
					'cardButtonLabel' => __( 'Button label', 'moksa-for-line' ),
					'cardButtonUri' => __( 'Button link', 'moksa-for-line' ),
					'cardButton'   => __( 'Find out more', 'moksa-for-line' ),
					'cardNewTitle' => __( 'New card', 'moksa-for-line' ),
					/* translators: %d: card position in the carousel. */
					'cardUntitled' => __( 'Card %d', 'moksa-for-line' ),
					'cardMoveLeft' => __( 'Move left', 'moksa-for-line' ),
					'cardMoveRight' => __( 'Move right', 'moksa-for-line' ),
					'cardDuplicate' => __( 'Duplicate', 'moksa-for-line' ),
					'cardRemove'   => __( 'Remove this card?', 'moksa-for-line' ),
					'cardTooMany'  => __( 'A carousel holds at most 12 cards.', 'moksa-for-line' ),
					'cardDropOthers' => __( 'Keep only the card you are editing and remove the rest?', 'moksa-for-line' ),
					'cardNone'     => __( 'No cards yet. Add the first one below.', 'moksa-for-line' ),
					'cardUnreadable' => __( 'This message is not a card or a carousel, so it can only be edited as JSON.', 'moksa-for-line' ),
					'cardCustom'   => __( 'This card has a layout these fields cannot describe, so it is edited as JSON below. Nothing here will change it.', 'moksa-for-line' ),
					'cardFieldsNote' => __( 'These cover the common card. Anything else -- extra rows, colours, more buttons -- is edited in the JSON.', 'moksa-for-line' ),
					'flowPrompt'   => __( 'What the bot asks', 'moksa-for-line' ),
					'flowAnswerType' => __( 'Answer', 'moksa-for-line' ),
					'flowKey'      => __( 'Stored as', 'moksa-for-line' ),
					'flowChoices'  => __( 'Buttons, one per line', 'moksa-for-line' ),
					'flowMoveUp'   => __( 'Move up', 'moksa-for-line' ),
					'flowMoveDown' => __( 'Move down', 'moksa-for-line' ),
					'flowDuplicate' => __( 'Duplicate', 'moksa-for-line' ),
					'flowDeleteStep' => __( 'Remove this question?', 'moksa-for-line' ),
					'flowNoSteps'  => __( 'No questions yet. Add the first one below.', 'moksa-for-line' ),
					'flowPromptMissing' => __( '(no question yet)', 'moksa-for-line' ),
					'flowSampleText' => __( 'Their answer', 'moksa-for-line' ),
					'flowCancel'   => __( 'Cancel', 'moksa-for-line' ),
					'flowBadJson'  => __( 'That JSON cannot be read, so the questions below are not showing it.', 'moksa-for-line' ),
					/* translators: %d: question number. */
					'flowNoPrompt' => __( 'Question %d has nothing to ask.', 'moksa-for-line' ),
					/* translators: 1: first question number, 2: second question number, 3: the key. */
					'flowDuplicateKey' => __( 'Questions %1$d and %2$d both store their answer as "%3$s", so the second overwrites the first.', 'moksa-for-line' ),
					/* translators: %d: question number. */
					'flowNoChoices' => __( 'Question %d offers buttons but none are listed.', 'moksa-for-line' ),
					/* translators: 1: question number, 2: how many buttons, 3: how many are sent. */
					'flowTooManyChoices' => __( 'Question %1$d has %2$d buttons. LINE allows 13 including the Cancel button, so only the first %3$d are sent.', 'moksa-for-line' ),
					/* translators: 1: the button label, 2: question number, 3: the limit. */
					'flowLongChoice' => __( 'Button "%1$s" on question %2$d is longer than the %3$d characters LINE shows, and is cut.', 'moksa-for-line' ),
					'sampleName'   => _x( 'Ming', 'sample customer name shown in previews', 'moksa-for-line' ),
					'replyEmpty'   => __( 'Nothing to reply with yet.', 'moksa-for-line' ),
					/* translators: 1: reply type, 2: the chosen item. */
					'replyReference' => __( '%1$s: %2$s', 'moksa-for-line' ),
					/* translators: 1: characters used, 2: the limit. */
					'charactersUsed' => __( '%1$s of %2$s characters', 'moksa-for-line' ),
					/* translators: %s: Flex template name. */
					'flexAttached' => __( 'Plus the Flex card "%s", drawn below this message.', 'moksa-for-line' ),
					'broadcastEmpty' => __( 'Nothing to send yet.', 'moksa-for-line' ),
					'sendAgain'    => __( 'Send again', 'moksa-for-line' ),
					'confirmResend' => __( 'Send this notification to the customer again?', 'moksa-for-line' ),
					'copyFailed'   => __( 'Could not copy -- select it and copy by hand', 'moksa-for-line' ),
					/* translators: %d: how many messages arrived. */
					'newMessages'  => __( '%d new messages', 'moksa-for-line' ),
					'confirmClearLogs' => __( 'Delete every entry in the plugin log? The log is only used for diagnosis, so nothing else is lost.', 'moksa-for-line' ),
					'altTextEmpty' => __( '(no fallback text -- the notification would be blank)', 'moksa-for-line' ),
					'altTextMissing' => __( 'Fallback text is empty. The chat list and the push notification would show nothing.', 'moksa-for-line' ),
					/* translators: %d: character count. */
					'altTextTooLong' => __( 'Fallback text is %d characters. LINE allows 1500 and cuts the rest.', 'moksa-for-line' ),
					/* translators: 1: the text, 2: measured contrast ratio, 3: required ratio. */
					'contrastWarning' => __( '"%1$s" has a contrast ratio of %2$s against its background; %3$s is the readable minimum.', 'moksa-for-line' ),
					'failed'       => __( 'That did not work.', 'moksa-for-line' ),
					'confirmYes'   => __( 'Yes, do it', 'moksa-for-line' ),
					'confirmDeleteAction' => _x( 'Delete', 'confirmation button', 'moksa-for-line' ),
					'confirmSendAction' => _x( 'Send', 'confirmation button', 'moksa-for-line' ),
					'confirmClearAction' => _x( 'Clear', 'confirmation button', 'moksa-for-line' ),
					'confirmReplaceAction' => _x( 'Replace', 'confirmation button', 'moksa-for-line' ),
					'productsReplaceAction' => _x( 'Replace', 'confirmation button', 'moksa-for-line' ),
					'confirmClearAreas' => __( 'Remove every area from this menu?', 'moksa-for-line' ),
					'dismiss'      => __( 'Dismiss', 'moksa-for-line' ),
					'confirmNo'    => __( 'Cancel', 'moksa-for-line' ),
					'confirmDelete' => __( 'Delete this permanently?', 'moksa-for-line' ),
					'confirmDeleteDefault' => __( 'This is the default rich menu. Deleting it takes the menu away from every customer at once, until you make another one the default. Delete it anyway?', 'moksa-for-line' ),
					'publishing'   => __( 'Publishing to LINE...', 'moksa-for-line' ),
					'working'      => __( 'Working...', 'moksa-for-line' ),
					'chooseImage'  => __( 'Rich menu image', 'moksa-for-line' ),
					'editing'      => __( 'Editing', 'moksa-for-line' ),
					'menu'         => __( 'Menu', 'moksa-for-line' ),
					'area'         => __( 'Area', 'moksa-for-line' ),
					'pickAnArea'   => __( 'Drag on the image to add an area, or click one to edit it.', 'moksa-for-line' ),
					'actionType'   => __( 'When tapped', 'moksa-for-line' ),
					'actionLabel'  => __( 'Label', 'moksa-for-line' ),
					'actionMessage' => __( 'Send a message', 'moksa-for-line' ),
					'actionUri'    => __( 'Open a link', 'moksa-for-line' ),
					'actionPostback' => __( 'Send a hidden command (postback)', 'moksa-for-line' ),
					'actionSwitch' => __( 'Switch to another tab', 'moksa-for-line' ),
					'actionUriValue' => __( 'Link', 'moksa-for-line' ),
					'actionText'   => __( 'Message the customer sends', 'moksa-for-line' ),
					'actionAlias'  => __( 'Tab to switch to', 'moksa-for-line' ),
					'actionData'   => __( 'Postback data', 'moksa-for-line' ),
					'noTabs'       => __( 'No other menus in this tab group yet', 'moksa-for-line' ),
					'deleteArea'   => __( 'Delete this area', 'moksa-for-line' ),
					'nudgeHint'    => __( 'Arrow keys nudge by 1 pixel, with Shift by 10.', 'moksa-for-line' ),
					'replaceAreas' => __( 'Replace the current areas with this layout?', 'moksa-for-line' ),
					/* translators: %s: list of overlapping area numbers. */
					'areasOverlap' => __( 'Areas %s overlap. LINE uses whichever comes first, which is rarely what you want.', 'moksa-for-line' ),
					/* translators: %d: maximum number of areas. */
					'tooManyAreas' => __( 'LINE allows at most %d areas; the extra ones will be dropped.', 'moksa-for-line' ),
					/* translators: %d: number of areas without a destination. */
					'areasIncomplete' => __( '%d area(s) have no destination set yet.', 'moksa-for-line' ),
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
		$path = MOFOLINE_DIR . $relative;
		$time = is_readable( $path ) ? filemtime( $path ) : false;

		return false === $time ? MOFOLINE_VERSION : MOFOLINE_VERSION . '.' . $time;
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
				esc_html__( 'Settings', 'moksa-for-line' )
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
			'label' => __( 'LINE Login channel connected', 'moksa-for-line' ),
			'hint'  => __( 'Add the Channel ID and Channel Secret from the LINE Developers Console.', 'moksa-for-line' ),
			'fix'   => $tab( 'general' ),
		);

		$items[] = array(
			'done'  => \Mofoline\Api\TokenManager::is_configured(),
			'label' => __( 'Messaging API channel connected', 'moksa-for-line' ),
			'hint'  => __( 'The bot cannot send or receive anything until this is set.', 'moksa-for-line' ),
			'fix'   => $tab( 'messaging' ),
		);

		$items[] = array(
			'done'  => ! \Mofoline\Api\Signature::using_fallback_secret(),
			'label' => __( 'Webhook signing secret is the Messaging API one', 'moksa-for-line' ),
			'hint'  => __( 'Webhooks are signed with the Messaging API channel secret. Falling back to the Login channel secret only works when both channels are the same, which is unusual.', 'moksa-for-line' ),
			'fix'   => $tab( 'messaging' ),
		);

		$items[] = array(
			'done'  => \Mofoline\Webhook\EventQueue::has_any(),
			'label' => __( 'Webhook has received an event', 'moksa-for-line' ),
			'hint'  => __( 'Paste the webhook URL into the Console, enable "Use webhook", then press Verify. "Check what LINE has" on the Messaging API tab reports whether that worked.', 'moksa-for-line' ),
			'fix'   => $tab( 'messaging' ),
		);

		if ( Options::get( 'ai_enabled' ) ) {
			$provider = Providers::make();

			// Three outcomes with three different fixes: no provider chosen, the
			// provider is missing, or it is present but has no credentials. One
			// message for all three sends most readers to the wrong place.
			if ( ! $provider ) {
				$hint = __( 'AI replies are switched on but no AI service is selected.', 'moksa-for-line' );
			} elseif ( ! $provider->installed() ) {
				$hint = __( 'AI replies are switched on, but the selected AI service is not available on this site.', 'moksa-for-line' );
			} else {
				$hint = __( 'The AI service is present but has no provider connected yet, so it cannot answer anything.', 'moksa-for-line' );
			}

			$items[] = array(
				'done'  => $provider && $provider->is_available(),
				'label' => __( 'AI provider available', 'moksa-for-line' ),
				'hint'  => $hint,
				'fix'   => $tab( 'ai' ),
			);
		}

		if ( Options::get( 'pay_enabled' ) ) {
			$items[] = array(
				'done'  => \Mofoline\Pay\LinePayClient::is_configured(),
				'label' => __( 'LINE Pay credentials present', 'moksa-for-line' ),
				'hint'  => __( 'Add the LINE Pay Channel ID and Channel Secret.', 'moksa-for-line' ),
			'fix'   => $tab( 'pay' ),
			);

			$items[] = array(
				'done'  => ! Options::get( 'pay_sandbox' ),
				'label' => __( 'LINE Pay is in production mode', 'moksa-for-line' ),
				'hint'  => __( 'Sandbox mode is on, so no real money moves. Turn it off when you go live.', 'moksa-for-line' ),
			'fix'   => $tab( 'pay' ),
			);
		}

		if ( ! \Mofoline\Support\Crypto::available() ) {
			$items[] = array(
				'done'  => false,
				'label' => __( 'Credentials are encrypted at rest', 'moksa-for-line' ),
				'hint'  => __( 'OpenSSL with AES-256-GCM is not available on this server, so channel secrets are stored as plain text.', 'moksa-for-line' ),
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
		$messaging = \Mofoline\Api\TokenManager::is_configured();

		if ( $login || $messaging ) {
			return;
		}

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Moksa for LINE is installed but not connected to a LINE channel yet.', 'moksa-for-line' ),
			esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-settings' ) ),
			esc_html__( 'Finish setup', 'moksa-for-line' )
		);
	}

	// --- Settings ---------------------------------------------------------------------

	/**
	 * Persist the settings form.
	 */
	public function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'moksa-for-line' ), 403 );
		}

		check_admin_referer( 'mofoline_settings' );

		$tab       = Ajax::key( 'tab', 'general' );
		$schema    = Options::schema();
		$submitted = Ajax::fields_typed(
			'mofoline',
			static function ( string $field ) use ( $schema ): string {
				$type = (string) ( $schema[ $field ]['type'] ?? 'string' );

				if ( 'url' === $type ) {
					return 'url';
				}

				return 'text' === $type ? 'textarea' : 'text';
			}
		);

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
		foreach ( Ajax::keys( 'mofoline_booleans' ) as $key ) {
			if ( isset( $schema[ $key ] ) && 'bool' === $schema[ $key ]['type'] && ! isset( $submitted[ $key ] ) ) {
				Options::set( $key, false );
			}
		}

		// Order status checkboxes are a list rather than a single value, so
		// they are handled outside the schema loop. An empty submission on the
		// WooCommerce tab means "notify on nothing", which is a real choice.
		if ( 'woo' === $tab ) {
			Options::set( 'woo_notify_statuses', Ajax::keys( 'mofoline_woo_statuses' ) );
		}

		// The role for new accounts is chosen from a list of harmless roles;
		// a value from outside that list -- an old setting, or a hand-made
		// request -- falls back to the least a role can be.
		if ( isset( $submitted['new_user_role'] ) && ! isset( \Mofoline\Login\LoginModule::registration_roles()[ (string) $submitted['new_user_role'] ] ) ) {
			Options::set( 'new_user_role', 'subscriber' );
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
			set_transient( 'mofoline_settings_warning', $warnings, 60 );
		} else {
			delete_transient( 'mofoline_settings_warning' );
		}

		Options::flush_cache();

		// A changed messaging secret invalidates any cached token.
		\Mofoline\Api\TokenManager::forget();

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

		Ajax::bail( $current );

		$ours    = \Mofoline\Webhook\WebhookModule::endpoint_url();
		$matches = untrailingslashit( $current['endpoint'] ) === untrailingslashit( $ours );
		$lines   = array();

		if ( '' === $current['endpoint'] ) {
			$lines[] = __( 'This channel has no webhook URL set, so nothing anyone sends the bot reaches this site.', 'moksa-for-line' );
		} else {
			$lines[] = sprintf(
				/* translators: %s: webhook URL. */
				__( 'LINE is set to deliver to %s', 'moksa-for-line' ),
				$current['endpoint']
			);

			if ( ! $matches ) {
				$lines[] = sprintf(
					/* translators: %s: webhook URL. */
					__( 'That is not this site, which expects %s. Events are going somewhere else.', 'moksa-for-line' ),
					$ours
				);
			}

			$lines[] = $current['active']
				? __( 'Webhook delivery is switched on.', 'moksa-for-line' )
				: __( 'Webhook delivery is switched OFF in the Console, so LINE holds the URL but sends nothing to it.', 'moksa-for-line' );
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
					__( 'LINE delivered a test event and this site answered %d.', 'moksa-for-line' ),
					$status
				);
			} else {
				$lines[] = sprintf(
					/* translators: 1: LINE's reason code, 2: HTTP status code. */
					__( 'LINE could not deliver a test event: %1$s (HTTP %2$d).', 'moksa-for-line' ),
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
					? __( 'The channel is pointed at this site and delivery is on.', 'moksa-for-line' )
					: __( 'The channel is not delivering to this site.', 'moksa-for-line' ),
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

		$ours   = \Mofoline\Webhook\WebhookModule::endpoint_url();
		$result = MessagingClient::set_webhook_endpoint( $ours );

		Ajax::bail( $result );

		wp_send_json_success(
			array(
				'message' => __( 'LINE now delivers to this site. If delivery is still off, turn on "Use webhook" in the Console -- that switch has no API.', 'moksa-for-line' ),
			)
		);
	}

	private function webhook_guard(): void {
		Ajax::guard( __( 'You cannot change the webhook settings.', 'moksa-for-line' ) );

		// The extra one: there is no point asking LINE anything without the
		// credentials to ask with, and "unauthorised" would be the wrong thing
		// to say about it.
		if ( ! \Mofoline\Api\TokenManager::is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Fill in the Messaging API channel first.', 'moksa-for-line' ) ) );
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
		check_ajax_referer( 'mofoline_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot clear the log.', 'moksa-for-line' ) ), 403 );
		}

		$table = \Mofoline\Support\Logger::table();

		$removed = (int) Db::query( Db::prepare( "DELETE FROM %i", $table ) );

		wp_send_json_success(
			array(
				'removed' => $removed,
				'message' => sprintf(
					/* translators: %s: number of entries removed. */
					__( 'Cleared %s entries.', 'moksa-for-line' ),
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
		check_ajax_referer( 'mofoline_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot resend notifications.', 'moksa-for-line' ) ), 403 );
		}

		$order_id = Ajax::int( 'order_id' );
		$status   = Ajax::key( 'status' );

		if ( $order_id <= 0 || '' === $status ) {
			wp_send_json_error( array( 'message' => __( 'That row does not name an order and a status to resend.', 'moksa-for-line' ) ) );
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'moksa-for-line' ) ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'That order no longer exists.', 'moksa-for-line' ) ) );
		}

		// The duplicate guard is what stops a second send, so it has to be
		// cleared for this status before dispatch will do anything at all.
		\Mofoline\Woo\WooModule::reset_notified( $order, $status );

		$module = new \Mofoline\Woo\WooModule();
		$module->dispatch( $order_id, $status );

		wp_send_json_success( array( 'message' => __( 'Sent again. The result is in the list below.', 'moksa-for-line' ) ) );
	}

	public function ajax_broadcast(): void {
		check_ajax_referer( 'mofoline_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot send broadcasts.', 'moksa-for-line' ) ), 403 );
		}

		$mode     = Ajax::key( 'mode', 'test' );
		$body     = Ajax::textarea( 'message' );
		$flex_id  = Ajax::int( 'flex_id' );
		$target   = Ajax::text( 'line_user_id' );

		$messages = array();

		if ( $flex_id > 0 ) {
			$template = \Mofoline\Data\Flex::find( $flex_id );
			$contents = \Mofoline\Data\Flex::contents( $flex_id );

			if ( ! $template || ! $contents ) {
				wp_send_json_error( array( 'message' => __( 'That template could not be loaded.', 'moksa-for-line' ) ) );
			}

			$messages[] = \Mofoline\Api\MessagingClient::flex( (string) $template->alt_text, $contents );
		}

		if ( '' !== trim( $body ) ) {
			array_unshift( $messages, \Mofoline\Api\MessagingClient::text( $body ) );
		}

		if ( empty( $messages ) ) {
			wp_send_json_error( array( 'message' => __( 'Write a message or choose a template first.', 'moksa-for-line' ) ) );
		}

		if ( 'test' === $mode ) {
			if ( '' === $target ) {
				wp_send_json_error( array( 'message' => __( 'Enter a LINE user id to send the test to.', 'moksa-for-line' ) ) );
			}

			$result = \Mofoline\Api\MessagingClient::push( $target, $messages );

			Ajax::bail( $result );

			wp_send_json_success( array( 'message' => __( 'Test sent.', 'moksa-for-line' ) ) );
		}

		// Sending to everyone needs the operator to type the confirmation word,
		// because there is no undo and it costs one message per friend.
		$confirmation = Ajax::text( 'confirm' );

		if ( 'SEND' !== strtoupper( $confirmation ) ) {
			wp_send_json_error( array( 'message' => __( 'Type SEND in the confirmation box to go ahead.', 'moksa-for-line' ) ) );
		}

		if ( 'all' === $mode ) {
			$result = \Mofoline\Api\MessagingClient::broadcast( $messages );

			Ajax::bail( $result );

			wp_send_json_success( array( 'message' => __( 'Broadcast sent to every friend of the account.', 'moksa-for-line' ) ) );
		}

		// 'known' sends only to the friends this site has actually recorded,
		// which is a smaller and more predictable bill than a full broadcast.
		$recipients = \Mofoline\Data\Users::friend_ids();

		if ( empty( $recipients ) ) {
			wp_send_json_error( array( 'message' => __( 'No friends have been recorded on this site yet.', 'moksa-for-line' ) ) );
		}

		$report = \Mofoline\Api\MessagingClient::multicast( $recipients, $messages );

		if ( $report['failed'] > 0 ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: delivered count, 2: failed count, 3: first error. */
						__( 'Delivered to %1$d, failed for %2$d. %3$s', 'moksa-for-line' ),
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
					__( 'Sent to %d people.', 'moksa-for-line' ),
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
			wp_die( esc_html__( 'You do not have permission to view this page.', 'moksa-for-line' ), 403 );
		}

		$path = MOFOLINE_DIR . 'views/' . $name . '.php';

		if ( ! is_readable( $path ) ) {
			printf( '<div class="wrap"><p>%s</p></div>', esc_html__( 'This screen is missing.', 'moksa-for-line' ) );

			return;
		}

		include $path;
	}
}
