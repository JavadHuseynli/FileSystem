<?php
// Database Backup System

function createDatabaseBackup($pdo, $backup_dir = null, $delete_student_data = false) {
    if (!$backup_dir) {
        $backup_dir = __DIR__ . '/../backups';
    }

    // Create backups directory if not exists
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = $backup_dir . '/backup_' . $timestamp . '.sql';

    // Delete student data if requested
    if ($delete_student_data) {
        deleteStudentData($pdo);
    }

    try {
        $backup_content = "-- Database Backup\n";
        $backup_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        // Get all tables
        $tables = ['students', 'uploads', 'admin_users', 'permissions', 'user_permissions', 'teacher_subjects', 'grades'];

        foreach ($tables as $table) {
            // Get table structure
            $create_table = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'")->fetch(PDO::FETCH_ASSOC);

            if ($create_table) {
                $backup_content .= "\n-- Table: $table\n";
                $backup_content .= "DROP TABLE IF EXISTS $table;\n";
                $backup_content .= $create_table['sql'] . ";\n\n";

                // Get table data
                $rows = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);

                if (count($rows) > 0) {
                    $backup_content .= "-- Data for table: $table\n";

                    foreach ($rows as $row) {
                        $columns = array_keys($row);
                        $values = array_map(function($val) use ($pdo) {
                            return $val === null ? 'NULL' : $pdo->quote($val);
                        }, array_values($row));

                        $backup_content .= "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $backup_content .= "\n";
                }
            }
        }

        file_put_contents($backup_file, $backup_content);

        // Keep only last 10 backups
        cleanOldBackups($backup_dir, 10);

        return $backup_file;
    } catch (Exception $e) {
        error_log("Backup failed: " . $e->getMessage());
        return false;
    }
}

function cleanOldBackups($backup_dir, $keep = 10) {
    $backups = glob($backup_dir . '/backup_*.sql');

    if (count($backups) > $keep) {
        // Sort by modification time
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Delete old backups
        for ($i = $keep; $i < count($backups); $i++) {
            unlink($backups[$i]);
        }
    }
}

function restoreBackup($pdo, $backup_file) {
    if (!file_exists($backup_file)) {
        return false;
    }

    try {
        $sql = file_get_contents($backup_file);
        $pdo->exec($sql);
        return true;
    } catch (Exception $e) {
        error_log("Restore failed: " . $e->getMessage());
        return false;
    }
}

function getAvailableBackups($backup_dir = null) {
    if (!$backup_dir) {
        $backup_dir = __DIR__ . '/../backups';
    }

    $backups = glob($backup_dir . '/backup_*.sql');
    $result = [];

    foreach ($backups as $backup) {
        $result[] = [
            'filename' => basename($backup),
            'path' => $backup,
            'size' => filesize($backup),
            'date' => filemtime($backup)
        ];
    }

    // Sort by date (newest first)
    usort($result, function($a, $b) {
        return $b['date'] - $a['date'];
    });

    return $result;
}

// Auto-backup every day
function autoBackup($pdo) {
    $last_backup_file = __DIR__ . '/../backups/.last_backup';
    $today = date('Y-m-d');

    if (!file_exists($last_backup_file) || file_get_contents($last_backup_file) !== $today) {
        $result = createDatabaseBackup($pdo);
        if ($result) {
            file_put_contents($last_backup_file, $today);
        }
        return $result;
    }

    return null;
}

// Delete all student data
function deleteStudentData($pdo) {
    try {
        // Delete all uploaded files
        $uploads_dir = __DIR__ . '/../uploads';
        if (is_dir($uploads_dir)) {
            $folders = scandir($uploads_dir);
            foreach ($folders as $folder) {
                if ($folder !== '.' && $folder !== '..' && is_dir($uploads_dir . '/' . $folder)) {
                    // Delete all files in folder
                    $files = scandir($uploads_dir . '/' . $folder);
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..') {
                            unlink($uploads_dir . '/' . $folder . '/' . $file);
                        }
                    }
                    // Delete folder
                    rmdir($uploads_dir . '/' . $folder);
                }
            }
        }

        // Delete database records
        $pdo->exec("DELETE FROM grades");
        $pdo->exec("DELETE FROM uploads");
        $pdo->exec("DELETE FROM students");

        // Clear all relevant caches
        if (function_exists('cache_delete')) {
            $cache_dir = __DIR__ . '/../cache';
            if (is_dir($cache_dir)) {
                $cache_files = glob($cache_dir . '/*');
                foreach ($cache_files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Delete student data failed: " . $e->getMessage());
        return false;
    }
}
?>
