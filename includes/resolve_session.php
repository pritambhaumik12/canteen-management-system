<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || empty($_POST['action'])) {
    echo json_encode(['success' => false]);
    exit;
}

$action = $_POST['action'];

if ($action === 'logout') {
    // User chose to logout (or timer ran out)
    session_unset();
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
} elseif ($action === 'keep') {
    // User chose to kick the other device out and stay here
    $role = $_SESSION['role'];
    $table = '';
    switch ($role) {
        case 'member': $table = 'members'; break;
        case 'manager': $table = 'managers'; break;
        case 'staff': $table = 'canteen_staff'; break;
        case 'super_admin': $table = 'super_admins'; break;
        default: echo json_encode(['success' => false]); exit;
    }

    // Generate a fresh session token for this device
    $new_token = bin2hex(random_bytes(32));
    $_SESSION['session_token'] = $new_token;

    // Save the new token in the database to lock out the other device
    $stmt = $pdo->prepare("UPDATE {$table} SET session_token = ? WHERE id = ?");
    $stmt->execute([$new_token, $_SESSION['id']]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false]);
?>