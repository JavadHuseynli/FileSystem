<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';
require_once 'includes/permissions.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_GET['user_id'] ?? 0;

if ($user_id > 0) {
    $permissions = getUserPermissions($pdo, $user_id);
    echo json_encode(['permissions' => $permissions]);
} else {
    echo json_encode(['error' => 'Invalid user ID']);
}
?>
