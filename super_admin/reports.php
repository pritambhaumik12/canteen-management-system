<?php
// Start session at the very top for the PRG pattern
session_start();
require_once '../includes/db.php';

// --- 1. PRG Pattern implementation for the Filter Form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_reports'])) {
    // Save the POST data to the session
    $_SESSION['report_start_date'] = $_POST['start_date'];
    $_SESSION['report_end_date'] = $_POST['end_date'];
    
    // Redirect to the same page via GET
    header("Location: reports.php");
    exit();
}

// --- 2. Retrieve Filter Data (from Session or Default) ---
// Default to the current month if no filter is applied
$start_date = $_SESSION['report_start_date'] ?? date('Y-m-01');
$end_date = $_SESSION['report_end_date'] ?? date('Y-m-t');

// --- 3. Fetch Report Data ---
// Summary Data
$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders, SUM(total_amount) as total_revenue FROM orders WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
$stmt->execute([$start_date, $end_date]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$total_orders = $summary['total_orders'] ?: 0;
$total_revenue = $summary['total_revenue'] ?: 0;
$avg_order = $total_orders > 0 ? $total_revenue / $total_orders : 0;

// Detailed Orders Data (REMOVED user_id and user_role to prevent the column not found error)
$stmt_orders = $pdo->prepare("
    SELECT id as order_id, total_amount, status, created_at 
    FROM orders 
    WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? 
    ORDER BY created_at DESC
");
$stmt_orders->execute([$start_date, $end_date]);
$orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

// --- 4. CSV Export Feature ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sales_report_' . $start_date . '_to_' . $end_date . '.csv');
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, array('Order ID', 'Amount (Rs)', 'Date', 'Status'));
    
    // CSV Rows
    foreach ($orders as $row) {
        fputcsv($output, array(
            $row['order_id'], 
            $row['total_amount'], 
            date('d M Y, h:i A', strtotime($row['created_at'])), 
            ucfirst($row['status'])
        ));
    }
    fclose($output);
    exit();
}

// Include header only after all potential redirects and header modifications
include 'header.php'; 
?>

<style>
    /* Full Page Loader */
    #page-loader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.95);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        transition: opacity 0.5s ease, visibility 0.5s ease;
    }
    
    .spinner {
        width: 60px;
        height: 60px;
        border: 6px solid #e3e6f0;
        border-top: 6px solid #4e73df;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Fade-in Animation */
    .fade-in-up {
        animation: fadeInUp 0.8s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
        opacity: 0;
        transform: translateY(20px);
    }
    
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }

    @keyframes fadeInUp {
        to { opacity: 1; transform: translateY(0); }
    }

    /* Card & Table Styling */
    .card-stats {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: none;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .card-stats:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 20px rgba(0, 0, 0, 0.15) !important;
    }

    .card-stats i {
        position: absolute;
        right: 20px;
        top: 25px;
        font-size: 3rem;
        opacity: 0.3;
        transition: transform 0.3s ease;
    }

    .card-stats:hover i {
        transform: scale(1.2) rotate(10deg);
        opacity: 0.5;
    }

    .table-hover tbody tr {
        transition: background-color 0.2s ease, transform 0.2s ease;
    }
    
    .table-hover tbody tr:hover {
        background-color: #f8f9fc;
        transform: scale(1.01);
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .badge {
        padding: 0.5em 0.8em;
        border-radius: 30px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .filter-card {
        border-radius: 12px;
        border: none;
        background: #f8f9fc;
    }
</style>

<div id="page-loader">
    <div class="spinner"></div>
</div>

<div class="container-fluid fade-in-up">
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <h3 class="text-gray-800 mb-0 font-weight-bold">Sales & Order Reports</h3>
        <a href="reports.php?export=csv" class="d-sm-inline-block btn btn-sm btn-success shadow-sm px-3 py-2 mt-2 mt-sm-0" style="border-radius: 20px; transition: 0.3s;">
            <i class="fas fa-file-csv fa-sm text-white-50 mr-1"></i> Export to CSV
        </a>
    </div>

    <div class="card shadow-sm filter-card mb-4 fade-in-up delay-1">
        <div class="card-body">
            <form method="POST" action="reports.php" class="row align-items-center">
                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <label class="sr-only" for="start_date">Start Date</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <div class="input-group-text bg-white"><i class="fas fa-calendar-alt text-primary"></i></div>
                        </div>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                    </div>
                </div>
                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <label class="sr-only" for="end_date">End Date</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <div class="input-group-text bg-white"><i class="fas fa-calendar-alt text-primary"></i></div>
                        </div>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <button type="submit" name="filter_reports" class="btn btn-primary btn-block shadow-sm" style="border-radius: 8px;">
                        <i class="fas fa-filter mr-1"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4 fade-in-up delay-1">
            <div class="card card-stats bg-gradient-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-white text-uppercase mb-1" style="opacity: 0.8;">Total Revenue</div>
                            <div class="h3 mb-0 font-weight-bold text-white">₹<?php echo number_format((float)$total_revenue, 2); ?></div>
                        </div>
                    </div>
                </div>
                <i class="fas fa-rupee-sign text-white"></i>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4 fade-in-up delay-2">
            <div class="card card-stats bg-gradient-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-white text-uppercase mb-1" style="opacity: 0.8;">Total Orders</div>
                            <div class="h3 mb-0 font-weight-bold text-white"><?php echo $total_orders; ?></div>
                        </div>
                    </div>
                </div>
                <i class="fas fa-shopping-cart text-white"></i>
            </div>
        </div>

        <div class="col-xl-4 col-md-12 mb-4 fade-in-up delay-3">
            <div class="card card-stats bg-gradient-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-white text-uppercase mb-1" style="opacity: 0.8;">Average Order Value</div>
                            <div class="h3 mb-0 font-weight-bold text-white">₹<?php echo number_format((float)$avg_order, 2); ?></div>
                        </div>
                    </div>
                </div>
                <i class="fas fa-chart-pie text-white"></i>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4 fade-in-up delay-3" style="border-radius: 12px; border: none;">
        <div class="card-header py-3 bg-white" style="border-radius: 12px 12px 0 0;">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-2 text-info"></i>Detailed Order List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" width="100%" cellspacing="0" style="border-collapse: separate; border-spacing: 0 8px;">
                    <thead class="text-uppercase text-xs font-weight-bold text-muted border-bottom-0">
                        <tr>
                            <th class="border-top-0 border-bottom-0">Order ID</th>
                            <th class="border-top-0 border-bottom-0">Amount</th>
                            <th class="border-top-0 border-bottom-0">Status</th>
                            <th class="border-top-0 border-bottom-0">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($orders)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No orders found for the selected date range.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                            <tr class="bg-white shadow-sm" style="border-radius: 8px;">
                                <td class="align-middle border-top-0 rounded-left font-weight-bold text-dark">
                                    #<?php echo htmlspecialchars($order['order_id']); ?>
                                </td>
                                <td class="align-middle border-top-0 font-weight-bold text-success">
                                    ₹<?php echo number_format((float)$order['total_amount'], 2); ?>
                                </td>
                                <td class="align-middle border-top-0">
                                    <span class="badge badge-<?php 
                                        echo match(strtolower($order['status'])) {
                                            'completed', 'delivered' => 'success',
                                            'pending' => 'warning',
                                            'cancelled' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?> shadow-sm">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td class="align-middle border-top-0 rounded-right text-muted font-weight-bold">
                                    <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?>
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

<script src="https://kit.fontawesome.com/a076d05399.js"></script>

<script>
    // Handle the Full Page Loader
    window.addEventListener('load', function() {
        const loader = document.getElementById('page-loader');
        loader.style.opacity = '0';
        setTimeout(function() {
            loader.style.visibility = 'hidden';
            loader.style.display = 'none';
        }, 500);
    });
</script>

<?php include 'footer.php'; ?>