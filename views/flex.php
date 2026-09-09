<?php
/**
 * Flex Message templates and editor.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Data\Flex;
use Moksa\Line\Flex\FlexModule;

defined( 'ABSPATH' ) || exit;

$templates = Flex::all();
$starters  = FlexModule::starters();

// The basic ID is the closest thing to the account name the plugin stores. It is
// not the display name the customer sees, but it is at least this account and
// not a placeholder that makes the preview feel generic.
$basic_id     = ltrim( trim( (string) \Moksa\Line\Support\Options::get( 'bot_basic_id' ) ), '@' );
$account_name = '' !== $basic_id ? '@' . $basic_id : __( 'Your official account', 'moksa-line' );
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Flex messages', 'moksa-line' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'The preview is an approximation. Use "Check" to validate against LINE itself, and "Send test" to see the real thing in a chat.', 'moksa-line' ); ?>
	</p>

	<div class="moksa-split moksa-split--wide">
		<div class="moksa-split__main">
			<form class="moksa-panel" data-moksa-flex-form>
				<input type="hidden" name="id" value="0" />

				<p>
					<label for="moksa-flex-name"><?php esc_html_e( 'Template name', 'moksa-line' ); ?></label>
					<input type="text" id="moksa-flex-name" name="name" class="widefat" required />
				</p>

				<p>
					<label for="moksa-flex-alt"><?php esc_html_e( 'Fallback text', 'moksa-line' ); ?></label>
					<input type="text" id="moksa-flex-alt" name="alt_text" class="widefat" maxlength="1500" required />
					<span class="description"><?php esc_html_e( 'Shown in the chat list and in push notifications, where the bubble cannot be drawn.', 'moksa-line' ); ?></span>
				</p>

				<p>
					<label for="moksa-flex-starter"><?php esc_html_e( 'Start from', 'moksa-line' ); ?></label>
					<select id="moksa-flex-starter" data-moksa-flex-starter>
						<option value=""><?php esc_html_e( 'Blank', 'moksa-line' ); ?></option>
						<?php foreach ( $starters as $key => $starter ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"
								data-contents="<?php echo esc_attr( (string) wp_json_encode( $starter['contents'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?>">
								<?php echo esc_html( $starter['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>

				<p>
					<label for="moksa-flex-contents"><?php esc_html_e( 'Bubble or carousel JSON', 'moksa-line' ); ?></label>
					<textarea id="moksa-flex-contents" name="contents" rows="20" class="widefat code" spellcheck="false" data-moksa-flex-json></textarea>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save template', 'moksa-line' ); ?></button>
					<button type="button" class="button" data-moksa-flex-check><?php esc_html_e( 'Check', 'moksa-line' ); ?></button>
					<button type="button" class="button" data-moksa-flex-reset><?php esc_html_e( 'New template', 'moksa-line' ); ?></button>
				</p>

				<p>
					<label for="moksa-flex-test"><?php esc_html_e( 'Send a test to', 'moksa-line' ); ?></label>
					<input type="text" id="moksa-flex-test" class="regular-text" placeholder="U1234..." data-moksa-flex-test-target />
					<button type="button" class="button" data-moksa-flex-test><?php esc_html_e( 'Send test', 'moksa-line' ); ?></button>
					<span class="description"><?php esc_html_e( 'A LINE user id, which you can copy from the LINE users screen. Push messages are billed.', 'moksa-line' ); ?></span>
				</p>

				<div class="moksa-feedback" data-moksa-feedback></div>
			</form>
		</div>

		<div class="moksa-split__side">
			<div class="moksa-panel">
				<h2><?php esc_html_e( 'Preview', 'moksa-line' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Drawn as the customer receives it, on a phone-width screen. A push cannot be recalled, so this and "Send test" are the only checks you get.', 'moksa-line' ); ?>
				</p>

				<?php // data-line-preview marks a deliberate imitation of another product's UI, so accessibility scanners skip it. ?>
				<div class="moksa-phone-chat" data-line-preview>
					<div class="moksa-phone-chat__bar">
						<span class="moksa-phone-chat__dot"></span>
						<?php echo esc_html( $account_name ); ?>
					</div>
					<div class="moksa-phone-chat__body">
						<span class="moksa-phone-chat__avatar" aria-hidden="true"></span>
						<div class="moksa-flex-preview" data-moksa-flex-preview></div>
					</div>
				</div>

				<h3><?php esc_html_e( 'Chat list and lock screen', 'moksa-line' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'All the customer sees until they open the chat. This is the fallback text, not the bubble.', 'moksa-line' ); ?>
				</p>
				<div class="moksa-notif" data-line-preview>
					<span class="moksa-notif__avatar" aria-hidden="true"></span>
					<span class="moksa-notif__text">
						<span class="moksa-notif__name"><?php echo esc_html( $account_name ); ?></span>
						<span class="moksa-notif__body" data-moksa-notif-body></span>
					</span>
				</div>

				<div class="moksa-preview-warnings" data-moksa-preview-warnings></div>
			</div>

			<div class="moksa-panel">
				<h2><?php esc_html_e( 'Saved templates', 'moksa-line' ); ?></h2>
				<ul class="moksa-list">
					<?php if ( empty( $templates ) ) : ?>
						<li><?php esc_html_e( 'None yet.', 'moksa-line' ); ?></li>
					<?php endif; ?>
					<?php foreach ( $templates as $template ) : ?>
						<li data-template='<?php echo esc_attr( (string) wp_json_encode( $template ) ); ?>'>
							<button type="button" class="button-link" data-moksa-load-flex><?php echo esc_html( (string) $template->name ); ?></button>
							<button type="button" class="button-link delete" data-moksa-delete-flex="<?php echo esc_attr( (string) $template->id ); ?>"><?php esc_html_e( 'Delete', 'moksa-line' ); ?></button>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
</div>
