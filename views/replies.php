<?php
/**
 * Keyword auto-reply rules.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Bot\AutoReply;
use Moksa\Line\Bot\Flow;
use Moksa\Line\Data\Flex;
use Moksa\Line\Data\QuickReplies;

defined( 'ABSPATH' ) || exit;

$rules = AutoReply::all();
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Auto replies', 'moksa-line-login' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Rules are checked in order of match strength first (exact, then prefix, then pattern, then partial, then catch-all) and priority second, so a lower priority number wins between rules of equal strength.', 'moksa-line-login' ); ?>
	</p>

	<div class="moksa-split">
		<div class="moksa-split__main">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'moksa-line-login' ); ?></th>
						<th><?php esc_html_e( 'Trigger', 'moksa-line-login' ); ?></th>
						<th><?php esc_html_e( 'Match', 'moksa-line-login' ); ?></th>
						<th><?php esc_html_e( 'Reply', 'moksa-line-login' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'moksa-line-login' ); ?></th>
						<th><?php esc_html_e( 'Hits', 'moksa-line-login' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rules ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No rules yet.', 'moksa-line-login' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rules as $rule ) : ?>
						<tr data-rule='<?php echo esc_attr( wp_json_encode( $rule ) ); ?>'>
							<td>
								<strong><?php echo esc_html( '' !== $rule->name ? (string) $rule->name : (string) $rule->keyword ); ?></strong>
								<?php if ( ! (int) $rule->is_active ) : ?>
									<span class="moksa-pill moksa-pill--bad"><?php esc_html_e( 'off', 'moksa-line-login' ); ?></span>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( (string) $rule->keyword ); ?></code></td>
							<td><?php echo esc_html( (string) $rule->match_type ); ?></td>
							<td><?php echo esc_html( (string) $rule->reply_type ); ?></td>
							<td><?php echo esc_html( (string) (int) $rule->priority ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $rule->hit_count ) ); ?></td>
							<td>
								<button type="button" class="button-link" data-moksa-edit-rule><?php esc_html_e( 'Edit', 'moksa-line-login' ); ?></button>
								<button type="button" class="button-link delete" data-moksa-delete-rule="<?php echo esc_attr( (string) $rule->id ); ?>"><?php esc_html_e( 'Delete', 'moksa-line-login' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="moksa-split__side">
			<form class="moksa-panel" data-moksa-rule-form>
				<h2><?php esc_html_e( 'Add or edit a rule', 'moksa-line-login' ); ?></h2>
				<input type="hidden" name="id" value="0" />

				<p>
					<label for="moksa-rule-name"><?php esc_html_e( 'Name', 'moksa-line-login' ); ?></label>
					<input type="text" id="moksa-rule-name" name="name" class="widefat" />
				</p>

				<p>
					<label for="moksa-rule-keyword"><?php esc_html_e( 'Trigger text', 'moksa-line-login' ); ?></label>
					<input type="text" id="moksa-rule-keyword" name="keyword" class="widefat" />
				</p>

				<p>
					<label for="moksa-rule-match"><?php esc_html_e( 'Match', 'moksa-line-login' ); ?></label>
					<select id="moksa-rule-match" name="match_type" class="widefat">
						<option value="exact"><?php esc_html_e( 'The whole message is exactly this', 'moksa-line-login' ); ?></option>
						<option value="prefix"><?php esc_html_e( 'The message starts with this', 'moksa-line-login' ); ?></option>
						<option value="partial"><?php esc_html_e( 'The message contains this', 'moksa-line-login' ); ?></option>
						<option value="regex"><?php esc_html_e( 'The message matches this pattern', 'moksa-line-login' ); ?></option>
						<option value="any"><?php esc_html_e( 'Anything (catch-all)', 'moksa-line-login' ); ?></option>
					</select>
				</p>

				<p>
					<label for="moksa-rule-type"><?php esc_html_e( 'Reply with', 'moksa-line-login' ); ?></label>
					<select id="moksa-rule-type" name="reply_type" class="widefat" data-moksa-rule-type>
						<option value="text"><?php esc_html_e( 'Text', 'moksa-line-login' ); ?></option>
						<option value="flex"><?php esc_html_e( 'A Flex template', 'moksa-line-login' ); ?></option>
						<option value="quick_reply"><?php esc_html_e( 'A quick reply set', 'moksa-line-login' ); ?></option>
						<option value="flow"><?php esc_html_e( 'Start a conversation flow', 'moksa-line-login' ); ?></option>
						<option value="sticker"><?php esc_html_e( 'A sticker', 'moksa-line-login' ); ?></option>
						<option value="image"><?php esc_html_e( 'An image', 'moksa-line-login' ); ?></option>
						<option value="raw"><?php esc_html_e( 'Raw message JSON', 'moksa-line-login' ); ?></option>
					</select>
				</p>

				<p data-moksa-reply-field="text">
					<label for="moksa-rule-text"><?php esc_html_e( 'Message', 'moksa-line-login' ); ?></label>
					<textarea id="moksa-rule-text" name="reply_data_text" rows="4" class="widefat"></textarea>
					<span class="description"><?php esc_html_e( 'You can use {display_name}, {site_name} and {site_url}.', 'moksa-line-login' ); ?></span>
				</p>

				<p data-moksa-reply-field="flex" hidden>
					<label for="moksa-rule-flex"><?php esc_html_e( 'Template', 'moksa-line-login' ); ?></label>
					<select id="moksa-rule-flex" name="reply_data_flex" class="widefat">
						<?php foreach ( Flex::all() as $template ) : ?>
							<option value="<?php echo esc_attr( (string) $template->id ); ?>"><?php echo esc_html( (string) $template->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p data-moksa-reply-field="quick_reply" hidden>
					<label for="moksa-rule-quick"><?php esc_html_e( 'Quick reply set', 'moksa-line-login' ); ?></label>
					<select id="moksa-rule-quick" name="reply_data_quick_reply" class="widefat">
						<?php foreach ( QuickReplies::all() as $set ) : ?>
							<option value="<?php echo esc_attr( (string) $set->id ); ?>"><?php echo esc_html( (string) $set->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p data-moksa-reply-field="flow" hidden>
					<label for="moksa-rule-flow"><?php esc_html_e( 'Flow', 'moksa-line-login' ); ?></label>
					<select id="moksa-rule-flow" name="reply_data_flow" class="widefat">
						<?php foreach ( Flow::all() as $flow ) : ?>
							<option value="<?php echo esc_attr( (string) $flow->id ); ?>"><?php echo esc_html( (string) $flow->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p data-moksa-reply-field="sticker" hidden>
					<label for="moksa-rule-sticker"><?php esc_html_e( 'Package and sticker ID', 'moksa-line-login' ); ?></label>
					<input type="text" id="moksa-rule-sticker" name="reply_data_sticker" class="widefat" placeholder="446,1988" />
				</p>

				<p data-moksa-reply-field="image" hidden>
					<label for="moksa-rule-image"><?php esc_html_e( 'Image URL', 'moksa-line-login' ); ?></label>
					<input type="url" id="moksa-rule-image" name="reply_data_image" class="widefat" />
					<span class="description"><?php esc_html_e( 'Must be HTTPS, JPEG or PNG, and at most 10 MB.', 'moksa-line-login' ); ?></span>
				</p>

				<p data-moksa-reply-field="raw" hidden>
					<label for="moksa-rule-raw"><?php esc_html_e( 'Message JSON', 'moksa-line-login' ); ?></label>
					<textarea id="moksa-rule-raw" name="reply_data_raw" rows="8" class="widefat code"></textarea>
				</p>

				<p>
					<label for="moksa-rule-priority"><?php esc_html_e( 'Priority', 'moksa-line-login' ); ?></label>
					<input type="number" id="moksa-rule-priority" name="priority" value="10" class="small-text" />
				</p>

				<p>
					<label>
						<input type="checkbox" name="is_active" value="1" checked />
						<?php esc_html_e( 'Active', 'moksa-line-login' ); ?>
					</label>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save rule', 'moksa-line-login' ); ?></button>
					<button type="button" class="button" data-moksa-reset-rule><?php esc_html_e( 'New rule', 'moksa-line-login' ); ?></button>
				</p>

				<div class="moksa-feedback" data-moksa-feedback></div>
			</form>
		</div>
	</div>
</div>
