<?php
session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'manager'){
    header("location: ../index.php");
    exit;
}

require_once '../includes/db.php';

// Check token but DO NOT redirect instantly
$stmt_token = $pdo->prepare("SELECT session_token FROM managers WHERE id = ?");
$stmt_token->execute([$_SESSION['id']]);
$db_token = $stmt_token->fetchColumn();

$session_mismatch = false;
if ($db_token !== $_SESSION['session_token']) {
    $session_mismatch = true;
}

$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'canteen_name'");
$stmt->execute();
$canteen_name = $stmt->fetchColumn();

$stmt_logo = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'canteen_logo'");
$stmt_logo->execute();
$canteen_logo = $stmt_logo->fetchColumn();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($canteen_name); ?> - Manager</title>
    
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#0062E6">
    <link rel="apple-touch-icon" href="../assets/images/logos/<?php echo htmlspecialchars($canteen_logo); ?>">

    <link rel="icon" href="../assets/images/logos/<?php echo htmlspecialchars($canteen_logo); ?>" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar-overlay { display: none; position: fixed; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.5); z-index: 1039; opacity: 0; transition: all 0.3s ease-in-out; backdrop-filter: blur(2px); }
        .sidebar-overlay.active { display: block; opacity: 1; }
        .custom-navbar { background: linear-gradient(135deg, #0062E6 0%, #33AEFF 100%) !important; padding: 12px 20px; box-shadow: 0 4px 20px rgba(0, 98, 230, 0.25); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .custom-navbar .navbar-brand { font-weight: 700; color: #ffffff !important; font-size: 1.3rem; letter-spacing: -0.5px; display: flex; align-items: center; }
        .canteen-logo-img { height: 38px; width: 38px; object-fit: contain; background: #ffffff; border-radius: 12px; padding: 4px; margin-right: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        .fallback-icon { background: rgba(255, 255, 255, 0.2); color: white; padding: 8px; border-radius: 12px; margin-right: 12px; font-size: 1.1rem; backdrop-filter: blur(5px); }
        #sidebar { background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%); z-index: 1040; }
        #sidebar .sidebar-header { background: rgba(0,0,0,0.15); position: relative; }
        #sidebar .nav-item .nav-link { color: rgba(255, 255, 255, 0.85); transition: all 0.3s ease; border-radius: 12px; margin: 4px 10px; padding: 10px 15px; font-weight: 500; }
        #sidebar .nav-item .nav-link:hover, #sidebar .nav-item .nav-link.active { background: rgba(255, 255, 255, 0.2); color: #ffffff; font-weight: 600; transform: translateX(5px); }
        .btn-nav-modern { background: rgba(255, 255, 255, 0.1); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 12px; padding: 8px 16px; font-weight: 500; transition: all 0.2s ease; backdrop-filter: blur(5px); }
        .btn-nav-modern:hover { background: rgba(255, 255, 255, 0.25); color: #ffffff; text-decoration: none; transform: translateY(-2px); }
        .btn-logout-modern { background: rgba(255, 255, 255, 0.1); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 12px; padding: 8px 16px; font-weight: 500; transition: all 0.2s ease; backdrop-filter: blur(5px); }
        .btn-logout-modern:hover { background: #ff4757; border-color: #ff4757; color: white; text-decoration: none; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3); }
        #sidebarCollapse { background: rgba(255, 255, 255, 0.2); border: none; color: white; padding: 6px 12px; border-radius: 10px; transition: background 0.3s; }
        #sidebarCollapse:hover { background: rgba(255, 255, 255, 0.3); }
        .mobile-close-btn { display: none; position: absolute; top: 15px; right: 15px; background: rgba(255,255,255,0.2); border: none; color: white; width: 30px; height: 30px; border-radius: 50%; justify-content: center; align-items: center; transition: background 0.3s; }
        .mobile-close-btn:hover { background: #ff4757; }
        @media (max-width: 768px) { .mobile-close-btn { display: flex; } }

        /* Session Modal */
        .session-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.85); z-index: 1050; display: none; align-items: center; justify-content: center; backdrop-filter: blur(8px); }
        .session-modal-content { background: #ffffff; padding: 40px 30px; border-radius: 20px; text-align: center; max-width: 420px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); animation: slideDown 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes slideDown { from { transform: translateY(-50px) scale(0.95); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
        .session-modal-icon { font-size: 60px; color: #ff4757; margin-bottom: 20px; }
        .session-modal-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 15px; }
        .session-modal-text { color: #64748b; font-size: 0.95rem; margin-bottom: 25px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loading-overlay"><div class="loader"></div></div>

    <div class="session-modal-overlay" id="sessionModalOverlay">
        <div class="session-modal-content">
            <i class="fas fa-exclamation-triangle session-modal-icon"></i>
            <div class="session-modal-title">Multiple Logins Detected</div>
            <div class="session-modal-text">
                Your account was just logged in from another device. 
                Do you want to log out of this device, or keep this session active?
                <br><br><strong class="text-danger">Auto-logout in <span id="sessionCountdown">10</span>s...</strong>
            </div>
            <div class="d-flex justify-content-between">
                <button onclick="resolveSession('logout')" class="btn btn-secondary flex-fill mr-2 p-2 font-weight-bold rounded-lg shadow-sm">Logout</button>
                <button onclick="resolveSession('keep')" class="btn btn-primary flex-fill ml-2 p-2 font-weight-bold rounded-lg shadow-sm" style="background: linear-gradient(135deg, #0062E6 0%, #33AEFF 100%); border: none;">Stay Here</button>
            </div>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div id="wrapper" class="d-flex">
        <nav id="sidebar" class="shadow-lg">
            <div class="sidebar-header p-4 text-center border-bottom border-secondary text-white">
                <button class="mobile-close-btn" id="mobileSidebarClose"><i class="fas fa-times"></i></button>
                <div class="mb-2"><i class="fas fa-user-tie fa-2x text-warning"></i></div>
                <h5 class="mb-0 font-weight-bold text-uppercase tracking-wide">Manager Portal</h5>
            </div>
            <ul class="list-unstyled components mt-3">
                <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php"><i class="fas fa-home fa-fw mr-2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'pos.php') ? 'active' : ''; ?>" href="pos.php"><i class="fas fa-cash-register fa-fw mr-2"></i> POS System</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'user_management.php') ? 'active' : ''; ?>" href="user_management.php"><i class="fas fa-users fa-fw mr-2"></i> User Management</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'menu_management.php') ? 'active' : ''; ?>" href="menu_management.php"><i class="fas fa-hamburger fa-fw mr-2"></i> Menu Management</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'sales_reports.php') ? 'active' : ''; ?>" href="sales_reports.php"><i class="fas fa-chart-line fa-fw mr-2"></i> Sales Reports</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'not_served.php') ? 'active' : ''; ?>" href="not_served.php"><i class="fas fa-exclamation-circle fa-fw mr-2"></i> Pending Orders</a></li>
                 <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'member_details.php') ? 'active' : ''; ?>" href="member_details.php"><i class="fas fa-id-card fa-fw mr-2"></i> Member Details</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'recharge.php') ? 'active' : ''; ?>" href="recharge.php"><i class="fas fa-wallet fa-fw mr-2"></i> Wallet Recharge</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'my_logs.php') ? 'active' : ''; ?>" href="my_logs.php"><i class="fas fa-clipboard-list fa-fw mr-2"></i> My Logs</a></li>
            </ul>
        </nav>

        <div id="content" class="w-100 flex-grow-1">
            <nav class="navbar navbar-expand-lg custom-navbar sticky-top">
                <button type="button" id="sidebarCollapse" class="shadow-sm"><i class="fas fa-bars"></i></button>
                <a class="navbar-brand ml-3" href="dashboard.php">
                    <?php if (!empty($canteen_logo) && file_exists("../assets/images/logos/" . $canteen_logo)): ?>
                        <img src="../assets/images/logos/<?php echo htmlspecialchars($canteen_logo); ?>" alt="Logo" class="canteen-logo-img">
                    <?php else: ?><i class="fas fa-utensils fallback-icon"></i><?php endif; ?>
                    <?php echo htmlspecialchars($canteen_name); ?>
                </a>
                <div class="ml-auto d-flex align-items-center">
                    <a class="btn-nav-modern mr-2 d-none d-sm-inline-flex align-items-center" href="profile.php"><i class="fas fa-user-circle mr-1"></i> Profile</a>
                    <a class="btn-logout-modern d-flex align-items-center" href="../logout.php"><i class="fas fa-sign-out-alt mr-1"></i> <span class="d-none d-sm-inline">Logout</span></a>
                </div>
            </nav>

            <script>
                // PWA Service Worker Registration
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', function() {
                        navigator.serviceWorker.register('../sw.js').then(function(registration) {
                            console.log('ServiceWorker registration successful');
                        }, function(err) {
                            console.log('ServiceWorker registration failed: ', err);
                        });
                    });
                }

                let sessionModalActive = false;
                let sessionCountdown = 10;
                let sessionTimer = null;

                function showSessionModal() {
                    if (sessionModalActive) return;
                    sessionModalActive = true;
                    document.getElementById('sessionModalOverlay').style.display = 'flex';
                    sessionCountdown = 10;
                    document.getElementById('sessionCountdown').innerText = sessionCountdown;
                    
                    sessionTimer = setInterval(() => {
                        sessionCountdown--;
                        document.getElementById('sessionCountdown').innerText = sessionCountdown;
                        if (sessionCountdown <= 0) resolveSession('logout');
                    }, 1000);
                }

                function resolveSession(action) {
                    if(sessionTimer) clearInterval(sessionTimer);
                    fetch('../includes/resolve_session.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=' + action
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (action === 'logout') {
                            window.location.href = '../index.php?login_err=' + encodeURIComponent('Logged out due to another login.');
                        } else {
                            document.getElementById('sessionModalOverlay').style.display = 'none';
                            sessionModalActive = false;
                        }
                    }).catch(() => { if(action === 'logout') window.location.href = '../index.php'; });
                }

                document.addEventListener("DOMContentLoaded", function() {
                    <?php if (isset($session_mismatch) && $session_mismatch): ?> showSessionModal(); <?php endif; ?>

                    setInterval(function() {
                        if(sessionModalActive) return;
                        fetch('../includes/check_session.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'multiple_login') showSessionModal();
                            else if (data.status === 'logged_out') window.location.href = '../index.php';
                        }).catch(e => console.error(e));
                    }, 5000); 
                });
            </script>
            <div class="container-fluid mt-0 mb-5 px-0 px-md-3">