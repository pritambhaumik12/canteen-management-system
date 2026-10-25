<?php
// Start output buffering to prevent header.php from sending HTML before PRG redirect
ob_start();
require_once 'header.php';
require_once '../includes/db.php';

// ==========================================
// EMAIL QUEUE FUNCTION (FAST AND EFFICIENT)
// ==========================================
function queueRechargeEmail($member, $amount, $balance, $status, $reason, $payment_mode, $txn_details, $txn_date, $pdo) {
    if (!$member || empty($member['email'])) return;

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

    $subject = ($status === 'Success') ? "✅ Wallet Recharge Successful - {$cant_name}" : "❌ Wallet Recharge Failed - {$cant_name}";
    $color = ($status === 'Success') ? '#10b981' : '#ef4444';
    $icon = ($status === 'Success') ? '✓' : '✗';
    $bg_color = ($status === 'Success') ? '#f8fafc' : '#fef2f2';
    $border_color = ($status === 'Success') ? '#e2e8f0' : '#fca5a5';
    
    $htmlBody = "
    <div style='font-family: \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px 20px; background-color: #f1f5f9;'>
        {$header_html}
        <div style='background-color: #ffffff; padding: 40px 30px; border-radius: 16px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
            <div style='background-color: {$color}; color: white; width: 70px; height: 70px; line-height: 70px; border-radius: 50%; font-size: 35px; margin: 0 auto 25px; box-shadow: 0 4px 15px ".($status === 'Success' ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)').";'>
                {$icon}
            </div>
            <h2 style='color: #0f172a; margin-top: 0; font-size: 24px; font-weight: 700;'>Recharge {$status}</h2>
            <p style='color: #475569; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>Hello <strong style='color: #0f172a;'>{$member['name']}</strong>,</p>";

    if ($status === 'Success') {
        $htmlBody .= "<p style='color: #475569; font-size: 16px; margin-bottom: 25px;'>Your wallet has been successfully recharged. Here are the details of your transaction:</p>";
    } else {
        $htmlBody .= "<p style='color: #475569; font-size: 16px; margin-bottom: 25px;'>An attempt to recharge your wallet failed. Here are the details:</p>";
    }

    $htmlBody .= "
            <div style='background-color: {$bg_color}; padding: 25px; border-radius: 12px; margin: 20px 0; border: 1px solid {$border_color}; text-align: left;'>
                <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom: 15px;'>
                    <tr>
                        <td width='50%'>
                            <p style='margin: 0; color: #64748b; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;'>".($status === 'Success' ? 'Recharge Amount' : 'Attempted Amount')."</p>
                            <p style='margin: 5px 0 0 0; color: {$color}; font-size: 24px; font-weight: 700;'>₹" . number_format($amount, 2) . "</p>
                        </td>
                        <td width='50%'>";
    
    if ($status === 'Success') {
        $htmlBody .= "
                            <p style='margin: 0; color: #64748b; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;'>Latest Balance</p>
                            <p style='margin: 5px 0 0 0; color: #3b82f6; font-size: 24px; font-weight: 700;'>₹" . number_format($balance, 2) . "</p>";
    } else {
        $htmlBody .= "
                            <p style='margin: 0; color: #991b1b; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;'>Reason</p>
                            <p style='margin: 5px 0 0 0; color: #ef4444; font-size: 16px; font-weight: 600;'>{$reason}</p>";
    }

    $htmlBody .= "      </td>
                    </tr>
                </table>
                
                <div style='border-top: 1px solid ".($status === 'Success' ? '#e2e8f0' : '#fecaca')."; padding-top: 15px; margin-top: 15px;'>
                    <table width='100%' cellpadding='0' cellspacing='0'>
                        <tr>
                            <td style='padding-bottom: 15px;' width='50%'>
                                <p style='margin: 0; color: #64748b; font-size: 13px; text-transform: uppercase;'>Payment Mode</p>
                                <p style='margin: 2px 0 0 0; color: #0f172a; font-size: 15px; font-weight: 600;'>{$payment_mode}</p>
                            </td>
                            <td style='padding-bottom: 15px;' width='50%'>
                                <p style='margin: 0; color: #64748b; font-size: 13px; text-transform: uppercase;'>Transaction Details</p>
                                <p style='margin: 2px 0 0 0; color: #0f172a; font-size: 15px; font-weight: 600;'>{$txn_details}</p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan='2'>
                                <p style='margin: 0; color: #64748b; font-size: 13px; text-transform: uppercase;'>Date & Time</p>
                                <p style='margin: 2px 0 0 0; color: #0f172a; font-size: 15px; font-weight: 600;'>{$txn_date}</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>";

    $htmlBody .= "
            <p style='color: #94a3b8; font-size: 14px; margin-top: 35px; border-top: 1px solid #f1f5f9; padding-top: 20px;'>
                Thank you for using <strong>{$cant_name}</strong> at <strong>{$inst_name}</strong>.
            </p>
        </div>
    </div>";

    // INSERT INTO QUEUE DATABASE INSTEAD OF SENDING IMMEDIATELY
    $images_json = !empty($embeddedImages) ? json_encode($embeddedImages) : null;
    $stmt = $pdo->prepare("INSERT INTO email_queue (recipient_email, recipient_name, subject, html_body, sender_name, embedded_images) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$member['email'], $member['name'], $subject, $htmlBody, $sender_name, $images_json]);
}

// ==========================================
// PRG PATTERN: Handle Form Submissions (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recharge_wallet'])) {
    $member_internal_id = $_POST['member_internal_id'];
    $user_id_field = $_POST['user_id_field'];
    $amount = floatval($_POST['amount']);
    $confirm_amount = floatval($_POST['confirm_amount']);
    $t_pin = $_POST['t_pin'] ?? '';
    $payment_mode = $_POST['payment_mode'] ?? 'Cash';
    
    $txn_date = date('d M Y, h:i A'); 
    $txn_details = 'Cash Deposit';
    $upi_txn = '';
    
    if ($payment_mode === 'UPI') {
        $upi_txn = htmlspecialchars($_POST['upi_transaction_id'] ?? 'N/A');
        $raw_upi_date = $_POST['upi_date'] ?? date('Y-m-d H:i:s');
        $txn_date = date('d M Y, h:i A', strtotime($raw_upi_date));
        $txn_details = "Txn ID: " . $upi_txn;
    }

    $manager_id_session = $_SESSION['id'] ?? 0;

    $stmt_member = $pdo->prepare("SELECT name, email FROM members WHERE id = ?");
    $stmt_member->execute([$member_internal_id]);
    $member_info = $stmt_member->fetch(PDO::FETCH_ASSOC);

    $stmt_tpin = $pdo->prepare("SELECT t_pin FROM managers WHERE id = ?");
    $stmt_tpin->execute([$manager_id_session]);
    $manager_data = $stmt_tpin->fetch(PDO::FETCH_ASSOC);

    if ($amount !== $confirm_amount || $amount <= 0) {
        $_SESSION['recharge_error'] = "Amounts do not match or are invalid.";
        queueRechargeEmail($member_info, $amount, 0, 'Failed', 'Amount mismatch or invalid amount.', $payment_mode, $txn_details, $txn_date, $pdo);
    } elseif (empty($manager_data['t_pin'])) {
        $_SESSION['recharge_error'] = "T-PIN is not set for your account. Please update it in your profile first.";
        queueRechargeEmail($member_info, $amount, 0, 'Failed', 'Manager authorization incomplete. No T-PIN set.', $payment_mode, $txn_details, $txn_date, $pdo);
    } elseif ($manager_data['t_pin'] !== $t_pin) {
        $_SESSION['recharge_error'] = "Invalid T-PIN entered. Transaction failed.";
        queueRechargeEmail($member_info, $amount, 0, 'Failed', 'Invalid authorization during transaction.', $payment_mode, $txn_details, $txn_date, $pdo);
    } else {
        $pdo->beginTransaction();
        try {
            $sql_wallet = "UPDATE wallet SET balance = balance + ? WHERE member_id = ?";
            $pdo->prepare($sql_wallet)->execute([$amount, $member_internal_id]);

            if ($payment_mode === 'UPI') {
                $desc = "Recharged (UPI) - {$txn_details}, Date: {$txn_date} | By Manager ID: {$manager_id_session}";
            } else {
                $desc = "Recharged (Cash) | By Manager ID: {$manager_id_session}";
            }

            $sql_trans = "INSERT INTO transactions (member_id, type, amount, description) VALUES (?, 'credit', ?, ?)";
            $pdo->prepare($sql_trans)->execute([$member_internal_id, $amount, $desc]);
            
            $pdo->commit();

            $stmt_bal = $pdo->prepare("SELECT balance FROM wallet WHERE member_id = ?");
            $stmt_bal->execute([$member_internal_id]);
            $new_balance = $stmt_bal->fetchColumn();

            $_SESSION['recharge_success'] = "Wallet for {$member_info['name']} successfully recharged with ₹" . number_format($amount, 2);
            
            queueRechargeEmail($member_info, $amount, $new_balance, 'Success', '', $payment_mode, $txn_details, $txn_date, $pdo);

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['recharge_error'] = "Failed to recharge wallet due to a system error.";
            queueRechargeEmail($member_info, $amount, 0, 'Failed', 'Failed to recharge wallet due to an internal system error.', $payment_mode, $txn_details, $txn_date, $pdo);
        }
    }
    
    // PRG Redirect
    ob_end_clean();
    header("Location: recharge.php?user_id=" . urlencode($user_id_field));
    exit;
}

// ==========================================
// PRG PATTERN: Retrieve Flash Messages (GET)
// ==========================================
$success_msg = $_SESSION['recharge_success'] ?? '';
$error_msg = $_SESSION['recharge_error'] ?? '';
unset($_SESSION['recharge_success'], $_SESSION['recharge_error']);

// Fetch Manager's T-PIN for client-side validation
$manager_id_session = $_SESSION['id'] ?? 0;
$stmt_m = $pdo->prepare("SELECT t_pin FROM managers WHERE id = ?");
$stmt_m->execute([$manager_id_session]);
$m_data = $stmt_m->fetch(PDO::FETCH_ASSOC);
$manager_tpin = $m_data['t_pin'] ?? '';

$member = null;
$wallet = null;
$search_user_id = $_GET['user_id'] ?? '';

if (!empty(trim($search_user_id))) {
    $user_id_to_find = trim($search_user_id);
    
    $sql = "SELECT id, member_id as user_id, name, department, email FROM members WHERE member_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id_to_find]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        $sql_wallet = "SELECT * FROM wallet WHERE member_id = ?";
        $stmt_wallet = $pdo->prepare($sql_wallet);
        $stmt_wallet->execute([$member['id']]);
        $wallet = $stmt_wallet->fetch(PDO::FETCH_ASSOC);
        
        if(!$wallet) {
            $pdo->prepare("INSERT INTO wallet (member_id, balance) VALUES (?, 0)")->execute([$member['id']]);
            $wallet = ['balance' => 0];
        }
    } else {
        $error_msg = "No member found with User ID: " . htmlspecialchars($user_id_to_find);
    }
}

ob_end_flush(); 
?>

<style>
    /* Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

    /* Theme Variables */
    :root {
        --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --dark-gradient: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        --card-gradient: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
        --glass-bg: rgba(255, 255, 255, 0.95);
        --glass-border: rgba(255, 255, 255, 0.5);
        --skeleton-base: #e2e8f0;
        --skeleton-highlight: #f8fafc;
        --font-family: 'Outfit', sans-serif;
    }

    body { font-family: var(--font-family); background-color: #f8fafc; }

    /* Animations */
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    @keyframes slideUpFade { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes pulse-soft {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); }
        70% { transform: scale(1.02); box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
    }

    /* Skeleton Styles */
    .skeleton-box {
        background: var(--skeleton-base);
        background-image: linear-gradient(90deg, var(--skeleton-base) 0px, var(--skeleton-highlight) 50%, var(--skeleton-base) 100%);
        background-size: 1000px 100%;
        animation: shimmer 2s infinite linear;
        border-radius: 16px;
    }
    .sk-search { height: 75px; width: 100%; border-radius: 20px; margin-bottom: 30px; }
    .sk-card { height: 400px; width: 100%; border-radius: 20px; margin-bottom: 25px; }

    /* Card UI */
    .interactive-card {
        background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 24px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04); backdrop-filter: blur(10px);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); overflow: hidden;
    }
    .interactive-card:hover {
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.08);
        transform: translateY(-4px);
    }

    /* Digital Wallet Credit Card Style */
    .digital-wallet-card {
        background: var(--card-gradient);
        border-radius: 20px;
        padding: 30px;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 15px 35px rgba(79, 70, 229, 0.3);
        margin-top: 20px;
        transition: transform 0.3s ease;
    }
    .digital-wallet-card:hover { transform: scale(1.02) translateY(-5px); }
    .digital-wallet-card::before {
        content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 60%);
        transform: rotate(30deg); pointer-events: none;
    }
    .wallet-chip {
        width: 45px; height: 35px;
        background: linear-gradient(135deg, #fceabb 0%, #f8b500 100%);
        border-radius: 6px; margin-bottom: 20px;
        position: relative;
    }
    .wallet-balance-amount {
        font-size: 2.5rem; font-weight: 800; letter-spacing: -1px;
        margin: 0; text-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    /* Form Inputs */
    .form-floating-custom { position: relative; margin-bottom: 1.5rem; }
    .form-control-custom {
        border-radius: 14px; padding: 26px 16px 10px; height: auto;
        border: 2px solid #e2e8f0; background-color: #f8fafc;
        font-weight: 600; font-size: 1.15rem; transition: all 0.3s ease;
    }
    .form-control-custom:focus {
        border-color: #4f46e5; background-color: #fff;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); outline: none;
    }
    .form-floating-custom label {
        position: absolute; top: 0; left: 0; height: 100%; padding: 18px 16px;
        pointer-events: none; transform-origin: 0 0;
        transition: opacity .2s, transform .2s;
        color: #94a3b8; font-weight: 500; font-size: 1rem;
    }
    .form-control-custom:focus ~ label, .form-control-custom:not(:placeholder-shown) ~ label {
        transform: scale(0.8) translateY(-0.8rem) translateX(0.15rem); color: #4f46e5; font-weight: 600;
    }

    /* Quick Amounts */
    .quick-amount-wrapper { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
    .quick-amt-btn {
        flex: 1; min-width: 80px; background: white; border: 2px solid #e2e8f0;
        color: #475569; font-weight: 600; font-size: 1rem; padding: 10px 15px; border-radius: 12px;
        transition: all 0.2s ease; cursor: pointer;
    }
    .quick-amt-btn:hover { border-color: #6366f1; color: #6366f1; background: #eef2ff; transform: translateY(-2px); }
    .quick-amt-btn:active { transform: scale(0.95); }

    /* Search Bar */
    .search-container { max-width: 800px; margin: 0 auto 40px; }
    .search-input {
        width: 100%; height: 75px; border-radius: 20px; padding: 0 30px 0 70px; font-size: 1.2rem;
        font-family: var(--font-family); border: 2px solid #e2e8f0; background: white;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03); transition: all 0.3s ease;
    }
    .search-input:focus { border-color: #6366f1; outline: none; transform: translateY(-2px); box-shadow: 0 15px 40px rgba(99, 102, 241, 0.15); }
    .search-icon { position: absolute; left: 25px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1.5rem; transition: color 0.3s ease; }
    .search-input:focus + .search-icon { color: #6366f1; }
    .search-btn {
        position: absolute; right: 10px; top: 10px; bottom: 10px; border-radius: 14px; padding: 0 35px;
        background: var(--dark-gradient); color: white; border: none; font-weight: 600; font-size: 1rem;
        transition: all 0.3s ease;
    }
    .search-btn:hover { background: #0f172a; transform: scale(1.03); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.2); }

    /* PIN Modal */
    .pin-input { letter-spacing: 10px; font-size: 1.5rem; font-weight: 800; text-align: center; padding-left: 15px !important; }
    .modal-content { border-radius: 24px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
    .modal-header { border-bottom: 1px solid #f1f5f9; padding: 25px; border-radius: 24px 24px 0 0; }
    .modal-body { padding: 30px; }
    .modal-footer { border-top: none; padding: 20px 30px 30px; }
    
    #page-content { animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
</style>

<div id="alert-container" class="position-fixed" style="top: 20px; right: 20px; z-index: 1050; min-width: 320px;">
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-lg border-0" style="border-left: 5px solid #10b981; border-radius: 12px; font-family: var(--font-family);">
            <i class="fas fa-check-circle mr-2 fa-lg"></i> <strong>Success!</strong><br> <?php echo $success_msg; ?>
            <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-lg border-0" style="border-left: 5px solid #ef4444; border-radius: 12px; font-family: var(--font-family);">
            <i class="fas fa-exclamation-triangle mr-2 fa-lg"></i> <strong>Error!</strong><br> <?php echo $error_msg; ?>
            <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>
        </div>
    <?php endif; ?>
</div>

<div id="page-skeleton">
    <div class="sk-search skeleton-box mx-auto"></div>
    <?php if ($search_user_id && !$error_msg): ?>
    <div class="row">
        <div class="col-12 col-lg-4"><div class="sk-card skeleton-box" style="height: 400px;"></div></div>
        <div class="col-12 col-lg-8"><div class="sk-card skeleton-box" style="height: 500px;"></div></div>
    </div>
    <?php endif; ?>
</div>

<div id="page-content" style="display: none;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="font-weight-bold text-dark m-0" style="letter-spacing: -0.5px;">Wallet Recharge</h2>
            <p class="text-muted">Instantly top-up student or staff wallets securely.</p>
        </div>
    </div>

    <div class="search-container">
        <form action="recharge.php" method="get">
            <div class="position-relative">
                <input type="text" class="form-control search-input" name="user_id" placeholder="Enter Member's User ID..." value="<?php echo htmlspecialchars($search_user_id); ?>" required autocomplete="off">
                <i class="fas fa-search search-icon"></i>
                <button type="submit" class="search-btn"><i class="fas fa-bolt mr-1"></i> Check</button>
            </div>
        </form>
    </div>

    <?php if ($member): ?>
    <div class="row">
        <div class="col-12 col-lg-4 mb-4">
            <div class="interactive-card h-100 p-4 d-flex flex-column">
                <div class="d-flex align-items-center mb-4 pb-3 border-bottom border-light">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-3 shadow" style="width: 60px; height: 60px; font-size: 24px;">
                        <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h4 class="font-weight-bold text-dark mb-0"><?php echo htmlspecialchars($member['name']); ?></h4>
                        <span class="badge badge-light border text-primary font-weight-bold px-2 py-1 mt-1">
                            <i class="fas fa-fingerprint mr-1"></i> <?php echo htmlspecialchars($member['user_id']); ?>
                        </span>
                    </div>
                </div>

                <div class="mb-2">
                    <small class="text-muted text-uppercase font-weight-bold" style="letter-spacing: 1px;">Contact Info</small>
                    <p class="font-weight-bold text-dark mt-1 mb-2"><i class="fas fa-envelope text-primary mr-2"></i> <?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?></p>
                    <p class="font-weight-bold text-dark m-0"><i class="fas fa-building text-primary mr-2"></i> <?php echo htmlspecialchars($member['department'] ?? 'N/A'); ?></p>
                </div>

                <div class="digital-wallet-card mt-auto">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="wallet-chip"></div>
                        <i class="fas fa-wifi" style="transform: rotate(90deg); opacity: 0.7; font-size: 1.5rem;"></i>
                    </div>
                    <p class="text-uppercase mb-1 font-weight-bold" style="letter-spacing: 2px; font-size: 0.8rem; opacity: 0.8;">Available Balance</p>
                    <h2 class="wallet-balance-amount">₹<?php echo number_format($wallet['balance'], 2); ?></h2>
                    <div class="mt-4 pt-3 border-top" style="border-color: rgba(255,255,255,0.2) !important;">
                        <small class="text-uppercase d-block" style="opacity: 0.8; font-size: 0.7rem; letter-spacing: 1px;">Member Status</small>
                        <span class="font-weight-bold"><i class="fas fa-check-circle text-success mr-1"></i> Active Account</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8 mb-4">
            <div class="interactive-card h-100 p-4">
                <div class="mb-4">
                    <h4 class="font-weight-bold text-dark m-0">Top-up Wallet</h4>
                    <p class="text-muted small">Select a quick amount or enter a custom value to recharge securely.</p>
                </div>
                
                <form action="recharge.php" method="post" id="rechargeForm" class="needs-validation" novalidate onsubmit="event.preventDefault(); startRechargeProcess();">
                    <input type="hidden" name="member_internal_id" value="<?php echo $member['id']; ?>">
                    <input type="hidden" name="user_id_field" value="<?php echo $member['user_id']; ?>">
                    <input type="hidden" name="t_pin" id="form_tpin" value="">
                    <input type="hidden" name="recharge_wallet" value="1">
                    
                    <div class="quick-amount-wrapper">
                        <button type="button" class="quick-amt-btn" onclick="setAmount(100)">+ ₹100</button>
                        <button type="button" class="quick-amt-btn" onclick="setAmount(200)">+ ₹200</button>
                        <button type="button" class="quick-amt-btn" onclick="setAmount(500)">+ ₹500</button>
                        <button type="button" class="quick-amt-btn" onclick="setAmount(1000)">+ ₹1000</button>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating-custom">
                                <input type="number" step="0.01" min="1" name="amount" id="amount" class="form-control form-control-custom" placeholder=" " required>
                                <label for="amount">Top-up Amount (₹)</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating-custom">
                                <input type="number" step="0.01" min="1" name="confirm_amount" id="confirm_amount" class="form-control form-control-custom" placeholder=" " required>
                                <label for="confirm_amount">Confirm Amount (₹)</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating-custom">
                        <select name="payment_mode" id="payment_mode" class="form-control form-control-custom" onchange="toggleUpiFields()" required>
                            <option value="" disabled selected></option>
                            <option value="Cash">Cash Deposit</option>
                            <option value="UPI">UPI Transfer</option>
                        </select>
                        <label for="payment_mode">Select Payment Mode</label>
                    </div>
                    
                    <div id="upi_fields" style="display: none; animation: slideUpFade 0.3s ease;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating-custom">
                                    <input type="text" name="upi_transaction_id" id="upi_transaction_id" class="form-control form-control-custom" placeholder=" ">
                                    <label for="upi_transaction_id">UPI Transaction ID</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating-custom">
                                    <input type="datetime-local" name="upi_date" id="upi_date" class="form-control form-control-custom" placeholder=" ">
                                    <label for="upi_date">Txn Date & Time</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-block rounded-pill py-3 mt-3 font-weight-bold shadow text-uppercase" style="background: var(--success-gradient); border: none; font-size: 1.1rem; letter-spacing: 1px;">
                        <i class="fas fa-lock mr-2"></i> Authorize & Add Funds
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($member): ?>
    <div class="modal fade" id="tpinModal1" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header bg-light justify-content-center border-0 pt-4">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow" style="width: 60px; height: 60px; font-size: 24px;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h5 class="modal-title font-weight-bold text-dark">Security Check</h5>
                        <p class="text-muted small m-0">Manager authorization required</p>
                    </div>
                </div>
                <div class="modal-body text-center pb-2">
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-dark mb-2">Enter your 4 to 6-Digit T-PIN</label>
                        <input type="password" id="temp_tpin_1" class="form-control form-control-lg pin-input bg-light border-0 rounded-lg shadow-sm" maxlength="6" placeholder="••••••" required autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 font-weight-bold text-muted" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 font-weight-bold shadow" onclick="goToSummary()">Verify <i class="fas fa-arrow-right ml-1"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="summaryModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title font-weight-bold text-dark"><i class="fas fa-file-invoice-dollar text-primary mr-2"></i>Review Transaction</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div class="bg-light p-3 rounded-lg mb-4 border shadow-sm">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted font-weight-bold small text-uppercase">Member</span>
                            <strong id="sum_name" class="text-dark text-right"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted font-weight-bold small text-uppercase">User ID</span>
                            <strong id="sum_id" class="text-primary text-right"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted font-weight-bold small text-uppercase">Payment Mode</span>
                            <strong id="sum_mode" class="text-dark text-right"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                            <span class="text-muted font-weight-bold small text-uppercase">Date</span>
                            <strong id="sum_date" class="text-dark text-right small"></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                            <span class="text-muted font-weight-bold text-uppercase">Total Recharge</span>
                            <strong id="sum_amount" class="text-success h3 m-0 font-weight-bold"></strong>
                        </div>
                    </div>

                    <div class="form-group mb-0 text-center">
                        <label class="font-weight-bold text-danger mb-2"><i class="fas fa-exclamation-triangle mr-1"></i> Final Confirmation (T-PIN)</label>
                        <input type="password" id="final_tpin" class="form-control form-control-lg pin-input bg-white border rounded-lg shadow-sm mx-auto" style="max-width: 200px;" maxlength="6" placeholder="••••••" required autocomplete="new-password">
                        <div id="tpin_error" class="text-danger mt-2 small font-weight-bold bg-danger-light p-2 rounded" style="display:none; background: #fee2e2;"></div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom pt-3 pb-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4 font-weight-bold" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success rounded-pill px-4 font-weight-bold shadow text-uppercase" onclick="submitRecharge()" style="background: var(--success-gradient); border: none;">Confirm & Add Funds <i class="fas fa-check-circle ml-1"></i></button>
                </div>
            </div>
        </div>
    </div>

    <script>
    const MANAGER_TPIN = "<?php echo addslashes((string)$manager_tpin); ?>";

    function setAmount(val) {
        let amountInput = document.getElementById('amount');
        let confirmInput = document.getElementById('confirm_amount');
        
        amountInput.value = val;
        confirmInput.value = val;

        amountInput.dispatchEvent(new Event('input'));
        confirmInput.dispatchEvent(new Event('input'));

        amountInput.style.transform = 'scale(1.02)';
        confirmInput.style.transform = 'scale(1.02)';
        amountInput.style.borderColor = '#10b981';
        confirmInput.style.borderColor = '#10b981';
        
        setTimeout(() => {
            amountInput.style.transform = 'scale(1)';
            confirmInput.style.transform = 'scale(1)';
            amountInput.style.borderColor = '';
            confirmInput.style.borderColor = '';
        }, 300);
    }

    function toggleUpiFields() {
        const mode = document.getElementById('payment_mode').value;
        const upiFields = document.getElementById('upi_fields');
        const upiTxnId = document.getElementById('upi_transaction_id');
        const upiDate = document.getElementById('upi_date');

        if (mode === 'UPI') {
            upiFields.style.display = 'block';
            upiTxnId.required = true;
            upiDate.required = true;
            if (!upiDate.value) {
                let now = new Date();
                let tzOffset = now.getTimezoneOffset() * 60000;
                let localISOTime = (new Date(now - tzOffset)).toISOString().slice(0, 16);
                upiDate.value = localISOTime;
            }
        } else {
            upiFields.style.display = 'none';
            upiTxnId.required = false;
            upiDate.required = false;
            upiTxnId.value = '';
            upiDate.value = '';
        }
    }

    function startRechargeProcess() {
        const loadingOverlay = document.getElementById('loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.classList.add('hidden');
            loadingOverlay.style.display = 'none';
        }
        
        if (!MANAGER_TPIN) { 
            alert('T-PIN is not set for your account. Please update it in your profile first.'); 
            return; 
        }
        
        let form = document.getElementById('rechargeForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        let amt = parseFloat(document.getElementById('amount').value);
        let c_amt = parseFloat(document.getElementById('confirm_amount').value);
        if (amt <= 0) {
            alert('Amount must be greater than zero.');
            return;
        }
        if (amt !== c_amt) { 
            alert('Amount and Confirm Amount must match exactly!'); 
            return; 
        }
        
        document.getElementById('temp_tpin_1').value = '';
        $('#tpinModal1').modal('show');
        setTimeout(() => document.getElementById('temp_tpin_1').focus(), 500);
    }

    function goToSummary() {
        let tpin1 = document.getElementById('temp_tpin_1').value;
        if (!tpin1) {
            alert('Please enter your T-PIN to proceed.');
            return;
        }

        if (tpin1 !== MANAGER_TPIN) {
            let input = document.getElementById('temp_tpin_1');
            input.classList.add('is-invalid');
            input.value = '';
            alert('Incorrect T-PIN entered! Authorization denied.'); 
            return;
        }
        
        document.getElementById('sum_name').innerText = "<?php echo addslashes((string)($member['name'] ?? '')); ?>";
        document.getElementById('sum_id').innerText = "<?php echo addslashes((string)($member['user_id'] ?? '')); ?>";
        document.getElementById('sum_amount').innerText = "₹" + parseFloat(document.getElementById('amount').value).toFixed(2);
        
        let mode = document.getElementById('payment_mode').value;
        document.getElementById('sum_mode').innerText = mode;

        if (mode === 'UPI') {
            let dateVal = document.getElementById('upi_date').value;
            let d = new Date(dateVal);
            let dateString = !isNaN(d) ? d.toLocaleString() : dateVal;
            document.getElementById('sum_date').innerText = dateString + ' (Txn: ' + document.getElementById('upi_transaction_id').value + ')';
        } else {
            document.getElementById('sum_date').innerText = new Date().toLocaleString() + ' (Current)';
        }

        $('#tpinModal1').one('hidden.bs.modal', function () {
            document.getElementById('final_tpin').value = '';
            document.getElementById('tpin_error').style.display = 'none';
            $('#summaryModal').modal('show');
            setTimeout(() => document.getElementById('final_tpin').focus(), 500);
        }).modal('hide');
    }

    function submitRecharge() {
        let tpin2 = document.getElementById('final_tpin').value;
        if (tpin2 !== MANAGER_TPIN) {
            document.getElementById('tpin_error').innerHTML = "<i class='fas fa-times-circle mr-1'></i> Incorrect T-PIN entered. Please try again!";
            document.getElementById('tpin_error').style.display = 'block';
            document.getElementById('final_tpin').value = ''; 
            document.getElementById('final_tpin').focus();
            return;
        }
        
        document.getElementById('form_tpin').value = tpin2;
        let overlay = document.getElementById('loading-overlay');
        if (overlay) { overlay.style.display = ''; overlay.classList.remove('hidden'); }
        event.target.disabled = true; event.target.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
        document.getElementById('rechargeForm').submit();
    }

    document.addEventListener("DOMContentLoaded", function() {
        ['temp_tpin_1', 'final_tpin'].forEach(id => {
            let el = document.getElementById(id);
            if(el) el.addEventListener('keypress', e => { if (e.key === 'Enter') { e.preventDefault(); id === 'temp_tpin_1' ? goToSummary() : submitRecharge(); } });
        });
    });
    </script>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(() => {
        let skeleton = document.getElementById('page-skeleton'), content = document.getElementById('page-content');
        if (skeleton && content) {
            skeleton.style.transition = 'opacity 0.4s ease'; skeleton.style.opacity = '0';
            setTimeout(() => { skeleton.style.display = 'none'; content.style.display = 'block'; }, 400);
        }
    }, 600); 

    setTimeout(function() {
        let alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (typeof $ !== 'undefined' && $.fn.alert) {
                $(alert).alert('close');
            } else {
                alert.style.transition = "opacity 0.5s ease, transform 0.5s ease";
                alert.style.opacity = "0";
                alert.style.transform = "translateX(100%)";
                setTimeout(() => alert.remove(), 500);
            }
        });
    }, 5000);

    // ==========================================
    // ASYNCHRONOUS EMAIL QUEUE TRIGGER
    // ==========================================
    // This calls the background worker script 1 second after the page finishes loading.
    // It runs completely silently without blocking the UI.
    setTimeout(() => { 
        fetch('../includes/process_email_queue.php').catch(e => console.error("Queue process background check failed:", e)); 
    }, 1000);
});
</script>

<?php require_once 'footer.php'; ?>