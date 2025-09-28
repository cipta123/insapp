<?php
echo "✅ Server is working!";
echo "<br>Time: " . date("Y-m-d H:i:s");
echo "<br>Server: " . $_SERVER["HTTP_HOST"];
echo "<br>IP: " . $_SERVER["SERVER_ADDR"] ?? "Unknown";
?>