<?php
session_save_path(__DIR__ . '/sessions');
session_start();
require_once 'config.php';
require_once 'includes/permissions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$current_page = 'dashboard';
$page_title = 'Dashboard';

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

// Get statistics (cached for 5 minutes)
$cache_key = 'dashboard_stats_' . ($_SESSION['admin_role'] === 'teacher' ? $_SESSION['admin_id'] : 'admin');
$stats = cache_remember($cache_key, function() use ($pdo, $combined_assignments) { // Use combined_assignments
    $where_clauses = [];
    $params = [];

    if ($_SESSION['admin_role'] === 'teacher') {
        if (!empty($combined_assignments)) {
            $assignment_conditions = [];
            foreach ($combined_assignments as $assignment) {
                $assignment_conditions[] = "(s.group_number = ? AND s.student_code = ?)";
                $params[] = $assignment['group_number'];
                $params[] = $assignment['subject_code'];
            }
            $where_clauses[] = "(" . implode(' OR ', $assignment_conditions) . ")";
        }

        if (empty($where_clauses)) {
            return ['total_subjects' => 0, 'total_students' => 0, 'total_files' => 0];
        }

        $where_sql = "WHERE " . implode(' AND ', $where_clauses); // Should only be one clause here

        // Query for total_subjects and total_students
        $stmt_students = $pdo->prepare("
            SELECT
                COUNT(DISTINCT student_code) as total_subjects,
                COUNT(DISTINCT s.id) as total_students
            FROM students s
            {$where_sql}
        ");
        $stmt_students->execute($params);
        $student_stats = $stmt_students->fetch(PDO::FETCH_ASSOC);

        // Query for total_files
        $stmt_files = $pdo->prepare("
            SELECT COUNT(u.id) as total_files
            FROM uploads u
            INNER JOIN students s ON u.student_id = s.id
            {$where_sql}
        ");
        $stmt_files->execute($params);
        $file_stats = $stmt_files->fetch(PDO::FETCH_ASSOC);

        return [
            'total_subjects' => $student_stats['total_subjects'],
            'total_students' => $student_stats['total_students'],
            'total_files' => $file_stats['total_files']
        ];

    } else { // Admin role
        $stats_stmt = $pdo->query("
            SELECT
                COUNT(DISTINCT student_code) as total_subjects,
                COUNT(DISTINCT id) as total_students,
                (SELECT COUNT(*) FROM uploads) as total_files
            FROM students
        ");
        return $stats_stmt->fetch(PDO::FETCH_ASSOC);
    }
}, 300);

// Get recent uploads (cached for 2 minutes)
$recent_cache_key = 'dashboard_recent_uploads_' . ($_SESSION['admin_role'] === 'teacher' ? $_SESSION['admin_id'] : 'admin');
$recent_uploads = cache_remember($recent_cache_key, function() use ($pdo, $combined_assignments) {
    $where_clauses = [];
    $params = [];

    if ($_SESSION['admin_role'] === 'teacher') {
        if (!empty($combined_assignments)) {
            $assignment_conditions = [];
            foreach ($combined_assignments as $assignment) {
                $assignment_conditions[] = "(s.group_number = ? AND s.student_code = ?)";
                $params[] = $assignment['group_number'];
                $params[] = $assignment['subject_code'];
            }
            $where_clauses[] = "(" . implode(' OR ', $assignment_conditions) . ")";
        }

        if (empty($where_clauses)) {
            return [];
        }

        $where_sql = "WHERE " . implode(' AND ', $where_clauses);

        $recent_uploads_stmt = $pdo->prepare("
            SELECT u.*, s.full_name, s.student_code, s.group_number
            FROM uploads u
            LEFT JOIN students s ON u.student_id = s.id
            {$where_sql}
            ORDER BY u.upload_date DESC
            LIMIT 10
        ");
        $recent_uploads_stmt->execute($params);
        return $recent_uploads_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else { // Admin role
        $recent_uploads_stmt = $pdo->query("
            SELECT u.*, s.full_name, s.student_code, s.group_number
            FROM uploads u
            LEFT JOIN students s ON u.student_id = s.id
            ORDER BY u.upload_date DESC
            LIMIT 10
        ");
        return $recent_uploads_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}, 120);

// Get user count (cached for 10 minutes)
$user_count = cache_remember('dashboard_user_count', function() use ($pdo) {
    $user_count_stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_users");
    return $user_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
}, 600);

// Get uploads by subject (cached for 5 minutes)
$uploads_cache_key = 'dashboard_uploads_by_subject_' . ($_SESSION['admin_role'] === 'teacher' ? $_SESSION['admin_id'] : 'admin');
$uploads_by_subject = cache_remember($uploads_cache_key, function() use ($pdo, $combined_assignments) {
    $where_clauses = [];
    $params = [];

    if ($_SESSION['admin_role'] === 'teacher') {
        if (!empty($combined_assignments)) {
            $assignment_conditions = [];
            foreach ($combined_assignments as $assignment) {
                $assignment_conditions[] = "(s.group_number = ? AND s.student_code = ?)";
                $params[] = $assignment['group_number'];
                $params[] = $assignment['subject_code'];
            }
            $where_clauses[] = "(" . implode(' OR ', $assignment_conditions) . ")";
        }

        if (empty($where_clauses)) {
            return [];
        }

        $where_sql = "WHERE " . implode(' AND ', $where_clauses);

        $stmt = $pdo->prepare("
            SELECT s.student_code, COUNT(u.id) as upload_count
            FROM students s
            LEFT JOIN uploads u ON s.id = u.student_id
            {$where_sql}
            GROUP BY s.student_code
            ORDER BY upload_count DESC
            LIMIT 5
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else { // Admin role
        return $pdo->query("
            SELECT s.student_code, COUNT(u.id) as upload_count
            FROM students s
            LEFT JOIN uploads u ON s.id = u.student_id
            GROUP BY s.student_code
            ORDER BY upload_count DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}, 300);

include 'includes/admin_header.php';
include 'includes/admin_sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 20px; color: var(--text-secondary); font-weight: 500;">
            Xoş gəlmisiniz, <?php echo htmlspecialchars($_SESSION['admin_full_name']); ?>! 👋
        </h2>
    </div>

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
                <h3>Qeydiyyatlı Tələbələr</h3>
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

        <?php if ($_SESSION['admin_role'] !== 'teacher'): ?>
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="stat-icon" style="background: rgba(255,255,255,0.2); color: white;">
                ⚙️
            </div>
            <div class="stat-info">
                <h3 style="color: rgba(255,255,255,0.9);">Sistem İstifadəçiləri</h3>
                <p style="color: white;"><?php echo $user_count; ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Dashboard Grid -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 32px;">
        <!-- Recent Uploads -->
        <div class="profile-card">
            <h3>📥 Son Yüklənən Fayllar</h3>
            <?php if (count($recent_uploads) > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 12px 0; text-align: left; font-size: 12px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Fayl</th>
                                <th style="padding: 12px 0; text-align: left; font-size: 12px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Fən</th>
                                <?php if ($_SESSION['admin_role'] !== 'teacher'): ?>
                                <th style="padding: 12px 0; text-align: left; font-size: 12px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Tələbə</th>
                                <?php endif; ?>
                                <th style="padding: 12px 0; text-align: left; font-size: 12px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Tarix</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_uploads as $upload): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 14px 0;">
                                        <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($upload['original_name']); ?></div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                                            <?php echo strtoupper($upload['file_type']); ?>
                                        </div>
                                    </td>
                                    <td style="padding: 14px 0;">
                                        <div style="font-weight: 600; font-size: 14px; color: #667eea;">
                                            <?php echo htmlspecialchars(mb_strtoupper($upload['student_code'], 'UTF-8')); ?>
                                        </div>
                                    </td>
                                    <?php if ($_SESSION['admin_role'] !== 'teacher'): ?>
                                    <td style="padding: 14px 0;">
                                        <div style="font-weight: 500; font-size: 14px;"><?php echo htmlspecialchars($upload['full_name']); ?></div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                                            <?php echo htmlspecialchars(mb_strtoupper($upload['group_number'], 'UTF-8')); ?>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                    <td style="padding: 14px 0; font-size: 13px; color: var(--text-secondary);">
                                        <?php echo date('d.m.Y H:i', strtotime($upload['upload_date'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 0; color: var(--text-secondary);">
                    <div style="font-size: 48px; margin-bottom: 16px;">📂</div>
                    <p>Hələ heç bir fayl yüklənməyib</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Subjects by Uploads -->
        <div class="profile-card">
            <h3>📊 Ən Aktiv Fənlər</h3>
            <div style="margin-top: 20px;">
                <?php if (count($uploads_by_subject) > 0): ?>
                    <?php foreach ($uploads_by_subject as $index => $subject): ?>
                        <div style="margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-weight: 600; font-size: 14px;">
                                    <?php echo htmlspecialchars(mb_strtoupper($subject['student_code'], 'UTF-8')); ?>
                                </span>
                                <span style="font-weight: 700; font-size: 16px; color: #667eea;">
                                    <?php echo $subject['upload_count']; ?>
                                </span>
                            </div>
                            <div style="height: 8px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                                <?php
                                $max_uploads = $uploads_by_subject[0]['upload_count'];
                                $percentage = $max_uploads > 0 ? ($subject['upload_count'] / $max_uploads) * 100 : 0;
                                ?>
                                <div style="height: 100%; width: <?php echo $percentage; ?>%; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 0.3s;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 0; color: var(--text-secondary);">
                        <div style="font-size: 36px; margin-bottom: 12px;">📈</div>
                        <p style="font-size: 13px;">Məlumat yoxdur</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="profile-card" style="margin-top: 32px;">
        <h3>⚡ Sürətli Əməliyyatlar</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 20px;">
            <a href="user.php" class="btn btn-primary" style="text-decoration: none; justify-content: center;">
                👥 Tələbələrə bax
            </a>
            <?php if (hasPermission($pdo, $_SESSION['admin_id'], 'download_files')): ?>
                <form method="POST" action="download.php" style="margin: 0;">
                    <button type="submit" name="download_all" class="btn btn-warning" style="width: 100%;">
                        📥 Bütün faylları yüklə
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($_SESSION['admin_role'] === 'admin'): ?>
                <a href="admin_users.php" class="btn btn-success" style="text-decoration: none; justify-content: center;">
                    ➕ Yeni istifadəçi
                </a>
            <?php endif; ?>
            <a href="admin_profile.php" class="btn btn-secondary" style="text-decoration: none; justify-content: center;">
                👤 Profili redaktə et
            </a>
        </div>
    </div>
</div>

</body>
</html>
