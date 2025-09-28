<?php
/**
 * Instagram Webhook Handler Class
 * Handles webhook verification and event processing
 */

class WebhookHandler {
    private $config;
    private $logFile;
    private $db;
    
    public function __construct($config) {
        $this->config = $config;
        $this->logFile = __DIR__ . '/../logs/webhook.log';
        
        // Create logs directory if it doesn't exist
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
        
        // Initialize database connection
        try {
            require_once __DIR__ . '/SimpleDatabase.php';
            $this->db = SimpleDatabase::getInstance();
        } catch (Exception $e) {
            $this->log('Database connection failed: ' . $e->getMessage());
            $this->db = null;
        }
    }
    
    /**
     * Handle webhook verification requests from Meta
     */
    public function handleVerification() {
        $this->log('Webhook verification request received');
        
        // Get query parameters
        $mode = $_GET['hub_mode'] ?? '';
        $token = $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub_challenge'] ?? '';
        
        // Verify the request
        if ($mode === 'subscribe' && $token === $this->config['verify_token']) {
            $this->log('Webhook verification successful');
            echo $challenge;
            http_response_code(200);
        } else {
            $this->log('Webhook verification failed - Invalid token or mode');
            http_response_code(403);
            echo 'Forbidden';
        }
    }
    
    /**
     * Handle webhook event notifications
     */
    public function handleEvent() {
        $this->log('Webhook event received');
        
        // Get the raw POST data
        $input = file_get_contents('php://input');
        
        // Validate the payload signature
        if (!$this->validateSignature($input)) {
            $this->log('Invalid signature - rejecting webhook');
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        
        // Parse the JSON payload
        $data = json_decode($input, true);
        
        if (!$data) {
            $this->log('Invalid JSON payload');
            http_response_code(400);
            echo 'Bad Request';
            return;
        }
        
        $this->log('Webhook payload: ' . json_encode($data, JSON_PRETTY_PRINT));
        
        // Process the webhook event
        $this->processEvent($data);
        
        // Respond with 200 OK
        http_response_code(200);
        echo 'OK';
    }
    
    /**
     * Validate webhook signature
     */
    private function validateSignature($payload) {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        
        if (empty($signature)) {
            return false;
        }
        
        // Remove 'sha256=' prefix
        $signature = str_replace('sha256=', '', $signature);
        
        // Generate expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $this->config['app_secret']);
        
        // Compare signatures
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Process webhook events
     */
    private function processEvent($data) {
        $object = $data['object'] ?? '';
        $entries = $data['entry'] ?? [];
        
        // Log the entire webhook payload for debugging
        $this->log("=== WEBHOOK EVENT RECEIVED ===");
        $this->log("Full payload: " . json_encode($data, JSON_PRETTY_PRINT));
        
        // Save to webhook events log table
        $this->saveWebhookEvent($data);
        
        foreach ($entries as $entry) {
            $id = $entry['id'] ?? '';
            $time = $entry['time'] ?? '';
            $changes = $entry['changes'] ?? [];
            $messaging = $entry['messaging'] ?? [];
            
            $this->log("Processing entry for ID: $id at time: $time");
            
            // Handle changes-based events (comments, mentions, etc.)
            foreach ($changes as $change) {
                $field = $change['field'] ?? '';
                $value = $change['value'] ?? [];
                
                $this->log("Processing change for field: $field with value: " . json_encode($value));
                
                switch ($field) {
                    case 'comments':
                        $this->log("🔄 Processing COMMENTS event...");
                        $this->handleCommentEvent($value);
                        break;
                        
                    case 'live_comments':
                        $this->log("🔄 Processing LIVE_COMMENTS event...");
                        $this->handleLiveCommentEvent($value);
                        break;
                        
                    case 'mentions':
                        $this->log("🔄 Processing MENTIONS event...");
                        $this->handleMentionEvent($value);
                        break;
                        
                    case 'messages':
                        $this->log("🔄 Processing MESSAGES event...");
                        $this->handleMessageEvent($value);
                        break;
                        
                    case 'message_reactions':
                        $this->log("🔄 Processing MESSAGE_REACTIONS event...");
                        $this->handleMessageReactionEvent($value);
                        break;
                        
                    case 'story_insights':
                        $this->log("🔄 Processing STORY_INSIGHTS event...");
                        $this->handleStoryInsightsEvent($value);
                        break;
                        
                    default:
                        $this->log("❌ Unhandled webhook field: $field");
                        break;
                }
            }
            
            // Handle messaging-based events (direct messages)
            foreach ($messaging as $message) {
                $this->log("🔄 Processing MESSAGING event: " . json_encode($message));
                $this->handleMessagingEvent($message);
            }
        }
        
        $this->log("=== WEBHOOK EVENT PROCESSED ===");
    }
    
    /**
     * Handle comment events
     */
    private function handleCommentEvent($value) {
        $this->log('🔄 Handling comment event: ' . json_encode($value));
        
        // Instagram comment structure: {"from": {...}, "media": {...}, "id": "...", "text": "..."}
        $verb = $value['verb'] ?? 'add'; // Default to 'add' since Instagram doesn't always send verb
        $commentId = $value['id'] ?? $value['object_id'] ?? '';
        $mediaId = $value['media']['id'] ?? '';
        
        $this->log("🔍 Comment ID: '$commentId', Media ID: '$mediaId', Verb: '$verb'");
        
        // Get additional comment data from Instagram API if needed
        $commentData = $this->enrichCommentData($value);
        
        // Always try to save the comment (Instagram typically sends 'add' events)
        if (!empty($commentId) && !empty($mediaId)) {
            $this->log("🔄 Attempting to save comment...");
            $this->saveCommentToDatabase($mediaId, $commentData);
        } else {
            $this->log("❌ Missing required fields - commentId: '$commentId', mediaId: '$mediaId'");
        }
        
        // Legacy verb handling (in case Instagram sends verb field)
        switch ($verb) {
            case 'add':
                $this->log("✅ New comment processed");
                break;
                
            case 'edited':
                $this->log("✅ Comment edit processed");
                break;
                
            case 'remove':
                $this->log("✅ Comment removal processed");
                break;
                
            default:
                $this->log("✅ Comment processed with verb: $verb");
                break;
        }
    }
    
    /**
     * Handle live comment events
     */
    private function handleLiveCommentEvent($value) {
        $this->log('Handling live comment event: ' . json_encode($value));
        
        $verb = $value['verb'] ?? '';
        $objectId = $value['object_id'] ?? '';
        
        if ($verb === 'add') {
            $this->log("New live comment added to media: $objectId");
            // Add your live comment handling logic here
        }
    }
    
    /**
     * Handle mention events
     */
    private function handleMentionEvent($value) {
        $this->log('Handling mention event: ' . json_encode($value));
        
        $verb = $value['verb'] ?? '';
        $objectId = $value['object_id'] ?? '';
        
        if ($verb === 'add') {
            $this->log("New mention in media: $objectId");
            // Add your mention handling logic here
        }
    }
    
    /**
     * Handle messaging events (Instagram Direct Messages)
     */
    private function handleMessagingEvent($messaging) {
        $this->log('🔄 Handling messaging event: ' . json_encode($messaging));
        
        // Extract message data from messaging structure
        $senderId = $messaging['sender']['id'] ?? '';
        $recipientId = $messaging['recipient']['id'] ?? '';
        $messageData = $messaging['message'] ?? [];
        $messageId = $messageData['mid'] ?? '';
        $messageText = $messageData['text'] ?? '';
        $timestamp = $messaging['timestamp'] ?? '';
        
        $this->log("🔍 Sender: '$senderId', Recipient: '$recipientId'");
        $this->log("🔍 Message ID: '$messageId', Text: '$messageText'");
        $this->log("🔍 Timestamp: '$timestamp'");
        
        if (!empty($messageId)) {
            // Prepare data for database
            $dbData = [
                'id' => $messageId,
                'sender' => ['id' => $senderId],
                'recipient' => ['id' => $recipientId], 
                'text' => $messageText,
                'timestamp' => $timestamp,
                'messaging_data' => $messaging
            ];
            
            $this->log("🔄 Attempting to save messaging data...");
            $result = $this->saveMessageToDatabase($messageId, $dbData);
            if ($result) {
                $this->log("✅ Messaging saved successfully with DB ID: $result");
            } else {
                $this->log("❌ Failed to save messaging to database");
            }
        } else {
            $this->log("❌ Missing message ID in messaging event");
        }
    }
    
    /**
     * Handle message events (legacy - for changes-based messages)
     */
    private function handleMessageEvent($value) {
        $this->log('🔄 Handling message event: ' . json_encode($value));
        
        // Instagram message structure analysis
        $this->log("🔍 Available message keys: " . implode(', ', array_keys($value)));
        
        // Try different possible message ID fields
        $messageId = $value['id'] ?? $value['object_id'] ?? $value['message_id'] ?? '';
        $verb = $value['verb'] ?? 'add';
        
        $this->log("🔍 Message ID: '$messageId', Verb: '$verb'");
        
        // Check if we have required data
        $hasFrom = isset($value['from']) || isset($value['sender']);
        $hasTo = isset($value['to']) || isset($value['recipient']);
        $hasMessage = isset($value['message']) || isset($value['text']);
        
        $this->log("🔍 Has From: " . ($hasFrom ? 'Yes' : 'No'));
        $this->log("🔍 Has To: " . ($hasTo ? 'Yes' : 'No'));
        $this->log("🔍 Has Message: " . ($hasMessage ? 'Yes' : 'No'));
        
        // Get additional message data from Instagram API if needed
        $messageData = $this->enrichMessageData($value);
        
        // Always try to save the message if we have minimum required data
        if (!empty($messageId)) {
            $this->log("🔄 Attempting to save message with ID: $messageId");
            $result = $this->saveMessageToDatabase($messageId, $messageData);
            if ($result) {
                $this->log("✅ Message saved successfully with DB ID: $result");
            } else {
                $this->log("❌ Failed to save message to database");
            }
        } else {
            $this->log("❌ Missing message ID - cannot save message");
            $this->log("❌ Available value keys: " . implode(', ', array_keys($value)));
        }
        
        $this->log("✅ Message event processing completed");
    }
    
    /**
     * Handle message reaction events
     */
    private function handleMessageReactionEvent($value) {
        $this->log('Handling message reaction event: ' . json_encode($value));
        
        $verb = $value['verb'] ?? '';
        $objectId = $value['object_id'] ?? '';
        
        if ($verb === 'add') {
            $this->log("New message reaction: $objectId");
            // Add your message reaction handling logic here
        }
    }
    
    /**
     * Handle story insights events
     */
    private function handleStoryInsightsEvent($value) {
        $this->log('Handling story insights event: ' . json_encode($value));
        
        $verb = $value['verb'] ?? '';
        $objectId = $value['object_id'] ?? '';
        
        if ($verb === 'add') {
            $this->log("Story insights available for: $objectId");
            // Add your story insights handling logic here
        }
    }
    
    /**
     * Enrich comment data with additional information
     */
    private function enrichCommentData($value) {
        // Start with original webhook data
        $enrichedData = $value;
        
        // Try to get additional comment details from Instagram API
        $commentId = $value['object_id'] ?? '';
        if (!empty($commentId)) {
            try {
                require_once __DIR__ . '/InstagramAPI.php';
                $instagram = new InstagramAPI($this->config);
                
                // Get comment details (this might require additional API calls)
                // For now, we'll work with the webhook data
                $this->log("Comment ID for enrichment: $commentId");
                
            } catch (Exception $e) {
                $this->log("Failed to enrich comment data: " . $e->getMessage());
            }
        }
        
        return $enrichedData;
    }
    
    /**
     * Enrich message data with additional information  
     */
    private function enrichMessageData($value) {
        // Start with original webhook data
        $enrichedData = $value;
        
        // Try to get additional message details
        $messageId = $value['object_id'] ?? '';
        if (!empty($messageId)) {
            try {
                $this->log("Message ID for enrichment: $messageId");
                // Additional message processing can be added here
                
            } catch (Exception $e) {
                $this->log("Failed to enrich message data: " . $e->getMessage());
            }
        }
        
        return $enrichedData;
    }
    
    /**
     * Save comment to database
     */
    private function saveCommentToDatabase($mediaId, $data) {
        $this->log("🔄 saveCommentToDatabase called with mediaId: $mediaId");
        $this->log("🔄 Comment data received: " . json_encode($data));
        
        if (!$this->db) {
            $this->log("❌ Database not available, skipping comment save");
            return false;
        }
        
        try {
            // Debug: log each field extraction based on Instagram's actual structure
            $commentId = $data['id'] ?? $data['object_id'] ?? $data['comment_id'] ?? '';
            $this->log("🔍 Extracted comment_id: '$commentId'");
            
            $userId = $data['from']['id'] ?? $data['user_id'] ?? '';
            $this->log("🔍 Extracted user_id: '$userId'");
            
            $username = $data['from']['username'] ?? $data['username'] ?? '';
            $this->log("🔍 Extracted username: '$username'");
            
            $commentText = $data['text'] ?? $data['message'] ?? '';
            $this->log("🔍 Extracted comment_text: '$commentText'");
            
            $verb = $data['verb'] ?? 'add';
            $this->log("🔍 Extracted verb: '$verb'");
            
            $parentId = $data['parent_id'] ?? $data['parent'] ?? null;
            $this->log("🔍 Extracted parent_id: '$parentId'");
            
            $commentData = [
                'media_id' => $mediaId,
                'comment_id' => $commentId,
                'parent_comment_id' => $parentId,
                'user_id' => $userId,
                'username' => $username,
                'comment_text' => $commentText,
                'verb' => $verb,
                'webhook_data' => json_encode($data)
            ];
            
            $this->log("🔄 Attempting to insert comment data: " . json_encode($commentData));
            
            $insertId = $this->db->insert('instagram_comments', $commentData);
            if ($insertId) {
                $this->log("✅ Comment saved to instagram_comments table with ID: $insertId");
            } else {
                $this->log("❌ Failed to save comment - insert returned false");
            }
            return $insertId;
            
        } catch (Exception $e) {
            $this->log("❌ Exception in saveCommentToDatabase: " . $e->getMessage());
            $this->log("❌ Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Save webhook event to log table
     */
    private function saveWebhookEvent($data) {
        if (!$this->db) {
            $this->log("Database not available, skipping webhook event save");
            return false;
        }
        
        try {
            $entries = $data['entry'] ?? [];
            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];
                foreach ($changes as $change) {
                    $eventData = [
                        'event_type' => $data['object'] ?? 'unknown',
                        'object_type' => $data['object'] ?? 'unknown',
                        'object_id' => $change['value']['object_id'] ?? $entry['id'] ?? 'unknown',
                        'field_name' => $change['field'] ?? 'unknown',
                        'verb' => $change['value']['verb'] ?? 'unknown',
                        'raw_payload' => json_encode($data),
                        'processed' => 1
                    ];
                    
                    $insertId = $this->db->insert('webhook_events_log', $eventData);
                    $this->log("Webhook event saved to database with ID: $insertId");
                }
            }
            return true;
            
        } catch (Exception $e) {
            $this->log("Failed to save webhook event to database: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save message to database
     */
    private function saveMessageToDatabase($messageId, $data) {
        $this->log("🔄 saveMessageToDatabase called with messageId: $messageId");
        $this->log("🔄 Message data received: " . json_encode($data));
        
        if (!$this->db) {
            $this->log("❌ Database not available, skipping message save");
            return false;
        }
        
        try {
            // Debug: log each field extraction - Instagram messaging structure
            // Try multiple possible sender field locations
            $senderId = '';
            if (isset($data['sender']['id'])) {
                $senderId = $data['sender']['id'];
                $this->log("🔍 Found sender_id in sender.id: '$senderId'");
            } elseif (isset($data['from']['id'])) {
                $senderId = $data['from']['id'];
                $this->log("🔍 Found sender_id in from.id: '$senderId'");
            } elseif (isset($data['sender_id'])) {
                $senderId = $data['sender_id'];
                $this->log("🔍 Found sender_id in sender_id: '$senderId'");
            } else {
                $this->log("🔍 No sender_id found in data");
            }
            
            // Try multiple possible recipient field locations
            $recipientId = '';
            if (isset($data['recipient']['id'])) {
                $recipientId = $data['recipient']['id'];
                $this->log("🔍 Found recipient_id in recipient.id: '$recipientId'");
            } elseif (isset($data['to']['id'])) {
                $recipientId = $data['to']['id'];
                $this->log("🔍 Found recipient_id in to.id: '$recipientId'");
            } elseif (isset($data['recipient_id'])) {
                $recipientId = $data['recipient_id'];
                $this->log("🔍 Found recipient_id in recipient_id: '$recipientId'");
            } else {
                $this->log("🔍 No recipient_id found in data");
            }
            
            // Try multiple possible message text locations
            $messageText = '';
            if (isset($data['text'])) {
                $messageText = $data['text'];
                $this->log("🔍 Found message_text in text: '$messageText'");
            } elseif (isset($data['message']['text'])) {
                $messageText = $data['message']['text'];
                $this->log("🔍 Found message_text in message.text: '$messageText'");
            } elseif (isset($data['message']) && is_string($data['message'])) {
                $messageText = $data['message'];
                $this->log("🔍 Found message_text in message: '$messageText'");
            } else {
                $this->log("🔍 No message_text found in data");
            }
            
            $messageType = $data['message']['type'] ?? $data['type'] ?? $data['message_type'] ?? 'text';
            $this->log("🔍 Extracted message_type: '$messageType'");
            
            $isEcho = isset($data['is_echo']) ? (int)$data['is_echo'] : 0;
            $this->log("🔍 Extracted is_echo: $isEcho");
            
            $isSelf = isset($data['is_self']) ? (int)$data['is_self'] : 0;
            $this->log("🔍 Extracted is_self: $isSelf");
            
            // Additional debugging for message structure
            $this->log("🔍 All available data keys: " . implode(', ', array_keys($data)));
            if (isset($data['message']) && is_array($data['message'])) {
                $this->log("🔍 Message object keys: " . implode(', ', array_keys($data['message'])));
            }
            if (isset($data['from']) && is_array($data['from'])) {
                $this->log("🔍 From object keys: " . implode(', ', array_keys($data['from'])));
            }
            if (isset($data['to']) && is_array($data['to'])) {
                $this->log("🔍 To object keys: " . implode(', ', array_keys($data['to'])));
            }
            
            $messageData = [
                'message_id' => $messageId,
                'sender_id' => $senderId,
                'recipient_id' => $recipientId,
                'message_text' => $messageText,
                'message_type' => $messageType,
                'is_echo' => $isEcho,
                'is_self' => $isSelf,
                'webhook_data' => json_encode($data)
            ];
            
            $this->log("🔄 Attempting to insert message data: " . json_encode($messageData));
            
            $insertId = $this->db->insert('instagram_messages', $messageData);
            if ($insertId) {
                $this->log("✅ Message saved to instagram_messages table with ID: $insertId");
            } else {
                $this->log("❌ Failed to save message - insert returned false");
            }
            return $insertId;
            
        } catch (Exception $e) {
            $this->log("❌ Exception in saveMessageToDatabase: " . $e->getMessage());
            $this->log("❌ Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Log messages to file
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}
?>
