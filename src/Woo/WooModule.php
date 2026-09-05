<?php
/**
 * WooCommerce integration: order notifications, account linking, login buttons.
 *
 * Order notifications are push messages, so they are billed and they are sent
 * once per order and status. The retry key is derived from the order and the
 * status, which means a status that flips back and forth, or a plugin that
 * fires the hook twice, cannot spam the customer.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Woo;

use Moksa\Line\Data\Users;
use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Login\LoginModule;
use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;

defined( 'ABSPATH' ) || exit;

class WooModule {

	const ENDPOINT = 'line-account';
	const META_KEY = '_moksa_line_user_id';

	/** Which statuses this order has already been notified about. */
	const META_SENT = '_moksa_line_notified';

	/** How many times we have waited for a tracking number on this order. */
	const META_ATTEMPTS = '_moksa_line_notify_attempts';

	/**
	 * @var NotifyTemplates
	 */
	private $templates;

	public function __construct() {
		$this->templates = new NotifyTemplates();
	}

	public function register(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->templates->register();

		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 10, 4 );
		add_action( 'moksa_line_order_notify', array( $this, 'dispatch' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'store_line_id_on_order' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_line_id_in_admin' ) );

		if ( Options::get( 'woo_account_tab' ) ) {
			add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_item' ) );
			add_action( 'init', array( $this, 'add_endpoint' ) );
			add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_account_page' ) );
			add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
		}

		if ( Options::get( 'woo_login_buttons' ) ) {
			add_action( 'woocommerce_login_form_start', array( $this, 'render_login_button' ) );
			add_action( 'woocommerce_register_form_start', array( $this, 'render_login_button' ) );
			add_action( 'woocommerce_before_checkout_form', array( $this, 'render_checkout_prompt' ), 5 );
		}
	}

	// --- Notifications ------------------------------------------------------------

	/**
	 * Notify the customer in LINE when their order moves on.
	 *
	 * @param int       $order_id   Order id.
	 * @param string    $old_status Previous status.
	 * @param string    $new_status New status.
	 * @param \WC_Order $order      Order object.
	 */
	public function on_status_changed( $order_id, $old_status, $new_status, $order ): void {
		if ( ! Options::get( 'woo_notify' ) ) {
			return;
		}

		if ( ! $this->status_is_notifiable( $new_status, $order ) ) {
			return;
		}

		// Shipping notifications are worth holding briefly: the logistics
		// plugin usually writes the tracking number a moment after the status
		// changes, and a "your order has shipped" message with no tracking
		// number in it is the one thing customers write in about.
		if ( $this->awaiting_tracking( $order, $new_status ) ) {
			$this->schedule( (int) $order_id, $new_status, (int) Options::get( 'woo_tracking_delay' ) );

			return;
		}

		$delay = max( 0, (int) Options::get( 'woo_notify_delay' ) );

		if ( $delay > 0 ) {
			$this->schedule( (int) $order_id, $new_status, $delay );

			return;
		}

		$this->dispatch( (int) $order_id, $new_status );
	}

	/**
	 * Send the notification for an order and status.
	 *
	 * Also the cron callback, so everything that must be true before a message
	 * goes out is checked here rather than at scheduling time -- by the time a
	 * delayed job runs, the order may have moved on again.
	 *
	 * @param int    $order_id Order id.
	 * @param string $status   Status slug being notified about.
	 */
	public function dispatch( $order_id, $status ): void {
		$order_id = (int) $order_id;
		$status   = (string) $status;

		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || ! Options::get( 'woo_notify' ) ) {
			return;
		}

		// One notification per order per status. The previous plugin shipped a
		// class for this that nothing ever called, so a status set twice sent
		// the customer two messages.
		if ( $this->already_notified( $order, $status ) ) {
			return;
		}

		// Still waiting on a tracking number, and retries left?
		if ( $this->awaiting_tracking( $order, $status ) ) {
			$attempts = (int) $order->get_meta( self::META_ATTEMPTS );
			$max      = max( 0, (int) Options::get( 'woo_tracking_retries' ) );

			if ( $attempts < $max ) {
				$order->update_meta_data( self::META_ATTEMPTS, $attempts + 1 );
				$order->save();

				$this->schedule( $order_id, $status, (int) Options::get( 'woo_tracking_delay' ) );

				return;
			}

			// Out of retries: send without the tracking number rather than
			// never telling the customer anything.
			Logger::info(
				'Sending an order notification without a tracking number after exhausting retries',
				array( 'order_id' => $order_id, 'attempts' => $attempts ),
				'woo'
			);
		}

		$order->delete_meta_data( self::META_ATTEMPTS );

		$line_user_id = $this->line_id_for_order( $order );

		if ( '' === $line_user_id ) {
			return;
		}

		$messages = $this->messages_for( $order, $status, $line_user_id );

		if ( empty( $messages ) ) {
			return;
		}

		$recipient = trim(
			$order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
			. ' (' . $order->get_billing_email() . ')'
		);

		$delivered = 0;

		foreach ( $messages as $template_id => $message ) {
			$history_id = NotifyHistory::begin(
				array(
					'wp_user_id'   => (int) $order->get_customer_id(),
					'line_user_id' => $line_user_id,
					'recipient'    => $recipient,
					'order_id'     => $order_id,
					'template_id'  => (int) $template_id,
					'order_status' => $status,
					'content'      => (string) wp_json_encode( $message, JSON_UNESCAPED_UNICODE ),
				)
			);

			$result = MessagingClient::push(
				$line_user_id,
				array( $message ),
				// Keyed on order, status and template so LINE also refuses a
				// duplicate if this runs twice within its retry window.
				array( 'retry_key' => sprintf( 'order-%d-%s-%d', $order_id, $status, (int) $template_id ) )
			);

			// The LINE push endpoint answers 200 with an empty body, so success
			// is "not a WP_Error". The previous plugin looked for a status key
			// that is never present and recorded every successful send as
			// failed.
			$failed = is_wp_error( $result );

			NotifyHistory::settle( $history_id, ! $failed, $failed ? $result->get_error_message() : '' );

			if ( $failed ) {
				Logger::capture( $result, 'Could not deliver an order notification', 'woo' );
				continue;
			}

			++$delivered;
		}

		if ( 0 === $delivered ) {
			return;
		}

		$this->mark_notified( $order, $status );

		$order->add_order_note(
			sprintf(
				/* translators: 1: number of messages, 2: order status label. */
				_n(
					'%1$d LINE notification sent for status: %2$s',
					'%1$d LINE notifications sent for status: %2$s',
					$delivered,
					'moksa-line'
				),
				$delivered,
				wc_get_order_status_name( $status )
			)
		);
	}

	/**
	 * The messages to send for this order and status.
	 *
	 * Templates take precedence; the built-in card is the fallback for shops
	 * that have not written any, so notifications work out of the box.
	 *
	 * @param \WC_Order $order        Order.
	 * @param string    $status       Status slug.
	 * @param string    $line_user_id Recipient.
	 * @return array<int,array> Template id (0 for the built-in card) => message.
	 */
	private function messages_for( $order, string $status, string $line_user_id ): array {
		$messages = array();

		foreach ( NotifyTemplates::for_status( $status, $order ) as $template_id ) {
			$message = NotifyTemplates::render( $template_id, $order, $status );

			if ( null !== $message ) {
				$messages[ $template_id ] = $message;
			}
		}

		if ( empty( $messages ) && in_array( $status, (array) Options::get( 'woo_notify_statuses' ), true ) ) {
			$messages[0] = $this->build_order_message( $order, $status );
		}

		/**
		 * Filter the order notifications before they are sent.
		 *
		 * @param array     $messages     Template id => flex message.
		 * @param \WC_Order $order        Order.
		 * @param string    $status       Status slug.
		 * @param string    $line_user_id Recipient.
		 */
		return (array) apply_filters( 'moksa_line_order_messages', $messages, $order, $status, $line_user_id );
	}

	/**
	 * Whether anything is configured to fire for this status.
	 *
	 * @param string    $status Status slug.
	 * @param \WC_Order $order  Order.
	 */
	private function status_is_notifiable( string $status, $order ): bool {
		if ( in_array( $status, (array) Options::get( 'woo_notify_statuses' ), true ) ) {
			return true;
		}

		return ! empty( NotifyTemplates::for_status( $status, $order ) );
	}

	/**
	 * Whether this notification should wait for a tracking number.
	 *
	 * @param \WC_Order $order  Order.
	 * @param string    $status Status slug.
	 */
	private function awaiting_tracking( $order, string $status ): bool {
		if ( ! Options::get( 'woo_wait_for_tracking' ) ) {
			return false;
		}

		if ( $status !== (string) Options::get( 'woo_tracking_status' ) ) {
			return false;
		}

		if ( (int) Options::get( 'woo_tracking_retries' ) <= 0 ) {
			return false;
		}

		return '' === OrderContext::tracking_number( $order );
	}

	/**
	 * Queue a notification.
	 *
	 * @param int    $order_id Order id.
	 * @param string $status   Status slug.
	 * @param int    $delay    Seconds to wait.
	 */
	private function schedule( int $order_id, string $status, int $delay ): void {
		$args = array( $order_id, $status );

		// Never stack two jobs for the same order and status.
		if ( wp_next_scheduled( 'moksa_line_order_notify', $args ) ) {
			return;
		}

		wp_schedule_single_event( time() + max( 5, $delay ), 'moksa_line_order_notify', $args );
	}

	/**
	 * Whether this order has already been notified about this status.
	 *
	 * @param \WC_Order $order  Order.
	 * @param string    $status Status slug.
	 */
	private function already_notified( $order, string $status ): bool {
		$sent = $order->get_meta( self::META_SENT );

		return is_array( $sent ) && isset( $sent[ $status ] );
	}

	/**
	 * Record that this status has been notified about.
	 *
	 * @param \WC_Order $order  Order.
	 * @param string    $status Status slug.
	 */
	private function mark_notified( $order, string $status ): void {
		$sent = $order->get_meta( self::META_SENT );
		$sent = is_array( $sent ) ? $sent : array();

		$sent[ $status ] = current_time( 'mysql', true );

		$order->update_meta_data( self::META_SENT, $sent );
		$order->save();
	}

	/**
	 * Allow a status to be notified about again, for resends.
	 *
	 * @param \WC_Order $order  Order.
	 * @param string    $status Status slug, or '' to clear them all.
	 */
	public static function reset_notified( $order, string $status = '' ): void {
		if ( '' === $status ) {
			$order->delete_meta_data( self::META_SENT );
		} else {
			$sent = $order->get_meta( self::META_SENT );

			if ( is_array( $sent ) ) {
				unset( $sent[ $status ] );
				$order->update_meta_data( self::META_SENT, $sent );
			}
		}

		$order->save();
	}

	/**
	 * Build the order card.
	 *
	 * @param \WC_Order $order  Order.
	 * @param string    $status New status slug.
	 * @return array
	 */
	private function build_order_message( $order, string $status ): array {
		$status_label = wc_get_order_status_name( $status );

		$rows = array();

		foreach ( $order->get_items() as $item ) {
			if ( count( $rows ) >= 6 ) {
				break;
			}

			$rows[] = array(
				'type'     => 'box',
				'layout'   => 'horizontal',
				'contents' => array(
					array(
						'type'  => 'text',
						'text'  => mb_substr( wp_strip_all_tags( (string) $item->get_name() ), 0, 40 ),
						'size'  => 'sm',
						'color' => '#555555',
						'wrap'  => true,
						'flex'  => 4,
					),
					array(
						'type'  => 'text',
						'text'  => 'x' . (int) $item->get_quantity(),
						'size'  => 'sm',
						'color' => '#888888',
						'align' => 'end',
						'flex'  => 1,
					),
				),
			);
		}

		$body = array(
			array(
				'type'  => 'text',
				'text'  => $status_label,
				'size'  => 'sm',
				'color' => '#06C755',
				'weight' => 'bold',
			),
			array(
				'type'   => 'text',
				'text'   => sprintf(
					/* translators: %s: order number. */
					__( 'Order %s', 'moksa-line' ),
					(string) $order->get_order_number()
				),
				'weight' => 'bold',
				'size'   => 'lg',
				'margin' => 'sm',
			),
			array( 'type' => 'separator', 'margin' => 'md' ),
		);

		if ( ! empty( $rows ) ) {
			$body[] = array(
				'type'     => 'box',
				'layout'   => 'vertical',
				'margin'   => 'md',
				'spacing'  => 'sm',
				'contents' => $rows,
			);
		}

		$body[] = array(
			'type'     => 'box',
			'layout'   => 'horizontal',
			'margin'   => 'md',
			'contents' => array(
				array( 'type' => 'text', 'text' => __( 'Total', 'moksa-line' ), 'size' => 'sm', 'color' => '#888888' ),
				array(
					'type'   => 'text',
					'text'   => wp_strip_all_tags( (string) $order->get_formatted_order_total() ),
					'size'   => 'sm',
					'align'  => 'end',
					'weight' => 'bold',
				),
			),
		);

		$bubble = array(
			'type' => 'bubble',
			'body' => array(
				'type'     => 'box',
				'layout'   => 'vertical',
				'contents' => $body,
			),
		);

		// LINE only accepts https, tel: and its own schemes in a uri action, so
		// a site served over plain http cannot carry a link. Dropping just the
		// button keeps the notification itself deliverable -- attaching it
		// anyway makes LINE reject the whole message, and the customer hears
		// nothing at all about their order.
		$view_url = (string) $order->get_view_order_url();

		if ( 0 === strpos( $view_url, 'https://' ) ) {
			$bubble['footer'] = array(
				'type'     => 'box',
				'layout'   => 'vertical',
				'contents' => array(
					array(
						'type'   => 'button',
						'style'  => 'primary',
						'color'  => '#06C755',
						'height' => 'sm',
						'action' => array(
							'type'  => 'uri',
							'label' => __( 'View order', 'moksa-line' ),
							'uri'   => $view_url,
						),
					),
				),
			);
		} else {
			Logger::warning(
				'Order notifications are being sent without a "View order" button because this site is not served over https, which LINE requires for links.',
				array( 'url' => $view_url ),
				'woo'
			);
		}

		return MessagingClient::flex(
			sprintf(
				/* translators: 1: order number, 2: status label. */
				__( 'Order %1$s: %2$s', 'moksa-line' ),
				(string) $order->get_order_number(),
				$status_label
			),
			$bubble
		);
	}

	/**
	 * Which LINE identity should hear about this order.
	 *
	 * The id captured at checkout wins, because a guest who logged in with
	 * LINE at that moment may not have a lasting WordPress account.
	 *
	 * @param \WC_Order $order Order.
	 */
	private function line_id_for_order( $order ): string {
		$stored = (string) $order->get_meta( self::META_KEY );

		if ( '' !== $stored ) {
			return $stored;
		}

		$customer_id = (int) $order->get_customer_id();

		if ( $customer_id > 0 ) {
			$record = Users::by_wp_id( $customer_id );

			if ( $record && '' !== (string) $record->line_user_id ) {
				return (string) $record->line_user_id;
			}
		}

		return '';
	}

	/**
	 * Record the buyer's LINE identity on the order at checkout, so the order
	 * remains contactable even if the account is later unlinked.
	 *
	 * @param int   $order_id Order id.
	 * @param array $data     Checkout data.
	 */
	public function store_line_id_on_order( $order_id, $data ): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$record = Users::by_wp_id( get_current_user_id() );

		if ( ! $record || '' === (string) $record->line_user_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( self::META_KEY, (string) $record->line_user_id );
		$order->save();
	}

	/**
	 * Show the LINE identity on the admin order screen.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function show_line_id_in_admin( $order ): void {
		$line_user_id = $this->line_id_for_order( $order );

		if ( '' === $line_user_id ) {
			return;
		}

		$record = Users::by_line_id( $line_user_id );

		printf(
			'<p><strong>%s:</strong><br />%s<br /><code>%s</code></p>',
			esc_html__( 'LINE account', 'moksa-line' ),
			esc_html( $record ? (string) $record->display_name : '' ),
			esc_html( $line_user_id )
		);
	}

	// --- My Account -----------------------------------------------------------------

	/**
	 * Add the endpoint rewrite.
	 */
	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * Register the query var with WooCommerce.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;

		return $vars;
	}

	/**
	 * Add the tab to the My Account menu, before Logout.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function account_menu_item( $items ) {
		$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;

		unset( $items['customer-logout'] );

		$items[ self::ENDPOINT ] = __( 'LINE', 'moksa-line' );

		if ( null !== $logout ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	/**
	 * Render the LINE tab.
	 */
	public function render_account_page(): void {
		$record = Users::by_wp_id( get_current_user_id() );

		wp_enqueue_style( 'moksa-line-front' );

		if ( $record && '' !== (string) $record->line_user_id ) {
			printf(
				'<div class="moksa-line-profile">%s<span class="moksa-line-profile__name">%s</span></div>',
				'' !== (string) $record->picture_url
					? sprintf(
						'<img class="moksa-line-profile__avatar" src="%s" alt="" width="48" height="48" loading="lazy" />',
						esc_url( (string) $record->picture_url )
					)
					: '',
				esc_html( (string) $record->display_name )
			);

			printf(
				'<p>%s</p><p><button type="button" class="button" data-moksa-line-unlink="%d" data-nonce="%s">%s</button></p>',
				esc_html__( 'Your LINE account is linked. Order updates will be sent to you on LINE.', 'moksa-line' ),
				(int) get_current_user_id(),
				esc_attr( wp_create_nonce( 'moksa_line_link' ) ),
				esc_html__( 'Unlink', 'moksa-line' )
			);

			// The unlink control needs the admin script's handler.
			wp_enqueue_script( 'moksa-line-admin', MOKSA_LINE_URL . 'assets/js/admin.js', array( 'jquery' ), MOKSA_LINE_VERSION, true );
			wp_localize_script(
				'moksa-line-admin',
				'moksaLine',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'moksa_line_admin' ),
					'strings' => array( 'confirmDelete' => __( 'Unlink your LINE account?', 'moksa-line' ) ),
				)
			);

			return;
		}

		printf(
			'<p>%s</p><p><a class="moksa-line-button" href="%s">%s</a></p>',
			esc_html__( 'Link your LINE account to get order updates in LINE and to sign in with one tap.', 'moksa-line' ),
			esc_url(
				add_query_arg(
					array(
						'action'      => 'moksa_line_start',
						'link'        => 1,
						'_wpnonce'    => wp_create_nonce( 'moksa_line_link' ),
						'redirect_to' => rawurlencode( wc_get_account_endpoint_url( self::ENDPOINT ) ),
					),
					admin_url( 'admin-ajax.php' )
				)
			),
			esc_html__( 'Link my LINE account', 'moksa-line' )
		);
	}

	// --- Login buttons -----------------------------------------------------------------

	/**
	 * A LINE button above the WooCommerce login and register forms.
	 */
	public function render_login_button(): void {
		if ( is_user_logged_in() || '' === (string) Options::get( 'channel_id' ) ) {
			return;
		}

		wp_enqueue_style( 'moksa-line-front' );

		printf(
			'<p class="moksa-line-woo-login"><a class="moksa-line-button" href="%s">%s</a></p>',
			esc_url(
				add_query_arg(
					array(
						'action'      => 'moksa_line_start',
						'redirect_to' => rawurlencode( wc_get_page_permalink( 'myaccount' ) ),
					),
					admin_url( 'admin-ajax.php' )
				)
			),
			esc_html__( 'Continue with LINE', 'moksa-line' )
		);
	}

	/**
	 * Offer LINE sign-in at the top of checkout, so a returning customer does
	 * not retype an address they have already given us.
	 */
	public function render_checkout_prompt(): void {
		if ( is_user_logged_in() || '' === (string) Options::get( 'channel_id' ) ) {
			return;
		}

		wp_enqueue_style( 'moksa-line-front' );

		printf(
			'<div class="woocommerce-info moksa-line-checkout-prompt">%s <a class="moksa-line-button moksa-line-button--small" href="%s">%s</a></div>',
			esc_html__( 'Already shopped with us?', 'moksa-line' ),
			esc_url(
				add_query_arg(
					array(
						'action'      => 'moksa_line_start',
						'redirect_to' => rawurlencode( wc_get_checkout_url() ),
					),
					admin_url( 'admin-ajax.php' )
				)
			),
			esc_html__( 'Sign in with LINE', 'moksa-line' )
		);
	}

	/**
	 * Order statuses offered on the settings screen.
	 *
	 * @return array<string,string>
	 */
	public static function statuses(): array {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}

		$statuses = array();

		foreach ( wc_get_order_statuses() as $slug => $label ) {
			$statuses[ str_replace( 'wc-', '', $slug ) ] = $label;
		}

		return $statuses;
	}
}
