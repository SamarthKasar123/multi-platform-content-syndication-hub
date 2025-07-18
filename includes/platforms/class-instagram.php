<?php
/**
 * Instagram Platform Handler
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Platform_Instagram {
    
    private $access_token;
    private $business_account_id;
    private $app_id;
    private $app_secret;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * Load Instagram API credentials
     */
    private function load_credentials() {
        $config = MPCS_Hub_Database::get_platform_config('instagram');
        
        if ($config && !empty($config->config_data)) {
            $credentials = $config->config_data;
            $this->access_token = $credentials['access_token'] ?? '';
            $this->business_account_id = $credentials['business_account_id'] ?? '';
            $this->app_id = $credentials['app_id'] ?? '';
            $this->app_secret = $credentials['app_secret'] ?? '';
        }
    }
    
    /**
     * Publish content to Instagram
     */
    public function publish($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Instagram API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Instagram requires an image/video
            if (empty($content_data['images']) && empty($content_data['video'])) {
                return new WP_Error('media_required', __('Instagram posts require an image or video', 'mpcs-hub'));
            }
            
            $media_type = !empty($content_data['video']) ? 'VIDEO' : 'IMAGE';
            $media_url = !empty($content_data['video']) ? 
                $content_data['video'] : 
                $content_data['images'][0]['url'];
            
            // Step 1: Create media container
            $container_data = [
                'image_url' => $media_url,
                'caption' => $this->format_caption($content_data),
                'access_token' => $this->access_token
            ];
            
            if ($media_type === 'VIDEO') {
                $container_data['media_type'] = 'VIDEO';
                $container_data['video_url'] = $media_url;
                unset($container_data['image_url']);
            }
            
            $container_response = $this->make_api_request(
                'POST', 
                "/{$this->business_account_id}/media", 
                $container_data
            );
            
            if (is_wp_error($container_response)) {
                return $container_response;
            }
            
            $container_id = $container_response['id'];
            
            // Step 2: Wait for media to be processed (for videos)
            if ($media_type === 'VIDEO') {
                $this->wait_for_media_processing($container_id);
            }
            
            // Step 3: Publish the media
            $publish_response = $this->make_api_request(
                'POST',
                "/{$this->business_account_id}/media_publish",
                [
                    'creation_id' => $container_id,
                    'access_token' => $this->access_token
                ]
            );
            
            if (is_wp_error($publish_response)) {
                return $publish_response;
            }
            
            $media_id = $publish_response['id'];
            $instagram_url = "https://www.instagram.com/p/{$this->get_short_code($media_id)}";
            
            return [
                'success' => true,
                'external_id' => $media_id,
                'external_url' => $instagram_url,
                'platform' => 'instagram',
                'response' => $publish_response
            ];
            
        } catch (Exception $e) {
            return new WP_Error('publish_failed', $e->getMessage());
        }
    }
    
    /**
     * Update existing Instagram post (not supported)
     */
    public function update($content_data) {
        return new WP_Error(
            'not_supported',
            __('Instagram does not support updating posts', 'mpcs-hub')
        );
    }
    
    /**
     * Delete Instagram post
     */
    public function delete($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Instagram API credentials not configured', 'mpcs-hub'));
        }
        
        if (empty($content_data['external_id'])) {
            return new WP_Error('missing_id', __('Instagram media ID is required for deletion', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request(
                'DELETE', 
                "/{$content_data['external_id']}", 
                ['access_token' => $this->access_token]
            );
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'deleted' => true,
                'platform' => 'instagram'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('delete_failed', $e->getMessage());
        }
    }
    
    /**
     * Get Instagram media analytics
     */
    public function get_analytics($media_id, $metrics = ['likes', 'comments', 'shares', 'reach']) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Instagram API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Get media insights
            $insights_response = $this->make_api_request(
                'GET',
                "/{$media_id}/insights",
                [
                    'metric' => 'engagement,impressions,reach,saved',
                    'access_token' => $this->access_token
                ]
            );
            
            if (is_wp_error($insights_response)) {
                return $insights_response;
            }
            
            // Get basic media info
            $media_response = $this->make_api_request(
                'GET',
                "/{$media_id}",
                [
                    'fields' => 'like_count,comments_count,permalink',
                    'access_token' => $this->access_token
                ]
            );
            
            if (is_wp_error($media_response)) {
                return $media_response;
            }
            
            $metrics_data = [
                'likes' => $media_response['like_count'] ?? 0,
                'comments' => $media_response['comments_count'] ?? 0,
                'shares' => 0, // Not available via Instagram Basic Display API
                'reach' => 0,
                'impressions' => 0
            ];
            
            // Parse insights data
            foreach ($insights_response['data'] as $insight) {
                switch ($insight['name']) {
                    case 'reach':
                        $metrics_data['reach'] = $insight['values'][0]['value'] ?? 0;
                        break;
                    case 'impressions':
                        $metrics_data['impressions'] = $insight['values'][0]['value'] ?? 0;
                        break;
                }
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
            return new WP_Error('not_configured', __('Instagram API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request(
                'GET',
                "/{$this->business_account_id}",
                [
                    'fields' => 'id,username,account_type,media_count',
                    'access_token' => $this->access_token
                ]
            );
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'user_id' => $response['id'],
                'username' => $response['username'],
                'account_type' => $response['account_type'],
                'media_count' => $response['media_count'],
                'message' => 'Instagram connection successful'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('connection_failed', $e->getMessage());
        }
    }
    
    /**
     * Upload media to Instagram (handled in publish method)
     */
    public function upload_media($media_url) {
        // Media upload is handled in the publish method for Instagram
        return new WP_Error(
            'not_supported',
            __('Instagram media upload is handled during publishing', 'mpcs-hub')
        );
    }
    
    /**
     * Format caption for Instagram
     */
    private function format_caption($content_data) {
        $caption = $content_data['platform_content'];
        
        // Instagram supports up to 2,200 characters
        if (strlen($caption) > 2200) {
            $caption = substr($caption, 0, 2197) . '...';
        }
        
        // Add hashtags if provided
        if (!empty($content_data['hashtags'])) {
            $hashtags = array_map(function($tag) {
                return '#' . ltrim($tag, '#');
            }, $content_data['hashtags']);
            
            $hashtag_string = "\n\n" . implode(' ', $hashtags);
            
            // Ensure we don't exceed character limit with hashtags
            if (strlen($caption . $hashtag_string) <= 2200) {
                $caption .= $hashtag_string;
            }
        }
        
        return $caption;
    }
    
    /**
     * Wait for video processing to complete
     */
    private function wait_for_media_processing($container_id, $max_attempts = 30) {
        $attempts = 0;
        
        while ($attempts < $max_attempts) {
            $status_response = $this->make_api_request(
                'GET',
                "/{$container_id}",
                [
                    'fields' => 'status_code',
                    'access_token' => $this->access_token
                ]
            );
            
            if (!is_wp_error($status_response)) {
                $status = $status_response['status_code'] ?? '';
                
                if ($status === 'FINISHED') {
                    break;
                } elseif ($status === 'ERROR') {
                    throw new Exception('Video processing failed');
                }
            }
            
            sleep(2); // Wait 2 seconds before checking again
            $attempts++;
        }
        
        if ($attempts >= $max_attempts) {
            throw new Exception('Video processing timeout');
        }
    }
    
    /**
     * Get short code from media ID for Instagram URL
     */
    private function get_short_code($media_id) {
        $response = $this->make_api_request(
            'GET',
            "/{$media_id}",
            [
                'fields' => 'permalink',
                'access_token' => $this->access_token
            ]
        );
        
        if (!is_wp_error($response) && !empty($response['permalink'])) {
            // Extract short code from permalink
            preg_match('/\/p\/([^\/]+)/', $response['permalink'], $matches);
            return $matches[1] ?? $media_id;
        }
        
        return $media_id;
    }
    
    /**
     * Check if platform is properly configured
     */
    private function is_configured() {
        return !empty($this->access_token) && 
               !empty($this->business_account_id) && 
               !empty($this->app_id);
    }
    
    /**
     * Make API request to Instagram
     */
    private function make_api_request($method, $endpoint, $data = []) {
        $base_url = 'https://graph.facebook.com/v18.0';
        $url = $base_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => 60, // Longer timeout for video uploads
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ];
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
            $args['headers']['Content-Type'] = 'application/json';
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        } elseif ($method === 'DELETE' && !empty($data)) {
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
            $error_message = 'Instagram API Error: ' . $status_code;
            
            if (isset($decoded_body['error']['message'])) {
                $error_message .= ' - ' . $decoded_body['error']['message'];
            }
            
            return new WP_Error('api_error', $error_message);
        }
        
        return $decoded_body;
    }
    
    /**
     * Get account insights
     */
    public function get_account_insights() {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Instagram API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request(
                'GET',
                "/{$this->business_account_id}/insights",
                [
                    'metric' => 'follower_count,reach,impressions,profile_views',
                    'period' => 'day',
                    'access_token' => $this->access_token
                ]
            );
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $response['data'] ?? [];
            
        } catch (Exception $e) {
            return new WP_Error('insights_failed', $e->getMessage());
        }
    }
}
