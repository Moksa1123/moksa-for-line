<?php
/**
 * Customer-service inbox.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Inbox\Conversations;
use Moksa\Line\Support\Options;

defined( 'ABSPATH' ) || exit;

if ( ! Options::get( 'inbox_enabled' ) ) {
	printf(
		'<div class="wrap"><h1>%s</h1><p>%s</p></div>',
		esc_html__( 'Inbox', 'moksa-line-login' ),
		esc_html__( 'The inbox is switched off under LINE > Settings > Messaging API.', 'moksa-line-login' )
	);

	return;
}

$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$list = Conversations::paginate(
	array(
		'status'   => $status,
		'search'   => $search,
		'per_page' => 50,
	)
);
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Inbox', 'moksa-line-login' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Only messages received after this plugin was installed appear here. LINE provides no way to read earlier chat history.', 'moksa-line-login' ); ?>
		<?php esc_html_e( 'Replies are sent as push messages, which are billed against your channel quota.', 'moksa-line-login' ); ?>
	</p>

	<form method="get" class="moksa-inbox__filters">
		<input type="hidden" name="page" value="moksa-line-inbox" />
		<select name="status">
			<option value=""><?php esc_html_e( 'All conversations', 'moksa-line-login' ); ?></option>
			<option value="bot" <?php selected( $status, 'bot' ); ?>><?php esc_html_e( 'Handled by the bot', 'moksa-line-login' ); ?></option>
			<option value="human" <?php selected( $status, 'human' ); ?>><?php esc_html_e( 'Taken over by a person', 'moksa-line-login' ); ?></option>
			<option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Closed', 'moksa-line-login' ); ?></option>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name or message', 'moksa-line-login' ); ?>" />
		<?php submit_button( __( 'Filter', 'moksa-line-login' ), 'secondary', '', false ); ?>
	</form>

	<div class="moksa-inbox">
		<div class="moksa-inbox__list">
			<?php if ( empty( $list['rows'] ) ) : ?>
				<p class="moksa-inbox__empty"><?php esc_html_e( 'No conversations yet.', 'moksa-line-login' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $list['rows'] as $conversation ) : ?>
				<button type="button" class="moksa-conv" data-conversation="<?php echo esc_attr( (string) $conversation->id ); ?>">
					<?php if ( '' !== (string) $conversation->picture_url ) : ?>
						<img class="moksa-conv__avatar" src="<?php echo esc_url( (string) $conversation->picture_url ); ?>" alt="" width="36" height="36" loading="lazy" />
					<?php endif; ?>
					<span class="moksa-conv__body">
						<span class="moksa-conv__name">
							<?php echo esc_html( '' !== $conversation->display_name ? (string) $conversation->display_name : (string) $conversation->line_user_id ); ?>
							<?php if ( (int) $conversation->unread_count > 0 ) : ?>
								<span class="moksa-conv__unread"><?php echo esc_html( (string) (int) $conversation->unread_count ); ?></span>
							<?php endif; ?>
						</span>
						<span class="moksa-conv__preview"><?php echo esc_html( (string) $conversation->last_message_preview ); ?></span>
						<span class="moksa-conv__meta">
							<span class="moksa-pill moksa-pill--<?php echo esc_attr( 'human' === $conversation->status ? 'warn' : ( 'closed' === $conversation->status ? 'bad' : 'ok' ) ); ?>">
								<?php echo esc_html( (string) $conversation->status ); ?>
							</span>
							<?php if ( $conversation->last_message_at ) : ?>
								<?php echo esc_html( mysql2date( 'Y-m-d H:i', get_date_from_gmt( (string) $conversation->last_message_at ) ) ); ?>
							<?php endif; ?>
						</span>
					</span>
				</button>
			<?php endforeach; ?>
		</div>

		<div class="moksa-inbox__thread">
			<div class="moksa-inbox__header">
				<strong data-moksa-thread-name><?php esc_html_e( 'Choose a conversation', 'moksa-line-login' ); ?></strong>
				<span class="moksa-inbox__actions" hidden data-moksa-thread-actions>
					<button type="button" class="button" data-moksa-status="human"><?php esc_html_e( 'Take over', 'moksa-line-login' ); ?></button>
					<button type="button" class="button" data-moksa-status="bot"><?php esc_html_e( 'Give back to the bot', 'moksa-line-login' ); ?></button>
					<button type="button" class="button" data-moksa-status="closed"><?php esc_html_e( 'Close', 'moksa-line-login' ); ?></button>
				</span>
			</div>

			<div class="moksa-inbox__messages" data-moksa-thread></div>

			<form class="moksa-inbox__reply" data-moksa-reply hidden>
				<label class="screen-reader-text" for="moksa-reply-text"><?php esc_html_e( 'Reply', 'moksa-line-login' ); ?></label>
				<textarea id="moksa-reply-text" rows="3" required placeholder="<?php esc_attr_e( 'Write a reply', 'moksa-line-login' ); ?>"></textarea>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'moksa-line-login' ); ?></button>
			</form>
		</div>
	</div>
</div>
