<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || empty($_SESSION["role"]) || empty($_SESSION["id"]) || empty($_SESSION["session_token"])) {
    echo json_encode(['status' => 'logged_out']);
    exit;
}

$role = $_SESSION['role'];
$table = '';
switch ($role) {
    case 'member': $table = 'members'; break;
    case 'manager': $table = 'managers'; break;
    case 'staff': $table = 'canteen_staff'; break;
    case 'super_admin': $table = 'super_admins'; break;
    default: echo json_encode(['status' => 'logged_out']); exit;
}

try {
    $stmt_token = $pdo->prepare("SELECT session_token FROM {$table} WHERE id = ?");
    $stmt_token->execute([$_SESSION['id']]);
    $db_token = $stmt_token->fetchColumn();

    if ($db_token !== $_SESSION['session_token']) {
        // DO NOT DESTROY SESSION HERE. Warn the frontend to show the choice modal.
        echo json_encode(['status' => 'multiple_login']);
        exit;
    }

    echo json_encode(['status' => 'active']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error']);
}
?>