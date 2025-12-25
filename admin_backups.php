<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';
require_once 'includes/backup.php';

// Check if admin is logged in and has admin role
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true || $_SESSION['admin_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$current_page = 'backups';
$page_title = 'Ehtiyat Nüsxə İdarəetməsi';

$success_message = '';
$error_message = '';
$new_backup_file = null; // Initialize variable

try {
    // Handle backup actions
    if (isset($_POST['create_backup'])) {
        $delete_student_data = isset($_POST['delete_student_data']) && $_POST['delete_student_data'] == '1';
        $backup_file_path = createDatabaseBackup($pdo, null, $delete_student_data);
        if ($backup_file_path) {
            $success_message = "Ehtiyat nüsxə uğurla yaradıldı.";
            if ($delete_student_data) {
                $success_message .= " Tələbə məlumatları silindi.";
            }
            $new_backup_file = $backup_file_path; // Store the path
        } else {
            $error_message = "Ehtiyat nüsxə yaradılarkən xəta baş verdi.";
        }
    }

    if (isset($_POST['restore_backup'])) {
        $file_path = $_POST['backup_file_path'];
        if (restoreBackup($pdo, $file_path)) {
            $success_message = "Ehtiyat nüsxə uğurla bərpa edildi.";
        } else {
            $error_message = "Ehtiyat nüsxə bərpa edilərkən xəta baş verdi.";
        }
    }

    if (isset($_POST['delete_backup'])) {
        $file_path = $_POST['backup_file_path'];
        if (file_exists($file_path) && unlink($file_path)) {
            $success_message = "Ehtiyat nüsxə silindi.";
        } else {
            $error_message = "Ehtiyat nüsxə silinərkən xəta baş verdi.";
        }
    }

    // Get available backups
    $available_backups = getAvailableBackups();

} catch (Exception $e) {
    logError($e);
    $error_message = "Gözlənilməyən bir xəta baş verdi.";
    $available_backups = [];
}


include 'includes/admin_header.php';
include 'includes/admin_sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <?php if ($success_message): ?>
        <div class="alert alert-success">✓ <?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">✗ <?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- Create Backup -->
    <div class="profile-card">
        <h3>➕ Yeni Ehtiyat Nüsxə Yarat</h3>
        <form method="POST">
            <p>Verilənlər bazasının ehtiyat nüsxəsini yaratmaq üçün aşağıdakı düyməyə klikləyin.</p>
            <div class="form-group" style="margin: 20px 0;">
                <label style="display: flex; align-items: center; gap: 10px; padding: 16px; background: #fff5f5; border: 2px solid #fc8181; border-radius: 12px; cursor: pointer;">
                    <input type="checkbox" name="delete_student_data" value="1" style="width: 20px; height: 20px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 600; color: #c53030;">⚠️ Tələbə məlumatlarını sil</div>
                        <div style="font-size: 13px; color: #718096; margin-top: 4px;">
                            Bu seçimi aktiv etsəniz, ehtiyat nüsxə yaradılmazdan əvvəl bütün tələbə məlumatları və yüklənmiş fayllar silinəcək.
                        </div>
                    </div>
                </label>
            </div>
            <button type="submit" name="create_backup" class="btn btn-primary">Ehtiyat Nüsxə Yarat</button>
        </form>
    </div>

    <!-- Backups List -->
    <div class="profile-card">
        <h3>🗄️ Mövcud Ehtiyat Nüsxələr</h3>
        <?php if (!empty($available_backups)): ?>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Fayl Adı</th>
                            <th>Tarix</th>
                            <th>Həcm</th>
                            <th>Əməliyyatlar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_backups as $backup): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($backup['filename'] ?? ''); ?></strong></td>
                                <td><?php echo date('d.m.Y H:i', $backup['date'] ?? 0); ?></td>
                                <td><?php echo round(($backup['size'] ?? 0) / 1024, 2); ?> KB</td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bu ehtiyat nüsxəsini bərpa etmək istədiyinizə əminsiniz? Bu əməliyyat geri qaytarılmazdır.');">
                                            <input type="hidden" name="backup_file_path" value="<?php echo htmlspecialchars($backup['path'] ?? ''); ?>">
                                            <button type="submit" name="restore_backup" class="btn btn-warning" style="padding: 6px 12px; font-size: 12px;">Bərpa Et</button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bu ehtiyat nüsxəsini silmək istədiyinizə əminsiniz?');">
                                            <input type="hidden" name="backup_file_path" value="<?php echo htmlspecialchars($backup['path'] ?? ''); ?>">
                                            <button type="submit" name="delete_backup" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">Sil</button>
                                        </form>
                                        <a href="<?php echo htmlspecialchars($backup['path'] ?? ''); ?>" download class="btn btn-success" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">Yüklə</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Heç bir ehtiyat nüsxə tapılmadı.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
if (isset($new_backup_file) && $new_backup_file) {
    // We need to make the path relative to the web root
    $relative_path = 'backups/' . basename($new_backup_file);
    $download_url = htmlspecialchars($relative_path, ENT_QUOTES, 'UTF-8');
    $file_name = htmlspecialchars(basename($new_backup_file), ENT_QUOTES, 'UTF-8');

    echo <<<JS
<script>
    window.addEventListener('DOMContentLoaded', (event) => {
        const link = document.createElement('a');
        link.href = '{$download_url}';
        link.download = '{$file_name}';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        // Optional: redirect or clean up URL to avoid re-triggering download on refresh
        // window.history.replaceState(null, null, window.location.pathname);
    });
</script>
JS;
}
?>

</body>
</html>
