<?php
// PostgreSQL bağlantı parametrləri
$host = "YOUR API ADDRES";
$port = "xxx";
$dbname = "xxx";
$user = "xxxx";
$password = "xxx";

// Bağlantı cümləsi
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password";

// Bağlantı yoxlaması
$conn = pg_connect($conn_string);

if (!$conn) {
    echo "❌ Bağlantı alınmadı: " . pg_last_error();
} else {
    echo "✅ PostgreSQL-ə uğurla qoşuldu!";
    pg_close($conn);
}
?>

