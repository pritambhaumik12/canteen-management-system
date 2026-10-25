<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php'; 

$baseUrl = "http://" . $_SERVER['HTTP_HOST'] . "/canteen"; 

$step = 1; 
$error = '';
$member_details = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'find') {
        $member_id = trim($_POST['member_id']);
        
        $stmt = $pdo->prepare("SELECT id, member_id, name, department, email FROM members WHERE member_id = ? AND status = 'active'");
        $stmt->execute([$member_id]);
        
        if ($stmt->rowCount() == 1) {
            $member_details = $stmt->fetch(PDO::FETCH_ASSOC);
            if (empty($member_details['email'])) {
                $error = "No email address linked to this account. Contact admin.";
            } else {
                $step = 2; 
            }
        } else {
            $error = "Active Member ID not found.";
        }
    } 
    elseif (isset($_POST['action']) && $_POST['action'] == 'send_link') {
        $member_id = $_POST['member_id'];
        $email = $_POST['email'];
        $name = $_POST['name'];
        
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $stmt = $pdo->prepare("UPDATE members SET reset_token = ?, reset_expires = ? WHERE member_id = ?");
        if ($stmt->execute([$token, $expiry, $member_id])) {
            
            $settings = [];
            $setStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $setStmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $inst_name = $settings['institution_name'] ?? 'Institution';
            $canteen_name = $settings['canteen_name'] ?? 'Canteen';
            
            $inst_logo_url = $baseUrl . "/assets/images/logos/" . ($settings['institution_logo'] ?? 'default_inst.png');
            $canteen_logo_url = $baseUrl . "/assets/images/logos/" . ($settings['canteen_logo'] ?? 'default_canteen.png');
            
            $reset_link = $baseUrl . "/member/reset_password.php?token=" . $token;
            
            $subject = "Password Reset Request - " . $canteen_name;
            $htmlBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
                <div style='background: linear-gradient(135deg, #28a745, #218838); padding: 20px; text-align: center; color: white;'>
                    <img src='{$inst_logo_url}' alt='Institution Logo' style='max-height: 50px; margin-right: 15px; vertical-align: middle;'>
                    <img src='{$canteen_logo_url}' alt='Canteen Logo' style='max-height: 50px; vertical-align: middle;'>
                    <h2 style='margin: 15px 0 0 0;'>{$inst_name}</h2>
                    <p style='margin: 5px 0 0 0; font-size: 18px;'>{$canteen_name}</p>
                </div>
                <div style='padding: 30px; background-color: #f9f9f9;'>
                    <h3 style='color: #333;'>Password Reset Request</h3>
                    <p>Hello <strong>{$name}</strong> (ID: {$member_id}),</p>
                    <p>We received a request to reset your password. This link is valid for <strong>15 minutes</strong>.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$reset_link}' style='background-color: #28a745; color: white; padding: 12px 25px; text-decoration: none; font-weight: bold; border-radius: 25px; display: inline-block; font-size: 16px;'>Reset Password</a>
                    </div>
                    
                    <p style='color: #666; font-size: 14px;'>If the button doesn't work, copy and paste the following link into your browser:</p>
                    <p style='word-break: break-all; background: #e9ecef; padding: 10px; border-radius: 5px; font-size: 13px; color: #333;'><a href='{$reset_link}'>{$reset_link}</a></p>
                    
                    <p style='color: #999; font-size: 12px; margin-top: 30px;'>If you did not request this, please ignore this email.</p>
                </div>
            </div>";
            
            if (sendMail($email, $name, $subject, $htmlBody)) {
                $step = 3;
            } else {
                $error = "Failed to send email. Please try again later.";
                $step = 1;
            }
        } else {
            $error = "Database error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="login-card shadow-lg border-0 w-100" style="max-width: 450px;">
            <div class="card-header bg-gradient-success text-white text-center py-4 border-0">
                <div class="mb-3"><i class="fas fa-key fa-3x"></i></div>
                <h4 class="mb-0 font-weight-bold">Reset Password</h4>
            </div>
            <div class="login-body p-4 p-md-5">
                <?php if(!empty($error)): ?><div class="alert alert-danger text-center small"><?php echo $error; ?></div><?php endif; ?>
                
                <?php if($step == 1): ?>
                    <form action="" method="post">
                        <input type="hidden" name="action" value="find">
                        <p class="text-muted text-center mb-4">Enter your Member ID to locate your account.</p>
                        <div class="form-group mb-4">
                            <label class="form-label font-weight-bold">Member ID</label>
                            <input type="text" name="member_id" class="form-control rounded-pill px-4" placeholder="e.g. MEM001" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block rounded-pill py-2 shadow-sm font-weight-bold"><i class="fas fa-search"></i> Find Account</button>
                    </form>
                    
                <?php elseif($step == 2): ?>
                    <form action="" method="post">
                        <input type="hidden" name="action" value="send_link">
                        <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($member_details['member_id']); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($member_details['email']); ?>">
                        <input type="hidden" name="name" value="<?php echo htmlspecialchars($member_details['name']); ?>">
                        
                        <div class="alert alert-info border-0 rounded">
                            <h5 class="font-weight-bold border-bottom pb-2"><i class="fas fa-user-circle"></i> Account Found</h5>
                            <ul class="list-unstyled mb-0 mt-3">
                                <li class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($member_details['name']); ?></li>
                                <li class="mb-2"><strong>ID:</strong> <?php echo htmlspecialchars($member_details['member_id']); ?></li>
                                <li class="mb-2"><strong>Dept:</strong> <?php echo htmlspecialchars($member_details['department'] ?? 'N/A'); ?></li>
                                <li><strong>Email:</strong> <?php echo htmlspecialchars(substr_replace($member_details['email'], '***', 3, strpos($member_details['email'], '@') - 3)); ?></li>
                            </ul>
                        </div>
                        
                        <p class="small text-muted text-center">A reset link valid for 15 minutes will be sent to your registered email address.</p>
                        
                        <button type="submit" class="btn btn-success btn-block rounded-pill py-2 shadow-sm font-weight-bold"><i class="fas fa-paper-plane"></i> Get reset link</button>
                    </form>
                    
                <?php elseif($step == 3): ?>
                    <div class="text-center">
                        <div class="text-success mb-3"><i class="fas fa-check-circle fa-4x"></i></div>
                        <h5 class="font-weight-bold text-success">Email Sent Successfully!</h5>
                        <p class="text-muted mt-3">Please check your inbox (and spam folder) for the password reset link.</p>
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