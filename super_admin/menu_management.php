<?php
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

// PRG pattern: Process POST request and redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new category
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        if (!empty($category_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO menu_categories (name) VALUES (?)");
                $stmt->execute([$category_name]);
            } catch (PDOException $e) {
                // Ignore if exists
            }
        }
    }

    // Add menu item
    if (isset($_POST['add_menu'])) {
        $name = trim($_POST['name']);
        $category_id = $_POST['category_id'];
        $description = trim($_POST['description']);
        // Prevent negative values on the backend
        $price = max(0, (float)$_POST['price']);
        $stock = max(0, (int)$_POST['stock']);
        $available_from = $_POST['available_from'];
        $available_to = $_POST['available_to'];
        $available_days = implode(',', $_POST['available_days'] ?? []);
        $image = 'default.jpg';

        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $image_name = time() . '_' . basename($_FILES["image"]["name"]);
            $target_file = $upload_dir . $image_name;
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image = $image_name;
            }
        }
        
        $sql = "INSERT INTO menu (name, category_id, description, price, stock, available_from, available_to, available_days, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $category_id, $description, $price, $stock, $available_from, $available_to, $available_days, $image]);
    }

    // Update menu item
    if (isset($_POST['update_menu'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $category_id = $_POST['category_id'];
        $description = trim($_POST['description']);
        // Prevent negative values on the backend
        $price = max(0, (float)$_POST['price']);
        $stock = max(0, (int)$_POST['stock']);
        $available_from = $_POST['available_from'];
        $available_to = $_POST['available_to'];
        $available_days = implode(',', $_POST['available_days'] ?? []);
        $status = $_POST['status'];
        
        $sql = "UPDATE menu SET name=?, category_id=?, description=?, price=?, stock=?, available_from=?, available_to=?, available_days=?, status=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $category_id, $description, $price, $stock, $available_from, $available_to, $available_days, $status, $id]);

        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $image_name = time() . '_' . basename($_FILES["image"]["name"]);
            $target_file = $upload_dir . $image_name;
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $sql_update_image = "UPDATE menu SET image=? WHERE id=?";
                $pdo->prepare($sql_update_image)->execute([$image_name, $id]);
            }
        }
    }

    // Delete menu item
    if (isset($_POST['delete_menu'])) {
        $id = $_POST['id'];
        // First, get the image name to delete the file
        $stmt = $pdo->prepare("SELECT image FROM menu WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetchColumn();
        if ($image && $image !== 'default.jpg' && file_exists($upload_dir . $image)) {
            unlink($upload_dir . $image);
        }

        $sql = "DELETE FROM menu WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
    }
    
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch menu items with category names
$menu_items = $pdo->query("SELECT m.*, c.name as category_name FROM menu m LEFT JOIN menu_categories c ON m.category_id = c.id ORDER BY m.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

// Variable to store modals outside the table to prevent clipping
$globalModals = "";
?>

<style>
    /* Full Page Loader */
    #page-loader {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.95); z-index: 9999;
        display: flex; justify-content: center; align-items: center;
        transition: opacity 0.5s ease, visibility 0.5s ease;
    }
    .spinner {
        width: 60px; height: 60px; border: 6px solid #e3e6f0;
        border-top: 6px solid #4e73df; border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    /* Fade-in Animations */
    .fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.165, 0.84, 0.44, 1) forwards; opacity: 0; transform: translateY(20px); }
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }

    /* Elegant Layout Additions */
    .table-container { padding: 0 1rem; }
    .table-hover tbody tr { transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border-radius: 12px; }
    .table-hover tbody tr:hover { background-color: #ffffff; transform: scale(1.01) translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); z-index: 1; position: relative; }
    
    /* Image Thumbnail Styling */
    .img-thumbnail-menu { width: 60px; height: 60px; object-fit: cover; border-radius: 12px; border: 2px solid #e2e8f0; padding: 2px; background-color: #fff; }

    /* Action Buttons */
    .action-btn { transition: all 0.2s ease; }
    .action-btn:hover { transform: translateY(-3px); box-shadow: 0 5px 12px rgba(0,0,0,0.15); }

    /* Custom Checkboxes */
    .custom-checkbox-wrapper { display: flex; flex-wrap: wrap; gap: 8px; }
    .custom-checkbox-wrapper .custom-control-label {
        background: #f1f5f9; padding: 6px 14px; border-radius: 20px; cursor: pointer;
        transition: all 0.2s; border: 1px solid transparent; font-size: 0.85rem; font-weight: 600; color: #64748b;
    }
    .custom-checkbox-wrapper .custom-control-input:checked ~ .custom-control-label {
        background: #4e73df; color: white; box-shadow: 0 4px 10px rgba(78, 115, 223, 0.3); border-color: #4e73df;
    }
    .custom-checkbox-wrapper .custom-control-label::before, 
    .custom-checkbox-wrapper .custom-control-label::after { display: none; }
</style>

<div id="page-loader">
    <div class="spinner"></div>
</div>

<div class="container-fluid px-0 fade-in-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-dark font-weight-bold mb-0">Menu Management</h3>
    </div>

    <div class="row align-items-start">
        
        <div class="col-lg-4 col-md-12 mb-4 fade-in-up delay-1">
            <div class="card elegant-card shadow-sm h-100">
                <div class="card-header bg-white py-4 border-bottom" style="border-radius: 16px 16px 0 0;">
                    <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-hamburger mr-2 text-warning"></i> Add New Item</h5>
                </div>
                <div class="card-body p-4 bg-light" style="border-radius: 0 0 16px 16px;">
                    <form action="menu_management.php" method="post" enctype="multipart/form-data" class="needs-validation">
                        
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-muted small text-uppercase">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g., Veg Burger">
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-muted small text-uppercase d-flex justify-content-between align-items-center">
                                <span>Category <span class="text-danger">*</span></span>
                                <a href="#" data-toggle="modal" data-target="#addCategoryModal" class="badge badge-primary text-white p-2" style="cursor: pointer; font-size: 10px;"><i class="fas fa-plus"></i> NEW</a>
                            </label>
                            <select class="form-control custom-select" name="category_id" required>
                                <option value="" disabled selected>Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row mb-3">
                            <div class="form-group col-6 mb-0">
                                <label class="font-weight-bold text-muted small text-uppercase">Price (₹) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control" name="price" required placeholder="0.00">
                            </div>
                            <div class="form-group col-6 mb-0">
                                <label class="font-weight-bold text-muted small text-uppercase">Stock <span class="text-danger">*</span></label>
                                <input type="number" min="0" class="form-control" name="stock" required placeholder="Quantity">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-muted small text-uppercase">Description</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Short details about the item..."></textarea>
                        </div>
                        
                        <div class="form-row mb-3">
                            <div class="form-group col-6 mb-0">
                                <label class="font-weight-bold text-muted small text-uppercase">Available From <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="available_from" required>
                            </div>
                            <div class="form-group col-6 mb-0">
                                <label class="font-weight-bold text-muted small text-uppercase">Available To <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="available_to" required>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-muted small text-uppercase mb-2">Available Days <span class="text-danger">*</span></label>
                            <div class="custom-checkbox-wrapper">
                                <?php foreach ($days as $day): ?>
                                    <div class="custom-control custom-checkbox d-inline-block p-0">
                                        <input type="checkbox" class="custom-control-input" id="day_<?php echo $day; ?>" name="available_days[]" value="<?php echo $day; ?>" checked>
                                        <label class="custom-control-label m-0" for="day_<?php echo $day; ?>"><?php echo ucfirst(substr($day, 0, 3)); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-muted small text-uppercase">Item Image</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="customFile" name="image" accept="image/*">
                                <label class="custom-file-label" for="customFile" style="color: #94a3b8;">Choose image...</label>
                            </div>
                        </div>
                        
                        <div class="pt-2">
                            <button type="submit" name="add_menu" class="btn btn-primary btn-block shadow-sm py-3 action-btn rounded-pill font-weight-bold">
                                <i class="fas fa-plus-circle mr-2"></i> Publish Menu Item
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-md-12 mb-4 fade-in-up delay-2">
            <div class="card elegant-card shadow-sm h-100">
                <div class="card-header bg-white py-4 border-bottom-0" style="border-radius: 16px 16px 0 0;">
                    <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-list-alt mr-2 text-success"></i> Existing Catalog</h5>
                </div>
                
                <div class="card-body p-0 pb-4 bg-white" style="border-radius: 0 0 16px 16px;">
                    <?php if(empty($menu_items)): ?>
                        <div class='text-center py-5 text-muted'>
                            <i class='fas fa-utensils fa-4x mb-3 opacity-25'></i><br>
                            <h5 class="font-weight-bold mt-2">No menu items found</h5>
                            <p>Add your first item using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive table-container">
                            <table class="table table-hover table-borderless align-middle mb-0" width="100%" cellspacing="0" style="border-collapse: separate; border-spacing: 0 8px;">
                                <thead class="text-uppercase text-xs font-weight-bold text-muted">
                                    <tr>
                                        <th class="pl-4">Item Details</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th class="text-right pr-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($menu_items as $item): ?>
                                    <tr class="bg-light shadow-sm">
                                        <td class="align-middle rounded-left pl-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="mr-3 d-none d-sm-block">
                                                    <img src="../assets/images/menu/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="img-thumbnail-menu shadow-sm">
                                                </div>
                                                <div>
                                                    <div class="font-weight-bold text-dark mb-1" style="font-size: 1.05rem;">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                        <?php if(!empty($item['category_name'])): ?>
                                                            <span class="badge badge-info ml-1" style="font-size: 0.7rem; vertical-align: middle;"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="small text-muted font-weight-bold"><i class="far fa-clock mr-1"></i><?php echo date('h:i A', strtotime($item['available_from'])) . ' - ' . date('h:i A', strtotime($item['available_to'])); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td class="align-middle font-weight-bold text-success" style="font-size: 1.1rem;">
                                            ₹<?php echo number_format($item['price'], 2); ?>
                                        </td>
                                        
                                        <td class="align-middle text-secondary font-weight-bold">
                                            <span class="badge badge-light border px-3 py-2 text-dark"><i class="fas fa-box-open mr-1"></i> <?php echo $item['stock']; ?></span>
                                        </td>

                                        <td class="align-middle">
                                            <?php $statusBadge = $item['status'] === 'active' ? 'success' : 'secondary'; ?>
                                            <span class="badge badge-<?php echo $statusBadge; ?> badge-pill px-3 py-2 shadow-sm">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        </td>
                                        
                                        <td class="align-middle rounded-right text-right pr-4">
                                            <button class="btn btn-sm btn-white text-info rounded-circle px-2 py-1 mr-1 action-btn shadow-sm" data-toggle="modal" data-target="#editModal-<?php echo $item['id']; ?>" title="Edit Item">
                                                <i class="fas fa-pen fa-sm"></i>
                                            </button>
                                            <form action="menu_management.php" method="post" class="d-inline" onsubmit="return confirm('Permanently delete this item?');">
                                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="delete_menu" class="btn btn-sm btn-white text-danger rounded-circle px-2 py-1 action-btn shadow-sm" title="Delete Item">
                                                    <i class="fas fa-trash fa-sm"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>

                                    <?php 
                                    // Generate the modal HTML and store it outside the loop to prevent clipping/z-index issues
                                    $item_days = explode(',', $item['available_days']);
                                    $checkboxes_html = "";
                                    foreach ($days as $day) {
                                        $checked = in_array($day, $item_days) ? 'checked' : '';
                                        $checkboxes_html .= "
                                        <div class='custom-control custom-checkbox d-inline-block p-0'>
                                            <input type='checkbox' class='custom-control-input' id='edit_day_{$item['id']}_{$day}' name='available_days[]' value='{$day}' {$checked}>
                                            <label class='custom-control-label m-0' for='edit_day_{$item['id']}_{$day}'>" . ucfirst(substr($day, 0, 3)) . "</label>
                                        </div>";
                                    }

                                    $activeSel = $item['status'] === 'active' ? 'selected' : '';
                                    $inactiveSel = $item['status'] === 'inactive' ? 'selected' : '';
                                    
                                    $category_options = "<option value='' disabled>Select Category</option>";
                                    foreach ($categories as $cat) {
                                        $catSelected = ($item['category_id'] == $cat['id']) ? 'selected' : '';
                                        $category_options .= "<option value='{$cat['id']}' {$catSelected}>" . htmlspecialchars($cat['name']) . "</option>";
                                    }

                                    $imagePath = "../assets/images/menu/" . htmlspecialchars($item['image']);

                                    $globalModals .= "
                                    <div class='modal fade' id='editModal-{$item['id']}' tabindex='-1'>
                                        <div class='modal-dialog modal-lg modal-dialog-centered'>
                                            <div class='modal-content border-0 shadow-lg' style='border-radius: 16px;'>
                                                <div class='modal-header bg-light border-bottom-0 py-3' style='border-radius: 16px 16px 0 0;'>
                                                    <h5 class='modal-title font-weight-bold text-dark'><i class='fas fa-edit text-primary mr-2'></i>Edit: " . htmlspecialchars($item['name']) . "</h5>
                                                    <button type='button' class='close' data-dismiss='modal'><span>&times;</span></button>
                                                </div>
                                                <form action='menu_management.php' method='post' enctype='multipart/form-data'>
                                                    <div class='modal-body px-4 py-4 text-left'>
                                                        <input type='hidden' name='id' value='{$item['id']}'>
                                                        
                                                        <div class='form-row'>
                                                            <div class='form-group col-md-6'>
                                                                <label class='font-weight-bold text-muted small text-uppercase mb-1'>Item Name</label>
                                                                <input type='text' class='form-control' name='name' value='" . htmlspecialchars($item['name']) . "' required>
                                                            </div>
                                                            <div class='form-group col-md-6'>
                                                                <label class='font-weight-bold text-muted small text-uppercase mb-1'>Category</label>
                                                                <select class='form-control custom-select' name='category_id' required>
                                                                    {$category_options}
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class='form-row'>
                                                            <div class='form-group col-md-6'>
                                                                <label class='font-weight-bold text-muted small text-uppercase mb-1'>Price (₹)</label>
                                                                <input type='number' step='0.01' min='0' class='form-control' name='price' value='{$item['price']}' required>
                                                            </div>
                                                            <div class='form-group col-md-6'>
                                                                <label class='font-weight-bold text-muted small text-uppercase mb-1'>Stock</label>
                                                                <input type='number' min='0' class='form-control' name='stock' value='{$item['stock']}' required>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class='form-group'>
                                                            <label class='font-weight-bold text-muted small text-uppercase mb-1'>Description</label>
                                                            <textarea class='form-control' name='description' rows='2'>" . htmlspecialchars($item['description']) . "</textarea>
                                                        </div>
                                                        
                                                        <div class='form-row'>
                                                            <div class='form-group col-md-6'>
                                                                <label class='font-weight-bold text-muted small text-uppercase mb-1'>Available From</label>
                                                                <input type='time' class='form-control' name='available_from' value='{$item['available_from']}' required>
                                                            </div>
                                                            <div class='form-group col-md-6'>
                                                                <label class='font-weight-bold text-muted small text-uppercase mb-1'>Available To</label>
                                                                <input type='time' class='form-control' name='available_to' value='{$item['available_to']}' required>
                                                            </div>
                                                        </div>

                                                        <div class='form-group'>
                                                            <label class='font-weight-bold text-muted small text-uppercase mb-2'>Available Days</label>
                                                            <div class='custom-checkbox-wrapper'>
                                                                {$checkboxes_html}
                                                            </div>
                                                        </div>
                                                        
                                                        <div class='form-row mt-3'>
                                                            <div class='form-group col-md-6'>
                                                                <label class='font-weight-bold text-muted small text-uppercase mb-1'>Status</label>
                                                                <select class='form-control custom-select' name='status' required>
                                                                    <option value='active' {$activeSel}>Active</option>
                                                                    <option value='inactive' {$inactiveSel}>Inactive</option>
                                                                </select>
                                                                
                                                                <label class='font-weight-bold text-muted small text-uppercase mb-1 mt-4'>Update Image (Optional)</label>
                                                                <div class='custom-file'>
                                                                    <input type='file' class='custom-file-input' id='customFile_{$item['id']}' name='image' accept='image/*'>
                                                                    <label class='custom-file-label form-control' for='customFile_{$item['id']}' style='color: #94a3b8;'>Choose new image...</label>
                                                                </div>
                                                            </div>
                                                            <div class='form-group col-md-6 text-center'>
                                                                <label class='font-weight-bold text-muted small text-uppercase mb-2'>Current Image</label><br>
                                                                <img src='{$imagePath}' alt='Current Image' class='img-thumbnail shadow-sm' style='height: 140px; width: 140px; object-fit: cover; border-radius: 16px;'>
                                                            </div>
                                                        </div>
                                                        
                                                    </div>
                                                    <div class='modal-footer border-top-0 px-4 pb-4 bg-white' style='border-radius: 0 0 16px 16px;'>
                                                        <button type='button' class='btn btn-light rounded-pill px-4' data-dismiss='modal'>Cancel</button>
                                                        <button type='submit' name='update_menu' class='btn btn-primary rounded-pill px-4 shadow-sm'>Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>";
                                    ?>
                                    
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-light border-bottom-0 py-3" style="border-radius: 16px 16px 0 0;">
                <h5 class="modal-title font-weight-bold text-dark"><i class="fas fa-tags text-primary mr-2"></i>Add New Category</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="menu_management.php" method="post">
                <div class="modal-body px-4 py-4">
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-muted small text-uppercase">Category Name</label>
                        <input type="text" class="form-control" name="category_name" required placeholder="e.g., Beverages">
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 bg-white" style="border-radius: 0 0 16px 16px;">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary rounded-pill px-4 shadow-sm">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php echo $globalModals; ?>

<script>
    // Handle loading icon dismissal
    window.addEventListener('load', function() {
        const loader = document.getElementById('page-loader');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(function() {
                loader.style.visibility = 'hidden';
                loader.style.display = 'none';
            }, 500);
        }
    });

    // Update custom file input labels with selected filename
    document.addEventListener('DOMContentLoaded', function() {
        var fileInputs = document.querySelectorAll('.custom-file-input');
        fileInputs.forEach(function(input) {
            input.addEventListener('change', function(e) {
                var fileName = e.target.files[0].name;
                var label = e.target.nextElementSibling;
                label.innerText = fileName;
                label.style.color = '#333'; 
            });
        });
    });
</script>

<?php include 'footer.php'; ?>