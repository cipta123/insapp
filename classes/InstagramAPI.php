<?php
/**
 * Instagram API Class
 * Handles API calls to Instagram Graph API
 */

class InstagramAPI {
    private $config;
    private $baseUrl;
    private $accessToken;
    
    public function __construct($config) {
        $this->config = $config;
        $this->baseUrl = $config['base_url'] . '/' . $config['api_version'];
        $this->accessToken = $config['access_token'];
    }
    
    /**
     * Subscribe to webhook fields
     */
    public function subscribeToWebhooks($fields = ['comments', 'messages', 'mentions']) {
        $endpoint = $this->baseUrl . '/me/subscribed_apps';
        
        $params = [
            'subscribed_fields' => implode(',', $fields),
            'access_token' => $this->accessToken
        ];
        
        $response = $this->makeRequest('POST', $endpoint, $params);
        
        if ($response && isset($response['success']) && $response['success']) {
            $this->log('Successfully subscribed to webhook fields: ' . implode(', ', $fields));
            return true;
        } else {
            $this->log('Failed to subscribe to webhook fields: ' . json_encode($response));
            return false;
        }
    }
    
    /**
     * Get current webhook subscriptions
     */
    public function getWebhookSubscriptions() {
        $endpoint = $this->baseUrl . '/me/subscribed_apps';
        
        $params = [
            'access_token' => $this->accessToken
        ];
        
        $response = $this->makeRequest('GET', $endpoint, $params);
        
        if ($response) {
            $this->log('Current webhook subscriptions: ' . json_encode($response));
            return $response;
        } else {
            $this->log('Failed to get webhook subscriptions');
            return false;
        }
    }
    
    /**
     * Get Instagram account information
     */
    public function getAccountInfo() {
        $endpoint = $this->baseUrl . '/me';
        
        $params = [
            'fields' => 'id,username,account_type,media_count',
            'access_token' => $this->accessToken
        ];
        
        $response = $this->makeRequest('GET', $endpoint, $params);
        
        if ($response) {
            $this->log('Account info retrieved: ' . json_encode($response));
            return $response;
        } else {
            $this->log('Failed to get account info');
            return false;
        }
    }
    
    /**
     * Get media details by ID
     */
    public function getMedia($mediaId, $fields = ['id', 'caption', 'media_type', 'media_url', 'permalink', 'timestamp', 'comments_count', 'like_count']) {
        $endpoint = $this->baseUrl . '/' . $mediaId;
        
        $params = [
            'fields' => implode(',', $fields),
            'access_token' => $this->accessToken
        ];
        
        $response = $this->makeRequest('GET', $endpoint, $params);
        
        if ($response) {
            $this->log("Media details retrieved for ID $mediaId: " . json_encode($response));
            return $response;
        } else {
            $this->log("Failed to get media details for ID $mediaId");
            return false;
        }
    }
    
    /**
     * Get comments for a media
     */
    public function getComments($mediaId, $fields = ['id', 'text', 'timestamp', 'username']) {
        $endpoint = $this->baseUrl . '/' . $mediaId . '/comments';
        
        $params = [
            'fields' => implode(',', $fields),
            'access_token' => $this->accessToken
        ];
        
        $response = $this->makeRequest('GET', $endpoint, $params);
        
        if ($response) {
            $this->log("Comments retrieved for media ID $mediaId: " . json_encode($response));
            return $response;
        } else {
            $this->log("Failed to get comments for media ID $mediaId");
            return false;
        }
    }
    
    /**
     * Reply to a comment
     */
    public function replyToComment($commentId, $message) {
        $endpoint = $this->baseUrl . '/' . $commentId . '/replies';
        
        $params = [
            'message' => $message,
            'access_token' => $this->accessToken
        ];
        
        $response = $this->makeRequest('POST', $endpoint, $params);
        
        if ($response && isset($response['id'])) {
            $this->log("Successfully replied to comment $commentId with message: $message");
            return $response;
        } else {
            $this->log("Failed to reply to comment $commentId: " . json_encode($response));
            return false;
        }
    }
    
    /**
     * Send a message to a user
     */
    public function sendMessage($recipientId, $message) {
        $endpoint = $this->baseUrl . '/me/messages';
        
        $params = [
            'recipient' => json_encode(['id' => $recipientId]),
            'message' => json_encode(['text' => $message]),
            'access_token' => $this->accessToken
        ];
        
        $response = $this->makeRequest('POST', $endpoint, $params);
        
        if ($response && isset($response['message_id'])) {
            $this->log("Successfully sent message to $recipientId: $message");
            return $response;
        } else {
            $this->log("Failed to send message to $recipientId: " . json_encode($response));
            return false;
        }
    }
    
    /**
     * Get user profile information
     */
    public function getUserProfile($userId, $fields = ['id', 'username', 'account_type']) {
        $endpoint = $this->baseUrl . '/' . $userId;
        
        $params = [
            'fields' => implode(',', $fields),
            'access_token' => $this->accessToken
        ];
        
        $response = $this->makeRequest('GET', $endpoint, $params);
        
        if ($response) {
            $this->log("User profile retrieved for ID $userId: " . json_encode($response));
            return $response;
        } else {
            $this->log("Failed to get user profile for ID $userId");
            return false;
        }
    }
    
    /**
     * Make HTTP request to Instagram API
     */
    private function makeRequest($method, $url, $params = []) {
        $ch = curl_init();
        
        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            } else {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: UTSerang-Instagram-Webhook/1.0'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            $this->log("cURL error: $error");
            return false;
        }
        
        if ($httpCode >= 400) {
            $this->log("HTTP error $httpCode: $response");
            return false;
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON decode error: " . json_last_error_msg());
            return false;
        }
        
        return $decodedResponse;
    }
    
    /**
     * Post a comment on Instagram media
     */
    public function postCommentOnMedia($mediaId, $commentText) {
        $endpoint = $this->baseUrl . '/' . $mediaId . '/comments';
        
        $params = [
            'message' => $commentText,
            'access_token' => $this->accessToken
        ];
        
        $this->log("Attempting to post comment on media $mediaId: " . substr($commentText, 0, 100));
        
        $response = $this->makeRequest('POST', $endpoint, $params);
        
        if ($response && isset($response['id'])) {
            $this->log("Successfully posted comment. Comment ID: " . $response['id']);
            return $response;
        } else {
            $this->log("Failed to post comment: " . json_encode($response));
            return $response;
        }
    }
    
    
    /**
     * Get comment information
     */
    public function getComment($commentId) {
        $endpoint = $this->baseUrl . '/' . $commentId;
        
        $params = [
            'fields' => 'id,text,timestamp,username,from,parent',
            'access_token' => $this->accessToken
        ];
        
        return $this->makeRequest('GET', $endpoint, $params);
    }

    /**
     * Log messages to file
     */
    private function log($message) {
        $logFile = __DIR__ . '/../logs/instagram_api.log';
        
        // Create logs directory if it doesn't exist
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}
?>
