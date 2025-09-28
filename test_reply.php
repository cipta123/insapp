<?php
/**
 * Test Reply Comment Functionality
 * Test script untuk memastikan reply comment berfungsi
 */

require_once 'classes/InstagramAPI.php';
require_once 'classes/SimpleDatabase.php';

echo "<h1>🧪 Test Reply Comment Functionality</h1>";
echo "<hr>";

try {
    $config = require 'config/instagram_config.php';
    $instagram = new InstagramAPI($config);
    $db = SimpleDatabase::getInstance();
    
    echo "<p style='color: green;'>✅ Instagram API and Database loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading dependencies: " . $e->getMessage() . "</p>";
    exit;
}

// Get recent comments for testing
$recentComments = $db->select('instagram_comments', '*', '', [], 'created_at DESC', '5');

echo "<h2>Recent Comments Available for Testing</h2>";

if (!empty($recentComments)) {
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px;'>";
    foreach ($recentComments as $comment) {
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
        echo "<strong>Comment ID:</strong> {$comment['comment_id']}<br>";
        echo "<strong>Media ID:</strong> {$comment['media_id']}<br>";
        echo "<strong>Username:</strong> @{$comment['username']}<br>";
        echo "<strong>Text:</strong> " . htmlspecialchars($comment['comment_text']) . "<br>";
        echo "<strong>Created:</strong> {$comment['created_at']}<br>";
        
        if ($comment['parent_comment_id']) {
            echo "<strong>Reply to:</strong> {$comment['parent_comment_id']}<br>";
        }
        
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<p>No comments found. Comment on your Instagram post first.</p>";
}

echo "<hr>";

// Test form
echo "<h2>Test Reply Functionality</h2>";

if (isset($_POST['test_reply'])) {
    $commentId = $_POST['comment_id'];
    $replyText = $_POST['reply_text'];
    
    echo "<h3>Testing Reply...</h3>";
    echo "<p><strong>Comment ID:</strong> $commentId</p>";
    echo "<p><strong>Reply Text:</strong> " . htmlspecialchars($replyText) . "</p>";
    
    try {
        // Get comment details
        $comment = $db->select('instagram_comments', '*', 'comment_id = ?', [$commentId]);
        
        if (empty($comment)) {
            echo "<p style='color: red;'>❌ Comment not found in database</p>";
        } else {
            $comment = $comment[0];
            $mediaId = $comment['media_id'];
            
            echo "<p><strong>Media ID:</strong> $mediaId</p>";
            
            // Test API call (true reply with threading)
            $response = $instagram->replyToComment($commentId, $replyText);
            
            if ($response && isset($response['id'])) {
                echo "<p style='color: green;'>✅ Reply posted successfully!</p>";
                echo "<p><strong>Reply ID:</strong> {$response['id']}</p>";
                
                // Save to database
                $replyData = [
                    'media_id' => $mediaId,
                    'comment_id' => $response['id'],
                    'parent_comment_id' => $commentId,
                    'user_id' => $config['instagram_user_id'] ?? 'admin',
                    'username' => 'utserang_official',
                    'comment_text' => $replyText,
                    'verb' => 'add',
                    'webhook_data' => json_encode([
                        'reply_from_test' => true,
                        'original_comment_id' => $commentId,
                        'timestamp' => time()
                    ])
                ];
                
                $insertId = $db->insert('instagram_comments', $replyData);
                echo "<p style='color: green;'>✅ Reply saved to database with ID: $insertId</p>";
                
            } else {
                echo "<p style='color: red;'>❌ Failed to post reply</p>";
                echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

?>

<form method="POST" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3>Test Reply Form</h3>
    
    <div style="margin-bottom: 15px;">
        <label><strong>Comment ID to Reply To:</strong></label><br>
        <select name="comment_id" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Select a comment...</option>
            <?php foreach ($recentComments as $comment): ?>
                <option value="<?= $comment['comment_id'] ?>">
                    <?= $comment['comment_id'] ?> - @<?= $comment['username'] ?> - "<?= substr($comment['comment_text'], 0, 50) ?>..."
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label><strong>Reply Text:</strong></label><br>
        <textarea name="reply_text" required placeholder="Enter your reply..." 
                  style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; height: 80px;"></textarea>
    </div>
    
    <button type="submit" name="test_reply" 
            style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
        🧪 Test Reply
    </button>
</form>

<hr>

<h2>Configuration Check</h2>

<div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
    <p><strong>Access Token:</strong> <?= !empty($config['access_token']) ? '✅ Present' : '❌ Missing' ?></p>
    <p><strong>App ID:</strong> <?= $config['app_id'] ?? 'Not set' ?></p>
    <p><strong>API Version:</strong> <?= $config['api_version'] ?? 'Not set' ?></p>
    <p><strong>Base URL:</strong> <?= $config['base_url'] ?? 'Not set' ?></p>
</div>

<hr>

<div style="text-align: center;">
    <a href="dashboard.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">
        🏠 Back to Dashboard
    </a>
    <a href="check_data.php" style="background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-left: 10px;">
        📊 Check Data
    </a>
</div>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

h1, h2, h3 {
    color: #333;
}

hr {
    border: none;
    border-top: 1px solid #ddd;
    margin: 30px 0;
}
</style>
