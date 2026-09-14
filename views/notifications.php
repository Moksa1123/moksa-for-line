<?php
/**
 * Order notification history.
 *
 * @package Mofoline
 */

use Mofoline\Admin\Ajax;
use Mofoline\Support\Options;
use Mofoline\Woo\NotifyHistory;
use Mofoline\Woo\NotifyTemplates;

defined( 'ABSPATH' ) || exit;

( static function (): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		printf(
			'<div class="wrap"><h1>%s</h1><p>%s</p></div>',
			esc_html__( 'Order notifications', 'moksa-for-line' ),
			esc_html__( 'WooCommerce is not active, so there is nothing to notify about.', 'moksa-for-line' )
		);

		return;
	}

	$status   = Ajax::query_key( 'status' );
	$order_id = Ajax::query_int( 'order_id' );
	$page_num = Ajax::query_page();

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
	<div class="wrap mofoline-wrap">
		<h1><?php esc_html_e( 'Order notifications', 'moksa-for-line' ); ?></h1>

		<?php if ( ! Options::get( 'woo_notify' ) ) : ?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'Order notifications are switched off, so nothing is being sent.', 'moksa-for-line' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mofoline-settings&tab=woo' ) ); ?>">
						<?php esc_html_e( 'Turn them on', 'moksa-for-line' ); ?>
					</a>
				</p>
			</div>
		<?php endif; ?>

		<div class="moksa-cards">
			<div class="moksa-card">
				<span class="moksa-card__number"><?php echo esc_html( number_format_i18n( $tally['sent'] ) ); ?></span>
				<span class="moksa-card__label"><?php esc_html_e( 'Delivered in the last 30 days', 'moksa-for-line' ); ?></span>
			</div>
			<div class="moksa-card">
				<span class="moksa-card__number"><?php echo esc_html( number_format_i18n( $tally['failed'] ) ); ?></span>
				<span class="moksa-card__label"><?php esc_html_e( 'Failed in the last 30 days', 'moksa-for-line' ); ?></span>
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
				<span class="moksa-card__label"><?php esc_html_e( 'Active templates', 'moksa-for-line' ); ?></span>
			</div>
		</div>

		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . NotifyTemplates::POST_TYPE ) ); ?>">
				<?php esc_html_e( 'Add a notification template', 'moksa-for-line' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . NotifyTemplates::POST_TYPE ) ); ?>">
				<?php esc_html_e( 'Manage templates', 'moksa-for-line' ); ?>
			</a>
		</p>

		<form method="get" class="moksa-inbox__filters">
			<input type="hidden" name="page" value="mofoline-notifications" />
			<label for="moksa-notify-status" class="screen-reader-text"><?php esc_html_e( 'Which results to show', 'moksa-for-line' ); ?></label>
			<select id="moksa-notify-status" name="status">
				<option value=""><?php esc_html_e( 'All results', 'moksa-for-line' ); ?></option>
				<option value="sent" <?php selected( $status, 'sent' ); ?>><?php esc_html_e( 'Delivered', 'moksa-for-line' ); ?></option>
				<option value="failed" <?php selected( $status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'moksa-for-line' ); ?></option>
				<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Never settled', 'moksa-for-line' ); ?></option>
				<option value="unknown" <?php selected( $status, 'unknown' ); ?>><?php esc_html_e( 'Imported, result unknown', 'moksa-for-line' ); ?></option>
			</select>
			<label for="moksa-notify-order" class="screen-reader-text"><?php esc_html_e( 'Order number', 'moksa-for-line' ); ?></label>
			<input type="number" id="moksa-notify-order" name="order_id" value="<?php echo esc_attr( $order_id ? (string) $order_id : '' ); ?>"
				placeholder="<?php esc_attr_e( 'Order id', 'moksa-for-line' ); ?>" class="small-text" />
			<?php submit_button( __( 'Filter', 'moksa-for-line' ), 'secondary', '', false ); ?>
		</form>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'moksa-for-line' ); ?></th>
					<th><?php esc_html_e( 'Order', 'moksa-for-line' ); ?></th>
					<th><?php esc_html_e( 'Status', 'moksa-for-line' ); ?></th>
					<th><?php esc_html_e( 'Recipient', 'moksa-for-line' ); ?></th>
					<th><?php esc_html_e( 'Template', 'moksa-for-line' ); ?></th>
					<th><?php esc_html_e( 'Result', 'moksa-for-line' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $list['rows'] ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Nothing sent yet.', 'moksa-for-line' ); ?></td></tr>
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
								esc_html_e( 'Built-in card', 'moksa-for-line' );
							}
							?>
						</td>
						<td>
							<span class="moksa-pill moksa-pill--<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( (string) $row->status ); ?></span>
							<?php if ( '' !== (string) $row->error ) : ?>
								<br /><span class="description"><?php echo esc_html( (string) $row->error ); ?></span>
							<?php endif; ?>
							<?php if ( 'sent' !== $row->status && (int) $row->order_id > 0 && '' !== (string) $row->order_status ) : ?>
								<br />
								<button type="button" class="button button-small" data-moksa-resend-notification
									data-order="<?php echo esc_attr( (string) (int) $row->order_id ); ?>"
									data-status="<?php echo esc_attr( (string) $row->order_status ); ?>">
									<?php esc_html_e( 'Send again', 'moksa-for-line' ); ?>
								</button>
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
	<?php
} )();
