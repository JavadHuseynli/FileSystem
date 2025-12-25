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

$current_page = 'profile';
$page_title = 'Profil';

$success_message = '';
$error_message = '';

// Password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($current_password, $user['password'])) {
        $error_message = "Cari şifrə yanlışdır!";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Yeni şifrələr uyğun gəlmir!";
    } elseif (strlen($new_password) < 4) {
        $error_message = "Şifrə ən azı 4 simvol olmalıdır!";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $_SESSION['admin_id']]);
        $success_message = "Şifrə uğurla dəyişdirildi!";
    }
}

// Get user permissions
$user_permissions = $_SESSION['admin_role'] === 'admin' ? getAllPermissions($pdo) : getUserPermissions($pdo, $_SESSION['admin_id']);

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

    <div class="profile-grid">
        <!-- Profile Info -->
        <div class="profile-card">
            <h3>👤 Profil Məlumatları</h3>
            <div class="info-group">
                <label>İstifadəçi adı</label>
                <div class="value"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></div>
            </div>
            <div class="info-group">
                <label>Ad Soyad</label>
                <div class="value"><?php echo htmlspecialchars($_SESSION['admin_full_name']); ?></div>
            </div>
            <div class="info-group">
                <label>Rol</label>
                <div class="value">
                    <span class="badge badge-<?php echo $_SESSION['admin_role']; ?>">
                        <?php echo $_SESSION['admin_role'] === 'admin' ? 'Administrator' : 'Müəllim'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="profile-card">
            <h3>🔒 Şifrəni Dəyişdir</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Cari Şifrə</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>Yeni Şifrə</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Yeni Şifrə (Təkrar)</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary">Şifrəni Dəyişdir</button>
            </form>
        </div>
    </div>

    <!-- User Permissions -->
    <div class="profile-card">
        <h3>🔐 Mənim Səlahiyyətlərim</h3>
        <?php if ($_SESSION['admin_role'] === 'admin'): ?>
            <div style="padding: 20px; text-align: center; background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border-radius: 12px;">
                <div style="font-size: 48px; margin-bottom: 12px;">👑</div>
                <h4 style="margin: 0; color: #667eea;">Administrator</h4>
                <p style="color: var(--text-secondary); margin-top: 8px;">Bütün sistem səlahiyyətlərinə sahibsiniz</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                <?php
                $all_perms = getAllPermissions($pdo);
                $user_perm_names = is_array($user_permissions) ? $user_permissions : [];

                foreach ($all_perms as $perm):
                    $has_perm = in_array($perm['name'], $user_perm_names);
                ?>
                    <div style="display: flex; align-items: center; gap: 10px; padding: 14px; background: <?php echo $has_perm ? 'linear-gradient(135deg, rgba(17, 153, 142, 0.1), rgba(56, 239, 125, 0.1))' : '#f8f9fd'; ?>; border-radius: 8px; border: 2px solid <?php echo $has_perm ? '#11998e' : 'transparent'; ?>;">
                        <div style="font-size: 24px;"><?php echo $has_perm ? '✓' : '✗'; ?></div>
                        <div>
                            <div style="font-weight: 600; font-size: 13px; color: <?php echo $has_perm ? '#11998e' : '#e53e3e'; ?>;">
                                <?php echo htmlspecialchars($perm['description']); ?>
                            </div>
                            <div style="font-size: 11px; color: #718096; margin-top: 2px;"><?php echo $perm['name']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
