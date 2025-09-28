<?php
/**
 * Check Instagram API Methods
 * Verify no duplicate methods exist
 */

echo "<h1>🔍 Instagram API Methods Check</h1>";
echo "<hr>";

try {
    require_once 'classes/InstagramAPI.php';
    $config = require 'config/instagram_config.php';
    $instagram = new InstagramAPI($config);
    
    echo "<p style='color: green;'>✅ InstagramAPI class loaded successfully!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading InstagramAPI: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Available Methods</h2>";

$reflection = new ReflectionClass('InstagramAPI');
$methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px;'>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #e9ecef;'>";
echo "<th>Method Name</th><th>Parameters</th><th>Description</th>";
echo "</tr>";

foreach ($methods as $method) {
    if ($method->name === '__construct') continue;
    
    $params = [];
    foreach ($method->getParameters() as $param) {
        $paramStr = '$' . $param->getName();
        if ($param->isDefaultValueAvailable()) {
            $default = $param->getDefaultValue();
            if (is_array($default)) {
                $paramStr .= ' = [...]';
            } else {
                $paramStr .= ' = ' . var_export($default, true);
            }
        }
        $params[] = $paramStr;
    }
    
    $docComment = $method->getDocComment();
    $description = '';
    if ($docComment) {
        preg_match('/\*\s*(.+?)(?:\n|\*\/)/s', $docComment, $matches);
        $description = isset($matches[1]) ? trim($matches[1]) : '';
    }
    
    echo "<tr>";
    echo "<td><strong>{$method->name}()</strong></td>";
    echo "<td>" . implode(', ', $params) . "</td>";
    echo "<td>" . htmlspecialchars($description) . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

echo "<hr>";

echo "<h2>Method Count Check</h2>";

$methodNames = array_map(function($method) {
    return $method->name;
}, $methods);

$methodCounts = array_count_values($methodNames);
$duplicates = array_filter($methodCounts, function($count) {
    return $count > 1;
});

if (empty($duplicates)) {
    echo "<p style='color: green;'>✅ No duplicate methods found!</p>";
} else {
    echo "<p style='color: red;'>❌ Duplicate methods found:</p>";
    foreach ($duplicates as $methodName => $count) {
        echo "<p style='color: red;'>- $methodName: $count times</p>";
    }
}

echo "<hr>";

echo "<h2>Test Basic Methods</h2>";

// Test getAccountInfo
try {
    echo "<h3>Testing getAccountInfo()</h3>";
    $accountInfo = $instagram->getAccountInfo();
    if ($accountInfo) {
        echo "<p style='color: green;'>✅ getAccountInfo() works</p>";
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
        echo htmlspecialchars(json_encode($accountInfo, JSON_PRETTY_PRINT));
        echo "</pre>";
    } else {
        echo "<p style='color: orange;'>⚠️ getAccountInfo() returned false</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ getAccountInfo() error: " . $e->getMessage() . "</p>";
}

echo "<hr>";

echo "<div style='text-align: center;'>";
echo "<a href='test_reply.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Test Reply Function</a>";
echo "<a href='dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-left: 10px;'>Back to Dashboard</a>";
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
    font-size: 14px;
}

th, td {
    padding: 8px;
    text-align: left;
    border: 1px solid #ddd;
}

th {
    background-color: #f2f2f2;
}

pre {
    max-height: 300px;
    overflow-y: auto;
}
</style>
