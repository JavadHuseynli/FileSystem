<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Get the last student code from database
    $stmt = $pdo->query("SELECT student_code FROM students ORDER BY id DESC LIMIT 1");
    $last_student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last_student) {
        // First student - start with 1001 A 001
        $new_code = "1001 A 001";
    } else {
        $last_code = $last_student['student_code'];

        // Parse the code: "1002 A 001" -> [1002, A, 001]
        $parts = preg_split('/\s+/', trim($last_code));

        if (count($parts) === 3) {
            $prefix = $parts[0];      // 1002
            $letter = $parts[1];      // A
            $number = (int)$parts[2]; // 001

            // Increment the number
            $number++;

            // If number exceeds 999, increment letter
            if ($number > 999) {
                $number = 1;
                $letter = chr(ord($letter) + 1);

                // If letter exceeds Z, increment prefix
                if ($letter > 'Z') {
                    $letter = 'A';
                    $prefix = (int)$prefix + 1;
                }
            }

            // Format the new code
            $new_code = sprintf("%s %s %03d", $prefix, $letter, $number);
        } else {
            // Fallback if format is unexpected
            $new_code = "1001 A 001";
        }
    }

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
