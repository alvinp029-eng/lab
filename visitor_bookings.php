<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'pengunjung') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

// Ambil data pemesanan milik pengunjung ini
 $reservations = [];
try {
    // Query ini sudah benar dan mengambil semua kolom yang diperlukan, termasuk rejected_reason
    $sql = "SELECT r.*, l.name as lab_name 
            FROM reservations r
            JOIN laboratories l ON r.lab_id = l.id
            WHERE r.user_id = :user_id
            ORDER BY r.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal memuat data pemesanan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pemesanan Saya - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Sidebar khusus pengunjung -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>SILABKOM</h3>
        </div>
        <nav class="sidebar-menu">
            <a href="visitor_home.php" class="nav-link">
                <i class="bi bi-house-door"></i> Beranda
            </a>
            <a href="visitor_bookings.php" class="nav-link active">
                <i class="bi bi-calendar-check"></i> Pemesanan Saya
            </a>
            <a href="profile.php" class="nav-link">
                <i class="bi bi-person-circle"></i> Profil
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="user-role">Pengunjung</p>
                </div>
            </div>
            <a href="logout.php" class="nav-link" style="margin-top: 15px;">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <h1 class="page-title">Daftar Pemesanan Saya</h1>
            <span><?php echo date('d F Y'); ?></span>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Riwayat Pemesanan Laboratorium</h3>
                <a href="visitor_home.php" class="btn-custom btn-primary-custom">
                    <i class="bi bi-plus-circle"></i> Buat Pemesanan Baru
                </a>
            </div>
            
            <?php if (empty($reservations)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Anda belum pernah melakukan pemesanan.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Laboratorium</th>
                                <th>Tujuan</th>
                                <th>Waktu</th>
                                <th>Status</th>
                                <!-- PERBAIKAN: Tambahkan kolom untuk alasan penolakan -->
                                <th>Alasan/Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $res): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($res['lab_name']); ?></td>
                                    <td><?php echo htmlspecialchars($res['purpose']); ?></td>
                                    <td>
                                        <?php 
                                        $start = new DateTime($res['start_time']);
                                        $end = new DateTime($res['end_time']);
                                        echo $start->format('d M Y, H:i') . ' - ' . $end->format('H:i');
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $res['status']; ?>">
                                            <?php 
                                            switch($res['status']){
                                                case 'pending': echo 'Menunggu Persetujuan'; break;
                                                case 'approved': echo 'Disetujui'; break;
                                                case 'rejected': echo 'Ditolak'; break;
                                                case 'completed': echo 'Selesai'; break;
                                                case 'cancelled': echo 'Dibatalkan'; break;
                                                default: echo $res['status'];
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <!-- PERBAIKAN: Tampilkan alasan penolakan jika statusnya 'rejected' -->
                                    <td>
                                        <?php 
                                        if ($res['status'] === 'rejected' && !empty($res['rejected_reason'])) {
                                            echo htmlspecialchars($res['rejected_reason']);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>