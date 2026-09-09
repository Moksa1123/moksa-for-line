<?php
/**
 * Settings screen.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Admin\AdminModule;
use Moksa\Line\Bot\Ai\AiEngineProvider;
use Moksa\Line\Login\LoginModule;
use Moksa\Line\Support\Options;
use Moksa\Line\Webhook\WebhookModule;

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'general'   => __( 'LINE Login', 'moksa-line' ),
	'messaging' => __( 'Messaging API', 'moksa-line' ),
	'ai'        => __( 'AI replies', 'moksa-line' ),
	'liff'      => __( 'LIFF', 'moksa-line' ),
	'pay'       => __( 'LINE Pay', 'moksa-line' ),
	'advanced'  => __( 'Advanced', 'moksa-line' ),
);

if ( class_exists( 'WooCommerce' ) ) {
	$tabs = array_slice( $tabs, 0, 4, true )
		+ array( 'woo' => __( 'WooCommerce', 'moksa-line' ) )
		+ array_slice( $tabs, 4, null, true );
}

$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
$current = isset( $tabs[ $current ] ) ? $current : 'general';

/**
 * Render a text field.
 *
 * @param string $key   Option key.
 * @param string $label Field label.
 * @param string $help  Help text.
 * @param string $type  Input type.
 */
$field = function ( $key, $label, $help = '', $type = 'text' ) {
	$schema = Options::schema();
	$secret = ! empty( $schema[ $key ]['secret'] );
	$value  = $secret ? Options::mask( $key ) : (string) Options::get( $key );
	?>
	<tr>
		<th scope="row"><label for="moksa-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<input type="<?php echo esc_attr( $type ); ?>"
				id="moksa-<?php echo esc_attr( $key ); ?>"
				name="moksa_line[<?php echo esc_attr( $key ); ?>]"
				value="<?php echo esc_attr( $value ); ?>"
				class="regular-text"
				<?php // A text field followed by a password field is what browsers
				// autofill as a login form, which put an email address into the
				// Channel ID. new-password is the token they actually honour. ?>
				autocomplete="<?php echo $secret ? 'new-password' : 'off'; ?>"
				<?php echo $secret ? 'placeholder="' . esc_attr__( 'Leave unchanged to keep the stored value', 'moksa-line' ) . '"' : ''; ?> />
			<?php if ( '' !== $help ) : ?>
				<p class="description"><?php echo wp_kses_post( $help ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
};

/**
 * Render a checkbox.
 *
 * @param string $key   Option key.
 * @param string $label Field label.
 * @param string $help  Help text.
 */
$checkbox = function ( $key, $label, $help = '' ) {
	?>
	<tr>
		<th scope="row"><?php echo esc_html( $label ); ?></th>
		<td>
			<input type="hidden" name="moksa_line_booleans[]" value="<?php echo esc_attr( $key ); ?>" />
			<label>
				<input type="checkbox" name="moksa_line[<?php echo esc_attr( $key ); ?>]" value="1"
					<?php checked( (bool) Options::get( $key ) ); ?> />
				<?php echo esc_html( $help ); ?>
			</label>
		</td>
	</tr>
	<?php
};

/**
 * Render a select.
 *
 * @param string $key     Option key.
 * @param string $label   Field label.
 * @param array  $choices value => label.
 * @param string $help    Help text.
 */
$select = function ( $key, $label, $choices, $help = '' ) {
	$value = (string) Options::get( $key );
	?>
	<tr>
		<th scope="row"><label for="moksa-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<select id="moksa-<?php echo esc_attr( $key ); ?>" name="moksa_line[<?php echo esc_attr( $key ); ?>]">
				<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
					<option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( $value, (string) $choice_value ); ?>>
						<?php echo esc_html( $choice_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if ( '' !== $help ) : ?>
				<p class="description"><?php echo wp_kses_post( $help ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
};
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'LINE settings', 'moksa-line' ); ?></h1>

	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'moksa-line' ); ?></p></div>
	<?php endif; ?>

	<?php
	$warnings = get_transient( 'moksa_line_settings_warning' );

	if ( is_array( $warnings ) && $warnings ) :
		$names = array(
			'channel_id'           => __( 'LINE Login Channel ID', 'moksa-line' ),
			'messaging_channel_id' => __( 'Messaging API Channel ID', 'moksa-line' ),
			'pay_channel_id'       => __( 'LINE Pay Channel ID', 'moksa-line' ),
		);

		$labels = array();

		foreach ( $warnings as $warning_key ) {
			$labels[] = isset( $names[ $warning_key ] ) ? $names[ $warning_key ] : $warning_key;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: comma-separated list of field names. */
					esc_html__( 'This does not look like a channel ID: %s. A LINE channel ID is a number, usually ten digits. If your browser filled this in for you, clear it and paste the value from the LINE Developers Console.', 'moksa-line' ),
					esc_html( implode( ', ', $labels ) )
				);
				?>
			</p>
		</div>
		<?php
		delete_transient( 'moksa_line_settings_warning' );
	endif;
	?>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a class="nav-tab <?php echo $slug === $current ? 'nav-tab-active' : ''; ?>"
				href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminModule::SLUG . '-settings&tab=' . $slug ) ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
		<?php wp_nonce_field( 'moksa_line_settings' ); ?>
		<input type="hidden" name="action" value="moksa_line_save_settings" />
		<input type="hidden" name="tab" value="<?php echo esc_attr( $current ); ?>" />

		<table class="form-table" role="presentation">
			<?php if ( 'general' === $current ) : ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'Callback URL', 'moksa-line' ); ?></th>
					<td>
						<code class="moksa-copyable"><?php echo esc_html( LoginModule::callback_url() ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Paste this into the LINE Login channel as a Callback URL. It must match exactly, including https and any trailing path.', 'moksa-line' ); ?>
						</p>
					</td>
				</tr>

				<?php
				$field( 'channel_id', __( 'Channel ID', 'moksa-line' ), __( 'From the LINE Login channel, not the Messaging API one.', 'moksa-line' ) );
				$field( 'channel_secret', __( 'Channel secret', 'moksa-line' ), __( 'Stored encrypted. Only the last four characters are ever shown again.', 'moksa-line' ), 'password' );
				$checkbox( 'auto_register', __( 'New visitors', 'moksa-line' ), __( 'Create a WordPress account the first time someone logs in with LINE', 'moksa-line' ) );
				// Administrator is deliberately absent: a self-service login
				// flow must not be able to mint administrators.
				$roles = wp_roles()->get_names();
				unset( $roles['administrator'] );
				$select( 'new_user_role', __( 'Role for new accounts', 'moksa-line' ), $roles );
				$checkbox( 'sync_profile', __( 'Profile sync', 'moksa-line' ), __( 'Keep the WordPress display name in step with the LINE display name', 'moksa-line' ) );
				$checkbox( 'request_email', __( 'Email address', 'moksa-line' ), __( 'Request the email scope (needs approval from LINE first)', 'moksa-line' ) );
				$checkbox( 'link_by_email', __( 'Match by email', 'moksa-line' ), __( 'Link to an existing account when the email matches', 'moksa-line' ) );
				?>
				<tr>
					<th scope="row"></th>
					<td>
						<p class="description moksa-warning">
							<?php esc_html_e( 'Matching by email means anyone who controls a LINE account with that address can sign in as the matching WordPress user. Leave it off unless you know every LINE email on your site is verified and trusted.', 'moksa-line' ); ?>
						</p>
					</td>
				</tr>
				<?php
				$select(
					'bot_prompt',
					__( 'Invite to add the bot', 'moksa-line' ),
					array(
						'none'       => __( 'Do not invite', 'moksa-line' ),
						'normal'     => __( 'Show an option to add as a friend', 'moksa-line' ),
						'aggressive' => __( 'Show the add-friend step prominently', 'moksa-line' ),
					),
					__( 'Offers the official account while the visitor is logging in.', 'moksa-line' )
				);
				$field( 'login_redirect', __( 'Default destination', 'moksa-line' ), __( 'Where to send people after login. Leave blank to return them to the page they came from.', 'moksa-line' ), 'url' );
				?>

				<tr>
					<th scope="row" colspan="2"><h2 class="moksa-subhead"><?php esc_html_e( 'Login button', 'moksa-line' ); ?></h2></th>
				</tr>

				<?php
				$field( 'button_text', __( 'Button text', 'moksa-line' ), __( 'Leave blank to use the shortcode label, or the default wording.', 'moksa-line' ) );
				$field( 'button_bg_color', __( 'Background', 'moksa-line' ), __( 'A hex colour, for example #06C755.', 'moksa-line' ), 'color' );
				$field( 'button_text_color', __( 'Text colour', 'moksa-line' ), '', 'color' );
				$field( 'button_border_radius', __( 'Corner radius', 'moksa-line' ), __( 'Pixels. 0 gives square corners.', 'moksa-line' ), 'number' );
				$field( 'button_width', __( 'Width', 'moksa-line' ), __( 'For example 100%, 240px, or blank to fit the text.', 'moksa-line' ) );
				$field( 'button_height', __( 'Minimum height', 'moksa-line' ), __( 'Pixels. 0 lets the padding decide.', 'moksa-line' ), 'number' );
				?>

				<tr>
					<th scope="row"><?php esc_html_e( 'Preview', 'moksa-line' ); ?></th>
					<td>
						<?php
						// Rendered with the real front-end markup and settings, so
						// what is shown here is what a visitor sees.
						wp_enqueue_style( 'moksa-line-front' );

						printf(
							'<a class="moksa-line-button" href="#" onclick="return false;"%s>%s</a>',
							\Moksa\Line\Frontend\ShortcodeModule::style_attribute(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
							esc_html(
								'' !== trim( (string) Options::get( 'button_text' ) )
									? (string) Options::get( 'button_text' )
									: __( 'Log in with LINE', 'moksa-line' )
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Save to update the preview.', 'moksa-line' ); ?></p>
					</td>
				</tr>

			<?php elseif ( 'messaging' === $current ) : ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'Webhook URL', 'moksa-line' ); ?></th>
					<td>
						<code class="moksa-copyable"><?php echo esc_html( WebhookModule::endpoint_url() ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Paste this into the Messaging API channel, turn on "Use webhook", then press Verify.', 'moksa-line' ); ?>
						</p>
					</td>
				</tr>

				<?php
				$field( 'messaging_channel_id', __( 'Channel ID', 'moksa-line' ), __( 'From the Messaging API channel.', 'moksa-line' ) );
				$field( 'messaging_secret', __( 'Channel secret', 'moksa-line' ), __( 'Webhook signatures are verified with this. Without it, nothing the bot receives can be trusted.', 'moksa-line' ), 'password' );
				$select(
					'token_mode',
					__( 'Access token', 'moksa-line' ),
					array(
						'stateless'  => __( 'Issue short-lived tokens automatically (recommended)', 'moksa-line' ),
						'long_lived' => __( 'Use a long-lived token I paste below', 'moksa-line' ),
					),
					__( 'Short-lived tokens last 15 minutes, are unlimited in number, and never need storing. A long-lived token never expires, which means a leak is unrecoverable without rotating the channel.', 'moksa-line' )
				);
				$field( 'messaging_token', __( 'Long-lived token', 'moksa-line' ), __( 'Only needed when the mode above is set to long-lived.', 'moksa-line' ), 'password' );
				$field( 'bot_basic_id', __( 'Official account ID', 'moksa-line' ), __( 'For example @moksa. Used by the add-friend shortcode.', 'moksa-line' ) );
				?>
				<tr>
					<th scope="row"><label for="moksa-greeting_message"><?php esc_html_e( 'Greeting message', 'moksa-line' ); ?></label></th>
					<td>
						<textarea id="moksa-greeting_message" name="moksa_line[greeting_message]" rows="4" class="large-text"><?php echo esc_textarea( (string) Options::get( 'greeting_message' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Sent when someone adds the account. You can use {display_name}, {site_name} and {site_url}.', 'moksa-line' ); ?></p>
					</td>
				</tr>
				<?php
				$field( 'webhook_forward_url', __( 'Forward events to', 'moksa-line' ), __( 'Optional. Every webhook delivery is relayed here unchanged, with its signature header, for n8n, Make or your own service.', 'moksa-line' ), 'url' );
				$checkbox( 'inbox_enabled', __( 'Inbox', 'moksa-line' ), __( 'Record conversations and allow replying from WordPress', 'moksa-line' ) );
				?>

			<?php elseif ( 'ai' === $current ) : ?>

				<?php
				$provider  = new AiEngineProvider();
				$available = $provider->is_available();
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Provider status', 'moksa-line' ); ?></th>
					<td>
						<?php if ( $available ) : ?>
							<span class="moksa-pill moksa-pill--ok"><?php echo esc_html( $provider->label() ); ?></span>
						<?php else : ?>
							<span class="moksa-pill moksa-pill--warn"><?php esc_html_e( 'AI Engine is not active', 'moksa-line' ); ?></span>
							<p class="description"><?php esc_html_e( 'Install and activate AI Engine, then configure a chatbot in it. AI replies stay switched off until then.', 'moksa-line' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>

				<?php
				$checkbox( 'ai_enabled', __( 'AI replies', 'moksa-line' ), __( 'Let the AI answer messages no rule or flow handled', 'moksa-line' ) );

				if ( $available ) {
					$select( 'ai_bot_id', __( 'Chatbot', 'moksa-line' ), $provider->chatbots(), __( 'Whichever chatbot you have set up in AI Engine, including its knowledge base.', 'moksa-line' ) );
				} else {
					$field( 'ai_bot_id', __( 'Chatbot ID', 'moksa-line' ), __( 'The bot id from AI Engine.', 'moksa-line' ) );
				}

				$field( 'ai_daily_cap', __( 'Daily reply limit', 'moksa-line' ), __( 'Stops the AI answering after this many replies in a day. Set to 0 for no limit -- but every reply costs money.', 'moksa-line' ), 'number' );
				$field( 'ai_max_chars', __( 'Maximum reply length', 'moksa-line' ), __( 'Answers longer than this are truncated. LINE hard-limits a text message at 5000 characters.', 'moksa-line' ), 'number' );
				$field( 'ai_handoff_keyword', __( 'Hand-off keyword', 'moksa-line' ), __( 'When a message contains this, the bot stops answering and the conversation is flagged for a human.', 'moksa-line' ) );
				?>

			<?php elseif ( 'liff' === $current ) : ?>

				<?php
				$field( 'liff_id', __( 'Default LIFF ID', 'moksa-line' ), __( 'Used by the LIFF shortcodes unless one is given explicitly.', 'moksa-line' ) );
				$field( 'liff_chat_id', __( 'Chat LIFF ID', 'moksa-line' ), __( 'Optional separate LIFF app for the chat shortcode.', 'moksa-line' ) );
				$field( 'liff_pay_id', __( 'Payment LIFF ID', 'moksa-line' ), __( 'Optional separate LIFF app for in-app payment pages.', 'moksa-line' ) );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Shortcodes', 'moksa-line' ); ?></th>
					<td>
						<p><code>[moksa_liff_profile]</code> &mdash; <?php esc_html_e( 'greets the visitor by their LINE name', 'moksa-line' ); ?></p>
						<p><code>[moksa_line_chat]</code> &mdash; <?php esc_html_e( 'a message box that posts into the inbox', 'moksa-line' ); ?></p>
						<p><code>[moksa_line_login]</code> &mdash; <?php esc_html_e( 'the sign-in button', 'moksa-line' ); ?></p>
						<p><code>[moksa_line_add_friend]</code> &mdash; <?php esc_html_e( 'a link to your official account', 'moksa-line' ); ?></p>
					</td>
				</tr>

			<?php elseif ( 'woo' === $current ) : ?>

				<?php
				$checkbox( 'woo_notify', __( 'Order notifications', 'moksa-line' ), __( 'Message the customer in LINE when their order status changes', 'moksa-line' ) );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Notify on', 'moksa-line' ); ?></th>
					<td>
						<?php
						$selected_statuses = (array) Options::get( 'woo_notify_statuses' );

						foreach ( \Moksa\Line\Woo\WooModule::statuses() as $slug => $label ) :
							?>
							<label class="moksa-inline-check">
								<input type="checkbox" name="moksa_line_woo_statuses[]" value="<?php echo esc_attr( $slug ); ?>"
									<?php checked( in_array( $slug, $selected_statuses, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description">
							<?php esc_html_e( 'Each notification is a push message and is billed. The customer only hears about a given order and status once, however many times the status is set.', 'moksa-line' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Templates', 'moksa-line' ); ?></th>
					<td>
						<p class="description">
							<?php
							printf(
								/* translators: %s: URL of the notification templates screen. */
								wp_kses_post( __( 'The statuses above use a built-in card. For full control -- your own Flex design, and conditions such as payment or shipping method -- create <a href="%s">notification templates</a>. When a template matches, it replaces the built-in card.', 'moksa-line' ) ),
								esc_url( admin_url( 'edit.php?post_type=' . \Moksa\Line\Woo\NotifyTemplates::POST_TYPE ) )
							);
							?>
						</p>
					</td>
				</tr>

				<?php
				$field( 'woo_notify_delay', __( 'Delay before sending', 'moksa-line' ), __( 'Seconds. Useful when another plugin adjusts the order right after the status changes. 0 sends immediately.', 'moksa-line' ), 'number' );
				$checkbox( 'woo_wait_for_tracking', __( 'Wait for tracking', 'moksa-line' ), __( 'Hold the shipping notification until the tracking number appears', 'moksa-line' ) );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Tracking wait', 'moksa-line' ); ?></th>
					<td>
						<label>
							<?php esc_html_e( 'On status', 'moksa-line' ); ?>
							<select name="moksa_line[woo_tracking_status]">
								<?php foreach ( \Moksa\Line\Woo\NotifyTemplates::order_statuses() as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) Options::get( 'woo_tracking_status' ), $slug ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
						<label>
							<?php esc_html_e( 'check every', 'moksa-line' ); ?>
							<input type="number" name="moksa_line[woo_tracking_delay]" class="small-text"
								value="<?php echo esc_attr( (string) Options::get( 'woo_tracking_delay' ) ); ?>" />
							<?php esc_html_e( 'seconds,', 'moksa-line' ); ?>
						</label>
						<label>
							<?php esc_html_e( 'up to', 'moksa-line' ); ?>
							<input type="number" name="moksa_line[woo_tracking_retries]" class="small-text"
								value="<?php echo esc_attr( (string) Options::get( 'woo_tracking_retries' ) ); ?>" />
							<?php esc_html_e( 'times', 'moksa-line' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Tracking numbers are read from ECPay, RY Tools, Advanced Shipment Tracking and WooCommerce Shipment Tracking. After the last attempt the notification is sent anyway, without the number.', 'moksa-line' ); ?>
						</p>
					</td>
				</tr>

				<?php
				$field( 'woo_history_days', __( 'Keep notification history for', 'moksa-line' ), __( 'Days. Set to 0 to keep it indefinitely.', 'moksa-line' ), 'number' );
				$checkbox( 'woo_account_tab', __( 'My Account tab', 'moksa-line' ), __( 'Add a LINE tab where customers can link and unlink their account', 'moksa-line' ) );
				$checkbox( 'woo_login_buttons', __( 'Login buttons', 'moksa-line' ), __( 'Offer LINE sign-in on the account and checkout pages', 'moksa-line' ) );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Payments', 'moksa-line' ); ?></th>
					<td>
						<p class="description">
							<?php
							printf(
								/* translators: %s: LINE Pay settings tab URL. */
								wp_kses_post( __( 'LINE Pay is configured on the <a href="%s">LINE Pay tab</a>, and the gateway is switched on under WooCommerce > Settings > Payments.', 'moksa-line' ) ),
								esc_url( admin_url( 'admin.php?page=' . AdminModule::SLUG . '-settings&tab=pay' ) )
							);
							?>
						</p>
					</td>
				</tr>

			<?php elseif ( 'pay' === $current ) : ?>

				<?php
				$checkbox( 'pay_enabled', __( 'LINE Pay', 'moksa-line' ), __( 'Enable LINE Pay for WooCommerce and payment links', 'moksa-line' ) );
				$field( 'pay_channel_id', __( 'Pay Channel ID', 'moksa-line' ), __( 'From the LINE Pay merchant console, not the Developers Console.', 'moksa-line' ) );
				$field( 'pay_channel_secret', __( 'Pay Channel secret', 'moksa-line' ), '', 'password' );
				$checkbox( 'pay_sandbox', __( 'Sandbox', 'moksa-line' ), __( 'Use the sandbox environment (no real money moves)', 'moksa-line' ) );
				$select(
					'pay_currency',
					__( 'Currency', 'moksa-line' ),
					array( 'TWD' => 'TWD', 'JPY' => 'JPY', 'USD' => 'USD', 'THB' => 'THB' ),
					__( 'Must match both your LINE Pay merchant currency and the WooCommerce store currency, or the gateway hides itself at checkout.', 'moksa-line' )
				);
				$checkbox( 'pay_capture', __( 'Capture immediately', 'moksa-line' ), __( 'Take the money as soon as the customer confirms', 'moksa-line' ) );
				?>
				<tr>
					<th scope="row"></th>
					<td>
						<p class="description">
							<?php esc_html_e( 'With immediate capture off, payments are only authorised. You then have to capture or void each one, and an order cancelled in WooCommerce releases its authorisation automatically.', 'moksa-line' ); ?>
						</p>
					</td>
				</tr>

			<?php else : ?>

				<?php
				$select(
					'log_level',
					__( 'Log level', 'moksa-line' ),
					array(
						'debug'   => __( 'Everything', 'moksa-line' ),
						'info'    => __( 'Notable events', 'moksa-line' ),
						'warning' => __( 'Warnings and errors', 'moksa-line' ),
						'error'   => __( 'Errors only', 'moksa-line' ),
						'off'     => __( 'Off', 'moksa-line' ),
					)
				);
				$field( 'log_retention_days', __( 'Keep logs for', 'moksa-line' ), __( 'Days. Older rows are deleted daily.', 'moksa-line' ), 'number' );
				$field( 'inbox_retention_days', __( 'Keep conversations for', 'moksa-line' ), __( 'Days. Set to 0 to keep messages forever.', 'moksa-line' ), 'number' );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Encryption', 'moksa-line' ); ?></th>
					<td>
						<?php if ( \Moksa\Line\Support\Crypto::available() ) : ?>
							<span class="moksa-pill moksa-pill--ok"><?php esc_html_e( 'Credentials are encrypted at rest', 'moksa-line' ); ?></span>
							<p class="description"><?php esc_html_e( 'Keys are derived from this site\'s WordPress salts, so a database dump alone cannot reveal them. Changing the salts in wp-config.php makes stored credentials unreadable and they will need re-entering.', 'moksa-line' ); ?></p>
						<?php else : ?>
							<span class="moksa-pill moksa-pill--warn"><?php esc_html_e( 'Not available on this server', 'moksa-line' ); ?></span>
							<p class="description"><?php esc_html_e( 'OpenSSL with AES-256-GCM is missing, so credentials are stored as plain text.', 'moksa-line' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>

			<?php endif; ?>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
