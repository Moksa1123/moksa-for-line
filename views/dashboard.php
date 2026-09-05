<?php
/**
 * Dashboard: setup state and headline numbers.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Admin\AdminModule;
use Moksa\Line\Data\Users;
use Moksa\Line\Inbox\Conversations;
use Moksa\Line\Api\MessagingClient;
use Moksa\Line\Api\TokenManager;

defined( 'ABSPATH' ) || exit;

$stats     = Users::stats();
$checklist = AdminModule::checklist();
$quota     = null;

if ( TokenManager::is_configured() ) {
	$consumption = MessagingClient::quota_consumption();
	$allowance   = MessagingClient::quota();

	if ( ! is_wp_error( $consumption ) && ! is_wp_error( $allowance ) ) {
		$quota = array(
			'used'  => isset( $consumption['totalUsage'] ) ? (int) $consumption['totalUsage'] : 0,
			'type'  => isset( $allowance['type'] ) ? (string) $allowance['type'] : '',
			'limit' => isset( $allowance['value'] ) ? (int) $allowance['value'] : 0,
		);
	}
}
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'LINE', 'moksa-line-login' ); ?></h1>

	<div class="moksa-cards">
		<div class="moksa-card">
			<span class="moksa-card__number"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
			<span class="moksa-card__label"><?php esc_html_e( 'LINE users known', 'moksa-line-login' ); ?></span>
		</div>
		<div class="moksa-card">
			<span class="moksa-card__number"><?php echo esc_html( number_format_i18n( $stats['friends'] ) ); ?></span>
			<span class="moksa-card__label"><?php esc_html_e( 'Friends of the account', 'moksa-line-login' ); ?></span>
		</div>
		<div class="moksa-card">
			<span class="moksa-card__number"><?php echo esc_html( number_format_i18n( $stats['linked'] ) ); ?></span>
			<span class="moksa-card__label"><?php esc_html_e( 'Linked WordPress accounts', 'moksa-line-login' ); ?></span>
		</div>
		<div class="moksa-card">
			<span class="moksa-card__number"><?php echo esc_html( number_format_i18n( Conversations::unread_total() ) ); ?></span>
			<span class="moksa-card__label"><?php esc_html_e( 'Conversations waiting', 'moksa-line-login' ); ?></span>
		</div>
	</div>

	<div class="moksa-panel">
		<h2><?php esc_html_e( 'Setup', 'moksa-line-login' ); ?></h2>
		<ul class="moksa-checklist">
			<?php foreach ( $checklist as $item ) : ?>
				<li class="moksa-checklist__item moksa-checklist__item--<?php echo $item['done'] ? 'done' : 'todo'; ?>">
					<strong><?php echo esc_html( $item['label'] ); ?></strong>
					<?php if ( ! $item['done'] ) : ?>
						<span class="moksa-checklist__hint"><?php echo esc_html( $item['hint'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminModule::SLUG . '-settings' ) ); ?>">
				<?php esc_html_e( 'Open settings', 'moksa-line-login' ); ?>
			</a>
		</p>
	</div>

	<?php if ( $quota ) : ?>
		<div class="moksa-panel">
			<h2><?php esc_html_e( 'Message quota this month', 'moksa-line-login' ); ?></h2>
			<?php if ( 'limited' === $quota['type'] && $quota['limit'] > 0 ) : ?>
				<p>
					<?php
					printf(
						/* translators: 1: messages used, 2: monthly allowance. */
						esc_html__( '%1$s of %2$s push messages used.', 'moksa-line-login' ),
						esc_html( number_format_i18n( $quota['used'] ) ),
						esc_html( number_format_i18n( $quota['limit'] ) )
					);
					?>
				</p>
				<div class="moksa-meter">
					<span style="width: <?php echo esc_attr( (string) min( 100, round( $quota['used'] / max( 1, $quota['limit'] ) * 100 ) ) ); ?>%"></span>
				</div>
			<?php else : ?>
				<p>
					<?php
					printf(
						/* translators: %s: messages used. */
						esc_html__( '%s push messages sent this month. This plan has no monthly cap.', 'moksa-line-login' ),
						esc_html( number_format_i18n( $quota['used'] ) )
					);
					?>
				</p>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Replies to an inbound message are free. Push, multicast and broadcast are billed.', 'moksa-line-login' ); ?></p>
		</div>
	<?php endif; ?>
</div>
