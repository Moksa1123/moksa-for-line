<?php
/**
 * The conversation list, on its own so it can be drawn twice: once when the
 * page loads and again, over the heartbeat, when a message arrives.
 *
 * Expects $list (from Conversations::paginate) and $status_labels.
 *
 * @package Mofoline
 */

defined( 'ABSPATH' ) || exit;

( static function ( array $list, array $status_labels ): void {
	if ( empty( $list['rows'] ) ) : ?>
		<p class="moksa-inbox__empty"><?php esc_html_e( 'No conversations yet.', 'moksa-for-line' ); ?></p>
	<?php endif; ?>

	<?php foreach ( $list['rows'] as $conversation ) : ?>
		<button type="button" class="moksa-conv" data-conversation="<?php echo esc_attr( (string) $conversation->id ); ?>">
			<?php
			$conv_name    = '' !== $conversation->display_name ? (string) $conversation->display_name : (string) $conversation->line_user_id;
			$conv_initial = '' !== trim( $conv_name ) ? mb_substr( trim( $conv_name ), 0, 1 ) : '?';
			?>
			<span class="moksa-conv__avatar moksa-avatar" aria-hidden="true">
				<?php if ( '' !== (string) $conversation->picture_url ) : ?>
					<img src="<?php echo esc_url( (string) $conversation->picture_url ); ?>" alt="" width="36" height="36" loading="lazy" />
				<?php endif; ?>
				<span class="moksa-avatar__initial"><?php echo esc_html( $conv_initial ); ?></span>
			</span>
			<span class="moksa-conv__body">
				<span class="moksa-conv__name">
					<?php echo esc_html( $conv_name ); ?>
					<?php if ( (int) $conversation->unread_count > 0 ) : ?>
						<span class="moksa-conv__unread"><?php echo esc_html( (string) (int) $conversation->unread_count ); ?></span>
					<?php endif; ?>
				</span>
				<span class="moksa-conv__preview"><?php echo esc_html( (string) $conversation->last_message_preview ); ?></span>
				<span class="moksa-conv__meta">
					<?php // The same words the take-over buttons set without a reload, so the pill does not change language when clicked. ?>
					<span class="moksa-pill moksa-pill--<?php echo esc_attr( 'human' === $conversation->status ? 'warn' : ( 'closed' === $conversation->status ? 'bad' : 'ok' ) ); ?>">
						<?php echo esc_html( $status_labels[ (string) $conversation->status ] ?? (string) $conversation->status ); ?>
					</span>
					<?php if ( $conversation->last_message_at ) : ?>
						<?php echo esc_html( mysql2date( 'Y-m-d H:i', get_date_from_gmt( (string) $conversation->last_message_at ) ) ); ?>
					<?php endif; ?>
				</span>
			</span>
		</button>
	<?php endforeach; ?>
	<?php
} )( $list, $status_labels );
