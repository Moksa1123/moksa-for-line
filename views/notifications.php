<?php
/**
 * Order notification history.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Support\Options;
use Moksa\Line\Woo\NotifyHistory;
use Moksa\Line\Woo\NotifyTemplates;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	printf(
		'<div class="wrap"><h1>%s</h1><p>%s</p></div>',
		esc_html__( 'Order notifications', 'moksa-line' ),
		esc_html__( 'WooCommerce is not active, so there is nothing to notify about.', 'moksa-line' )
	);

	return;
}

$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
$page_num = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$list  = NotifyHistory::paginate(
	array(
		'status'   => $status,
		'order_id' => $order_id,
		'page'     => $page_num,
		'per_page' => 30,
	)
);
$tally = NotifyHistory::tally( 30 );
$pages = (int) ceil( $list['total'] / 30 );
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Order notifications', 'moksa-line' ); ?></h1>

	<?php if ( ! Options::get( 'woo_notify' ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'Order notifications are switched off, so nothing is being sent.', 'moksa-line' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=moksa-line-settings&tab=woo' ) ); ?>">
					<?php esc_html_e( 'Turn them on', 'moksa-line' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<div class="moksa-cards">
		<div class="moksa-card">
			<span class="moksa-card__number"><?php echo esc_html( number_format_i18n( $tally['sent'] ) ); ?></span>
			<span class="moksa-card__label"><?php esc_html_e( 'Delivered in the last 30 days', 'moksa-line' ); ?></span>
		</div>
		<div class="moksa-card">
			<span class="moksa-card__number"><?php echo esc_html( number_format_i18n( $tally['failed'] ) ); ?></span>
			<span class="moksa-card__label"><?php esc_html_e( 'Failed in the last 30 days', 'moksa-line' ); ?></span>
		</div>
		<div class="moksa-card">
			<span class="moksa-card__number">
				<?php
				echo esc_html(
					number_format_i18n(
						(int) wp_count_posts( NotifyTemplates::POST_TYPE )->publish
					)
				);
				?>
			</span>
			<span class="moksa-card__label"><?php esc_html_e( 'Active templates', 'moksa-line' ); ?></span>
		</div>
	</div>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . NotifyTemplates::POST_TYPE ) ); ?>">
			<?php esc_html_e( 'Add a notification template', 'moksa-line' ); ?>
		</a>
		<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . NotifyTemplates::POST_TYPE ) ); ?>">
			<?php esc_html_e( 'Manage templates', 'moksa-line' ); ?>
		</a>
	</p>

	<form method="get" class="moksa-inbox__filters">
		<input type="hidden" name="page" value="moksa-line-notifications" />
		<select name="status">
			<option value=""><?php esc_html_e( 'All results', 'moksa-line' ); ?></option>
			<option value="sent" <?php selected( $status, 'sent' ); ?>><?php esc_html_e( 'Delivered', 'moksa-line' ); ?></option>
			<option value="failed" <?php selected( $status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'moksa-line' ); ?></option>
			<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Never settled', 'moksa-line' ); ?></option>
			<option value="unknown" <?php selected( $status, 'unknown' ); ?>><?php esc_html_e( 'Imported, result unknown', 'moksa-line' ); ?></option>
		</select>
		<input type="number" name="order_id" value="<?php echo esc_attr( $order_id ? (string) $order_id : '' ); ?>"
			placeholder="<?php esc_attr_e( 'Order id', 'moksa-line' ); ?>" class="small-text" />
		<?php submit_button( __( 'Filter', 'moksa-line' ), 'secondary', '', false ); ?>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'When', 'moksa-line' ); ?></th>
				<th><?php esc_html_e( 'Order', 'moksa-line' ); ?></th>
				<th><?php esc_html_e( 'Status', 'moksa-line' ); ?></th>
				<th><?php esc_html_e( 'Recipient', 'moksa-line' ); ?></th>
				<th><?php esc_html_e( 'Template', 'moksa-line' ); ?></th>
				<th><?php esc_html_e( 'Result', 'moksa-line' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $list['rows'] ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'Nothing sent yet.', 'moksa-line' ); ?></td></tr>
			<?php endif; ?>

			<?php foreach ( $list['rows'] as $row ) : ?>
				<?php
				$pill = 'warn';

				if ( 'sent' === $row->status ) {
					$pill = 'ok';
				} elseif ( 'failed' === $row->status ) {
					$pill = 'bad';
				}
				?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', get_date_from_gmt( (string) $row->created_at ) ) ); ?></td>
					<td>
						<?php if ( (int) $row->order_id ) : ?>
							<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $row->order_id . '&action=edit' ) ); ?>">
								#<?php echo esc_html( (string) (int) $row->order_id ); ?>
							</a>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( (string) $row->order_status ); ?></td>
					<td>
						<?php echo esc_html( (string) $row->recipient ); ?>
						<?php if ( '' !== (string) $row->line_user_id ) : ?>
							<br /><code><?php echo esc_html( substr( (string) $row->line_user_id, 0, 12 ) ); ?></code>
						<?php endif; ?>
					</td>
					<td>
						<?php
						$template = (int) $row->template_id ? get_post( (int) $row->template_id ) : null;

						if ( $template ) {
							printf(
								'<a href="%s">%s</a>',
								esc_url( (string) get_edit_post_link( $template->ID ) ),
								esc_html( get_the_title( $template ) )
							);
						} else {
							esc_html_e( 'Built-in card', 'moksa-line' );
						}
						?>
					</td>
					<td>
						<span class="moksa-pill moksa-pill--<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( (string) $row->status ); ?></span>
						<?php if ( '' !== (string) $row->error ) : ?>
							<br /><span class="description"><?php echo esc_html( (string) $row->error ); ?></span>
						<?php endif; ?>
					</td>
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
							'current' => $page_num,
							'total'   => $pages,
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
