<?php
/**
 * Database management class
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Database {
    
    /**
     * Initialize database operations
     */
    public static function init() {
        // Database operations are handled during activation
    }
    
    /**
     * Create plugin database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Syndication logs table
        $logs_table = $wpdb->prefix . 'mpcs_syndication_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            platform varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            external_id varchar(255) DEFAULT NULL,
            external_url text DEFAULT NULL,
            response_data longtext DEFAULT NULL,
            error_message text DEFAULT NULL,
            attempts int(11) DEFAULT 0,
            scheduled_at datetime DEFAULT NULL,
            synced_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_platform (post_id, platform),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";
        
        // Platform configurations table
        $platforms_table = $wpdb->prefix . 'mpcs_platform_configs';
        $platforms_sql = "CREATE TABLE $platforms_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            platform varchar(50) NOT NULL,
            config_name varchar(100) NOT NULL,
            config_data longtext NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY platform_config (platform, config_name),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Analytics data table
        $analytics_table = $wpdb->prefix . 'mpcs_analytics';
        $analytics_sql = "CREATE TABLE $analytics_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            platform varchar(50) NOT NULL,
            metric_type varchar(50) NOT NULL,
            metric_value bigint(20) DEFAULT 0,
            date_recorded date NOT NULL,
            raw_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_metric (post_id, platform, metric_type, date_recorded),
            KEY post_platform (post_id, platform),
            KEY date_recorded (date_recorded)
        ) $charset_collate;";
        
        // Content versions table
        $versions_table = $wpdb->prefix . 'mpcs_content_versions';
        $versions_sql = "CREATE TABLE $versions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            platform varchar(50) NOT NULL,
            version_number int(11) DEFAULT 1,
            title text DEFAULT NULL,
            content longtext DEFAULT NULL,
            excerpt text DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            images longtext DEFAULT NULL,
            hashtags text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_platform_version (post_id, platform, version_number),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Queue table for background processing
        $queue_table = $wpdb->prefix . 'mpcs_sync_queue';
        $queue_sql = "CREATE TABLE $queue_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            platform varchar(50) NOT NULL,
            action varchar(50) NOT NULL DEFAULT 'publish',
            priority int(11) DEFAULT 5,
            payload longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            scheduled_at datetime DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status_priority (status, priority),
            KEY scheduled_at (scheduled_at),
            KEY post_platform (post_id, platform)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($logs_sql);
        dbDelta($platforms_sql);
        dbDelta($analytics_sql);
        dbDelta($versions_sql);
        dbDelta($queue_sql);
        
        // Insert default platform configurations
        self::insert_default_platforms();
    }
    
    /**
     * Insert default platform configurations
     */
    private static function insert_default_platforms() {
        global $wpdb;
        
        $platforms_table = $wpdb->prefix . 'mpcs_platform_configs';
        
        $default_platforms = [
            [
                'platform' => 'facebook',
                'config_name' => 'default',
                'config_data' => json_encode([
                    'api_version' => 'v18.0',
                    'max_title_length' => 255,
                    'max_content_length' => 63206,
                    'supports_images' => true,
                    'supports_videos' => true,
                    'supports_hashtags' => true,
                    'required_fields' => ['access_token', 'page_id']
                ])
            ],
            [
                'platform' => 'twitter',
                'config_name' => 'default',
                'config_data' => json_encode([
                    'api_version' => 'v2',
                    'max_title_length' => 280,
                    'max_content_length' => 280,
                    'supports_images' => true,
                    'supports_videos' => true,
                    'supports_hashtags' => true,
                    'required_fields' => ['api_key', 'api_secret', 'access_token', 'access_token_secret']
                ])
            ],
            [
                'platform' => 'linkedin',
                'config_name' => 'default',
                'config_data' => json_encode([
                    'api_version' => 'v2',
                    'max_title_length' => 150,
                    'max_content_length' => 3000,
                    'supports_images' => true,
                    'supports_videos' => true,
                    'supports_hashtags' => true,
                    'required_fields' => ['access_token', 'organization_id']
                ])
            ],
            [
                'platform' => 'medium',
                'config_name' => 'default',
                'config_data' => json_encode([
                    'api_version' => 'v1',
                    'max_title_length' => 100,
                    'max_content_length' => -1,
                    'supports_images' => true,
                    'supports_videos' => false,
                    'supports_hashtags' => true,
                    'required_fields' => ['access_token', 'author_id']
                ])
            ],
            [
                'platform' => 'dev_to',
                'config_name' => 'default',
                'config_data' => json_encode([
                    'api_version' => 'v1',
                    'max_title_length' => 100,
                    'max_content_length' => -1,
                    'supports_images' => true,
                    'supports_videos' => true,
                    'supports_hashtags' => true,
                    'required_fields' => ['api_key']
                ])
            ]
        ];
        
        foreach ($default_platforms as $platform) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $platforms_table WHERE platform = %s AND config_name = %s",
                $platform['platform'],
                $platform['config_name']
            ));
            
            if (!$existing) {
                $wpdb->insert($platforms_table, $platform);
            }
        }
    }
    
    /**
     * Get syndication logs
     */
    public static function get_logs($args = []) {
        global $wpdb;
        
        $defaults = [
            'post_id' => null,
            'platform' => null,
            'status' => null,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . 'mpcs_syndication_logs';
        $where_clauses = ['1=1'];
        $where_values = [];
        
        if ($args['post_id']) {
            $where_clauses[] = 'post_id = %d';
            $where_values[] = $args['post_id'];
        }
        
        if ($args['platform']) {
            $where_clauses[] = 'platform = %s';
            $where_values[] = $args['platform'];
        }
        
        if ($args['status']) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        $order_clause = sprintf('%s %s', $args['orderby'], $args['order']);
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY $order_clause $limit_clause";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Insert syndication log
     */
    public static function insert_log($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_syndication_logs';
        
        $defaults = [
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        return $wpdb->insert($table, $data);
    }
    
    /**
     * Update syndication log
     */
    public static function update_log($id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_syndication_logs';
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update($table, $data, ['id' => $id]);
    }
    
    /**
     * Get platform configuration
     */
    public static function get_platform_config($platform, $config_name = 'default') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_platform_configs';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE platform = %s AND config_name = %s AND is_active = 1",
            $platform,
            $config_name
        ));
        
        if ($result) {
            $result->config_data = json_decode($result->config_data, true);
        }
        
        return $result;
    }
    
    /**
     * Save platform configuration
     */
    public static function save_platform_config($platform, $config_name, $config_data, $user_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_platform_configs';
        
        $data = [
            'platform' => $platform,
            'config_name' => $config_name,
            'config_data' => json_encode($config_data),
            'created_by' => $user_id ?: get_current_user_id(),
            'updated_at' => current_time('mysql')
        ];
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE platform = %s AND config_name = %s",
            $platform,
            $config_name
        ));
        
        if ($existing) {
            return $wpdb->update($table, $data, ['id' => $existing]);
        } else {
            $data['created_at'] = current_time('mysql');
            return $wpdb->insert($table, $data);
        }
    }
    
    /**
     * Get analytics data
     */
    public static function get_analytics($args = []) {
        global $wpdb;
        
        $defaults = [
            'post_id' => null,
            'platform' => null,
            'metric_type' => null,
            'start_date' => null,
            'end_date' => null,
            'limit' => 100
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . 'mpcs_analytics';
        $where_clauses = ['1=1'];
        $where_values = [];
        
        if ($args['post_id']) {
            $where_clauses[] = 'post_id = %d';
            $where_values[] = $args['post_id'];
        }
        
        if ($args['platform']) {
            $where_clauses[] = 'platform = %s';
            $where_values[] = $args['platform'];
        }
        
        if ($args['metric_type']) {
            $where_clauses[] = 'metric_type = %s';
            $where_values[] = $args['metric_type'];
        }
        
        if ($args['start_date']) {
            $where_clauses[] = 'date_recorded >= %s';
            $where_values[] = $args['start_date'];
        }
        
        if ($args['end_date']) {
            $where_clauses[] = 'date_recorded <= %s';
            $where_values[] = $args['end_date'];
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        $limit_clause = sprintf('LIMIT %d', $args['limit']);
        
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY date_recorded DESC $limit_clause";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Insert analytics data
     */
    public static function insert_analytics($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_analytics';
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for metrics
        $sql = $wpdb->prepare(
            "INSERT INTO $table (post_id, platform, metric_type, metric_value, date_recorded, raw_data, created_at)
             VALUES (%d, %s, %s, %d, %s, %s, %s)
             ON DUPLICATE KEY UPDATE 
             metric_value = VALUES(metric_value),
             raw_data = VALUES(raw_data),
             created_at = VALUES(created_at)",
            $data['post_id'],
            $data['platform'],
            $data['metric_type'],
            $data['metric_value'],
            $data['date_recorded'],
            $data['raw_data'] ?? null,
            current_time('mysql')
        );
        
        return $wpdb->query($sql);
    }
}
