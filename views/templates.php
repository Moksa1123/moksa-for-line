<?php
/**
 * Template messages: fixed-layout cards with buttons.
 *
 * @package Mofoline
 */

use Mofoline\Data\Templates;
use Mofoline\Template\TemplateMessages;
use Mofoline\Support\Options;

defined( 'ABSPATH' ) || exit;

( static function (): void {
	$templates    = Templates::all();
	$basic_id     = ltrim( trim( (string) Options::get( 'bot_basic_id' ) ), '@' );
	$account_name = '' !== $basic_id ? '@' . $basic_id : __( 'Your official account', 'moksa-for-line' );
	?>
	<div class="wrap mofoline-wrap">
		<h1><?php esc_html_e( 'Template messages', 'moksa-for-line' ); ?></h1>

		<p class="description">
			<?php esc_html_e( 'Fixed layouts with buttons, for when a card is all you need and a Flex design is more than you want to build.', 'moksa-for-line' ); ?>
		</p>

		<div class="moksa-split moksa-split--wide">
			<div class="moksa-split__main">
				<form class="moksa-panel" data-moksa-template-form>
					<h2>
						<?php esc_html_e( 'Template', 'moksa-for-line' ); ?>
						<span class="moksa-editing-badge"><?php esc_html_e( 'Editing', 'moksa-for-line' ); ?></span>
					</h2>
					<input type="hidden" name="id" value="0" />

					<div class="moksa-field-row">
						<p>
							<label for="moksa-template-name"><?php esc_html_e( 'Template name', 'moksa-for-line' ); ?></label>
							<input type="text" id="moksa-template-name" name="name" class="widefat" required />
						</p>

						<p>
							<label for="moksa-template-alt"><?php esc_html_e( 'Fallback text', 'moksa-for-line' ); ?></label>
							<input type="text" id="moksa-template-alt" name="alt_text" class="widefat" maxlength="1500" required />
							<span class="description"><?php esc_html_e( 'Shown in the chat list and in push notifications, where the card cannot be drawn.', 'moksa-for-line' ); ?></span>
						</p>
					</div>

					<p>
						<label for="moksa-template-kind"><?php esc_html_e( 'Kind', 'moksa-for-line' ); ?></label>
						<select id="moksa-template-kind" class="widefat" data-moksa-template-kind>
							<?php foreach ( TemplateMessages::kinds() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<?php
					// The shape switches apply to the whole carousel, because LINE
					// refuses a carousel whose cards disagree about having an image,
					// having a title, or how many buttons they carry. Modelling that
					// per card would let someone build something impossible.
					?>
					<div class="moksa-template-shape" data-moksa-template-shape hidden>
						<span class="description"><?php esc_html_e( 'These apply to every card. LINE refuses a carousel whose cards are not built the same way.', 'moksa-for-line' ); ?></span>
						<span class="moksa-template-shape__row">
							<label><input type="checkbox" data-moksa-shape="image" /> <?php esc_html_e( 'Cards have an image', 'moksa-for-line' ); ?></label>
							<label><input type="checkbox" data-moksa-shape="title" checked /> <?php esc_html_e( 'Cards have a title', 'moksa-for-line' ); ?></label>
							<label>
								<?php esc_html_e( 'Buttons per card', 'moksa-for-line' ); ?>
								<select data-moksa-shape="buttons">
									<option value="1">1</option>
									<option value="2">2</option>
									<option value="3">3</option>
								</select>
							</label>
						</span>
					</div>

					<div class="moksa-template-cards" data-moksa-template-cards></div>

					<p data-moksa-template-add hidden>
						<button type="button" class="button" data-moksa-add-template-card><?php esc_html_e( '+ Add a card', 'moksa-for-line' ); ?></button>
					</p>

					<div class="moksa-preview-warnings" data-moksa-template-warnings></div>

					<p>
						<label for="moksa-template-json"><?php esc_html_e( 'Template JSON', 'moksa-for-line' ); ?></label>
						<textarea id="moksa-template-json" name="definition" rows="14" class="widefat code moksa-area-json" spellcheck="false" data-moksa-template-json hidden></textarea>
						<button type="button" class="button-link" data-moksa-toggle-template-json><?php esc_html_e( 'Edit the JSON directly', 'moksa-for-line' ); ?></button>
					</p>

					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save template', 'moksa-for-line' ); ?></button>
						<button type="button" class="button" data-moksa-template-check><?php esc_html_e( 'Check', 'moksa-for-line' ); ?></button>
						<button type="button" class="button" data-moksa-reset-template><?php esc_html_e( 'New template', 'moksa-for-line' ); ?></button>
					</p>

					<p>
						<label for="moksa-template-test"><?php esc_html_e( 'Send a test to', 'moksa-for-line' ); ?></label>
						<input type="text" id="moksa-template-test" class="regular-text" placeholder="U1234..." data-moksa-template-test-target />
						<button type="button" class="button" data-moksa-template-test><?php esc_html_e( 'Send test', 'moksa-for-line' ); ?></button>
						<span class="description"><?php esc_html_e( 'Save first. Push messages are billed.', 'moksa-for-line' ); ?></span>
					</p>

					<div class="moksa-feedback" data-moksa-feedback></div>
				</form>
			</div>

			<div class="moksa-split__side">
				<div class="moksa-panel">
					<h2><?php esc_html_e( 'Preview', 'moksa-for-line' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Drawn as the customer receives it. The exact fonts and corners are LINE\'s, not this preview\'s.', 'moksa-for-line' ); ?>
					</p>

					<?php // data-line-preview marks a deliberate imitation of LINE's UI so accessibility scanners skip it. ?>
					<div class="moksa-phone-chat" data-line-preview>
						<div class="moksa-phone-chat__bar">
							<span class="moksa-phone-chat__dot"></span>
							<?php echo esc_html( $account_name ); ?>
						</div>
						<div class="moksa-phone-chat__body">
							<span class="moksa-phone-chat__avatar" aria-hidden="true"></span>
							<div class="moksa-template-preview" data-moksa-template-preview></div>
						</div>
					</div>

					<p class="description moksa-carousel-note" data-moksa-template-count hidden></p>
				</div>

				<div class="moksa-panel">
					<h2><?php esc_html_e( 'Saved templates', 'moksa-for-line' ); ?></h2>
					<ul class="moksa-list">
						<?php if ( empty( $templates ) ) : ?>
							<li><?php esc_html_e( 'None yet.', 'moksa-for-line' ); ?></li>
						<?php endif; ?>
						<?php foreach ( $templates as $template ) : ?>
							<li data-template='<?php echo esc_attr( (string) wp_json_encode( $template ) ); ?>'>
								<button type="button" class="button-link" data-moksa-load-template><?php echo esc_html( (string) $template->name ); ?></button>
								<span class="moksa-pill moksa-pill--ok"><?php echo esc_html( (string) $template->kind ); ?></span>
								<button type="button" class="button-link delete" data-moksa-delete-template="<?php echo esc_attr( (string) $template->id ); ?>"><?php esc_html_e( 'Delete', 'moksa-for-line' ); ?></button>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</div>
	<?php
} )();
