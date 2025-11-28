<?php
// File: diagnosa.php
// Tujuan: Melihat struktur tabel 'inventory' dan menguji koneksi database

require_once 'config.php';

echo "<h1>Diagnosa Tabel 'inventory'</h1>";
echo "<p>Skrip ini akan mencoba terhubung ke database dan menampilkan daftar kolom di tabel 'inventory'.</p>";

try {
    // 1. Uji Koneksi
    $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    echo "<p style='color: green;'><strong>Sukses:</strong> Koneksi ke database berhasil.</p>";

    // 2. Ambil Nama Kolom dari Tabel 'inventory'
    $stmt = $pdo->prepare("DESCRIBE `inventory`");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<h2>Kolom yang Ditemukan di Tabel 'inventory':</h2>";
    if (empty($columns)) {
        echo "<p style='color: red;'><strong>Error:</strong> Tidak bisa mengambil daftar kolom. Mungkin tabel 'inventory' tidak ada atau user database tidak memiliki izin untuk membaca strukturnya.</p>";
    } else {
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li><code>" . htmlspecialchars($column) . "</code></li>";
        }
        echo "</ul>";
    }

} catch (PDOException $e) {
    // 3. Tangkap Error Koneksi atau Query
    echo "<p style='color: red;'><strong>Gagal:</strong> Terjadi kesalahan.</p>";
    echo "<p><strong>Pesan Error:</strong> " . $e->getMessage() . "</p>";
}
?>