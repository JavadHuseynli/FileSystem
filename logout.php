<?php
// Sessiyanı başlat
session_save_path(__DIR__ . '/sessions');
session_start();

// Yönləndirmə üçün admin olub-olmadığını yoxla
$is_admin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// Bütün sessiya dəyişənlərini təmizlə
$_SESSION = array();

// Sessiya cookie-sini sil
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Sessiyanı sonlandır
session_destroy();

// Admin idisə admin login-ə, deyilsə index-ə yönləndir
if ($is_admin) {
    header('Location: admin_login.php');
} else {
    header('Location: index.php');
}
exit();
?>