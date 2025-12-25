<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$current_page = 'students';
$page_title = 'Tələbələr';

// Get user's assigned subjects if teacher
$user_subjects = [];
if ($_SESSION['admin_role'] === 'teacher') {
    $stmt = $pdo->prepare("SELECT subject_code FROM teacher_subjects WHERE teacher_id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $user_subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get filter parameters
$filter_name = $_GET['filter_name'] ?? '';
$filter_group = $_GET['filter_group'] ?? '';
$filter_subject = $_GET['filter_subject'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';

// Build query with filters
$query = "
    SELECT s.*, COUNT(u.id) as file_count, MAX(u.upload_date) as last_upload_date,
           (SELECT folder_path FROM uploads WHERE student_id = s.id LIMIT 1) as folder_path
    FROM students s
    LEFT JOIN uploads u ON s.id = u.student_id
    WHERE 1=1
";

$params = [];

// Filter by teacher's assigned subjects if not admin
if ($_SESSION['admin_role'] === 'teacher' && !empty($user_subjects)) {
    $placeholders = str_repeat('?,', count($user_subjects) - 1) . '?';
    $query .= " AND s.student_code IN ($placeholders)";
    $params = array_merge($params, $user_subjects);
} elseif ($_SESSION['admin_role'] === 'teacher' && empty($user_subjects)) {
    // Teacher has no assigned subjects, show nothing
    $query .= " AND 1=0";
}

if ($filter_name) {
    $query .= " AND s.full_name LIKE ?";
    $params[] = "%$filter_name%";
}

if ($filter_group) {
    $query .= " AND s.group_number LIKE ?";
    $params[] = "%$filter_group%";
}

if ($filter_subject) {
    $query .= " AND s.student_code LIKE ?";
    $params[] = "%$filter_subject%";
}

if ($filter_date) {
    $query .= " AND DATE(u.upload_date) = ?";
    $params[] = $filter_date;
}

$query .= " GROUP BY s.id HAVING file_count > 0 ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics (cached for 5 minutes)
$cache_key = 'user_page_stats_' . ($_SESSION['admin_role'] === 'teacher' ? $_SESSION['admin_id'] : 'admin');
$stats = cache_remember($cache_key, function() use ($pdo, $user_subjects) {
    if ($_SESSION['admin_role'] === 'teacher' && !empty($user_subjects)) {
        $placeholders = str_repeat('?,', count($user_subjects) - 1) . '?';
        $stats_stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT student_code) as total_subjects,
                COUNT(DISTINCT s.id) as total_students,
                (SELECT COUNT(*) FROM uploads u INNER JOIN students st ON u.student_id = st.id WHERE st.student_code IN ($placeholders)) as total_files
            FROM students s
            WHERE s.student_code IN ($placeholders)
        ");
        $stats_stmt->execute(array_merge($user_subjects, $user_subjects));
    } elseif ($_SESSION['admin_role'] === 'teacher') {
        // Teacher has no subjects assigned
        return ['total_subjects' => 0, 'total_students' => 0, 'total_files' => 0];
    } else {
        $stats_stmt = $pdo->query("
            SELECT
                COUNT(DISTINCT student_code) as total_subjects,
                COUNT(DISTINCT id) as total_students,
                (SELECT COUNT(*) FROM uploads) as total_files
            FROM students
        ");
    }
    return $stats_stmt->fetch(PDO::FETCH_ASSOC);
}, 300);

// Get unique subjects for filter (cached for 30 minutes)
$subjects_cache_key = 'filter_subjects_' . ($_SESSION['admin_role'] === 'teacher' ? $_SESSION['admin_id'] : 'admin');
$subjects = cache_remember($subjects_cache_key, function() use ($pdo, $user_subjects) {
    if ($_SESSION['admin_role'] === 'teacher' && !empty($user_subjects)) {
        $placeholders = str_repeat('?,', count($user_subjects) - 1) . '?';
        $subjects_stmt = $pdo->prepare("SELECT DISTINCT student_code FROM students WHERE student_code IN ($placeholders) ORDER BY student_code");
        $subjects_stmt->execute($user_subjects);
        return $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($_SESSION['admin_role'] === 'teacher') {
        return [];
    } else {
        $subjects_stmt = $pdo->query("SELECT DISTINCT student_code FROM students ORDER BY student_code");
        return $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}, 1800);

// Get unique groups for filter (cached for 30 minutes)
$groups_cache_key = 'filter_groups_' . ($_SESSION['admin_role'] === 'teacher' ? $_SESSION['admin_id'] : 'admin');
$groups = cache_remember($groups_cache_key, function() use ($pdo, $user_subjects) {
    if ($_SESSION['admin_role'] === 'teacher' && !empty($user_subjects)) {
        $placeholders = str_repeat('?,', count($user_subjects) - 1) . '?';
        $groups_stmt = $pdo->prepare("SELECT DISTINCT group_number FROM students WHERE student_code IN ($placeholders) ORDER BY group_number");
        $groups_stmt->execute($user_subjects);
        return $groups_stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($_SESSION['admin_role'] === 'teacher') {
        return [];
    } else {
        $groups_stmt = $pdo->query("SELECT DISTINCT group_number FROM students ORDER BY group_number");
        return $groups_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}, 1800);

include 'includes/admin_header.php';
include 'includes/admin_sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">✓ <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">✗ <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                📚
            </div>
            <div class="stat-info">
                <h3>Qeydiyyatlı Fənlər</h3>
                <p><?php echo $stats['total_subjects']; ?></p>
            </div>
        </div>

        <?php if ($_SESSION['admin_role'] !== 'teacher'): ?>
        <div class="stat-card">
            <div class="stat-icon green">
                👥
            </div>
            <div class="stat-info">
                <h3>Qeydiyyatlı İstifadəçilər</h3>
                <p><?php echo $stats['total_students']; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="stat-card">
            <div class="stat-icon orange">
                📁
            </div>
            <div class="stat-info">
                <h3>Yüklənmiş Fayllar</h3>
                <p><?php echo $stats['total_files']; ?></p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <h3>🔍 Filtrlər</h3>
        <form method="GET">
            <div class="filter-grid">
                <?php if ($_SESSION['admin_role'] !== 'teacher'): ?>
                <div class="filter-item">
                    <label>Ad və Soyad</label>
                    <input type="text" name="filter_name" value="<?php echo htmlspecialchars($filter_name); ?>" placeholder="Axtarış...">
                </div>
                <?php endif; ?>
                <div class="filter-item">
                    <label>Fənin adı</label>
                    <select name="filter_subject">
                        <option value="">Hamısı</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject); ?>"
                                <?php echo ($filter_subject == $subject) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(mb_strtoupper($subject, 'UTF-8')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($_SESSION['admin_role'] !== 'teacher'): ?>
                <div class="filter-item">
                    <label>Qrup</label>
                    <select name="filter_group">
                        <option value="">Hamısı</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo htmlspecialchars($group); ?>"
                                <?php echo ($filter_group == $group) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(mb_strtoupper($group, 'UTF-8')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="filter-item">
                    <label>Yükləmə Tarixi</label>
                    <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Axtar</button>
                <a href="user.php" class="btn btn-secondary">Təmizlə</a>
            </div>
        </form>
    </div>

    <!-- Action Buttons -->
    <div style="display: flex; gap: 12px; margin-bottom: 20px;">
        <?php if ($stats['total_files'] > 0): ?>
            <form method="POST" action="download.php" style="display: inline;">
                <button type="submit" name="download_all" class="btn btn-warning">
                    📥 Bütün Faylları Yüklə (ZIP)
                </button>
            </form>
        <?php endif; ?>

        <?php if ($_SESSION['admin_role'] === 'admin'): ?>
            <button type="button" class="btn btn-primary" onclick="bulkAssignCodes()">
                🔢 Hamısına Kod Təyin Et
            </button>
        <?php endif; ?>
    </div>

    <!-- Students Table -->
    <?php if (count($students) > 0): ?>
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>Fənin adı</th>
                        <?php if ($_SESSION['admin_role'] !== 'teacher'): ?>
                        <th>Ad və Soyad</th>
                        <?php endif; ?>
                        <th>Şifrələnmiş Kod</th>
                        <?php if ($_SESSION['admin_role'] !== 'teacher'): ?>
                        <th>Qrup</th>
                        <th>Faylların Sayı</th>
                        <?php endif; ?>
                        <th>Yükləmə Tarixi</th>
                        <th>Əməliyyatlar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars(mb_strtoupper($student['student_code'], 'UTF-8')); ?></strong></td>
                            <?php if ($_SESSION['admin_role'] !== 'teacher'): ?>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span><?php echo htmlspecialchars($student['full_name']); ?></span>
                                    <button type="button" class="btn btn-primary" style="padding: 6px 10px; font-size: 11px;" onclick="assignStudentCode(<?php echo $student['id']; ?>, this)">
                                        🔢 Kod Yarat
                                    </button>
                                </div>
                            </td>
                            <?php endif; ?>
                            <td>
                                <?php if (!empty($student['assigned_code'])): ?>
                                    <strong style="color: #667eea;"><?php echo htmlspecialchars($student['assigned_code']); ?></strong>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($_SESSION['admin_role'] !== 'teacher'): ?>
                            <td><?php echo htmlspecialchars(mb_strtoupper($student['group_number'], 'UTF-8')); ?></td>
                            <td><span class="badge badge-teacher"><?php echo $student['file_count']; ?> fayl</span></td>
                            <?php endif; ?>
                            <td><?php echo $student['last_upload_date'] ? date('d.m.Y H:i', strtotime($student['last_upload_date'])) : '-'; ?></td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <?php if (!empty($student['folder_path'])): ?>
                                        <form method="POST" action="download.php" style="display: inline;">
                                            <input type="hidden" name="folder_path" value="<?php echo htmlspecialchars($student['folder_path']); ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" class="btn btn-success" style="padding: 8px 12px; font-size: 12px;">📥 Yüklə</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;">Fayl yoxdur</span>
                                    <?php endif; ?>

                                    <?php if ($_SESSION['admin_role'] === 'teacher'): ?>
                                        <button type="button" class="btn btn-primary" style="padding: 8px 12px; font-size: 12px;" onclick="openGradeModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['assigned_code'] ?? $student['student_code']); ?>')">
                                            📝 Qiymətləndir
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Filtrə uyğun nəticə tapılmadı.
        </div>
    <?php endif; ?>
</div>

<!-- Grade Modal -->
<div id="gradeModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 32px; border-radius: 20px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <h3 style="margin-bottom: 24px;">Qiymətləndirmə: <span id="grade_student_code"></span></h3>
        <form method="POST" action="save_grades.php" id="gradeForm">
            <input type="hidden" name="student_id" id="grade_student_id">

            <?php for ($i = 1; $i <= 3; $i++): ?>
            <div style="padding: 20px; background: #f7fafc; border-radius: 12px; margin-bottom: 16px;">
                <h4 style="margin-bottom: 12px; color: #667eea;">İş <?php echo $i; ?></h4>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Qiymət</label>
                    <input type="text" name="grade_<?php echo $i; ?>" id="grade_<?php echo $i; ?>" placeholder="Məsələn: 90, A, 5" style="padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 14px;">
                </div>
                <div class="form-group">
                    <label>Qeyd</label>
                    <textarea name="comment_<?php echo $i; ?>" id="comment_<?php echo $i; ?>" rows="2" placeholder="Əlavə qeyd..." style="padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 14px; resize: vertical;"></textarea>
                </div>
            </div>
            <?php endfor; ?>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn btn-primary">Yadda saxla</button>
                <button type="button" class="btn btn-secondary" onclick="closeGradeModal()">Ləğv et</button>
            </div>
        </form>
    </div>
</div>

<script>
function assignStudentCode(studentId, button) {
    if (!confirm('Bu tələbəyə avtomatik kod təyin etmək istədiyinizə əminsiniz?')) {
        return;
    }

    // Disable button and show loading
    button.disabled = true;
    button.textContent = '⏳ Yüklənir...';

    fetch('update_student_code.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'student_id=' + studentId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Tələbə kodu uğurla yaradıldı: ' + data.code);
            location.reload();
        } else {
            alert('Xəta: ' + (data.message || 'Kod yaradılarkən xəta baş verdi'));
            button.disabled = false;
            button.textContent = '🔢 Kod Yarat';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Kod yaradılarkən xəta baş verdi');
        button.disabled = false;
        button.textContent = '🔢 Kod Yarat';
    });
}

function openGradeModal(studentId, studentCode) {
    document.getElementById('grade_student_id').value = studentId;
    document.getElementById('grade_student_code').textContent = studentCode;
    document.getElementById('gradeModal').style.display = 'flex';

    // Load existing grades
    fetch('get_grades.php?student_id=' + studentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.grades.forEach((grade, index) => {
                    const workNumber = index + 1;
                    if (grade) {
                        document.getElementById('grade_' + workNumber).value = grade.grade || '';
                        document.getElementById('comment_' + workNumber).value = grade.comment || '';
                    }
                });
            }
        });
}

function closeGradeModal() {
    document.getElementById('gradeModal').style.display = 'none';
    // Clear form
    document.getElementById('gradeForm').reset();
}

// Close modal on outside click
document.getElementById('gradeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeGradeModal();
    }
});

function bulkAssignCodes() {
    if (!confirm('Bütün tələbələrə avtomatik kod təyin etmək istədiyinizə əminsiniz?\n\nBu əməliyyat yalnız kod təyin edilməmiş tələbələrə kod təyin edəcək.')) {
        return;
    }

    // Show loading message
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'bulk-loading';
    loadingDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 32px; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); z-index: 10000; text-align: center;';
    loadingDiv.innerHTML = '<div style="font-size: 48px; margin-bottom: 16px;">⏳</div><div style="font-size: 18px; font-weight: 600;">Kodlar təyin edilir...</div>';
    document.body.appendChild(loadingDiv);

    fetch('bulk_assign_codes.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        document.body.removeChild(loadingDiv);

        if (data.success) {
            alert('✓ ' + data.message);
            location.reload();
        } else {
            alert('✗ Xəta: ' + (data.message || 'Kodlar təyin edilərkən xəta baş verdi'));
        }
    })
    .catch(error => {
        if (document.getElementById('bulk-loading')) {
            document.body.removeChild(loadingDiv);
        }
        console.error('Error:', error);
        alert('✗ Kodlar təyin edilərkən xəta baş verdi');
    });
}
</script>

</body>
</html>
