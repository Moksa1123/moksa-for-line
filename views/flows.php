<?php
/**
 * Conversation flows.
 *
 * @package Mofoline
 */

use Mofoline\Admin\Ajax;
use Mofoline\Bot\Flow;

defined( 'ABSPATH' ) || exit;

( static function (): void {
	$basic_id     = ltrim( trim( (string) \Mofoline\Support\Options::get( 'bot_basic_id' ) ), '@' );
	$account_name = '' !== $basic_id ? '@' . $basic_id : __( 'Your official account', 'moksa-for-line' );

	$flows    = Flow::all();
	$selected = Ajax::query_int( 'flow' );
	// The worked example a new flow starts from. It is the first thing a shop
	// edits, so it is translated like any other visible text -- left in English it
	// would have them rewriting four questions before writing their own.
	$example  = array(
		'steps'            => array(
			array( 'key' => 'name', 'prompt' => __( 'What name should we put this under?', 'moksa-for-line' ), 'type' => 'text' ),
			array( 'key' => 'phone', 'prompt' => __( 'A phone number we can reach you on?', 'moksa-for-line' ), 'type' => 'phone' ),
			array( 'key' => 'date', 'prompt' => __( 'Which date would you like?', 'moksa-for-line' ), 'type' => 'date' ),
			array(
				'key'     => 'service',
				'prompt'  => __( 'Which service?', 'moksa-for-line' ),
				'type'    => 'choice',
				'choices' => array( __( 'Consultation', 'moksa-for-line' ), __( 'Follow-up', 'moksa-for-line' ) ),
			),
		),
		'complete_message' => __( 'Thank you {display_name}, we have your request and will confirm shortly.', 'moksa-for-line' ),
	);
	?>
	<div class="wrap mofoline-wrap">
		<h1><?php esc_html_e( 'Conversation flows', 'moksa-for-line' ); ?></h1>

		<p class="description">
			<?php esc_html_e( 'A flow asks a series of questions and stores the answers. Only one flow runs per person at a time, it expires after 30 minutes of silence, and every question offers a way to cancel.', 'moksa-for-line' ); ?>
		</p>

		<div class="moksa-split moksa-split--wide">
			<div class="moksa-split__main">
				<form class="moksa-panel" data-moksa-flow-form>
					<input type="hidden" name="id" value="0" />

					<p>
						<label for="moksa-flow-name"><?php esc_html_e( 'Name', 'moksa-for-line' ); ?></label>
						<input type="text" id="moksa-flow-name" name="name" class="widefat" required />
					</p>

					<p>
						<label for="moksa-flow-trigger-type"><?php esc_html_e( 'Start when a message', 'moksa-for-line' ); ?></label>
						<select id="moksa-flow-trigger-type" name="trigger_type" class="widefat">
							<option value="keyword"><?php esc_html_e( 'is exactly this', 'moksa-for-line' ); ?></option>
							<option value="contains"><?php esc_html_e( 'contains this', 'moksa-for-line' ); ?></option>
						</select>
					</p>

					<p>
						<label for="moksa-flow-trigger"><?php esc_html_e( 'Trigger text', 'moksa-for-line' ); ?></label>
						<input type="text" id="moksa-flow-trigger" name="trigger_value" class="widefat" />
					</p>

					<div class="moksa-field">
						<label><?php esc_html_e( 'Questions', 'moksa-for-line' ); ?></label>
						<span class="description">
							<?php esc_html_e( 'Asked one at a time, in this order. Each answer is stored under its own key, which is the column you see in submissions.', 'moksa-for-line' ); ?>
						</span>

						<div class="moksa-flow-editor" data-moksa-flow-editor="[data-moksa-flow-definition]"
							data-types="<?php
							echo esc_attr(
								(string) wp_json_encode(
									array(
										'text'   => __( 'Anything they type', 'moksa-for-line' ),
										'choice' => __( 'One of these buttons', 'moksa-for-line' ),
										'number' => __( 'A number', 'moksa-for-line' ),
										'phone'  => __( 'A phone number', 'moksa-for-line' ),
										'email'  => __( 'An email address', 'moksa-for-line' ),
										'date'   => __( 'A date', 'moksa-for-line' ),
									)
								)
							);
							?>">
							<div class="moksa-flow-steps" data-moksa-flow-steps></div>
							<p>
								<button type="button" class="button" data-moksa-add-step>
									<?php esc_html_e( '+ Add a question', 'moksa-for-line' ); ?>
								</button>
							</p>
							<div class="moksa-preview-warnings" data-moksa-flow-warnings></div>
						</div>

						<label for="moksa-flow-definition" class="screen-reader-text"><?php esc_html_e( 'The questions as JSON', 'moksa-for-line' ); ?></label>
						<textarea id="moksa-flow-definition" name="definition" rows="18" class="widefat code moksa-area-json" spellcheck="false" data-moksa-flow-definition hidden><?php echo esc_textarea( (string) wp_json_encode( $example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
						<button type="button" class="button-link" data-moksa-toggle-flow-json><?php esc_html_e( 'Edit the JSON directly', 'moksa-for-line' ); ?></button>
					</div>

					<p>
						<label for="moksa-flow-complete"><?php esc_html_e( 'When they finish, say', 'moksa-for-line' ); ?></label>
						<textarea id="moksa-flow-complete" rows="2" class="widefat" data-moksa-flow-complete></textarea>
						<span class="description"><?php esc_html_e( 'You can use {display_name}, {site_name}, {site_url}, and the field name of any answer -- {name} for a step stored as name.', 'moksa-for-line' ); ?></span>
					</p>

					<p>
						<label for="moksa-flow-email"><?php esc_html_e( 'Email submissions to', 'moksa-for-line' ); ?></label>
						<input type="email" id="moksa-flow-email" name="notify_email" class="widefat" />
					</p>

					<p>
						<label>
							<input type="checkbox" name="is_active" value="1" checked />
							<?php esc_html_e( 'Active', 'moksa-for-line' ); ?>
						</label>
					</p>

					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save flow', 'moksa-for-line' ); ?></button>
						<button type="button" class="button" data-moksa-reset-flow><?php esc_html_e( 'New flow', 'moksa-for-line' ); ?></button>
					</p>

					<div class="moksa-feedback" data-moksa-feedback></div>
				</form>
			</div>

			<div class="moksa-split__side">
				<div class="moksa-panel">
					<h2><?php esc_html_e( 'Preview', 'moksa-for-line' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'The whole conversation, from the trigger to the last answer. Buttons are drawn where LINE puts them, under the message.', 'moksa-for-line' ); ?>
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
					<h2><?php esc_html_e( 'Saved flows', 'moksa-for-line' ); ?></h2>
					<ul class="moksa-list">
						<?php if ( empty( $flows ) ) : ?>
							<li><?php esc_html_e( 'None yet.', 'moksa-for-line' ); ?></li>
						<?php endif; ?>
						<?php foreach ( $flows as $flow ) : ?>
							<li data-flow='<?php echo esc_attr( (string) wp_json_encode( $flow ) ); ?>'>
								<button type="button" class="button-link" data-moksa-load-flow><?php echo esc_html( (string) $flow->name ); ?></button>
								<a href="<?php echo esc_url( add_query_arg( 'flow', (int) $flow->id ) ); ?>"><?php esc_html_e( 'Submissions', 'moksa-for-line' ); ?></a>
								<button type="button" class="button-link delete" data-moksa-delete-flow="<?php echo esc_attr( (string) $flow->id ); ?>"><?php esc_html_e( 'Delete', 'moksa-for-line' ); ?></button>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<?php if ( $selected > 0 ) : ?>
					<div class="moksa-panel">
						<h2><?php esc_html_e( 'Submissions', 'moksa-for-line' ); ?></h2>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'When', 'moksa-for-line' ); ?></th>
									<th><?php esc_html_e( 'Who', 'moksa-for-line' ); ?></th>
									<th><?php esc_html_e( 'Answers', 'moksa-for-line' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php $submissions = Flow::submissions( $selected ); ?>
								<?php if ( empty( $submissions ) ) : ?>
									<tr><td colspan="3"><?php esc_html_e( 'Nothing submitted yet.', 'moksa-for-line' ); ?></td></tr>
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
	<?php
} )();
