<?php
/**
 * Test Token Refresh System
 */

require_once 'refresh_token.php';

echo "<h2>Instagram Token Refresh Test</h2>";
echo "<pre>";

$refresher = new TokenRefresher();

echo "=== Testing Current Token ===\n";
$info = $refresher->getTokenInfo();
if ($info) {
    echo "✅ Current token is VALID\n";
    echo "User ID: " . $info['id'] . "\n";
    echo "Username: " . $info['username'] . "\n";
} else {
    echo "❌ Current token is INVALID\n";
}

echo "\n=== Token Refresh Test ===\n";
echo "Click the button below to test token refresh:\n";
echo "</pre>";

if (isset($_GET['action']) && $_GET['action'] === 'refresh') {
    echo "<pre>";
    echo "Starting token refresh...\n";
    $result = $refresher->refreshToken();
    if ($result) {
        echo "✅ Token refresh SUCCESS!\n";
    } else {
        echo "❌ Token refresh FAILED!\n";
    }
    echo "</pre>";
}

echo '<p><a href="?action=refresh" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🔄 Test Token Refresh</a></p>';

echo '<p><a href="dashboard.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">📊 Back to Dashboard</a></p>';
?>
