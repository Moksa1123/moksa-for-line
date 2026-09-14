<?php
/**
 * LINE Pay transactions and payment links.
 *
 * @package Mofoline
 */

use Mofoline\Pay\LinePayClient;
use Mofoline\Pay\Payments;
use Mofoline\Support\Options;

defined( 'ABSPATH' ) || exit;

( static function (): void {
	// Switched off is a normal state, not an error -- but the screen used to say
	// so in one line of unstyled grey text naming a settings page it did not link
	// to, which leaves the reader to go and find it. Same words, somewhere to go.
	if ( ! Options::get( 'pay_enabled' ) ) {
		$settings_url = admin_url( 'admin.php?page=mofoline-settings&tab=pay' );
		?>
		<div class="wrap mofoline-wrap">
			<h1><?php esc_html_e( 'Payments', 'moksa-for-line' ); ?></h1>

			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e( 'LINE Pay is switched off, so nothing is being charged and there is nothing to show here yet.', 'moksa-for-line' ); ?>
					<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Turn it on in the LINE Pay settings', 'moksa-for-line' ); ?></a>
				</p>
			</div>
		</div>
		<?php
		return;
	}

	$rows = Payments::recent( 100 );
	?>
	<div class="wrap mofoline-wrap">
		<h1><?php esc_html_e( 'Payments', 'moksa-for-line' ); ?></h1>

		<?php if ( Options::get( 'pay_sandbox' ) ) : ?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'LINE Pay is in sandbox mode. No real money moves.', 'moksa-for-line' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( ! LinePayClient::is_configured() ) : ?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'LINE Pay credentials are missing, so nothing can be charged yet.', 'moksa-for-line' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="moksa-panel">
			<h2><?php esc_html_e( 'Create a payment link', 'moksa-for-line' ); ?></h2>
			<form data-moksa-pay-link>
				<p>
					<label for="moksa-pay-title"><?php esc_html_e( 'What is this for', 'moksa-for-line' ); ?></label>
					<input type="text" id="moksa-pay-title" name="title" class="widefat" required />
				</p>
				<p>
					<label for="moksa-pay-amount"><?php esc_html_e( 'Amount', 'moksa-for-line' ); ?></label>
					<input type="number" id="moksa-pay-amount" name="amount" step="1" min="1" class="small-text" required />
					<?php echo esc_html( (string) Options::get( 'pay_currency' ) ); ?>
				</p>
				<p>
					<label for="moksa-pay-user"><?php esc_html_e( 'Send it to (optional)', 'moksa-for-line' ); ?></label>
					<input type="text" id="moksa-pay-user" name="line_user_id" class="widefat" placeholder="U1234..." />
					<span class="description"><?php esc_html_e( 'A LINE user id. Leave blank to just get a link you can share yourself.', 'moksa-for-line' ); ?></span>
				</p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create link', 'moksa-for-line' ); ?></button></p>
				<div class="moksa-feedback" data-moksa-feedback></div>
			</form>
		</div>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Reference', 'moksa-for-line' ); ?></th>
					<th><?php esc_html_e( 'Source', 'moksa-for-line' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'moksa-for-line' ); ?></th>
					<th><?php esc_html_e( 'Status', 'moksa-for-line' ); ?></th>
					<th><?php esc_html_e( 'Transaction', 'moksa-for-line' ); ?></th>
					<th><?php esc_html_e( 'Created', 'moksa-for-line' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No payments yet.', 'moksa-for-line' ); ?></td></tr>
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
										esc_html__( 'Order #%d', 'moksa-for-line' ),
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
										esc_html__( 'refunded %s', 'moksa-for-line' ),
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
	<?php
} )();
