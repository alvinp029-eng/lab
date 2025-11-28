<?php
// dashboard_guru.php (DASHBOARD KHUSUS UNTUK ROLE GURU)
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Cek apakah role user adalah 'guru'
if ($_SESSION['user_role'] !== 'guru') {
    // Jika bukan guru, redirect ke dashboard yang sesuai atau halaman akses ditolak
    header('Location: dashboard.php'); // atau buat halaman access_denied.php
    exit();
}

require_once 'config.php';

// Ambil data user dari database
 $user = null;
try {
    $sql = "SELECT id, name, email, nip_nim, phone, department, role, avatar, is_active FROM users WHERE id = :id LIMIT 1";
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
    die("Terjadi kesalahan saat memuat data pengguna.");
}

// Ambil statistik dashboard khusus guru
 $stats = [];
 $upcoming_bookings = [];
 $active_borrows = [];

try {
    // Jumlah laboratorium (informasi umum)
    $sql = "SELECT COUNT(*) as total FROM laboratories";
    $stmt = $pdo->query($sql);
    $stats['labs'] = $stmt->fetch()['total'];
    
    // Jumlah komputer (informasi umum)
    $sql = "SELECT COUNT(*) as total FROM computers";
    $stmt = $pdo->query($sql);
    $stats['computers'] = $stmt->fetch()['total'];

    // Statistik pemesanan guru (pending & approved)
    $sql = "SELECT COUNT(*) as total FROM reservations WHERE user_id = :user_id AND status IN ('pending', 'approved')";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
    $stmt->execute();
    $stats['my_bookings'] = $stmt->fetch()['total'];

    // Statistik peminjaman aktif guru (approved & borrowed)
    $sql = "SELECT COUNT(*) as total FROM item_borrows WHERE user_id = :user_id AND status IN ('approved', 'borrowed')";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
    $stmt->execute();
    $stats['my_borrows'] = $stmt->fetch()['total'];

    // Data pemesanan lab mendatang
    $sql = "SELECT r.id, l.name as lab_name, r.purpose, r.start_time, r.end_time, r.status
            FROM reservations r
            JOIN laboratories l ON r.lab_id = l.id
            WHERE r.user_id = :user_id AND r.status IN ('pending', 'approved')
            ORDER BY r.start_time ASC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
    $stmt->execute();
    $upcoming_bookings = $stmt->fetchAll();
    
    // Data peminjaman barang aktif
    $sql = "SELECT ib.id, i.item_name, i.item_type, ib.borrow_date, ib.expected_return_date, ib.status
            FROM item_borrows ib
            JOIN inventory i ON ib.inventory_id = i.id
            WHERE ib.user_id = :user_id AND ib.status IN ('approved', 'borrowed')
            ORDER BY ib.borrow_date DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
    $stmt->execute();
    $active_borrows = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Guru Dashboard error: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat data dashboard.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar Dinamis Berdasarkan Role -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>SILABKOM</h3>
        </div>
        <nav class="sidebar-menu">
            <!-- Menu Khusus Guru -->
            <a href="dashboard_guru.php" class="nav-link active">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="manage_bookings.php" class="nav-link">
                <i class="bi bi-calendar-plus"></i> Pesan Lab
            </a>
            <a href="borrow_items.php" class="nav-link">
                <i class="bi bi-box-arrow-up-right"></i> Pinjam Barang
            </a>
            <a href="return_items.php" class="nav-link">
                <i class="bi bi-box-arrow-in-down-left"></i> Kembalikan Barang
            </a>
            <a href="my_history.php" class="nav-link">
                <i class="bi bi-clock-history"></i> Riwayat Saya
            </a>
            
            <!-- Menu Profil (untuk semua role) -->
            <a href="profile.php" class="nav-link">
                <i class="bi bi-person-circle"></i> Profil
            </a>
        </nav>
        
        <!-- Sidebar Footer (sama dengan di profile.php) -->
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar role-<?php echo $user['role']; ?> <?php echo $user['is_active'] ? '' : 'inactive'; ?>">
                    <?php if ($user['avatar'] && file_exists('uploads/avatars/' . $user['avatar'])): ?>
                        <img src="uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                            <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($user['name']); ?></p>
                    <p class="user-role">
                        <i class="bi bi-book"></i> Guru
                    </p>
                </div>
            </div>
            <a href="profile.php" class="nav-link">
                <i class="bi bi-person-gear"></i> Pengaturan
            </a>
            <a href="logout.php" class="nav-link">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <h1 class="page-title">Dashboard Guru</h1>
            <div>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="bi bi-building"></i>
                </div>
                <div class="stat-value"><?php echo $stats['labs']; ?></div>
                <div class="stat-label">Laboratorium</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="bi bi-pc-display"></i>
                </div>
                <div class="stat-value"><?php echo $stats['computers']; ?></div>
                <div class="stat-label">Komputer</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="stat-value"><?php echo $stats['my_bookings']; ?></div>
                <div class="stat-label">Pemesanan Saya</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="bi bi-box-arrow-up-right"></i>
                </div>
                <div class="stat-value"><?php echo $stats['my_borrows']; ?></div>
                <div class="stat-label">Peminjaman Aktif</div>
            </div>
        </div>

        <!-- Upcoming Bookings -->
        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Pemesanan Lab Mendatang</h3>
                <a href="manage_bookings.php" class="btn-custom btn-primary-custom">
                    <i class="bi bi-calendar-plus"></i> Pesan Lab Baru
                </a>
            </div>
            <?php if (empty($upcoming_bookings)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Anda belum memiliki pemesanan lab.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Lab</th>
                                <th>Tujuan</th>
                                <th>Waktu</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['lab_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['purpose']); ?></td>
                                    <td>
                                        <?php 
                                        $start = new DateTime($booking['start_time']);
                                        $end = new DateTime($booking['end_time']);
                                        echo $start->format('d M Y, H:i') . ' - ' . $end->format('H:i');
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $booking['status']; ?>">
                                            <?php 
                                            switch($booking['status']) {
                                                case 'pending': echo 'Menunggu'; break;
                                                case 'approved': echo 'Disetujui'; break;
                                                case 'rejected': echo 'Ditolak'; break;
                                                case 'completed': echo 'Selesai'; break;
                                                case 'cancelled': echo 'Dibatalkan'; break;
                                                default: echo htmlspecialchars($booking['status']);
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

        <!-- Active Borrows -->
        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Peminjaman Barang Aktif</h3>
                <a href="borrow_items.php" class="btn-custom btn-primary-custom">
                    <i class="bi bi-plus-circle"></i> Pinjam Barang
                </a>
            </div>
            <?php if (empty($active_borrows)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-box" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Anda tidak memiliki peminjaman barang aktif.</p>
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
                            <?php foreach ($active_borrows as $borrow): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($borrow['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($borrow['item_type']); ?></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($borrow['borrow_date'])); ?></td>
                                    <td><?php echo date('d M Y', strtotime($borrow['expected_return_date'])); ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $borrow['status']; ?>">
                                            <?php 
                                            switch($borrow['status']) {
                                                case 'pending': echo 'Menunggu'; break;
                                                case 'approved': echo 'Disetujui'; break;
                                                case 'borrowed': echo 'Dipinjam'; break;
                                                case 'overdue': echo 'Terlambat'; break;
                                                case 'returned': echo 'Dikembalikan'; break;
                                                default: echo htmlspecialchars($borrow['status']);
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