<?php
/**
 * Order notification templates, stored as a custom post type.
 *
 * The post type slug and meta keys are inherited unchanged from the plugin
 * this replaces, so templates a shop has already written keep working: they
 * live in wp_posts, which this plugin never rewrites.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Woo;

use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Flex\Validator;
use Moksa\Line\Support\Logger;

defined( 'ABSPATH' ) || exit;

class NotifyTemplates {

	const POST_TYPE = 'moksa-order-notify';

	const META_STATUSES = '_moksa_notify_trigger_statuses';
	const META_RULES    = '_moksa_notify_trigger_rules';
	const META_CONTENT  = '_moksa_notify_content';

	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ), 10, 2 );

		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );

		add_action( 'wp_ajax_moksa_line_notify_test', array( $this, 'ajax_test' ) );
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Order notifications', 'moksa-line' ),
					'singular_name' => __( 'Order notification', 'moksa-line' ),
					'add_new_item'  => __( 'Add order notification', 'moksa-line' ),
					'edit_item'     => __( 'Edit order notification', 'moksa-line' ),
					'search_items'  => __( 'Search notifications', 'moksa-line' ),
					'not_found'     => __( 'No notification templates yet.', 'moksa-line' ),
				),
				'public'          => false,
				'show_ui'         => true,
				// Not attached to the LINE menu here: WordPress would insert it
				// wherever registration happens to land, which put it above the
				// dashboard and produced a second entry with the same name as
				// the history screen. AdminModule places it explicitly instead.
				'show_in_menu'    => false,
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'manage_woocommerce' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'show_in_rest'    => false,
			)
		);
	}

	// --- Editing ---------------------------------------------------------------

	public function add_meta_boxes(): void {
		add_meta_box(
			'moksa-notify-trigger',
			__( 'When to send', 'moksa-line' ),
			array( $this, 'render_trigger' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'moksa-notify-content',
			__( 'Message', 'moksa-line' ),
			array( $this, 'render_content' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'moksa-notify-params',
			__( 'Available values', 'moksa-line' ),
			array( $this, 'render_params' ),
			self::POST_TYPE,
			'side'
		);
	}

	/**
	 * Status checkboxes and the rule rows.
	 *
	 * @param \WP_Post $post Template being edited.
	 */
	public function render_trigger( $post ): void {
		wp_nonce_field( 'moksa_line_notify_save', 'moksa_line_notify_nonce' );

		$statuses = (array) get_post_meta( $post->ID, self::META_STATUSES, true );
		$rules    = (array) get_post_meta( $post->ID, self::META_RULES, true );
		?>
		<p><strong><?php esc_html_e( 'Order statuses', 'moksa-line' ); ?></strong></p>
		<p class="description">
			<?php esc_html_e( 'The customer is messaged when the order enters any of these statuses. Each notification is billed as a push message.', 'moksa-line' ); ?>
		</p>

		<?php foreach ( self::order_statuses() as $slug => $label ) : ?>
			<label class="moksa-inline-check">
				<input type="checkbox" name="moksa_notify_statuses[]" value="<?php echo esc_attr( $slug ); ?>"
					<?php checked( in_array( $slug, $statuses, true ) ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
		<?php endforeach; ?>

		<hr />

		<p><strong><?php esc_html_e( 'Only when', 'moksa-line' ); ?></strong></p>
		<p class="description">
			<?php esc_html_e( 'Every condition must hold. Leave empty to send for every order that reaches those statuses.', 'moksa-line' ); ?>
		</p>

		<table class="widefat moksa-rules" data-moksa-rules>
			<tbody>
				<?php
				$rows = ! empty( $rules ) ? $rules : array( array() );

				foreach ( $rows as $index => $rule ) :
					$this->render_rule_row( (int) $index, is_array( $rule ) ? $rule : array() );
				endforeach;
				?>
			</tbody>
		</table>
		<p><button type="button" class="button" data-moksa-add-rule><?php esc_html_e( 'Add condition', 'moksa-line' ); ?></button></p>
		<?php
	}

	/**
	 * One rule row.
	 *
	 * @param int   $index Row index.
	 * @param array $rule  Stored rule.
	 */
	private function render_rule_row( int $index, array $rule ): void {
		$type      = isset( $rule['type'] ) ? (string) $rule['type'] : '';
		$operator  = isset( $rule['operator'] ) ? (string) $rule['operator'] : '';
		$value     = isset( $rule['value'] ) ? (string) $rule['value'] : '';
		$operators = TriggerRules::operators();
		?>
		<tr>
			<td>
				<select name="moksa_notify_rules[<?php echo esc_attr( (string) $index ); ?>][type]">
					<option value=""><?php esc_html_e( '-- choose --', 'moksa-line' ); ?></option>
					<?php foreach ( TriggerRules::types() as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $type, $slug ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<select name="moksa_notify_rules[<?php echo esc_attr( (string) $index ); ?>][operator]">
					<?php
					foreach ( $operators as $rule_type => $choices ) :
						foreach ( $choices as $slug => $label ) :
							?>
							<option value="<?php echo esc_attr( $slug ); ?>"
								data-for="<?php echo esc_attr( $rule_type ); ?>"
								<?php selected( $operator, $slug ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
							<?php
						endforeach;
					endforeach;
					?>
				</select>
			</td>
			<td>
				<input type="text" name="moksa_notify_rules[<?php echo esc_attr( (string) $index ); ?>][value]"
					value="<?php echo esc_attr( $value ); ?>" class="regular-text"
					placeholder="<?php esc_attr_e( 'e.g. cod, flat_rate, 1000', 'moksa-line' ); ?>" />
			</td>
			<td><button type="button" class="button-link delete" data-moksa-remove-rule><?php esc_html_e( 'Remove', 'moksa-line' ); ?></button></td>
		</tr>
		<?php
	}

	/**
	 * The Flex JSON for this notification.
	 *
	 * @param \WP_Post $post Template being edited.
	 */
	public function render_content( $post ): void {
		$content = (string) get_post_meta( $post->ID, self::META_CONTENT, true );

		if ( '' === $content ) {
			$content = (string) wp_json_encode( self::starter_template(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		?>
		<p class="description">
			<?php esc_html_e( 'A Flex Message bubble or carousel, as JSON. Values in braces are replaced with the order\'s details before sending.', 'moksa-line' ); ?>
		</p>
		<textarea name="moksa_notify_content" rows="20" class="widefat code" spellcheck="false"
			data-moksa-notify-json><?php echo esc_textarea( $content ); ?></textarea>

		<p>
			<label for="moksa-notify-test-to"><?php esc_html_e( 'Send a test to', 'moksa-line' ); ?></label>
			<input type="text" id="moksa-notify-test-to" class="regular-text" placeholder="U1234..." data-moksa-notify-test-to />
			<button type="button" class="button" data-moksa-notify-test="<?php echo esc_attr( (string) $post->ID ); ?>">
				<?php esc_html_e( 'Send test', 'moksa-line' ); ?>
			</button>
			<span class="description"><?php esc_html_e( 'Uses the most recent order to fill in the values. Save first.', 'moksa-line' ); ?></span>
		</p>

		<div class="moksa-feedback" data-moksa-feedback></div>
		<?php
	}

	/**
	 * Placeholder reference.
	 */
	public function render_params(): void {
		echo '<p class="description">' . esc_html__( 'Click to copy.', 'moksa-line' ) . '</p><ul class="moksa-params">';

		foreach ( OrderContext::documented() as $placeholder => $description ) {
			printf(
				'<li><code class="moksa-copyable">%s</code><br /><span class="description">%s</span></li>',
				esc_html( $placeholder ),
				esc_html( $description )
			);
		}

		echo '</ul>';
	}

	/**
	 * Persist the meta boxes.
	 *
	 * @param int      $post_id Template id.
	 * @param \WP_Post $post    Template.
	 */
	public function save( $post_id, $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST['moksa_line_notify_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['moksa_line_notify_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'moksa_line_notify_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$statuses = isset( $_POST['moksa_notify_statuses'] )
			? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['moksa_notify_statuses'] ) )
			: array();

		$allowed  = array_keys( self::order_statuses() );
		$statuses = array_values( array_intersect( $statuses, $allowed ) );

		update_post_meta( $post_id, self::META_STATUSES, $statuses );

		$rules = isset( $_POST['moksa_notify_rules'] ) ? (array) wp_unslash( $_POST['moksa_notify_rules'] ) : array();
		update_post_meta( $post_id, self::META_RULES, TriggerRules::sanitize( $rules ) );

		// The content is Flex JSON and must survive intact, so it is validated
		// rather than sanitised into something that no longer parses.
		$content = isset( $_POST['moksa_notify_content'] ) ? (string) wp_unslash( $_POST['moksa_notify_content'] ) : '';
		$decoded = json_decode( $content, true );

		if ( is_array( $decoded ) ) {
			update_post_meta(
				$post_id,
				self::META_CONTENT,
				(string) wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			);
		} elseif ( '' !== trim( $content ) ) {
			// Keep what the author typed so their work is not destroyed, and
			// tell them on the next screen why it will not send.
			update_post_meta( $post_id, self::META_CONTENT, $content );

			set_transient(
				'moksa_line_notify_json_error_' . $post_id,
				sprintf(
					/* translators: %s: JSON parser message. */
					__( 'The message is not valid JSON, so this notification will not send: %s', 'moksa-line' ),
					json_last_error_msg()
				),
				60
			);
		}
	}

	// --- Listing ---------------------------------------------------------------

	/**
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['moksa_statuses'] = __( 'Statuses', 'moksa-line' );
				$out['moksa_rules']    = __( 'Conditions', 'moksa-line' );
				$out['moksa_sent']     = __( 'Sent (30 days)', 'moksa-line' );
			}
		}

		return $out;
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Template id.
	 */
	public function column( $column, $post_id ): void {
		if ( 'moksa_statuses' === $column ) {
			$statuses = (array) get_post_meta( $post_id, self::META_STATUSES, true );
			$labels   = self::order_statuses();
			$names    = array();

			foreach ( $statuses as $slug ) {
				$names[] = isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
			}

			echo $names
				? esc_html( implode( ', ', $names ) )
				: '<span class="moksa-pill moksa-pill--warn">' . esc_html__( 'never fires', 'moksa-line' ) . '</span>';

			return;
		}

		if ( 'moksa_rules' === $column ) {
			$rules = (array) get_post_meta( $post_id, self::META_RULES, true );

			echo esc_html(
				$rules
					? sprintf(
						/* translators: %d: number of conditions. */
						_n( '%d condition', '%d conditions', count( $rules ), 'moksa-line' ),
						count( $rules )
					)
					: __( 'Any order', 'moksa-line' )
			);

			return;
		}

		if ( 'moksa_sent' === $column ) {
			global $wpdb;
			$table = NotifyHistory::table();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE template_id = %d AND status = 'sent' AND created_at >= %s",
					$post_id,
					gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) )
				)
			);

			echo esc_html( number_format_i18n( $count ) );
		}
	}

	// --- Selection ---------------------------------------------------------------

	/**
	 * Templates that should fire for an order entering a status.
	 *
	 * Statuses are filtered in PHP rather than with a serialized LIKE query:
	 * matching a serialized array by substring is fragile, and a shop has few
	 * enough templates that loading them is cheaper than getting it wrong.
	 *
	 * @param string         $status Status slug, without the wc- prefix.
	 * @param \WC_Order|null $order  Order, for rule evaluation.
	 * @return int[] Template ids.
	 */
	public static function for_status( string $status, $order = null ): array {
		$templates = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$matched = array();

		foreach ( (array) $templates as $template_id ) {
			$statuses = (array) get_post_meta( $template_id, self::META_STATUSES, true );

			// Accept both the bare slug and the wc- prefixed form, because the
			// previous plugin stored the prefixed one.
			if ( ! in_array( $status, $statuses, true ) && ! in_array( 'wc-' . $status, $statuses, true ) ) {
				continue;
			}

			if ( $order ) {
				$rules = get_post_meta( $template_id, self::META_RULES, true );

				if ( ! TriggerRules::match( $rules, $order ) ) {
					continue;
				}
			}

			$matched[] = (int) $template_id;
		}

		return $matched;
	}

	/**
	 * Render a template against an order.
	 *
	 * @param int       $template_id Template id.
	 * @param \WC_Order $order       Order.
	 * @param string    $status      Status slug.
	 * @return array|null A flex message object, or null when it cannot be built.
	 */
	public static function render( int $template_id, $order, string $status ): ?array {
		$template_json = (string) get_post_meta( $template_id, self::META_CONTENT, true );

		if ( '' === trim( $template_json ) ) {
			return null;
		}

		$contents = OrderContext::render( $template_json, $order, $status );

		if ( null === $contents ) {
			Logger::warning(
				'An order notification template did not produce valid JSON after substitution',
				array( 'template_id' => $template_id, 'order_id' => $order->get_id() ),
				'woo'
			);

			return null;
		}

		// A template may hold a whole flex message or just the container.
		if ( isset( $contents['type'] ) && 'flex' === $contents['type'] ) {
			$message = $contents;
		} else {
			$message = MessagingClient::flex(
				sprintf(
					/* translators: 1: order number, 2: status label. */
					__( 'Order %1$s: %2$s', 'moksa-line' ),
					(string) $order->get_order_number(),
					wc_get_order_status_name( $status )
				),
				$contents
			);
		}

		$problems = Validator::check_message( $message );

		if ( ! empty( $problems ) ) {
			Logger::warning(
				'An order notification template would be rejected by LINE',
				array(
					'template_id' => $template_id,
					'order_id'    => $order->get_id(),
					'problems'    => array_slice( $problems, 0, 3 ),
				),
				'woo'
			);

			return null;
		}

		return $message;
	}

	// --- Test send -------------------------------------------------------------

	/**
	 * Send a template to one LINE user, filled in from the most recent order.
	 */
	public function ajax_test(): void {
		check_ajax_referer( 'moksa_line_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot send test notifications.', 'moksa-line' ) ), 403 );
		}

		$template_id  = isset( $_POST['template_id'] ) ? (int) $_POST['template_id'] : 0;
		$line_user_id = isset( $_POST['line_user_id'] ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';

		if ( '' === $line_user_id ) {
			wp_send_json_error( array( 'message' => __( 'Enter a LINE user id to send the test to.', 'moksa-line' ) ) );
		}

		if ( ! function_exists( 'wc_get_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'moksa-line' ) ) );
		}

		$orders = wc_get_orders( array( 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );

		if ( empty( $orders ) ) {
			wp_send_json_error( array( 'message' => __( 'There are no orders yet to build a preview from.', 'moksa-line' ) ) );
		}

		$order   = $orders[0];
		$message = self::render( $template_id, $order, (string) $order->get_status() );

		if ( null === $message ) {
			wp_send_json_error(
				array( 'message' => __( 'This template did not produce a valid message. Check the JSON and the placeholders.', 'moksa-line' ) )
			);
		}

		$result = MessagingClient::push( $line_user_id, array( $message ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: order number used for the preview. */
					__( 'Sent, using order %s for the values.', 'moksa-line' ),
					(string) $order->get_order_number()
				),
			)
		);
	}

	// --- Helpers ---------------------------------------------------------------

	/**
	 * Order statuses without the wc- prefix.
	 *
	 * @return array<string,string>
	 */
	public static function order_statuses(): array {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}

		$statuses = array();

		foreach ( wc_get_order_statuses() as $slug => $label ) {
			$statuses[ str_replace( 'wc-', '', $slug ) ] = $label;
		}

		return $statuses;
	}

	/**
	 * A usable starting point for a new template.
	 *
	 * @return array
	 */
	public static function starter_template(): array {
		return array(
			'type' => 'bubble',
			'body' => array(
				'type'     => 'box',
				'layout'   => 'vertical',
				'spacing'  => 'md',
				'contents' => array(
					array(
						'type'   => 'text',
						'text'   => '{status_label}',
						'size'   => 'sm',
						'weight' => 'bold',
						'color'  => '{status_color}',
					),
					array(
						'type'   => 'text',
						'text'   => 'Order {order_number}',
						'weight' => 'bold',
						'size'   => 'lg',
						'wrap'   => true,
					),
					array( 'type' => 'separator' ),
					array(
						'type'   => 'text',
						'text'   => '{order_items}',
						'size'   => 'sm',
						'color'  => '#666666',
						'wrap'   => true,
					),
					array(
						'type'     => 'box',
						'layout'   => 'horizontal',
						'contents' => array(
							array( 'type' => 'text', 'text' => 'Total', 'size' => 'sm', 'color' => '#888888' ),
							array( 'type' => 'text', 'text' => '{total}', 'size' => 'sm', 'align' => 'end', 'weight' => 'bold' ),
						),
					),
				),
			),
		);
	}
}
