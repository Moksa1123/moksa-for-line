<?php
/**
 * Settings screen.
 *
 * @package Mofoline
 */

use Mofoline\Admin\Ajax;
use Mofoline\Admin\AdminModule;
use Mofoline\Bot\Ai\Providers;
use Mofoline\Frontend\ShortcodeModule;
use Mofoline\Login\LoginModule;
use Mofoline\Support\Options;
use Mofoline\Webhook\WebhookModule;

defined( 'ABSPATH' ) || exit;

( static function (): void {
	$tabs = array(
		'general'   => __( 'LINE Login', 'moksa-for-line' ),
		'messaging' => __( 'Messaging API', 'moksa-for-line' ),
		'ai'        => __( 'AI replies', 'moksa-for-line' ),
		'liff'      => __( 'LIFF', 'moksa-for-line' ),
		'pay'       => __( 'LINE Pay', 'moksa-for-line' ),
		'advanced'  => __( 'Advanced', 'moksa-for-line' ),
	);

	if ( class_exists( 'WooCommerce' ) ) {
		$tabs = array_slice( $tabs, 0, 4, true )
			+ array( 'woo' => __( 'WooCommerce', 'moksa-for-line' ) )
			+ array_slice( $tabs, 4, null, true );
	}

	$current = Ajax::query_key( 'tab', 'general' );
	$current = isset( $tabs[ $current ] ) ? $current : 'general';

	/**
	 * Render a text field.
	 *
	 * @param string $key   Option key.
	 * @param string $label Field label.
	 * @param string $help  Help text.
	 * @param string $type  Input type.
	 * @param string $when  Optional "otherkey=value" condition; the row is only
	 *                      shown while that other control holds that value, so a
	 *                      field nobody can act on yet is not offered as if they
	 *                      could.
	 */
	$field = function ( $key, $label, $help = '', $type = 'text', $when = '' ) {
		$schema = Options::schema();
		$secret = ! empty( $schema[ $key ]['secret'] );
		$value  = $secret ? Options::mask( $key ) : (string) Options::get( $key );
		?>
		<tr<?php echo '' !== $when ? ' data-moksa-visible-when="' . esc_attr( $when ) . '"' : ''; ?>>
			<th scope="row"><label for="moksa-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="<?php echo esc_attr( $type ); ?>"
					id="moksa-<?php echo esc_attr( $key ); ?>"
					name="mofoline[<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( $value ); ?>"
					class="regular-text"
					<?php // A text field followed by a password field is what browsers
					// autofill as a login form, which put an email address into the
					// Channel ID. new-password is the token they actually honour. ?>
					autocomplete="<?php echo $secret ? 'new-password' : 'off'; ?>"
					<?php echo $secret ? 'placeholder="' . esc_attr__( 'Leave unchanged to keep the stored value', 'moksa-for-line' ) . '"' : ''; ?> />
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
				<input type="hidden" name="mofoline_booleans[]" value="<?php echo esc_attr( $key ); ?>" />
				<label>
					<input type="checkbox" name="mofoline[<?php echo esc_attr( $key ); ?>]" value="1"
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
				<select id="moksa-<?php echo esc_attr( $key ); ?>" name="mofoline[<?php echo esc_attr( $key ); ?>]">
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
	<div class="wrap mofoline-wrap">
		<h1><?php esc_html_e( 'LINE settings', 'moksa-for-line' ); ?></h1>

		<?php if ( Ajax::query_flag( 'updated' ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'moksa-for-line' ); ?></p></div>
		<?php endif; ?>

		<?php
		$warnings = get_transient( 'mofoline_settings_warning' );

		if ( is_array( $warnings ) && $warnings ) :
			$names = array(
				'channel_id'           => __( 'LINE Login Channel ID', 'moksa-for-line' ),
				'messaging_channel_id' => __( 'Messaging API Channel ID', 'moksa-for-line' ),
				'pay_channel_id'       => __( 'LINE Pay Channel ID', 'moksa-for-line' ),
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
						esc_html__( 'This does not look like a channel ID: %s. A LINE channel ID is a number, usually ten digits. If your browser filled this in for you, clear it and paste the value from the LINE Developers Console.', 'moksa-for-line' ),
						esc_html( implode( ', ', $labels ) )
					);
					?>
				</p>
			</div>
			<?php
			delete_transient( 'mofoline_settings_warning' );
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
			<?php wp_nonce_field( 'mofoline_settings' ); ?>
			<input type="hidden" name="action" value="mofoline_save_settings" />
			<input type="hidden" name="tab" value="<?php echo esc_attr( $current ); ?>" />

			<table class="form-table" role="presentation">
				<?php if ( 'general' === $current ) : ?>

					<tr>
						<th scope="row"><?php esc_html_e( 'Callback URL', 'moksa-for-line' ); ?></th>
						<td>
							<button type="button" class="moksa-copyable" data-moksa-copy="<?php echo esc_attr( LoginModule::callback_url() ); ?>"><code><?php echo esc_html( LoginModule::callback_url() ); ?></code></button>
							<p class="description">
								<?php esc_html_e( 'Paste this into the LINE Login channel as a Callback URL. It must match exactly, including https and any trailing path.', 'moksa-for-line' ); ?>
							</p>
						</td>
					</tr>

					<?php
					$field( 'channel_id', __( 'Channel ID', 'moksa-for-line' ), __( 'From the LINE Login channel, not the Messaging API one.', 'moksa-for-line' ) );
					$field( 'channel_secret', __( 'Channel secret', 'moksa-for-line' ), __( 'Stored encrypted. Only the last four characters are ever shown again.', 'moksa-for-line' ), 'password' );
					$checkbox( 'auto_register', __( 'New visitors', 'moksa-for-line' ), __( 'Create a WordPress account the first time someone logs in with LINE', 'moksa-for-line' ) );
					// Only roles that can do nothing but read: a self-service
					// sign-up must not be able to mint an editor, let alone an
					// administrator. LoginModule enforces the same list on save
					// and at sign-up, so the choice here is never more than a
					// choice among the harmless ones.
					$select( 'new_user_role', __( 'Role for new accounts', 'moksa-for-line' ), LoginModule::registration_roles(), __( 'Only roles that can do nothing beyond reading the site are offered.', 'moksa-for-line' ) );
					$checkbox( 'sync_profile', __( 'Profile sync', 'moksa-for-line' ), __( 'Keep the WordPress display name in step with the LINE display name', 'moksa-for-line' ) );
					$checkbox( 'request_email', __( 'Email address', 'moksa-for-line' ), __( 'Request the email scope (needs approval from LINE first)', 'moksa-for-line' ) );
					$checkbox( 'link_by_email', __( 'Match by email', 'moksa-for-line' ), __( 'Link to an existing account when the email matches', 'moksa-for-line' ) );
					?>
					<tr>
						<th scope="row"></th>
						<td>
							<p class="description moksa-warning">
								<?php esc_html_e( 'Matching by email means anyone who controls a LINE account with that address can sign in as the matching WordPress user. Leave it off unless you know every LINE email on your site is verified and trusted.', 'moksa-for-line' ); ?>
							</p>
						</td>
					</tr>
					<?php
					$select(
						'bot_prompt',
						__( 'Invite to add the bot', 'moksa-for-line' ),
						array(
							'none'       => __( 'Do not invite', 'moksa-for-line' ),
							'normal'     => __( 'Show an option to add as a friend', 'moksa-for-line' ),
							'aggressive' => __( 'Show the add-friend step prominently', 'moksa-for-line' ),
						),
						__( 'Offers the official account while the visitor is logging in.', 'moksa-for-line' )
					);
					?>

					<?php
					// A URL typed from memory is a URL with a typo in it, and the
					// symptom -- everyone landing on a 404 after signing in -- does
					// not point back at this field. The pages are right here, so
					// offer them; the text box stays for anything not on the list.
					$destination = (string) Options::get( 'login_redirect' );
					$choices     = array();

					if ( function_exists( 'wc_get_page_permalink' ) ) {
						foreach ( array( 'myaccount' => __( 'My account', 'moksa-for-line' ), 'shop' => __( 'Shop', 'moksa-for-line' ), 'cart' => __( 'Cart', 'moksa-for-line' ) ) as $wc_page => $wc_label ) {
							$wc_url = wc_get_page_permalink( $wc_page );

							if ( $wc_url ) {
								$choices[ $wc_url ] = $wc_label;
							}
						}
					}

					$choices[ home_url( '/' ) ] = __( 'Home page', 'moksa-for-line' );

					foreach ( get_pages( array( 'sort_column' => 'menu_order,post_title', 'number' => 100 ) ) as $page_option ) {
						$page_url = get_permalink( $page_option );

						if ( $page_url && ! isset( $choices[ $page_url ] ) ) {
							$choices[ $page_url ] = $page_option->post_title;
						}
					}

					$is_listed = '' !== $destination && isset( $choices[ $destination ] );
					$mode      = '' === $destination ? 'back' : ( $is_listed ? 'page' : 'custom' );
					?>
					<tr>
						<th scope="row"><label for="moksa-login-destination"><?php esc_html_e( 'Default destination', 'moksa-for-line' ); ?></label></th>
						<td>
							<select id="moksa-login-destination" class="widefat" data-moksa-destination>
								<option value="back" <?php selected( $mode, 'back' ); ?>><?php esc_html_e( 'Back to the page they came from', 'moksa-for-line' ); ?></option>
								<?php foreach ( $choices as $choice_url => $choice_label ) : ?>
									<option value="<?php echo esc_attr( $choice_url ); ?>" <?php selected( $destination, $choice_url ); ?>>
										<?php echo esc_html( $choice_label ); ?>
									</option>
								<?php endforeach; ?>
								<option value="custom" <?php selected( $mode, 'custom' ); ?>><?php esc_html_e( 'Another address...', 'moksa-for-line' ); ?></option>
							</select>

							<p class="description"><?php esc_html_e( 'Where to send people after they sign in with LINE.', 'moksa-for-line' ); ?></p>

							<p data-moksa-destination-custom<?php echo 'custom' === $mode ? '' : ' hidden'; ?>>
								<label for="moksa-login_redirect" class="screen-reader-text"><?php esc_html_e( 'Address to send people to', 'moksa-for-line' ); ?></label>
								<input type="url" id="moksa-login_redirect" name="mofoline[login_redirect]"
									value="<?php echo esc_attr( $destination ); ?>" class="widefat"
									placeholder="https://" autocomplete="off" data-moksa-destination-url />
							</p>
						</td>
					</tr>
					<?php
					?>

					<?php
					$select(
						'login_duration',
						__( 'Stay signed in for', 'moksa-for-line' ),
						array(
							'browser' => __( 'Until they close the browser', 'moksa-for-line' ),
							'default' => __( '14 days (the WordPress default)', 'moksa-for-line' ),
							'30'      => __( '30 days', 'moksa-for-line' ),
							'90'      => __( '90 days', 'moksa-for-line' ),
						),
						__( 'Only applies to signing in with LINE. Signing in with a password keeps whatever WordPress does.', 'moksa-for-line' )
					);
					?>

					<tr>
						<th scope="row" colspan="2"><h2 class="moksa-subhead"><?php esc_html_e( 'Login button', 'moksa-for-line' ); ?></h2></th>
					</tr>

					<?php
					$field( 'button_text', __( 'Button text', 'moksa-for-line' ), __( 'Leave blank to use the shortcode label, or the default wording.', 'moksa-for-line' ) );
					$field(
						'button_bg_color',
						__( 'Background', 'moksa-for-line' ),
						// Worth stating plainly: LINE's login button guidelines
						// name #06C755 with white text and list non-designated
						// colours among the mistakes to avoid. A shop that restyles
						// this to match their theme can fail LINE's channel review.
						__( 'LINE requires #06C755 with white text on the login button. Changing it can fail channel review.', 'moksa-for-line' ),
						'color'
					);
					$field( 'button_text_color', __( 'Text colour', 'moksa-for-line' ), __( 'LINE requires #FFFFFF.', 'moksa-for-line' ), 'color' );

					// Placement, not appearance. Corner radius, width and height
					// were settings here until it became clear what they produced:
					// one button on the page shaped unlike every other, because the
					// theme was never consulted. Size and type now come from the
					// theme, and these decide where the button goes.
					$select(
						'button_position',
						__( 'Position', 'moksa-for-line' ),
						array(
							'above' => __( 'Above the login form', 'moksa-for-line' ),
							'below' => __( 'Below the login form', 'moksa-for-line' ),
						),
						__( 'Where the button sits on the account and checkout pages.', 'moksa-for-line' )
					);

					$select(
						'button_align',
						__( 'Alignment', 'moksa-for-line' ),
						array(
							'start'  => __( 'Follow the form', 'moksa-for-line' ),
							'center' => __( 'Centred', 'moksa-for-line' ),
							'full'   => __( 'Full width', 'moksa-for-line' ),
						),
						__( 'Everything else about the shape -- corners, height, type -- comes from your theme, so the button matches the page it is on.', 'moksa-for-line' )
					);

					$checkbox( 'button_divider', __( 'Divider', 'moksa-for-line' ), __( 'Draw a line with "or" between the button and the form', 'moksa-for-line' ) );
					?>

					<tr>
						<th scope="row"><?php esc_html_e( 'Preview', 'moksa-for-line' ); ?></th>
						<td>
							<?php
							// Rendered with the real front-end markup and settings, so
							// what is shown here is what a visitor sees.
							wp_enqueue_style( 'mofoline-front' );

							printf(
								'<a class="mofoline-button" href="#" onclick="return false;" style="%s">%s</a>',
								esc_attr( ShortcodeModule::style_declarations() ),
								esc_html(
									'' !== trim( (string) Options::get( 'button_text' ) )
										? (string) Options::get( 'button_text' )
										: __( 'Log in with LINE', 'moksa-for-line' )
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Save to update the preview.', 'moksa-for-line' ); ?></p>
						</td>
					</tr>

				<?php elseif ( 'messaging' === $current ) : ?>

					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook URL', 'moksa-for-line' ); ?></th>
						<td>
							<button type="button" class="moksa-copyable" data-moksa-copy="<?php echo esc_attr( WebhookModule::endpoint_url() ); ?>"><code><?php echo esc_html( WebhookModule::endpoint_url() ); ?></code></button>
							<p class="description">
								<?php esc_html_e( 'Paste this into the Messaging API channel, turn on "Use webhook", then press Verify.', 'moksa-for-line' ); ?>
							</p>

							<?php // Checking costs nothing and answers the one question the URL alone cannot: is LINE actually delivering here? ?>
							<p>
								<button type="button" class="button" data-moksa-webhook-check><?php esc_html_e( 'Check what LINE has', 'moksa-for-line' ); ?></button>
								<button type="button" class="button" data-moksa-webhook-set><?php esc_html_e( 'Point LINE at this site', 'moksa-for-line' ); ?></button>
							</p>

							<div class="moksa-feedback" data-moksa-webhook data-moksa-feedback></div>
						</td>
					</tr>

					<?php
					$field( 'messaging_channel_id', __( 'Channel ID', 'moksa-for-line' ), __( 'From the Messaging API channel.', 'moksa-for-line' ) );
					$field( 'messaging_secret', __( 'Channel secret', 'moksa-for-line' ), __( 'Webhook signatures are verified with this. Without it, nothing the bot receives can be trusted.', 'moksa-for-line' ), 'password' );
					$select(
						'token_mode',
						__( 'Channel access token', 'moksa-for-line' ),
						array(
							'stateless'  => __( 'Issue short-lived tokens automatically (recommended)', 'moksa-for-line' ),
							'long_lived' => __( 'Use a long-lived token I paste below', 'moksa-for-line' ),
						),
						__( 'Short-lived tokens last 15 minutes, are unlimited in number, and never need storing. A long-lived token never expires, which means a leak is unrecoverable without rotating the channel.', 'moksa-for-line' )
					);
					$field(
						'messaging_token',
						__( 'Long-lived token', 'moksa-for-line' ),
						__( 'Never expires, so treat it like a password. Rotate the channel if it leaks.', 'moksa-for-line' ),
						'password',
						'token_mode=long_lived'
					);
					$field( 'bot_basic_id', __( 'Official account ID', 'moksa-for-line' ), __( 'For example @moksa. Used by the add-friend shortcode.', 'moksa-for-line' ) );
					?>
					<tr>
						<th scope="row"><label for="moksa-greeting_message"><?php esc_html_e( 'Greeting message', 'moksa-for-line' ); ?></label></th>
						<td>
							<textarea id="moksa-greeting_message" name="mofoline[greeting_message]" rows="4" class="large-text"><?php echo esc_textarea( (string) Options::get( 'greeting_message' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Sent when someone adds the account. You can use {display_name}, {site_name} and {site_url}.', 'moksa-for-line' ); ?></p>
						</td>
					</tr>
					<?php
					$field( 'webhook_forward_url', __( 'Forward events to', 'moksa-for-line' ), __( 'Optional. Every webhook delivery is relayed here unchanged, with its signature header, for n8n, Make or your own service.', 'moksa-for-line' ), 'url' );
					$checkbox( 'inbox_enabled', __( 'Inbox', 'moksa-for-line' ), __( 'Record conversations and allow replying from WordPress', 'moksa-for-line' ) );
					?>

				<?php elseif ( 'ai' === $current ) : ?>

					<?php
					$selected  = (string) Options::get( 'ai_provider' );
					$provider  = Providers::make();
					$installed = $provider && $provider->installed();
					$ready     = $provider && $provider->is_available();

					$select( 'ai_provider', __( 'AI service', 'moksa-for-line' ), Providers::choices(), __( 'The WordPress AI Client is part of WordPress itself from version 7.0, so nothing needs installing to use it. It still needs a provider connected before it can answer.', 'moksa-for-line' ) );
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Provider status', 'moksa-for-line' ); ?></th>
						<td>
							<?php
							// Three states, not two. "Present" and "ready" are
							// different questions: a service can be installed,
							// list its options, and still answer nothing because
							// no credentials have been connected to it.
							?>
							<?php if ( ! $provider ) : ?>
								<span class="moksa-pill moksa-pill--warn"><?php esc_html_e( 'No AI service selected', 'moksa-for-line' ); ?></span>
							<?php elseif ( $ready ) : ?>
								<span class="moksa-pill moksa-pill--ok"><?php echo esc_html( $provider->label() ); ?></span>
							<?php elseif ( $installed ) : ?>
								<span class="moksa-pill moksa-pill--warn"><?php echo esc_html( $provider->label() ); ?></span>
								<p class="description">
									<?php if ( 'core' === $selected ) : ?>
										<?php esc_html_e( 'WordPress has the AI Client but no provider is connected, so it cannot answer anything yet. Install the AI plugin from WordPress.org and connect a provider there.', 'moksa-for-line' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'AI Engine is active but has no AI service with an API key, so it cannot answer anything yet. Add one under Meow Apps > AI Engine > Settings.', 'moksa-for-line' ); ?>
									<?php endif; ?>
								</p>
							<?php elseif ( 'core' === $selected ) : ?>
								<span class="moksa-pill moksa-pill--warn"><?php esc_html_e( 'This WordPress has no AI Client', 'moksa-for-line' ); ?></span>
								<p class="description"><?php esc_html_e( 'The AI Client ships with WordPress 7.0 and later. On an older WordPress, choose AI Engine instead or upgrade.', 'moksa-for-line' ); ?></p>
							<?php else : ?>
								<span class="moksa-pill moksa-pill--warn"><?php esc_html_e( 'AI Engine is not active', 'moksa-for-line' ); ?></span>
								<p class="description"><?php esc_html_e( 'Install and activate AI Engine, then configure a chatbot in it. AI replies stay switched off until then.', 'moksa-for-line' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>

					<?php
					$checkbox( 'ai_enabled', __( 'AI replies', 'moksa-for-line' ), __( 'Let the AI answer messages no rule or flow handled', 'moksa-for-line' ) );

					// A chatbot with its own persona and knowledge base is an AI
					// Engine idea; the core client has no equivalent, so the field
					// is only offered where it means something.
					if ( 'ai_engine' === $selected ) {
						if ( $installed && method_exists( $provider, 'chatbots' ) ) {
							$select( 'ai_bot_id', __( 'Chatbot', 'moksa-for-line' ), $provider->chatbots(), __( 'Whichever chatbot you have set up in AI Engine, including its knowledge base.', 'moksa-for-line' ) );
						} else {
							$field( 'ai_bot_id', __( 'Chatbot ID', 'moksa-for-line' ), __( 'The bot id from AI Engine.', 'moksa-for-line' ) );
						}
					}

					$field( 'ai_daily_cap', __( 'Daily reply limit', 'moksa-for-line' ), __( 'Stops the AI answering after this many replies in a day. Set to 0 for no limit -- but every reply costs money.', 'moksa-for-line' ), 'number' );
					$field( 'ai_max_chars', __( 'Maximum reply length', 'moksa-for-line' ), __( 'Answers longer than this are truncated. LINE hard-limits a text message at 5000 characters.', 'moksa-for-line' ), 'number' );
					$field( 'ai_handoff_keyword', __( 'Hand-off keyword', 'moksa-for-line' ), __( 'When a message contains this, the bot stops answering and the conversation is flagged for a human.', 'moksa-for-line' ) );
					?>

				<?php elseif ( 'liff' === $current ) : ?>

					<?php $liff_endpoint = \Mofoline\Liff\LiffModule::endpoint_url(); ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Endpoint URL', 'moksa-for-line' ); ?></th>
						<td>
							<?php if ( '' !== $liff_endpoint ) : ?>
								<button type="button" class="moksa-copyable" data-moksa-copy="<?php echo esc_attr( $liff_endpoint ); ?>"><code><?php echo esc_html( $liff_endpoint ); ?></code></button>
								<p class="description">
									<?php esc_html_e( 'Paste this as the Endpoint URL when you add a LIFF app in the LINE Developers Console, under your LINE Login channel. Size: Full. Scopes: openid and profile. Then put the LIFF ID it gives you in the field below.', 'moksa-for-line' ); ?>
								</p>
							<?php else : ?>
								<p class="description">
									<?php esc_html_e( 'A LIFF app opens a page on this site inside LINE. There is no page carrying the LIFF shortcodes yet, so there is nothing to register.', 'moksa-for-line' ); ?>
								</p>
								<p>
									<button type="button" class="button" data-moksa-liff-create><?php esc_html_e( 'Create the page', 'moksa-for-line' ); ?></button>
								</p>
								<div class="moksa-feedback" data-moksa-feedback data-moksa-liff-feedback></div>
							<?php endif; ?>
						</td>
					</tr>

					<?php
					$field( 'liff_id', __( 'Default LIFF ID', 'moksa-for-line' ), __( 'Used by the LIFF shortcodes unless one is given explicitly.', 'moksa-for-line' ) );
					$field( 'liff_chat_id', __( 'Chat LIFF ID', 'moksa-for-line' ), __( 'Optional separate LIFF app for the chat shortcode.', 'moksa-for-line' ) );
					$field( 'liff_pay_id', __( 'Payment LIFF ID', 'moksa-for-line' ), __( 'Optional separate LIFF app for in-app payment pages.', 'moksa-for-line' ) );
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Shortcodes', 'moksa-for-line' ); ?></th>
						<td>
							<p><code>[mofoline_liff_profile]</code> &mdash; <?php esc_html_e( 'greets the visitor by their LINE name', 'moksa-for-line' ); ?></p>
							<p><code>[mofoline_chat]</code> &mdash; <?php esc_html_e( 'a message box that posts into the inbox', 'moksa-for-line' ); ?></p>
							<p><code>[mofoline_login]</code> &mdash; <?php esc_html_e( 'the sign-in button', 'moksa-for-line' ); ?></p>
							<p><code>[mofoline_add_friend]</code> &mdash; <?php esc_html_e( 'a link to your official account', 'moksa-for-line' ); ?></p>
						</td>
					</tr>

				<?php elseif ( 'woo' === $current ) : ?>

					<?php
					$checkbox( 'woo_notify', __( 'Order notifications', 'moksa-for-line' ), __( 'Message the customer in LINE when their order status changes', 'moksa-for-line' ) );
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Notify on', 'moksa-for-line' ); ?></th>
						<td>
							<?php
							$selected_statuses = (array) Options::get( 'woo_notify_statuses' );

							foreach ( \Mofoline\Woo\WooModule::statuses() as $slug => $label ) :
								?>
								<label class="moksa-inline-check">
									<input type="checkbox" name="mofoline_woo_statuses[]" value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, $selected_statuses, true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description">
								<?php esc_html_e( 'Each notification is a push message and is billed. The customer only hears about a given order and status once, however many times the status is set.', 'moksa-for-line' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Templates', 'moksa-for-line' ); ?></th>
						<td>
							<p class="description">
								<?php
								printf(
									/* translators: %s: URL of the notification templates screen. */
									wp_kses_post( __( 'The statuses above use a built-in card. For full control -- your own Flex design, and conditions such as payment or shipping method -- create <a href="%s">notification templates</a>. When a template matches, it replaces the built-in card.', 'moksa-for-line' ) ),
									esc_url( admin_url( 'edit.php?post_type=' . \Mofoline\Woo\NotifyTemplates::POST_TYPE ) )
								);
								?>
							</p>
						</td>
					</tr>

					<?php
					$field( 'woo_notify_delay', __( 'Delay before sending', 'moksa-for-line' ), __( 'Seconds. Useful when another plugin adjusts the order right after the status changes. 0 sends immediately.', 'moksa-for-line' ), 'number' );
					$checkbox( 'woo_wait_for_tracking', __( 'Wait for tracking', 'moksa-for-line' ), __( 'Hold the shipping notification until the tracking number appears', 'moksa-for-line' ) );
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tracking wait', 'moksa-for-line' ); ?></th>
						<td>
							<label>
								<?php esc_html_e( 'On status', 'moksa-for-line' ); ?>
								<select name="mofoline[woo_tracking_status]">
									<?php foreach ( \Mofoline\Woo\NotifyTemplates::order_statuses() as $slug => $label ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) Options::get( 'woo_tracking_status' ), $slug ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>
							<label>
								<?php esc_html_e( 'check every', 'moksa-for-line' ); ?>
								<input type="number" name="mofoline[woo_tracking_delay]" class="small-text"
									value="<?php echo esc_attr( (string) Options::get( 'woo_tracking_delay' ) ); ?>" />
								<?php esc_html_e( 'seconds,', 'moksa-for-line' ); ?>
							</label>
							<label>
								<?php esc_html_e( 'up to', 'moksa-for-line' ); ?>
								<input type="number" name="mofoline[woo_tracking_retries]" class="small-text"
									value="<?php echo esc_attr( (string) Options::get( 'woo_tracking_retries' ) ); ?>" />
								<?php esc_html_e( 'times', 'moksa-for-line' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Tracking numbers are read from ECPay, RY Tools, Advanced Shipment Tracking and WooCommerce Shipment Tracking. After the last attempt the notification is sent anyway, without the number.', 'moksa-for-line' ); ?>
							</p>
						</td>
					</tr>

					<?php
					$field( 'woo_history_days', __( 'Keep notification history for', 'moksa-for-line' ), __( 'Days. Set to 0 to keep it indefinitely.', 'moksa-for-line' ), 'number' );
					$checkbox( 'woo_account_tab', __( 'My Account tab', 'moksa-for-line' ), __( 'Add a LINE tab where customers can link and unlink their account', 'moksa-for-line' ) );
					$checkbox( 'woo_login_buttons', __( 'Login buttons', 'moksa-for-line' ), __( 'Offer LINE sign-in on the account and checkout pages', 'moksa-for-line' ) );
					$checkbox( 'member_card', __( 'Membership card', 'moksa-for-line' ), __( 'Show customers a card with a QR code that staff can scan at the counter', 'moksa-for-line' ) );
					?>

					<?php if ( Options::get( 'member_card' ) ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Counter lookup', 'moksa-for-line' ); ?></th>
							<td>
								<p class="description">
									<?php esc_html_e( 'Staff scan a card with any phone camera. When the camera will not read it, they can type the code the customer reads out:', 'moksa-for-line' ); ?>
								</p>
								<p>
									<a href="<?php echo esc_url( home_url( '/' . \Mofoline\Member\MemberModule::SLUG . '/' ) ); ?>" target="_blank" rel="noopener">
										<?php echo esc_html( home_url( '/' . \Mofoline\Member\MemberModule::SLUG . '/' ) ); ?>
									</a>
								</p>
								<p class="description">
									<?php esc_html_e( 'Both pages are staff-only: anyone else who opens a card sees a sign-in prompt and nothing about the customer.', 'moksa-for-line' ); ?>
								</p>
							</td>
						</tr>
					<?php endif; ?>

					<?php
					// Switching this on with no LINE Login channel behind it draws
					// nothing at all: the button is correctly hidden when it cannot
					// work, but from here that looks like the setting is broken.
					if ( Options::get( 'woo_login_buttons' ) && '' === (string) Options::get( 'channel_id' ) ) :
						?>
						<tr>
							<th scope="row"></th>
							<td>
								<div class="notice notice-warning inline">
									<p>
										<?php esc_html_e( 'Those buttons are switched on but nothing is drawn yet: signing in with LINE needs the LINE Login channel, which has no credentials.', 'moksa-for-line' ); ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminModule::SLUG . '-settings&tab=general' ) ); ?>"><?php esc_html_e( 'Fill them in', 'moksa-for-line' ); ?></a>
									</p>
								</div>
							</td>
						</tr>
						<?php
					endif;
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Payments', 'moksa-for-line' ); ?></th>
						<td>
							<p class="description">
								<?php
								printf(
									/* translators: %s: LINE Pay settings tab URL. */
									wp_kses_post( __( 'LINE Pay is configured on the <a href="%s">LINE Pay tab</a>, and the gateway is switched on under WooCommerce > Settings > Payments.', 'moksa-for-line' ) ),
									esc_url( admin_url( 'admin.php?page=' . AdminModule::SLUG . '-settings&tab=pay' ) )
								);
								?>
							</p>
						</td>
					</tr>

				<?php elseif ( 'pay' === $current ) : ?>

					<?php
					$checkbox( 'pay_enabled', __( 'LINE Pay', 'moksa-for-line' ), __( 'Enable LINE Pay for WooCommerce and payment links', 'moksa-for-line' ) );
					$field( 'pay_channel_id', __( 'Pay Channel ID', 'moksa-for-line' ), __( 'From the LINE Pay merchant console, not the Developers Console.', 'moksa-for-line' ) );
					$field( 'pay_channel_secret', __( 'Pay Channel secret', 'moksa-for-line' ), '', 'password' );
					$checkbox( 'pay_sandbox', __( 'Sandbox', 'moksa-for-line' ), __( 'Use the sandbox environment (no real money moves)', 'moksa-for-line' ) );
					$select(
						'pay_currency',
						__( 'Currency', 'moksa-for-line' ),
						array( 'TWD' => 'TWD', 'JPY' => 'JPY', 'USD' => 'USD', 'THB' => 'THB' ),
						__( 'Must match both your LINE Pay merchant currency and the WooCommerce store currency, or the gateway hides itself at checkout.', 'moksa-for-line' )
					);
					$checkbox( 'pay_capture', __( 'Capture immediately', 'moksa-for-line' ), __( 'Take the money as soon as the customer confirms', 'moksa-for-line' ) );
					?>
					<tr>
						<th scope="row"></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'With immediate capture off, payments are only authorised. You then have to capture or void each one, and an order cancelled in WooCommerce releases its authorisation automatically.', 'moksa-for-line' ); ?>
							</p>
						</td>
					</tr>

				<?php else : ?>

					<?php
					$select(
						'log_level',
						__( 'Log level', 'moksa-for-line' ),
						array(
							'debug'   => __( 'Everything', 'moksa-for-line' ),
							'info'    => __( 'Notable events', 'moksa-for-line' ),
							'warning' => __( 'Warnings and errors', 'moksa-for-line' ),
							'error'   => __( 'Errors only', 'moksa-for-line' ),
							'off'     => __( 'Off', 'moksa-for-line' ),
						)
					);
					$field( 'log_retention_days', __( 'Keep logs for', 'moksa-for-line' ), __( 'Days. Older rows are deleted daily.', 'moksa-for-line' ), 'number' );
					$field( 'inbox_retention_days', __( 'Keep conversations for', 'moksa-for-line' ), __( 'Days. Set to 0 to keep messages forever.', 'moksa-for-line' ), 'number' );
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Encryption', 'moksa-for-line' ); ?></th>
						<td>
							<?php if ( \Mofoline\Support\Crypto::available() ) : ?>
								<span class="moksa-pill moksa-pill--ok"><?php esc_html_e( 'Credentials are encrypted at rest', 'moksa-for-line' ); ?></span>
								<p class="description"><?php esc_html_e( 'Keys are derived from this site\'s WordPress salts, so a database dump alone cannot reveal them. Changing the salts in wp-config.php makes stored credentials unreadable and they will need re-entering.', 'moksa-for-line' ); ?></p>
							<?php else : ?>
								<span class="moksa-pill moksa-pill--warn"><?php esc_html_e( 'Not available on this server', 'moksa-for-line' ); ?></span>
								<p class="description"><?php esc_html_e( 'OpenSSL with AES-256-GCM is missing, so credentials are stored as plain text.', 'moksa-for-line' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>

				<?php endif; ?>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
} )();
