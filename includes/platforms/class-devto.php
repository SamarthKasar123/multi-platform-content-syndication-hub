<?php
/**
 * Dev.to Platform Handler
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Platform_Devto {
    
    private $api_key;
    private $user_id;
    private $organization_id;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * Load Dev.to API credentials
     */
    private function load_credentials() {
        $config = MPCS_Hub_Database::get_platform_config('devto');
        
        if ($config && !empty($config->config_data)) {
            $credentials = $config->config_data;
            $this->api_key = $credentials['api_key'] ?? '';
            $this->user_id = $credentials['user_id'] ?? '';
            $this->organization_id = $credentials['organization_id'] ?? '';
        }
    }
    
    /**
     * Publish content to Dev.to
     */
    public function publish($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Dev.to API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Prepare article data
            $article_data = [
                'article' => [
                    'title' => $content_data['title'],
                    'body_markdown' => $this->format_content($content_data),
                    'published' => $content_data['published'] ?? true,
                    'series' => $content_data['series'] ?? null,
                    'main_image' => $content_data['images'][0]['url'] ?? null,
                    'description' => $content_data['excerpt'] ?? null
                ]
            ];
            
            // Add tags if provided
            if (!empty($content_data['hashtags'])) {
                $tags = array_map(function($tag) {
                    return ltrim($tag, '#');
                }, array_slice($content_data['hashtags'], 0, 4)); // Dev.to allows max 4 tags
                
                $article_data['article']['tags'] = $tags;
            }
            
            // Add canonical URL if provided
            if (!empty($content_data['canonical_url'])) {
                $article_data['article']['canonical_url'] = $content_data['canonical_url'];
            }
            
            // Add organization if configured
            if (!empty($this->organization_id)) {
                $article_data['article']['organization_id'] = $this->organization_id;
            }
            
            // Make API request
            $response = $this->make_api_request('POST', '/articles', $article_data);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $article_id = $response['id'];
            $devto_url = $response['url'];
            
            return [
                'success' => true,
                'external_id' => $article_id,
                'external_url' => $devto_url,
                'platform' => 'devto',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return new WP_Error('publish_failed', $e->getMessage());
        }
    }
    
    /**
     * Update existing Dev.to article
     */
    public function update($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Dev.to API credentials not configured', 'mpcs-hub'));
        }
        
        if (empty($content_data['external_id'])) {
            return new WP_Error('missing_id', __('Dev.to article ID is required for updates', 'mpcs-hub'));
        }
        
        try {
            // Prepare updated article data
            $article_data = [
                'article' => [
                    'title' => $content_data['title'],
                    'body_markdown' => $this->format_content($content_data),
                    'published' => $content_data['published'] ?? true,
                    'series' => $content_data['series'] ?? null,
                    'main_image' => $content_data['images'][0]['url'] ?? null,
                    'description' => $content_data['excerpt'] ?? null
                ]
            ];
            
            // Add tags if provided
            if (!empty($content_data['hashtags'])) {
                $tags = array_map(function($tag) {
                    return ltrim($tag, '#');
                }, array_slice($content_data['hashtags'], 0, 4));
                
                $article_data['article']['tags'] = $tags;
            }
            
            // Add canonical URL if provided
            if (!empty($content_data['canonical_url'])) {
                $article_data['article']['canonical_url'] = $content_data['canonical_url'];
            }
            
            $response = $this->make_api_request(
                'PUT', 
                "/articles/{$content_data['external_id']}", 
                $article_data
            );
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'updated' => true,
                'external_id' => $response['id'],
                'external_url' => $response['url'],
                'platform' => 'devto',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return new WP_Error('update_failed', $e->getMessage());
        }
    }
    
    /**
     * Delete Dev.to article (unpublish)
     */
    public function delete($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Dev.to API credentials not configured', 'mpcs-hub'));
        }
        
        if (empty($content_data['external_id'])) {
            return new WP_Error('missing_id', __('Dev.to article ID is required for deletion', 'mpcs-hub'));
        }
        
        try {
            // Dev.to doesn't have a direct delete endpoint, so we unpublish instead
            $article_data = [
                'article' => [
                    'published' => false
                ]
            ];
            
            $response = $this->make_api_request(
                'PUT', 
                "/articles/{$content_data['external_id']}", 
                $article_data
            );
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'deleted' => true,
                'unpublished' => true,
                'platform' => 'devto',
                'note' => 'Article unpublished (Dev.to does not support permanent deletion)'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('delete_failed', $e->getMessage());
        }
    }
    
    /**
     * Get Dev.to article analytics
     */
    public function get_analytics($article_id, $metrics = ['views', 'reactions', 'comments']) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Dev.to API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Get article details
            $response = $this->make_api_request('GET', "/articles/{$article_id}");
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $metrics_data = [
                'views' => $response['page_views_count'] ?? 0,
                'reactions' => $response['public_reactions_count'] ?? 0,
                'comments' => $response['comments_count'] ?? 0,
                'positive_reactions' => $response['positive_reactions_count'] ?? 0,
                'reading_time' => $response['reading_time_minutes'] ?? 0
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
            return new WP_Error('not_configured', __('Dev.to API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', '/users/me');
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'user_id' => $response['id'],
                'username' => $response['username'],
                'name' => $response['name'],
                'profile_image' => $response['profile_image'],
                'message' => 'Dev.to connection successful'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('connection_failed', $e->getMessage());
        }
    }
    
    /**
     * Upload media to Dev.to (not directly supported)
     */
    public function upload_media($image_url) {
        // Dev.to doesn't have a direct media upload API
        // Images need to be hosted externally and referenced by URL
        return new WP_Error(
            'not_supported',
            __('Dev.to requires externally hosted images', 'mpcs-hub')
        );
    }
    
    /**
     * Format content for Dev.to (Markdown)
     */
    private function format_content($content_data) {
        $content = $content_data['content'];
        
        // Convert HTML to Markdown if needed
        if (strpos($content, '<') !== false) {
            // Basic HTML to Markdown conversion
            $content = $this->html_to_markdown($content);
        }
        
        // Add front matter if not present
        if (strpos($content, '---') !== 0) {
            $frontmatter = "---\n";
            $frontmatter .= "title: " . $content_data['title'] . "\n";
            $frontmatter .= "published: " . ($content_data['published'] ?? 'true') . "\n";
            
            if (!empty($content_data['excerpt'])) {
                $frontmatter .= "description: " . $content_data['excerpt'] . "\n";
            }
            
            if (!empty($content_data['hashtags'])) {
                $tags = array_map(function($tag) {
                    return ltrim($tag, '#');
                }, array_slice($content_data['hashtags'], 0, 4));
                $frontmatter .= "tags: " . implode(', ', $tags) . "\n";
            }
            
            if (!empty($content_data['canonical_url'])) {
                $frontmatter .= "canonical_url: " . $content_data['canonical_url'] . "\n";
            }
            
            $frontmatter .= "---\n\n";
            $content = $frontmatter . $content;
        }
        
        return $content;
    }
    
    /**
     * Basic HTML to Markdown conversion
     */
    private function html_to_markdown($html) {
        // Basic conversion - in production, consider using a proper HTML to Markdown library
        $markdown = $html;
        
        // Convert common HTML elements to Markdown
        $conversions = [
            '/<h1[^>]*>(.*?)<\/h1>/i' => '# $1',
            '/<h2[^>]*>(.*?)<\/h2>/i' => '## $1',
            '/<h3[^>]*>(.*?)<\/h3>/i' => '### $1',
            '/<h4[^>]*>(.*?)<\/h4>/i' => '#### $1',
            '/<h5[^>]*>(.*?)<\/h5>/i' => '##### $1',
            '/<h6[^>]*>(.*?)<\/h6>/i' => '###### $1',
            '/<strong[^>]*>(.*?)<\/strong>/i' => '**$1**',
            '/<b[^>]*>(.*?)<\/b>/i' => '**$1**',
            '/<em[^>]*>(.*?)<\/em>/i' => '*$1*',
            '/<i[^>]*>(.*?)<\/i>/i' => '*$1*',
            '/<code[^>]*>(.*?)<\/code>/i' => '`$1`',
            '/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/i' => '[$2]($1)',
            '/<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>/i' => '![$2]($1)',
            '/<img[^>]*src="([^"]*)"[^>]*>/i' => '![]($1)',
            '/<p[^>]*>(.*?)<\/p>/i' => '$1' . "\n\n",
            '/<br[^>]*>/i' => "\n",
            '/<\/?(ul|ol|li)[^>]*>/i' => '',
        ];
        
        foreach ($conversions as $pattern => $replacement) {
            $markdown = preg_replace($pattern, $replacement, $markdown);
        }
        
        // Clean up extra whitespace
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
        $markdown = trim($markdown);
        
        return $markdown;
    }
    
    /**
     * Get user's articles
     */
    public function get_user_articles($page = 1, $per_page = 30) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Dev.to API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', '/articles/me', [
                'page' => $page,
                'per_page' => $per_page
            ]);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $response;
            
        } catch (Exception $e) {
            return new WP_Error('articles_failed', $e->getMessage());
        }
    }
    
    /**
     * Get organization articles
     */
    public function get_organization_articles($page = 1, $per_page = 30) {
        if (!$this->is_configured() || !$this->organization_id) {
            return new WP_Error('not_configured', __('Dev.to organization not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', '/organizations/' . $this->organization_id . '/articles', [
                'page' => $page,
                'per_page' => $per_page
            ]);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $response;
            
        } catch (Exception $e) {
            return new WP_Error('org_articles_failed', $e->getMessage());
        }
    }
    
    /**
     * Check if platform is properly configured
     */
    private function is_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * Make API request to Dev.to
     */
    private function make_api_request($method, $endpoint, $data = []) {
        $base_url = 'https://dev.to/api';
        $url = $base_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'api-key' => $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/vnd.forem.api-v1+json'
            ]
        ];
        
        if ($method === 'POST' || $method === 'PUT') {
            if (!empty($data)) {
                $args['body'] = json_encode($data);
            }
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
            $error_message = 'Dev.to API Error: ' . $status_code;
            
            if (isset($decoded_body['error'])) {
                $error_message .= ' - ' . $decoded_body['error'];
            } elseif (isset($decoded_body['message'])) {
                $error_message .= ' - ' . $decoded_body['message'];
            }
            
            return new WP_Error('api_error', $error_message);
        }
        
        return $decoded_body;
    }
    
    /**
     * Create draft article
     */
    public function create_draft($content_data) {
        $content_data['published'] = false;
        return $this->publish($content_data);
    }
}
