<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (isset($_GET['imported'])): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Settings imported successfully.', 'moksa-line-login'); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="moksa-line-tools-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        
        <!-- Export Users -->
        <div class="card">
            <h2><?php _e('Export Users', 'moksa-line-login'); ?></h2>
            <p><?php _e('Export all connected LINE users to a CSV file.', 'moksa-line-login'); ?></p>
            <form method="post">
                <?php wp_nonce_field('moksa_line_tools_action'); ?>
                <input type="hidden" name="moksa_line_action" value="export_users">
                <button type="submit" class="button button-primary">
                    <?php _e('Download CSV', 'moksa-line-login'); ?>
                </button>
            </form>
        </div>
        
        <!-- Export Settings -->
        <div class="card">
            <h2><?php _e('Export Settings', 'moksa-line-login'); ?></h2>
            <p><?php _e('Export plugin settings to a JSON file for backup or migration.', 'moksa-line-login'); ?></p>
            <form method="post">
                <?php wp_nonce_field('moksa_line_tools_action'); ?>
                <input type="hidden" name="moksa_line_action" value="export_settings">
                <button type="submit" class="button">
                    <?php _e('Export Settings', 'moksa-line-login'); ?>
                </button>
            </form>
        </div>
        
        <!-- Import Settings -->
        <div class="card">
            <h2><?php _e('Import Settings', 'moksa-line-login'); ?></h2>
            <p><?php _e('Import settings from a JSON file.', 'moksa-line-login'); ?></p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('moksa_line_tools_action'); ?>
                <input type="hidden" name="moksa_line_action" value="import_settings">
                <p>
                    <input type="file" name="import_file" accept=".json" required>
                </p>
                <button type="submit" class="button button-primary">
                    <?php _e('Import Settings', 'moksa-line-login'); ?>
                </button>
            </form>
        </div>
        
    </div>
</div>
