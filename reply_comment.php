<?php
/**
 * Reply Comment Handler
 * Handle comment replies from dashboard
 */

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$commentId = $input['comment_id'] ?? '';
$replyText = trim($input['reply_text'] ?? '');

// Validate input
if (empty($commentId) || empty($replyText)) {
    http_response_code(400);
    echo json_encode(['error' => 'Comment ID and reply text are required']);
    exit;
}

// Validate reply text length (Instagram limit)
if (strlen($replyText) > 2200) {
    http_response_code(400);
    echo json_encode(['error' => 'Reply text too long (max 2200 characters)']);
    exit;
}

try {
    // Load Instagram API
    require_once 'classes/InstagramAPI.php';
    require_once 'classes/SimpleDatabase.php';
    
    $config = require 'config/instagram_config.php';
    $instagram = new InstagramAPI($config);
    $db = SimpleDatabase::getInstance();
    
    // Get comment details from database
    $comment = $db->select('instagram_comments', '*', 'comment_id = ?', [$commentId]);
    
    if (empty($comment)) {
        http_response_code(404);
        echo json_encode(['error' => 'Comment not found']);
        exit;
    }
    
    $comment = $comment[0];
    $mediaId = $comment['media_id'];
    
    // Log the reply attempt
    $logFile = __DIR__ . '/logs/reply.log';
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] Attempting to reply to comment $commentId on media $mediaId: " . substr($replyText, 0, 100) . "...\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    // Post reply using Instagram API (true reply with threading)
    $response = $instagram->replyToComment($commentId, $replyText);
    
    if ($response && isset($response['id'])) {
        // Success - save reply to database
        $replyData = [
            'media_id' => $mediaId,
            'comment_id' => $response['id'],
            'parent_comment_id' => $commentId,
            'user_id' => $config['instagram_user_id'] ?? 'admin',
            'username' => 'utserang_official',
            'comment_text' => $replyText,
            'verb' => 'add',
            'webhook_data' => json_encode([
                'reply_from_dashboard' => true,
                'original_comment_id' => $commentId,
                'timestamp' => time(),
                'api_response' => $response
            ])
        ];
        
        $insertId = $db->insert('instagram_comments', $replyData);
        
        $logMessage = "[$timestamp] Reply posted successfully. Reply ID: {$response['id']}, DB ID: $insertId\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        echo json_encode([
            'success' => true,
            'message' => 'Reply posted successfully',
            'reply_id' => $response['id'],
            'db_id' => $insertId
        ]);
        
    } else {
        // Failed to post
        $error = $response['error']['message'] ?? 'Failed to post reply';
        
        $logMessage = "[$timestamp] Failed to post reply: $error\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        http_response_code(400);
        echo json_encode([
            'error' => $error,
            'details' => $response
        ]);
    }
    
} catch (Exception $e) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] Exception in reply handler: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
