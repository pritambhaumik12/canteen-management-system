<?php
include 'header.php';
require_once '../includes/db.php';

$upload_dir = "../assets/images/logos/";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    foreach (['institution_name', 'canteen_name', 'address1', 'address2'] as $key) {
        if (isset($_POST[$key])) $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([trim($_POST[$key]), $key]);
    }

    foreach (['institution_logo', 'canteen_logo'] as $key) {
        if (isset($_FILES[$key]) && $_FILES[$key]['error'] == 0) {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?"); $stmt->execute([$key]); $old = $stmt->fetchColumn();
            if ($old && file_exists($upload_dir . $old)) unlink($upload_dir . $old);
            
            $name = time() . '_' . basename($_FILES[$key]["name"]);
            if (move_uploaded_file($_FILES[$key]["tmp_name"], $upload_dir . $name)) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$name, $key]);
            }
        }
    }
    header("Location: " . $_SERVER['REQUEST_URI'] . "?success=1");
    exit;
}

$settings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
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

    /* Elegant UI Elements */
    .elegant-card { border-radius: 16px; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.04) !important; overflow: hidden; }
    .form-control-custom { border-radius: 10px; border: 1px solid #e2e8f0; padding: 0.75rem 1rem; background-color: #f8fafc; transition: all 0.3s ease; }
    .form-control-custom:focus { border-color: #4e73df; background-color: #ffffff; box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.15); outline: none; }
    .action-btn { transition: all 0.2s ease; }
    .action-btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
    
    /* Image Preview Boxes */
    .img-preview-box { border-radius: 12px; border: 2px dashed #cbd5e1; padding: 10px; background-color: #f8fafc; transition: all 0.3s ease; }
    .img-preview-box:hover { border-color: #94a3b8; background-color: #f1f5f9; }
</style>

<div id="page-loader">
    <div class="spinner"></div>
</div>

<div class="container-fluid px-0 fade-in-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-dark font-weight-bold mb-1">Platform Settings</h3>
            <p class="text-muted mb-0"><i class="fas fa-cogs mr-1"></i> Manage Global System Configurations</p>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-lg border-0 fade-in-up delay-1" role="alert" style="border-left: 5px solid #1cc88a;">
        <i class="fas fa-check-circle mr-2 text-success"></i> <strong class="text-dark">Success!</strong> System configurations updated successfully.
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
    <?php endif; ?>

    <div class="card elegant-card shadow-sm mb-5 fade-in-up delay-2">
        <div class="card-header bg-white py-4 border-bottom-0" style="border-radius: 16px 16px 0 0;">
            <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-sliders-h text-primary mr-2"></i> Core Configurations</h5>
        </div>
        <div class="card-body p-4 p-md-5 bg-light" style="border-radius: 0 0 16px 16px;">
            <form action="settings.php" method="post" enctype="multipart/form-data">
                <div class="row">
                    
                    <div class="col-lg-6 pr-lg-5 mb-5 mb-lg-0 border-right-lg">
                        <div class="d-flex align-items-center mb-4">
                            <div class="icon-circle bg-primary text-white mr-3 shadow-sm" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-building"></i>
                            </div>
                            <h6 class="text-uppercase font-weight-bold text-dark mb-0 tracking-wide">Text Identities</h6>
                        </div>
                        
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-muted small text-uppercase mb-2">Institution Name</label>
                            <input type="text" class="form-control form-control-custom form-control-lg" name="institution_name" value="<?php echo htmlspecialchars($settings['institution_name'] ?? ''); ?>" placeholder="Enter institution name">
                        </div>
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-muted small text-uppercase mb-2">Canteen Application Name</label>
                            <input type="text" class="form-control form-control-custom form-control-lg" name="canteen_name" value="<?php echo htmlspecialchars($settings['canteen_name'] ?? ''); ?>" placeholder="Enter application name">
                        </div>
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-muted small text-uppercase mb-2">Address Line 1</label>
                            <input type="text" class="form-control form-control-custom" name="address1" value="<?php echo htmlspecialchars($settings['address1'] ?? ''); ?>" placeholder="Street address or P.O. Box">
                        </div>
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-muted small text-uppercase mb-2">Address Line 2</label>
                            <input type="text" class="form-control form-control-custom" name="address2" value="<?php echo htmlspecialchars($settings['address2'] ?? ''); ?>" placeholder="City, State, ZIP code">
                        </div>
                    </div>
                    
                    <div class="col-lg-6 pl-lg-5">
                        <div class="d-flex align-items-center mb-4">
                            <div class="icon-circle bg-info text-white mr-3 shadow-sm" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-images"></i>
                            </div>
                            <h6 class="text-uppercase font-weight-bold text-dark mb-0 tracking-wide">Branding Assets</h6>
                        </div>
                        
                        <div class="card border-0 shadow-sm rounded-lg mb-4 bg-white p-4">
                            <label class="font-weight-bold text-muted small text-uppercase d-block mb-3">Institution Logo</label>
                            <div class="text-center mb-3 img-preview-box">
                                <?php if(!empty($settings['institution_logo'])): ?>
                                    <img src="../assets/images/logos/<?php echo htmlspecialchars($settings['institution_logo']); ?>" class="img-fluid rounded" style="max-height: 100px; object-fit:contain;">
                                <?php else: ?>
                                    <div class="py-4 text-muted"><i class="fas fa-image fa-2x mb-2 opacity-50"></i><br>No logo uploaded</div>
                                <?php endif; ?>
                            </div>
                            <div class="custom-file text-left">
                                <input type="file" class="custom-file-input" name="institution_logo" id="instLogo" accept="image/*">
                                <label class="custom-file-label" for="instLogo" style="color: #94a3b8; border-radius: 8px;">Choose new image...</label>
                            </div>
                        </div>
                        
                        <div class="card border-0 shadow-sm rounded-lg bg-white p-4">
                            <label class="font-weight-bold text-muted small text-uppercase d-block mb-3">Canteen Logo</label>
                            <div class="text-center mb-3 img-preview-box">
                                <?php if(!empty($settings['canteen_logo'])): ?>
                                    <img src="../assets/images/logos/<?php echo htmlspecialchars($settings['canteen_logo']); ?>" class="img-fluid rounded" style="max-height: 100px; object-fit:contain;">
                                <?php else: ?>
                                    <div class="py-4 text-muted"><i class="fas fa-image fa-2x mb-2 opacity-50"></i><br>No logo uploaded</div>
                                <?php endif; ?>
                            </div>
                            <div class="custom-file text-left">
                                <input type="file" class="custom-file-input" name="canteen_logo" id="cantLogo" accept="image/*">
                                <label class="custom-file-label" for="cantLogo" style="color: #94a3b8; border-radius: 8px;">Choose new image...</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr class="my-5 border-light">
                
                <div class="text-right">
                    <button type="submit" name="update_settings" class="btn btn-primary btn-lg shadow-sm rounded-pill px-5 font-weight-bold action-btn">
                        <i class="fas fa-save mr-2"></i> Apply Global Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Desktop responsive border for the split layout */
    @media (min-width: 992px) {
        .border-right-lg {
            border-right: 1px solid #e2e8f0;
        }
    }
</style>

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
    document.querySelectorAll('.custom-file-input').forEach(input => {
        input.addEventListener('change', function(e) {
            let fileName = e.target.files[0].name;
            let label = e.target.nextElementSibling;
            label.innerText = fileName;
            label.style.color = '#333'; // Darken text once file is selected
        });
    });
</script>

<?php include 'footer.php'; ?>