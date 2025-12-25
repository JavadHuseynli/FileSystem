<?php
// PostgreSQL bağlantı parametrləri
$host = "172.18.250.22";
$port = "5432";
$dbname = "salam";
$user = "postgres";
$password = "S@l@m2015";

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
