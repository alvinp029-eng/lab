<?php
session_start();

// Cek apakah user sudah login dan role-nya adalah 'siswa'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'siswa') {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// Ambil data user dari database
try {
    $sql = "SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Student Dashboard error: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat data pengguna.");
}

// Ambil statistik dashboard
 $stats = [];
try {
    // PERBAIKAN: Query ini disesuaikan dengan struktur database baru.
    // Kita tidak lagi menggunakan kolom 'status' di tabel 'inventory'.
    // Sebaliknya, kita menghitung barang yang memiliki quantity > 0.
    $sql = "SELECT COUNT(*) as total FROM inventory WHERE is_borrowable = 1 AND quantity > 0";
    $stmt = $pdo->query($sql);
    $stats['available_inventory'] = $stmt->fetch()['total'];

    // Jumlah peminjaman aktif siswa ini
    $sql = "SELECT COUNT(*) as total FROM item_borrows WHERE user_id = :user_id AND status IN ('approved', 'borrowed')";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $stats['active_borrows'] = $stmt->fetch()['total'];

} catch (PDOException $e) {
    error_log("Student Dashboard error: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat data dashboard.");
}

// Ambil data peminjaman terbaru untuk user ini
 $recent_borrows = [];
try {
    $sql = "SELECT ib.id, i.item_name, i.item_type, ib.borrow_date, ib.expected_return_date, ib.status
            FROM item_borrows ib
            JOIN inventory i ON ib.inventory_id = i.id
            WHERE ib.user_id = :user_id
            ORDER BY ib.borrow_date DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $recent_borrows = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Student Dashboard error: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat data peminjaman.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar khusus siswa -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>SILABKOM</h3>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard_student.php" class="nav-link active">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="borrow_items.php" class="nav-link">
                <i class="bi bi-box-arrow-up-right"></i> Pinjam Barang
            </a>
            <a href="return_items.php" class="nav-link">
                <i class="bi bi-box-arrow-in-down-left"></i> Pengembalian Barang
            </a>
            <a href="my_borrows.php" class="nav-link"><i class="bi bi-clock-history"></i> Riwayat Peminjaman</a>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
            
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($user['name']); ?></p>
                    <p class="user-role">Siswa</p>
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
            <h1 class="page-title">Dashboard</h1>
            <div>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="stat-value"><?php echo $stats['available_inventory']; ?></div>
                <div class="stat-label">Inventaris Tersedia</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="bi bi-box-arrow-up-right"></i>
                </div>
                <div class="stat-value"><?php echo $stats['active_borrows']; ?></div>
                <div class="stat-label">Peminjaman Aktif</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Aksi Cepat</h3>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="d-grid">
                        <a href="borrow_items.php" class="btn btn-primary-custom btn-lg">
                            <i class="bi bi-box-arrow-up-right"></i> Ajukan Peminjaman Barang
                        </a>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="d-grid">
                        <a href="my_borrows.php" class="btn btn-success btn-lg">
                            <i class="bi bi-clock-history"></i> Lihat Riwayat Peminjaman
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabel Riwayat Peminjaman Saya -->
        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Peminjaman Terbaru Saya</h3>
                <a href="my_borrows.php" class="btn-custom btn-primary-custom">
                    Lihat Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <?php if (empty($recent_borrows)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-box" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Anda belum pernah meminjam barang.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Tipe</th>
                                <th>Tanggal Pinjam</th>
                                <th>Harus Kembali</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_borrows as $borrow): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($borrow['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($borrow['item_type']); ?></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($borrow['borrow_date'])); ?></td>
                                    <td><?php echo date('d M Y', strtotime($borrow['expected_return_date'])); ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $borrow['status']; ?>">
                                            <?php 
                                            // INTEGRASI & PERBAIKAN: Tambahkan status 'return_requested'
                                            switch($borrow['status']) {
                                                case 'pending': echo 'Menunggu Persetujuan'; break;
                                                case 'approved': echo 'Disetujui'; break;
                                                case 'borrowed': echo 'Dipinjam'; break;
                                                case 'return_requested': echo 'Menunggu Konfirmasi Pengembalian'; break; // BARU
                                                case 'returned': echo 'Dikembalikan'; break;
                                                case 'overdue': echo 'Terlambat'; break;
                                                case 'lost': echo 'Hilang'; break;
                                                default: echo $borrow['status'];
                                            }
                                            ?>
                                        </span>
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