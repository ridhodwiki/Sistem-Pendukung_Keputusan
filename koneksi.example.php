<?php
// ============================================
// koneksi.example.php — Template Konfigurasi Database
// ============================================
// Cara pakai:
//   1. Salin file ini: copy koneksi.example.php koneksi.php
//   2. Isi nilai variabel di bawah sesuai konfigurasi lokal Anda
//   3. File koneksi.php sudah di-.gitignore, aman dari GitHub
// ============================================

$host = "localhost";
$user = "DB_USERNAME";   // ganti: username MySQL Anda (contoh: root)
$pass = "DB_PASSWORD";   // ganti: password MySQL Anda (kosong jika tidak ada)
$db   = "DB_NAME";       // ganti: nama database (contoh: db_spk_ta)

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Sistem sedang dalam maintenance. Silakan hubungi administrator.");
}

if (!$conn->set_charset("utf8mb4")) {
    error_log("Error setting charset utf8mb4: " . $conn->error);
}
?>
