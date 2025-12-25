<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: index.php'); // Redirect to new login page
    exit();
}

try {
    // Count existing files
    $stmt = $pdo->prepare("SELECT COUNT(*) as file_count FROM uploads WHERE student_id = ?");
    $stmt->execute([$_SESSION['student_id']]);
    $result = $stmt->fetch();
    $current_file_count = $result['file_count'];
    $remaining_slots = 3 - $current_file_count;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
        $response = ['success' => false, 'message' => ''];

        // Check file count
        $new_files_count = count(array_filter($_FILES['files']['name']));
        if ($new_files_count > $remaining_slots) {
            $response['message'] = "Maksimum {$remaining_slots} fayl daha yükləyə bilərsiniz.";
            echo json_encode($response);
            exit();
        }

        // Create folder name
        $folder_name = $_SESSION['student_code'] . '_' .
                      $_SESSION['full_name'] . '_' .
                      mb_strtoupper($_SESSION['group_number'], 'UTF-8');
        $folder_name = preg_replace('/[^\p{L}\p{N}_]/u', '_', $folder_name);

        $base_upload_dir = 'uploads/';
        $student_dir = $base_upload_dir . $folder_name . '/';
        if (!file_exists($student_dir)) {
            mkdir($student_dir, 0777, true);
        }

        $allowed = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'psd'];
        $files_uploaded = 0;

        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            if (empty($tmp_name)) continue;

            $file_name = $_FILES['files']['name'][$key];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed)) {
                $response['message'] = "Yalnız Word, Excel, PowerPoint və PSD faylları qəbul edilir. '{$file_name}' yüklənmədi.";
                echo json_encode($response);
                exit();
            }

            $new_filename = time() . '_' . uniqid() . '.' . $file_ext;

            if (move_uploaded_file($tmp_name, $student_dir . $new_filename)) {
                $stmt = $pdo->prepare("INSERT INTO uploads (student_id, file_name, original_name, file_type, folder_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['student_id'],
                    $new_filename,
                    $file_name,
                    $file_ext,
                    $folder_name
                ]);
                $files_uploaded++;
            }
        }

        if ($files_uploaded > 0) {
            // Clear relevant caches
            cache_delete('dashboard_stats');
            cache_delete('dashboard_recent_uploads');
            cache_delete('dashboard_uploads_by_subject');
            cache_delete('user_page_stats');
            $response['success'] = true;
            $response['message'] = "{$files_uploaded} fayl uğurla yükləndi!";
        } else if (empty($response['message'])) {
            $response['message'] = 'Fayl yüklənmədi. Zəhmət olmasa yenidən cəhd edin.';
        }

        echo json_encode($response);
        exit();
    }

    // Get existing files
    $stmt = $pdo->prepare("SELECT * FROM uploads WHERE student_id = ? ORDER BY upload_date DESC");
    $stmt->execute([$_SESSION['student_id']]);
    $uploads = $stmt->fetchAll();

} catch (PDOException $e) {
    handleDatabaseError($e);
    $uploads = [];
    $current_file_count = 0;
    $page_error = "Verilənlər bazası ilə əlaqədar xəta baş verdi.";
} catch (Exception $e) {
    logError($e);
    $uploads = [];
    $current_file_count = 0;
    $page_error = "Gözlənilməyən bir xəta baş verdi.";
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tapşırıq Yükləmə</title>
    <link rel="stylesheet" href="admin_modern.css">
    <style>
        body {
            background: #f0f2f5;
        }
        .main-content { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .profile-card, .upload-card, .files-card { 
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px; 
        }
        h3 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2d3748;
        }
        .profile-details p { margin: 0 0 10px; color: #4a5568; }
        .profile-details p:last-child { margin-bottom: 0; }

        .file-table { width: 100%; border-collapse: collapse; }
        .file-table th, .file-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .file-table th { background-color: #f7fafc; font-weight: 600; color: #4a5568; }
        .file-table td { color: #2d3748; }
        
        .delete-btn { background: #e53e3e; color: white; padding: 8px 12px; border-radius: 8px; cursor: pointer; border: none; font-weight: 500; }
        .delete-btn:hover { background: #c53030; }
        
        .upload-container { border: 3px dashed #cbd5e0; border-radius: 12px; padding: 40px; text-align: center; cursor: pointer; transition: all 0.3s ease; background-color: #f7fafc; }
        .upload-container.highlight { border-color: #667eea; background-color: #ebf4ff; }
        
        .progress { height: 10px; background-color: #e2e8f0; border-radius: 5px; overflow: hidden; margin-top: 20px; display: none; }
        .progress-bar { height: 100%; background-color: #667eea; width: 0%; transition: width 0.4s ease; }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .alert-info { color: #31708f; background-color: #d9edf7; border-color: #bce8f1; }
        .alert-danger { color: #a94442; background-color: #f2dede; border-color: #ebccd1; }
        .alert-success { color: #3c763d; background-color: #dff0d8; border-color: #d6e9c6; }
        .logout-btn {
            display: inline-block;
            text-decoration: none;
            background: #718096;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background 0.3s;
        }
        .logout-btn:hover { background: #4a5568; }
    </style>
</head>
<body>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="font-size: 28px;">Tapşırıq Yükləmə Paneli</h1>
        <a href="logout.php" class="logout-btn">Çıxış</a>
    </div>

    <div class="profile-card">
        <h3>Sizin Məlumatlar</h3>
        <div class="profile-details">
            <p><strong>Fənn:</strong> <?php echo htmlspecialchars(mb_strtoupper($_SESSION['student_code'], 'UTF-8')); ?></p>
            <p><strong>Ad Soyad:</strong> <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
            <p><strong>Qrup:</strong> <?php echo htmlspecialchars(mb_strtoupper($_SESSION['group_number'], 'UTF-8')); ?></p>
            <p><strong>Yüklənmiş Fayllar:</strong> <?php echo $current_file_count; ?> / 3</p>
        </div>
    </div>

    <?php if (isset($page_error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($page_error); ?></div>
    <?php endif; ?>

    <?php if ($remaining_slots > 0): ?>
    <div class="upload-card">
        <h3>Fayl Yüklə</h3>
        <div class="upload-container" id="dropZone">
            <p style="font-size: 18px; font-weight: 500; color: #2d3748;">Faylları bura sürüşdürün və ya seçmək üçün klikləyin</p>
            <p style="font-size: 14px; color: #718096;">(Maksimum <?php echo $remaining_slots; ?> fayl, yalnız .doc, .docx, .xls, .xlsx, .ppt, .pptx, .psd)</p>
            <input type="file" id="fileInput" multiple style="display: none" accept=".doc,.docx,.xls,.xlsx,.ppt,.pptx,.psd">
            <div class="progress"><div class="progress-bar"></div></div>
        </div>
        <div id="uploadStatus" style="margin-top: 15px;"></div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        Maksimum fayl yükləmə limitinə (3) çatmısınız. Yeni fayl yükləmək üçün mövcud fayllardan birini silin.
    </div>
    <?php endif; ?>

    <?php if (!empty($uploads)): ?>
    <div class="files-card">
        <h3>Yüklənmiş Fayllar</h3>
        <div class="data-table">
            <table class="file-table">
                <thead>
                    <tr>
                        <th>Fayl Adı</th>
                        <th>Növü</th>
                        <th>Tarix</th>
                        <th>Əməliyyat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uploads as $file): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($file['original_name']); ?></td>
                            <td><?php echo strtoupper($file['file_type']); ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($file['upload_date'])); ?></td>
                            <td>
                                <button class="delete-btn" onclick="deleteFile(<?php echo $file['id']; ?>)">Sil</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const uploadStatus = document.getElementById('uploadStatus');
    const progressBar = document.querySelector('.progress-bar');
    const progressContainer = document.querySelector('.progress');
    const remainingSlots = <?php echo $remaining_slots; ?>;

    if (dropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });
        ['dragenter', 'dragover'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.classList.add('highlight'), false));
        ['dragleave', 'drop'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.classList.remove('highlight'), false));
        
        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('drop', e => handleFiles(e.dataTransfer.files), false);
        fileInput.addEventListener('change', e => handleFiles(e.target.files), false);
    }

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function handleFiles(files) {
        if (files.length > remainingSlots) {
            showStatus(`Maksimum ${remainingSlots} fayl yükləyə bilərsiniz.`, 'error');
            return;
        }
        uploadFiles([...files]);
    }

    function uploadFiles(files) {
        const formData = new FormData();
        files.forEach(file => formData.append('files[]', file));

        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';

        // Simple progress simulation
        let currentProgress = 0;
        const interval = setInterval(() => {
            currentProgress += 10;
            progressBar.style.width = currentProgress + '%';
            if (currentProgress >= 90) {
                clearInterval(interval);
            }
        }, 200);

        fetch('upload.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            clearInterval(interval);
            progressBar.style.width = '100%';
            showStatus(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                setTimeout(() => location.reload(), 1500);
            } else {
                setTimeout(() => {
                    progressContainer.style.display = 'none';
                    progressBar.style.width = '0%';
                }, 2000);
            }
        })
        .catch(err => {
            clearInterval(interval);
            showStatus('Yükləmə zamanı xəta baş verdi.', 'error');
            progressContainer.style.display = 'none';
        });
    }

    function showStatus(message, type) {
        uploadStatus.innerHTML = `<div class="alert alert-${type === 'success' ? 'success' : 'error'}">${message}</div>`;
    }
});

function deleteFile(fileId) {
    if (confirm('Bu faylı silmək istədiyinizə əminsiniz?')) {
        fetch('delete_file.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_id: fileId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Fayl silinərkən xəta baş verdi.');
            }
        })
        .catch(() => alert('Fayl silinərkən xəta baş verdi.'));
    }
}
</script>

</body>
</html>
