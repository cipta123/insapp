<?php
/**
 * Instagram Dashboard V3 - Enterprise Performance Edition
 * Features: Redis Cache + Database Optimization + AJAX + Lazy Loading
 */

require_once 'classes/SimpleDatabase.php';
require_once 'classes/RedisCache.php';

// Initialize Redis cache
$redis = new RedisCache();
$useRedis = $redis->isConnected();

// Initialize database
try {
    $db = SimpleDatabase::getInstance();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// AJAX endpoint for data loading
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? '';
    $tab = $_GET['tab'] ?? 'comments';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $itemsPerPage = 20;
    $offset = ($page - 1) * $itemsPerPage;
    
    $response = ['success' => false, 'data' => [], 'pagination' => []];
    
    try {
        if ($action === 'load_data') {
            $cacheKey = "dashboard_v3_{$tab}_p{$page}";
            
            // Try Redis cache first
            if ($useRedis) {
                $cachedData = $redis->get($cacheKey);
                if ($cachedData) {
                    $response = $cachedData;
                    $response['cached'] = true;
                    $response['cache_type'] = 'redis';
                    echo json_encode($response);
                    exit;
                }
            }
            
            // Load from database with optimized queries
            if ($tab === 'comments') {
                $totalItems = $db->select('instagram_comments', 'COUNT(*) as count')[0]['count'] ?? 0;
                $items = $db->select(
                    'instagram_comments',
                    'comment_id, username, comment_text, created_at, media_id',
                    '',
                    [],
                    'created_at DESC',
                    $itemsPerPage,
                    $offset
                );
            } else {
                $totalItems = $db->select('instagram_messages', 'COUNT(*) as count')[0]['count'] ?? 0;
                $items = $db->select(
                    'instagram_messages',
                    'message_id, sender_id, message_text, created_at, is_echo, is_self',
                    '',
                    [],
                    'created_at DESC',
                    $itemsPerPage,
                    $offset
                );
            }
            
            $totalPages = ceil($totalItems / $itemsPerPage);
            
            $response = [
                'success' => true,
                'data' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalItems,
                    'items_per_page' => $itemsPerPage
                ],
                'cached' => false,
                'cache_type' => 'none'
            ];
            
            // Cache the result
            if ($useRedis) {
                $redis->set($cacheKey, $response, 60); // 1 minute cache
            }
        }
        
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Get basic stats (cached)
$statsKey = 'dashboard_v3_stats';
$stats = null;

if ($useRedis) {
    $stats = $redis->get($statsKey);
}

if (!$stats) {
    $stats = [
        'total_comments' => $db->select('instagram_comments', 'COUNT(*) as count')[0]['count'] ?? 0,
        'total_messages' => $db->select('instagram_messages', 'COUNT(*) as count')[0]['count'] ?? 0
    ];
    $stats['total_events'] = $stats['total_comments'] + $stats['total_messages'];
    
    if ($useRedis) {
        $redis->set($statsKey, $stats, 300); // 5 minutes
    }
}

$activeTab = $_GET['tab'] ?? 'comments';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instagram Dashboard V3 - Enterprise Edition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .nav-tabs {
            border-bottom: none;
            background: #f8f9fa;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 1rem 2rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link.active {
            background: white;
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
        }

        .data-item {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1rem;
            padding: 1.5rem;
            border-radius: 0 10px 10px 0;
            transition: all 0.3s ease;
        }

        .data-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 3rem;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            gap: 1rem;
        }

        .cache-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .performance-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Cache Indicator -->
    <div class="cache-indicator">
        <span class="badge bg-info" id="cacheStatus">
            <i class="fas fa-server"></i> 
            <?= $useRedis ? 'Redis Connected' : 'File Cache' ?>
        </span>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fab fa-instagram"></i> Instagram Dashboard V3</h1>
                    <p class="mb-0">Enterprise Performance Edition - Redis + AJAX + Lazy Loading</p>
                    <div class="performance-badge mt-2">
                        <i class="fas fa-rocket"></i> High Performance Mode
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard_v2.php" class="btn btn-outline-light me-2">V2</a>
                    <a href="dashboard.php" class="btn btn-outline-light">V1</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card text-center">
                    <i class="fas fa-comments fa-3x text-primary mb-3"></i>
                    <h3><?= number_format($stats['total_comments']) ?></h3>
                    <p class="text-muted">Total Comments</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card text-center">
                    <i class="fas fa-envelope fa-3x text-success mb-3"></i>
                    <h3><?= number_format($stats['total_messages']) ?></h3>
                    <p class="text-muted">Total Messages</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card text-center">
                    <i class="fas fa-chart-line fa-3x text-info mb-3"></i>
                    <h3><?= number_format($stats['total_events']) ?></h3>
                    <p class="text-muted">Total Events</p>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content-card">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'comments' ? 'active' : '' ?>" 
                       href="#" onclick="switchTab('comments')">
                        <i class="fas fa-comments"></i> Comments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'messages' ? 'active' : '' ?>" 
                       href="#" onclick="switchTab('messages')">
                        <i class="fas fa-envelope"></i> Messages
                    </a>
                </li>
            </ul>

            <div class="card-body">
                <!-- Loading Spinner -->
                <div class="loading-spinner" id="loadingSpinner">
                    <i class="fas fa-spinner fa-spin fa-3x text-primary"></i>
                    <p class="mt-3">Loading data...</p>
                </div>

                <!-- Data Container -->
                <div id="dataContainer"></div>

                <!-- Pagination -->
                <div class="pagination-container" id="paginationContainer"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentTab = '<?= $activeTab ?>';
        let currentPage = 1;
        let isLoading = false;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadData(currentTab, 1);
        });

        // Switch tab with AJAX
        function switchTab(tab) {
            if (isLoading || tab === currentTab) return;
            
            currentTab = tab;
            currentPage = 1;
            
            // Update tab appearance
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            event.target.closest('.nav-link').classList.add('active');
            
            // Load data
            loadData(tab, 1);
            
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        }

        // Load data via AJAX
        function loadData(tab, page) {
            if (isLoading) return;
            
            isLoading = true;
            showLoading();
            
            const startTime = performance.now();
            
            fetch(`?ajax=1&action=load_data&tab=${tab}&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    const loadTime = Math.round(performance.now() - startTime);
                    
                    if (data.success) {
                        renderData(data.data, tab);
                        renderPagination(data.pagination);
                        updateCacheStatus(data.cached, data.cache_type, loadTime);
                        currentPage = page;
                    } else {
                        showError(data.error || 'Failed to load data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Network error occurred');
                })
                .finally(() => {
                    isLoading = false;
                    hideLoading();
                });
        }

        // Render data items
        function renderData(items, tab) {
            const container = document.getElementById('dataContainer');
            
            if (!items || items.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-${tab === 'comments' ? 'comments' : 'envelope'} fa-4x text-muted mb-3"></i>
                        <h4>No ${tab} found</h4>
                        <p class="text-muted">Data will appear here when available</p>
                    </div>
                `;
                return;
            }

            let html = '';
            items.forEach(item => {
                if (tab === 'comments') {
                    html += `
                        <div class="data-item">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>${escapeHtml(item.username || 'Unknown User')}</strong>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> ${formatDate(item.created_at)}
                                </small>
                            </div>
                            ${item.comment_text ? `<p>${escapeHtml(item.comment_text)}</p>` : ''}
                            <small class="text-muted">
                                <i class="fas fa-hashtag"></i> ${escapeHtml(item.comment_id)}
                            </small>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="data-item">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>User: ${escapeHtml(item.sender_id)}</strong>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> ${formatDate(item.created_at)}
                                </small>
                            </div>
                            ${item.message_text ? `<p>${escapeHtml(item.message_text)}</p>` : ''}
                            <small class="text-muted">
                                <i class="fas fa-envelope"></i> ${escapeHtml(item.message_id)}
                                ${item.is_echo ? '<span class="badge bg-info ms-2">Echo</span>' : ''}
                            </small>
                        </div>
                    `;
                }
            });
            
            container.innerHTML = html;
        }

        // Render pagination
        function renderPagination(pagination) {
            const container = document.getElementById('paginationContainer');
            
            if (pagination.total_pages <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '<nav><ul class="pagination">';
            
            // Previous button
            if (pagination.current_page > 1) {
                html += `<li class="page-item">
                    <a class="page-link" href="#" onclick="loadData('${currentTab}', ${pagination.current_page - 1})">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                </li>`;
            }

            // Page numbers
            const start = Math.max(1, pagination.current_page - 2);
            const end = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            for (let i = start; i <= end; i++) {
                html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadData('${currentTab}', ${i})">${i}</a>
                </li>`;
            }

            // Next button
            if (pagination.current_page < pagination.total_pages) {
                html += `<li class="page-item">
                    <a class="page-link" href="#" onclick="loadData('${currentTab}', ${pagination.current_page + 1})">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </li>`;
            }

            html += '</ul></nav>';
            container.innerHTML = html;
        }

        // Utility functions
        function showLoading() {
            document.getElementById('loadingSpinner').style.display = 'block';
            document.getElementById('dataContainer').style.display = 'none';
        }

        function hideLoading() {
            document.getElementById('loadingSpinner').style.display = 'none';
            document.getElementById('dataContainer').style.display = 'block';
        }

        function showError(message) {
            document.getElementById('dataContainer').innerHTML = `
                <div class="alert alert-danger text-center">
                    <i class="fas fa-exclamation-triangle"></i> ${message}
                </div>
            `;
        }

        function updateCacheStatus(cached, cacheType, loadTime) {
            const status = document.getElementById('cacheStatus');
            if (cached) {
                status.innerHTML = `<i class="fas fa-rocket"></i> ${cacheType.toUpperCase()} Cache (${loadTime}ms)`;
                status.className = 'badge bg-success';
            } else {
                status.innerHTML = `<i class="fas fa-database"></i> Database (${loadTime}ms)`;
                status.className = 'badge bg-warning';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateString) {
            return new Date(dateString).toLocaleString('id-ID');
        }

        // Handle browser back/forward
        window.addEventListener('popstate', function() {
            const params = new URLSearchParams(window.location.search);
            const tab = params.get('tab') || 'comments';
            if (tab !== currentTab) {
                currentTab = tab;
                loadData(tab, 1);
            }
        });
    </script>
</body>
</html>
