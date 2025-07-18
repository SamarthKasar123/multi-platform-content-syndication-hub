<?php
/**
 * Facebook Platform Handler
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Platform_Facebook {
    
    private $app_id;
    private $app_secret;
    private $access_token;
    private $page_id;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * Load Facebook API credentials
     */
    private function load_credentials() {
        $config = MPCS_Hub_Database::get_platform_config('facebook');
        
        if ($config && !empty($config->config_data)) {
            $credentials = $config->config_data;
            $this->app_id = $credentials['app_id'] ?? '';
            $this->app_secret = $credentials['app_secret'] ?? '';
            $this->access_token = $credentials['access_token'] ?? '';
            $this->page_id = $credentials['page_id'] ?? '';
        }
    }
    
    /**
     * Publish content to Facebook
     */
    public function publish($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Facebook API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Prepare post data
            $post_data = [
                'message' => $content_data['platform_content'],
                'link' => $content_data['url'],
                'access_token' => $this->access_token
            ];
            
            // Add image if available
            if (!empty($content_data['images']) && isset($content_data['images'][0])) {
                $image_url = $content_data['images'][0]['url'];
                $post_data['picture'] = $image_url;
            }
            
            // Make API request
            $response = $this->make_api_request('POST', "/{$this->page_id}/feed", $post_data);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'external_id' => $response['id'],
                'external_url' => 'https://facebook.com/' . $response['id'],
                'platform' => 'facebook',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return new WP_Error('publish_failed', $e->getMessage());
        }
    }
    
    /**
     * Update existing Facebook post
     */
    public function update($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Facebook API credentials not configured', 'mpcs-hub'));
        }
        
        if (empty($content_data['external_id'])) {
            return new WP_Error('missing_id', __('Facebook post ID is required for update', 'mpcs-hub'));
        }
        
        try {
            $update_data = [
                'message' => $content_data['platform_content'],
                'access_token' => $this->access_token
            ];
            
            $response = $this->make_api_request('POST', "/{$content_data['external_id']}", $update_data);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'updated' => true,
                'platform' => 'facebook',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return new WP_Error('update_failed', $e->getMessage());
        }
    }
    
    /**
     * Delete Facebook post
     */
    public function delete($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Facebook API credentials not configured', 'mpcs-hub'));
        }
        
        if (empty($content_data['external_id'])) {
            return new WP_Error('missing_id', __('Facebook post ID is required for deletion', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('DELETE', "/{$content_data['external_id']}", [
                'access_token' => $this->access_token
            ]);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'deleted' => $response['success'] ?? true,
                'platform' => 'facebook'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('delete_failed', $e->getMessage());
        }
    }
    
    /**
     * Get Facebook post analytics
     */
    public function get_analytics($post_id, $metrics = ['likes', 'comments', 'shares', 'impressions']) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Facebook API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $metrics_string = implode(',', $metrics);
            $response = $this->make_api_request('GET', "/{$post_id}/insights", [
                'metric' => $metrics_string,
                'access_token' => $this->access_token
            ]);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $metrics_data = [];
            
            if (isset($response['data'])) {
                foreach ($response['data'] as $metric) {
                    $metric_name = $metric['name'];
                    $metric_value = isset($metric['values'][0]['value']) ? $metric['values'][0]['value'] : 0;
                    $metrics_data[$metric_name] = $metric_value;
                }
            }
            
            // Also get basic engagement metrics
            $engagement_response = $this->make_api_request('GET', "/{$post_id}", [
                'fields' => 'likes.summary(true),comments.summary(true),shares',
                'access_token' => $this->access_token
            ]);
            
            if (!is_wp_error($engagement_response)) {
                $metrics_data['likes'] = $engagement_response['likes']['summary']['total_count'] ?? 0;
                $metrics_data['comments'] = $engagement_response['comments']['summary']['total_count'] ?? 0;
                $metrics_data['shares'] = $engagement_response['shares']['count'] ?? 0;
            }
            
            return $metrics_data;
            
        } catch (Exception $e) {
            return new WP_Error('analytics_failed', $e->getMessage());
        }
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Facebook API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', "/{$this->page_id}", [
                'fields' => 'id,name,access_token',
                'access_token' => $this->access_token
            ]);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'page_id' => $response['id'],
                'page_name' => $response['name'],
                'message' => 'Facebook connection successful'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('connection_failed', $e->getMessage());
        }
    }
    
    /**
     * Upload media to Facebook
     */
    public function upload_media($image_url) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Facebook API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Download image
            $image_data = wp_remote_get($image_url);
            if (is_wp_error($image_data)) {
                return $image_data;
            }
            
            $image_content = wp_remote_retrieve_body($image_data);
            
            // Upload to Facebook
            $upload_data = [
                'url' => $image_url,
                'published' => 'false',
                'access_token' => $this->access_token
            ];
            
            $response = $this->make_api_request('POST', "/{$this->page_id}/photos", $upload_data);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $response['id'];
            
        } catch (Exception $e) {
            return new WP_Error('upload_failed', $e->getMessage());
        }
    }
    
    /**
     * Check if platform is properly configured
     */
    private function is_configured() {
        return !empty($this->app_id) && 
               !empty($this->app_secret) && 
               !empty($this->access_token) && 
               !empty($this->page_id);
    }
    
    /**
     * Make API request to Facebook
     */
    private function make_api_request($method, $endpoint, $data = []) {
        $base_url = 'https://graph.facebook.com/v18.0';
        $url = $base_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ];
        
        if ($method === 'POST') {
            $args['body'] = http_build_query($data);
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        } elseif ($method === 'DELETE') {
            $url .= '?' . http_build_query($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);
        
        if ($status_code >= 400) {
            $error_message = 'Facebook API Error: ' . $status_code;
            
            if (isset($decoded_body['error']['message'])) {
                $error_message .= ' - ' . $decoded_body['error']['message'];
            }
            
            return new WP_Error('api_error', $error_message);
        }
        
        return $decoded_body;
    }
    
    /**
     * Get page insights
     */
    public function get_page_insights($metrics = ['page_impressions', 'page_engaged_users']) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Facebook API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', "/{$this->page_id}/insights", [
                'metric' => implode(',', $metrics),
                'period' => 'day',
                'access_token' => $this->access_token
            ]);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $response['data'] ?? [];
            
        } catch (Exception $e) {
            return new WP_Error('insights_failed', $e->getMessage());
        }
    }
}
