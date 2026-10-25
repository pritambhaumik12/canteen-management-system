<?php
// START OUTPUT BUFFERING: This is critical! It prevents header.php from sending 
// the loading spinner HTML to the browser before our POST redirect happens.
ob_start(); 

include 'header.php';
require_once '../includes/db.php';

// Store current URL to preserve filters after form submission
$current_url = $_SERVER['REQUEST_URI'] ?? 'not_served.php';

// ==========================================
// POST: Process Single Refund (PRG Pattern)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
    $item_id = $_POST['item_id'];
    $member_db_id = $_POST['member_db_id'];
    $order_id = $_POST['order_id'];
    $coupon_id = $_POST['coupon_id'];
    $restore_stock = isset($_POST['restore_stock']) ? true : false; 
    
    try {
        $pdo->beginTransaction();
        
        // Lock and fetch current item details
        $stmt = $pdo->prepare("SELECT menu_id, quantity, served_quantity, rate FROM order_items WHERE id = ? FOR UPDATE");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item && $item['quantity'] > $item['served_quantity']) {
            $unserved_qty = $item['quantity'] - $item['served_quantity'];
            $refund_amount = $unserved_qty * $item['rate'];
            
            // 1. Refund to member's wallet
            $stmt_wallet = $pdo->prepare("UPDATE wallet SET balance = balance + ? WHERE member_id = ?");
            $stmt_wallet->execute([$refund_amount, $member_db_id]);
            
            // 2. Record Transaction
            $desc = "Refund for unserved item (Coupon #$coupon_id)";
            $stmt_txn = $pdo->prepare("INSERT INTO transactions (member_id, type, amount, description, created_at) VALUES (?, 'credit', ?, ?, NOW())");
            $stmt_txn->execute([$member_db_id, $refund_amount, $desc]);
            
            // 3. Update order item
            $new_qty = $item['served_quantity'];
            $new_amt = $new_qty * $item['rate'];
            $item_status = 'refunded';
            
            $stmt_update_item = $pdo->prepare("UPDATE order_items SET quantity = ?, amount = ?, status = ? WHERE id = ?");
            $stmt_update_item->execute([$new_qty, $new_amt, $item_status, $item_id]);
            
            // 4. Update the total amount of the main order
            $stmt_update_order = $pdo->prepare("UPDATE orders SET total_amount = total_amount - ? WHERE id = ?");
            $stmt_update_order->execute([$refund_amount, $order_id]);
            
            // 5. Conditionally Restore Menu Stock
            if ($restore_stock) {
                $stmt_stock = $pdo->prepare("UPDATE menu SET stock = stock + ? WHERE id = ?");
                $stmt_stock->execute([$unserved_qty, $item['menu_id']]);
            }

            // 6. Check main order status
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND quantity > served_quantity AND status NOT IN ('cancelled', 'refunded')");
            $stmt_check->execute([$order_id]);
            
            if ($stmt_check->fetchColumn() == 0) {
                // Determine if they actually ate anything or got a full refund
                $stmt_served = $pdo->prepare("SELECT SUM(served_quantity) FROM order_items WHERE order_id = ?");
                $stmt_served->execute([$order_id]);
                if ($stmt_served->fetchColumn() > 0) {
                    $pdo->prepare("UPDATE orders SET status = 'partially_refunded' WHERE id = ?")->execute([$order_id]);
                } else {
                    $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?")->execute([$order_id]);
                }
            }
            
            $pdo->commit();
            $stock_msg = $restore_stock ? " and stock restored." : " (Stock was not restored).";
            $_SESSION['success'] = "Successfully refunded ₹" . number_format($refund_amount, 2) . " to the member's wallet" . $stock_msg;
        } else {
            $pdo->rollBack();
            $_SESSION['error'] = "Item has already been served or is invalid.";
        }
    } catch(Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error processing refund: " . $e->getMessage();
    }
    
    // Clear the output buffer to safely run the redirect without crashing
    ob_end_clean();
    header("Location: " . $current_url);
    exit;
}

// ==========================================
// POST: Process Bulk Refund (PRG Pattern)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_bulk_refund'])) {
    $selected_items = $_POST['selected_items'] ?? [];
    $restore_stock = isset($_POST['bulk_restore_stock']) ? true : false;
    
    if (!empty($selected_items)) {
        try {
            $pdo->beginTransaction();
            $total_refunded = 0;
            $items_refunded_count = 0;
            $affected_orders = [];

            foreach ($selected_items as $item_id) {
                $stmt = $pdo->prepare("
                    SELECT oi.menu_id, oi.quantity, oi.served_quantity, oi.rate, oi.order_id,
                           o.member_id as member_db_id, o.coupon_id
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE oi.id = ? FOR UPDATE
                ");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($item && $item['quantity'] > $item['served_quantity']) {
                    $unserved_qty = $item['quantity'] - $item['served_quantity'];
                    $refund_amount = $unserved_qty * $item['rate'];
                    $member_db_id = $item['member_db_id'];
                    $order_id = $item['order_id'];
                    $coupon_id = $item['coupon_id'];

                    $stmt_wallet = $pdo->prepare("UPDATE wallet SET balance = balance + ? WHERE member_id = ?");
                    $stmt_wallet->execute([$refund_amount, $member_db_id]);

                    $desc = "Refund for unserved item (Coupon #$coupon_id)";
                    $stmt_txn = $pdo->prepare("INSERT INTO transactions (member_id, type, amount, description, created_at) VALUES (?, 'credit', ?, ?, NOW())");
                    $stmt_txn->execute([$member_db_id, $refund_amount, $desc]);

                    $new_qty = $item['served_quantity'];
                    $new_amt = $new_qty * $item['rate'];
                    $item_status = 'refunded';

                    $stmt_update_item = $pdo->prepare("UPDATE order_items SET quantity = ?, amount = ?, status = ? WHERE id = ?");
                    $stmt_update_item->execute([$new_qty, $new_amt, $item_status, $item_id]);

                    $stmt_update_order = $pdo->prepare("UPDATE orders SET total_amount = total_amount - ? WHERE id = ?");
                    $stmt_update_order->execute([$refund_amount, $order_id]);

                    if ($restore_stock) {
                        $stmt_stock = $pdo->prepare("UPDATE menu SET stock = stock + ? WHERE id = ?");
                        $stmt_stock->execute([$unserved_qty, $item['menu_id']]);
                    }

                    $affected_orders[] = $order_id;
                    $total_refunded += $refund_amount;
                    $items_refunded_count++;
                }
            }
            
            $affected_orders = array_unique($affected_orders);
            foreach ($affected_orders as $o_id) {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND quantity > served_quantity AND status NOT IN ('cancelled', 'refunded')");
                $stmt_check->execute([$o_id]);
                if ($stmt_check->fetchColumn() == 0) {
                    $stmt_served = $pdo->prepare("SELECT SUM(served_quantity) FROM order_items WHERE order_id = ?");
                    $stmt_served->execute([$o_id]);
                    if ($stmt_served->fetchColumn() > 0) {
                        $pdo->prepare("UPDATE orders SET status = 'partially_refunded' WHERE id = ?")->execute([$o_id]);
                    } else {
                        $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?")->execute([$o_id]);
                    }
                }
            }
            
            $pdo->commit();
            if ($items_refunded_count > 0) {
                $stock_msg = $restore_stock ? " (Stock restored)." : " (Stock NOT restored).";
                $_SESSION['success'] = "Successfully processed bulk refund! ₹" . number_format($total_refunded, 2) . " returned for $items_refunded_count items" . $stock_msg;
            } else {
                $_SESSION['error'] = "No valid pending items found to refund.";
            }

        } catch(Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error processing bulk refund: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "No items selected for refund.";
    }
    
    // Clear the output buffer to safely run the redirect without crashing
    ob_end_clean();
    header("Location: " . $current_url);
    exit;
}

// ==========================================
// DATE FILTER LOGIC
// ==========================================
$date_from = '';
$date_to = '';
$range_text = "All Pending";

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
    if ($range == 'today') {
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d');
        $range_text = "Today";
    } else if ($range == '7d') {
        $date_from = date('Y-m-d', strtotime('-6 days'));
        $date_to = date('Y-m-d');
        $range_text = "Last 7 Days";
    }
}

// ==========================================
// FETCH UNSERVED ITEMS
// ==========================================
$params = [];
$date_condition = "";

if ($date_from && $date_to) {
    $date_condition = " AND o.created_at BETWEEN ? AND ?";
    $params[] = $date_from . " 00:00:00";
    $params[] = $date_to . " 23:59:59";
}

$sql = "
    SELECT 
        oi.id as item_id,
        oi.quantity,
        oi.served_quantity,
        oi.rate,
        oi.amount,
        o.id as order_id,
        o.created_at,
        o.coupon_id,
        u.id as member_db_id,
        u.member_id as member_uid,
        u.name as member_name,
        m.name as menu_item_name
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN members u ON o.member_id = u.id
    JOIN menu m ON oi.menu_id = m.id
    WHERE oi.quantity > oi.served_quantity AND o.status NOT IN ('fully_served', 'refunded') AND oi.status NOT IN ('cancelled', 'refunded')
    $date_condition
    ORDER BY o.created_at ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$unserved_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_unserved_amount = 0;
$total_pending_items_count = 0;
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

    :root {
        --font-family: 'Outfit', sans-serif;
        --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
    .stat-card i { position: absolute; right: 20px; bottom: -10px; font-size: 5rem; opacity: 0.15; transform: rotate(-15deg); }
    .stat-card.bg-warning-custom { background: var(--warning-gradient); }
    .stat-card.bg-danger-custom { background: var(--danger-gradient); }
    
    /* Table Styling */
    .table-elegant th { 
        border-top: none; background-color: #f1f5f9; text-transform: uppercase; font-size: 0.8rem; 
        letter-spacing: 1px; color: #64748b; font-weight: 700; 
    }
    .table-elegant td { vertical-align: middle; font-weight: 500; color: #334155; border-bottom: 1px dashed #e2e8f0; padding: 15px 12px; }
    .table-elegant tbody tr:hover { background-color: #f8fafc; }
    
    /* Search & Filter Controls */
    .search-input-wrapper { position: relative; width: 100%; max-width: 350px; }
    .search-input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    .search-input { padding-left: 40px; border-radius: 20px; border: 2px solid #e2e8f0; height: 42px; transition: border-color 0.3s; box-shadow: none; }
    .search-input:focus { border-color: #6366f1; outline: none; }
    .filter-btn { border-radius: 20px; padding: 6px 16px; font-size: 0.85rem; font-weight: 600; margin-right: 5px; transition: all 0.2s; }

    /* Buttons */
    .btn-refund { background-color: #fff; color: #dc2626; border: 1px solid #fca5a5; border-radius: 20px; padding: 5px 15px; font-size: 0.85rem; font-weight: 600; transition: all 0.2s; }
    .btn-refund:hover { background-color: #fef2f2; border-color: #ef4444; transform: scale(1.05); }

    /* Custom Checkbox */
    .custom-control-label::before { border-radius: 4px; border: 2px solid #cbd5e1; width: 1.25rem; height: 1.25rem; }
    .custom-control-label::after { width: 1.25rem; height: 1.25rem; }
    .custom-checkbox .custom-control-input:checked~.custom-control-label::before { background-color: #4f46e5; border-color: #4f46e5; }

    /* Skeleton Animations */
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    @keyframes slideUpFade { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    
    .skeleton-box { background: var(--skeleton-base); background-image: linear-gradient(90deg, var(--skeleton-base) 0px, var(--skeleton-highlight) 50%, var(--skeleton-base) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite linear; border-radius: 12px; }
    .sk-header { height: 40px; width: 30%; margin-bottom: 20px; }
    .sk-filter { height: 60px; width: 100%; margin-bottom: 25px; }
    .sk-card { height: 130px; width: 100%; margin-bottom: 20px; }
    .sk-table { height: 400px; width: 100%; }

    #page-content { animation: slideUpFade 0.5s ease forwards; }
    
    /* Modal styling */
    .modal-content { border-radius: 20px; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
    .modal-header { border-bottom: 1px dashed #e2e8f0; padding: 20px 25px; }
    .modal-footer { border-top: 1px dashed #e2e8f0; padding: 20px 25px; }
</style>

<div id="page-skeleton">
    <div class="sk-header skeleton-box"></div>
    <div class="sk-filter skeleton-box"></div>
    <div class="row">
        <div class="col-md-6"><div class="sk-card skeleton-box"></div></div>
        <div class="col-md-6"><div class="sk-card skeleton-box"></div></div>
    </div>
    <div class="sk-table skeleton-box"></div>
</div>

<div id="page-content" style="display: none;">
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" style="border-radius: 12px; border-left: 5px solid #10b981;">
            <i class="fas fa-check-circle mr-2"></i> <?php echo $_SESSION['success']; ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" style="border-radius: 12px; border-left: 5px solid #ef4444;">
            <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo $_SESSION['error']; ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="d-sm-flex justify-content-between align-items-center mb-4 mt-2">
        <div>
            <h3 class="text-dark font-weight-bold mb-0">Unserved Food Items</h3>
            <p class="text-muted m-0 small">Monitor pending orders and process refunds</p>
        </div>
        <span class="badge badge-light border text-primary px-3 py-2 font-weight-bold shadow-sm" style="font-size: 0.95rem;">
            <i class="far fa-calendar-alt mr-1"></i> <?php echo htmlspecialchars($range_text); ?>
        </span>
    </div>

    <div class="interactive-card p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="mb-2 mb-md-0 d-flex align-items-center">
            <span class="text-muted mr-3 font-weight-bold small text-uppercase">Filters:</span>
            <a href="not_served.php" class="btn filter-btn btn-outline-secondary <?php echo (!isset($_GET['range']) && !isset($_GET['from'])) ? 'active' : ''; ?>">All Time</a>
            <a href="?range=today" class="btn filter-btn btn-outline-primary <?php echo (isset($_GET['range']) && $_GET['range'] == 'today') ? 'active' : ''; ?>">Today</a>
            <a href="?range=7d" class="btn filter-btn btn-outline-primary <?php echo (isset($_GET['range']) && $_GET['range'] == '7d') ? 'active' : ''; ?>">7 Days</a>
        </div>
        
        <form action="not_served.php" method="get" class="form-inline mb-2 mb-md-0">
            <input type="date" class="form-control form-control-sm mr-2 rounded-pill px-3" name="from" value="<?php echo htmlspecialchars($date_from); ?>" required>
            <span class="mr-2 text-muted font-weight-bold small">to</span>
            <input type="date" class="form-control form-control-sm mr-2 rounded-pill px-3" name="to" value="<?php echo htmlspecialchars($date_to); ?>" required>
            <button type="submit" class="btn btn-sm btn-dark rounded-pill px-3 font-weight-bold"><i class="fas fa-filter mr-1"></i> Apply</button>
        </form>

        <div class="search-input-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" class="form-control search-input" placeholder="Search Coupon, Member, or Item..." onkeyup="filterTable()">
        </div>
    </div>

    <?php 
        foreach ($unserved_items as $item) {
            $pending_qty = $item['quantity'] - $item['served_quantity'];
            $total_unserved_amount += ($item['rate'] * $pending_qty);
            $total_pending_items_count += $pending_qty;
        }
    ?>

    <div class="row mb-4">
        <div class="col-md-6 mb-3 mb-md-0">
            <div class="stat-card bg-warning-custom shadow-sm h-100">
                <i class="fas fa-hamburger"></i>
                <h6 class="text-uppercase font-weight-bold text-white-50 mb-1" style="letter-spacing: 1px;">Total Pending Items</h6>
                <h2 class="font-weight-bold mb-0" id="totalItemsDisplay"><?php echo $total_pending_items_count; ?> <span style="font-size: 1rem; font-weight:500;">Units</span></h2>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card bg-danger-custom shadow-sm h-100">
                <i class="fas fa-rupee-sign"></i>
                <h6 class="text-uppercase font-weight-bold text-white-50 mb-1" style="letter-spacing: 1px;">Value of Pending Orders</h6>
                <h2 class="font-weight-bold mb-0" id="totalValueDisplay">₹<?php echo number_format($total_unserved_amount, 2); ?></h2>
            </div>
        </div>
    </div>

    <div class="interactive-card overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-clipboard-list text-primary mr-2"></i> Pending Queue</h6>
            
            <button type="button" id="bulkRefundBtn" class="btn btn-danger rounded-pill px-4 shadow-sm" disabled onclick="openBulkRefundModal()">
                <i class="fas fa-undo-alt mr-1"></i> Refund Selected (<span id="selectedCount">0</span>)
            </button>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                <table class="table table-elegant text-nowrap m-0" id="unservedTable">
                    <thead class="sticky-top shadow-sm">
                        <tr>
                            <th class="pl-4" style="width: 50px;">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="selectAll">
                                    <label class="custom-control-label" for="selectAll"></label>
                                </div>
                            </th>
                            <th>Order Time</th>
                            <th>Coupon ID</th>
                            <th>Member Info</th>
                            <th>Menu Item</th>
                            <th class="text-center">Qty Pending</th>
                            <th class="text-right">Rate</th>
                            <th class="text-right">Pending Value</th>
                            <th class="text-center pr-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($unserved_items)): ?>
                            <tr id="emptyRow"><td colspan="9" class="text-center py-5 text-muted font-weight-bold">
                                <i class="fas fa-check-circle text-success fa-3x mb-3 d-block"></i>
                                All orders are cleared for this period!
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($unserved_items as $item): 
                                $pending_qty = $item['quantity'] - $item['served_quantity'];
                                $unserved_value_for_item = $item['rate'] * $pending_qty;
                            ?>
                            <tr class="searchable-row" data-value="<?php echo $unserved_value_for_item; ?>" data-qty="<?php echo $pending_qty; ?>">
                                <td class="pl-4">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input row-checkbox" id="chk_<?php echo $item['item_id']; ?>" value="<?php echo $item['item_id']; ?>">
                                        <label class="custom-control-label" for="chk_<?php echo $item['item_id']; ?>"></label>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-dark font-weight-bold"><?php echo date('h:i A', strtotime($item['created_at'])); ?></div>
                                    <div class="small text-muted"><?php echo date('d M Y', strtotime($item['created_at'])); ?></div>
                                </td>
                                <td><span class="badge badge-dark font-family-monospace" style="font-size: 0.9rem;">#<?php echo htmlspecialchars($item['coupon_id']); ?></span></td>
                                <td>
                                    <div class="text-dark font-weight-bold"><?php echo htmlspecialchars($item['member_name']); ?></div>
                                    <div class="small text-primary"><i class="fas fa-id-card mr-1"></i> <?php echo htmlspecialchars($item['member_uid']); ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center mr-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-utensils text-muted"></i>
                                        </div>
                                        <span class="font-weight-bold text-dark"><?php echo htmlspecialchars($item['menu_item_name']); ?></span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-danger px-2 py-1" style="font-size: 0.9rem;"><?php echo $pending_qty; ?></span>
                                    <?php if($item['served_quantity'] > 0): ?>
                                        <div class="small text-muted mt-1">(Out of <?php echo $item['quantity']; ?>)</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right text-muted">₹<?php echo number_format($item['rate'], 2); ?></td>
                                <td class="text-right font-weight-bold text-danger">₹<?php echo number_format($unserved_value_for_item, 2); ?></td>
                                <td class="text-center pr-4">
                                    <button class="btn btn-refund" 
                                            onclick="openSingleRefundModal(
                                                '<?php echo $item['item_id']; ?>', 
                                                '<?php echo $item['order_id']; ?>', 
                                                '<?php echo $item['member_db_id']; ?>', 
                                                '<?php echo htmlspecialchars($item['coupon_id']); ?>',
                                                '<?php echo htmlspecialchars(addslashes($item['member_name'])); ?>',
                                                '<?php echo htmlspecialchars(addslashes($item['menu_item_name'])); ?>',
                                                '<?php echo $pending_qty; ?>',
                                                '<?php echo $unserved_value_for_item; ?>'
                                            )">
                                        <i class="fas fa-undo-alt mr-1"></i> Refund
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <tr id="searchEmptyRow" style="display: none;">
                            <td colspan="9" class="text-center py-5 text-muted font-weight-bold">
                                <i class="fas fa-search fa-2x mb-2 d-block opacity-50"></i>
                                No matching pending items found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="singleRefundModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold text-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i> Confirm Refund
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
            </div>
            <form method="POST" action="" id="singleRefundForm" onsubmit="this.querySelector('button[type=submit]').innerHTML='<i class=\'fas fa-spinner fa-spin mr-1\'></i> Processing...'; this.querySelector('button[type=submit]').classList.add('disabled');">
                <div class="modal-body p-4">
                    <p class="text-muted mb-4">You are about to cancel this pending item and return the funds directly to the member's wallet.</p>
                    <div class="bg-light p-3 rounded-lg border shadow-sm">
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted font-weight-bold small text-uppercase">Member</span><strong id="modal_member_name" class="text-dark"></strong></div>
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted font-weight-bold small text-uppercase">Coupon</span><strong id="modal_coupon_id" class="text-primary font-family-monospace"></strong></div>
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted font-weight-bold small text-uppercase">Item & Qty</span><strong class="text-dark"><span id="modal_qty"></span>x <span id="modal_item_name"></span></strong></div>
                        <div class="d-flex justify-content-between mt-3 pt-3 border-top"><span class="text-muted font-weight-bold text-uppercase">Refund Amount</span><strong id="modal_amount" class="text-danger h4 m-0 font-weight-bold"></strong></div>
                    </div>
                    
                    <input type="hidden" name="process_refund" value="1">
                    <input type="hidden" name="item_id" id="input_item_id">
                    <input type="hidden" name="order_id" id="input_order_id">
                    <input type="hidden" name="member_db_id" id="input_member_db_id">
                    <input type="hidden" name="coupon_id" id="input_coupon_id">
                    
                    <div class="custom-control custom-switch mt-4 pl-5">
                        <input type="checkbox" class="custom-control-input" id="restoreStockSwitch" name="restore_stock" value="1" checked>
                        <label class="custom-control-label font-weight-bold text-dark" for="restoreStockSwitch">Restore item to inventory stock</label>
                        <small class="form-text text-muted mt-1">Uncheck this if the food was wasted, burnt, or cannot be resold.</small>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary rounded-pill px-4 font-weight-bold" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 font-weight-bold shadow"><i class="fas fa-undo-alt mr-1"></i> Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkRefundModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title font-weight-bold text-white">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Confirm Bulk Refund
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
            </div>
            <form method="POST" action="" id="bulkRefundForm" onsubmit="this.querySelector('button[type=submit]').innerHTML='<i class=\'fas fa-spinner fa-spin mr-1\'></i> Processing...'; this.querySelector('button[type=submit]').classList.add('disabled');">
                <div class="modal-body p-4 text-center">
                    <div class="mb-4">
                        <i class="fas fa-boxes fa-4x text-danger opacity-50 mb-3"></i>
                        <h4 class="font-weight-bold text-dark mb-1">Refund Multiple Items?</h4>
                        <p class="text-muted">You have selected <strong id="bulkItemCount" class="text-danger">0</strong> items for a bulk refund.</p>
                    </div>
                    <div class="alert alert-warning text-left small border-warning">
                        <i class="fas fa-info-circle mr-1"></i> This action will cancel all selected pending items and return funds directly to each member's wallet instantly. This cannot be undone.
                    </div>

                    <div class="text-left mt-3">
                        <input type="hidden" name="process_bulk_refund" value="1">
                        <div id="bulkHiddenInputsContainer"></div>
                        
                        <div class="custom-control custom-switch pl-5 mb-2 mt-2">
                            <input type="checkbox" class="custom-control-input" id="bulkRestoreStockSwitch" name="bulk_restore_stock" value="1" checked>
                            <label class="custom-control-label font-weight-bold text-dark" for="bulkRestoreStockSwitch">Restore items to inventory stock</label>
                            <small class="form-text text-muted mt-1">Uncheck this if the selected food was wasted and cannot be resold.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top-0 justify-content-center pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 font-weight-bold border" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 font-weight-bold shadow"><i class="fas fa-undo-alt mr-1"></i> Process All Selected</button>
                </div>
            </form>
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

    const selectAllCheckbox = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkBtn = document.getElementById('bulkRefundBtn');
    const selectedCountText = document.getElementById('selectedCount');

    function updateBulkBtnState() {
        let checkedCount = 0;
        let visibleUncheckedCount = 0;
        let totalVisibleCount = 0;

        rowCheckboxes.forEach(chk => {
            const row = chk.closest('tr');
            if (row.style.display !== 'none') {
                totalVisibleCount++;
                if (chk.checked) checkedCount++;
                else visibleUncheckedCount++;
            }
        });

        selectedCountText.innerText = checkedCount;
        bulkBtn.disabled = (checkedCount === 0);

        if (totalVisibleCount > 0 && checkedCount === totalVisibleCount) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedCount > 0 && checkedCount < totalVisibleCount) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
    }

    if(selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            rowCheckboxes.forEach(chk => {
                const row = chk.closest('tr');
                if (row.style.display !== 'none') chk.checked = isChecked;
            });
            updateBulkBtnState();
        });
    }

    rowCheckboxes.forEach(chk => chk.addEventListener('change', updateBulkBtnState));
});

function filterTable() {
    const query = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.searchable-row');
    const emptySearchRow = document.getElementById('searchEmptyRow');
    const baseEmptyRow = document.getElementById('emptyRow');
    
    let visibleCount = 0;
    let newTotalValue = 0;
    let newTotalQty = 0;

    rows.forEach(row => {
        const rowText = row.innerText.toLowerCase();
        if (rowText.includes(query)) {
            row.style.display = '';
            visibleCount++;
            newTotalValue += parseFloat(row.getAttribute('data-value'));
            newTotalQty += parseInt(row.getAttribute('data-qty'));
        } else {
            row.style.display = 'none';
            const chk = row.querySelector('.row-checkbox');
            if(chk) chk.checked = false; 
        }
    });

    if (baseEmptyRow) emptySearchRow.style.display = 'none';
    else emptySearchRow.style.display = (visibleCount === 0) ? '' : 'none';

    document.getElementById('totalValueDisplay').innerText = '₹' + newTotalValue.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('totalItemsDisplay').innerHTML = newTotalQty + ' <span style="font-size: 1rem; font-weight:500;">Units</span>';
    
    const event = new Event('change');
    const selectAllCheckbox = document.getElementById('selectAll');
    if(selectAllCheckbox && !selectAllCheckbox.checked) {
        document.querySelectorAll('.row-checkbox')[0]?.dispatchEvent(event);
    }
}

function openSingleRefundModal(itemId, orderId, memberDbId, couponId, memberName, itemName, qty, amount) {
    document.getElementById('modal_member_name').innerText = memberName;
    document.getElementById('modal_coupon_id').innerText = '#' + couponId;
    document.getElementById('modal_item_name').innerText = itemName;
    document.getElementById('modal_qty').innerText = qty;
    document.getElementById('modal_amount').innerText = '₹' + parseFloat(amount).toFixed(2);
    
    document.getElementById('input_item_id').value = itemId;
    document.getElementById('input_order_id').value = orderId;
    document.getElementById('input_member_db_id').value = memberDbId;
    document.getElementById('input_coupon_id').value = couponId;
    
    document.getElementById('restoreStockSwitch').checked = true;

    $('#singleRefundModal').modal('show');
}

function openBulkRefundModal() {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    const container = document.getElementById('bulkHiddenInputsContainer');
    container.innerHTML = ''; 
    
    let count = 0;
    checkedBoxes.forEach(chk => {
        if(chk.closest('tr').style.display !== 'none') {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_items[]';
            input.value = chk.value;
            container.appendChild(input);
            count++;
        }
    });

    if (count > 0) {
        document.getElementById('bulkItemCount').innerText = count;
        document.getElementById('bulkRestoreStockSwitch').checked = true;
        $('#bulkRefundModal').modal('show');
    }
}
</script>

<?php include 'footer.php'; ?>