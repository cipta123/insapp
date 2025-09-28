<?php
/**
 * Instagram Configuration Manager
 * Manage Instagram API settings and account switching
 */

session_start();

// Security check - simple password protection
$adminPassword = 'utserang2024'; // Change this to a secure password
$isAuthenticated = isset($_SESSION['config_auth']) && $_SESSION['config_auth'] === true;

if (isset($_POST['login'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['config_auth'] = true;
        $isAuthenticated = true;
    } else {
        $loginError = 'Invalid password';
    }
}

if (isset($_POST['logout'])) {
    unset($_SESSION['config_auth']);
    $isAuthenticated = false;
}

// If not authenticated, show login form
if (!$isAuthenticated) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Configuration Manager - Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-card {
                background: white;
                border-radius: 15px;
                padding: 2rem;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                max-width: 400px;
                width: 100%;
            }
        </style>
    </head>
    <body>
        <div class="login-card">
            <div class="text-center mb-4">
                <i class="fas fa-cog fa-3x text-primary mb-3"></i>
                <h3>Configuration Manager</h3>
                <p class="text-muted">Enter password to access settings</p>
            </div>
            
            <?php if (isset($loginError)): ?>
                <div class="alert alert-danger"><?= $loginError ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="text-center mt-3">
                <a href="dashboard.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Load current configuration
$configFile = __DIR__ . '/config/instagram_config.php';
$currentConfig = [];

if (file_exists($configFile)) {
    $currentConfig = require $configFile;
}

// Handle form submission
$message = '';
$messageType = '';

if (isset($_POST['save_config'])) {
    try {
        $newConfig = [
            'app_id' => trim($_POST['app_id']),
            'app_secret' => trim($_POST['app_secret']),
            'access_token' => trim($_POST['access_token']),
            'instagram_user_id' => trim($_POST['instagram_user_id']),
            'webhook_verify_token' => trim($_POST['webhook_verify_token']),
            'base_url' => trim($_POST['base_url']) ?: 'https://graph.facebook.com',
            'api_version' => trim($_POST['api_version']) ?: 'v18.0',
            'webhook_url' => trim($_POST['webhook_url'])
        ];
        
        // Validate required fields
        $requiredFields = ['app_id', 'app_secret', 'access_token', 'webhook_verify_token'];
        foreach ($requiredFields as $field) {
            if (empty($newConfig[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Create config file content
        $configContent = "<?php\n";
        $configContent .= "/**\n";
        $configContent .= " * Instagram API Configuration\n";
        $configContent .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $configContent .= " */\n\n";
        $configContent .= "return [\n";
        
        foreach ($newConfig as $key => $value) {
            $configContent .= "    '$key' => '" . addslashes($value) . "',\n";
        }
        
        $configContent .= "];\n";
        
        // Backup old config
        if (file_exists($configFile)) {
            $backupFile = $configFile . '.backup.' . date('Y-m-d_H-i-s');
            copy($configFile, $backupFile);
        }
        
        // Save new config
        file_put_contents($configFile, $configContent);
        
        $message = 'Configuration saved successfully!';
        $messageType = 'success';
        
        // Update current config for display
        $currentConfig = $newConfig;
        
    } catch (Exception $e) {
        $message = 'Error saving configuration: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Test connection
if (isset($_POST['test_connection'])) {
    try {
        require_once 'classes/InstagramAPI.php';
        $testConfig = require $configFile;
        $instagram = new InstagramAPI($testConfig);
        
        $accountInfo = $instagram->getAccountInfo();
        
        if ($accountInfo && isset($accountInfo['id'])) {
            $message = 'Connection successful! Account: @' . ($accountInfo['username'] ?? 'Unknown') . ' (ID: ' . $accountInfo['id'] . ')';
            $messageType = 'success';
        } else {
            $message = 'Connection failed. Please check your access token.';
            $messageType = 'warning';
        }
        
    } catch (Exception $e) {
        $message = 'Connection test failed: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Load predefined accounts (you can add more accounts here)
$predefinedAccounts = [
    'utserang_main' => [
        'name' => 'UT Serang Main Account',
        'app_id' => '846118025023479',
        'app_secret' => '', // Fill this
        'access_token' => '', // Fill this
        'instagram_user_id' => '17841404217906448',
        'webhook_verify_token' => 'utserang_webhook_2024',
        'base_url' => 'https://graph.facebook.com',
        'api_version' => 'v18.0',
        'webhook_url' => 'https://utserang.info/insapp/webhook.php'
    ],
    'utserang_backup' => [
        'name' => 'UT Serang Backup Account',
        'app_id' => '',
        'app_secret' => '',
        'access_token' => '',
        'instagram_user_id' => '',
        'webhook_verify_token' => 'utserang_backup_2024',
        'base_url' => 'https://graph.facebook.com',
        'api_version' => 'v18.0',
        'webhook_url' => 'https://utserang.info/insapp/webhook.php'
    ]
];

// Load predefined account
if (isset($_POST['load_account'])) {
    $accountKey = $_POST['account_key'];
    if (isset($predefinedAccounts[$accountKey])) {
        $currentConfig = $predefinedAccounts[$accountKey];
        unset($currentConfig['name']); // Remove name field
        $message = 'Account template loaded: ' . $predefinedAccounts[$accountKey]['name'];
        $messageType = 'info';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instagram Configuration Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .config-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        
        .btn-group-custom {
            gap: 0.5rem;
        }
        
        .account-template {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
        }
        
        .config-status {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .config-status.complete {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .config-status.incomplete {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .field-help {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-cog"></i> Instagram Configuration Manager</h1>
                    <p class="mb-0">Manage Instagram API settings and switch between accounts</p>
                </div>
                <div class="col-md-4 text-end">
                    <form method="POST" class="d-inline">
                        <button type="submit" name="logout" class="btn btn-outline-light">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Configuration Status -->
        <?php
        $isConfigComplete = !empty($currentConfig['app_id']) && !empty($currentConfig['access_token']) && !empty($currentConfig['webhook_verify_token']);
        ?>
        <div class="config-status <?= $isConfigComplete ? 'complete' : 'incomplete' ?>">
            <div class="d-flex align-items-center">
                <i class="fas fa-<?= $isConfigComplete ? 'check-circle' : 'exclamation-triangle' ?> fa-2x me-3"></i>
                <div>
                    <h5 class="mb-1">Configuration Status: <?= $isConfigComplete ? 'Complete' : 'Incomplete' ?></h5>
                    <p class="mb-0">
                        <?= $isConfigComplete 
                            ? 'Your Instagram webhook system is properly configured and ready to use.' 
                            : 'Please complete the configuration to use the Instagram webhook system.' ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Account Templates -->
            <div class="col-md-4">
                <div class="config-card">
                    <h4><i class="fas fa-users"></i> Account Templates</h4>
                    <p class="text-muted">Quick load predefined account settings</p>
                    
                    <form method="POST">
                        <?php foreach ($predefinedAccounts as $key => $account): ?>
                            <div class="account-template">
                                <h6><?= htmlspecialchars($account['name']) ?></h6>
                                <small class="text-muted">App ID: <?= htmlspecialchars($account['app_id'] ?: 'Not set') ?></small><br>
                                <small class="text-muted">User ID: <?= htmlspecialchars($account['instagram_user_id'] ?: 'Not set') ?></small>
                                <div class="mt-2">
                                    <button type="submit" name="load_account" value="<?= $key ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i> Load Template
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </form>
                </div>
            </div>

            <!-- Configuration Form -->
            <div class="col-md-8">
                <div class="config-card">
                    <h4><i class="fas fa-edit"></i> Instagram API Configuration</h4>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">App ID *</label>
                                    <input type="text" name="app_id" class="form-control" 
                                           value="<?= htmlspecialchars($currentConfig['app_id'] ?? '') ?>" required>
                                    <div class="field-help">Your Meta App ID from Meta for Developers</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Instagram User ID</label>
                                    <input type="text" name="instagram_user_id" class="form-control" 
                                           value="<?= htmlspecialchars($currentConfig['instagram_user_id'] ?? '') ?>">
                                    <div class="field-help">Your Instagram Business Account ID</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">App Secret *</label>
                            <input type="password" name="app_secret" class="form-control" 
                                   value="<?= htmlspecialchars($currentConfig['app_secret'] ?? '') ?>" required>
                            <div class="field-help">Your Meta App Secret (keep this secure)</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Access Token *</label>
                            <textarea name="access_token" class="form-control" rows="3" required><?= htmlspecialchars($currentConfig['access_token'] ?? '') ?></textarea>
                            <div class="field-help">Your Instagram Basic Display API Access Token</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Webhook Verify Token *</label>
                            <input type="text" name="webhook_verify_token" class="form-control" 
                                   value="<?= htmlspecialchars($currentConfig['webhook_verify_token'] ?? '') ?>" required>
                            <div class="field-help">Custom token for webhook verification (choose any secure string)</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Base URL</label>
                                    <input type="text" name="base_url" class="form-control" 
                                           value="<?= htmlspecialchars($currentConfig['base_url'] ?? 'https://graph.facebook.com') ?>">
                                    <div class="field-help">Facebook Graph API base URL</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">API Version</label>
                                    <input type="text" name="api_version" class="form-control" 
                                           value="<?= htmlspecialchars($currentConfig['api_version'] ?? 'v18.0') ?>">
                                    <div class="field-help">Graph API version to use</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Webhook URL</label>
                            <input type="url" name="webhook_url" class="form-control" 
                                   value="<?= htmlspecialchars($currentConfig['webhook_url'] ?? 'https://utserang.info/insapp/webhook.php') ?>">
                            <div class="field-help">Your webhook endpoint URL (must be HTTPS)</div>
                        </div>

                        <div class="d-flex btn-group-custom">
                            <button type="submit" name="save_config" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Configuration
                            </button>
                            <button type="submit" name="test_connection" class="btn btn-success">
                                <i class="fas fa-plug"></i> Test Connection
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Current Configuration Display -->
        <?php if (!empty($currentConfig)): ?>
            <div class="config-card">
                <h4><i class="fas fa-eye"></i> Current Configuration</h4>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>App ID:</strong></td>
                                <td><?= htmlspecialchars($currentConfig['app_id'] ?? 'Not set') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Instagram User ID:</strong></td>
                                <td><?= htmlspecialchars($currentConfig['instagram_user_id'] ?? 'Not set') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Webhook Token:</strong></td>
                                <td><?= htmlspecialchars($currentConfig['webhook_verify_token'] ?? 'Not set') ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Base URL:</strong></td>
                                <td><?= htmlspecialchars($currentConfig['base_url'] ?? 'Not set') ?></td>
                            </tr>
                            <tr>
                                <td><strong>API Version:</strong></td>
                                <td><?= htmlspecialchars($currentConfig['api_version'] ?? 'Not set') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Webhook URL:</strong></td>
                                <td><?= htmlspecialchars($currentConfig['webhook_url'] ?? 'Not set') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="config-card">
            <h4><i class="fas fa-rocket"></i> Quick Actions</h4>
            <div class="d-flex flex-wrap gap-2">
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="test_reply.php" class="btn btn-outline-success">
                    <i class="fas fa-comment"></i> Test Reply
                </a>
                <a href="check_data.php" class="btn btn-outline-info">
                    <i class="fas fa-database"></i> Check Data
                </a>
                <a href="setup_webhooks.php" class="btn btn-outline-warning">
                    <i class="fas fa-cogs"></i> Setup Webhooks
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4 mb-4">
            <small class="text-muted">
                <i class="fas fa-shield-alt"></i> Configuration Manager - UT Serang Instagram Webhook System
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                }
            });
        }, 5000);

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = ['app_id', 'app_secret', 'access_token', 'webhook_verify_token'];
            let isValid = true;

            requiredFields.forEach(field => {
                const input = document.querySelector(`[name="${field}"]`);
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
    </script>
</body>
</html>
