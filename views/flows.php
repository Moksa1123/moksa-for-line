<?php
/**
 * Conversation flows.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Bot\Flow;

defined( 'ABSPATH' ) || exit;

$basic_id     = ltrim( trim( (string) \Moksa\Line\Support\Options::get( 'bot_basic_id' ) ), '@' );
$account_name = '' !== $basic_id ? '@' . $basic_id : __( 'Your official account', 'moksa-line' );

$flows    = Flow::all();
$selected = isset( $_GET['flow'] ) ? (int) $_GET['flow'] : 0;
// The worked example a new flow starts from. It is the first thing a shop
// edits, so it is translated like any other visible text -- left in English it
// would have them rewriting four questions before writing their own.
$example  = array(
	'steps'            => array(
		array( 'key' => 'name', 'prompt' => __( 'What name should we put this under?', 'moksa-line' ), 'type' => 'text' ),
		array( 'key' => 'phone', 'prompt' => __( 'A phone number we can reach you on?', 'moksa-line' ), 'type' => 'phone' ),
		array( 'key' => 'date', 'prompt' => __( 'Which date would you like?', 'moksa-line' ), 'type' => 'date' ),
		array(
			'key'     => 'service',
			'prompt'  => __( 'Which service?', 'moksa-line' ),
			'type'    => 'choice',
			'choices' => array( __( 'Consultation', 'moksa-line' ), __( 'Follow-up', 'moksa-line' ) ),
		),
	),
	'complete_message' => __( 'Thank you {display_name}, we have your request and will confirm shortly.', 'moksa-line' ),
);
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Conversation flows', 'moksa-line' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'A flow asks a series of questions and stores the answers. Only one flow runs per person at a time, it expires after 30 minutes of silence, and every question offers a way to cancel.', 'moksa-line' ); ?>
	</p>

	<div class="moksa-split moksa-split--wide">
		<div class="moksa-split__main">
			<form class="moksa-panel" data-moksa-flow-form>
				<input type="hidden" name="id" value="0" />

				<p>
					<label for="moksa-flow-name"><?php esc_html_e( 'Name', 'moksa-line' ); ?></label>
					<input type="text" id="moksa-flow-name" name="name" class="widefat" required />
				</p>

				<p>
					<label for="moksa-flow-trigger-type"><?php esc_html_e( 'Start when a message', 'moksa-line' ); ?></label>
					<select id="moksa-flow-trigger-type" name="trigger_type" class="widefat">
						<option value="keyword"><?php esc_html_e( 'is exactly this', 'moksa-line' ); ?></option>
						<option value="contains"><?php esc_html_e( 'contains this', 'moksa-line' ); ?></option>
					</select>
				</p>

				<p>
					<label for="moksa-flow-trigger"><?php esc_html_e( 'Trigger text', 'moksa-line' ); ?></label>
					<input type="text" id="moksa-flow-trigger" name="trigger_value" class="widefat" />
				</p>

				<div class="moksa-field">
					<label><?php esc_html_e( 'Questions', 'moksa-line' ); ?></label>
					<span class="description">
						<?php esc_html_e( 'Asked one at a time, in this order. Each answer is stored under its own key, which is the column you see in submissions.', 'moksa-line' ); ?>
					</span>

					<div class="moksa-flow-editor" data-moksa-flow-editor="[data-moksa-flow-definition]"
						data-types="<?php
						echo esc_attr(
							(string) wp_json_encode(
								array(
									'text'   => __( 'Anything they type', 'moksa-line' ),
									'choice' => __( 'One of these buttons', 'moksa-line' ),
									'number' => __( 'A number', 'moksa-line' ),
									'phone'  => __( 'A phone number', 'moksa-line' ),
									'email'  => __( 'An email address', 'moksa-line' ),
									'date'   => __( 'A date', 'moksa-line' ),
								)
							)
						);
						?>">
						<div class="moksa-flow-steps" data-moksa-flow-steps></div>
						<p>
							<button type="button" class="button" data-moksa-add-step>
								<?php esc_html_e( '+ Add a question', 'moksa-line' ); ?>
							</button>
						</p>
						<div class="moksa-preview-warnings" data-moksa-flow-warnings></div>
					</div>

					<label for="moksa-flow-definition" class="screen-reader-text"><?php esc_html_e( 'The questions as JSON', 'moksa-line' ); ?></label>
					<textarea id="moksa-flow-definition" name="definition" rows="18" class="widefat code moksa-area-json" spellcheck="false" data-moksa-flow-definition hidden><?php echo esc_textarea( (string) wp_json_encode( $example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
					<button type="button" class="button-link" data-moksa-toggle-flow-json><?php esc_html_e( 'Edit the JSON directly', 'moksa-line' ); ?></button>
				</div>

				<p>
					<label for="moksa-flow-complete"><?php esc_html_e( 'When they finish, say', 'moksa-line' ); ?></label>
					<textarea id="moksa-flow-complete" rows="2" class="widefat" data-moksa-flow-complete></textarea>
					<span class="description"><?php esc_html_e( 'You can use {display_name}, {site_name}, {site_url}, and the field name of any answer -- {name} for a step stored as name.', 'moksa-line' ); ?></span>
				</p>

				<p>
					<label for="moksa-flow-email"><?php esc_html_e( 'Email submissions to', 'moksa-line' ); ?></label>
					<input type="email" id="moksa-flow-email" name="notify_email" class="widefat" />
				</p>

				<p>
					<label>
						<input type="checkbox" name="is_active" value="1" checked />
						<?php esc_html_e( 'Active', 'moksa-line' ); ?>
					</label>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save flow', 'moksa-line' ); ?></button>
					<button type="button" class="button" data-moksa-reset-flow><?php esc_html_e( 'New flow', 'moksa-line' ); ?></button>
				</p>

				<div class="moksa-feedback" data-moksa-feedback></div>
			</form>
		</div>

		<div class="moksa-split__side">
			<div class="moksa-panel">
				<h2><?php esc_html_e( 'Preview', 'moksa-line' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'The whole conversation, from the trigger to the last answer. Buttons are drawn where LINE puts them, under the message.', 'moksa-line' ); ?>
				</p>

				<?php // data-line-preview marks a deliberate imitation of LINE's UI so accessibility scanners skip it. ?>
				<div class="moksa-phone-chat" data-line-preview>
					<div class="moksa-phone-chat__bar">
						<span class="moksa-phone-chat__dot"></span>
						<?php echo esc_html( $account_name ); ?>
					</div>
					<div class="moksa-phone-chat__body moksa-phone-chat__body--thread" data-moksa-flow-preview></div>
				</div>
			</div>

			<div class="moksa-panel">
				<h2><?php esc_html_e( 'Saved flows', 'moksa-line' ); ?></h2>
				<ul class="moksa-list">
					<?php if ( empty( $flows ) ) : ?>
						<li><?php esc_html_e( 'None yet.', 'moksa-line' ); ?></li>
					<?php endif; ?>
					<?php foreach ( $flows as $flow ) : ?>
						<li data-flow='<?php echo esc_attr( (string) wp_json_encode( $flow ) ); ?>'>
							<button type="button" class="button-link" data-moksa-load-flow><?php echo esc_html( (string) $flow->name ); ?></button>
							<a href="<?php echo esc_url( add_query_arg( 'flow', (int) $flow->id ) ); ?>"><?php esc_html_e( 'Submissions', 'moksa-line' ); ?></a>
							<button type="button" class="button-link delete" data-moksa-delete-flow="<?php echo esc_attr( (string) $flow->id ); ?>"><?php esc_html_e( 'Delete', 'moksa-line' ); ?></button>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<?php if ( $selected > 0 ) : ?>
				<div class="moksa-panel">
					<h2><?php esc_html_e( 'Submissions', 'moksa-line' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'When', 'moksa-line' ); ?></th>
								<th><?php esc_html_e( 'Who', 'moksa-line' ); ?></th>
								<th><?php esc_html_e( 'Answers', 'moksa-line' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php $submissions = Flow::submissions( $selected ); ?>
							<?php if ( empty( $submissions ) ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'Nothing submitted yet.', 'moksa-line' ); ?></td></tr>
							<?php endif; ?>
							<?php foreach ( $submissions as $submission ) : ?>
								<tr>
									<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', get_date_from_gmt( (string) $submission->created_at ) ) ); ?></td>
									<td><?php echo esc_html( (string) $submission->display_name ); ?></td>
									<td>
										<?php
										$answers = json_decode( (string) $submission->answers, true );

										if ( is_array( $answers ) ) {
											foreach ( $answers as $key => $value ) {
												printf(
													'<div><strong>%s</strong>: %s</div>',
													esc_html( (string) $key ),
													esc_html( is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value ) )
												);
											}
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
