<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'assignments' => []]);
    exit();
}

$user_id = $_GET['user_id'] ?? 0;

if (empty($user_id)) {
    echo json_encode(['success' => false, 'message' => 'User ID is required', 'assignments' => []]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, group_number, subject_code FROM teacher_group_subjects WHERE teacher_id = ? ORDER BY group_number, subject_code");
    $stmt->execute([$user_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'assignments' => $assignments
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_teacher_group_subjects.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error', 'assignments' => []]);
} catch (Exception $e) {
    error_log("General error in get_teacher_group_subjects.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred', 'assignments' => []]);
}
?>