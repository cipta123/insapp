<?php
/**
 * Simple Database Connection Test
 * Test database connection and create tables if needed
 */

echo "<h1>🗄️ Database Connection Test</h1>";
echo "<hr>";

// Test 1: Basic Connection
echo "<h2>1. Testing Database Connection</h2>";

try {
    $config = require 'config/database.php';
    
    $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    
    echo "<p style='color: green;'>✅ MySQL connection successful!</p>";
    
    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$config['database']}'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Database '{$config['database']}' exists</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Database '{$config['database']}' does not exist</p>";
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p style='color: green;'>✅ Database '{$config['database']}' created</p>";
    }
    
    // Connect to the specific database
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    
    echo "<p style='color: green;'>✅ Connected to database '{$config['database']}'</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

echo "<hr>";

// Test 2: Check Tables
echo "<h2>2. Checking Required Tables</h2>";

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
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $existingTables[] = $table;
            echo "<p style='color: green;'>✅ Table '$table' exists</p>";
        } else {
            $missingTables[] = $table;
            echo "<p style='color: red;'>❌ Table '$table' missing</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Error checking table '$table': " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";

// Test 3: Create Missing Tables
if (!empty($missingTables)) {
    echo "<h2>3. Creating Missing Tables</h2>";
    
    if (isset($_POST['create_tables'])) {
        $sqlFile = __DIR__ . '/database/instagram_webhooks.sql';
        
        if (file_exists($sqlFile)) {
            try {
                $sql = file_get_contents($sqlFile);
                
                // Remove comments and split into statements
                $sql = preg_replace('/--.*$/m', '', $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
                
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    function($stmt) {
                        return !empty($stmt) && !preg_match('/^\s*$/', $stmt);
                    }
                );
                
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($statements as $statement) {
                    try {
                        $pdo->exec($statement);
                        $successCount++;
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            echo "<p style='color: orange;'>⚠️ " . $e->getMessage() . "</p>";
                            $errorCount++;
                        }
                    }
                }
                
                echo "<p style='color: green;'>✅ Tables created successfully!</p>";
                echo "<p>Executed: $successCount statements";
                if ($errorCount > 0) {
                    echo " ($errorCount warnings)";
                }
                echo "</p>";
                
                // Refresh page to show updated status
                echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
                
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Error creating tables: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ SQL file not found: $sqlFile</p>";
        }
    } else {
        echo "<p>Missing tables detected. Click below to create them:</p>";
        echo "<form method='POST'>";
        echo "<button type='submit' name='create_tables' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Create Missing Tables</button>";
        echo "</form>";
    }
} else {
    echo "<h2>3. All Tables Present</h2>";
    echo "<p style='color: green;'>✅ All required tables exist!</p>";
}

echo "<hr>";

// Test 4: Test Data Operations
if (empty($missingTables)) {
    echo "<h2>4. Testing Data Operations</h2>";
    
    if (isset($_POST['test_operations'])) {
        try {
            // Test insert
            $testData = [
                'event_type' => 'test',
                'object_type' => 'instagram', 
                'object_id' => 'test_' . time(),
                'field_name' => 'messages',
                'verb' => 'add',
                'raw_payload' => json_encode(['test' => true, 'timestamp' => time()]),
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
            $result = $stmt->fetch();
            
            if ($result) {
                echo "<p style='color: green;'>✅ Select test successful!</p>";
            }
            
            // Test delete (cleanup)
            $stmt = $pdo->prepare("DELETE FROM webhook_events_log WHERE id = ?");
            $stmt->execute([$insertId]);
            
            echo "<p style='color: green;'>✅ Delete test successful!</p>";
            echo "<p style='color: green;'><strong>✅ Database is fully functional!</strong></p>";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Data operation test failed: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<form method='POST'>";
        echo "<button type='submit' name='test_operations' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Test Data Operations</button>";
        echo "</form>";
    }
}

echo "<hr>";

// Test 5: Database Class Test
echo "<h2>5. Testing Database Class</h2>";

try {
    require_once 'classes/Database.php';
    $db = Database::getInstance();
    
    echo "<p style='color: green;'>✅ Database class instantiated successfully!</p>";
    
    $status = $db->checkDatabase();
    if ($status) {
        echo "<p><strong>Database Status:</strong></p>";
        echo "<ul>";
        echo "<li>Database: {$status['database']}</li>";
        echo "<li>Tables: {$status['tables_exist']} / {$status['tables_total']}</li>";
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database class error: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// Summary
echo "<h2>6. Summary</h2>";

if (empty($missingTables)) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
    echo "<h3 style='color: #155724;'>✅ Database Setup Complete!</h3>";
    echo "<p>Your database is ready for webhook data storage.</p>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Test webhooks: <a href='webhook_monitor.php'>webhook_monitor.php</a></li>";
    echo "<li>Send Instagram DM or comment to test</li>";
    echo "<li>Check logs for webhook activity</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24;'>⚠️ Database Setup Incomplete</h3>";
    echo "<p>Some tables are missing. Please create them using the button above.</p>";
    echo "</div>";
}

?>

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
