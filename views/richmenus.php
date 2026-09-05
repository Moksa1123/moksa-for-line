<?php
/**
 * Rich menus, including tab groups.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Api\RichMenuClient;
use Moksa\Line\RichMenu\RichMenuModule;

defined( 'ABSPATH' ) || exit;

$menus  = RichMenuModule::all();
$groups = RichMenuModule::groups();
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Rich menus', 'moksa-line' ); ?></h1>

	<p class="description">
		<?php
		printf(
			/* translators: 1: full size, 2: half size. */
			esc_html__( 'Menu images must be %1$s or %2$s pixels, JPEG or PNG, and at most 1 MB.', 'moksa-line' ),
			esc_html( RichMenuClient::WIDTH . ' x ' . RichMenuClient::HEIGHT_FULL ),
			esc_html( RichMenuClient::WIDTH . ' x ' . RichMenuClient::HEIGHT_HALF )
		);
		?>
		<?php esc_html_e( 'To build tabs, give several menus the same tab group and point their tab buttons at each other with a "switch tab" action.', 'moksa-line' ); ?>
	</p>

	<div class="moksa-split moksa-split--wide">
		<div class="moksa-split__main">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'moksa-line' ); ?></th>
						<th><?php esc_html_e( 'Tab group', 'moksa-line' ); ?></th>
						<th><?php esc_html_e( 'Alias', 'moksa-line' ); ?></th>
						<th><?php esc_html_e( 'Published', 'moksa-line' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $menus ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No rich menus yet.', 'moksa-line' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $menus as $menu ) : ?>
						<tr data-menu='<?php echo esc_attr( (string) wp_json_encode( $menu ) ); ?>'>
							<td>
								<strong><?php echo esc_html( (string) $menu->name ); ?></strong>
								<?php if ( (int) $menu->is_default ) : ?>
									<span class="moksa-pill moksa-pill--ok"><?php esc_html_e( 'default', 'moksa-line' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) $menu->tab_group ); ?></td>
							<td><code><?php echo esc_html( (string) $menu->alias_id ); ?></code></td>
							<td>
								<?php if ( '' !== (string) $menu->richmenu_id ) : ?>
									<span class="moksa-pill moksa-pill--ok"><?php echo esc_html( mysql2date( 'Y-m-d H:i', get_date_from_gmt( (string) $menu->synced_at ) ) ); ?></span>
								<?php else : ?>
									<span class="moksa-pill moksa-pill--warn"><?php esc_html_e( 'not published', 'moksa-line' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<button type="button" class="button-link" data-moksa-edit-menu><?php esc_html_e( 'Edit', 'moksa-line' ); ?></button>
								<button type="button" class="button-link" data-moksa-publish-menu="<?php echo esc_attr( (string) $menu->id ); ?>"><?php esc_html_e( 'Publish', 'moksa-line' ); ?></button>
								<button type="button" class="button-link" data-moksa-default-menu="<?php echo esc_attr( (string) $menu->id ); ?>"><?php esc_html_e( 'Make default', 'moksa-line' ); ?></button>
								<button type="button" class="button-link delete" data-moksa-delete-menu="<?php echo esc_attr( (string) $menu->id ); ?>"><?php esc_html_e( 'Delete', 'moksa-line' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $groups ) ) : ?>
				<div class="moksa-panel">
					<h2><?php esc_html_e( 'Publish a whole tab group', 'moksa-line' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Publishes every menu in the group in order and repoints their aliases, so the tabs stay consistent with each other.', 'moksa-line' ); ?></p>
					<?php foreach ( $groups as $group ) : ?>
						<button type="button" class="button" data-moksa-publish-group="<?php echo esc_attr( $group ); ?>">
							<?php
							printf(
								/* translators: %s: tab group name. */
								esc_html__( 'Publish "%s"', 'moksa-line' ),
								esc_html( $group )
							);
							?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="moksa-split__side">
			<form class="moksa-panel" data-moksa-menu-form>
				<h2><?php esc_html_e( 'Menu', 'moksa-line' ); ?></h2>
				<input type="hidden" name="id" value="0" />

				<p>
					<label for="moksa-menu-name"><?php esc_html_e( 'Name', 'moksa-line' ); ?></label>
					<input type="text" id="moksa-menu-name" name="name" class="widefat" required />
				</p>

				<p>
					<label for="moksa-menu-bar"><?php esc_html_e( 'Chat bar label', 'moksa-line' ); ?></label>
					<input type="text" id="moksa-menu-bar" name="chat_bar_text" class="widefat" maxlength="14" />
					<span class="description"><?php esc_html_e( 'At most 14 characters, shown at the bottom of the chat.', 'moksa-line' ); ?></span>
				</p>

				<p>
					<label for="moksa-menu-size"><?php esc_html_e( 'Size', 'moksa-line' ); ?></label>
					<select id="moksa-menu-size" name="size" class="widefat">
						<option value="full"><?php esc_html_e( 'Full (2500 x 1686)', 'moksa-line' ); ?></option>
						<option value="half"><?php esc_html_e( 'Half (2500 x 843)', 'moksa-line' ); ?></option>
					</select>
				</p>

				<p>
					<label for="moksa-menu-group"><?php esc_html_e( 'Tab group', 'moksa-line' ); ?></label>
					<input type="text" id="moksa-menu-group" name="tab_group" class="widefat" list="moksa-menu-groups" />
					<datalist id="moksa-menu-groups">
						<?php foreach ( $groups as $group ) : ?>
							<option value="<?php echo esc_attr( $group ); ?>"></option>
						<?php endforeach; ?>
					</datalist>
					<span class="description"><?php esc_html_e( 'Leave blank for a standalone menu.', 'moksa-line' ); ?></span>
				</p>

				<p>
					<label for="moksa-menu-order"><?php esc_html_e( 'Tab order', 'moksa-line' ); ?></label>
					<input type="number" id="moksa-menu-order" name="tab_order" value="0" class="small-text" />
				</p>

				<p>
					<label for="moksa-menu-alias"><?php esc_html_e( 'Alias ID', 'moksa-line' ); ?></label>
					<input type="text" id="moksa-menu-alias" name="alias_id" class="widefat" />
					<span class="description"><?php esc_html_e( 'Lowercase letters, digits, hyphen and underscore. Other tabs point at this. Generated automatically if left blank.', 'moksa-line' ); ?></span>
				</p>

				<p>
					<label><?php esc_html_e( 'Image', 'moksa-line' ); ?></label>
					<input type="hidden" name="image_attachment_id" value="0" />
					<button type="button" class="button" data-moksa-pick-image><?php esc_html_e( 'Choose image', 'moksa-line' ); ?></button>
					<span class="moksa-menu-image" data-moksa-image-preview></span>
				</p>

				<p>
					<label><?php esc_html_e( 'Tappable areas', 'moksa-line' ); ?></label>
					<textarea name="areas" rows="12" class="widefat code" spellcheck="false" data-moksa-menu-areas></textarea>
					<span class="description">
						<?php esc_html_e( 'A JSON array of areas. Each has bounds (x, y, width, height in image pixels) and an action. For a tab button use an action of type richmenuswitch with the sibling menu\'s alias ID.', 'moksa-line' ); ?>
					</span>
				</p>

				<p>
					<label>
						<input type="checkbox" name="selected" value="1" />
						<?php esc_html_e( 'Open the menu by default when the chat opens', 'moksa-line' ); ?>
					</label>
				</p>

				<p>
					<label>
						<input type="checkbox" name="is_default" value="1" />
						<?php esc_html_e( 'Use as the default menu for everyone', 'moksa-line' ); ?>
					</label>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'moksa-line' ); ?></button>
					<button type="button" class="button" data-moksa-reset-menu><?php esc_html_e( 'New menu', 'moksa-line' ); ?></button>
					<button type="button" class="button" data-moksa-sync-menus><?php esc_html_e( 'Compare with LINE', 'moksa-line' ); ?></button>
				</p>

				<div class="moksa-feedback" data-moksa-feedback></div>
			</form>
		</div>
	</div>
</div>
