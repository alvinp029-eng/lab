<?php
// process_maintenance.php
session_start();

// Cek login dan role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'kepala_lab') {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

if (!isset($_GET['action']) || !isset($_GET['id'])) {
    header('Location: manage_maintenance.php');
    exit();
}

 $action = $_GET['action'];
 $maintenance_id = (int)$_GET['id'];

// Validasi aksi
if (!in_array($action, ['complete', 'cancel'])) {
    $_SESSION['flash_error'] = "Aksi tidak valid.";
    header('Location: manage_maintenance.php');
    exit();
}

try {
    // Tentukan status dan tanggal akhir berdasarkan aksi
    $status = ($action === 'complete') ? 'completed' : 'cancelled';
    $end_date = ($action === 'complete') ? date('Y-m-d H:i:s') : null;

    $sql = "UPDATE computer_maintenance SET status = :status, end_date = :end_date WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $status,
        ':end_date' => $end_date,
        ':id' => $maintenance_id
    ]);

    // Set pesan sukses
    $message = ($action === 'complete') ? 'Pemeliharaan berhasil ditandai sebagai selesai.' : 'Pemeliharaan berhasil dibatalkan.';
    $_SESSION['flash_message'] = $message;

} catch (PDOException $e) {
    // Set pesan error
    $_SESSION['flash_error'] = "Terjadi kesalahan: " . $e->getMessage();
}

// Redirect kembali ke halaman kelola
header('Location: manage_maintenance.php');
exit();