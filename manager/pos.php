<?php
session_start();
require_once '../includes/db.php'; 

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Default logged-in manager ID
$logged_in_manager_id = $_SESSION['id'] ?? 1; 

// -----------------------------------------------------------------------------
// AJAX: Fetch Menu Items
// -----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'get_menu') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->query("SELECT id, name, price, image, stock FROM menu WHERE status = 'active' AND stock > 0 ORDER BY name ASC");
        $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $menu_items]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// -----------------------------------------------------------------------------
// AJAX: Verify Member
// -----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'verify_member') {
    header('Content-Type: application/json');
    $member_id = $_GET['member_id'] ?? '';
    
    if (empty($member_id)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a Member ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT m.id as db_id, m.name, IFNULL(w.balance, 0) as balance 
            FROM members m 
            LEFT JOIN wallet w ON m.id = w.member_id 
            WHERE m.member_id = ? AND m.status = 'active'
        ");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($member) {
            echo json_encode([
                'success' => true, 
                'name' => $member['name'], 
                'balance' => $member['balance'],
                'db_id' => $member['db_id']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or inactive Member ID.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// -----------------------------------------------------------------------------
// POST: Process Order (PRG Pattern)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_checkout'])) {
    $member_code = trim($_POST['member_code']);
    $payment_mode = $_POST['payment_mode'] ?? 'wallet'; 
    $t_pin = trim($_POST['t_pin']);
    $cart_json = $_POST['cart_items'];
    $cart = json_decode($cart_json, true);

    if (empty($member_code) || empty($cart) || empty($t_pin)) {
        $_SESSION['error'] = "Invalid order data. Please ensure all fields and T-PIN are filled.";
        header("Location: pos.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Verify Manager T-PIN
        $stmt = $pdo->prepare("SELECT t_pin FROM managers WHERE id = ?");
        $stmt->execute([$logged_in_manager_id]);
        $manager = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$manager || $manager['t_pin'] !== $t_pin) {
            throw new Exception("Authorization Failed: Invalid T-PIN.");
        }

        // 2. Verify member and lock wallet row for update (FETCH EMAIL ALSO)
        $stmt = $pdo->prepare("SELECT m.id, m.name, m.email, IFNULL(w.balance, 0) as balance FROM members m LEFT JOIN wallet w ON m.id = w.member_id WHERE m.member_id = ? FOR UPDATE");
        $stmt->execute([$member_code]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) throw new Exception("Invalid member.");

        $member_db_id = $member['id'];
        $current_balance = (float)$member['balance'];
        $total_amount = 0;

        // 3. Calculate Total & Verify Stock
        foreach ($cart as $item) {
            $stmt = $pdo->prepare("SELECT id, price, stock FROM menu WHERE id = ? AND status = 'active' FOR UPDATE");
            $stmt->execute([$item['id']]);
            $menu_item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$menu_item || $menu_item['stock'] < $item['quantity']) {
                throw new Exception("Item '{$item['name']}' is out of stock.");
            }
            $total_amount += ($menu_item['price'] * $item['quantity']);
        }

        // 4. Handle Wallet Deduction
        $new_balance = $current_balance;
        if ($payment_mode === 'wallet') {
            if ($current_balance < $total_amount) {
                throw new Exception("Insufficient wallet balance. Current: ₹" . number_format($current_balance, 2));
            }
            $new_balance = $current_balance - $total_amount;
            $stmt = $pdo->prepare("UPDATE wallet SET balance = ? WHERE member_id = ?");
            $stmt->execute([$new_balance, $member_db_id]);
        }

        // 5. Generate Unique Serial Coupon ID for Today (YYYYMMDD-000001)
        $today_date_str = date('Ymd');
        $today_start = date('Y-m-d 00:00:00');
        $stmt_coupon = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_at >= ?");
        $stmt_coupon->execute([$today_start]);
        $daily_order_count = $stmt_coupon->fetchColumn();
        $next_id = $daily_order_count + 1;
        $coupon_id = $today_date_str . '-' . str_pad($next_id, 6, '0', STR_PAD_LEFT);

        // 6. Insert Order
        $stmt = $pdo->prepare("INSERT INTO orders (coupon_id, member_id, total_amount, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        $stmt->execute([$coupon_id, $member_db_id, $total_amount]);
        $order_id = $pdo->lastInsertId();

        // 7. Insert Items & Deduct Stock
        $stmt_insert_item = $pdo->prepare("INSERT INTO order_items (order_id, menu_id, quantity, rate, amount, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt_update_stock = $pdo->prepare("UPDATE menu SET stock = stock - ? WHERE id = ?");

        foreach ($cart as $item) {
            $amount = $item['price'] * $item['quantity'];
            $stmt_insert_item->execute([$order_id, $item['id'], $item['quantity'], $item['price'], $amount]);
            $stmt_update_stock->execute([$item['quantity'], $item['id']]);
        }

        // 8. Record Transaction (Appended Manager ID for logs)
        $desc = "Order placed: #$coupon_id via POS (Paid via " . strtoupper($payment_mode) . ") | By Manager ID: {$logged_in_manager_id}";
        $stmt = $pdo->prepare("INSERT INTO transactions (member_id, type, amount, description, created_at) VALUES (?, 'debit', ?, ?, NOW())");
        $stmt->execute([$member_db_id, $total_amount, $desc]);

        // 9. Fetch System Settings for Receipt & Email
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $canteen_name_setting = $settings_data['canteen_name'] ?? 'Canteen System';
        $inst_name = $settings_data['institution_name'] ?? 'Institution';

        $pdo->commit();

        $transaction_date_time = date('d M Y, h:i A');

        // 10. Queue Email to Member (If email exists)
        if (!empty($member['email'])) {
            $subject = "Order Confirmation - Coupon #$coupon_id";
            $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($coupon_id);
            $sender_name = "{$canteen_name_setting} - {$inst_name}";
            
            // Handle Image Embedding via CID
            $inst_logo = $settings_data['institution_logo'] ?? '';
            $cant_logo = $settings_data['canteen_logo'] ?? '';
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

            // Build Items Table HTML
            $items_html = '<table style="width:100%; border-collapse: collapse; margin-top: 15px;" border="1" cellpadding="8">';
            $items_html .= '<tr style="background:#f3f4f6;"><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th></tr>';
            foreach ($cart as $item) {
                $amt_format = number_format($item['price'] * $item['quantity'], 2);
                $rate_format = number_format($item['price'], 2);
                $items_html .= "<tr><td>{$item['name']}</td><td style='text-align:center;'>{$item['quantity']}</td><td style='text-align:right;'>₹{$rate_format}</td><td style='text-align:right;'>₹{$amt_format}</td></tr>";
            }
            $items_html .= "</table>";

            // Build Wallet Details HTML (if applicable)
            $wallet_html = "";
            if ($payment_mode === 'wallet') {
                $deducted = number_format($total_amount, 2);
                $latest = number_format($new_balance, 2);
                $wallet_html = "
                    <div style='margin-top:20px; padding:15px; background:#e0f2fe; border-radius:8px; border-left: 5px solid #0284c7;'>
                        <h3 style='margin-top:0; color: #0369a1;'>Wallet Transaction Details</h3>
                        <p style='margin:5px 0;'><strong>Date & Time:</strong> {$transaction_date_time}</p>
                        <p style='margin:5px 0;'><strong>Amount Deducted:</strong> <span style='color: #dc2626;'>-₹{$deducted}</span></p>
                        <p style='margin:5px 0;'><strong>Latest Available Balance:</strong> <span style='color: #16a34a; font-weight:bold;'>₹{$latest}</span></p>
                    </div>
                ";
            }

            // Combine HTML Email Body
            $htmlBody = "
                <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px; background-color: #f1f5f9;'>
                    <div style='background-color: #ffffff; padding: 30px; border-radius: 10px;'>
                        {$header_html}
                        
                        <p style='text-align:center; margin-top:0; color:#6b7280; font-weight:bold;'>Order Receipt & Coupon</p>
                        
                        <p>Dear <strong>" . htmlspecialchars($member['name']) . "</strong>,</p>
                        <p>Your order has been placed successfully. Please show the QR code or Coupon ID at the counter to collect your items.</p>
                        
                        <div style='text-align: center; margin: 25px 0; padding: 15px; background: #f9fafb; border-radius: 8px;'>
                            <img src='{$qr_api_url}' alt='QR Code' style='border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 5px;'>
                            <h2 style='margin: 15px 0 0 0; letter-spacing: 2px;'>ID: {$coupon_id}</h2>
                        </div>
                        
                        <h3 style='border-bottom: 2px solid #f3f4f6; padding-bottom: 5px;'>Order Summary</h3>
                        {$items_html}
                        
                        <h3 style='text-align:right; color: #111827;'>Total Amount: ₹" . number_format($total_amount, 2) . "</h3>
                        <p style='text-align:right;'><strong>Payment Mode:</strong> " . strtoupper($payment_mode) . "</p>
                        
                        {$wallet_html}
                        
                        <p style='margin-top: 30px; font-size: 0.85em; color: #9ca3af; text-align:center; border-top: 1px solid #e2e8f0; padding-top: 15px;'>
                            Thank you for using the Canteen System!
                        </p>
                    </div>
                </div>
            ";

            // INSERT INTO QUEUE DATABASE INSTEAD OF SENDING IMMEDIATELY
            $images_json = !empty($embeddedImages) ? json_encode($embeddedImages) : null;
            $stmt_queue = $pdo->prepare("INSERT INTO email_queue (recipient_email, recipient_name, subject, html_body, sender_name, embedded_images) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_queue->execute([$member['email'], $member['name'], $subject, $htmlBody, $sender_name, $images_json]);
        }

        // 11. Prepare Receipt Session Data for Frontend Modal
        $_SESSION['receipt'] = [
            'institution_name' => $inst_name,
            'canteen_name' => $canteen_name_setting,
            'address1' => $settings_data['address1'] ?? '',
            'address2' => $settings_data['address2'] ?? '',
            'coupon_id' => $coupon_id,
            'date' => $transaction_date_time,
            'member_id' => $member_code,
            'member_name' => $member['name'],
            'payment_mode' => strtoupper($payment_mode),
            'items' => $cart,
            'total' => $total_amount
        ];
        $_SESSION['success'] = "Order processed successfully!";
        
        header("Location: pos.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header("Location: pos.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive POS - Canteen System</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <style>
        :root {
            --primary: #4F46E5; --primary-hover: #4338CA;
            --danger: #EF4444; --success: #10B981;
            --bg-color: #F3F4F6; --card-bg: #FFFFFF;
            --text-main: #111827; --text-muted: #6B7280;
            --border: #E5E7EB;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Navbar */
        .pos-header { background: var(--card-bg); padding: 15px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; z-index: 10; flex-wrap: wrap; gap: 15px; }
        .pos-header h1 { font-size: 1.5rem; color: var(--primary); display: flex; align-items: center; gap: 10px; margin: 0; }
        .pos-header a { text-decoration: none; color: var(--text-muted); font-weight: 500; }
        
        .pos-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap;}
        
        /* Advanced Interactive Search & Sort UI */
        .search-wrapper { position: relative; width: 300px; display: flex; align-items: center; }
        @media (max-width: 600px) { .search-wrapper, .sort-bar { width: 100%; order: 3; } .pos-controls { width: 100%;} }
        .search-bar, .sort-bar { padding: 10px 35px 10px 40px; border: 2px solid transparent; border-radius: 30px; background: #f8fafc; outline: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-size: 0.95rem; font-weight: 500; color: var(--text-main); box-shadow: inset 0 1px 3px rgba(0,0,0,0.05); }
        .search-bar { width: 100%; }
        .sort-bar { padding-left: 15px; width: 180px; cursor: pointer;}
        .search-bar:focus, .sort-bar:focus { background: #ffffff; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15); transform: translateY(-1px); }
        .search-icon { position: absolute; left: 14px; color: #9ca3af; pointer-events: none; transition: color 0.3s ease; }
        .search-bar:focus ~ .search-icon { color: var(--primary); }
        .clear-search { position: absolute; right: 14px; color: #9ca3af; cursor: pointer; display: none; transition: all 0.2s ease; background: #e5e7eb; border-radius: 50%; padding: 2px; }
        .clear-search:hover { color: var(--danger); background: #fee2e2; transform: scale(1.1); }

        /* Layout */
        .pos-container { display: flex; flex: 1; overflow: hidden; }
        @media (max-width: 850px) { .pos-container { flex-direction: column; overflow-y: auto; } }

        /* Menu Area */
        .menu-area { flex: 2; padding: 20px; overflow-y: auto; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; }
        
        .menu-card { background: var(--card-bg); border-radius: 10px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: transform 0.2s; display: flex; flex-direction: column; position: relative; }
        .menu-card:hover { transform: translateY(-3px); box-shadow: 0 5px 10px rgba(0,0,0,0.1); }
        .menu-card img { width: 100%; height: 150px; object-fit: cover; }
        .menu-card-body { padding: 15px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .menu-title { font-weight: 600; font-size: 1.1rem; margin-bottom: 5px; color: var(--text-main); }
        .menu-price { color: var(--success); font-weight: bold; font-size: 1.25rem; }
        .menu-stock { font-size: 0.85rem; color: var(--text-muted); margin-top: 5px; }

        /* Interactive Controls */
        .qty-controls { display: flex; align-items: center; justify-content: space-between; background: var(--bg-color); border-radius: 6px; padding: 5px; margin-top: 10px; }
        .qty-btn { background: white; border: 1px solid var(--border); border-radius: 4px; width: 30px; height: 30px; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center; color: var(--primary); transition: background 0.2s; }
        .qty-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .qty-display { font-weight: bold; width: 40px; text-align: center; }
        .add-btn { width: 100%; padding: 8px; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; margin-top: 10px; transition: 0.2s; }
        .add-btn:hover { background: var(--primary-hover); }

        /* Cart Area */
        .cart-area { flex: 1; min-width: 350px; max-width: 450px; background: var(--card-bg); border-left: 1px solid var(--border); display: flex; flex-direction: column; z-index: 5; }
        @media (max-width: 850px) { .cart-area { max-width: 100%; min-height: 60vh; border-left: none; } }
        
        .cart-header { padding: 15px 20px; border-bottom: 1px solid var(--border); background: #f8fafc; }
        .cart-items { flex: 1; overflow-y: auto; padding: 10px 20px; }
        .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed var(--border); }
        .cart-item-title { font-weight: 600; font-size: 1rem; }
        .cart-item-price { font-size: 0.9rem; color: var(--text-muted); }
        
        /* Checkout Panel */
        .checkout-panel { padding: 20px; border-top: 1px solid var(--border); background: #f8fafc; }
        .member-lookup { display: flex; gap: 10px; margin-bottom: 15px; }
        .member-lookup input { flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 6px; }
        .member-lookup button { padding: 10px 15px; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        
        .member-details { background: #e0f2fe; padding: 12px; border-radius: 6px; margin-bottom: 15px; display: none; }
        
        .payment-options { display: flex; gap: 15px; margin-bottom: 15px; background: white; padding: 10px; border-radius: 6px; border: 1px solid var(--border); }
        .payment-options label { display: flex; align-items: center; gap: 5px; cursor: pointer; font-weight: 500; }
        
        .tpin-group { margin-bottom: 15px; }
        .tpin-group input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 1rem; text-align: center; letter-spacing: 2px; }
        .tpin-group input:focus { border-color: var(--primary); outline: none; }

        .totals { display: flex; justify-content: space-between; font-size: 1.4rem; font-weight: bold; margin-bottom: 15px; color: var(--text-main); }
        
        .btn-checkout { width: 100%; padding: 15px; background: var(--success); color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-checkout:hover:not(:disabled) { background: #059669; transform: scale(1.02); }
        .btn-checkout:disabled { background: #9CA3AF; cursor: not-allowed; }

        /* Skeleton Loaders */
        .skeleton { background: linear-gradient(110deg, #ececec 8%, #f5f5f5 18%, #ececec 33%); border-radius: 5px; background-size: 200% 100%; animation: 1.5s shine linear infinite; }
        .skeleton-img { width: 100%; height: 150px; border-radius: 10px 10px 0 0; }
        .skeleton-text { height: 20px; margin-bottom: 10px; width: 80%; }
        @keyframes shine { to { background-position-x: -200%; } }

        /* Alerts */
        .alert { padding: 12px 20px; margin: 10px 20px; border-radius: 6px; font-weight: 500; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }

        /* Receipt Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; justify-content: center; align-items: center; z-index: 100; backdrop-filter: blur(3px); }
        .receipt-card { background: white; width: 350px; border-radius: 10px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .receipt-header { text-align: center; border-bottom: 2px dashed #ccc; padding-bottom: 15px; margin-bottom: 15px; }
        
        /* Receipt Institution Styles */
        .receipt-inst-name { font-size: 1.4rem; font-weight: bold; color: #000; margin-bottom: 3px; }
        .receipt-cant-name { font-size: 1.1rem; font-weight: 600; color: #333; margin-bottom: 5px; }
        .receipt-address { font-size: 0.85rem; color: #555; margin-bottom: 15px; }
        
        .qr-container { display: flex; justify-content: center; margin: 15px 0; }
        .receipt-meta { font-size: 0.85rem; color: #555; margin-top: 10px; line-height: 1.4; text-align: left; }
        .receipt-table { width: 100%; font-size: 0.9rem; margin-bottom: 15px; border-collapse: collapse; }
        .receipt-table th, .receipt-table td { padding: 5px 0; text-align: left; border-bottom: 1px solid #eee; }
        .receipt-table th:last-child, .receipt-table td:last-child { text-align: right; }
        .receipt-total { display: flex; justify-content: space-between; font-weight: bold; font-size: 1.2rem; border-top: 2px dashed #ccc; padding-top: 10px; }
        .receipt-actions { display: flex; gap: 10px; margin-top: 20px; }
        .receipt-actions button { flex: 1; padding: 10px; border-radius: 5px; border: none; font-weight: bold; cursor: pointer; }
        .btn-print { background: var(--primary); color: white; }
        .btn-close { background: #e5e7eb; color: var(--text-main); }
    </style>
</head>
<body>

    <header class="pos-header">
        <h1><span>🍔</span> POS Terminal</h1>
        
        <div class="pos-controls">
            <div class="search-wrapper">
                <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="searchInput" class="search-bar" placeholder="Search menu (e.g., Spicy Chicken)..." oninput="filterMenu()">
                <svg id="clearSearchBtn" class="clear-search" onclick="clearSearch()" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </div>
            
            <select id="sortInput" class="sort-bar" onchange="filterMenu()">
                <option value="name_asc">Sort: A-Z</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
            </select>
        </div>

        <a href="dashboard.php">&larr; Dashboard</a>
    </header>

    <?php if (isset($_SESSION['success']) && !isset($_SESSION['receipt'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="pos-container">
        <main class="menu-area">
            <div class="menu-grid" id="menu-container">
                <?php for($i=0; $i<8; $i++): ?>
                <div class="menu-card">
                    <div class="skeleton skeleton-img"></div>
                    <div class="menu-card-body">
                        <div class="skeleton skeleton-text"></div>
                        <div class="skeleton skeleton-text" style="width: 40%;"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </main>

        <aside class="cart-area">
            <div class="cart-header">
                <h2>Current Order</h2>
            </div>
            
            <div class="cart-items" id="cart-container">
                <div style="text-align:center; color:#999; margin-top:40px;">Cart is empty</div>
            </div>

            <div class="checkout-panel">
                <div class="member-lookup">
                    <input type="text" id="memberIdInput" placeholder="Enter Member ID">
                    <button type="button" onclick="verifyMember()">Verify</button>
                </div>
                
                <div class="member-details" id="memberDetailsBox">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <strong id="memberNameDisplay"></strong>
                        <span style="font-size:0.85rem; background:var(--primary); color:white; padding:2px 6px; border-radius:10px;">ID: <span id="memberIdText"></span></span>
                    </div>
                    <div>Wallet Balance: <strong style="color:var(--success);">₹<span id="memberBalanceDisplay"></span></strong></div>
                </div>

                <div class="payment-options">
                    <label><input type="radio" name="pay_mode_ui" value="wallet" checked onchange="validateCheckout()"> Wallet</label>
                    <label><input type="radio" name="pay_mode_ui" value="cash" onchange="validateCheckout()"> Cash</label>
                    <label><input type="radio" name="pay_mode_ui" value="upi" onchange="validateCheckout()"> UPI</label>
                </div>

                <div class="totals">
                    <span>Total:</span>
                    <span>₹<span id="cartTotalDisplay">0.00</span></span>
                </div>

                <form method="POST" action="pos.php" id="checkoutForm">
                    <input type="hidden" name="process_checkout" value="1">
                    <input type="hidden" name="member_code" id="hiddenMemberCode" required>
                    <input type="hidden" name="payment_mode" id="hiddenPaymentMode" value="wallet">
                    <input type="hidden" name="cart_items" id="hiddenCartItems" required>
                    
                    <div class="tpin-group">
                        <input type="password" name="t_pin" id="tPinInput" placeholder="Enter Manager T-PIN" required oninput="validateCheckout()">
                    </div>

                    <button type="submit" class="btn-checkout" id="checkoutBtn" disabled>Process Order</button>
                </form>
            </div>
        </aside>
    </div>

    <?php if (isset($_SESSION['receipt'])): $rcpt = $_SESSION['receipt']; ?>
    <div class="modal-overlay" id="receiptModal" style="display: flex;">
        <div class="receipt-card" id="printableReceipt">
            <div class="receipt-header">
                <div class="receipt-inst-name"><?= htmlspecialchars($rcpt['institution_name']) ?></div>
                <div class="receipt-cant-name"><?= htmlspecialchars($rcpt['canteen_name']) ?></div>
                <div class="receipt-address">
                    <?= htmlspecialchars($rcpt['address1']) ?>
                    <?= !empty($rcpt['address2']) ? '<br>' . htmlspecialchars($rcpt['address2']) : '' ?>
                </div>
                
                <div class="qr-container" id="qrcode"></div>
                
                <div class="receipt-meta">
                    <div><strong>Coupon ID:</strong> <?= $rcpt['coupon_id'] ?></div>
                    <div><strong>Date:</strong> <?= $rcpt['date'] ?></div>
                    <div><strong>Member:</strong> <?= htmlspecialchars($rcpt['member_name']) ?> (<?= htmlspecialchars($rcpt['member_id']) ?>)</div>
                    <div><strong>Payment:</strong> <?= $rcpt['payment_mode'] ?></div>
                </div>
            </div>
            
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Amt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rcpt['items'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td>₹<?= number_format($item['price'], 2) ?></td>
                        <td>₹<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="receipt-total">
                <span>Total Amount:</span>
                <span>₹<?= number_format($rcpt['total'], 2) ?></span>
            </div>

            <div class="receipt-actions" data-html2canvas-ignore="true">
                <button class="btn-close" onclick="closeReceipt()">Close</button>
                <button class="btn-print" onclick="printReceipt()">Print Receipt</button>
            </div>
        </div>
    </div>
    
    <script>
        // Generate QR Code once the DOM loads
        document.addEventListener("DOMContentLoaded", function() {
            new QRCode(document.getElementById("qrcode"), {
                text: "<?= $rcpt['coupon_id'] ?>",
                width: 120,
                height: 120,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        });
    </script>
    <?php unset($_SESSION['receipt']); unset($_SESSION['success']); endif; ?>

    <script>
        let menuItems = [];
        let cart = {}; 
        let currentMember = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadMenu();
            setTimeout(() => {
                fetch('../includes/process_email_queue.php').catch(e => console.error("Queue process check failed:", e));
            }, 1000);
        });

        // =========================================================
        // DIVIDE AND CONQUER ALGORITHM: Merge Sort
        // =========================================================
        function mergeSort(arr, compareFn) {
            if (arr.length <= 1) return arr;
            
            const mid = Math.floor(arr.length / 2);
            const left = mergeSort(arr.slice(0, mid), compareFn);
            const right = mergeSort(arr.slice(mid), compareFn);
            
            return merge(left, right, compareFn);
        }

        function merge(left, right, compareFn) {
            let result = [];
            let i = 0, j = 0;
            
            while (i < left.length && j < right.length) {
                if (compareFn(left[i], right[j]) <= 0) {
                    result.push(left[i++]);
                } else {
                    result.push(right[j++]);
                }
            }
            return result.concat(left.slice(i)).concat(right.slice(j));
        }

        // =========================================================
        // STRING MATCHING ALGORITHM: Knuth-Morris-Pratt (KMP)
        // =========================================================
        function computeLPSArray(pattern) {
            const m = pattern.length;
            const lps = new Array(m).fill(0);
            let len = 0; 
            let i = 1;
            
            while (i < m) {
                if (pattern[i] === pattern[len]) {
                    len++;
                    lps[i] = len;
                    i++;
                } else {
                    if (len !== 0) {
                        len = lps[len - 1];
                    } else {
                        lps[i] = 0;
                        i++;
                    }
                }
            }
            return lps;
        }

        function kmpSearch(text, pattern) {
            if (!pattern) return true; 
            const n = text.length;
            const m = pattern.length;
            const lps = computeLPSArray(pattern);
            let i = 0; 
            let j = 0; 

            while (n - i >= m - j) {
                if (pattern[j] === text[i]) {
                    j++;
                    i++;
                }
                if (j === m) {
                    return true; 
                } else if (i < n && pattern[j] !== text[i]) {
                    if (j !== 0) {
                        j = lps[j - 1]; 
                    } else {
                        i++;
                    }
                }
            }
            return false;
        }
        
        // =========================================================

        function loadMenu() {
            fetch('pos.php?action=get_menu')
                .then(res => res.json())
                .then(res => {
                    const container = document.getElementById('menu-container');
                    container.innerHTML = '';
                    if (res.success && res.data.length > 0) {
                        menuItems = res.data;
                        filterMenu(); // Initial render via filter to apply default sorting
                    } else {
                        container.innerHTML = '<p style="grid-column: 1 / -1; color: #666; text-align: center;">No active items available right now.</p>';
                    }
                })
                .catch(err => console.error("Error loading menu:", err));
        }

        function filterMenu() {
            const inputEl = document.getElementById('searchInput');
            const clearBtn = document.getElementById('clearSearchBtn');
            const sortVal = document.getElementById('sortInput').value;
            const query = inputEl.value.toLowerCase().trim();
            
            // Toggle Clear Button UI
            clearBtn.style.display = query.length > 0 ? 'block' : 'none';

            let filtered = menuItems;

            if (query) {
                const keywords = query.split(/\s+/);
                filtered = menuItems.filter(item => {
                    const itemName = item.name.toLowerCase();
                    return keywords.every(kw => kmpSearch(itemName, kw));
                });
            }

            // Apply Merge Sort based on dropdown selection
            filtered = mergeSort(filtered, (a, b) => {
                if (sortVal === 'price_asc') {
                    return parseFloat(a.price) - parseFloat(b.price);
                } else if (sortVal === 'price_desc') {
                    return parseFloat(b.price) - parseFloat(a.price);
                } else {
                    return a.name.localeCompare(b.name); // Default A-Z
                }
            });

            // "No Results" visual feedback handling
            const container = document.getElementById('menu-container');
            if (filtered.length === 0) {
                container.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 50px 20px;">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:15px; opacity: 0.6;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                        <h3 style="color: var(--text-main); font-weight: 600; font-size:1.3rem;">No items found</h3>
                        <p style="color: var(--text-muted); margin-top: 5px;">We couldn't find any exact KMP algorithm match for "<strong>${inputEl.value}</strong>".</p>
                    </div>`;
            } else {
                renderMenu(filtered);
            }
        }

        function clearSearch() {
            document.getElementById('searchInput').value = '';
            filterMenu();
            document.getElementById('searchInput').focus();
        }

        function renderMenu(items) {
            const container = document.getElementById('menu-container');
            container.innerHTML = '';
            items.forEach(item => {
                const qty = cart[item.id] ? cart[item.id].quantity : 0;
                let imgSrc = item.image && item.image !== 'default.jpg' ? `../assets/images/menu/${item.image}` : `../assets/images/menu/default.jpg`;
                
                let controlsHtml = '';
                if (qty > 0) {
                    controlsHtml = `
                        <div class="qty-controls">
                            <button class="qty-btn" type="button" onclick="updateItem(${item.id}, -1)">-</button>
                            <span class="qty-display">${qty}</span>
                            <button class="qty-btn" type="button" onclick="updateItem(${item.id}, 1)">+</button>
                        </div>
                    `;
                } else {
                    controlsHtml = `<button class="add-btn" type="button" onclick="updateItem(${item.id}, 1)">Add to Order</button>`;
                }

                container.innerHTML += `
                    <div class="menu-card">
                        <img src="${imgSrc}" onerror="this.src='../assets/images/menu/default.jpg'">
                        <div class="menu-card-body">
                            <div>
                                <div class="menu-title">${item.name}</div>
                                <div style="display:flex; justify-content:space-between;">
                                    <div class="menu-price">₹${parseFloat(item.price).toFixed(2)}</div>
                                    <div class="menu-stock">Stock: ${item.stock}</div>
                                </div>
                            </div>
                            ${controlsHtml}
                        </div>
                    </div>
                `;
            });
        }

        function updateItem(id, delta) {
            const item = menuItems.find(m => m.id == id);
            if (!item) return;

            if (!cart[id]) {
                if (delta > 0) cart[id] = { ...item, quantity: 1, max: item.stock };
            } else {
                let newQty = cart[id].quantity + delta;
                if (newQty <= 0) {
                    delete cart[id];
                } else if (newQty > cart[id].max) {
                    alert("Max stock reached!");
                } else {
                    cart[id].quantity = newQty;
                }
            }
            filterMenu(); 
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('cart-container');
            const totalDisplay = document.getElementById('cartTotalDisplay');
            const cartArr = Object.values(cart);
            
            if (cartArr.length === 0) {
                container.innerHTML = '<div style="text-align:center; color:#999; margin-top:40px;">Cart is empty</div>';
                totalDisplay.innerText = '0.00';
            } else {
                container.innerHTML = '';
                let total = 0;
                cartArr.forEach(item => {
                    total += (parseFloat(item.price) * item.quantity);
                    container.innerHTML += `
                        <div class="cart-item">
                            <div style="flex:1;">
                                <div class="cart-item-title">${item.name}</div>
                                <div class="cart-item-price">₹${parseFloat(item.price).toFixed(2)} x ${item.quantity}</div>
                            </div>
                            <div class="qty-controls" style="margin:0;">
                                <button class="qty-btn" type="button" onclick="updateItem(${item.id}, -1)">-</button>
                                <span class="qty-display">${item.quantity}</span>
                                <button class="qty-btn" type="button" onclick="updateItem(${item.id}, 1)">+</button>
                            </div>
                        </div>
                    `;
                });
                totalDisplay.innerText = total.toFixed(2);
            }
            
            document.getElementById('hiddenCartItems').value = JSON.stringify(cartArr);
            validateCheckout();
        }

        function verifyMember() {
            const mid = document.getElementById('memberIdInput').value.trim();
            if(!mid) return alert("Enter Member ID");

            fetch(`pos.php?action=verify_member&member_id=${encodeURIComponent(mid)}`)
                .then(r => r.json())
                .then(res => {
                    const box = document.getElementById('memberDetailsBox');
                    
                    if (res.success) {
                        const balanceFloat = parseFloat(res.balance);
                        currentMember = { id: mid, balance: balanceFloat };
                        
                        document.getElementById('memberNameDisplay').innerText = res.name;
                        document.getElementById('memberIdText').innerText = mid;
                        document.getElementById('memberBalanceDisplay').innerText = balanceFloat.toFixed(2);
                        document.getElementById('hiddenMemberCode').value = mid;
                        
                        box.style.display = 'block';
                        box.style.backgroundColor = '#e0f2fe';
                        box.style.color = '#111827';
                    } else {
                        currentMember = null;
                        document.getElementById('hiddenMemberCode').value = '';
                        box.style.display = 'block';
                        box.style.backgroundColor = '#fee2e2';
                        box.style.color = '#991b1b';
                        box.innerHTML = `<strong>Error:</strong> ${res.message}`;
                    }
                    validateCheckout();
                })
                .catch(err => {
                    console.error("Verification failed:", err);
                    alert("An error occurred trying to verify the member.");
                });
        }

        document.getElementById('memberIdInput').addEventListener('keypress', e => { 
            if(e.key === 'Enter') { 
                e.preventDefault(); 
                verifyMember(); 
            } 
        });

        function validateCheckout() {
            const btn = document.getElementById('checkoutBtn');
            const payMode = document.querySelector('input[name="pay_mode_ui"]:checked').value;
            const tpin = document.getElementById('tPinInput').value.trim();
            document.getElementById('hiddenPaymentMode').value = payMode;

            const cartTotal = Object.values(cart).reduce((sum, i) => sum + (parseFloat(i.price) * i.quantity), 0);
            
            if (Object.keys(cart).length === 0 || !currentMember || tpin === "") {
                btn.disabled = true;
                btn.innerText = "Process Order";
                return;
            }

            if (payMode === 'wallet') {
                if (currentMember.balance >= cartTotal) {
                    btn.disabled = false;
                    btn.innerText = `Pay ₹${cartTotal.toFixed(2)} via Wallet`;
                } else {
                    btn.disabled = true;
                    btn.innerText = "Insufficient Wallet Balance";
                }
            } else {
                btn.disabled = false;
                btn.innerText = `Process ₹${cartTotal.toFixed(2)} (${payMode.toUpperCase()})`;
            }
        }

        function closeReceipt() {
            document.getElementById('receiptModal').style.display = 'none';
        }

        function printReceipt() {
            const actions = document.querySelector('.receipt-actions');
            actions.style.display = 'none'; 
            
            const content = document.getElementById('printableReceipt').innerHTML;
            const printWin = window.open('', '', 'width=400,height=600');
            
            printWin.document.write(`
                <html>
                    <head>
                        <title>Print Receipt</title>
                        <style>
                            body { font-family: monospace; padding: 20px; color: #000; }
                            .receipt-header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 15px; margin-bottom: 15px; }
                            .receipt-inst-name { font-size: 1.4rem; font-weight: bold; margin-bottom: 3px; }
                            .receipt-cant-name { font-size: 1.1rem; font-weight: bold; margin-bottom: 5px; }
                            .receipt-address { font-size: 0.85rem; margin-bottom: 15px; }
                            .qr-container { display: flex; justify-content: center; margin: 15px 0; }
                            .receipt-meta { font-size: 0.9rem; margin-top: 10px; line-height: 1.4; text-align: left; }
                            .receipt-table { width: 100%; font-size: 0.9rem; margin-bottom: 15px; border-collapse: collapse; }
                            .receipt-table th, .receipt-table td { padding: 5px 0; text-align: left; border-bottom: 1px solid #ccc; }
                            .receipt-table th:last-child, .receipt-table td:last-child { text-align: right; }
                            .receipt-total { display: flex; justify-content: space-between; font-weight: bold; font-size: 1.2rem; border-top: 2px dashed #000; padding-top: 10px; }
                        </style>
                    </head>
                    <body>
                        ${content}
                    </body>
                </html>
            `);
            printWin.document.close();
            printWin.focus();
            
            setTimeout(() => {
                printWin.print();
                printWin.close();
                actions.style.display = 'flex'; 
            }, 500); 
        }
    </script>
</body>
</html>