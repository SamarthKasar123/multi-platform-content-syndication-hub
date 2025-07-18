<?php
/**
 * Plugin Name: Multi-Platform Content Syndication Hub
 * Plugin URI: https://github.com/yourusername/multi-platform-syndication
 * Description: Automatically syndicate and optimize content across multiple platforms with intelligent formatting and scheduling.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mpcs-hub
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * Network: true
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MPCS_HUB_VERSION', '1.0.0');
define('MPCS_HUB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MPCS_HUB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MPCS_HUB_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class MPCS_Hub {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        }
        
        // AJAX hooks
        add_action('wp_ajax_mpcs_sync_content', [$this, 'ajax_sync_content']);
        add_action('wp_ajax_mpcs_get_analytics', [$this, 'ajax_get_analytics']);
        add_action('wp_ajax_mpcs_retry_syndication', [$this, 'ajax_retry_syndication']);
        add_action('wp_ajax_mpcs_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_mpcs_toggle_platform', [$this, 'ajax_toggle_platform']);
        add_action('wp_ajax_mpcs_save_platform_config', [$this, 'ajax_save_platform_config']);
        add_action('wp_ajax_mpcs_sync_latest_posts', [$this, 'ajax_sync_latest_posts']);
        add_action('wp_ajax_mpcs_test_all_connections', [$this, 'ajax_test_all_connections']);
        
        // Post hooks
        add_action('save_post', [$this, 'on_post_save'], 10, 2);
        add_action('publish_post', [$this, 'on_post_publish'], 10, 2);
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load required classes
        $this->load_dependencies();
        
        // Initialize database tables
        $this->init_database();
        
        // Initialize REST API endpoints
        $this->init_rest_api();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once MPCS_HUB_PLUGIN_DIR . 'includes/class-database.php';
        require_once MPCS_HUB_PLUGIN_DIR . 'includes/class-platform-manager.php';
        require_once MPCS_HUB_PLUGIN_DIR . 'includes/class-content-formatter.php';
        require_once MPCS_HUB_PLUGIN_DIR . 'includes/class-scheduler.php';
        require_once MPCS_HUB_PLUGIN_DIR . 'includes/class-analytics.php';
        require_once MPCS_HUB_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once MPCS_HUB_PLUGIN_DIR . 'admin/class-admin.php';
        
        // Initialize components
        new MPCS_Hub_Admin();
        MPCS_Hub_Scheduler::init();
        
        // Load test file in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            require_once MPCS_HUB_PLUGIN_DIR . 'test-installation.php';
        }
    }
    
    /**
     * Initialize database tables
     */
    private function init_database() {
        MPCS_Hub_Database::init();
    }
    
    /**
     * Initialize REST API
     */
    private function init_rest_api() {
        new MPCS_Hub_REST_API();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'mpcs-hub',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Content Syndication Hub', 'mpcs-hub'),
            __('Syndication Hub', 'mpcs-hub'),
            'manage_options',
            'mpcs-hub',
            [$this, 'admin_page'],
            'dashicons-share',
            30
        );
        
        // Add submenus
        add_submenu_page(
            'mpcs-hub',
            __('Platforms', 'mpcs-hub'),
            __('Platforms', 'mpcs-hub'),
            'manage_options',
            'mpcs-hub-platforms',
            [$this, 'platforms_page']
        );
        
        add_submenu_page(
            'mpcs-hub',
            __('Analytics', 'mpcs-hub'),
            __('Analytics', 'mpcs-hub'),
            'manage_options',
            'mpcs-hub-analytics',
            [$this, 'analytics_page']
        );
        
        add_submenu_page(
            'mpcs-hub',
            __('Settings', 'mpcs-hub'),
            __('Settings', 'mpcs-hub'),
            'manage_options',
            'mpcs-hub-settings',
            [$this, 'settings_page']
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'mpcs-hub') === false) {
            return;
        }
        
        wp_enqueue_script(
            'mpcs-hub-admin',
            MPCS_HUB_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api-fetch'],
            MPCS_HUB_VERSION,
            true
        );
        
        wp_enqueue_style(
            'mpcs-hub-admin',
            MPCS_HUB_PLUGIN_URL . 'assets/css/admin.css',
            [],
            MPCS_HUB_VERSION
        );
        
        // Localize script
        wp_localize_script('mpcs-hub-admin', 'mpcsHub', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpcs_hub_nonce'),
            'restUrl' => rest_url('mpcs-hub/v1/'),
            'restNonce' => wp_create_nonce('wp_rest')
        ]);
    }
    
    /**
     * Admin pages callbacks
     */
    public function admin_page() {
        include MPCS_HUB_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    public function platforms_page() {
        include MPCS_HUB_PLUGIN_DIR . 'admin/views/platforms.php';
    }
    
    public function analytics_page() {
        include MPCS_HUB_PLUGIN_DIR . 'admin/views/analytics.php';
    }
    
    public function settings_page() {
        include MPCS_HUB_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_sync_content() {
        check_ajax_referer('mpcs_hub_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Unauthorized', 'mpcs-hub'));
        }
        
        $post_id = intval($_POST['post_id']);
        $platforms = isset($_POST['platforms']) ? $_POST['platforms'] : [];
        
        if (is_string($platforms)) {
            $platforms = json_decode($platforms, true);
        }
        
        $result = MPCS_Hub_Platform_Manager::sync_content($post_id, $platforms);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_analytics() {
        check_ajax_referer('mpcs_hub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mpcs-hub'));
        }
        
        $analytics = MPCS_Hub_Analytics::get_dashboard_data();
        
        wp_send_json_success($analytics);
    }
    
    public function ajax_retry_syndication() {
        check_ajax_referer('mpcs_hub_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Unauthorized', 'mpcs-hub'));
        }
        
        $log_id = intval($_POST['log_id']);
        $result = MPCS_Hub_Platform_Manager::retry_syndication($log_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_test_connection() {
        check_ajax_referer('mpcs_hub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mpcs-hub'));
        }
        
        $platform = sanitize_text_field($_POST['platform']);
        
        $handler_class = 'MPCS_Hub_Platform_' . ucfirst($platform);
        
        if (!class_exists($handler_class)) {
            wp_send_json_error(__('Platform handler not found', 'mpcs-hub'));
        }
        
        $handler = new $handler_class();
        
        if (!method_exists($handler, 'test_connection')) {
            wp_send_json_error(__('Test connection not supported', 'mpcs-hub'));
        }
        
        $result = $handler->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_toggle_platform() {
        check_ajax_referer('mpcs_hub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mpcs-hub'));
        }
        
        $platform = sanitize_text_field($_POST['platform']);
        $enabled = (bool) $_POST['enabled'];
        
        $user_id = get_current_user_id();
        $enabled_platforms = get_user_meta($user_id, 'mpcs_enabled_platforms', true);
        
        if (!is_array($enabled_platforms)) {
            $enabled_platforms = [];
        }
        
        if ($enabled) {
            $enabled_platforms[$platform] = true;
        } else {
            unset($enabled_platforms[$platform]);
        }
        
        update_user_meta($user_id, 'mpcs_enabled_platforms', $enabled_platforms);
        
        wp_send_json_success([
            'platform' => $platform,
            'enabled' => $enabled
        ]);
    }
    
    public function ajax_save_platform_config() {
        check_ajax_referer('mpcs_hub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mpcs-hub'));
        }
        
        $platform = sanitize_text_field($_POST['platform']);
        $config = $_POST['config'] ?? [];
        
        // Sanitize config data
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
            wp_send_json_error(__('Failed to save configuration', 'mpcs-hub'));
        }
        
        wp_send_json_success([
            'platform' => $platform,
            'message' => __('Configuration saved successfully', 'mpcs-hub')
        ]);
    }
    
    public function ajax_sync_latest_posts() {
        check_ajax_referer('mpcs_hub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mpcs-hub'));
        }
        
        // Get latest 5 published posts
        $posts = get_posts([
            'numberposts' => 5,
            'post_status' => 'publish',
            'post_type' => 'post'
        ]);
        
        $results = [];
        
        foreach ($posts as $post) {
            $result = MPCS_Hub_Platform_Manager::sync_content($post->ID);
            $results[$post->ID] = $result;
        }
        
        wp_send_json_success([
            'synced_posts' => count($posts),
            'results' => $results
        ]);
    }
    
    public function ajax_test_all_connections() {
        check_ajax_referer('mpcs_hub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mpcs-hub'));
        }
        
        $enabled_platforms = MPCS_Hub_Platform_Manager::get_enabled_platforms();
        $results = [];
        
        foreach ($enabled_platforms as $platform => $config) {
            $handler_class = 'MPCS_Hub_Platform_' . ucfirst($platform);
            
            if (class_exists($handler_class)) {
                $handler = new $handler_class();
                
                if (method_exists($handler, 'test_connection')) {
                    $result = $handler->test_connection();
                    $results[$platform] = is_wp_error($result) ? 'failed' : 'success';
                } else {
                    $results[$platform] = 'not_supported';
                }
            } else {
                $results[$platform] = 'handler_missing';
            }
        }
        
        wp_send_json_success([
            'results' => $results,
            'message' => sprintf(__('Tested %d platform connections', 'mpcs-hub'), count($results))
        ]);
    }
    
    /**
     * Post hooks
     */
    public function on_post_save($post_id, $post) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Auto-save syndication settings
        MPCS_Hub_Platform_Manager::save_post_settings($post_id);
    }
    
    public function on_post_publish($post_id, $post) {
        // Auto-sync if enabled
        $auto_sync = get_post_meta($post_id, '_mpcs_auto_sync', true);
        if ($auto_sync) {
            MPCS_Hub_Platform_Manager::auto_sync_content($post_id);
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        MPCS_Hub_Database::create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron jobs
        wp_schedule_event(time(), 'hourly', 'mpcs_hub_sync_scheduled');
        wp_schedule_event(time(), 'daily', 'mpcs_hub_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('mpcs_hub_sync_scheduled');
        wp_clear_scheduled_hook('mpcs_hub_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = [
            'mpcs_hub_version' => MPCS_HUB_VERSION,
            'mpcs_hub_auto_sync' => false,
            'mpcs_hub_platforms' => [],
            'mpcs_hub_retry_attempts' => 3,
            'mpcs_hub_log_level' => 'error'
        ];
        
        foreach ($defaults as $option => $value) {
            if (!get_option($option)) {
                add_option($option, $value);
            }
        }
    }
}

// Initialize the plugin
MPCS_Hub::get_instance();
