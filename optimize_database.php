<?php
/**
 * Database Optimization Script
 * Adds indexes and optimizes database structure for better performance
 */

require_once 'classes/SimpleDatabase.php';

try {
    $db = SimpleDatabase::getInstance();
    echo "<h2>🚀 Database Optimization Script</h2>";
    echo "<pre>";
    
    // Check current indexes
    echo "📊 Checking current database structure...\n\n";
    
    // Add indexes for instagram_comments table
    echo "🔧 Optimizing instagram_comments table...\n";
    
    $commentIndexes = [
        "CREATE INDEX IF NOT EXISTS idx_comments_created_at ON instagram_comments(created_at DESC)",
        "CREATE INDEX IF NOT EXISTS idx_comments_username ON instagram_comments(username)",
        "CREATE INDEX IF NOT EXISTS idx_comments_media_id ON instagram_comments(media_id)",
        "CREATE INDEX IF NOT EXISTS idx_comments_parent ON instagram_comments(parent_comment_id)",
        "CREATE INDEX IF NOT EXISTS idx_comments_composite ON instagram_comments(created_at DESC, username)",
        "CREATE INDEX IF NOT EXISTS idx_comments_search ON instagram_comments(comment_text(100))" // Full-text search
    ];
    
    foreach ($commentIndexes as $sql) {
        try {
            $db->query($sql);
            echo "✅ " . substr($sql, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // Add indexes for instagram_messages table
    echo "\n🔧 Optimizing instagram_messages table...\n";
    
    $messageIndexes = [
        "CREATE INDEX IF NOT EXISTS idx_messages_created_at ON instagram_messages(created_at DESC)",
        "CREATE INDEX IF NOT EXISTS idx_messages_sender ON instagram_messages(sender_id)",
        "CREATE INDEX IF NOT EXISTS idx_messages_type ON instagram_messages(message_type)",
        "CREATE INDEX IF NOT EXISTS idx_messages_echo ON instagram_messages(is_echo)",
        "CREATE INDEX IF NOT EXISTS idx_messages_composite ON instagram_messages(created_at DESC, sender_id)",
        "CREATE INDEX IF NOT EXISTS idx_messages_search ON instagram_messages(message_text(100))"
    ];
    
    foreach ($messageIndexes as $sql) {
        try {
            $db->query($sql);
            echo "✅ " . substr($sql, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // Optimize table structure
    echo "\n🔧 Optimizing table structure...\n";
    
    $optimizations = [
        "ALTER TABLE instagram_comments ENGINE=InnoDB",
        "ALTER TABLE instagram_messages ENGINE=InnoDB",
        "OPTIMIZE TABLE instagram_comments",
        "OPTIMIZE TABLE instagram_messages"
    ];
    
    foreach ($optimizations as $sql) {
        try {
            $db->query($sql);
            echo "✅ " . substr($sql, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "⚠️  " . $e->getMessage() . "\n";
        }
    }
    
    // Add performance monitoring table
    echo "\n📈 Creating performance monitoring table...\n";
    
    $monitoringTable = "
    CREATE TABLE IF NOT EXISTS performance_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        metric_name VARCHAR(100) NOT NULL,
        metric_value DECIMAL(10,4) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_metrics_name (metric_name),
        INDEX idx_metrics_created (created_at DESC)
    ) ENGINE=InnoDB
    ";
    
    try {
        $db->query($monitoringTable);
        echo "✅ Performance metrics table created\n";
    } catch (Exception $e) {
        echo "❌ Error creating metrics table: " . $e->getMessage() . "\n";
    }
    
    // Show index information
    echo "\n📋 Current indexes on instagram_comments:\n";
    $indexes = $db->query("SHOW INDEX FROM instagram_comments");
    foreach ($indexes as $index) {
        echo "   - {$index['Key_name']}: {$index['Column_name']}\n";
    }
    
    echo "\n📋 Current indexes on instagram_messages:\n";
    $indexes = $db->query("SHOW INDEX FROM instagram_messages");
    foreach ($indexes as $index) {
        echo "   - {$index['Key_name']}: {$index['Column_name']}\n";
    }
    
    // Performance test
    echo "\n⚡ Running performance tests...\n";
    
    $startTime = microtime(true);
    $db->select('instagram_comments', 'COUNT(*) as count', 'created_at >= ?', [date('Y-m-d H:i:s', strtotime('-24 hours'))]);
    $commentTime = (microtime(true) - $startTime) * 1000;
    
    $startTime = microtime(true);
    $db->select('instagram_messages', 'COUNT(*) as count', 'created_at >= ?', [date('Y-m-d H:i:s', strtotime('-24 hours'))]);
    $messageTime = (microtime(true) - $startTime) * 1000;
    
    echo "📊 Query Performance:\n";
    echo "   - Comments query: {$commentTime}ms\n";
    echo "   - Messages query: {$messageTime}ms\n";
    
    // Store performance metrics
    try {
        $db->insert('performance_metrics', [
            'metric_name' => 'comments_query_time',
            'metric_value' => $commentTime
        ]);
        $db->insert('performance_metrics', [
            'metric_name' => 'messages_query_time', 
            'metric_value' => $messageTime
        ]);
        echo "✅ Performance metrics stored\n";
    } catch (Exception $e) {
        echo "⚠️  Could not store metrics: " . $e->getMessage() . "\n";
    }
    
    echo "\n🎉 Database optimization completed!\n";
    echo "\n💡 Recommendations:\n";
    echo "   - Run this script monthly for maintenance\n";
    echo "   - Monitor query performance regularly\n";
    echo "   - Consider partitioning for very large datasets\n";
    echo "   - Enable MySQL query cache if not already enabled\n";
    
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error: " . $e->getMessage() . "</h3>";
}
?>
