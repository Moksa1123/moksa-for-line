<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php
    $dashboard = Moksa_Line_Dashboard::get_instance();
    $total_friends = $dashboard->get_total_friends();
    $message_stats = $dashboard->get_message_stats();
    $recent_events = $dashboard->get_recent_events();
    ?>
    
    <div class="moksa-line-dashboard-widgets" style="display: flex; gap: 20px; margin-bottom: 20px;">
        
        <div class="card" style="flex: 1; text-align: center; padding: 20px;">
            <h2 style="margin-top: 0;"><?php _e('Total Friends', 'moksa-line-login'); ?></h2>
            <div style="font-size: 48px; font-weight: bold; color: #2563eb;">
                <?php echo number_format($total_friends); ?>
            </div>
            <p><?php _e('Linked Users', 'moksa-line-login'); ?></p>
        </div>
        
        <div class="card" style="flex: 1; text-align: center; padding: 20px;">
            <h2 style="margin-top: 0;"><?php _e('Messages Sent', 'moksa-line-login'); ?></h2>
            <div style="font-size: 48px; font-weight: bold; color: #3b82f6;">
                <?php echo number_format($message_stats['total_sent']); ?>
            </div>
            <p><?php _e('Total Broadcasts/Push', 'moksa-line-login'); ?></p>
        </div>
        
    </div>
    
    <div class="moksa-line-flex-container" style="display: flex; gap: 20px;">
        
        <div class="card" style="flex: 1;">
            <h2><?php _e('Message Types', 'moksa-line-login'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Type', 'moksa-line-login'); ?></th>
                        <th><?php _e('Count', 'moksa-line-login'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($message_stats['by_type']) : ?>
                        <?php foreach ($message_stats['by_type'] as $stat) : ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst($stat->message_type)); ?></td>
                                <td><?php echo number_format($stat->count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="2"><?php _e('No data yet.', 'moksa-line-login'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card" style="flex: 2;">
            <h2><?php _e('Recent Webhook Events', 'moksa-line-login'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'moksa-line-login'); ?></th>
                        <th><?php _e('Event', 'moksa-line-login'); ?></th>
                        <th><?php _e('Source', 'moksa-line-login'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_events) : ?>
                        <?php foreach ($recent_events as $event) : ?>
                            <tr>
                                <td><?php echo esc_html($event->created_at); ?></td>
                                <td><?php echo esc_html($event->event_type); ?></td>
                                <td><?php echo esc_html($event->line_user_id); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="3"><?php _e('No events yet.', 'moksa-line-login'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    </div>
</div>
