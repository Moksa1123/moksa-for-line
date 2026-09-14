<?php
/**
 * Customer-service inbox.
 *
 * @package Mofoline
 */

use Mofoline\Admin\Ajax;
use Mofoline\Inbox\Conversations;
use Mofoline\Inbox\Messages;
use Mofoline\Support\Options;

defined( 'ABSPATH' ) || exit;

( static function (): void {
	if ( ! Options::get( 'inbox_enabled' ) ) {
		printf(
			'<div class="wrap"><h1>%s</h1><p>%s</p></div>',
			esc_html__( 'Inbox', 'moksa-for-line' ),
			esc_html__( 'The inbox is switched off under LINE > Settings > Messaging API.', 'moksa-for-line' )
		);

		return;
	}

	$status = Ajax::query_key( 'status' );
	$search = Ajax::query_text( 's' );

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
		'bot'    => __( 'bot', 'moksa-for-line' ),
		'human'  => __( 'human', 'moksa-for-line' ),
		'closed' => __( 'closed', 'moksa-for-line' ),
	);
	?>
	<div class="wrap mofoline-wrap">
		<h1><?php esc_html_e( 'Inbox', 'moksa-for-line' ); ?></h1>

		<p class="description">
			<?php esc_html_e( 'Only messages received after this plugin was installed appear here. LINE provides no way to read earlier chat history.', 'moksa-for-line' ); ?>
			<?php esc_html_e( 'Replies are sent as push messages, which are billed against your channel quota.', 'moksa-for-line' ); ?>
		</p>

		<form method="get" class="moksa-inbox__filters">
			<input type="hidden" name="page" value="mofoline-inbox" />
			<label for="moksa-inbox-status" class="screen-reader-text"><?php esc_html_e( 'Which conversations to show', 'moksa-for-line' ); ?></label>
			<select id="moksa-inbox-status" name="status">
				<option value=""><?php esc_html_e( 'All conversations', 'moksa-for-line' ); ?></option>
				<option value="bot" <?php selected( $status, 'bot' ); ?>><?php esc_html_e( 'Handled by the bot', 'moksa-for-line' ); ?></option>
				<option value="human" <?php selected( $status, 'human' ); ?>><?php esc_html_e( 'Taken over by a person', 'moksa-for-line' ); ?></option>
				<option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Closed', 'moksa-for-line' ); ?></option>
			</select>
			<label for="moksa-inbox-search" class="screen-reader-text"><?php esc_html_e( 'Search conversations', 'moksa-for-line' ); ?></label>
			<input type="search" id="moksa-inbox-search" class="moksa-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name or message', 'moksa-for-line' ); ?>" />
			<?php submit_button( __( 'Filter', 'moksa-for-line' ), 'secondary', '', false ); ?>
		</form>

		<div class="moksa-inbox">
			<div class="moksa-inbox__list" data-moksa-inbox-list
				data-status="<?php echo esc_attr( $status ); ?>"
				data-search="<?php echo esc_attr( $search ); ?>"
				data-latest="<?php echo esc_attr( (string) Messages::latest_id() ); ?>">
				<?php require MOFOLINE_DIR . 'views/inbox-list.php'; ?>
			</div>

			<div class="moksa-inbox__thread">
				<div class="moksa-inbox__header">
					<strong data-moksa-thread-name><?php esc_html_e( 'Choose a conversation', 'moksa-for-line' ); ?></strong>
					<button type="button" class="button button-small" data-moksa-notify-enable hidden>
						<?php esc_html_e( 'Desktop notifications', 'moksa-for-line' ); ?>
					</button>
					<span class="moksa-inbox__actions" hidden data-moksa-thread-actions>
						<button type="button" class="button" data-moksa-status="human"><?php esc_html_e( 'Take over', 'moksa-for-line' ); ?></button>
						<button type="button" class="button" data-moksa-status="bot"><?php esc_html_e( 'Give back to the bot', 'moksa-for-line' ); ?></button>
						<button type="button" class="button" data-moksa-status="closed"><?php esc_html_e( 'Close', 'moksa-for-line' ); ?></button>
					</span>
				</div>

				<div class="moksa-inbox__messages" data-moksa-thread>
					<p class="moksa-inbox-empty"><span><?php esc_html_e( 'Pick a conversation on the left to read it and reply.', 'moksa-for-line' ); ?></span></p>
				</div>

				<?php // The picker sits above the composer so opening it does not push the send button off screen. ?>
				<div class="moksa-stickers" data-moksa-sticker-picker hidden>
					<div class="moksa-stickers__packs">
						<?php foreach ( \Mofoline\Bot\Stickers::packs() as $index => $pack ) : ?>
							<button type="button" class="button button-small<?php echo 0 === $index ? ' is-current' : ''; ?>"
								data-moksa-sticker-pack="<?php echo esc_attr( (string) $pack['package_id'] ); ?>">
								<?php echo esc_html( (string) $pack['label'] ); ?>
							</button>
						<?php endforeach; ?>
						<span class="description">
							<?php esc_html_e( 'LINE only accepts these from a bot. Pick one, then press Send. It is billed like any other message.', 'moksa-for-line' ); ?>
						</span>
					</div>

					<?php foreach ( \Mofoline\Bot\Stickers::packs() as $index => $pack ) : ?>
						<div class="moksa-stickers__grid" data-moksa-sticker-grid="<?php echo esc_attr( (string) $pack['package_id'] ); ?>"<?php echo 0 === $index ? '' : ' hidden'; ?>>
							<?php for ( $id = (int) $pack['from']; $id <= (int) $pack['to']; $id++ ) : ?>
								<button type="button" class="moksa-sticker"
									data-moksa-pick-sticker="<?php echo esc_attr( (string) $pack['package_id'] ); ?>"
									data-sticker-id="<?php echo esc_attr( (string) $id ); ?>"
									title="<?php echo esc_attr( $pack['package_id'] . ', ' . $id ); ?>">
									<img src="<?php echo esc_url( \Mofoline\Bot\Stickers::image_url( (string) $id ) ); ?>"
										alt="" width="60" height="60" loading="lazy" />
								</button>
							<?php endfor; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<form class="moksa-inbox__reply" data-moksa-reply hidden>
					<?php
					// Tools sit above the field, not beside it. Stretching an icon
					// button to the height of a three-row textarea made a 47x78
					// sliver; a square button in its own row keeps its proportions
					// and leaves the row below to the field and Send.
					?>
					<div class="moksa-inbox__tools">
						<button type="button" class="button moksa-inbox__sticker-toggle" data-moksa-toggle-stickers
							aria-expanded="false" title="<?php esc_attr_e( 'Send a sticker', 'moksa-for-line' ); ?>">
							<span aria-hidden="true">☺</span>
							<span class="screen-reader-text"><?php esc_html_e( 'Send a sticker', 'moksa-for-line' ); ?></span>
						</button>

						<?php
						// A chosen sticker waits here until Send is pressed, exactly
						// as typed text does. Nothing here leaves on one click.
						?>
						<span class="moksa-reply-sticker" data-moksa-chosen-sticker hidden>
							<img src="" alt="" width="34" height="34" data-moksa-chosen-sticker-image />
							<button type="button" class="moksa-reply-sticker__clear" data-moksa-clear-sticker
								title="<?php esc_attr_e( 'Remove this sticker', 'moksa-for-line' ); ?>">
								<span aria-hidden="true">&times;</span>
								<span class="screen-reader-text"><?php esc_html_e( 'Remove this sticker', 'moksa-for-line' ); ?></span>
							</button>
						</span>
					</div>

					<div class="moksa-inbox__compose">
						<label class="screen-reader-text" for="moksa-reply-text"><?php esc_html_e( 'Reply', 'moksa-for-line' ); ?></label>
						<?php // Not required: a sticker on its own is a complete reply. ?>
						<textarea id="moksa-reply-text" rows="3" placeholder="<?php esc_attr_e( 'Write a reply', 'moksa-for-line' ); ?>"></textarea>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'moksa-for-line' ); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
} )();
