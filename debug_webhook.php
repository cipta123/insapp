<?php
/**
 * Debug Webhook Data
 * Melihat struktur data webhook yang sebenarnya diterima dari Instagram
 */

require_once 'classes/SimpleDatabase.php';

echo "<h1>🔍 Debug Webhook Data</h1>";
echo "<p>Melihat struktur data webhook yang diterima dari Instagram...</p>";
echo "<hr>";

try {
    $db = SimpleDatabase::getInstance();
    echo "<p style='color: green;'>✅ Database connected</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Get recent webhook events with full payload
echo "<h2>1. Recent Webhook Payloads (Raw Data)</h2>";

$events = $db->select('webhook_events_log', '*', '', [], 'created_at DESC', '5');

if (!empty($events)) {
    foreach ($events as $index => $event) {
        $time = date('Y-m-d H:i:s', strtotime($event['created_at']));
        
        echo "<div style='background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 8px; border: 1px solid #ddd;'>";
        echo "<h3>Event #" . ($index + 1) . " - {$event['field_name']} ({$event['verb']})</h3>";
        echo "<p><strong>Time:</strong> $time | <strong>Object ID:</strong> {$event['object_id']}</p>";
        
        echo "<h4>Raw Payload:</h4>";
        echo "<pre style='background: #ffffff; padding: 10px; border: 1px solid #ccc; border-radius: 4px; max-height: 400px; overflow-y: auto; font-size: 12px;'>";
        
        $payload = json_decode($event['raw_payload'], true);
        if ($payload) {
            echo htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT));
        } else {
            echo htmlspecialchars($event['raw_payload']);
        }
        echo "</pre>";
        
        // Analyze structure
        if ($payload && isset($payload['entry'])) {
            echo "<h4>Analyzed Structure:</h4>";
            foreach ($payload['entry'] as $entryIndex => $entry) {
                echo "<div style='background: #e3f2fd; padding: 10px; margin: 5px 0; border-radius: 4px;'>";
                echo "<strong>Entry $entryIndex:</strong><br>";
                echo "ID: " . ($entry['id'] ?? 'N/A') . "<br>";
                echo "Time: " . ($entry['time'] ?? 'N/A') . "<br>";
                
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $changeIndex => $change) {
                        echo "<div style='margin-left: 20px; margin-top: 5px;'>";
                        echo "<strong>Change $changeIndex:</strong><br>";
                        echo "Field: " . ($change['field'] ?? 'N/A') . "<br>";
                        
                        if (isset($change['value'])) {
                            echo "Value keys: " . implode(', ', array_keys($change['value'])) . "<br>";
                            
                            // Show specific fields for debugging
                            $value = $change['value'];
                            echo "<div style='font-size: 11px; color: #666; margin-top: 5px;'>";
                            echo "object_id: " . ($value['object_id'] ?? 'N/A') . "<br>";
                            echo "verb: " . ($value['verb'] ?? 'N/A') . "<br>";
                            
                            if ($change['field'] === 'messages') {
                                echo "from: " . (isset($value['from']) ? json_encode($value['from']) : 'N/A') . "<br>";
                                echo "to: " . (isset($value['to']) ? json_encode($value['to']) : 'N/A') . "<br>";
                                echo "message: " . (isset($value['message']) ? json_encode($value['message']) : 'N/A') . "<br>";
                            }
                            
                            if ($change['field'] === 'comments') {
                                echo "from: " . (isset($value['from']) ? json_encode($value['from']) : 'N/A') . "<br>";
                                echo "text: " . ($value['text'] ?? 'N/A') . "<br>";
                                echo "parent_id: " . ($value['parent_id'] ?? 'N/A') . "<br>";
                            }
                            echo "</div>";
                        }
                        echo "</div>";
                    }
                }
                echo "</div>";
            }
        }
        echo "</div>";
    }
} else {
    echo "<p>Tidak ada webhook events. Kirim DM atau comment untuk test.</p>";
}

echo "<hr>";

// Check if tables exist and have correct structure
echo "<h2>2. Database Tables Check</h2>";

$tables = ['instagram_messages', 'instagram_comments'];

foreach ($tables as $table) {
    echo "<h3>Table: $table</h3>";
    
    if ($db->tableExists($table)) {
        echo "<p style='color: green;'>✅ Table exists</p>";
        
        // Get table structure
        try {
            $structure = $db->getConnection()->query("DESCRIBE $table")->fetchAll();
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse; font-size: 12px;'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            foreach ($structure as $column) {
                echo "<tr>";
                echo "<td>{$column['Field']}</td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "<td>{$column['Default']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Count records
            $count = $db->select($table, 'COUNT(*) as count')[0]['count'] ?? 0;
            echo "<p><strong>Records:</strong> $count</p>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error getting table structure: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Table does not exist</p>";
    }
}

echo "<hr>";

// Test manual insert
echo "<h2>3. Test Manual Insert</h2>";

if (isset($_POST['test_insert'])) {
    $testType = $_POST['test_type'];
    
    try {
        if ($testType === 'message') {
            $testData = [
                'message_id' => 'test_msg_' . time(),
                'sender_id' => 'test_sender_123',
                'recipient_id' => 'test_recipient_456', 
                'message_text' => 'Test message from debug script',
                'message_type' => 'text',
                'is_echo' => 0,
                'is_self' => 0,
                'webhook_data' => json_encode(['test' => true])
            ];
            
            $insertId = $db->insert('instagram_messages', $testData);
            if ($insertId) {
                echo "<p style='color: green;'>✅ Test message inserted with ID: $insertId</p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to insert test message</p>";
            }
            
        } elseif ($testType === 'comment') {
            $testData = [
                'media_id' => 'test_media_' . time(),
                'comment_id' => 'test_comment_' . time(),
                'parent_comment_id' => null,
                'user_id' => 'test_user_789',
                'username' => 'test_username',
                'comment_text' => 'Test comment from debug script',
                'verb' => 'add',
                'webhook_data' => json_encode(['test' => true])
            ];
            
            $insertId = $db->insert('instagram_comments', $testData);
            if ($insertId) {
                echo "<p style='color: green;'>✅ Test comment inserted with ID: $insertId</p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to insert test comment</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Insert test failed: " . $e->getMessage() . "</p>";
    }
}

echo "<form method='POST'>";
echo "<button type='submit' name='test_insert' value='1' onclick=\"this.form.test_type.value='message'\" style='background: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; margin: 5px;'>Test Message Insert</button>";
echo "<button type='submit' name='test_insert' value='1' onclick=\"this.form.test_type.value='comment'\" style='background: #28a745; color: white; padding: 8px 16px; border: none; border-radius: 4px; margin: 5px;'>Test Comment Insert</button>";
echo "<input type='hidden' name='test_type' value=''>";
echo "</form>";

echo "<hr>";

// Show recent logs
echo "<h2>4. Recent Webhook Logs</h2>";

$logFile = 'logs/webhook.log';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    if (!empty($logs)) {
        $lines = explode("\n", $logs);
        $recentLines = array_slice($lines, -20); // Last 20 lines
        
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto;'>";
        echo "<pre style='margin: 0; font-size: 11px;'>";
        foreach ($recentLines as $line) {
            if (strpos($line, 'saveCommentToDatabase') !== false || strpos($line, 'saveMessageToDatabase') !== false) {
                echo "<span style='color: blue; font-weight: bold;'>$line</span>\n";
            } elseif (strpos($line, '✅') !== false) {
                echo "<span style='color: green;'>$line</span>\n";
            } elseif (strpos($line, '❌') !== false) {
                echo "<span style='color: red;'>$line</span>\n";
            } elseif (strpos($line, 'Handling') !== false) {
                echo "<span style='color: purple;'>$line</span>\n";
            } else {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo "</pre>";
        echo "</div>";
    }
}

echo "<hr>";

// Instructions
echo "<h2>5. Debug Instructions</h2>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<h3>🔍 Cara Debug:</h3>";
echo "<ol>";
echo "<li><strong>Kirim DM atau Comment</strong> ke Instagram account</li>";
echo "<li><strong>Refresh halaman ini</strong> untuk melihat payload yang diterima</li>";
echo "<li><strong>Lihat struktur data</strong> di 'Analyzed Structure'</li>";
echo "<li><strong>Check logs</strong> untuk melihat proses penyimpanan</li>";
echo "<li><strong>Test manual insert</strong> untuk memastikan database berfungsi</li>";
echo "</ol>";

echo "<h3>🎯 Yang Harus Dicari:</h3>";
echo "<ul>";
echo "<li>Apakah field 'from', 'to', 'message', 'text' ada di payload?</li>";
echo "<li>Apakah ada error di logs saat save ke database?</li>";
echo "<li>Apakah manual insert berhasil?</li>";
echo "</ul>";
echo "</div>";

echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<a href='debug_webhook.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Refresh</a>";
echo "</div>";

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

table {
    width: 100%;
    margin: 10px 0;
}

th, td {
    padding: 5px;
    text-align: left;
    border: 1px solid #ddd;
}

th {
    background-color: #f2f2f2;
}

button:hover {
    opacity: 0.8;
}

pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}
</style>
