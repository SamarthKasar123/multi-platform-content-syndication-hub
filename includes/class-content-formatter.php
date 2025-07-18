<?php
/**
 * Content Formatter class - handles platform-specific content formatting
 *
 * @package MPCS_Hub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPCS_Hub_Content_Formatter {
    
    /**
     * Platform configuration
     */
    private $platform;
    private $config;
    
    /**
     * Constructor
     */
    public function __construct($platform, $config = null) {
        $this->platform = $platform;
        $this->config = $config ?: MPCS_Hub_Database::get_platform_config($platform);
    }
    
    /**
     * Format post for specific platform
     */
    public function format_post($post) {
        if (!is_object($post)) {
            $post = get_post($post);
        }
        
        if (!$post) {
            return new WP_Error('invalid_post', __('Invalid post', 'mpcs-hub'));
        }
        
        $method = 'format_for_' . $this->platform;
        
        if (method_exists($this, $method)) {
            return $this->$method($post);
        }
        
        // Fallback to generic formatting
        return $this->format_generic($post);
    }
    
    /**
     * Generic content formatting
     */
    private function format_generic($post) {
        $title = $this->format_title($post->post_title);
        $content = $this->format_content($post->post_content);
        $excerpt = $this->format_excerpt($post);
        $images = $this->extract_images($post);
        $hashtags = $this->extract_hashtags($post);
        
        return [
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'images' => $images,
            'hashtags' => $hashtags,
            'url' => get_permalink($post->ID),
            'post_id' => $post->ID,
            'author' => get_the_author_meta('display_name', $post->post_author),
            'published_date' => $post->post_date,
            'categories' => wp_get_post_categories($post->ID, ['fields' => 'names']),
            'tags' => wp_get_post_tags($post->ID, ['fields' => 'names'])
        ];
    }
    
    /**
     * Format for Facebook
     */
    private function format_for_facebook($post) {
        $base_data = $this->format_generic($post);
        
        // Facebook-specific formatting
        $content = $this->strip_html_preserve_links($base_data['content']);
        
        // Combine title and content for Facebook
        $facebook_text = $base_data['title'] . "\n\n" . $content;
        
        // Truncate if needed (Facebook limit: ~63,206 characters)
        if (strlen($facebook_text) > 60000) {
            $facebook_text = substr($facebook_text, 0, 59950) . '... [Read more]';
        }
        
        // Add hashtags at the end
        if (!empty($base_data['hashtags'])) {
            $hashtag_text = "\n\n" . implode(' ', array_map(function($tag) {
                return '#' . str_replace(' ', '', $tag);
            }, array_slice($base_data['hashtags'], 0, 30))); // Facebook limit: 30 hashtags
            
            $facebook_text .= $hashtag_text;
        }
        
        return array_merge($base_data, [
            'platform_content' => $facebook_text,
            'message' => $facebook_text,
            'link' => $base_data['url'],
            'published' => false // Set to true for immediate publishing
        ]);
    }
    
    /**
     * Format for Twitter/X
     */
    private function format_for_twitter($post) {
        $base_data = $this->format_generic($post);
        
        $title = $base_data['title'];
        $url = $base_data['url'];
        
        // Twitter character limit considerations
        $url_length = 23; // Twitter's t.co URL length
        $hashtag_space = 50; // Reserve space for hashtags
        $available_chars = 280 - $url_length - $hashtag_space - 4; // 4 for spaces and newlines
        
        // Create tweet content
        if (strlen($title) <= $available_chars) {
            $tweet_text = $title;
        } else {
            $tweet_text = substr($title, 0, $available_chars - 3) . '...';
        }
        
        // Add hashtags (max 2-3 for readability)
        $hashtags = array_slice($base_data['hashtags'], 0, 3);
        if (!empty($hashtags)) {
            $hashtag_text = ' ' . implode(' ', array_map(function($tag) {
                return '#' . str_replace([' ', '-'], '', ucwords($tag));
            }, $hashtags));
            
            // Check if hashtags fit
            if (strlen($tweet_text . $hashtag_text . ' ' . $url) <= 280) {
                $tweet_text .= $hashtag_text;
            }
        }
        
        $tweet_text .= "\n\n" . $url;
        
        return array_merge($base_data, [
            'platform_content' => $tweet_text,
            'text' => $tweet_text,
            'media_ids' => $this->prepare_twitter_media($base_data['images'])
        ]);
    }
    
    /**
     * Format for LinkedIn
     */
    private function format_for_linkedin($post) {
        $base_data = $this->format_generic($post);
        
        // LinkedIn prefers professional tone
        $content = $this->strip_html_preserve_links($base_data['content']);
        
        // Create LinkedIn post
        $linkedin_text = $base_data['title'] . "\n\n" . $content;
        
        // LinkedIn limit: 3000 characters
        if (strlen($linkedin_text) > 2800) {
            $linkedin_text = substr($linkedin_text, 0, 2750) . '...\n\nRead the full article: ' . $base_data['url'];
        } else {
            $linkedin_text .= "\n\n" . $base_data['url'];
        }
        
        // Add relevant hashtags
        if (!empty($base_data['hashtags'])) {
            $hashtags = array_slice($base_data['hashtags'], 0, 5);
            $hashtag_text = "\n\n" . implode(' ', array_map(function($tag) {
                return '#' . str_replace(' ', '', ucwords($tag));
            }, $hashtags));
            
            $linkedin_text .= $hashtag_text;
        }
        
        return array_merge($base_data, [
            'platform_content' => $linkedin_text,
            'text' => $linkedin_text,
            'visibility' => 'PUBLIC'
        ]);
    }
    
    /**
     * Format for Medium
     */
    private function format_for_medium($post) {
        $base_data = $this->format_generic($post);
        
        // Medium supports full HTML
        $content = $this->format_content_for_medium($base_data['content']);
        
        return array_merge($base_data, [
            'title' => $base_data['title'],
            'content' => $content,
            'contentFormat' => 'html',
            'publishStatus' => 'draft', // Start as draft
            'tags' => array_slice($base_data['tags'], 0, 5), // Medium allows up to 5 tags
            'canonicalUrl' => $base_data['url'] // Set canonical URL to original post
        ]);
    }
    
    /**
     * Format for Dev.to
     */
    private function format_for_dev_to($post) {
        $base_data = $this->format_generic($post);
        
        // Dev.to uses Markdown
        $content = $this->convert_html_to_markdown($base_data['content']);
        
        // Add canonical URL
        $content = "---\ncanonical_url: " . $base_data['url'] . "\n---\n\n" . $content;
        
        return array_merge($base_data, [
            'title' => $base_data['title'],
            'body_markdown' => $content,
            'published' => false, // Start as draft
            'series' => null,
            'main_image' => !empty($base_data['images']) ? $base_data['images'][0]['url'] : null,
            'canonical_url' => $base_data['url'],
            'description' => $base_data['excerpt'],
            'tags' => array_slice($base_data['tags'], 0, 4) // Dev.to allows up to 4 tags
        ]);
    }
    
    /**
     * Format for Newsletter platforms (Mailchimp, ConvertKit, etc.)
     */
    private function format_for_newsletter($post, $platform = 'mailchimp') {
        $base_data = $this->format_generic($post);
        
        // Newsletter-specific formatting
        $content = $this->format_content_for_email($base_data['content']);
        
        return array_merge($base_data, [
            'subject_line' => $base_data['title'],
            'preview_text' => $base_data['excerpt'],
            'html_content' => $content,
            'text_content' => strip_tags($content),
            'from_name' => get_bloginfo('name'),
            'reply_to' => get_option('admin_email')
        ]);
    }
    
    /**
     * Format title with length constraints
     */
    private function format_title($title, $max_length = null) {
        $title = wp_strip_all_tags($title);
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        
        if ($max_length && strlen($title) > $max_length) {
            $title = substr($title, 0, $max_length - 3) . '...';
        }
        
        return $title;
    }
    
    /**
     * Format content
     */
    private function format_content($content) {
        // Apply WordPress content filters
        $content = apply_filters('the_content', $content);
        
        // Remove shortcodes that might not work on external platforms
        $content = strip_shortcodes($content);
        
        // Clean up excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Format excerpt
     */
    private function format_excerpt($post, $length = 160) {
        $excerpt = '';
        
        if (!empty($post->post_excerpt)) {
            $excerpt = $post->post_excerpt;
        } else {
            $excerpt = wp_trim_words(strip_tags($post->post_content), 30, '...');
        }
        
        $excerpt = wp_strip_all_tags($excerpt);
        
        if (strlen($excerpt) > $length) {
            $excerpt = substr($excerpt, 0, $length - 3) . '...';
        }
        
        return $excerpt;
    }
    
    /**
     * Extract images from post
     */
    private function extract_images($post) {
        $images = [];
        
        // Featured image
        $featured_image_id = get_post_thumbnail_id($post->ID);
        if ($featured_image_id) {
            $image_data = $this->get_image_data($featured_image_id);
            if ($image_data) {
                $images[] = $image_data;
            }
        }
        
        // Images from content
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches);
        if (!empty($matches[1])) {
            foreach (array_slice($matches[1], 0, 10) as $src) { // Limit to 10 images
                if (!in_array($src, array_column($images, 'url'))) {
                    $images[] = [
                        'url' => $src,
                        'alt' => '',
                        'width' => null,
                        'height' => null
                    ];
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Get image data from attachment ID
     */
    private function get_image_data($attachment_id) {
        $image_url = wp_get_attachment_image_url($attachment_id, 'large');
        if (!$image_url) {
            return null;
        }
        
        $metadata = wp_get_attachment_metadata($attachment_id);
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        
        return [
            'id' => $attachment_id,
            'url' => $image_url,
            'alt' => $alt_text,
            'width' => $metadata['width'] ?? null,
            'height' => $metadata['height'] ?? null,
            'mime_type' => get_post_mime_type($attachment_id)
        ];
    }
    
    /**
     * Extract hashtags from post
     */
    private function extract_hashtags($post) {
        $hashtags = [];
        
        // From categories
        $categories = wp_get_post_categories($post->ID, ['fields' => 'names']);
        foreach ($categories as $category) {
            $hashtags[] = sanitize_title($category);
        }
        
        // From tags
        $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);
        foreach ($tags as $tag) {
            $hashtags[] = sanitize_title($tag);
        }
        
        // From custom meta
        $custom_hashtags = get_post_meta($post->ID, '_mpcs_custom_hashtags', true);
        if (!empty($custom_hashtags)) {
            $custom_hashtags = explode(',', $custom_hashtags);
            foreach ($custom_hashtags as $hashtag) {
                $hashtags[] = sanitize_title(trim($hashtag));
            }
        }
        
        // Remove duplicates and empty values
        $hashtags = array_unique(array_filter($hashtags));
        
        return $hashtags;
    }
    
    /**
     * Strip HTML but preserve links
     */
    private function strip_html_preserve_links($content) {
        // Convert links to plain text format
        $content = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', '$2 ($1)', $content);
        
        // Strip remaining HTML
        $content = wp_strip_all_tags($content);
        
        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        
        return $content;
    }
    
    /**
     * Convert HTML to Markdown (basic conversion)
     */
    private function convert_html_to_markdown($html) {
        // Basic HTML to Markdown conversion
        $markdown = $html;
        
        // Headers
        $markdown = preg_replace('/<h1[^>]*>(.*?)<\/h1>/i', '# $1', $markdown);
        $markdown = preg_replace('/<h2[^>]*>(.*?)<\/h2>/i', '## $1', $markdown);
        $markdown = preg_replace('/<h3[^>]*>(.*?)<\/h3>/i', '### $1', $markdown);
        
        // Bold and italic
        $markdown = preg_replace('/<strong[^>]*>(.*?)<\/strong>/i', '**$1**', $markdown);
        $markdown = preg_replace('/<b[^>]*>(.*?)<\/b>/i', '**$1**', $markdown);
        $markdown = preg_replace('/<em[^>]*>(.*?)<\/em>/i', '*$1*', $markdown);
        $markdown = preg_replace('/<i[^>]*>(.*?)<\/i>/i', '*$1*', $markdown);
        
        // Links
        $markdown = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', '[$2]($1)', $markdown);
        
        // Lists
        $markdown = preg_replace('/<li[^>]*>(.*?)<\/li>/i', '- $1', $markdown);
        $markdown = preg_replace('/<\/?[uo]l[^>]*>/i', '', $markdown);
        
        // Code
        $markdown = preg_replace('/<code[^>]*>(.*?)<\/code>/i', '`$1`', $markdown);
        $markdown = preg_replace('/<pre[^>]*>(.*?)<\/pre>/is', "```\n$1\n```", $markdown);
        
        // Images
        $markdown = preg_replace('/<img[^>]+src=["\']([^"\']+)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*>/i', '![$2]($1)', $markdown);
        $markdown = preg_replace('/<img[^>]+alt=["\']([^"\']*)["\'][^>]*src=["\']([^"\']+)["\'][^>]*>/i', '![$1]($2)', $markdown);
        $markdown = preg_replace('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', '![]($1)', $markdown);
        
        // Paragraphs
        $markdown = preg_replace('/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $markdown);
        
        // Line breaks
        $markdown = str_replace('<br>', "\n", $markdown);
        $markdown = str_replace('<br/>', "\n", $markdown);
        $markdown = str_replace('<br />', "\n", $markdown);
        
        // Strip remaining HTML
        $markdown = strip_tags($markdown);
        
        // Clean up extra whitespace
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
        $markdown = trim($markdown);
        
        return $markdown;
    }
    
    /**
     * Format content for Medium (HTML with specific styling)
     */
    private function format_content_for_medium($content) {
        // Medium supports most HTML, but we should clean it up
        $content = $this->clean_html_for_platform($content);
        
        // Add source attribution
        $original_url = get_permalink();
        $source_note = sprintf(
            '<hr><p><em>Originally published at <a href="%s">%s</a></em></p>',
            $original_url,
            get_bloginfo('name')
        );
        
        return $content . $source_note;
    }
    
    /**
     * Format content for email newsletters
     */
    private function format_content_for_email($content) {
        // Email-safe HTML
        $content = $this->clean_html_for_email($content);
        
        // Add newsletter-specific styling
        $styles = '<style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            h1, h2, h3 { color: #333; }
            a { color: #0066cc; }
            img { max-width: 100%; height: auto; }
        </style>';
        
        return $styles . $content;
    }
    
    /**
     * Clean HTML for specific platforms
     */
    private function clean_html_for_platform($content) {
        // Remove WordPress-specific shortcodes and elements
        $content = strip_shortcodes($content);
        
        // Remove empty paragraphs
        $content = preg_replace('/<p[^>]*><\/p>/', '', $content);
        
        // Clean up excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        return trim($content);
    }
    
    /**
     * Clean HTML for email
     */
    private function clean_html_for_email($content) {
        // Remove scripts and styles that don't work in email
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);
        
        // Convert relative URLs to absolute
        $site_url = home_url();
        $content = preg_replace('/src=["\']\/([^"\']+)["\']/', 'src="' . $site_url . '/$1"', $content);
        $content = preg_replace('/href=["\']\/([^"\']+)["\']/', 'href="' . $site_url . '/$1"', $content);
        
        return $content;
    }
    
    /**
     * Prepare media for Twitter
     */
    private function prepare_twitter_media($images) {
        // This would handle uploading images to Twitter and getting media IDs
        // For now, return empty array
        return [];
    }
}
