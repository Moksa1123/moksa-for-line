<?php
/**
 * Flex Message templates.
 *
 * The editor's value is in catching mistakes before a customer sees them, so
 * saving runs the local structural checks and, when the channel is connected,
 * LINE's own validation endpoint. A template that will not send cannot be
 * saved as if it were fine.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Flex;

use Moksa\Line\Data\Flex;
use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Woo\ProductCards;
use Moksa\Line\Api\TokenManager;

defined( 'ABSPATH' ) || exit;

class FlexModule {

	public function register(): void {
		add_action( 'wp_ajax_moksa_line_flex_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_moksa_line_flex_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_moksa_line_flex_validate', array( $this, 'ajax_validate' ) );
		add_action( 'wp_ajax_moksa_line_flex_send_test', array( $this, 'ajax_send_test' ) );
		add_action( 'wp_ajax_moksa_line_flex_products', array( $this, 'ajax_products' ) );
		add_action( 'wp_ajax_moksa_line_flex_product_cards', array( $this, 'ajax_product_cards' ) );
	}

	/**
	 * Products for the picker.
	 */
	public function ajax_products(): void {
		$this->guard();

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		wp_send_json_success( array( 'products' => ProductCards::search( $search ) ) );
	}

	/**
	 * Turn the chosen products into Flex contents.
	 */
	public function ajax_product_cards(): void {
		$this->guard();

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['ids'] ) ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose at least one product.', 'moksa-line' ) ) );
		}

		$contents = ProductCards::carousel( $ids );

		if ( is_wp_error( $contents ) ) {
			wp_send_json_error( array( 'message' => $contents->get_error_message() ) );
		}

		// Built cards go through the same validator as hand-written ones. If
		// this plugin can generate something LINE would refuse, the shop should
		// hear it here rather than when a broadcast fails.
		$problems = Validator::check_message( MessagingClient::flex( 'x', $contents ) );

		wp_send_json_success(
			array(
				'contents' => $contents,
				'problems' => $problems,
			)
		);
	}

	/**
	 * Save a template.
	 */
	public function ajax_save(): void {
		$this->guard();

		$contents_raw = isset( $_POST['contents'] ) ? wp_unslash( $_POST['contents'] ) : '';
		$decoded      = json_decode( (string) $contents_raw, true );

		if ( ! is_array( $decoded ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: JSON parser message. */
						__( 'That is not valid JSON: %s', 'moksa-line' ),
						json_last_error_msg()
					),
				)
			);
		}

		$alt_text = isset( $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '';
		$problems = Validator::check_message( MessagingClient::flex( $alt_text, $decoded ) );

		if ( ! empty( $problems ) ) {
			wp_send_json_error(
				array(
					'message'  => __( 'This template will not send as it stands.', 'moksa-line' ),
					'problems' => $problems,
				)
			);
		}

		$id = Flex::save(
			array(
				'id'       => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
				'name'     => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'alt_text' => $alt_text,
				'category' => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
				// Re-encode from the decoded structure so what is stored is
				// always canonical JSON, whatever the editor sent.
				'contents' => wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			)
		);

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'The template could not be saved.', 'moksa-line' ) ) );
		}

		wp_send_json_success(
			array(
				'id'      => $id,
				'message' => __( 'Template saved.', 'moksa-line' ),
			)
		);
	}

	/**
	 * Delete a template.
	 */
	public function ajax_delete(): void {
		$this->guard();

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( ! Flex::delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That template no longer exists.', 'moksa-line' ) ), 404 );
		}

		wp_send_json_success( array( 'message' => __( 'Template deleted.', 'moksa-line' ) ) );
	}

	/**
	 * Check a template without saving it: local rules first, then LINE's own
	 * validator when a channel is connected.
	 */
	public function ajax_validate(): void {
		$this->guard();

		$decoded = json_decode( (string) wp_unslash( $_POST['contents'] ?? '' ), true );

		if ( ! is_array( $decoded ) ) {
			wp_send_json_error(
				array(
					'message'  => __( 'That is not valid JSON.', 'moksa-line' ),
					'problems' => array( json_last_error_msg() ),
				)
			);
		}

		$alt_text = isset( $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : 'Preview';
		$message  = MessagingClient::flex( $alt_text, $decoded );
		$problems = Validator::check_message( $message );

		if ( ! empty( $problems ) ) {
			wp_send_json_error(
				array(
					'message'  => __( 'Found problems before contacting LINE.', 'moksa-line' ),
					'problems' => $problems,
					'source'   => 'local',
				)
			);
		}

		if ( ! TokenManager::is_configured() ) {
			wp_send_json_success(
				array(
					'message' => __( 'Passed the local checks. Connect the Messaging API channel to also validate against LINE.', 'moksa-line' ),
					'source'  => 'local',
				)
			);
		}

		$remote = MessagingClient::validate( array( $message ), 'push' );

		if ( is_wp_error( $remote ) ) {
			wp_send_json_error(
				array(
					'message'  => __( 'LINE rejected this template.', 'moksa-line' ),
					'problems' => array( $remote->get_error_message() ),
					'source'   => 'line',
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'LINE accepted this template.', 'moksa-line' ),
				'source'  => 'line',
			)
		);
	}

	/**
	 * Push a template to one LINE user so it can be seen in a real chat.
	 */
	public function ajax_send_test(): void {
		$this->guard();

		$line_user_id = isset( $_POST['line_user_id'] ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';

		if ( '' === $line_user_id ) {
			wp_send_json_error( array( 'message' => __( 'Enter the LINE user id to send the test to.', 'moksa-line' ) ) );
		}

		$decoded = json_decode( (string) wp_unslash( $_POST['contents'] ?? '' ), true );

		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => __( 'That is not valid JSON.', 'moksa-line' ) ) );
		}

		$alt_text = isset( $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : 'Preview';

		$result = MessagingClient::push( $line_user_id, array( MessagingClient::flex( $alt_text, $decoded ) ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Sent. Check the chat on your phone.', 'moksa-line' ) ) );
	}

	/**
	 * Starter templates offered in the editor.
	 *
	 * @return array<string,array{label:string,contents:array}>
	 */
	public static function starters(): array {
		return array(
			'card'    => array(
				'label'    => __( 'Image card with a button', 'moksa-line' ),
				'contents' => array(
					'type' => 'bubble',
					'hero' => array(
						'type'        => 'image',
						// The site's own icon, not a third-party placeholder
						// service: the one this used has since shut down, so
						// every new template started with a broken image, and
						// it added an undeclared external dependency.
						'url'         => self::placeholder_image(),
						'size'        => 'full',
						'aspectRatio' => '20:13',
						'aspectMode'  => 'cover',
					),
					'body' => array(
						'type'     => 'box',
						'layout'   => 'vertical',
						'spacing'  => 'sm',
						'contents' => array(
							array(
								'type'   => 'text',
								'text'   => __( 'Headline', 'moksa-line' ),
								'weight' => 'bold',
								'size'   => 'xl',
								'wrap'   => true,
							),
							array(
								'type'  => 'text',
								'text'  => __( 'A sentence or two about what this is.', 'moksa-line' ),
								'size'  => 'sm',
								'color' => '#666666',
								'wrap'  => true,
							),
						),
					),
					'footer' => array(
						'type'     => 'box',
						'layout'   => 'vertical',
						'contents' => array(
							array(
								'type'   => 'button',
								'style'  => 'primary',
								// LINE's brand green is #06C755, but white text
								// on it is 2.3:1 -- under half the 4.5:1 that
								// makes small text readable. LINE gets away with
								// it on its own chrome; a shop's only call to
								// action should not start out that faint. This
								// is the same hue two steps darker, at 4.8:1.
								'color'  => '#06843A',
								'action' => array(
									'type'  => 'uri',
									'label' => __( 'Find out more', 'moksa-line' ),
									'uri'   => home_url(),
								),
							),
						),
					),
				),
			),
			'receipt' => array(
				'label'    => __( 'Order summary', 'moksa-line' ),
				'contents' => array(
					'type' => 'bubble',
					'body' => array(
						'type'     => 'box',
						'layout'   => 'vertical',
						'spacing'  => 'md',
						'contents' => array(
							array(
								'type'   => 'text',
								'text'   => __( 'Order confirmed', 'moksa-line' ),
								'weight' => 'bold',
								'size'   => 'lg',
							),
							array( 'type' => 'separator' ),
							array(
								'type'     => 'box',
								'layout'   => 'horizontal',
								'contents' => array(
									array( 'type' => 'text', 'text' => __( 'Total', 'moksa-line' ), 'size' => 'sm', 'color' => '#767676' ),
									array( 'type' => 'text', 'text' => 'NT$0', 'size' => 'sm', 'align' => 'end' ),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * An image URL to start a template with.
	 *
	 * LINE requires https for image URLs, so a site that is not on https gets
	 * no hero image rather than one the message will be rejected for.
	 */
	public static function placeholder_image(): string {
		$candidates = array(
			(string) get_site_icon_url( 1024 ),
			(string) get_header_image(),
		);

		foreach ( $candidates as $url ) {
			if ( '' !== $url && 0 === strpos( $url, 'https://' ) ) {
				return $url;
			}
		}

		// Nothing usable on this site: point at the plugin's own asset, which
		// is served from the same origin and therefore https wherever the
		// admin is reachable at all.
		$fallback = MOKSA_LINE_URL . 'assets/img/flex-placeholder.png';

		return 0 === strpos( $fallback, 'https://' ) ? $fallback : '';
	}

	/**
	 * Shared nonce and capability check.
	 */
	private function guard(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage templates.', 'moksa-line' ) ), 403 );
		}
	}
}
