<?php
ob_start();
include 'header.php';

// --- START PRG (Post-Redirect-Get) PATTERN LOGIC ---

// 1. If coming from the POST form (orders.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $_SESSION['view_order_id'] = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);
    // Redirect to self without parameters to hide URL and prevent form resubmission
    header("Location: order_coupon.php");
    exit;
}

// 2. Fallback: If coming from cart checkout redirect (which sends a GET param)
if (isset($_GET['order_id'])) {
    $_SESSION['view_order_id'] = filter_var($_GET['order_id'], FILTER_VALIDATE_INT);
    // Redirect immediately to hide the parameter from the URL
    header("Location: order_coupon.php");
    exit;
}

// 3. Final Check: Ensure a session variable actually exists
if (!isset($_SESSION['view_order_id'])) {
    header("Location: orders.php");
    exit;
}

$order_id = $_SESSION['view_order_id'];
// --- END PRG PATTERN LOGIC ---


$member_id = $_SESSION['id'];

// Fetch order details and verify ownership
$sql = "SELECT * FROM orders WHERE id = ? AND member_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id, $member_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "<div class='container mt-5'><div class='alert alert-danger shadow-sm border-0' style='border-radius: 12px;'><i class='fas fa-exclamation-circle mr-2'></i> Order not found or access denied.</div></div>";
    include 'footer.php';
    exit;
}

// Verify coupon is for today
$order_date = new DateTime($order['created_at']);
$today = new DateTime('today');
if ($order_date->format('Y-m-d') !== $today->format('Y-m-d')) {
     echo "<div class='container mt-5'><div class='alert alert-warning shadow-sm border-0' style='border-radius: 12px;'><i class='fas fa-clock mr-2'></i> This coupon has expired. Coupons are only valid on the day of purchase.</div></div>";
    include 'footer.php';
    exit;
}

// Fetch settings
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch Items
$sql_items = "SELECT oi.*, m.name as item_name 
              FROM order_items oi 
              JOIN menu m ON oi.menu_id = m.id 
              WHERE oi.order_id = ?";
$stmt_items = $pdo->prepare($sql_items);
$stmt_items->execute([$order_id]);
$order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$qr_data = $order['coupon_id'];
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($qr_data);
$payment_method = isset($order['payment_method']) ? $order['payment_method'] : 'Wallet Balance'; // Default fallback
?>

<style>
    /* Prevent empty gap from header */
    body > .container.mt-4.mb-5:first-of-type { display: none !important; margin: 0 !important; padding: 0 !important; height: 0 !important; border: none !important; }

    body {
        background-color: #f4f7f6;
        font-family: 'Poppins', sans-serif;
    }

    /* Skeleton Loader */
    .skeleton-box {
        background: #e2e5e7;
        background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0));
        background-size: 40px 100%;
        background-repeat: no-repeat;
        animation: shine 1.5s ease infinite;
        border-radius: 12px;
    }
    @keyframes shine { to { background-position: right -40px top 0; } }

    /* Modern Ticket Design */
    .ticket-wrapper {
        max-width: 480px;
        margin: 0 auto;
        filter: drop-shadow(0 15px 35px rgba(0,0,0,0.08));
        transition: transform 0.3s ease;
    }
    .ticket-wrapper:hover {
        transform: translateY(-5px);
    }

    .ticket {
        background: #ffffff;
        border-radius: 16px;
        position: relative;
        overflow: hidden;
    }

    /* Ticket Perforation Effect */
    .ticket-divider {
        position: relative;
        height: 40px;
        background: #ffffff;
    }
    .ticket-divider::before, .ticket-divider::after {
        content: '';
        position: absolute;
        top: 50%;
        width: 40px;
        height: 40px;
        background-color: #f4f7f6; /* matches body bg */
        border-radius: 50%;
        transform: translateY(-50%);
        box-shadow: inset 0 3px 5px rgba(0,0,0,0.02);
    }
    .ticket-divider::before { left: -20px; }
    .ticket-divider::after { right: -20px; }
    
    .ticket-divider-line {
        position: absolute;
        top: 50%;
        left: 25px;
        right: 25px;
        border-top: 2px dashed #dee2e6;
        transform: translateY(-50%);
    }

    /* Typography & Layout inside ticket */
    .ticket-header {
        background: linear-gradient(135deg, #0062E6, #33AEFF);
        color: white;
        padding: 30px 20px;
        text-align: center;
        border-radius: 16px 16px 0 0;
    }
    .ticket-header h3 { font-weight: 700; margin-bottom: 5px; letter-spacing: 0.5px; }
    .ticket-header p { opacity: 0.9; font-size: 0.9rem; margin: 0; }

    .qr-section {
        padding: 30px 20px 10px;
        text-align: center;
    }
    .qr-box {
        background: #fff;
        padding: 15px;
        border-radius: 12px;
        border: 1px solid #e9ecef;
        display: inline-block;
        box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    }
    .qr-box img { width: 160px; height: 160px; }
    
    .coupon-code {
        font-family: 'Courier New', monospace;
        font-size: 1.8rem;
        font-weight: 800;
        color: #212529;
        letter-spacing: 2px;
        margin-top: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    .copy-btn {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #6c757d;
        border-radius: 8px;
        padding: 6px 10px;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .copy-btn:hover { background: #e9ecef; color: #0062E6; }

    .ticket-body { padding: 10px 30px 30px; }
    
    .item-table { width: 100%; margin-bottom: 20px; font-size: 0.95rem; }
    .item-table th { color: #adb5bd; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding-bottom: 10px; border-bottom: 1px solid #f1f3f5; }
    .item-table td { padding: 10px 0; border-bottom: 1px dashed #f1f3f5; color: #495057; }
    .item-table tr:last-child td { border-bottom: none; }
    .item-name { font-weight: 600; color: #212529; }
    
    .ticket-footer-info {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 15px 20px;
        margin-top: 15px;
    }
    
    .paid-badge {
        display: inline-flex;
        align-items: center;
        background: #e8f5e9;
        color: #2e7d32;
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    /* Print Styles */
    @media print {
        body { background: white !important; }
        body * { visibility: hidden; }
        .ticket-wrapper, .ticket-wrapper * { visibility: visible; }
        .ticket-wrapper { 
            position: absolute; left: 0; top: 0; width: 100%; max-width: 100%; 
            filter: none; transform: none !important; margin: 0; 
        }
        .ticket { border: 2px solid #000; border-radius: 0; }
        .ticket-divider::before, .ticket-divider::after { display: none; }
        .ticket-header { background: #fff; color: #000; border-bottom: 2px solid #000; padding: 15px; }
        .ticket-header p { color: #333; }
        nav, footer, .d-print-none, .copy-btn { display: none !important; }
    }
</style>

<div class="container pt-4 pb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4 max-w-480 mx-auto d-print-none" style="max-width: 480px;">
        <a href="orders.php" class="btn btn-light border text-muted font-weight-bold shadow-sm rounded-pill px-3 py-2">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
        <button class="btn btn-primary font-weight-bold shadow-sm rounded-pill px-4 py-2" onclick="window.print()" style="background: linear-gradient(135deg, #0062E6, #33AEFF); border: none;">
            <i class="fas fa-print mr-1"></i> Print
        </button>
    </div>

    <div id="skeleton-ticket" class="ticket-wrapper d-print-none">
        <div class="ticket">
            <div class="skeleton-box" style="height: 120px; border-radius: 16px 16px 0 0;"></div>
            <div class="p-4 text-center">
                <div class="skeleton-box mx-auto mb-3" style="height: 160px; width: 160px; border-radius: 12px;"></div>
                <div class="skeleton-box mx-auto" style="height: 30px; width: 200px; border-radius: 6px;"></div>
            </div>
            <div class="ticket-divider"></div>
            <div class="p-4">
                <div class="skeleton-box mb-3" style="height: 20px; width: 100%; border-radius: 4px;"></div>
                <div class="skeleton-box mb-3" style="height: 20px; width: 100%; border-radius: 4px;"></div>
                <div class="skeleton-box" style="height: 60px; width: 100%; border-radius: 12px; margin-top: 20px;"></div>
            </div>
        </div>
    </div>

    <div id="actual-ticket" class="ticket-wrapper d-none">
        <div class="ticket">
            
            <div class="ticket-header">
                <h3><?php echo htmlspecialchars($settings['institution_name'] ?? 'Institution'); ?></h3>
                <p><?php echo htmlspecialchars($settings['canteen_name'] ?? 'Canteen'); ?></p>
            </div>

            <div class="qr-section">
                <div class="qr-box">
                    <img src="<?php echo $qr_code_url; ?>" alt="Order QR Code">
                </div>
                <div class="coupon-code">
                    <span id="couponText">#<?php echo htmlspecialchars($order['coupon_id']); ?></span>
                    <button class="copy-btn d-print-none" onclick="copyCouponCode()" title="Copy Code">
                        <i class="far fa-copy"></i>
                    </button>
                </div>
                <small class="text-muted font-weight-bold mt-2 d-block">
                    ISSUED: <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?>
                </small>
            </div>

            <div class="ticket-divider d-print-none">
                <div class="ticket-divider-line"></div>
            </div>

            <div class="ticket-body">
                <h6 class="text-uppercase text-muted font-weight-bold mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Order Summary</h6>
                
                <table class="item-table">
                    <thead>
                        <tr>
                            <th class="text-left">Item</th>
                            <th class="text-right">Rate</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_items = 0;
                        $total_quantity = 0;
                        foreach($order_items as $item): 
                            $total_items++;
                            $total_quantity += $item['quantity'];
                            // Calculate rate based on amount and quantity (fallback to 0 to prevent division by zero)
                            $rate = $item['quantity'] > 0 ? ($item['amount'] / $item['quantity']) : 0;
                        ?>
                        <tr>
                            <td class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td class="text-right text-muted">₹<?php echo number_format($rate, 2); ?></td>
                            <td class="text-center font-weight-bold">x<?php echo $item['quantity']; ?></td>
                            <td class="text-right font-weight-bold text-dark">₹<?php echo number_format($item['amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="ticket-footer-info">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Total Quantity:</span>
                        <span class="font-weight-bold text-dark"><?php echo $total_quantity; ?> Items</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small">Paid Via:</span>
                        <span class="paid-badge">
                            <i class="fas fa-check-circle mr-1"></i> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $payment_method))); ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-end pt-3 border-top">
                        <span class="text-uppercase text-muted font-weight-bold" style="letter-spacing: 1px; font-size: 0.85rem;">Grand Total</span>
                        <h3 class="mb-0 font-weight-bold text-primary">₹<?php echo number_format($order['total_amount'], 2); ?></h3>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <p class="mb-0 font-weight-bold text-danger" style="font-size: 0.85rem;">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Valid only for today
                    </p>
                    <small class="text-muted">Present this ticket at the delivery counter.</small>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
// Logic to handle Skeleton Loader
document.addEventListener('DOMContentLoaded', () => {
    // Hide global loader if exists
    const globalLoader = document.getElementById('loading-overlay');
    if(globalLoader) {
        globalLoader.style.display = 'none';
        globalLoader.classList.add('d-none');
    }

    // Switch from skeleton to actual content after short delay
    setTimeout(() => {
        document.getElementById('skeleton-ticket').classList.add('d-none');
        const actualTicket = document.getElementById('actual-ticket');
        actualTicket.classList.remove('d-none');
        // Trigger reflow for animation
        void actualTicket.offsetWidth;
        actualTicket.style.opacity = "1";
    }, 400); // 400ms smooth transition
});

// Interactive Copy Button Logic
function copyCouponCode() {
    const text = document.getElementById('couponText').innerText.replace('#', '').trim();
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('.copy-btn');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(() => {
            btn.innerHTML = originalHtml;
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy text: ', err);
    });
}
</script>

<?php include 'footer.php'; ?>