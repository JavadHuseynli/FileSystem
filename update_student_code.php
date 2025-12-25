<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'Giriş icazəniz yoxdur'
    ]);
    exit();
}

try {
    $student_id = $_POST['student_id'] ?? 0;

    if (!$student_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Tələbə ID-si tapılmadı'
        ]);
        exit();
    }

    // Get student's group number
    $stmt = $pdo->prepare("SELECT group_number FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode([
            'success' => false,
            'message' => 'Tələbə tapılmadı'
        ]);
        exit();
    }

    // Extract group prefix from group_number (e.g., "A-101" -> "101")
    $group_number = $student['group_number'];
    $group_prefix = preg_replace('/[^0-9]/', '', $group_number); // Extract numbers only

    if (empty($group_prefix)) {
        $group_prefix = '1001'; // Default if no numbers found
    }

    // Get the last assigned code with the same group prefix
    $stmt = $pdo->prepare("SELECT assigned_code FROM students WHERE assigned_code LIKE ? ORDER BY assigned_code DESC LIMIT 1");
    $stmt->execute([$group_prefix . '%']);
    $last_assigned = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last_assigned || empty($last_assigned['assigned_code'])) {
        // First code for this group - start with {group}a001
        $new_code = $group_prefix . 'a001';
    } else {
        $last_code = $last_assigned['assigned_code'];

        // Parse the code: "1002a001" -> group: 1002, letter: a, number: 001
        if (preg_match('/^(\d+)([a-z])(\d+)$/i', $last_code, $matches)) {
            $prefix = $matches[1];  // 1002
            $letter = strtolower($matches[2]); // a
            $number = (int)$matches[3]; // 001

            // Increment the number
            $number++;

            // If number exceeds 999, increment letter
            if ($number > 999) {
                $number = 1;
                $letter = chr(ord($letter) + 1);

                // If letter exceeds z, start over with 'a'
                if ($letter > 'z') {
                    $letter = 'a';
                }
            }

            // Format the new code (no spaces, lowercase letter)
            $new_code = sprintf("%s%s%03d", $prefix, $letter, $number);
        } else {
            // Fallback if format is unexpected
            $new_code = $group_prefix . 'a001';
        }
    }

    // Update the student's assigned_code (şifrələnmiş kod)
    $stmt = $pdo->prepare("UPDATE students SET assigned_code = ? WHERE id = ?");
    $stmt->execute([$new_code, $student_id]);

    // Clear relevant caches
    cache_delete('dashboard_stats');
    cache_delete('user_page_stats');
    cache_delete('filter_subjects');

    echo json_encode([
        'success' => true,
        'code' => $new_code
    ]);

} catch (PDOException $e) {
    handleDatabaseError($e);
    echo json_encode([
        'success' => false,
        'message' => 'Verilənlər bazası xətası'
    ]);
} catch (Exception $e) {
    handleGeneralError($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Xəta baş verdi'
    ]);
}
?>
