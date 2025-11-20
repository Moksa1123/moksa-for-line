<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('檢視 LINE 官方帳號的即時數據與活動概況。', 'moksa-line-login'); ?></p>
        </div>
    </div>

    <?php
    $dashboard = Moksa_Line_Dashboard::get_instance();
    $total_friends = $dashboard->get_total_friends();
    $message_stats = $dashboard->get_message_stats();
    $recent_events = $dashboard->get_recent_events();
    ?>

    <div class="moksa-editor-layout" style="display: block;">
        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 20px;">
            <div class="moksa-card" style="text-align: center; padding: 30px 20px;">
                <h2 style="margin-top: 0; font-size: 16px; color: #64748b;"><?php _e('好友總數', 'moksa-line-login'); ?></h2>
                <div style="font-size: 48px; font-weight: 700; color: #2563eb; line-height: 1.2; margin: 10px 0;">
                    <?php echo number_format($total_friends); ?>
                </div>
                <p style="margin: 0; color: #94a3b8; font-size: 13px;"><?php _e('已連結的 LINE 使用者', 'moksa-line-login'); ?></p>
            </div>
            
            <div class="moksa-card" style="text-align: center; padding: 30px 20px;">
                <h2 style="margin-top: 0; font-size: 16px; color: #64748b;"><?php _e('已發送訊息', 'moksa-line-login'); ?></h2>
                <div style="font-size: 48px; font-weight: 700; color: #06b6d4; line-height: 1.2; margin: 10px 0;">
                    <?php echo number_format(isset($message_stats['total_sent']) ? $message_stats['total_sent'] : 0); ?>
                </div>
                <p style="margin: 0; color: #94a3b8; font-size: 13px;"><?php _e('廣播與推播訊息總計', 'moksa-line-login'); ?></p>
            </div>
        </div>
        
        <!-- Data Tables -->
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
            <div class="moksa-card">
                <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 15px; font-size: 18px;"><?php _e('訊息類型分佈', 'moksa-line-login'); ?></h2>
                <table class="widefat striped" style="border: none; box-shadow: none;">
                    <thead>
                        <tr>
                            <th style="padding-left: 0;"><?php _e('類型', 'moksa-line-login'); ?></th>
                            <th style="text-align: right; padding-right: 0;"><?php _e('數量', 'moksa-line-login'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($message_stats['by_type']) && $message_stats['by_type']) : ?>
                            <?php foreach ($message_stats['by_type'] as $stat) : ?>
                                <tr>
                                    <td style="padding-left: 0;"><?php echo esc_html(ucfirst($stat->message_type)); ?></td>
                                    <td style="text-align: right; padding-right: 0; font-weight: 600;"><?php echo number_format($stat->count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="2" style="padding-left: 0; color: #94a3b8;"><?php _e('尚無數據。', 'moksa-line-login'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="moksa-card">
                <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 15px; font-size: 18px;"><?php _e('近期 Webhook 事件', 'moksa-line-login'); ?></h2>
                <table class="widefat striped" style="border: none; box-shadow: none;">
                    <thead>
                        <tr>
                            <th style="padding-left: 0;"><?php _e('時間', 'moksa-line-login'); ?></th>
                            <th><?php _e('事件', 'moksa-line-login'); ?></th>
                            <th style="text-align: right; padding-right: 0;"><?php _e('來源 ID', 'moksa-line-login'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_events) : ?>
                            <?php foreach ($recent_events as $event) : ?>
                                <tr>
                                    <td style="padding-left: 0; color: #64748b;"><?php echo esc_html($event->created_at); ?></td>
                                    <td><span style="background: #eff6ff; color: #2563eb; padding: 2px 8px; border-radius: 4px; font-size: 12px;"><?php echo esc_html($event->event_type); ?></span></td>
                                    <td style="text-align: right; padding-right: 0; font-family: monospace;"><?php echo esc_html($event->line_user_id); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="3" style="padding-left: 0; color: #94a3b8;"><?php _e('尚無事件。', 'moksa-line-login'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
