<?php
// Sessiyanı başlat
session_save_path(__DIR__ . '/sessions');
session_start();

// Bütün sessiya dəyişənlərini təmizlə
$_SESSION = array();

// Sessiya cookie-sini sil
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Sessiyanı sonlandır
session_destroy();

// İstifadəçini giriş səhifəsinə yönləndir
header('Location: admin_login.php');
exit();
?>