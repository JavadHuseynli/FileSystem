<?php
// index.php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['student_code']) && isset($_POST['full_name']) && isset($_POST['group_number'])) {
        $student_code = mb_strtoupper(trim($_POST['student_code']), 'UTF-8');
        $full_name = trim($_POST['full_name']);
        $group_number = mb_strtoupper(trim($_POST['group_number']), 'UTF-8');

        if (empty($student_code) || empty($full_name) || empty($group_number)) {
            $error = "Bütün sahələri doldurmaq məcburidir!";
        } else {
            try {
                // Əvvəlcə mövcud qeydiyyatı yoxla
                $stmt = $pdo->prepare("SELECT * FROM students WHERE student_code = ? AND full_name = ? AND group_number = ?");
                $stmt->execute([$student_code, $full_name, $group_number]);
                $existing_student = $stmt->fetch();

                if ($existing_student) {
                    // Mövcud qeydiyyatı istifadə et
                    $_SESSION['student_id'] = $existing_student['id'];
                    $_SESSION['student_code'] = $existing_student['student_code'];
                    $_SESSION['full_name'] = $existing_student['full_name'];
                    $_SESSION['group_number'] = $existing_student['group_number'];
                } else {
                    // Yeni qeydiyyat yarat
                    $stmt = $pdo->prepare("INSERT INTO students (student_code, full_name, group_number) VALUES (?, ?, ?)");
                    $stmt->execute([$student_code, $full_name, $group_number]);

                    $_SESSION['student_id'] = $pdo->lastInsertId();
                    $_SESSION['student_code'] = $student_code;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['group_number'] = $group_number;

                    // Clear caches for new student registration
                    cache_delete('dashboard_stats');
                    cache_delete('user_page_stats');
                    cache_delete('filter_subjects');
                    cache_delete('filter_groups');
                }

                header('Location: upload.php');
                exit();
            } catch (PDOException $e) {
                $error = "Xəta baş verdi. Zəhmət olmasa yenidən cəhd edin.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <title>Tapşırıq Yükləmə Sistemi - Qeydiyyat</title>
    <link rel="stylesheet" href="style_modern.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-icon">📝</div>
            <h2>Xoş gəlmisiniz</h2>
            <p class="subtitle">Davam etmək üçün məlumatlarınızı daxil edin</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="student_code">Fənnin adı</label>
                <input type="text" id="student_code" name="student_code"
                    value="<?php echo isset($_POST['student_code']) ? htmlspecialchars(mb_strtoupper($_POST['student_code'], 'UTF-8')) : ''; ?>"
                    style="text-transform: uppercase;"
                    onkeyup="this.value = this.value.toUpperCase();"
                    placeholder="Məsələn: XARİCİ DİL"
                    required>
            </div>

            <div class="form-group">
                <label for="full_name">Ad və Soyad</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" placeholder="Məsələn: Əliyev Vəli" required>
            </div>

            <div class="form-group">
                <label for="group_number">Qrup nömrəsi</label>
                <input type="text" id="group_number" name="group_number"
                    value="<?php echo isset($_POST['group_number']) ? htmlspecialchars(strtoupper($_POST['group_number'])) : ''; ?>"
                    style="text-transform: uppercase;"
                    onkeyup="this.value = this.value.toUpperCase();"
                    placeholder="Məsələn: 650.23"
                    required>
            </div>

            <button type="submit">Davam et</button>
        </form>
    </div>
</body>
</html>