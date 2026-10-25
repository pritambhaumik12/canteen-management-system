<?php
include 'header.php';
require_once '../includes/db.php';

// ==========================================
// DATE FILTER LOGIC
// ==========================================
$date_from = date('Y-m-d');
$date_to = date('Y-m-d');
$range_text = "Today";

if (isset($_GET['from']) && isset($_GET['to']) && !empty($_GET['from']) && !empty($_GET['to'])) {
    $date_from = $_GET['from'];
    $date_to = $_GET['to'];
    if ($date_from == $date_to) {
        $range_text = date('d M Y', strtotime($date_from));
    } else {
        $range_text = date('d M Y', strtotime($date_from)) . " to " . date('d M Y', strtotime($date_to));
    }
} else if (isset($_GET['range'])) {
    $range = $_GET['range'];
    if ($range == '7d') {
        $date_from = date('Y-m-d', strtotime('-6 days'));
        $range_text = "Last 7 Days";
    } else if ($range == '1m') {
        $date_from = date('Y-m-d', strtotime('-30 days'));
        $range_text = "Last 30 Days";
    }
}

$start_datetime = $date_from . " 00:00:00";
$end_datetime = $date_to . " 23:59:59";

// ==========================================
// FETCH SALES DATA (With Members Join)
// ==========================================
$sql = "
    SELECT o.*, u.name as member_name, u.member_id as member_uid 
    FROM orders o 
    LEFT JOIN members u ON o.member_id = u.id 
    WHERE o.created_at BETWEEN ? AND ? 
    ORDER BY o.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_datetime, $end_datetime]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals and group by date for the algorithm
$total_sales = 0;
$valid_orders_count = 0;
$daily_sales_map = [];

foreach ($orders as $order) {
    if ($order['status'] !== 'cancelled') {
        $total_sales += $order['total_amount'];
        $valid_orders_count++;
        
        $date_only = date('Y-m-d', strtotime($order['created_at']));
        if (!isset($daily_sales_map[$date_only])) {
            $daily_sales_map[$date_only] = 0;
        }
        $daily_sales_map[$date_only] += $order['total_amount'];
    }
}

// Convert map to indexed array for Divide and Conquer
$daily_sales_array = [];
foreach ($daily_sales_map as $date => $amount) {
    $daily_sales_array[] = ['date' => $date, 'amount' => $amount];
}

// ==========================================
// DIVIDE & CONQUER ALGORITHM: Find Max & Min Sales
// ==========================================
function findMinMaxSalesDivideConquer($arr, $low, $high) {
    if ($low == $high) {
        return ['min' => $arr[$low], 'max' => $arr[$low]];
    }
    if ($high == $low + 1) {
        if ($arr[$low]['amount'] > $arr[$high]['amount']) {
            return ['min' => $arr[$high], 'max' => $arr[$low]];
        } else {
            return ['min' => $arr[$low], 'max' => $arr[$high]];
        }
    }
    
    $mid = intdiv($low + $high, 2);
    $left = findMinMaxSalesDivideConquer($arr, $low, $mid);
    $right = findMinMaxSalesDivideConquer($arr, $mid + 1, $high);
    
    $result = [];
    $result['min'] = ($left['min']['amount'] < $right['min']['amount']) ? $left['min'] : $right['min'];
    $result['max'] = ($left['max']['amount'] > $right['max']['amount']) ? $left['max'] : $right['max'];
    
    return $result;
}

$min_sales_day = null;
$max_sales_day = null;

if (count($daily_sales_array) > 0) {
    $algo_result = findMinMaxSalesDivideConquer($daily_sales_array, 0, count($daily_sales_array) - 1);
    $min_sales_day = $algo_result['min'];
    $max_sales_day = $algo_result['max'];
}

// ==========================================
// FETCH MENU-WISE SALES DATA
// ==========================================
$sql_menu = "
    SELECT m.name, SUM(oi.quantity) as total_quantity, SUM(oi.amount) as total_revenue
    FROM order_items oi
    JOIN menu m ON oi.menu_id = m.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ? AND o.status != 'cancelled' AND oi.status != 'cancelled'
    GROUP BY m.name
    ORDER BY total_revenue DESC
";
$stmt_menu = $pdo->prepare($sql_menu);
$stmt_menu->execute([$start_datetime, $end_datetime]);
$menu_sales = $stmt_menu->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

    :root {
        --font-family: 'Outfit', sans-serif;
        --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        --info-gradient: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        --glass-bg: rgba(255, 255, 255, 0.95);
        --skeleton-base: #e2e8f0;
        --skeleton-highlight: #f8fafc;
    }

    body { font-family: var(--font-family); background-color: #f8fafc; }

    /* Interactive Cards */
    .interactive-card {
        background: var(--glass-bg); border: 1px solid rgba(0,0,0,0.05);
        border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    /* Summary Stat Cards */
    .stat-card { border-radius: 16px; color: white; padding: 25px; position: relative; overflow: hidden; border: none; }
    .stat-card i.bg-icon { position: absolute; right: 20px; bottom: -10px; font-size: 5rem; opacity: 0.15; transform: rotate(-15deg); }
    .stat-card.bg-primary-custom { background: var(--primary-gradient); }
    .stat-card.bg-info-custom { background: var(--info-gradient); }
    .stat-card.bg-success-custom { background: var(--success-gradient); }
    .stat-card.bg-danger-custom { background: var(--danger-gradient); }
    
    /* Table Styling */
    .table-elegant th { 
        border-top: none; background-color: #f1f5f9; text-transform: uppercase; font-size: 0.8rem; 
        letter-spacing: 1px; color: #64748b; font-weight: 700; 
    }
    .table-elegant td { vertical-align: middle; font-weight: 500; color: #334155; border-bottom: 1px dashed #e2e8f0; padding: 15px 12px; }
    .table-elegant tbody tr:hover { background-color: #f8fafc; }
    
    /* Controls */
    .filter-btn { border-radius: 20px; padding: 6px 16px; font-size: 0.85rem; font-weight: 600; margin-right: 5px; transition: all 0.2s; }
    .search-input-wrapper { position: relative; width: 100%; max-width: 300px; }
    .search-input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    .search-input { padding-left: 40px; border-radius: 20px; border: 2px solid #e2e8f0; height: 38px; transition: border-color 0.3s; box-shadow: none; font-size: 0.9rem; }
    .search-input:focus { border-color: #6366f1; outline: none; }
    
    /* Algo Badge */
    .algo-badge {
        font-size: 0.7rem; letter-spacing: 0.5px; padding: 3px 8px; border-radius: 12px;
        background: rgba(255,255,255,0.2); backdrop-filter: blur(5px);
    }

    /* Skeleton Animations */
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    @keyframes slideUpFade { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    
    .skeleton-box { background: var(--skeleton-base); background-image: linear-gradient(90deg, var(--skeleton-base) 0px, var(--skeleton-highlight) 50%, var(--skeleton-base) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite linear; border-radius: 12px; }
    .sk-header { height: 40px; width: 30%; margin-bottom: 20px; }
    .sk-filter { height: 60px; width: 100%; margin-bottom: 25px; }
    .sk-card { height: 130px; width: 100%; margin-bottom: 20px; }
    .sk-table { height: 400px; width: 100%; }

    #page-content { animation: slideUpFade 0.5s ease forwards; }
</style>

<div id="page-skeleton">
    <div class="sk-header skeleton-box"></div>
    <div class="sk-filter skeleton-box"></div>
    <div class="row">
        <div class="col-md-3"><div class="sk-card skeleton-box"></div></div>
        <div class="col-md-3"><div class="sk-card skeleton-box"></div></div>
        <div class="col-md-3"><div class="sk-card skeleton-box"></div></div>
        <div class="col-md-3"><div class="sk-card skeleton-box"></div></div>
    </div>
    <div class="row">
        <div class="col-md-5"><div class="sk-table skeleton-box"></div></div>
        <div class="col-md-7"><div class="sk-table skeleton-box"></div></div>
    </div>
</div>

<div id="page-content" style="display: none;">
    <div class="d-sm-flex justify-content-between align-items-center mb-4 mt-2">
        <div>
            <h3 class="text-dark font-weight-bold mb-0">Sales Analytics</h3>
            <p class="text-muted m-0 small">Algorithm-powered revenue and order management</p>
        </div>
        <span class="badge badge-light border text-primary px-3 py-2 font-weight-bold shadow-sm" style="font-size: 0.95rem;">
            <i class="far fa-calendar-alt mr-1"></i> <?php echo htmlspecialchars($range_text); ?>
        </span>
    </div>

    <div class="interactive-card p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="mb-2 mb-md-0 d-flex align-items-center">
            <span class="text-muted mr-3 font-weight-bold small text-uppercase">Presets:</span>
            <a href="?range=today" class="btn filter-btn btn-outline-primary <?php echo (isset($_GET['range']) && $_GET['range'] == 'today') ? 'active' : (!isset($_GET['range']) && !isset($_GET['from']) ? 'active' : ''); ?>">Today</a>
            <a href="?range=7d" class="btn filter-btn btn-outline-primary <?php echo (isset($_GET['range']) && $_GET['range'] == '7d') ? 'active' : ''; ?>">7 Days</a>
            <a href="?range=1m" class="btn filter-btn btn-outline-primary <?php echo (isset($_GET['range']) && $_GET['range'] == '1m') ? 'active' : ''; ?>">30 Days</a>
        </div>
        <form action="sales_reports.php" method="get" class="form-inline mb-2 mb-md-0">
            <input type="date" class="form-control form-control-sm mr-2 rounded-pill px-3" name="from" value="<?php echo htmlspecialchars($date_from); ?>" required>
            <span class="mr-2 text-muted font-weight-bold small">to</span>
            <input type="date" class="form-control form-control-sm mr-2 rounded-pill px-3" name="to" value="<?php echo htmlspecialchars($date_to); ?>" required>
            <button type="submit" class="btn btn-sm btn-dark rounded-pill px-3 font-weight-bold"><i class="fas fa-filter mr-1"></i> Apply</button>
        </form>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card bg-primary-custom shadow-sm h-100">
                <i class="fas fa-wallet bg-icon"></i>
                <h6 class="text-uppercase font-weight-bold text-white-50 mb-1" style="letter-spacing: 1px;">Total Revenue</h6>
                <h2 class="font-weight-bold mb-0">₹<?php echo number_format($total_sales, 2); ?></h2>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card bg-info-custom shadow-sm h-100">
                <i class="fas fa-receipt bg-icon"></i>
                <h6 class="text-uppercase font-weight-bold text-white-50 mb-1" style="letter-spacing: 1px;">Orders Processed</h6>
                <h2 class="font-weight-bold mb-0"><?php echo $valid_orders_count; ?></h2>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card bg-success-custom shadow-sm h-100">
                <i class="fas fa-chart-line bg-icon"></i>
                <div class="d-flex justify-content-between align-items-start">
                    <h6 class="text-uppercase font-weight-bold text-white-50 mb-1" style="letter-spacing: 1px;">Peak Sales Day</h6>
                    <span class="text-uppercase font-weight-bold text-white algo-badge"><i class="fas fa-brain mr-1"></i> D&C Algo</span>
                </div>
                <h3 class="font-weight-bold mb-0 mt-1">₹<?php echo $max_sales_day ? number_format($max_sales_day['amount'], 2) : '0.00'; ?></h3>
                <div class="small mt-1 text-white-50 font-weight-bold"><?php echo $max_sales_day ? date('d M Y', strtotime($max_sales_day['date'])) : 'N/A'; ?></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card bg-danger-custom shadow-sm h-100">
                <i class="fas fa-chart-area bg-icon" style="transform: rotate(180deg) translate(-20px, 10px);"></i>
                <div class="d-flex justify-content-between align-items-start">
                    <h6 class="text-uppercase font-weight-bold text-white-50 mb-1" style="letter-spacing: 1px;">Lowest Sales Day</h6>
                    <span class="text-uppercase font-weight-bold text-white algo-badge"><i class="fas fa-brain mr-1"></i> D&C Algo</span>
                </div>
                <h3 class="font-weight-bold mb-0 mt-1">₹<?php echo $min_sales_day ? number_format($min_sales_day['amount'], 2) : '0.00'; ?></h3>
                <div class="small mt-1 text-white-50 font-weight-bold"><?php echo $min_sales_day ? date('d M Y', strtotime($min_sales_day['date'])) : 'N/A'; ?></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="interactive-card h-100 overflow-hidden">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-chart-pie text-primary mr-2"></i> Menu Performance Breakdown</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-elegant mb-0">
                            <thead class="sticky-top shadow-sm">
                                <tr><th class="pl-4">Item Name</th><th class="text-center">Qty Sold</th><th class="text-right pr-4">Revenue</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($menu_sales)): ?>
                                    <tr><td colspan="3" class="text-center py-5 text-muted font-weight-bold"><i class="fas fa-box-open fa-2x mb-2 d-block opacity-50"></i>No items sold in this period.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($menu_sales as $sale): ?>
                                    <tr>
                                        <td class="pl-4 font-weight-bold text-dark"><?php echo htmlspecialchars($sale['name']); ?></td>
                                        <td class="text-center"><span class="badge badge-primary px-2 py-1"><?php echo $sale['total_quantity']; ?></span></td>
                                        <td class="text-right pr-4 text-success font-weight-bold">₹<?php echo number_format($sale['total_revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-4">
            <div class="interactive-card h-100 overflow-hidden">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-list-alt text-primary mr-2"></i> Transaction Log</h6>
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="orderSearch" class="form-control search-input" placeholder="Search orders..." onkeyup="filterOrders()">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-elegant mb-0 text-nowrap" id="ordersTable">
                            <thead class="sticky-top shadow-sm">
                                <tr>
                                    <th class="pl-4">Date & Time</th>
                                    <th>Coupon ID</th>
                                    <th>Member</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-center pr-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                    <tr id="emptyOrdersRow"><td colspan="5" class="text-center py-5 text-muted font-weight-bold"><i class="fas fa-receipt fa-2x mb-2 d-block opacity-50"></i>No orders found for this date range.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                    <tr class="searchable-order">
                                        <td class="pl-4">
                                            <div class="text-dark font-weight-bold"><?php echo date('h:i A', strtotime($order['created_at'])); ?></div>
                                            <div class="small text-muted"><?php echo date('d M Y', strtotime($order['created_at'])); ?></div>
                                        </td>
                                        <td><span class="badge badge-dark font-family-monospace" style="font-size: 0.9rem;">#<?php echo htmlspecialchars($order['coupon_id']); ?></span></td>
                                        <td>
                                            <div class="text-dark font-weight-bold"><?php echo htmlspecialchars($order['member_name'] ?? 'Unknown'); ?></div>
                                            <div class="small text-muted font-weight-500 mt-1"><i class="fas fa-id-badge mr-1"></i><?php echo htmlspecialchars($order['member_uid'] ?? 'N/A'); ?></div>
                                        </td>
                                        <td class="text-right font-weight-bold <?php echo in_array($order['status'], ['cancelled', 'refunded']) ? 'text-muted text-decoration-line-through' : 'text-success'; ?>">
                                            ₹<?php echo number_format($order['total_amount'], 2); ?>
                                        </td>
                                        <td class="text-center pr-4">
                                            <?php 
                                                // Dynamic UI Status text conversion
                                                $statusClass = 'secondary';
                                                $statusText = str_replace('_', ' ', $order['status']);
                                                
                                                if ($order['status'] === 'fully_served') {
                                                    $statusClass = 'success';
                                                } elseif ($order['status'] === 'partially_served') {
                                                    $statusClass = 'info';
                                                } elseif ($order['status'] === 'pending') {
                                                    $statusClass = 'primary';
                                                } elseif ($order['status'] === 'cancelled') {
                                                    $statusClass = 'danger';
                                                    $statusText = 'Refunded / Cancelled';
                                                }
                                            ?>
                                            <span class="badge badge-<?php echo $statusClass; ?> px-2 py-1 text-uppercase" style="font-size: 0.75rem;">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(() => {
        const skeleton = document.getElementById('page-skeleton');
        const content = document.getElementById('page-content');
        if (skeleton && content) {
            skeleton.style.transition = 'opacity 0.4s ease';
            skeleton.style.opacity = '0';
            setTimeout(() => { skeleton.style.display = 'none'; content.style.display = 'block'; }, 400);
        }
    }, 500); 
});

function filterOrders() {
    const query = document.getElementById('orderSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.searchable-order');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowText = row.innerText.toLowerCase();
        if (rowText.includes(query)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const emptyRow = document.getElementById('emptyOrdersRow');
    if (emptyRow) {
        if (visibleCount === 0 && rows.length > 0) {
            emptyRow.innerHTML = '<td colspan="5" class="text-center py-5 text-muted font-weight-bold"><i class="fas fa-search fa-2x mb-2 d-block opacity-50"></i>No matching orders found.</td>';
            emptyRow.style.display = '';
        } else if (visibleCount > 0) {
            emptyRow.style.display = 'none';
        }
    }
}
</script>

<?php include 'footer.php'; ?>