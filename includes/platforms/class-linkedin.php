<?php
/**
 * LinkedIn Platform Handler
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Platform_Linkedin {
    
    private $client_id;
    private $client_secret;
    private $access_token;
    private $organization_id;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * Load LinkedIn API credentials
     */
    private function load_credentials() {
        $config = MPCS_Hub_Database::get_platform_config('linkedin');
        
        if ($config && !empty($config->config_data)) {
            $credentials = $config->config_data;
            $this->client_id = $credentials['client_id'] ?? '';
            $this->client_secret = $credentials['client_secret'] ?? '';
            $this->access_token = $credentials['access_token'] ?? '';
            $this->organization_id = $credentials['organization_id'] ?? '';
        }
    }
    
    /**
     * Publish content to LinkedIn
     */
    public function publish($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('LinkedIn API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Determine if posting as organization or individual
            $author = $this->organization_id ? 
                "urn:li:organization:{$this->organization_id}" : 
                "urn:li:person:{$this->get_person_id()}";
            
            // Prepare post data
            $post_data = [
                'author' => $author,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $content_data['platform_content']
                        ],
                        'shareMediaCategory' => 'NONE'
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];
            
            // Add media if available
            if (!empty($content_data['images']) && isset($content_data['images'][0])) {
                $media_urn = $this->upload_media($content_data['images'][0]['url']);
                if (!is_wp_error($media_urn)) {
                    $post_data['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'IMAGE';
                    $post_data['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [
                        [
                            'status' => 'READY',
                            'description' => [
                                'text' => $content_data['images'][0]['alt'] ?? ''
                            ],
                            'media' => $media_urn,
                            'title' => [
                                'text' => $content_data['title']
                            ]
                        ]
                    ];
                }
            }
            
            // Make API request
            $response = $this->make_api_request('POST', '/v2/ugcPosts', $post_data);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $post_id = $response['id'];
            $linkedin_url = "https://www.linkedin.com/feed/update/{$post_id}";
            
            return [
                'success' => true,
                'external_id' => $post_id,
                'external_url' => $linkedin_url,
                'platform' => 'linkedin',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return new WP_Error('publish_failed', $e->getMessage());
        }
    }
    
    /**
     * Update existing LinkedIn post (not supported)
     */
    public function update($content_data) {
        return new WP_Error(
            'not_supported',
            __('LinkedIn does not support updating posts', 'mpcs-hub')
        );
    }
    
    /**
     * Delete LinkedIn post
     */
    public function delete($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('LinkedIn API credentials not configured', 'mpcs-hub'));
        }
        
        if (empty($content_data['external_id'])) {
            return new WP_Error('missing_id', __('LinkedIn post ID is required for deletion', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('DELETE', "/v2/ugcPosts/{$content_data['external_id']}");
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'deleted' => true,
                'platform' => 'linkedin'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('delete_failed', $e->getMessage());
        }
    }
    
    /**
     * Get LinkedIn post analytics
     */
    public function get_analytics($post_id, $metrics = ['likes', 'comments', 'shares', 'impressions']) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('LinkedIn API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Get post statistics
            $response = $this->make_api_request('GET', "/v2/socialActions/{$post_id}");
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $metrics_data = [
                'likes' => $response['likesSummary']['totalLikes'] ?? 0,
                'comments' => $response['commentsSummary']['totalComments'] ?? 0,
                'shares' => $response['sharesSummary']['totalShares'] ?? 0,
                'impressions' => 0 // LinkedIn doesn't provide impression data via this endpoint
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
            return new WP_Error('not_configured', __('LinkedIn API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Test by getting user profile
            $response = $this->make_api_request('GET', '/v2/people/~:(id,firstName,lastName)');
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $name = ($response['firstName']['localized']['en_US'] ?? '') . ' ' . 
                    ($response['lastName']['localized']['en_US'] ?? '');
            
            return [
                'success' => true,
                'user_id' => $response['id'],
                'name' => trim($name),
                'message' => 'LinkedIn connection successful'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('connection_failed', $e->getMessage());
        }
    }
    
    /**
     * Upload media to LinkedIn
     */
    public function upload_media($image_url) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('LinkedIn API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Step 1: Register upload
            $person_id = $this->get_person_id();
            $owner = $this->organization_id ? 
                "urn:li:organization:{$this->organization_id}" : 
                "urn:li:person:{$person_id}";
            
            $register_data = [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner' => $owner,
                    'serviceRelationships' => [
                        [
                            'relationshipType' => 'OWNER',
                            'identifier' => 'urn:li:userGeneratedContent'
                        ]
                    ]
                ]
            ];
            
            $register_response = $this->make_api_request('POST', '/v2/assets?action=registerUpload', $register_data);
            
            if (is_wp_error($register_response)) {
                return $register_response;
            }
            
            $upload_url = $register_response['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $asset_id = $register_response['value']['asset'];
            
            // Step 2: Upload image
            $image_data = wp_remote_get($image_url);
            if (is_wp_error($image_data)) {
                return $image_data;
            }
            
            $image_content = wp_remote_retrieve_body($image_data);
            
            $upload_response = wp_remote_post($upload_url, [
                'body' => $image_content,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Content-Type' => 'application/octet-stream'
                ]
            ]);
            
            if (is_wp_error($upload_response)) {
                return $upload_response;
            }
            
            return $asset_id;
            
        } catch (Exception $e) {
            return new WP_Error('upload_failed', $e->getMessage());
        }
    }
    
    /**
     * Get person ID for the authenticated user
     */
    private function get_person_id() {
        static $person_id = null;
        
        if ($person_id === null) {
            $response = $this->make_api_request('GET', '/v2/people/~:(id)');
            if (!is_wp_error($response)) {
                $person_id = $response['id'];
            }
        }
        
        return $person_id;
    }
    
    /**
     * Check if platform is properly configured
     */
    private function is_configured() {
        return !empty($this->client_id) && 
               !empty($this->client_secret) && 
               !empty($this->access_token);
    }
    
    /**
     * Make API request to LinkedIn
     */
    private function make_api_request($method, $endpoint, $data = []) {
        $base_url = 'https://api.linkedin.com';
        $url = $base_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0'
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
        
        // LinkedIn sometimes returns empty body for successful operations
        if (empty($body) && $status_code >= 200 && $status_code < 300) {
            return ['success' => true];
        }
        
        $decoded_body = json_decode($body, true);
        
        if ($status_code >= 400) {
            $error_message = 'LinkedIn API Error: ' . $status_code;
            
            if (isset($decoded_body['message'])) {
                $error_message .= ' - ' . $decoded_body['message'];
            }
            
            return new WP_Error('api_error', $error_message);
        }
        
        return $decoded_body;
    }
    
    /**
     * Get company page statistics (if posting as organization)
     */
    public function get_company_stats() {
        if (!$this->is_configured() || !$this->organization_id) {
            return new WP_Error('not_configured', __('LinkedIn organization not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', "/v2/organizationalEntityFollowerStatistics", [
                'q' => 'organizationalEntity',
                'organizationalEntity' => "urn:li:organization:{$this->organization_id}"
            ]);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $response['elements'] ?? [];
            
        } catch (Exception $e) {
            return new WP_Error('stats_failed', $e->getMessage());
        }
    }
}
