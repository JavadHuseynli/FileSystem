<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if teacher is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true || $_SESSION['admin_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Giriş icazəniz yoxdur']);
    exit();
}

$student_id = $_GET['student_id'] ?? 0;

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Tələbə ID-si tapılmadı']);
    exit();
}

try {
    // Get all grades for this student
    $stmt = $pdo->prepare("SELECT * FROM grades WHERE student_id = ? ORDER BY work_number ASC");
    $stmt->execute([$student_id]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure we have 3 entries (even if null)
    $result = [null, null, null];
    foreach ($grades as $grade) {
        $index = $grade['work_number'] - 1;
        if ($index >= 0 && $index < 3) {
            $result[$index] = $grade;
        }
    }

    echo json_encode([
        'success' => true,
        'grades' => $result
    ]);

} catch (PDOException $e) {
    handleDatabaseError($e);
    echo json_encode(['success' => false, 'message' => 'Verilənlər bazası xətası']);
}
?>
