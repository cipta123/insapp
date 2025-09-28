<?php
/**
 * Instagram Webhook Monitor
 * Monitor webhook activity and test webhook functionality
 */

require_once 'config/instagram_config.php';
require_once 'classes/Database.php';

// Load configuration
$config = require 'config/instagram_config.php';

echo "<h1>Instagram Webhook Monitor</h1>";
echo "<hr>";

// Initialize database
try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✅ Database connected successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Clear logs if requested
if (isset($_POST['clear_logs'])) {
    $logFiles = ['logs/webhook.log', 'logs/instagram_api.log', 'logs/database.log'];
    foreach ($logFiles as $logFile) {
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }
    }
    echo "<p style='color: green;'>✅ All logs cleared!</p>";
}

// Test webhook verification
echo "<h2>1. Webhook Verification Test</h2>";

$verifyToken = $config['verify_token'];
$challenge = rand(1000000, 9999999);
$verificationUrl = "webhook.php?hub.mode=subscribe&hub.verify_token=" . urlencode($verifyToken) . "&hub.challenge=" . $challenge;

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<p><strong>Webhook URL:</strong> {$config['webhook_url']}</p>";
echo "<p><strong>Verify Token:</strong> $verifyToken</p>";
echo "<p><strong>Test Challenge:</strong> $challenge</p>";
echo "<p><a href='$verificationUrl' target='_blank' style='background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Test Verification</a></p>";
echo "<p><em>Expected result: Should display the challenge number: $challenge</em></p>";
echo "</div>";

echo "<hr>";

// Recent webhook activity
echo "<h2>2. Recent Webhook Activity</h2>";

try {
    $recentEvents = $db->select(
        'webhook_events_log', 
        '*', 
        '', 
        [], 
        'created_at DESC', 
        '10'
    );
    
    if (!empty($recentEvents)) {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f2f2f2;'>";
        echo "<th>Time</th><th>Event Type</th><th>Object ID</th><th>Field</th><th>Verb</th><th>Processed</th>";
        echo "</tr>";
        
        foreach ($recentEvents as $event) {
            $processed = $event['processed'] ? '✅' : '❌';
            $bgColor = $event['processed'] ? '#d4edda' : '#f8d7da';
            
            echo "<tr style='background: $bgColor;'>";
            echo "<td>" . date('H:i:s', strtotime($event['created_at'])) . "</td>";
            echo "<td>{$event['event_type']}</td>";
            echo "<td>{$event['object_id']}</td>";
            echo "<td>{$event['field_name']}</td>";
            echo "<td>{$event['verb']}</td>";
            echo "<td>$processed</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No webhook events recorded yet. Send a message or comment to your Instagram account to test.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error loading webhook events: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// Recent comments
echo "<h2>3. Recent Comments</h2>";

try {
    $recentComments = $db->select(
        'instagram_comments', 
        '*', 
        '', 
        [], 
        'created_at DESC', 
        '5'
    );
    
    if (!empty($recentComments)) {
        echo "<div style='max-height: 300px; overflow-y: auto;'>";
        foreach ($recentComments as $comment) {
            echo "<div style='background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
            echo "<strong>@{$comment['username']}</strong> ";
            echo "<span style='color: #666; font-size: 12px;'>" . date('Y-m-d H:i:s', strtotime($comment['created_at'])) . "</span><br>";
            echo "<p style='margin: 5px 0;'>" . htmlspecialchars($comment['comment_text']) . "</p>";
            echo "<small>Media: {$comment['media_id']} | Action: {$comment['verb']}</small>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p>No comments recorded yet.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error loading comments: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// Recent messages
echo "<h2>4. Recent Messages</h2>";

try {
    $recentMessages = $db->select(
        'instagram_messages', 
        '*', 
        '', 
        [], 
        'created_at DESC', 
        '5'
    );
    
    if (!empty($recentMessages)) {
        echo "<div style='max-height: 300px; overflow-y: auto;'>";
        foreach ($recentMessages as $message) {
            $isEcho = $message['is_echo'] ? ' (Echo)' : '';
            echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
            echo "<strong>From:</strong> {$message['sender_id']}$isEcho ";
            echo "<span style='color: #666; font-size: 12px;'>" . date('Y-m-d H:i:s', strtotime($message['created_at'])) . "</span><br>";
            echo "<p style='margin: 5px 0;'>" . htmlspecialchars($message['message_text']) . "</p>";
            echo "<small>Type: {$message['message_type']}</small>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p>No messages recorded yet.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error loading messages: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// Webhook logs
echo "<h2>5. Webhook Logs</h2>";

$logFiles = [
    'webhook.log' => 'Webhook Events',
    'instagram_api.log' => 'Instagram API',
    'database.log' => 'Database Operations'
];

foreach ($logFiles as $filename => $title) {
    $logFile = "logs/$filename";
    echo "<h3>$title</h3>";
    
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        if (!empty($logs)) {
            // Show only last 20 lines
            $lines = explode("\n", $logs);
            $recentLines = array_slice($lines, -20);
            $recentLogs = implode("\n", $recentLines);
            
            echo "<pre style='background: #f8f8f8; padding: 10px; border-radius: 4px; max-height: 200px; overflow-y: auto; font-size: 12px;'>";
            echo htmlspecialchars($recentLogs);
            echo "</pre>";
        } else {
            echo "<p>No logs found.</p>";
        }
    } else {
        echo "<p>Log file not found.</p>";
    }
}

echo "<hr>";

// Configuration status
echo "<h2>6. Configuration Status</h2>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "<h3>Current Configuration:</h3>";
echo "<ul>";
echo "<li><strong>App ID:</strong> {$config['app_id']}</li>";
echo "<li><strong>Webhook URL:</strong> {$config['webhook_url']}</li>";
echo "<li><strong>Verify Token:</strong> {$config['verify_token']}</li>";
echo "<li><strong>API Version:</strong> {$config['api_version']}</li>";
echo "</ul>";

echo "<h3>Testing Instructions:</h3>";
echo "<ol>";
echo "<li><strong>Test Webhook Verification:</strong> Click the verification link above</li>";
echo "<li><strong>Test Real Interactions:</strong>";
echo "<ul>";
echo "<li>Send a DM to your Instagram account</li>";
echo "<li>Comment on one of your Instagram posts</li>";
echo "<li>Mention your account in a story or comment</li>";
echo "</ul>";
echo "</li>";
echo "<li><strong>Monitor Results:</strong> Refresh this page to see new webhook events</li>";
echo "</ol>";

echo "<h3>Meta App Dashboard:</h3>";
echo "<p>Configure webhooks at: <a href='https://developers.facebook.com/apps/{$config['app_id']}/webhooks/' target='_blank'>Meta App Dashboard</a></p>";
echo "</div>";

echo "<hr>";

// Auto-refresh and clear logs
echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<form method='POST' style='display: inline-block; margin-right: 10px;'>";
echo "<button type='submit' name='clear_logs' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Clear All Logs</button>";
echo "</form>";

echo "<button onclick='location.reload()' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Refresh Page</button>";

echo "<button onclick='startAutoRefresh()' id='autoRefreshBtn' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;'>Start Auto-Refresh</button>";
echo "</div>";

?>

<script>
let autoRefreshInterval;
let isAutoRefreshing = false;

function startAutoRefresh() {
    const btn = document.getElementById('autoRefreshBtn');
    
    if (isAutoRefreshing) {
        clearInterval(autoRefreshInterval);
        btn.textContent = 'Start Auto-Refresh';
        btn.style.background = '#007bff';
        isAutoRefreshing = false;
    } else {
        autoRefreshInterval = setInterval(() => {
            location.reload();
        }, 30000); // Refresh every 30 seconds
        
        btn.textContent = 'Stop Auto-Refresh';
        btn.style.background = '#fd7e14';
        isAutoRefreshing = true;
    }
}

// Show last updated time
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString();
    document.title = `Webhook Monitor - Last updated: ${timeStr}`;
});
</script>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

table {
    width: 100%;
    margin: 20px 0;
}

th, td {
    padding: 8px;
    text-align: left;
    border: 1px solid #ddd;
}

th {
    background-color: #f2f2f2;
    font-weight: bold;
}

pre {
    background: #f8f8f8;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    overflow-x: auto;
}

h1, h2, h3 {
    color: #333;
}

hr {
    border: none;
    border-top: 1px solid #ddd;
    margin: 30px 0;
}

button:hover {
    opacity: 0.9;
}

a {
    color: #007bff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>
