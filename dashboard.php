<?php
// dashboard.php (KEPALA LAB ONLY VERSION - WITH MAINTENANCE MENU)
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// Ambil data user dari database
try {
    $sql = "SELECT id, name, email, role, nip_nim, phone, department FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] !== 'kepala_lab') {
        // Jika user tidak ditemukan atau bukan kepala_lab, logout
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat data pengguna.");
}

// Ambil statistik dashboard
 $stats = [];
try {
    // Jumlah laboratorium
    $sql = "SELECT COUNT(*) as total FROM laboratories";
    $stmt = $pdo->query($sql);
    $stats['labs'] = $stmt->fetch()['total'];
    
    // Jumlah komputer
    $sql = "SELECT COUNT(*) as total FROM computers";
    $stmt = $pdo->query($sql);
    $stats['computers'] = $stmt->fetch()['total'];
    
    // Jumlah inventaris
    $sql = "SELECT COUNT(*) as total FROM inventory";
    $stmt = $pdo->query($sql);
    $stats['inventory'] = $stmt->fetch()['total'];
    
    // Jumlah pemesanan (reservasi)
    $sql = "SELECT COUNT(*) as total FROM reservations";
    $stmt = $pdo->query($sql);
    $stats['reservations'] = $stmt->fetch()['total'];
    
    // Jumlah permintaan menunggu
    $sql = "SELECT COUNT(*) as total FROM item_borrows WHERE status = 'pending'";
    $stmt = $pdo->query($sql);
    $stats['pending_requests'] = $stmt->fetch()['total'];

    // Jumlah peminjaman aktif
    $sql = "SELECT COUNT(*) as total FROM item_borrows WHERE status IN ('approved', 'borrowed')";
    $stmt = $pdo->query($sql);
    $stats['active_borrows'] = $stmt->fetch()['total'];
    
    // Jumlah barang terlambat
    $sql = "SELECT COUNT(*) as total FROM item_borrows WHERE status = 'overdue'";
    $stmt = $pdo->query($sql);
    $stats['overdue_items'] = $stmt->fetch()['total'];

    // TAMBAHAN: Jumlah pemeliharaan aktif
    $sql = "SELECT COUNT(*) as total FROM computer_maintenance WHERE status = 'in_progress'";
    $stmt = $pdo->query($sql);
    $stats['maintenance_in_progress'] = $stmt->fetch()['total'];
    
    // Permintaan peminjaman terbaru
    $sql = "SELECT ib.id, u.name as user_name, i.item_name, i.item_type, ib.created_at, ib.expected_return_date 
            FROM item_borrows ib
            JOIN users u ON ib.user_id = u.id
            JOIN inventory i ON ib.inventory_id = i.id
            WHERE ib.status = 'pending'
            ORDER BY ib.created_at DESC
            LIMIT 5";
    $stmt = $pdo->query($sql);
    $pending_requests = $stmt->fetchAll();
    
    // Data peminjaman terbaru
    $sql = "SELECT ib.id, u.name as user_name, i.item_name, i.item_type, ib.borrow_date, ib.expected_return_date, ib.status
            FROM item_borrows ib
            JOIN users u ON ib.user_id = u.id
            JOIN inventory i ON ib.inventory_id = i.id
            ORDER BY ib.borrow_date DESC
            LIMIT 5";
    $stmt = $pdo->query($sql);
    $recent_borrows = $stmt->fetchAll();

    // TAMBAHAN: Data pemeliharaan terbaru
    $sql = "SELECT cm.id, c.computer_name, cm.maintenance_type, u.name as technician_name, cm.start_date, cm.status
            FROM computer_maintenance cm
            JOIN computers c ON cm.computer_id = c.id
            LEFT JOIN users u ON cm.technician_id = u.id
            ORDER BY cm.start_date DESC
            LIMIT 5";
    $stmt = $pdo->query($sql);
    $recent_maintenance = $stmt->fetchAll();
    
    // Data pemesanan terbaru
    $sql = "SELECT r.id, u.name as user_name, l.name as lab_name, r.purpose, r.start_time, r.end_time, r.status
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            JOIN laboratories l ON r.lab_id = l.id
            ORDER BY r.start_time DESC
            LIMIT 5";
    $stmt = $pdo->query($sql);
    $recent_reservations = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat data dashboard.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kepala Lab - SILABKOM</title>
    
    <!-- Menghubungkan ke file CSS eksternal -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>SILABKOM</h3>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="nav-link active">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            
            <!-- Menu Kepala Laboratorium dengan Submenu -->
            <div class="menu-item" id="labManagementMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('labManagementMenu'); return false;">
                    <div>
                        <i class="bi bi-building"></i>
                        <span>Manajemen Lab</span>
                    </div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="laboratories.php" class="nav-link">
                        <i class="bi bi-house-door"></i> Laboratorium
                    </a>
                    <a href="computers.php" class="nav-link">
                        <i class="bi bi-pc-display"></i> Komputer
                    </a>
                    <a href="inventory.php" class="nav-link">
                        <i class="bi bi-box-seam"></i> Inventaris
                    </a>
                </div>
            </div>
            
            <!-- Menu Peminjaman dengan Submenu -->
            <div class="menu-item" id="borrowManagementMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('borrowManagementMenu'); return false;">
                    <div>
                        <i class="bi bi-clipboard-check"></i>
                        <span>Activity</span>
                    </div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="manage_borrows.php" class="nav-link">
                        <i class="bi bi-list-check"></i> Kelola Peminjaman
                    </a>
                    <a href="manage_bookings.php" class="nav-link">
                        <i class="bi bi-calendar-check"></i> Pemesanan Lab
                    </a>
                </div>
            </div>

            <!-- TAMBAHAN: Menu Pemeliharaan dengan Submenu -->
            <div class="menu-item" id="maintenanceMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('maintenanceMenu'); return false;">
                    <div>
                        <i class="bi bi-tools"></i>
                        <span>Pemeliharaan</span>
                    </div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="manage_maintenance.php" class="nav-link">
                        <i class="bi bi-list-ul"></i> Daftar Pemeliharaan
                    </a>
                    <a href="add_maintenance.php" class="nav-link">
                        <i class="bi bi-plus-circle"></i> Tambah Pemeliharaan
                    </a>
                </div>
            </div>
            
            <!-- Menu Laporan -->
            <a href="reports.php" class="nav-link">
                <i class="bi bi-graph-up"></i> Laporan
            </a>
            
            <!-- Menu Pengguna -->
            <a href="manage_users.php" class="nav-link">
                <i class="bi bi-people"></i> Kelola Pengguna
            </a>
            
            <!-- Menu Profil -->
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
                    <p class="user-name"><?php echo htmlspecialchars($user['name']); ?></p>
                    <p class="user-role">Kepala Lab</p>
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
            <h1 class="page-title">Dashboard Kepala Lab</h1>
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
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="stat-value"><?php echo $stats['pending_requests']; ?></div>
                <div class="stat-label">Permintaan Menunggu</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="bi bi-box-arrow-up-right"></i>
                </div>
                <div class="stat-value"><?php echo $stats['active_borrows']; ?></div>
                <div class="stat-label">Peminjaman Aktif</div>
            </div>
            <!-- TAMBAHAN: Statistik Pemeliharaan Aktif -->
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['maintenance_in_progress']; ?></div>
                <div class="stat-label">Pemeliharaan Aktif</div>
            </div>
        </div>

        <!-- Recent Borrow Requests -->
        <div class="content-card mb-4">
            <div class="card-header-custom">
                <h3 class="card-title">Permintaan Peminjaman Terbaru</h3>
                <a href="manage_borrows.php" class="btn-custom btn-primary-custom">
                    Kelola Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <?php if (empty($pending_requests)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-check-circle" style="font-size: 3rem; color: #28a745;"></i>
                    <p class="mt-3 text-muted">Tidak ada permintaan peminjaman menunggu</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Peminjam</th>
                                <th>Barang</th>
                                <th>Tipe</th>
                                <th>Tanggal Permintaan</th>
                                <th>Harus Kembali</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['item_type']); ?></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($request['created_at'])); ?></td>
                                    <td><?php echo date('d M Y', strtotime($request['expected_return_date'])); ?></td>
                                    <td>
                                        <a href="process_borrow.php?action=approve&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle"></i> Setujui
                                        </a>
                                        <a href="process_borrow.php?action=reject&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-danger">
                                            <i class="bi bi-x-circle"></i> Tolak
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAMBAHAN: Recent Maintenance Section -->
        <div class="content-card mb-4">
            <div class="card-header-custom">
                <h3 class="card-title">Pemeliharaan Terbaru</h3>
                <a href="manage_maintenance.php" class="btn-custom btn-primary-custom">
                    Kelola Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <?php if (empty($recent_maintenance)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-tools" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Belum ada data pemeliharaan</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Komputer</th>
                                <th>Jenis Pemeliharaan</th>
                                <th>Teknisi</th>
                                <th>Tanggal Mulai</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_maintenance as $maintenance): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($maintenance['computer_name']); ?></td>
                                    <td>
                                        <?php 
                                        $type_labels = [
                                            'hardware' => 'Perangkat Keras',
                                            'software' => 'Perangkat Lunak',
                                            'network' => 'Jaringan',
                                            'cleaning' => 'Pembersihan',
                                            'other' => 'Lainnya'
                                        ];
                                        echo $type_labels[$maintenance['maintenance_type']] ?? ucfirst($maintenance['maintenance_type']);
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($maintenance['technician_name'] ?? 'Belum Ditugaskan'); ?></td>
                                    <td><?php echo date('d M Y', strtotime($maintenance['start_date'])); ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $maintenance['status']; ?>">
                                            <?php 
                                            switch($maintenance['status']) {
                                                case 'in_progress': echo 'Sedang Berjalan'; break;
                                                case 'completed': echo 'Selesai'; break;
                                                case 'cancelled': echo 'Dibatalkan'; break;
                                                default: echo ucfirst($maintenance['status']);
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

        <!-- Recent Activity -->
        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Peminjaman Terbaru</h3>
                <a href="all_borrows.php" class="btn-custom btn-primary-custom">
                    Lihat Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <?php if (empty($recent_borrows)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-box" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Belum ada peminjaman</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Peminjam</th>
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
                                    <td><?php echo htmlspecialchars($borrow['user_name']); ?></td>
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

    <!-- Menghubungkan ke file JavaScript eksternal -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>