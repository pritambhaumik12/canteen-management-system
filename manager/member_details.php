<?php
// Start output buffering to prevent header.php from sending HTML before PRG redirect
ob_start();
require_once 'header.php';
require_once '../includes/db.php';

// ==========================================
// PRG PATTERN: Handle Form Submissions (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_member'])) {
    $member_internal_id = $_POST['member_internal_id'];
    $user_id_field = $_POST['user_id_field'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $status = trim($_POST['status']);
    
    try {
        $sql = "UPDATE members SET name = ?, email = ?, department = ?, status = ? WHERE id = ?";
        $pdo->prepare($sql)->execute([$name, $email, $department, $status, $member_internal_id]);
        $_SESSION['member_success'] = "Member details updated successfully! 🎉";
    } catch (PDOException $e) {
        $_SESSION['member_error'] = "Failed to update member details. Please try again.";
    }
    
    // PRG Redirect: Clear the buffer, redirect to GET, and exit
    ob_end_clean();
    header("Location: member_details.php?user_id=" . urlencode($user_id_field));
    exit;
}

// ==========================================
// PRG PATTERN: Retrieve Flash Messages & Data (GET)
// ==========================================
$success_msg = $_SESSION['member_success'] ?? '';
$error_msg = $_SESSION['member_error'] ?? '';
unset($_SESSION['member_success'], $_SESSION['member_error']);

$member = null;
$wallet = null;
$orders = [];
$transactions = [];
$search_user_id = $_GET['user_id'] ?? '';

// Check if a user ID is provided to view details
if (!empty(trim($search_user_id))) {
    $user_id_to_find = trim($search_user_id);
    
    // Select email, status, and other fields
    $sql = "SELECT id, member_id as user_id, name, department, email, status FROM members WHERE member_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id_to_find]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        // Fetch wallet
        $sql_wallet = "SELECT * FROM wallet WHERE member_id = ?";
        $stmt_wallet = $pdo->prepare($sql_wallet);
        $stmt_wallet->execute([$member['id']]);
        $wallet = $stmt_wallet->fetch(PDO::FETCH_ASSOC);
        
        if(!$wallet) {
            // Create a wallet if it doesn't exist
            $pdo->prepare("INSERT INTO wallet (member_id, balance) VALUES (?, 0)")->execute([$member['id']]);
            $wallet = ['balance' => 0];
        }

        // Fetch recent orders
        $sql_orders = "SELECT * FROM orders WHERE member_id = ? ORDER BY created_at DESC LIMIT 10";
        $stmt_orders = $pdo->prepare($sql_orders);
        $stmt_orders->execute([$member['id']]);
        $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

        // Fetch recent transactions
        $sql_trans = "SELECT * FROM transactions WHERE member_id = ? ORDER BY created_at DESC LIMIT 10";
        $stmt_trans = $pdo->prepare($sql_trans);
        $stmt_trans->execute([$member['id']]);
        $transactions = $stmt_trans->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error_msg = "No member found with User ID: " . htmlspecialchars($user_id_to_find);
    }
}

// Flush the buffered HTML from header.php so the page begins rendering
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

    body {
        font-family: var(--font-family);
        background-color: #f8fafc;
    }

    /* Core Animations */
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }
    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
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
    .sk-card { height: 280px; width: 100%; border-radius: 20px; margin-bottom: 25px; }
    .sk-tabs { height: 60px; width: 100%; border-radius: 15px; margin-bottom: 25px; }

    /* Modern Card UI */
    .interactive-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
        backdrop-filter: blur(10px);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        overflow: hidden;
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
    .digital-wallet-card:hover {
        transform: scale(1.02) translateY(-5px);
    }
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

    /* Search Bar Design */
    .search-container { max-width: 800px; margin: 0 auto 40px; }
    .search-input {
        width: 100%; height: 75px; border-radius: 20px;
        padding: 0 30px 0 70px; font-size: 1.2rem; font-family: var(--font-family);
        border: 2px solid #e2e8f0; background: white;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03); transition: all 0.3s ease;
    }
    .search-input:focus {
        border-color: #6366f1; box-shadow: 0 15px 40px rgba(99, 102, 241, 0.15);
        outline: none; transform: translateY(-2px);
    }
    .search-icon {
        position: absolute; left: 25px; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: 1.5rem; transition: color 0.3s ease;
    }
    .search-input:focus + .search-icon { color: #6366f1; }
    .search-btn {
        position: absolute; right: 10px; top: 10px; bottom: 10px;
        border-radius: 14px; padding: 0 35px;
        background: var(--dark-gradient); color: white;
        border: none; font-weight: 600; font-size: 1rem;
        transition: all 0.3s ease;
    }
    .search-btn:hover { background: #0f172a; transform: scale(1.03); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.2); }

    /* Segmented Control Tabs */
    .segmented-control {
        background-color: #f1f5f9;
        border-radius: 16px;
        padding: 6px;
        display: flex;
        position: relative;
        margin-bottom: 30px;
    }
    .segmented-control .nav-item { flex: 1; z-index: 2; }
    .segmented-control .nav-link {
        border-radius: 12px;
        color: #64748b; font-weight: 600;
        text-align: center; padding: 14px 20px;
        transition: all 0.3s ease; border: none; background: transparent;
    }
    .segmented-control .nav-link:hover { color: #1e293b; }
    .segmented-control .nav-link.active {
        color: #fff; background: var(--primary-gradient);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
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

    /* Banking Style Lists */
    .banking-list { padding: 0; margin: 0; list-style: none; }
    .banking-list-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 20px; border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s; border-radius: 16px; margin-bottom: 8px;
    }
    .banking-list-item:hover { background: #f8fafc; cursor: pointer; transform: scale(1.01); }
    .banking-icon {
        width: 50px; height: 50px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; flex-shrink: 0; margin-right: 15px;
    }
    .banking-icon.credit { background: #d1fae5; color: #059669; }
    .banking-icon.debit { background: #fee2e2; color: #dc2626; }
    .banking-icon.order { background: #e0e7ff; color: #4f46e5; }

    /* Content Wrapper Animation */
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
        <div class="col-12 col-lg-8">
            <div class="sk-tabs skeleton-box"></div>
            <div class="sk-card skeleton-box" style="height: 500px;"></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="page-content" style="display: none;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="font-weight-bold text-dark m-0" style="letter-spacing: -0.5px;">Member Hub</h2>
            <p class="text-muted">Search and manage member profiles, orders, and transactions securely.</p>
        </div>
    </div>

    <div class="search-container">
        <form action="member_details.php" method="get" id="searchForm">
            <div class="position-relative">
                <input type="text" class="form-control search-input" name="user_id" id="user_id" placeholder="Enter Member's User ID..." value="<?php echo htmlspecialchars($search_user_id); ?>" required autocomplete="off">
                <i class="fas fa-search search-icon"></i>
                <button type="submit" class="search-btn"><i class="fas fa-magic mr-1"></i> Locate</button>
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
                        <span class="font-weight-bold">
                            <?php if (($member['status'] ?? 'active') === 'active'): ?>
                                <i class="fas fa-check-circle text-success mr-1"></i> Active Account
                            <?php else: ?>
                                <i class="fas fa-times-circle text-danger mr-1"></i> Inactive Account
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

            </div>
        </div>

        <div class="col-12 col-lg-8 mb-4">
            <div class="interactive-card h-100 p-4">
                
                <ul class="nav nav-pills segmented-control" id="memberTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="profile-tab" data-toggle="pill" href="#profile" role="tab">
                            <i class="fas fa-user-edit mr-2"></i> Profile Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="transactions-tab" data-toggle="pill" href="#transactions" role="tab">
                            <i class="fas fa-history mr-2"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="orders-tab" data-toggle="pill" href="#orders" role="tab">
                            <i class="fas fa-shopping-bag mr-2"></i> Food Orders
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content" id="memberTabsContent">
                    
                    <div class="tab-pane fade show active" id="profile" role="tabpanel">
                        <div class="mb-4">
                            <h5 class="font-weight-bold text-dark">Update Member Details</h5>
                            <p class="text-muted small">Modify the personal information and account status of the member here.</p>
                        </div>
                        
                        <form action="member_details.php" method="post" id="updateForm" class="needs-validation" novalidate>
                            <input type="hidden" name="member_internal_id" value="<?php echo $member['id']; ?>">
                            <input type="hidden" name="user_id_field" value="<?php echo htmlspecialchars($member['user_id']); ?>">
                            <input type="hidden" name="update_member" value="1">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating-custom">
                                        <input type="text" name="name" id="name" class="form-control form-control-custom" placeholder=" " value="<?php echo htmlspecialchars($member['name']); ?>" required>
                                        <label for="name">Full Name</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating-custom">
                                        <input type="email" name="email" id="email" class="form-control form-control-custom" placeholder=" " value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>">
                                        <label for="email">Email Address (Optional)</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating-custom">
                                        <input type="text" name="department" id="department" class="form-control form-control-custom" placeholder=" " value="<?php echo htmlspecialchars($member['department'] ?? ''); ?>">
                                        <label for="department">Department</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating-custom">
                                        <select name="status" id="status" class="form-control form-control-custom" required>
                                            <option value="active" <?php echo ($member['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($member['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <label for="status">Account Status</label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block rounded-pill py-3 mt-2 font-weight-bold shadow text-uppercase" style="background: var(--primary-gradient); border: none; font-size: 1.1rem; letter-spacing: 1px;">
                                <i class="fas fa-save mr-2"></i> Save Changes
                            </button>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="transactions" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="font-weight-bold text-dark m-0">Recent Activity</h5>
                            <span class="badge badge-light border text-muted px-3 py-1 rounded-pill">Last 10 Txns</span>
                        </div>
                        
                        <ul class="banking-list">
                            <?php if (empty($transactions)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-receipt fa-3x mb-3 opacity-25"></i>
                                    <p class="font-weight-bold">No transaction history found.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($transactions as $t): ?>
                                <li class="banking-list-item shadow-sm">
                                    <div class="d-flex align-items-center">
                                        <div class="banking-icon <?php echo $t['type'] === 'credit' ? 'credit' : 'debit'; ?>">
                                            <i class="fas <?php echo $t['type'] === 'credit' ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                                        </div>
                                        <div>
                                            <h6 class="m-0 font-weight-bold text-dark"><?php echo htmlspecialchars($t['description']); ?></h6>
                                            <small class="text-muted font-weight-medium"><i class="far fa-calendar-alt mr-1"></i> <?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <h5 class="font-weight-bold m-0 text-<?php echo $t['type'] === 'credit' ? 'success' : 'danger'; ?>">
                                            <?php echo ($t['type'] === 'credit' ? '+' : '-') . '₹' . number_format($t['amount'], 2); ?>
                                        </h5>
                                        <small class="text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;"><?php echo $t['type']; ?></small>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="tab-pane fade" id="orders" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="font-weight-bold text-dark m-0">Canteen Orders</h5>
                            <span class="badge badge-light border text-muted px-3 py-1 rounded-pill">Last 10 Orders</span>
                        </div>

                        <ul class="banking-list">
                            <?php if (empty($orders)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-hamburger fa-3x mb-3 opacity-25"></i>
                                    <p class="font-weight-bold">No orders found.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($orders as $o): ?>
                                <li class="banking-list-item shadow-sm">
                                    <div class="d-flex align-items-center">
                                        <div class="banking-icon order">
                                            <i class="fas fa-utensils"></i>
                                        </div>
                                        <div>
                                            <h6 class="m-0 font-weight-bold text-dark d-flex align-items-center">
                                                Order ID: <span class="badge badge-dark ml-2 font-family-monospace"><?php echo htmlspecialchars($o['coupon_id']); ?></span>
                                            </h6>
                                            <small class="text-muted font-weight-medium"><i class="far fa-clock mr-1"></i> <?php echo date('d M Y • h:i A', strtotime($o['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <h5 class="font-weight-bold m-0 text-dark">₹<?php echo number_format($o['total_amount'], 2); ?></h5>
                                        <?php 
                                        $statusClass = 'secondary'; $icon = 'fa-circle';
                                        if($o['status'] === 'completed' || $o['status'] === 'served') { $statusClass = 'success'; $icon = 'fa-check-circle'; }
                                        elseif($o['status'] === 'pending') { $statusClass = 'warning text-dark'; $icon = 'fa-clock'; }
                                        elseif($o['status'] === 'cancelled') { $statusClass = 'danger'; $icon = 'fa-times-circle'; }
                                        ?>
                                        <span class="badge badge-<?php echo $statusClass; ?> px-2 py-1 mt-1 rounded text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                            <i class="fas <?php echo $icon; ?> mr-1"></i> <?php echo str_replace('_', ' ', $o['status']); ?>
                                        </span>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Skeleton Loader Smooth Transition
    setTimeout(() => {
        const skeleton = document.getElementById('page-skeleton');
        const content = document.getElementById('page-content');
        
        if (skeleton && content) {
            skeleton.style.transition = 'opacity 0.4s ease';
            skeleton.style.opacity = '0';
            
            setTimeout(() => {
                skeleton.style.display = 'none';
                content.style.display = 'block';
            }, 400);
        }
    }, 600); 

    // Auto-dismiss Alerts after 5 seconds with slide out animation
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
});
</script>

<?php require_once 'footer.php'; ?>