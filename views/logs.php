<?php
/**
 * Logs and recent webhook events.
 *
 * The log is the first place anyone looks when LINE misbehaves, so it has to
 * answer "what went wrong" without scrolling. Unfiltered, the errors that
 * matter sit buried among debug lines that do not, which is why the level
 * filter starts at warnings and above rather than at everything.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Admin\Ajax;
use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;
use Moksa\Line\Webhook\EventQueue;

defined( 'ABSPATH' ) || exit;

( static function (): void {
	$levels = array(
		'problems' => __( 'Warnings and errors', 'moksa-line' ),
		'error'    => __( 'Errors only', 'moksa-line' ),
		'warning'  => __( 'Warnings only', 'moksa-line' ),
		'info'     => __( 'Info only', 'moksa-line' ),
		'debug'    => __( 'Debug only', 'moksa-line' ),
		'all'      => __( 'Everything', 'moksa-line' ),
	);

	// The word shown on each row. The raw column value was printed instead, so a
	// screen otherwise in the site's language listed "error" and "warning".
	$level_labels = array(
		'error'   => __( 'Error', 'moksa-line' ),
		'warning' => __( 'Warning', 'moksa-line' ),
		'info'    => __( 'Info', 'moksa-line' ),
		'debug'   => __( 'Debug', 'moksa-line' ),
	);

	$channels = Logger::channels();
	$level    = Ajax::query_key( 'level', 'problems' );
	$level    = isset( $levels[ $level ] ) ? $level : 'problems';
	$channel  = Ajax::query_key( 'channel' );
	$channel  = in_array( $channel, $channels, true ) ? $channel : '';
	$per_page = 100;

	$grand_total = Logger::count();
	$page        = Logger::paginate(
		array(
			'level'    => $level,
			'channel'  => $channel,
			'page'     => Ajax::query_page(),
			'per_page' => $per_page,
		)
	);

	$logs   = $page['rows'];
	$total  = $page['total'];
	$pages  = $page['pages'];
	$paged  = $page['page'];

	$events    = EventQueue::recent( 50 );
	$retention = max( 1, (int) Options::get( 'log_retention_days' ) );
	?>
	<div class="wrap moksa-line-wrap">
		<h1><?php esc_html_e( 'Logs', 'moksa-line' ); ?></h1>

		<h2><?php esc_html_e( 'Recent webhook events', 'moksa-line' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Received', 'moksa-line' ); ?></th>
					<th><?php esc_html_e( 'Type', 'moksa-line' ); ?></th>
					<th><?php esc_html_e( 'From', 'moksa-line' ); ?></th>
					<th><?php esc_html_e( 'Status', 'moksa-line' ); ?></th>
					<th><?php esc_html_e( 'Detail', 'moksa-line' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $events ) ) : ?>
					<tr>
						<td colspan="5">
							<?php esc_html_e( 'Nothing yet. If you have already pressed Verify in the LINE Console and this is still empty, the signature check is rejecting deliveries, which almost always means the Messaging API channel secret is wrong.', 'moksa-line' ); ?>
						</td>
					</tr>
				<?php endif; ?>
				<?php foreach ( $events as $event ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'Y-m-d H:i:s', get_date_from_gmt( (string) $event->received_at ) ) ); ?></td>
						<td><code><?php echo esc_html( (string) $event->event_type ); ?></code></td>
						<td>
							<?php // The whole id, not a prefix: it is only useful if it can be pasted into a broadcast test or a Flex send. ?>
							<button type="button" class="moksa-copyable" data-moksa-copy="<?php echo esc_attr( (string) $event->source_id ); ?>">
								<code><?php echo esc_html( (string) $event->source_id ); ?></code>
							</button>
						</td>
						<td>
							<?php
							// The raw column value was printed straight out, so the
							// one word describing each row stayed in English on a
							// site running entirely in Chinese.
							$labels = array(
								'done'    => __( 'Handled', 'moksa-line' ),
								'failed'  => __( 'Failed', 'moksa-line' ),
								'pending' => __( 'Waiting', 'moksa-line' ),
							);
							$status = (string) $event->status;
							?>
							<span class="moksa-pill moksa-pill--<?php echo esc_attr( 'done' === $status ? 'ok' : ( 'failed' === $status ? 'bad' : 'warn' ) ); ?>">
								<?php echo esc_html( $labels[ $status ] ?? $status ); ?>
							</span>
						</td>
						<td><?php echo esc_html( \Moksa\Line\Webhook\EventQueue::summary( $event ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Plugin log', 'moksa-line' ); ?></h2>

		<form method="get" class="moksa-filters">
			<input type="hidden" name="page" value="moksa-line-logs" />

			<label for="moksa-log-level" class="screen-reader-text"><?php esc_html_e( 'Show which entries', 'moksa-line' ); ?></label>
			<select id="moksa-log-level" name="level">
				<?php foreach ( $levels as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $level, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="moksa-log-channel" class="screen-reader-text"><?php esc_html_e( 'Which area of the plugin', 'moksa-line' ); ?></label>
			<select id="moksa-log-channel" name="channel">
				<option value=""><?php esc_html_e( 'All areas', 'moksa-line' ); ?></option>
				<?php foreach ( $channels as $name ) : ?>
					<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $channel, $name ); ?>><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'moksa-line' ); ?></button>

			<span class="moksa-filters__count">
				<?php
				printf(
					/* translators: 1: rows matching the filter, 2: rows kept in total. */
					esc_html__( '%1$s of %2$s entries', 'moksa-line' ),
					esc_html( number_format_i18n( $total ) ),
					esc_html( number_format_i18n( $grand_total ) )
				);
				?>
			</span>

			<?php if ( $grand_total > 0 ) : ?>
				<button type="button" class="button moksa-button-danger" data-moksa-clear-logs>
					<?php esc_html_e( 'Clear the log', 'moksa-line' ); ?>
				</button>
			<?php endif; ?>
		</form>

		<p class="description">
			<?php
			printf(
				/* translators: %s: number of days. */
				esc_html__( 'Entries are deleted automatically after %s days, which you can change under Settings > Advanced.', 'moksa-line' ),
				esc_html( number_format_i18n( $retention ) )
			);
			?>
		</p>

		<table class="widefat striped moksa-log-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'moksa-line' ); ?></th>
					<th><?php esc_html_e( 'Level', 'moksa-line' ); ?></th>
					<th><?php esc_html_e( 'Area', 'moksa-line' ); ?></th>
					<th><?php esc_html_e( 'Message', 'moksa-line' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr>
						<td colspan="4">
							<?php if ( $grand_total > 0 ) : ?>
								<?php esc_html_e( 'Nothing matches this filter. Set it to Everything to see the rest.', 'moksa-line' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Nothing logged.', 'moksa-line' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
				<?php foreach ( $logs as $log ) : ?>
					<?php
					$context = trim( (string) $log->context );
					$decoded = '' !== $context ? json_decode( $context, true ) : null;
					$pretty  = is_array( $decoded )
						? (string) wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
						: $context;
					?>
					<tr>
						<td class="moksa-log-time"><?php echo esc_html( mysql2date( 'Y-m-d H:i:s', get_date_from_gmt( (string) $log->created_at ) ) ); ?></td>
						<td>
							<span class="moksa-pill moksa-pill--<?php echo esc_attr( 'error' === $log->level ? 'bad' : ( 'warning' === $log->level ? 'warn' : 'ok' ) ); ?>">
								<?php echo esc_html( $level_labels[ (string) $log->level ] ?? (string) $log->level ); ?>
							</span>
						</td>
						<td><?php echo esc_html( (string) $log->channel ); ?></td>
						<td>
							<?php echo esc_html( (string) $log->message ); ?>
							<?php if ( '' !== $context && '[]' !== $context && '{}' !== $context ) : ?>
								<?php // Folded away: context is the widest and least readable column, and it is only wanted once a particular line is already under suspicion. ?>
								<details class="moksa-log-context">
									<summary><?php esc_html_e( 'Context', 'moksa-line' ); ?></summary>
									<pre><?php echo esc_html( $pretty ); ?></pre>
								</details>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg(
								array(
									'page'    => 'moksa-line-logs',
									'level'   => $level,
									'channel' => $channel,
									'paged'   => '%#%',
								),
								admin_url( 'admin.php' )
							),
							'format'    => '',
							'current'   => $paged,
							'total'     => $pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>
	</div>
	<?php
} )();
