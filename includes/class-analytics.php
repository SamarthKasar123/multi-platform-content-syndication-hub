<?php
/**
 * Analytics class for tracking and reporting
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Analytics {
    
    /**
     * Get dashboard analytics data
     */
    public static function get_dashboard_data() {
        $data = [
            'total_syncs' => self::get_total_syncs(),
            'success_rate' => self::get_success_rate(),
            'platform_performance' => self::get_platform_performance(),
            'recent_activity' => self::get_recent_activity(),
            'top_performing_posts' => self::get_top_performing_posts()
        ];
        
        return $data;
    }
    
    /**
     * Get total syncs count
     */
    public static function get_total_syncs($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_syndication_logs';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE created_at >= %s",
            $start_date
        ));
    }
    
    /**
     * Get success rate
     */
    public static function get_success_rate($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_syndication_logs';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE created_at >= %s",
            $start_date
        ));
        
        if ($total == 0) return 0;
        
        $successful = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE created_at >= %s AND status = 'success'",
            $start_date
        ));
        
        return round(($successful / $total) * 100, 2);
    }
    
    /**
     * Get platform performance data
     */
    public static function get_platform_performance($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_syndication_logs';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                platform,
                COUNT(*) as total_syncs,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_syncs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_syncs,
                ROUND(
                    (SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 
                    2
                ) as success_rate
             FROM $table 
             WHERE created_at >= %s 
             GROUP BY platform 
             ORDER BY total_syncs DESC",
            $start_date
        ));
    }
    
    /**
     * Get recent activity
     */
    public static function get_recent_activity($limit = 20) {
        return MPCS_Hub_Database::get_logs([
            'limit' => $limit,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ]);
    }
    
    /**
     * Get top performing posts
     */
    public static function get_top_performing_posts($days = 30, $limit = 10) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'mpcs_analytics';
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                post_id,
                SUM(metric_value) as total_engagement,
                COUNT(DISTINCT platform) as platforms_count
             FROM $analytics_table 
             WHERE date_recorded >= %s 
             AND metric_type IN ('likes', 'shares', 'comments', 'impressions')
             GROUP BY post_id 
             ORDER BY total_engagement DESC 
             LIMIT %d",
            $start_date,
            $limit
        ));
    }
    
    /**
     * Get analytics chart data
     */
    public static function get_chart_data($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_syndication_logs';
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as sync_date,
                platform,
                COUNT(*) as sync_count,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count
             FROM $table 
             WHERE DATE(created_at) BETWEEN %s AND %s
             GROUP BY DATE(created_at), platform
             ORDER BY sync_date ASC",
            $start_date,
            $end_date
        ));
        
        // Format data for Chart.js
        $dates = [];
        $platforms = [];
        $datasets = [];
        
        // Generate date range
        $current_date = new DateTime($start_date);
        $end_date_obj = new DateTime($end_date);
        
        while ($current_date <= $end_date_obj) {
            $dates[] = $current_date->format('M j');
            $current_date->add(new DateInterval('P1D'));
        }
        
        // Group data by platform
        foreach ($results as $result) {
            if (!in_array($result->platform, $platforms)) {
                $platforms[] = $result->platform;
            }
        }
        
        // Create datasets for each platform
        foreach ($platforms as $platform) {
            $data = [];
            $success_data = [];
            
            foreach ($dates as $date) {
                $found = false;
                foreach ($results as $result) {
                    if ($result->platform === $platform && 
                        date('M j', strtotime($result->sync_date)) === $date) {
                        $data[] = $result->sync_count;
                        $success_data[] = $result->success_count;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $data[] = 0;
                    $success_data[] = 0;
                }
            }
            
            $datasets[] = [
                'label' => ucfirst($platform) . ' Total',
                'data' => $data,
                'borderColor' => self::get_platform_color($platform),
                'backgroundColor' => self::get_platform_color($platform, 0.1),
                'fill' => false
            ];
            
            $datasets[] = [
                'label' => ucfirst($platform) . ' Success',
                'data' => $success_data,
                'borderColor' => self::get_platform_color($platform, 0.8),
                'backgroundColor' => self::get_platform_color($platform, 0.2),
                'fill' => false,
                'borderDash' => [5, 5]
            ];
        }
        
        return [
            'labels' => $dates,
            'datasets' => $datasets
        ];
    }
    
    /**
     * Get platform-specific color
     */
    private static function get_platform_color($platform, $alpha = 1) {
        $colors = [
            'twitter' => "rgba(29, 161, 242, $alpha)",
            'facebook' => "rgba(24, 119, 242, $alpha)",
            'linkedin' => "rgba(10, 102, 194, $alpha)",
            'instagram' => "rgba(225, 48, 108, $alpha)",
            'medium' => "rgba(0, 0, 0, $alpha)",
            'dev_to' => "rgba(9, 9, 121, $alpha)",
            'default' => "rgba(75, 192, 192, $alpha)"
        ];
        
        return $colors[$platform] ?? $colors['default'];
    }
    
    /**
     * Export analytics data
     */
    public static function export_data($format = 'csv', $days = 30) {
        $data = [
            'platform_performance' => self::get_platform_performance($days),
            'top_posts' => self::get_top_performing_posts($days),
            'daily_stats' => self::get_daily_stats($days)
        ];
        
        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
            case 'csv':
                return self::convert_to_csv($data);
            default:
                return $data;
        }
    }
    
    /**
     * Convert data to CSV format
     */
    private static function convert_to_csv($data) {
        $csv = "Platform Performance\n";
        $csv .= "Platform,Total Syncs,Successful,Failed,Success Rate\n";
        
        foreach ($data['platform_performance'] as $platform) {
            $csv .= sprintf(
                "%s,%d,%d,%d,%.2f%%\n",
                $platform->platform,
                $platform->total_syncs,
                $platform->successful_syncs,
                $platform->failed_syncs,
                $platform->success_rate
            );
        }
        
        return $csv;
    }
    
    /**
     * Get daily statistics
     */
    private static function get_daily_stats($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_syndication_logs';
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as sync_date,
                COUNT(*) as total_syncs,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_syncs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_syncs
             FROM $table 
             WHERE DATE(created_at) >= %s
             GROUP BY DATE(created_at)
             ORDER BY sync_date DESC",
            $start_date
        ));
    }
}
