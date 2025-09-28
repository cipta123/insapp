<?php
/**
 * Instagram Webhook Dashboard V2 - Modern Sidebar Navigation
 */
require_once 'classes/SimpleDatabase.php';

try {
    $db = SimpleDatabase::getInstance();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$activeTab = $_GET['tab'] ?? 'comments';
$itemsPerPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $itemsPerPage;

// Get statistics
$stats = [
    'total_comments' => $db->select('instagram_comments', 'COUNT(*) as count')[0]['count'] ?? 0,
    'total_messages' => $db->select('instagram_messages', 'COUNT(*) as count')[0]['count'] ?? 0,
    'total_events' => ($db->select('instagram_comments', 'COUNT(*) as count')[0]['count'] ?? 0) + 
                     ($db->select('instagram_messages', 'COUNT(*) as count')[0]['count'] ?? 0)
];

// Get recent stats
$yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
$recentStats = [
    'recent_comments' => $db->select('instagram_comments', 'COUNT(*) as count', 'created_at >= ?', [$yesterday])[0]['count'] ?? 0,
    'recent_messages' => $db->select('instagram_messages', 'COUNT(*) as count', 'created_at >= ?', [$yesterday])[0]['count'] ?? 0
];

// Get data based on active tab
if ($activeTab === 'comments') {
    $totalItems = $db->select('instagram_comments', 'COUNT(*) as count')[0]['count'] ?? 0;
    $totalPages = ceil($totalItems / $itemsPerPage);
    $items = $db->select('instagram_comments', '*', '', [], 'created_at DESC', $itemsPerPage, $offset);
} else {
    $totalItems = $db->select('instagram_messages', 'COUNT(*) as count')[0]['count'] ?? 0;
    $totalPages = ceil($totalItems / $itemsPerPage);
    $items = $db->select('instagram_messages', '*', '', [], 'created_at DESC', $itemsPerPage, $offset);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instagram Dashboard V2 - UT Serang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transition: left 0.3s ease;
            z-index: 1050;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar.show {
            left: 0 !important;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-menu .menu-item {
            display: block;
            padding: 0.75rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .sidebar-menu .menu-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-menu .menu-item.active {
            background: rgba(255,255,255,0.2);
            color: white;
            border-right: 3px solid white;
        }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(0,0,0,0.1);
        }

        .submenu.show {
            max-height: 500px;
        }

        .submenu .menu-item {
            padding-left: 3rem;
            font-size: 0.9rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 0;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .main-content.shifted {
            margin-left: 280px;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
            position: sticky;
            top: 0;
            z-index: 1040;
        }

        .sidebar-toggle {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            font-size: 1.2rem;
            padding: 0.5rem 0.75rem;
            margin-right: 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .sidebar-toggle:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .comment-card, .message-card {
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
        }

        .message-card {
            border-left-color: #28a745;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .main-content.shifted {
                margin-left: 0;
            }
            .sidebar {
                width: 100%;
                left: -100%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i class="fab fa-instagram"></i> IG Dashboard</h4>
            <small>UT Serang - V2</small>
        </div>
        <div class="sidebar-menu">
            <a href="?tab=comments" class="menu-item <?= $activeTab === 'comments' ? 'active' : '' ?>">
                <i class="fas fa-comments"></i> Comments
            </a>
            <a href="?tab=messages" class="menu-item <?= $activeTab === 'messages' ? 'active' : '' ?>">
                <i class="fas fa-envelope"></i> Messages
            </a>
            
            <button class="menu-item" onclick="toggleSubmenu('functions')">
                <i class="fas fa-tools"></i> Functions <i class="fas fa-chevron-down float-end"></i>
            </button>
            <div class="submenu" id="functions-submenu">
                <a href="test_token_refresh.php" class="menu-item" target="_blank">
                    <i class="fas fa-sync-alt"></i> Token Refresh Test
                </a>
                <a href="config_manager.php" class="menu-item" target="_blank">
                    <i class="fas fa-cog"></i> Configuration Manager
                </a>
                <a href="webhook_monitor.php" class="menu-item" target="_blank">
                    <i class="fas fa-chart-line"></i> Webhook Monitor
                </a>
                <a href="webhook_diagnostics.php" class="menu-item" target="_blank">
                    <i class="fas fa-stethoscope"></i> Webhook Diagnostics
                </a>
                <a href="test_webhook.php" class="menu-item" target="_blank">
                    <i class="fas fa-vial"></i> Test Webhook
                </a>
                <a href="test_database.php" class="menu-item" target="_blank">
                    <i class="fas fa-database"></i> Database Test
                </a>
                <a href="check_data.php" class="menu-item" target="_blank">
                    <i class="fas fa-table"></i> Raw Data Viewer
                </a>
                <a href="debug_webhook.php" class="menu-item" target="_blank">
                    <i class="fas fa-bug"></i> Debug Webhook
                </a>
            </div>

            <button class="menu-item" onclick="toggleSubmenu('settings')">
                <i class="fas fa-cog"></i> Settings <i class="fas fa-chevron-down float-end"></i>
            </button>
            <div class="submenu" id="settings-submenu">
                <a href="setup_database.php" class="menu-item" target="_blank">
                    <i class="fas fa-database"></i> Setup Database
                </a>
                <a href="setup_webhooks.php" class="menu-item" target="_blank">
                    <i class="fas fa-webhook"></i> Setup Webhooks
                </a>
                <a href="fix_database.php" class="menu-item" target="_blank">
                    <i class="fas fa-wrench"></i> Fix Database
                </a>
            </div>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <button id="sidebarToggle" style="background: white; border: 2px solid #333; color: #333; font-size: 18px; padding: 8px 12px; margin-right: 15px; border-radius: 5px; cursor: pointer;" onclick="toggleSidebar()" title="Toggle Menu">
                            ☰ MENU
                        </button>
                        <span style="font-size: 24px; font-weight: bold;"><i class="fab fa-instagram"></i> Instagram Dashboard V2</span>
                        <br>
                        <small>Modern Interface with Sidebar Navigation</small>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="dashboard.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left"></i> Old Version
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <i class="fas fa-comments fa-2x text-primary mb-2"></i>
                        <div class="stats-number"><?= number_format($stats['total_comments']) ?></div>
                        <div class="text-muted">Total Comments</div>
                        <?php if ($recentStats['recent_comments'] > 0): ?>
                            <small class="badge bg-danger">+<?= $recentStats['recent_comments'] ?> today</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <i class="fas fa-envelope fa-2x text-success mb-2"></i>
                        <div class="stats-number"><?= number_format($stats['total_messages']) ?></div>
                        <div class="text-muted">Total Messages</div>
                        <?php if ($recentStats['recent_messages'] > 0): ?>
                            <small class="badge bg-danger">+<?= $recentStats['recent_messages'] ?> today</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                        <div class="stats-number"><?= number_format($stats['total_events']) ?></div>
                        <div class="text-muted">Total Events</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <div class="stats-number"><?= date('H:i') ?></div>
                        <div class="text-muted">Last Updated</div>
                        <small class="text-muted"><?= date('d/m/Y') ?></small>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="content-card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'comments' ? 'active' : '' ?>" href="?tab=comments">
                                <i class="fas fa-comments"></i> Comments (<?= number_format($stats['total_comments']) ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'messages' ? 'active' : '' ?>" href="?tab=messages">
                                <i class="fas fa-envelope"></i> Messages (<?= number_format($stats['total_messages']) ?>)
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?php if ($activeTab === 'comments'): ?>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $comment): ?>
                                <div class="comment-card">
                                    <div class="d-flex align-items-start">
                                        <div class="user-avatar me-3">
                                            <?= strtoupper(substr($comment['username'] ?: 'U', 0, 1)) ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between mb-2">
                                                <strong><?= htmlspecialchars($comment['username'] ?: 'Unknown User') ?></strong>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?>
                                                </small>
                                            </div>
                                            <?php if (!empty($comment['comment_text'])): ?>
                                                <div class="mb-2">
                                                    <?= nl2br(htmlspecialchars($comment['comment_text'])) ?>
                                                </div>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <i class="fas fa-hashtag"></i> <?= htmlspecialchars($comment['comment_id']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                                <h4>No Comments Yet</h4>
                                <p class="text-muted">Comments will appear here when received</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $message): ?>
                                <div class="message-card">
                                    <div class="d-flex align-items-start">
                                        <div class="user-avatar me-3" style="background: linear-gradient(135deg, #28a745, #20c997);">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between mb-2">
                                                <strong>User: <?= htmlspecialchars($message['sender_id']) ?></strong>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($message['created_at'])) ?>
                                                </small>
                                            </div>
                                            <?php if (!empty($message['message_text'])): ?>
                                                <div class="mb-2">
                                                    <?= nl2br(htmlspecialchars($message['message_text'])) ?>
                                                </div>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <i class="fas fa-envelope"></i> Message ID: <?= htmlspecialchars($message['message_id']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-envelope fa-4x text-muted mb-3"></i>
                                <h4>No Messages Yet</h4>
                                <p class="text-muted">Direct messages will appear here when received</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            console.log('Toggle sidebar clicked!');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mainContent = document.getElementById('mainContent');
            
            console.log('Sidebar element:', sidebar);
            console.log('Overlay element:', overlay);
            console.log('Main content element:', mainContent);
            
            if (sidebar && overlay && mainContent) {
                const isShowing = sidebar.classList.contains('show');
                console.log('Current state - showing:', isShowing);
                
                if (isShowing) {
                    // Hide sidebar
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                    mainContent.classList.remove('shifted');
                    console.log('Sidebar hidden');
                } else {
                    // Show sidebar
                    sidebar.classList.add('show');
                    overlay.classList.add('show');
                    if (window.innerWidth > 768) {
                        mainContent.classList.add('shifted');
                    }
                    console.log('Sidebar shown');
                }
            } else {
                console.error('Elements not found!');
                console.error('Missing elements:', {
                    sidebar: !sidebar,
                    overlay: !overlay,
                    mainContent: !mainContent
                });
            }
        }

        // Test function on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded, testing elements...');
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');
            console.log('Sidebar found:', sidebar ? 'YES' : 'NO');
            console.log('Toggle button found:', toggle ? 'YES' : 'NO');
        });

        function toggleSubmenu(id) {
            const submenu = document.getElementById(id + '-submenu');
            const button = event.target;
            const icon = button.querySelector('.fa-chevron-down, .fa-chevron-up');
            
            submenu.classList.toggle('show');
            
            if (submenu.classList.contains('show')) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !toggle.contains(event.target) && 
                sidebar.classList.contains('show')) {
                toggleSidebar();
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mainContent = document.getElementById('mainContent');
            
            if (window.innerWidth > 768) {
                if (sidebar.classList.contains('show')) {
                    mainContent.classList.add('shifted');
                }
                overlay.classList.remove('show');
            } else {
                mainContent.classList.remove('shifted');
            }
        });
    </script>
</body>
</html>
