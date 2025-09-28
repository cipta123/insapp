<?php
/**
 * Database Fix Script
 * Fix the database connection and table issues
 */

echo "<h1>🔧 Database Fix Script</h1>";
echo "<hr>";

// Step 1: Direct database connection test
echo "<h2>1. Direct Database Connection Test</h2>";

try {
    $config = require 'config/database.php';
    
    echo "<p><strong>Config loaded:</strong></p>";
    echo "<ul>";
    echo "<li>Host: {$config['host']}</li>";
    echo "<li>Database: {$config['database']}</li>";
    echo "<li>Username: {$config['username']}</li>";
    echo "<li>Password: " . (empty($config['password']) ? '(empty)' : '***') . "</li>";
    echo "</ul>";
    
    // Create connection without specifying database first
    $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ MySQL connection successful</p>";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✅ Database ensured to exist</p>";
    
    // Connect to the specific database
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Connected to database '{$config['database']}'</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

echo "<hr>";

// Step 2: Check tables using direct query (not prepared statement)
echo "<h2>2. Check Tables (Fixed Method)</h2>";

$requiredTables = [
    'instagram_comments',
    'instagram_messages',
    'instagram_mentions', 
    'instagram_message_reactions',
    'instagram_story_insights',
    'instagram_media',
    'instagram_users',
    'webhook_events_log',
    'app_config'
];

$existingTables = [];
$missingTables = [];

foreach ($requiredTables as $table) {
    try {
        // Use direct query instead of prepared statement for SHOW TABLES
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            $existingTables[] = $table;
            echo "<p style='color: green;'>✅ $table exists</p>";
        } else {
            $missingTables[] = $table;
            echo "<p style='color: red;'>❌ $table missing</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Error checking $table: " . $e->getMessage() . "</p>";
        $missingTables[] = $table;
    }
}

echo "<hr>";

// Step 3: Create tables if missing
if (!empty($missingTables)) {
    echo "<h2>3. Create Missing Tables</h2>";
    
    if (isset($_POST['create_tables'])) {
        // Create tables manually with essential structure
        $tableQueries = [
            'webhook_events_log' => "
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
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'instagram_messages' => "
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
                    webhook_data JSON NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'instagram_comments' => "
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
                    webhook_data JSON NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'instagram_mentions' => "
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
                    webhook_data JSON NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
        ];
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($tableQueries as $tableName => $query) {
            try {
                $pdo->exec($query);
                echo "<p style='color: green;'>✅ Created table: $tableName</p>";
                $successCount++;
            } catch (PDOException $e) {
                echo "<p style='color: red;'>❌ Failed to create $tableName: " . $e->getMessage() . "</p>";
                $errorCount++;
            }
        }
        
        echo "<p><strong>Summary:</strong> $successCount created, $errorCount errors</p>";
        
        if ($successCount > 0) {
            echo "<p style='color: green;'>✅ Essential tables created! Refresh page to see updated status.</p>";
            echo "<script>setTimeout(function(){ location.reload(); }, 3000);</script>";
        }
        
    } else {
        echo "<p>Missing tables detected. Click to create essential tables:</p>";
        echo "<form method='POST'>";
        echo "<button type='submit' name='create_tables' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Create Essential Tables</button>";
        echo "</form>";
    }
} else {
    echo "<h2>3. All Tables Present</h2>";
    echo "<p style='color: green;'>✅ All required tables exist!</p>";
}

echo "<hr>";

// Step 4: Test database operations
if (empty($missingTables)) {
    echo "<h2>4. Test Database Operations</h2>";
    
    if (isset($_POST['test_insert'])) {
        try {
            // Test webhook_events_log insert
            $testData = [
                'event_type' => 'test_fix',
                'object_type' => 'instagram',
                'object_id' => 'test_' . time(),
                'field_name' => 'messages',
                'verb' => 'add',
                'raw_payload' => json_encode(['test' => 'database_fix', 'timestamp' => time()]),
                'processed' => 1
            ];
            
            $columns = implode(',', array_keys($testData));
            $placeholders = ':' . implode(', :', array_keys($testData));
            $sql = "INSERT INTO webhook_events_log ($columns) VALUES ($placeholders)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($testData);
            $insertId = $pdo->lastInsertId();
            
            echo "<p style='color: green;'>✅ Insert test successful! ID: $insertId</p>";
            
            // Test select
            $stmt = $pdo->prepare("SELECT * FROM webhook_events_log WHERE id = ?");
            $stmt->execute([$insertId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo "<p style='color: green;'>✅ Select test successful!</p>";
                echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;'>";
                echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT));
                echo "</pre>";
            }
            
            // Cleanup
            $stmt = $pdo->prepare("DELETE FROM webhook_events_log WHERE id = ?");
            $stmt->execute([$insertId]);
            
            echo "<p style='color: green;'>✅ All database operations working perfectly!</p>";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Database operation failed: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<form method='POST'>";
        echo "<button type='submit' name='test_insert' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Test Database Operations</button>";
        echo "</form>";
    }
}

echo "<hr>";

// Step 5: Clear old logs and reset Database class
echo "<h2>5. Clear Logs and Reset</h2>";

if (isset($_POST['clear_logs'])) {
    $logFiles = [
        'logs/database.log',
        'logs/webhook.log', 
        'logs/instagram_api.log'
    ];
    
    foreach ($logFiles as $logFile) {
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            echo "<p style='color: green;'>✅ Cleared: $logFile</p>";
        }
    }
    
    echo "<p style='color: green;'>✅ All logs cleared! Old errors should be gone.</p>";
}

echo "<form method='POST'>";
echo "<button type='submit' name='clear_logs' style='background: #ffc107; color: black; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Clear All Logs</button>";
echo "</form>";

echo "<hr>";

// Step 6: Test the Database class
echo "<h2>6. Test Database Class</h2>";

if (isset($_POST['test_class'])) {
    try {
        // Force reload the class
        if (class_exists('Database')) {
            echo "<p style='color: orange;'>⚠️ Database class already loaded</p>";
        }
        
        require_once 'classes/Database.php';
        $db = Database::getInstance();
        
        echo "<p style='color: green;'>✅ Database class loaded successfully!</p>";
        
        // Test the problematic checkDatabase method
        $status = $db->checkDatabase();
        if ($status) {
            echo "<p style='color: green;'>✅ checkDatabase() method working!</p>";
            echo "<p>Database: {$status['database']}</p>";
            echo "<p>Tables: {$status['tables_exist']} / {$status['tables_total']}</p>";
        } else {
            echo "<p style='color: red;'>❌ checkDatabase() method failed</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Database class error: " . $e->getMessage() . "</p>";
    }
}

echo "<form method='POST'>";
echo "<button type='submit' name='test_class' style='background: #17a2b8; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Test Database Class</button>";
echo "</form>";

echo "<hr>";

// Final summary
echo "<h2>7. Next Steps</h2>";

if (empty($missingTables)) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
    echo "<h3 style='color: #155724;'>✅ Database Should Be Working Now!</h3>";
    echo "<p><strong>Test your webhooks:</strong></p>";
    echo "<ol>";
    echo "<li>Send a DM to your Instagram account</li>";
    echo "<li>Comment on your Instagram post</li>";
    echo "<li>Check: <a href='webhook_monitor.php'>webhook_monitor.php</a></li>";
    echo "<li>Look for saved data in database tables</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24;'>⚠️ Create Tables First</h3>";
    echo "<p>Click the 'Create Essential Tables' button above first.</p>";
    echo "</div>";
}

?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
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

button {
    margin: 5px;
}

button:hover {
    opacity: 0.9;
}

pre {
    max-height: 300px;
    overflow-y: auto;
}

a {
    color: #007bff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>
