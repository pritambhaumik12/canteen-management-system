<?php
session_start();
require_once '../includes/db.php';

$error = isset($_SESSION['rp_error']) ? $_SESSION['rp_error'] : '';
$success = isset($_SESSION['rp_success']) ? $_SESSION['rp_success'] : '';
unset($_SESSION['rp_error'], $_SESSION['rp_success']);

$valid_token = false;
$user_id = '';
$user_name = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("SELECT id, admin_id, name FROM super_admins WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    
    if ($stmt->rowCount() == 1) {
        $valid_token = true;
        $row = $stmt->fetch();
        $user_id = $row['admin_id'];
        $user_name = $row['name'];
    } else {
        $error = "Invalid or expired link. Please request a new one.";
    }
} else {
    $error = "No reset token provided.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 6) {
        $_SESSION['rp_error'] = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['rp_error'] = "Passwords do not match.";
    } else {
        $stmt = $pdo->prepare("UPDATE super_admins SET password = ?, reset_token = NULL, reset_expires = NULL WHERE admin_id = ?");
        if ($stmt->execute([$new_password, $user_id])) {
            $_SESSION['rp_success'] = "Password successfully reset! You can now log in.";
        } else {
            $_SESSION['rp_error'] = "Something went wrong. Please try again.";
        }
    }
    header("Location: reset_password.php?token=" . urlencode($token));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin New Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body.login-page { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); min-height: 100vh; display: flex; align-items: center; position: relative; z-index: 1; }
        body.login-page::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: url('../assets/images/logos/university.png'); background-size: cover; background-position: center; background-repeat: no-repeat; opacity: 0.15; z-index: -1; }
        .login-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 20px; overflow: hidden; transition: all 0.3s ease; }
        .login-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3) !important; }
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); z-index: 9999; display: flex; align-items: center; justify-content: center; transition: opacity 0.6s ease, visibility 0.6s ease; }
        .loading-overlay.hidden { opacity: 0; visibility: hidden; }
        .skeleton-box { width: 100%; max-width: 420px; height: 400px; background: rgba(255,255,255,0.1); border-radius: 20px; position: relative; overflow: hidden; }
        .skeleton-box::after { content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 100% { left: 200%; } }
    </style>
</head>
<body class="login-page">
    <div class="loading-overlay" id="loading-overlay"><div class="skeleton-box shadow-lg"></div></div>
    <div class="container d-flex justify-content-center align-items-center min-vh-100 skeleton-container">
        <div class="login-card shadow-lg border-0 w-100" style="max-width: 420px;">
            <div class="card-header bg-gradient-danger text-white text-center py-4 border-0">
                <div class="mb-3"><i class="fas fa-unlock-alt fa-3x"></i></div>
                <h4 class="mb-0 font-weight-bold">New Admin Password</h4>
            </div>
            <div class="login-body p-4 p-md-5">
                
                <?php if(!empty($error)): ?><div class="alert alert-danger text-center small"><?php echo $error; ?></div><?php endif; ?>
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success text-center small">
                        <?php echo $success; ?>
                        <br><br>
                        <a href="login.php" class="btn btn-sm btn-danger rounded-pill">Go to Login</a>
                    </div>
                <?php endif; ?>

                <?php if($valid_token && empty($success)): ?>
                    <div class="text-center mb-4">
                        <span class="badge badge-danger p-2 w-100 mb-2" style="font-size:14px;">Role: Super Admin</span>
                        <h6 class="font-weight-bold text-dark"><?php echo htmlspecialchars($user_name); ?></h6>
                        <small class="text-muted">ID: <?php echo htmlspecialchars($user_id); ?></small>
                    </div>
                    <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
                        <div class="form-group mb-3">
                            <label class="form-label font-weight-bold">New Password</label>
                            <input type="password" name="new_password" class="form-control rounded-pill px-4" required minlength="6">
                        </div>
                        <div class="form-group mb-4">
                            <label class="form-label font-weight-bold">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control rounded-pill px-4" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-danger btn-block rounded-pill py-2 shadow-sm font-weight-bold"><i class="fas fa-save"></i> Save Password</button>
                    </form>
                <?php elseif(empty($success)): ?>
                    <div class="text-center mt-3">
                        <a href="forgot_password.php" class="btn btn-outline-danger rounded-pill">Request New Link</a>
                    </div>
                <?php endif; ?>
                
                <?php if(empty($success)): ?>
                <div class="text-center mt-4">
                    <a href="login.php" class="text-muted small"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        window.addEventListener('load', function() {
            setTimeout(() => {
                const loadingOverlay = document.getElementById('loading-overlay');
                if (loadingOverlay) {
                    loadingOverlay.classList.add('hidden');
                    setTimeout(() => { loadingOverlay.style.display = 'none'; }, 600);
                }
            }, 300);
        });
    </script>
</body>
</html>