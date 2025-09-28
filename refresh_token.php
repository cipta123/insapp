<?php
/**
 * Auto Token Refresh Script
 * This script automatically refreshes Instagram access tokens before they expire
 */

require_once 'config/instagram_config.php';

class TokenRefresher {
    private $config;
    private $logFile;
    
    public function __construct() {
        $this->config = require 'config/instagram_config.php';
        $this->logFile = 'logs/token_refresh.log';
        
        // Create logs directory if it doesn't exist
        if (!file_exists('logs')) {
            mkdir('logs', 0755, true);
        }
    }
    
    /**
     * Log messages with timestamp
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        echo $logMessage;
    }
    
    /**
     * Get current token info from Instagram API
     */
    public function getTokenInfo() {
        $url = $this->config['base_url'] . '/me?fields=id,username&access_token=' . $this->config['access_token'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        } else {
            $this->log("Error getting token info: HTTP $httpCode - $response");
            return false;
        }
    }
    
    /**
     * Refresh the access token using Instagram Basic Display API method
     */
    public function refreshToken() {
        $this->log("Starting token refresh process...");
        
        // First, check if current token is still valid
        $tokenInfo = $this->getTokenInfo();
        if (!$tokenInfo) {
            $this->log("Current token is invalid, refresh needed");
        } else {
            $this->log("Current token is valid for user: " . $tokenInfo['username']);
        }
        
        // Method 1: Try Instagram Graph API refresh
        $success = $this->refreshInstagramToken();
        
        if (!$success) {
            $this->log("Instagram method failed, trying Facebook Graph API method...");
            $success = $this->refreshFacebookToken();
        }
        
        return $success;
    }
    
    /**
     * Refresh using Instagram Graph API
     */
    private function refreshInstagramToken() {
        $url = "https://graph.instagram.com/refresh_access_token";
        $params = [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $this->config['access_token']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->log("Instagram API Response: HTTP $httpCode - $response");
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['access_token'])) {
                $newToken = $data['access_token'];
                $expiresIn = isset($data['expires_in']) ? $data['expires_in'] : 'Unknown';
                $this->log("Successfully got new Instagram token (expires in $expiresIn seconds): " . substr($newToken, 0, 20) . "...");
                
                // Update the config file
                if ($this->updateConfigFile($newToken)) {
                    $this->log("Token refresh completed successfully!");
                    return true;
                } else {
                    $this->log("Failed to update config file");
                    return false;
                }
            } else {
                $this->log("No access_token in Instagram API response: $response");
                return false;
            }
        } else {
            $this->log("Error refreshing Instagram token: HTTP $httpCode - $response");
            return false;
        }
    }
    
    /**
     * Refresh using Facebook Graph API (fallback method)
     */
    private function refreshFacebookToken() {
        $url = "https://graph.facebook.com/{$this->config['api_version']}/oauth/access_token";
        $params = [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'fb_exchange_token' => $this->config['access_token']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->log("Facebook API Response: HTTP $httpCode - $response");
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['access_token'])) {
                $newToken = $data['access_token'];
                $this->log("Successfully got new Facebook token: " . substr($newToken, 0, 20) . "...");
                
                // Update the config file
                if ($this->updateConfigFile($newToken)) {
                    $this->log("Token refresh completed successfully!");
                    return true;
                } else {
                    $this->log("Failed to update config file");
                    return false;
                }
            } else {
                $this->log("No access_token in Facebook API response: $response");
                return false;
            }
        } else {
            $this->log("Error refreshing Facebook token: HTTP $httpCode - $response");
            return false;
        }
    }
    
    /**
     * Update the config file with new token
     */
    private function updateConfigFile($newToken) {
        $configPath = 'config/instagram_config.php';
        $configContent = file_get_contents($configPath);
        
        // Replace the old token with new token
        $pattern = "/'access_token' => '[^']*'/";
        $replacement = "'access_token' => '$newToken'";
        $newContent = preg_replace($pattern, $replacement, $configContent);
        
        if ($newContent && $newContent !== $configContent) {
            if (file_put_contents($configPath, $newContent)) {
                $this->log("Config file updated successfully");
                return true;
            } else {
                $this->log("Failed to write to config file");
                return false;
            }
        } else {
            $this->log("Failed to update config content");
            return false;
        }
    }
    
    /**
     * Check if token needs refresh (runs daily)
     */
    public function checkAndRefresh() {
        $this->log("=== Daily Token Check Started ===");
        
        // Check if current token is valid
        $tokenInfo = $this->getTokenInfo();
        
        if (!$tokenInfo) {
            $this->log("Token is invalid, attempting refresh...");
            $result = $this->refreshToken();
            if ($result) {
                $this->log("Token refresh successful!");
            } else {
                $this->log("Token refresh failed!");
            }
        } else {
            $this->log("Token is still valid, no refresh needed");
        }
        
        $this->log("=== Daily Token Check Completed ===");
    }
}

// If script is run directly (not included)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $refresher = new TokenRefresher();
    
    // Check command line arguments
    $action = isset($argv[1]) ? $argv[1] : 'check';
    
    switch ($action) {
        case 'refresh':
            $refresher->refreshToken();
            break;
        case 'info':
            $info = $refresher->getTokenInfo();
            if ($info) {
                echo "Token is valid for user: " . $info['username'] . "\n";
            } else {
                echo "Token is invalid\n";
            }
            break;
        case 'check':
        default:
            $refresher->checkAndRefresh();
            break;
    }
}
?>
