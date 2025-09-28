<?php
/**
 * Cron Job Script for Token Refresh
 * This script should be run daily via cron job
 */

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Include the token refresher
require_once __DIR__ . '/refresh_token.php';

// Create refresher instance
$refresher = new TokenRefresher();

// Run the daily check
$refresher->checkAndRefresh();

// Optional: Send email notification if token refresh fails
// You can uncomment and configure this if you want email alerts
/*
function sendAlert($message) {
    $to = 'ciptaanugrahh@gmail.com';
    $subject = 'Instagram Token Refresh Alert';
    $headers = 'From: noreply@utserang.info';
    
    mail($to, $subject, $message, $headers);
}
*/
?>
