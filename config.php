<?php
// Ubah bagian koneksi ke PostgreSQL
$host = 'localhost'; 
$dbname = 'bookberryss'; 
$username = 'postgres'; 
$password = 'kamisukses';

try {
    // Koneksi ke PostgreSQL menggunakan PDO
    $conn = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    // Set atribut error mode ke exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Koneksi Gagal: " . $e->getMessage();
}
?>