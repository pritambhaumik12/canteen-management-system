<?php
include 'header.php';
require_once '../includes/db.php';

// Default date range (today)
$date_from = date('Y-m-d');
$date_to = date('Y-m-d');
$range_text = "Last 24 Hours";
$active_range = '24h';

// Check for custom date range
if (isset($_GET['from']) && isset($_GET['to'])) {
    $date_from = $_GET['from'];
    $date_to = $_GET['to'];
    if ($date_from == $date_to) {
        $range_text = date('d M Y', strtotime($date_from));
    } else {
        $range_text = date('d M Y', strtotime($date_from)) . " to " . date('d M Y', strtotime($date_to));
    }
    $active_range = 'custom';
} else if (isset($_GET['range'])) {
    $range = $_GET['range'];
    $active_range = $range;
    if ($range == '7d') {
        $date_from = date('Y-m-d', strtotime('-6 days'));
        $range_text = "Last 7 Days";
    } else if ($range == '10d') {
        $date_from = date('Y-m-d', strtotime('-9 days'));
        $range_text = "Last 10 Days";
    } else if ($range == '1m') {
        $date_from = date('Y-m-d', strtotime('-1 month'));
        $range_text = "Last 1 Month";
    } else if ($range == '3m') {
        $date_from = date('Y-m-d', strtotime('-3 months'));
        $range_text = "Last 3 Months";
    }
}

$start_datetime = $date_from . " 00:00:00";
$end_datetime = $date_to . " 23:59:59";

// Fetch sales data
$sql = "SELECT * FROM orders WHERE created_at BETWEEN ? AND ? ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_datetime, $end_datetime]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals & Group by date for Divide and Conquer Analytics
$total_sales = 0;
$total_orders = count($orders);
$daily_sales_map = [];

foreach ($orders as $order) {
    $total_sales += $order['total_amount'];
    
    // Aggregate by date
    $date_key = date('Y-m-d', strtotime($order['created_at']));
    if (!isset($daily_sales_map[$date_key])) {
        $daily_sales_map[$date_key] = ['date' => $date_key, 'total' => 0];
    }
    $daily_sales_map[$date_key]['total'] += $order['total_amount'];
}

// Re-index for D&C
$daily_sales = array_values($daily_sales_map);

// DIVIDE AND CONQUER ALGORITHM: Max and Min Sales Spikes
function getAnalyticsSpikes($arr, $low, $high) {
    $result = ['max' => 0, 'min' => 0, 'max_date' => null, 'min_date' => null];

    if (count($arr) == 0) return $result;

    if ($low == $high) {
        $result['max'] = $arr[$low]['total'];
        $result['min'] = $arr[$low]['total'];
        $result['max_date'] = $arr[$low]['date'];
        $result['min_date'] = $arr[$low]['date'];
        return $result;
    }

    if ($high == $low + 1) {
        if ($arr[$low]['total'] > $arr[$high]['total']) {
            $result['max'] = $arr[$low]['total'];
            $result['min'] = $arr[$high]['total'];
            $result['max_date'] = $arr[$low]['date'];
            $result['min_date'] = $arr[$high]['date'];
        } else {
            $result['max'] = $arr[$high]['total'];
            $result['min'] = $arr[$low]['total'];
            $result['max_date'] = $arr[$high]['date'];
            $result['min_date'] = $arr[$low]['date'];
        }
        return $result;
    }

    $mid = intdiv($low + $high, 2);
    $left = getAnalyticsSpikes($arr, $low, $mid);
    $right = getAnalyticsSpikes($arr, $mid + 1, $high);

    // Evaluate Maximums
    if ($left['max'] > $right['max']) {
        $result['max'] = $left['max'];
        $result['max_date'] = $left['max_date'];
    } else {
        $result['max'] = $right['max'];
        $result['max_date'] = $right['max_date'];
    }

    // Evaluate Minimums
    if ($left['min'] < $right['min']) {
        $result['min'] = $left['min'];
        $result['min_date'] = $left['min_date'];
    } else {
        $result['min'] = $right['min'];
        $result['min_date'] = $right['min_date'];
    }

    return $result;
}

$sales_analytics = getAnalyticsSpikes($daily_sales, 0, count($daily_sales) - 1);

// Fetch menu-wise sales data
$sql_menu = "
    SELECT m.name, SUM(oi.quantity) as total_quantity, SUM(oi.amount) as total_revenue
    FROM order_items oi
    JOIN menu m ON oi.menu_id = m.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY m.name
    ORDER BY total_revenue DESC";
$stmt_menu = $pdo->prepare($sql_menu);
$stmt_menu->execute([$start_datetime, $end_datetime]);
$menu_sales = $stmt_menu->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
    /* Skeleton Loader */
    #skeleton-loader {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(248, 250, 252, 0.98); z-index: 9999;
        display: flex; flex-direction: column; padding: 3rem;
        transition: opacity 0.5s ease;
    }
    .skel-box { background: #e2e8f0; border-radius: 12px; margin-bottom: 20px; position: relative; overflow: hidden; }
    .skel-box::after {
        content: ""; position: absolute; top: 0; left: -100%; width: 50%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent);
        animation: loading 1.2s infinite;
    }
    @keyframes loading { 100% { left: 100%; } }

    /* Fade-in Animations */
    .content-wrapper { display: none; }
    .fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.165, 0.84, 0.44, 1) forwards; opacity: 0; transform: translateY(20px); }
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }

    /* Elegant UI Elements */
    .elegant-card { border-radius: 16px; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.04) !important; overflow: hidden; }
    .table-container { padding: 0 1.5rem; }
    .table-hover tbody tr { transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border-radius: 12px; }
    .table-hover tbody tr:hover { background-color: #ffffff !important; transform: scale(1.01) translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.06); z-index: 1; position: relative; }
    
    /* Summary Cards */
    .summary-card { transition: transform 0.3s ease; }
    .summary-card:hover { transform: translateY(-5px); }
    .icon-bg { position: absolute; right: -10px; bottom: -15px; font-size: 6rem; opacity: 0.08; transform: rotate(-15deg); transition: transform 0.3s ease; }
    .summary-card:hover .icon-bg { transform: rotate(0deg) scale(1.1); opacity: 0.12;}

    /* Filter Pills */
    .filter-pill { border-radius: 20px; font-weight: 600; padding: 6px 16px; transition: all 0.2s ease; border: 1px solid #e2e8f0; color: #64748b; background: white; display: inline-block; margin-bottom: 5px; }
    .filter-pill:hover { background: #f8fafc; color: #4e73df; text-decoration: none; }
    .filter-pill.active { background: #4e73df; color: white; border-color: #4e73df; box-shadow: 0 4px 10px rgba(78, 115, 223, 0.3); }

    /* Form Inputs */
    .form-control-custom { border-radius: 10px; border: 1px solid #e2e8f0; padding: 0.5rem 1rem; background-color: #f8fafc; transition: all 0.3s ease; }
    .form-control-custom:focus { border-color: #4e73df; background-color: #ffffff; box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.15); outline: none; }
</style>

<div id="skeleton-loader">
    <div class="d-flex justify-content-between mb-4">
        <div class="skel-box" style="width: 30%; height: 50px;"></div>
        <div class="skel-box" style="width: 15%; height: 40px; border-radius: 20px;"></div>
    </div>
    <div class="skel-box" style="width: 100%; height: 80px;"></div>
    <div class="row">
        <div class="col-md-3"><div class="skel-box" style="height: 140px;"></div></div>
        <div class="col-md-3"><div class="skel-box" style="height: 140px;"></div></div>
        <div class="col-md-3"><div class="skel-box" style="height: 140px;"></div></div>
        <div class="col-md-3"><div class="skel-box" style="height: 140px;"></div></div>
    </div>
    <div class="row mt-3">
        <div class="col-md-5"><div class="skel-box" style="height: 400px;"></div></div>
        <div class="col-md-7"><div class="skel-box" style="height: 400px;"></div></div>
    </div>
</div>

<div class="container-fluid px-0 content-wrapper fade-in-up" id="main-content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h3 class="text-dark font-weight-bold mb-1">Financial Intelligence</h3>
            <p class="text-muted mb-0"><i class="fas fa-chart-line mr-1"></i> Sales Trend Analytics</p>
        </div>
        <div class="mt-3 mt-md-0">
            <span class="badge badge-primary px-4 py-2 font-weight-normal shadow-sm rounded-pill" style="font-size: 1rem;">
                <i class="far fa-calendar-alt mr-2"></i> <?php echo $range_text; ?>
            </span>
        </div>
    </div>

    <div class="card elegant-card shadow-sm mb-4 fade-in-up delay-1">
        <div class="card-body p-4 bg-light d-flex flex-wrap align-items-center justify-content-between">
            <div class="mb-3 mb-lg-0 d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted mr-3 font-weight-bold text-uppercase small"><i class="fas fa-filter mr-1"></i> Presets</span>
                <div class="d-flex flex-wrap gap-2" style="gap: 10px;">
                    <a href="?range=24h" class="filter-pill <?php echo $active_range == '24h' ? 'active' : ''; ?>">24H</a>
                    <a href="?range=7d" class="filter-pill <?php echo $active_range == '7d' ? 'active' : ''; ?>">7 Days</a>
                    <a href="?range=10d" class="filter-pill <?php echo $active_range == '10d' ? 'active' : ''; ?>">10 Days</a>
                    <a href="?range=1m" class="filter-pill <?php echo $active_range == '1m' ? 'active' : ''; ?>">1 Month</a>
                    <a href="?range=3m" class="filter-pill <?php echo $active_range == '3m' ? 'active' : ''; ?>">3 Months</a>
                </div>
            </div>
            
            <form action="sales_reports.php" method="get" class="form-inline bg-white p-2 rounded-lg shadow-sm border m-0">
                <span class="text-muted mx-3 font-weight-bold text-uppercase small">Custom Range</span>
                <input type="date" class="form-control-custom mr-2 mb-2 mb-md-0" name="from" value="<?php echo $date_from; ?>" required>
                <span class="text-muted mx-2 mb-2 mb-md-0"><i class="fas fa-arrow-right"></i></span>
                <input type="date" class="form-control-custom mr-3 mb-2 mb-md-0" name="to" value="<?php echo $date_to; ?>" required>
                <button type="submit" class="btn btn-dark rounded-lg px-4 font-weight-bold shadow-sm mb-2 mb-md-0">Apply</button>
            </form>
        </div>
    </div>

    <div class="row fade-in-up delay-1">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card elegant-card summary-card bg-white h-100 py-3" style="border-left: 5px solid #1cc88a;">
                <div class="card-body position-relative p-4">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-2 tracking-wide">Gross Revenue</div>
                    <div class="h3 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($total_sales, 2); ?></div>
                    <i class="fas fa-rupee-sign icon-bg text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card elegant-card summary-card bg-white h-100 py-3" style="border-left: 5px solid #4e73df;">
                <div class="card-body position-relative p-4">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-2 tracking-wide">Orders Processed</div>
                    <div class="h3 mb-0 font-weight-bold text-gray-800"><?php echo $total_orders; ?></div>
                    <i class="fas fa-shopping-basket icon-bg text-primary"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card elegant-card summary-card bg-white h-100 py-3" style="border-left: 5px solid #f6c23e;">
                <div class="card-body position-relative p-4">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-2 tracking-wide">Peak Sales Spike</div>
                    <div class="h3 mb-1 font-weight-bold text-gray-800">₹<?php echo number_format($sales_analytics['max'], 2); ?></div>
                    <div class="small text-muted font-weight-bold">
                        <i class="fas fa-calendar-day mr-1"></i> <?php echo $sales_analytics['max_date'] ? date('d M Y', strtotime($sales_analytics['max_date'])) : 'No Data'; ?>
                    </div>
                    <i class="fas fa-arrow-trend-up icon-bg text-warning"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card elegant-card summary-card bg-white h-100 py-3" style="border-left: 5px solid #e74a3b;">
                <div class="card-body position-relative p-4">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-2 tracking-wide">Minimum Sales Dip</div>
                    <div class="h3 mb-1 font-weight-bold text-gray-800">₹<?php echo number_format($sales_analytics['min'], 2); ?></div>
                    <div class="small text-muted font-weight-bold">
                        <i class="fas fa-calendar-day mr-1"></i> <?php echo $sales_analytics['min_date'] ? date('d M Y', strtotime($sales_analytics['min_date'])) : 'No Data'; ?>
                    </div>
                    <i class="fas fa-arrow-trend-down icon-bg text-danger"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 col-md-12 mb-4 fade-in-up delay-2">
            <div class="card elegant-card shadow-sm h-100">
                <div class="card-header bg-white py-4 border-bottom-0">
                    <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-fire text-danger mr-2"></i> Trending Items</h5>
                </div>
                <div class="card-body p-0 pb-4">
                    <div class="table-responsive table-container">
                        <table class="table table-hover table-borderless align-middle mb-0" style="border-collapse: separate; border-spacing: 0 8px;">
                            <thead class="text-uppercase text-xs font-weight-bold text-muted">
                                <tr>
                                    <th class="pl-3">Menu Item</th>
                                    <th class="text-center">Qty Sold</th>
                                    <th class="text-right pr-3">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($menu_sales)): ?>
                                    <tr><td colspan="3" class="text-center py-4 text-muted">No sales data found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($menu_sales as $sale): ?>
                                    <tr class="bg-light">
                                        <td class="align-middle rounded-left pl-3 font-weight-bold text-dark py-3">
                                            <?php echo htmlspecialchars($sale['name']); ?>
                                        </td>
                                        <td class="align-middle text-center">
                                            <span class="badge badge-light border px-3 py-2 text-dark font-weight-bold"><?php echo $sale['total_quantity']; ?></span>
                                        </td>
                                        <td class="align-middle rounded-right text-right pr-3 font-weight-bold text-success">
                                            ₹<?php echo number_format($sale['total_revenue'], 2); ?>
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

        <div class="col-lg-7 col-md-12 mb-4 fade-in-up delay-3">
            <div class="card elegant-card shadow-sm h-100">
                <div class="card-header bg-white py-4 border-bottom-0 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-list-ul text-info mr-2"></i> Order Ledger</h5>
                </div>
                <div class="card-body p-0 pb-4">
                    <div class="table-responsive table-container" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover table-borderless align-middle mb-0" style="border-collapse: separate; border-spacing: 0 8px;">
                            <thead class="text-uppercase text-xs font-weight-bold text-muted sticky-top bg-white">
                                <tr>
                                    <th class="pl-3">Date & Time</th>
                                    <th>Coupon ID</th>
                                    <th>Amount</th>
                                    <th class="text-right pr-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No orders found for this period.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                    <tr class="bg-light">
                                        <td class="align-middle rounded-left pl-3 py-3">
                                            <div class="font-weight-bold text-dark"><?php echo date('d M Y', strtotime($order['created_at'])); ?></div>
                                            <div class="small text-muted"><i class="far fa-clock mr-1"></i><?php echo date('H:i A', strtotime($order['created_at'])); ?></div>
                                        </td>
                                        <td class="align-middle font-weight-bold text-primary">
                                            #<?php echo htmlspecialchars($order['coupon_id']); ?>
                                        </td>
                                        <td class="align-middle font-weight-bold text-dark">
                                            ₹<?php echo number_format($order['total_amount'], 2); ?>
                                        </td>
                                        <td class="align-middle rounded-right text-right pr-3">
                                            <?php 
                                            $badge_class = 'secondary';
                                            if ($order['status'] == 'fully_served') $badge_class = 'success';
                                            if ($order['status'] == 'partially_served') $badge_class = 'warning';
                                            ?>
                                            <span class="badge badge-<?php echo $badge_class; ?> badge-pill px-3 py-2 shadow-sm">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
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
    // Handle skeleton loader dismissal
    window.addEventListener('load', function() {
        const skeleton = document.getElementById('skeleton-loader');
        const content = document.getElementById('main-content');
        if (skeleton) {
            skeleton.style.opacity = '0';
            setTimeout(function() {
                skeleton.style.display = 'none';
                content.style.display = 'block';
            }, 400); // Wait for fade out
        }
    });
</script>

<?php include 'footer.php'; ?>