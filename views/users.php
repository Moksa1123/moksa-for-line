<?php
/**
 * LINE users.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Admin\Ajax;
use Moksa\Line\Data\Users;

defined( 'ABSPATH' ) || exit;

( static function (): void {
	$search = Ajax::query_text( 's' );
	$page   = Ajax::query_page();

	$list  = Users::paginate(
		array(
			'search'   => $search,
			'page'     => $page,
			'per_page' => 30,
		)
	);
	$pages = (int) ceil( $list['total'] / 30 );
	?>
	<div class="wrap moksa-line-wrap">
		<h1><?php esc_html_e( 'LINE users', 'moksa-line' ); ?></h1>

		<form method="get">
			<input type="hidden" name="page" value="moksa-line-users" />
			<p class="search-box">
				<label for="moksa-users-search" class="screen-reader-text"><?php esc_html_e( 'Search LINE users', 'moksa-line' ); ?></label>
				<input type="search" id="moksa-users-search" class="moksa-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, LINE id or email', 'moksa-line' ); ?>" />
				<?php submit_button( __( 'Search', 'moksa-line' ), 'secondary', '', false ); ?>
			</p>
		</form>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'moksa-line' ); ?></th>
					<th><?php esc_html_e( 'LINE user id', 'moksa-line' ); ?></th>
					<th><?php esc_html_e( 'Friend', 'moksa-line' ); ?></th>
					<th><?php esc_html_e( 'WordPress account', 'moksa-line' ); ?></th>
					<th><?php esc_html_e( 'Last seen', 'moksa-line' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $list['rows'] ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No LINE users recorded yet.', 'moksa-line' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $list['rows'] as $row ) : ?>
					<tr>
						<td>
							<?php
							// A LINE profile URL expires, and plenty of records have
							// none at all, so the slot is always drawn: without it
							// the names in this column do not line up with each
							// other, and a dead URL leaves a broken-image glyph.
							$initial = trim( (string) $row->display_name );
							$initial = '' !== $initial ? mb_substr( $initial, 0, 1 ) : '?';
							?>
							<span class="moksa-identity">
								<span class="moksa-avatar" aria-hidden="true">
									<?php if ( '' !== (string) $row->picture_url ) : ?>
										<img src="<?php echo esc_url( (string) $row->picture_url ); ?>" alt="" width="28" height="28" loading="lazy" />
									<?php endif; ?>
									<span class="moksa-avatar__initial"><?php echo esc_html( $initial ); ?></span>
								</span>
								<?php echo esc_html( (string) $row->display_name ); ?>
							</span>
						</td>
						<td><button type="button" class="moksa-copyable" data-moksa-copy="<?php echo esc_attr( (string) $row->line_user_id ); ?>"><code><?php echo esc_html( (string) $row->line_user_id ); ?></code></button></td>
						<td>
							<?php if ( (int) $row->is_friend ) : ?>
								<span class="moksa-pill moksa-pill--ok"><?php esc_html_e( 'yes', 'moksa-line' ); ?></span>
							<?php else : ?>
								<span class="moksa-pill moksa-pill--warn"><?php esc_html_e( 'no', 'moksa-line' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$wp_user = (int) $row->wp_user_id > 0 ? get_userdata( (int) $row->wp_user_id ) : null;

							if ( $wp_user ) {
								printf(
									'<a href="%s">%s</a>',
									esc_url( get_edit_user_link( $wp_user->ID ) ),
									esc_html( $wp_user->user_login )
								);
							} else {
								esc_html_e( 'Not linked', 'moksa-line' );
							}
							?>
						</td>
						<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', get_date_from_gmt( (string) $row->updated_at ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'    => add_query_arg( 'paged', '%#%' ),
								'format'  => '',
								'current' => $page,
								'total'   => $pages,
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
} )();
