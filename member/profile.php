<?php
// Start output buffering to prevent header issues with PRG pattern
ob_start();

require_once 'header.php'; 
require_once '../includes/mailer.php'; // Include mailer for notifications

// Ensure user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

$member_id = $_SESSION['id'];

// --- Helper function for IP and Location ---
function getClientLocationDetails() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $location = 'Unknown';
    
    // Set a quick timeout so the page doesn't hang if the API is down
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    
    if ($ip !== '::1' && $ip !== '127.0.0.1') {
        $geo = @json_decode(file_get_contents("http://ip-api.com/json/{$ip}", false, $ctx));
        if ($geo && $geo->status === 'success') {
            $location = $geo->city . ', ' . $geo->regionName . ', ' . $geo->country;
        }
    } else {
        $location = 'Local Machine (Localhost)';
    }
    
    return ['ip' => $ip, 'location' => $location];
}

// --- Helper function to send interactive security email ---
function sendSecurityEmail($pdo, $toEmail, $toName, $alertType, $actionDesc) {
    if (empty($toEmail)) return;

    $clientDetails = getClientLocationDetails();
    $datetime = date('F d, Y h:i A');

    // Dynamically determine the login URL for the member area
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $login_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/login.php';

    // Fetch Settings for Logos and Names
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $inst_name = $settings['institution_name'] ?? 'Institution';
    $cant_name = $settings['canteen_name'] ?? 'Canteen';
    $inst_logo = $settings['institution_logo'] ?? '';
    $cant_logo = $settings['canteen_logo'] ?? '';
    
    // Construct dynamic Sender Name
    $sender_name = "{$cant_name} - {$inst_name}";

    // Handle Image Embedding via CID
    $embeddedImages = [];
    $header_html = '<div style="text-align: center; margin-bottom: 25px;">';
    
    if ($inst_logo || $cant_logo) {
        $header_html .= '<div style="margin-bottom: 15px;">';
        if ($inst_logo) {
            $inst_path = dirname(__DIR__) . '/assets/images/logos/' . $inst_logo;
            if (file_exists($inst_path)) {
                $embeddedImages['inst_logo_cid'] = $inst_path;
                $header_html .= '<img src="cid:inst_logo_cid" alt="'.htmlspecialchars($inst_name).'" style="max-height: 70px; margin: 0 15px; vertical-align: middle;">';
            }
        }
        if ($cant_logo) {
            $cant_path = dirname(__DIR__) . '/assets/images/logos/' . $cant_logo;
            if (file_exists($cant_path)) {
                $embeddedImages['cant_logo_cid'] = $cant_path;
                $header_html .= '<img src="cid:cant_logo_cid" alt="'.htmlspecialchars($cant_name).'" style="max-height: 70px; margin: 0 15px; vertical-align: middle;">';
            }
        }
        $header_html .= '</div>';
    }

    $header_html .= '<h1 style="color: #1e293b; margin: 0; font-size: 22px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">'.htmlspecialchars($inst_name).'</h1>';
    $header_html .= '<p style="color: #64748b; margin: 5px 0 0 0; font-size: 16px; font-weight: 600;">'.htmlspecialchars($cant_name).'</p>';
    $header_html .= '</div>';

    $year = date('Y');
    $subject = "Security Alert: {$alertType}";
    
    // Dynamic styling based on alert type
    $emoji = strpos($alertType, 'Password') !== false ? '🔑' : '🛡️';
    $color_grad = "linear-gradient(135deg, #f59e0b 0%, #d97706 100%)"; // Amber warning theme
    $shadow_color = "rgba(245, 158, 11, 0.4)";

    $html_body = "
    <div style='font-family: \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px 20px; background-color: #f1f5f9;'>
        {$header_html}
        <div style='background-color: #ffffff; padding: 40px 30px; border-radius: 16px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
            
            <div style='background: {$color_grad}; color: white; width: 80px; height: 80px; line-height: 80px; border-radius: 50%; font-size: 35px; margin: 0 auto 25px; box-shadow: 0 10px 25px {$shadow_color};'>
                {$emoji}
            </div>
            
            <h2 style='color: #0f172a; margin-top: 0; font-size: 26px; font-weight: 800;'>Security Alert</h2>
            <p style='color: #475569; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>Hi <strong style='color: #0f172a;'>" . htmlspecialchars($toName) . "</strong>,<br>This is a notification that your <strong style='color: #0f172a;'>{$actionDesc}</strong>.</p>

            <div style='background-color: #f8fafc; padding: 25px; border-radius: 12px; margin: 25px 0; border: 1px dashed #cbd5e1; text-align: left; position: relative;'>
                
                <div style='text-align: center; margin-bottom: 20px;'>
                    <span style='background: #fff; padding: 5px 15px; border-radius: 20px; border: 1px solid #e2e8f0; font-weight: 700; color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; position: absolute; top: -14px; left: 50%; transform: translateX(-50%);'>Activity Details</span>
                </div>

                <table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Date & Time</td>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 14px;'>" . htmlspecialchars($datetime) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>IP Address</td>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 14px; font-family: monospace;'>" . htmlspecialchars($clientDetails['ip']) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 15px 0 0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Location</td>
                        <td style='padding: 15px 0 0; text-align: right; color: #0f172a; font-weight: 800; font-size: 14px;'>" . htmlspecialchars($clientDetails['location']) . "</td>
                    </tr>
                </table>
            </div>

            <div style='text-align: center; margin: 35px 0 25px;'>
                <a href='" . htmlspecialchars($login_url) . "' style='background: #0062E6; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px rgba(0, 98, 230, 0.2); transition: all 0.3s ease;'>Log In to System</a>
            </div>

            <div style='text-align: center; margin-top: 30px;'>
                <p style='margin: 0; font-size: 14px; color: #64748b; line-height: 1.5;'>
                    If you did not perform this action, please contact the canteen administrator <strong style='color: #ef4444;'>immediately</strong> to secure your account.
                </p>
            </div>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; color: #94a3b8; font-size: 12px;'>
                &copy; $year {$cant_name}. All rights reserved.<br>
                Powered by Tech Innavo
            </div>
        </div>
    </div>";

    // Call mailer function defined in mailer.php
    sendMail($toEmail, $toName, $subject, $html_body, $sender_name, $embeddedImages);
}

// --- Handle Form Submissions (PRG Pattern) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle Password Change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        try {
            // Fetch current password and user details for the email
            $stmt = $pdo->prepare("SELECT name, email, password FROM members WHERE id = ?");
            $stmt->execute([$member_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['password'] === $current_password) {
                if ($new_password === $confirm_password) {
                    $update_stmt = $pdo->prepare("UPDATE members SET password = ? WHERE id = ?");
                    $update_stmt->execute([$new_password, $member_id]);
                    $_SESSION['flash_success'] = "Password changed successfully!";

                    // Send Security Email
                    sendSecurityEmail($pdo, $user['email'], $user['name'], 'Login Password Changed', 'Canteen Member Portal login password was recently changed');

                } else {
                    $_SESSION['flash_error'] = "New passwords do not match!";
                }
            } else {
                $_SESSION['flash_error'] = "Incorrect current password!";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "An error occurred while changing the password.";
        }

        header("Location: profile.php");
        exit;
    }

    // Handle T-PIN Change/Create
    if (isset($_POST['change_tpin'])) {
        $current_password = $_POST['tpin_password'];
        $new_tpin = $_POST['new_tpin'];
        $confirm_tpin = $_POST['confirm_tpin'];

        try {
            // Fetch user details to verify identity and send email
            $stmt = $pdo->prepare("SELECT name, email, password, t_pin FROM members WHERE id = ?");
            $stmt->execute([$member_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['password'] === $current_password) {
                if ($new_tpin === $confirm_tpin && preg_match('/^\d{4}$/', $new_tpin)) {
                    
                    $actionType = empty($user['t_pin']) ? "Created" : "Reset";

                    // Update T-PIN
                    $update_stmt = $pdo->prepare("UPDATE members SET t_pin = ? WHERE id = ?");
                    $update_stmt->execute([$new_tpin, $member_id]);
                    $_SESSION['flash_success'] = "T-PIN $actionType successfully!";

                    // Send Security Email
                    sendSecurityEmail($pdo, $user['email'], $user['name'], "T-PIN {$actionType}", "Canteen Wallet T-PIN was recently {$actionType}");

                } else {
                    $_SESSION['flash_error'] = "Invalid T-PIN. It must be exactly 4 digits and match the confirmation.";
                }
            } else {
                $_SESSION['flash_error'] = "Incorrect login password! Cannot update T-PIN.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Database error: Could not update T-PIN.";
        }

        header("Location: profile.php");
        exit;
    }
}

// --- Fetch Current Member Data ---
$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if T-PIN is set
$has_tpin = !empty($member['t_pin']);

// Check for flash messages
$flash_success = '';
$flash_error = '';
if (isset($_SESSION['flash_success'])) {
    $flash_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?>

<style>
    /* Skeleton Loader Styles */
    .skeleton-wrapper { width: 100%; }
    .skeleton-box {
        background: #e2e5e7;
        background: linear-gradient(90deg, #e2e5e7 25%, #f0f2f3 50%, #e2e5e7 75%);
        background-size: 200% 100%;
        animation: loadingShimmer 1.5s infinite;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .skeleton-avatar { width: 120px; height: 120px; border-radius: 50%; margin: 0 auto 20px auto; }
    .skeleton-title { height: 30px; width: 50%; margin-bottom: 25px; }
    .skeleton-text { height: 20px; width: 100%; }
    .skeleton-text-short { height: 20px; width: 70%; }
    
    @keyframes loadingShimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Page UI Enhancements */
    .profile-card {
        background: #ffffff;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: none;
        overflow: hidden;
    }
    .profile-header {
        background: linear-gradient(135deg, #0062E6 0%, #33AEFF 100%);
        color: white;
        padding: 30px 20px;
        text-align: center;
    }
    .profile-avatar {
        width: 100px;
        height: 100px;
        background: white;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: #0062E6;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        margin-bottom: 15px;
    }
    .card-title-modern {
        font-weight: 600;
        color: #333;
        margin-bottom: 20px;
        border-bottom: 2px solid #f4f7f6;
        padding-bottom: 10px;
    }
    .info-label { font-size: 0.85rem; color: #777; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
    .info-value { font-size: 1.1rem; font-weight: 500; color: #222; margin-bottom: 15px; }
    
    .form-control-modern {
        border-radius: 10px;
        padding: 12px 15px;
        border: 1px solid #ced4da;
        transition: all 0.3s ease;
    }
    .form-control-modern:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 98, 230, 0.25);
        border-color: #0062E6;
    }
    .btn-primary-modern {
        background: #0062E6;
        border: none;
        border-radius: 10px;
        padding: 10px 25px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .btn-primary-modern:hover {
        background: #004bb5;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 98, 230, 0.3);
    }
</style>

<div id="skeleton-loader" class="row">
    <div class="col-lg-4 mb-4">
        <div class="profile-card p-4 text-center skeleton-wrapper">
            <div class="skeleton-box skeleton-avatar"></div>
            <div class="skeleton-box skeleton-title mx-auto"></div>
            <div class="skeleton-box skeleton-text"></div>
            <div class="skeleton-box skeleton-text-short mx-auto"></div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="profile-card p-4 mb-4 skeleton-wrapper">
            <div class="skeleton-box skeleton-title"></div>
            <div class="skeleton-box skeleton-text"></div>
            <div class="skeleton-box skeleton-text"></div>
        </div>
        <div class="profile-card p-4 skeleton-wrapper">
            <div class="skeleton-box skeleton-title"></div>
            <div class="skeleton-box skeleton-text"></div>
            <div class="skeleton-box skeleton-text"></div>
        </div>
    </div>
</div>

<div id="actual-content" style="display: none;">
    
    <?php if ($flash_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($flash_success); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($flash_error); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($member['name']); ?></h4>
                    <p class="mb-0 text-light"><i class="fas fa-id-badge mr-1"></i> <?php echo htmlspecialchars($member['member_id']); ?></p>
                </div>
                <div class="p-4">
                    <div class="info-label">Account Status</div>
                    <div class="info-value">
                        <?php if($member['status'] === 'active'): ?>
                            <span class="badge badge-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle mr-1"></i> Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger px-3 py-2 rounded-pill"><i class="fas fa-times-circle mr-1"></i> Inactive</span>
                        <?php endif; ?>
                    </div>

                    <div class="info-label mt-4">T-PIN Status</div>
                    <div class="info-value">
                        <?php if($has_tpin): ?>
                            <span class="text-success"><i class="fas fa-shield-alt mr-1"></i> Configured</span>
                        <?php else: ?>
                            <span class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i> Not Set</span>
                        <?php endif; ?>
                    </div>

                    <div class="info-label mt-4">Department</div>
                    <div class="info-value"><i class="fas fa-building mr-2 text-primary"></i><?php echo htmlspecialchars($member['department'] ?: 'N/A'); ?></div>

                    <div class="info-label mt-4">Email</div>
                    <div class="info-value"><i class="fas fa-envelope mr-2 text-primary"></i><?php echo htmlspecialchars($member['email'] ?: 'N/A'); ?></div>

                    <div class="info-label mt-4">Member Since</div>
                    <div class="info-value"><i class="far fa-calendar-alt mr-2 text-primary"></i><?php echo date('F d, Y', strtotime($member['created_at'])); ?></div>
                    
                    <div class="info-label mt-4">Last Login</div>
                    <div class="info-value"><i class="far fa-clock mr-2 text-primary"></i><?php echo $member['last_login'] ? date('F d, Y h:i A', strtotime($member['last_login'])) : 'Never'; ?></div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            
            <div class="profile-card p-4 mb-4">
                <h5 class="card-title-modern text-primary"><i class="fas fa-lock mr-2"></i> Change Login Password</h5>
                
                <form action="profile.php" method="POST" id="passwordForm">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold text-muted">Current Password</label>
                            <input type="password" name="current_password" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold text-muted">New Password</label>
                            <input type="password" name="new_password" id="new_password" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold text-muted">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-modern" required>
                            <small id="passwordError" class="text-danger mt-1 d-none">Passwords do not match!</small>
                        </div>
                    </div>
                    <div class="text-right mt-3">
                        <button type="submit" name="change_password" class="btn btn-primary-modern">
                            <i class="fas fa-key mr-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>

            <div class="profile-card p-4">
                <h5 class="card-title-modern text-warning"><i class="fas fa-th mr-2"></i> <?php echo $has_tpin ? 'Reset' : 'Create'; ?> T-PIN</h5>
                <p class="text-muted small mb-4">Your T-PIN (Transaction PIN) is a 4-digit security code used to authorize wallet deductions during checkout.</p>
                
                <form action="profile.php" method="POST" id="tpinForm">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold text-muted">Current Login Password <small class="(Required for security)"></small></label>
                            <input type="password" name="tpin_password" class="form-control form-control-modern" placeholder="Enter your login password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold text-muted">New 4-Digit T-PIN</label>
                            <input type="password" name="new_tpin" id="new_tpin" class="form-control form-control-modern" maxlength="4" pattern="\d{4}" placeholder="e.g. 1234" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold text-muted">Confirm New T-PIN</label>
                            <input type="password" name="confirm_tpin" id="confirm_tpin" class="form-control form-control-modern" maxlength="4" pattern="\d{4}" placeholder="Confirm 4 digits" required>
                            <small id="tpinError" class="text-danger mt-1 d-none">T-PINs do not match!</small>
                        </div>
                    </div>
                    <div class="text-right mt-3">
                        <button type="submit" name="change_tpin" class="btn btn-primary-modern bg-warning text-dark border-0">
                            <i class="fas fa-save mr-1"></i> <?php echo $has_tpin ? 'Update T-PIN' : 'Set T-PIN'; ?>
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Handle Skeleton Loader transition 
        setTimeout(function() {
            document.getElementById('skeleton-loader').style.display = 'none';
            document.getElementById('actual-content').style.display = 'block';
        }, 500);

        // --- Password Form Validation ---
        const passwordForm = document.getElementById('passwordForm');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordError = document.getElementById('passwordError');

        passwordForm.addEventListener('submit', function(e) {
            if (newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                passwordError.classList.remove('d-none');
                confirmPassword.classList.add('border-danger');
            }
        });

        confirmPassword.addEventListener('input', function() {
             if (newPassword.value === confirmPassword.value) {
                 passwordError.classList.add('d-none');
                 confirmPassword.classList.remove('border-danger');
             }
        });

        // --- T-PIN Form Validation ---
        const tpinForm = document.getElementById('tpinForm');
        const newTpin = document.getElementById('new_tpin');
        const confirmTpin = document.getElementById('confirm_tpin');
        const tpinError = document.getElementById('tpinError');

        tpinForm.addEventListener('submit', function(e) {
            let isValid = true;
            let errorMsg = "";

            if (newTpin.value !== confirmTpin.value) {
                isValid = false;
                errorMsg = "T-PINs do not match!";
            } else if (!/^\d{4}$/.test(newTpin.value)) {
                isValid = false;
                errorMsg = "T-PIN must be exactly 4 digits.";
            }

            if (!isValid) {
                e.preventDefault();
                tpinError.innerText = errorMsg;
                tpinError.classList.remove('d-none');
                confirmTpin.classList.add('border-danger');
                if(!/^\d{4}$/.test(newTpin.value)) newTpin.classList.add('border-danger');
            }
        });

        [newTpin, confirmTpin].forEach(input => {
            input.addEventListener('input', function() {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                
                if (newTpin.value === confirmTpin.value && /^\d{4}$/.test(newTpin.value)) {
                    tpinError.classList.add('d-none');
                    confirmTpin.classList.remove('border-danger');
                    newTpin.classList.remove('border-danger');
                }
            });
        });
    });
</script>

<?php 
require_once 'footer.php'; 
ob_end_flush(); 
?>