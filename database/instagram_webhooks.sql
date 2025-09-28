-- Instagram Webhooks Database Schema
-- Create database and tables for storing webhook data

-- Create database (uncomment if needed)
-- CREATE DATABASE instagram_webhooks;
-- USE instagram_webhooks;

-- Table for storing Instagram comments
CREATE TABLE IF NOT EXISTS instagram_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_id VARCHAR(255) NOT NULL,
    comment_id VARCHAR(255) UNIQUE NOT NULL,
    parent_comment_id VARCHAR(255) NULL,
    user_id VARCHAR(255) NOT NULL,
    username VARCHAR(255) NULL,
    comment_text TEXT NULL,
    verb ENUM('add', 'edited', 'remove') NOT NULL DEFAULT 'add',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    webhook_data JSON NULL,
    INDEX idx_media_id (media_id),
    INDEX idx_comment_id (comment_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Table for storing Instagram messages
CREATE TABLE IF NOT EXISTS instagram_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255) UNIQUE NOT NULL,
    sender_id VARCHAR(255) NOT NULL,
    recipient_id VARCHAR(255) NOT NULL,
    message_text TEXT NULL,
    message_type ENUM('text', 'image', 'video', 'audio', 'file') DEFAULT 'text',
    is_echo BOOLEAN DEFAULT FALSE,
    is_self BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    webhook_data JSON NULL,
    INDEX idx_message_id (message_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_recipient_id (recipient_id),
    INDEX idx_created_at (created_at)
);

-- Table for storing Instagram mentions
CREATE TABLE IF NOT EXISTS instagram_mentions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_id VARCHAR(255) NOT NULL,
    mention_id VARCHAR(255) UNIQUE NOT NULL,
    mentioned_user_id VARCHAR(255) NOT NULL,
    mentioned_username VARCHAR(255) NULL,
    mentioning_user_id VARCHAR(255) NOT NULL,
    mentioning_username VARCHAR(255) NULL,
    mention_text TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    webhook_data JSON NULL,
    INDEX idx_media_id (media_id),
    INDEX idx_mentioned_user_id (mentioned_user_id),
    INDEX idx_mentioning_user_id (mentioning_user_id),
    INDEX idx_created_at (created_at)
);

-- Table for storing Instagram message reactions
CREATE TABLE IF NOT EXISTS instagram_message_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255) NOT NULL,
    reaction_id VARCHAR(255) UNIQUE NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    reaction_type VARCHAR(50) NOT NULL,
    verb ENUM('add', 'remove') NOT NULL DEFAULT 'add',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    webhook_data JSON NULL,
    INDEX idx_message_id (message_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Table for storing Instagram story insights
CREATE TABLE IF NOT EXISTS instagram_story_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    story_id VARCHAR(255) UNIQUE NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    insights_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    webhook_data JSON NULL,
    INDEX idx_story_id (story_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Table for storing Instagram media information
CREATE TABLE IF NOT EXISTS instagram_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_id VARCHAR(255) UNIQUE NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    media_type ENUM('IMAGE', 'VIDEO', 'CAROUSEL_ALBUM') NOT NULL,
    media_url TEXT NULL,
    permalink TEXT NULL,
    caption TEXT NULL,
    timestamp TIMESTAMP NULL,
    like_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_media_id (media_id),
    INDEX idx_user_id (user_id),
    INDEX idx_timestamp (timestamp)
);

-- Table for storing Instagram users
CREATE TABLE IF NOT EXISTS instagram_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(255) NULL,
    account_type ENUM('PERSONAL', 'BUSINESS', 'CREATOR') NULL,
    profile_picture_url TEXT NULL,
    followers_count INT DEFAULT 0,
    following_count INT DEFAULT 0,
    media_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_username (username)
);

-- Table for storing webhook events log
CREATE TABLE IF NOT EXISTS webhook_events_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    object_type VARCHAR(50) NOT NULL,
    object_id VARCHAR(255) NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    verb VARCHAR(50) NOT NULL,
    raw_payload JSON NOT NULL,
    processed BOOLEAN DEFAULT FALSE,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_object_id (object_id),
    INDEX idx_created_at (created_at),
    INDEX idx_processed (processed)
);

-- Table for storing app configuration
CREATE TABLE IF NOT EXISTS app_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default configuration values
INSERT INTO app_config (config_key, config_value, description) VALUES
('webhook_verify_token', 'utserang_webhook_verify_token_2024', 'Token used to verify webhook requests from Meta'),
('auto_reply_enabled', 'false', 'Enable automatic replies to comments and messages'),
('auto_reply_message', 'Terima kasih telah menghubungi UT Serang! Kami akan segera merespons pesan Anda.', 'Default auto-reply message'),
('log_retention_days', '30', 'Number of days to keep webhook logs'),
('rate_limit_enabled', 'true', 'Enable rate limiting for API calls')
ON DUPLICATE KEY UPDATE 
config_value = VALUES(config_value),
updated_at = CURRENT_TIMESTAMP;

-- Create views for easier data access
CREATE OR REPLACE VIEW recent_comments AS
SELECT 
    c.id,
    c.media_id,
    c.comment_id,
    c.comment_text,
    c.username,
    c.verb,
    c.created_at,
    m.caption as media_caption,
    m.media_type
FROM instagram_comments c
LEFT JOIN instagram_media m ON c.media_id = m.media_id
WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY c.created_at DESC;

CREATE OR REPLACE VIEW recent_messages AS
SELECT 
    m.id,
    m.message_id,
    m.sender_id,
    m.recipient_id,
    m.message_text,
    m.message_type,
    m.is_echo,
    m.created_at,
    u.username as sender_username
FROM instagram_messages m
LEFT JOIN instagram_users u ON m.sender_id = u.user_id
WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY m.created_at DESC;

-- Create stored procedures for common operations
DELIMITER //

CREATE PROCEDURE GetWebhookStats(IN days_back INT)
BEGIN
    SELECT 
        'Comments' as event_type,
        COUNT(*) as total_count,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL days_back DAY) THEN 1 END) as recent_count
    FROM instagram_comments
    
    UNION ALL
    
    SELECT 
        'Messages' as event_type,
        COUNT(*) as total_count,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL days_back DAY) THEN 1 END) as recent_count
    FROM instagram_messages
    
    UNION ALL
    
    SELECT 
        'Mentions' as event_type,
        COUNT(*) as total_count,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL days_back DAY) THEN 1 END) as recent_count
    FROM instagram_mentions;
END //

CREATE PROCEDURE CleanupOldLogs(IN retention_days INT)
BEGIN
    DELETE FROM webhook_events_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL retention_days DAY);
    
    SELECT ROW_COUNT() as deleted_rows;
END //

DELIMITER ;

-- Create indexes for better performance
CREATE INDEX idx_webhook_events_created_at ON webhook_events_log(created_at);
CREATE INDEX idx_comments_created_at ON instagram_comments(created_at);
CREATE INDEX idx_messages_created_at ON instagram_messages(created_at);
CREATE INDEX idx_mentions_created_at ON instagram_mentions(created_at);

-- Grant permissions (adjust as needed for your setup)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON instagram_webhooks.* TO 'webhook_user'@'localhost';
-- FLUSH PRIVILEGES;
