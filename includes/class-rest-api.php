<?php
/**
 * REST API endpoints for Multi-Platform Syndication Hub
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_REST_API {
    
    /**
     * API namespace
     */
    private $namespace = 'mpcs-hub/v1';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get syndication logs
        register_rest_route($this->namespace, '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_logs'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id' => [
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'platform' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'status' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 20,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Sync content
        register_rest_route($this->namespace, '/sync', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_content'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'platforms' => [
                    'required' => false,
                    'type' => 'array',
                    'default' => []
                ]
            ]
        ]);
        
        // Get analytics
        register_rest_route($this->namespace, '/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id' => [
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'platform' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'days' => [
                    'type' => 'integer',
                    'default' => 30,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Get platform status
        register_rest_route($this->namespace, '/platforms/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_platform_status'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        // Test platform connection
        register_rest_route($this->namespace, '/platforms/(?P<platform>[a-zA-Z0-9_-]+)/test', [
            'methods' => 'POST',
            'callback' => [$this, 'test_platform_connection'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'platform' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Get dashboard data
        register_rest_route($this->namespace, '/dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard_data'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        // Update platform configuration
        register_rest_route($this->namespace, '/platforms/(?P<platform>[a-zA-Z0-9_-]+)/config', [
            'methods' => 'POST',
            'callback' => [$this, 'update_platform_config'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args' => [
                'platform' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'config' => [
                    'required' => true,
                    'type' => 'object'
                ]
            ]
        ]);
    }
    
    /**
     * Get syndication logs
     */
    public function get_logs($request) {
        $params = $request->get_params();
        
        $logs = MPCS_Hub_Database::get_logs($params);
        
        // Add post titles to logs
        foreach ($logs as &$log) {
            $post = get_post($log->post_id);
            $log->post_title = $post ? $post->post_title : __('Deleted Post', 'mpcs-hub');
            $log->post_url = $post ? get_permalink($post->ID) : null;
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $logs,
            'count' => count($logs)
        ]);
    }
    
    /**
     * Sync content to platforms
     */
    public function sync_content($request) {
        $post_id = $request->get_param('post_id');
        $platforms = $request->get_param('platforms');
        
        if (!get_post($post_id)) {
            return new WP_Error(
                'invalid_post',
                __('Post not found', 'mpcs-hub'),
                ['status' => 404]
            );
        }
        
        $result = MPCS_Hub_Platform_Manager::sync_content($post_id, $platforms);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $result,
            'message' => __('Sync initiated successfully', 'mpcs-hub')
        ]);
    }
    
    /**
     * Get analytics data
     */
    public function get_analytics($request) {
        $params = $request->get_params();
        
        if (isset($params['post_id'])) {
            // Get analytics for specific post
            $analytics = MPCS_Hub_Database::get_analytics($params);
        } else {
            // Get dashboard analytics
            $analytics = MPCS_Hub_Analytics::get_dashboard_data();
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $analytics
        ]);
    }
    
    /**
     * Get platform status
     */
    public function get_platform_status($request) {
        $platforms = MPCS_Hub_Platform_Manager::get_platforms();
        $enabled_platforms = MPCS_Hub_Platform_Manager::get_enabled_platforms();
        $platform_stats = MPCS_Hub_Platform_Manager::get_platform_stats();
        
        $status = [];
        
        foreach ($platforms as $platform_key => $platform_name) {
            $is_enabled = MPCS_Hub_Platform_Manager::is_platform_enabled($platform_key);
            $config = MPCS_Hub_Database::get_platform_config($platform_key);
            
            $platform_stat = null;
            foreach ($platform_stats as $stat) {
                if ($stat->platform === $platform_key) {
                    $platform_stat = $stat;
                    break;
                }
            }
            
            $status[$platform_key] = [
                'name' => $platform_name,
                'enabled' => $is_enabled,
                'configured' => $config && $config->is_active,
                'stats' => $platform_stat ? [
                    'total_syncs' => $platform_stat->total_syncs,
                    'successful_syncs' => $platform_stat->successful_syncs,
                    'failed_syncs' => $platform_stat->failed_syncs,
                    'success_rate' => round(($platform_stat->successful_syncs / $platform_stat->total_syncs) * 100, 2)
                ] : null
            ];
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $status
        ]);
    }
    
    /**
     * Test platform connection
     */
    public function test_platform_connection($request) {
        $platform = $request->get_param('platform');
        
        $handler_class = 'MPCS_Hub_Platform_' . ucfirst($platform);
        
        if (!class_exists($handler_class)) {
            return new WP_Error(
                'platform_not_found',
                sprintf(__('Platform handler for %s not found', 'mpcs-hub'), $platform),
                ['status' => 404]
            );
        }
        
        $handler = new $handler_class();
        
        if (!method_exists($handler, 'test_connection')) {
            return new WP_Error(
                'method_not_implemented',
                __('Test connection method not implemented for this platform', 'mpcs-hub'),
                ['status' => 501]
            );
        }
        
        $result = $handler->test_connection();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $result,
            'message' => sprintf(__('%s connection successful', 'mpcs-hub'), ucfirst($platform))
        ]);
    }
    
    /**
     * Get dashboard data
     */
    public function get_dashboard_data($request) {
        $data = MPCS_Hub_Analytics::get_dashboard_data();
        
        return rest_ensure_response([
            'success' => true,
            'data' => $data
        ]);
    }
    
    /**
     * Update platform configuration
     */
    public function update_platform_config($request) {
        $platform = $request->get_param('platform');
        $config = $request->get_param('config');
        
        if (!is_array($config)) {
            return new WP_Error(
                'invalid_config',
                __('Configuration must be an object', 'mpcs-hub'),
                ['status' => 400]
            );
        }
        
        // Sanitize configuration data
        $sanitized_config = [];
        foreach ($config as $key => $value) {
            $sanitized_config[sanitize_text_field($key)] = sanitize_text_field($value);
        }
        
        $result = MPCS_Hub_Database::save_platform_config(
            $platform,
            'default',
            $sanitized_config,
            get_current_user_id()
        );
        
        if ($result === false) {
            return new WP_Error(
                'save_failed',
                __('Failed to save platform configuration', 'mpcs-hub'),
                ['status' => 500]
            );
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => sprintf(__('%s configuration updated successfully', 'mpcs-hub'), ucfirst($platform))
        ]);
    }
    
    /**
     * Check user permissions for API access
     */
    public function check_permissions($request) {
        return current_user_can('edit_posts');
    }
    
    /**
     * Check admin permissions for sensitive operations
     */
    public function check_admin_permissions($request) {
        return current_user_can('manage_options');
    }
}
