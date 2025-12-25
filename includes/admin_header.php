<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Panel'; ?> - Fayl İdarəetmə Sistemi</title>
    <link rel="stylesheet" href="admin_modern.css">
    <style>
        <?php if (isset($extra_css)) echo $extra_css; ?>
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <div class="topbar">
        <div class="topbar-left">
            <h1><?php echo $page_title ?? 'Admin Panel'; ?></h1>
        </div>
        <div class="topbar-right">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['admin_full_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="user-info">
                    <span class="name"><?php echo htmlspecialchars($_SESSION['admin_full_name'] ?? 'Admin'); ?></span>
                    <span class="role"><?php echo ($_SESSION['admin_role'] ?? 'admin') === 'admin' ? 'Administrator' : 'Müəllim'; ?></span>
                </div>
            </div>
        </div>
    </div>
