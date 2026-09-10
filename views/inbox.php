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
		esc_html__( 'Inbox', 'moksa-line' ),
		esc_html__( 'The inbox is switched off under LINE > Settings > Messaging API.', 'moksa-line' )
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
<?php
$status_labels = array(
	'bot'    => __( 'bot', 'moksa-line' ),
	'human'  => __( 'human', 'moksa-line' ),
	'closed' => __( 'closed', 'moksa-line' ),
);
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Inbox', 'moksa-line' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Only messages received after this plugin was installed appear here. LINE provides no way to read earlier chat history.', 'moksa-line' ); ?>
		<?php esc_html_e( 'Replies are sent as push messages, which are billed against your channel quota.', 'moksa-line' ); ?>
	</p>

	<form method="get" class="moksa-inbox__filters">
		<input type="hidden" name="page" value="moksa-line-inbox" />
		<label for="moksa-inbox-status" class="screen-reader-text"><?php esc_html_e( 'Which conversations to show', 'moksa-line' ); ?></label>
		<select id="moksa-inbox-status" name="status">
			<option value=""><?php esc_html_e( 'All conversations', 'moksa-line' ); ?></option>
			<option value="bot" <?php selected( $status, 'bot' ); ?>><?php esc_html_e( 'Handled by the bot', 'moksa-line' ); ?></option>
			<option value="human" <?php selected( $status, 'human' ); ?>><?php esc_html_e( 'Taken over by a person', 'moksa-line' ); ?></option>
			<option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Closed', 'moksa-line' ); ?></option>
		</select>
		<label for="moksa-inbox-search" class="screen-reader-text"><?php esc_html_e( 'Search conversations', 'moksa-line' ); ?></label>
		<input type="search" id="moksa-inbox-search" class="moksa-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name or message', 'moksa-line' ); ?>" />
		<?php submit_button( __( 'Filter', 'moksa-line' ), 'secondary', '', false ); ?>
	</form>

	<div class="moksa-inbox">
		<div class="moksa-inbox__list">
			<?php if ( empty( $list['rows'] ) ) : ?>
				<p class="moksa-inbox__empty"><?php esc_html_e( 'No conversations yet.', 'moksa-line' ); ?></p>
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
							<?php echo esc_html( '' !== $conversation->display_name ? (string) $conversation->display_name : (string) $conversation->line_user_id ); ?>
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
		</div>

		<div class="moksa-inbox__thread">
			<div class="moksa-inbox__header">
				<strong data-moksa-thread-name><?php esc_html_e( 'Choose a conversation', 'moksa-line' ); ?></strong>
				<span class="moksa-inbox__actions" hidden data-moksa-thread-actions>
					<button type="button" class="button" data-moksa-status="human"><?php esc_html_e( 'Take over', 'moksa-line' ); ?></button>
					<button type="button" class="button" data-moksa-status="bot"><?php esc_html_e( 'Give back to the bot', 'moksa-line' ); ?></button>
					<button type="button" class="button" data-moksa-status="closed"><?php esc_html_e( 'Close', 'moksa-line' ); ?></button>
				</span>
			</div>

			<div class="moksa-inbox__messages" data-moksa-thread>
				<p class="moksa-inbox-empty"><?php esc_html_e( 'Pick a conversation on the left to read it and reply.', 'moksa-line' ); ?></p>
			</div>

			<?php // The picker sits above the composer so opening it does not push the send button off screen. ?>
			<div class="moksa-stickers" data-moksa-sticker-picker hidden>
				<div class="moksa-stickers__packs">
					<?php foreach ( \Moksa\Line\Bot\Stickers::packs() as $index => $pack ) : ?>
						<button type="button" class="button button-small<?php echo 0 === $index ? ' is-current' : ''; ?>"
							data-moksa-sticker-pack="<?php echo esc_attr( (string) $pack['package_id'] ); ?>">
							<?php echo esc_html( (string) $pack['label'] ); ?>
						</button>
					<?php endforeach; ?>
					<span class="description">
						<?php esc_html_e( 'LINE only accepts these from a bot. Pick one, then press Send. It is billed like any other message.', 'moksa-line' ); ?>
					</span>
				</div>

				<?php foreach ( \Moksa\Line\Bot\Stickers::packs() as $index => $pack ) : ?>
					<div class="moksa-stickers__grid" data-moksa-sticker-grid="<?php echo esc_attr( (string) $pack['package_id'] ); ?>"<?php echo 0 === $index ? '' : ' hidden'; ?>>
						<?php for ( $id = (int) $pack['from']; $id <= (int) $pack['to']; $id++ ) : ?>
							<button type="button" class="moksa-sticker"
								data-moksa-pick-sticker="<?php echo esc_attr( (string) $pack['package_id'] ); ?>"
								data-sticker-id="<?php echo esc_attr( (string) $id ); ?>"
								title="<?php echo esc_attr( $pack['package_id'] . ', ' . $id ); ?>">
								<img src="<?php echo esc_url( \Moksa\Line\Bot\Stickers::image_url( (string) $id ) ); ?>"
									alt="" width="60" height="60" loading="lazy" />
							</button>
						<?php endfor; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<form class="moksa-inbox__reply" data-moksa-reply hidden>
				<label class="screen-reader-text" for="moksa-reply-text"><?php esc_html_e( 'Reply', 'moksa-line' ); ?></label>

				<?php
				// A chosen sticker waits here until Send is pressed, exactly as
				// typed text does. Nothing in this composer leaves on one click.
				?>
				<span class="moksa-reply-sticker" data-moksa-chosen-sticker hidden>
					<img src="" alt="" width="52" height="52" data-moksa-chosen-sticker-image />
					<button type="button" class="moksa-reply-sticker__clear" data-moksa-clear-sticker
						title="<?php esc_attr_e( 'Remove this sticker', 'moksa-line' ); ?>">
						<span aria-hidden="true">&times;</span>
						<span class="screen-reader-text"><?php esc_html_e( 'Remove this sticker', 'moksa-line' ); ?></span>
					</button>
				</span>

				<?php // Not required: a sticker on its own is a complete reply. ?>
				<textarea id="moksa-reply-text" rows="3" placeholder="<?php esc_attr_e( 'Write a reply', 'moksa-line' ); ?>"></textarea>
				<button type="button" class="button moksa-inbox__sticker-toggle" data-moksa-toggle-stickers
					aria-expanded="false" title="<?php esc_attr_e( 'Send a sticker', 'moksa-line' ); ?>">
					<span aria-hidden="true">☺</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Send a sticker', 'moksa-line' ); ?></span>
				</button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'moksa-line' ); ?></button>
			</form>
		</div>
	</div>
</div>
