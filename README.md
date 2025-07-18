# Multi-Platform Content Syndication Hub

> Enterprise-grade WordPress plugin for automatic content syndication across multiple social media platforms and publishing networks.

[![WordPress Plugin](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![rtCamp](https://img.shields.io/badge/Built%20for-rtCamp-orange.svg)](https://rtcamp.com/)
[![GitHub Issues](https://img.shields.io/github/issues/yourusername/multi-platform-syndication.svg)](https://github.com/yourusername/multi-platform-syndication/issues)
[![GitHub Stars](https://img.shields.io/github/stars/yourusername/multi-platform-syndication.svg)](https://github.com/yourusername/multi-platform-syndication/stargazers)

---

## 🎯 **rtCamp Application Project**

This plugin was developed as a comprehensive demonstration for **rtCamp's Associate Software Engineer** position, showcasing enterprise-grade WordPress development skills required for high-profile clients including **Google**, **Facebook (Meta)**, **Indian Express**, **Penske Media**, and **Al Jazeera**.

**🔗 About rtCamp**: [rtcamp.com](https://rtcamp.com/) | **💼 Campus Program**: [careers.rtcamp.com/campus](https://careers.rtcamp.com/campus)

## 🚀 Features

### Core Functionality
- **Automatic Content Syndication**: Publish WordPress posts to multiple platforms simultaneously
- **Queue System**: Background processing for reliable content delivery
- **Smart Content Formatting**: Platform-specific content optimization
- **Real-time Analytics**: Track performance across all platforms
- **Retry Mechanism**: Automatic retry for failed publications
- **Bulk Operations**: Sync multiple posts at once

### Supported Platforms
1. **Twitter/X** - Tweet with media support
2. **Facebook** - Posts with images and videos
3. **LinkedIn** - Professional content sharing
4. **Instagram** - Visual content with captions
5. **Medium** - Long-form article publishing
6. **Dev.to** - Developer-focused content
7. **Email Newsletter** - Mailchimp integration

### Advanced Features
- **Content Formatting**: Automatic adaptation for each platform's requirements
- **Media Handling**: Smart image and video processing
- **Analytics Dashboard**: Comprehensive performance tracking
- **API Rate Limiting**: Respectful platform API usage
- **Error Handling**: Robust error management and logging
- **Mobile API**: RESTful endpoints for external integrations

## 📋 Technical Requirements

- WordPress 5.0+
- PHP 8.0+
- MySQL 5.7+
- SSL Certificate (required for most APIs)

## 🛠 Installation

1. **Download the Plugin**:
   ```bash
   git clone https://github.com/your-username/multi-platform-syndication
   ```

2. **Upload to WordPress**:
   - Copy the plugin folder to `/wp-content/plugins/`
   - Or upload as a ZIP file through WordPress admin

3. **Activate the Plugin**:
   - Go to Plugins > Installed Plugins
   - Click "Activate" on Multi-Platform Content Syndication Hub

4. **Configure API Keys**:
   - Navigate to **Tools > Syndication Hub**
   - Configure each platform's API credentials

## ⚙️ Configuration

### Platform Setup

#### Twitter/X Configuration
```php
// Required credentials
- Consumer Key (API Key)
- Consumer Secret (API Secret)
- Access Token
- Access Token Secret
```

#### Facebook Configuration
```php
// Required credentials
- App ID
- App Secret
- Access Token
- Page ID (optional, for page posting)
```

#### LinkedIn Configuration
```php
// Required credentials
- Client ID
- Client Secret
- Access Token
- Organization ID (optional)
```

#### Instagram Configuration
```php
// Required credentials
- App ID
- App Secret
- Access Token
- Business Account ID
```

#### Medium Configuration
```php
// Required credentials
- Access Token
- User ID
- Publication ID (optional)
```

#### Dev.to Configuration
```php
// Required credentials
- API Key
- User ID (optional)
- Organization ID (optional)
```

#### Newsletter Configuration
```php
// Required credentials
- Mailchimp API Key
- List ID
- Template ID (optional)
```

## 🎯 Usage

### Basic Usage

1. **Write Your Post**: Create a new WordPress post as usual
2. **Configure Syndication**: In the post editor, check platforms for auto-sync
3. **Publish**: When you publish the post, it automatically syndicates to selected platforms

### Manual Syndication

```php
// Sync a specific post to platforms
$result = MPCS_Hub_Platform_Manager::sync_content(
    $post_id, 
    ['twitter', 'facebook', 'linkedin']
);
```

### Bulk Syndication

```php
// Sync multiple posts
$results = MPCS_Hub_Platform_Manager::bulk_sync_content(
    [123, 456, 789], // Post IDs
    ['medium', 'devto'] // Platforms
);
```

## 📊 Analytics

### Viewing Analytics

1. **Dashboard Widget**: Quick overview on WordPress dashboard
2. **Full Analytics Page**: Tools > Syndication Hub > Analytics
3. **Post-specific Analytics**: View analytics for individual posts

### Available Metrics

- **Engagement**: Likes, shares, comments, reactions
- **Reach**: Views, impressions, click-through rates
- **Performance**: Success rates, error logs, processing times

## 🔌 API Integration

### REST API Endpoints

```bash
# Get platform status
GET /wp-json/mpcs-hub/v1/platforms

# Sync content
POST /wp-json/mpcs-hub/v1/sync
{
    "post_id": 123,
    "platforms": ["twitter", "facebook"]
}

# Get analytics
GET /wp-json/mpcs-hub/v1/analytics/123
```

### Webhook Support

```php
// Register webhook for platform updates
add_action('mpcs_hub_content_synced', function($post_id, $platform, $result) {
    // Custom webhook logic
});
```

## 🧩 Developer Hooks

### Actions

```php
// Before content sync
do_action('mpcs_hub_before_sync', $post_id, $platforms);

// After content sync
do_action('mpcs_hub_after_sync', $post_id, $results);

// On sync error
do_action('mpcs_hub_sync_error', $post_id, $platform, $error);
```

### Filters

```php
// Modify content before platform formatting
add_filter('mpcs_hub_content_data', function($content_data, $post_id) {
    // Modify content data
    return $content_data;
}, 10, 2);

// Add custom platforms
add_filter('mpcs_hub_platforms', function($platforms) {
    $platforms['custom'] = [
        'name' => 'Custom Platform',
        'class' => 'My_Custom_Platform'
    ];
    return $platforms;
});
```

## 📁 File Structure

```
multi-platform-syndication/
├── multi-platform-syndication.php     # Main plugin file
├── includes/
│   ├── class-database.php             # Database operations
│   ├── class-platform-manager.php     # Core syndication logic
│   ├── class-content-formatter.php    # Content formatting
│   └── platforms/                     # Platform handlers
│       ├── class-twitter.php
│       ├── class-facebook.php
│       ├── class-linkedin.php
│       ├── class-instagram.php
│       ├── class-medium.php
│       ├── class-devto.php
│       └── class-newsletter.php
├── admin/
│   ├── class-admin.php               # WordPress admin integration
│   └── views/
│       ├── dashboard.php             # Main admin dashboard
│       ├── platform-config.php       # Platform configuration
│       └── analytics.php             # Analytics page
├── assets/
│   ├── css/
│   │   └── admin.css                 # Admin styling
│   └── js/
│       └── admin.js                  # Admin JavaScript
└── README.md                         # Documentation
```

## 🔧 Architecture

### Database Schema

```sql
-- Sync logs table
CREATE TABLE wp_mpcs_sync_logs (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    post_id int(11) NOT NULL,
    platform varchar(50) NOT NULL,
    action varchar(20) NOT NULL,
    status varchar(20) NOT NULL,
    external_id varchar(255),
    external_url text,
    error_message text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP
);

-- Platform analytics table
CREATE TABLE wp_mpcs_platform_analytics (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    post_id int(11) NOT NULL,
    platform varchar(50) NOT NULL,
    external_id varchar(255),
    metrics json,
    last_updated datetime DEFAULT CURRENT_TIMESTAMP
);

-- Sync queue table
CREATE TABLE wp_mpcs_sync_queue (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    post_id int(11) NOT NULL,
    platform varchar(50) NOT NULL,
    action varchar(20) NOT NULL,
    priority int(5) DEFAULT 10,
    payload longtext,
    attempts int(5) DEFAULT 0,
    max_attempts int(5) DEFAULT 3,
    scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
    created_at datetime DEFAULT CURRENT_TIMESTAMP
);
```

### Class Architecture

```php
// Core Classes
- MPCS_Hub_Platform_Manager    # Main orchestrator
- MPCS_Hub_Database           # Database operations
- MPCS_Hub_Content_Formatter  # Content adaptation
- MPCS_Hub_Admin             # WordPress admin integration

// Platform Classes
- MPCS_Hub_Platform_Twitter
- MPCS_Hub_Platform_Facebook
- MPCS_Hub_Platform_Linkedin
- MPCS_Hub_Platform_Instagram
- MPCS_Hub_Platform_Medium
- MPCS_Hub_Platform_Devto
- MPCS_Hub_Platform_Newsletter
```

## 🧪 Testing

### Manual Testing

1. **Create Test Post**: Write a sample blog post
2. **Configure Platforms**: Set up at least 2-3 platform credentials
3. **Test Syndication**: Publish and verify content appears on platforms
4. **Check Analytics**: Verify metrics are being collected

### Automated Testing

```bash
# Run PHPUnit tests (if implemented)
./vendor/bin/phpunit tests/

# Check code quality
./vendor/bin/phpcs --standard=WordPress .
```

## 🚨 Troubleshooting

### Common Issues

1. **API Rate Limits**:
   - Solution: Enable queue system for delayed processing
   - Check platform-specific rate limits

2. **Authentication Errors**:
   - Verify API credentials are correct
   - Check token expiration dates
   - Ensure proper OAuth flows

3. **Content Formatting Issues**:
   - Review character limits per platform
   - Check media file formats and sizes

### Debug Mode

```php
// Enable debug logging
define('MPCS_HUB_DEBUG', true);

// View logs
tail -f /wp-content/debug.log | grep "MPCS_HUB"
```

## 📚 API Documentation

### Platform API Requirements

| Platform | API Version | Auth Method | Rate Limit |
|----------|-------------|-------------|------------|
| Twitter | v2.0 | OAuth 1.0a | 300/15min |
| Facebook | v18.0 | OAuth 2.0 | 4800/hour |
| LinkedIn | v2.0 | OAuth 2.0 | 1000/day |
| Instagram | v18.0 | OAuth 2.0 | 1000/hour |
| Medium | v1.0 | Bearer Token | 1000/hour |
| Dev.to | v1.0 | API Key | 1000/hour |
| Mailchimp | v3.0 | API Key | 10/second |

## 🤝 Contributing

This project was created for rtCamp job application demonstration. However, if you'd like to contribute:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 👤 Author

**Your Name**
- GitHub: [@your-username](https://github.com/your-username)
- LinkedIn: [Your LinkedIn](https://linkedin.com/in/your-profile)
- Email: your.email@example.com

## 🎯 rtCamp Application

This plugin demonstrates the following skills required for rtCamp:

### Technical Skills
- **WordPress Development**: Custom plugin architecture, hooks, filters
- **PHP**: Object-oriented programming, error handling, best practices
- **MySQL**: Database design, complex queries, performance optimization
- **JavaScript**: Modern ES6+, AJAX, admin interface interactions
- **API Integration**: Multiple platform APIs, OAuth, rate limiting
- **CSS**: Responsive design, WordPress admin styling

### Architecture Skills
- **Scalable Design**: Modular architecture supporting multiple platforms
- **Performance**: Queue system, background processing, caching considerations
- **Security**: Input sanitization, nonce verification, capability checks
- **Error Handling**: Comprehensive error management and logging
- **Documentation**: Extensive documentation and code comments

### WordPress Expertise
- **Plugin Development**: Complete plugin lifecycle, activation/deactivation
- **Admin Interface**: Custom admin pages, meta boxes, dashboard widgets
- **Database**: Custom tables, migration handling, data integrity
- **REST API**: Custom endpoints, authentication, data validation
- **Best Practices**: WordPress coding standards, security, performance

This project showcases enterprise-level WordPress development skills and demonstrates the ability to create complex, production-ready solutions for modern web applications.

---

**Built with ❤️ for rtCamp**
