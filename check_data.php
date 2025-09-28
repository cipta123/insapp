<?php
/**
 * Check Data Script
 * Monitor data yang tersimpan di database dari webhook
 */

require_once 'classes/SimpleDatabase.php';

echo "<h1>📊 Check Webhook Data</h1>";
echo "<p>Memeriksa data yang tersimpan dari webhook Instagram...</p>";
echo "<hr>";

try {
    $db = SimpleDatabase::getInstance();
    echo "<p style='color: green;'>✅ Database connected successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Auto refresh
if (isset($_GET['auto'])) {
    echo "<script>setTimeout(function(){ location.reload(); }, 10000);</script>";
    echo "<p style='color: blue;'>🔄 Auto-refresh enabled (every 10 seconds)</p>";
}

echo "<hr>";

// 1. Webhook Events Log
echo "<h2>1. Webhook Events Log (Recent 10)</h2>";

$events = $db->select('webhook_events_log', '*', '', [], 'created_at DESC', '10');

if (!empty($events)) {
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f2f2f2;'>";
    echo "<th>ID</th><th>Time</th><th>Event</th><th>Field</th><th>Object ID</th><th>Verb</th>";
    echo "</tr>";
    
    foreach ($events as $event) {
        $time = date('H:i:s', strtotime($event['created_at']));
        echo "<tr>";
        echo "<td>{$event['id']}</td>";
        echo "<td>$time</td>";
        echo "<td>{$event['event_type']}</td>";
        echo "<td><strong>{$event['field_name']}</strong></td>";
        echo "<td>{$event['object_id']}</td>";
        echo "<td>{$event['verb']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Tidak ada webhook events. Kirim DM atau comment ke Instagram untuk test.</p>";
}

echo "<hr>";

// 2. Instagram Messages
echo "<h2>2. Instagram Messages (Recent 10)</h2>";

$messages = $db->select('instagram_messages', '*', '', [], 'created_at DESC', '10');

if (!empty($messages)) {
    echo "<div style='max-height: 400px; overflow-y: auto;'>";
    foreach ($messages as $message) {
        $time = date('Y-m-d H:i:s', strtotime($message['created_at']));
        $isEcho = $message['is_echo'] ? ' (Echo)' : '';
        
        echo "<div style='background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #2196f3;'>";
        echo "<div style='display: flex; justify-content: between; align-items: center; margin-bottom: 8px;'>";
        echo "<strong>Message ID:</strong> {$message['message_id']}$isEcho";
        echo "<span style='margin-left: auto; color: #666; font-size: 12px;'>$time</span>";
        echo "</div>";
        
        echo "<div style='margin-bottom: 8px;'>";
        echo "<strong>From:</strong> {$message['sender_id']} → <strong>To:</strong> {$message['recipient_id']}";
        echo "</div>";
        
        if (!empty($message['message_text'])) {
            echo "<div style='background: white; padding: 10px; border-radius: 4px; margin: 8px 0;'>";
            echo "<strong>Text:</strong> " . htmlspecialchars($message['message_text']);
            echo "</div>";
        }
        
        echo "<div style='font-size: 12px; color: #666;'>";
        echo "Type: {$message['message_type']} | Echo: " . ($message['is_echo'] ? 'Yes' : 'No') . " | Self: " . ($message['is_self'] ? 'Yes' : 'No');
        echo "</div>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<p style='color: orange;'>⚠️ Tidak ada messages tersimpan. Kirim DM ke Instagram account untuk test.</p>";
}

echo "<hr>";

// 3. Instagram Comments  
echo "<h2>3. Instagram Comments (Recent 10)</h2>";

$comments = $db->select('instagram_comments', '*', '', [], 'created_at DESC', '10');

if (!empty($comments)) {
    echo "<div style='max-height: 400px; overflow-y: auto;'>";
    foreach ($comments as $comment) {
        $time = date('Y-m-d H:i:s', strtotime($comment['created_at']));
        
        echo "<div style='background: #f3e5f5; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #9c27b0;'>";
        echo "<div style='display: flex; justify-content: between; align-items: center; margin-bottom: 8px;'>";
        echo "<strong>@{$comment['username']}</strong>";
        echo "<span style='margin-left: auto; color: #666; font-size: 12px;'>$time</span>";
        echo "</div>";
        
        if (!empty($comment['comment_text'])) {
            echo "<div style='background: white; padding: 10px; border-radius: 4px; margin: 8px 0;'>";
            echo htmlspecialchars($comment['comment_text']);
            echo "</div>";
        }
        
        echo "<div style='font-size: 12px; color: #666;'>";
        echo "Media: {$comment['media_id']} | Comment ID: {$comment['comment_id']} | Action: {$comment['verb']}";
        echo "</div>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<p style='color: orange;'>⚠️ Tidak ada comments tersimpan. Comment di Instagram post untuk test.</p>";
}

echo "<hr>";

// 4. Statistics
echo "<h2>4. Statistics</h2>";

$stats = [
    'Total Webhook Events' => $db->select('webhook_events_log', 'COUNT(*) as count')[0]['count'] ?? 0,
    'Total Messages' => $db->select('instagram_messages', 'COUNT(*) as count')[0]['count'] ?? 0,
    'Total Comments' => $db->select('instagram_comments', 'COUNT(*) as count')[0]['count'] ?? 0,
];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
foreach ($stats as $label => $count) {
    $color = $count > 0 ? 'green' : 'orange';
    echo "<tr>";
    echo "<td><strong>$label</strong></td>";
    echo "<td style='color: $color; font-weight: bold;'>$count</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";

// 5. Recent Logs
echo "<h2>5. Recent Webhook Logs</h2>";

$logFile = 'logs/webhook.log';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    if (!empty($logs)) {
        $lines = explode("\n", $logs);
        $recentLines = array_slice($lines, -15); // Last 15 lines
        
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
        echo "<pre style='margin: 0; font-size: 12px;'>";
        foreach ($recentLines as $line) {
            if (strpos($line, '✅') !== false) {
                echo "<span style='color: green;'>$line</span>\n";
            } elseif (strpos($line, '❌') !== false) {
                echo "<span style='color: red;'>$line</span>\n";
            } elseif (strpos($line, '⚠️') !== false) {
                echo "<span style='color: orange;'>$line</span>\n";
            } else {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<p>Log file kosong.</p>";
    }
} else {
    echo "<p>Log file tidak ditemukan.</p>";
}

echo "<hr>";

// 6. Actions
echo "<h2>6. Actions</h2>";

echo "<div style='text-align: center;'>";
echo "<a href='check_data.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Refresh</a>";
echo "<a href='check_data.php?auto=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Auto Refresh</a>";
echo "<a href='webhook_monitor.php' style='background: #6f42c1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Full Monitor</a>";
echo "</div>";

echo "<hr>";

// 7. Test Instructions
echo "<h2>7. Test Instructions</h2>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<h3>🧪 Cara Test Webhook:</h3>";
echo "<ol>";
echo "<li><strong>Test Messages:</strong> Kirim DM ke Instagram account Anda</li>";
echo "<li><strong>Test Comments:</strong> Comment di salah satu post Instagram Anda</li>";
echo "<li><strong>Check Results:</strong> Refresh halaman ini untuk melihat data baru</li>";
echo "<li><strong>Monitor Logs:</strong> Lihat section 'Recent Webhook Logs' untuk debug</li>";
echo "</ol>";

echo "<h3>✅ Yang Harus Terlihat:</h3>";
echo "<ul>";
echo "<li>Webhook events di tabel pertama</li>";
echo "<li>Messages tersimpan di tabel kedua</li>";
echo "<li>Comments tersimpan di tabel ketiga</li>";
echo "<li>Log menunjukkan '✅ Message saved' atau '✅ Comment saved'</li>";
echo "</ul>";
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
    padding: 8px;
    text-align: left;
    border: 1px solid #ddd;
}

th {
    background-color: #f2f2f2;
    font-weight: bold;
}

h1, h2, h3 {
    color: #333;
}

hr {
    border: none;
    border-top: 1px solid #ddd;
    margin: 30px 0;
}

a {
    color: inherit;
    text-decoration: none;
}

a:hover {
    opacity: 0.8;
}

pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}
</style>
