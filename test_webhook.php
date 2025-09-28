<?php
/**
 * Instagram Webhook Test Script
 * Use this script to test your webhook endpoint
 */

require_once 'config/instagram_config.php';

// Load configuration
$config = require 'config/instagram_config.php';

echo "<h1>Instagram Webhook Test</h1>";
echo "<hr>";

// Test webhook verification
echo "<h2>1. Test Webhook Verification</h2>";

$verifyToken = $config['verify_token'];
$challenge = rand(1000000, 9999999);

$verificationUrl = "webhook.php?hub.mode=subscribe&hub.verify_token=" . urlencode($verifyToken) . "&hub.challenge=" . $challenge;

echo "<p>Testing webhook verification with:</p>";
echo "<ul>";
echo "<li><strong>Mode:</strong> subscribe</li>";
echo "<li><strong>Verify Token:</strong> $verifyToken</li>";
echo "<li><strong>Challenge:</strong> $challenge</li>";
echo "</ul>";

echo "<p><a href='$verificationUrl' target='_blank'>Click here to test webhook verification</a></p>";
echo "<p><em>Expected result: The page should display the challenge number: $challenge</em></p>";

echo "<hr>";

// Test webhook event simulation
echo "<h2>2. Test Webhook Event Simulation</h2>";

$samplePayload = [
    'object' => 'instagram',
    'entry' => [
        [
            'id' => '1755847768034402',
            'time' => time(),
            'changes' => [
                [
                    'field' => 'comments',
                    'value' => [
                        'verb' => 'add',
                        'object_id' => '17841400455970028',
                        'comment_id' => '17841400455970029',
                        'parent_id' => '17841400455970028'
                    ]
                ]
            ]
        ]
    ]
];

echo "<p>Sample webhook payload:</p>";
echo "<pre>" . json_encode($samplePayload, JSON_PRETTY_PRINT) . "</pre>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_webhook'])) {
    echo "<h3>Sending Test Webhook...</h3>";
    
    // Use localhost for local testing, or allow manual URL override
    $isLocal = ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
    
    if ($isLocal) {
        $webhookUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/webhook.php';
    } else {
        // For production, use localhost to avoid self-connection issues
        $webhookUrl = 'http://localhost' . dirname($_SERVER['REQUEST_URI']) . '/webhook.php';
    }
    
    // Generate signature
    $payload = json_encode($samplePayload);
    $signature = 'sha256=' . hash_hmac('sha256', $payload, $config['app_secret']);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $webhookUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Hub-Signature-256: ' . $signature
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "<p style='color: red;'>❌ cURL Error: $error</p>";
    } elseif ($httpCode === 200) {
        echo "<p style='color: green;'>✅ Webhook test successful! HTTP Code: $httpCode</p>";
        echo "<p>Response: $response</p>";
    } else {
        echo "<p style='color: red;'>❌ Webhook test failed! HTTP Code: $httpCode</p>";
        echo "<p>Response: $response</p>";
    }
}

?>

<form method="POST">
    <button type="submit" name="test_webhook" style="background: #1877f2; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
        Send Test Webhook Event
    </button>
</form>

<hr>

<!-- Log viewer -->
<h2>3. Webhook Logs</h2>

<?php
$logFile = 'logs/webhook.log';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    if (!empty($logs)) {
        echo "<h3>Recent Webhook Activity:</h3>";
        echo "<pre style='max-height: 400px; overflow-y: auto;'>" . htmlspecialchars($logs) . "</pre>";
        
        echo "<form method='POST' style='margin-top: 10px;'>";
        echo "<button type='submit' name='clear_logs' style='background: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;'>Clear Logs</button>";
        echo "</form>";
        
        if (isset($_POST['clear_logs'])) {
            file_put_contents($logFile, '');
            echo "<p style='color: green;'>✅ Logs cleared!</p>";
            echo "<script>setTimeout(function(){ location.reload(); }, 1000);</script>";
        }
    } else {
        echo "<p>No webhook logs found. Send some test events to see logs here.</p>";
    }
} else {
    echo "<p>Log file not found. Webhook events will create the log file automatically.</p>";
}
?>

<hr>

<h2>4. Configuration Check</h2>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
    <h3>Current Configuration:</h3>
    <ul>
        <li><strong>App Name:</strong> <?php echo htmlspecialchars($config['app_name']); ?></li>
        <li><strong>App ID:</strong> <?php echo htmlspecialchars($config['app_id']); ?></li>
        <li><strong>Webhook URL:</strong> <?php echo htmlspecialchars($config['webhook_url']); ?></li>
        <li><strong>Verify Token:</strong> <?php echo htmlspecialchars($config['verify_token']); ?></li>
        <li><strong>API Version:</strong> <?php echo htmlspecialchars($config['api_version']); ?></li>
    </ul>
    
    <h3>Checklist:</h3>
    <ul>
        <li>✅ Webhook endpoint created</li>
        <li>✅ Verification logic implemented</li>
        <li>✅ Event handlers implemented</li>
        <li>⚠️ Update webhook URL in config to your public domain</li>
        <li>⚠️ Configure webhooks in Meta App Dashboard</li>
        <li>⚠️ Set app to Live mode</li>
        <li>⚠️ Ensure HTTPS is enabled for production</li>
    </ul>
</div>

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

h1, h2, h3 {
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

button:hover {
    opacity: 0.9;
}
</style>
