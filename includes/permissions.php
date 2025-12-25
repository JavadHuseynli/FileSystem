<?php
// Permission helper functions

function hasPermission($pdo, $user_id, $permission_name) {
    try {
        // Admin həmişə bütün səlahiyyətlərə sahibdir
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin') {
            return true;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = ? AND permission_name = ?");
        $stmt->execute([$user_id, $permission_name]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        handleDatabaseError($e);
        return false;
    }
}

function getUserPermissions($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        handleDatabaseError($e);
        return [];
    }
}

function getAllPermissions($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM permissions ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        handleDatabaseError($e);
        return [];
    }
}

function grantPermission($pdo, $user_id, $permission_name) {
    try {
        $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_name) VALUES (?, ?)");
        $stmt->execute([$user_id, $permission_name]);
        return true;
    } catch (PDOException $e) {
        handleDatabaseError($e);
        return false;
    }
}

function revokePermission($pdo, $user_id, $permission_name) {
    try {
        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission_name = ?");
        $stmt->execute([$user_id, $permission_name]);
        return true;
    } catch (PDOException $e) {
        handleDatabaseError($e);
        return false;
    }
}

function updateUserPermissions($pdo, $user_id, $permissions = []) {
    try {
        // Mövcud səlahiyyətləri sil
        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Yeni səlahiyyətləri əlavə et
        foreach ($permissions as $permission) {
            grantPermission($pdo, $user_id, $permission);
        }

        return true;
    } catch (PDOException $e) {
        handleDatabaseError($e);
        return false;
    }
}
?>
