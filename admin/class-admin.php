<?php
/**
 * Admin interface for Multi-Platform Syndication Hub
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    /**
     * Initialize admin functionality
     */
    public function init() {
        // Add meta boxes to post edit screen
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        
        // Add post list column
        add_filter('manage_posts_columns', [$this, 'add_posts_column']);
        add_action('manage_posts_custom_column', [$this, 'populate_posts_column'], 10, 2);
        
        // Add admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // Add dashboard widgets
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);
    }
    
    /**
     * Add meta boxes to post edit screen
     */
    public function add_meta_boxes() {
        $post_types = get_post_types(['public' => true]);
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'mpcs-syndication',
                __('Content Syndication', 'mpcs-hub'),
                [$this, 'render_syndication_meta_box'],
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render syndication meta box
     */
    public function render_syndication_meta_box($post) {
        wp_nonce_field('mpcs_syndication_meta', 'mpcs_syndication_nonce');
        
        $auto_sync = get_post_meta($post->ID, '_mpcs_auto_sync', true);
        $auto_sync_platforms = get_post_meta($post->ID, '_mpcs_auto_sync_platforms', true);
        $platforms = MPCS_Hub_Platform_Manager::get_platforms();
        $enabled_platforms = MPCS_Hub_Platform_Manager::get_enabled_platforms();
        
        if (!is_array($auto_sync_platforms)) {
            $auto_sync_platforms = [];
        }
        
        include MPCS_HUB_PLUGIN_DIR . 'admin/views/meta-box-syndication.php';
    }
    
    /**
     * Add syndication column to posts list
     */
    public function add_posts_column($columns) {
        $columns['mpcs_syndication'] = __('Syndication', 'mpcs-hub');
        return $columns;
    }
    
    /**
     * Populate syndication column
     */
    public function populate_posts_column($column, $post_id) {
        if ($column === 'mpcs_syndication') {
            $logs = MPCS_Hub_Platform_Manager::get_post_syndication_history($post_id);
            
            if (empty($logs)) {
                echo '<span class="mpcs-status-none">' . __('Not synced', 'mpcs-hub') . '</span>';
                return;
            }
            
            $platforms = [];
            foreach ($logs as $log) {
                $status_class = 'mpcs-status-' . $log->status;
                $platforms[] = sprintf(
                    '<span class="%s" title="%s: %s">%s</span>',
                    $status_class,
                    ucfirst($log->platform),
                    ucfirst($log->status),
                    strtoupper(substr($log->platform, 0, 2))
                );
            }
            
            echo implode(' ', $platforms);
        }
    }
    
    /**
     * Add admin notices
     */
    public function admin_notices() {
        // Check if any platforms are configured
        $configured_platforms = $this->get_configured_platforms_count();
        
        if ($configured_platforms === 0) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('Multi-Platform Syndication Hub is installed but no platforms are configured.', 'mpcs-hub'); ?>
                    <a href="<?php echo admin_url('admin.php?page=mpcs-hub-platforms'); ?>">
                        <?php _e('Configure platforms now', 'mpcs-hub'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'mpcs_syndication_stats',
            __('Syndication Statistics', 'mpcs-hub'),
            [$this, 'render_dashboard_widget']
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $stats = MPCS_Hub_Platform_Manager::get_platform_stats();
        include MPCS_HUB_PLUGIN_DIR . 'admin/views/dashboard-widget.php';
    }
    
    /**
     * Get count of configured platforms
     */
    private function get_configured_platforms_count() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcs_platform_configs';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_active = 1");
    }
}
