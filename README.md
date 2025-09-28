# Instagram Webhooks for UT Serang

This project implements Instagram API webhooks to receive real-time notifications for comments, messages, mentions, and other Instagram events for the UT Serang Instagram account.

## Features

- ✅ Webhook verification and event handling
- ✅ Support for comments, messages, mentions, reactions, and story insights
- ✅ Secure signature validation
- ✅ Database storage for webhook data
- ✅ Comprehensive logging system
- ✅ Test utilities and setup scripts
- ✅ Auto-reply functionality (configurable)

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- XAMPP or similar local development environment
- Instagram Business/Creator account
- Meta Developer App with Instagram API access
- HTTPS-enabled domain for production webhooks

## Installation

### 1. Clone/Download Files

Place all files in your XAMPP htdocs directory:
```
c:\xampp\htdocs\insapp\
```

### 2. Configure Instagram API

Edit `config/instagram_config.php` with your Instagram API credentials:

```php
return [
    'app_name' => 'utserangapp-IG',
    'app_id' => '846118025023479',
    'app_secret' => '17808d6163fccd7e402c5a5b64c1e2c8',
    'access_token' => 'YOUR_ACCESS_TOKEN',
    'verify_token' => 'your_custom_verify_token',
    'webhook_url' => 'https://yourdomain.com/insapp/webhook.php',
    // ... other config
];
```

### 3. Set Up Database

1. Create a MySQL database for the project
2. Import the database schema:
```sql
mysql -u root -p your_database_name < database/instagram_webhooks.sql
```

### 4. Configure Database Connection

Create `config/database.php`:

```php
<?php
return [
    'host' => 'localhost',
    'database' => 'instagram_webhooks',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];
?>
```

## Setup Instructions

### 1. Test Local Setup

1. Start XAMPP (Apache + MySQL)
2. Visit: `http://localhost/insapp/setup_webhooks.php`
3. This will:
   - Test your Instagram API connection
   - Subscribe to webhook fields
   - Show current subscriptions

### 2. Configure Meta App Dashboard

1. Go to [Meta Developers](https://developers.facebook.com/apps/846118025023479/webhooks/)
2. Add Callback URL: `https://yourdomain.com/insapp/webhook.php`
3. Add Verify Token: `utserang_webhook_verify_token_2024` (or your custom token)
4. Subscribe to these fields:
   - `comments`
   - `messages`
   - `mentions`
   - `message_reactions`
   - `story_insights`

### 3. Set App to Live Mode

1. In Meta App Dashboard, go to App Review
2. Switch your app from Development to Live mode
3. Ensure you have Advanced Access for required permissions

### 4. Test Webhook

1. Visit: `http://localhost/insapp/test_webhook.php`
2. Test webhook verification
3. Send test webhook events
4. Check logs for successful processing

## File Structure

```
insapp/
├── config/
│   ├── instagram_config.php    # Instagram API configuration
│   └── database.php           # Database configuration (create this)
├── classes/
│   ├── WebhookHandler.php     # Main webhook processing logic
│   └── InstagramAPI.php       # Instagram API wrapper
├── database/
│   └── instagram_webhooks.sql # Database schema
├── logs/
│   ├── webhook.log           # Webhook events log
│   └── instagram_api.log     # API calls log
├── webhook.php               # Main webhook endpoint
├── setup_webhooks.php        # Setup and subscription script
├── test_webhook.php          # Testing utilities
└── README.md                 # This file
```

## Usage

### Webhook Endpoint

The main webhook endpoint is `webhook.php`. This handles:

- **GET requests**: Webhook verification from Meta
- **POST requests**: Webhook event notifications

### Event Types Supported

1. **Comments** (`comments`)
   - New comments on your posts
   - Comment edits and deletions
   - Stored in `instagram_comments` table

2. **Messages** (`messages`)
   - Direct messages to your account
   - Message echoes and self-messages
   - Stored in `instagram_messages` table

3. **Mentions** (`mentions`)
   - When users @mention your account
   - Stored in `instagram_mentions` table

4. **Message Reactions** (`message_reactions`)
   - Reactions to your messages
   - Stored in `instagram_message_reactions` table

5. **Story Insights** (`story_insights`)
   - Analytics data for your stories
   - Stored in `instagram_story_insights` table

### API Methods Available

The `InstagramAPI` class provides methods for:

- `subscribeToWebhooks()` - Subscribe to webhook fields
- `getWebhookSubscriptions()` - Get current subscriptions
- `getAccountInfo()` - Get account information
- `getMedia()` - Get media details
- `getComments()` - Get comments for media
- `replyToComment()` - Reply to comments
- `sendMessage()` - Send direct messages
- `getUserProfile()` - Get user profile info

## Configuration Options

### Webhook Fields

You can customize which webhook fields to subscribe to in `setup_webhooks.php`:

```php
$webhookFields = [
    'comments',
    'messages', 
    'mentions',
    'message_reactions',
    'story_insights'
];
```

### Auto-Reply

Configure auto-reply settings in the database `app_config` table:

```sql
UPDATE app_config SET config_value = 'true' WHERE config_key = 'auto_reply_enabled';
UPDATE app_config SET config_value = 'Your custom message' WHERE config_key = 'auto_reply_message';
```

## Security Features

- **Signature Validation**: All webhook payloads are validated using HMAC-SHA256
- **Verify Token**: Webhook verification uses a secure token
- **HTTPS Required**: Production webhooks must use HTTPS
- **Rate Limiting**: Built-in protection against excessive requests

## Logging

The system provides comprehensive logging:

- `logs/webhook.log` - All webhook events and processing
- `logs/instagram_api.log` - API calls and responses

## Troubleshooting

### Common Issues

1. **Webhook Verification Fails**
   - Check verify token matches in config and Meta dashboard
   - Ensure webhook URL is publicly accessible

2. **Invalid Signature Errors**
   - Verify app secret is correct
   - Check HTTPS is properly configured

3. **No Webhook Events Received**
   - Ensure app is in Live mode
   - Check webhook subscriptions are active
   - Verify Instagram account is Business/Creator type

4. **Database Connection Errors**
   - Create `config/database.php` with correct credentials
   - Import database schema from `database/instagram_webhooks.sql`

### Debug Mode

Enable debug logging by adding to your config:

```php
'debug' => true,
'log_level' => 'DEBUG'
```

## Production Deployment

### Requirements for Production

1. **HTTPS Domain**: Webhooks require HTTPS
2. **SSL Certificate**: Valid SSL certificate (not self-signed)
3. **Public Access**: Webhook URL must be publicly accessible
4. **Live App**: Meta app must be in Live mode
5. **Advanced Access**: Required for comments and live_comments

### Security Checklist

- [ ] Use strong verify token
- [ ] Keep app secret secure
- [ ] Enable HTTPS
- [ ] Implement rate limiting
- [ ] Regular log rotation
- [ ] Database access controls
- [ ] Monitor webhook logs

## API Limits and Best Practices

- **Rate Limits**: Respect Instagram API rate limits
- **Batch Processing**: Process webhook events in batches when possible
- **Error Handling**: Implement retry logic for failed API calls
- **Data Retention**: Regularly clean up old webhook logs
- **Monitoring**: Monitor webhook delivery success rates

## Support

For issues related to:

- **Instagram API**: Check [Instagram API Documentation](https://developers.facebook.com/docs/instagram-api)
- **Webhooks**: Review [Instagram Webhooks Guide](https://developers.facebook.com/docs/instagram-api/webhooks)
- **Meta Developer**: Visit [Meta Developer Support](https://developers.facebook.com/support/)

## License

This project is developed for UT Serang internal use.

---

**Last Updated**: September 26, 2024
**Version**: 1.0.0
