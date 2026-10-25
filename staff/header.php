<?php
session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'staff'){
    header("location: ../index.php");
    exit;
}

if(!isset($_SESSION["id"])){
    header("location: ../index.php");
    exit;
}

require_once '../includes/db.php';

// Check token but DO NOT redirect instantly
$stmt_token = $pdo->prepare("SELECT session_token FROM canteen_staff WHERE id = ?");
$stmt_token->execute([$_SESSION['id']]);
$db_token = $stmt_token->fetchColumn();

$session_mismatch = false;
if ($db_token !== $_SESSION['session_token']) {
    $session_mismatch = true;
}

$stmt_name = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'canteen_name'");
$stmt_name->execute();
$canteen_name = $stmt_name->fetchColumn();

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
    <title><?php echo htmlspecialchars($canteen_name); ?> - Staff</title>

    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#0062E6">
    <link rel="apple-touch-icon" href="../assets/images/logos/<?php echo htmlspecialchars($canteen_logo); ?>">

    <link rel="icon" href="../assets/images/logos/<?php echo htmlspecialchars($canteen_logo); ?>" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; }
        .custom-navbar { background: linear-gradient(135deg, #0062E6 0%, #33AEFF 100%) !important; padding: 12px 20px; box-shadow: 0 4px 20px rgba(0, 98, 230, 0.25); transition: all 0.3s ease; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .custom-navbar .navbar-brand { font-weight: 700; color: #ffffff !important; font-size: 1.3rem; letter-spacing: -0.5px; display: flex; align-items: center; }
        .canteen-logo-img { height: 38px; width: 38px; object-fit: contain; background: #ffffff; border-radius: 12px; padding: 4px; margin-right: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        .custom-navbar .navbar-brand i.fallback-icon { background: rgba(255, 255, 255, 0.2); color: white; padding: 8px; border-radius: 12px; margin-right: 12px; font-size: 1.1rem; backdrop-filter: blur(5px); }
        .custom-navbar .nav-link { font-weight: 500; color: rgba(255, 255, 255, 0.8) !important; padding: 8px 16px !important; border-radius: 12px; transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1); margin: 0 4px; display: flex; align-items: center; }
        .custom-navbar .nav-link i { margin-right: 8px; font-size: 1.1rem; transition: color 0.2s ease; }
        .custom-navbar .nav-link:hover { background: rgba(255, 255, 255, 0.15); color: #ffffff !important; }
        .custom-navbar .nav-link.active { background: rgba(255, 255, 255, 0.25); color: #ffffff !important; font-weight: 600; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .role-badge { background: rgba(255, 255, 255, 0.2); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 12px; padding: 8px 16px; font-weight: 600; display: inline-flex; align-items: center; font-size: 0.95rem; backdrop-filter: blur(5px); }
        .btn-logout-modern { background: rgba(255, 255, 255, 0.1); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 12px; padding: 8px 16px; font-weight: 500; transition: all 0.2s ease; backdrop-filter: blur(5px); }
        .btn-logout-modern:hover { background: #ff4757; border-color: #ff4757; color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3); }
        .navbar-toggler { border: none; padding: 6px 10px; border-radius: 10px; background: rgba(255, 255, 255, 0.2); }
        .navbar-toggler:focus { outline: none; box-shadow: 0 0 0 3px rgba(255,255,255,0.4); }
        .navbar-toggler-icon { background-image: url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba(255, 255, 255, 1)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E"); }
        @media (max-width: 991px) { .navbar-collapse { margin-top: 15px; padding: 15px; background: rgba(0, 75, 196, 0.95); border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 25px rgba(0,0,0,0.15); backdrop-filter: blur(10px); } .custom-navbar .nav-link { margin: 4px 0; padding: 10px 15px !important; } .action-buttons { display: flex; flex-direction: column; gap: 10px; margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px; } .role-badge, .btn-logout-modern { width: 100%; justify-content: center; } }

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

    <nav class="navbar navbar-expand-lg custom-navbar sticky-top">
        <div class="container-fluid px-2 px-md-4">
            <a class="navbar-brand" href="dashboard.php">
                <?php if (!empty($canteen_logo) && file_exists("../assets/images/logos/" . $canteen_logo)): ?>
                    <img src="../assets/images/logos/<?php echo htmlspecialchars($canteen_logo); ?>" alt="Logo" class="canteen-logo-img">
                <?php else: ?><i class="fas fa-utensils fallback-icon"></i><?php endif; ?>
                <?php echo htmlspecialchars($canteen_name); ?>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto ml-lg-4">
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php"><i class="fas fa-clipboard-check"></i> Process Order</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'history.php') ? 'active' : ''; ?>" href="history.php"><i class="fas fa-history"></i> Serving History</a></li>
                    <li class="nav-item"><a class="nav-link text-dark font-weight-bold px-3 <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                </ul>
                <div class="d-flex align-items-lg-center action-buttons">
                    <div class="mr-lg-3"><span class="role-badge" title="Role"><i class="fas fa-user-tag"></i> Staff</span></div>
                    <div><a class="btn-logout-modern text-decoration-none d-flex align-items-center" href="../logout.php"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a></div>
                </div>
            </div>
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
    <div class="container mt-4 mb-5">