<?php
// Fix: Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and is a super admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'super_admin') {
    header("location: ../index.php");
    exit;
}

require_once '../includes/db.php';

$admin_id_session = $_SESSION['id'];
$success_msg = '';
$error_msg = '';

// Retrieve session messages for PRG pattern
if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Handle Form Submission (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic Validation
    if (empty($name) || empty($email)) {
        $_SESSION['error_msg'] = "Name and Email are required.";
        header("Location: profile.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_msg'] = "Invalid email format.";
        header("Location: profile.php");
        exit;
    }

    // Check if email already exists for another super admin
    $stmt_check = $pdo->prepare("SELECT id FROM super_admins WHERE email = ? AND id != ?");
    $stmt_check->execute([$email, $admin_id_session]);
    if ($stmt_check->rowCount() > 0) {
        $_SESSION['error_msg'] = "This email is already in use by another admin.";
        header("Location: profile.php");
        exit;
    }

    // Prepare Update Query
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $_SESSION['error_msg'] = "New passwords do not match.";
            header("Location: profile.php");
            exit;
        }
        
        // Note: If your system uses password_hash() for login, wrap $new_password in password_hash($new_password, PASSWORD_DEFAULT). 
        // Based on the SQL dump, passwords seem to be plain text. If so, save it directly. 
        // We recommend upgrading to password_hash() in the future.
        $stmt = $pdo->prepare("UPDATE super_admins SET name = ?, email = ?, password = ? WHERE id = ?");
        $result = $stmt->execute([$name, $email, $new_password, $admin_id_session]);
    } else {
        $stmt = $pdo->prepare("UPDATE super_admins SET name = ?, email = ? WHERE id = ?");
        $result = $stmt->execute([$name, $email, $admin_id_session]);
    }

    if ($result) {
        // Update session name if changed
        $_SESSION['name'] = $name;
        $_SESSION['success_msg'] = "Profile updated successfully!";
    } else {
        $_SESSION['error_msg'] = "Something went wrong. Please try again.";
    }

    // Redirect to implement PRG pattern
    header("Location: profile.php");
    exit;
}

// Fetch current user details (GET)
$stmt = $pdo->prepare("SELECT admin_id, name, email FROM super_admins WHERE id = ?");
$stmt->execute([$admin_id_session]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("Admin record not found.");
}

include 'header.php';
?>

<style>
    .skeleton-wrapper {
        display: block;
    }
    .skeleton {
        background: #eee;
        background: linear-gradient(110deg, #ececec 8%, #f5f5f5 18%, #ececec 33%);
        border-radius: 5px;
        background-size: 200% 100%;
        animation: 1.5s shine linear infinite;
    }
    @keyframes shine {
        to { background-position-x: -200%; }
    }
    .skeleton-card { height: 400px; width: 100%; border-radius: 15px; margin-bottom: 20px;}
    .skeleton-title { height: 30px; width: 40%; margin-bottom: 20px; border-radius: 5px;}
    
    .real-content {
        display: none; /* Hidden until loaded */
    }

    /* Custom Form Styles */
    .profile-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        background: #ffffff;
    }
    .profile-header {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        border-radius: 15px 15px 0 0;
        padding: 20px;
        font-weight: 600;
    }
    .form-control:focus {
        border-color: #2a5298;
        box-shadow: 0 0 0 0.2rem rgba(42, 82, 152, 0.25);
    }
    .btn-update {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        border: none;
        padding: 10px 30px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .btn-update:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(42, 82, 152, 0.4);
        color: white;
    }
</style>

<div class="container-fluid mt-4">
    <div class="skeleton-wrapper" id="skeleton-loader">
        <div class="skeleton skeleton-title"></div>
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="skeleton skeleton-card"></div>
            </div>
        </div>
    </div>

    <div class="real-content" id="real-content">
        <h3 class="mb-4 text-dark font-weight-bold"><i class="fas fa-user-circle mr-2 text-primary"></i>My Profile</h3>

        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                
                <?php if (!empty($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                        <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success_msg); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error_msg); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="card profile-card">
                    <div class="profile-header d-flex align-items-center">
                        <i class="fas fa-user-shield fa-2x mr-3"></i>
                        <h5 class="mb-0">Edit Account Details</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="profile.php" method="POST" id="profileForm">
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="font-weight-bold text-muted">Admin ID</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light border-right-0"><i class="fas fa-id-badge text-muted"></i></span>
                                        </div>
                                        <input type="text" class="form-control border-left-0 bg-light" value="<?php echo htmlspecialchars($admin['admin_id']); ?>" readonly>
                                    </div>
                                    <small class="text-muted">Admin ID cannot be changed.</small>
                                </div>
                                
                                <div class="form-group col-md-6">
                                    <label for="name" class="font-weight-bold text-muted">Full Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-user text-primary"></i></span>
                                        </div>
                                        <input type="text" class="form-control border-left-0" id="name" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="email" class="font-weight-bold text-muted">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white border-right-0"><i class="fas fa-envelope text-primary"></i></span>
                                    </div>
                                    <input type="email" class="form-control border-left-0" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="text-muted font-weight-bold mb-3"><i class="fas fa-lock mr-2"></i>Change Password (Optional)</h6>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="new_password" class="text-muted">New Password</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-key text-secondary"></i></span>
                                        </div>
                                        <input type="password" class="form-control border-left-0" id="new_password" name="new_password" placeholder="Leave blank to keep current">
                                    </div>
                                </div>
                                
                                <div class="form-group col-md-6">
                                    <label for="confirm_password" class="text-muted">Confirm New Password</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-check-double text-secondary"></i></span>
                                        </div>
                                        <input type="password" class="form-control border-left-0" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group text-right mt-4 mb-0">
                                <button type="submit" class="btn btn-update">
                                    <i class="fas fa-save mr-2"></i> Save Changes
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
    // Skeleton Loader Logic
    document.addEventListener("DOMContentLoaded", function() {
        // Simulate a slight delay to show skeleton (optional, can be removed in production)
        setTimeout(function() {
            document.getElementById('skeleton-loader').style.display = 'none';
            document.getElementById('real-content').style.display = 'block';
        }, 400); // 400ms delay for visual feedback
    });

    // Frontend Password Match Validation
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        var newPass = document.getElementById('new_password').value;
        var confirmPass = document.getElementById('confirm_password').value;

        if (newPass !== '' && newPass !== confirmPass) {
            e.preventDefault();
            alert('Error: New Password and Confirm Password do not match!');
        }
    });
</script>

<?php
// Note: Ensure footer.php does not close the container div prematurely if it's meant to wrap
// We assume footer.php just closes body and html or provides script tags.
include 'footer.php';
?>