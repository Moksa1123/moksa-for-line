<?php
/**
 * Dashboard: setup state and headline numbers.
 *
 * @package Mofoline
 */

use Mofoline\Admin\AdminModule;
use Mofoline\Data\Users;
use Mofoline\Inbox\Conversations;
use Mofoline\Api\MessagingClient;
use Mofoline\Api\TokenManager;

defined( 'ABSPATH' ) || exit;

( static function (): void {
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
	<div class="wrap mofoline-wrap">
		<h1><?php esc_html_e( 'LINE', 'moksa-for-line' ); ?></h1>

		<?php
		// Each number is a question -- "which three people?" -- so each card is the
		// link to its own answer rather than a figure you then have to go and find.
		$users_url = admin_url( 'admin.php?page=' . AdminModule::SLUG . '-users' );
		$cards     = array(
			array(
				'number' => $stats['total'],
				'label'  => __( 'LINE users known', 'moksa-for-line' ),
				'url'    => $users_url,
			),
			array(
				'number' => $stats['friends'],
				'label'  => __( 'Friends of the account', 'moksa-for-line' ),
				'url'    => $users_url,
			),
			array(
				'number' => $stats['linked'],
				'label'  => __( 'Linked WordPress accounts', 'moksa-for-line' ),
				'url'    => $users_url,
			),
			array(
				'number' => Conversations::unread_total(),
				'label'  => __( 'Conversations waiting', 'moksa-for-line' ),
				'url'    => admin_url( 'admin.php?page=' . AdminModule::SLUG . '-inbox' ),
			),
		);
		?>
		<div class="moksa-cards">
			<?php foreach ( $cards as $card ) : ?>
				<a class="moksa-card" href="<?php echo esc_url( $card['url'] ); ?>">
					<span class="moksa-card__number"><?php echo esc_html( number_format_i18n( $card['number'] ) ); ?></span>
					<span class="moksa-card__label"><?php echo esc_html( $card['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="moksa-panel">
			<h2><?php esc_html_e( 'Setup', 'moksa-for-line' ); ?></h2>
			<ul class="moksa-checklist">
				<?php foreach ( $checklist as $item ) : ?>
					<li class="moksa-checklist__item moksa-checklist__item--<?php echo $item['done'] ? 'done' : 'todo'; ?>">
						<strong><?php echo esc_html( $item['label'] ); ?></strong>
						<?php if ( ! $item['done'] ) : ?>
							<span class="moksa-checklist__hint"><?php echo esc_html( $item['hint'] ); ?></span>
							<?php if ( ! empty( $item['fix'] ) ) : ?>
								<a class="moksa-checklist__fix" href="<?php echo esc_url( (string) $item['fix'] ); ?>">
									<?php esc_html_e( 'Fix this', 'moksa-for-line' ); ?> <span aria-hidden="true">&rarr;</span>
								</a>
							<?php endif; ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminModule::SLUG . '-settings' ) ); ?>">
					<?php esc_html_e( 'Open settings', 'moksa-for-line' ); ?>
				</a>
			</p>
		</div>

		<?php if ( $quota ) : ?>
			<div class="moksa-panel">
				<h2><?php esc_html_e( 'Message quota this month', 'moksa-for-line' ); ?></h2>
				<?php if ( 'limited' === $quota['type'] && $quota['limit'] > 0 ) : ?>
					<p>
						<?php
						printf(
							/* translators: 1: messages used, 2: monthly allowance. */
							esc_html__( '%1$s of %2$s push messages used.', 'moksa-for-line' ),
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
							esc_html__( '%s push messages sent this month. This plan has no monthly cap.', 'moksa-for-line' ),
							esc_html( number_format_i18n( $quota['used'] ) )
						);
						?>
					</p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Replies to an inbound message are free. Push, multicast and broadcast are billed.', 'moksa-for-line' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
	<?php
} )();
