<?php
session_start();
require_once 'includes/db.php';

// Fetch Institution/Canteen details for display
$stmt_settings = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('canteen_name', 'canteen_logo', 'institution_name')");
$stmt_settings->execute();
$settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
$canteen_name = $settings['canteen_name'] ?? 'Canteen System';
$institution_name = $settings['institution_name'] ?? 'Welcome';
$logo_file = $settings['canteen_logo'] ?? '';
$logo_path = !empty($logo_file) ? "assets/images/logos/" . $logo_file : "assets/images/logo-placeholder.png";

// If user is already logged in, redirect to their specific dashboard
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    $role = $_SESSION["role"];
    header("location: {$role}/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Portal Selection - <?php echo htmlspecialchars($canteen_name); ?></title>
    
    <link rel="manifest" href="/canteen/manifest.json" crossorigin="use-credentials">
    <meta name="theme-color" content="#1e3c72">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($logo_path); ?>">

    <link rel="icon" href="<?php echo htmlspecialchars($logo_path); ?>" type="image/png">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Base and Background with Low Opacity Image */
        body.role-selection-page {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        body.role-selection-page::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('assets/images/logos/university.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0.15;
            z-index: -1;
        }

        /* Interactive Role Cards */
        .role-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border-radius: 20px;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            overflow: hidden;
            position: relative;
        }
        .role-card::after {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%);
            transform: skewX(-25deg);
            transition: all 0.75s ease;
        }
        .role-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3) !important;
        }
        .role-card:hover::after {
            left: 200%;
        }
        .icon-wrapper {
            width: 85px; height: 85px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s ease;
        }
        .role-card:hover .icon-wrapper {
            transform: scale(1.15) rotate(5deg);
        }

        /* Animations */
        .fade-in { animation: fadeInUp 0.8s ease forwards; opacity: 0; transform: translateY(30px); }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }
        
        /* Skeleton Loader Overlay */
        .loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            z-index: 9999; 
            display: flex; 
            align-items: center;
            transition: opacity 0.6s ease, visibility 0.6s ease;
        }
        .loading-overlay.hidden { opacity: 0; visibility: hidden; }
        
        .skeleton {
            background: rgba(255, 255, 255, 0.1);
            background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0));
            background-size: 200px 100%;
            background-repeat: no-repeat;
            border-radius: 15px;
            animation: shimmer 1.5s infinite;
        }
        .skeleton-logo { width: 100px; height: 100px; border-radius: 50%; margin: 0 auto; }
        .skeleton-title { width: 60%; height: 40px; margin: 20px auto; border-radius: 8px; }
        .skeleton-subtitle { width: 40%; height: 20px; margin: 0 auto 50px auto; border-radius: 8px; }
        .skeleton-card { height: 260px; width: 100%; border-radius: 20px; }
        
        @keyframes shimmer {
            0% { background-position: -200px 0; }
            100% { background-position: calc(200px + 100%) 0; }
        }

        /* Status Footer Link */
        .status-link {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            padding: 8px 20px;
            border-radius: 30px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .status-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.2);
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        /* Custom PWA Install Button (Static) */
        #installAppBtn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border-radius: 30px;
            font-weight: 600;
            padding: 10px 20px;
            transition: all 0.3s ease;
            z-index: 1050;
        }
        #installAppBtn:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-2px);
            color: #ffffff;
        }
    </style>
</head>
<body class="role-selection-page">
    
    <button id="installAppBtn" class="btn position-fixed shadow" style="top: 20px; right: 20px;">
        <i class="fas fa-download mr-2"></i> Install App
    </button>

    <div class="loading-overlay" id="loading-overlay">
        <div class="container py-5">
            <div class="text-center mb-5">
                <div class="skeleton skeleton-logo"></div>
                <div class="skeleton skeleton-title"></div>
                <div class="skeleton skeleton-subtitle"></div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-3 col-md-6 mb-4"><div class="skeleton skeleton-card"></div></div>
                <div class="col-lg-3 col-md-6 mb-4"><div class="skeleton skeleton-card"></div></div>
                <div class="col-lg-3 col-md-6 mb-4"><div class="skeleton skeleton-card"></div></div>
                <div class="col-lg-3 col-md-6 mb-4"><div class="skeleton skeleton-card"></div></div>
            </div>
        </div>
    </div>

    <div class="container py-5" id="main-content">
        <div class="text-center text-white mb-5 header-intro fade-in">
            <?php if(file_exists($logo_path) && !empty($logo_file)): ?>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" style="width: 110px; height: 110px; object-fit: contain; border-radius: 50%; padding: 8px; background: rgba(255,255,255,0.9); box-shadow: 0 8px 25px rgba(0,0,0,0.2); margin-bottom: 20px; transition: transform 0.3s ease;">
            <?php else: ?>
                <div style="width: 110px; height: 110px; border-radius: 50%; background: rgba(255,255,255,0.9); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px; box-shadow: 0 8px 25px rgba(0,0,0,0.2);">
                    <i class="fas fa-utensils fa-3x text-primary"></i>
                </div>
            <?php endif; ?>
            <h1 class="font-weight-bold display-4 text-shadow" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo htmlspecialchars($institution_name); ?></h1>
            <p class="lead font-weight-light" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.3);"><?php echo htmlspecialchars($canteen_name); ?> Access Portal</p>
        </div>

        <div class="row justify-content-center">
            
            <div class="col-lg-3 col-md-6 mb-4 fade-in delay-1">
                <a href="member/login.php" class="card role-card text-center text-decoration-none shadow-lg border-0 h-100">
                    <div class="card-body py-5 d-flex flex-column justify-content-center">
                        <div class="icon-wrapper mb-4 mx-auto text-white shadow-sm" style="background: linear-gradient(135deg, #1cc88a, #13855c);">
                            <i class="fas fa-user-graduate fa-2x"></i>
                        </div>
                        <h4 class="card-title text-dark font-weight-bold mb-2">Member</h4>
                        <p class="text-muted small mb-0">Students & Employees</p>
                    </div>
                </a>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4 fade-in delay-2">
                <a href="staff/login.php" class="card role-card text-center text-decoration-none shadow-lg border-0 h-100">
                    <div class="card-body py-5 d-flex flex-column justify-content-center">
                        <div class="icon-wrapper mb-4 mx-auto text-white shadow-sm" style="background: linear-gradient(135deg, #36b9cc, #258391);">
                            <i class="fas fa-concierge-bell fa-2x"></i>
                        </div>
                        <h4 class="card-title text-dark font-weight-bold mb-2">Staff</h4>
                        <p class="text-muted small mb-0">Canteen Servers</p>
                    </div>
                </a>
            </div>

            <div class="col-lg-3 col-md-6 mb-4 fade-in delay-3">
                <a href="manager/login.php" class="card role-card text-center text-decoration-none shadow-lg border-0 h-100">
                    <div class="card-body py-5 d-flex flex-column justify-content-center">
                        <div class="icon-wrapper mb-4 mx-auto text-white shadow-sm" style="background: linear-gradient(135deg, #f6c23e, #dda20a);">
                            <i class="fas fa-tasks fa-2x"></i>
                        </div>
                        <h4 class="card-title text-dark font-weight-bold mb-2">Manager</h4>
                        <p class="text-muted small mb-0">Operations & Sales</p>
                    </div>
                </a>
            </div>

            <div class="col-lg-3 col-md-6 mb-4 fade-in delay-4">
                <a href="super_admin/login.php" class="card role-card text-center text-decoration-none shadow-lg border-0 h-100">
                    <div class="card-body py-5 d-flex flex-column justify-content-center">
                        <div class="icon-wrapper mb-4 mx-auto text-white shadow-sm" style="background: linear-gradient(135deg, #e74a3b, #be2617);">
                            <i class="fas fa-user-shield fa-2x"></i>
                        </div>
                        <h4 class="card-title text-dark font-weight-bold mb-2">Super Admin</h4>
                        <p class="text-muted small mb-0">System Configuration</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <div class="position-fixed fade-in delay-4" style="bottom: 20px; width: 100%; text-align: center; z-index: 10;">
        <a href="system_status.php" class="status-link small font-weight-bold">
            <i class="fas fa-server mr-1"></i> Check System Status
        </a>
    </div>

    <div class="modal fade" id="installAppModal" tabindex="-1" role="dialog" aria-labelledby="installAppModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg" style="border-radius: 20px; border: none; overflow: hidden;">
                <div class="modal-header border-0 bg-light" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                    <h5 class="modal-title font-weight-bold" id="installAppModalLabel">Install Application</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center py-4">
                    <?php if(file_exists($logo_path) && !empty($logo_file)): ?>
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="App Logo" style="width: 85px; height: 85px; object-fit: contain; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px;">
                    <?php else: ?>
                        <div style="width: 85px; height: 85px; border-radius: 20px; background: #f8f9fa; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                            <i class="fas fa-utensils fa-2x text-primary"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h4 class="font-weight-bold mb-2 text-dark"><?php echo htmlspecialchars($canteen_name); ?></h4>
                    <p class="text-muted mb-0 px-3" id="installModalText">Install this application on your device for quick access, an optimized mobile experience, and offline features.</p>
                </div>
                <div class="modal-footer border-0 d-flex justify-content-between pb-4 px-4 bg-white">
                    <button type="button" class="btn btn-light rounded-pill px-4 font-weight-bold shadow-sm" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 font-weight-bold shadow-sm" id="confirmInstallBtn" style="background: linear-gradient(135deg, #0062E6 0%, #33AEFF 100%); border: none;">
                        <i class="fas fa-download mr-1"></i> Install Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- 1. Skeleton Loader Logic ---
        window.addEventListener('load', function() {
            setTimeout(() => {
                const loadingOverlay = document.getElementById('loading-overlay');
                if (loadingOverlay) {
                    loadingOverlay.classList.add('hidden');
                    setTimeout(() => { loadingOverlay.style.display = 'none'; }, 600);
                }
            }, 300); 
        });
        
        document.querySelectorAll('.role-card, .status-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (e.ctrlKey || e.shiftKey || e.metaKey || (e.button && e.button == 1)) return;
                const overlay = document.getElementById('loading-overlay');
                overlay.style.display = 'flex';
                setTimeout(() => { overlay.classList.remove('hidden'); }, 10);
            });
        });

        // --- 2. Advanced Static PWA Install Logic ---
        let deferredPrompt = null;
        const installAppBtn = document.getElementById('installAppBtn');
        const confirmInstallBtn = document.getElementById('confirmInstallBtn');

        // Detect device characteristics
        const isIos = () => /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
        const isInStandaloneMode = () => 
            ('standalone' in window.navigator && window.navigator.standalone) || 
            window.matchMedia('(display-mode: standalone)').matches;

        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').then(registration => {
                    console.log('✅ ServiceWorker registered successfully.');
                }).catch(err => {
                    console.error('❌ ServiceWorker registration failed:', err);
                });
            });
        }

        // Capture automated prompt if available
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            console.log("🚀 Browser confirmed PWA is ready for installation.");
        });

        // Handle the static Install Button click
        installAppBtn.addEventListener('click', () => {
            if (isInStandaloneMode()) {
                // User is already inside the installed PWA
                document.getElementById('installAppModalLabel').innerText = "App Installed";
                document.getElementById('installModalText').innerHTML = "You are already using the installed version of this app! No further action is needed.";
                confirmInstallBtn.style.display = 'none';
                $('#installAppModal').modal('show');
            } 
            else if (isIos()) {
                // Apple devices require manual sharing actions
                document.getElementById('installAppModalLabel').innerText = "Install on iOS";
                document.getElementById('installModalText').innerHTML = "To install this app on your iPhone/iPad:<br><br>1. Tap the <strong>Share</strong> icon at the bottom of Safari.<br>2. Scroll down and tap <strong>Add to Home Screen</strong> <i class='far fa-plus-square'></i>.";
                confirmInstallBtn.style.display = 'none';
                $('#installAppModal').modal('show');
            } 
            else if (deferredPrompt) {
                // Windows/Android user ready for auto-install
                document.getElementById('installAppModalLabel').innerText = "Install Application";
                document.getElementById('installModalText').innerHTML = "Install this application on your device for quick access, an optimized mobile experience, and offline features.";
                confirmInstallBtn.style.display = 'inline-block';
                $('#installAppModal').modal('show');
            } 
            else {
                // Desktop or unsupported Android browser fallback
                document.getElementById('installAppModalLabel').innerText = "Install Application";
                document.getElementById('installModalText').innerHTML = "To install this app manually:<br><br>Tap your browser's menu (three dots) and select <strong>Install App</strong> or <strong>Add to Home screen</strong>.";
                confirmInstallBtn.style.display = 'none';
                $('#installAppModal').modal('show');
            }
        });

        // Handle the final install trigger
        confirmInstallBtn.addEventListener('click', async () => {
            $('#installAppModal').modal('hide');
            
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response: ${outcome}`);
                deferredPrompt = null;
                
                // Hide button if they accepted the install
                if (outcome === 'accepted') {
                    installAppBtn.style.display = 'none';
                }
            }
        });

        // Optional: Automatically hide button when the app successfully installs
        window.addEventListener('appinstalled', () => {
            installAppBtn.style.display = 'none';
            deferredPrompt = null;
            console.log('🎉 PWA installed successfully');
        });
    </script>
</body>
</html>