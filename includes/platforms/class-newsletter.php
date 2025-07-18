<?php
/**
 * Newsletter Platform Handler (Mailchimp)
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Platform_Newsletter {
    
    private $api_key;
    private $server_prefix;
    private $list_id;
    private $template_id;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * Load Newsletter API credentials
     */
    private function load_credentials() {
        $config = MPCS_Hub_Database::get_platform_config('newsletter');
        
        if ($config && !empty($config->config_data)) {
            $credentials = $config->config_data;
            $this->api_key = $credentials['api_key'] ?? '';
            $this->list_id = $credentials['list_id'] ?? '';
            $this->template_id = $credentials['template_id'] ?? '';
            
            // Extract server prefix from API key
            if ($this->api_key) {
                $parts = explode('-', $this->api_key);
                $this->server_prefix = end($parts);
            }
        }
    }
    
    /**
     * Publish content as newsletter campaign
     */
    public function publish($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Newsletter API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            // Create campaign
            $campaign_data = [
                'type' => 'regular',
                'recipients' => [
                    'list_id' => $this->list_id
                ],
                'settings' => [
                    'subject_line' => $content_data['title'],
                    'preview_text' => $content_data['excerpt'] ?? substr(strip_tags($content_data['content']), 0, 150),
                    'title' => $content_data['title'],
                    'from_name' => get_bloginfo('name'),
                    'reply_to' => get_option('admin_email'),
                    'auto_footer' => false,
                    'inline_css' => true
                ]
            ];
            
            $campaign_response = $this->make_api_request('POST', '/campaigns', $campaign_data);
            
            if (is_wp_error($campaign_response)) {
                return $campaign_response;
            }
            
            $campaign_id = $campaign_response['id'];
            
            // Set campaign content
            $email_content = $this->format_email_content($content_data);
            
            $content_response = $this->make_api_request(
                'PUT', 
                "/campaigns/{$campaign_id}/content",
                ['html' => $email_content]
            );
            
            if (is_wp_error($content_response)) {
                return $content_response;
            }
            
            // Send campaign (or save as draft based on settings)
            if ($content_data['send_immediately'] ?? false) {
                $send_response = $this->make_api_request('POST', "/campaigns/{$campaign_id}/actions/send");
                
                if (is_wp_error($send_response)) {
                    return $send_response;
                }
                
                $status = 'sent';
                $campaign_url = $this->get_campaign_archive_url($campaign_id);
            } else {
                $status = 'draft';
                $campaign_url = "https://{$this->server_prefix}.admin.mailchimp.com/campaigns/edit?id={$campaign_id}";
            }
            
            return [
                'success' => true,
                'external_id' => $campaign_id,
                'external_url' => $campaign_url,
                'platform' => 'newsletter',
                'status' => $status,
                'response' => $campaign_response
            ];
            
        } catch (Exception $e) {
            return new WP_Error('publish_failed', $e->getMessage());
        }
    }
    
    /**
     * Update existing newsletter campaign
     */
    public function update($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Newsletter API credentials not configured', 'mpcs-hub'));
        }
        
        if (empty($content_data['external_id'])) {
            return new WP_Error('missing_id', __('Campaign ID is required for updates', 'mpcs-hub'));
        }
        
        try {
            $campaign_id = $content_data['external_id'];
            
            // Check campaign status first
            $campaign_info = $this->make_api_request('GET', "/campaigns/{$campaign_id}");
            
            if (is_wp_error($campaign_info)) {
                return $campaign_info;
            }
            
            if ($campaign_info['status'] === 'sent') {
                return new WP_Error('campaign_sent', __('Cannot update sent campaigns', 'mpcs-hub'));
            }
            
            // Update campaign settings
            $update_data = [
                'settings' => [
                    'subject_line' => $content_data['title'],
                    'preview_text' => $content_data['excerpt'] ?? substr(strip_tags($content_data['content']), 0, 150),
                    'title' => $content_data['title']
                ]
            ];
            
            $update_response = $this->make_api_request('PATCH', "/campaigns/{$campaign_id}", $update_data);
            
            if (is_wp_error($update_response)) {
                return $update_response;
            }
            
            // Update campaign content
            $email_content = $this->format_email_content($content_data);
            
            $content_response = $this->make_api_request(
                'PUT', 
                "/campaigns/{$campaign_id}/content",
                ['html' => $email_content]
            );
            
            if (is_wp_error($content_response)) {
                return $content_response;
            }
            
            return [
                'success' => true,
                'updated' => true,
                'external_id' => $campaign_id,
                'platform' => 'newsletter',
                'response' => $update_response
            ];
            
        } catch (Exception $e) {
            return new WP_Error('update_failed', $e->getMessage());
        }
    }
    
    /**
     * Delete newsletter campaign
     */
    public function delete($content_data) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Newsletter API credentials not configured', 'mpcs-hub'));
        }
        
        if (empty($content_data['external_id'])) {
            return new WP_Error('missing_id', __('Campaign ID is required for deletion', 'mpcs-hub'));
        }
        
        try {
            $campaign_id = $content_data['external_id'];
            
            // Check campaign status first
            $campaign_info = $this->make_api_request('GET', "/campaigns/{$campaign_id}");
            
            if (is_wp_error($campaign_info)) {
                return $campaign_info;
            }
            
            if ($campaign_info['status'] === 'sent') {
                return new WP_Error('campaign_sent', __('Cannot delete sent campaigns', 'mpcs-hub'));
            }
            
            $response = $this->make_api_request('DELETE', "/campaigns/{$campaign_id}");
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'deleted' => true,
                'platform' => 'newsletter'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('delete_failed', $e->getMessage());
        }
    }
    
    /**
     * Get newsletter campaign analytics
     */
    public function get_analytics($campaign_id, $metrics = ['opens', 'clicks', 'bounces', 'unsubscribes']) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Newsletter API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', "/reports/{$campaign_id}");
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $metrics_data = [
                'emails_sent' => $response['emails_sent'] ?? 0,
                'opens' => $response['opens']['opens_total'] ?? 0,
                'unique_opens' => $response['opens']['unique_opens'] ?? 0,
                'open_rate' => $response['opens']['open_rate'] ?? 0,
                'clicks' => $response['clicks']['clicks_total'] ?? 0,
                'unique_clicks' => $response['clicks']['unique_clicks'] ?? 0,
                'click_rate' => $response['clicks']['click_rate'] ?? 0,
                'bounces' => $response['bounces']['hard_bounces'] + $response['bounces']['soft_bounces'] ?? 0,
                'unsubscribes' => $response['unsubscribed']['unsubscribes'] ?? 0,
                'spam_reports' => $response['abuse_reports'] ?? 0
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
            return new WP_Error('not_configured', __('Newsletter API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', '/');
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return [
                'success' => true,
                'account_name' => $response['account_name'],
                'account_id' => $response['account_id'],
                'email' => $response['email'],
                'total_subscribers' => $response['total_subscribers'],
                'message' => 'Newsletter connection successful'
            ];
            
        } catch (Exception $e) {
            return new WP_Error('connection_failed', $e->getMessage());
        }
    }
    
    /**
     * Upload media to newsletter (not applicable)
     */
    public function upload_media($image_url) {
        // Mailchimp doesn't require media upload - images are referenced by URL
        return $image_url;
    }
    
    /**
     * Format content for email newsletter
     */
    private function format_email_content($content_data) {
        $content = $content_data['content'];
        
        // Use template if available
        if ($this->template_id) {
            $template = $this->get_template_content($this->template_id);
            if (!is_wp_error($template)) {
                // Replace placeholders in template
                $email_content = str_replace(
                    ['{{TITLE}}', '{{CONTENT}}', '{{DATE}}'],
                    [$content_data['title'], $content, date('F j, Y')],
                    $template['html']
                );
            } else {
                $email_content = $this->create_basic_email_template($content_data);
            }
        } else {
            $email_content = $this->create_basic_email_template($content_data);
        }
        
        return $email_content;
    }
    
    /**
     * Create basic email template
     */
    private function create_basic_email_template($content_data) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $featured_image = '';
        
        if (!empty($content_data['images']) && isset($content_data['images'][0])) {
            $featured_image = sprintf(
                '<img src="%s" alt="%s" style="max-width: 100%%; height: auto; margin-bottom: 20px;" />',
                esc_url($content_data['images'][0]['url']),
                esc_attr($content_data['images'][0]['alt'] ?? '')
            );
        }
        
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($content_data['title']) . '</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <header style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 20px;">
                <h1 style="color: #2c3e50; margin: 0;">' . esc_html($site_name) . '</h1>
                <p style="color: #7f8c8d; margin: 5px 0 0 0;">Newsletter</p>
            </header>
            
            <main>
                <h2 style="color: #2c3e50; margin-bottom: 20px;">' . esc_html($content_data['title']) . '</h2>
                
                ' . $featured_image . '
                
                <div style="margin-bottom: 30px;">
                    ' . wpautop($content_data['content']) . '
                </div>
                
                ' . (!empty($content_data['canonical_url']) ? 
                    '<p style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-left: 4px solid #007cba;">
                        <a href="' . esc_url($content_data['canonical_url']) . '" style="color: #007cba; text-decoration: none;">
                            Read the full article on our website →
                        </a>
                    </p>' : '') . '
            </main>
            
            <footer style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #7f8c8d; font-size: 14px;">
                <p>You received this email because you are subscribed to updates from <a href="' . esc_url($site_url) . '" style="color: #007cba;">' . esc_html($site_name) . '</a></p>
                <p>
                    <a href="*|UPDATE_PROFILE|*" style="color: #007cba;">Update preferences</a> | 
                    <a href="*|UNSUB|*" style="color: #007cba;">Unsubscribe</a>
                </p>
            </footer>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Get template content
     */
    private function get_template_content($template_id) {
        try {
            return $this->make_api_request('GET', "/templates/{$template_id}");
        } catch (Exception $e) {
            return new WP_Error('template_failed', $e->getMessage());
        }
    }
    
    /**
     * Get campaign archive URL
     */
    private function get_campaign_archive_url($campaign_id) {
        try {
            $response = $this->make_api_request('GET', "/campaigns/{$campaign_id}");
            return $response['archive_url'] ?? '';
        } catch (Exception $e) {
            return '';
        }
    }
    
    /**
     * Get mailing lists
     */
    public function get_lists() {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Newsletter API credentials not configured', 'mpcs-hub'));
        }
        
        try {
            $response = $this->make_api_request('GET', '/lists');
            return $response['lists'] ?? [];
        } catch (Exception $e) {
            return new WP_Error('lists_failed', $e->getMessage());
        }
    }
    
    /**
     * Check if platform is properly configured
     */
    private function is_configured() {
        return !empty($this->api_key) && 
               !empty($this->server_prefix) && 
               !empty($this->list_id);
    }
    
    /**
     * Make API request to Mailchimp
     */
    private function make_api_request($method, $endpoint, $data = []) {
        $base_url = "https://{$this->server_prefix}.api.mailchimp.com/3.0";
        $url = $base_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('anystring:' . $this->api_key),
                'Content-Type' => 'application/json'
            ]
        ];
        
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
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
        
        // Handle empty response for successful DELETE requests
        if ($method === 'DELETE' && $status_code === 204) {
            return ['success' => true];
        }
        
        $decoded_body = json_decode($body, true);
        
        if ($status_code >= 400) {
            $error_message = 'Newsletter API Error: ' . $status_code;
            
            if (isset($decoded_body['detail'])) {
                $error_message .= ' - ' . $decoded_body['detail'];
            }
            
            return new WP_Error('api_error', $error_message);
        }
        
        return $decoded_body;
    }
}
