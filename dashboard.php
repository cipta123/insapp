<?php
/**
 * Instagram Webhook Dashboard
 * Dashboard dengan tab Comments dan Messages
 */

require_once 'classes/SimpleDatabase.php';

// Initialize database
try {
    $db = SimpleDatabase::getInstance();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get active tab
$activeTab = $_GET['tab'] ?? 'comments';

// Pagination settings
$itemsPerPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $itemsPerPage;

// Get data based on active tab
if ($activeTab === 'comments') {
    $totalItems = $db->select('instagram_comments', 'COUNT(*) as count')[0]['count'] ?? 0;
    
    // Get all comments for tree structure
    $allComments = $db->select(
        'instagram_comments', 
        '*', 
        '', 
        [], 
        'created_at ASC'
    );
    
    // Build comment tree
    $commentTree = buildCommentTree($allComments);
    
    // Paginate the root comments only
    $rootComments = array_filter($commentTree, function($comment) {
        return empty($comment['parent_comment_id']);
    });
    
    $totalRootComments = count($rootComments);
    $totalPages = ceil($totalRootComments / $itemsPerPage);
    
    $items = array_slice($rootComments, $offset, $itemsPerPage);
    
} else {
    $totalItems = $db->select('instagram_messages', 'COUNT(*) as count')[0]['count'] ?? 0;
    $items = $db->select(
        'instagram_messages', 
        '*', 
        '', 
        [], 
        'created_at DESC', 
        "$offset, $itemsPerPage"
    );
}

$totalPages = ceil($totalItems / $itemsPerPage);

// Get statistics
$stats = [
    'total_comments' => $db->select('instagram_comments', 'COUNT(*) as count')[0]['count'] ?? 0,
    'total_messages' => $db->select('instagram_messages', 'COUNT(*) as count')[0]['count'] ?? 0,
    'total_events' => $db->select('webhook_events_log', 'COUNT(*) as count')[0]['count'] ?? 0,
];

// Get recent activity (last 24 hours)
$yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
$recentStats = [
    'recent_comments' => $db->select('instagram_comments', 'COUNT(*) as count', 'created_at >= ?', [$yesterday])[0]['count'] ?? 0,
    'recent_messages' => $db->select('instagram_messages', 'COUNT(*) as count', 'created_at >= ?', [$yesterday])[0]['count'] ?? 0,
];

/**
 * Build comment tree structure with replies
 */
function buildCommentTree($comments) {
    $tree = [];
    $lookup = [];
    
    // First pass: create lookup array and add all comments
    foreach ($comments as $comment) {
        $comment['replies'] = [];
        $lookup[$comment['comment_id']] = $comment;
        $tree[] = $comment;
    }
    
    // Second pass: organize into tree structure
    $organizedTree = [];
    foreach ($tree as $comment) {
        if (!empty($comment['parent_comment_id']) && isset($lookup[$comment['parent_comment_id']])) {
            // This is a reply, add it to parent's replies
            $parentId = $comment['parent_comment_id'];
            if (!isset($organizedTree[$parentId])) {
                $organizedTree[$parentId] = $lookup[$parentId];
                $organizedTree[$parentId]['replies'] = [];
            }
            $organizedTree[$parentId]['replies'][] = $comment;
        } else {
            // This is a root comment
            if (!isset($organizedTree[$comment['comment_id']])) {
                $organizedTree[$comment['comment_id']] = $comment;
            }
        }
    }
    
    return array_values($organizedTree);
}

/**
 * Render comment with replies
 */
function renderComment($comment, $level = 0) {
    $indent = $level > 0 ? 'reply-item' : '';
    $replyCount = count($comment['replies'] ?? []);
    
    echo "<div class='comment-thread $indent'>";
    
    // Main comment
    echo "<div class='comment-card'>";
    echo "<div class='d-flex align-items-start'>";
    echo "<div class='user-avatar me-3'>";
    echo strtoupper(substr($comment['username'] ?: 'U', 0, 1));
    echo "</div>";
    echo "<div class='flex-grow-1'>";
    
    // Header with username and reply count
    echo "<div class='d-flex justify-content-between align-items-center mb-2'>";
    echo "<div>";
    echo "<strong>@" . htmlspecialchars($comment['username'] ?: 'Unknown') . "</strong>";
    echo "<span class='badge bg-primary ms-2'>" . ucfirst($comment['verb']) . "</span>";
    if ($replyCount > 0) {
        echo "<span class='reply-count'><i class='fas fa-reply'></i> $replyCount " . ($replyCount == 1 ? 'reply' : 'replies') . "</span>";
    }
    if ($level > 0) {
        echo "<span class='badge bg-secondary ms-2'><i class='fas fa-reply'></i> Reply</span>";
    }
    echo "</div>";
    echo "<div class='timestamp'>";
    echo "<i class='fas fa-clock'></i> " . date('d/m/Y H:i', strtotime($comment['created_at']));
    echo "</div>";
    echo "</div>";
    
    // Comment text
    if (!empty($comment['comment_text'])) {
        echo "<div class='content-text'>";
        echo nl2br(htmlspecialchars($comment['comment_text']));
        echo "</div>";
    }
    
    // Comment details
    echo "<div class='row mt-2'>";
    echo "<div class='col-md-6'>";
    echo "<small class='text-muted'>";
    echo "<i class='fas fa-photo-video'></i> Media: " . htmlspecialchars($comment['media_id']);
    echo "</small>";
    echo "</div>";
    echo "<div class='col-md-6'>";
    echo "<small class='text-muted'>";
    echo "<i class='fas fa-hashtag'></i> Comment: " . htmlspecialchars($comment['comment_id']);
    echo "</small>";
    echo "</div>";
    echo "</div>";
    
    // Parent comment info
    if ($comment['parent_comment_id']) {
        echo "<div class='mt-1'>";
        echo "<small class='text-info'>";
        echo "<i class='fas fa-reply'></i> Reply to: " . htmlspecialchars($comment['parent_comment_id']);
        echo "</small>";
        echo "</div>";
    }
    
    // Reply button (only for root comments)
    if ($level === 0) {
        echo "<div class='mt-2'>";
        echo "<button class='btn btn-sm btn-outline-primary reply-btn' onclick=\"toggleReplyForm('{$comment['comment_id']}')\">";
        echo "<i class='fas fa-reply'></i> Reply";
        echo "</button>";
        
        // Reply form
        echo "<div id='reply-form-{$comment['comment_id']}' class='reply-form mt-2' style='display: none;'>";
        echo "<div class='input-group'>";
        echo "<textarea class='form-control' id='reply-text-{$comment['comment_id']}' placeholder='Write your reply...' rows='2' maxlength='2200'></textarea>";
        echo "<button class='btn btn-primary' type='button' onclick=\"postReply('{$comment['comment_id']}')\">";
        echo "<i class='fas fa-paper-plane'></i> Send";
        echo "</button>";
        echo "</div>";
        echo "<small class='text-muted'>";
        echo "<span id='char-count-{$comment['comment_id']}'>0</span>/2200 characters";
        echo "</small>";
        echo "</div>";
        
        echo "<div id='reply-status-{$comment['comment_id']}' class='mt-2'></div>";
        echo "</div>";
    }
    
    echo "</div>"; // flex-grow-1
    echo "</div>"; // d-flex
    echo "</div>"; // comment-card
    
    // Render replies
    if (!empty($comment['replies'])) {
        echo "<div class='thread-line'>";
        foreach ($comment['replies'] as $reply) {
            renderComment($reply, $level + 1);
        }
        echo "</div>";
    }
    
    echo "</div>"; // comment-thread
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instagram Webhook Dashboard - UT Serang</title>
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
            color: #667eea;
        }
        
        .tab-content {
            background: white;
            border-radius: 10px;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nav-tabs {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 10px 10px 0 0;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 2rem;
        }
        
        .nav-tabs .nav-link.active {
            background: white;
            color: #667eea;
            border-bottom: 3px solid #667eea;
        }
        
        .comment-card, .message-card {
            border-left: 4px solid #667eea;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
            transition: all 0.2s;
        }
        
        .comment-card:hover, .message-card:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .message-card {
            border-left-color: #28a745;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .timestamp {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .content-text {
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            margin: 0.5rem 0;
            border: 1px solid #e9ecef;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }
        
        .pagination .page-link {
            color: #667eea;
            border-color: #667eea;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }
        
        .badge-new {
            background: #dc3545;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .auto-refresh {
            background: #28a745;
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
        }
        
        .reply-form {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .reply-btn {
            transition: all 0.2s;
        }
        
        .reply-btn:hover {
            transform: translateY(-1px);
        }
        
        .reply-status {
            padding: 0.5rem;
            border-radius: 4px;
            margin-top: 0.5rem;
        }
        
        .reply-status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .reply-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .reply-status.loading {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .char-counter {
            font-size: 0.75rem;
        }
        
        .char-counter.warning {
            color: #ffc107;
        }
        
        .char-counter.danger {
            color: #dc3545;
        }
        
        .reply-item {
            margin-left: 40px;
            margin-top: 10px;
            padding-left: 15px;
            border-left: 3px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
        }
        
        .reply-item .comment-card {
            background: transparent;
            border-left: none;
            margin-bottom: 0.5rem;
            padding: 0.75rem;
        }
        
        .reply-indicator {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .reply-count {
            background: #e9ecef;
            color: #6c757d;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        
        .thread-line {
            border-left: 2px solid #dee2e6;
            margin-left: 20px;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fab fa-instagram"></i> Instagram Webhook Dashboard</h1>
                    <p class="mb-0">Universitas Terbuka Serang - Real-time Instagram Monitoring</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="config_manager.php" class="btn btn-outline-light me-2">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <button class="btn auto-refresh" onclick="toggleAutoRefresh()" id="autoRefreshBtn">
                        <i class="fas fa-sync-alt"></i> Auto Refresh: OFF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <i class="fas fa-comments fa-2x text-primary mb-2"></i>
                    <div class="stats-number"><?= number_format($stats['total_comments']) ?></div>
                    <div class="text-muted">Total Comments</div>
                    <?php if ($recentStats['recent_comments'] > 0): ?>
                        <small class="badge badge-new">+<?= $recentStats['recent_comments'] ?> today</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <i class="fas fa-envelope fa-2x text-success mb-2"></i>
                    <div class="stats-number"><?= number_format($stats['total_messages']) ?></div>
                    <div class="text-muted">Total Messages</div>
                    <?php if ($recentStats['recent_messages'] > 0): ?>
                        <small class="badge badge-new">+<?= $recentStats['recent_messages'] ?> today</small>
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

        <!-- Tabs -->
        <div class="card">
            <div class="card-header p-0">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'comments' ? 'active' : '' ?>" 
                           href="?tab=comments">
                            <i class="fas fa-comments"></i> Comments (<?= number_format($stats['total_comments']) ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'messages' ? 'active' : '' ?>" 
                           href="?tab=messages">
                            <i class="fas fa-envelope"></i> Messages (<?= number_format($stats['total_messages']) ?>)
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <?php if ($activeTab === 'comments'): ?>
                    <!-- Comments Tab with Tree Structure -->
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $comment): ?>
                            <?php renderComment($comment); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <h4>No Comments Yet</h4>
                            <p>Comments from your Instagram posts will appear here.</p>
                            <small class="text-muted">Try commenting on one of your Instagram posts to test the webhook.</small>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Messages Tab -->
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $message): ?>
                            <div class="message-card">
                                <div class="d-flex align-items-start">
                                    <div class="user-avatar me-3" style="background: linear-gradient(135deg, #28a745, #20c997);">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <strong>User ID: <?= htmlspecialchars($message['sender_id']) ?></strong>
                                                <?php if ($message['is_echo']): ?>
                                                    <span class="badge bg-info ms-2">Echo</span>
                                                <?php endif; ?>
                                                <?php if ($message['is_self']): ?>
                                                    <span class="badge bg-warning ms-2">Self</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="timestamp">
                                                <i class="fas fa-clock"></i> 
                                                <?= date('d/m/Y H:i', strtotime($message['created_at'])) ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($message['message_text'])): ?>
                                            <div class="content-text">
                                                <?= nl2br(htmlspecialchars($message['message_text'])) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="row mt-2">
                                            <div class="col-md-4">
                                                <small class="text-muted">
                                                    <i class="fas fa-paper-plane"></i> From: <?= htmlspecialchars($message['sender_id']) ?>
                                                </small>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">
                                                    <i class="fas fa-inbox"></i> To: <?= htmlspecialchars($message['recipient_id']) ?>
                                                </small>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">
                                                    <i class="fas fa-tag"></i> Type: <?= ucfirst($message['message_type']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-envelope"></i>
                            <h4>No Messages Yet</h4>
                            <p>Direct messages to your Instagram account will appear here.</p>
                            <small class="text-muted">Try sending a DM to your Instagram account to test the webhook.</small>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer Info -->
        <div class="text-center mt-4 mb-4">
            <small class="text-muted">
                <i class="fas fa-info-circle"></i> 
                Showing <?= count($items) ?> of <?= number_format($totalItems) ?> items | 
                Page <?= $page ?> of <?= $totalPages ?> | 
                <a href="check_data.php" class="text-decoration-none">View Raw Data</a> | 
                <a href="webhook_monitor.php" class="text-decoration-none">Full Monitor</a>
            </small>
        </div>
    </div>

    <!-- Refresh Button -->
    <button class="btn btn-primary refresh-btn" onclick="location.reload()" title="Refresh Data">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let autoRefreshInterval;
        let isAutoRefreshing = false;

        function toggleAutoRefresh() {
            const btn = document.getElementById('autoRefreshBtn');
            
            if (isAutoRefreshing) {
                clearInterval(autoRefreshInterval);
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Auto Refresh: OFF';
                btn.className = 'btn auto-refresh';
                isAutoRefreshing = false;
            } else {
                autoRefreshInterval = setInterval(() => {
                    location.reload();
                }, 30000); // Refresh every 30 seconds
                
                btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Auto Refresh: ON';
                btn.className = 'btn auto-refresh';
                btn.style.background = '#dc3545';
                isAutoRefreshing = true;
            }
        }

        // Show loading state when navigating
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                if (this.href && !this.href.includes('#')) {
                    document.body.style.opacity = '0.7';
                    document.body.style.pointerEvents = 'none';
                }
            });
        });

        // Auto-scroll to top when changing tabs
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab')) {
            window.scrollTo(0, 0);
        }

        // Update timestamps every minute
        setInterval(function() {
            const now = new Date();
            document.querySelectorAll('.stats-card .stats-number').forEach((el, index) => {
                if (index === 3) { // Time card
                    el.textContent = now.toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'});
                }
            });
        }, 60000);

        // Reply functionality
        function toggleReplyForm(commentId) {
            const form = document.getElementById(`reply-form-${commentId}`);
            const btn = document.querySelector(`[onclick="toggleReplyForm('${commentId}')"]`);
            
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-times"></i> Cancel';
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-outline-secondary');
                
                // Focus on textarea
                const textarea = document.getElementById(`reply-text-${commentId}`);
                textarea.focus();
                
                // Add character counter
                textarea.addEventListener('input', function() {
                    updateCharCounter(commentId);
                });
                
            } else {
                form.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-reply"></i> Reply';
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-outline-primary');
                
                // Clear form
                document.getElementById(`reply-text-${commentId}`).value = '';
                document.getElementById(`reply-status-${commentId}`).innerHTML = '';
            }
        }

        function updateCharCounter(commentId) {
            const textarea = document.getElementById(`reply-text-${commentId}`);
            const counter = document.getElementById(`char-count-${commentId}`);
            const length = textarea.value.length;
            
            counter.textContent = length;
            
            // Update counter color based on length
            counter.className = 'char-counter';
            if (length > 2000) {
                counter.classList.add('danger');
            } else if (length > 1800) {
                counter.classList.add('warning');
            }
        }

        function postReply(commentId) {
            const textarea = document.getElementById(`reply-text-${commentId}`);
            const statusDiv = document.getElementById(`reply-status-${commentId}`);
            const sendBtn = document.querySelector(`[onclick="postReply('${commentId}')"]`);
            const replyText = textarea.value.trim();
            
            // Validate input
            if (!replyText) {
                showReplyStatus(commentId, 'error', 'Please enter a reply message');
                return;
            }
            
            if (replyText.length > 2200) {
                showReplyStatus(commentId, 'error', 'Reply is too long (max 2200 characters)');
                return;
            }
            
            // Show loading state
            showReplyStatus(commentId, 'loading', '<i class="fas fa-spinner fa-spin"></i> Posting reply...');
            
            // Disable form
            textarea.disabled = true;
            sendBtn.disabled = true;
            
            // Send AJAX request
            fetch('reply_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    reply_text: replyText
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showReplyStatus(commentId, 'success', 
                        `<i class="fas fa-check"></i> Reply posted successfully! Reply ID: ${data.reply_id}`);
                    
                    // Clear form after success
                    setTimeout(() => {
                        toggleReplyForm(commentId);
                    }, 3000);
                    
                } else {
                    showReplyStatus(commentId, 'error', 
                        `<i class="fas fa-exclamation-triangle"></i> Error: ${data.error}`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showReplyStatus(commentId, 'error', 
                    '<i class="fas fa-exclamation-triangle"></i> Network error. Please try again.');
            })
            .finally(() => {
                // Re-enable form
                textarea.disabled = false;
                sendBtn.disabled = false;
            });
        }

        function showReplyStatus(commentId, type, message) {
            const statusDiv = document.getElementById(`reply-status-${commentId}`);
            statusDiv.innerHTML = `<div class="reply-status ${type}">${message}</div>`;
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => {
                    statusDiv.innerHTML = '';
                }, 5000);
            }
        }
    </script>
</body>
</html>
