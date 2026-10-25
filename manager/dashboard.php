<?php
include 'header.php';
require_once '../includes/db.php';

// Fetch the manager's name dynamically
$manager_id = $_SESSION['id'];
$stmt_manager = $pdo->prepare("SELECT name FROM managers WHERE id = ?");
$stmt_manager->execute([$manager_id]);
$full_name = $stmt_manager->fetchColumn();
$first_name = $full_name ? explode(' ', trim($full_name))[0] : 'Manager';

// Fetch stats correctly using separated tables
$count_staff = $pdo->query("SELECT count(*) FROM canteen_staff")->fetchColumn();
$count_members = $pdo->query("SELECT count(*) FROM members")->fetchColumn();
$total_users = $count_staff + $count_members;

$stmt_menu = $pdo->query("SELECT count(*) FROM menu");
$total_menu_items = $stmt_menu->fetchColumn();

$today_date = date('Y-m-d');
$stmt_orders = $pdo->prepare("SELECT count(*) FROM orders WHERE DATE(created_at) = ?");
$stmt_orders->execute([$today_date]);
$today_orders = $stmt_orders->fetchColumn();

$stmt_sales = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) = ?");
$stmt_sales->execute([$today_date]);
$today_sales = $stmt_sales->fetchColumn();
$today_sales = $today_sales ? $today_sales : 0; 
?>

<style>
    /* Gradient App Banner similar to Member portal */
    .greeting-section { 
        background: linear-gradient(135deg, #0062E6 0%, #33AEFF 100%); 
        color: white; 
        padding: 30px 25px 60px; 
        border-radius: 0 0 30px 30px; 
        box-shadow: 0 4px 15px rgba(0, 98, 230, 0.2); 
        position: relative;
        z-index: 1;
        margin-bottom: 30px;
    }
    
    .summary-card-container {
        margin-top: -50px; /* Overlaps with the greeting section */
        position: relative;
        z-index: 2;
        padding: 0 15px;
    }

    .elegant-card {
        border: none;
        border-radius: 20px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05) !important;
    }
    
    .elegant-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
    }
    
    .icon-bg {
        position: absolute;
        right: -10px;
        bottom: -15px;
        font-size: 5rem;
        opacity: 0.1;
        transform: rotate(-15deg);
    }
    
    .action-btn {
        border-radius: 50px;
        padding: 12px 25px;
        font-weight: 600;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
    }
    
    .action-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.15) !important;
    }
</style>

<div class="greeting-section text-center text-md-left">
    <div class="d-sm-flex justify-content-between align-items-center">
        <div>
            <h3 class="font-weight-bold mb-1">Welcome back, <?php echo htmlspecialchars($first_name); ?>! 📊</h3>
            <p class="mb-0 text-light" style="font-size: 0.95rem;">Here is the summary of your canteen today.</p>
        </div>
        <div class="mt-3 mt-sm-0 text-white font-weight-bold px-3 py-2" style="background: rgba(255,255,255,0.2); border-radius: 12px; backdrop-filter: blur(5px);">
            <i class="far fa-calendar-alt mr-1"></i> <?php echo date('l, d F Y'); ?>
        </div>
    </div>
</div>

<div class="row summary-card-container">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card elegant-card py-2 border-left-primary" style="border-left-width: 5px !important; border-left-color: #4e73df !important;">
            <div class="card-body position-relative">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Staff & Members</div>
                        <div class="h3 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                    </div>
                </div>
                <i class="fas fa-users icon-bg text-primary"></i>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card elegant-card py-2 border-left-success" style="border-left-width: 5px !important; border-left-color: #1cc88a !important;">
            <div class="card-body position-relative">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Menu Items</div>
                        <div class="h3 mb-0 font-weight-bold text-gray-800"><?php echo $total_menu_items; ?></div>
                    </div>
                </div>
                <i class="fas fa-utensils icon-bg text-success"></i>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card elegant-card py-2 border-left-info" style="border-left-width: 5px !important; border-left-color: #36b9cc !important;">
            <div class="card-body position-relative">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Orders (Today)</div>
                        <div class="h3 mb-0 font-weight-bold text-gray-800"><?php echo $today_orders; ?></div>
                    </div>
                </div>
                <i class="fas fa-clipboard-list icon-bg text-info"></i>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card elegant-card py-2 border-left-warning" style="border-left-width: 5px !important; border-left-color: #f6c23e !important;">
            <div class="card-body position-relative">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Revenue (Today)</div>
                        <div class="h3 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($today_sales, 2); ?></div>
                    </div>
                </div>
                <i class="fas fa-rupee-sign icon-bg text-warning"></i>
            </div>
        </div>
    </div>
</div>

<div class="row mt-2 px-3">
    <div class="col-12 px-0">
        <div class="card elegant-card">
            <div class="card-header bg-white py-4 border-bottom-0">
                <h5 class="m-0 font-weight-bold text-primary"><i class="fas fa-bolt text-warning mr-2"></i> Quick Actions</h5>
            </div>
            <div class="card-body bg-light text-center py-5 rounded-bottom">
                <a href="menu_management.php" class="btn btn-primary action-btn shadow-sm m-2">
                    <i class="fas fa-hamburger mr-2"></i> Manage Menu
                </a>
                <a href="user_management.php" class="btn btn-info action-btn shadow-sm m-2 text-white">
                    <i class="fas fa-user-edit mr-2"></i> Manage Users
                </a>
                <a href="sales_reports.php" class="btn btn-success action-btn shadow-sm m-2">
                    <i class="fas fa-chart-line mr-2"></i> View Sales Reports
                </a>
                <a href="not_served.php" class="btn btn-danger action-btn shadow-sm m-2">
                    <i class="fas fa-exclamation-circle mr-2"></i> Pending Orders
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>