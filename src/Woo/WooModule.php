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

	public function register(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 10, 4 );
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

		$statuses = (array) Options::get( 'woo_notify_statuses' );

		if ( ! in_array( $new_status, $statuses, true ) ) {
			return;
		}

		$line_user_id = $this->line_id_for_order( $order );

		if ( '' === $line_user_id ) {
			return;
		}

		$message = $this->build_order_message( $order, $new_status );

		/**
		 * Filter the order notification before it is sent.
		 *
		 * @param array     $message      Flex message object.
		 * @param \WC_Order $order        Order.
		 * @param string    $new_status   New status slug.
		 * @param string    $line_user_id Recipient.
		 */
		$message = apply_filters( 'moksa_line_order_message', $message, $order, $new_status, $line_user_id );

		if ( empty( $message ) ) {
			return;
		}

		$result = MessagingClient::push(
			$line_user_id,
			array( $message ),
			// Keyed on order and status: a hook that fires twice, or a status
			// toggled back and forth, will not message the customer again.
			array( 'retry_key' => 'order-' . $order_id . '-' . $new_status )
		);

		if ( Logger::capture( $result, 'Could not deliver an order notification', 'woo' ) ) {
			return;
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: order status label. */
				__( 'LINE notification sent for status: %s', 'moksa-line-login' ),
				wc_get_order_status_name( $new_status )
			)
		);
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
					__( 'Order %s', 'moksa-line-login' ),
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
				array( 'type' => 'text', 'text' => __( 'Total', 'moksa-line-login' ), 'size' => 'sm', 'color' => '#888888' ),
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
			'footer' => array(
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
							'label' => __( 'View order', 'moksa-line-login' ),
							'uri'   => $order->get_view_order_url(),
						),
					),
				),
			),
		);

		return MessagingClient::flex(
			sprintf(
				/* translators: 1: order number, 2: status label. */
				__( 'Order %1$s: %2$s', 'moksa-line-login' ),
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
			esc_html__( 'LINE account', 'moksa-line-login' ),
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

		$items[ self::ENDPOINT ] = __( 'LINE', 'moksa-line-login' );

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
				esc_html__( 'Your LINE account is linked. Order updates will be sent to you on LINE.', 'moksa-line-login' ),
				(int) get_current_user_id(),
				esc_attr( wp_create_nonce( 'moksa_line_link' ) ),
				esc_html__( 'Unlink', 'moksa-line-login' )
			);

			// The unlink control needs the admin script's handler.
			wp_enqueue_script( 'moksa-line-admin', MOKSA_LINE_URL . 'assets/js/admin.js', array( 'jquery' ), MOKSA_LINE_VERSION, true );
			wp_localize_script(
				'moksa-line-admin',
				'moksaLine',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'moksa_line_admin' ),
					'strings' => array( 'confirmDelete' => __( 'Unlink your LINE account?', 'moksa-line-login' ) ),
				)
			);

			return;
		}

		printf(
			'<p>%s</p><p><a class="moksa-line-button" href="%s">%s</a></p>',
			esc_html__( 'Link your LINE account to get order updates in LINE and to sign in with one tap.', 'moksa-line-login' ),
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
			esc_html__( 'Link my LINE account', 'moksa-line-login' )
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
			esc_html__( 'Continue with LINE', 'moksa-line-login' )
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
			esc_html__( 'Already shopped with us?', 'moksa-line-login' ),
			esc_url(
				add_query_arg(
					array(
						'action'      => 'moksa_line_start',
						'redirect_to' => rawurlencode( wc_get_checkout_url() ),
					),
					admin_url( 'admin-ajax.php' )
				)
			),
			esc_html__( 'Sign in with LINE', 'moksa-line-login' )
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
