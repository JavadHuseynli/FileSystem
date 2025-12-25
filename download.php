<?php // download.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    exit('İcazə yoxdur!');
}

// Get user's assigned subjects if teacher
$user_subjects = [];
if ($_SESSION['admin_role'] === 'teacher') {
    $stmt = $pdo->prepare("SELECT subject_code FROM teacher_subjects WHERE teacher_id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $user_subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// UTF-8 kodlaşdırmasını təyin et
header('Content-Type: text/html; charset=utf-8');

if (isset($_POST['download_all'])) {
    // Temp qovluğunu yarat
    if (!file_exists('temp')) {
        mkdir('temp', 0777, true);
    }

    $date = date('Y-m-d_H-i-s');
    $zip_name = "butun_telebeler_" . $date . ".zip";
    $zip_path = "temp/" . $zip_name;

    // ZIP kitabxanası ilə bütün qovluğu zip et
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        // Bütün tələbələri və onların qovluqlarını götür (müəllim üçün filtr)
        if ($_SESSION['admin_role'] === 'teacher' && !empty($user_subjects)) {
            $placeholders = str_repeat('?,', count($user_subjects) - 1) . '?';
            $stmt = $pdo->prepare("SELECT DISTINCT s.id, s.assigned_code, u.folder_path
                                 FROM students s
                                 INNER JOIN uploads u ON s.id = u.student_id
                                 WHERE u.folder_path IS NOT NULL AND s.student_code IN ($placeholders)");
            $stmt->execute($user_subjects);
        } elseif ($_SESSION['admin_role'] === 'teacher') {
            // Teacher has no subjects
            $students_folders = [];
        } else {
            $stmt = $pdo->query("SELECT DISTINCT s.id, s.assigned_code, u.folder_path
                                 FROM students s
                                 INNER JOIN uploads u ON s.id = u.student_id
                                 WHERE u.folder_path IS NOT NULL");
        }

        if (isset($stmt)) {
            $students_folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $uploads_dir = 'uploads/';
        foreach ($students_folders as $student) {
            $real_folder = $student['folder_path'];
            // ZIP-də istifadə olunacaq qovluq adı - şifrələnmiş kod varsa onu istifadə et
            $zip_folder_name = !empty($student['assigned_code']) ? $student['assigned_code'] : $real_folder;

            if (is_dir($uploads_dir . $real_folder)) {
                $files = scandir($uploads_dir . $real_folder);
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..' && is_file($uploads_dir . $real_folder . '/' . $file)) {
                        // ZIP-də şifrələnmiş kod ilə qovluq adı istifadə et
                        $zip->addFile($uploads_dir . $real_folder . '/' . $file, $zip_folder_name . '/' . $file);
                    }
                }
            }
        }
        $zip->close();
        
        if (file_exists($zip_path)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_name . '"');
            header('Content-Length: ' . filesize($zip_path));
            readfile($zip_path);
            unlink($zip_path); // Müvəqqəti faylı sil
            exit;
        } else {
            echo "Zip faylı yaradılarkən xəta baş verdi!";
        }
    } else {
        echo "Zip arxivi açılarkən xəta baş verdi!";
    }
}

// Tək qovluq üçün
if (isset($_POST['folder_path'])) {
    // İlk öncə, göndərilən qovluğun adı ilə qovluğu tapmağa çalışaq
    $requested_folder = $_POST['folder_path'];
    $student_id = $_POST['student_id'] ?? 0;

    // Check if teacher has access to this student's subject
    if ($_SESSION['admin_role'] === 'teacher' && $student_id) {
        $stmt = $pdo->prepare("SELECT student_code FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $student_code = $stmt->fetchColumn();

        if (!in_array($student_code, $user_subjects)) {
            exit('Bu tələbənin fayllarına çıxış icazəniz yoxdur!');
        }
    }

    $folder_found = false;
    $actual_folder_path = '';

    // Bütün mövcud qovluqları yoxlayaq
    if (is_dir('uploads')) {
        $all_folders = scandir('uploads');
        foreach ($all_folders as $folder) {
            if ($folder !== '.' && $folder !== '..' && is_dir('uploads/' . $folder)) {
                // Əgər qovluğun adı dəqiq uyğun gəlirsə və ya UTF-8 kodlaşdırma fərqləri varsa
                if ($folder === $requested_folder || mb_strtolower($folder, 'UTF-8') === mb_strtolower($requested_folder, 'UTF-8')) {
                    $folder_found = true;
                    $actual_folder_path = 'uploads/' . $folder;
                    break;
                }
            }
        }
    }

    // Əgər qovluq tapılmayıbsa, yenidən normalaşdırılmış adla axtarış edək
    if (!$folder_found) {
        if (is_dir('uploads')) {
            $all_folders = scandir('uploads');
            foreach ($all_folders as $folder) {
                if ($folder !== '.' && $folder !== '..' && is_dir('uploads/' . $folder)) {
                    // Hər iki adı müqayisə etmək üçün simvolları və boşluqları təmizləyək
                    $cleaned_folder = preg_replace('/[^a-zA-Z0-9]/', '', $folder);
                    $cleaned_requested = preg_replace('/[^a-zA-Z0-9]/', '', $requested_folder);

                    if ($cleaned_folder === $cleaned_requested ||
                        mb_strtolower($cleaned_folder, 'UTF-8') === mb_strtolower($cleaned_requested, 'UTF-8')) {
                        $folder_found = true;
                        $actual_folder_path = 'uploads/' . $folder;
                        break;
                    }
                }
            }
        }
    }

    if ($folder_found) {
        // Şifrələnmiş kodu əldə et
        $zip_name_prefix = $requested_folder;
        if ($student_id) {
            $stmt = $pdo->prepare("SELECT assigned_code FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($student && !empty($student['assigned_code'])) {
                $zip_name_prefix = $student['assigned_code'];
            }
        }

        $zip_name = preg_replace('/[^\p{L}\p{N}_]/u', '_', $zip_name_prefix) . '_files.zip';
        $zip_path = 'temp/' . $zip_name;
        
        if (!file_exists('temp')) {
            mkdir('temp', 0777, true);
        }
        
        // ZIP kitabxanası ilə tək qovluğu zip et
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = scandir($actual_folder_path);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..' && is_file($actual_folder_path . '/' . $file)) {
                    // ZIP-də şifrələnmiş kod ilə qovluq adı istifadə et
                    $zip->addFile($actual_folder_path . '/' . $file, $zip_name_prefix . '/' . $file);
                }
            }
            $zip->close();
            
            if (file_exists($zip_path)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zip_name . '"');
                header('Content-Length: ' . filesize($zip_path));
                readfile($zip_path);
                unlink($zip_path); // Müvəqqəti faylı sil
                exit;
            } else {
                echo "Zip faylı yaradılarkən xəta baş verdi!";
            }
        } else {
            echo "Zip arxivi açılarkən xəta baş verdi!";
        }
    } else {
        echo "Qovluq tapılmadı: uploads/" . htmlspecialchars($requested_folder);
        echo "<br><br>Mövcud qovluqlar 'uploads' içində:<br>";
        if (is_dir('uploads')) {
            $all_folders = scandir('uploads');
            echo "<pre>";
            print_r($all_folders);
            echo "</pre>";
        }
    }
}
?>