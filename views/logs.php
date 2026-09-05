<?php
/**
 * Logs and recent webhook events.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Support\Logger;
use Moksa\Line\Webhook\EventQueue;

defined( 'ABSPATH' ) || exit;

global $wpdb;

$log_table = Logger::table();
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
$logs = (array) $wpdb->get_results( "SELECT * FROM {$log_table} ORDER BY id DESC LIMIT 100" );

$events = EventQueue::recent( 50 );
?>
<div class="wrap moksa-line-wrap">
	<h1><?php esc_html_e( 'Logs', 'moksa-line-login' ); ?></h1>

	<h2><?php esc_html_e( 'Recent webhook events', 'moksa-line-login' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Received', 'moksa-line-login' ); ?></th>
				<th><?php esc_html_e( 'Type', 'moksa-line-login' ); ?></th>
				<th><?php esc_html_e( 'From', 'moksa-line-login' ); ?></th>
				<th><?php esc_html_e( 'Status', 'moksa-line-login' ); ?></th>
				<th><?php esc_html_e( 'Detail', 'moksa-line-login' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $events ) ) : ?>
				<tr>
					<td colspan="5">
						<?php esc_html_e( 'Nothing yet. If you have already pressed Verify in the LINE Console and this is still empty, the signature check is rejecting deliveries, which almost always means the Messaging API channel secret is wrong.', 'moksa-line-login' ); ?>
					</td>
				</tr>
			<?php endif; ?>
			<?php foreach ( $events as $event ) : ?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'Y-m-d H:i:s', get_date_from_gmt( (string) $event->received_at ) ) ); ?></td>
					<td><code><?php echo esc_html( (string) $event->event_type ); ?></code></td>
					<td><code><?php echo esc_html( substr( (string) $event->source_id, 0, 12 ) ); ?></code></td>
					<td>
						<span class="moksa-pill moksa-pill--<?php echo esc_attr( 'done' === $event->status ? 'ok' : ( 'failed' === $event->status ? 'bad' : 'warn' ) ); ?>">
							<?php echo esc_html( (string) $event->status ); ?>
						</span>
					</td>
					<td><?php echo esc_html( (string) $event->error ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Plugin log', 'moksa-line-login' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'moksa-line-login' ); ?></th>
				<th><?php esc_html_e( 'Level', 'moksa-line-login' ); ?></th>
				<th><?php esc_html_e( 'Area', 'moksa-line-login' ); ?></th>
				<th><?php esc_html_e( 'Message', 'moksa-line-login' ); ?></th>
				<th><?php esc_html_e( 'Context', 'moksa-line-login' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'Nothing logged.', 'moksa-line-login' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $logs as $log ) : ?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'Y-m-d H:i:s', get_date_from_gmt( (string) $log->created_at ) ) ); ?></td>
					<td>
						<span class="moksa-pill moksa-pill--<?php echo esc_attr( 'error' === $log->level ? 'bad' : ( 'warning' === $log->level ? 'warn' : 'ok' ) ); ?>">
							<?php echo esc_html( (string) $log->level ); ?>
						</span>
					</td>
					<td><?php echo esc_html( (string) $log->channel ); ?></td>
					<td><?php echo esc_html( (string) $log->message ); ?></td>
					<td><code class="moksa-context"><?php echo esc_html( (string) $log->context ); ?></code></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
