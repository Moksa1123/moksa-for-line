<?php
/**
 * LINE Pay transactions and payment links.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Pay\LinePayClient;
use Moksa\Line\Pay\Payments;
use Moksa\Line\Support\Options;

defined( 'ABSPATH' ) || exit;

if ( ! Options::get( 'pay_enabled' ) ) {
	printf(
		'<div class="wrap"><h1>%s</h1><p>%s</p></div>',
		esc_html__( 'Payments', 'moksa-line' ),
		esc_html__( 'LINE Pay is switched off under LINE > Settings > LINE Pay.', 'moksa-line' )
	);

	return;
}

$rows = Payments::recent( 100 );
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Payments', 'moksa-line' ); ?></h1>

	<?php if ( Options::get( 'pay_sandbox' ) ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'LINE Pay is in sandbox mode. No real money moves.', 'moksa-line' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! LinePayClient::is_configured() ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'LINE Pay credentials are missing, so nothing can be charged yet.', 'moksa-line' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="moksa-panel">
		<h2><?php esc_html_e( 'Create a payment link', 'moksa-line' ); ?></h2>
		<form data-moksa-pay-link>
			<p>
				<label for="moksa-pay-title"><?php esc_html_e( 'What is this for', 'moksa-line' ); ?></label>
				<input type="text" id="moksa-pay-title" name="title" class="widefat" required />
			</p>
			<p>
				<label for="moksa-pay-amount"><?php esc_html_e( 'Amount', 'moksa-line' ); ?></label>
				<input type="number" id="moksa-pay-amount" name="amount" step="1" min="1" class="small-text" required />
				<?php echo esc_html( (string) Options::get( 'pay_currency' ) ); ?>
			</p>
			<p>
				<label for="moksa-pay-user"><?php esc_html_e( 'Send it to (optional)', 'moksa-line' ); ?></label>
				<input type="text" id="moksa-pay-user" name="line_user_id" class="widefat" placeholder="U1234..." />
				<span class="description"><?php esc_html_e( 'A LINE user id. Leave blank to just get a link you can share yourself.', 'moksa-line' ); ?></span>
			</p>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create link', 'moksa-line' ); ?></button></p>
			<div class="moksa-feedback" data-moksa-feedback></div>
		</form>
	</div>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Reference', 'moksa-line' ); ?></th>
				<th><?php esc_html_e( 'Source', 'moksa-line' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'moksa-line' ); ?></th>
				<th><?php esc_html_e( 'Status', 'moksa-line' ); ?></th>
				<th><?php esc_html_e( 'Transaction', 'moksa-line' ); ?></th>
				<th><?php esc_html_e( 'Created', 'moksa-line' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No payments yet.', 'moksa-line' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<?php
				$pill = 'warn';

				if ( in_array( $row->status, array( 'captured', 'authorized' ), true ) ) {
					$pill = 'ok';
				} elseif ( in_array( $row->status, array( 'failed', 'cancelled', 'expired', 'void' ), true ) ) {
					$pill = 'bad';
				}
				?>
				<tr>
					<td>
						<code><?php echo esc_html( (string) $row->order_ref ); ?></code>
						<?php if ( (int) $row->wc_order_id > 0 ) : ?>
							<br /><a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $row->wc_order_id . '&action=edit' ) ); ?>">
								<?php
								printf(
									/* translators: %d: WooCommerce order id. */
									esc_html__( 'Order #%d', 'moksa-line' ),
									(int) $row->wc_order_id
								);
								?>
							</a>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( (string) $row->source ); ?></td>
					<td>
						<?php
						echo esc_html(
							sprintf(
								'%s %s',
								(string) $row->currency,
								number_format_i18n(
									(float) $row->amount,
									in_array( $row->currency, array( 'TWD', 'JPY', 'KRW' ), true ) ? 0 : 2
								)
							)
						);
						?>
						<?php if ( (float) $row->refunded > 0 ) : ?>
							<br /><span class="description">
								<?php
								printf(
									/* translators: %s: refunded amount. */
									esc_html__( 'refunded %s', 'moksa-line' ),
									esc_html( number_format_i18n( (float) $row->refunded, 0 ) )
								);
								?>
							</span>
						<?php endif; ?>
					</td>
					<td><span class="moksa-pill moksa-pill--<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( (string) $row->status ); ?></span></td>
					<td><code><?php echo esc_html( (string) $row->transaction_id ); ?></code></td>
					<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', get_date_from_gmt( (string) $row->created_at ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
