<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';
require_once 'includes/permissions.php';

// Check if admin is logged in and has admin role
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true || $_SESSION['admin_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$current_page = 'users';
$page_title = 'İstifadəçi İdarəetməsi';

$success_message = '';
$error_message = '';
$all_users = [];
$all_permissions = [];

try {
    // Add new user
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'teacher';
        $permissions = $_POST['permissions'] ?? [];

        if (empty($username) || empty($password) || empty($full_name)) {
            $error_message = "Bütün sahələri doldurun!";
        } else {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error_message = "Bu istifadəçi adı artıq mövcuddur!";
            } else {
                $password_to_hash = empty($password) ? '' : $password; // Use empty string if provided password is empty
                $hashed_password = password_hash($password_to_hash, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $full_name, $role]);

                $new_user_id = $pdo->lastInsertId();

                // Səlahiyyətləri əlavə et
                if ($role !== 'admin') {
                    updateUserPermissions($pdo, $new_user_id, $permissions);
                }

                // Clear user count cache
                cache_delete('dashboard_user_count');

                $success_message = "İstifadəçi uğurla əlavə edildi!";
            }
        }
    }

    // Update user permissions
    if (isset($_POST['update_permissions'])) {
        $user_id = $_POST['user_id'] ?? 0;
        $permissions = $_POST['permissions'] ?? [];

        updateUserPermissions($pdo, $user_id, $permissions);
        $success_message = "Səlahiyyətlər yeniləndi!";
    }

    // Add new teacher group subject assignment (NEW)
    if (isset($_POST['update_teacher_group_subjects'])) {
        $user_id = $_POST['user_id'] ?? 0;
        $group_number = mb_strtoupper(trim($_POST['group_number'] ?? ''), 'UTF-8');
        $subject_code = mb_strtoupper(trim($_POST['subject_code'] ?? ''), 'UTF-8');

        if (empty($user_id) || empty($group_number) || empty($subject_code)) {
            echo json_encode(['success' => false, 'message' => 'Bütün sahələri doldurun!']);
            exit();
        }

        // Check if assignment already exists
        $stmt = $pdo->prepare("SELECT id FROM teacher_group_subjects WHERE teacher_id = ? AND group_number = ? AND subject_code = ?");
        $stmt->execute([$user_id, $group_number, $subject_code]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Bu fənn/qrup təyinatı artıq mövcuddur!']);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO teacher_group_subjects (teacher_id, group_number, subject_code) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $group_number, $subject_code]);

        // Clear relevant caches for this teacher
        cache_delete('dashboard_stats_' . $user_id);
        cache_delete('user_page_stats_' . $user_id);
        cache_delete('filter_subjects_' . $user_id);
        cache_delete('filter_groups_' . $user_id);
        cache_delete('dashboard_recent_uploads_' . $user_id);
        cache_delete('dashboard_uploads_by_subject_' . $user_id);

        echo json_encode(['success' => true, 'message' => 'Fənn/Qrup təyinatı uğurla əlavə edildi!']);
        exit();
    }

    // Delete teacher group subject assignment (NEW)
    if (isset($_POST['delete_teacher_assignment'])) {
        $assignment_id = $_POST['assignment_id'] ?? 0;

        if (empty($assignment_id)) {
            echo json_encode(['success' => false, 'message' => 'Təyinat ID-si boş ola bilməz!']);
            exit();
        }

        // Get teacher_id before deleting for cache clearing
        $stmt = $pdo->prepare("SELECT teacher_id FROM teacher_group_subjects WHERE id = ?");
        $stmt->execute([$assignment_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            echo json_encode(['success' => false, 'message' => 'Təyinat tapılmadı!']);
            exit();
        }
        $user_id = $assignment['teacher_id'];

        $stmt = $pdo->prepare("DELETE FROM teacher_group_subjects WHERE id = ?");
        $stmt->execute([$assignment_id]);

        // Clear relevant caches for this teacher
        cache_delete('dashboard_stats_' . $user_id);
        cache_delete('user_page_stats_' . $user_id);
        cache_delete('filter_subjects_' . $user_id);
        cache_delete('filter_groups_' . $user_id);
        cache_delete('dashboard_recent_uploads_' . $user_id);
        cache_delete('dashboard_uploads_by_subject_' . $user_id);

        echo json_encode(['success' => true, 'message' => 'Təyinat uğurla silindi!']);
        exit();
    }

    // Delete user
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'] ?? 0;
        if ($user_id != $_SESSION['admin_id']) {
            $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
            $stmt->execute([$user_id]);

            // Clear user count cache
            cache_delete('dashboard_user_count');

            $success_message = "İstifadəçi silindi!";
        } else {
            $error_message = "Özünüzü silə bilməzsiniz!";
        }
    }

    // Get all users
    $stmt = $pdo->query("SELECT * FROM admin_users ORDER BY created_at DESC");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all permissions
    $all_permissions = getAllPermissions($pdo);

    // Get all subjects
    $stmt = $pdo->query("SELECT DISTINCT student_code FROM students ORDER BY student_code");
    $all_subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get all unique group numbers
    $stmt = $pdo->query("SELECT DISTINCT group_number FROM students WHERE group_number IS NOT NULL AND group_number != '' ORDER BY group_number");
    $all_groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    handleDatabaseError($e);
    $error_message = "Verilənlər bazası ilə əlaqədar xəta baş verdi.";
} catch (Exception $e) {
    logError($e);
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

    <!-- Add New User -->
    <div class="profile-card">
        <h3>➕ Yeni İstifadəçi Əlavə Et</h3>
        <form method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>İstifadəçi adı</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Ad Soyad</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Şifrə</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select name="role" id="user_role" onchange="togglePermissions(this.value)">
                        <option value="teacher">Müəllim</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>

            <div class="form-group" id="permissions_section">
                <label>Səlahiyyətlər</label>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 12px;">
                    <?php foreach ($all_permissions as $perm): ?>
                        <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fd; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: all 0.3s;" class="permission-checkbox">
                            <input type="checkbox" name="permissions[]" value="<?php echo $perm['name']; ?>" style="width: 18px; height: 18px; cursor: pointer;">
                            <div>
                                <div style="font-weight: 600; font-size: 13px;"><?php echo htmlspecialchars($perm['description']); ?></div>
                                <div style="font-size: 11px; color: #718096; margin-top: 2px;"><?php echo $perm['name']; ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" name="add_user" class="btn btn-success">İstifadəçi Əlavə Et</button>
        </form>
    </div>

    <!-- Users List -->
    <div class="profile-card">
        <h3>👥 Bütün İstifadəçilər</h3>
        <div class="data-table" style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>İstifadəçi adı</th>
                        <th>Ad Soyad</th>
                        <th>Rol</th>
                        <th>Səlahiyyətlər</th>
                        <th>Təyin Edilmiş Fənn/Qruplar</th> <!-- NEW -->
                        <th>Qeydiyyat Tarixi</th>
                        <th>Əməliyyatlar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_users as $user): ?>
                        <?php
                        $user_permissions = $user['role'] === 'admin' ? ['Bütün səlahiyyətlər'] : getUserPermissions($pdo, $user['id']);

                        // Get teacher combined assignments (NEW)
                        $user_combined_assignments_count = 0;
                        if ($user['role'] === 'teacher') {
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM teacher_group_subjects WHERE teacher_id = ?");
                            $stmt->execute([$user['id']]);
                            $user_combined_assignments_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['role']; ?>">
                                    <?php echo $user['role'] === 'admin' ? 'Administrator' : 'Müəllim'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span style="color: #11998e; font-weight: 600;">✓ Bütün səlahiyyətlər</span>
                                <?php elseif (empty($user_permissions)): ?>
                                    <span style="color: #e53e3e;">✗ Səlahiyyət yoxdur</span>
                                <?php else: ?>
                                    <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="showPermissions(<?php echo $user['id']; ?>)">
                                        <?php echo count($user_permissions); ?> səlahiyyət
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td> <!-- NEW Combined Assignments Column -->
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span style="color: #11998e; font-weight: 600;">✓ Bütün fənn/qruplar</span>
                                <?php elseif ($user_combined_assignments_count === 0): ?>
                                    <span style="color: #e53e3e;">✗ Təyinat yoxdur</span>
                                <?php else: ?>
                                    <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="assignSubjectGroup(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                        <?php echo $user_combined_assignments_count; ?> təyinat
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                    <div style="display: flex; gap: 8px;">
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <button class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="editPermissions(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                                Səlahiyyətlər
                                            </button>
                                            <button class="btn btn-success" style="padding: 6px 12px; font-size: 12px;" onclick="assignSubjectGroup(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                                Fənn/Qrup Təyin Et
                                            </button>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bu istifadəçini silmək istədiyinizə əminsiniz?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">Sil</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-admin">Siz</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Permissions Modal -->
<div id="permissionsModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 32px; border-radius: 20px; max-width: 600px; width: 90%;">
        <h3 style="margin-bottom: 24px;">Səlahiyyətləri Redaktə Et: <span id="modal_user_name"></span></h3>
        <form method="POST" id="permissionsForm">
            <input type="hidden" name="user_id" id="modal_user_id">
            <div style="display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 24px;">
                <?php foreach ($all_permissions as $perm): ?>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fd; border-radius: 8px; cursor: pointer;" class="permission-checkbox">
                        <input type="checkbox" name="permissions[]" value="<?php echo $perm['name']; ?>" class="modal-permission" style="width: 18px; height: 18px; cursor: pointer;">
                        <div>
                            <div style="font-weight: 600; font-size: 13px;"><?php echo htmlspecialchars($perm['description']); ?></div>
                            <div style="font-size: 11px; color: #718096;"><?php echo $perm['name']; ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            <div style="display: flex; gap: 12px;">
                <button type="submit" name="update_permissions" class="btn btn-primary">Yadda saxla</button>
                <button type="button" class="btn btn-secondary" onclick="closePermissionsModal()">Ləğv et</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Subject/Group Modal -->
<div id="assignSubjectGroupModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 32px; border-radius: 20px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <h3 style="margin-bottom: 24px;">Fənn/Qrup Təyin Et: <span id="modal_assign_user_name"></span></h3>
        <form method="POST" id="assignSubjectGroupForm">
            <input type="hidden" name="user_id" id="modal_assign_user_id">
            <div style="display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 24px;">
                <div class="form-group">
                    <label>Qrup</label>
                    <select name="group_number" id="modal_assign_group_select" onchange="loadSubjectsForAssignment(this.value)">
                        <option value="">-- Qrup seçin --</option>
                        <?php foreach ($all_groups as $group): ?>
                            <option value="<?php echo htmlspecialchars($group); ?>"><?php echo htmlspecialchars(mb_strtoupper($group, 'UTF-8')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fənn</label>
                    <select name="subject_code" id="modal_assign_subject_select">
                        <option value="">-- Əvvəlcə qrup seçin --</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 12px;">
                <button type="submit" name="update_teacher_group_subjects" class="btn btn-primary">Təyin Et</button>
                <button type="button" class="btn btn-secondary" onclick="closeAssignSubjectGroupModal()">Ləğv et</button>
            </div>
        </form>

        <h4 style="margin-top: 30px; margin-bottom: 15px; border-top: 1px solid #eee; padding-top: 20px;">Təyin Edilmiş Fənn/Qruplar</h4>
        <div id="assigned_list" style="max-height: 200px; overflow-y: auto; border: 1px solid #eee; border-radius: 8px; padding: 10px;">
            <!-- Assigned subjects/groups will be loaded here -->
            <p style="text-align: center; color: #718096;">Yüklənir...</p>
        </div>
    </div>
</div>

<style>
.permission-checkbox:has(input:checked) {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)) !important;
    border-color: #667eea !important;
}
.assigned-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f8f9fd;
    border-radius: 8px;
    margin-bottom: 8px;
}
.assigned-item:last-child {
    margin-bottom: 0;
}
.assigned-item .remove-btn {
    background: #e53e3e;
    color: white;
    border: none;
    border-radius: 5px;
    padding: 4px 8px;
    cursor: pointer;
    font-size: 12px;
}
.assigned-item .remove-btn:hover {
    background: #c53030;
}
</style>

<script>
function togglePermissions(role) {
    const section = document.getElementById('permissions_section');
    section.style.display = role === 'teacher' ? 'block' : 'none';
}

function editPermissions(userId, userName) {
    document.getElementById('modal_user_id').value = userId;
    document.getElementById('modal_user_name').textContent = userName;
    document.getElementById('permissionsModal').style.display = 'flex';

    // Load user permissions
    fetch(`get_user_permissions.php?user_id=${userId}`)
        .then(r => r.json())
        .then(data => {
            document.querySelectorAll('.modal-permission').forEach(cb => {
                cb.checked = data.permissions.includes(cb.value);
            });
        });
}

function closePermissionsModal() {
    document.getElementById('permissionsModal').style.display = 'none';
}

function showPermissions(userId) {
    fetch(`get_user_permissions.php?user_id=${userId}`)
        .then(r => r.json())
        .then(data => {
            const perms = data.permissions.map(p => {
                const perm = <?php echo json_encode(array_column($all_permissions, 'description', 'name')); ?>;
                return perm[p] || p;
            }).join('\n• ');
            alert('Səlahiyyətlər:\n\n• ' + perms);
        });
}

// New functions for combined assignment
let currentTeacherId = null;

function assignSubjectGroup(userId, userName) {
    currentTeacherId = userId;
    document.getElementById('modal_assign_user_id').value = userId;
    document.getElementById('modal_assign_user_name').textContent = userName;
    document.getElementById('assignSubjectGroupModal').style.display = 'flex';

    // Reset dropdowns
    document.getElementById('modal_assign_group_select').value = '';
    document.getElementById('modal_assign_subject_select').innerHTML = '<option value="">-- Əvvəlcə qrup seçin --</option>';

    loadAssignedSubjectsGroups(userId);
}

function closeAssignSubjectGroupModal() {
    document.getElementById('assignSubjectGroupModal').style.display = 'none';
    currentTeacherId = null;
}

function loadSubjectsForAssignment(groupNumber) {
    const subjectSelect = document.getElementById('modal_assign_subject_select');
    subjectSelect.innerHTML = '<option value="">-- Yüklənir... --</option>';

    if (groupNumber === '') {
        subjectSelect.innerHTML = '<option value="">-- Əvvəlcə qrup seçin --</option>';
        return;
    }

    fetch(`get_subjects_by_group.php?group_number=${encodeURIComponent(groupNumber)}`)
        .then(response => response.json())
        .then(data => {
            subjectSelect.innerHTML = '<option value="">-- Fənn seçin --</option>';
            if (data.success && data.subjects.length > 0) {
                data.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject;
                    option.textContent = subject.toUpperCase();
                    subjectSelect.appendChild(option);
                });
            } else {
                subjectSelect.innerHTML = '<option value="">-- Bu qrup üçün fənn tapılmadı --</option>';
            }
        })
        .catch(error => {
            console.error('Error loading subjects for assignment:', error);
            subjectSelect.innerHTML = '<option value="">-- Fənn yüklənərkən xəta baş verdi --</option>';
        });
}

function loadAssignedSubjectsGroups(userId) {
    const assignedListDiv = document.getElementById('assigned_list');
    assignedListDiv.innerHTML = '<p style="text-align: center; color: #718096;">Yüklənir...</p>';

    fetch(`get_teacher_group_subjects.php?user_id=${userId}`) // NEW API endpoint
        .then(response => response.json())
        .then(data => {
            assignedListDiv.innerHTML = '';
            if (data.success && data.assignments.length > 0) {
                data.assignments.forEach(assignment => {
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'assigned-item';
                    itemDiv.innerHTML = `
                        <span>${assignment.group_number.toUpperCase()} - ${assignment.subject_code.toUpperCase()}</span>
                        <button type="button" class="remove-btn" onclick="removeAssignedSubjectGroup(${assignment.id})">Sil</button>
                    `;
                    assignedListDiv.appendChild(itemDiv);
                });
            } else {
                assignedListDiv.innerHTML = '<p style="text-align: center; color: #718096;">Heç bir fənn/qrup təyin edilməyib.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading assigned subjects/groups:', error);
            assignedListDiv.innerHTML = '<p style="text-align: center; color: #e53e3e;">Təyin edilmiş fənn/qruplar yüklənərkən xəta baş verdi.</p>';
        });
}

function removeAssignedSubjectGroup(assignmentId) {
    if (confirm('Bu təyinatı silmək istədiyinizə əminsiniz?')) {
        fetch('admin_users.php', { // POST to admin_users.php to handle deletion
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `delete_teacher_assignment=1&assignment_id=${assignmentId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadAssignedSubjectsGroups(currentTeacherId); // Reload the list
                // Optionally show a success message
            } else {
                alert(data.message || 'Təyinat silinərkən xəta baş verdi.');
            }
        })
        .catch(error => {
            console.error('Error removing assignment:', error);
            alert('Təyinat silinərkən xəta baş verdi.');
        });
    }
}

// Close modal on outside click
document.getElementById('permissionsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePermissionsModal();
    }
});

document.getElementById('assignSubjectGroupModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAssignSubjectGroupModal();
    }
});
</script>

</body>
</html>
