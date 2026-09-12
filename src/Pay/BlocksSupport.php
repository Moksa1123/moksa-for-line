<?php
/**
 * LINE Pay in the block-based checkout.
 *
 * WooCommerce has shipped the block checkout as the default since 8.3, and a
 * gateway that only knows the shortcode checkout does not appear on it at
 * all -- not greyed out, not listed, simply absent, with nothing in the admin
 * to say why. This is the other half: a payment method type the block registry
 * can see, backed by a small script that draws the option.
 *
 * Nothing about the payment itself lives here. The block only collects the
 * choice; process_payment() on the gateway does the same work either way.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Pay;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Moksa\Line\Admin\AdminModule;

defined( 'ABSPATH' ) || exit;

final class BlocksSupport extends AbstractPaymentMethodType {

	/** @var string Must match the gateway id, or the block and the gateway never meet. */
	protected $name = 'moksa_line_pay';

	/**
	 * @var WooGateway|null The gateway instance WooCommerce built, when it has.
	 */
	private $gateway = null;

	public function initialize() {
		$this->settings = get_option( 'woocommerce_moksa_line_pay_settings', array() );
	}

	/**
	 * Whether the block should offer this method at all.
	 *
	 * The gateway's own is_available() is the authority: it already knows
	 * about credentials, currency, and whether another plugin is handling LINE
	 * Pay on this site. Asking it here keeps the two checkouts agreeing.
	 */
	public function is_active() {
		$gateway = $this->gateway();

		return $gateway ? $gateway->is_available() : false;
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'moksa-line-pay-blocks',
			MOKSA_LINE_URL . 'assets/js/pay-blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			AdminModule::asset_version( 'assets/js/pay-blocks.js' ),
			true
		);

		wp_set_script_translations( 'moksa-line-pay-blocks', 'moksa-line', MOKSA_LINE_DIR . 'languages' );

		return array( 'moksa-line-pay-blocks' );
	}

	/**
	 * What the script needs to draw the option.
	 */
	public function get_payment_method_data() {
		$gateway = $this->gateway();

		return array(
			'title'       => $gateway ? $gateway->title : __( 'LINE Pay', 'moksa-line' ),
			'description' => $gateway ? $gateway->description : '',
			'icon'        => MOKSA_LINE_URL . 'assets/img/linepay.svg',
			'supports'    => $gateway ? array_filter( $gateway->supports, array( $gateway, 'supports' ) ) : array( 'products' ),
		);
	}

	/**
	 * The gateway WooCommerce instantiated, found by id.
	 */
	private function gateway(): ?WooGateway {
		if ( null !== $this->gateway ) {
			return $this->gateway;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return null;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		if ( isset( $gateways[ $this->name ] ) && $gateways[ $this->name ] instanceof WooGateway ) {
			$this->gateway = $gateways[ $this->name ];
		}

		return $this->gateway;
	}
}
