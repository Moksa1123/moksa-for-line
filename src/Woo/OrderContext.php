<?php
/**
 * Order data for notification templates.
 *
 * Resolves the {placeholder} set a template can use, and knows where Taiwanese
 * logistics plugins hide the things WooCommerce itself does not model --
 * tracking numbers and convenience-store pickup details. Those meta keys are
 * the accumulated result of integrating with ECPay, RY Tools, AST and
 * WooCommerce Shipment Tracking, and are worth more than the code around them.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Woo;

defined( 'ABSPATH' ) || exit;

class OrderContext {

	/**
	 * Meta keys that may hold a shipment tracking number, in priority order.
	 *
	 * @var string[]
	 */
	private static $tracking_keys = array(
		'_shipping_tracking_number',   // Advanced Shipment Tracking.
		'_ecpay_logistics_id',         // ECPay logistics.
		'ry_tracking_number',          // RY Tools.
		'_tracking_number',            // Generic.
		'_wc_shipment_tracking_items', // WooCommerce Shipment Tracking (array).
		'_tracking_provider',
		'_tracking_link',
	);

	/**
	 * Meta keys holding a pickup store name.
	 *
	 * @var string[]
	 */
	private static $store_name_keys = array(
		'_shipping_store_name',
		'_ecpay_receiver_store_name',
		'ry_store_name',
	);

	/**
	 * Meta keys holding a pickup store address.
	 *
	 * @var string[]
	 */
	private static $store_address_keys = array(
		'_shipping_store_address',
		'_ecpay_receiver_store_address',
	);

	/**
	 * Make a WooCommerce price string readable in a chat.
	 *
	 * wc_price() returns markup whose currency symbol is HTML entities.
	 * Stripping the tags leaves those entities behind, so an order total went
	 * out to customers as "&#78;&#84;&#36;1,000" instead of "NT$1,000" -- in
	 * the notification, in the order card, and in the {total} placeholder.
	 *
	 * @param string $formatted Output of wc_price() or get_formatted_order_total().
	 * @return string
	 */
	public static function clean_money( string $formatted ): string {
		$text = html_entity_decode( wp_strip_all_tags( $formatted ), ENT_QUOTES, 'UTF-8' );

		// wc_price() separates the symbol from the number with a non-breaking
		// space, which reads as a stray character in some LINE clients.
		$text = str_replace( "\xc2\xa0", ' ', $text );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Format an amount and clean it in one step.
	 *
	 * @param float|string $amount Raw amount.
	 * @return string
	 */
	public static function money( $amount ): string {
		if ( '' === $amount || null === $amount ) {
			return '';
		}

		return self::clean_money( (string) wc_price( (float) $amount ) );
	}

	/**
	 * Build the replacement map for an order.
	 *
	 * @param \WC_Order $order  Order.
	 * @param string    $status Status slug being notified about.
	 * @return array<string,string> Placeholder (with braces) => value.
	 */
	public static function placeholders( $order, string $status ): array {
		$billing_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$shipping_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );

		$shipping_address = trim(
			trim( $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() )
			. ' ' . $order->get_shipping_city()
		);

		$billing_address = trim(
			trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() )
			. ' ' . $order->get_billing_city()
		);

		$values = array(
			'order_number'      => (string) $order->get_order_number(),
			'order_status'      => $status,
			'status_label'      => wc_get_order_status_name( $status ),
			'order_date'        => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d' ) : '',
			'total'             => self::clean_money( (string) $order->get_formatted_order_total() ),
			'order_subtotal'    => self::money( (float) $order->get_subtotal() ),
			'items_count'       => (string) $order->get_item_count(),
			'order_items_nums'  => (string) $order->get_item_count(),
			'order_items'       => self::item_list( $order ),
			'customer_full_name' => $billing_name,
			'customer_email'    => (string) $order->get_billing_email(),
			'billing_name'      => $billing_name,
			'billing_phone'     => (string) $order->get_billing_phone(),
			'billing_address'   => $billing_address,
			'shipping_name'     => '' !== $shipping_name ? $shipping_name : $billing_name,
			'shipping_address'  => '' !== $shipping_address ? $shipping_address : $billing_address,
			'shipping_city'     => (string) $order->get_shipping_city(),
			'shipping_method'   => wp_strip_all_tags( (string) $order->get_shipping_method() ),
			'payment_method'    => (string) $order->get_payment_method_title(),
			'tracking_number'   => self::tracking_number( $order ),
			'store_name'        => self::first_meta( $order, self::$store_name_keys ),
			'store_address'     => self::first_meta( $order, self::$store_address_keys ),
			'customer_note'     => (string) $order->get_customer_note(),
			'view_order_url'    => (string) $order->get_view_order_url(),
			'status_color'      => self::status_colour( $status ),
		);

		/**
		 * Filter the values available to notification templates.
		 *
		 * @param array     $values Placeholder name => value, without braces.
		 * @param \WC_Order $order  Order.
		 * @param string    $status Status slug.
		 */
		$values = apply_filters( 'moksa_line_order_placeholders', $values, $order, $status );

		$map = array();

		foreach ( $values as $name => $value ) {
			$map[ '{' . $name . '}' ] = is_scalar( $value ) ? (string) $value : '';
		}

		return $map;
	}

	/**
	 * Substitute placeholders into a template.
	 *
	 * The template is Flex JSON, so replacements are JSON-escaped before they
	 * go in. Without that, a customer whose name contains a quote produces
	 * invalid JSON and the notification silently fails to send.
	 *
	 * @param string    $template_json Raw template JSON.
	 * @param \WC_Order $order         Order.
	 * @param string    $status        Status slug.
	 * @return array|null Decoded message, or null when the result is not valid JSON.
	 */
	public static function render( string $template_json, $order, string $status ): ?array {
		$map      = self::placeholders( $order, $status );
		$escaped  = array();

		foreach ( $map as $placeholder => $value ) {
			// json_encode a string yields it quoted; trim the quotes to get an
			// escaped fragment safe to splice into the JSON.
			$encoded = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$escaped[ $placeholder ] = false === $encoded ? '' : trim( (string) $encoded, '"' );
		}

		$rendered = strtr( $template_json, $escaped );
		$decoded  = json_decode( $rendered, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * A readable list of what was ordered.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function item_list( $order ): string {
		$lines = array();

		foreach ( $order->get_items() as $item ) {
			if ( count( $lines ) >= 10 ) {
				$lines[] = '...';
				break;
			}

			$lines[] = sprintf(
				'%s x%d',
				wp_strip_all_tags( (string) $item->get_name() ),
				(int) $item->get_quantity()
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * The shipment tracking number, wherever the logistics plugin put it.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function tracking_number( $order ): string {
		foreach ( self::$tracking_keys as $key ) {
			$value = $order->get_meta( $key, true );

			if ( is_array( $value ) && ! empty( $value ) ) {
				// WooCommerce Shipment Tracking stores a list of shipments.
				if ( isset( $value[0]['tracking_number'] ) ) {
					return (string) $value[0]['tracking_number'];
				}

				if ( isset( $value['tracking_number'] ) ) {
					return (string) $value['tracking_number'];
				}

				$first = reset( $value );

				if ( is_string( $first ) && '' !== $first ) {
					return $first;
				}

				continue;
			}

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		/**
		 * Supply a tracking number from a source this plugin does not know.
		 *
		 * @param string    $tracking_number Empty when nothing was found.
		 * @param \WC_Order $order           Order.
		 */
		return (string) apply_filters( 'moksa_line_order_tracking_number', '', $order );
	}

	/**
	 * First non-empty value across a list of meta keys.
	 *
	 * @param \WC_Order $order Order.
	 * @param string[]  $keys  Meta keys in priority order.
	 */
	private static function first_meta( $order, array $keys ): string {
		foreach ( $keys as $key ) {
			$value = $order->get_meta( $key, true );

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return '';
	}

	/**
	 * A colour that matches the mood of the status.
	 *
	 * @param string $status Status slug.
	 */
	public static function status_colour( string $status ): string {
		if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
			return '#FF9800';
		}

		if ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
			return '#FF334B';
		}

		return '#06C755';
	}

	/**
	 * The same placeholders, in the groups a person looks for them in.
	 *
	 * A flat list of twenty-four is a list you read once and then hunt through.
	 * The groups are also where another plugin's values land: anything added
	 * through the moksa_line_order_placeholders filter and described through
	 * moksa_line_documented_placeholders appears under "From other plugins",
	 * so a shop can see what its own extensions provide without reading code.
	 *
	 * @return array<string,array<string,string>> Group label => placeholder => description.
	 */
	public static function documented_groups(): array {
		$groups = array(
			__( 'The order', 'moksa-line' )     => array(
				'{order_number}', '{order_status}', '{status_label}', '{status_color}',
				'{order_date}', '{total}', '{order_subtotal}', '{items_count}', '{order_items}',
				'{view_order_url}',
			),
			__( 'The customer', 'moksa-line' )  => array(
				'{customer_full_name}', '{customer_email}', '{billing_name}',
				'{billing_phone}', '{billing_address}', '{customer_note}',
			),
			__( 'Delivery', 'moksa-line' )      => array(
				'{shipping_name}', '{shipping_address}', '{shipping_city}',
				'{shipping_method}', '{tracking_number}', '{store_name}', '{store_address}',
			),
			__( 'Payment', 'moksa-line' )       => array(
				'{payment_method}',
			),
		);

		$documented = self::documented();
		$out        = array();
		$placed     = array();

		foreach ( $groups as $label => $keys ) {
			foreach ( $keys as $key ) {
				if ( isset( $documented[ $key ] ) ) {
					$out[ $label ][ $key ] = $documented[ $key ];
					$placed[ $key ]        = true;
				}
			}
		}

		$extra = array_diff_key( $documented, $placed );

		if ( $extra ) {
			$out[ __( 'From other plugins', 'moksa-line' ) ] = $extra;
		}

		return $out;
	}

	/**
	 * Every placeholder, for the editor's reference panel.
	 *
	 * @return array<string,string> Placeholder => description.
	 */
	public static function documented(): array {
		$documented = array(
			'{order_number}'       => __( 'Order number', 'moksa-line' ),
			'{order_status}'       => __( 'Status slug', 'moksa-line' ),
			'{status_label}'       => __( 'Status name, translated', 'moksa-line' ),
			'{status_color}'       => __( 'A colour matching the status', 'moksa-line' ),
			'{order_date}'         => __( 'Date the order was placed', 'moksa-line' ),
			'{total}'              => __( 'Order total, formatted', 'moksa-line' ),
			'{order_subtotal}'     => __( 'Subtotal, formatted', 'moksa-line' ),
			'{items_count}'        => __( 'Number of items', 'moksa-line' ),
			'{order_items}'        => __( 'List of items and quantities', 'moksa-line' ),
			'{customer_full_name}' => __( 'Customer name', 'moksa-line' ),
			'{customer_email}'     => __( 'Customer email', 'moksa-line' ),
			'{billing_name}'       => __( 'Billing name', 'moksa-line' ),
			'{billing_phone}'      => __( 'Billing phone', 'moksa-line' ),
			'{billing_address}'    => __( 'Billing address', 'moksa-line' ),
			'{shipping_name}'      => __( 'Shipping name', 'moksa-line' ),
			'{shipping_address}'   => __( 'Shipping address', 'moksa-line' ),
			'{shipping_city}'      => __( 'Shipping city', 'moksa-line' ),
			'{shipping_method}'    => __( 'Shipping method', 'moksa-line' ),
			'{payment_method}'     => __( 'Payment method', 'moksa-line' ),
			'{tracking_number}'    => __( 'Tracking number, from ECPay, RY Tools, AST or Shipment Tracking', 'moksa-line' ),
			'{store_name}'         => __( 'Pickup store name', 'moksa-line' ),
			'{store_address}'      => __( 'Pickup store address', 'moksa-line' ),
			'{customer_note}'      => __( 'Customer note', 'moksa-line' ),
			'{view_order_url}'     => __( 'Link to the order (https sites only)', 'moksa-line' ),
		);

		/**
		 * Describe placeholders added by other plugins.
		 *
		 * A plugin adding values through moksa_line_order_placeholders could
		 * substitute them but had no way to say what they were, so they were
		 * invisible to whoever was writing the message. Describe them here and
		 * they appear in the reference beside the built-in ones.
		 *
		 * @param array<string,string> $documented Placeholder => description.
		 */
		return (array) apply_filters( 'moksa_line_documented_placeholders', $documented );
	}
}
