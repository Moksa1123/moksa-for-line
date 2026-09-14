<?php
/**
 * WooCommerce integration: order notifications, account linking, login buttons.
 *
 * Order notifications are push messages, so they are billed and they are sent
 * once per order and status. The retry key is derived from the order and the
 * status, which means a status that flips back and forth, or a plugin that
 * fires the hook twice, cannot spam the customer.
 *
 * @package Mofoline
 */

namespace Mofoline\Woo;

use Mofoline\Data\Users;
use Mofoline\Api\MessagingClient;
use Mofoline\Login\LoginModule;
use Mofoline\Support\Logger;
use Mofoline\Frontend\ShortcodeModule;
use Mofoline\Support\Options;

defined( 'ABSPATH' ) || exit;

class WooModule {

	const ENDPOINT = 'line-account';
	const META_KEY = '_mofoline_user_id';

	/** Which statuses this order has already been notified about. */
	const META_SENT = '_mofoline_notified';

	/** How many times we have waited for a tracking number on this order. */
	const META_ATTEMPTS = '_mofoline_notify_attempts';

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
		add_action( 'mofoline_order_notify', array( $this, 'dispatch' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'store_line_id_on_order' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_line_id_in_admin' ) );

		if ( Options::get( 'woo_account_tab' ) ) {
			add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_item' ) );
			add_action( 'init', array( $this, 'add_endpoint' ) );
			add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_account_page' ) );
			add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
		}

		if ( Options::get( 'woo_login_buttons' ) ) {
			// Above the form or below it, as configured. WooCommerce gives us a
			// hook at each end, so this is a choice of hook rather than CSS
			// order -- which keeps the tab order matching what people see.
			$above = 'below' !== Options::get( 'button_position' );

			add_action( $above ? 'woocommerce_login_form_start' : 'woocommerce_login_form_end', array( $this, 'render_login_button' ) );
			add_action( $above ? 'woocommerce_register_form_start' : 'woocommerce_register_form_end', array( $this, 'render_login_button' ) );
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
				// duplicate if this runs twice within its retry window. It has
				// to be a UUID, so the description is hashed into one rather
				// than sent as text, which LINE rejects.
				array(
					'retry_key' => MessagingClient::retry_key(
						sprintf( 'order-%d-%s-%d', $order_id, $status, (int) $template_id )
					),
				)
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
					'moksa-for-line'
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
		return (array) apply_filters( 'mofoline_order_messages', $messages, $order, $status, $line_user_id );
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
		if ( wp_next_scheduled( 'mofoline_order_notify', $args ) ) {
			return;
		}

		wp_schedule_single_event( time() + max( 5, $delay ), 'mofoline_order_notify', $args );
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
					__( 'Order %s', 'moksa-for-line' ),
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
				array( 'type' => 'text', 'text' => __( 'Total', 'moksa-for-line' ), 'size' => 'sm', 'color' => '#888888' ),
				array(
					'type'   => 'text',
					'text'   => OrderContext::clean_money( (string) $order->get_formatted_order_total() ),
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
							'label' => __( 'View order', 'moksa-for-line' ),
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
				__( 'Order %1$s: %2$s', 'moksa-for-line' ),
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
			esc_html__( 'LINE account', 'moksa-for-line' ),
			esc_html( $record ? (string) $record->display_name : '' ),
			esc_html( $line_user_id )
		);
	}

	// --- My Account -----------------------------------------------------------------

	/**
	 * Add the endpoint rewrite, and make sure it is actually in the rules.
	 */
	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
		self::flush_endpoint_once();
	}

	/**
	 * Rebuild the rewrite rules the first time this endpoint is registered.
	 *
	 * add_rewrite_endpoint() only declares the endpoint; it does nothing until
	 * the rules are rebuilt. Activation flushes them, but this tab is behind a
	 * setting, so it is usually switched on long after that -- and then the
	 * endpoint is a registered query var with no rule behind it, which is a 404
	 * on /my-account/line-account/ for good, until somebody happens to re-save
	 * the permalink settings. It was in exactly that state on a site where the
	 * tab had been on for weeks.
	 *
	 * Flushing is expensive, so it happens once and then the flag says so.
	 */
	private static function flush_endpoint_once(): void {
		if ( self::ENDPOINT === get_option( 'mofoline_account_endpoint' ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'mofoline_account_endpoint', self::ENDPOINT, false );
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

		$items[ self::ENDPOINT ] = __( 'LINE', 'moksa-for-line' );

		if ( null !== $logout ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	/**
	 * Scripts for the LINE panel on the account page.
	 *
	 * This used to enqueue assets/js/admin.js -- the whole admin bundle, with
	 * every inbox, rich menu and Flex binding in it -- on a customer-facing
	 * page, to get one unlink button. When that file gained a dependency on the
	 * confirmation dialog, which the front end never loaded, the button threw
	 * and did nothing for every customer, silently.
	 */
	private static function enqueue_account_assets(): void {
		// The strings and the ajax URL ride along from where the script is
		// registered; enqueuing is all this has to do.
		wp_enqueue_style( 'mofoline-confirm' );
		wp_enqueue_script( 'mofoline-account' );
	}

	/**
	 * Render the LINE tab.
	 */
	public function render_account_page(): void {
		$record = Users::by_wp_id( get_current_user_id() );
		$linked = $record && '' !== (string) $record->line_user_id;

		wp_enqueue_style( 'mofoline-front' );
		self::enqueue_account_assets();

		$basic_id  = ltrim( trim( (string) Options::get( 'bot_basic_id' ) ), '@' );
		$friend_url = '' !== $basic_id ? 'https://line.me/R/ti/p/' . rawurlencode( '@' . $basic_id ) : '';
		?>
		<div class="moksa-account">
			<div class="moksa-account__head">
				<span class="moksa-account__mark" aria-hidden="true">LINE</span>
				<span class="moksa-account__title"><?php esc_html_e( 'LINE account', 'moksa-for-line' ); ?></span>
				<span class="moksa-account__state moksa-account__state--<?php echo $linked ? 'on' : 'off'; ?>">
					<?php echo esc_html( $linked ? __( 'Connected', 'moksa-for-line' ) : __( 'Not connected', 'moksa-for-line' ) ); ?>
				</span>
			</div>

			<?php if ( $linked ) : ?>
				<div class="moksa-account__who">
					<?php if ( '' !== (string) $record->picture_url ) : ?>
						<img class="moksa-account__avatar" src="<?php echo esc_url( (string) $record->picture_url ); ?>"
							alt="" width="56" height="56" loading="lazy" />
					<?php else : ?>
						<span class="moksa-account__avatar moksa-account__avatar--blank" aria-hidden="true">
							<?php echo esc_html( mb_substr( (string) $record->display_name, 0, 1 ) ); ?>
						</span>
					<?php endif; ?>

					<span class="moksa-account__identity">
						<?php
						// LINE does not always give a display name -- an account
						// with no profile set comes back blank -- and an empty
						// strong tag beside an avatar reads as a broken card.
						$shown_name = trim( (string) $record->display_name );
						?>
						<strong class="moksa-account__name">
							<?php echo esc_html( '' !== $shown_name ? $shown_name : __( 'Your LINE account', 'moksa-for-line' ) ); ?>
						</strong>
						<?php if ( '' !== (string) $record->created_at ) : ?>
							<span class="moksa-account__since">
								<?php
								printf(
									/* translators: %s: the date the account was linked. */
									esc_html__( 'Linked since %s', 'moksa-for-line' ),
									esc_html(
										mysql2date(
											/* translators: date format for "Linked since", see https://www.php.net/manual/datetime.format.php -- translate it to whatever reads naturally in your language, not literally. */
											_x( 'F j, Y', 'linked-since date', 'moksa-for-line' ),
											get_date_from_gmt( (string) $record->created_at )
										)
									)
								);
								?>
							</span>
						<?php endif; ?>
					</span>
				</div>

				<ul class="moksa-account__facts">
					<li class="moksa-account__fact moksa-account__fact--yes">
						<?php esc_html_e( 'Order updates come to you on LINE', 'moksa-for-line' ); ?>
					</li>

					<?php
					// Being linked is not enough: LINE refuses a message to
					// someone who has not added the account, so an unlinked
					// friend status is a notification that will never arrive.
					if ( ! (int) $record->is_friend ) :
						?>
						<li class="moksa-account__fact moksa-account__fact--no">
							<?php
							// Phrased as a condition, not a statement of fact.
							// The flag is only as good as the events we have
							// seen: someone who added the account before this
							// site had a webhook is recorded as not a friend,
							// and telling them otherwise is worse than silence.
							esc_html_e( 'They cannot reach you until you add the official account as a friend', 'moksa-for-line' );
							?>
							<?php if ( '' !== $friend_url ) : ?>
								<a class="mofoline-button mofoline-button--small" href="<?php echo esc_url( $friend_url ); ?>"
									target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Add as a friend', 'moksa-for-line' ); ?></a>
							<?php endif; ?>
						</li>
					<?php endif; ?>

					<li class="moksa-account__fact moksa-account__fact--yes">
						<?php esc_html_e( 'You can sign in with LINE instead of a password', 'moksa-for-line' ); ?>
					</li>
				</ul>

				<div class="moksa-account__actions">
					<button type="button" class="moksa-account__unlink"
						data-mofoline-unlink="<?php echo esc_attr( (string) get_current_user_id() ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'mofoline_link' ) ); ?>">
						<?php esc_html_e( 'Unlink', 'moksa-for-line' ); ?>
					</button>
				</div>
			<?php else : ?>
				<p class="moksa-account__lead"><?php esc_html_e( 'Link your account to:', 'moksa-for-line' ); ?></p>

				<ul class="moksa-account__facts">
					<li class="moksa-account__fact moksa-account__fact--yes">
						<?php esc_html_e( 'Hear about your order in LINE, from checkout to delivery', 'moksa-for-line' ); ?>
					</li>
					<li class="moksa-account__fact moksa-account__fact--yes">
						<?php esc_html_e( 'Sign in with one tap next time', 'moksa-for-line' ); ?>
					</li>
				</ul>

				<div class="moksa-account__actions">
					<a class="mofoline-button" href="<?php
						echo esc_url(
							add_query_arg(
								array(
									'action'      => 'mofoline_start',
									'link'        => 1,
									'_wpnonce'    => wp_create_nonce( 'mofoline_link' ),
									'redirect_to' => rawurlencode( wc_get_account_endpoint_url( self::ENDPOINT ) ),
								),
								admin_url( 'admin-ajax.php' )
							)
						);
					?>"><?php esc_html_e( 'Link my account', 'moksa-for-line' ); ?></a>
				</div>
			<?php endif; ?>

			<?php
			// Inside the panel, not a second card beside it: linking and the
			// membership code are two things about one account, and stacking
			// them as separate boxes made the page look like two plugins.
			//
			// Not conditional on being linked, either -- the code identifies
			// the customer at the counter, which is just as useful for someone
			// who signs in with a password.
			\Mofoline\Member\MemberModule::render_customer_card( get_current_user_id() );
			?>
		</div>
		<?php
	}

	// --- Login buttons -----------------------------------------------------------------

	/**
	 * A LINE button above the WooCommerce login and register forms.
	 */
	public function render_login_button(): void {
		if ( is_user_logged_in() || '' === (string) Options::get( 'channel_id' ) ) {
			return;
		}

		wp_enqueue_style( 'mofoline-front' );

		$position = 'below' !== Options::get( 'button_position' ) ? 'above' : 'below';
		$label    = trim( (string) Options::get( 'button_text' ) );
		$divider  = Options::get( 'button_divider' )
			? sprintf(
				'<p class="mofoline-divider" aria-hidden="true"><span>%s</span></p>',
				esc_html_x( 'or', 'between the LINE button and the login form', 'moksa-for-line' )
			)
			: '';

		$button = sprintf(
			'<p class="mofoline-woo-login"><a class="mofoline-button%1$s" href="%2$s" style="%3$s" rel="nofollow">%4$s</a></p>',
			esc_attr( ShortcodeModule::align_class() ),
			esc_url(
				add_query_arg(
					array(
						'action'      => 'mofoline_start',
						'redirect_to' => rawurlencode( wc_get_page_permalink( 'myaccount' ) ),
					),
					admin_url( 'admin-ajax.php' )
				)
			),
			esc_attr( ShortcodeModule::style_declarations() ),
			esc_html( '' !== $label ? $label : __( 'Continue with LINE', 'moksa-for-line' ) )
		);

		// The rule goes between the two things it separates, whichever way
		// round they are.
		echo wp_kses_post( 'above' === $position ? $button . $divider : $divider . $button );
	}

	/**
	 * Offer LINE sign-in at the top of checkout, so a returning customer does
	 * not retype an address they have already given us.
	 */
	public function render_checkout_prompt(): void {
		if ( is_user_logged_in() || '' === (string) Options::get( 'channel_id' ) ) {
			return;
		}

		wp_enqueue_style( 'mofoline-front' );

		printf(
			'<div class="woocommerce-info mofoline-checkout-prompt"><span>%s</span> <a class="mofoline-button mofoline-button--small" href="%s" style="%s">%s</a></div>',
			esc_html__( 'Already shopped with us?', 'moksa-for-line' ),
			esc_url(
				add_query_arg(
					array(
						'action'      => 'mofoline_start',
						'redirect_to' => rawurlencode( wc_get_checkout_url() ),
					),
					admin_url( 'admin-ajax.php' )
				)
			),
			esc_attr( ShortcodeModule::style_declarations() ),
			esc_html__( 'Sign in with LINE', 'moksa-for-line' )
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
