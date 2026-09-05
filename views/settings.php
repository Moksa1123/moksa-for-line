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
	'general'   => __( 'LINE Login', 'moksa-line-login' ),
	'messaging' => __( 'Messaging API', 'moksa-line-login' ),
	'ai'        => __( 'AI replies', 'moksa-line-login' ),
	'liff'      => __( 'LIFF', 'moksa-line-login' ),
	'pay'       => __( 'LINE Pay', 'moksa-line-login' ),
	'advanced'  => __( 'Advanced', 'moksa-line-login' ),
);

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
				autocomplete="off"
				<?php echo $secret ? 'placeholder="' . esc_attr__( 'Leave unchanged to keep the stored value', 'moksa-line-login' ) . '"' : ''; ?> />
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
	<h1><?php esc_html_e( 'LINE settings', 'moksa-line-login' ); ?></h1>

	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'moksa-line-login' ); ?></p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a class="nav-tab <?php echo $slug === $current ? 'nav-tab-active' : ''; ?>"
				href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminModule::SLUG . '-settings&tab=' . $slug ) ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'moksa_line_settings' ); ?>
		<input type="hidden" name="action" value="moksa_line_save_settings" />
		<input type="hidden" name="tab" value="<?php echo esc_attr( $current ); ?>" />

		<table class="form-table" role="presentation">
			<?php if ( 'general' === $current ) : ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'Callback URL', 'moksa-line-login' ); ?></th>
					<td>
						<code class="moksa-copyable"><?php echo esc_html( LoginModule::callback_url() ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Paste this into the LINE Login channel as a Callback URL. It must match exactly, including https and any trailing path.', 'moksa-line-login' ); ?>
						</p>
					</td>
				</tr>

				<?php
				$field( 'channel_id', __( 'Channel ID', 'moksa-line-login' ), __( 'From the LINE Login channel, not the Messaging API one.', 'moksa-line-login' ) );
				$field( 'channel_secret', __( 'Channel secret', 'moksa-line-login' ), __( 'Stored encrypted. Only the last four characters are ever shown again.', 'moksa-line-login' ), 'password' );
				$checkbox( 'auto_register', __( 'New visitors', 'moksa-line-login' ), __( 'Create a WordPress account the first time someone logs in with LINE', 'moksa-line-login' ) );
				// Administrator is deliberately absent: a self-service login
				// flow must not be able to mint administrators.
				$roles = wp_roles()->get_names();
				unset( $roles['administrator'] );
				$select( 'new_user_role', __( 'Role for new accounts', 'moksa-line-login' ), $roles );
				$checkbox( 'sync_profile', __( 'Profile sync', 'moksa-line-login' ), __( 'Keep the WordPress display name in step with the LINE display name', 'moksa-line-login' ) );
				$checkbox( 'request_email', __( 'Email address', 'moksa-line-login' ), __( 'Request the email scope (needs approval from LINE first)', 'moksa-line-login' ) );
				$checkbox( 'link_by_email', __( 'Match by email', 'moksa-line-login' ), __( 'Link to an existing account when the email matches', 'moksa-line-login' ) );
				?>
				<tr>
					<th scope="row"></th>
					<td>
						<p class="description moksa-warning">
							<?php esc_html_e( 'Matching by email means anyone who controls a LINE account with that address can sign in as the matching WordPress user. Leave it off unless you know every LINE email on your site is verified and trusted.', 'moksa-line-login' ); ?>
						</p>
					</td>
				</tr>
				<?php
				$select(
					'bot_prompt',
					__( 'Invite to add the bot', 'moksa-line-login' ),
					array(
						'none'       => __( 'Do not invite', 'moksa-line-login' ),
						'normal'     => __( 'Show an option to add as a friend', 'moksa-line-login' ),
						'aggressive' => __( 'Show the add-friend step prominently', 'moksa-line-login' ),
					),
					__( 'Offers the official account while the visitor is logging in.', 'moksa-line-login' )
				);
				$field( 'login_redirect', __( 'Default destination', 'moksa-line-login' ), __( 'Where to send people after login. Leave blank to return them to the page they came from.', 'moksa-line-login' ), 'url' );
				?>

			<?php elseif ( 'messaging' === $current ) : ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'Webhook URL', 'moksa-line-login' ); ?></th>
					<td>
						<code class="moksa-copyable"><?php echo esc_html( WebhookModule::endpoint_url() ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Paste this into the Messaging API channel, turn on "Use webhook", then press Verify.', 'moksa-line-login' ); ?>
						</p>
					</td>
				</tr>

				<?php
				$field( 'messaging_channel_id', __( 'Channel ID', 'moksa-line-login' ), __( 'From the Messaging API channel.', 'moksa-line-login' ) );
				$field( 'messaging_secret', __( 'Channel secret', 'moksa-line-login' ), __( 'Webhook signatures are verified with this. Without it, nothing the bot receives can be trusted.', 'moksa-line-login' ), 'password' );
				$select(
					'token_mode',
					__( 'Access token', 'moksa-line-login' ),
					array(
						'stateless'  => __( 'Issue short-lived tokens automatically (recommended)', 'moksa-line-login' ),
						'long_lived' => __( 'Use a long-lived token I paste below', 'moksa-line-login' ),
					),
					__( 'Short-lived tokens last 15 minutes, are unlimited in number, and never need storing. A long-lived token never expires, which means a leak is unrecoverable without rotating the channel.', 'moksa-line-login' )
				);
				$field( 'messaging_token', __( 'Long-lived token', 'moksa-line-login' ), __( 'Only needed when the mode above is set to long-lived.', 'moksa-line-login' ), 'password' );
				$field( 'bot_basic_id', __( 'Official account ID', 'moksa-line-login' ), __( 'For example @moksa. Used by the add-friend shortcode.', 'moksa-line-login' ) );
				?>
				<tr>
					<th scope="row"><label for="moksa-greeting_message"><?php esc_html_e( 'Greeting message', 'moksa-line-login' ); ?></label></th>
					<td>
						<textarea id="moksa-greeting_message" name="moksa_line[greeting_message]" rows="4" class="large-text"><?php echo esc_textarea( (string) Options::get( 'greeting_message' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Sent when someone adds the account. You can use {display_name}, {site_name} and {site_url}.', 'moksa-line-login' ); ?></p>
					</td>
				</tr>
				<?php
				$field( 'webhook_forward_url', __( 'Forward events to', 'moksa-line-login' ), __( 'Optional. Every webhook delivery is relayed here unchanged, with its signature header, for n8n, Make or your own service.', 'moksa-line-login' ), 'url' );
				$checkbox( 'inbox_enabled', __( 'Inbox', 'moksa-line-login' ), __( 'Record conversations and allow replying from WordPress', 'moksa-line-login' ) );
				?>

			<?php elseif ( 'ai' === $current ) : ?>

				<?php
				$provider  = new AiEngineProvider();
				$available = $provider->is_available();
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Provider status', 'moksa-line-login' ); ?></th>
					<td>
						<?php if ( $available ) : ?>
							<span class="moksa-pill moksa-pill--ok"><?php echo esc_html( $provider->label() ); ?></span>
						<?php else : ?>
							<span class="moksa-pill moksa-pill--warn"><?php esc_html_e( 'AI Engine is not active', 'moksa-line-login' ); ?></span>
							<p class="description"><?php esc_html_e( 'Install and activate AI Engine, then configure a chatbot in it. AI replies stay switched off until then.', 'moksa-line-login' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>

				<?php
				$checkbox( 'ai_enabled', __( 'AI replies', 'moksa-line-login' ), __( 'Let the AI answer messages no rule or flow handled', 'moksa-line-login' ) );

				if ( $available ) {
					$select( 'ai_bot_id', __( 'Chatbot', 'moksa-line-login' ), $provider->chatbots(), __( 'Whichever chatbot you have set up in AI Engine, including its knowledge base.', 'moksa-line-login' ) );
				} else {
					$field( 'ai_bot_id', __( 'Chatbot ID', 'moksa-line-login' ), __( 'The bot id from AI Engine.', 'moksa-line-login' ) );
				}

				$field( 'ai_daily_cap', __( 'Daily reply limit', 'moksa-line-login' ), __( 'Stops the AI answering after this many replies in a day. Set to 0 for no limit -- but every reply costs money.', 'moksa-line-login' ), 'number' );
				$field( 'ai_max_chars', __( 'Maximum reply length', 'moksa-line-login' ), __( 'Answers longer than this are truncated. LINE hard-limits a text message at 5000 characters.', 'moksa-line-login' ), 'number' );
				$field( 'ai_handoff_keyword', __( 'Hand-off keyword', 'moksa-line-login' ), __( 'When a message contains this, the bot stops answering and the conversation is flagged for a human.', 'moksa-line-login' ) );
				?>

			<?php elseif ( 'liff' === $current ) : ?>

				<?php
				$field( 'liff_id', __( 'Default LIFF ID', 'moksa-line-login' ), __( 'Used by the LIFF shortcodes unless one is given explicitly.', 'moksa-line-login' ) );
				$field( 'liff_chat_id', __( 'Chat LIFF ID', 'moksa-line-login' ), __( 'Optional separate LIFF app for the chat shortcode.', 'moksa-line-login' ) );
				$field( 'liff_pay_id', __( 'Payment LIFF ID', 'moksa-line-login' ), __( 'Optional separate LIFF app for in-app payment pages.', 'moksa-line-login' ) );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Shortcodes', 'moksa-line-login' ); ?></th>
					<td>
						<p><code>[moksa_liff_profile]</code> &mdash; <?php esc_html_e( 'greets the visitor by their LINE name', 'moksa-line-login' ); ?></p>
						<p><code>[moksa_line_chat]</code> &mdash; <?php esc_html_e( 'a message box that posts into the inbox', 'moksa-line-login' ); ?></p>
						<p><code>[moksa_line_login]</code> &mdash; <?php esc_html_e( 'the sign-in button', 'moksa-line-login' ); ?></p>
						<p><code>[moksa_line_add_friend]</code> &mdash; <?php esc_html_e( 'a link to your official account', 'moksa-line-login' ); ?></p>
					</td>
				</tr>

			<?php elseif ( 'pay' === $current ) : ?>

				<?php
				$checkbox( 'pay_enabled', __( 'LINE Pay', 'moksa-line-login' ), __( 'Enable LINE Pay for WooCommerce and payment links', 'moksa-line-login' ) );
				$field( 'pay_channel_id', __( 'Pay Channel ID', 'moksa-line-login' ), __( 'From the LINE Pay merchant console, not the Developers Console.', 'moksa-line-login' ) );
				$field( 'pay_channel_secret', __( 'Pay Channel secret', 'moksa-line-login' ), '', 'password' );
				$checkbox( 'pay_sandbox', __( 'Sandbox', 'moksa-line-login' ), __( 'Use the sandbox environment (no real money moves)', 'moksa-line-login' ) );
				$select(
					'pay_currency',
					__( 'Currency', 'moksa-line-login' ),
					array( 'TWD' => 'TWD', 'JPY' => 'JPY', 'USD' => 'USD', 'THB' => 'THB' ),
					__( 'Must match both your LINE Pay merchant currency and the WooCommerce store currency, or the gateway hides itself at checkout.', 'moksa-line-login' )
				);
				$checkbox( 'pay_capture', __( 'Capture immediately', 'moksa-line-login' ), __( 'Take the money as soon as the customer confirms', 'moksa-line-login' ) );
				?>
				<tr>
					<th scope="row"></th>
					<td>
						<p class="description">
							<?php esc_html_e( 'With immediate capture off, payments are only authorised. You then have to capture or void each one, and an order cancelled in WooCommerce releases its authorisation automatically.', 'moksa-line-login' ); ?>
						</p>
					</td>
				</tr>

			<?php else : ?>

				<?php
				$select(
					'log_level',
					__( 'Log level', 'moksa-line-login' ),
					array(
						'debug'   => __( 'Everything', 'moksa-line-login' ),
						'info'    => __( 'Notable events', 'moksa-line-login' ),
						'warning' => __( 'Warnings and errors', 'moksa-line-login' ),
						'error'   => __( 'Errors only', 'moksa-line-login' ),
						'off'     => __( 'Off', 'moksa-line-login' ),
					)
				);
				$field( 'log_retention_days', __( 'Keep logs for', 'moksa-line-login' ), __( 'Days. Older rows are deleted daily.', 'moksa-line-login' ), 'number' );
				$field( 'inbox_retention_days', __( 'Keep conversations for', 'moksa-line-login' ), __( 'Days. Set to 0 to keep messages forever.', 'moksa-line-login' ), 'number' );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Encryption', 'moksa-line-login' ); ?></th>
					<td>
						<?php if ( \Moksa\Line\Support\Crypto::available() ) : ?>
							<span class="moksa-pill moksa-pill--ok"><?php esc_html_e( 'Credentials are encrypted at rest', 'moksa-line-login' ); ?></span>
							<p class="description"><?php esc_html_e( 'Keys are derived from this site\'s WordPress salts, so a database dump alone cannot reveal them. Changing the salts in wp-config.php makes stored credentials unreadable and they will need re-entering.', 'moksa-line-login' ); ?></p>
						<?php else : ?>
							<span class="moksa-pill moksa-pill--warn"><?php esc_html_e( 'Not available on this server', 'moksa-line-login' ); ?></span>
							<p class="description"><?php esc_html_e( 'OpenSSL with AES-256-GCM is missing, so credentials are stored as plain text.', 'moksa-line-login' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>

			<?php endif; ?>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
