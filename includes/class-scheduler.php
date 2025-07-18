<?php
/**
 * Scheduler class for managing timed syndication
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Scheduler {
    
    /**
     * Initialize scheduler
     */
    public static function init() {
        // Register cron hooks
        add_action('mpcs_hub_process_queue', [__CLASS__, 'process_scheduled_queue']);
        add_action('mpcs_hub_cleanup', [__CLASS__, 'cleanup_old_data']);
        add_action('mpcs_hub_fetch_analytics', [__CLASS__, 'fetch_platform_analytics']);
        
        // Schedule events if not already scheduled
        if (!wp_next_scheduled('mpcs_hub_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'mpcs_hub_process_queue');
        }
        
        if (!wp_next_scheduled('mpcs_hub_cleanup')) {
            wp_schedule_event(time(), 'daily', 'mpcs_hub_cleanup');
        }
        
        if (!wp_next_scheduled('mpcs_hub_fetch_analytics')) {
            wp_schedule_event(time(), 'hourly', 'mpcs_hub_fetch_analytics');
        }
    }
    
    /**
     * Process scheduled queue items
     */
    public static function process_scheduled_queue() {
        MPCS_Hub_Platform_Manager::process_sync_queue(20);
    }
    
    /**
     * Clean up old data
     */
    public static function cleanup_old_data() {
        MPCS_Hub_Platform_Manager::cleanup_old_data();
    }
    
    /**
     * Fetch analytics from platforms
     */
    public static function fetch_platform_analytics() {
        // Implementation for fetching analytics from platforms
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'mpcs_syndication_logs';
        $recent_syncs = $wpdb->get_results(
            "SELECT * FROM $logs_table 
             WHERE status = 'success' 
             AND external_id IS NOT NULL 
             AND synced_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             LIMIT 50"
        );
        
        foreach ($recent_syncs as $sync) {
            self::fetch_single_analytics($sync);
        }
    }
    
    /**
     * Fetch analytics for a single sync
     */
    private static function fetch_single_analytics($sync) {
        $handler_class = 'MPCS_Hub_Platform_' . ucfirst($sync->platform);
        
        if (class_exists($handler_class)) {
            $handler = new $handler_class();
            
            if (method_exists($handler, 'get_analytics')) {
                $analytics = $handler->get_analytics($sync->external_id);
                
                if (!is_wp_error($analytics) && is_array($analytics)) {
                    foreach ($analytics as $metric => $value) {
                        MPCS_Hub_Database::insert_analytics([
                            'post_id' => $sync->post_id,
                            'platform' => $sync->platform,
                            'metric_type' => $metric,
                            'metric_value' => $value,
                            'date_recorded' => date('Y-m-d'),
                            'raw_data' => json_encode($analytics)
                        ]);
                    }
                }
            }
        }
    }
}
