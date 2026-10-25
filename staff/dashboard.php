<?php
ob_start(); // Start output buffering at the absolute beginning
include 'header.php';
require_once '../includes/db.php';

$order = null;
$order_items = [];
$error_msg = '';
$success_msg = '';
$coupon_id = '';

// PRG Part 2: Display messages from URL
if (isset($_GET['success'])) {
    $success_msg = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error_msg = $_GET['error'];
}

// PRG Part 1: Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_served'])) {
    $coupon_id_posted = $_POST['coupon_id'];
    $order_id = $_POST['order_id'];
    $items_to_serve = isset($_POST['serve_item']) ? $_POST['serve_item'] : [];
    $staff_id = $_SESSION['id'];
    
    if (empty($items_to_serve)) {
        header("Location: dashboard.php?coupon_id={$coupon_id_posted}&error=" . urlencode("No items selected to serve."));
        exit;
    }

    try {
        $pdo->beginTransaction();

        foreach ($items_to_serve as $item_id) {
            $qty_to_serve = (int)$_POST['serve_qty'][$item_id];

            // Get current served quantity
            $stmt = $pdo->prepare("SELECT quantity, served_quantity FROM order_items WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            $remaining_qty = $item['quantity'] - $item['served_quantity'];

            if ($qty_to_serve > 0 && $qty_to_serve <= $remaining_qty) {
                // Update served_quantity
                $sql_update = "UPDATE order_items SET served_quantity = served_quantity + ? WHERE id = ?";
                $pdo->prepare($sql_update)->execute([$qty_to_serve, $item_id]);

                // Add to serving history
                $sql_history = "INSERT INTO serving_history (order_item_id, staff_id, quantity_served) VALUES (?, ?, ?)";
                $pdo->prepare($sql_history)->execute([$item_id, $staff_id, $qty_to_serve]);
            }
        }

        // Check and update overall order status
        $stmt_check = $pdo->prepare("SELECT SUM(quantity) as total_qty, SUM(served_quantity) as total_served FROM order_items WHERE order_id = ?");
        $stmt_check->execute([$order_id]);
        $status_check = $stmt_check->fetch();
        
        $new_status = 'partially_served';
        if ($status_check['total_served'] >= $status_check['total_qty']) {
            $new_status = 'fully_served';
        }
        
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$new_status, $order_id]);

        $pdo->commit();
        header("Location: dashboard.php?coupon_id={$coupon_id_posted}&success=" . urlencode("Order updated successfully."));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: dashboard.php?coupon_id={$coupon_id_posted}&error=" . urlencode("Database error: " . $e->getMessage()));
        exit;
    }
}

// Handle finding the order
if (isset($_GET['coupon_id'])) {
    $coupon_id = trim($_GET['coupon_id']);
    
    // Today's date for coupon validity
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');

    // Fetch order details with member ID
    $sql = "SELECT o.*, m.name as member_name, m.member_id 
            FROM orders o 
            JOIN members m ON o.member_id = m.id
            WHERE o.coupon_id = ? AND o.created_at BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$coupon_id, $today_start, $today_end]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        if ($order['status'] == 'fully_served') {
            $error_msg = "This coupon has already been fully served.";
        } else {
            // Fetch order items
            $sql_items = "SELECT oi.*, mn.name as item_name 
                        FROM order_items oi 
                        JOIN menu mn ON oi.menu_id = mn.id 
                        WHERE oi.order_id = ?";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([$order['id']]);
            $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $error_msg = "Invalid or expired coupon ID.";
    }
}
?>

<style>
    /* Skeleton Loader Styles */
    .skeleton-wrapper { width: 100%; }
    .skeleton-box {
        background: #e2e5e7;
        background: linear-gradient(90deg, #e2e5e7 25%, #f0f2f3 50%, #e2e5e7 75%);
        background-size: 200% 100%;
        animation: loadingShimmer 1.5s infinite;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .skeleton-title { height: 30px; width: 50%; margin-bottom: 25px; }
    .skeleton-text { height: 20px; width: 100%; }
    
    @keyframes loadingShimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Modern Card UI */
    .profile-card {
        background: #ffffff;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: none;
        overflow: hidden;
    }
    .card-title-modern {
        font-weight: 600;
        color: #333;
        margin-bottom: 20px;
        border-bottom: 2px solid #f4f7f6;
        padding-bottom: 10px;
    }

    /* Form Controls */
    .form-control-modern {
        border-radius: 10px;
        padding: 12px 15px;
        border: 1px solid #ced4da;
        transition: all 0.3s ease;
    }
    .form-control-modern:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 98, 230, 0.25);
        border-color: #0062E6;
    }
    .btn-primary-modern {
        background: #0062E6;
        border: none;
        border-radius: 10px;
        padding: 10px 25px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .btn-primary-modern:hover {
        background: #004bb5;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 98, 230, 0.3);
    }
    .btn-modern-lg { padding: 12px 30px; font-size: 1.1rem; }
    
    .transition-all { transition: all 0.3s ease; }

    /* Interactive Table Styles */
    .row-selected {
        background-color: rgba(0, 98, 230, 0.04) !important;
        border-left: 4px solid #0062E6 !important;
    }
    .row-deselected {
        opacity: 0.6;
        background-color: #f8f9fa !important;
        border-left: 4px solid transparent !important;
    }
    .table-hover tbody tr { transition: all 0.2s ease; border-left: 4px solid transparent; }

    /* Quantity Controls Styles */
    .qty-btn {
        width: 32px; 
        height: 32px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-weight: bold;
        transition: all 0.2s ease;
        background-color: #f8f9fa;
        color: #495057;
    }
    .qty-btn:hover:not(:disabled) {
        background-color: #e2e6ea;
        color: #000;
    }
    .qty-btn:active:not(:disabled) {
        transform: scale(0.9);
    }
    .qty-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Hide spin arrows in number input */
    input[type=number].serve-qty-input::-webkit-inner-spin-button, 
    input[type=number].serve-qty-input::-webkit-outer-spin-button { 
        -webkit-appearance: none; 
        margin: 0; 
    }
    input[type=number].serve-qty-input {
        appearance: textfield;
        -moz-appearance: textfield;
        pointer-events: none; /* Prevent manual typing to force button usage */
    }

    /* Toast Notifications */
    #toast-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1060; pointer-events: none; width: 90%; max-width: 350px;}
    .custom-toast { color: white; border-radius: 50px; padding: 12px 25px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; font-weight: 500; opacity: 0; transform: translateY(-20px); transition: all 0.3s ease; backdrop-filter: blur(5px); text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2);}
    .custom-toast.show { opacity: 1; transform: translateY(0); }
    .toast-success { background: rgba(40, 167, 69, 0.9); }
    .toast-error { background: rgba(220, 53, 69, 0.9); }
    
    /* QR Scanner Canvas Border Radius Fix */
    #reader { border-radius: 15px; overflow: hidden; border: 2px solid #e9ecef; }
    #reader video { border-radius: 15px; }
</style>

<div id="toast-container"></div>

<div id="skeleton-loader" class="profile-card p-4 mb-4 skeleton-wrapper">
    <div class="skeleton-box skeleton-title"></div>
    <div class="skeleton-box skeleton-text" style="height: 50px;"></div>
    <div class="skeleton-box skeleton-text"></div>
</div>

<div id="actual-content" style="display: none;">
    
    <div class="profile-card p-4 mb-4">
        <h5 class="card-title-modern text-primary"><i class="fas fa-qrcode mr-2"></i> Scan/Enter Coupon ID</h5>
        
        <div id="reader" style="width: 100%; max-width: 500px; margin: 0 auto; display: none;" class="mb-4 shadow-sm"></div>

        <form action="dashboard.php" method="get" id="searchForm" class="d-flex flex-column flex-md-row align-items-center">
            <div class="flex-grow-1 w-100 mb-2 mb-md-0 mr-md-2">
                <input type="text" class="form-control form-control-modern w-100" name="coupon_id" id="coupon_id" placeholder="Enter Coupon ID" value="<?php echo htmlspecialchars($coupon_id); ?>" autofocus required>
            </div>
            <button type="submit" id="findOrderBtn" class="btn btn-primary-modern text-white mb-2 mb-md-0 mr-md-2 w-100" style="max-width: 150px;">Find Order</button>
            <button type="button" class="btn btn-secondary mb-2 mb-md-0 w-100 transition-all" style="border-radius: 10px; padding: 10px 25px; max-width: 150px;" id="btn-scan-qr">
                <i class="fas fa-camera mr-1"></i> Scan QR
            </button>
        </form>
    </div>

    <?php if ($order && $order['status'] !== 'fully_served'): ?>
    <div class="profile-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h5 class="card-title-modern text-primary mb-0 border-0 pb-0"><i class="fas fa-list-alt mr-2"></i> Order Details</h5>
            <span class="badge badge-warning px-3 py-2 rounded-pill"><i class="fas fa-clock mr-1"></i> Pending Service</span>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-4 mb-2 mb-md-0">
                <div class="p-3 bg-light rounded text-center h-100 d-flex flex-column justify-content-center border" style="border-color: #e9ecef !important;">
                    <strong class="text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">Coupon Number</strong>
                    <span class="text-primary font-weight-bold mt-1" style="font-size:1.4rem;"><?php echo htmlspecialchars($order['coupon_id']); ?></span>
                </div>
            </div>
            <div class="col-md-4 mb-2 mb-md-0">
                <div class="p-3 bg-light rounded text-center h-100 d-flex flex-column justify-content-center border" style="border-color: #e9ecef !important;">
                    <strong class="text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">Member Info</strong>
                    <span class="text-dark font-weight-bold mt-1" style="font-size: 1.1rem;"><?php echo htmlspecialchars($order['member_name']); ?></span>
                    <div><span class="badge badge-info mt-1 px-2 py-1"><i class="fas fa-id-badge mr-1"></i> <?php echo htmlspecialchars($order['member_id']); ?></span></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded text-center h-100 d-flex flex-column justify-content-center border" style="border-color: #e9ecef !important;">
                    <strong class="text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">Order Time</strong>
                    <span class="text-dark font-weight-bold mt-1"><?php echo date('d M Y', strtotime($order['created_at'])); ?></span>
                    <span class="text-muted small"><?php echo date('h:i A', strtotime($order['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <form action="dashboard.php" method="post" id="serveForm">
            <input type="hidden" name="coupon_id" value="<?php echo htmlspecialchars($order['coupon_id']); ?>">
            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
            
            <div class="table-responsive rounded shadow-sm mb-4 border">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th class="text-center align-middle" style="width: 60px;">
                                <div class="custom-control custom-checkbox" title="Select/Deselect All">
                                    <input type="checkbox" class="custom-control-input" id="selectAll" checked>
                                    <label class="custom-control-label" for="selectAll" style="cursor:pointer;"></label>
                                </div>
                            </th>
                            <th class="text-uppercase text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">Item Name</th>
                            <th class="text-center text-uppercase text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">Total Qty</th>
                            <th class="text-center text-uppercase text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">Served</th>
                            <th class="text-center text-uppercase text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">Pending</th>
                            <th class="text-center text-uppercase text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px; width: 160px;">Serve Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_items = 0;
                        $total_quantity = 0;
                        foreach($order_items as $item): 
                            $total_items++;
                            $total_quantity += $item['quantity'];
                            $pending_qty = $item['quantity'] - $item['served_quantity'];
                        ?>
                        <tr class="item-row <?php echo ($pending_qty <= 0) ? 'table-success' : 'row-selected'; ?>">
                            <td class="text-center align-middle">
                                <?php if($pending_qty > 0): ?>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input serve-checkbox" id="check_<?php echo $item['id']; ?>" name="serve_item[]" value="<?php echo $item['id']; ?>" checked>
                                    <label class="custom-control-label" for="check_<?php echo $item['id']; ?>" style="cursor:pointer;"></label>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle font-weight-bold" <?php echo ($pending_qty <= 0) ? 'style="text-decoration: line-through; color: #6c757d;"' : ''; ?>>
                                <?php echo htmlspecialchars($item['item_name']); ?>
                            </td>
                            <td class="text-center align-middle"><?php echo $item['quantity']; ?></td>
                            <td class="text-center align-middle text-success font-weight-bold"><?php echo $item['served_quantity']; ?></td>
                            <td class="text-center align-middle text-danger font-weight-bold"><?php echo $pending_qty; ?></td>
                            <td class="align-middle">
                                <?php if($pending_qty > 0): ?>
                                <div class="d-flex justify-content-center align-items-center qty-control-group transition-all">
                                    <button type="button" class="btn border rounded-circle shadow-sm qty-btn qty-minus" data-id="<?php echo $item['id']; ?>" data-min="1"><i class="fas fa-minus" style="font-size: 0.7rem;"></i></button>
                                    
                                    <input type="number" name="serve_qty[<?php echo $item['id']; ?>]" id="qty_<?php echo $item['id']; ?>" class="form-control form-control-sm text-center font-weight-bold serve-qty-input mx-2 border-0 bg-transparent" value="<?php echo $pending_qty; ?>" min="1" max="<?php echo $pending_qty; ?>" style="width: 45px; font-size: 1.1rem;" readonly>
                                    
                                    <button type="button" class="btn border rounded-circle shadow-sm qty-btn qty-plus" data-id="<?php echo $item['id']; ?>" data-max="<?php echo $pending_qty; ?>"><i class="fas fa-plus" style="font-size: 0.7rem;"></i></button>
                                </div>
                                <?php else: ?>
                                <span class="badge badge-success px-3 py-2 rounded-pill"><i class="fas fa-check"></i> Served</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-right">
                <button type="button" id="submitServeBtn" class="btn btn-success btn-modern-lg rounded-pill shadow-sm transition-all" data-toggle="modal" data-target="#confirmServeModal">
                    <i class="fas fa-check-circle mr-2"></i> Mark Selected as Served
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="confirmServeModal" tabindex="-1" role="dialog" aria-labelledby="confirmServeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-primary text-white" style="border-radius: 15px 15px 0 0;">
                <h5 class="modal-title font-weight-bold" id="confirmServeModalLabel"><i class="fas fa-question-circle mr-2"></i> Confirm Action</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="mb-3">
                    <i class="fas fa-check-double text-success" style="font-size: 3rem;"></i>
                </div>
                <h5 class="font-weight-bold text-dark mb-2">Mark as Served?</h5>
                <p class="text-muted mb-0">Are you sure you want to mark the selected quantities as served? This action cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center bg-light" style="border-radius: 0 0 15px 15px;">
                <button type="button" class="btn btn-secondary px-4 rounded-pill font-weight-bold" data-dismiss="modal">Cancel</button>
                <button type="submit" form="serveForm" name="mark_served" class="btn btn-success px-4 rounded-pill font-weight-bold shadow-sm">
                    <i class="fas fa-check mr-1"></i> Yes, Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Skeleton Loader transition 
    setTimeout(function() {
        document.getElementById('skeleton-loader').style.display = 'none';
        document.getElementById('actual-content').style.display = 'block';
    }, 400);

    // Toast Notification System
    function showToast(message, type) {
        const container = document.getElementById('toast-container');
        if(!container) return;
        const toast = document.createElement('div');
        toast.className = `custom-toast ${type === 'success' ? 'toast-success' : 'toast-error'} shadow-lg mx-auto`;
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i> ${message}`;
        container.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    <?php if($success_msg): ?>
        showToast(<?php echo json_encode($success_msg); ?>, 'success');
    <?php endif; ?>

    <?php if($error_msg): ?>
        showToast(<?php echo json_encode($error_msg); ?>, 'error');
    <?php endif; ?>

    // --- Interactive Table Logic (Select All, +/- Buttons & Row Highlighting) ---
    const selectAll = document.getElementById('selectAll');
    const serveCheckboxes = document.querySelectorAll('.serve-checkbox');
    const submitBtn = document.getElementById('submitServeBtn');

    // Handle Minus Button Click
    document.querySelectorAll('.qty-minus').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if(this.disabled) return;
            const id = this.getAttribute('data-id');
            const min = parseInt(this.getAttribute('data-min'));
            const input = document.getElementById('qty_' + id);
            let val = parseInt(input.value);
            
            if (val > min) {
                input.value = val - 1;
            }
        });
    });

    // Handle Plus Button Click
    document.querySelectorAll('.qty-plus').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if(this.disabled) return;
            const id = this.getAttribute('data-id');
            const max = parseInt(this.getAttribute('data-max'));
            const input = document.getElementById('qty_' + id);
            let val = parseInt(input.value);
            
            if (val < max) {
                input.value = val + 1;
            }
        });
    });

    if (serveCheckboxes.length > 0) {
        // Toggle all checkboxes when 'Select All' is clicked
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                serveCheckboxes.forEach(cb => {
                    cb.checked = this.checked;
                    // Trigger the change event manually to update row styles
                    cb.dispatchEvent(new Event('change'));
                });
            });
        }

        // Handle individual checkbox changes
        serveCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const tr = this.closest('tr');
                const qtyGroup = tr.querySelector('.qty-control-group');
                const minusBtn = tr.querySelector('.qty-minus');
                const plusBtn = tr.querySelector('.qty-plus');
                
                // Update Row Styles & Button state
                if (this.checked) {
                    tr.classList.remove('row-deselected');
                    tr.classList.add('row-selected');
                    if(qtyGroup) {
                        qtyGroup.style.opacity = '1';
                        if(minusBtn) minusBtn.disabled = false;
                        if(plusBtn) plusBtn.disabled = false;
                    }
                } else {
                    tr.classList.remove('row-selected');
                    tr.classList.add('row-deselected');
                    if(qtyGroup) {
                        qtyGroup.style.opacity = '0.5';
                        if(minusBtn) minusBtn.disabled = true;
                        if(plusBtn) plusBtn.disabled = true;
                    }
                }

                // Check if ALL are selected to update the Select All checkbox
                let allChecked = true;
                let anyChecked = false;
                serveCheckboxes.forEach(c => {
                    if (!c.checked) allChecked = false;
                    if (c.checked) anyChecked = true;
                });
                
                if (selectAll) selectAll.checked = allChecked;

                // Disable Submit button if nothing is selected
                if (submitBtn) {
                    submitBtn.disabled = !anyChecked;
                    if(!anyChecked) {
                        submitBtn.classList.replace('btn-success', 'btn-secondary');
                    } else {
                        submitBtn.classList.replace('btn-secondary', 'btn-success');
                    }
                }
            });
        });
    } else if (selectAll) {
        // If there are no items to serve, disable select all
        selectAll.disabled = true;
    }


    // --- QR Scanner Auto-Submit Logic ---
    const scannerButton = document.getElementById('btn-scan-qr');
    const readerDiv = document.getElementById('reader');
    const searchForm = document.getElementById('searchForm');
    const couponInput = document.getElementById('coupon_id');
    const findOrderBtn = document.getElementById('findOrderBtn');
    let html5QrcodeScanner = null;
    let scanProcessed = false; // Flag to prevent duplicate submissions

    if (scannerButton) {
        scannerButton.addEventListener('click', function() {
            if (readerDiv.style.display === 'none') {
                readerDiv.style.display = 'block';
                scanProcessed = false; // Reset flag when opening scanner
                
                if (!html5QrcodeScanner) {
                    html5QrcodeScanner = new Html5QrcodeScanner(
                        "reader", 
                        { fps: 10, qrbox: {width: 250, height: 250} },
                        false
                    );
                    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                }
                
                scannerButton.innerHTML = '<i class="fas fa-times mr-1"></i> Close Scanner';
                scannerButton.classList.remove('btn-secondary');
                scannerButton.classList.add('btn-danger');
            } else {
                closeScanner();
            }
        });
    }

    function closeScanner() {
        readerDiv.style.display = 'none';
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear().then(() => {
                html5QrcodeScanner = null;
                readerDiv.innerHTML = ""; 
            }).catch(error => {
                console.error("Failed to clear scanner: ", error);
            });
        }
        
        scannerButton.innerHTML = '<i class="fas fa-camera mr-1"></i> Scan QR';
        scannerButton.classList.remove('btn-danger');
        scannerButton.classList.add('btn-secondary');
    }

    function onScanSuccess(decodedText, decodedResult) {
        if (scanProcessed) return; // Halt if already processed
        scanProcessed = true; // Set flag to prevent rapid repeated scans

        // 1. Set the decoded text into the input field
        couponInput.value = decodedText;
        
        // 2. Visual Feedback: Show user we caught it and are loading
        if(findOrderBtn) {
            findOrderBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing...';
            findOrderBtn.disabled = true;
        }

        // 3. Pause the scanner immediately (better than clear() to prevent navigation blocking)
        if (html5QrcodeScanner) {
            html5QrcodeScanner.pause(true);
        }

        // 4. Instantly auto-submit the form
        searchForm.submit();
    }

    function onScanFailure(error) {
        // Handle scan failure silently to avoid console spam
    }
});
</script>

<?php 
ob_end_flush();
include 'footer.php'; 
?>