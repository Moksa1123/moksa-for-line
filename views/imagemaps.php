<?php
/**
 * Imagemap messages.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Data\Imagemaps;
use Moksa\Line\Imagemap\ImagemapImages;
use Moksa\Line\Imagemap\ImagemapModule;

defined( 'ABSPATH' ) || exit;

$imagemaps = Imagemaps::all();
$is_https  = 0 === stripos( (string) rest_url(), 'https://' );
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Imagemaps', 'moksa-line' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'One banner image the customer taps. The regions are invisible to them, so draw them on the image rather than guessing coordinates.', 'moksa-line' ); ?>
		<?php
		printf(
			/* translators: %d: the imagemap action limit. */
			esc_html__( 'Up to %d regions. The image must be at least 1040 pixels wide; LINE is served five sizes of it from this site.', 'moksa-line' ),
			(int) ImagemapModule::MAX_ACTIONS
		);
		?>
	</p>

	<?php if ( ! $is_https ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'This site is served over plain HTTP. LINE only fetches imagemap images over HTTPS, so an imagemap from this site would arrive blank.', 'moksa-line' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="moksa-split moksa-split--wide">
		<div class="moksa-split__main">
			<form class="moksa-panel" data-moksa-imagemap-form>
				<h2>
					<?php esc_html_e( 'Imagemap', 'moksa-line' ); ?>
					<span class="moksa-editing-badge"><?php esc_html_e( 'Editing', 'moksa-line' ); ?></span>
				</h2>
				<input type="hidden" name="id" value="0" />

				<div class="moksa-field-row">
					<p>
						<label for="moksa-imagemap-name"><?php esc_html_e( 'Name', 'moksa-line' ); ?></label>
						<input type="text" id="moksa-imagemap-name" name="name" class="widefat" required />
					</p>

					<p>
						<label for="moksa-imagemap-alt"><?php esc_html_e( 'Fallback text', 'moksa-line' ); ?></label>
						<input type="text" id="moksa-imagemap-alt" name="alt_text" class="widefat" maxlength="1500" required />
						<span class="description"><?php esc_html_e( 'Shown in the chat list and in push notifications, where the image cannot be drawn.', 'moksa-line' ); ?></span>
					</p>
				</div>

				<p>
					<label><?php esc_html_e( 'Banner image', 'moksa-line' ); ?></label>
					<input type="hidden" name="image_attachment_id" value="0" />
					<button type="button" class="button" data-moksa-pick-imagemap-image><?php esc_html_e( 'Choose image', 'moksa-line' ); ?></button>
					<span class="moksa-menu-image" data-moksa-imagemap-image-preview></span>
					<span class="description">
						<?php esc_html_e( 'At least 1040 pixels wide. Five copies are cut on save, because LINE asks for the image at five widths and refuses a URL with a file extension.', 'moksa-line' ); ?>
					</span>
				</p>

				<p>
					<label><?php esc_html_e( 'Tappable regions', 'moksa-line' ); ?></label>
					<span class="description">
						<?php esc_html_e( 'Drag on the image to draw a region. Click one to move, resize or set what it does. Coordinates are in the 1040-wide space LINE renders in.', 'moksa-line' ); ?>
					</span>

					<span class="moksa-area-toolbar">
						<?php esc_html_e( 'Start from a layout:', 'moksa-line' ); ?>
						<?php foreach ( array( '1x1', '2x1', '3x1', '2x2', '3x2' ) as $preset ) : ?>
							<button type="button" class="button button-small" data-moksa-grid="<?php echo esc_attr( $preset ); ?>">
								<?php echo esc_html( str_replace( 'x', ' × ', $preset ) ); ?>
							</button>
						<?php endforeach; ?>
						<button type="button" class="button button-small" data-moksa-clear-areas><?php esc_html_e( 'Clear', 'moksa-line' ); ?></button>
					</span>

					<?php // The same editor the rich menu uses; only the coordinate space differs. ?>
					<span class="moksa-area-editor" tabindex="0"
						data-moksa-area-editor="[data-moksa-imagemap-areas]"
						data-image-width="1040"
						data-image-height="1040"
						data-no-switch="1"
						data-switch-targets="<?php echo esc_attr( (string) wp_json_encode( array() ) ); ?>">

						<span class="moksa-area-editor__canvas">
							<span class="moksa-canvas" data-moksa-canvas>
								<img alt="" data-moksa-canvas-image />
								<span class="moksa-canvas__layer" data-moksa-canvas-layer></span>
								<span class="moksa-canvas__empty">
									<?php esc_html_e( 'Choose a banner image to start drawing regions.', 'moksa-line' ); ?>
								</span>
							</span>
							<span class="moksa-area-warnings" data-moksa-area-warnings></span>
						</span>

						<span class="moksa-area-editor__side">
							<span class="moksa-inspector" data-moksa-inspector></span>
						</span>
					</span>

					<label for="moksa-imagemap-areas" class="screen-reader-text"><?php esc_html_e( 'Tappable regions as JSON', 'moksa-line' ); ?></label>
					<textarea id="moksa-imagemap-areas" name="areas" rows="6" class="widefat code moksa-area-json" spellcheck="false" data-moksa-imagemap-areas hidden></textarea>
					<button type="button" class="button-link" data-moksa-toggle-imagemap-json><?php esc_html_e( 'Edit the JSON directly', 'moksa-line' ); ?></button>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'moksa-line' ); ?></button>
					<button type="button" class="button" data-moksa-reset-imagemap><?php esc_html_e( 'New imagemap', 'moksa-line' ); ?></button>
				</p>

				<p>
					<label for="moksa-imagemap-test"><?php esc_html_e( 'Send a test to', 'moksa-line' ); ?></label>
					<input type="text" id="moksa-imagemap-test" class="regular-text" placeholder="U1234..." data-moksa-imagemap-test-target />
					<button type="button" class="button" data-moksa-imagemap-test><?php esc_html_e( 'Send test', 'moksa-line' ); ?></button>
					<span class="description"><?php esc_html_e( 'Save first. Push messages are billed.', 'moksa-line' ); ?></span>
				</p>

				<div class="moksa-feedback" data-moksa-feedback></div>
			</form>
		</div>

		<div class="moksa-split__side">
			<div class="moksa-panel">
				<h2><?php esc_html_e( 'Saved imagemaps', 'moksa-line' ); ?></h2>
				<ul class="moksa-list">
					<?php if ( empty( $imagemaps ) ) : ?>
						<li><?php esc_html_e( 'None yet.', 'moksa-line' ); ?></li>
					<?php endif; ?>
					<?php foreach ( $imagemaps as $imagemap ) : ?>
						<?php
						// The editor restores its canvas from this, so the row
						// carries the resolved URL rather than just the id.
						$row = (array) $imagemap;
						$row['image_url'] = (int) $imagemap->image_attachment_id
							? (string) wp_get_attachment_url( (int) $imagemap->image_attachment_id )
							: '';
						?>
						<li data-imagemap='<?php echo esc_attr( (string) wp_json_encode( $row ) ); ?>'>
							<button type="button" class="button-link" data-moksa-load-imagemap><?php echo esc_html( (string) $imagemap->name ); ?></button>
							<?php if ( ! ImagemapImages::complete( (int) $imagemap->id ) ) : ?>
								<span class="moksa-pill moksa-pill--warn"><?php esc_html_e( 'images missing', 'moksa-line' ); ?></span>
							<?php endif; ?>
							<button type="button" class="button-link delete" data-moksa-delete-imagemap="<?php echo esc_attr( (string) $imagemap->id ); ?>"><?php esc_html_e( 'Delete', 'moksa-line' ); ?></button>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="moksa-panel">
				<h2><?php esc_html_e( 'How LINE fetches the image', 'moksa-line' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'LINE treats the base URL as a prefix and appends the width it wants, with no file extension. This is why the image is served from the plugin rather than straight from the media library.', 'moksa-line' ); ?>
				</p>
				<ul class="moksa-list">
					<?php foreach ( ImagemapImages::WIDTHS as $width ) : ?>
						<li><code><?php echo esc_html( '.../imagemap/{id}/' . $width ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
</div>
