<?php
/**
 * Twitter Platform Handler
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Platform_Twitter {
    
    private $api_key;
    private $api_secret;
    private $access_token;
    private $access_token_secret;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * Load Twitter API credentials
     */
    private function load_credentials() {
        $config = MPCS_Hub_Database::get_platform_config('twitter');
        
        if ($config && !empty($config->config_data)) {
            $credentials = $config->config_data;
            $this->api_key = $credentials['api_key'] ?? '';
            $this->api_secret = $credentials['api_secret'] ?? '';
            $this->access_token = $credentials['access_token'] ?? '';
            $this->access_token_secret = $credentials['access_token_secret'] ?? '';
        }
    }
    
    /**
     * Publish content to Twitter
     */
    public function publish($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Twitter API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Prepare tweet data
            $tweet_data = [
                'text' => $content_data['platform_content']
            ];
            
            // Add media if available
            if (!empty($content_data['media_ids'])) {
                $tweet_data['media'] = [
                    'media_ids' => $content_data['media_ids']
                ];
            }
            
            // Make API request
            $response = $this->make_api_request('POST', '/2/tweets', $tweet_data);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'external_id' => $response['data']['id'],
                'external_url' => 'https://twitter.com/i/web/status/' . $response['data']['id'],
                'platform' => 'twitter',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return new WP_Error('publish_failed', $e->getMessage());
        }
    }
    
    /**
     * Update existing tweet (not possible on Twitter, so we return error)
     */
    public function update($content_data) {
        return new WP_Error(
            'not_supported',
            __('Twitter does not support updating tweets', 'mpcs-hub')
        );
    }
    
    /**
     * Delete tweet
     */
    public function delete($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Twitter API credentials not configured', 'mpcs-hub'));
        }
        
        if (empty($content_data['external_id'])) {
            return new WP_Error('missing_id', __('Tweet ID is required for deletion', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request(
                'DELETE',
                '/2/tweets/' . $content_data['external_id']
            );
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'deleted' => $response['data']['deleted'],
                'platform' => 'twitter'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('delete_failed', $e->getMessage());
        }
    }
    
    /**
     * Upload media to Twitter
     */
    public function upload_media($image_url) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Twitter API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Download image
            $image_data = wp_remote_get($image_url);
            if (is_wp_error($image_data)) {
                return $image_data;
            }
            
            $image_content = wp_remote_retrieve_body($image_data);
            $image_type = wp_remote_retrieve_header($image_data, 'content-type');
            
            // Upload to Twitter
            $upload_data = [
                'media_data' => base64_encode($image_content),
                'media_type' => $image_type
            ];
            
            $response = $this->make_api_request('POST', '/1.1/media/upload.json', $upload_data);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $response['media_id_string'];
            
        } catch (Exception $e) {
            return new WP_Error('upload_failed', $e->getMessage());
        }
    }
    
    /**
     * Get tweet analytics
     */
    public function get_analytics($tweet_id, $metrics = ['impressions', 'likes', 'retweets', 'replies']) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Twitter API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $endpoint = '/2/tweets/' . $tweet_id . '?tweet.fields=' . implode(',', [
                'public_metrics',
                'organic_metrics',
                'promoted_metrics'
            ]);
            
            $response = $this->make_api_request('GET', $endpoint);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $metrics_data = [];
            
            if (isset($response['data']['public_metrics'])) {
                $public_metrics = $response['data']['public_metrics'];
                $metrics_data['impressions'] = $public_metrics['impression_count'] ?? 0;
                $metrics_data['likes'] = $public_metrics['like_count'] ?? 0;
                $metrics_data['retweets'] = $public_metrics['retweet_count'] ?? 0;
                $metrics_data['replies'] = $public_metrics['reply_count'] ?? 0;
                $metrics_data['quotes'] = $public_metrics['quote_count'] ?? 0;
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
            return new WP_Error('not_configured', __('Twitter API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', '/2/users/me');
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'user_id' => $response['data']['id'],
                'username' => $response['data']['username'],
                'name' => $response['data']['name']
            ];
            
        } catch (Exception $e) {
            return new WP_Error('connection_failed', $e->getMessage());
        }
    }
    
    /**
     * Check if platform is properly configured
     */
    private function is_configured() {
        return !empty($this->api_key) && 
               !empty($this->api_secret) && 
               !empty($this->access_token) && 
               !empty($this->access_token_secret);
    }
    
    /**
     * Make API request to Twitter
     */
    private function make_api_request($method, $endpoint, $data = []) {
        $base_url = 'https://api.twitter.com';
        $url = $base_url . $endpoint;
        
        // Generate OAuth signature
        $oauth_params = $this->generate_oauth_params();
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => $this->build_oauth_header($oauth_params, $method, $url, $data),
                'Content-Type' => 'application/json'
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
            $error_message = 'Twitter API Error: ' . $status_code;
            
            if (isset($decoded_body['errors'][0]['message'])) {
                $error_message .= ' - ' . $decoded_body['errors'][0]['message'];
            } elseif (isset($decoded_body['detail'])) {
                $error_message .= ' - ' . $decoded_body['detail'];
            }
            
            return new WP_Error('api_error', $error_message);
        }
        
        return $decoded_body;
    }
    
    /**
     * Generate OAuth parameters
     */
    private function generate_oauth_params() {
        return [
            'oauth_consumer_key' => $this->api_key,
            'oauth_nonce' => wp_generate_password(32, false),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $this->access_token,
            'oauth_version' => '1.0'
        ];
    }
    
    /**
     * Build OAuth authorization header
     */
    private function build_oauth_header($oauth_params, $method, $url, $data = []) {
        // For OAuth 1.0a signature generation
        $signature_params = array_merge($oauth_params, $data);
        
        // Generate signature base string
        $base_string = $method . '&' . 
                      rawurlencode($url) . '&' . 
                      rawurlencode(http_build_query($signature_params));
        
        // Generate signing key
        $signing_key = rawurlencode($this->api_secret) . '&' . rawurlencode($this->access_token_secret);
        
        // Generate signature
        $oauth_params['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_string, $signing_key, true));
        
        // Build header
        $header_parts = [];
        foreach ($oauth_params as $key => $value) {
            $header_parts[] = $key . '="' . rawurlencode($value) . '"';
        }
        
        return 'OAuth ' . implode(', ', $header_parts);
    }
    
    /**
     * Get rate limit status
     */
    public function get_rate_limit_status() {
        try {
            $response = $this->make_api_request('GET', '/1.1/application/rate_limit_status.json');
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $response['resources'];
            
        } catch (Exception $e) {
            return new WP_Error('rate_limit_check_failed', $e->getMessage());
        }
    }
    
    /**
     * Schedule tweet for later (if using premium features)
     */
    public function schedule_tweet($content_data, $schedule_time) {
        // This would require Twitter's premium/enterprise API
        // For now, we'll store it in our queue system
        return new WP_Error(
            'not_implemented',
            __('Tweet scheduling requires premium API access', 'mpcs-hub')
        );
    }
}
