<?php
session_start();
require_once '../includes/db.php';

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}

$login_err = isset($_SESSION['login_err']) ? $_SESSION['login_err'] : '';
unset($_SESSION['login_err']);
$login_msg = isset($_SESSION['login_msg']) ? $_SESSION['login_msg'] : '';
unset($_SESSION['login_msg']);
$login_msg_type = isset($_SESSION['login_msg_type']) ? $_SESSION['login_msg_type'] : 'success';
unset($_SESSION['login_msg_type']);

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $user_id = trim($_POST["user_id"]);
    $password = trim($_POST["password"]);
    
    if(empty($user_id) || empty($password)){
        $_SESSION['login_err'] = "Please enter both Member ID and password.";
        header("location: login.php");
        exit;
    }
    
    $sql = "SELECT id, member_id, password, status, email, name, last_login FROM members WHERE member_id = :user_id";
    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_STR);
        if($stmt->execute() && $stmt->rowCount() == 1){
            if($row = $stmt->fetch()){
                if($password === $row["password"]){
                    
                    if($row["status"] == 'inactive'){
                        if(empty($row['last_login'])){
                            if(!empty($row['email'])){
                                $token = bin2hex(random_bytes(32));
                                $expiry = date('Y-m-d H:i:s', time() + 3600); 
                                
                                $pdo->prepare("UPDATE members SET reset_token = ?, reset_expires = ? WHERE id = ?")->execute([$token, $expiry, $row['id']]);
                                
                                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                $domain = $_SERVER['HTTP_HOST'];
                                $base_dir = dirname(dirname($_SERVER['PHP_SELF']));
                                $activation_link = $protocol . "://" . $domain . $base_dir . "/member/activate.php?token=" . $token;
                                
                                $subject = "Activate Your Account 🚀";
                                $html_body = "
                                <div style='font-family: \"Segoe UI\", Roboto, sans-serif; max-width: 600px; margin: auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #f8fafc; text-align: center;'>
                                    <div style='background-color: #ffffff; padding: 40px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                                        <h2 style='color: #0f172a; margin-top: 0;'>Welcome, " . htmlspecialchars($row['name']) . "!</h2>
                                        <p style='color: #475569; font-size: 16px; line-height: 1.6;'>This is your first time logging in. To secure your account and access the canteen portal, please activate your account and set a new password by clicking the button below:</p>
                                        <div style='margin: 35px 0;'>
                                            <a href='" . htmlspecialchars($activation_link) . "' style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 14px 30px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); display: inline-block;'>Activate Account &rarr;</a>
                                        </div>
                                        <p style='color: #94a3b8; font-size: 13px;'>This link is valid for 1 hour. If you did not request this, please contact the administrator.</p>
                                    </div>
                                </div>";
                                
                                $stmt = $pdo->prepare("INSERT INTO email_queue (recipient_email, recipient_name, subject, html_body, sender_name, status) VALUES (?, ?, ?, ?, 'Canteen Manager', 'pending')");
                                $stmt->execute([$row['email'], $row['name'], $subject, $html_body]);
                                
                                $_SESSION['login_msg'] = "This is your first login! An activation link has been sent to your registered email address. Please check your inbox.";
                                $_SESSION['login_msg_type'] = "info";
                            } else {
                                $_SESSION['login_err'] = "This is your first login, but no email is registered. Please contact the administrator to activate your account.";
                            }
                        } else {
                            $_SESSION['login_err'] = "Your account is currently inactive. Please contact admin.";
                        }
                    } else {
                        $session_token = bin2hex(random_bytes(32));
                        $pdo->prepare("UPDATE members SET session_token = ?, last_login = NOW() WHERE id = ?")->execute([$session_token, $row['id']]);

                        session_unset(); session_destroy(); session_start();
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $row["id"];
                        $_SESSION["user_id"] = $user_id;
                        $_SESSION["role"] = "member";
                        $_SESSION["session_token"] = $session_token;
                        
                        header("location: dashboard.php");
                        exit;
                    }
                } else {
                    $_SESSION['login_err'] = "Invalid ID or password.";
                }
            }
        } else {
            $_SESSION['login_err'] = "Invalid ID or password.";
        }
    }
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Member Login</title>
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
    
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="login-card shadow-lg border-0 w-100" style="max-width: 420px;">
            <div class="card-header bg-gradient-success text-white text-center py-4 border-0">
                <div class="mb-3"><i class="fas fa-user-graduate fa-3x"></i></div>
                <h4 class="mb-0 font-weight-bold">Member Access</h4>
            </div>
            <div class="login-body p-4 p-md-5">
                
                <?php if(!empty($login_err)): ?>
                    <div class="alert alert-danger text-center small font-weight-bold shadow-sm rounded-lg"><?php echo $login_err; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($login_msg)): ?>
                    <div class="alert alert-<?php echo $login_msg_type; ?> text-center small font-weight-bold shadow-sm rounded-lg">
                        <?php if($login_msg_type == 'info'): ?><i class="fas fa-envelope-open-text mr-1"></i><?php endif; ?>
                        <?php echo $login_msg; ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="post">
                    <div class="form-group mb-4">
                        <label class="form-label font-weight-bold">Member ID</label>
                        <input type="text" name="user_id" class="form-control rounded-pill px-4" placeholder="Enter your ID" required>
                    </div>    
                    <div class="form-group mb-4">
                        <label class="form-label font-weight-bold">Password</label>
                        <input type="password" name="password" class="form-control rounded-pill px-4" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn btn-success btn-block rounded-pill py-2 shadow-sm font-weight-bold">Sign In <i class="fas fa-arrow-right ml-2"></i></button>
                    <div class="text-center mt-3">
                        <a href="forgot_password.php" class="text-primary small font-weight-bold">Forgot Password?</a>
                    </div>
                    <div class="text-center mt-4">
                        <a href="../index.php" class="text-muted small"><i class="fas fa-arrow-left"></i> Back to Portal Selection</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
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

    <?php if(!empty($login_msg) && $login_msg_type == 'info'): ?>
        setTimeout(() => {
            fetch('../includes/process_email_queue.php').catch(e => console.error("Mail trigger failed:", e));
        }, 500);
    <?php endif; ?>
    </script>
</body>
</html>