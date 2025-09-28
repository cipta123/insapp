<?php
/**
 * Cache Management for Dashboard V2
 * Clears old cache files to prevent storage buildup
 */

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $type = $_GET['type'] ?? $_POST['type'] ?? '';
    $cacheDir = 'cache/';
    
    if (!is_dir($cacheDir)) {
        $response['message'] = 'Cache directory not found';
        echo json_encode($response);
        exit;
    }
    
    $cleared = 0;
    $maxAge = 300; // 5 minutes
    
    if ($type === 'dashboard_v2') {
        // Clear dashboard v2 cache files
        $pattern = $cacheDir . 'dashboard_v2_*.json';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            if (file_exists($file) && (time() - filemtime($file)) > $maxAge) {
                if (unlink($file)) {
                    $cleared++;
                }
            }
        }
    } else {
        // Clear all old cache files
        $files = glob($cacheDir . '*.json');
        
        foreach ($files as $file) {
            if (file_exists($file) && (time() - filemtime($file)) > $maxAge) {
                if (unlink($file)) {
                    $cleared++;
                }
            }
        }
    }
    
    $response['success'] = true;
    $response['message'] = "Cleared $cleared cache files";
    $response['cleared'] = $cleared;
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
