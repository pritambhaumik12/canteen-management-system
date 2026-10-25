<?php
session_start();
require_once '../includes/db.php';

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}

$login_err = isset($_SESSION['login_err']) ? $_SESSION['login_err'] : '';
unset($_SESSION['login_err']);

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $user_id = trim($_POST["user_id"]);
    $password = trim($_POST["password"]);
    
    if(empty($user_id) || empty($password)){
        $_SESSION['login_err'] = "Please enter both Admin ID and password.";
        header("location: login.php");
        exit;
    }
    
    $sql = "SELECT id, admin_id, password, status FROM super_admins WHERE admin_id = :user_id";
    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_STR);
        if($stmt->execute() && $stmt->rowCount() == 1){
            if($row = $stmt->fetch()){
                if($row["status"] == 'inactive'){
                    $_SESSION['login_err'] = "Account inactive. Please contact system owner.";
                } elseif($password === $row["password"]){
                    $session_token = bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE super_admins SET session_token = ?, last_login = NOW() WHERE id = ?")->execute([$session_token, $row['id']]);

                    session_unset(); session_destroy(); session_start();
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $row["id"];
                    $_SESSION["user_id"] = $user_id;
                    $_SESSION["role"] = "super_admin";
                    $_SESSION["session_token"] = $session_token;
                    
                    header("location: dashboard.php");
                    exit;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login</title>
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
                <div class="mb-3"><i class="fas fa-user-shield fa-3x"></i></div>
                <h4 class="mb-0 font-weight-bold">Super Admin Access</h4>
            </div>
            <div class="login-body p-4 p-md-5">
                <?php if(!empty($login_err)): ?><div class="alert alert-danger text-center small font-weight-bold shadow-sm rounded-lg"><?php echo $login_err; ?></div><?php endif; ?>
                
                <form action="login.php" method="post">
                    <div class="form-group mb-4">
                        <label class="form-label font-weight-bold">Admin ID</label>
                        <input type="text" name="user_id" class="form-control rounded-pill px-4" placeholder="Enter Admin ID" required>
                    </div>    
                    <div class="form-group mb-2">
                        <label class="form-label font-weight-bold">Password</label>
                        <input type="password" name="password" class="form-control rounded-pill px-4" placeholder="Enter your password" required>
                    </div>
                    <div class="text-right mb-4">
                        <a href="forgot_password.php" class="small text-danger font-weight-bold">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn btn-danger btn-block rounded-pill py-2 shadow-sm font-weight-bold">Sign In <i class="fas fa-arrow-right ml-2"></i></button>
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
    </script>
</body>
</html>