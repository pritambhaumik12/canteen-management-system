<?php
ob_start();
include 'header.php';
require_once '../includes/db.php';

// Auto-setup categories table and alter menu table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE
    )");
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM menu_categories");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO menu_categories (name) VALUES ('Main Dish'), ('Side Dish'), ('Thali')");
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM menu LIKE 'category_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE menu ADD COLUMN category_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE menu ADD CONSTRAINT fk_menu_category FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE SET NULL");
    }
} catch (PDOException $e) {
    // Ignore duplicate key errors if constraints already exist
}

// File upload path
$upload_dir = "../assets/images/menu/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Auto-restore stocks to default_stock if a new day has started
try {
    $reset_sql = "UPDATE menu SET stock = default_stock, last_reset_date = CURDATE() WHERE last_reset_date IS NULL OR last_reset_date < CURDATE()";
    $pdo->exec($reset_sql);
} catch (PDOException $e) {
    // Handling cases prior to schema migration
}

// PRG pattern: Process POST request and redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new category
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        if (!empty($category_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO menu_categories (name) VALUES (?)");
                $stmt->execute([$category_name]);
                $_SESSION['toast_msg'] = "Category '$category_name' added successfully! 📂";
            } catch (PDOException $e) {
                $_SESSION['toast_msg'] = "Category already exists! ⚠️";
            }
        }
    }

    // Add menu item
    if (isset($_POST['add_menu'])) {
        $name = trim($_POST['name']);
        $category_id = $_POST['category_id'];
        $description = trim($_POST['description']);
        $price = $_POST['price'];
        $stock = $_POST['stock'];
        $default_stock = $_POST['default_stock'];
        $available_from = $_POST['available_from'];
        $available_to = $_POST['available_to'];
        $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
        $image = 'default.jpg';

        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $image_name = basename($_FILES["image"]["name"]);
            $target_file = $upload_dir . $image_name;
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image = $image_name;
            }
        }
        
        $sql = "INSERT INTO menu (name, category_id, description, price, stock, default_stock, available_from, available_to, available_days, image, last_reset_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $category_id, $description, $price, $stock, $default_stock, $available_from, $available_to, $available_days, $image]);
        
        $_SESSION['toast_msg'] = "Menu item added successfully! 🍔";
    }

    // Update menu item
    if (isset($_POST['update_menu'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $category_id = $_POST['category_id'];
        $description = trim($_POST['description']);
        $price = $_POST['price'];
        $stock = $_POST['stock'];
        $default_stock = $_POST['default_stock'];
        $available_from = $_POST['available_from'];
        $available_to = $_POST['available_to'];
        $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
        $status = $_POST['status'];
        
        $sql = "UPDATE menu SET name=?, category_id=?, description=?, price=?, stock=?, default_stock=?, available_from=?, available_to=?, available_days=?, status=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $category_id, $description, $price, $stock, $default_stock, $available_from, $available_to, $available_days, $status, $id]);

        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $image_name = basename($_FILES["image"]["name"]);
            $target_file = $upload_dir . $image_name;
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $sql_update_image = "UPDATE menu SET image=? WHERE id=?";
                $pdo->prepare($sql_update_image)->execute([$image_name, $id]);
            }
        }
        $_SESSION['toast_msg'] = "Menu item updated successfully! ✏️";
    }

    // Delete menu item
    if (isset($_POST['delete_menu'])) {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT image FROM menu WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetchColumn();
        if ($image && $image !== 'default.jpg' && file_exists($upload_dir . $image)) {
            unlink($upload_dir . $image);
        }

        $sql = "DELETE FROM menu WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        $_SESSION['toast_msg'] = "Menu item deleted! 🗑️";
    }
    
    ob_end_clean();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// Retrieve Flash Toast Messages
$toast_msg = $_SESSION['toast_msg'] ?? '';
unset($_SESSION['toast_msg']);

// Fetch Categories
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing items with category names
$menu_items = $pdo->query("SELECT m.*, c.name as category_name FROM menu m LEFT JOIN menu_categories c ON m.category_id = c.id ORDER BY m.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

// Calculate Statistics
$total_items = count($menu_items);
$active_items = count(array_filter($menu_items, function($item) { return $item['status'] === 'active'; }));
$inactive_items = $total_items - $active_items;

?>
</div>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    nav.navbar + .container, 
    nav.navbar + .container-fluid,
    .navbar-expand-lg + .container,
    .navbar-expand-lg + .container-fluid { 
        display: none !important; margin: 0 !important; padding: 0 !important; height: 0 !important; 
    }
    
    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }

    .greeting-section { 
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); 
        color: white; padding: 30px 20px 60px; border-radius: 0 0 30px 30px; 
        box-shadow: 0 4px 15px rgba(0,123,255,0.2); margin-top: -1.5rem; position: relative; z-index: 10;
    }
    
    .search-wrapper { 
        background: white; border-radius: 50px; padding: 5px 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); 
        transform: translateY(-25px); margin: 0 auto 10px; max-width: 600px; display: flex; align-items: center; position: relative; z-index: 20; 
    }
    .search-wrapper input { border: none; box-shadow: none !important; padding-left: 15px; padding-right: 15px; font-weight: 500; height: 45px; }
    
    .stat-card { transition: all 0.3s ease; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important; }

    .food-card { border-radius: 20px; border: none; transition: all 0.3s ease; overflow: hidden; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.04); display: flex; flex-direction: column; }
    .food-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
    
    .food-img-container { height: 160px; width: 100%; overflow: hidden; position: relative; background: #f8f9fa; }
    .food-img-container img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
    .food-card:hover .food-img-container img { transform: scale(1.05); }
    
    .price-badge { position: absolute; top: 15px; right: 15px; background: rgba(0,0,0,0.7); color: white; padding: 5px 12px; border-radius: 50px; font-weight: 700; backdrop-filter: blur(5px); box-shadow: 0 4px 10px rgba(0,0,0,0.2); z-index: 2; }
    .status-badge { position: absolute; top: 15px; left: 15px; padding: 5px 12px; border-radius: 50px; font-weight: 600; font-size: 0.75rem; box-shadow: 0 4px 10px rgba(0,0,0,0.2); z-index: 2; }
    
    .form-card { border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; }
    .form-card .card-header { background: transparent; border-bottom: 1px solid #f1f3f5; padding: 20px 25px; }
    
    .skeleton { background-color: #e2e5e7; background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0)); background-size: 40px 100%; background-repeat: no-repeat; animation: shine 1.5s ease infinite; }
    @keyframes shine { to { background-position: right -40px top 0; } }
    .skeleton-card { border-radius: 20px; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.04); display: flex; flex-direction: column; overflow: hidden; }
    .skeleton-img { height: 160px; width: 100%; }
    .skeleton-text-title { height: 20px; width: 70%; margin: 15px; border-radius: 4px; }
    .skeleton-text-sub { height: 12px; width: 50%; margin: 0 15px 15px; border-radius: 4px; }
    .skeleton-btn { height: 35px; width: 40%; border-radius: 50px; display: inline-block; margin: 0 2%; }

    #actual-content { opacity: 0; transition: opacity 0.5s ease; }
    #actual-content.show-content { opacity: 1; }
    
    .modal-content { border-radius: 20px; border: none; overflow: hidden; }
    .modal-header { background: #f8f9fa; border-bottom: none; padding: 20px 25px; }
    .modal-footer { border-top: none; padding: 15px 25px 20px; }

    @media (max-width: 576px) {
        .greeting-section { padding: 20px 15px 50px; }
        .search-wrapper { margin: 0 10px 10px; width: calc(100% - 20px) !important; }
        .action-btns .btn { margin-bottom: 10px; width: 100%; }
    }
</style>

<?php if (!empty($toast_msg)): ?>
<div id="toast-notification" class="shadow-lg rounded-pill px-4 py-3 d-flex align-items-center" style="position: fixed; top: 20px; right: 20px; z-index: 1055; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; font-weight: 600; transform: translateX(150%); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
    <i class="fas fa-check-circle fa-lg mr-2"></i>
    <span><?php echo $toast_msg; ?></span>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var toast = document.getElementById('toast-notification');
        if (toast) {
            setTimeout(() => toast.style.transform = 'translateX(0)', 100);
            setTimeout(() => {
                toast.style.transform = 'translateX(150%)';
                setTimeout(() => toast.remove(), 400);
            }, 4500);
        }
    });
</script>
<?php endif; ?>

<div class="container-fluid px-0">
    <div class="greeting-section text-center text-md-left">
        <div class="container">
            <h4 class="font-weight-bold mb-1">Menu Management</h4>
            <p class="mb-0 text-light" style="font-size: 0.95rem;">Manage your canteen's offerings and daily stock.</p>
        </div>
    </div>
    
    <div class="container px-3 px-md-0">
        <form class="search-wrapper w-100" onsubmit="event.preventDefault();">
            <i class="fas fa-search text-muted ml-3"></i>
            <input class="form-control" type="search" placeholder="Search menu items by name, category, desc..." id="searchInput" oninput="liveSearch()" autocomplete="off">
        </form>
    </div>
</div>

<div class="container mt-2 mb-5">
    
    <div class="row mb-4">
        <div class="col-4">
            <div class="bg-white rounded-lg p-3 shadow-sm text-center stat-card border" style="border-radius: 15px;">
                <div class="text-muted small font-weight-bold text-uppercase mb-1">Total Items</div>
                <div class="h4 mb-0 font-weight-bold text-dark"><?php echo number_format($total_items); ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bg-white rounded-lg p-3 shadow-sm text-center stat-card border" style="border-radius: 15px;">
                <div class="text-muted small font-weight-bold text-uppercase mb-1">Active</div>
                <div class="h4 mb-0 font-weight-bold text-success"><?php echo number_format($active_items); ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bg-white rounded-lg p-3 shadow-sm text-center stat-card border" style="border-radius: 15px;">
                <div class="text-muted small font-weight-bold text-uppercase mb-1">Inactive</div>
                <div class="h4 mb-0 font-weight-bold text-danger"><?php echo number_format($inactive_items); ?></div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 action-btns">
        <div class="mb-2 mb-md-0 w-100 w-md-auto">
            <button class="btn btn-primary rounded-pill shadow-sm px-4 py-2 font-weight-bold mr-2" type="button" data-toggle="collapse" data-target="#addMenuCollapse" aria-expanded="false" aria-controls="addMenuCollapse">
                <i class="fas fa-plus-circle mr-2"></i> Add Menu Item
            </button>
            <button class="btn btn-outline-primary rounded-pill shadow-sm px-4 py-2 font-weight-bold bg-white" type="button" data-toggle="modal" data-target="#addCategoryModal">
                <i class="fas fa-tags mr-2"></i> Add Category
            </button>
        </div>
    </div>

    <div class="collapse mb-4" id="addMenuCollapse">
        <div class="card form-card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Add New Item</h6>
            </div>
            <div class="card-body p-4">
                <form action="menu_management.php" method="post" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="font-weight-500 text-dark">Name</label>
                            <input type="text" class="form-control rounded-pill px-3" name="name" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-500 text-dark">Category</label>
                            <select class="form-control rounded-pill px-3" name="category_id" required>
                                <option value="" disabled selected>Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-500 text-dark">Price (₹)</label>
                            <input type="number" step="0.01" class="form-control rounded-pill px-3" name="price" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-500 text-dark">Current Stock</label>
                            <input type="number" class="form-control rounded-pill px-3" name="stock" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="font-weight-500 text-dark">Default Daily Stock</label>
                            <input type="number" class="form-control rounded-pill px-3" name="default_stock" required>
                        </div>
                        <div class="form-group col-md-9">
                            <label class="font-weight-500 text-dark">Description</label>
                            <textarea class="form-control rounded" name="description" rows="1" style="border-radius: 15px;"></textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-500 text-dark">Available From</label>
                            <input type="time" class="form-control rounded-pill px-3" name="available_from" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-500 text-dark">Available To</label>
                            <input type="time" class="form-control rounded-pill px-3" name="available_to" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-500 text-dark d-block mb-2">Available Days</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($days as $day): ?>
                            <div class="custom-control custom-checkbox custom-control-inline mb-2">
                                <input class="custom-control-input" type="checkbox" name="available_days[]" value="<?php echo $day; ?>" id="day_add_<?php echo $day; ?>" checked>
                                <label class="custom-control-label" for="day_add_<?php echo $day; ?>"><?php echo ucfirst($day); ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-500 text-dark">Item Image</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" name="image" id="customFile">
                            <label class="custom-file-label rounded-pill px-3" for="customFile">Choose image...</label>
                        </div>
                    </div>
                    <button type="submit" name="add_menu" class="btn btn-primary rounded-pill px-4 shadow-sm mt-2">Add Menu Item</button>
                </form>
            </div>
        </div>
    </div>

    <div id="skeleton-container" class="row">
        <?php for($i=0; $i<8; $i++): ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4">
            <div class="skeleton-card h-100">
                <div class="skeleton skeleton-img"></div>
                <div class="skeleton skeleton-text-title"></div>
                <div class="skeleton skeleton-text-sub"></div>
                <div class="w-100 text-center mb-3 mt-auto">
                    <div class="skeleton skeleton-btn"></div>
                    <div class="skeleton skeleton-btn"></div>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="actual-content" class="row d-none">
        
        <div id="empty-search-state" class="col-12 text-center py-5 d-none w-100">
            <i class="fas fa-utensils fa-4x text-muted mb-3 opacity-50"></i>
            <h5 class="font-weight-bold mt-2 text-dark">No matching items found</h5>
            <p class="text-muted">Try tweaking your search terms.</p>
        </div>

        <?php foreach ($menu_items as $item): 
            $searchable_text = htmlspecialchars(strtolower($item['name'] . ' ' . $item['category_name'] . ' ' . $item['description']));
            $default_stock = isset($item['default_stock']) ? $item['default_stock'] : 0;
            $status_class = $item['status'] === 'active' ? 'bg-success text-white' : 'bg-danger text-white';
            $image_path = "../assets/images/menu/" . htmlspecialchars($item['image']);
            if(!file_exists($image_path) || empty($item['image'])) $image_path = "../assets/images/menu/default.jpg";
        ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4 menu-item-container" data-search-text="<?php echo $searchable_text; ?>">
            <div class="food-card h-100 position-relative">
                
                <span class="status-badge <?php echo $status_class; ?>">
                    <?php echo ucfirst($item['status']); ?>
                </span>
                
                <span class="price-badge">
                    ₹<?php echo number_format($item['price'], 2); ?>
                </span>

                <div class="food-img-container">
                    <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                </div>
                
                <div class="p-3 d-flex flex-column flex-grow-1">
                    <h5 class="font-weight-bold mb-1 text-truncate" title="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php echo htmlspecialchars($item['name']); ?>
                    </h5>

                    <?php if(!empty($item['category_name'])): ?>
                        <span class="badge badge-info mb-2 d-inline-block text-truncate" style="max-width: 100px;"><?php echo htmlspecialchars($item['category_name']); ?></span>
                    <?php endif; ?>
                    
                    <p class="text-muted small mb-2 text-truncate" title="<?php echo htmlspecialchars($item['description']); ?>">
                        <?php echo htmlspecialchars($item['description']) ?: 'No description available.'; ?>
                    </p>
                    
                    <div class="mt-auto mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1 bg-light rounded px-2 py-1">
                            <span class="small font-weight-500 text-muted">Stock / Default</span>
                            <span class="small font-weight-bold text-dark"><?php echo $item['stock']; ?> / <?php echo $default_stock; ?></span>
                        </div>
                        <div class="small text-muted mt-2">
                            <i class="far fa-clock mr-1"></i> <?php echo date('h:i A', strtotime($item['available_from'])); ?> - <?php echo date('h:i A', strtotime($item['available_to'])); ?>
                        </div>
                    </div>
                    
                    <div class="w-100 d-flex justify-content-between mt-2 border-top pt-3">
                        <button class="btn btn-sm btn-outline-info flex-grow-1 mr-1 rounded-pill font-weight-bold" data-toggle="modal" data-target="#editModal-<?php echo $item['id']; ?>"><i class="fas fa-edit"></i> Edit</button>
                        <form action="menu_management.php" method="post" class="d-inline flex-grow-1 ml-1" onsubmit="return confirm('Are you sure you want to delete this menu item?');">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <button type="submit" name="delete_menu" class="btn btn-sm btn-outline-danger w-100 rounded-pill font-weight-bold"><i class="fas fa-trash"></i> Delete</button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <div class="modal fade" id="editModal-<?php echo $item['id']; ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content shadow-lg">
                    <div class="modal-header">
                        <h5 class="modal-title font-weight-bold">Edit Menu Item</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form action="menu_management.php" method="post" enctype="multipart/form-data">
                        <div class="modal-body p-4">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            
                            <div class="text-center mb-4">
                                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="img-thumbnail rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                <h6 class="font-weight-bold mb-0 text-dark"><?php echo htmlspecialchars($item['name']); ?></h6>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="font-weight-500 text-dark">Name</label>
                                    <input type="text" class="form-control rounded-pill px-3" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="font-weight-500 text-dark">Category</label>
                                    <select class="form-control rounded-pill px-3" name="category_id" required>
                                        <option value="" disabled>Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo ($item['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label class="font-weight-500 text-dark">Price (₹)</label>
                                    <input type="number" step="0.01" class="form-control rounded-pill px-3" name="price" value="<?php echo $item['price']; ?>" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="font-weight-500 text-dark">Current Stock</label>
                                    <input type="number" class="form-control rounded-pill px-3" name="stock" value="<?php echo $item['stock']; ?>" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="font-weight-500 text-dark">Default Daily</label>
                                    <input type="number" class="form-control rounded-pill px-3" name="default_stock" value="<?php echo $default_stock; ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-500 text-dark">Description</label>
                                <textarea class="form-control rounded" name="description" rows="2" style="border-radius: 15px;"><?php echo htmlspecialchars($item['description']); ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="font-weight-500 text-dark">Available From</label>
                                    <input type="time" class="form-control rounded-pill px-3" name="available_from" value="<?php echo $item['available_from']; ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="font-weight-500 text-dark">Available To</label>
                                    <input type="time" class="form-control rounded-pill px-3" name="available_to" value="<?php echo $item['available_to']; ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-500 text-dark d-block mb-2">Available Days</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php $item_days = explode(',', $item['available_days']); ?>
                                    <?php foreach ($days as $day): ?>
                                    <div class="custom-control custom-checkbox custom-control-inline mb-2">
                                        <input class="custom-control-input" type="checkbox" name="available_days[]" value="<?php echo $day; ?>" id="day_edit_<?php echo $item['id'] . '_' . $day; ?>" <?php echo in_array($day, $item_days) ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="day_edit_<?php echo $item['id'] . '_' . $day; ?>"><?php echo ucfirst($day); ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="font-weight-500 text-dark">Status</label>
                                    <select class="form-control rounded-pill px-3" name="status" required>
                                        <option value="active" <?php echo $item['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $item['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="font-weight-500 text-dark">Upload New Image <small>(optional)</small></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" name="image" id="editImage-<?php echo $item['id']; ?>">
                                        <label class="custom-file-label rounded-pill px-3" for="editImage-<?php echo $item['id']; ?>">Choose image...</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-dismiss="modal">Close</button>
                            <button type="submit" name="update_menu" class="btn btn-primary rounded-pill px-4 shadow-sm">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
</div>

<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0 py-3">
                <h5 class="modal-title font-weight-bold text-dark"><i class="fas fa-tags text-primary mr-2"></i>Add New Category</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="menu_management.php" method="post">
                <div class="modal-body px-4 py-4">
                    <div class="form-group mb-0">
                        <label class="font-weight-500 text-dark">Category Name</label>
                        <input type="text" class="form-control rounded-pill px-3" name="category_name" required placeholder="e.g., Beverages">
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 bg-white">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary rounded-pill px-4 shadow-sm">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function KMPSearch(pat, txt) {
    if (pat === "") return true;
    return txt.indexOf(pat) !== -1;
}

function liveSearch() {
    let input = document.getElementById('searchInput').value.toLowerCase().trim();
    let items = document.querySelectorAll('.menu-item-container');
    let emptyState = document.getElementById('empty-search-state');
    
    let keywords = input.split(/\s+/).filter(kw => kw.length > 0);
    let visibleCount = 0;

    items.forEach(item => {
        if (input === '') {
            item.classList.remove('d-none');
            visibleCount++;
            return;
        }

        let text = item.getAttribute('data-search-text');
        let allMatch = true;

        for (let kw of keywords) {
            if (!KMPSearch(kw, text)) { allMatch = false; break; }
        }

        if (allMatch) {
            item.classList.remove('d-none');
            visibleCount++;
        } else {
            item.classList.add('d-none');
        }
    });
    
    if (visibleCount === 0 && items.length > 0) {
        emptyState.classList.remove('d-none');
    } else {
        emptyState.classList.add('d-none');
    }
}

document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        var skel = document.getElementById('skeleton-container');
        var content = document.getElementById('actual-content');
        if (skel) skel.classList.add('d-none');
        if (content) {
            content.classList.remove('d-none');
            setTimeout(() => content.classList.add('show-content'), 50); 
            liveSearch();
        }
    }, 400); 

    document.querySelectorAll('.custom-file-input').forEach(function(input) {
        input.addEventListener('change', function(e) {
            if(e.target.files.length > 0) {
                var fileName = e.target.files[0].name;
                var nextSibling = e.target.nextElementSibling;
                nextSibling.innerText = fileName;
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>