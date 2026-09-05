<?php
/**
 * Conversation flows.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Bot\Flow;

defined( 'ABSPATH' ) || exit;

$flows    = Flow::all();
$selected = isset( $_GET['flow'] ) ? (int) $_GET['flow'] : 0;
$example  = array(
	'steps'            => array(
		array( 'key' => 'name', 'prompt' => 'What name should we put this under?', 'type' => 'text' ),
		array( 'key' => 'phone', 'prompt' => 'A phone number we can reach you on?', 'type' => 'phone' ),
		array( 'key' => 'date', 'prompt' => 'Which date would you like?', 'type' => 'date' ),
		array( 'key' => 'service', 'prompt' => 'Which service?', 'type' => 'choice', 'choices' => array( 'Consultation', 'Follow-up' ) ),
	),
	'complete_message' => 'Thank you {display_name}, we have your request and will confirm shortly.',
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

				<p>
					<label for="moksa-flow-definition"><?php esc_html_e( 'Steps', 'moksa-line' ); ?></label>
					<textarea id="moksa-flow-definition" name="definition" rows="18" class="widefat code" spellcheck="false"><?php echo esc_textarea( (string) wp_json_encode( $example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
					<span class="description">
						<?php esc_html_e( 'Each step needs a prompt. Types: text, number, phone, email, date, choice. A choice step also needs a choices array, which is rendered as quick reply buttons.', 'moksa-line' ); ?>
					</span>
				</p>

				<p>
					<label for="moksa-flow-email"><?php esc_html_e( 'Email submissions to', 'moksa-line' ); ?></label>
					<input type="email" id="moksa-flow-email" name="notify_email" class="regular-text" />
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
