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
    // Get all students without assigned codes
    $stmt = $pdo->query("SELECT id, group_number FROM students WHERE assigned_code IS NULL OR assigned_code = '' ORDER BY group_number, id");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        echo json_encode([
            'success' => true,
            'message' => 'Bütün tələbələrə artıq kod təyin edilib',
            'assigned_count' => 0
        ]);
        exit();
    }

    $assigned_count = 0;
    $group_counters = []; // Track last code per group

    foreach ($students as $student) {
        $student_id = $student['id'];
        $group_number = $student['group_number'];

        // Extract group prefix from group_number
        $group_prefix = preg_replace('/[^0-9]/', '', $group_number);

        if (empty($group_prefix)) {
            $group_prefix = '1001';
        }

        // Check if we already have a counter for this group in this batch
        if (!isset($group_counters[$group_prefix])) {
            // Get the last assigned code for this group from database
            $stmt = $pdo->prepare("SELECT assigned_code FROM students WHERE assigned_code LIKE ? ORDER BY assigned_code DESC LIMIT 1");
            $stmt->execute([$group_prefix . '%']);
            $last_assigned = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$last_assigned || empty($last_assigned['assigned_code'])) {
                // First code for this group
                $group_counters[$group_prefix] = [
                    'prefix' => $group_prefix,
                    'letter' => 'a',
                    'number' => 1
                ];
            } else {
                // Parse existing code
                if (preg_match('/^(\d+)([a-z])(\d+)$/i', $last_assigned['assigned_code'], $matches)) {
                    $group_counters[$group_prefix] = [
                        'prefix' => $matches[1],
                        'letter' => strtolower($matches[2]),
                        'number' => (int)$matches[3]
                    ];
                } else {
                    $group_counters[$group_prefix] = [
                        'prefix' => $group_prefix,
                        'letter' => 'a',
                        'number' => 1
                    ];
                }
            }
        }

        // Increment counter
        $group_counters[$group_prefix]['number']++;

        // Check overflow
        if ($group_counters[$group_prefix]['number'] > 999) {
            $group_counters[$group_prefix]['number'] = 1;
            $group_counters[$group_prefix]['letter'] = chr(ord($group_counters[$group_prefix]['letter']) + 1);

            if ($group_counters[$group_prefix]['letter'] > 'z') {
                $group_counters[$group_prefix]['letter'] = 'a';
            }
        }

        // Generate new code
        $new_code = sprintf(
            "%s%s%03d",
            $group_counters[$group_prefix]['prefix'],
            $group_counters[$group_prefix]['letter'],
            $group_counters[$group_prefix]['number']
        );

        // Assign code to student
        $stmt = $pdo->prepare("UPDATE students SET assigned_code = ? WHERE id = ?");
        $stmt->execute([$new_code, $student_id]);

        $assigned_count++;
    }

    // Clear relevant caches
    cache_delete('dashboard_stats');
    cache_delete('user_page_stats');
    cache_delete('filter_subjects');

    echo json_encode([
        'success' => true,
        'message' => "$assigned_count tələbəyə kod təyin edildi",
        'assigned_count' => $assigned_count
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
