<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'subjects' => []]);
    exit();
}

$group_number = $_GET['group_number'] ?? '';

if (empty($group_number)) {
    echo json_encode(['success' => false, 'message' => 'Group number is required', 'subjects' => []]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT DISTINCT student_code FROM students WHERE group_number = ? AND student_code IS NOT NULL AND student_code != '' ORDER BY student_code");
    $stmt->execute([$group_number]);
    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'subjects' => $subjects
    ]);

} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database error in get_subjects_by_group.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error', 'subjects' => []]);
} catch (Exception $e) {
    // Log the error for debugging
    error_log("General error in get_subjects_by_group.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred', 'subjects' => []]);
}
?>