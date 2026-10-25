<?php
ob_start(); // Start output buffering for PRG Pattern
include 'header.php';
require_once '../includes/db.php';

$user_id = $_SESSION['id'];

// PRG Check for flash messages
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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $email = trim($_POST['email']);
        
        $sql = "UPDATE canteen_staff SET email = ? WHERE id = ?";
        if ($pdo->prepare($sql)->execute([$email, $user_id])) {
            $_SESSION['flash_success'] = "Email updated successfully!";
        } else {
            $_SESSION['flash_error'] = "Failed to update email.";
        }
    }

    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Get current password (plain text as in original)
        $stmt_pass = $pdo->prepare("SELECT password FROM canteen_staff WHERE id = ?");
        $stmt_pass->execute([$user_id]);
        $stored_password = $stmt_pass->fetchColumn();

        // Check plain text match
        if ($current_password === $stored_password) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $new_plain_password = $new_password; // Plain text
                    $sql_pass = "UPDATE canteen_staff SET password = ? WHERE id = ?";
                    if ($pdo->prepare($sql_pass)->execute([$new_plain_password, $user_id])) {
                        $_SESSION['flash_success'] = "Password changed successfully!";
                    } else {
                        $_SESSION['flash_error'] = "Failed to change password.";
                    }
                } else {
                    $_SESSION['flash_error'] = "Password must be at least 6 characters long.";
                }
            } else {
                $_SESSION['flash_error'] = "New passwords do not match.";
            }
        } else {
            $_SESSION['flash_error'] = "Incorrect current password.";
        }
    }
    
    // Redirect to apply PRG
    header("Location: profile.php");
    exit;
}

// Fetch current user data (querying canteen_staff)
$stmt = $pdo->prepare("SELECT email, staff_id FROM canteen_staff WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

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
    
    /* Toast Notifications */
    #toast-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1060; pointer-events: none; width: 90%; max-width: 350px;}
    .custom-toast { color: white; border-radius: 50px; padding: 12px 25px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; font-weight: 500; opacity: 0; transform: translateY(-20px); transition: all 0.3s ease; backdrop-filter: blur(5px); text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2);}
    .custom-toast.show { opacity: 1; transform: translateY(0); }
    .toast-success { background: rgba(40, 167, 69, 0.9); }
    .toast-error { background: rgba(220, 53, 69, 0.9); }
</style>

<div id="toast-container"></div>

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
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="profile-card h-100">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h4 class="mb-1">Staff Member</h4>
                    <p class="mb-0 text-light"><i class="fas fa-id-badge mr-1"></i> <?php echo htmlspecialchars($user['staff_id']); ?></p>
                </div>
                <div class="p-4">
                    <div class="info-label">Account Role</div>
                    <div class="info-value">
                        <span class="badge badge-info px-3 py-2 rounded-pill"><i class="fas fa-user-tag mr-1"></i> Staff</span>
                    </div>

                    <div class="info-label mt-4">Email Address</div>
                    <div class="info-value"><i class="fas fa-envelope mr-2 text-primary"></i><?php echo htmlspecialchars($user['email'] ?: 'N/A'); ?></div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="profile-card p-4 mb-4">
                <h5 class="card-title-modern text-primary"><i class="fas fa-id-card mr-2"></i> Update Profile Information</h5>
                <form action="profile.php" method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold text-muted">Staff ID</label>
                            <input type="text" class="form-control form-control-modern bg-light" value="<?php echo htmlspecialchars($user['staff_id']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold text-muted">Email Address</label>
                            <input type="email" class="form-control form-control-modern" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    <div class="text-right mt-2">
                        <button type="submit" name="update_profile" class="btn btn-primary-modern">
                            <i class="fas fa-save mr-1"></i> Update Email
                        </button>
                    </div>
                </form>
            </div>

            <div class="profile-card p-4">
                <h5 class="card-title-modern text-warning"><i class="fas fa-lock mr-2"></i> Change Password</h5>
                <form action="profile.php" method="post" id="passwordForm">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold text-muted">Current Password</label>
                            <input type="password" class="form-control form-control-modern" name="current_password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold text-muted">New Password</label>
                            <input type="password" class="form-control form-control-modern" name="new_password" id="new_password" minlength="6" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold text-muted">Confirm New Password</label>
                            <input type="password" class="form-control form-control-modern" name="confirm_password" id="confirm_password" required>
                            <small id="passwordError" class="text-danger mt-1 d-none">Passwords do not match!</small>
                        </div>
                    </div>
                    <div class="text-right mt-2">
                        <button type="submit" name="change_password" class="btn btn-primary-modern bg-warning text-dark border-0">
                            <i class="fas fa-key mr-1"></i> Change Password
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
    }, 400);

    // Toast Notification System
    function showToast(message, type) {
        const container = document.getElementById('toast-container');
        if(!container) return;
        const toast = document.createElement('div');
        toast.className = `custom-toast ${type === 'success' ? 'toast-success' : 'toast-error'} shadow-lg mx-auto`;
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i> ${message}`;
        container.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    <?php if($flash_success): ?>
        showToast(<?php echo json_encode($flash_success); ?>, 'success');
    <?php endif; ?>

    <?php if($flash_error): ?>
        showToast(<?php echo json_encode($flash_error); ?>, 'error');
    <?php endif; ?>

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
});
</script>

<?php 
ob_end_flush();
include 'footer.php'; 
?>