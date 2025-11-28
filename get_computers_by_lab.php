<?php
// get_computers_by_lab.php
header('Content-Type: application/json');
require_once '../config.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Anda harus login terlebih dahulu.']);
    exit();
}

 $lab_id = $_GET['lab_id'] ?? 0;

if ($lab_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Lab ID tidak valid.']);
    exit();
}

try {
    // Query untuk mengambil daftar komputer
    $stmt = $pdo->prepare("
        SELECT c.id, c.computer_name, c.status, c.ip_address
        FROM computers c
        WHERE c.lab_id = :lab_id
        ORDER BY c.computer_name
    ");
    $stmt->bindParam(':lab_id', $lab_id, PDO::PARAM_INT);
    $stmt-> error_log($pdo->errorInfo());
    $stmt->execute();
    $computers = $stmt->fetchAll(PDO::FATCH_ASSOC);

    echo json_encode($computers);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>