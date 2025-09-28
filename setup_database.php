<?php
/**
 * Database Setup Script
 * Initialize database and tables for Instagram webhooks
 */

require_once 'classes/Database.php';

echo "<h1>Instagram Webhooks Database Setup</h1>";
echo "<hr>";

// Database configuration
$dbConfig = require 'config/database.php';

echo "<h2>1. Database Configuration</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td>Host</td><td>{$dbConfig['host']}</td></tr>";
echo "<tr><td>Database</td><td>{$dbConfig['database']}</td></tr>";
echo "<tr><td>Username</td><td>{$dbConfig['username']}</td></tr>";
echo "<tr><td>Password</td><td>" . (empty($dbConfig['password']) ? '(empty)' : '***') . "</td></tr>";
echo "</table>";

echo "<hr>";

// Test database connection
echo "<h2>2. Database Connection Test</h2>";

try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Check database status
    $status = $db->checkDatabase();
    
    if ($status) {
        echo "<h3>Database Status:</h3>";
        echo "<ul>";
        echo "<li><strong>Current Database:</strong> {$status['database']}</li>";
        echo "<li><strong>Tables Found:</strong> {$status['tables_exist']} / {$status['tables_total']}</li>";
        echo "</ul>";
        
        if (!empty($status['existing_tables'])) {
            echo "<h4>Existing Tables:</h4>";
            echo "<ul>";
            foreach ($status['existing_tables'] as $table) {
                echo "<li style='color: green;'>✅ $table</li>";
            }
            echo "</ul>";
        }
        
        if (!empty($status['missing_tables'])) {
            echo "<h4>Missing Tables:</h4>";
            echo "<ul>";
            foreach ($status['missing_tables'] as $table) {
                echo "<li style='color: red;'>❌ $table</li>";
            }
            echo "</ul>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<div style='background: #ffebee; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>Setup Instructions:</h3>";
    echo "<ol>";
    echo "<li>Make sure XAMPP MySQL is running</li>";
    echo "<li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>";
    echo "<li>Create a new database named: <strong>{$dbConfig['database']}</strong></li>";
    echo "<li>Import the SQL file: <strong>database/instagram_webhooks.sql</strong></li>";
    echo "<li>Refresh this page</li>";
    echo "</ol>";
    echo "</div>";
    exit;
}

echo "<hr>";

// Create database and tables if needed
echo "<h2>3. Database Setup</h2>";

if (isset($_POST['create_database'])) {
    echo "<h3>Creating Database and Tables...</h3>";
    
    try {
        // Read SQL file
        $sqlFile = __DIR__ . '/database/instagram_webhooks.sql';
        
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL file not found: $sqlFile");
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^(--|\/\*|\s*$)/', $stmt);
            }
        );
        
        $connection = $db->getConnection();
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($statements as $statement) {
            try {
                $connection->exec($statement);
                $successCount++;
            } catch (PDOException $e) {
                // Skip errors for existing tables/data
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    echo "<p style='color: orange;'>⚠️ Warning: " . $e->getMessage() . "</p>";
                    $errorCount++;
                }
            }
        }
        
        echo "<p style='color: green;'>✅ Database setup completed!</p>";
        echo "<p>Executed: $successCount statements";
        if ($errorCount > 0) {
            echo " ($errorCount warnings)";
        }
        echo "</p>";
        
        // Refresh status
        $status = $db->checkDatabase();
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Database setup failed: " . $e->getMessage() . "</p>";
    }
}

// Show setup form if tables are missing
$status = $db->checkDatabase();
if ($status && !empty($status['missing_tables'])) {
    echo "<form method='POST'>";
    echo "<p>Some tables are missing. Click the button below to create them:</p>";
    echo "<button type='submit' name='create_database' style='background: #1877f2; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>";
    echo "Create Database Tables";
    echo "</button>";
    echo "</form>";
} else {
    echo "<p style='color: green;'>✅ All required tables are present!</p>";
}

echo "<hr>";

// Test data insertion
echo "<h2>4. Test Data Operations</h2>";

if (isset($_POST['test_data'])) {
    try {
        // Test webhook event log
        $testData = [
            'event_type' => 'test',
            'object_type' => 'instagram',
            'object_id' => 'test_' . time(),
            'field_name' => 'comments',
            'verb' => 'add',
            'raw_payload' => json_encode(['test' => true, 'timestamp' => time()]),
            'processed' => 0
        ];
        
        $insertId = $db->insert('webhook_events_log', $testData);
        echo "<p style='color: green;'>✅ Test data inserted successfully! ID: $insertId</p>";
        
        // Test data retrieval
        $results = $db->select('webhook_events_log', '*', 'id = ?', [$insertId]);
        if (!empty($results)) {
            echo "<p style='color: green;'>✅ Test data retrieved successfully!</p>";
            echo "<pre>" . json_encode($results[0], JSON_PRETTY_PRINT) . "</pre>";
        }
        
        // Clean up test data
        $db->delete('webhook_events_log', 'id = ?', [$insertId]);
        echo "<p style='color: green;'>✅ Test data cleaned up!</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Data operation test failed: " . $e->getMessage() . "</p>";
    }
}

if ($status && empty($status['missing_tables'])) {
    echo "<form method='POST'>";
    echo "<button type='submit' name='test_data' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>";
    echo "Test Data Operations";
    echo "</button>";
    echo "</form>";
}

echo "<hr>";

// Next steps
echo "<h2>5. Next Steps</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "<h3>After database setup is complete:</h3>";
echo "<ol>";
echo "<li>✅ Database is configured and ready</li>";
echo "<li>🔄 <a href='setup_webhooks.php'>Run webhook subscription setup</a></li>";
echo "<li>🔄 <a href='test_webhook.php'>Test webhook functionality</a></li>";
echo "<li>🔄 Configure webhooks in Meta App Dashboard</li>";
echo "<li>🔄 Test with real Instagram interactions</li>";
echo "</ol>";
echo "</div>";

?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

table {
    width: 100%;
    margin: 20px 0;
}

th, td {
    padding: 10px;
    text-align: left;
    border: 1px solid #ddd;
}

th {
    background-color: #f2f2f2;
}

pre {
    background: #f8f8f8;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    overflow-x: auto;
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
</style>
