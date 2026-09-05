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
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Flex messages', 'moksa-line-login' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'The preview is an approximation. Use "Check" to validate against LINE itself, and "Send test" to see the real thing in a chat.', 'moksa-line-login' ); ?>
	</p>

	<div class="moksa-split moksa-split--wide">
		<div class="moksa-split__main">
			<form class="moksa-panel" data-moksa-flex-form>
				<input type="hidden" name="id" value="0" />

				<p>
					<label for="moksa-flex-name"><?php esc_html_e( 'Template name', 'moksa-line-login' ); ?></label>
					<input type="text" id="moksa-flex-name" name="name" class="widefat" required />
				</p>

				<p>
					<label for="moksa-flex-alt"><?php esc_html_e( 'Fallback text', 'moksa-line-login' ); ?></label>
					<input type="text" id="moksa-flex-alt" name="alt_text" class="widefat" maxlength="400" required />
					<span class="description"><?php esc_html_e( 'Shown in the chat list and in push notifications, where the bubble cannot be drawn.', 'moksa-line-login' ); ?></span>
				</p>

				<p>
					<label for="moksa-flex-starter"><?php esc_html_e( 'Start from', 'moksa-line-login' ); ?></label>
					<select id="moksa-flex-starter" data-moksa-flex-starter>
						<option value=""><?php esc_html_e( 'Blank', 'moksa-line-login' ); ?></option>
						<?php foreach ( $starters as $key => $starter ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"
								data-contents="<?php echo esc_attr( (string) wp_json_encode( $starter['contents'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?>">
								<?php echo esc_html( $starter['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>

				<p>
					<label for="moksa-flex-contents"><?php esc_html_e( 'Bubble or carousel JSON', 'moksa-line-login' ); ?></label>
					<textarea id="moksa-flex-contents" name="contents" rows="20" class="widefat code" spellcheck="false" data-moksa-flex-json></textarea>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save template', 'moksa-line-login' ); ?></button>
					<button type="button" class="button" data-moksa-flex-check><?php esc_html_e( 'Check', 'moksa-line-login' ); ?></button>
					<button type="button" class="button" data-moksa-flex-reset><?php esc_html_e( 'New template', 'moksa-line-login' ); ?></button>
				</p>

				<p>
					<label for="moksa-flex-test"><?php esc_html_e( 'Send a test to', 'moksa-line-login' ); ?></label>
					<input type="text" id="moksa-flex-test" class="regular-text" placeholder="U1234..." data-moksa-flex-test-target />
					<button type="button" class="button" data-moksa-flex-test><?php esc_html_e( 'Send test', 'moksa-line-login' ); ?></button>
					<span class="description"><?php esc_html_e( 'A LINE user id, which you can copy from the LINE users screen. Push messages are billed.', 'moksa-line-login' ); ?></span>
				</p>

				<div class="moksa-feedback" data-moksa-feedback></div>
			</form>
		</div>

		<div class="moksa-split__side">
			<div class="moksa-panel">
				<h2><?php esc_html_e( 'Preview', 'moksa-line-login' ); ?></h2>
				<div class="moksa-flex-preview" data-moksa-flex-preview></div>
			</div>

			<div class="moksa-panel">
				<h2><?php esc_html_e( 'Saved templates', 'moksa-line-login' ); ?></h2>
				<ul class="moksa-list">
					<?php if ( empty( $templates ) ) : ?>
						<li><?php esc_html_e( 'None yet.', 'moksa-line-login' ); ?></li>
					<?php endif; ?>
					<?php foreach ( $templates as $template ) : ?>
						<li data-template='<?php echo esc_attr( (string) wp_json_encode( $template ) ); ?>'>
							<button type="button" class="button-link" data-moksa-load-flex><?php echo esc_html( (string) $template->name ); ?></button>
							<button type="button" class="button-link delete" data-moksa-delete-flex="<?php echo esc_attr( (string) $template->id ); ?>"><?php esc_html_e( 'Delete', 'moksa-line-login' ); ?></button>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
</div>
