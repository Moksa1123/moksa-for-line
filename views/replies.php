<?php
/**
 * Keyword auto-reply rules.
 *
 * @package Mofoline
 */

use Mofoline\Bot\AutoReply;
use Mofoline\Bot\Flow;
use Mofoline\Data\Flex;
use Mofoline\Data\QuickReplies;

defined( 'ABSPATH' ) || exit;

( static function (): void {
	$rules = AutoReply::all();

	// The same labels the edit form uses, so the two can never disagree.
	$match_labels = AutoReply::match_labels();
	$reply_labels = AutoReply::reply_labels();

	$basic_id     = ltrim( trim( (string) \Mofoline\Support\Options::get( 'bot_basic_id' ) ), '@' );
	$account_name = '' !== $basic_id ? '@' . $basic_id : __( 'Your official account', 'moksa-for-line' );

	// A conversation an agent has taken over gets no keyword replies at all -- by
	// design, so the bot does not talk over a person. Nothing said so anywhere,
	// which makes a working rule look broken: you send the keyword, and nothing
	// happens.
	$held = \Mofoline\Support\Options::get( 'inbox_enabled' )
		? \Mofoline\Inbox\Conversations::human_handled_total()
		: 0;
	?>
	<div class="wrap mofoline-wrap">
		<h1><?php esc_html_e( 'Auto replies', 'moksa-for-line' ); ?></h1>

		<?php if ( $held > 0 ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php
					printf(
						/* translators: %s: number of conversations. */
						esc_html( _n(
							'%s conversation is being handled by a person right now. None of these rules run for it -- the bot stays quiet so it does not talk over you. Hand it back to the bot in the inbox to test a rule with that account.',
							'%s conversations are being handled by a person right now. None of these rules run for them -- the bot stays quiet so it does not talk over you. Hand them back to the bot in the inbox to test a rule with those accounts.',
							$held,
							'moksa-for-line'
						) ),
						esc_html( number_format_i18n( $held ) )
					);
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mofoline-inbox' ) ); ?>"><?php esc_html_e( 'Open the inbox', 'moksa-for-line' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<p class="description">
			<?php esc_html_e( 'Rules are checked in order of match strength first (exact, then prefix, then pattern, then partial, then catch-all) and priority second, so a lower priority number wins between rules of equal strength.', 'moksa-for-line' ); ?>
		</p>

		<div class="moksa-split">
			<div class="moksa-split__main">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'moksa-for-line' ); ?></th>
							<th><?php esc_html_e( 'Trigger', 'moksa-for-line' ); ?></th>
							<th><?php esc_html_e( 'Match', 'moksa-for-line' ); ?></th>
							<th><?php esc_html_e( 'Reply', 'moksa-for-line' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'moksa-for-line' ); ?></th>
							<th><?php esc_html_e( 'Hits', 'moksa-for-line' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'moksa-for-line' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rules ) ) : ?>
							<tr><td colspan="7"><?php esc_html_e( 'No rules yet.', 'moksa-for-line' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $rules as $rule ) : ?>
							<tr data-rule='<?php echo esc_attr( wp_json_encode( $rule ) ); ?>'>
								<td>
									<strong><?php echo esc_html( '' !== $rule->name ? (string) $rule->name : (string) $rule->keyword ); ?></strong>
									<?php if ( ! (int) $rule->is_active ) : ?>
										<span class="moksa-pill moksa-pill--bad"><?php esc_html_e( 'off', 'moksa-for-line' ); ?></span>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( (string) $rule->keyword ); ?></code></td>
								<td><?php echo esc_html( $match_labels[ (string) $rule->match_type ] ?? (string) $rule->match_type ); ?></td>
								<td><?php echo esc_html( $reply_labels[ (string) $rule->reply_type ] ?? (string) $rule->reply_type ); ?></td>
								<td><?php echo esc_html( (string) (int) $rule->priority ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $rule->hit_count ) ); ?></td>
								<td>
									<button type="button" class="button-link" data-moksa-edit-rule><?php esc_html_e( 'Edit', 'moksa-for-line' ); ?></button>
									<button type="button" class="button-link delete" data-moksa-delete-rule="<?php echo esc_attr( (string) $rule->id ); ?>"><?php esc_html_e( 'Delete', 'moksa-for-line' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="moksa-split__side">
				<form class="moksa-panel" data-moksa-rule-form>
					<h2>
						<?php esc_html_e( 'Add or edit a rule', 'moksa-for-line' ); ?>
						<span class="moksa-editing-badge"><?php esc_html_e( 'Editing', 'moksa-for-line' ); ?></span>
					</h2>
					<input type="hidden" name="id" value="0" />

					<p>
						<label for="moksa-rule-name"><?php esc_html_e( 'Name', 'moksa-for-line' ); ?></label>
						<input type="text" id="moksa-rule-name" name="name" class="widefat" />
					</p>

					<p>
						<label for="moksa-rule-keyword"><?php esc_html_e( 'Trigger text', 'moksa-for-line' ); ?></label>
						<input type="text" id="moksa-rule-keyword" name="keyword" class="widefat" />
					</p>

					<p>
						<label for="moksa-rule-match"><?php esc_html_e( 'Match', 'moksa-for-line' ); ?></label>
						<select id="moksa-rule-match" name="match_type" class="widefat">
							<?php foreach ( $match_labels as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label for="moksa-rule-type"><?php esc_html_e( 'Reply with', 'moksa-for-line' ); ?></label>
						<select id="moksa-rule-type" name="reply_type" class="widefat" data-moksa-rule-type>
							<?php foreach ( $reply_labels as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p data-moksa-reply-field="text">
						<label for="moksa-rule-text"><?php esc_html_e( 'Message', 'moksa-for-line' ); ?></label>
						<textarea id="moksa-rule-text" name="reply_data_text" rows="4" class="widefat"></textarea>
						<span class="description"><?php esc_html_e( 'You can use {display_name}, {site_name} and {site_url}.', 'moksa-for-line' ); ?></span>
					</p>

					<p data-moksa-reply-field="flex" hidden>
						<label for="moksa-rule-flex"><?php esc_html_e( 'Template', 'moksa-for-line' ); ?></label>
						<select id="moksa-rule-flex" name="reply_data_flex" class="widefat">
							<?php foreach ( Flex::all() as $template ) : ?>
								<option value="<?php echo esc_attr( (string) $template->id ); ?>"><?php echo esc_html( (string) $template->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p data-moksa-reply-field="quick_reply" hidden>
						<label for="moksa-rule-quick"><?php esc_html_e( 'Quick reply set', 'moksa-for-line' ); ?></label>
						<select id="moksa-rule-quick" name="reply_data_quick_reply" class="widefat">
							<?php foreach ( QuickReplies::all() as $set ) : ?>
								<option value="<?php echo esc_attr( (string) $set->id ); ?>"><?php echo esc_html( (string) $set->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p data-moksa-reply-field="flow" hidden>
						<label for="moksa-rule-flow"><?php esc_html_e( 'Flow', 'moksa-for-line' ); ?></label>
						<select id="moksa-rule-flow" name="reply_data_flow" class="widefat">
							<?php foreach ( Flow::all() as $flow ) : ?>
								<option value="<?php echo esc_attr( (string) $flow->id ); ?>"><?php echo esc_html( (string) $flow->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p data-moksa-reply-field="sticker" hidden>
						<label for="moksa-rule-sticker"><?php esc_html_e( 'Package and sticker ID', 'moksa-for-line' ); ?></label>
						<input type="text" id="moksa-rule-sticker" name="reply_data_sticker" class="widefat" placeholder="446,1988" />
					</p>

					<p data-moksa-reply-field="image" hidden>
						<label for="moksa-rule-image"><?php esc_html_e( 'Image URL', 'moksa-for-line' ); ?></label>
						<input type="url" id="moksa-rule-image" name="reply_data_image" class="widefat" />
						<span class="description"><?php esc_html_e( 'Must be HTTPS, JPEG or PNG, and at most 10 MB.', 'moksa-for-line' ); ?></span>
					</p>

					<p data-moksa-reply-field="raw" hidden>
						<label for="moksa-rule-raw"><?php esc_html_e( 'Message JSON', 'moksa-for-line' ); ?></label>
						<textarea id="moksa-rule-raw" name="reply_data_raw" rows="8" class="widefat code"></textarea>
					</p>

					<p>
						<label for="moksa-rule-priority"><?php esc_html_e( 'Priority', 'moksa-for-line' ); ?></label>
						<input type="number" id="moksa-rule-priority" name="priority" value="10" class="small-text" />
					</p>

					<p>
						<label>
							<input type="checkbox" name="is_active" value="1" checked />
							<?php esc_html_e( 'Active', 'moksa-for-line' ); ?>
						</label>
					</p>

					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save rule', 'moksa-for-line' ); ?></button>
						<button type="button" class="button" data-moksa-reset-rule><?php esc_html_e( 'New rule', 'moksa-for-line' ); ?></button>
					</p>

					<div class="moksa-feedback" data-moksa-feedback></div>
				</form>

				<div class="moksa-panel">
					<h2><?php esc_html_e( 'Preview', 'moksa-for-line' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'The customer sends the trigger, the bot answers. Placeholders are filled in with sample values so you can see the sentence they actually read.', 'moksa-for-line' ); ?>
					</p>

					<?php // data-line-preview marks a deliberate imitation of LINE's UI so accessibility scanners skip it. ?>
					<div class="moksa-phone-chat" data-line-preview>
						<div class="moksa-phone-chat__bar">
							<span class="moksa-phone-chat__dot"></span>
							<?php echo esc_html( $account_name ); ?>
						</div>
						<div class="moksa-phone-chat__body moksa-phone-chat__body--thread" data-moksa-reply-preview></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
} )();
