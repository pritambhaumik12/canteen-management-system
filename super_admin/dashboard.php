<?php
include 'header.php';
require_once '../includes/db.php';

// --- Fetch Statistics ---

// Total Users
$total_users = 0;
$total_users += $pdo->query("SELECT count(*) FROM members")->fetchColumn();
$total_users += $pdo->query("SELECT count(*) FROM canteen_staff")->fetchColumn();
$total_users += $pdo->query("SELECT count(*) FROM managers")->fetchColumn();

// Total Menu Items
$stmt_menu = $pdo->query("SELECT count(*) FROM menu");
$total_menu_items = $stmt_menu->fetchColumn();

// Today's Orders & Sales
$today_date = date('Y-m-d');
$stmt_orders = $pdo->prepare("SELECT count(*) FROM orders WHERE DATE(created_at) = ?");
$stmt_orders->execute([$today_date]);
$today_orders = $stmt_orders->fetchColumn();

$stmt_sales = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) = ?");
$stmt_sales->execute([$today_date]);
$today_sales = $stmt_sales->fetchColumn();

// --- Fetch Recent Users Separated By Role ---
$managers = $pdo->query("SELECT manager_id as user_id, name, 'manager' as role, created_at FROM managers ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$staff = $pdo->query("SELECT staff_id as user_id, name, 'staff' as role, created_at FROM canteen_staff ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$members = $pdo->query("SELECT member_id as user_id, name, 'member' as role, created_at FROM members ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
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

    /* Fade-in Animation for elements */
    .fade-in-up {
        animation: fadeInUp 0.8s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
        opacity: 0;
        transform: translateY(20px);
    }
    
    /* Staggered animation delays for a cascading effect */
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }

    @keyframes fadeInUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Card Hover Effects */
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

    /* Quick Action Buttons */
    .action-btn {
        transition: all 0.3s ease;
        border-radius: 8px;
        border: 1px solid transparent;
    }

    .action-btn:hover {
        background-color: #f8f9fc !important;
        transform: translateX(5px);
        border-color: #eaecf4;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05) !important;
    }

    /* Table Enhancements */
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

    /* Custom Nav Pills for Tabs */
    .custom-pills .nav-link {
        border-radius: 30px;
        padding: 8px 20px;
        color: #5a5c69;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .custom-pills .nav-link.active {
        background-color: #4e73df;
        color: #fff;
        box-shadow: 0 4px 10px rgba(78, 115, 223, 0.3);
    }
</style>

<div id="page-loader">
    <div class="spinner"></div>
</div>

<div class="container-fluid fade-in-up">
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <h3 class="text-gray-800 mb-0 font-weight-bold">Super Admin Dashboard</h3>
        <a href="reports.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm px-3 py-2" style="border-radius: 20px; transition: 0.3s;">
            <i class="fas fa-download fa-sm text-white-50 mr-1"></i> Generate Report
        </a>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4 fade-in-up delay-1">
            <div class="card card-stats bg-gradient-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-white text-uppercase mb-1" style="opacity: 0.8;">Total Users</div>
                            <div class="h3 mb-0 font-weight-bold text-white"><?php echo $total_users; ?></div>
                        </div>
                    </div>
                </div>
                <i class="fas fa-users text-white"></i>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 fade-in-up delay-2">
            <div class="card card-stats bg-gradient-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-white text-uppercase mb-1" style="opacity: 0.8;">Menu Items</div>
                            <div class="h3 mb-0 font-weight-bold text-white"><?php echo $total_menu_items; ?></div>
                        </div>
                    </div>
                </div>
                <i class="fas fa-utensils text-white"></i>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 fade-in-up delay-3">
            <div class="card card-stats bg-gradient-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-white text-uppercase mb-1" style="opacity: 0.8;">Orders (Today)</div>
                            <div class="h3 mb-0 font-weight-bold text-white"><?php echo $today_orders; ?></div>
                        </div>
                    </div>
                </div>
                <i class="fas fa-clipboard-list text-white"></i>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 fade-in-up delay-4">
            <div class="card card-stats bg-gradient-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-white text-uppercase mb-1" style="opacity: 0.8;">Sales (Today)</div>
                            <div class="h3 mb-0 font-weight-bold text-white">₹<?php echo number_format((float)$today_sales, 2); ?></div>
                        </div>
                    </div>
                </div>
                <i class="fas fa-rupee-sign text-white"></i>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-4 fade-in-up delay-3">
            <div class="card shadow mb-4" style="border-radius: 12px; border: none;">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-bolt mr-2 text-warning"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column">
                        <a href="user_management.php" class="btn btn-white text-dark mb-3 text-left shadow-sm action-btn py-3 font-weight-bold">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary-light p-2 rounded-circle mr-3"><i class="fas fa-user-plus text-primary fa-lg"></i></div>
                                <span>Add / Manage Users</span>
                            </div>
                        </a>
                        <a href="menu_management.php" class="btn btn-white text-dark mb-3 text-left shadow-sm action-btn py-3 font-weight-bold">
                            <div class="d-flex align-items-center">
                                <div class="bg-success-light p-2 rounded-circle mr-3"><i class="fas fa-hamburger text-success fa-lg"></i></div>
                                <span>Update Menu</span>
                            </div>
                        </a>
                        <a href="sales_reports.php" class="btn btn-white text-dark mb-3 text-left shadow-sm action-btn py-3 font-weight-bold">
                            <div class="d-flex align-items-center">
                                <div class="bg-info-light p-2 rounded-circle mr-3"><i class="fas fa-chart-line text-info fa-lg"></i></div>
                                <span>View Sales Reports</span>
                            </div>
                        </a>
                        <a href="settings.php" class="btn btn-white text-dark text-left shadow-sm action-btn py-3 font-weight-bold">
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary-light p-2 rounded-circle mr-3"><i class="fas fa-cogs text-secondary fa-lg"></i></div>
                                <span>System Settings</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-4 fade-in-up delay-4">
            <div class="card shadow" style="border-radius: 12px; border: none;">
                <div class="card-header py-3 bg-white" style="border-radius: 12px 12px 0 0;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h6 class="m-0 font-weight-bold text-primary mb-2 mb-md-0"><i class="fas fa-clock mr-2 text-info"></i>Recently Added</h6>
                        
                        <ul class="nav nav-pills custom-pills" id="roleTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="managers-tab" data-toggle="pill" href="#tab-managers" role="tab" aria-selected="true">Managers</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="staff-tab" data-toggle="pill" href="#tab-staff" role="tab" aria-selected="false">Staff</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="members-tab" data-toggle="pill" href="#tab-members" role="tab" aria-selected="false">Members</a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="tab-content" id="roleTabsContent">
                        
                        <div class="tab-pane fade show active" id="tab-managers" role="tabpanel" aria-labelledby="managers-tab">
                            <?php renderUserTable($managers, 'primary'); ?>
                        </div>

                        <div class="tab-pane fade" id="tab-staff" role="tabpanel" aria-labelledby="staff-tab">
                            <?php renderUserTable($staff, 'info'); ?>
                        </div>

                        <div class="tab-pane fade" id="tab-members" role="tabpanel" aria-labelledby="members-tab">
                            <?php renderUserTable($members, 'secondary'); ?>
                        </div>

                    </div>

                    <div class="text-center mt-4">
                        <a href="user_management.php" class="btn btn-sm btn-outline-primary px-4" style="border-radius: 20px;">View All Users &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to render the table so we don't repeat HTML 3 times
function renderUserTable($users, $badgeColor) {
    echo '<div class="table-responsive">
            <table class="table table-hover mb-0" width="100%" cellspacing="0" style="border-collapse: separate; border-spacing: 0 8px;">
                <thead class="text-uppercase text-xs font-weight-bold text-muted border-bottom-0">
                    <tr>
                        <th class="border-top-0 border-bottom-0">Name</th>
                        <th class="border-top-0 border-bottom-0">Role</th>
                        <th class="border-top-0 border-bottom-0">Joined Date</th>
                    </tr>
                </thead>
                <tbody>';
    
    if(empty($users)) {
        echo '<tr><td colspan="3" class="text-center text-muted py-4">No users found in this role.</td></tr>';
    } else {
        foreach ($users as $user) {
            $initial = strtoupper(substr($user['name'], 0, 1));
            $name = htmlspecialchars($user['name']);
            $id = htmlspecialchars($user['user_id']);
            $role = ucfirst($user['role']);
            $date = date('d M Y', strtotime($user['created_at']));
            
            echo "<tr class='bg-white shadow-sm' style='border-radius: 8px;'>
                    <td class='align-middle border-top-0 rounded-left'>
                        <div class='d-flex align-items-center'>
                            <div class='mr-3'>
                                <div class='rounded-circle bg-gray-200 text-gray-600 d-flex justify-content-center align-items-center' style='width: 40px; height: 40px; font-weight: bold;'>
                                    {$initial}
                                </div>
                            </div>
                            <div>
                                <div class='font-weight-bold text-dark mb-1'>{$name}</div>
                                <div class='small text-muted'><i class='fas fa-id-badge mr-1'></i>{$id}</div>
                            </div>
                        </div>
                    </td>
                    <td class='align-middle border-top-0'>
                        <span class='badge badge-{$badgeColor} shadow-sm'>{$role}</span>
                    </td>
                    <td class='align-middle border-top-0 rounded-right text-muted font-weight-bold'>
                        {$date}
                    </td>
                </tr>";
        }
    }
    
    echo '</tbody></table></div>';
}
?>

<script src="https://kit.fontawesome.com/a076d05399.js"></script>

<script>
    // Loader Script
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