<?php
ob_start(); // Start output buffering to prevent headers already sent errors

// Handle CSV Template Download (Must be before any HTML output)
if (isset($_GET['download_template']) && $_GET['download_template'] == '1') {
    $role = isset($_GET['role']) && $_GET['role'] === 'staff' ? 'staff' : 'member';
    
    ob_end_clean(); // Clear buffer before outputting CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="user_import_template_' . $role . '.csv"');
    $output = fopen('php://output', 'w');
    
    if ($role === 'staff') {
        // Staff Template
        fputcsv($output, ['user_id', 'name', 'email']);
        fputcsv($output, ['EMP101', 'John Doe', 'john.doe@example.com']);
    } else {
        // Member Template
        fputcsv($output, ['user_id', 'name', 'email', 'department']);
        fputcsv($output, ['STU202', 'Jane Smith', 'jane.smith@example.com', 'Computer Science']);
    }
    
    fclose($output);
    exit;
}

require_once '../includes/db.php';

// ==========================================
// AJAX: Real-time Duplicate Check
// ==========================================
if (isset($_POST['action']) && $_POST['action'] === 'check_duplicate') {
    ob_end_clean();
    header('Content-Type: application/json');
    $field = $_POST['field'] ?? '';
    $value = trim($_POST['value'] ?? '');
    
    $response = ['exists' => false, 'message' => ''];
    
    if ($value !== '') {
        if ($field === 'user_id') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM canteen_staff WHERE staff_id = ? UNION ALL SELECT COUNT(*) FROM members WHERE member_id = ?");
            $stmt->execute([$value, $value]);
            $counts = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (array_sum($counts) > 0) {
                $response['exists'] = true;
                $response['message'] = 'User ID already exists.';
            }
        } elseif ($field === 'email') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM canteen_staff WHERE email = ? UNION ALL SELECT COUNT(*) FROM members WHERE email = ?");
            $stmt->execute([$value, $value]);
            $counts = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (array_sum($counts) > 0) {
                $response['exists'] = true;
                $response['message'] = 'Email already exists.';
            }
        }
    }
    echo json_encode($response);
    exit;
}

include 'header.php';

// Function to queue the highly interactive POS-style welcome email
function queue_welcome_email($pdo, $email, $name, $user_id, $password, $role, $department = null) {
    // Fetch Settings for Logos and Names
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $inst_name = $settings['institution_name'] ?? 'Institution';
    $cant_name = $settings['canteen_name'] ?? 'Canteen';
    $inst_logo = $settings['institution_logo'] ?? '';
    $cant_logo = $settings['canteen_logo'] ?? '';
    
    // Construct dynamic Sender Name
    $sender_name = "{$cant_name} - {$inst_name}";

    // Handle Image Embedding via CID (Content-ID)
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
                $header_html .= '<img src="cid:cant_logo_cid" alt="'.htmlspecialchars($cant_name).'" style="max-height: 70px; margin: 0 15px; vertical-align: middle;">';
            }
        }
        
        $header_html .= '</div>';
    }

    $header_html .= '<h1 style="color: #1e293b; margin: 0; font-size: 22px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">'.htmlspecialchars($inst_name).'</h1>';
    $header_html .= '<p style="color: #64748b; margin: 5px 0 0 0; font-size: 16px; font-weight: 600;">'.htmlspecialchars($cant_name).'</p>';
    $header_html .= '</div>';

    // Dynamically build the portal URL based on the current server configuration
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $domain = $_SERVER['HTTP_HOST'];
    $base_dir = dirname(dirname($_SERVER['PHP_SELF'])); // Moves up one level to /canteen
    $portal_url = $protocol . "://" . $domain . $base_dir . "/" . $role . "/login.php";
    $year = date('Y');
    
    $subject = "Welcome to {$cant_name}! Your Login Details 🍔";
    
    // Conditional Department Row
    $dept_html = "";
    if ($role === 'member' && !empty($department)) {
        $dept_html = "
        <tr>
            <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Department</td>
            <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 16px;'>" . htmlspecialchars($department) . "</td>
        </tr>";
    }

    // Attractive, Interactive Modern HTML Email Template
    $html_body = "
    <div style='font-family: \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px 20px; background-color: #f1f5f9;'>
        {$header_html}
        <div style='background-color: #ffffff; padding: 40px 30px; border-radius: 16px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
            
            <div style='background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: white; width: 80px; height: 80px; line-height: 80px; border-radius: 50%; font-size: 35px; margin: 0 auto 25px; box-shadow: 0 10px 25px rgba(79, 70, 229, 0.4);'>
                👋
            </div>
            
            <h2 style='color: #0f172a; margin-top: 0; font-size: 26px; font-weight: 800;'>Welcome Aboard!</h2>
            <p style='color: #475569; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>Hi <strong style='color: #0f172a;'>" . htmlspecialchars($name) . "</strong>,<br>Your account is ready! You can now access the canteen portal to explore the menu and place orders seamlessly.</p>

            <div style='background-color: #f8fafc; padding: 25px; border-radius: 12px; margin: 25px 0; border: 1px dashed #cbd5e1; text-align: left; position: relative;'>
                
                <div style='text-align: center; margin-bottom: 20px;'>
                    <span style='background: #fff; padding: 5px 15px; border-radius: 20px; border: 1px solid #e2e8f0; font-weight: 700; color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; position: absolute; top: -14px; left: 50%; transform: translateX(-50%);'>Account Details</span>
                </div>

                <table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Name</td>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 16px;'>" . htmlspecialchars($name) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Email</td>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 16px;'>" . htmlspecialchars($email) . "</td>
                    </tr>
                    {$dept_html}
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>User ID</td>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 18px; font-family: monospace;'>" . htmlspecialchars($user_id) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Password</td>
                        <td style='padding: 10px 0; border-bottom: 1px dashed #e2e8f0; text-align: right; color: #0f172a; font-weight: 800; font-size: 18px; font-family: monospace;'>" . htmlspecialchars($password) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 15px 0 0; color: #64748b; font-weight: 600; font-size: 14px; text-transform: uppercase;'>Role</td>
                        <td style='padding: 15px 0 0; text-align: right; color: #4f46e5; text-transform: uppercase; font-weight: 800; font-size: 14px;'>" . htmlspecialchars($role) . "</td>
                    </tr>
                </table>
            </div>

            <div style='text-align: center; margin-top: 30px;'>
                <a href='" . htmlspecialchars($portal_url) . "' style='display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; text-decoration: none; padding: 16px 35px; border-radius: 50px; font-weight: 700; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4); transition: transform 0.2s;'>
                    Login to Portal &rarr;
                </a>
                
                <p style='margin: 20px 0 0; font-size: 13px; color: #94a3b8; line-height: 1.5;'>
                    <i style='color: #ef4444;'>*</i> For your security, please update your password after your first login.
                </p>
            </div>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; color: #94a3b8; font-size: 12px;'>
                &copy; $year {$cant_name}. All rights reserved.<br>
                Powered by Tech Innavo
            </div>
        </div>
    </div>";

    $images_json = !empty($embeddedImages) ? json_encode($embeddedImages) : null;
    $stmt = $pdo->prepare("INSERT INTO email_queue (recipient_email, recipient_name, subject, html_body, sender_name, embedded_images, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$email, $name, $subject, $html_body, $sender_name, $images_json]);
}

// PRG pattern: Process POST request and redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    
    $emails_queued = false;

    // Add single user
    if (isset($_POST['add_user'])) {
        $user_id = trim($_POST['user_id']);
        $name = trim($_POST['name']);
        $email = !empty(trim($_POST['email'])) ? trim($_POST['email']) : null;
        $role = $_POST['role'];
        $password = $user_id; // Plain text password (same as user_id)

        // Additional validation to prevent duplicate insertion
        $isDuplicate = false;
        $stmtDup = $pdo->prepare("SELECT COUNT(*) FROM canteen_staff WHERE staff_id = ? OR email = ? UNION ALL SELECT COUNT(*) FROM members WHERE member_id = ? OR email = ?");
        $stmtDup->execute([$user_id, $email, $user_id, $email]);
        if (array_sum($stmtDup->fetchAll(PDO::FETCH_COLUMN)) > 0) {
            $isDuplicate = true;
            $_SESSION['toast_msg'] = "Failed: User ID or Email already exists. ⚠️";
        }

        if (!$isDuplicate) {
            if ($role === 'staff') {
                // SET STATUS TO INACTIVE BY DEFAULT
                $sql = "INSERT INTO canteen_staff (staff_id, password, name, email, status) VALUES (?, ?, ?, ?, 'inactive')";
                if ($pdo->prepare($sql)->execute([$user_id, $password, $name, $email])) {
                    if (!empty($email)) {
                        queue_welcome_email($pdo, $email, $name, $user_id, $password, 'staff');
                        $emails_queued = true;
                    }
                    $_SESSION['toast_msg'] = $emails_queued ? "Staff created & welcome email queued! 📧" : "Staff created successfully! 🎉";
                }
            } elseif ($role === 'member') {
                $department = isset($_POST['department']) ? trim($_POST['department']) : null;
                // SET STATUS TO INACTIVE BY DEFAULT
                $sql = "INSERT INTO members (member_id, password, name, email, department, status) VALUES (?, ?, ?, ?, ?, 'inactive')";
                if ($pdo->prepare($sql)->execute([$user_id, $password, $name, $email, $department])) {
                    if (!empty($email)) {
                        queue_welcome_email($pdo, $email, $name, $user_id, $password, 'member', $department);
                        $emails_queued = true;
                    }
                    $_SESSION['toast_msg'] = $emails_queued ? "Member created & welcome email queued! 📧" : "Member created successfully! 🎉";
                }
            }
        }
    }

    // Update user
    if (isset($_POST['update_user'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $email = !empty(trim($_POST['email'])) ? trim($_POST['email']) : null;
        $role = $_POST['role']; 
        $status = $_POST['status'];

        if ($role === 'staff') {
            $sql = "UPDATE canteen_staff SET name = ?, email = ?, status = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$name, $email, $status, $id]);
        } elseif ($role === 'member') {
            $department = isset($_POST['department']) ? trim($_POST['department']) : null;
            $sql = "UPDATE members SET name = ?, email = ?, department = ?, status = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$name, $email, $department, $status, $id]);
        }
        $_SESSION['toast_msg'] = "User updated successfully! ✏️";
    }

    // Delete user
    if (isset($_POST['delete_user'])) {
        $id = $_POST['id'];
        $role = $_POST['role'];
        
        if ($role === 'staff') {
            $pdo->prepare("DELETE FROM canteen_staff WHERE id = ?")->execute([$id]);
        } elseif ($role === 'member') {
            $pdo->prepare("DELETE FROM members WHERE id = ?")->execute([$id]);
        }
        $_SESSION['toast_msg'] = "User deleted successfully! 🗑️";
    }
    
    // Import CSV Bulk Users
    if (isset($_POST['import_csv'])) {
        $import_role = $_POST['import_role']; 
        
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['csv_file']['tmp_name'];
            $fileExtension = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            
            if ($fileExtension === 'csv') {
                if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
                    $header = fgetcsv($handle, 1000, ","); 
                    
                    if ($import_role === 'staff') {
                        $check_staff = $pdo->prepare("SELECT id FROM canteen_staff WHERE staff_id = ?");
                        // SET STATUS TO INACTIVE BY DEFAULT
                        $insert_staff = $pdo->prepare("INSERT INTO canteen_staff (staff_id, password, name, email, status) VALUES (?, ?, ?, ?, 'inactive')");
                        
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            if (count($data) >= 2) { 
                                $user_id = trim($data[0] ?? '');
                                $name = trim($data[1] ?? '');
                                $email = !empty(trim($data[2] ?? '')) ? trim($data[2]) : null;
                                $password = $user_id; 
                                
                                if ($user_id === '' || $name === '') continue; 
                                
                                $check_staff->execute([$user_id]);
                                if (!$check_staff->fetch()) {
                                    if ($insert_staff->execute([$user_id, $password, $name, $email]) && !empty($email)) {
                                        queue_welcome_email($pdo, $email, $name, $user_id, $password, 'staff');
                                        $emails_queued = true;
                                    }
                                }
                            }
                        }
                    } elseif ($import_role === 'member') {
                        $check_member = $pdo->prepare("SELECT id FROM members WHERE member_id = ?");
                        // SET STATUS TO INACTIVE BY DEFAULT
                        $insert_member = $pdo->prepare("INSERT INTO members (member_id, password, name, email, department, status) VALUES (?, ?, ?, ?, ?, 'inactive')");
                        
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            if (count($data) >= 2) { 
                                $user_id = trim($data[0] ?? '');
                                $name = trim($data[1] ?? '');
                                $email = !empty(trim($data[2] ?? '')) ? trim($data[2]) : null;
                                $department = !empty(trim($data[3] ?? '')) ? trim($data[3]) : null;
                                $password = $user_id; 
                                
                                if ($user_id === '' || $name === '') continue; 
                                
                                $check_member->execute([$user_id]);
                                if (!$check_member->fetch()) {
                                    if ($insert_member->execute([$user_id, $password, $name, $email, $department]) && !empty($email)) {
                                        queue_welcome_email($pdo, $email, $name, $user_id, $password, 'member', $department);
                                        $emails_queued = true;
                                    }
                                }
                            }
                        }
                    }
                    
                    fclose($handle);
                    $_SESSION['toast_msg'] = $emails_queued ? "Import complete & emails queued! 📧" : "Bulk import completed! 🎉";
                }
            }
        }
    }
    
    ob_end_clean(); // Clear the main output buffer before redirecting to prevent header issues
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); // Redirect clearing GET params
    exit;
}

// Retrieve Flash Toast Messages
$toast_msg = $_SESSION['toast_msg'] ?? '';
unset($_SESSION['toast_msg']);

// ==========================================
// USER STATISTICS LOGIC
// ==========================================
$total_staff = $pdo->query("SELECT COUNT(*) FROM canteen_staff")->fetchColumn();
$total_members = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$total_users = $total_staff + $total_members;


// ==========================================
// DATA FETCHING & FILTER LOGIC
// ==========================================
$filter_status = $_GET['status'] ?? 'all';
$filter_role = $_GET['role'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$view_all = isset($_GET['view_all']) && $_GET['view_all'] == 1;

$is_filtered = ($filter_status !== 'all' || $filter_role !== 'all' || !empty($date_from) || !empty($date_to));

$staff_where = [];
$member_where = [];
$params_staff = [];
$params_member = [];

if ($filter_status !== 'all') {
    $staff_where[] = "status = ?";
    $member_where[] = "status = ?";
    $params_staff[] = $filter_status;
    $params_member[] = $filter_status;
}
if (!empty($date_from)) {
    $staff_where[] = "DATE(created_at) >= ?";
    $member_where[] = "DATE(created_at) >= ?";
    $params_staff[] = $date_from;
    $params_member[] = $date_from;
}
if (!empty($date_to)) {
    $staff_where[] = "DATE(created_at) <= ?";
    $member_where[] = "DATE(created_at) <= ?";
    $params_staff[] = $date_to;
    $params_member[] = $date_to;
}

$staff_sql = "SELECT id, staff_id AS user_id, name, email, 'staff' AS role, NULL AS department, status, created_at FROM canteen_staff";
if (count($staff_where) > 0) $staff_sql .= " WHERE " . implode(' AND ', $staff_where);

$member_sql = "SELECT id, member_id AS user_id, name, email, 'member' AS role, department, status, created_at FROM members";
if (count($member_where) > 0) $member_sql .= " WHERE " . implode(' AND ', $member_where);

$final_params = [];
if ($filter_role === 'staff') {
    $combined_sql = $staff_sql;
    $final_params = $params_staff;
} elseif ($filter_role === 'member') {
    $combined_sql = $member_sql;
    $final_params = $params_member;
} else {
    $combined_sql = "$staff_sql UNION ALL $member_sql";
    $final_params = array_merge($params_staff, $params_member);
}

$final_sql = "SELECT * FROM ($combined_sql) AS all_users ORDER BY created_at DESC";

// Default: Show 10 latest users if no filters are applied and "View All" isn't requested.
if (!$is_filtered && !$view_all) {
    $final_sql .= " LIMIT 10";
}

$stmt = $pdo->prepare($final_sql);
$stmt->execute($final_params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Close the container opened in header.php so we can make a full-width banner
?>
</div>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* UI FIX: Removes blank gap from header's empty container */
    nav.navbar + .container, 
    nav.navbar + .container-fluid,
    .navbar-expand-lg + .container,
    .navbar-expand-lg + .container-fluid { 
        display: none !important; 
        margin: 0 !important; 
        padding: 0 !important; 
        height: 0 !important; 
    }
    
    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }

    /* App-like Header Section */
    .greeting-section { 
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); 
        color: white; 
        padding: 30px 20px 60px; 
        border-radius: 0 0 30px 30px; 
        box-shadow: 0 4px 15px rgba(0,123,255,0.2); 
        margin-top: -1.5rem; /* Pulls the banner up to cover any remaining gap */
        position: relative;
        z-index: 10;
    }
    
    /* ADDED position: relative and z-index: 20 to bring search bar forward */
    .search-wrapper { 
        background: white; 
        border-radius: 50px; 
        padding: 5px 15px; 
        box-shadow: 0 10px 25px rgba(0,0,0,0.08); 
        transform: translateY(-25px); 
        margin: 0 auto 10px; 
        max-width: 600px; 
        display: flex; 
        align-items: center; 
        position: relative; 
        z-index: 20; 
    }
    .search-wrapper input { border: none; box-shadow: none !important; padding-left: 15px; padding-right: 15px; font-weight: 500; height: 45px; }
    
    /* Statistics Cards */
    .user-stat-card { transition: all 0.3s ease; }
    .user-stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important; }

    /* Interactive User Cards */
    .user-card { border-radius: 20px; border: none; transition: all 0.3s ease; overflow: hidden; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.04); }
    .user-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
    
    .avatar-circle { width: 65px; height: 65px; border-radius: 50%; background: linear-gradient(135deg, #e9ecef, #dee2e6); display: flex; align-items: center; justify-content: center; font-size: 26px; color: #495057; margin-bottom: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .badge-role { font-size: 0.75rem; padding: 6px 12px; border-radius: 50px; font-weight: 600; }
    
    .form-card { border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; }
    .form-card .card-header { background: transparent; border-bottom: 1px solid #f1f3f5; padding: 20px 25px; }
    
    /* Skeleton Loader */
    .skeleton { background-color: #e2e5e7; background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0)); background-size: 40px 100%; background-repeat: no-repeat; animation: shine 1.5s ease infinite; }
    @keyframes shine { to { background-position: right -40px top 0; } }
    .skeleton-card { border-radius: 20px; background: #fff; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.04); display: flex; flex-direction: column; align-items: center; }
    .skeleton-avatar { width: 65px; height: 65px; border-radius: 50%; margin-bottom: 15px; }
    .skeleton-text-title { height: 20px; width: 70%; margin-bottom: 10px; border-radius: 4px; }
    .skeleton-text-sub { height: 12px; width: 50%; margin-bottom: 15px; border-radius: 4px; }
    .skeleton-btn { height: 35px; width: 45%; border-radius: 50px; display: inline-block; margin: 0 2%; }

    #actual-content { opacity: 0; transition: opacity 0.5s ease; }
    #actual-content.show-content { opacity: 1; }
    
    /* Modals */
    .modal-content { border-radius: 20px; border: none; overflow: hidden; }
    .modal-header { background: #f8f9fa; border-bottom: none; padding: 20px 25px; }
    .modal-footer { border-top: none; padding: 15px 25px 20px; }
    
    .custom-file-label { border-radius: 50px; padding-left: 20px; padding-right: 20px; }
    .custom-file-label::after { border-radius: 0 50px 50px 0; }
    .custom-file-input:focus ~ .custom-file-label { box-shadow: none; border-color: #007bff; }

    /* Custom Scrollbar for the preview table */
    .table-responsive::-webkit-scrollbar { width: 8px; height: 8px; }
    .table-responsive::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .table-responsive::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
    .table-responsive::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }

    @media (max-width: 576px) {
        .greeting-section { padding: 20px 15px 50px; }
        .search-wrapper { margin: 0 10px 10px; width: calc(100% - 20px) !important; }
        .action-btns .btn { margin-bottom: 10px; width: 100%; }
        .action-btns .btn.ml-2 { margin-left: 0 !important; }
    }
</style>

<?php if (!empty($toast_msg)): ?>
<div id="toast-notification" class="shadow-lg rounded-pill px-4 py-3 d-flex align-items-center" style="position: fixed; top: 20px; right: 20px; z-index: 1055; background: linear-gradient(135deg, <?php echo strpos($toast_msg, 'Failed') !== false ? '#ef4444 0%, #dc2626 100%' : '#10b981 0%, #059669 100%'; ?>); color: white; font-weight: 600; transform: translateX(150%); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
    <i class="fas <?php echo strpos($toast_msg, 'Failed') !== false ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> fa-lg mr-2"></i>
    <span><?php echo $toast_msg; ?></span>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var toast = document.getElementById('toast-notification');
        if (toast) {
            // Slide in
            setTimeout(() => toast.style.transform = 'translateX(0)', 100);
            // Slide out & remove
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
            <h4 class="font-weight-bold mb-1">User Management</h4>
            <p class="mb-0 text-light" style="font-size: 0.95rem;">Manage your staff and members effortlessly.</p>
        </div>
    </div>
    
    <div class="container px-3 px-md-0">
        <form class="search-wrapper w-100" onsubmit="event.preventDefault();">
            <i class="fas fa-search text-muted ml-3"></i>
            <input class="form-control" type="search" placeholder="Search displayed users by name, ID, email..." id="searchInput" oninput="liveSearch()" autocomplete="off">
        </form>
    </div>
</div>

<div class="container mt-2 mb-5">
    
    <div class="row mb-4">
        <div class="col-4">
            <div class="bg-white rounded-lg p-3 shadow-sm text-center user-stat-card border" style="border-radius: 15px;">
                <div class="text-muted small font-weight-bold text-uppercase mb-1">Total Users</div>
                <div class="h4 mb-0 font-weight-bold text-dark"><?php echo number_format($total_users); ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bg-white rounded-lg p-3 shadow-sm text-center user-stat-card border" style="border-radius: 15px;">
                <div class="text-muted small font-weight-bold text-uppercase mb-1">Staff</div>
                <div class="h4 mb-0 font-weight-bold text-info"><?php echo number_format($total_staff); ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bg-white rounded-lg p-3 shadow-sm text-center user-stat-card border" style="border-radius: 15px;">
                <div class="text-muted small font-weight-bold text-uppercase mb-1">Members</div>
                <div class="h4 mb-0 font-weight-bold text-primary"><?php echo number_format($total_members); ?></div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 action-btns">
        <div class="mb-2 mb-md-0 w-100 w-md-auto">
            <button class="btn btn-primary rounded-pill shadow-sm px-4 py-2 font-weight-bold" type="button" data-toggle="collapse" data-target="#addUserCollapse" aria-expanded="false" aria-controls="addUserCollapse">
                <i class="fas fa-user-plus mr-2"></i> Add New
            </button>
            <button class="btn btn-outline-success rounded-pill shadow-sm px-4 py-2 font-weight-bold ml-2 bg-white" data-toggle="modal" data-target="#importCSVModal">
                <i class="fas fa-file-csv mr-2"></i> Import CSV
            </button>
        </div>
    </div>

    <div class="bg-white rounded-lg p-3 shadow-sm mb-4 border" style="border-radius: 15px;">
        <form method="get" action="user_management.php" class="row align-items-end mb-0">
            <div class="col-md-2 col-6 mb-2 mb-md-0">
                <label class="small font-weight-bold text-muted mb-1">Role</label>
                <select name="role" class="form-control form-control-sm rounded-pill px-3 bg-light border-0">
                    <option value="all">All Roles</option>
                    <option value="staff" <?php echo $filter_role === 'staff' ? 'selected' : ''; ?>>Staff</option>
                    <option value="member" <?php echo $filter_role === 'member' ? 'selected' : ''; ?>>Member</option>
                </select>
            </div>
            <div class="col-md-2 col-6 mb-2 mb-md-0">
                <label class="small font-weight-bold text-muted mb-1">Status</label>
                <select name="status" class="form-control form-control-sm rounded-pill px-3 bg-light border-0">
                    <option value="all">All Status</option>
                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3 col-6 mb-2 mb-md-0">
                <label class="small font-weight-bold text-muted mb-1">Added From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control form-control-sm rounded-pill px-3 bg-light border-0">
            </div>
            <div class="col-md-3 col-6 mb-2 mb-md-0">
                <label class="small font-weight-bold text-muted mb-1">Added To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control form-control-sm rounded-pill px-3 bg-light border-0">
            </div>
            <div class="col-md-2 col-12 mt-2 mt-md-0 d-flex">
                <button type="submit" class="btn btn-sm btn-info rounded-pill flex-grow-1 font-weight-bold mr-1 shadow-sm"><i class="fas fa-filter mr-1"></i> Apply</button>
                <a href="user_management.php" class="btn btn-sm btn-light rounded-pill border shadow-sm text-secondary px-3" title="Clear Filters"><i class="fas fa-redo-alt"></i></a>
            </div>
        </form>
    </div>

    <?php if (!$is_filtered && !$view_all && $total_users > 10): ?>
    <div class="alert alert-primary bg-white border-primary shadow-sm rounded-pill text-center py-2 mb-4 d-flex justify-content-center align-items-center flex-wrap" style="border-left: 4px solid #007bff !important;">
        <span class="text-dark font-weight-500 mr-md-3 mb-2 mb-md-0"><i class="fas fa-info-circle text-primary mr-1"></i> Showing the 10 most recently added users.</span>
        <a href="user_management.php?view_all=1" class="btn btn-sm btn-primary rounded-pill font-weight-bold px-4 shadow-sm">View All <?php echo number_format($total_users); ?> Users</a>
    </div>
    <?php endif; ?>

    <div class="collapse mb-4" id="addUserCollapse">
        <div class="card form-card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Register New User</h6>
            </div>
            <div class="card-body p-4">
                <form action="user_management.php" method="post" id="addUserForm">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="add_user_id" class="font-weight-500 text-dark">User ID <small>(Student/Emp Code)</small></label>
                            <input type="text" class="form-control" name="user_id" id="add_user_id" required>
                            <div id="user_id_feedback" class="invalid-feedback"></div>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="name" class="font-weight-500 text-dark">Full Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="add_email" class="font-weight-500 text-dark">Email <small>(Optional)</small></label>
                            <input type="email" class="form-control" name="email" id="add_email">
                            <div id="email_feedback" class="invalid-feedback"></div>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="role" class="font-weight-500 text-dark">Role</label>
                            <select class="form-control" name="role" id="role" required>
                                <option value="staff">Staff</option>
                                <option value="member">Member</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4" id="department-group">
                            <label for="department" class="font-weight-500 text-dark">Department</label>
                            <input type="text" class="form-control" name="department">
                        </div>
                    </div>
                    <button type="submit" name="add_user" id="add_user_btn" class="btn btn-primary rounded-pill px-4 shadow-sm mt-2">Create User</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importCSVModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold">Bulk Import Users</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="user_management.php" method="post" enctype="multipart/form-data" id="importForm">
                    <div class="modal-body p-4">
                        
                        <div id="import-step-1" class="text-center">
                            <div class="form-group text-left mb-4">
                                <label class="font-weight-bold text-dark">1. Select Target Role</label>
                                <select class="form-control rounded-pill px-3 shadow-sm border-info" name="import_role" id="import_role" required>
                                    <option value="member" selected>Members (Students, Faculty, etc.)</option>
                                    <option value="staff">Canteen Staff (Employees)</option>
                                </select>
                            </div>
                            
                            <div class="mb-4 pt-2 border-top">
                                <label class="font-weight-bold text-dark d-block text-left">2. Download Template</label>
                                <p class="text-muted small text-left mb-2">Download the blank template corresponding to the role selected above to ensure correct formatting.</p>
                                <a href="user_management.php?download_template=1&role=member" id="download_template_btn" class="btn btn-sm btn-outline-info rounded-pill font-weight-bold px-4 py-2 shadow-sm d-inline-flex align-items-center">
                                    <i class="fas fa-download mr-2"></i> Download CSV Template
                                </a>
                            </div>
                            
                            <div class="form-group text-left mb-0 pt-3 border-top">
                                <label class="font-weight-bold text-dark">3. Upload Data</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="csv_file" name="csv_file" accept=".csv" required>
                                    <label class="custom-file-label text-truncate" for="csv_file">Choose .csv file...</label>
                                </div>
                                <small class="form-text text-muted mt-2"><i class="fas fa-info-circle"></i> Password will be auto-set to match their User ID. Existing IDs will be safely ignored. Welcome emails will be queued for those with valid email addresses.</small>
                            </div>
                        </div>

                        <div id="import-step-2" class="d-none">
                            <div class="d-flex justify-content-between align-items-end mb-3">
                                <div>
                                    <h6 class="font-weight-bold text-dark mb-1">Data Preview</h6>
                                    <small class="text-muted">Found <strong id="preview-count" class="text-primary">0</strong> valid rows ready for import.</small>
                                </div>
                                <span class="badge badge-info px-3 py-2 rounded-pill" id="preview-role-badge"></span>
                            </div>
                            
                            <div class="table-responsive border rounded bg-white shadow-sm" style="max-height: 350px; overflow-y: auto;">
                                <table class="table table-sm table-striped table-hover mb-0" style="font-size: 0.9rem;">
                                    <thead class="thead-light sticky-top" id="preview-thead">
                                        </thead>
                                    <tbody id="preview-tbody">
                                        </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-light d-flex justify-content-between">
                        <div>
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4 d-none" id="btn-back">
                                <i class="fas fa-arrow-left mr-1"></i> Back
                            </button>
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-info rounded-pill px-4 shadow-sm" id="btn-preview">
                                Preview Data <i class="fas fa-arrow-right ml-1"></i>
                            </button>
                            <button type="submit" name="import_csv" class="btn btn-success rounded-pill px-4 shadow-sm d-none" id="btn-confirm">
                                <i class="fas fa-check-circle mr-1"></i> Confirm & Import
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="skeleton-container" class="row">
        <?php for($i=0; $i<8; $i++): ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4">
            <div class="skeleton-card h-100">
                <div class="skeleton skeleton-avatar"></div>
                <div class="skeleton skeleton-text-title"></div>
                <div class="skeleton skeleton-text-sub"></div>
                <div class="w-100 text-center mt-3">
                    <div class="skeleton skeleton-btn"></div>
                    <div class="skeleton skeleton-btn"></div>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="actual-content" class="row d-none">
        
        <div id="empty-search-state" class="col-12 text-center py-5 d-none w-100">
            <img src="../assets/images/no-food.svg" alt="No User" style="width: 150px; opacity: 0.5; max-width: 100%;" onerror="this.style.display='none'">
            <h5 class="font-weight-bold mt-4 text-dark">No matching users found</h5>
            <p class="text-muted">Try tweaking your search terms or adjusting your filters.</p>
        </div>

        <?php foreach ($users as $user): 
            $searchable_text = htmlspecialchars(strtolower($user['name'] . ' ' . $user['user_id'] . ' ' . $user['email'] . ' ' . $user['role'] . ' ' . ($user['department'] ?? '')));
        ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4 user-item-container" data-search-text="<?php echo $searchable_text; ?>">
            <div class="user-card h-100 p-4 d-flex flex-column align-items-center text-center">
                
                <div class="avatar-circle font-weight-bold text-uppercase">
                    <?php echo substr($user['name'], 0, 1); ?>
                </div>
                
                <h5 class="font-weight-bold mb-1 text-truncate w-100" title="<?php echo htmlspecialchars($user['name']); ?>">
                    <?php echo htmlspecialchars($user['name']); ?>
                </h5>
                <p class="text-muted small mb-2 font-weight-500">ID: <?php echo htmlspecialchars($user['user_id']); ?></p>
                
                <div class="mb-3">
                    <span class="badge badge-role <?php echo $user['role'] === 'staff' ? 'badge-info bg-info text-white' : 'badge-primary bg-primary text-white'; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    <span class="badge badge-role <?php echo $user['status'] === 'active' ? 'badge-success bg-success text-white' : 'badge-danger bg-danger text-white'; ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </div>
                
                <div class="small text-muted mb-3 text-truncate w-100">
                    <?php if (!empty($user['email'])): ?>
                        <div class="mb-1"><i class="fas fa-envelope mr-1 text-secondary"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($user['role'] === 'member'): ?>
                        <div><i class="fas fa-building mr-1 text-secondary"></i> <?php echo htmlspecialchars($user['department'] ?: 'N/A'); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mt-auto w-100 d-flex justify-content-between">
                    <button class="btn btn-sm btn-outline-info flex-grow-1 mr-1 rounded-pill font-weight-bold" data-toggle="modal" data-target="#editModal-<?php echo $user['role'] . '-' . $user['id']; ?>">Edit</button>
                    <form action="user_management.php" method="post" class="d-inline flex-grow-1 ml-1" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <input type="hidden" name="role" value="<?php echo $user['role']; ?>">
                        <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger w-100 rounded-pill font-weight-bold">Delete</button>
                    </form>
                </div>

            </div>
        </div>

        <div class="modal fade" id="editModal-<?php echo $user['role'] . '-' . $user['id']; ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content shadow-lg">
                    <div class="modal-header">
                        <h5 class="modal-title font-weight-bold">Edit User</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form action="user_management.php" method="post">
                        <div class="modal-body p-4">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <input type="hidden" name="role" value="<?php echo $user['role']; ?>">
                            
                            <div class="text-center mb-4">
                                <div class="avatar-circle font-weight-bold text-uppercase mx-auto mb-2" style="width: 80px; height: 80px; font-size: 32px;">
                                    <?php echo substr($user['name'], 0, 1); ?>
                                </div>
                                <h6 class="text-muted mb-0">ID: <?php echo htmlspecialchars($user['user_id']); ?></h6>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-500 text-dark">Full Name</label>
                                <input type="text" class="form-control rounded-pill px-3" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="font-weight-500 text-dark">Email Address</label>
                                <input type="email" class="form-control rounded-pill px-3" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="font-weight-500 text-dark">Role</label>
                                <input type="text" class="form-control rounded-pill px-3" value="<?php echo ucfirst($user['role']); ?>" readonly>
                                <small class="text-muted ml-2">Role cannot be changed directly.</small>
                            </div>
                            
                            <?php if ($user['role'] === 'member'): ?>
                            <div class="form-group">
                                <label class="font-weight-500 text-dark">Department</label>
                                <input type="text" class="form-control rounded-pill px-3" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label class="font-weight-500 text-dark">Account Status</label>
                                <select class="form-control rounded-pill px-3" name="status" required>
                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-dismiss="modal">Close</button>
                            <button type="submit" name="update_user" class="btn btn-primary rounded-pill px-4 shadow-sm">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
</div>

<script>
// ==========================================
// STRING MATCHING ALGORITHM (KMP)
// Frontend Live Search Logic
// ==========================================
function computeLPSArray(pat, M, lps) {
    let len = 0;
    let i = 1;
    lps[0] = 0;
    while (i < M) {
        if (pat.charAt(i) === pat.charAt(len)) {
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
}

function KMPSearch(pat, txt) {
    let M = pat.length;
    let N = txt.length;
    let lps = new Array(M);
    computeLPSArray(pat, M, lps);

    let i = 0; let j = 0;
    while ((N - i) >= (M - j)) {
        if (pat.charAt(j) === txt.charAt(i)) { j++; i++; }
        if (j === M) { return true; } 
        else if (i < N && pat.charAt(j) !== txt.charAt(i)) {
            if (j !== 0) { j = lps[j - 1]; } else { i++; }
        }
    }
    return false;
}

function liveSearch() {
    let input = document.getElementById('searchInput').value.toLowerCase().trim();
    let items = document.querySelectorAll('.user-item-container');
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

// ==========================================
// CSV FILE PREVIEW LOGIC
// ==========================================
function setupCSVPreview() {
    const btnPreview = document.getElementById('btn-preview');
    const btnConfirm = document.getElementById('btn-confirm');
    const btnBack = document.getElementById('btn-back');
    const step1 = document.getElementById('import-step-1');
    const step2 = document.getElementById('import-step-2');
    const fileInput = document.getElementById('csv_file');
    const roleSelect = document.getElementById('import_role');

    // Parse CSV handling quoted strings
    function parseCSVLine(text) {
        return text.split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/).map(item => item.replace(/(^"|"$)/g, '').trim());
    }

    if(btnPreview) {
        btnPreview.addEventListener('click', function() {
            if (fileInput.files.length === 0) {
                alert('Please select a CSV file to upload first.');
                return;
            }

            const role = roleSelect.value;
            const file = fileInput.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                const text = e.target.result;
                const lines = text.split(/\r?\n/).filter(line => line.trim() !== '');

                if (lines.length <= 1) {
                    alert('The selected CSV file appears to be empty or only contains headers.');
                    return;
                }

                // Prepare Headers
                const headers = role === 'staff' ? ['User ID', 'Name', 'Email'] : ['User ID', 'Name', 'Email', 'Department'];
                document.getElementById('preview-thead').innerHTML = '<tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr>';

                // Read Data Rows
                let tbodyHtml = '';
                let validRowsCount = 0;

                // Start from index 1 (skipping header row)
                for (let i = 1; i < lines.length; i++) {
                    const data = parseCSVLine(lines[i]);
                    
                    // Enforce minimum required fields (ID and Name)
                    if (data.length >= 2 && data[0] !== '' && data[1] !== '') {
                        tbodyHtml += '<tr>';
                        tbodyHtml += `<td><span class="font-weight-bold text-dark">${data[0]}</span></td>`; // User ID
                        tbodyHtml += `<td>${data[1]}</td>`; // Name
                        tbodyHtml += `<td>${data[2] || '<span class="text-muted small">N/A</span>'}</td>`; // Email
                        
                        if (role === 'member') {
                            tbodyHtml += `<td>${data[3] || '<span class="text-muted small">N/A</span>'}</td>`; // Department
                        }
                        
                        tbodyHtml += '</tr>';
                        validRowsCount++;
                    }
                }

                if (validRowsCount === 0) {
                    alert('Could not find any valid user data rows in the file. Please check the template.');
                    return;
                }

                // Update DOM
                document.getElementById('preview-tbody').innerHTML = tbodyHtml;
                document.getElementById('preview-count').innerText = validRowsCount;
                document.getElementById('preview-role-badge').innerText = role === 'staff' ? 'Staff Import' : 'Member Import';

                // Transition UI Elements
                step1.classList.add('d-none');
                step2.classList.remove('d-none');
                btnPreview.classList.add('d-none');
                btnConfirm.classList.remove('d-none');
                btnBack.classList.remove('d-none');
            };

            reader.readAsText(file);
        });
    }

    if(btnBack) {
        btnBack.addEventListener('click', function() {
            // Revert to Step 1
            step2.classList.add('d-none');
            step1.classList.remove('d-none');
            btnConfirm.classList.add('d-none');
            btnBack.classList.add('d-none');
            btnPreview.classList.remove('d-none');
        });
    }

    // Reset Modal Completely on Close
    $('#importCSVModal').on('hidden.bs.modal', function () {
        step2.classList.add('d-none');
        step1.classList.remove('d-none');
        btnConfirm.classList.add('d-none');
        btnBack.classList.add('d-none');
        btnPreview.classList.remove('d-none');
        
        fileInput.value = ''; // Clear file input
        const fileLabel = document.querySelector('.custom-file-label');
        if(fileLabel) fileLabel.innerText = 'Choose .csv file...';
        
        document.getElementById('preview-tbody').innerHTML = '';
    });
}

// ==========================================
// UI Initialization Logic & Validation
// ==========================================
document.addEventListener("DOMContentLoaded", function() {
    
    // Skeleton Loader Dismissal
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

    // Form Department Toggle
    const roleSelect = document.getElementById('role');
    const departmentGroup = document.getElementById('department-group');

    function toggleDepartment() {
        if (roleSelect.value === 'staff') {
            departmentGroup.style.display = 'none';
        } else {
            departmentGroup.style.display = 'block';
        }
    }

    if(roleSelect) {
        toggleDepartment(); 
        roleSelect.addEventListener('change', toggleDepartment);
    }
    
    // Dynamic Modal Template Link Update
    const importRoleSelect = document.getElementById('import_role');
    const downloadTemplateBtn = document.getElementById('download_template_btn');
    
    if(importRoleSelect && downloadTemplateBtn) {
        importRoleSelect.addEventListener('change', function() {
            downloadTemplateBtn.href = 'user_management.php?download_template=1&role=' + this.value;
        });
    }
    
    // Custom File Input Label Update (Vanilla JS)
    const csvInput = document.getElementById('csv_file');
    if(csvInput) {
        csvInput.addEventListener('change', function(e) {
            if(e.target.files.length > 0) {
                var fileName = e.target.files[0].name;
                var nextSibling = e.target.nextElementSibling;
                nextSibling.innerText = fileName;
            }
        });
    }

    // Initialize the CSV Preview Module
    setupCSVPreview();
    
    // ==========================================
    // REAL-TIME DUPLICATE CHECKER (AJAX)
    // ==========================================
    const addUserIdInput = document.getElementById('add_user_id');
    const addEmailInput = document.getElementById('add_email');
    const submitBtn = document.getElementById('add_user_btn');
    const userIdFeedback = document.getElementById('user_id_feedback');
    const emailFeedback = document.getElementById('email_feedback');

    let userIdValid = true;
    let emailValid = true;

    function updateSubmitButton() {
        if (userIdValid && emailValid) {
            submitBtn.removeAttribute('disabled');
        } else {
            submitBtn.setAttribute('disabled', 'true');
        }
    }

    function checkDuplicate(field, value, feedbackElement, inputElement, validationFlagSetter) {
        if (!value.trim()) {
            inputElement.classList.remove('is-invalid');
            feedbackElement.innerText = '';
            validationFlagSetter(true);
            updateSubmitButton();
            return;
        }

        const formData = new FormData();
        formData.append('action', 'check_duplicate');
        formData.append('field', field);
        formData.append('value', value);

        fetch('user_management.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                inputElement.classList.add('is-invalid');
                feedbackElement.innerText = data.message;
                validationFlagSetter(false);
            } else {
                inputElement.classList.remove('is-invalid');
                inputElement.classList.add('is-valid');
                feedbackElement.innerText = '';
                validationFlagSetter(true);
            }
            updateSubmitButton();
        })
        .catch(error => console.error('Error:', error));
    }

    let timeoutIdUser;
    if (addUserIdInput) {
        addUserIdInput.addEventListener('input', function() {
            this.classList.remove('is-valid');
            clearTimeout(timeoutIdUser);
            timeoutIdUser = setTimeout(() => {
                checkDuplicate('user_id', this.value, userIdFeedback, addUserIdInput, (val) => userIdValid = val);
            }, 500); // 500ms debounce
        });
    }

    let timeoutIdEmail;
    if (addEmailInput) {
        addEmailInput.addEventListener('input', function() {
            this.classList.remove('is-valid');
            clearTimeout(timeoutIdEmail);
            timeoutIdEmail = setTimeout(() => {
                checkDuplicate('email', this.value, emailFeedback, addEmailInput, (val) => emailValid = val);
            }, 500); // 500ms debounce
        });
    }

    // ==========================================
    // ASYNCHRONOUS EMAIL QUEUE TRIGGER
    // ==========================================
    // This calls the background worker script safely 1 second after the page finishes loading.
    // It processes emails quietly without blocking the main thread or page reload.
    setTimeout(() => {
        fetch('../includes/process_email_queue.php').catch(e => console.error("Queue process background check failed:", e));
    }, 1000);
});
</script>

<?php include 'footer.php'; ?>