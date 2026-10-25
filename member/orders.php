<?php
ob_start();
include 'header.php';

$member_id = $_SESSION['id'];

// Define defaults (Last 3 days)
$today = date('Y-m-d');
$default_start = date('Y-m-d', strtotime('-2 days')); // Today + 2 previous days = 3 days

// Get filter parameters or use defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $today;

// Sanitize and Validate Dates
$start_ts = strtotime($start_date);
$end_ts = strtotime($end_date);

// If dates are invalid or start is after end, reset to default
if (!$start_ts || !$end_ts || $start_ts > $end_ts) {
    $start_date = $default_start;
    $end_date = $today;
    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
}

// Enforce maximum 1 month (30 days) range on the backend as a failsafe
$diff_days = round(($end_ts - $start_ts) / (60 * 60 * 24));
if ($diff_days > 30) {
    // If range exceeds 30 days, adjust start date to 30 days before end date
    $start_date = date('Y-m-d', strtotime('-30 days', $end_ts));
}

// Determine active filter button for UI
$active_filter = 'custom';
if ($end_date === $today) {
    if ($start_date === date('Y-m-d', strtotime('-2 days'))) $active_filter = '3days';
    elseif ($start_date === date('Y-m-d', strtotime('-6 days'))) $active_filter = '7days';
    elseif ($start_date === date('Y-m-d', strtotime('-30 days'))) $active_filter = '1month';
}

// Fetch filtered orders for the logged-in member
$sql = "SELECT * FROM orders WHERE member_id = ? AND DATE(created_at) >= ? AND DATE(created_at) <= ? ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$member_id, $start_date, $end_date]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
</div>

<style>
    /* AGGRESSIVE GAP KILLER: Force hides the empty container from header.php */
    body > .container.mt-4.mb-5:first-of-type { display: none !important; margin: 0 !important; padding: 0 !important; height: 0 !important; border: none !important; }
</style>

<script>
    /* JAVASCRIPT FAILSAFE: Physically removes the gap element from the DOM */
    document.addEventListener("DOMContentLoaded", function() {
        let containers = document.querySelectorAll('body > .container.mt-4.mb-5');
        if(containers.length > 0 && containers[0].innerHTML.trim() === '') {
            containers[0].remove();
        }
    });
</script>

<style>
    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; padding-bottom: 90px; }

    /* Vibrant Full-Width Banner */
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

    /* Filter Section */
    .filter-container {
        background: #fff;
        border-radius: 16px;
        padding: 15px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        margin-bottom: 25px;
    }

    .filter-pills {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 5px;
        scrollbar-width: none; /* Firefox */
    }
    .filter-pills::-webkit-scrollbar { display: none; } /* Chrome */

    .filter-pill {
        white-space: nowrap;
        padding: 8px 18px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #6c757d;
        background: #f1f3f5;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .filter-pill.active {
        background: #e3f2fd;
        color: #0062E6;
        border-color: #bbdefb;
    }
    .filter-pill:hover:not(.active) {
        background: #e9ecef;
    }

    .custom-date-wrapper {
        display: none;
        animation: slideDown 0.3s ease-out forwards;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px dashed #e9ecef;
    }
    .custom-date-wrapper.show {
        display: flex;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .date-input-group {
        position: relative;
        flex: 1;
    }
    .date-input-group label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #adb5bd;
        margin-bottom: 4px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .modern-date-input {
        width: 100%;
        padding: 10px 15px;
        border-radius: 10px;
        border: 1px solid #dee2e6;
        background: #f8f9fa;
        color: #495057;
        font-size: 0.9rem;
        transition: all 0.3s;
    }
    .modern-date-input:focus {
        border-color: #33AEFF;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(51, 174, 255, 0.1);
        outline: none;
    }
    .modern-date-input:disabled {
        background: #e9ecef;
        cursor: not-allowed;
        opacity: 0.7;
    }

    /* Modern UI Card Styling */
    .modern-card {
        border-radius: 16px;
        border: none;
        background: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .order-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }

    /* Status Badges */
    .status-badge {
        border-radius: 50px;
        padding: 6px 14px;
        font-weight: 600;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        letter-spacing: 0.3px;
    }
    .status-fully_served { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
    .status-partially_served { background: #fff8e1; color: #f57f17; border: 1px solid #ffecb3; }
    .status-pending { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
    .status-cancelled { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
    .status-refunded { background: #f3e5f5; color: #6a1b9a; border: 1px solid #e1bee7; }
    .status-partially_refunded { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }

    /* Buttons */
    .btn-view-coupon {
        background: rgba(0, 98, 230, 0.05);
        color: #0062E6;
        border: 1px solid rgba(0, 98, 230, 0.2);
        border-radius: 50px;
        font-weight: 600;
        padding: 8px 20px;
        transition: all 0.3s ease;
    }
    .btn-view-coupon:hover:not(:disabled) {
        background: #0062E6;
        color: white;
        box-shadow: 0 4px 12px rgba(0, 98, 230, 0.2);
    }
    .btn-disabled-status {
        background: #f8f9fa;
        color: #adb5bd;
        border: 1px solid #e9ecef;
        border-radius: 50px;
        font-weight: 600;
        padding: 8px 20px;
        cursor: not-allowed;
    }

    /* Skeleton Loader */
    .skeleton { background-color: #e2e5e7; background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0)); background-size: 40px 100%; background-repeat: no-repeat; animation: shine 1.5s ease infinite; }
    @keyframes shine { to { background-position: right -40px top 0; } }
    .skel-card { height: 120px; border-radius: 16px; margin-bottom: 15px; }

    #actual-content { opacity: 0; transition: opacity 0.4s ease; }
    #actual-content.show-content { opacity: 1; }

    @media (max-width: 576px) {
        .order-meta-info { flex-direction: column; align-items: flex-start !important; }
        .order-meta-info .action-area { width: 100%; margin-top: 15px; border-top: 1px solid #f1f3f5; padding-top: 15px; display: flex; justify-content: space-between; align-items: center; }
        .custom-date-wrapper { flex-direction: column; gap: 10px; }
        .date-btn-apply { width: 100%; margin-top: 5px; }
    }
</style>

<div class="container-fluid px-0">
    <div class="greeting-section text-center text-md-left">
        <div class="container d-flex align-items-center justify-content-center justify-content-md-start">
            <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center mr-3 shadow-sm" style="width: 50px; height: 50px;">
                <i class="fas fa-receipt fa-lg"></i>
            </div>
            <div>
                <h4 class="font-weight-bold mb-0">Order History</h4>
                <p class="mb-0 text-light" style="font-size: 0.95rem;">Track your past and current cravings.</p>
            </div>
        </div>
    </div>
</div>

<div class="container mt-2 mb-5" style="max-width: 800px;">

    <div class="filter-container">
        <div class="filter-pills">
            <div class="filter-pill <?php echo $active_filter === '3days' ? 'active' : ''; ?>" onclick="applyQuickFilter(2)">
                <i class="fas fa-clock mr-1"></i> Last 3 Days
            </div>
            <div class="filter-pill <?php echo $active_filter === '7days' ? 'active' : ''; ?>" onclick="applyQuickFilter(6)">
                <i class="fas fa-calendar-week mr-1"></i> Last 7 Days
            </div>
            <div class="filter-pill <?php echo $active_filter === '1month' ? 'active' : ''; ?>" onclick="applyQuickFilter(30)">
                <i class="fas fa-calendar-alt mr-1"></i> 1 Month
            </div>
            <div class="filter-pill <?php echo $active_filter === 'custom' ? 'active' : ''; ?>" onclick="toggleCustomDate()">
                <i class="fas fa-sliders-h mr-1"></i> Custom
            </div>
        </div>

        <form id="filterForm" method="GET" action="orders.php">
            <input type="hidden" name="start_date" id="form_start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            <input type="hidden" name="end_date" id="form_end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            
            <div id="customDateSection" class="custom-date-wrapper <?php echo $active_filter === 'custom' ? 'show' : ''; ?> align-items-end gap-3">
                <div class="date-input-group pr-md-2">
                    <label>From Date</label>
                    <input type="date" id="ui_start_date" class="modern-date-input" value="<?php echo htmlspecialchars($start_date); ?>" onchange="updateDateConstraints()">
                </div>
                <div class="date-input-group pl-md-2 pr-md-3">
                    <label>To Date <small class="text-lowercase">(Max 1 month)</small></label>
                    <input type="date" id="ui_end_date" class="modern-date-input" value="<?php echo htmlspecialchars($end_date); ?>" onchange="updateDateConstraints()">
                </div>
                <button type="button" class="btn btn-primary date-btn-apply py-2 px-4 rounded-lg font-weight-bold" onclick="submitCustomDate()" style="background: #0062E6; border: none; height: 44px;">
                    Apply
                </button>
            </div>
        </form>
    </div>

    <div id="skeleton-container">
        <?php for($i=0; $i<4; $i++): ?>
            <div class="modern-card skel-card skeleton"></div>
        <?php endfor; ?>
    </div>

    <div id="actual-content" class="d-none">
        
        <?php if (empty($orders)): ?>
            <div class="modern-card text-center py-5">
                <div class="card-body py-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                        <i class="fas fa-box-open fa-3x text-muted opacity-50"></i>
                    </div>
                    <h4 class="font-weight-bold text-dark">No orders found</h4>
                    <p class="text-muted mb-4">No orders match the selected date range. Let's change that!</p>
                    <a href="dashboard.php" class="btn btn-primary rounded-pill px-5 py-2 font-weight-bold shadow-sm" style="background: linear-gradient(135deg, #0062E6, #33AEFF); border: none;">
                        <i class="fas fa-utensils mr-2"></i> Explore Menu
                    </a>
                </div>
            </div>
        <?php else: ?>
            
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <small class="text-muted font-weight-bold text-uppercase tracking-wide">
                    Showing <?php echo count($orders); ?> order(s)
                </small>
                <small class="text-muted bg-light px-2 py-1 rounded border">
                    <?php echo date('d M', strtotime($start_date)); ?> - <?php echo date('d M', strtotime($end_date)); ?>
                </small>
            </div>

            <?php foreach ($orders as $order): 
                $order_date = new DateTime($order['created_at']);
                $today_dt = new DateTime('today');
                $is_today = $order_date->format('Y-m-d') === $today_dt->format('Y-m-d');
                $status_class = 'status-' . (empty($order['status']) ? 'pending' : $order['status']);
                $status_text = empty($order['status']) ? 'Pending' : ucfirst(str_replace('_', ' ', $order['status']));
                
                // Icon logic based on status
                $status_icon = 'fa-clock';
                if ($order['status'] === 'fully_served') $status_icon = 'fa-check-circle';
                if ($order['status'] === 'partially_served') $status_icon = 'fa-adjust';
                if ($order['status'] === 'cancelled') $status_icon = 'fa-times-circle';
                if ($order['status'] === 'refunded') $status_icon = 'fa-hand-holding-usd';
                if ($order['status'] === 'partially_refunded') $status_icon = 'fa-file-invoice-dollar';
            ?>
            <div class="modern-card order-card mb-3 p-3 p-md-4 border">
                <div class="d-flex justify-content-between align-items-center order-meta-info">
                    
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge <?php echo $is_today ? 'badge-primary bg-primary text-white' : 'badge-light text-muted border'; ?> mr-2 px-2 py-1" style="font-size: 0.75rem;">
                                <?php echo $is_today ? 'TODAY' : $order_date->format('d M, Y'); ?>
                            </span>
                            <span class="text-muted small"><i class="far fa-clock mr-1"></i> <?php echo $order_date->format('h:i A'); ?></span>
                        </div>
                        
                        <div class="d-flex align-items-baseline mb-1">
                            <h5 class="font-weight-bold text-dark mb-0 mr-2">
                                Order #<?php echo htmlspecialchars($order['coupon_id']); ?>
                            </h5>
                        </div>
                        
                        <div class="d-flex align-items-center mt-2">
                            <span class="font-weight-bold text-dark h5 mb-0 mr-3">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <i class="fas <?php echo $status_icon; ?> mr-1"></i> <?php echo $status_text; ?>
                            </span>
                        </div>
                    </div>

                    <div class="action-area text-right">
                        <?php if (in_array($order['status'], ['refunded', 'cancelled'])): ?>
                            <button class="btn btn-disabled-status text-nowrap" disabled>
                                <i class="fas fa-ban mr-1"></i> Unavailable
                            </button>
                        <?php elseif ($is_today): ?>
                            <form action="order_coupon.php" method="POST" class="m-0 p-0 d-inline-block">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" class="btn btn-view-coupon text-nowrap">
                                    <i class="fas fa-ticket-alt mr-1"></i> View Coupon
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="d-flex flex-column align-items-end align-items-sm-center">
                                <span class="badge badge-light border text-muted px-3 py-2 rounded-pill mb-1">
                                    <i class="fas fa-history mr-1"></i> Expired
                                </span>
                                <small class="text-muted" style="font-size: 0.7rem;">Coupons valid for 1 day</small>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="text-center mt-4 mb-2">
                <i class="fas fa-ellipsis-h text-muted opacity-50"></i>
            </div>
            
        <?php endif; ?>
    </div>
</div>

<script>
// Format date as YYYY-MM-DD
function formatDate(date) {
    const d = new Date(date);
    let month = '' + (d.getMonth() + 1);
    let day = '' + d.getDate();
    const year = d.getFullYear();
    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;
    return [year, month, day].join('-');
}

// Dynamically disable calendar dates outside the 1-month range
function updateDateConstraints() {
    const startInput = document.getElementById('ui_start_date');
    const endInput = document.getElementById('ui_end_date');
    const todayStr = formatDate(new Date());

    if (startInput.value) {
        let startDate = new Date(startInput.value);
        let maxEndDate = new Date(startDate);
        maxEndDate.setDate(startDate.getDate() + 30); // Max 30 days ahead
        let maxEndStr = formatDate(maxEndDate);
        
        // End date cannot be before start date, nor further than 30 days, nor in the future
        endInput.min = startInput.value;
        endInput.max = maxEndStr > todayStr ? todayStr : maxEndStr;
        
        // Auto-correct end date if it falls out of bounds
        if(endInput.value > endInput.max) endInput.value = endInput.max;
        if(endInput.value < endInput.min) endInput.value = endInput.min;
    }

    if (endInput.value) {
        let endDate = new Date(endInput.value);
        let minStartDate = new Date(endDate);
        minStartDate.setDate(endDate.getDate() - 30); // Max 30 days behind
        
        // Start date cannot be after end date, nor further back than 30 days
        startInput.max = endInput.value;
        startInput.min = formatDate(minStartDate);
        
        // Auto-correct start date if it falls out of bounds
        if(startInput.value > startInput.max) startInput.value = startInput.max;
        if(startInput.value < startInput.min) startInput.value = startInput.min;
    }
}

function applyQuickFilter(daysBack) {
    const today = new Date();
    const startDate = new Date(today);
    startDate.setDate(today.getDate() - daysBack);

    document.getElementById('form_end_date').value = formatDate(today);
    document.getElementById('form_start_date').value = formatDate(startDate);
    
    // Show loading overlay
    const globalLoader = document.getElementById('loading-overlay');
    if(globalLoader) { globalLoader.style.display = 'flex'; globalLoader.classList.remove('d-none'); }
    
    document.getElementById('filterForm').submit();
}

function toggleCustomDate() {
    const section = document.getElementById('customDateSection');
    section.classList.toggle('show');
    
    // Update active pill visually
    document.querySelectorAll('.filter-pill').forEach(el => el.classList.remove('active'));
    event.currentTarget.classList.add('active');
    
    // Initialize date constraints when opening
    updateDateConstraints();
}

function submitCustomDate() {
    updateDateConstraints(); // Final validation check
    document.getElementById('form_start_date').value = document.getElementById('ui_start_date').value;
    document.getElementById('form_end_date').value = document.getElementById('ui_end_date').value;
    
    const globalLoader = document.getElementById('loading-overlay');
    if(globalLoader) { globalLoader.style.display = 'flex'; globalLoader.classList.remove('d-none'); }
    
    document.getElementById('filterForm').submit();
}

// Skeleton Loader & Initialization Logic
window.addEventListener('DOMContentLoaded', () => {
    // Initialize limits based on initial values on load
    updateDateConstraints();
    
    // Force kill global overlay if it was triggered by header.php initially
    const globalLoader = document.getElementById('loading-overlay');
    if (globalLoader) {
        globalLoader.style.display = 'none';
        globalLoader.classList.add('d-none');
    }

    // Handle soft skeleton load for the orders
    setTimeout(() => {
        let skel = document.getElementById('skeleton-container');
        let content = document.getElementById('actual-content');
        
        if(skel) skel.classList.add('d-none');
        
        if(content) {
            content.classList.remove('d-none');
            // Slight delay to allow display block to render before animating opacity
            setTimeout(() => {
                content.classList.add('show-content');
            }, 50);
        }
    }, 400);
});

window.addEventListener('load', () => {
    const globalLoader = document.getElementById('loading-overlay');
    if (globalLoader) globalLoader.style.display = 'none';
});
</script>

<?php include 'footer.php'; ?>