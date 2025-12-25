<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

if (!isset($_SESSION['student_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$file_id = $data['file_id'] ?? 0;

try {
    // Fayl məlumatlarını al
    $stmt = $pdo->prepare("SELECT * FROM uploads WHERE id = ? AND student_id = ?");
    $stmt->execute([$file_id, $_SESSION['student_id']]);
    $file = $stmt->fetch();
    
    if ($file) {
        // Fiziki faylı sil
        $file_path = 'uploads/' . $file['folder_path'] . '/' . $file['file_name'];
        if (file_exists($file_path)) {
            unlink($file_path); // Faylı sil
            
            // Qovluğun yolunu al
            $folder_path = 'uploads/' . $file['folder_path'];
            
            // Bazadan faylı sil
            $stmt = $pdo->prepare("DELETE FROM uploads WHERE id = ?");
            $stmt->execute([$file_id]);
            
            // Qovluqda başqa fayl var mı yoxla
            $remaining_files = glob($folder_path . "/*");
            if (empty($remaining_files)) {
                // Qovluq boşdursa sil
                rmdir($folder_path);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Fayl və qovluq silindi'
            ]);
        } else {
            // Fayl fiziki olaraq yoxdursa, sadəcə bazadan sil
            $stmt = $pdo->prepare("DELETE FROM uploads WHERE id = ?");
            $stmt->execute([$file_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Fayl bazadan silindi'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Fayl tapılmadı'
        ]);
    }
} catch (PDOException $e) {
    handleDatabaseError($e);
    echo json_encode(['success' => false, 'message' => 'Verilənlər bazası xətası.']);
} catch (Exception $e) {
    logError($e);
    echo json_encode([
        'success' => false, 
        'message' => 'Gözlənilməyən xəta baş verdi.'
    ]);
}
?>