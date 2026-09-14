<?php
/**
 * Broadcast.
 *
 * @package Mofoline
 */

use Mofoline\Data\Flex;
use Mofoline\Data\Users;
use Mofoline\Api\MessagingClient;
use Mofoline\Api\TokenManager;

defined( 'ABSPATH' ) || exit;

( static function (): void {
	$stats = Users::stats();
	$quota = null;

	// The size of the bill, when LINE will tell us. It only computes this up to
	// yesterday and answers "unready" otherwise, so a missing number is normal
	// rather than an error worth reporting.
	$reachable = TokenManager::is_configured() ? MessagingClient::reachable() : null;

	$basic_id     = ltrim( trim( (string) \Mofoline\Support\Options::get( 'bot_basic_id' ) ), '@' );
	$account_name = '' !== $basic_id ? '@' . $basic_id : __( 'Your official account', 'moksa-for-line' );

	if ( TokenManager::is_configured() ) {
		$consumption = MessagingClient::quota_consumption();
		$allowance   = MessagingClient::quota();

		if ( ! is_wp_error( $consumption ) && ! is_wp_error( $allowance ) && isset( $allowance['value'] ) ) {
			$quota = max( 0, (int) $allowance['value'] - (int) ( $consumption['totalUsage'] ?? 0 ) );
		}
	}
	?>
	<div class="wrap mofoline-wrap">
		<h1><?php esc_html_e( 'Broadcast', 'moksa-for-line' ); ?></h1>

		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'Broadcasts are billed per recipient and cannot be recalled. Send a test to yourself first.', 'moksa-for-line' ); ?>
				<?php if ( null !== $quota ) : ?>
					<?php
					printf(
						/* translators: %s: remaining message allowance. */
						esc_html__( 'About %s messages remain in this month allowance.', 'moksa-for-line' ),
						esc_html( number_format_i18n( $quota ) )
					);
					?>
				<?php endif; ?>
			</p>
		</div>

		<div class="moksa-split moksa-split--wide">
		<div class="moksa-split__main">
		<form class="moksa-panel" data-moksa-broadcast>
			<p>
				<label for="moksa-broadcast-message"><?php esc_html_e( 'Message', 'moksa-for-line' ); ?></label>
				<textarea id="moksa-broadcast-message" name="message" rows="5" class="widefat"
					maxlength="5000" data-moksa-count-into="[data-moksa-broadcast-count]"></textarea>
				<span class="description">
					<span data-moksa-broadcast-count></span>
				</span>
			</p>

			<p>
				<label for="moksa-broadcast-flex"><?php esc_html_e( 'Attach a Flex template', 'moksa-for-line' ); ?></label>
				<select id="moksa-broadcast-flex" name="flex_id" class="widefat">
					<option value="0"><?php esc_html_e( 'None', 'moksa-for-line' ); ?></option>
					<?php foreach ( Flex::all() as $template ) : ?>
						<?php // The card travels with the option so the preview can draw the real thing rather than name it. ?>
						<option value="<?php echo esc_attr( (string) $template->id ); ?>"
							data-contents="<?php echo esc_attr( (string) $template->contents ); ?>"
							data-alt="<?php echo esc_attr( (string) $template->alt_text ); ?>">
							<?php echo esc_html( (string) $template->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="moksa-broadcast-mode"><?php esc_html_e( 'Send to', 'moksa-for-line' ); ?></label>
				<select id="moksa-broadcast-mode" name="mode" class="widefat">
					<option value="test"><?php esc_html_e( 'Just one person (a test)', 'moksa-for-line' ); ?></option>
					<option value="known">
						<?php
						printf(
							/* translators: %s: number of recorded friends. */
							esc_html__( 'Friends recorded on this site (%s)', 'moksa-for-line' ),
							esc_html( number_format_i18n( $stats['friends'] ) )
						);
						?>
					</option>
					<option value="all">
						<?php
						if ( null !== $reachable ) {
							printf(
								/* translators: %s: number of people a broadcast reaches. */
								esc_html__( 'Everyone who follows the account (%s)', 'moksa-for-line' ),
								esc_html( number_format_i18n( $reachable ) )
							);
						} else {
							esc_html_e( 'Everyone who follows the account', 'moksa-for-line' );
						}
						?>
					</option>
				</select>
				<span class="description">
					<?php esc_html_e( 'The recorded list only includes people this site has seen since the webhook was switched on, so it is usually smaller than the real follower count.', 'moksa-for-line' ); ?>
				</span>
			</p>

			<p data-moksa-broadcast-target>
				<label for="moksa-broadcast-user"><?php esc_html_e( 'LINE user id', 'moksa-for-line' ); ?></label>
				<input type="text" id="moksa-broadcast-user" name="line_user_id" class="widefat" placeholder="U1234..." />
			</p>

			<p data-moksa-broadcast-confirm hidden>
				<label for="moksa-broadcast-confirm"><?php esc_html_e( 'Type SEND to confirm', 'moksa-for-line' ); ?></label>
				<input type="text" id="moksa-broadcast-confirm" name="confirm" class="small-text" autocomplete="off" />
			</p>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'moksa-for-line' ); ?></button></p>

			<div class="moksa-feedback" data-moksa-feedback></div>
		</form>
		</div>

		<div class="moksa-split__side">
			<div class="moksa-panel">
				<h2><?php esc_html_e( 'Preview', 'moksa-for-line' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'What every recipient gets. A broadcast is billed per person and cannot be recalled, so this is the last look you have.', 'moksa-for-line' ); ?>
				</p>

				<?php // data-line-preview marks a deliberate imitation of LINE's UI so accessibility scanners skip it. ?>
				<div class="moksa-phone-chat" data-line-preview>
					<div class="moksa-phone-chat__bar">
						<span class="moksa-phone-chat__dot"></span>
						<?php echo esc_html( $account_name ); ?>
					</div>
					<div class="moksa-phone-chat__body">
						<span class="moksa-phone-chat__avatar" aria-hidden="true"></span>
						<div class="moksa-broadcast-preview" data-moksa-broadcast-preview></div>
					</div>
				</div>
			</div>
		</div>
		</div>
	</div>
	<?php
} )();
