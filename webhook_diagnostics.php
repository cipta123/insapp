<?php
/**
 * Instagram Webhook Diagnostics
 * Comprehensive troubleshooting for webhook issues
 */

require_once 'config/instagram_config.php';
require_once 'classes/InstagramAPI.php';

// Load configuration
$config = require 'config/instagram_config.php';

echo "<h1>🔍 Instagram Webhook Diagnostics</h1>";
echo "<p>Let's diagnose why your webhooks aren't working...</p>";
echo "<hr>";

// Test 1: Basic Configuration Check
echo "<h2>1. ✅ Configuration Check</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$checks = [
    'App ID' => [$config['app_id'], !empty($config['app_id']) ? '✅' : '❌'],
    'App Secret' => [substr($config['app_secret'], 0, 10) . '...', !empty($config['app_secret']) ? '✅' : '❌'],
    'Access Token' => [substr($config['access_token'], 0, 20) . '...', !empty($config['access_token']) ? '✅' : '❌'],
    'Webhook URL' => [$config['webhook_url'], !empty($config['webhook_url']) ? '✅' : '❌'],
    'Verify Token' => [$config['verify_token'], !empty($config['verify_token']) ? '✅' : '❌']
];

foreach ($checks as $setting => $data) {
    echo "<tr><td><strong>$setting</strong></td><td>{$data[0]}</td><td>{$data[1]}</td></tr>";
}
echo "</table>";

echo "<hr>";

// Test 2: Webhook Endpoint Accessibility
echo "<h2>2. 🌐 Webhook Endpoint Test</h2>";

$webhookUrl = $config['webhook_url'];
echo "<p><strong>Testing:</strong> $webhookUrl</p>";

// Test GET request (verification)
$verifyToken = $config['verify_token'];
$challenge = rand(1000000, 9999999);
$testUrl = $webhookUrl . "?hub.mode=subscribe&hub.verify_token=" . urlencode($verifyToken) . "&hub.challenge=" . $challenge;

echo "<h3>GET Request Test (Webhook Verification):</h3>";
echo "<p><a href='$testUrl' target='_blank' style='background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Test Webhook Verification</a></p>";
echo "<p><em>Expected result: Should show the challenge number: <strong>$challenge</strong></em></p>";

// Test basic connectivity
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $webhookUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Instagram-Webhook-Diagnostic/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>Basic Connectivity Test:</h3>";
if ($error) {
    echo "<p style='color: red;'>❌ Connection Error: $error</p>";
} else {
    echo "<p style='color: green;'>✅ Connection Successful - HTTP Code: $httpCode</p>";
    if ($httpCode == 405) {
        echo "<p style='color: orange;'>ℹ️ HTTP 405 is expected for GET requests without parameters</p>";
    }
}

echo "<hr>";

// Test 3: Instagram API Connection
echo "<h2>3. 📱 Instagram API Connection Test</h2>";

try {
    $instagram = new InstagramAPI($config);
    
    echo "<h3>Account Information:</h3>";
    $accountInfo = $instagram->getAccountInfo();
    
    if ($accountInfo) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<p style='color: green;'><strong>✅ Instagram API Connection Successful!</strong></p>";
        echo "<ul>";
        echo "<li><strong>Account ID:</strong> " . ($accountInfo['id'] ?? 'N/A') . "</li>";
        echo "<li><strong>Username:</strong> " . ($accountInfo['username'] ?? 'N/A') . "</li>";
        echo "<li><strong>Account Type:</strong> " . ($accountInfo['account_type'] ?? 'N/A') . "</li>";
        echo "<li><strong>Media Count:</strong> " . ($accountInfo['media_count'] ?? 'N/A') . "</li>";
        echo "</ul>";
        echo "</div>";
        
        // Check account type
        $accountType = $accountInfo['account_type'] ?? '';
        if ($accountType === 'PERSONAL') {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<p style='color: red;'><strong>⚠️ WARNING: Personal Account Detected!</strong></p>";
            echo "<p>Instagram webhooks require a <strong>Business</strong> or <strong>Creator</strong> account. Personal accounts cannot receive webhooks.</p>";
            echo "<p><strong>Solution:</strong> Convert your Instagram account to Business or Creator in Instagram app settings.</p>";
            echo "</div>";
        } else {
            echo "<p style='color: green;'>✅ Account type is compatible with webhooks</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Failed to get account information. Check your access token.</p>";
    }
    
    echo "<h3>Current Webhook Subscriptions:</h3>";
    $subscriptions = $instagram->getWebhookSubscriptions();
    
    if ($subscriptions) {
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
        echo json_encode($subscriptions, JSON_PRETTY_PRINT);
        echo "</pre>";
        
        if (isset($subscriptions['data']) && !empty($subscriptions['data'])) {
            echo "<p style='color: green;'>✅ Webhook subscriptions are active</p>";
        } else {
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<p style='color: #856404;'><strong>⚠️ No webhook subscriptions found!</strong></p>";
            echo "<p>Your app is not subscribed to any webhook fields.</p>";
            echo "<p><strong>Solution:</strong> <a href='setup_webhooks.php'>Run webhook subscription setup</a></p>";
            echo "</div>";
        }
    } else {
        echo "<p style='color: red;'>❌ Failed to get webhook subscriptions</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Instagram API Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// Test 4: Meta App Dashboard Configuration
echo "<h2>4. ⚙️ Meta App Dashboard Configuration</h2>";

echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 5px;'>";
echo "<h3>Required Configuration in Meta App Dashboard:</h3>";
echo "<p><strong>Dashboard URL:</strong> <a href='https://developers.facebook.com/apps/{$config['app_id']}/webhooks/' target='_blank'>https://developers.facebook.com/apps/{$config['app_id']}/webhooks/</a></p>";

echo "<h4>Webhook Configuration:</h4>";
echo "<ul>";
echo "<li><strong>Callback URL:</strong> {$config['webhook_url']}</li>";
echo "<li><strong>Verify Token:</strong> {$config['verify_token']}</li>";
echo "<li><strong>Required Fields:</strong> comments, messages, mentions, message_reactions, story_insights</li>";
echo "</ul>";

echo "<h4>Checklist:</h4>";
echo "<ul>";
echo "<li>☐ App is set to <strong>Live</strong> mode</li>";
echo "<li>☐ Webhook callback URL is added and verified</li>";
echo "<li>☐ Webhook fields are subscribed</li>";
echo "<li>☐ App has <strong>Advanced Access</strong> for comments and live_comments</li>";
echo "<li>☐ Instagram account is <strong>Business</strong> or <strong>Creator</strong> type</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";

// Test 5: Log Files Check
echo "<h2>5. 📋 Log Files Check</h2>";

$logFiles = [
    'logs/webhook.log' => 'Webhook Events',
    'logs/instagram_api.log' => 'Instagram API Calls',
    'logs/database.log' => 'Database Operations'
];

foreach ($logFiles as $logFile => $description) {
    echo "<h3>$description ($logFile):</h3>";
    
    if (file_exists($logFile)) {
        $size = filesize($logFile);
        $lastModified = date('Y-m-d H:i:s', filemtime($logFile));
        
        echo "<p><strong>File Size:</strong> $size bytes | <strong>Last Modified:</strong> $lastModified</p>";
        
        if ($size > 0) {
            $content = file_get_contents($logFile);
            $lines = explode("\n", trim($content));
            $recentLines = array_slice($lines, -5); // Last 5 lines
            
            echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0;'>";
            echo "<strong>Recent entries:</strong><br>";
            echo "<pre style='margin: 5px 0; font-size: 12px;'>" . htmlspecialchars(implode("\n", $recentLines)) . "</pre>";
            echo "</div>";
        } else {
            echo "<p style='color: orange;'>⚠️ Log file is empty</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Log file does not exist</p>";
    }
}

echo "<hr>";

// Test 6: Manual Webhook Test
echo "<h2>6. 🧪 Manual Webhook Test</h2>";

if (isset($_POST['manual_test'])) {
    echo "<h3>Sending Manual Webhook Test...</h3>";
    
    // Create a test payload
    $testPayload = [
        'object' => 'instagram',
        'entry' => [
            [
                'id' => 'test_account_id',
                'time' => time(),
                'changes' => [
                    [
                        'field' => 'comments',
                        'value' => [
                            'verb' => 'add',
                            'object_id' => 'test_media_id_' . time(),
                            'comment_id' => 'test_comment_id_' . time(),
                            'text' => 'This is a test comment from diagnostics',
                            'user_id' => 'test_user_id',
                            'username' => 'test_user'
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    // Send to webhook endpoint
    $payload = json_encode($testPayload);
    $signature = 'sha256=' . hash_hmac('sha256', $payload, $config['app_secret']);
    
    // Use file_get_contents for local testing
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'X-Hub-Signature-256: ' . $signature
            ],
            'content' => $payload
        ]
    ]);
    
    $localWebhookUrl = str_replace('https://utserang.info', 'http://localhost', $config['webhook_url']);
    $result = @file_get_contents($localWebhookUrl, false, $context);
    
    if ($result !== false) {
        echo "<p style='color: green;'>✅ Manual webhook test sent successfully!</p>";
        echo "<p>Check the logs above for the test entry.</p>";
    } else {
        echo "<p style='color: red;'>❌ Manual webhook test failed</p>";
        echo "<p>This might be normal if the webhook endpoint is not accessible locally.</p>";
    }
}

echo "<form method='POST'>";
echo "<button type='submit' name='manual_test' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Send Manual Test Webhook</button>";
echo "</form>";

echo "<hr>";

// Test 7: Next Steps
echo "<h2>7. 🚀 Next Steps</h2>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<h3>Most Common Issues & Solutions:</h3>";

echo "<h4>1. No Webhook Subscriptions:</h4>";
echo "<p>→ <a href='setup_webhooks.php'>Run webhook subscription setup</a></p>";

echo "<h4>2. Personal Instagram Account:</h4>";
echo "<p>→ Convert to Business/Creator account in Instagram app</p>";

echo "<h4>3. Webhook Not Configured in Meta Dashboard:</h4>";
echo "<p>→ <a href='https://developers.facebook.com/apps/{$config['app_id']}/webhooks/' target='_blank'>Configure in Meta Dashboard</a></p>";

echo "<h4>4. App Not in Live Mode:</h4>";
echo "<p>→ Switch app to Live mode in Meta Dashboard</p>";

echo "<h4>5. Missing Advanced Access:</h4>";
echo "<p>→ Request Advanced Access for comments and live_comments</p>";

echo "<h3>Immediate Actions:</h3>";
echo "<ol>";
echo "<li>Check if your Instagram account is Business/Creator type</li>";
echo "<li>Verify webhook configuration in Meta App Dashboard</li>";
echo "<li>Run webhook subscription setup if no subscriptions found</li>";
echo "<li>Test with the manual webhook test above</li>";
echo "<li>Monitor logs after sending real Instagram interactions</li>";
echo "</ol>";
echo "</div>";

?>

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
    padding: 10px;
    text-align: left;
    border: 1px solid #ddd;
}

th {
    background-color: #f2f2f2;
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
