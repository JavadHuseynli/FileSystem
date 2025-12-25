<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

// Check if teacher is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true || $_SESSION['admin_role'] !== 'teacher') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: user.php');
    exit();
}

$student_id = $_POST['student_id'] ?? 0;

if (!$student_id) {
    $_SESSION['error_message'] = 'Tələbə ID-si tapılmadı';
    header('Location: user.php');
    exit();
}

try {
    $teacher_id = $_SESSION['admin_id'];

    // Save grades for each work (1-3)
    for ($i = 1; $i <= 3; $i++) {
        $grade = trim($_POST["grade_$i"] ?? '');
        $comment = trim($_POST["comment_$i"] ?? '');

        // Check if this grade already exists
        $stmt = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND work_number = ?");
        $stmt->execute([$student_id, $i]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing grade
            if (!empty($grade) || !empty($comment)) {
                $stmt = $pdo->prepare("UPDATE grades SET grade = ?, comment = ?, teacher_id = ?, updated_at = CURRENT_TIMESTAMP WHERE student_id = ? AND work_number = ?");
                $stmt->execute([$grade, $comment, $teacher_id, $student_id, $i]);
            } else {
                // Delete if both are empty
                $stmt = $pdo->prepare("DELETE FROM grades WHERE student_id = ? AND work_number = ?");
                $stmt->execute([$student_id, $i]);
            }
        } else {
            // Insert new grade only if at least one field is filled
            if (!empty($grade) || !empty($comment)) {
                $stmt = $pdo->prepare("INSERT INTO grades (student_id, teacher_id, work_number, grade, comment) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $teacher_id, $i, $grade, $comment]);
            }
        }
    }

    $_SESSION['success_message'] = 'Qiymətlər uğurla yadda saxlanıldı!';

} catch (PDOException $e) {
    handleDatabaseError($e);
    $_SESSION['error_message'] = 'Qiymətlər yadda saxlanılarkən xəta baş verdi.';
}

header('Location: user.php');
exit();
?>
