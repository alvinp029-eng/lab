<?php
/**
 * File Konfigurasi Koneksi Database SILABKOM
 * 
 * File ini menangani koneksi ke database MySQL menggunakan PDO.
 * Semua detail koneksi disimpan dalam konstanta untuk kemudahan perubahan.
 * 
 * @author  Z.ai Assistant
 * @version  2.0 - Diperbarui untuk kompatibilitas phpMyAdmin 8
 */

// --- Detail Koneksi Database ---
// Sesuaikan dengan pengaturan server lokal Anda (XAMPP, WAMP, MAMP, dll.)
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Ganti jika username database Anda berbeda
define('DB_PASS', '');     // Ganti jika password database Anda memiliki password
define('DB_NAME', 'silabkom_db');
define('DB_CHARSET', 'utf8mb4');

// --- Opsi PDO yang Direkomendasikan ---
// Aktifkan mode error untuk debugging (non-produksi)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Membuat Koneksi PDO ---
try {
    // Buat instance PDO baru dengan DSN yang lengkap
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_PERSISTENT => false, // Non-persistent connection untuk phpMyAdmin 8
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // Jika koneksi gagal, hentikan skrip dan tampilkan pesan error.
    die("ERROR: Tidak dapat terhubung ke database. <br>Pesan Kesalahan: " . $e->getMessage());
}

?>