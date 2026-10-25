<?php
session_start();
require_once '../includes/db.php';

$token = $_GET['token'] ?? '';
$error = '';
$form_err = '';

if (empty($token)) {
    die("Invalid access.");
}

// Verify Token and Fetch Extended User Details
$sql = "SELECT id, name, staff_id FROM canteen_staff WHERE reset_token = :token AND reset_expires > NOW() AND status = 'inactive'";
$stmt = $pdo->prepare($sql);
$stmt->execute([':token' => $token]);

if ($stmt->rowCount() == 0) {
    $error = "This activation link is invalid, has expired, or the account is already active. Please try logging in again to request a new one.";
} else {
    $user = $stmt->fetch();
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Standard Password Rules: Min 8 chars, 1 Uppercase, 1 Lowercase, 1 Number, 1 Special Character
        $password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
        
        if ($new_password !== $confirm_password) {
            $form_err = "Passwords do not match.";
        } elseif (!preg_match($password_pattern, $new_password)) {
            $form_err = "Password does not meet the security requirements.";
        } else {
            // Update password, activate the account, and clear tokens
            $update_sql = "UPDATE canteen_staff SET password = :password, status = 'active', last_login = NOW(), reset_token = NULL, reset_expires = NULL WHERE id = :id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                ':password' => $new_password,
                ':id' => $user['id']
            ]);
            
            $_SESSION['login_msg'] = "Staff account activated successfully! You can now log in with your new password.";
            $_SESSION['login_msg_type'] = "success";
            header("Location: login.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate Staff Account</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .rule-list { font-size: 0.8rem; padding-left: 1.2rem; margin-bottom: 0; color: #6c757d; }
        .rule-valid { color: #28a745; transition: color 0.3s; }
        .rule-invalid { color: #dc3545; transition: color 0.3s; }
    </style>
</head>
<body class="login-page">
    <div class="container d-flex justify-content-center align-items-center min-vh-100 py-5">
        <div class="login-card shadow-lg border-0 w-100" style="max-width: 480px;">
            <div class="card-header bg-gradient-info text-white text-center py-4">
                <div class="mb-3"><i class="fas fa-user-cog fa-3x"></i></div>
                <h4 class="mb-0 font-weight-bold">Secure Staff Account</h4>
            </div>
            <div class="login-body p-4 p-md-5">
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger text-center shadow-sm rounded-lg">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                        <?php echo $error; ?>
                    </div>
                    <div class="text-center mt-4">
                        <a href="login.php" class="btn btn-outline-info rounded-pill px-4">Back to Login</a>
                    </div>
                <?php else: ?>
                    
                    <div class="bg-light rounded-lg p-3 mb-4 border shadow-sm">
                        <h6 class="font-weight-bold text-info border-bottom pb-2 mb-3"><i class="fas fa-id-badge mr-2"></i>Staff Details</h6>
                        <div class="row text-sm">
                            <div class="col-12 mb-2">
                                <span class="text-muted d-block" style="font-size: 0.75rem; text-transform: uppercase;">Name</span>
                                <strong class="text-dark"><?php echo htmlspecialchars($user['name']); ?></strong>
                            </div>
                            <div class="col-12 mb-0">
                                <span class="text-muted d-block" style="font-size: 0.75rem; text-transform: uppercase;">Staff ID</span>
                                <strong class="text-dark font-family-monospace"><?php echo htmlspecialchars($user['staff_id']); ?></strong>
                            </div>
                        </div>
                    </div>

                    <?php if(!empty($form_err)): ?>
                        <div class="alert alert-danger text-center small font-weight-bold shadow-sm rounded-lg"><i class="fas fa-times-circle mr-1"></i><?php echo $form_err; ?></div>
                    <?php endif; ?>

                    <form action="activate.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
                        
                        <div class="form-group mb-2">
                            <label class="form-label font-weight-bold">New Password</label>
                            <div class="input-group shadow-sm rounded-pill">
                                <input type="password" id="new_password" name="new_password" class="form-control px-4 border-right-0" style="border-radius: 50px 0 0 50px;" placeholder="Create new password" required>
                                <div class="input-group-append">
                                    <span class="input-group-text bg-white border-left-0 text-muted" style="border-radius: 0 50px 50px 0; cursor: pointer;" onclick="togglePassword('new_password', this)">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="px-2 mb-3">
                            <div class="progress mt-2 shadow-sm" style="height: 6px; border-radius: 10px;">
                                <div id="strength-bar" class="progress-bar" role="progressbar" style="width: 0%; transition: width 0.4s ease, background-color 0.4s ease;"></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <small id="strength-text" class="text-muted font-weight-bold" style="font-size: 0.75rem;">Password Strength</small>
                            </div>
                            
                            <ul class="rule-list mt-2">
                                <li id="rule-length" class="rule-invalid">At least 8 characters</li>
                                <li id="rule-upper" class="rule-invalid">One uppercase letter (A-Z)</li>
                                <li id="rule-lower" class="rule-invalid">One lowercase letter (a-z)</li>
                                <li id="rule-number" class="rule-invalid">One number (0-9)</li>
                                <li id="rule-special" class="rule-invalid">One special character (@$!%*?&)</li>
                            </ul>
                        </div>
                        
                        <div class="form-group mb-4 mt-3">
                            <label class="form-label font-weight-bold">Confirm Password</label>
                            <div class="input-group shadow-sm rounded-pill">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control px-4 border-right-0" style="border-radius: 50px 0 0 50px;" placeholder="Confirm your password" required>
                                <div class="input-group-append">
                                    <span class="input-group-text bg-white border-left-0 text-muted" style="border-radius: 0 50px 50px 0; cursor: pointer;" onclick="togglePassword('confirm_password', this)">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <small id="match-text" class="d-block mt-1 font-weight-bold" style="font-size: 0.75rem; padding-left: 15px;"></small>
                        </div>

                        <button type="submit" id="submit-btn" class="btn btn-info btn-block rounded-pill py-2 shadow-sm font-weight-bold mt-4" disabled>Activate Account <i class="fas fa-arrow-right ml-2"></i></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function togglePassword(inputId, iconSpan) {
        var input = document.getElementById(inputId);
        var icon = iconSpan.querySelector('i');
        
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            input.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        const passwordInput = document.getElementById('new_password');
        const confirmInput = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submit-btn');
        const matchText = document.getElementById('match-text');

        function checkRules() {
            const val = passwordInput.value;
            let score = 0;

            // Define Rules
            const rules = {
                length: val.length >= 8,
                upper: /[A-Z]/.test(val),
                lower: /[a-z]/.test(val),
                number: /[0-9]/.test(val),
                special: /[\W_]/.test(val)
            };

            // Update UI Rules
            for (const [key, isValid] of Object.entries(rules)) {
                const element = document.getElementById('rule-' + key);
                if (element) {
                    if (isValid) {
                        element.classList.remove('rule-invalid');
                        element.classList.add('rule-valid');
                        element.innerHTML = '<i class="fas fa-check text-success mr-1"></i>' + element.innerText.replace(/(.*)/, '$1');
                        score++;
                    } else {
                        element.classList.remove('rule-valid');
                        element.classList.add('rule-invalid');
                        element.innerHTML = element.innerText.replace(/(.*)/, '$1');
                    }
                }
            }

            // Update Strength Bar
            const bar = document.getElementById('strength-bar');
            const text = document.getElementById('strength-text');
            const percent = (score / 5) * 100;
            bar.style.width = percent + '%';

            if (score <= 2) {
                bar.className = 'progress-bar bg-danger';
                text.innerText = 'Weak';
                text.className = 'text-danger font-weight-bold';
            } else if (score <= 4) {
                bar.className = 'progress-bar bg-warning';
                text.innerText = 'Moderate';
                text.className = 'text-warning font-weight-bold';
            } else {
                bar.className = 'progress-bar bg-success';
                text.innerText = 'Strong';
                text.className = 'text-success font-weight-bold';
            }

            checkMatch();
        }

        function checkMatch() {
            const pwd = passwordInput.value;
            const conf = confirmInput.value;
            const rulesMet = document.querySelectorAll('.rule-invalid').length === 0;

            if (conf.length > 0) {
                if (pwd === conf) {
                    matchText.innerText = 'Passwords match';
                    matchText.className = 'text-success d-block mt-1 font-weight-bold';
                    submitBtn.disabled = !rulesMet;
                } else {
                    matchText.innerText = 'Passwords do not match';
                    matchText.className = 'text-danger d-block mt-1 font-weight-bold';
                    submitBtn.disabled = true;
                }
            } else {
                matchText.innerText = '';
                submitBtn.disabled = true;
            }
        }

        if(passwordInput) passwordInput.addEventListener('input', checkRules);
        if(confirmInput) confirmInput.addEventListener('input', checkMatch);
    });
    </script>
</body>
</html>