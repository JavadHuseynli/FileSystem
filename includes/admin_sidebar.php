<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h2>📁 File System</h2>
        <p>İdarəetmə Paneli</p>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?php echo ($current_page ?? '') === 'dashboard' ? 'active' : ''; ?>">
                <span class="icon">📊</span>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="user.php" class="<?php echo ($current_page ?? '') === 'students' ? 'active' : ''; ?>">
                <span class="icon">👥</span>
                <span>Tələbələr</span>
            </a>
        </li>
        <li>
            <a href="admin_profile.php" class="<?php echo ($current_page ?? '') === 'profile' ? 'active' : ''; ?>">
                <span class="icon">👤</span>
                <span>Profil</span>
            </a>
        </li>
        <?php if (($_SESSION['admin_role'] ?? '') === 'admin'): ?>
        <li>
            <a href="admin_users.php" class="<?php echo ($current_page ?? '') === 'users' ? 'active' : ''; ?>">
                <span class="icon">⚙️</span>
                <span>İstifadəçilər</span>
            </a>
        </li>
        <li>
            <a href="admin_students.php" class="<?php echo ($current_page ?? '') === 'students_admin' ? 'active' : ''; ?>">
                <span class="icon">🎓</span>
                <span>Tələbə İdarəetməsi</span>
            </a>
        </li>
        <li>
            <a href="admin_backups.php" class="<?php echo ($current_page ?? '') === 'backups' ? 'active' : ''; ?>">
                <span class="icon">🗄️</span>
                <span>Ehtiyat Nüsxə</span>
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="logout.php">
                <span class="icon">🚪</span>
                <span>Çıxış</span>
            </a>
        </li>
    </ul>
</div>
