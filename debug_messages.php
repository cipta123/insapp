<?php
/**
 * Debug Messages Webhook
 * Khusus untuk debug message/DM webhook dari Instagram
 */

require_once 'classes/SimpleDatabase.php';

echo "<h1>💬 Debug Messages Webhook</h1>";
echo "<p>Khusus untuk debug DM/Messages dari Instagram...</p>";
echo "<hr>";

try {
    $db = SimpleDatabase::getInstance();
    echo "<p style='color: green;'>✅ Database connected</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Get recent message events from webhook_events_log
echo "<h2>1. Recent Message Events</h2>";

$messageEvents = $db->select(
    'webhook_events_log', 
    '*', 
    "field_name = 'messages'", 
    [], 
    'created_at DESC', 
    '5'
);

if (!empty($messageEvents)) {
    foreach ($messageEvents as $index => $event) {
        $time = date('Y-m-d H:i:s', strtotime($event['created_at']));
        
        echo "<div style='background: #e3f2fd; padding: 15px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #2196f3;'>";
        echo "<h3>Message Event #" . ($index + 1) . " - {$event['verb']}</h3>";
        echo "<p><strong>Time:</strong> $time | <strong>Object ID:</strong> {$event['object_id']}</p>";
        
        echo "<h4>Raw Message Payload:</h4>";
        echo "<pre style='background: #ffffff; padding: 10px; border: 1px solid #ccc; border-radius: 4px; max-height: 400px; overflow-y: auto; font-size: 12px;'>";
        
        $payload = json_decode($event['raw_payload'], true);
        if ($payload) {
            echo htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT));
        } else {
            echo htmlspecialchars($event['raw_payload']);
        }
        echo "</pre>";
        
        // Analyze message structure
        if ($payload && isset($payload['entry'])) {
            echo "<h4>Message Structure Analysis:</h4>";
            foreach ($payload['entry'] as $entryIndex => $entry) {
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $changeIndex => $change) {
                        if ($change['field'] === 'messages') {
                            $value = $change['value'];
                            echo "<div style='background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 4px;'>";
                            echo "<strong>Message Value Structure:</strong><br>";
                            echo "<div style='font-family: monospace; font-size: 11px; margin: 5px 0;'>";
                            
                            // Show all available keys
                            echo "<strong>Available keys:</strong> " . implode(', ', array_keys($value)) . "<br>";
                            
                            // Show specific message fields
                            echo "<strong>ID:</strong> " . ($value['id'] ?? 'N/A') . "<br>";
                            echo "<strong>Object ID:</strong> " . ($value['object_id'] ?? 'N/A') . "<br>";
                            echo "<strong>Verb:</strong> " . ($value['verb'] ?? 'N/A') . "<br>";
                            
                            // Check for from/sender
                            if (isset($value['from'])) {
                                echo "<strong>From:</strong> " . json_encode($value['from']) . "<br>";
                            }
                            if (isset($value['sender'])) {
                                echo "<strong>Sender:</strong> " . json_encode($value['sender']) . "<br>";
                            }
                            
                            // Check for to/recipient
                            if (isset($value['to'])) {
                                echo "<strong>To:</strong> " . json_encode($value['to']) . "<br>";
                            }
                            if (isset($value['recipient'])) {
                                echo "<strong>Recipient:</strong> " . json_encode($value['recipient']) . "<br>";
                            }
                            
                            // Check for message content
                            if (isset($value['message'])) {
                                echo "<strong>Message Object:</strong> " . json_encode($value['message']) . "<br>";
                            }
                            if (isset($value['text'])) {
                                echo "<strong>Text:</strong> " . ($value['text']) . "<br>";
                            }
                            
                            // Check for other fields
                            echo "<strong>Is Echo:</strong> " . ($value['is_echo'] ?? 'N/A') . "<br>";
                            echo "<strong>Is Self:</strong> " . ($value['is_self'] ?? 'N/A') . "<br>";
                            echo "<strong>Type:</strong> " . ($value['type'] ?? 'N/A') . "<br>";
                            
                            echo "</div>";
                            echo "</div>";
                        }
                    }
                }
            }
        }
        echo "</div>";
    }
} else {
    echo "<p>Tidak ada message events. Kirim DM ke Instagram untuk test.</p>";
}

echo "<hr>";

// Check instagram_messages table
echo "<h2>2. Instagram Messages Table Status</h2>";

if ($db->tableExists('instagram_messages')) {
    echo "<p style='color: green;'>✅ instagram_messages table exists</p>";
    
    $messageCount = $db->select('instagram_messages', 'COUNT(*) as count')[0]['count'] ?? 0;
    echo "<p><strong>Total messages in table:</strong> $messageCount</p>";
    
    if ($messageCount > 0) {
        $recentMessages = $db->select('instagram_messages', '*', '', [], 'created_at DESC', '5');
        echo "<h3>Recent Messages in Table:</h3>";
        foreach ($recentMessages as $msg) {
            echo "<div style='background: #d4edda; padding: 10px; margin: 5px 0; border-radius: 4px;'>";
            echo "<strong>ID:</strong> {$msg['id']} | <strong>Message ID:</strong> {$msg['message_id']}<br>";
            echo "<strong>From:</strong> {$msg['sender_id']} → <strong>To:</strong> {$msg['recipient_id']}<br>";
            echo "<strong>Text:</strong> " . htmlspecialchars($msg['message_text']) . "<br>";
            echo "<strong>Time:</strong> {$msg['created_at']}<br>";
            echo "</div>";
        }
    }
} else {
    echo "<p style='color: red;'>❌ instagram_messages table does not exist</p>";
}

echo "<hr>";

// Show recent webhook logs for messages
echo "<h2>3. Recent Message Logs</h2>";

$logFile = 'logs/webhook.log';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    if (!empty($logs)) {
        $lines = explode("\n", $logs);
        
        // Filter lines related to messages
        $messageLines = array_filter($lines, function($line) {
            return strpos($line, 'message') !== false || 
                   strpos($line, 'Message') !== false ||
                   strpos($line, 'MESSAGE') !== false ||
                   strpos($line, 'saveMessageToDatabase') !== false ||
                   strpos($line, 'handleMessageEvent') !== false;
        });
        
        $recentMessageLines = array_slice($messageLines, -20); // Last 20 message-related lines
        
        if (!empty($recentMessageLines)) {
            echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto;'>";
            echo "<pre style='margin: 0; font-size: 11px;'>";
            foreach ($recentMessageLines as $line) {
                if (strpos($line, 'saveMessageToDatabase') !== false) {
                    echo "<span style='color: blue; font-weight: bold;'>$line</span>\n";
                } elseif (strpos($line, '✅') !== false) {
                    echo "<span style='color: green;'>$line</span>\n";
                } elseif (strpos($line, '❌') !== false) {
                    echo "<span style='color: red;'>$line</span>\n";
                } elseif (strpos($line, '🔄') !== false) {
                    echo "<span style='color: purple;'>$line</span>\n";
                } elseif (strpos($line, '🔍') !== false) {
                    echo "<span style='color: orange;'>$line</span>\n";
                } else {
                    echo htmlspecialchars($line) . "\n";
                }
            }
            echo "</pre>";
            echo "</div>";
        } else {
            echo "<p>Tidak ada log terkait message.</p>";
        }
    }
} else {
    echo "<p>Log file tidak ditemukan.</p>";
}

echo "<hr>";

// Test manual message insert
echo "<h2>4. Test Manual Message Insert</h2>";

if (isset($_POST['test_message_insert'])) {
    try {
        $testData = [
            'message_id' => 'debug_msg_' . time(),
            'sender_id' => 'debug_sender_123',
            'recipient_id' => 'debug_recipient_456',
            'message_text' => 'Debug test message from script',
            'message_type' => 'text',
            'is_echo' => 0,
            'is_self' => 0,
            'webhook_data' => json_encode(['debug' => true, 'timestamp' => time()])
        ];
        
        $insertId = $db->insert('instagram_messages', $testData);
        if ($insertId) {
            echo "<p style='color: green;'>✅ Test message inserted successfully with ID: $insertId</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to insert test message</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Test insert failed: " . $e->getMessage() . "</p>";
    }
}

echo "<form method='POST'>";
echo "<button type='submit' name='test_message_insert' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Test Message Insert</button>";
echo "</form>";

echo "<hr>";

// Instructions
echo "<h2>5. Debug Instructions</h2>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<h3>🔍 Untuk Debug Message:</h3>";
echo "<ol>";
echo "<li><strong>Kirim DM</strong> ke Instagram account Anda</li>";
echo "<li><strong>Refresh halaman ini</strong> untuk melihat payload message</li>";
echo "<li><strong>Lihat struktur data</strong> di 'Message Structure Analysis'</li>";
echo "<li><strong>Check logs</strong> untuk melihat proses penyimpanan</li>";
echo "<li><strong>Bandingkan</strong> dengan test manual insert</li>";
echo "</ol>";

echo "<h3>🎯 Yang Perlu Dicari:</h3>";
echo "<ul>";
echo "<li>Field apa saja yang tersedia di message payload?</li>";
echo "<li>Apakah ada field 'from', 'to', 'message', 'text'?</li>";
echo "<li>Apakah saveMessageToDatabase dipanggil?</li>";
echo "<li>Apakah ada error saat insert ke database?</li>";
echo "</ul>";
echo "</div>";

echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<a href='debug_messages.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Refresh</a>";
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

pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}

button:hover {
    opacity: 0.8;
}
</style>
