<?php
/**
 * Database Configuration
 * Configure your MySQL database connection here
 */

return [
    'host' => 'localhost',
    'database' => 'instagram_webhooks',
    'username' => 'root',
    'password' => '', // Update with your MySQL password if any
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
