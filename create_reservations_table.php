<?php
// create_reservations_table.php - Membuat tabel reservations
require_once 'config.php';

echo "<h2>Membuat Tabel Reservations</h2>";

try {
    // SQL untuk membuat tabel reservations
    $createTableSQL = "CREATE TABLE IF NOT EXISTS `reservations` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `lab_id` int(11) NOT NULL,
      `purpose` text NOT NULL,
      `participants` int(11) DEFAULT NULL,
      `start_time` datetime NOT NULL,
      `end_time` datetime NOT NULL,
      `notes` text DEFAULT NULL,
      `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `approved_by` int(11) DEFAULT NULL,
      `approved_at` datetime DEFAULT NULL,
      `rejected_by` int(11) DEFAULT NULL,
      `rejected_at` datetime DEFAULT NULL,
      `rejected_reason` text DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_reservations_user_id` (`user_id`),
      KEY `idx_reservations_lab_id` (`lab_id`),
      KEY `idx_reservations_status` (`status`),
      KEY `idx_reservations_start_time` (`start_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($pdo->exec($createTableSQL)) {
        echo "<p style='color: green;'>✓ Tabel 'reservations' berhasil dibuat!</p>";
        
        // Insert data contoh jika tabel baru dibuat
        $insertSampleSQL = "INSERT INTO `reservations` (user_id, lab_id, purpose, participants, start_time, end_time, status) 
                          VALUES (1, 1, 'Test Booking', 5, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'pending')";
        
        if ($pdo->exec($insertSampleSQL)) {
            echo "<p style='color: green;'>✓ Data contoh berhasil ditambahkan!</p>";
        }
    } else {
        echo "<p style='color: red;'>⚠ Gagal membuat tabel: " . $pdo->errorInfo()[2] . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Error:</h2>";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color: red;'>File: " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p style='color: red;'>Line: " . $e->getLine() . "</p>";
    echo "<pre style='color: red;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>