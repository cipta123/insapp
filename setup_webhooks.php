<?php
/**
 * Instagram Webhook Setup Script
 * Use this script to subscribe to webhook fields and test your setup
 */

require_once 'config/instagram_config.php';
require_once 'classes/InstagramAPI.php';

// Load configuration
$config = require 'config/instagram_config.php';

// Initialize Instagram API
$instagram = new InstagramAPI($config);

echo "<h1>Instagram Webhook Setup</h1>";
echo "<hr>";

// Get account information
echo "<h2>1. Account Information</h2>";
$accountInfo = $instagram->getAccountInfo();
if ($accountInfo) {
    echo "<pre>" . json_encode($accountInfo, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<p style='color: red;'>Failed to get account information. Please check your access token.</p>";
}

echo "<hr>";

// Get current webhook subscriptions
echo "<h2>2. Current Webhook Subscriptions</h2>";
$subscriptions = $instagram->getWebhookSubscriptions();
if ($subscriptions) {
    echo "<pre>" . json_encode($subscriptions, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<p style='color: red;'>Failed to get webhook subscriptions.</p>";
}

echo "<hr>";

// Subscribe to webhook fields
echo "<h2>3. Subscribe to Webhook Fields</h2>";

// Define the fields you want to subscribe to
$webhookFields = [
    'comments',
    'messages', 
    'mentions',
    'message_reactions',
    'story_insights'
];

echo "<p>Subscribing to fields: " . implode(', ', $webhookFields) . "</p>";

$subscribeResult = $instagram->subscribeToWebhooks($webhookFields);

if ($subscribeResult) {
    echo "<p style='color: green;'>✅ Successfully subscribed to webhook fields!</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to subscribe to webhook fields.</p>";
}

echo "<hr>";

// Verify webhook subscriptions after subscribing
echo "<h2>4. Verify Webhook Subscriptions</h2>";
$updatedSubscriptions = $instagram->getWebhookSubscriptions();
if ($updatedSubscriptions) {
    echo "<pre>" . json_encode($updatedSubscriptions, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<p style='color: red;'>Failed to verify webhook subscriptions.</p>";
}

echo "<hr>";

// Configuration instructions
echo "<h2>5. Next Steps</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
echo "<h3>Configure Webhooks in Meta App Dashboard:</h3>";
echo "<ol>";
echo "<li>Go to <a href='https://developers.facebook.com/apps/{$config['app_id']}/webhooks/' target='_blank'>Meta App Dashboard - Webhooks</a></li>";
echo "<li>Click 'Add Callback URL'</li>";
echo "<li>Enter your webhook URL: <strong>{$config['webhook_url']}</strong></li>";
echo "<li>Enter your verify token: <strong>{$config['verify_token']}</strong></li>";
echo "<li>Click 'Verify and Save'</li>";
echo "<li>Subscribe to the webhook fields you want to receive notifications for</li>";
echo "<li>Make sure your app is set to 'Live' mode</li>";
echo "</ol>";

echo "<h3>Test Your Webhook:</h3>";
echo "<ol>";
echo "<li>Send a test message to your Instagram account</li>";
echo "<li>Post a comment on your Instagram media</li>";
echo "<li>Check the webhook logs at: <code>logs/webhook.log</code></li>";
echo "</ol>";

echo "<h3>Important Notes:</h3>";
echo "<ul>";
echo "<li>Your webhook URL must be publicly accessible via HTTPS</li>";
echo "<li>Your Instagram account must be a professional account (Business or Creator)</li>";
echo "<li>Your app must have Advanced Access for comments and live_comments</li>";
echo "<li>Your app must be in Live mode to receive webhook notifications</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";

// Test webhook endpoint
echo "<h2>6. Test Webhook Endpoint</h2>";
echo "<p>Test your webhook endpoint: <a href='test_webhook.php' target='_blank'>test_webhook.php</a></p>";

?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

pre {
    background: #f8f8f8;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    overflow-x: auto;
}

h1, h2 {
    color: #333;
}

hr {
    border: none;
    border-top: 1px solid #ddd;
    margin: 30px 0;
}

a {
    color: #1877f2;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>
