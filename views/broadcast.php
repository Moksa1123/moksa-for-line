<?php
/**
 * Broadcast.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Data\Flex;
use Moksa\Line\Data\Users;
use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Api\TokenManager;

defined( 'ABSPATH' ) || exit;

$stats = Users::stats();
$quota = null;

if ( TokenManager::is_configured() ) {
	$consumption = MessagingClient::quota_consumption();
	$allowance   = MessagingClient::quota();

	if ( ! is_wp_error( $consumption ) && ! is_wp_error( $allowance ) && isset( $allowance['value'] ) ) {
		$quota = max( 0, (int) $allowance['value'] - (int) ( $consumption['totalUsage'] ?? 0 ) );
	}
}
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Broadcast', 'moksa-line' ); ?></h1>

	<div class="notice notice-warning">
		<p>
			<?php esc_html_e( 'Broadcasts are billed per recipient and cannot be recalled. Send a test to yourself first.', 'moksa-line' ); ?>
			<?php if ( null !== $quota ) : ?>
				<?php
				printf(
					/* translators: %s: remaining message allowance. */
					esc_html__( 'About %s messages remain in this month allowance.', 'moksa-line' ),
					esc_html( number_format_i18n( $quota ) )
				);
				?>
			<?php endif; ?>
		</p>
	</div>

	<form class="moksa-panel" data-moksa-broadcast>
		<p>
			<label for="moksa-broadcast-message"><?php esc_html_e( 'Message', 'moksa-line' ); ?></label>
			<textarea id="moksa-broadcast-message" name="message" rows="5" class="widefat"></textarea>
		</p>

		<p>
			<label for="moksa-broadcast-flex"><?php esc_html_e( 'Attach a Flex template', 'moksa-line' ); ?></label>
			<select id="moksa-broadcast-flex" name="flex_id" class="widefat">
				<option value="0"><?php esc_html_e( 'None', 'moksa-line' ); ?></option>
				<?php foreach ( Flex::all() as $template ) : ?>
					<option value="<?php echo esc_attr( (string) $template->id ); ?>"><?php echo esc_html( (string) $template->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="moksa-broadcast-mode"><?php esc_html_e( 'Send to', 'moksa-line' ); ?></label>
			<select id="moksa-broadcast-mode" name="mode" class="widefat">
				<option value="test"><?php esc_html_e( 'Just one person (a test)', 'moksa-line' ); ?></option>
				<option value="known">
					<?php
					printf(
						/* translators: %s: number of recorded friends. */
						esc_html__( 'Friends recorded on this site (%s)', 'moksa-line' ),
						esc_html( number_format_i18n( $stats['friends'] ) )
					);
					?>
				</option>
				<option value="all"><?php esc_html_e( 'Everyone who follows the account', 'moksa-line' ); ?></option>
			</select>
			<span class="description">
				<?php esc_html_e( 'The recorded list only includes people this site has seen since the webhook was switched on, so it is usually smaller than the real follower count.', 'moksa-line' ); ?>
			</span>
		</p>

		<p data-moksa-broadcast-target>
			<label for="moksa-broadcast-user"><?php esc_html_e( 'LINE user id', 'moksa-line' ); ?></label>
			<input type="text" id="moksa-broadcast-user" name="line_user_id" class="regular-text" placeholder="U1234..." />
		</p>

		<p data-moksa-broadcast-confirm hidden>
			<label for="moksa-broadcast-confirm"><?php esc_html_e( 'Type SEND to confirm', 'moksa-line' ); ?></label>
			<input type="text" id="moksa-broadcast-confirm" name="confirm" class="small-text" autocomplete="off" />
		</p>

		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'moksa-line' ); ?></button></p>

		<div class="moksa-feedback" data-moksa-feedback></div>
	</form>
</div>
