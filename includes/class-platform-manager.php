<?php
/**
 * Platform Manager class - handles all platform integrations
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Platform_Manager {
    
    /**
     * Available platforms with their configurations
     */
    private static $platforms = [
        'facebook' => [
            'name' => 'Facebook',
            'description' => 'Share content on Facebook',
            'character_limit' => 63206,
            'supports_images' => true,
            'supports_videos' => true,
            'class' => 'MPCS_Hub_Platform_Facebook'
        ],
        'twitter' => [
            'name' => 'Twitter/X',
            'description' => 'Share content on Twitter',
            'character_limit' => 280,
            'supports_images' => true,
            'supports_videos' => true,
            'class' => 'MPCS_Hub_Platform_Twitter'
        ],
        'linkedin' => [
            'name' => 'LinkedIn',
            'description' => 'Share professional content on LinkedIn',
            'character_limit' => 3000,
            'supports_images' => true,
            'supports_videos' => true,
            'class' => 'MPCS_Hub_Platform_Linkedin'
        ],
        'instagram' => [
            'name' => 'Instagram',
            'description' => 'Share visual content on Instagram',
            'character_limit' => 2200,
            'supports_images' => true,
            'supports_videos' => true,
            'class' => 'MPCS_Hub_Platform_Instagram'
        ],
        'medium' => [
            'name' => 'Medium',
            'description' => 'Publish long-form articles on Medium',
            'character_limit' => 0,
            'supports_images' => true,
            'supports_videos' => false,
            'class' => 'MPCS_Hub_Platform_Medium'
        ],
        'devto' => [
            'name' => 'Dev.to',
            'description' => 'Share developer-focused content on Dev.to',
            'character_limit' => 0,
            'supports_images' => true,
            'supports_videos' => false,
            'class' => 'MPCS_Hub_Platform_Devto'
        ],
        'newsletter' => [
            'name' => 'Email Newsletter',
            'description' => 'Send content via email newsletter (Mailchimp)',
            'character_limit' => 0,
            'supports_images' => true,
            'supports_videos' => false,
            'class' => 'MPCS_Hub_Platform_Newsletter'
        ]
    ];
    
    /**
     * Get all available platforms
     */
    public static function get_platforms() {
        return apply_filters('mpcs_hub_platforms', self::$platforms);
    }
    
    /**
     * Get enabled platforms for a user
     */
    public static function get_enabled_platforms($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $enabled = get_user_meta($user_id, 'mpcs_enabled_platforms', true);
        if (!is_array($enabled)) {
            $enabled = [];
        }
        
        return $enabled;
    }
    
    /**
     * Get platform instance
     */
    public static function get_platform_instance($platform) {
        $platforms = self::get_platforms();
        
        if (!isset($platforms[$platform])) {
            return new WP_Error('invalid_platform', __('Invalid platform specified', 'mpcs-hub'));
        }
        
        $platform_config = $platforms[$platform];
        $class_name = $platform_config['class'];
        
        // Include the platform class file
        $file_path = MPCS_HUB_PATH . 'includes/platforms/class-' . str_replace('_', '-', strtolower(str_replace('MPCS_Hub_Platform_', '', $class_name))) . '.php';
        
        if (file_exists($file_path)) {
            require_once $file_path;
        }
        
        if (!class_exists($class_name)) {
            return new WP_Error('class_not_found', sprintf(__('Platform class %s not found', 'mpcs-hub'), $class_name));
        }
        
        return new $class_name();
    }
    
    /**
     * Check if platform is configured and enabled
     */
    public static function is_platform_enabled($platform, $user_id = null) {
        $enabled_platforms = self::get_enabled_platforms($user_id);
        
        if (!in_array($platform, array_keys($enabled_platforms))) {
            return false;
        }
        
        $config = MPCS_Hub_Database::get_platform_config($platform);
        return $config && $config->is_active;
    }
    
    /**
     * Sync content to multiple platforms
     */
    public static function sync_content($post_id, $platforms = []) {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_Error('invalid_post', __('Invalid or unpublished post', 'mpcs-hub'));
        }
        
        if (empty($platforms)) {
            $platforms = self::get_enabled_platforms();
        }
        
        if (!is_array($platforms)) {
            $platforms = explode(',', $platforms);
        }
        
        $results = [];
        
        foreach ($platforms as $platform) {
            if (!self::is_platform_enabled($platform)) {
                $results[$platform] = new WP_Error(
                    'platform_disabled',
                    sprintf(__('Platform %s is not enabled', 'mpcs-hub'), $platform)
                );
                continue;
            }
            
            // Queue the sync operation
            $queue_id = self::queue_sync_operation($post_id, $platform);
            
            if ($queue_id) {
                $results[$platform] = [
                    'status' => 'queued',
                    'queue_id' => $queue_id,
                    'message' => __('Sync operation queued', 'mpcs-hub')
                ];
                
                // Log the operation
                MPCS_Hub_Database::insert_log([
                    'post_id' => $post_id,
                    'platform' => $platform,
                    'status' => 'queued'
                ]);
            } else {
                $results[$platform] = new WP_Error(
                    'queue_failed',
                    __('Failed to queue sync operation', 'mpcs-hub')
                );
            }
        }
        
        // Process queue immediately for real-time sync (optional)
        if (get_option('mpcs_hub_realtime_sync', false)) {
            self::process_sync_queue();
        }
        
        return $results;
    }
    
    /**
     * Queue sync operation for background processing
     */
    private static function queue_sync_operation($post_id, $platform, $action = 'publish') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_sync_queue';
        
        // Prepare content data
        $post = get_post($post_id);
        $content_data = self::prepare_content_for_platform($post, $platform);
        
        $data = [
            'post_id' => $post_id,
            'platform' => $platform,
            'action' => $action,
            'priority' => self::get_platform_priority($platform),
            'payload' => json_encode($content_data),
            'status' => 'pending',
            'scheduled_at' => current_time('mysql'),
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table, $data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Prepare content for specific platform
     */
    private static function prepare_content_for_platform($post, $platform) {
        $config = MPCS_Hub_Database::get_platform_config($platform);
        $content_formatter = new MPCS_Hub_Content_Formatter($platform, $config);
        
        return $content_formatter->format_post($post);
    }
    
    /**
     * Get platform priority for queue processing
     */
    private static function get_platform_priority($platform) {
        $priorities = [
            'twitter' => 1,      // Highest priority - real-time
            'facebook' => 2,
            'linkedin' => 3,
            'instagram' => 4,
            'medium' => 5,
            'dev_to' => 6,
            'hashnode' => 7,
            'rss' => 8,
            'mailchimp' => 9,
            'slack' => 10,       // Lowest priority
        ];
        
        return $priorities[$platform] ?? 5;
    }
    
    /**
     * Process sync queue
     */
    public static function process_sync_queue($limit = 10) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_sync_queue';
        
        // Get pending items ordered by priority and creation time
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE status = 'pending' 
             AND (scheduled_at IS NULL OR scheduled_at <= %s)
             AND attempts < max_attempts
             ORDER BY priority ASC, created_at ASC 
             LIMIT %d",
            current_time('mysql'),
            $limit
        ));
        
        foreach ($items as $item) {
            self::process_queue_item($item);
        }
    }
    
    /**
     * Process individual queue item
     */
    private static function process_queue_item($item) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_sync_queue';
        
        // Update status to processing
        $wpdb->update($table, [
            'status' => 'processing',
            'started_at' => current_time('mysql'),
            'attempts' => $item->attempts + 1
        ], ['id' => $item->id]);
        
        try {
            $payload = json_decode($item->payload, true);
            $result = self::sync_to_platform($item->post_id, $item->platform, $payload, $item->action);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Success
            $wpdb->update($table, [
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ], ['id' => $item->id]);
            
            // Update syndication log
            MPCS_Hub_Database::update_log($item->id, [
                'status' => 'success',
                'external_id' => $result['external_id'] ?? null,
                'external_url' => $result['external_url'] ?? null,
                'response_data' => json_encode($result),
                'synced_at' => current_time('mysql')
            ]);
            
        } catch (Exception $e) {
            // Handle failure
            $status = ($item->attempts >= $item->max_attempts) ? 'failed' : 'pending';
            
            $wpdb->update($table, [
                'status' => $status,
                'completed_at' => ($status === 'failed') ? current_time('mysql') : null
            ], ['id' => $item->id]);
            
            // Update syndication log
            MPCS_Hub_Database::update_log($item->id, [
                'status' => $status,
                'error_message' => $e->getMessage(),
                'attempts' => $item->attempts + 1
            ]);
            
            // Log error
            error_log(sprintf(
                'MPCS Hub: Failed to sync post %d to %s. Error: %s',
                $item->post_id,
                $item->platform,
                $e->getMessage()
            ));
        }
    }
    
    /**
     * Sync content to specific platform
     */
    private static function sync_to_platform($post_id, $platform, $content_data, $action = 'publish') {
        // Get platform instance
        $handler = self::get_platform_instance($platform);
        
        if (is_wp_error($handler)) {
            return $handler;
        }
        
        // Execute the requested action
        switch ($action) {
            case 'publish':
                return $handler->publish($content_data);
            case 'update':
                return $handler->update($content_data);
            case 'delete':
                return $handler->delete($content_data);
            default:
                return new WP_Error('invalid_action', __('Invalid sync action', 'mpcs-hub'));
        }
    }
    
    /**
     * Auto-sync content based on post settings
     */
    public static function auto_sync_content($post_id) {
        $auto_sync_platforms = get_post_meta($post_id, '_mpcs_auto_sync_platforms', true);
        
        if (!is_array($auto_sync_platforms) || empty($auto_sync_platforms)) {
            return;
        }
        
        return self::sync_content($post_id, $auto_sync_platforms);
    }
    
    /**
     * Save post syndication settings
     */
    public static function save_post_settings($post_id) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save auto-sync settings
        if (isset($_POST['mpcs_auto_sync'])) {
            update_post_meta($post_id, '_mpcs_auto_sync', (bool) $_POST['mpcs_auto_sync']);
        }
        
        if (isset($_POST['mpcs_auto_sync_platforms']) && is_array($_POST['mpcs_auto_sync_platforms'])) {
            update_post_meta($post_id, '_mpcs_auto_sync_platforms', $_POST['mpcs_auto_sync_platforms']);
        }
        
        // Save platform-specific settings
        if (isset($_POST['mpcs_platform_settings']) && is_array($_POST['mpcs_platform_settings'])) {
            foreach ($_POST['mpcs_platform_settings'] as $platform => $settings) {
                update_post_meta($post_id, '_mpcs_' . $platform . '_settings', $settings);
            }
        }
    }
    
    /**
     * Get syndication history for a post
     */
    public static function get_post_syndication_history($post_id) {
        return MPCS_Hub_Database::get_logs(['post_id' => $post_id]);
    }
    
    /**
     * Retry failed syndication
     */
    public static function retry_syndication($log_id) {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'mpcs_syndication_logs';
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $logs_table WHERE id = %d",
            $log_id
        ));
        
        if (!$log) {
            return new WP_Error('log_not_found', __('Syndication log not found', 'mpcs-hub'));
        }
        
        // Reset attempts and queue again
        $queue_id = self::queue_sync_operation($log->post_id, $log->platform);
        
        if ($queue_id) {
            MPCS_Hub_Database::update_log($log_id, [
                'status' => 'retrying',
                'attempts' => 0
            ]);
            
            return ['status' => 'queued', 'queue_id' => $queue_id];
        }
        
        return new WP_Error('retry_failed', __('Failed to retry syndication', 'mpcs-hub'));
    }
    
    /**
     * Get platform statistics
     */
    public static function get_platform_stats($platform = null, $days = 30) {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'mpcs_syndication_logs';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $where_clause = "created_at >= %s";
        $params = [$start_date];
        
        if ($platform) {
            $where_clause .= " AND platform = %s";
            $params[] = $platform;
        }
        
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                platform,
                COUNT(*) as total_syncs,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_syncs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_syncs,
                AVG(attempts) as avg_attempts
             FROM $logs_table 
             WHERE $where_clause 
             GROUP BY platform
             ORDER BY total_syncs DESC",
            $params
        ));
        
        return $stats;
    }
    
    /**
     * Clean up old logs and queue items
     */
    public static function cleanup_old_data($days = 90) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean up old syndication logs
        $logs_table = $wpdb->prefix . 'mpcs_syndication_logs';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $logs_table WHERE created_at < %s AND status IN ('success', 'failed')",
            $cutoff_date
        ));
        
        // Clean up completed queue items
        $queue_table = $wpdb->prefix . 'mpcs_sync_queue';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $queue_table WHERE created_at < %s AND status IN ('completed', 'failed')",
            $cutoff_date
        ));
        
        // Clean up old analytics data (keep 1 year)
        $analytics_cutoff = date('Y-m-d', strtotime('-1 year'));
        $analytics_table = $wpdb->prefix . 'mpcs_analytics';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $analytics_table WHERE date_recorded < %s",
            $analytics_cutoff
        ));
    }
}
