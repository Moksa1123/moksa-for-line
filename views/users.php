<?php
/**
 * LINE users.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Data\Users;

defined( 'ABSPATH' ) || exit;

$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

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
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, LINE id or email', 'moksa-line' ); ?>" />
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
						<?php if ( '' !== (string) $row->picture_url ) : ?>
							<img src="<?php echo esc_url( (string) $row->picture_url ); ?>" alt="" width="28" height="28" class="moksa-avatar" loading="lazy" />
						<?php endif; ?>
						<?php echo esc_html( (string) $row->display_name ); ?>
					</td>
					<td><code class="moksa-copyable"><?php echo esc_html( (string) $row->line_user_id ); ?></code></td>
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
