<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    echo json_encode(['success' => false, 'subjects' => []]);
    exit();
}

$user_id = $_GET['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT subject_code FROM teacher_subjects WHERE teacher_id = ?");
    $stmt->execute([$user_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'subjects' => $subjects
    ]);

} catch (PDOException $e) {
    handleDatabaseError($e);
    echo json_encode(['success' => false, 'subjects' => []]);
}
?>
