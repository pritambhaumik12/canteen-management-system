<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';

$baseUrl = "http://" . $_SERVER['HTTP_HOST'] . "/canteen"; 

$step = isset($_SESSION['fp_step']) ? $_SESSION['fp_step'] : 1;
$error = isset($_SESSION['fp_error']) ? $_SESSION['fp_error'] : '';
$details = isset($_SESSION['fp_details']) ? $_SESSION['fp_details'] : null;

unset($_SESSION['fp_error']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'find') {
        $user_id = trim($_POST['user_id']);
        
        $stmt = $pdo->prepare("SELECT id, manager_id as user_id, name, email FROM managers WHERE manager_id = ? AND status = 'active'");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (empty($user['email'])) {
                $_SESSION['fp_error'] = "No email address linked to this account. Contact admin.";
            } else {
                $_SESSION['fp_step'] = 2;
                $_SESSION['fp_details'] = $user;
            }
        } else {
            $_SESSION['fp_error'] = "Active Manager ID not found.";
        }
        header("Location: forgot_password.php");
        exit;
    } 
    elseif (isset($_POST['action']) && $_POST['action'] == 'send_link') {
        $user_id = $_POST['user_id'];
        $email = $_POST['email'];
        $name = $_POST['name'];
        
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $stmt = $pdo->prepare("UPDATE managers SET reset_token = ?, reset_expires = ? WHERE manager_id = ?");
        if ($stmt->execute([$token, $expiry, $user_id])) {
            
            $settings = [];
            $setStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $setStmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $inst_name = $settings['institution_name'] ?? 'Institution';
            $canteen_name = $settings['canteen_name'] ?? 'Canteen';
            $sender_name = $canteen_name . ' - ' . $inst_name;
            
            $inst_logo_url = $baseUrl . "/assets/images/logos/" . ($settings['institution_logo'] ?? 'default_inst.png');
            
            $reset_link = $baseUrl . "/manager/reset_password.php?token=" . $token;
            $subject = "Password Reset Request - " . $sender_name;
            
            $htmlBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                <div style='background: linear-gradient(135deg, #ffc107, #e0a800); padding: 30px 20px; text-align: center; color: white;'>
                    <img src='{$inst_logo_url}' alt='Logo' style='max-height: 60px; margin-bottom: 10px; background: white; padding: 5px; border-radius: 5px;'>
                    <h2 style='margin: 0; font-size: 24px; color: #333;'>{$sender_name}</h2>
                </div>
                <div style='padding: 30px; background-color: #ffffff; text-align: center;'>
                    <h3 style='color: #333; margin-top: 0;'>Secure Password Reset</h3>
                    <p style='color: #555; font-size: 16px; text-align: left;'>Hello <strong>{$name}</strong> (Role: Manager),</p>
                    <p style='color: #555; font-size: 15px; text-align: left; line-height: 1.5;'>We received a request to reset your portal password. For security reasons, this link will expire in <strong>15 minutes</strong>.</p>
                    
                    <div style='margin: 35px 0;'>
                        <a href='{$reset_link}' style='background-color: #ffc107; color: #333; padding: 14px 30px; text-decoration: none; font-weight: bold; border-radius: 30px; display: inline-block; font-size: 16px; box-shadow: 0 4px 6px rgba(255, 193, 7, 0.3);'>Reset My Password</a>
                    </div>
                    
                    <div style='text-align: left; background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;'>
                        <p style='color: #666; font-size: 14px; margin: 0 0 5px 0;'>Or copy and paste this link into your browser:</p>
                        <a href='{$reset_link}' style='word-break: break-all; font-size: 13px; color: #e0a800;'>{$reset_link}</a>
                    </div>
                </div>
                <div style='background-color: #f1f1f1; padding: 15px; text-align: center; border-top: 1px solid #ddd;'>
                    <p style='margin: 0; color: #888; font-size: 13px;'>If you did not request this, please safely ignore this email.</p>
                    <p style='margin: 10px 0 0 0; color: #555; font-size: 12px; font-weight: bold;'>Powered by Tech Innavo</p>
                </div>
            </div>";
            
            if (sendMail($email, $name, $subject, $htmlBody, $sender_name)) {
                $_SESSION['fp_step'] = 3;
                unset($_SESSION['fp_details']);
            } else {
                $_SESSION['fp_error'] = "Failed to send email. Please try again later.";
            }
        } else {
            $_SESSION['fp_error'] = "Database error. Please try again.";
        }
        header("Location: forgot_password.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Forgot Password</title>
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
        .skeleton-box { width: 100%; max-width: 450px; height: 400px; background: rgba(255,255,255,0.1); border-radius: 20px; position: relative; overflow: hidden; }
        .skeleton-box::after { content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 100% { left: 200%; } }
    </style>
</head>
<body class="login-page">
    <div class="loading-overlay" id="loading-overlay"><div class="skeleton-box shadow-lg"></div></div>
    <div class="container d-flex justify-content-center align-items-center min-vh-100 skeleton-container">
        <div class="login-card shadow-lg border-0 w-100" style="max-width: 450px;">
            <div class="card-header bg-gradient-warning text-white text-center py-4 border-0">
                <div class="mb-3"><i class="fas fa-key fa-3x"></i></div>
                <h4 class="mb-0 font-weight-bold">Manager Reset Password</h4>
            </div>
            <div class="login-body p-4 p-md-5">
                <?php if(!empty($error)): ?><div class="alert alert-danger text-center small"><?php echo $error; ?></div><?php endif; ?>
                
                <?php if($step == 1): ?>
                    <form action="forgot_password.php" method="post">
                        <input type="hidden" name="action" value="find">
                        <p class="text-muted text-center mb-4">Enter your Manager ID to locate your account.</p>
                        <div class="form-group mb-4">
                            <label class="form-label font-weight-bold">Manager ID</label>
                            <input type="text" name="user_id" class="form-control rounded-pill px-4" required>
                        </div>
                        <button type="submit" class="btn btn-warning btn-block rounded-pill py-2 shadow-sm font-weight-bold text-white"><i class="fas fa-search"></i> Find Account</button>
                    </form>
                    
                <?php elseif($step == 2 && $details): ?>
                    <form action="forgot_password.php" method="post">
                        <input type="hidden" name="action" value="send_link">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($details['user_id']); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($details['email']); ?>">
                        <input type="hidden" name="name" value="<?php echo htmlspecialchars($details['name']); ?>">
                        
                        <div class="alert alert-warning border-0 rounded">
                            <h5 class="font-weight-bold border-bottom pb-2 text-dark"><i class="fas fa-user-circle"></i> Account Found</h5>
                            <ul class="list-unstyled mb-0 mt-3 text-dark">
                                <li class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($details['name']); ?></li>
                                <li class="mb-2"><strong>ID:</strong> <?php echo htmlspecialchars($details['user_id']); ?></li>
                                <li class="mb-2"><strong>Role:</strong> Manager</li>
                                <li><strong>Email:</strong> <?php echo htmlspecialchars(substr_replace($details['email'], '***', 3, strpos($details['email'], '@') - 3)); ?></li>
                            </ul>
                        </div>
                        <p class="small text-muted text-center">A reset link valid for 15 minutes will be sent to your registered email address.</p>
                        <button type="submit" class="btn btn-success btn-block rounded-pill py-2 shadow-sm font-weight-bold"><i class="fas fa-paper-plane"></i> Send Link</button>
                    </form>
                    
                <?php elseif($step == 3): ?>
                    <div class="text-center">
                        <div class="text-success mb-3"><i class="fas fa-check-circle fa-4x"></i></div>
                        <h5 class="font-weight-bold text-success">Email Sent Successfully!</h5>
                        <p class="text-muted mt-3">Please check your inbox (and spam folder) for the password reset link.</p>
                        <?php unset($_SESSION['fp_step']); ?>
                    </div>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="login.php" class="text-muted small"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
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