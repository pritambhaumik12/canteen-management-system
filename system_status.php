<?php
// canteen/system_status.php

// Temporary Error Reporting (Remove or set to 0 once the 500 error is fixed and the page loads)
ini_set('display_errors', 0); 
error_reporting(E_ALL);

session_start();

// Post-Redirect-Get (PRG) pattern for manual refreshes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_status'])) {
    header("Location: system_status.php");
    exit;
}

// Initialize timing for performance metrics
$start_time = microtime(true);

// 1. Database Connection & System Metrics
$db_status = 'Offline';
$db_ping = 0;

// Business Metrics Variables
$today_orders_count = 0;
$today_revenue = 0;
$today_recharges = 0;
$pending_orders = 0;
$total_members = 0;

try {
    // Ensure 'includes/db.php' matches the exact case of your folder/file on the server
    require_once 'includes/db.php';
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT 1");
        if ($stmt) {
            $db_status = 'Online';
            
            // --- Fetch Business Metrics (Today) ---
            $stmt_orders = $pdo->query("SELECT COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE DATE(created_at) = CURDATE()");
            if($stmt_orders) {
                $order_stats = $stmt_orders->fetch(PDO::FETCH_ASSOC);
                $today_orders_count = $order_stats['order_count'] ?? 0;
                $today_revenue = $order_stats['revenue'] ?? 0;
            }
            
            $stmt_recharges = $pdo->query("SELECT COALESCE(SUM(amount), 0) as recharges FROM transactions WHERE type = 'credit' AND DATE(created_at) = CURDATE()");
            if($stmt_recharges) {
                $today_recharges = $stmt_recharges->fetch(PDO::FETCH_ASSOC)['recharges'] ?? 0;
            }
            
            $stmt_pending = $pdo->query("SELECT COUNT(*) as pending_count FROM orders WHERE status = 'pending'");
            if($stmt_pending) {
                $pending_orders = $stmt_pending->fetch(PDO::FETCH_ASSOC)['pending_count'] ?? 0;
            }

            $stmt_members = $pdo->query("SELECT COUNT(*) as member_count FROM members WHERE status = 'active'");
            if($stmt_members) {
                $total_members = $stmt_members->fetch(PDO::FETCH_ASSOC)['member_count'] ?? 0;
            }
        }
    }
} catch (Throwable $e) { // Changed from Exception to Throwable to catch fatal TypeErrors in PHP 7/8
    $db_status = 'Offline';
    // error_log("System Status DB Error: " . $e->getMessage()); // Uncomment to log exact failures
}
$db_ping = round((microtime(true) - $start_time) * 1000, 2);

// 2. Server Metrics (Load, Memory, Disk) - SAFELY WRAPPED
$server_load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
$memory_usage = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
$memory_peak = function_exists('memory_get_peak_usage') ? memory_get_peak_usage(true) : 0;

// Disk Space Calculation - Fix for strict production servers where disk functions are disabled
$disk_percentage = 0;
$disk_total = 1; // Prevent division by zero
$disk_used = 0;

if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
    $disk_free = @disk_free_space(".");
    $disk_total_val = @disk_total_space(".");
    if ($disk_free !== false && $disk_total_val !== false && $disk_total_val > 0) {
        $disk_total = $disk_total_val;
        $disk_used = $disk_total - $disk_free;
        $disk_percentage = round(($disk_used / $disk_total) * 100, 1);
    }
}

function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $pow = floor(log($bytes) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// 3. Overall Status Logic
$system_status = 'Operational';
$status_color = 'success';
$status_icon = 'fa-check-circle';

if ($db_status === 'Offline') {
    $system_status = 'Major Outage';
    $status_color = 'danger';
    $status_icon = 'fa-times-circle';
} elseif ($server_load[0] > 5.0 || $disk_percentage > 90 || $db_ping > 500) {
    $system_status = 'Degraded Performance';
    $status_color = 'warning';
    $status_icon = 'fa-exclamation-triangle';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>System & Business Status - Canteen Portal</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background-color: #f4f6f9; }
        .status-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            height: 100%;
            transition: transform 0.2s;
            position: relative;
            overflow: hidden;
        }
        .metric-card:hover { transform: translateY(-5px); }
        .metric-card i.bg-icon {
            position: absolute;
            right: -10px;
            bottom: -10px;
            font-size: 5rem;
            opacity: 0.05;
            transform: rotate(-15deg);
        }
        
        /* Skeleton Loader Animations */
        .skeleton {
            background: #e2e5e7;
            background-image: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,0.5), rgba(255,255,255,0));
            background-size: 40px 100%;
            background-repeat: no-repeat;
            background-position: left -40px top 0;
            animation: shine 1.2s ease-infinite;
            border-radius: 8px;
        }
        .skeleton-text { height: 20px; margin-bottom: 10px; width: 80%; }
        .skeleton-heading { height: 35px; margin-bottom: 15px; width: 50%; }
        .skeleton-icon { width: 50px; height: 50px; border-radius: 50%; }
        
        @keyframes shine {
            to { background-position: right -40px top 0; }
        }
        
        .progress-bar-custom { height: 10px; border-radius: 5px; }
        .content-hidden { display: none !important; }
        .section-title { font-weight: 600; color: #495057; margin-bottom: 20px; text-transform: uppercase; font-size: 1.1rem; letter-spacing: 1px; }
    </style>
</head>
<body>

    <div id="skeleton-container">
        <div class="status-header text-center">
            <div class="container">
                <div class="skeleton skeleton-heading mx-auto"></div>
                <div class="skeleton skeleton-text mx-auto mt-3" style="width: 30%;"></div>
            </div>
        </div>
        <div class="container mb-4">
            <div class="skeleton skeleton-text mb-3" style="width: 200px;"></div>
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4"><div class="metric-card"><div class="skeleton skeleton-icon mb-3"></div><div class="skeleton skeleton-heading"></div></div></div>
                <div class="col-lg-3 col-md-6 mb-4"><div class="metric-card"><div class="skeleton skeleton-icon mb-3"></div><div class="skeleton skeleton-heading"></div></div></div>
                <div class="col-lg-3 col-md-6 mb-4"><div class="metric-card"><div class="skeleton skeleton-icon mb-3"></div><div class="skeleton skeleton-heading"></div></div></div>
                <div class="col-lg-3 col-md-6 mb-4"><div class="metric-card"><div class="skeleton skeleton-icon mb-3"></div><div class="skeleton skeleton-heading"></div></div></div>
            </div>
            
            <div class="skeleton skeleton-text mt-4 mb-3" style="width: 250px;"></div>
            <div class="row">
                <div class="col-md-4 mb-4"><div class="metric-card"><div class="skeleton skeleton-icon mb-3"></div><div class="skeleton skeleton-heading"></div><div class="skeleton skeleton-text"></div></div></div>
                <div class="col-md-4 mb-4"><div class="metric-card"><div class="skeleton skeleton-icon mb-3"></div><div class="skeleton skeleton-heading"></div><div class="skeleton skeleton-text"></div></div></div>
                <div class="col-md-4 mb-4"><div class="metric-card"><div class="skeleton skeleton-icon mb-3"></div><div class="skeleton skeleton-heading"></div><div class="skeleton skeleton-text"></div></div></div>
            </div>
        </div>
    </div>

    <div id="actual-content" class="content-hidden">
        <div class="status-header text-center">
            <div class="container">
                <h1 class="display-4 font-weight-bold">
                    <i class="fas <?php echo $status_icon; ?> text-<?php echo $status_color; ?> bg-white rounded-circle p-1"></i> 
                    <?php echo $system_status; ?>
                </h1>
                <p class="lead mb-0">Real-time status of the Canteen Infrastructure</p>
                <p class="small text-light mt-2">Last updated: <?php echo date('d M Y, h:i:s A'); ?></p>
                
                <form method="POST" class="mt-3">
                    <button type="submit" name="refresh_status" class="btn btn-light btn-sm font-weight-bold shadow-sm">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                </form>
            </div>
        </div>

        <div class="container mb-5">
            
            <h4 class="section-title"><i class="fas fa-chart-line mr-2 text-primary"></i> Today's Business Activity</h4>
            <div class="row mb-5">
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="metric-card border-left border-primary shadow-sm" style="border-left-width: 4px !important;">
                        <i class="fas fa-shopping-cart bg-icon text-primary"></i>
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Today's Orders</div>
                        <div class="h3 mb-0 font-weight-bold text-gray-800"><?php echo number_format($today_orders_count); ?></div>
                    </div>
                </div>


                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="metric-card border-left border-warning shadow-sm" style="border-left-width: 4px !important;">
                        <i class="fas fa-clock bg-icon text-warning"></i>
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Queue</div>
                        <div class="h3 mb-0 font-weight-bold text-gray-800"><?php echo number_format($pending_orders); ?></div>
                    </div>
                </div>

            </div>

            <h4 class="section-title"><i class="fas fa-server mr-2 text-secondary"></i> Infrastructure Health</h4>
            <div class="row">
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="metric-card border-bottom border-<?php echo $db_status === 'Online' ? 'success' : 'danger'; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-muted font-weight-bold mb-0">Database</h5>
                            <i class="fas fa-database fa-2x text-<?php echo $db_status === 'Online' ? 'success' : 'danger'; ?>"></i>
                        </div>
                        <h3 class="font-weight-bold"><?php echo $db_status; ?></h3>
                        <p class="text-muted small mb-0">Latency: <strong><?php echo $db_ping; ?> ms</strong> | Members: <?php echo $total_members; ?></p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="metric-card border-bottom border-info">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-muted font-weight-bold mb-0">Server Load</h5>
                            <i class="fas fa-microchip fa-2x text-info"></i>
                        </div>
                        <h3 class="font-weight-bold"><?php echo number_format($server_load[0], 2); ?></h3>
                        <p class="text-muted small mb-0">1 min avg | 5m: <?php echo number_format($server_load[1], 2); ?> | 15m: <?php echo number_format($server_load[2], 2); ?></p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="metric-card border-bottom border-warning">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-muted font-weight-bold mb-0">Storage</h5>
                            <i class="fas fa-hdd fa-2x text-warning"></i>
                        </div>
                        <h3 class="font-weight-bold"><?php echo $disk_percentage; ?>%</h3>
                        <div class="progress progress-bar-custom mt-2 mb-1">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $disk_percentage; ?>%" aria-valuenow="<?php echo $disk_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <p class="text-muted small mb-0"><?php echo formatBytes($disk_used); ?> / <?php echo formatBytes($disk_total); ?></p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="metric-card border-bottom border-primary">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-muted font-weight-bold mb-0">Memory (PHP)</h5>
                            <i class="fas fa-memory fa-2x text-primary"></i>
                        </div>
                        <h3 class="font-weight-bold"><?php echo formatBytes($memory_usage); ?></h3>
                        <p class="text-muted small mb-0">Peak Allocation: <strong><?php echo formatBytes($memory_peak); ?></strong></p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="metric-card border-bottom border-secondary">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-muted font-weight-bold mb-0">Environment</h5>
                            <i class="fas fa-code-branch fa-2x text-secondary"></i>
                        </div>
                        <h4 class="font-weight-bold text-truncate">PHP <?php echo phpversion(); ?></h4>
                        <p class="text-muted small mb-0 text-truncate"><?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="metric-card border-bottom border-dark">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-muted font-weight-bold mb-0">Web Server</h5>
                            <i class="fas fa-globe fa-2x text-dark"></i>
                        </div>
                        <h3 class="font-weight-bold text-success">Online</h3>
                        <p class="text-muted small mb-0">Protocol: <?php echo htmlspecialchars($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1'); ?></p>
                    </div>
                </div>

            </div>
            
            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-outline-primary rounded-pill px-4">
                    <i class="fas fa-arrow-left"></i> Return to Canteen Portal
                </a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        // Smooth reveal using skeleton loaders
        window.addEventListener('load', function() {
            setTimeout(function() {
                // Fade out skeleton
                document.getElementById('skeleton-container').style.display = 'none';
                
                // Show actual content
                var actualContent = document.getElementById('actual-content');
                actualContent.classList.remove('content-hidden');
                
                // Add a small fade-in effect to the content
                actualContent.style.opacity = 0;
                actualContent.style.transition = "opacity 0.4s ease-in";
                setTimeout(() => actualContent.style.opacity = 1, 50);
                
            }, 800);
        });
    </script>
</body>
</html>