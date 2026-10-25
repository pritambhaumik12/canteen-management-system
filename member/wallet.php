<?php
ob_start();
include 'header.php'; // Includes db.php, balance is already fetched

$member_id = $_SESSION['id'];

// Fetch member details explicitly from the 'members' table
$stmt_user = $pdo->prepare("SELECT name, member_id FROM members WHERE id = ?");
$stmt_user->execute([$member_id]);
$user_row = $stmt_user->fetch(PDO::FETCH_ASSOC);

$member_name = $user_row ? $user_row['name'] : (isset($_SESSION['name']) ? $_SESSION['name'] : 'Canteen Member');
$real_member_id_str = $user_row ? $user_row['member_id'] : strval($member_id);

// Format User ID to look like a debit card ending (takes the last 4 characters)
$member_code = str_pad(substr($real_member_id_str, -4), 4, '0', STR_PAD_LEFT);

// Fetch Institution & Canteen Settings for the Card
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$inst_name = htmlspecialchars($settings['institution_name'] ?? 'Institution');
$cant_name = htmlspecialchars($settings['canteen_name'] ?? 'Canteen');
$inst_logo = !empty($settings['institution_logo']) ? '../assets/images/logos/' . htmlspecialchars($settings['institution_logo']) : '';
$cant_logo = !empty($settings['canteen_logo']) ? '../assets/images/logos/' . htmlspecialchars($settings['canteen_logo']) : '';


// Default Filter Settings
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'last10';
$today = date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $today;

// Determine active tab (If user is filtering, default to passbook tab)
$active_tab = (isset($_GET['filter']) || isset($_GET['start_date'])) ? 'passbook' : 'card';

// Base query for Passbook
$query = "SELECT * FROM transactions WHERE member_id = ?";
$params = [$member_id];

// Apply Filters
if ($filter === '7days') {
    $start_date = date('Y-m-d', strtotime('-6 days'));
    $query .= " AND DATE(created_at) >= ? AND DATE(created_at) <= ?";
    $params[] = $start_date; 
    $params[] = $end_date;
} elseif ($filter === '1month') {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $query .= " AND DATE(created_at) >= ? AND DATE(created_at) <= ?";
    $params[] = $start_date; 
    $params[] = $end_date;
} elseif ($filter === 'custom' && !empty($start_date) && !empty($end_date)) {
    if (strtotime($start_date) > strtotime($end_date)) {
        $temp = $start_date;
        $start_date = $end_date;
        $end_date = $temp;
    }
    $query .= " AND DATE(created_at) >= ? AND DATE(created_at) <= ?";
    $params[] = $start_date; 
    $params[] = $end_date;
}

$query .= " ORDER BY created_at DESC";

if ($filter === 'last10') {
    $query .= " LIMIT 10";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* AGGRESSIVE GAP KILLER */
    body > .container.mt-4.mb-5:first-of-type { display: none !important; margin: 0 !important; padding: 0 !important; height: 0 !important; border: none !important; }

    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; padding-bottom: 90px; }

    /* Page Header */
    .greeting-section { 
        background: linear-gradient(135deg, #0062E6 0%, #33AEFF 100%); 
        color: white; 
        padding: 25px 20px 30px; 
        border-radius: 0 0 30px 30px; 
        box-shadow: 0 4px 15px rgba(0, 98, 230, 0.2); 
        margin-top: -1px; 
        position: relative;
        z-index: 1;
        margin-bottom: 25px;
    }

    /* Custom Modern Tabs */
    .wallet-tabs {
        background: #fff;
        padding: 6px;
        border-radius: 50px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        display: inline-flex;
        margin-bottom: 30px;
    }
    .wallet-tabs .nav-link {
        border-radius: 50px;
        color: #6c757d;
        font-weight: 600;
        padding: 10px 25px;
        transition: all 0.3s ease;
        border: none;
    }
    .wallet-tabs .nav-link.active {
        background: #0062E6;
        color: white;
        box-shadow: 0 4px 10px rgba(0, 98, 230, 0.3);
    }
    .wallet-tabs .nav-link:hover:not(.active) {
        background: #f1f3f5;
    }

    /* Debit Card Style */
    .debit-card-wrapper { perspective: 1000px; margin: 0 auto 30px; max-width: 450px; }
    .debit-card {
        background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
        border-radius: 20px;
        padding: 25px;
        color: white;
        box-shadow: 0 15px 35px rgba(15, 32, 39, 0.4);
        position: relative;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .debit-card:hover { transform: translateY(-5px) rotateX(2deg) rotateY(2deg); box-shadow: 0 20px 40px rgba(15, 32, 39, 0.5); }
    .debit-card::before { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255, 255, 255, 0.05); border-radius: 50%; }
    .debit-card::after { content: ''; position: absolute; bottom: -80px; left: -20px; width: 200px; height: 200px; background: rgba(255, 255, 255, 0.03); border-radius: 50%; }
    
    .card-bank-info { position: relative; z-index: 2; margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 12px; }
    .bank-logo-img { height: 35px; width: 35px; object-fit: contain; background: white; padding: 3px; border-radius: 6px; margin-right: 12px; }
    .bank-name { font-size: 1.1rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
    .branch-name { font-size: 0.65rem; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px; color: #a8c0ff; }

    .card-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; position: relative; z-index: 2; }
    .card-chip { width: 45px; height: 35px; background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%); border-radius: 6px; opacity: 0.9; box-shadow: inset 0 0 5px rgba(0,0,0,0.2); }
    .card-contactless { font-size: 1.5rem; opacity: 0.8; }
    
    .card-balance-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px; opacity: 0.8; margin-bottom: 5px; display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2; }
    .card-balance { font-size: 2.5rem; font-weight: 700; margin-bottom: 20px; letter-spacing: 1px; position: relative; z-index: 2; text-shadow: 0 2px 5px rgba(0,0,0,0.3); transition: all 0.3s ease; }
    .toggle-eye { cursor: pointer; padding: 5px; border-radius: 50%; transition: background 0.2s; }
    .toggle-eye:hover { background: rgba(255,255,255,0.2); }
    
    .card-number { font-family: 'Courier New', monospace; font-size: 1.3rem; letter-spacing: 4px; margin-bottom: 20px; opacity: 0.9; position: relative; z-index: 2; display: flex; align-items: center; text-shadow: 0 1px 2px rgba(0,0,0,0.5); }
    .card-number small { font-family: 'Poppins', sans-serif; font-size: 0.6rem; letter-spacing: 1px; text-transform: uppercase; margin-right: 12px; opacity: 0.7; }
    
    .card-bottom { display: flex; justify-content: space-between; align-items: flex-end; position: relative; z-index: 2; }
    .card-holder { font-size: 0.95rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 500; text-shadow: 0 1px 3px rgba(0,0,0,0.5); }
    .canteen-logo-img { height: 30px; object-fit: contain; background: rgba(255,255,255,0.9); padding: 4px 8px; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }

    /* Filters & Passbook */
    .filter-container { background: #fff; border-radius: 16px; padding: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 25px; }
    .filter-pills { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: none; }
    .filter-pills::-webkit-scrollbar { display: none; }
    .filter-pill { white-space: nowrap; padding: 8px 18px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; color: #6c757d; background: #f1f3f5; border: 1px solid transparent; cursor: pointer; transition: all 0.3s ease; }
    .filter-pill.active { background: #e3f2fd; color: #0062E6; border-color: #bbdefb; }
    .filter-pill:hover:not(.active) { background: #e9ecef; }
    
    .custom-date-wrapper { display: none; animation: slideDown 0.3s ease-out forwards; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e9ecef; }
    .custom-date-wrapper.show { display: flex; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .modern-date-input { width: 100%; padding: 10px 15px; border-radius: 10px; border: 1px solid #dee2e6; background: #f8f9fa; color: #495057; font-size: 0.9rem; transition: all 0.3s; }
    .modern-date-input:focus { border-color: #33AEFF; background: #fff; box-shadow: 0 0 0 3px rgba(51, 174, 255, 0.1); outline: none; }

    /* Transaction List */
    .modern-card { border-radius: 16px; border: none; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.04); overflow: hidden; }
    .tx-item { transition: background 0.2s ease; border-bottom: 1px solid #f1f3f5; }
    .tx-item:hover { background: #f8f9fa; }
    .tx-item:last-child { border-bottom: none; }
    
    .icon-circle { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .bg-soft-success { background: #e8f5e9; color: #2e7d32; }
    .bg-soft-danger { background: #ffebee; color: #c62828; }

    /* Skeleton Loader */
    .skeleton { background-color: #e2e5e7; background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0)); background-size: 40px 100%; background-repeat: no-repeat; animation: shine 1.5s ease infinite; }
    @keyframes shine { to { background-position: right -40px top 0; } }
    .skel-card { height: 80px; border-radius: 12px; margin-bottom: 10px; }

    #actual-content { opacity: 0; transition: opacity 0.4s ease; }
    #actual-content.show-content { opacity: 1; }
</style>

<div class="container-fluid px-0">
    <div class="greeting-section text-center text-md-left">
        <div class="container d-flex align-items-center justify-content-center justify-content-md-start">
            <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center mr-3 shadow-sm" style="width: 50px; height: 50px;">
                <i class="fas fa-wallet fa-lg"></i>
            </div>
            <div>
                <h4 class="font-weight-bold mb-0">My Wallet</h4>
                <p class="mb-0 text-light" style="font-size: 0.95rem;">Manage your funds and track spending.</p>
            </div>
        </div>
    </div>
</div>

<div class="container mt-2 mb-5" style="max-width: 900px;">
    
    <div class="text-center">
        <ul class="nav nav-pills wallet-tabs" id="walletTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'card' ? 'active' : '' ?>" id="card-tab" data-toggle="pill" href="#tab-card" role="tab" aria-controls="tab-card" aria-selected="<?= $active_tab === 'card' ? 'true' : 'false' ?>">
                    <i class="far fa-credit-card mr-1"></i> Balance Card
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'passbook' ? 'active' : '' ?>" id="passbook-tab" data-toggle="pill" href="#tab-passbook" role="tab" aria-controls="tab-passbook" aria-selected="<?= $active_tab === 'passbook' ? 'true' : 'false' ?>">
                    <i class="fas fa-book mr-1"></i> Passbook
                </a>
            </li>
        </ul>
    </div>

    <div class="tab-content" id="walletTabContent">
        
        <div class="tab-pane fade <?= $active_tab === 'card' ? 'show active' : '' ?>" id="tab-card" role="tabpanel" aria-labelledby="card-tab">
            <div class="debit-card-wrapper mt-3">
                <div class="debit-card">
                    
                    <div class="card-bank-info d-flex align-items-center">
                        <?php if ($inst_logo): ?>
                            <img src="<?= $inst_logo ?>" alt="Bank Logo" class="bank-logo-img">
                        <?php else: ?>
                            <div class="bank-logo-img d-flex align-items-center justify-content-center text-dark bg-white">
                                <i class="fas fa-university"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="bank-name"><?= $inst_name ?></div>
                            <div class="branch-name">Branch: <?= $cant_name ?></div>
                        </div>
                    </div>

                    <div class="card-top">
                        <div class="card-chip"></div>
                        <i class="fas fa-wifi card-contactless"></i>
                    </div>
                    
                    <div class="card-balance-label">
                        <span>Available Balance</span>
                        <i class="fas fa-eye toggle-eye" id="toggleBalanceBtn" onclick="toggleBalance()" title="Show/Hide Balance"></i>
                    </div>
                    
                    <div class="card-balance" id="displayBalance" data-balance="₹<?php echo number_format($balance, 2); ?>">
                        ₹<?php echo number_format($balance, 2); ?>
                    </div>
                    
                    <div class="card-number">
                        <small>USER ID</small> <?php echo htmlspecialchars($member_code); ?>
                    </div>
                    
                    <div class="card-bottom">
                        <div class="card-holder"><?php echo htmlspecialchars($member_name); ?></div>
                        <div>
                            <?php if ($cant_logo): ?>
                                <img src="<?= $cant_logo ?>" alt="Canteen Logo" class="canteen-logo-img">
                            <?php else: ?>
                                <span class="font-weight-bold font-italic"><?= $cant_name ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <button class="btn btn-primary rounded-pill font-weight-bold px-5 py-2 shadow-sm" style="background: linear-gradient(135deg, #0062E6, #33AEFF); border: none;" onclick="alert('Online recharge coming soon!');">
                    <i class="fas fa-plus-circle mr-2"></i> Add Money Online
                </button>
            </div>
        </div>

        <div class="tab-pane fade <?= $active_tab === 'passbook' ? 'show active' : '' ?>" id="tab-passbook" role="tabpanel" aria-labelledby="passbook-tab">
            
            <div class="filter-container mt-2">
                <div class="filter-pills">
                    <div class="filter-pill <?= $filter === 'last10' ? 'active' : '' ?>" onclick="applyFilter('last10')">
                        <i class="fas fa-list-ol mr-1"></i> Last 10
                    </div>
                    <div class="filter-pill <?= $filter === '7days' ? 'active' : '' ?>" onclick="applyFilter('7days')">
                        <i class="fas fa-calendar-week mr-1"></i> 7 Days
                    </div>
                    <div class="filter-pill <?= $filter === '1month' ? 'active' : '' ?>" onclick="applyFilter('1month')">
                        <i class="fas fa-calendar-alt mr-1"></i> 1 Month
                    </div>
                    <div class="filter-pill <?= $filter === 'custom' ? 'active' : '' ?>" onclick="toggleCustomDate()">
                        <i class="fas fa-sliders-h mr-1"></i> Custom
                    </div>
                </div>

                <form id="filterForm" method="GET" action="wallet.php">
                    <input type="hidden" name="filter" id="form_filter" value="<?= htmlspecialchars($filter) ?>">
                    <input type="hidden" name="start_date" id="form_start_date" value="<?= htmlspecialchars($start_date) ?>">
                    <input type="hidden" name="end_date" id="form_end_date" value="<?= htmlspecialchars($end_date) ?>">
                    
                    <div id="customDateSection" class="custom-date-wrapper <?= $filter === 'custom' ? 'show' : '' ?> align-items-end flex-wrap gap-2">
                        <div class="flex-fill pr-1">
                            <label class="small text-muted font-weight-bold text-uppercase mb-1">From</label>
                            <input type="date" id="ui_start_date" class="modern-date-input" value="<?= htmlspecialchars($start_date) ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="flex-fill px-1">
                            <label class="small text-muted font-weight-bold text-uppercase mb-1">To</label>
                            <input type="date" id="ui_end_date" class="modern-date-input" value="<?= htmlspecialchars($end_date) ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="pl-1 mt-3 mt-sm-0">
                            <button type="button" class="btn btn-primary font-weight-bold rounded-lg px-4" onclick="submitCustomDate()" style="height: 44px; background: #0062E6; border: none;">
                                Apply
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modern-card">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-history text-primary mr-2"></i> Statement</h6>
                    <?php if ($filter !== 'last10'): ?>
                        <span class="badge badge-light border text-muted px-2 py-1">
                            <?= count($transactions) ?> found
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="card-body p-0">
                    
                    <div id="skeleton-container" class="p-3">
                        <?php for($i=0; $i<5; $i++): ?>
                            <div class="skel-card skeleton w-100"></div>
                        <?php endfor; ?>
                    </div>

                    <div id="actual-content" class="d-none">
                        <?php if (empty($transactions)): ?>
                            <div class="text-center py-5">
                                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                    <i class="fas fa-receipt fa-2x text-muted opacity-50"></i>
                                </div>
                                <h6 class="font-weight-bold text-dark mb-1">No transactions found</h6>
                                <p class="text-muted small mb-0">Your passbook history for this period is empty.</p>
                            </div>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($transactions as $t): 
                                    $is_credit = ($t['type'] === 'credit');
                                    $icon_class = $is_credit ? 'fa-arrow-down' : 'fa-arrow-up';
                                    $bg_class = $is_credit ? 'bg-soft-success' : 'bg-soft-danger';
                                    $text_class = $is_credit ? 'text-success' : 'text-danger';
                                    $sign = $is_credit ? '+' : '-';
                                ?>
                                <li class="tx-item px-4 py-3 d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="icon-circle <?= $bg_class ?> mr-3 shadow-sm flex-shrink-0">
                                            <i class="fas <?= $icon_class ?>"></i>
                                        </div>
                                        <div>
                                            <p class="mb-0 font-weight-bold text-dark" style="font-size: 0.95rem; line-height: 1.2;"><?= htmlspecialchars($t['description']) ?></p>
                                            <small class="text-muted"><i class="far fa-clock mr-1"></i> <?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></small>
                                        </div>
                                    </div>
                                    <div class="text-right pl-3 flex-shrink-0">
                                        <span class="font-weight-bold h6 mb-0 <?= $text_class ?>">
                                            <?= $sign ?> ₹<?= number_format($t['amount'], 2) ?>
                                        </span>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Show/Hide Balance Logic for ATM Card
let isBalanceVisible = true;
function toggleBalance() {
    const balanceEl = document.getElementById('displayBalance');
    const iconEl = document.getElementById('toggleBalanceBtn');
    
    if (isBalanceVisible) {
        balanceEl.innerHTML = '₹ * * * * *';
        iconEl.classList.remove('fa-eye');
        iconEl.classList.add('fa-eye-slash');
    } else {
        balanceEl.innerHTML = balanceEl.getAttribute('data-balance');
        iconEl.classList.remove('fa-eye-slash');
        iconEl.classList.add('fa-eye');
    }
    isBalanceVisible = !isBalanceVisible;
}

// Passbook Filter Logic
function applyFilter(filterType) {
    document.getElementById('form_filter').value = filterType;
    showLoaderAndSubmit();
}

function toggleCustomDate() {
    const section = document.getElementById('customDateSection');
    section.classList.toggle('show');
    
    document.querySelectorAll('.filter-pill').forEach(el => el.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('form_filter').value = 'custom';
}

function submitCustomDate() {
    let start = document.getElementById('ui_start_date').value;
    let end = document.getElementById('ui_end_date').value;
    
    if(!start || !end) {
        alert("Please select both start and end dates.");
        return;
    }

    document.getElementById('form_start_date').value = start;
    document.getElementById('form_end_date').value = end;
    showLoaderAndSubmit();
}

function showLoaderAndSubmit() {
    const globalLoader = document.getElementById('loading-overlay');
    if(globalLoader) { globalLoader.style.display = 'flex'; globalLoader.classList.remove('d-none'); }
    document.getElementById('filterForm').submit();
}

// Skeleton Loader & UI initialization
window.addEventListener('DOMContentLoaded', () => {
    
    // Hide global overlay initially
    const globalLoader = document.getElementById('loading-overlay');
    if (globalLoader) {
        globalLoader.style.display = 'none';
        globalLoader.classList.add('d-none');
    }

    // Passbook Skeleton Loader Transition
    setTimeout(() => {
        let skel = document.getElementById('skeleton-container');
        let content = document.getElementById('actual-content');
        
        if(skel) skel.classList.add('d-none');
        
        if(content) {
            content.classList.remove('d-none');
            setTimeout(() => {
                content.classList.add('show-content');
            }, 50);
        }
    }, 400); 
});
</script>

<?php include 'footer.php'; ?>