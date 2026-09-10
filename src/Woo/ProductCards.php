<?php
/**
 * Flex cards built from WooCommerce products.
 *
 * A product carousel is the thing shops most want Flex for, and until now the
 * only way to make one was to retype every name, price, image URL and link by
 * hand into the card editor. This builds them from the catalogue instead.
 *
 * The cards deliberately match the shape the card editor recognises -- hero
 * image, a headline, one line under it, one footer button -- so a generated
 * carousel stays editable in the fields afterwards rather than becoming a wall
 * of JSON the shop cannot touch.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Woo;

use Moksa\Line\Support\Logger;

defined( 'ABSPATH' ) || exit;

class ProductCards {

	/** LINE's carousel limit, and so the most products one can hold. */
	const MAX_PRODUCTS = 12;

	/**
	 * Whether products can be turned into cards at all.
	 */
	public static function available(): bool {
		return function_exists( 'wc_get_product' ) && function_exists( 'wc_price' );
	}

	/**
	 * Products matching a search, for the picker.
	 *
	 * @param string $search Search term, or '' for the most recent.
	 * @param int    $limit  How many to return.
	 * @return array<int,array<string,mixed>>
	 */
	public static function search( string $search = '', int $limit = 20 ): array {
		if ( ! self::available() ) {
			return array();
		}

		$args = array(
			'status' => 'publish',
			'limit'  => max( 1, min( 50, $limit ) ),
			'return' => 'objects',
			'orderby' => 'date',
			'order'  => 'DESC',
		);

		if ( '' !== trim( $search ) ) {
			$args['s'] = trim( $search );
		}

		$found = array();

		foreach ( wc_get_products( $args ) as $product ) {
			// A variable product's own price is a range and its own URL leads
			// to a form, not a purchase. That is still a reasonable card, so it
			// is offered -- the shop can see the range in the picker and decide.
			$found[] = array(
				'id'        => $product->get_id(),
				'name'      => $product->get_name(),
				'price'     => self::price_line( $product ),
				'image'     => self::image_url( $product, 'thumbnail' ),
				'in_stock'  => $product->is_in_stock(),
				'permalink' => (string) $product->get_permalink(),
			);
		}

		return $found;
	}

	/**
	 * A carousel, or a single bubble, for the given products.
	 *
	 * @param int[] $ids Product ids, in the order they should appear.
	 * @return array|\WP_Error Flex contents, or an error explaining why not.
	 */
	public static function carousel( array $ids ) {
		if ( ! self::available() ) {
			return new \WP_Error(
				'moksa_line_no_woo',
				__( 'WooCommerce is not active, so there are no products to build from.', 'moksa-line' )
			);
		}

		$ids     = array_slice( array_values( array_unique( array_map( 'intval', $ids ) ) ), 0, self::MAX_PRODUCTS );
		$bubbles = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );

			if ( ! $product ) {
				continue;
			}

			$bubbles[] = self::bubble( $product );
		}

		if ( empty( $bubbles ) ) {
			return new \WP_Error(
				'moksa_line_no_products',
				__( 'None of those products could be loaded.', 'moksa-line' )
			);
		}

		if ( 1 === count( $bubbles ) ) {
			return $bubbles[0];
		}

		return array(
			'type'     => 'carousel',
			'contents' => $bubbles,
		);
	}

	/**
	 * One product as a bubble.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	private static function bubble( $product ): array {
		$bubble = array(
			'type' => 'bubble',
			'body' => array(
				'type'     => 'box',
				'layout'   => 'vertical',
				'spacing'  => 'sm',
				'contents' => array(
					array(
						'type'   => 'text',
						'text'   => self::clamp( $product->get_name(), 60 ),
						'weight' => 'bold',
						'size'   => 'lg',
						'wrap'   => true,
					),
					array(
						'type'  => 'text',
						'text'  => self::price_line( $product ),
						'size'  => 'sm',
						'color' => '#767676',
						'wrap'  => true,
					),
				),
			),
		);

		$image = self::image_url( $product, 'large' );

		if ( '' !== $image ) {
			$bubble['hero'] = array(
				'type'        => 'image',
				'url'         => $image,
				'size'        => 'full',
				'aspectRatio' => '1:1',
				'aspectMode'  => 'cover',
			);
		}

		$link = self::https_only( (string) $product->get_permalink() );

		if ( '' !== $link ) {
			$bubble['footer'] = array(
				'type'     => 'box',
				'layout'   => 'vertical',
				'contents' => array(
					array(
						'type'  => 'button',
						'style' => 'primary',
						// Not LINE's brand green: white on #06C755 is 2.3:1,
						// which is below readable for a button label.
						'color' => '#06843A',
						'action' => array(
							'type'  => 'uri',
							'label' => $product->is_in_stock()
								? __( 'Buy now', 'moksa-line' )
								: __( 'View', 'moksa-line' ),
							// LINE cuts an action label at 20 characters.
							'uri'   => $link,
						),
					),
				),
			);
		}

		return $bubble;
	}

	/**
	 * The price, as one readable line.
	 *
	 * Built from the numbers rather than from get_price_html(), which is meant
	 * for a web page and is wrong here twice over. It encodes the currency
	 * symbol as HTML entities -- a Taiwanese shop's NT$ arrives as
	 * &#78;&#84;&#36; -- and Flex has no HTML to decode them, so the customer
	 * would read the entities. On a sale item it also folds in screen-reader
	 * text that is invisible on a page and plain noise in a chat bubble.
	 *
	 * A sale shows both numbers on one line rather than as a second component,
	 * so the card keeps the two-text shape the field editor can still edit.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function price_line( $product ): string {
		if ( ! $product->is_in_stock() ) {
			return __( 'Out of stock', 'moksa-line' );
		}

		// A variable product has a range, not a price.
		if ( $product->is_type( 'variable' ) ) {
			$min = self::money( $product->get_variation_price( 'min', true ) );
			$max = self::money( $product->get_variation_price( 'max', true ) );

			if ( '' === $min ) {
				return '';
			}

			return $min === $max
				? $min
				: sprintf(
					/* translators: 1: lowest price, 2: highest price. */
					__( '%1$s to %2$s', 'moksa-line' ),
					$min,
					$max
				);
		}

		$now = self::money( wc_get_price_to_display( $product ) );

		if ( '' === $now ) {
			return '';
		}

		if ( $product->is_on_sale() ) {
			$was = self::money( wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ) );

			if ( '' !== $was && $was !== $now ) {
				return self::clamp(
					sprintf(
						/* translators: 1: current price, 2: the price before the sale. */
						__( '%1$s (was %2$s)', 'moksa-line' ),
						$now,
						$was
					),
					60
				);
			}
		}

		return self::clamp( $now, 60 );
	}

	/**
	 * A formatted price as plain text.
	 *
	 * wc_price() returns markup with the currency symbol entity-encoded, so
	 * both the tags and the entities have to come off before it is safe to put
	 * in a message that has no HTML to render them.
	 *
	 * @param mixed $amount Price.
	 */
	private static function money( $amount ): string {
		if ( '' === $amount || null === $amount ) {
			return '';
		}

		$formatted = wp_strip_all_tags( (string) wc_price( (float) $amount ) );
		$formatted = html_entity_decode( $formatted, ENT_QUOTES, 'UTF-8' );

		// wc_price() separates the symbol from the number with a non-breaking
		// space, which reads as a stray character in some LINE clients.
		$formatted = str_replace( "Â ", ' ', $formatted );

		return trim( preg_replace( '/\s+/u', ' ', $formatted ) );
	}

	/**
	 * A product image URL that LINE will actually load.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $size    Image size.
	 */
	private static function image_url( $product, string $size ): string {
		$id = (int) $product->get_image_id();

		if ( $id <= 0 ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $id, $size );

		return $url ? self::https_only( (string) $url ) : '';
	}

	/**
	 * Drop anything that is not HTTPS.
	 *
	 * LINE refuses plain http for both image URLs and uri actions, and refuses
	 * the whole message rather than the one field, so a site still on http
	 * would produce a carousel that never sends. Better a card without an image
	 * than a message the customer never receives.
	 *
	 * @param string $url Candidate URL.
	 */
	private static function https_only( string $url ): string {
		if ( 0 === stripos( $url, 'https://' ) ) {
			return $url;
		}

		if ( '' !== $url ) {
			Logger::warning(
				'Left a product URL out of a Flex card because LINE requires HTTPS',
				array( 'url' => $url ),
				'flex'
			);
		}

		return '';
	}

	/**
	 * Shorten to a character count, with an ellipsis when it had to.
	 *
	 * @param string $text  Text.
	 * @param int    $limit Character limit.
	 */
	private static function clamp( string $text, int $limit ): string {
		$text = trim( wp_strip_all_tags( $text ) );

		return mb_strlen( $text ) > $limit ? mb_substr( $text, 0, $limit - 1 ) . '…' : $text;
	}
}
