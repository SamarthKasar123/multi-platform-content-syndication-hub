<?php
/**
 * Medium Platform Handler
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Platform_Medium {
    
    private $access_token;
    private $user_id;
    private $publication_id;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * Load Medium API credentials
     */
    private function load_credentials() {
        $config = MPCS_Hub_Database::get_platform_config('medium');
        
        if ($config && !empty($config->config_data)) {
            $credentials = $config->config_data;
            $this->access_token = $credentials['access_token'] ?? '';
            $this->user_id = $credentials['user_id'] ?? '';
            $this->publication_id = $credentials['publication_id'] ?? '';
        }
    }
    
    /**
     * Publish content to Medium
     */
    public function publish($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Medium API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Prepare article content
            $article_content = $this->format_content($content_data);
            
            // Determine publication endpoint
            $endpoint = $this->publication_id ? 
                "/v1/publications/{$this->publication_id}/posts" : 
                "/v1/users/{$this->user_id}/posts";
            
            // Prepare post data
            $post_data = [
                'title' => $content_data['title'],
                'contentFormat' => 'html',
                'content' => $article_content,
                'publishStatus' => $content_data['status'] ?? 'public',
                'license' => 'all-rights-reserved'
            ];
            
            // Add tags if provided
            if (!empty($content_data['hashtags'])) {
                $post_data['tags'] = array_map(function($tag) {
                    return ltrim($tag, '#');
                }, array_slice($content_data['hashtags'], 0, 5)); // Medium allows max 5 tags
            }
            
            // Add canonical URL if provided
            if (!empty($content_data['canonical_url'])) {
                $post_data['canonicalUrl'] = $content_data['canonical_url'];
            }
            
            // Make API request
            $response = $this->make_api_request('POST', $endpoint, $post_data);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $post_id = $response['data']['id'];
            $medium_url = $response['data']['url'];
            
            return [
                'success' => true,
                'external_id' => $post_id,
                'external_url' => $medium_url,
                'platform' => 'medium',
                'response' => $response['data']
            ];
            
        } catch (Exception $e) {
            return new WP_Error('publish_failed', $e->getMessage());
        }
    }
    
    /**
     * Update existing Medium post (not supported)
     */
    public function update($content_data) {
        return new WP_Error(
            'not_supported',
            __('Medium does not support updating published articles', 'mpcs-hub')
        );
    }
    
    /**
     * Delete Medium post (not supported)
     */
    public function delete($content_data) {
        return new WP_Error(
            'not_supported',
            __('Medium does not support deleting published articles', 'mpcs-hub')
        );
    }
    
    /**
     * Get Medium post analytics (limited data available)
     */
    public function get_analytics($post_id, $metrics = ['views', 'reads', 'claps']) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Medium API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Medium API has limited analytics endpoints
            // Most analytics are only available through Medium Partner Program
            
            // Get basic post information
            $response = $this->make_api_request('GET', "/v1/posts/{$post_id}");
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            // Medium API doesn't provide detailed analytics for individual posts
            // Return basic structure with zero values
            $metrics_data = [
                'views' => 0,
                'reads' => 0,
                'claps' => 0,
                'fans' => 0,
                'note' => 'Medium API provides limited analytics data'
            ];
            
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
            return new WP_Error('not_configured', __('Medium API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', '/v1/me');
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $user_data = $response['data'];
            
            return [
                'success' => true,
                'user_id' => $user_data['id'],
                'username' => $user_data['username'],
                'name' => $user_data['name'],
                'url' => $user_data['url'],
                'message' => 'Medium connection successful'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('connection_failed', $e->getMessage());
        }
    }
    
    /**
     * Upload media to Medium (not directly supported)
     */
    public function upload_media($image_url) {
        // Medium doesn't have a direct media upload API
        // Images need to be hosted externally and referenced by URL
        return new WP_Error(
            'not_supported',
            __('Medium requires externally hosted images', 'mpcs-hub')
        );
    }
    
    /**
     * Format content for Medium
     */
    private function format_content($content_data) {
        $content = $content_data['content'];
        
        // Convert WordPress content to Medium-friendly HTML
        $content = wpautop($content); // Add paragraphs
        
        // Add featured image if available
        if (!empty($content_data['images']) && isset($content_data['images'][0])) {
            $featured_image = $content_data['images'][0];
            $image_html = sprintf(
                '<img src="%s" alt="%s" />',
                esc_url($featured_image['url']),
                esc_attr($featured_image['alt'] ?? '')
            );
            $content = $image_html . "\n\n" . $content;
        }
        
        // Add source attribution if canonical URL is provided
        if (!empty($content_data['canonical_url'])) {
            $source_attribution = sprintf(
                '<p><em>Originally published at <a href="%s">%s</a></em></p>',
                esc_url($content_data['canonical_url']),
                esc_url($content_data['canonical_url'])
            );
            $content .= "\n\n" . $source_attribution;
        }
        
        return $content;
    }
    
    /**
     * Get user's publications
     */
    public function get_publications() {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Medium API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', "/v1/users/{$this->user_id}/publications");
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $response['data'] ?? [];
            
        } catch (Exception $e) {
            return new WP_Error('publications_failed', $e->getMessage());
        }
    }
    
    /**
     * Get user's posts
     */
    public function get_user_posts() {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Medium API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', "/v1/users/{$this->user_id}/posts");
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $response['data'] ?? [];
            
        } catch (Exception $e) {
            return new WP_Error('posts_failed', $e->getMessage());
        }
    }
    
    /**
     * Check if platform is properly configured
     */
    private function is_configured() {
        return !empty($this->access_token) && !empty($this->user_id);
    }
    
    /**
     * Make API request to Medium
     */
    private function make_api_request($method, $endpoint, $data = []) {
        $base_url = 'https://api.medium.com';
        $url = $base_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Accept-Charset' => 'utf-8'
            ]
        ];
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
        } elseif ($method === 'GET' && !empty($data)) {
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
            $error_message = 'Medium API Error: ' . $status_code;
            
            if (isset($decoded_body['errors'][0]['message'])) {
                $error_message .= ' - ' . $decoded_body['errors'][0]['message'];
            }
            
            return new WP_Error('api_error', $error_message);
        }
        
        return $decoded_body;
    }
    
    /**
     * Create draft post
     */
    public function create_draft($content_data) {
        $content_data['status'] = 'draft';
        return $this->publish($content_data);
    }
    
    /**
     * Get publication contributors
     */
    public function get_publication_contributors($publication_id = null) {
        $pub_id = $publication_id ?: $this->publication_id;
        
        if (!$this->is_configured() || !$pub_id) {
            return new WP_Error('not_configured', __('Medium publication not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', "/v1/publications/{$pub_id}/contributors");
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $response['data'] ?? [];
            
        } catch (Exception $e) {
            return new WP_Error('contributors_failed', $e->getMessage());
        }
    }
}
