<?php
ob_start(); // Start output buffering to prevent headers already sent errors
session_start();
require_once '../includes/db.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'super_admin'){
    header("location: ../index.php");
    exit;
}

// ==========================================
// AJAX: Real-time Duplicate Check
// ==========================================
if (isset($_POST['action']) && $_POST['action'] === 'check_duplicate') {
    ob_end_clean();
    header('Content-Type: application/json');
    $field = $_POST['field'] ?? '';
    $value = trim($_POST['value'] ?? '');
    
    $response = ['exists' => false, 'message' => ''];
    
    if ($value !== '') {
        if ($field === 'user_id') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT admin_id as id FROM super_admins UNION SELECT manager_id FROM managers UNION SELECT staff_id FROM canteen_staff UNION SELECT member_id FROM members) as all_users WHERE id = ?");
            $stmt->execute([$value]);
            if ($stmt->fetchColumn() > 0) {
                $response['exists'] = true;
                $response['message'] = 'User ID already exists.';
            }
        } elseif ($field === 'email') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT email FROM super_admins WHERE email IS NOT NULL UNION ALL SELECT email FROM managers WHERE email IS NOT NULL UNION ALL SELECT email FROM canteen_staff WHERE email IS NOT NULL UNION ALL SELECT email FROM members WHERE email IS NOT NULL) as all_emails WHERE email = ?");
            $stmt->execute([$value]);
            if ($stmt->fetchColumn() > 0) {
                $response['exists'] = true;
                $response['message'] = 'Email already exists.';
            }
        }
    }
    echo json_encode($response);
    exit;
}

// Function to queue the highly interactive POS-style welcome email
function queue_welcome_email($pdo, $email, $name, $user_id, $password, $role, $department = null) {
    // Fetch Settings for Logos and Names
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $inst_name = $settings['institution_name'] ?? 'Institution';
    $cant_name = $settings['canteen_name'] ?? 'Canteen';
    $inst_logo = $settings['institution_logo'] ?? '';
    $cant_logo = $settings['canteen_logo'] ?? '';
    
    // Construct dynamic Sender Name
    $sender_name = "{$cant_name} - {$inst_name}";

    // Handle Image Embedding via CID (Content-ID)
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

    // Dynamically build the portal URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $domain = $_SERVER['HTTP_HOST'];
    $base_dir = dirname(dirname($_SERVER['PHP_SELF'])); 
    $portal_url = $protocol . "://" . $domain . $base_dir . "/" . $role . "/login.php";
    $year = date('Y');
    
    $subject = "Welcome to {$cant_name}! Your Login Details 🍔";
    
    $dept_html = "";
    if ($role === 'member' && !empty($department)) {
        $dept_html = "
        <tr>
            <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Department</td>
            <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 16px;'>" . htmlspecialchars($department) . "</td>
        </tr>";
    }

    $html_body = "
    <div style='font-family: \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px 20px; background-color: #f1f5f9;'>
        {$header_html}
        <div style='background-color: #ffffff; padding: 40px 30px; border-radius: 16px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
            
            <div style='background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: white; width: 80px; height: 80px; line-height: 80px; border-radius: 50%; font-size: 35px; margin: 0 auto 25px; box-shadow: 0 10px 25px rgba(79, 70, 229, 0.4);'>
                👋
            </div>
            
            <h2 style='color: #0f172a; margin-top: 0; font-size: 26px; font-weight: 800;'>Welcome Aboard!</h2>
            <p style='color: #475569; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>Hi <strong style='color: #0f172a;'>" . htmlspecialchars($name) . "</strong>,<br>Your account is ready! You can now access the portal to explore your dashboard.</p>

            <div style='background-color: #f8fafc; padding: 25px; border-radius: 12px; margin: 25px 0; border: 1px dashed #cbd5e1; text-align: left; position: relative;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <span style='background: #fff; padding: 5px 15px; border-radius: 20px; border: 1px solid #e2e8f0; font-weight: 700; color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; position: absolute; top: -14px; left: 50%; transform: translateX(-50%);'>Account Details</span>
                </div>
                <table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Name</td>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 16px;'>" . htmlspecialchars($name) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Email</td>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 16px;'>" . htmlspecialchars($email) . "</td>
                    </tr>
                    {$dept_html}
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>User ID</td>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 18px; font-family: monospace;'>" . htmlspecialchars($user_id) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Password</td>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 18px; font-family: monospace;'>" . htmlspecialchars($password) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 15px 0 0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Role</td>
                        <td style='padding: 15px 0 0; text-align: right; color: #4f46e5; text-transform: uppercase; font-weight: 800; font-size: 14px;'>" . htmlspecialchars(str_replace('_', ' ', $role)) . "</td>
                    </tr>
                </table>
            </div>

            <div style='text-align: center; margin-top: 30px;'>
                <a href='" . htmlspecialchars($portal_url) . "' style='display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; text-decoration: none; padding: 16px 35px; border-radius: 50px; font-weight: 700; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4); transition: transform 0.2s;'>
                    Login to Portal &rarr;
                </a>
                <p style='margin: 20px 0 0; font-size: 13px; color: #94a3b8; line-height: 1.5;'>
                    <i style='color: #ef4444;'>*</i> For your security, please update your password after your first login.
                </p>
            </div>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; color: #94a3b8; font-size: 12px;'>
                &copy; $year {$cant_name}. All rights reserved.<br>
                Powered by Tech Innavo
            </div>
        </div>
    </div>";

    $images_json = !empty($embeddedImages) ? json_encode($embeddedImages) : null;
    $stmt = $pdo->prepare("INSERT INTO email_queue (recipient_email, recipient_name, subject, html_body, sender_name, embedded_images, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$email, $name, $subject, $html_body, $sender_name, $images_json]);
}

// PRG pattern: Process POST request and redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $emails_queued = false;

    // Add user
    if (isset($_POST['add_user'])) {
        $user_id = trim($_POST['user_id']);
        $name = trim($_POST['name']);
        $email = !empty(trim($_POST['email'])) ? trim($_POST['email']) : null;
        $role = $_POST['role'];
        $password = $user_id; 

        // Additional validation to prevent duplicate insertion
        $isDuplicate = false;
        $stmtDup = $pdo->prepare("SELECT COUNT(*) FROM (SELECT admin_id as id, email FROM super_admins UNION ALL SELECT manager_id, email FROM managers UNION ALL SELECT staff_id, email FROM canteen_staff UNION ALL SELECT member_id, email FROM members) as all_users WHERE id = ? OR (email = ? AND email IS NOT NULL)");
        $stmtDup->execute([$user_id, $email]);
        if ($stmtDup->fetchColumn() > 0) {
            $isDuplicate = true;
            $_SESSION['toast_msg'] = "Failed: User ID or Email already exists. ⚠️";
        }

        if (!$isDuplicate) {
            if ($role === 'super_admin') {
                if ($pdo->prepare("INSERT INTO super_admins (admin_id, password, name, email) VALUES (?, ?, ?, ?)")->execute([$user_id, $password, $name, $email])) {
                    if (!empty($email)) { queue_welcome_email($pdo, $email, $name, $user_id, $password, 'super_admin'); $emails_queued = true; }
                }
            } elseif ($role === 'manager') {
                if ($pdo->prepare("INSERT INTO managers (manager_id, password, name, email, status) VALUES (?, ?, ?, ?, 'active')")->execute([$user_id, $password, $name, $email])) {
                    if (!empty($email)) { queue_welcome_email($pdo, $email, $name, $user_id, $password, 'manager'); $emails_queued = true; }
                }
            } elseif ($role === 'staff') {
                if ($pdo->prepare("INSERT INTO canteen_staff (staff_id, password, name, email, status) VALUES (?, ?, ?, ?, 'inactive')")->execute([$user_id, $password, $name, $email])) {
                    if (!empty($email)) { queue_welcome_email($pdo, $email, $name, $user_id, $password, 'staff'); $emails_queued = true; }
                }
            } elseif ($role === 'member') {
                $department = isset($_POST['department']) ? trim($_POST['department']) : null;
                if ($pdo->prepare("INSERT INTO members (member_id, password, name, email, department, status) VALUES (?, ?, ?, ?, ?, 'inactive')")->execute([$user_id, $password, $name, $email, $department])) {
                    if (!empty($email)) { queue_welcome_email($pdo, $email, $name, $user_id, $password, 'member', $department); $emails_queued = true; }
                }
            }
            if(!isset($_SESSION['toast_msg'])){
                $_SESSION['toast_msg'] = $emails_queued ? "User created & welcome email queued! 📧" : "User created successfully! 🎉";
            }
        }
    }

    // Update user
    if (isset($_POST['update_user'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $email = !empty(trim($_POST['email'])) ? trim($_POST['email']) : null;
        $role = $_POST['role']; 
        $status = $_POST['status'] ?? 'active'; 

        if ($role === 'super_admin') {
            $pdo->prepare("UPDATE super_admins SET name = ?, email = ? WHERE id = ?")->execute([$name, $email, $id]);
        } elseif ($role === 'manager') {
            $pdo->prepare("UPDATE managers SET name = ?, email = ?, status = ? WHERE id = ?")->execute([$name, $email, $status, $id]);
        } elseif ($role === 'staff') {
            $pdo->prepare("UPDATE canteen_staff SET name = ?, email = ?, status = ? WHERE id = ?")->execute([$name, $email, $status, $id]);
        } elseif ($role === 'member') {
            $department = isset($_POST['department']) ? trim($_POST['department']) : null;
            $pdo->prepare("UPDATE members SET name = ?, email = ?, department = ?, status = ? WHERE id = ?")->execute([$name, $email, $department, $status, $id]);
        }
        $_SESSION['toast_msg'] = "User details updated successfully! ✏️";
    }

    // Delete user
    if (isset($_POST['delete_user'])) {
        $id = $_POST['id'];
        $role = $_POST['role'];
        if ($role === 'super_admin') $pdo->prepare("DELETE FROM super_admins WHERE id = ?")->execute([$id]);
        elseif ($role === 'manager') $pdo->prepare("DELETE FROM managers WHERE id = ?")->execute([$id]);
        elseif ($role === 'staff') $pdo->prepare("DELETE FROM canteen_staff WHERE id = ?")->execute([$id]);
        elseif ($role === 'member') $pdo->prepare("DELETE FROM members WHERE id = ?")->execute([$id]);
        
        $_SESSION['toast_msg'] = "User account removed permanently! 🗑️";
    }
    
    ob_end_clean();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

$toast_msg = $_SESSION['toast_msg'] ?? '';
unset($_SESSION['toast_msg']);

include 'header.php';

// Fetch all users including super_admins mapping email
$sql = "
    SELECT id, admin_id AS user_id, name, email, 'super_admin' AS role, NULL AS department, 'active' AS status, created_at FROM super_admins
    UNION ALL
    SELECT id, manager_id AS user_id, name, email, 'manager' AS role, NULL AS department, status, created_at FROM managers
    UNION ALL
    SELECT id, staff_id AS user_id, name, email, 'staff' AS role, NULL AS department, status, created_at FROM canteen_staff
    UNION ALL
    SELECT id, member_id AS user_id, name, email, 'member' AS role, department, status, created_at FROM members
    ORDER BY created_at DESC
";
$all_users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Split users by role
$super_admins = array_filter($all_users, fn($u) => $u['role'] === 'super_admin');
$managers = array_filter($all_users, fn($u) => $u['role'] === 'manager');
$staff = array_filter($all_users, fn($u) => $u['role'] === 'staff');
$members = array_filter($all_users, fn($u) => $u['role'] === 'member');

// Collect all modals
$globalModals = ""; 
?>

<style>
    /* Full Page Loader */
    #page-loader {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.95); z-index: 9999;
        display: flex; justify-content: center; align-items: center;
        transition: opacity 0.5s ease, visibility 0.5s ease;
    }
    .spinner {
        width: 60px; height: 60px; border: 6px solid #e3e6f0;
        border-top: 6px solid #4e73df; border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    /* Fade-in Animations */
    .fade-in-up {
        animation: fadeInUp 0.7s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
        opacity: 0; transform: translateY(15px);
    }
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }

    /* Elegant Styling */
    .elegant-card { 
        border-radius: 16px; 
        border: none; 
        box-shadow: 0 8px 24px rgba(0,0,0,0.04) !important;
    }
    .form-control, .custom-select {
        border-radius: 10px; 
        padding: 0.75rem 1rem; 
        border: 1px solid #e2e8f0;
        background-color: #f8fafc;
        transition: all 0.3s ease;
    }
    .form-control:focus, .custom-select:focus { 
        border-color: #4e73df; 
        background-color: #ffffff;
        box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.15); 
    }
    
    /* Table Styling */
    .table-container { padding: 0 1.5rem; }
    .table-hover tbody tr { 
        transition: all 0.2s ease; 
        box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    }
    .table-hover tbody tr:hover {
        background-color: #ffffff; 
        transform: scale(1.01) translateY(-2px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.08);
        z-index: 1;
        position: relative;
    }
    .avatar-circle {
        width: 42px; height: 42px; font-weight: bold; font-size: 1.1rem;
        display: flex; justify-content: center; align-items: center; border-radius: 50%;
    }
    .badge { padding: 0.55em 0.9em; font-weight: 600; letter-spacing: 0.3px; border-radius: 8px;}
    
    /* Buttons */
    .action-btn { transition: all 0.2s ease; }
    .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

    /* Tab Pills Menu */
    .nav-wrapper {
        background: #f1f5f9;
        padding: 5px;
        border-radius: 12px;
        display: inline-flex;
        overflow-x: auto;
    }
    .custom-pills .nav-link {
        border-radius: 8px; padding: 8px 20px; color: #64748b;
        font-weight: 600; transition: all 0.3s ease; border: none; white-space: nowrap;
    }
    .custom-pills .nav-link:hover { color: #4e73df; }
    .custom-pills .nav-link.active {
        background-color: #ffffff; color: #4e73df;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
</style>

<?php if (!empty($toast_msg)): ?>
<div id="toast-notification" class="shadow-lg rounded-pill px-4 py-3 d-flex align-items-center" style="position: fixed; top: 20px; right: 20px; z-index: 1055; background: linear-gradient(135deg, <?php echo strpos($toast_msg, 'Failed') !== false ? '#ef4444 0%, #dc2626 100%' : '#10b981 0%, #059669 100%'; ?>); color: white; font-weight: 600; transform: translateX(150%); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
    <i class="fas <?php echo strpos($toast_msg, 'Failed') !== false ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> fa-lg mr-2"></i>
    <span><?php echo $toast_msg; ?></span>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var toast = document.getElementById('toast-notification');
        if (toast) {
            setTimeout(() => toast.style.transform = 'translateX(0)', 100);
            setTimeout(() => {
                toast.style.transform = 'translateX(150%)';
                setTimeout(() => toast.remove(), 400);
            }, 4500);
        }
    });
</script>
<?php endif; ?>

<div id="page-loader">
    <div class="spinner"></div>
</div>

<div class="container-fluid fade-in-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-gray-800 font-weight-bold mb-0">User Management</h3>
    </div>

    <div class="row align-items-start">
        
        <div class="col-lg-4 col-md-5 mb-4 fade-in-up delay-1">
            <div class="card elegant-card">
                <div class="card-header bg-white py-4 border-bottom-0" style="border-radius: 16px 16px 0 0;">
                    <h5 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-plus mr-2 text-info"></i> Register New User</h5>
                </div>
                <div class="card-body p-4 pt-0">
                    <form action="user_management.php" method="post" class="needs-validation">
                        <div class="form-group">
                            <label class="font-weight-bold text-muted small text-uppercase mb-1">User ID / Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="user_id" id="new_user_id" required autocomplete="off" placeholder="e.g., EMP001">
                            <small id="user_id_feedback" class="text-danger font-weight-bold mt-1" style="display:none;"><i class="fas fa-times-circle mr-1"></i> ID already exists!</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="font-weight-bold text-muted small text-uppercase mb-1">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required placeholder="John Doe">
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold text-muted small text-uppercase mb-1">Email <span class="text-muted text-lowercase font-weight-normal">(Optional)</span></label>
                            <input type="email" class="form-control" name="email" id="new_email" placeholder="john@example.com">
                            <small id="email_feedback" class="text-danger font-weight-bold mt-1" style="display:none;"><i class="fas fa-times-circle mr-1"></i> Email already exists!</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="font-weight-bold text-muted small text-uppercase mb-1">Role <span class="text-danger">*</span></label>
                            <select class="form-control custom-select" name="role" id="role" required>
                                <option value="member">Member</option>
                                <option value="staff">Staff</option>
                                <option value="manager">Manager</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        
                        <div id="member-fields">
                            <div class="form-group">
                                <label class="font-weight-bold text-muted small text-uppercase mb-1">Department</label>
                                <input type="text" class="form-control" name="department" placeholder="e.g., CSE">
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-2">
                            <button type="submit" name="add_user" id="submit_btn" class="btn btn-primary btn-block shadow-sm py-2 action-btn" style="border-radius: 10px; font-weight: 600; font-size: 1.05rem;">
                                <i class="fas fa-check-circle mr-2"></i> Create User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-md-7 mb-4 fade-in-up delay-2">
            <div class="card elegant-card h-100">
                <div class="card-header bg-white py-4 border-bottom-0" style="border-radius: 16px 16px 0 0;">
                    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center">
                        <h5 class="m-0 font-weight-bold text-primary mb-3 mb-xl-0"><i class="fas fa-users-cog mr-2 text-warning"></i> Directory Listing</h5>
                        
                        <div class="nav-wrapper">
                            <ul class="nav nav-pills custom-pills" id="roleTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="superadmin-tab" data-toggle="pill" href="#tab-superadmin" role="tab">Super Admins</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="managers-tab" data-toggle="pill" href="#tab-managers" role="tab">Managers</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="staff-tab" data-toggle="pill" href="#tab-staff" role="tab">Staff</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="members-tab" data-toggle="pill" href="#tab-members" role="tab">Members</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-0 pb-4 bg-white" style="border-radius: 0 0 16px 16px;">
                    <div class="tab-content" id="roleTabsContent">
                        
                        <?php 
                        function renderTable($usersList, $roleName, $badgeClass, $hasDepartment) {
                            global $globalModals; 
                            $usersList = array_values($usersList);
                            $total = count($usersList);
                            
                            if($total === 0) {
                                echo "<div class='text-center py-5 text-muted'><i class='fas fa-folder-open fa-2x mb-3 text-light'></i><br>No {$roleName}s found in the system.</div>";
                                return;
                            }
                            
                            echo '<div class="table-responsive table-container">
                                    <table class="table table-hover mb-0" width="100%" cellspacing="0" style="border-collapse: separate; border-spacing: 0 10px;">
                                        <thead class="text-uppercase text-xs font-weight-bold text-muted border-bottom-0">
                                            <tr>
                                                <th class="border-top-0 border-bottom-0 pl-3">User Details</th>';
                            if($hasDepartment) {
                                echo '          <th class="border-top-0 border-bottom-0">Dept</th>';
                            }
                            echo '              <th class="border-top-0 border-bottom-0">Status</th>
                                                <th class="border-top-0 border-bottom-0 text-right pr-4">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>';

                            foreach ($usersList as $index => $user) {
                                $hiddenClass = ($index >= 2) ? "d-none toggle-row-{$roleName}" : "";
                                $initial = strtoupper(substr($user['name'], 0, 1));
                                $statusBadge = $user['status'] === 'active' ? 'success' : 'danger';
                                
                                $emailDisplay = !empty($user['email']) ? "<div class='small text-muted'><i class='fas fa-envelope mr-1'></i>" . htmlspecialchars($user['email']) . "</div>" : "";

                                echo "<tr class='bg-light {$hiddenClass}' style='border-radius: 12px;'>
                                        <td class='align-middle border-top-0 rounded-left pl-3 py-2'>
                                            <div class='d-flex align-items-center'>
                                                <div class='mr-3 d-none d-sm-block'>
                                                    <div class='avatar-circle bg-white text-primary shadow-sm'>{$initial}</div>
                                                </div>
                                                <div>
                                                    <div class='font-weight-bold text-dark mb-0' style='font-size: 1.05rem;'>" . htmlspecialchars($user['name']) . "</div>
                                                    <div class='small text-muted font-weight-bold mb-1'><i class='fas fa-id-badge mr-1'></i>" . htmlspecialchars($user['user_id']) . "</div>
                                                    {$emailDisplay}
                                                </div>
                                            </div>
                                        </td>";
                                        
                                if($hasDepartment) {
                                    $dept = htmlspecialchars($user['department'] ?? '-');
                                    echo "<td class='align-middle border-top-0 text-secondary font-weight-bold small'>{$dept}</td>";
                                }

                                echo "  <td class='align-middle border-top-0'>
                                            <span class='badge badge-{$statusBadge} bg-white border border-{$statusBadge} text-{$statusBadge} shadow-sm'>" . ucfirst($user['status']) . "</span>
                                        </td>
                                        <td class='align-middle border-top-0 rounded-right text-right pr-3'>
                                            <button class='btn btn-sm btn-white text-info rounded-circle px-2 py-1 mr-1 action-btn shadow-sm' data-toggle='modal' data-target='#editModal-{$user['role']}-{$user['id']}' title='Edit User'>
                                                <i class='fas fa-pen fa-sm'></i>
                                            </button>
                                            <form action='user_management.php' method='post' class='d-inline' onsubmit='return confirm(\"Permanently delete this user?\");'>
                                                <input type='hidden' name='id' value='{$user['id']}'>
                                                <input type='hidden' name='role' value='{$user['role']}'>
                                                <button type='submit' name='delete_user' class='btn btn-sm btn-white text-danger rounded-circle px-2 py-1 action-btn shadow-sm' title='Delete User'>
                                                    <i class='fas fa-trash fa-sm'></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>";
                                    
                                $deptField = "";
                                if ($hasDepartment) {
                                    $deptField = "<div class='form-group'>
                                                    <label class='font-weight-bold text-muted small text-uppercase'>Department</label>
                                                    <input type='text' class='form-control' name='department' value='" . htmlspecialchars($user['department'] ?? '') . "'>
                                                  </div>";
                                }
                                
                                $activeSel = $user['status'] === 'active' ? 'selected' : '';
                                $inactiveSel = $user['status'] === 'inactive' ? 'selected' : '';
                                
                                // Disable status modification for super_admin
                                $statusField = "";
                                if ($user['role'] !== 'super_admin') {
                                    $statusField = "
                                        <div class='form-group mb-0'>
                                            <label class='font-weight-bold text-muted small text-uppercase'>Account Status</label>
                                            <select class='form-control custom-select' name='status' required>
                                                <option value='active' {$activeSel}>Active</option>
                                                <option value='inactive' {$inactiveSel}>Inactive</option>
                                            </select>
                                        </div>";
                                } else {
                                    $statusField = "<input type='hidden' name='status' value='active'>";
                                }

                                $globalModals .= "
                                <div class='modal fade' id='editModal-{$user['role']}-{$user['id']}' tabindex='-1'>
                                    <div class='modal-dialog modal-dialog-centered'>
                                        <div class='modal-content border-0 shadow-lg' style='border-radius: 16px;'>
                                            <div class='modal-header bg-light border-bottom-0 py-3' style='border-radius: 16px 16px 0 0;'>
                                                <h5 class='modal-title font-weight-bold text-dark'><i class='fas fa-user-edit text-primary mr-2'></i>Edit Details</h5>
                                                <button type='button' class='close' data-dismiss='modal'><span>&times;</span></button>
                                            </div>
                                            <form action='user_management.php' method='post'>
                                                <div class='modal-body px-4 py-4 text-left'>
                                                    <input type='hidden' name='id' value='{$user['id']}'>
                                                    <input type='hidden' name='role' value='{$user['role']}'>
                                                    <div class='form-group'>
                                                        <label class='font-weight-bold text-muted small text-uppercase'>Full Name</label>
                                                        <input type='text' class='form-control' name='name' value='" . htmlspecialchars($user['name']) . "' required>
                                                    </div>
                                                    <div class='form-group'>
                                                        <label class='font-weight-bold text-muted small text-uppercase'>Email Address</label>
                                                        <input type='email' class='form-control' name='email' value='" . htmlspecialchars($user['email'] ?? '') . "'>
                                                    </div>
                                                    {$deptField}
                                                    {$statusField}
                                                </div>
                                                <div class='modal-footer border-top-0 px-4 pb-4'>
                                                    <button type='button' class='btn btn-light rounded-pill px-4' data-dismiss='modal'>Cancel</button>
                                                    <button type='submit' name='update_user' class='btn btn-primary rounded-pill px-4 shadow-sm'>Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>";
                            }
                            echo '      </tbody>
                                    </table>
                                </div>'; 
                                
                            // View All Button Logic
                            if($total > 2) {
                                echo "<div class='text-center mt-3 mb-2'>
                                        <button type='button' id='btn-view-{$roleName}' class='btn btn-sm btn-outline-primary px-4 py-2 action-btn' style='border-radius: 20px; font-weight: 600;' onclick='toggleViewAll(\"{$roleName}\")'>View All ({$total}) &rarr;</button>
                                      </div>";
                            }
                        }
                        ?>

                        <div class="tab-pane fade show active" id="tab-superadmin" role="tabpanel">
                            <?php renderTable($super_admins, 'superadmin', 'danger', false); ?>
                        </div>

                        <div class="tab-pane fade" id="tab-managers" role="tabpanel">
                            <?php renderTable($managers, 'manager', 'primary', false); ?>
                        </div>

                        <div class="tab-pane fade" id="tab-staff" role="tabpanel">
                            <?php renderTable($staff, 'staff', 'info', false); ?>
                        </div>

                        <div class="tab-pane fade" id="tab-members" role="tabpanel">
                            <?php renderTable($members, 'member', 'secondary', true); ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php echo $globalModals; ?>

<script src="https://kit.fontawesome.com/a076d05399.js"></script>

<script>
    // Full Page Loader
    window.addEventListener('load', function() {
        const loader = document.getElementById('page-loader');
        loader.style.opacity = '0';
        setTimeout(function() {
            loader.style.visibility = 'hidden';
            loader.style.display = 'none';
        }, 500);
    });

    // View All / View Less Toggle Script
    function toggleViewAll(roleName) {
        const rows = document.querySelectorAll('.toggle-row-' + roleName);
        const btn = document.getElementById('btn-view-' + roleName);
        let isCurrentlyHidden = false;

        rows.forEach(row => {
            if(row.classList.contains('d-none')) {
                row.classList.remove('d-none');
                isCurrentlyHidden = true; 
            } else {
                row.classList.add('d-none');
            }
        });

        if(isCurrentlyHidden) {
            btn.innerHTML = 'View Less &uarr;';
        } else {
            const total = document.querySelectorAll('#tab-' + roleName + ' tbody tr').length;
            btn.innerHTML = 'View All (' + total + ') &rarr;';
        }
    }

    // Form Interactions & Validation
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('role');
        const memberFields = document.getElementById('member-fields');
        
        // Hide/Show Department field smoothly
        roleSelect.addEventListener('change', () => {
            if(roleSelect.value === 'member') {
                memberFields.style.display = 'block';
                memberFields.style.opacity = 0;
                setTimeout(() => memberFields.style.opacity = 1, 50);
            } else {
                memberFields.style.display = 'none';
            }
        });

        // AJAX Duplicate Checks
        const userIdInput = document.getElementById('new_user_id');
        const emailInput = document.getElementById('new_email');
        const submitBtn = document.getElementById('submit_btn');
        const userFeedback = document.getElementById('user_id_feedback');
        const emailFeedback = document.getElementById('email_feedback');
        
        let userIdValid = false; 
        let emailValid = true; // Optional field, default valid

        function updateSubmitButton() {
            if (userIdValid && emailValid && userIdInput.value.trim() !== "") {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        function checkDuplicate(field, value, feedbackElement, inputElement, validationFlagSetter) {
            if (!value.trim()) {
                inputElement.classList.remove('is-invalid', 'is-valid');
                inputElement.style.borderColor = '#e2e8f0';
                feedbackElement.style.display = 'none';
                validationFlagSetter(true);
                updateSubmitButton();
                return;
            }

            const formData = new FormData();
            formData.append('action', 'check_duplicate');
            formData.append('field', field);
            formData.append('value', value);

            fetch('user_management.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.exists) { 
                    inputElement.classList.add('is-invalid'); 
                    inputElement.classList.remove('is-valid'); 
                    inputElement.style.borderColor = '#e74a3b';
                    feedbackElement.style.display = 'block'; 
                    validationFlagSetter(false);
                } else { 
                    inputElement.classList.remove('is-invalid'); 
                    inputElement.classList.add('is-valid'); 
                    inputElement.style.borderColor = '#1cc88a';
                    feedbackElement.style.display = 'none'; 
                    validationFlagSetter(true);
                }
                updateSubmitButton();
            });
        }

        let timeoutIdUser, timeoutIdEmail;

        if (userIdInput) {
            userIdInput.addEventListener('input', function() {
                this.classList.remove('is-valid');
                this.style.borderColor = '#e2e8f0';
                userIdValid = false;
                updateSubmitButton();
                clearTimeout(timeoutIdUser);
                timeoutIdUser = setTimeout(() => {
                    checkDuplicate('user_id', this.value, userFeedback, userIdInput, (val) => userIdValid = val);
                }, 500);
            });
        }

        if (emailInput) {
            emailInput.addEventListener('input', function() {
                this.classList.remove('is-valid');
                this.style.borderColor = '#e2e8f0';
                emailValid = false;
                updateSubmitButton();
                clearTimeout(timeoutIdEmail);
                timeoutIdEmail = setTimeout(() => {
                    checkDuplicate('email', this.value, emailFeedback, emailInput, (val) => emailValid = val);
                }, 500);
            });
        }

        // Trigger background email queue processor 1 second after page load
        setTimeout(() => {
            fetch('../includes/process_email_queue.php').catch(e => console.error("Queue process background check failed:", e));
        }, 1000);
    });
</script>

<?php include 'footer.php'; ?>