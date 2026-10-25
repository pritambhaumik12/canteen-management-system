<?php
ob_start(); // Start output buffering at the absolute beginning
include 'header.php'; // Includes db.php and session checks

date_default_timezone_set('Asia/Kolkata'); // Ensure timezone matches DB
$member_id = $_SESSION['id']; 

// SAFEGUARD: Ensure session cart is strictly an array
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fetch the member's name
$stmt_member = $pdo->prepare("SELECT name FROM members WHERE id = ?");
$stmt_member->execute([$member_id]);
$full_name = $stmt_member->fetchColumn();
$first_name = $full_name ? explode(' ', trim($full_name))[0] : 'Member';

// 1. DYNAMIC TABLE CREATION
$pdo->exec("CREATE TABLE IF NOT EXISTS cart_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    menu_id INT NOT NULL,
    quantity INT NOT NULL,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY member_menu (member_id, menu_id)
)");

// 2. STOCK RESTORATION
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
            
            if ($exp['member_id'] == $member_id && isset($_SESSION['cart'][$exp['menu_id']])) {
                unset($_SESSION['cart'][$exp['menu_id']]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

// 3. AJAX HANDLERS FOR REAL-TIME OPERATIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_cart'])) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json');
    
    $item_id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
    $new_qty = (int)$_POST['new_qty'];
    $old_qty = isset($_SESSION['cart'][$item_id]) ? (int)$_SESSION['cart'][$item_id] : 0;

    if (!$item_id || $new_qty < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt_lock = $pdo->prepare("SELECT stock FROM menu WHERE id = ? FOR UPDATE");
        $stmt_lock->execute([$item_id]);
        $locked_stock = $stmt_lock->fetchColumn();

        $current_stock = $locked_stock;

        if ($new_qty < $old_qty) {
            $diff = $old_qty - $new_qty;
            $stmt_restore = $pdo->prepare("UPDATE menu SET stock = stock + ? WHERE id = ?");
            $stmt_restore->execute([$diff, $item_id]);
            $current_stock = $locked_stock + $diff;

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
            $diff = $new_qty - $old_qty;
            
            if ($locked_stock !== false && $locked_stock >= $diff) {
                $stmt_deduct = $pdo->prepare("UPDATE menu SET stock = stock - ? WHERE id = ?");
                $stmt_deduct->execute([$diff, $item_id]);
                $current_stock = $locked_stock - $diff;

                $stmt_reserve = $pdo->prepare("INSERT INTO cart_reservations (member_id, menu_id, quantity, expires_at) 
                    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE)) 
                    ON DUPLICATE KEY UPDATE quantity = ?, expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)");
                $stmt_reserve->execute([$member_id, $item_id, $new_qty, $new_qty]);
                
                $_SESSION['cart'][$item_id] = $new_qty;
            } else {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Requested quantity is out of stock.']);
                exit;
            }
        }
        $pdo->commit();

        $stmt_my_res = $pdo->prepare("SELECT MIN(expires_at) as exp FROM cart_reservations WHERE member_id = ?");
        $stmt_my_res->execute([$member_id]);
        $my_expire = $stmt_my_res->fetchColumn();
        $remaining_seconds = $my_expire ? (strtotime($my_expire) - time()) : 0;
        
        $total_cart_count = !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

        echo json_encode([
            'status' => 'success', 
            'new_qty' => $new_qty,
            'new_stock' => $current_stock, 
            'cart_count' => $total_cart_count,
            'remaining_seconds' => $remaining_seconds,
            'message' => ($new_qty > 0) ? 'Cart updated successfully!' : 'Item removed from cart.'
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'System error while updating cart.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_get_stock'])) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json');
    $stmt = $pdo->query("SELECT id, stock FROM menu WHERE status = 'active'");
    $stocks = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode($stocks);
    exit;
}

// 4. CHECK USER'S RESERVATION EXPIRY
$stmt_my_res = $pdo->prepare("SELECT MIN(expires_at) as exp FROM cart_reservations WHERE member_id = ?");
$stmt_my_res->execute([$member_id]);
$my_expire = $stmt_my_res->fetchColumn();

// 5. FETCH CATEGORIES FOR FILTER
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM menu_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Categories table might not exist yet if manager/admin hasn't logged in
}

// 6. FETCH ALL MENU ITEMS WITH CATEGORY NAME
$current_time = date('H:i:s');
$current_day = strtolower(date('D'));

// Try joining categories, fallback to standard query if table doesn't exist
try {
    $sql = "SELECT m.*, c.name as category_name FROM menu m 
            LEFT JOIN menu_categories c ON m.category_id = c.id
            WHERE m.status = 'active' 
            AND m.available_from <= ?
            AND m.available_to >= ?
            AND m.available_days LIKE ?";
    $stmt = $pdo->prepare($sql);
} catch (PDOException $e) {
    $sql = "SELECT m.*, NULL as category_name FROM menu m 
            WHERE m.status = 'active' 
            AND m.available_from <= ?
            AND m.available_to >= ?
            AND m.available_days LIKE ?";
    $stmt = $pdo->prepare($sql);
}

$stmt->execute([$current_time, $current_time, '%' . $current_day . '%']);
$menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$initial_cart_count = !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// CRITICAL FIX: Close the container opened in header.php so it doesn't break our layout
?>
</div> 

<style>
    /* AGGRESSIVE GAP KILLER: Force hides the empty container from header.php */
    body > .container.mt-4.mb-5:first-of-type {
        display: none !important;
        margin: 0 !important;
        padding: 0 !important;
        height: 0 !important;
        border: none !important;
    }
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

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; padding-bottom: 90px; }

    /* SEAMLESS APP HEADER: Matched exactly to the header.php gradient */
    .greeting-section { 
        background: linear-gradient(135deg, #0062E6 0%, #33AEFF 100%); 
        color: white; 
        padding: 25px 20px 50px; 
        border-radius: 0 0 30px 30px; 
        box-shadow: 0 4px 15px rgba(0, 98, 230, 0.2); 
        margin-top: -1px; /* Pulls it up 1px to overlap any micro-seam */
        position: relative;
        z-index: 1;
    }
    
    .search-wrapper { background: white; border-radius: 20px; padding: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); transform: translateY(-25px); margin: 0 auto 10px; max-width: 750px; position: relative; z-index: 2;}
    .search-wrapper input { border: none; box-shadow: none !important; padding-left: 10px; padding-right: 10px; font-weight: 500; height: 42px; background: #f8f9fa; border-radius: 12px; }
    
    /* Sort Dropdown */
    .sort-select { border: none; background: #f1f3f5; border-radius: 12px; padding: 0 15px; height: 42px; font-size: 0.85rem; font-weight: 600; color: #495057; cursor: pointer; outline: none; transition: 0.2s; min-width: 140px;}
    .sort-select:hover, .sort-select:focus { background: #e2e6ea; }

    /* Interactive App Cards */
    .menu-card { border-radius: 20px; border: none; transition: all 0.3s ease; overflow: hidden; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.04); }
    .menu-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
    .card-img-wrapper { position: relative; padding: 10px 10px 0 10px; }
    .menu-card img { height: 160px; object-fit: cover; border-radius: 15px; }
    .out-of-stock img { filter: grayscale(100%); opacity: 0.6; }
    .sold-out-overlay { background: rgba(255,255,255,0.7); backdrop-filter: blur(2px); border-radius: 15px; }
    
    /* Quantity Pills */
    .qty-controls { background: #f1f3f5; border-radius: 50px; padding: 4px; display: flex; align-items: center; justify-content: space-between; }
    .qty-btn { width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: none; transition: 0.2s; cursor: pointer; }
    .qty-btn.minus { background: white; color: #dc3545; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .qty-btn.plus { background: #0062E6; color: white; box-shadow: 0 2px 5px rgba(0, 98, 230, 0.3); }
    .qty-btn:active { transform: scale(0.9); }
    .qty-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .qty-input { background: transparent; border: none; width: 40px; text-align: center; font-weight: 700; font-size: 1.1rem; pointer-events: none; }

    .btn-add-app { border-radius: 50px; font-weight: 600; padding: 8px 20px; border: 2px solid #0062E6; color: #0062E6; background: transparent; transition: 0.3s; width: 100%; }
    .btn-add-app:hover { background: #0062E6; color: white; }

    /* Floating Cart Bar */
    .floating-cart-bar { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(150%); width: 90%; max-width: 500px; background: #28a745; color: white; border-radius: 16px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 30px rgba(40, 167, 69, 0.4); z-index: 1040; transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); cursor: pointer; }
    .floating-cart-bar.visible { transform: translateX(-50%) translateY(0); }

    /* Skeleton Loader */
    .skeleton { background-color: #e2e5e7; background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0)); background-size: 40px 100%; background-repeat: no-repeat; animation: shine 1.5s ease infinite; }
    @keyframes shine { to { background-position: right -40px top 0; } }
    .skeleton-card { border-radius: 20px; background: #fff; padding: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.04); }
    .skeleton-img { width: 100%; height: 160px; border-radius: 15px; }
    .skeleton-title { height: 20px; width: 70%; margin: 15px 5px 10px; border-radius: 4px; }
    .skeleton-desc { height: 12px; width: 90%; margin: 0 5px 15px; border-radius: 4px; }
    .skeleton-btn { height: 40px; width: 100%; border-radius: 50px; }

    /* Toast Notifications */
    #toast-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1060; pointer-events: none; width: 90%; max-width: 350px;}
    .custom-toast { background: rgba(0,0,0,0.85); color: white; border-radius: 50px; padding: 12px 25px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; font-weight: 500; opacity: 0; transform: translateY(-20px); transition: all 0.3s ease; backdrop-filter: blur(5px); text-align: center;}
    .custom-toast.show { opacity: 1; transform: translateY(0); }
    
    #actual-content { opacity: 0; transition: opacity 0.5s ease; }
    #actual-content.show-content { opacity: 1; }
    
    @media (max-width: 768px) {
        .search-wrapper-inner { flex-direction: column; gap: 8px; }
        .controls-row { width: 100%; display: flex; gap: 8px; }
        .sort-select { flex: 1; min-width: 0; padding: 0 10px; }
    }
    
    @media (max-width: 576px) {
        .menu-card img { height: 120px; }
        .greeting-section { padding: 20px 15px 40px; }
        .search-wrapper { margin: 0 10px 10px; width: calc(100% - 20px) !important; padding: 10px; }
        .qty-btn { width: 30px; height: 30px; }
        .qty-input { width: 30px; font-size: 1rem; }
    }
</style>

<div id="toast-container"></div>

<div class="container-fluid px-0">
    <div class="greeting-section text-center text-md-left">
        <div class="container">
            <h4 class="font-weight-bold mb-1">Hungry, <?php echo htmlspecialchars($first_name); ?>? 😋</h4>
            <p class="mb-0 text-light" style="font-size: 0.95rem;">Order your favorite meals in seconds.</p>
        </div>
    </div>
    
    <div class="container px-3 px-md-0">
        <form class="search-wrapper w-100" onsubmit="event.preventDefault();">
            <div class="d-flex align-items-center justify-content-between search-wrapper-inner">
                
                <div class="d-flex flex-grow-1 w-100 position-relative">
                    <i class="fas fa-search text-muted position-absolute" style="left: 15px; top: 13px;"></i>
                    <input class="form-control" type="search" placeholder="Search menu items..." id="searchInput" oninput="liveSearch()" autocomplete="off" style="padding-left: 40px;">
                </div>
                
                <div class="controls-row ml-md-2 mt-2 mt-md-0">
                    <select id="categoryFilter" class="sort-select" onchange="liveSearch()">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars(strtolower($cat['name'])); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="sortInput" class="sort-select" onchange="liveSearch()">
                        <option value="name_asc">Sort A-Z</option>
                        <option value="price_asc">Price Low - High</option>
                        <option value="price_desc">Price High - Low</option>
                    </select>
                </div>
                
            </div>
        </form>
    </div>
</div>

<div class="container mt-2 mb-5">
    
    <?php 
    $initial_remaining = 0;
    $show_alert = false;
    if ($my_expire) {
        $initial_remaining = strtotime($my_expire) - time();
        if ($initial_remaining > 0) $show_alert = true;
    }
    ?>
    <div id="reservation-alert-box" class="alert bg-white shadow-sm border-left-warning mb-4 align-items-center <?php echo $show_alert ? 'd-flex' : 'd-none'; ?>" style="border-left: 5px solid #ffc107; border-radius: 12px;">
        <div class="mr-3 bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px; flex-shrink: 0;">
            <i class="fas fa-stopwatch"></i>
        </div>
        <div class="flex-grow-1">
            <strong class="text-dark">Cart Reserved!</strong><br>
            <small class="text-muted">Items secured for <span id="res-timer" class="font-weight-bold text-danger"></span></small>
        </div>
    </div>

    <div id="skeleton-container" class="row">
        <?php for($i=0; $i<8; $i++): ?>
        <div class="col-6 col-md-4 col-lg-3 mb-4">
            <div class="skeleton-card h-100">
                <div class="skeleton skeleton-img"></div>
                <div class="skeleton skeleton-title"></div>
                <div class="skeleton skeleton-desc"></div>
                <div class="skeleton skeleton-btn mt-3"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="actual-content" class="row d-none">
        
        <div id="empty-search-state" class="col-12 text-center py-5 d-none w-100">
            <img src="../assets/images/no-food.svg" alt="No Food" style="width: 150px; opacity: 0.5; max-width: 100%;" onerror="this.style.display='none'">
            <h5 class="font-weight-bold mt-4 text-dark">No matching items found</h5>
            <p class="text-muted">Try tweaking your search or category filter to find what you're craving.</p>
        </div>

        <?php foreach ($menu_items as $item): 
            $is_out_of_stock = ($item['stock'] <= 0);
            $stock_class = $item['stock'] > 10 ? 'success' : ($item['stock'] > 0 ? 'warning' : 'danger');
            $in_cart_qty = isset($_SESSION['cart'][$item['id']]) ? (int)$_SESSION['cart'][$item['id']] : 0;
            
            $cat_name = !empty($item['category_name']) ? $item['category_name'] : '';
            // Create a search string of Name + Description + Category to filter against
            $searchable_text = htmlspecialchars(strtolower($item['name'] . ' ' . $item['description'] . ' ' . $cat_name));
        ?>
        <div class="col-6 col-md-4 col-lg-3 mb-4 menu-item-container" 
             data-search-text="<?php echo $searchable_text; ?>" 
             data-category="<?php echo htmlspecialchars(strtolower($cat_name)); ?>"
             data-name="<?php echo htmlspecialchars($item['name']); ?>" 
             data-price="<?php echo $item['price']; ?>">
            
            <div class="menu-card h-100 d-flex flex-column <?php echo $is_out_of_stock ? 'out-of-stock' : ''; ?>" id="menu-card-<?php echo $item['id']; ?>">
                
                <div class="card-img-wrapper">
                    <img src="../assets/images/menu/<?php echo htmlspecialchars($item['image']); ?>" class="w-100" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <div id="sold-out-badge-container-<?php echo $item['id']; ?>" class="position-absolute w-100 h-100 top-0 left-0 d-flex justify-content-center align-items-center" style="pointer-events: none;">
                        <?php if ($is_out_of_stock): ?>
                            <div class="w-100 h-100 d-flex justify-content-center align-items-center sold-out-overlay">
                                <span class="badge badge-dark px-3 py-2 text-uppercase shadow-sm">Sold Out</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="p-2 p-md-3 d-flex flex-column flex-grow-1">
                    <h6 class="font-weight-bold text-dark mb-1 text-truncate" title="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php echo htmlspecialchars($item['name']); ?>
                    </h6>
                    
                    <?php if(!empty($cat_name)): ?>
                        <span class="badge badge-primary bg-primary text-white d-inline-block px-2 py-1 mb-2 align-self-start" style="font-size: 0.65rem; border-radius: 6px;">
                            <?php echo htmlspecialchars($cat_name); ?>
                        </span>
                    <?php endif; ?>

                    <p class="text-muted mb-2 flex-grow-1 d-none d-sm-block" style="font-size: 0.8rem; line-height: 1.3; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical;">
                        <?php echo htmlspecialchars($item['description']); ?>
                    </p>
                    
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 mt-auto">
                        <span class="font-weight-bold text-dark" style="font-size: 1.1rem;">₹<?php echo number_format($item['price'], 2); ?></span>
                        <span id="stock-count-<?php echo $item['id']; ?>" class="badge badge-<?php echo $stock_class; ?> bg-light text-<?php echo $stock_class; ?> border border-<?php echo $stock_class; ?> px-2 py-1 mt-1 mt-sm-0" style="font-size: 0.7rem;">
                            <?php echo $is_out_of_stock ? '0 Left' : $item['stock'] . ' Left'; ?>
                        </span>
                    </div>

                    <div id="cart-controls-<?php echo $item['id']; ?>" class="w-100">
                        <?php if ($in_cart_qty > 0): ?>
                            <div class="qty-controls shadow-sm w-100">
                                <button class="qty-btn minus" type="button" onclick="adjustDashboardQty(<?php echo $item['id']; ?>, -1)">-</button>
                                <input type="text" id="qty-input-<?php echo $item['id']; ?>" class="qty-input text-dark" value="<?php echo $in_cart_qty; ?>" readonly>
                                <button class="qty-btn plus plus-btn" type="button" onclick="adjustDashboardQty(<?php echo $item['id']; ?>, 1)" <?php echo $is_out_of_stock ? 'disabled' : ''; ?>>+</button>
                            </div>
                        <?php else: ?>
                            <button type="button" id="add-btn-<?php echo $item['id']; ?>" class="btn btn-add-app shadow-sm" onclick="adjustDashboardQty(<?php echo $item['id']; ?>, 1)" <?php echo $is_out_of_stock ? 'disabled' : ''; ?>>
                                ADD <i class="fas fa-plus ml-1" style="font-size: 0.8rem;"></i>
                            </button>
                            <input type="hidden" id="qty-input-<?php echo $item['id']; ?>" value="0">
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<div id="floating-cart" class="floating-cart-bar shadow-lg <?php echo ($initial_cart_count > 0) ? 'visible' : ''; ?>" onclick="window.location.href='cart.php'">
    <div>
        <h6 class="mb-0 font-weight-bold"><span id="float-cart-count"><?php echo $initial_cart_count; ?></span> ITEM(S) ADDED</h6>
        <small class="text-white-50 d-none d-sm-block">View cart to complete order</small>
    </div>
    <div class="d-flex align-items-center font-weight-bold">
        View Cart <i class="fas fa-chevron-right ml-2"></i>
    </div>
</div>

<script>
// ==========================================
// STRING MATCHING ALGORITHM (KMP)
// ==========================================
function computeLPSArray(pat, M, lps) {
    let len = 0; let i = 1; lps[0] = 0;
    while (i < M) {
        if (pat.charAt(i) === pat.charAt(len)) {
            len++; lps[i] = len; i++;
        } else {
            if (len !== 0) len = lps[len - 1];
            else { lps[i] = 0; i++; }
        }
    }
}

function KMPSearch(pat, txt) {
    let M = pat.length; let N = txt.length; let lps = new Array(M);
    computeLPSArray(pat, M, lps);
    let i = 0; let j = 0;
    while ((N - i) >= (M - j)) {
        if (pat.charAt(j) === txt.charAt(i)) { j++; i++; }
        if (j === M) return true; 
        else if (i < N && pat.charAt(j) !== txt.charAt(i)) {
            if (j !== 0) j = lps[j - 1];
            else i++;
        }
    }
    return false;
}

// =========================================================
// DIVIDE AND CONQUER ALGORITHM: Merge Sort
// =========================================================
function mergeSort(arr, compareFn) {
    if (arr.length <= 1) return arr;
    
    let mid = Math.floor(arr.length / 2);
    let left = mergeSort(arr.slice(0, mid), compareFn);
    let right = mergeSort(arr.slice(mid), compareFn);
    
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

// ==========================================
// Search, Filter and Sort Controller
// ==========================================
function liveSearch() {
    let input = document.getElementById('searchInput').value.toLowerCase().trim();
    let sortVal = document.getElementById('sortInput').value;
    let catFilter = document.getElementById('categoryFilter').value;
    
    let items = document.querySelectorAll('.menu-item-container');
    let emptyState = document.getElementById('empty-search-state');
    
    let keywords = input.split(/\s+/).filter(kw => kw.length > 0);
    let visibleCount = 0;

    // Convert NodeList to an Array for filtering & sorting
    let itemsArray = Array.from(items);

    // 1. Filter elements visually by toggling d-none
    itemsArray.forEach(item => {
        let match = true;
        let itemCat = item.getAttribute('data-category');
        
        // Category Filter logic
        if (catFilter !== 'all' && itemCat !== catFilter) {
            match = false;
        }

        // Text Search logic
        if (match && input !== '') {
            let text = item.getAttribute('data-search-text');
            for (let kw of keywords) {
                if (!KMPSearch(kw, text)) { match = false; break; }
            }
        }

        if (match) {
            item.classList.remove('d-none');
            visibleCount++;
        } else {
            item.classList.add('d-none');
        }
    });

    // 2. Sort the Array using Merge Sort algorithm based on chosen dropdown metric
    itemsArray = mergeSort(itemsArray, (a, b) => {
        if (sortVal === 'price_asc') {
            return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price'));
        } else if (sortVal === 'price_desc') {
            return parseFloat(b.getAttribute('data-price')) - parseFloat(a.getAttribute('data-price'));
        } else {
            // Default: name_asc
            return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
        }
    });

    // 3. Re-append sorted elements back to DOM
    let container = document.getElementById('actual-content');
    itemsArray.forEach(item => container.appendChild(item));
    
    // Toggle Empty State view
    if (visibleCount === 0 && items.length > 0) emptyState.classList.remove('d-none');
    else emptyState.classList.add('d-none');
}

// ==========================================
// Standard UI & Cart Logic
// ==========================================
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        var skel = document.getElementById('skeleton-container');
        var content = document.getElementById('actual-content');
        if (skel) skel.classList.add('d-none');
        if (content) {
            content.classList.remove('d-none');
            setTimeout(() => content.classList.add('show-content'), 50); 
            liveSearch(); // Applies initial sort/filter instantly on load
        }
    }, 400); 
});

let resInterval = null;

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
            setTimeout(() => window.location.reload(), 1000); 
            return;
        }
        let m = Math.floor(remaining / 60);
        let s = remaining % 60;
        timerEl.innerHTML = m + ":" + (s < 10 ? "0" : "") + s + " min";
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
    if(!container) return;
    const toast = document.createElement('div');
    toast.className = `custom-toast shadow-lg mx-auto`;
    toast.innerHTML = message;
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}

function renderCartControls(id, qty, isOutOfStock) {
    const container = document.getElementById(`cart-controls-${id}`);
    if (!container) return;
    
    if (qty > 0) {
        container.innerHTML = `
            <div class="qty-controls shadow-sm w-100">
                <button class="qty-btn minus" type="button" onclick="adjustDashboardQty(${id}, -1)">-</button>
                <input type="text" id="qty-input-${id}" class="qty-input text-dark" value="${qty}" readonly>
                <button class="qty-btn plus plus-btn" type="button" onclick="adjustDashboardQty(${id}, 1)" ${isOutOfStock ? 'disabled' : ''}>+</button>
            </div>
        `;
    } else {
        container.innerHTML = `
            <button type="button" id="add-btn-${id}" class="btn btn-add-app shadow-sm" onclick="adjustDashboardQty(${id}, 1)" ${isOutOfStock ? 'disabled' : ''}>
                ADD <i class="fas fa-plus ml-1" style="font-size: 0.8rem;"></i>
            </button>
            <input type="hidden" id="qty-input-${id}" value="0">
        `;
    }
}

function updateStockUI(itemId, stock) {
    stock = parseInt(stock, 10);
    const card = document.getElementById(`menu-card-${itemId}`);
    const stockCount = document.getElementById(`stock-count-${itemId}`);
    const badgeContainer = document.getElementById(`sold-out-badge-container-${itemId}`);

    if(!card) return; 

    const isOutOfStock = stock <= 0;
    let stockClass = stock > 10 ? 'success' : (stock > 0 ? 'warning' : 'danger');
    
    if(stockCount) {
        stockCount.className = `badge badge-${stockClass} bg-light text-${stockClass} border border-${stockClass} px-2 py-1 mt-1 mt-sm-0`;
        stockCount.innerHTML = isOutOfStock ? '0 Left' : `${stock} Left`;
    }

    if (isOutOfStock) {
        card.classList.add('out-of-stock');
        if(badgeContainer && !badgeContainer.innerHTML.trim()) {
            badgeContainer.innerHTML = `<div class="w-100 h-100 d-flex justify-content-center align-items-center sold-out-overlay">
                <span class="badge badge-dark px-3 py-2 text-uppercase shadow-sm">Sold Out</span>
            </div>`;
        }
    } else {
        card.classList.remove('out-of-stock');
        if(badgeContainer) badgeContainer.innerHTML = '';
    }

    let addBtn = document.getElementById(`add-btn-${itemId}`);
    if(addBtn) addBtn.disabled = isOutOfStock;
    
    let container = document.getElementById(`cart-controls-${itemId}`);
    if(container) {
        let plusBtn = container.querySelector('.plus-btn');
        if(plusBtn) plusBtn.disabled = isOutOfStock;
    }
}

function adjustDashboardQty(id, change) {
    let input = document.getElementById('qty-input-' + id);
    let currentVal = input ? parseInt(input.value) : 0;
    if (isNaN(currentVal)) currentVal = 0;
    
    let newQty = currentVal + change;
    if (newQty < 0) newQty = 0; 

    const card = document.getElementById(`menu-card-${id}`);
    if(card) {
        card.style.transform = 'scale(0.97)';
        setTimeout(() => card.style.transform = '', 150);
    }
    
    setTimeout(() => {
        const loadingOverlay = document.getElementById('loading-overlay');
        if (loadingOverlay) loadingOverlay.classList.add('d-none');
    }, 10);

    let formData = new FormData();
    formData.append('ajax_update_cart', '1');
    formData.append('item_id', id);
    formData.append('new_qty', newQty);

    fetch('dashboard.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            showToast(data.message);
            
            renderCartControls(id, data.new_qty, data.new_stock <= 0);
            updateStockUI(id, data.new_stock);
            
            if (data.cart_count > 0 && data.remaining_seconds > 0) {
                startReservationTimer(data.remaining_seconds);
            } else {
                let alertBox = document.getElementById('reservation-alert-box');
                if (alertBox) {
                    alertBox.classList.remove('d-flex');
                    alertBox.classList.add('d-none');
                }
                if (resInterval) clearInterval(resInterval);
            }
            
            if (data.cart_count !== undefined) {
                let badge = document.getElementById('header-cart-count');
                if (badge) badge.innerText = data.cart_count;
                
                let floatCart = document.getElementById('floating-cart');
                let floatCartCount = document.getElementById('float-cart-count');
                
                if (data.cart_count > 0) {
                    if(floatCart) floatCart.classList.add('visible');
                    if(floatCartCount) floatCartCount.innerText = data.cart_count;
                } else {
                    if(floatCart) floatCart.classList.remove('visible');
                }
            }
        } else {
            showToast(data.message || 'Error occurred');
        }
    })
    .catch(err => {
        showToast('Connection error. Please check your internet.');
    });
}

function fetchRealTimeStock() {
    fetch('dashboard.php?ajax_get_stock=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(res => res.json())
    .then(data => {
        for (const [id, stock] of Object.entries(data)) {
            updateStockUI(id, stock);
        }
    })
    .catch(err => console.error("Stock update failed", err));
}
setInterval(fetchRealTimeStock, 5000);
</script>

<?php include 'footer.php'; ?>