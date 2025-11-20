<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('提供資料匯出、匯入與備份等實用工具。', 'moksa-line-login'); ?></p>
        </div>
    </div>
    
    <?php if (isset($_GET['imported'])): ?>
    <div class="notice notice-success is-dismissible" style="margin-left: 0; margin-bottom: 20px;">
        <p><?php _e('設定匯入成功。', 'moksa-line-login'); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="moksa-editor-layout" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        
        <!-- Export Users -->
        <div class="moksa-card">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 15px; font-size: 18px;"><?php _e('匯出使用者', 'moksa-line-login'); ?></h2>
            <p style="color: #64748b; margin-bottom: 20px;"><?php _e('將所有已連結的 LINE 使用者資料匯出為 CSV 檔案。', 'moksa-line-login'); ?></p>
            <form method="post">
                <?php wp_nonce_field('moksa_line_tools_action'); ?>
                <input type="hidden" name="moksa_line_action" value="export_users">
                <button type="submit" class="button button-primary">
                    <?php _e('下載 CSV', 'moksa-line-login'); ?>
                </button>
            </form>
        </div>
        
        <!-- Export Settings -->
        <div class="moksa-card">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 15px; font-size: 18px;"><?php _e('匯出設定', 'moksa-line-login'); ?></h2>
            <p style="color: #64748b; margin-bottom: 20px;"><?php _e('將外掛設定匯出為 JSON 檔案，以進行備份或遷移。', 'moksa-line-login'); ?></p>
            <form method="post">
                <?php wp_nonce_field('moksa_line_tools_action'); ?>
                <input type="hidden" name="moksa_line_action" value="export_settings">
                <button type="submit" class="button">
                    <?php _e('匯出設定', 'moksa-line-login'); ?>
                </button>
            </form>
        </div>
        
        <!-- Import Settings -->
        <div class="moksa-card">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 15px; font-size: 18px;"><?php _e('匯入設定', 'moksa-line-login'); ?></h2>
            <p style="color: #64748b; margin-bottom: 20px;"><?php _e('從 JSON 檔案還原外掛設定。', 'moksa-line-login'); ?></p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('moksa_line_tools_action'); ?>
                <input type="hidden" name="moksa_line_action" value="import_settings">
                <p>
                    <input type="file" name="import_file" accept=".json" required style="width: 100%;">
                </p>
                <button type="submit" class="button button-primary">
                    <?php _e('匯入設定', 'moksa-line-login'); ?>
                </button>
            </form>
        </div>
        
    </div>
</div>
