<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';
require_once 'includes/permissions.php'; // NEW

// Check if admin is logged in and has admin role
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true || ($_SESSION['admin_role'] !== 'admin' && !hasPermission($pdo, $_SESSION['admin_id'], 'view_students'))) {
    header('Location: dashboard.php');
    exit();
}

$current_page = 'students_admin';
$page_title = 'Tələbə İdarəetməsi';

$success_message = '';
$error_message = '';
$all_students = [];

// Get user's assigned subjects if teacher
$user_subjects = [];
$user_groups = [];
$combined_assignments = []; // Initialize
if ($_SESSION['admin_role'] === 'teacher') {
    // Fetch combined assignments from teacher_group_subjects (NEW)
    $stmt = $pdo->prepare("SELECT group_number, subject_code FROM teacher_group_subjects WHERE teacher_id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $combined_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Extract unique subjects and groups for display/legacy filtering if needed
    foreach ($combined_assignments as $assignment) {
        $user_groups[] = $assignment['group_number'];
        $user_subjects[] = $assignment['subject_code'];
    }
    $user_groups = array_unique($user_groups);
    $user_subjects = array_unique($user_subjects);
}

// Get all subjects for dropdown
$stmt = $pdo->query("SELECT DISTINCT student_code FROM students WHERE student_code IS NOT NULL AND student_code != '' ORDER BY student_code");
$all_subjects_dropdown = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all groups for dropdown
$stmt = $pdo->query("SELECT DISTINCT group_number FROM students WHERE group_number IS NOT NULL AND group_number != '' ORDER BY group_number");
$all_groups_dropdown = $stmt->fetchAll(PDO::FETCH_COLUMN);

try {
    // Add new student
    if (isset($_POST['add_student'])) {
        $student_code = $_POST['student_code'] ?? '';
        if ($student_code === 'other') {
            $student_code = mb_strtoupper(trim($_POST['student_code_other'] ?? ''), 'UTF-8');
        } else {
            $student_code = mb_strtoupper(trim($student_code), 'UTF-8');
        }

        $full_name = trim($_POST['full_name'] ?? '');
        
        $group_number = $_POST['group_number'] ?? '';
        if ($group_number === 'other') {
            $group_number = mb_strtoupper(trim($_POST['group_number_other'] ?? ''), 'UTF-8');
        } else {
            $group_number = mb_strtoupper(trim($group_number), 'UTF-8');
        }

        if (empty($student_code) || empty($full_name)) {
            $error_message = "Tələbə kodu və Ad Soyad sahələrini doldurun!";
        } else {
            // Check if student code exists
            $stmt = $pdo->prepare("SELECT id FROM students WHERE student_code = ?");
            $stmt->execute([$student_code]);
            if ($stmt->fetch()) {
                $error_message = "Bu tələbə kodu artıq mövcuddur!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO students (student_code, full_name, group_number) VALUES (?, ?, ?)");
                $stmt->execute([$student_code, $full_name, $group_number]);

                // Clear relevant caches
                cache_delete('dashboard_stats');
                cache_delete('user_page_stats');
                cache_delete('filter_subjects');
                cache_delete('filter_groups');

                $success_message = "Tələbə uğurla əlavə edildi!";
            }
        }
    }

    // Update student
    if (isset($_POST['update_student'])) {
        $student_id = $_POST['student_id'] ?? 0;
        $student_code = mb_strtoupper(trim($_POST['student_code'] ?? ''), 'UTF-8');
        $full_name = trim($_POST['full_name'] ?? '');
        $group_number = mb_strtoupper(trim($_POST['group_number'] ?? ''), 'UTF-8');

        if (empty($student_code) || empty($full_name)) {
            $error_message = "Tələbə kodu və Ad Soyad sahələrini doldurun!";
        } else {
            // Check if student code exists for another student
            $stmt = $pdo->prepare("SELECT id FROM students WHERE student_code = ? AND id != ?");
            $stmt->execute([$student_code, $student_id]);
            if ($stmt->fetch()) {
                $error_message = "Bu tələbə kodu artıq başqa tələbə üçün istifadə olunur!";
            } else {
                $stmt = $pdo->prepare("UPDATE students SET student_code = ?, full_name = ?, group_number = ? WHERE id = ?");
                $stmt->execute([$student_code, $full_name, $group_number, $student_id]);

                // Clear relevant caches
                cache_delete('dashboard_stats');
                cache_delete('user_page_stats');
                cache_delete('filter_subjects');
                cache_delete('filter_groups');

                $success_message = "Tələbə məlumatları yeniləndi!";
            }
        }
    }

    // Delete student
    if (isset($_POST['delete_student'])) {
        $student_id = $_POST['student_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$student_id]);

        // Clear relevant caches
        cache_delete('dashboard_stats');
        cache_delete('user_page_stats');
        cache_delete('filter_subjects');
        cache_delete('filter_groups');

        $success_message = "Tələbə silindi!";
    }

    // Get all students
    $query = "SELECT * FROM students";
    $where_clauses = [];
    $params = [];

    if ($_SESSION['admin_role'] === 'teacher') {
        if (!empty($combined_assignments)) {
            $assignment_conditions = [];
            foreach ($combined_assignments as $assignment) {
                $assignment_conditions[] = "(group_number = ? AND student_code = ?)";
                $params[] = $assignment['group_number'];
                $params[] = $assignment['subject_code'];
            }
            $where_clauses[] = "(" . implode(' OR ', $assignment_conditions) . ")";
        }

        if (empty($where_clauses)) {
            // If teacher has no assigned subjects or groups, show no students
            $all_students = [];
        } else {
            $query .= " WHERE " . implode(' AND ', $where_clauses); // Should only be one clause here
            $query .= " ORDER BY created_at DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else { // Admin role
        $query .= " ORDER BY created_at DESC";
        $stmt = $pdo->query($query);
        $all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    handleDatabaseError($e);
    $error_message = "Verilənlər bazası ilə əlaqədar xəta baş verdi.";
} catch (Exception $e) {
    handleGeneralError($e->getMessage());
    $error_message = "Gözlənilməyən bir xəta baş verdi.";
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

    <?php if ($_SESSION['admin_role'] === 'admin'): ?>
    <!-- Add New Student -->
    <div class="profile-card">
        <h3>➕ Yeni Tələbə Əlavə Et</h3>
        <form method="POST">
            <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Tələbə Kodu</label>
                    <select name="student_code" id="admin_student_code_select" onchange="toggleOtherInput('admin_student_code_select', 'admin_student_code_other_div')">
                        <option value="">-- Fənn seçin --</option>
                        <?php foreach ($all_subjects_dropdown as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars(mb_strtoupper($subject, 'UTF-8')); ?></option>
                        <?php endforeach; ?>
                        <option value="other">-- Digər --</option>
                    </select>
                    <div id="admin_student_code_other_div" style="display: none; margin-top: 10px;">
                        <input type="text" name="student_code_other" id="admin_student_code_other" placeholder="Yeni fənn daxil edin" style="text-transform: uppercase;">
                    </div>
                </div>
                <div class="form-group">
                    <label>Ad Soyad</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Qrup</label>
                    <select name="group_number" id="admin_group_number_select" onchange="toggleOtherInput('admin_group_number_select', 'admin_group_number_other_div')">
                        <option value="">-- Qrup seçin --</option>
                        <?php foreach ($all_groups_dropdown as $group): ?>
                            <option value="<?php echo htmlspecialchars($group); ?>"><?php echo htmlspecialchars(mb_strtoupper($group, 'UTF-8')); ?></option>
                        <?php endforeach; ?>
                        <option value="other">-- Digər --</option>
                    </select>
                    <div id="admin_group_number_other_div" style="display: none; margin-top: 10px;">
                        <input type="text" name="group_number_other" id="admin_group_number_other" placeholder="Yeni qrup daxil edin" style="text-transform: uppercase;">
                    </div>
                </div>
            </div>
            <button type="submit" name="add_student" class="btn btn-success">Tələbə Əlavə Et</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Students List -->
    <div class="profile-card">
        <h3>🎓 Bütün Tələbələr</h3>
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>Tələbə Kodu</th>
                        <th>Ad Soyad</th>
                        <th>Şifrələnmiş Kod</th>
                        <th>Qrup</th>
                        <th>Qeydiyyat Tarixi</th>
                        <?php if ($_SESSION['admin_role'] === 'admin'): ?>
                        <th>Əməliyyatlar</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_students as $student): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td>
                                <?php if (!empty($student['assigned_code'])): ?>
                                    <strong style="color: #667eea;"><?php echo htmlspecialchars($student['assigned_code']); ?></strong>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(mb_strtoupper($student['group_number'] ?? '', 'UTF-8')); ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($student['created_at'])); ?></td>
                            <?php if ($_SESSION['admin_role'] === 'admin'): ?>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="editStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['student_code'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($student['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($student['group_number'] ?? '', ENT_QUOTES); ?>')">
                                        Redaktə Et
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bu tələbəni silmək istədiyinizə əminsiniz?');">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <button type="submit" name="delete_student" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">Sil</button>
                                    </form>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div id="editStudentModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 32px; border-radius: 20px; max-width: 600px; width: 90%;">
        <h3 style="margin-bottom: 24px;">Tələbə Məlumatlarını Redaktə Et</h3>
        <form method="POST">
            <input type="hidden" name="student_id" id="modal_student_id">
            <div style="display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 24px;">
                <div class="form-group">
                    <label>Tələbə Kodu</label>
                    <input type="text" name="student_code" id="modal_student_code" required style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label>Ad Soyad</label>
                    <input type="text" name="full_name" id="modal_full_name" required>
                </div>
                <div class="form-group">
                    <label>Qrup</label>
                    <input type="text" name="group_number" id="modal_group_number" style="text-transform: uppercase;">
                </div>
            </div>
            <div style="display: flex; gap: 12px;">
                <button type="submit" name="update_student" class="btn btn-primary">Yadda saxla</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Ləğv et</button>
            </div>
        </form>
    </div>
</div>

<script>
function editStudent(id, code, name, group) {
    document.getElementById('modal_student_id').value = id;
    document.getElementById('modal_student_code').value = code;
    document.getElementById('modal_full_name').value = name;
    document.getElementById('modal_group_number').value = group;
    document.getElementById('editStudentModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editStudentModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('editStudentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

function toggleOtherInput(selectId, otherDivId) {
    const selectElement = document.getElementById(selectId);
    const otherDiv = document.getElementById(otherDivId);
    if (selectElement.value === 'other') {
        otherDiv.style.display = 'block';
        otherDiv.querySelector('input').setAttribute('required', 'required');
    } else {
        otherDiv.style.display = 'none';
        otherDiv.querySelector('input').removeAttribute('required');
    }
}

function loadSubjectsByGroup(groupNumber) {
    const studentCodeSelect = document.getElementById('admin_student_code_select');
    studentCodeSelect.innerHTML = '<option value="">-- Yüklənir... --</option>'; // Loading state

    if (groupNumber === '' || groupNumber === 'other') {
        studentCodeSelect.innerHTML = '<option value="">-- Əvvəlcə qrup seçin --</option>';
        return;
    }

    fetch(`get_subjects_by_group.php?group_number=${encodeURIComponent(groupNumber)}`)
        .then(response => response.json())
        .then(data => {
            studentCodeSelect.innerHTML = '<option value="">-- Fənn seçin --</option>';
            if (data.success && data.subjects.length > 0) {
                data.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject;
                    option.textContent = subject.toUpperCase();
                    studentCodeSelect.appendChild(option);
                });
            } else {
                studentCodeSelect.innerHTML = '<option value="">-- Bu qrup üçün fənn tapılmadı --</option>';
            }
            // Always add the "Other" option
            const otherOption = document.createElement('option');
            otherOption.value = 'other';
            otherOption.textContent = '-- Digər --';
            studentCodeSelect.appendChild(otherOption);
        })
        .catch(error => {
            console.error('Error loading subjects:', error);
            studentCodeSelect.innerHTML = '<option value="">-- Fənn yüklənərkən xəta baş verdi --</option>';
            // Always add the "Other" option even on error
            const otherOption = document.createElement('option');
            otherOption.value = 'other';
            otherOption.textContent = '-- Digər --';
            studentCodeSelect.appendChild(otherOption);
        });
}
</script>

</body>
</html>
