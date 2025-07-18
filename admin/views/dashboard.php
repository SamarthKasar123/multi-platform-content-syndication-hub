<?php
/**
 * Main Dashboard View
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get recent activity
$recent_logs = MPCS_Hub_Database::get_logs(['limit' => 20]);
$platform_stats = MPCS_Hub_Platform_Manager::get_platform_stats();
$enabled_platforms = MPCS_Hub_Platform_Manager::get_enabled_platforms();
?>

<div class="wrap">
    <h1><?php _e('Multi-Platform Syndication Hub', 'mpcs-hub'); ?></h1>
    
    <div class="mpcs-dashboard">
        <!-- Statistics Cards -->
        <div class="mpcs-stats-grid">
            <div class="mpcs-stat-card">
                <div class="mpcs-stat-number"><?php echo count($enabled_platforms); ?></div>
                <div class="mpcs-stat-label"><?php _e('Enabled Platforms', 'mpcs-hub'); ?></div>
            </div>
            
            <div class="mpcs-stat-card">
                <div class="mpcs-stat-number"><?php echo count($recent_logs); ?></div>
                <div class="mpcs-stat-label"><?php _e('Recent Syncs', 'mpcs-hub'); ?></div>
            </div>
            
            <div class="mpcs-stat-card">
                <div class="mpcs-stat-number">
                    <?php 
                    $success_count = count(array_filter($recent_logs, function($log) {
                        return $log->status === 'success';
                    }));
                    echo $success_count;
                    ?>
                </div>
                <div class="mpcs-stat-label"><?php _e('Successful Syncs', 'mpcs-hub'); ?></div>
            </div>
            
            <div class="mpcs-stat-card">
                <div class="mpcs-stat-number">
                    <?php 
                    $failed_count = count(array_filter($recent_logs, function($log) {
                        return $log->status === 'failed';
                    }));
                    echo $failed_count;
                    ?>
                </div>
                <div class="mpcs-stat-label"><?php _e('Failed Syncs', 'mpcs-hub'); ?></div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="mpcs-quick-actions">
            <h2><?php _e('Quick Actions', 'mpcs-hub'); ?></h2>
            <div class="mpcs-action-buttons">
                <a href="<?php echo admin_url('admin.php?page=mpcs-hub-platforms'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Manage Platforms', 'mpcs-hub'); ?>
                </a>
                
                <button id="mpcs-sync-latest" class="button">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Sync Latest Posts', 'mpcs-hub'); ?>
                </button>
                
                <a href="<?php echo admin_url('admin.php?page=mpcs-hub-analytics'); ?>" class="button">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php _e('View Analytics', 'mpcs-hub'); ?>
                </a>
                
                <button id="mpcs-test-connections" class="button">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php _e('Test Connections', 'mpcs-hub'); ?>
                </button>
            </div>
        </div>
        
        <!-- Platform Overview -->
        <div class="mpcs-platform-overview">
            <h2><?php _e('Platform Overview', 'mpcs-hub'); ?></h2>
            
            <?php if (empty($platform_stats)) : ?>
                <div class="mpcs-empty-state">
                    <div class="mpcs-empty-icon">
                        <span class="dashicons dashicons-share"></span>
                    </div>
                    <h3><?php _e('No syndication activity yet', 'mpcs-hub'); ?></h3>
                    <p><?php _e('Start by configuring your platforms and publishing content.', 'mpcs-hub'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=mpcs-hub-platforms'); ?>" class="button button-primary">
                        <?php _e('Configure Platforms', 'mpcs-hub'); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="mpcs-platform-stats">
                    <?php foreach ($platform_stats as $stat) : ?>
                        <div class="mpcs-platform-stat">
                            <div class="mpcs-platform-header">
                                <h4><?php echo esc_html(ucfirst($stat->platform)); ?></h4>
                                <span class="mpcs-success-rate">
                                    <?php 
                                    $success_rate = $stat->total_syncs > 0 ? 
                                        round(($stat->successful_syncs / $stat->total_syncs) * 100) : 0;
                                    echo $success_rate . '%';
                                    ?>
                                </span>
                            </div>
                            
                            <div class="mpcs-platform-metrics">
                                <div class="mpcs-metric">
                                    <span class="mpcs-metric-value"><?php echo $stat->total_syncs; ?></span>
                                    <span class="mpcs-metric-label"><?php _e('Total', 'mpcs-hub'); ?></span>
                                </div>
                                
                                <div class="mpcs-metric success">
                                    <span class="mpcs-metric-value"><?php echo $stat->successful_syncs; ?></span>
                                    <span class="mpcs-metric-label"><?php _e('Success', 'mpcs-hub'); ?></span>
                                </div>
                                
                                <div class="mpcs-metric failed">
                                    <span class="mpcs-metric-value"><?php echo $stat->failed_syncs; ?></span>
                                    <span class="mpcs-metric-label"><?php _e('Failed', 'mpcs-hub'); ?></span>
                                </div>
                            </div>
                            
                            <div class="mpcs-progress-bar">
                                <div class="mpcs-progress-fill" style="width: <?php echo $success_rate; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Activity -->
        <div class="mpcs-recent-activity">
            <h2><?php _e('Recent Activity', 'mpcs-hub'); ?></h2>
            
            <?php if (empty($recent_logs)) : ?>
                <p><?php _e('No recent syndication activity.', 'mpcs-hub'); ?></p>
            <?php else : ?>
                <div class="mpcs-activity-list">
                    <?php foreach (array_slice($recent_logs, 0, 10) as $log) : ?>
                        <?php 
                        $post = get_post($log->post_id);
                        $status_class = 'mpcs-status-' . $log->status;
                        ?>
                        <div class="mpcs-activity-item <?php echo $status_class; ?>">
                            <div class="mpcs-activity-icon">
                                <span class="dashicons dashicons-<?php echo $this->get_status_icon($log->status); ?>"></span>
                            </div>
                            
                            <div class="mpcs-activity-content">
                                <div class="mpcs-activity-title">
                                    <?php if ($post) : ?>
                                        <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                            <?php echo esc_html($post->post_title); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php _e('Deleted Post', 'mpcs-hub'); ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mpcs-activity-meta">
                                    <span class="mpcs-platform"><?php echo esc_html(ucfirst($log->platform)); ?></span>
                                    <span class="mpcs-status"><?php echo esc_html(ucfirst($log->status)); ?></span>
                                    <span class="mpcs-time"><?php echo human_time_diff(strtotime($log->created_at)); ?> <?php _e('ago', 'mpcs-hub'); ?></span>
                                </div>
                                
                                <?php if ($log->error_message) : ?>
                                    <div class="mpcs-error-message">
                                        <?php echo esc_html($log->error_message); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mpcs-activity-actions">
                                <?php if ($log->status === 'failed') : ?>
                                    <button class="button button-small mpcs-retry-sync" data-log-id="<?php echo $log->id; ?>">
                                        <?php _e('Retry', 'mpcs-hub'); ?>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($log->external_url) : ?>
                                    <a href="<?php echo esc_url($log->external_url); ?>" class="button button-small" target="_blank">
                                        <?php _e('View', 'mpcs-hub'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mpcs-view-all">
                    <a href="<?php echo admin_url('admin.php?page=mpcs-hub&tab=logs'); ?>" class="button">
                        <?php _e('View All Activity', 'mpcs-hub'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Sync latest posts
    $('#mpcs-sync-latest').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('<?php _e('Syncing...', 'mpcs-hub'); ?>');
        
        $.post(ajaxurl, {
            action: 'mpcs_sync_latest_posts',
            nonce: '<?php echo wp_create_nonce('mpcs_hub_nonce'); ?>'
        }).done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('<?php _e('Sync failed. Please try again.', 'mpcs-hub'); ?>');
            }
        }).always(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> <?php _e('Sync Latest Posts', 'mpcs-hub'); ?>');
        });
    });
    
    // Test connections
    $('#mpcs-test-connections').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('<?php _e('Testing...', 'mpcs-hub'); ?>');
        
        $.post(ajaxurl, {
            action: 'mpcs_test_all_connections',
            nonce: '<?php echo wp_create_nonce('mpcs_hub_nonce'); ?>'
        }).done(function(response) {
            if (response.success) {
                // Show results
                alert(response.data.message);
            } else {
                alert('<?php _e('Connection test failed.', 'mpcs-hub'); ?>');
            }
        }).always(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools"></span> <?php _e('Test Connections', 'mpcs-hub'); ?>');
        });
    });
    
    // Retry sync
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

<?php
// Helper method for status icons
function get_status_icon($status) {
    switch ($status) {
        case 'success':
            return 'yes-alt';
        case 'failed':
            return 'dismiss';
        case 'pending':
            return 'clock';
        case 'processing':
            return 'update';
        default:
            return 'marker';
    }
}
?>
