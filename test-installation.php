<?php
/**
 * Plugin Installation and Testing Guide
 *
 * @package MPCS_Hub
 */

// This file is for testing purposes only - remove in production

// Test if WordPress is loaded
if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

// Test basic plugin functionality
function mpcs_hub_test_installation() {
    echo "<h2>Multi-Platform Content Syndication Hub - Installation Test</h2>";
    
    // Test 1: Check if main class exists
    echo "<h3>Test 1: Main Class</h3>";
    if (class_exists('MPCS_Hub')) {
        echo "✅ MPCS_Hub class loaded successfully<br>";
    } else {
        echo "❌ MPCS_Hub class not found<br>";
    }
    
    // Test 2: Check database tables
    echo "<h3>Test 2: Database Tables</h3>";
    global $wpdb;
    
    $tables = [
        'mpcs_syndication_logs',
        'mpcs_platform_configs',
        'mpcs_analytics',
        'mpcs_content_versions',
        'mpcs_sync_queue'
    ];
    
    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if ($exists) {
            echo "✅ Table $table_name exists<br>";
        } else {
            echo "❌ Table $table_name missing<br>";
        }
    }
    
    // Test 3: Check required classes
    echo "<h3>Test 3: Required Classes</h3>";
    $required_classes = [
        'MPCS_Hub_Database',
        'MPCS_Hub_Platform_Manager',
        'MPCS_Hub_Content_Formatter',
        'MPCS_Hub_Analytics',
        'MPCS_Hub_Admin'
    ];
    
    foreach ($required_classes as $class) {
        if (class_exists($class)) {
            echo "✅ $class loaded<br>";
        } else {
            echo "❌ $class not found<br>";
        }
    }
    
    // Test 4: Check admin pages
    echo "<h3>Test 4: Admin Integration</h3>";
    if (is_admin()) {
        if (current_user_can('manage_options')) {
            echo "✅ Admin interface accessible<br>";
            echo "✅ User has required permissions<br>";
        } else {
            echo "❌ User lacks required permissions<br>";
        }
        
        // Check if admin menu exists
        global $menu;
        $menu_exists = false;
        foreach ($menu as $menu_item) {
            if (isset($menu_item[2]) && $menu_item[2] === 'mpcs-hub') {
                $menu_exists = true;
                break;
            }
        }
        
        if ($menu_exists) {
            echo "✅ Admin menu registered<br>";
        } else {
            echo "❌ Admin menu not found<br>";
        }
    } else {
        echo "ℹ️ Not in admin area - admin tests skipped<br>";
    }
    
    // Test 5: Check REST API endpoints
    echo "<h3>Test 5: REST API</h3>";
    $rest_server = rest_get_server();
    $routes = $rest_server->get_routes();
    
    $api_routes = [
        '/mpcs-hub/v1/logs',
        '/mpcs-hub/v1/sync',
        '/mpcs-hub/v1/analytics',
        '/mpcs-hub/v1/platforms/status'
    ];
    
    foreach ($api_routes as $route) {
        if (isset($routes[$route])) {
            echo "✅ REST route $route registered<br>";
        } else {
            echo "❌ REST route $route not found<br>";
        }
    }
    
    // Test 6: Sample data insertion
    echo "<h3>Test 6: Database Operations</h3>";
    try {
        // Test platform config insertion
        $result = MPCS_Hub_Database::save_platform_config(
            'test_platform',
            'test_config',
            ['test_key' => 'test_value'],
            get_current_user_id()
        );
        
        if ($result) {
            echo "✅ Platform config insertion works<br>";
            
            // Test retrieval
            $config = MPCS_Hub_Database::get_platform_config('test_platform', 'test_config');
            if ($config && isset($config->config_data['test_key'])) {
                echo "✅ Platform config retrieval works<br>";
            } else {
                echo "❌ Platform config retrieval failed<br>";
            }
        } else {
            echo "❌ Platform config insertion failed<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Database test error: " . $e->getMessage() . "<br>";
    }
    
    echo "<h3>Overall Status</h3>";
    echo "<p><strong>Plugin Status:</strong> ";
    
    if (class_exists('MPCS_Hub') && class_exists('MPCS_Hub_Database')) {
        echo "🟢 <strong>READY FOR DEVELOPMENT</strong></p>";
        echo "<p>The plugin structure is complete and ready for platform integrations.</p>";
        
        echo "<h4>Next Steps:</h4>";
        echo "<ol>";
        echo "<li>Configure platform API credentials in admin panel</li>";
        echo "<li>Complete platform handler implementations</li>";
        echo "<li>Test with sample content</li>";
        echo "<li>Add unit tests</li>";
        echo "</ol>";
        
    } else {
        echo "🔴 <strong>INSTALLATION INCOMPLETE</strong></p>";
        echo "<p>Some components are missing. Please check the error messages above.</p>";
    }
}

// Add admin notice with test results
if (is_admin() && current_user_can('manage_options')) {
    add_action('admin_notices', function() {
        if (isset($_GET['mpcs_test']) && $_GET['mpcs_test'] === '1') {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<div style="padding: 10px;">';
            mpcs_hub_test_installation();
            echo '</div>';
            echo '</div>';
        }
    });
    
    // Add test link to admin bar
    add_action('admin_bar_menu', function($wp_admin_bar) {
        if (current_user_can('manage_options')) {
            $wp_admin_bar->add_node([
                'id' => 'mpcs-test',
                'title' => 'Test MPCS Plugin',
                'href' => admin_url('admin.php?page=mpcs-hub&mpcs_test=1'),
                'meta' => ['target' => '_self']
            ]);
        }
    }, 100);
}

// CLI test command (if WP-CLI is available)
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('mpcs test', function() {
        WP_CLI::line('Testing Multi-Platform Content Syndication Hub...');
        
        ob_start();
        mpcs_hub_test_installation();
        $output = ob_get_clean();
        
        // Convert HTML to plain text for CLI
        $output = strip_tags(str_replace(['<br>', '<h3>', '</h3>', '<h4>', '</h4>'], ["\n", "\n", "\n", "\n", "\n"], $output));
        
        WP_CLI::line($output);
    });
}
