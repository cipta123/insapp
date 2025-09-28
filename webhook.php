<?php
/**
 * Instagram Webhook Endpoint
 * Handles webhook verification and event notifications from Instagram API
 */

require_once 'config/instagram_config.php';
require_once 'classes/WebhookHandler.php';
require_once 'classes/InstagramAPI.php';
require_once 'classes/Database.php';

// Load configuration
$config = require 'config/instagram_config.php';

// Initialize webhook handler
$webhookHandler = new WebhookHandler($config);

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle different request types
switch ($method) {
    case 'GET':
        // Handle webhook verification
        $webhookHandler->handleVerification();
        break;
        
    case 'POST':
        // Handle webhook events
        $webhookHandler->handleEvent();
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
