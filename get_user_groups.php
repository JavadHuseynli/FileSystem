<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    echo json_encode(['success' => false, 'groups' => []]);
    exit();
}

$user_id = $_GET['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT group_number FROM teacher_groups WHERE teacher_id = ?");
    $stmt->execute([$user_id]);
    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'groups' => $groups
    ]);

} catch (PDOException $e) {
    handleDatabaseError($e);
    echo json_encode(['success' => false, 'groups' => []]);
}
?>