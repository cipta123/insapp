<?php
/**
 * Connectivity Test for Webhook Endpoint
 * Test if your webhook endpoint is accessible from the internet
 */

echo "<h1>🌐 Webhook Connectivity Test</h1>";
echo "<p>Testing if your webhook endpoint is accessible from the internet...</p>";
echo "<hr>";

// Configuration
$config = require 'config/instagram_config.php';
$webhookUrl = $config['webhook_url'];

echo "<h2>1. Basic Information</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td><strong>Webhook URL</strong></td><td>$webhookUrl</td></tr>";
echo "<tr><td><strong>Server</strong></td><td>{$_SERVER['HTTP_HOST']}</td></tr>";
echo "<tr><td><strong>Document Root</strong></td><td>{$_SERVER['DOCUMENT_ROOT']}</td></tr>";
echo "<tr><td><strong>Script Path</strong></td><td>{$_SERVER['SCRIPT_FILENAME']}</td></tr>";
echo "</table>";

echo "<hr>";

// Test 2: Check if webhook.php exists locally
echo "<h2>2. Local File Check</h2>";

$localWebhookPath = __DIR__ . '/webhook.php';
if (file_exists($localWebhookPath)) {
    echo "<p style='color: green;'>✅ webhook.php exists locally</p>";
    echo "<p><strong>File path:</strong> $localWebhookPath</p>";
    echo "<p><strong>File size:</strong> " . filesize($localWebhookPath) . " bytes</p>";
    echo "<p><strong>Last modified:</strong> " . date('Y-m-d H:i:s', filemtime($localWebhookPath)) . "</p>";
} else {
    echo "<p style='color: red;'>❌ webhook.php not found locally at: $localWebhookPath</p>";
}

echo "<hr>";

// Test 3: Local webhook test
echo "<h2>3. Local Webhook Test</h2>";

$localUrl = "http://localhost/insapp/webhook.php";
echo "<p><strong>Testing local URL:</strong> $localUrl</p>";

// Test GET request locally
$verifyToken = $config['verify_token'];
$challenge = rand(1000000, 9999999);
$localTestUrl = $localUrl . "?hub.mode=subscribe&hub.verify_token=" . urlencode($verifyToken) . "&hub.challenge=" . $challenge;

echo "<p><a href='$localTestUrl' target='_blank' style='background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Test Local Webhook</a></p>";
echo "<p><em>Expected result: Should show challenge number: <strong>$challenge</strong></em></p>";

// Test local connectivity with cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $localTestUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_FOLLOWLOCATION => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<p style='color: red;'>❌ Local cURL Error: $error</p>";
} else {
    echo "<p style='color: green;'>✅ Local Connection Successful - HTTP Code: $httpCode</p>";
    if ($response == $challenge) {
        echo "<p style='color: green;'>✅ Webhook verification working locally!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Response: " . htmlspecialchars($response) . "</p>";
    }
}

echo "<hr>";

// Test 4: Server Configuration Check
echo "<h2>4. Server Configuration</h2>";

echo "<h3>PHP Configuration:</h3>";
echo "<ul>";
echo "<li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>";
echo "<li><strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</li>";
echo "<li><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "<li><strong>HTTP Host:</strong> " . $_SERVER['HTTP_HOST'] . "</li>";
echo "</ul>";

echo "<h3>Required Extensions:</h3>";
$extensions = ['curl', 'json', 'pdo', 'pdo_mysql'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '✅' : '❌';
    echo "<p>$status <strong>$ext:</strong> " . ($loaded ? 'Loaded' : 'Not loaded') . "</p>";
}

echo "<hr>";

// Test 5: Network Diagnostics
echo "<h2>5. Network Diagnostics</h2>";

echo "<h3>Possible Issues:</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";

echo "<h4>🔥 Most Likely Causes:</h4>";
echo "<ol>";
echo "<li><strong>Firewall Blocking:</strong> Server firewall blocking incoming connections</li>";
echo "<li><strong>Port Not Open:</strong> Port 80/443 not accessible from internet</li>";
echo "<li><strong>DNS Issues:</strong> Domain not pointing to correct server</li>";
echo "<li><strong>Web Server Not Running:</strong> Apache/Nginx not running or misconfigured</li>";
echo "<li><strong>SSL Certificate Issues:</strong> HTTPS not properly configured</li>";
echo "</ol>";

echo "<h4>🛠️ Troubleshooting Steps:</h4>";
echo "<ol>";
echo "<li><strong>Check if Apache is running:</strong> In XAMPP Control Panel, ensure Apache is started</li>";
echo "<li><strong>Test domain resolution:</strong> Try accessing https://utserang.info in browser</li>";
echo "<li><strong>Check firewall:</strong> Ensure ports 80 and 443 are open</li>";
echo "<li><strong>Test simple PHP file:</strong> Create a simple test.php file and access it</li>";
echo "<li><strong>Check server logs:</strong> Look at Apache error logs</li>";
echo "</ol>";

echo "</div>";

echo "<hr>";

// Test 6: Simple Test File Creation
echo "<h2>6. Create Simple Test File</h2>";

if (isset($_POST['create_test'])) {
    $testContent = '<?php
echo "✅ Server is working!";
echo "<br>Time: " . date("Y-m-d H:i:s");
echo "<br>Server: " . $_SERVER["HTTP_HOST"];
echo "<br>IP: " . $_SERVER["SERVER_ADDR"] ?? "Unknown";
?>';
    
    $testFile = __DIR__ . '/server_test.php';
    file_put_contents($testFile, $testContent);
    
    echo "<p style='color: green;'>✅ Test file created: server_test.php</p>";
    echo "<p><strong>Test URLs:</strong></p>";
    echo "<ul>";
    echo "<li>Local: <a href='http://localhost/insapp/server_test.php' target='_blank'>http://localhost/insapp/server_test.php</a></li>";
    echo "<li>Public: <a href='https://utserang.info/insapp/server_test.php' target='_blank'>https://utserang.info/insapp/server_test.php</a></li>";
    echo "</ul>";
}

echo "<form method='POST'>";
echo "<button type='submit' name='create_test' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Create Server Test File</button>";
echo "</form>";

echo "<hr>";

// Test 7: Alternative Solutions
echo "<h2>7. Alternative Solutions</h2>";

echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 5px;'>";
echo "<h3>If Public Access Continues to Fail:</h3>";

echo "<h4>Option 1: Use ngrok (Recommended for Testing)</h4>";
echo "<ol>";
echo "<li>Download ngrok from <a href='https://ngrok.com' target='_blank'>https://ngrok.com</a></li>";
echo "<li>Run: <code>ngrok http 80</code></li>";
echo "<li>Use the ngrok URL as your webhook URL</li>";
echo "<li>Update webhook URL in Meta Dashboard</li>";
echo "</ol>";

echo "<h4>Option 2: Check Server Provider</h4>";
echo "<ul>";
echo "<li>Contact your hosting provider about firewall settings</li>";
echo "<li>Ensure ports 80 and 443 are open</li>";
echo "<li>Check if there are any DDoS protection settings blocking requests</li>";
echo "</ul>";

echo "<h4>Option 3: Use Different Subdomain</h4>";
echo "<ul>";
echo "<li>Try using a subdomain like: webhook.utserang.info</li>";
echo "<li>Point it directly to your server IP</li>";
echo "<li>Test connectivity with the new subdomain</li>";
echo "</ul>";

echo "</div>";

echo "<hr>";

// Test 8: Manual Server Check
echo "<h2>8. Manual Server Check</h2>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "<h3>Manual Tests You Can Do:</h3>";

echo "<h4>1. Browser Test:</h4>";
echo "<p>Open these URLs in your browser:</p>";
echo "<ul>";
echo "<li><a href='https://utserang.info' target='_blank'>https://utserang.info</a> (Main site)</li>";
echo "<li><a href='https://utserang.info/insapp/' target='_blank'>https://utserang.info/insapp/</a> (App directory)</li>";
echo "<li><a href='https://utserang.info/insapp/webhook.php' target='_blank'>https://utserang.info/insapp/webhook.php</a> (Webhook endpoint)</li>";
echo "</ul>";

echo "<h4>2. Command Line Test (if you have server access):</h4>";
echo "<pre style='background: #000; color: #0f0; padding: 10px;'>";
echo "curl -I https://utserang.info/insapp/webhook.php\n";
echo "telnet utserang.info 80\n";
echo "nslookup utserang.info";
echo "</pre>";

echo "<h4>3. Online Tools:</h4>";
echo "<ul>";
echo "<li><a href='https://www.whatsmydns.net/#A/utserang.info' target='_blank'>DNS Checker</a></li>";
echo "<li><a href='https://tools.pingdom.com/' target='_blank'>Website Speed Test</a></li>";
echo "<li><a href='https://downforeveryoneorjustme.com/utserang.info' target='_blank'>Site Availability Check</a></li>";
echo "</ul>";

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

code {
    background: #f8f8f8;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
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
