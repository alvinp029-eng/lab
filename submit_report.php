<?php
// submit_report.php
header('Content-Type: application/json');
require_once '../config.php';

// Cek apakah user sudah login
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

 $lab_id = $_POST['lab_id'];
 $computer_id = $_POST['computer_id'];
 $description = trim($_POST['description']);
 $user_id = $_SESSION['user_id'];

// Validasi input sederhana
if (empty($lab_id) || empty($computer_id) || empty($description)) {
    echo json_encode(['status' => 'error', 'message' => 'Semua field harus diisi.']);
    exit();
}

try {
    // Simpan laporan ke database
    $sql = "INSERT INTO maintenance (computer_id, lab_id, reported_by, description, status) VALUES (:computer_id, :lab_id, :user_id, :description, 'pending')";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':computer_id', $computer_id, PDO::PARAM_INT);
    $stmt->bindParam(':lab_id', $lab_id, PDO::PARAM_INT);
    $stmt->bindParam(':reported_by', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->execute();

    echo json_encode(['status' => 'success', 'message' => 'Laporan berhasil dikirim!']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan laporan.']);
}
?>