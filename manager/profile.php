<?php
// Start output buffering to prevent header.php from sending HTML before our PRG redirect
ob_start(); 
require_once 'header.php'; 
require_once '../includes/mailer.php';

$manager_id_session = $_SESSION['id'];
$baseUrl = "http://" . $_SERVER['HTTP_HOST'] . "/canteen";

// Fetch manager details
$stmt = $pdo->prepare("SELECT manager_id, name, email, t_pin FROM managers WHERE id = ?");
$stmt->execute([$manager_id_session]);
$manager = $stmt->fetch(PDO::FETCH_ASSOC);
$has_tpin = !empty($manager['t_pin']);

// ==========================================
// PRG PATTERN: Handle Form Submissions (POST)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- 1. HANDLE PASSWORD CHANGE ---
    if (isset($_POST['update_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (strlen($new_password) < 6) {
            $_SESSION['profile_error'] = "Password must be at least 6 characters.";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['profile_error'] = "Passwords do not match.";
        } else {
            $updateStmt = $pdo->prepare("UPDATE managers SET password = ? WHERE id = ?");
            if ($updateStmt->execute([$new_password, $manager_id_session])) {
                $_SESSION['profile_success'] = "Your password has been securely updated.";
            } else {
                $_SESSION['profile_error'] = "Failed to update password due to a system error.";
            }
        }
    }

    // --- 2. HANDLE T-PIN SETUP/RESET ---
    if (isset($_POST['update_tpin'])) {
        $new_tpin = $_POST['new_tpin'];
        $confirm_tpin = $_POST['confirm_tpin'];

        if (!ctype_digit($new_tpin) || strlen($new_tpin) < 4 || strlen($new_tpin) > 6) {
            $_SESSION['profile_error'] = "T-PIN must be exactly 4 to 6 digits long and contain only numbers.";
        } elseif ($new_tpin !== $confirm_tpin) {
            $_SESSION['profile_error'] = "T-PINs do not match. Please try again.";
        } else {
            $updateStmt = $pdo->prepare("UPDATE managers SET t_pin = ? WHERE id = ?");
            if ($updateStmt->execute([$new_tpin, $manager_id_session])) {
                $_SESSION['profile_success'] = $has_tpin ? "T-PIN successfully reset." : "T-PIN successfully created.";

                // Send Interactive Email Notification
                if (!empty($manager['email'])) {
                    $settings = [];
                    $setStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
                    while ($row = $setStmt->fetch(PDO::FETCH_ASSOC)) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                    
                    $inst_name = $settings['institution_name'] ?? 'Institution';
                    $canteen_name = $settings['canteen_name'] ?? 'Canteen';
                    $inst_logo_url = $baseUrl . "/assets/images/logos/" . ($settings['institution_logo'] ?? 'default_inst.png');
                    $action_text = $has_tpin ? "Reset" : "Created";
                    $subject = "Security Alert: T-PIN {$action_text} - {$canteen_name}";

                    $htmlBody = "
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 50px 20px; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif;'>
                        <div style='max-width: 550px; margin: 0 auto; background: rgba(255, 255, 255, 0.95); border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;'>
                            <div style='text-align: center; padding: 30px 20px; border-bottom: 2px dashed #eee;'>
                                <img src='{$inst_logo_url}' alt='Logo' style='max-height: 60px; margin-bottom: 15px;'>
                                <h2 style='color: #333; margin: 0; font-size: 24px; font-weight: 800;'>{$inst_name}</h2>
                                <p style='color: #764ba2; font-weight: 600; margin: 5px 0 0 0;'>{$canteen_name}</p>
                            </div>
                            <div style='padding: 40px 30px; text-align: center;'>
                                <div style='background: linear-gradient(45deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%); width: 80px; height: 80px; line-height: 80px; border-radius: 50%; margin: 0 auto 20px auto; color: #d63384; font-size: 35px;'>🔒</div>
                                <h3 style='color: #2d3748; font-size: 22px; margin-bottom: 15px;'>Your T-PIN was {$action_text}</h3>
                                <p style='color: #4a5568; font-size: 16px; line-height: 1.6;'>Hello <strong>{$manager['name']}</strong>,<br><br>This is a confirmation that your Transaction PIN (T-PIN) has been successfully {$action_text} on your account (ID: <strong>{$manager['manager_id']}</strong>).</p>
                                <div style='margin-top: 30px; padding: 15px; background: rgba(118, 75, 162, 0.1); border-left: 4px solid #764ba2; text-align: left;'>
                                    <p style='margin: 0; font-size: 14px; color: #555;'><strong>Note:</strong> If you did not authorize this change, please contact the Super Admin immediately to secure your account.</p>
                                </div>
                            </div>
                            <div style='background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #a0aec0;'>&copy; " . date('Y') . " {$canteen_name}. All rights reserved.</div>
                        </div>
                    </div>";
                    sendMail($manager['email'], $manager['name'], $subject, $htmlBody);
                }
            } else {
                $_SESSION['profile_error'] = "Failed to update T-PIN.";
            }
        }
    }

    // PRG Redirect: Clear the buffer, redirect to GET, and exit
    ob_end_clean();
    header("Location: profile.php");
    exit;
}

// ==========================================
// PRG PATTERN: Retrieve Flash Messages (GET)
// ==========================================
$success_msg = $_SESSION['profile_success'] ?? '';
$error_msg = $_SESSION['profile_error'] ?? '';
unset($_SESSION['profile_success'], $_SESSION['profile_error']);

// Flush the buffered HTML from header.php so the page begins rendering
ob_end_flush(); 
?>

<style>
    /* CSS Variables & Theme Integration */
    :root {
        --primary-theme-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --secondary-theme-grad: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        --glass-bg: rgba(255, 255, 255, 0.9);
        --glass-border: rgba(255, 255, 255, 0.5);
        --skeleton-base: #e2e8f0;
        --skeleton-highlight: #f8fafc;
        --text-dark: #1e293b;
        --text-muted: #64748b;
    }

    /* Core Animations */
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }
    @keyframes fadeInUpProfile {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulse-glow {
        0% { box-shadow: 0 0 0 0 rgba(118, 75, 162, 0.4); }
        70% { box-shadow: 0 0 0 15px rgba(118, 75, 162, 0); }
        100% { box-shadow: 0 0 0 0 rgba(118, 75, 162, 0); }
    }

    /* Skeleton Loader Styles */
    .skeleton-box {
        background: var(--skeleton-base);
        background-image: linear-gradient(90deg, var(--skeleton-base) 0px, var(--skeleton-highlight) 50%, var(--skeleton-base) 100%);
        background-size: 1000px 100%;
        animation: shimmer 2s infinite linear;
        border-radius: 12px;
    }
    .sk-profile-top { height: 160px; border-radius: 20px 20px 0 0; }
    .sk-avatar { width: 130px; height: 130px; border-radius: 50%; margin: -65px auto 20px auto; border: 5px solid #fff; }
    .sk-title { height: 28px; width: 60%; margin: 0 auto 10px auto; }
    .sk-subtitle { height: 18px; width: 40%; margin: 0 auto 25px auto; }
    .sk-list-item { height: 45px; width: 90%; margin: 10px auto; border-radius: 10px; }
    .sk-tab { height: 45px; width: 140px; display: inline-block; margin-right: 15px; border-radius: 30px; }
    .sk-input { height: 60px; width: 100%; margin-top: 15px; border-radius: 12px; }
    .sk-btn { height: 50px; width: 180px; border-radius: 30px; margin-top: 25px; }

    /* Layout & Cards */
    .profile-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    .modern-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
        backdrop-filter: blur(10px);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
    }
    .modern-card:hover {
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.08);
        transform: translateY(-3px);
    }
    
    /* Content Animation Wrapper */
    #profile-content {
        animation: fadeInUpProfile 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* Profile Sidebar Specifics */
    .profile-cover {
        background: var(--primary-theme-grad);
        height: 160px;
        position: relative;
    }
    .profile-cover::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIj48Zz48Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjIwMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvZz48L3N2Zz4=') no-repeat center center;
        background-size: cover;
        opacity: 0.6;
    }
    .profile-avatar-wrapper {
        position: relative;
        width: 130px;
        height: 130px;
        margin: -65px auto 20px;
        border-radius: 50%;
        background: white;
        padding: 5px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        z-index: 2;
    }
    .profile-avatar {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 55px;
        color: #764ba2;
        transition: all 0.4s ease;
    }
    .profile-avatar-wrapper:hover .profile-avatar {
        transform: scale(1.05);
        color: #667eea;
    }
    .profile-avatar-wrapper::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        border-radius: 50%;
        animation: pulse-glow 2s infinite;
        z-index: -1;
    }
    .info-pill {
        display: flex;
        align-items: center;
        padding: 12px 18px;
        background: #f8fafc;
        border-radius: 12px;
        margin-bottom: 12px;
        transition: all 0.3s ease;
        border: 1px solid #f1f5f9;
    }
    .info-pill:hover {
        background: white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        transform: translateX(5px);
    }
    .info-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        margin-right: 15px;
        font-size: 1.1rem;
    }
    .icon-email { background: rgba(102, 126, 234, 0.1); color: #667eea; }
    .icon-security { background: rgba(16, 185, 129, 0.1); color: #10b981; }

    /* Tabs Design */
    .custom-nav-pills {
        gap: 10px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f1f5f9;
    }
    .custom-nav-pills .nav-link {
        border-radius: 30px;
        padding: 12px 25px;
        color: var(--text-muted);
        font-weight: 600;
        background: transparent;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid transparent;
    }
    .custom-nav-pills .nav-link:hover {
        background: #f8fafc;
        color: #764ba2;
    }
    .custom-nav-pills .nav-link.active {
        background: var(--primary-theme-grad);
        color: white;
        box-shadow: 0 8px 20px rgba(118, 75, 162, 0.25);
        transform: translateY(-2px);
    }

    /* Form & Input Enhancements */
    .form-floating-custom {
        position: relative;
        margin-bottom: 1.8rem;
    }
    .form-control-custom {
        border-radius: 12px;
        padding: 24px 16px 10px;
        height: auto;
        border: 2px solid #e2e8f0;
        background-color: #f8fafc;
        color: var(--text-dark);
        font-weight: 500;
        transition: all 0.3s ease;
        font-size: 1rem;
    }
    .form-control-custom:focus {
        border-color: #764ba2;
        background-color: #fff;
        box-shadow: 0 0 0 4px rgba(118, 75, 162, 0.1);
    }
    .form-floating-custom label {
        position: absolute;
        top: 0; left: 0; height: 100%; padding: 16px;
        pointer-events: none;
        transform-origin: 0 0;
        transition: opacity .15s ease-in-out, transform .15s ease-in-out;
        color: #94a3b8;
        font-weight: 600;
        font-size: 0.9rem;
    }
    .form-control-custom:focus ~ label,
    .form-control-custom:not(:placeholder-shown) ~ label {
        transform: scale(0.8) translateY(-0.6rem) translateX(0.15rem);
        color: #764ba2;
    }
    
    .input-group-custom {
        position: relative;
        display: flex;
        align-items: stretch;
        width: 100%;
    }
    .input-group-custom .form-control-custom {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        border-right: none;
    }
    .input-group-text-custom {
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-left: none;
        border-radius: 0 12px 12px 0;
        cursor: pointer;
        color: #94a3b8;
        padding: 0 20px;
        display: flex;
        align-items: center;
        transition: all 0.3s;
    }
    .form-control-custom:focus + .input-group-text-custom,
    .input-group-text-custom:hover {
        border-color: #764ba2;
        color: #764ba2;
        background-color: #fff;
    }

    /* T-PIN specific styling */
    .tpin-input {
        letter-spacing: 8px;
        font-size: 1.4rem !important;
        font-family: monospace;
        font-weight: bold;
    }

    /* Button Animations */
    .btn-gradient {
        background: var(--primary-theme-grad);
        color: white;
        border: none;
        border-radius: 30px;
        padding: 12px 35px;
        font-weight: 600;
        letter-spacing: 0.5px;
        position: relative;
        overflow: hidden;
        z-index: 1;
        transition: all 0.3s ease;
    }
    .btn-gradient::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        background: var(--secondary-theme-grad);
        z-index: -1;
        transition: opacity 0.3s ease;
        opacity: 0;
    }
    .btn-gradient:hover::before { opacity: 1; }
    .btn-gradient:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(118, 75, 162, 0.3);
        color: white;
    }
    
    /* Alerts Styling */
    .floating-alert {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1050;
        min-width: 320px;
        max-width: 400px;
        animation: fadeInUpProfile 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .custom-alert {
        border: none;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        padding: 1rem 1.25rem;
    }
</style>

<div id="alert-container" class="floating-alert">
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show custom-alert" role="alert" style="border-left: 5px solid #10b981;">
            <div class="d-flex align-items-center">
                <div class="mr-3 text-success" style="font-size: 1.5rem;"><i class="fas fa-check-circle"></i></div>
                <div>
                    <h6 class="mb-0 font-weight-bold text-dark">Success</h6>
                    <small class="text-muted"><?php echo $success_msg; ?></small>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="padding: 1rem;"><span aria-hidden="true">&times;</span></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show custom-alert" role="alert" style="border-left: 5px solid #ef4444;">
            <div class="d-flex align-items-center">
                <div class="mr-3 text-danger" style="font-size: 1.5rem;"><i class="fas fa-exclamation-circle"></i></div>
                <div>
                    <h6 class="mb-0 font-weight-bold text-dark">Error</h6>
                    <small class="text-muted"><?php echo $error_msg; ?></small>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="padding: 1rem;"><span aria-hidden="true">&times;</span></button>
        </div>
    <?php endif; ?>
</div>

<div class="profile-container mt-2">
    <div id="profile-skeleton" class="row">
        <div class="col-12 col-lg-4 mb-4">
            <div class="modern-card">
                <div class="sk-profile-top skeleton-box"></div>
                <div class="p-4 text-center">
                    <div class="sk-avatar skeleton-box"></div>
                    <div class="sk-title skeleton-box"></div>
                    <div class="sk-subtitle skeleton-box"></div>
                    <div class="mt-4 text-left">
                        <div class="sk-list-item skeleton-box"></div>
                        <div class="sk-list-item skeleton-box"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8 mb-4">
            <div class="modern-card p-4">
                <div class="pb-3 border-bottom">
                    <div class="sk-tab skeleton-box"></div>
                    <div class="sk-tab skeleton-box"></div>
                </div>
                <div class="pt-4">
                    <div class="row">
                        <div class="col-md-6"><div class="sk-input skeleton-box"></div></div>
                        <div class="col-md-6"><div class="sk-input skeleton-box"></div></div>
                    </div>
                    <div class="sk-btn skeleton-box"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="profile-content" class="row" style="display: none;">
        <div class="col-12 col-lg-4 mb-4">
            <div class="modern-card h-100">
                <div class="profile-cover"></div>
                <div class="px-4 pb-4 text-center">
                    <div class="profile-avatar-wrapper">
                        <div class="profile-avatar">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                    <h4 class="font-weight-bold text-dark mb-1"><?php echo htmlspecialchars($manager['name']); ?></h4>
                    <span class="badge badge-pill badge-light text-muted border px-3 py-2 shadow-sm mb-4">
                        Manager ID: <strong class="text-primary"><?php echo htmlspecialchars($manager['manager_id']); ?></strong>
                    </span>
                    
                    <div class="text-left mt-3">
                        <h6 class="text-uppercase text-muted font-weight-bold mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Account Information</h6>
                        
                        <div class="info-pill">
                            <div class="info-icon icon-email"><i class="fas fa-envelope"></i></div>
                            <div>
                                <small class="text-muted d-block font-weight-bold">Email Address</small>
                                <span class="text-dark font-weight-bold" style="font-size: 0.95rem; word-break: break-all;">
                                    <?php echo htmlspecialchars($manager['email'] ?? 'Not provided'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-pill">
                            <div class="info-icon icon-security"><i class="fas fa-shield-alt"></i></div>
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block font-weight-bold">T-PIN Status</small>
                                    <span class="text-dark font-weight-bold" style="font-size: 0.95rem;">Security PIN</span>
                                </div>
                                <?php if ($has_tpin): ?>
                                    <span class="badge badge-success px-3 py-2 rounded-pill shadow-sm"><i class="fas fa-check mr-1"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge badge-warning px-3 py-2 rounded-pill shadow-sm text-white"><i class="fas fa-exclamation mr-1"></i> Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8 mb-4">
            <div class="modern-card h-100 p-4 p-md-5">
                
                <ul class="nav nav-pills custom-nav-pills mb-4" id="settingsTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="security-tab" data-toggle="pill" href="#security" role="tab">
                            <i class="fas fa-lock mr-2"></i> Password Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tpin-tab" data-toggle="pill" href="#tpin" role="tab">
                            <i class="fas fa-fingerprint mr-2"></i> Transaction PIN
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content pt-2" id="settingsTabsContent">
                    
                    <div class="tab-pane fade show active" id="security" role="tabpanel">
                        <div class="mb-4">
                            <h5 class="font-weight-bold text-dark">Update Password</h5>
                            <p class="text-muted small">Ensure your account is using a long, random password to stay secure.</p>
                        </div>
                        
                        <form action="profile.php" method="post" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating-custom">
                                        <div class="input-group-custom">
                                            <input type="password" name="new_password" id="new_password" class="form-control form-control-custom" placeholder=" " required minlength="6">
                                            <label for="new_password">New Password</label>
                                            <span class="input-group-text-custom toggle-password" data-target="new_password"><i class="fas fa-eye"></i></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating-custom">
                                        <div class="input-group-custom">
                                            <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-custom" placeholder=" " required minlength="6">
                                            <label for="confirm_password">Confirm Password</label>
                                            <span class="input-group-text-custom toggle-password" data-target="confirm_password"><i class="fas fa-eye"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right mt-2">
                                <button type="submit" name="update_password" class="btn btn-gradient shadow-sm">
                                    <i class="fas fa-save mr-2"></i> Save Password
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="tpin" role="tabpanel">
                        <div class="mb-4">
                            <h5 class="font-weight-bold text-dark"><?php echo $has_tpin ? 'Manage' : 'Create'; ?> Transaction PIN</h5>
                            <p class="text-muted small">Your T-PIN (4-6 digits) is an extra layer of security for authorizing critical operations.</p>
                        </div>
                        
                        <?php if($has_tpin): ?>
                        <div class="alert alert-info border-0 rounded-lg shadow-sm mb-4" style="background-color: #f0f9ff; color: #0369a1;">
                            <i class="fas fa-info-circle mr-2"></i> You already have an active T-PIN. Submitting this form will reset it.
                        </div>
                        <?php endif; ?>
                        
                        <form action="profile.php" method="post" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating-custom">
                                        <div class="input-group-custom">
                                            <input type="password" name="new_tpin" id="new_tpin" class="form-control form-control-custom numeric-only tpin-input" placeholder=" " required pattern="\d{4,6}" minlength="4" maxlength="6">
                                            <label for="new_tpin">Enter T-PIN (4-6 digits)</label>
                                            <span class="input-group-text-custom toggle-password" data-target="new_tpin"><i class="fas fa-eye"></i></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating-custom">
                                        <div class="input-group-custom">
                                            <input type="password" name="confirm_tpin" id="confirm_tpin" class="form-control form-control-custom numeric-only tpin-input" placeholder=" " required pattern="\d{4,6}" minlength="4" maxlength="6">
                                            <label for="confirm_tpin">Confirm T-PIN</label>
                                            <span class="input-group-text-custom toggle-password" data-target="confirm_tpin"><i class="fas fa-eye"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right mt-2">
                                <button type="submit" name="update_tpin" class="btn btn-gradient shadow-sm">
                                    <i class="fas fa-check-circle mr-2"></i> <?php echo $has_tpin ? 'Reset T-PIN' : 'Activate T-PIN'; ?>
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Skeleton Loader Logic (Smooth Transition)
    setTimeout(() => {
        const skeleton = document.getElementById('profile-skeleton');
        const content = document.getElementById('profile-content');
        
        // Fade out skeleton
        skeleton.style.transition = 'opacity 0.3s ease';
        skeleton.style.opacity = '0';
        
        setTimeout(() => {
            skeleton.style.display = 'none';
            content.style.display = 'flex'; // Bootstrap row uses flex
        }, 300);
    }, 800); // Simulated delay for visual feedback

    // 2. Interactive Show/Hide Password functionality
    document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', function() {
            let targetId = this.getAttribute('data-target');
            let inputField = document.getElementById(targetId);
            let iconElement = this.querySelector('i');
            
            if (inputField.type === 'password') {
                inputField.type = 'text';
                iconElement.classList.replace('fa-eye', 'fa-eye-slash');
                this.style.color = '#764ba2'; // Active color
                this.style.borderColor = '#764ba2';
                inputField.style.borderColor = '#764ba2';
            } else {
                inputField.type = 'password';
                iconElement.classList.replace('fa-eye-slash', 'fa-eye');
                this.style.color = ''; // Reset
                this.style.borderColor = '';
                inputField.style.borderColor = '';
            }
        });
    });

    // 3. Strict Numeric Input Filter for T-PIN fields
    document.querySelectorAll('.numeric-only').forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, ''); // Strip non-numeric characters instantly
        });
    });

    // 4. Smooth Auto-dismiss Alerts after 5 seconds
    setTimeout(function() {
        let alerts = document.querySelectorAll('.custom-alert');
        alerts.forEach(alert => {
            if (typeof $ !== 'undefined' && $.fn.alert) {
                $(alert).alert('close');
            } else {
                alert.style.transition = "opacity 0.5s ease, transform 0.5s ease";
                alert.style.opacity = "0";
                alert.style.transform = "translateX(20px)";
                setTimeout(() => alert.remove(), 500);
            }
        });
    }, 5000);
});
</script>

<?php require_once 'footer.php'; ?>