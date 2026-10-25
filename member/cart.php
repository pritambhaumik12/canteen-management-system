<?php
ob_start(); // Start output buffering to prevent HTML output during AJAX requests
include 'header.php';

$member_id = $_SESSION['id'];

// 1. GLOBAL STOCK RESTORATION: Clean up expired reservations
$stmt_expired = $pdo->query("SELECT id, menu_id, quantity, member_id FROM cart_reservations WHERE expires_at < NOW()");
$expired_items = $stmt_expired->fetchAll(PDO::FETCH_ASSOC);

if (!empty($expired_items)) {
    $pdo->beginTransaction();
    try {
        $restore_stmt = $pdo->prepare("UPDATE menu SET stock = stock + ? WHERE id = ?");
        $delete_stmt = $pdo->prepare("DELETE FROM cart_reservations WHERE id = ?");
        
        foreach ($expired_items as $exp) {
            $restore_stmt->execute([$exp['quantity'], $exp['menu_id']]);
            $delete_stmt->execute([$exp['id']]);
            
            // Remove from current user's session if expired
            if ($exp['member_id'] == $member_id && isset($_SESSION['cart'][$exp['menu_id']])) {
                unset($_SESSION['cart'][$exp['menu_id']]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

// 2. AJAX HANDLER: REAL-TIME CART UPDATES (Quantity Adjustments)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_cart'])) {
    while (ob_get_level() > 0) { ob_end_clean(); } // Clear header HTML
    header('Content-Type: application/json');
    $item_id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
    $new_qty = (int)$_POST['new_qty'];
    $old_qty = isset($_SESSION['cart'][$item_id]) ? (int)$_SESSION['cart'][$item_id] : 0;

    if (!$item_id || $new_qty < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
        exit;
    }

    if ($new_qty === $old_qty) {
        echo json_encode(['status' => 'success', 'message' => 'No change needed.']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        if ($new_qty < $old_qty) {
            // USER REDUCED QUANTITY OR REMOVED ITEM -> RESTORE STOCK
            $diff = $old_qty - $new_qty;
            
            $stmt_restore = $pdo->prepare("UPDATE menu SET stock = stock + ? WHERE id = ?");
            $stmt_restore->execute([$diff, $item_id]);

            if ($new_qty > 0) {
                $stmt_res = $pdo->prepare("UPDATE cart_reservations SET quantity = ? WHERE member_id = ? AND menu_id = ?");
                $stmt_res->execute([$new_qty, $member_id, $item_id]);
                $_SESSION['cart'][$item_id] = $new_qty;
            } else {
                $stmt_res = $pdo->prepare("DELETE FROM cart_reservations WHERE member_id = ? AND menu_id = ?");
                $stmt_res->execute([$member_id, $item_id]);
                unset($_SESSION['cart'][$item_id]);
            }
        } elseif ($new_qty > $old_qty) {
            // USER INCREASED QUANTITY -> DEDUCT STOCK IF AVAILABLE
            $diff = $new_qty - $old_qty;
            
            $stmt_lock = $pdo->prepare("SELECT stock FROM menu WHERE id = ? FOR UPDATE");
            $stmt_lock->execute([$item_id]);
            $current_stock = $stmt_lock->fetchColumn();

            if ($current_stock !== false && $current_stock >= $diff) {
                $stmt_deduct = $pdo->prepare("UPDATE menu SET stock = stock - ? WHERE id = ?");
                $stmt_deduct->execute([$diff, $item_id]);

                $stmt_res = $pdo->prepare("UPDATE cart_reservations SET quantity = ?, expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE member_id = ? AND menu_id = ?");
                $stmt_res->execute([$new_qty, $member_id, $item_id]);
                
                $_SESSION['cart'][$item_id] = $new_qty;
            } else {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Requested quantity exceeds available stock.']);
                exit;
            }
        }
        $pdo->commit();

        // Calculate dynamic response data
        $item_total = 0;
        if ($new_qty > 0) {
            $stmt_price = $pdo->prepare("SELECT price FROM menu WHERE id = ?");
            $stmt_price->execute([$item_id]);
            $price = $stmt_price->fetchColumn();
            $item_total = $price * $new_qty;
        }

        $grand_total = 0;
        $unique_cart_count = !empty($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
        $total_cart_count = 0;
        
        if (!empty($_SESSION['cart'])) {
            $item_ids = array_keys($_SESSION['cart']);
            $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
            $stmt_gt = $pdo->prepare("SELECT id, price FROM menu WHERE id IN ($placeholders)");
            $stmt_gt->execute($item_ids);
            $prices = $stmt_gt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($_SESSION['cart'] as $id => $q) {
                if (isset($prices[$id])) {
                    $grand_total += $prices[$id] * $q;
                }
                $total_cart_count += $q; 
            }
        }

        $stmt_my_res = $pdo->prepare("SELECT MIN(expires_at) as exp FROM cart_reservations WHERE member_id = ?");
        $stmt_my_res->execute([$member_id]);
        $my_expire = $stmt_my_res->fetchColumn();
        $remaining_seconds = $my_expire ? (strtotime($my_expire) - time()) : 0;

        echo json_encode([
            'status' => 'success',
            'new_qty' => $new_qty,
            'item_total' => $item_total,
            'grand_total' => $grand_total,
            'unique_count' => $unique_cart_count,
            'cart_count' => $total_cart_count,
            'remaining_seconds' => $remaining_seconds
        ]);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'System error updating cart.']);
        exit;
    }
}

// 2.5 AJAX HANDLER: PLANNER ADD TO CART (Direct Additions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_planner_add'])) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json');
    $item_ids = json_decode($_POST['item_ids'], true);
    
    if (!is_array($item_ids) || empty($item_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid items.']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $added_count = 0;
        foreach ($item_ids as $item_id) {
            $item_id = (int)$item_id;
            $current_qty = isset($_SESSION['cart'][$item_id]) ? $_SESSION['cart'][$item_id] : 0;
            $new_qty = $current_qty + 1;
            
            $stmt_lock = $pdo->prepare("SELECT stock FROM menu WHERE id = ? FOR UPDATE");
            $stmt_lock->execute([$item_id]);
            $current_stock = $stmt_lock->fetchColumn();
            
            if ($current_stock !== false && $current_stock >= 1) {
                $stmt_deduct = $pdo->prepare("UPDATE menu SET stock = stock - 1 WHERE id = ?");
                $stmt_deduct->execute([$item_id]);
                
                if ($current_qty > 0) {
                    $stmt_res = $pdo->prepare("UPDATE cart_reservations SET quantity = ?, expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE member_id = ? AND menu_id = ?");
                    $stmt_res->execute([$new_qty, $member_id, $item_id]);
                } else {
                    $stmt_res = $pdo->prepare("INSERT INTO cart_reservations (member_id, menu_id, quantity, expires_at) VALUES (?, ?, 1, DATE_ADD(NOW(), INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE quantity = quantity + 1, expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)");
                    $stmt_res->execute([$member_id, $item_id]);
                }
                $_SESSION['cart'][$item_id] = $new_qty;
                $added_count++;
            }
        }
        
        if ($added_count > 0) {
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => "Successfully added to cart!"]);
        } else {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Item(s) out of stock.']);
        }
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'System error.']);
        exit;
    }
}

// 3. PLACE ORDER (CHECKOUT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $cart_items = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

    if (empty($cart_items)) {
        header("Location: cart.php?error=" . urlencode("Your cart is empty."));
        exit;
    }

    try {
        $t_pin_entered = $_POST['t_pin'] ?? '';

        // Verify T-PIN Before Beginning Transaction
        $stmt_tpin = $pdo->prepare("SELECT t_pin FROM members WHERE id = ?");
        $stmt_tpin->execute([$member_id]);
        $stored_tpin = $stmt_tpin->fetchColumn();

        if (empty($stored_tpin)) {
            throw new Exception("Please configure your T-PIN in your profile settings before placing an order.");
        }

        if ($stored_tpin !== $t_pin_entered) {
            throw new Exception("Invalid T-PIN entered. Transaction failed.");
        }

        $pdo->beginTransaction();

        $item_ids = array_keys($cart_items);
        $sql_items = "SELECT * FROM menu WHERE id IN (" . implode(',', array_fill(0, count($item_ids), '?')) . ")";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute($item_ids);
        $menu_data = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        $grand_total = 0;
        $order_details = [];
        foreach ($menu_data as $item) {
            $quantity = $cart_items[$item['id']];
            $grand_total += $item['price'] * $quantity;
            $order_details[] = ['item' => $item, 'quantity' => $quantity];
        }

        $stmt_wallet = $pdo->prepare("SELECT balance FROM wallet WHERE member_id = ? FOR UPDATE");
        $stmt_wallet->execute([$member_id]);
        $balance = $stmt_wallet->fetchColumn();
        
        if ($balance < $grand_total) {
            throw new Exception("Insufficient wallet balance. Please recharge your wallet.");
        }

        // Generate Unique Serial Coupon ID for Today (YYYYMMDD-000001)
        $today_date_str = date('Ymd');
        $today_start = date('Y-m-d 00:00:00');
        $sql_coupon = "SELECT COUNT(*) FROM orders WHERE created_at >= ?";
        $stmt_coupon = $pdo->prepare($sql_coupon);
        $stmt_coupon->execute([$today_start]);
        $daily_order_count = $stmt_coupon->fetchColumn();
        $coupon_id = $today_date_str . '-' . str_pad($daily_order_count + 1, 6, '0', STR_PAD_LEFT);
        
        $checkout_time = !empty($_POST['checkout_time']) ? $_POST['checkout_time'] : null;

        $sql_order = "INSERT INTO orders (coupon_id, member_id, total_amount, expected_checkout_time) VALUES (?, ?, ?, ?)";
        $stmt_order = $pdo->prepare($sql_order);
        $stmt_order->execute([$coupon_id, $member_id, $grand_total, $checkout_time]);
        $order_id = $pdo->lastInsertId();

        $sql_oi = "INSERT INTO order_items (order_id, menu_id, quantity, rate, amount) VALUES (?, ?, ?, ?, ?)";
        $stmt_oi = $pdo->prepare($sql_oi);

        foreach ($order_details as $detail) {
            $item = $detail['item'];
            $quantity = $detail['quantity'];
            $amount = $item['price'] * $quantity;
            $stmt_oi->execute([$order_id, $item['id'], $quantity, $item['price'], $amount]);
        }

        $sql_update_wallet = "UPDATE wallet SET balance = balance - ? WHERE member_id = ?";
        $pdo->prepare($sql_update_wallet)->execute([$grand_total, $member_id]);

        $sql_trans = "INSERT INTO transactions (member_id, type, amount, description) VALUES (?, 'debit', ?, ?)";
        $pdo->prepare($sql_trans)->execute([$member_id, $grand_total, "Order placed: #{$coupon_id}"]);

        // Clear Reservations
        $pdo->prepare("DELETE FROM cart_reservations WHERE member_id = ?")->execute([$member_id]);

        // === QUEUE EMAIL CONFIRMATION LOGIC ===
        $stmt_member = $pdo->prepare("SELECT name, email FROM members WHERE id = ?");
        $stmt_member->execute([$member_id]);
        $member_info = $stmt_member->fetch(PDO::FETCH_ASSOC);

        if (!empty($member_info['email'])) {
            $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $settings_data = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
            $canteen_name_setting = $settings_data['canteen_name'] ?? 'Canteen System';
            $inst_name = $settings_data['institution_name'] ?? 'Institution';
            $inst_logo = $settings_data['institution_logo'] ?? '';
            $cant_logo = $settings_data['canteen_logo'] ?? '';

            $subject = "Order Confirmation - Coupon #$coupon_id";
            $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($coupon_id);
            $sender_name = "{$canteen_name_setting} - {$inst_name}";
            $transaction_date_time = date('d M Y, h:i A');

            $embeddedImages = [];
            $header_html = '<div style="text-align: center; margin-bottom: 25px;">';
            
            if ($inst_logo || $cant_logo) {
                $header_html .= '<div style="margin-bottom: 15px;">';
                if ($inst_logo) {
                    $inst_path = dirname(__DIR__) . '/assets/images/logos/' . $inst_logo;
                    if (file_exists($inst_path)) {
                        $embeddedImages['inst_logo_cid'] = $inst_path;
                        $header_html .= '<img src="cid:inst_logo_cid" alt="'.htmlspecialchars($inst_name).'" style="max-height: 70px; margin: 0 15px; vertical-align: middle;">';
                    }
                }
                if ($cant_logo) {
                    $cant_path = dirname(__DIR__) . '/assets/images/logos/' . $cant_logo;
                    if (file_exists($cant_path)) {
                        $embeddedImages['cant_logo_cid'] = $cant_path;
                        $header_html .= '<img src="cid:cant_logo_cid" alt="'.htmlspecialchars($canteen_name_setting).'" style="max-height: 70px; margin: 0 15px; vertical-align: middle;">';
                    }
                }
                $header_html .= '</div>';
            }

            $header_html .= '<h1 style="color: #1e293b; margin: 0; font-size: 22px; font-weight: 800; text-transform: uppercase;">'.htmlspecialchars($inst_name).'</h1>';
            $header_html .= '<p style="color: #64748b; margin: 5px 0 0 0; font-size: 16px; font-weight: 600;">'.htmlspecialchars($canteen_name_setting).'</p>';
            $header_html .= '</div>';

            $items_html = '<table style="width:100%; border-collapse: collapse; margin-top: 15px;" border="1" cellpadding="8">';
            $items_html .= '<tr style="background:#f3f4f6;"><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th></tr>';
            foreach ($order_details as $detail) {
                $item = $detail['item'];
                $qty = $detail['quantity'];
                $amt_format = number_format($item['price'] * $qty, 2);
                $rate_format = number_format($item['price'], 2);
                $items_html .= "<tr><td>{$item['name']}</td><td style='text-align:center;'>{$qty}</td><td style='text-align:right;'>₹{$rate_format}</td><td style='text-align:right;'>₹{$amt_format}</td></tr>";
            }
            $items_html .= "</table>";

            $new_balance = $balance - $grand_total;
            $deducted = number_format($grand_total, 2);
            $latest = number_format($new_balance, 2);
            $wallet_html = "
                <div style='margin-top:20px; padding:15px; background:#e0f2fe; border-radius:8px; border-left: 5px solid #0284c7;'>
                    <h3 style='margin-top:0; color: #0369a1;'>Wallet Transaction Details</h3>
                    <p style='margin:5px 0;'><strong>Date & Time:</strong> {$transaction_date_time}</p>
                    <p style='margin:5px 0;'><strong>Amount Deducted:</strong> <span style='color: #dc2626;'>-₹{$deducted}</span></p>
                    <p style='margin:5px 0;'><strong>Latest Available Balance:</strong> <span style='color: #16a34a; font-weight:bold;'>₹{$latest}</span></p>
                </div>
            ";

            $htmlBody = "
                <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px; background-color: #f1f5f9;'>
                    <div style='background-color: #ffffff; padding: 30px; border-radius: 10px;'>
                        {$header_html}
                        <p style='text-align:center; margin-top:0; color:#6b7280; font-weight:bold;'>Order Receipt & Coupon</p>
                        <p>Dear <strong>" . htmlspecialchars($member_info['name']) . "</strong>,</p>
                        <p>Your order has been placed successfully. Please show the QR code or Coupon ID at the counter to collect your items.</p>
                        
                        <div style='text-align: center; margin: 25px 0; padding: 15px; background: #f9fafb; border-radius: 8px;'>
                            <img src='{$qr_api_url}' alt='QR Code' style='border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 5px;'>
                            <h2 style='margin: 15px 0 0 0; letter-spacing: 2px;'>ID: {$coupon_id}</h2>
                        </div>
                        
                        <h3 style='border-bottom: 2px solid #f3f4f6; padding-bottom: 5px;'>Order Summary</h3>
                        {$items_html}
                        
                        <h3 style='text-align:right; color: #111827;'>Total Amount: ₹" . number_format($grand_total, 2) . "</h3>
                        <p style='text-align:right;'><strong>Payment Mode:</strong> WALLET</p>
                        
                        {$wallet_html}
                        
                        <p style='margin-top: 30px; font-size: 0.85em; color: #9ca3af; text-align:center; border-top: 1px solid #e2e8f0; padding-top: 15px;'>
                            Thank you for using the Canteen System!
                        </p>
                    </div>
                </div>
            ";

            $images_json = !empty($embeddedImages) ? json_encode($embeddedImages) : null;
            $stmt_queue = $pdo->prepare("INSERT INTO email_queue (recipient_email, recipient_name, subject, html_body, sender_name, embedded_images) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_queue->execute([$member_info['email'], $member_info['name'], $subject, $htmlBody, $sender_name, $images_json]);
        }

        $pdo->commit();
        
        unset($_SESSION['cart']);
        header("Location: order_coupon.php?order_id=" . $order_id);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: cart.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// Fetch Cart Details
$cart_items = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$cart_details = [];
$grand_total = 0;

if (!empty($cart_items)) {
    foreach ($cart_items as $id => $qty) {
        $item_data = $pdo->prepare("SELECT id, name, price, image FROM menu WHERE id = ?");
        $item_data->execute([$id]);
        $item = $item_data->fetch();

        if ($item) {
            $total = $item['price'] * $qty;
            $grand_total += $total;
            $cart_details[] = [
                'id' => $id,
                'name' => $item['name'],
                'price' => $item['price'],
                'image' => $item['image'],
                'quantity' => $qty,
                'total' => $total
            ];
        } else {
            unset($_SESSION['cart'][$id]);
        }
    }
}

// Fetch all active, in-stock, and currently available items for the Knapsack Budget Planner
$current_time = date('H:i:s');
$current_day = strtolower(date('D')); // e.g., 'mon', 'tue'

$stmt_all_menu = $pdo->prepare("
    SELECT id, name, price, image, stock 
    FROM menu 
    WHERE status = 'active' 
      AND stock > 0 
      AND ? >= available_from 
      AND ? <= available_to
      AND available_days LIKE ?
");
$stmt_all_menu->execute([$current_time, $current_time, "%$current_day%"]);
$all_menu_items = $stmt_all_menu->fetchAll(PDO::FETCH_ASSOC);

// Check Expiration
$stmt_my_res = $pdo->prepare("SELECT MIN(expires_at) as exp FROM cart_reservations WHERE member_id = ?");
$stmt_my_res->execute([$member_id]);
$my_expire = $stmt_my_res->fetchColumn();

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

    /* Vibrant Full-Width Banner matching Dashboard */
    .greeting-section { 
        background: linear-gradient(135deg, #0062E6 0%, #33AEFF 100%); 
        color: white; 
        padding: 25px 20px 30px; 
        border-radius: 0 0 30px 30px; 
        box-shadow: 0 4px 15px rgba(0, 98, 230, 0.2); 
        margin-top: -1px; 
        position: relative;
        z-index: 1;
        margin-bottom: 30px;
    }

    /* Modern UI Card Styling */
    .modern-card {
        border-radius: 20px;
        border: none;
        background: #fff;
        box-shadow: 0 5px 15px rgba(0,0,0,0.04);
        overflow: hidden;
    }

    /* Cart Item Card */
    .cart-item-card { transition: all 0.3s ease; }
    .cart-item-card:hover { background-color: #fcfcfd; }
    
    .cart-item-img {
        width: 90px; 
        height: 90px; 
        object-fit: cover;
        border-radius: 16px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    
    /* Modern Pill Quantity Controls */
    .qty-controls {
        background: #f1f3f5;
        border-radius: 50px;
        padding: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: space-between;
        min-width: 110px;
    }
    .qty-btn {
        width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: none; transition: 0.2s; cursor: pointer;
    }
    .qty-btn.minus { background: white; color: #dc3545; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .qty-btn.plus { background: #0062E6; color: white; box-shadow: 0 2px 5px rgba(0, 98, 230, 0.3); }
    .qty-btn:active { transform: scale(0.9); }
    .qty-input { background: transparent; border: none; width: 35px; text-align: center; font-weight: 700; font-size: 1.1rem; pointer-events: none; }

    /* Checkout Sidebar */
    .checkout-btn {
        background: linear-gradient(135deg, #28a745, #20c997);
        border: none;
        color: white;
        transition: all 0.3s ease;
    }
    .checkout-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
        color: white;
    }

    /* Knapsack Feature Button */
    .planner-btn {
        background: linear-gradient(135deg, #6f42c1, #8e44ad);
        color: white; border: none; transition: all 0.3s ease; font-weight: bold;
    }
    .planner-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(111, 66, 193, 0.3);
        color: white;
    }

    /* Modal Styling */
    .modal-content { border-radius: 20px; border: none; }
    .modal-backdrop.show { opacity: 0.7; backdrop-filter: blur(4px); }

    /* Toast Notifications - Dark Glass */
    #toast-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1060; pointer-events: none; width: 90%; max-width: 350px;}
    .custom-toast { background: rgba(0,0,0,0.85); color: white; border-radius: 50px; padding: 12px 25px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; font-weight: 500; opacity: 0; transform: translateY(-20px); transition: all 0.3s ease; backdrop-filter: blur(5px); text-align: center;}
    .custom-toast.show { opacity: 1; transform: translateY(0); }

    /* Skeleton Loader */
    .skeleton { background-color: #e2e5e7; background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0)); background-size: 40px 100%; background-repeat: no-repeat; animation: shine 1.5s ease infinite; }
    @keyframes shine { to { background-position: right -40px top 0; } }
    .skel-img { width: 90px; height: 90px; border-radius: 16px; }
    .skel-text { height: 20px; border-radius: 4px; margin-bottom: 8px; }
    .skel-box { height: 300px; border-radius: 20px; }

    .row-fade-out { animation: fadeOutRow 0.3s forwards; }
    @keyframes fadeOutRow { to { opacity: 0; transform: scale(0.95); margin-bottom: -100px; padding: 0; height: 0; overflow: hidden; } }

    @media (max-width: 576px) {
        .cart-item-img { width: 70px; height: 70px; }
        .qty-controls { min-width: 100px; }
        .qty-btn { width: 30px; height: 30px; }
        .qty-input { width: 30px; font-size: 1rem; }
    }
    
    /* Planner specific scroll area */
    #plannerResultsArea {
        max-height: 400px;
        overflow-y: auto;
    }
</style>

<div id="toast-container"></div>

<div class="container-fluid px-0">
    <div class="greeting-section text-center text-md-left">
        <div class="container d-flex align-items-center justify-content-center justify-content-md-start">
            <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center mr-3 shadow-sm" style="width: 50px; height: 50px;">
                <i class="fas fa-shopping-bag fa-lg"></i>
            </div>
            <div>
                <h4 class="font-weight-bold mb-0">Your Cart</h4>
                <p class="mb-0 text-light" style="font-size: 0.95rem;">Review your items before checkout.</p>
            </div>
        </div>
    </div>
</div>

<div class="container mt-2 mb-5">

    <?php 
    $initial_remaining = 0;
    $show_alert = false;
    if ($my_expire && !empty($cart_details)) {
        $initial_remaining = strtotime($my_expire) - time();
        if ($initial_remaining > 0) $show_alert = true;
    }
    ?>
    <div id="reservation-alert-box" class="alert bg-white shadow-sm mb-4 align-items-center <?php echo $show_alert ? 'd-flex' : 'd-none'; ?>" style="border-left: 5px solid #ffc107; border-radius: 15px;">
        <div class="mr-3 bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px; flex-shrink: 0;">
            <i class="fas fa-stopwatch"></i>
        </div>
        <div class="flex-grow-1">
            <strong class="text-dark">Complete Your Order!</strong><br>
            <small class="text-muted">Stock released in <span id="res-timer" class="font-weight-bold text-danger ml-1"></span></small>
        </div>
    </div>

    <?php if (isset($_GET['error'])): ?>
    <div class="alert bg-white border-left-danger shadow-sm rounded-lg d-flex align-items-center mb-4" style="border-left: 5px solid #dc3545;">
        <i class="fas fa-exclamation-triangle text-danger fa-2x mr-3"></i>
        <div class="flex-grow-1 font-weight-bold text-dark"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <button type="button" class="close text-danger" data-dismiss="alert">&times;</button>
    </div>
    <?php endif; ?>

    <div class="text-right mb-3">
        <button type="button" class="btn planner-btn rounded-pill px-4 shadow-sm" data-toggle="modal" data-target="#budgetPlannerModal">
            <i class="fas fa-magic mr-2"></i> Smart Budget Planner
        </button>
    </div>

    <div id="skeleton-container" class="row">
        <div class="col-lg-8 mb-4">
            <div class="modern-card p-4">
                <?php for($i=0; $i<3; $i++): ?>
                <div class="d-flex mb-4">
                    <div class="skeleton skel-img mr-3"></div>
                    <div class="w-100 d-flex flex-column justify-content-center">
                        <div class="skeleton skel-text w-50"></div>
                        <div class="skeleton skel-text w-25"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="skeleton skel-box shadow-sm"></div>
        </div>
    </div>

    <div id="actual-content" class="d-none">
        <?php if (empty($cart_details)): ?>
            <div class="modern-card text-center py-5">
                <div class="card-body py-5">
                    <img src="../assets/images/no-food.svg" alt="Empty Cart" style="width: 150px; opacity: 0.6; max-width: 100%; margin-bottom: 20px;" onerror="this.src=''; this.className='fas fa-shopping-basket fa-4x text-muted mb-4 opacity-50';">
                    <h4 class="font-weight-bold text-dark">Your cart is empty</h4>
                    <p class="text-muted mb-4">Looks like you haven't added anything to your cart yet.</p>
                    <a href="dashboard.php" class="btn btn-primary rounded-pill px-5 py-2 font-weight-bold shadow-sm" style="background: linear-gradient(135deg, #0062E6, #33AEFF); border: none;">
                        <i class="fas fa-utensils mr-2"></i> Browse Menu
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row" id="cart-container-row">
                
                <div class="col-lg-8 mb-4">
                    <div class="modern-card">
                        <div class="bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                            <h5 id="cart-count-header" class="mb-0 font-weight-bold text-dark">Reserved Items (<?php echo count($cart_details); ?>)</h5>
                            <a href="dashboard.php" class="text-primary text-decoration-none font-weight-bold small">+ Add More</a>
                        </div>
                        <div class="p-0" id="cart-items-wrapper">
                            <?php foreach ($cart_details as $index => $item): ?>
                            <div id="item-row-<?php echo $item['id']; ?>" class="cart-item-card d-flex flex-column flex-sm-row align-items-sm-center justify-content-between p-3 p-md-4 <?php echo $index < count($cart_details) - 1 ? 'border-bottom' : ''; ?>">
                                
                                <div class="d-flex align-items-center mb-3 mb-sm-0 flex-grow-1">
                                    <img src="../assets/images/menu/<?php echo htmlspecialchars($item['image']); ?>" class="cart-item-img mr-3" alt="Item">
                                    <div class="pr-2">
                                        <h6 class="font-weight-bold text-dark mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                        <div class="text-primary font-weight-bold mb-2">₹<?php echo number_format($item['price'], 2); ?></div>
                                        <button type="button" class="btn btn-sm btn-light text-danger rounded-pill px-3 font-weight-bold" style="font-size: 0.75rem;" onclick="adjustQty(<?php echo $item['id']; ?>, -9999)">
                                            <i class="fas fa-trash-alt mr-1"></i> Remove
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center justify-content-between justify-content-sm-end w-100 w-sm-auto mt-2 mt-sm-0">
                                    <div class="qty-controls shadow-sm mr-sm-4">
                                        <button class="qty-btn minus" type="button" onclick="adjustQty(<?php echo $item['id']; ?>, -1)">-</button>
                                        <input type="text" id="qty_<?php echo $item['id']; ?>" class="qty-input text-dark" value="<?php echo $item['quantity']; ?>" readonly>
                                        <button class="qty-btn plus" type="button" onclick="adjustQty(<?php echo $item['id']; ?>, 1)">+</button>
                                    </div>
                                    <div id="item-total-<?php echo $item['id']; ?>" class="font-weight-bold text-dark h5 mb-0 text-right" style="min-width: 80px;">
                                        ₹<?php echo number_format($item['total'], 2); ?>
                                    </div>
                                </div>

                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4" id="checkout-sidebar">
                    <div class="modern-card sticky-top" style="top: 100px;">
                        <div class="bg-white border-bottom py-3 px-4">
                            <h5 class="font-weight-bold mb-0 text-dark">Order Summary</h5>
                        </div>
                        <div class="p-4">
                            <div class="d-flex justify-content-between mb-3 text-muted">
                                <span>Subtotal</span>
                                <span class="font-weight-bold text-dark grand-total-display">₹<?php echo number_format($grand_total, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-3 text-muted">
                                <span>Taxes & Fees</span>
                                <span class="font-weight-bold text-success">Free</span>
                            </div>
                            
                            <div style="border-top: 2px dashed #e2e5e7; margin: 20px 0;"></div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="h5 mb-0 font-weight-bold text-dark">To Pay</span>
                                <span class="h4 mb-0 text-dark font-weight-bold grand-total-display">₹<?php echo number_format($grand_total, 2); ?></span>
                            </div>
                            
                            <form action="cart.php" method="post" id="checkout-form" onsubmit="return confirmCheckout(event);">
                                <div class="form-group mb-4">
                                    <label for="checkout_time" class="font-weight-bold small text-muted text-uppercase mb-2"><i class="far fa-clock mr-1"></i> Pickup Time (Optional)</label>
                                    <input type="time" id="checkout_time" name="checkout_time" class="form-control rounded-pill bg-light border-0 px-4 font-weight-bold text-dark" style="height: 45px;">
                                </div>
                                <button type="submit" class="btn checkout-btn btn-block py-3 font-weight-bold text-uppercase rounded-pill shadow-sm" style="font-size: 1rem; letter-spacing: 0.5px;">
                                    Checkout <i class="fas fa-chevron-right ml-2 text-white-50"></i>
                                </button>
                                
                                <div class="text-center mt-3">
                                    <small class="text-muted"><i class="fas fa-wallet mr-1"></i> Paid securely via Wallet</small>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="budgetPlannerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #6f42c1, #8e44ad); border: none;">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-magic mr-2"></i>Smart Budget Planner</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 bg-light">
                <p class="text-muted small">Enter your budget, and our <strong>Advanced 0/1 Knapsack Algorithm</strong> will recommend combinations, prioritizing complete meals and Thalis from our available stock.</p>
                <div class="input-group mb-3 shadow-sm" style="border-radius: 50px; overflow: hidden;">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white border-0 font-weight-bold text-dark px-4">₹</span>
                    </div>
                    <input type="number" id="budgetInput" class="form-control border-0 font-weight-bold" style="height: 50px; font-size: 1.1rem;" placeholder="e.g. 40">
                    <div class="input-group-append">
                        <button class="btn planner-btn px-4" type="button" onclick="runBudgetPlanner()">Calculate</button>
                    </div>
                </div>
                
                <div id="plannerResultsArea" class="mt-4 d-none custom-scrollbar pr-2">
                    </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="removeItemModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-body text-center p-4">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                    <i class="fas fa-trash-alt fa-2x text-danger"></i>
                </div>
                <h5 class="font-weight-bold text-dark">Remove Item?</h5>
                <p class="text-muted small mb-4">Are you sure you want to drop this item from your cart?</p>
                <div class="d-flex justify-content-center">
                    <button type="button" class="btn btn-light rounded-pill px-4 mr-2 font-weight-bold text-muted" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4 font-weight-bold shadow-sm" id="confirmRemoveBtn">Remove</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="checkoutModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 65px; height: 65px;">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <h4 class="font-weight-bold text-dark">Confirm Order</h4>
                    <p class="text-muted mb-0">Please review your payment details.</p>
                </div>
                
                <div class="bg-light rounded-lg p-3 mb-4 border" style="border-color: #e9ecef !important;">
                    <div class="d-flex justify-content-between mb-2 align-items-center">
                        <span class="text-muted font-weight-bold small text-uppercase">Total Amount</span>
                        <span class="text-dark font-weight-bold h4 mb-0" id="modal-grand-total">₹0.00</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted font-weight-bold small text-uppercase">Payment Method</span>
                        <span class="badge badge-success px-3 py-2 rounded-pill"><i class="fas fa-wallet mr-1"></i> My Wallet</span>
                    </div>
                </div>

                <div class="form-group mb-4 mt-3">
                    <label class="font-weight-bold small text-muted text-uppercase mb-2"><i class="fas fa-lock mr-1"></i> Enter 4-Digit T-PIN to Confirm</label>
                    <input type="password" id="modal_t_pin" class="form-control rounded-pill bg-light border-0 px-4 font-weight-bold text-center text-dark" maxlength="4" pattern="\d{4}" placeholder="••••" style="height: 50px; font-size: 1.5rem; letter-spacing: 0.5rem;" autocomplete="new-password">
                    <small id="tpin-error" class="text-danger d-none mt-2 d-block text-center">Please enter a valid 4-digit T-PIN.</small>
                </div>
                
                <div class="alert alert-info border-0 rounded-lg small mb-4 shadow-sm" style="background-color: #e8f4fd; color: #0062E6;">
                    <i class="fas fa-info-circle mr-1"></i> The amount will be instantly deducted from your wallet balance.
                </div>

                <div class="d-flex justify-content-between gap-3">
                    <button type="button" class="btn btn-light rounded-pill font-weight-bold text-muted w-100 mr-2" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn checkout-btn rounded-pill font-weight-bold shadow-sm w-100 ml-2" id="confirmCheckoutBtn">Pay & Order</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// =========================================================
// ADVANCED DYNAMIC PROGRAMMING: 0/1 Knapsack with Priorities
// =========================================================
const fullMenuData = <?php echo json_encode($all_menu_items); ?>;

// Dynamically tag and prioritize items 
fullMenuData.forEach(item => {
    let nameLower = item.name.toLowerCase();
    
    // Categorize using heuristics based on name
    item.isThali = nameLower.includes('thali');
    // Regex matches common Bengali/Indian sweets and desserts
    item.isDessert = /(sweet|dessert|ice cream|roshogolla|gulab jamun|dohi|curd|tok dohi|yogurt|cake|pastry|misti)/.test(nameLower);
    item.isMain = item.isThali || /(rice|roti|biryani|meal|paratha|noodles|fried|chowmein)/.test(nameLower);
    item.isSide = !item.isMain && !item.isDessert;

    // Internal value metrics hidden from the UI
    let baseInternalScore = (parseInt(item.id) * 7) % 10 + 1; 

    // Apply strict DP Weighting to enforce rules:
    // Rule 1: Thali beats everything. (Base: 10000)
    // Rule 2: Main dish + Side dish > Single Main dish. (Main: 1000, Side: 100, Dessert: 50)
    if (item.isThali) {
        item.popularityValue = 10000 + baseInternalScore; 
    } else if (item.isMain) {
        item.popularityValue = 1000 + baseInternalScore;
    } else if (item.isSide) {
        item.popularityValue = 100 + baseInternalScore;
    } else {
        item.popularityValue = 50 + baseInternalScore;
    }
});

function getKnapsackResult(menuItems, maxBudget) {
    const n = menuItems.length;
    const dp = Array(maxBudget + 1).fill(0);
    const itemSelection = Array(n).fill(0).map(() => Array(maxBudget + 1).fill(false));

    // Core Knapsack DP
    for (let i = 0; i < n; i++) {
        const weight = Math.ceil(parseFloat(menuItems[i].price)); 
        const value = menuItems[i].popularityValue; 
        
        for (let w = maxBudget; w >= weight; w--) {
            if (dp[w - weight] + value > dp[w]) {
                dp[w] = dp[w - weight] + value;
                itemSelection[i][w] = true;
            }
        }
    }

    // Backtrack to find selected items
    let selectedItems = [];
    let w = maxBudget;
    let totalCost = 0;
    let totalValue = 0;

    for (let i = n - 1; i >= 0; i--) {
        if (itemSelection[i][w]) {
            selectedItems.push(menuItems[i]);
            const weight = Math.ceil(parseFloat(menuItems[i].price));
            w -= weight;
            totalCost += parseFloat(menuItems[i].price);
            totalValue += menuItems[i].popularityValue;
        }
    }
    
    return { items: selectedItems, cost: totalCost, value: totalValue };
}

function runBudgetPlanner() {
    const budgetInput = document.getElementById('budgetInput').value;
    const maxBudget = Math.floor(parseFloat(budgetInput));
    
    if (isNaN(maxBudget) || maxBudget <= 0) {
        alert("Please enter a valid budget.");
        return;
    }

    let alternatives = [];
    let currentMenu = [...fullMenuData];

    // 1st Run (Best Optimal Selection)
    let result1 = getKnapsackResult(currentMenu, maxBudget);
    
    // Validation: Enforce the "Nobody eats only a side dish/dessert" rule
    if (result1.items.length > 0 && result1.items.some(i => i.isMain)) {
        alternatives.push(result1);

        // 2nd Run (Exclude the dominating item to force an alternative)
        let dominatingItem = result1.items.reduce((prev, current) => (prev.popularityValue > current.popularityValue) ? prev : current);
        let menu2 = currentMenu.filter(i => i.id !== dominatingItem.id);
        let result2 = getKnapsackResult(menu2, maxBudget);
        
        // Ensure the alternative is valid and different
        if (result2.items.length > 0 && result2.items.some(i => i.isMain) && result2.value !== result1.value) {
            alternatives.push(result2);
            
            // 3rd Run (Exclude the next dominating item)
            let dom2 = result2.items.reduce((prev, current) => (prev.popularityValue > current.popularityValue) ? prev : current);
            let menu3 = menu2.filter(i => i.id !== dom2.id);
            let result3 = getKnapsackResult(menu3, maxBudget);
            
            if (result3.items.length > 0 && result3.items.some(i => i.isMain) && result3.value !== result2.value && result3.value !== result1.value) {
                alternatives.push(result3);
            }
        }
    }

    // Render Results into the Modal UI
    const resultsArea = document.getElementById('plannerResultsArea');
    let html = '';

    if (alternatives.length === 0) {
        html = `
            <div class="alert alert-warning border-0 rounded p-3 d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x mr-3 text-warning"></i>
                <div>
                    <strong>No suitable complete meals found.</strong><br>
                    <small>Your budget might be too low, or you are trying to buy only side dishes/desserts. We require at least one Main Dish or Thali for a complete meal.</small>
                </div>
            </div>`;
    } else {
        alternatives.forEach((alt, index) => {
            let title = index === 0 ? '<i class="fas fa-star text-warning mr-1"></i> Top Recommendation' : `Alternative Option ${index + 1}`;
            let bgClass = index === 0 ? 'bg-white' : 'bg-light';
            let comboIds = alt.items.map(i => i.id).join(',');
            
            html += `
                <div class="mb-4 ${bgClass} p-3 rounded shadow-sm border">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h6 class="font-weight-bold text-dark mb-0">${title}</h6>
                        <button class="btn btn-sm btn-success rounded-pill px-3 shadow-sm font-weight-bold text-white" onclick="addPlannerItems([${comboIds}])"><i class="fas fa-cart-plus mr-1"></i> Add Combo</button>
                    </div>
                    <ul class="list-group list-group-flush rounded mb-3 border">
            `;
            
            alt.items.forEach(item => {
                let badgeClass = item.isThali ? 'badge-success' : (item.isMain ? 'badge-primary' : (item.isDessert ? 'badge-info text-white' : 'badge-secondary'));
                let tagText = item.isThali ? 'Thali' : (item.isMain ? 'Main' : (item.isDessert ? 'Dessert' : 'Side'));
                
                html += `
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2 px-3">
                        <div>
                            <span class="font-weight-bold d-block text-dark mb-1" style="font-size:0.95rem;">${item.name} <span class="badge ${badgeClass} ml-1" style="font-size: 0.65rem;">${tagText}</span></span>
                            <small class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i>Available</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge badge-dark badge-pill px-3 py-2 mr-2" style="font-size: 0.9rem;">₹${parseFloat(item.price).toFixed(2)}</span>
                            <button class="btn btn-sm btn-outline-primary rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0; display:flex; align-items:center; justify-content:center;" onclick="addPlannerItems([${item.id}])" title="Add to Cart"><i class="fas fa-plus"></i></button>
                        </div>
                    </li>
                `;
            });
            
            html += `
                    </ul>
                    <div class="d-flex justify-content-between align-items-center text-dark p-2 rounded font-weight-bold" style="background: #e8f5e9;">
                        <span>Total Combo Cost:</span>
                        <span class="text-success h5 mb-0">₹${alt.cost.toFixed(2)}</span>
                    </div>
                </div>
            `;
        });
    }

    resultsArea.innerHTML = html;
    resultsArea.classList.remove('d-none');
}

// Function to handle Planner Add to Cart functionality
function addPlannerItems(itemIds) {
    if (!itemIds || itemIds.length === 0) return;
    
    // Show loading indicator
    const globalLoader = document.getElementById('loading-overlay');
    if (globalLoader) {
        globalLoader.classList.remove('d-none');
        globalLoader.style.display = 'flex';
    }

    let formData = new FormData();
    formData.append('ajax_planner_add', '1');
    formData.append('item_ids', JSON.stringify(itemIds));

    fetch('cart.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            $('#budgetPlannerModal').modal('hide');
            showToast('<i class="fas fa-check-circle mr-2 text-success"></i> ' + data.message);
            // Reload page to reflect new cart state and update reservations visually
            setTimeout(() => window.location.reload(), 1000);
        } else {
            if (globalLoader) {
                globalLoader.style.display = 'none';
                globalLoader.classList.add('d-none');
            }
            showToast('<i class="fas fa-exclamation-circle mr-2 text-danger"></i> ' + data.message);
        }
    })
    .catch(err => {
        if (globalLoader) {
            globalLoader.style.display = 'none';
            globalLoader.classList.add('d-none');
        }
        showToast('Connection error. Please try again.');
    });
}

// =========================================================

let resInterval = null;
let itemToRemoveId = null;
let itemToRemoveInput = null;

function startReservationTimer(seconds) {
    let timerEl = document.getElementById('res-timer');
    let alertBox = document.getElementById('reservation-alert-box');
    
    if (!timerEl || !alertBox) return;
    
    alertBox.classList.remove('d-none');
    alertBox.classList.add('d-flex');
    
    let remaining = parseInt(seconds, 10);
    if (resInterval) clearInterval(resInterval);
    
    function updateDisplay() {
        if (remaining <= 0) {
            clearInterval(resInterval);
            timerEl.innerHTML = "EXPIRED";
            window.location.reload(); 
            return;
        }
        let m = Math.floor(remaining / 60);
        let s = remaining % 60;
        timerEl.innerHTML = m + ":" + (s < 10 ? "0" : "") + s;
        remaining--;
    }
    
    updateDisplay(); 
    resInterval = setInterval(updateDisplay, 1000);
}

<?php if ($show_alert): ?>
    startReservationTimer(<?php echo $initial_remaining; ?>);
<?php endif; ?>

function showToast(message) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `custom-toast shadow-lg mx-auto`;
    toast.innerHTML = message;
    
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

// Intercept standard quantity logic to use Modals
function adjustQty(id, change) {
    let input = document.getElementById('qty_' + id);
    let currentVal = parseInt(input.value);
    
    if (isNaN(currentVal)) currentVal = 0;
    
    let newVal = currentVal + change;
    if (newVal < 0) newVal = 0; 
    
    if (newVal === 0) {
        itemToRemoveId = id;
        itemToRemoveInput = input;
        $('#removeItemModal').modal('show');
        return;
    }

    // Visual feedback
    const card = document.getElementById(`item-row-${id}`);
    if(card && change !== -9999) {
        card.style.transform = 'scale(0.98)';
        setTimeout(() => card.style.transform = '', 150);
    }

    updateCartAjax(id, newVal, input);
}

function updateCartAjax(id, newQty, inputEl) {
    let formData = new FormData();
    formData.append('ajax_update_cart', '1');
    formData.append('item_id', id);
    formData.append('new_qty', newQty);

    fetch('cart.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            if (newQty === 0) {
                let row = document.getElementById('item-row-' + id);
                if (row) {
                    row.classList.add('row-fade-out');
                    setTimeout(() => row.remove(), 300);
                }
                showToast('Item removed');
            } else {
                if (inputEl) inputEl.value = data.new_qty;
                document.getElementById('item-total-' + id).innerText = '₹' + parseFloat(data.item_total).toFixed(2);
            }
            
            if (data.remaining_seconds && data.remaining_seconds > 0) {
                startReservationTimer(data.remaining_seconds);
            }
            
            document.querySelectorAll('.grand-total-display').forEach(el => {
                el.innerText = '₹' + parseFloat(data.grand_total).toFixed(2);
            });
            
            let headerCount = document.getElementById('cart-count-header');
            if(headerCount && data.unique_count !== undefined) {
                headerCount.innerText = `Reserved Items (${data.unique_count})`;
            }

            let navBadge = document.getElementById('header-cart-count');
            if(navBadge && data.cart_count !== undefined) {
                navBadge.innerText = data.cart_count;
            }

            if (data.unique_count === 0) {
                setTimeout(() => window.location.reload(), 300); 
            }
        } else {
            showToast(data.message || 'Error occurred');
        }
    })
    .catch(err => {
        showToast('Connection error. Please try again.');
    });
}

// Checkout Form Submission Interception
function confirmCheckout(e) {
    e.preventDefault(); // Stop normal form submission
    
    // Clear the TPIN input 
    let tpinInput = document.getElementById('modal_t_pin');
    if (tpinInput) {
        tpinInput.value = '';
        tpinInput.classList.remove('is-invalid');
    }
    let tpinError = document.getElementById('tpin-error');
    if (tpinError) {
        tpinError.classList.add('d-none');
    }

    // Inject the total amount into the modal UI
    let grandTotal = document.querySelector('.grand-total-display').innerText;
    document.getElementById('modal-grand-total').innerText = grandTotal;
    
    // Open the Custom Checkout Modal
    $('#checkoutModal').modal('show');
    return false;
}

// Wrap Event Listeners safely inside DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    
    // Remove Item Confirmation Event
    let confirmRemoveBtn = document.getElementById('confirmRemoveBtn');
    if (confirmRemoveBtn) {
        confirmRemoveBtn.addEventListener('click', function() {
            $('#removeItemModal').modal('hide');
            if (itemToRemoveId !== null) {
                updateCartAjax(itemToRemoveId, 0, itemToRemoveInput);
                itemToRemoveId = null;
                itemToRemoveInput = null;
            }
        });
    }

    // T-PIN Character Input restriction Event
    let modalTpin = document.getElementById('modal_t_pin');
    if (modalTpin) {
        modalTpin.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if(this.value.length === 4) {
                document.getElementById('tpin-error').classList.add('d-none');
                this.classList.remove('is-invalid');
            }
        });
    }

    // Checkout Confirmation Action Event
    let confirmCheckoutBtn = document.getElementById('confirmCheckoutBtn');
    if (confirmCheckoutBtn) {
        confirmCheckoutBtn.addEventListener('click', function() {
            let tpinInput = document.getElementById('modal_t_pin');
            let tpinValue = tpinInput.value.trim();

            // Validate T-PIN format before submitting
            if (!/^\d{4}$/.test(tpinValue)) {
                document.getElementById('tpin-error').classList.remove('d-none');
                tpinInput.classList.add('is-invalid');
                return; // Stop execution
            }

            $('#checkoutModal').modal('hide');
            
            // Show the system loading screen to prevent double clicks
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                loadingOverlay.classList.remove('d-none');
                loadingOverlay.style.display = 'flex';
            }
            
            // Programmatically submit the checkout form
            let form = document.getElementById('checkout-form');
            
            // Append T-PIN
            let tpinHidden = document.createElement('input');
            tpinHidden.type = 'hidden';
            tpinHidden.name = 't_pin';
            tpinHidden.value = tpinValue;
            form.appendChild(tpinHidden);

            // Since we intercepted the submit button, we must append a hidden input to trigger PHP isset($_POST['place_order'])
            let hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'place_order';
            hiddenInput.value = '1';
            form.appendChild(hiddenInput);
            
            form.submit();
        });
    }
});


// FIX FOR INFINITE LOADING: Immediately kill the global loader on DOM load
window.addEventListener('DOMContentLoaded', () => {
    
    const globalLoader = document.getElementById('loading-overlay');
    if (globalLoader) {
        globalLoader.style.display = 'none';
        globalLoader.classList.add('d-none');
    }

    setTimeout(() => {
        let skel = document.getElementById('skeleton-container');
        let content = document.getElementById('actual-content');
        if(skel) skel.classList.add('d-none');
        if(content) {
            content.classList.remove('d-none');
            content.style.opacity = 0;
            let opacity = 0;
            let interval = setInterval(function() {
                if (opacity < 1) {
                    opacity += 0.1;
                    content.style.opacity = opacity;
                } else {
                    clearInterval(interval);
                }
            }, 30);
        }
    }, 300);
});

// Extra Failsafe
window.addEventListener('load', () => {
    const globalLoader = document.getElementById('loading-overlay');
    if (globalLoader) globalLoader.style.display = 'none';
});
</script>

<?php include 'footer.php'; ?>