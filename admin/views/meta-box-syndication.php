<?php
/**
 * Syndication Meta Box View
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="mpcs-meta-box">
    <!-- Auto-sync Section -->
    <div class="mpcs-meta-section">
        <div class="mpcs-auto-sync-toggle">
            <label>
                <input type="checkbox" 
                       id="mpcs-auto-sync" 
                       name="mpcs_auto_sync" 
                       value="1" 
                       <?php checked($auto_sync, true); ?>>
                <strong><?php _e('Enable Auto-Sync', 'mpcs-hub'); ?></strong>
            </label>
            <p class="description">
                <?php _e('Automatically sync this content when published or updated.', 'mpcs-hub'); ?>
            </p>
        </div>
        
        <div class="mpcs-platform-checkboxes" style="<?php echo $auto_sync ? '' : 'display:none;'; ?>">
            <h4><?php _e('Select Platforms:', 'mpcs-hub'); ?></h4>
            <?php foreach ($platforms as $platform_key => $platform_name) : ?>
                <?php if (isset($enabled_platforms[$platform_key])) : ?>
                    <div class="mpcs-platform-checkbox">
                        <input type="checkbox" 
                               id="mpcs-platform-<?php echo esc_attr($platform_key); ?>"
                               name="mpcs_auto_sync_platforms[]" 
                               value="<?php echo esc_attr($platform_key); ?>"
                               <?php checked(in_array($platform_key, $auto_sync_platforms)); ?>>
                        <label for="mpcs-platform-<?php echo esc_attr($platform_key); ?>">
                            <?php echo esc_html($platform_name); ?>
                        </label>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Manual Sync Section -->
    <div class="mpcs-meta-section">
        <h4><?php _e('Manual Sync', 'mpcs-hub'); ?></h4>
        
        <div class="mpcs-sync-actions">
            <?php if ($post->post_status === 'publish') : ?>
                <button type="button" 
                        class="button button-primary mpcs-sync-button mpcs-sync-content"
                        data-post-id="<?php echo $post->ID; ?>"
                        data-platforms="<?php echo esc_attr(json_encode(array_keys($enabled_platforms))); ?>">
                    <span class="dashicons dashicons-share"></span>
                    <?php _e('Sync Now', 'mpcs-hub'); ?>
                </button>
            <?php else : ?>
                <p class="description">
                    <?php _e('Publish the post to enable manual sync.', 'mpcs-hub'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sync Status Section -->
    <div class="mpcs-meta-section">
        <h4><?php _e('Sync Status', 'mpcs-hub'); ?></h4>
        
        <?php 
        $sync_history = MPCS_Hub_Platform_Manager::get_post_syndication_history($post->ID);
        
        if (empty($sync_history)) :
        ?>
            <p class="description"><?php _e('No syndication history found.', 'mpcs-hub'); ?></p>
        <?php else : ?>
            <div class="mpcs-sync-history">
                <?php foreach ($sync_history as $log) : ?>
                    <div class="mpcs-sync-status <?php echo esc_attr($log->status); ?>" data-post-id="<?php echo $post->ID; ?>">
                        <div class="mpcs-sync-platform">
                            <strong><?php echo esc_html(ucfirst($log->platform)); ?></strong>
                        </div>
                        
                        <div class="mpcs-sync-details">
                            <span class="mpcs-sync-status-text <?php echo esc_attr($log->status); ?>">
                                <?php echo esc_html(ucfirst($log->status)); ?>
                            </span>
                            
                            <span class="mpcs-sync-time">
                                <?php echo human_time_diff(strtotime($log->created_at)); ?> <?php _e('ago', 'mpcs-hub'); ?>
                            </span>
                        </div>
                        
                        <div class="mpcs-sync-actions">
                            <?php if ($log->external_url) : ?>
                                <a href="<?php echo esc_url($log->external_url); ?>" 
                                   target="_blank" 
                                   class="button button-small">
                                    <?php _e('View', 'mpcs-hub'); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($log->status === 'failed') : ?>
                                <button type="button" 
                                        class="button button-small mpcs-retry-sync"
                                        data-log-id="<?php echo $log->id; ?>">
                                    <?php _e('Retry', 'mpcs-hub'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($log->error_message) : ?>
                            <div class="mpcs-error-message">
                                <?php echo esc_html($log->error_message); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Platform-specific Settings -->
    <div class="mpcs-meta-section">
        <h4><?php _e('Platform Settings', 'mpcs-hub'); ?></h4>
        
        <div class="mpcs-platform-settings">
            <?php foreach ($enabled_platforms as $platform_key => $platform_config) : ?>
                <div class="mpcs-platform-setting">
                    <h5><?php echo esc_html($platforms[$platform_key]); ?></h5>
                    
                    <?php
                    $platform_settings = get_post_meta($post->ID, '_mpcs_' . $platform_key . '_settings', true);
                    if (!is_array($platform_settings)) {
                        $platform_settings = [];
                    }
                    ?>
                    
                    <!-- Custom hashtags for this platform -->
                    <div class="mpcs-setting-field">
                        <label for="mpcs-<?php echo esc_attr($platform_key); ?>-hashtags">
                            <?php _e('Custom Hashtags:', 'mpcs-hub'); ?>
                        </label>
                        <input type="text" 
                               id="mpcs-<?php echo esc_attr($platform_key); ?>-hashtags"
                               name="mpcs_platform_settings[<?php echo esc_attr($platform_key); ?>][hashtags]"
                               value="<?php echo esc_attr($platform_settings['hashtags'] ?? ''); ?>"
                               placeholder="<?php _e('hashtag1, hashtag2, hashtag3', 'mpcs-hub'); ?>"
                               class="widefat">
                        <p class="description">
                            <?php _e('Comma-separated hashtags specific to this platform.', 'mpcs-hub'); ?>
                        </p>
                    </div>
                    
                    <!-- Custom excerpt -->
                    <div class="mpcs-setting-field">
                        <label for="mpcs-<?php echo esc_attr($platform_key); ?>-excerpt">
                            <?php _e('Custom Excerpt:', 'mpcs-hub'); ?>
                        </label>
                        <textarea id="mpcs-<?php echo esc_attr($platform_key); ?>-excerpt"
                                  name="mpcs_platform_settings[<?php echo esc_attr($platform_key); ?>][excerpt]"
                                  rows="3"
                                  class="widefat"
                                  placeholder="<?php _e('Custom excerpt for this platform...', 'mpcs-hub'); ?>"><?php echo esc_textarea($platform_settings['excerpt'] ?? ''); ?></textarea>
                        <p class="description">
                            <?php _e('Custom excerpt that will be used instead of the default.', 'mpcs-hub'); ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle platform checkboxes based on auto-sync setting
    $('#mpcs-auto-sync').on('change', function() {
        $('.mpcs-platform-checkboxes').toggle($(this).is(':checked'));
    });
    
    // Sync content button
    $('.mpcs-sync-content').on('click', function() {
        const button = $(this);
        const postId = button.data('post-id');
        const platforms = button.data('platforms');
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> <?php _e('Syncing...', 'mpcs-hub'); ?>');
        
        $.post(ajaxurl, {
            action: 'mpcs_sync_content',
            post_id: postId,
            platforms: platforms,
            nonce: '<?php echo wp_create_nonce('mpcs_hub_nonce'); ?>'
        }).done(function(response) {
            if (response.success) {
                alert('<?php _e('Sync initiated successfully!', 'mpcs-hub'); ?>');
                location.reload();
            } else {
                alert('<?php _e('Sync failed. Please try again.', 'mpcs-hub'); ?>');
            }
        }).always(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-share"></span> <?php _e('Sync Now', 'mpcs-hub'); ?>');
        });
    });
    
    // Retry sync button
    $('.mpcs-retry-sync').on('click', function() {
        const button = $(this);
        const logId = button.data('log-id');
        
        button.prop('disabled', true).text('<?php _e('Retrying...', 'mpcs-hub'); ?>');
        
        $.post(ajaxurl, {
            action: 'mpcs_retry_syndication',
            log_id: logId,
            nonce: '<?php echo wp_create_nonce('mpcs_hub_nonce'); ?>'
        }).done(function(response) {
            if (response.success) {
                alert('<?php _e('Retry initiated successfully!', 'mpcs-hub'); ?>');
                location.reload();
            } else {
                alert('<?php _e('Retry failed. Please try again.', 'mpcs-hub'); ?>');
            }
        }).always(function() {
            button.prop('disabled', false).text('<?php _e('Retry', 'mpcs-hub'); ?>');
        });
    });
});
</script>
