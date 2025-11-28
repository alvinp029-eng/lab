<?php
// borrow_history.php (HALAMAN HISTORY PEMINJAMAN - DIPERBAIKI)
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Hanya siswa dan pengunjung yang bisa mengakses halaman ini
if (!in_array($_SESSION['user_role'], ['siswa', 'pengunjung'])) {
    header('Location: dashboard.php');
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
    error_log("Borrow history error: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat data pengguna.");
}

// Ambil semua riwayat peminjaman user
try {
    $sql = "SELECT ib.*, i.item_name, i.item_type, l.name as lab_name 
            FROM item_borrows ib
            JOIN inventory i ON ib.inventory_id = i.id
            JOIN laboratories l ON i.lab_id = l.id
            WHERE ib.user_id = :user_id
            ORDER BY ib.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $borrow_history = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch borrow history error: " . $e->getMessage());
    $borrow_history = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History Peminjaman - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --primary-color: #0d6efd; --secondary-color: #0a58ca; --light-color: #f8f9fa; --dark-color: #212529; --success-color: #198754; --danger-color: #dc3545; --warning-color: #ffc107; --info-color: #0dcaf0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f7fb; color: var(--dark-color); }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 250px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 20px 0; z-index: 1000; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); }
        .sidebar-header { text-align: center; padding: 0 20px 30px; border-bottom: 1px solid rgba(255, 255, 255, 0.2); }
        .sidebar-header h3 { color: white; font-weight: bold; margin: 0; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu .nav-link { color: rgba(255, 255, 255, 0.8); padding: 12px 25px; display: flex; align-items: center; transition: all 0.3s; position: relative; }
        .sidebar-menu .nav-link:hover, .sidebar-menu .nav-link.active { color: white; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar-menu .nav-link i { margin-right: 10px; font-size: 1.2rem; }
        .notification-badge { position: absolute; top: 8px; right: 20px; background-color: var(--danger-color); color: white; border-radius: 10px; padding: 2px 6px; font-size: 0.7rem; font-weight: bold; }
        .sidebar-footer { position: absolute; bottom: 0; left: 0; right: 0; padding: 20px; border-top: 1px solid rgba(255, 255, 255, 0.2); }
        .user-info { display: flex; align-items: center; color: white; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center; margin-right: 10px; }
        .user-details { flex: 1; }
        .user-name { font-weight: bold; margin: 0; font-size: 0.9rem; }
        .user-role { margin: 0; font-size: 0.8rem; opacity: 0.8; }
        .main-content { margin-left: 250px; padding: 20px; min-height: 100vh; }
        .top-header { background-color: white; border-radius: 10px; padding: 15px 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 1.5rem; font-weight: bold; color: var(--primary-color); margin: 0; }
        .content-card { background-color: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); margin-bottom: 25px; }
        .card-header-custom { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .card-title { font-size: 1.2rem; font-weight: bold; margin: 0; color: var(--primary-color); }
        .table-custom { width: 100%; }
        .table-custom th { border-bottom: 2px solid #eee; font-weight: 600; color: #666; }
        .table-custom td { border-bottom: 1px solid #f5f5f5; vertical-align: middle; }
        .badge-status { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .badge-pending { background-color: #fff3cd; color: #856404; }
        .badge-approved { background-color: #cff4fc; color: #055160; }
        .badge-rejected { background-color: #f8d7da; color: #721c24; }
        .badge-returned { background-color: #d1e7dd; color: #0f5132; }
        .badge-overdue { background-color: #f8d7da; color: #721c24; }
        .badge-lost { background-color: #f8d7da; color: #721c24; }
        .badge-return_requested { background-color: #fff3cd; color: #856404; }
        .filter-tabs { display: flex; margin-bottom: 20px; border-bottom: 1px solid #eee; }
        .filter-tab { padding: 10px 20px; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .filter-tab.active { color: var(--primary-color); border-bottom-color: var(--primary-color); font-weight: 600; }
        .mobile-toggle { display: none; position: fixed; top: 20px; left: 20px; z-index: 1001; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; padding: 10px; font-size: 1.2rem; }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s; } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-toggle { display: block; } }
    </style>
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
            <a href="dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="borrow_items.php" class="nav-link">
                <i class="bi bi-box-arrow-up-right"></i> Peminjaman Barang
            </a>
            <a href="return_items.php" class="nav-link">
                <i class="bi bi-box-arrow-in-down-left"></i> Pengembalian Barang
            </a>
            <a href="borrow_history.php" class="nav-link active">
                <i class="bi bi-clock-history"></i> History Peminjaman
            </a>
            <a href="notifications.php" class="nav-link">
                <i class="bi bi-bell"></i> Notifikasi
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
                    <p class="user-name"><?php echo htmlspecialchars($user['name']); ?></p>
                    <p class="user-role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></p>
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
            <h1 class="page-title">History Peminjaman</h1>
            <div>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>

        <!-- Borrow History -->
        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Riwayat Peminjaman Barang</h3>
                <a href="borrow_items.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Ajukan Peminjaman Baru
                </a>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="filter-tab active" data-filter="all">Semua</div>
                <div class="filter-tab" data-filter="pending">Menunggu</div>
                <div class="filter-tab" data-filter="approved">Disetujui</div>
                <div class="filter-tab" data-filter="return_requested">Menunggu Konfirmasi</div>
                <div class="filter-tab" data-filter="returned">Dikembalikan</div>
                <div class="filter-tab" data-filter="rejected">Ditolak</div>
                <div class="filter-tab" data-filter="overdue">Terlambat</div>
            </div>

            <?php if (empty($borrow_history)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-clock-history" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Belum ada riwayat peminjaman</p>
                    <a href="borrow_items.php" class="btn btn-primary">Ajukan Peminjaman</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom" id="borrowTable">
                        <thead>
                            <tr>
                                <th>Tanggal Diajukan</th>
                                <th>Barang</th>
                                <th>Tipe</th>
                                <th>Laboratorium</th>
                                <th>Tujuan</th>
                                <th>Tanggal Pinjam</th>
                                <th>Harus Kembali</th>
                                <th>Tanggal Kembali</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($borrow_history as $borrow): ?>
                                <tr data-status="<?php echo $borrow['status']; ?>">
                                    <td><?php echo date('d M Y, H:i', strtotime($borrow['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($borrow['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($borrow['item_type']); ?></td>
                                    <td><?php echo htmlspecialchars($borrow['lab_name']); ?></td>
                                    <td><?php echo htmlspecialchars($borrow['borrow_purpose']); ?></td>
                                    <td><?php echo $borrow['borrow_date'] ? date('d M Y, H:i', strtotime($borrow['borrow_date'])) : '-'; ?></td>
                                    <td><?php echo date('d M Y', strtotime($borrow['expected_return_date'])); ?></td>
                                    <td><?php echo $borrow['actual_return_date'] ? date('d M Y, H:i', strtotime($borrow['actual_return_date'])) : '-'; ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $borrow['status']; ?>">
                                            <?php 
                                            switch($borrow['status']) {
                                                case 'pending': echo 'Menunggu'; break;
                                                case 'approved': echo 'Disetujui'; break;
                                                case 'return_requested': echo 'Menunggu Konfirmasi'; break;
                                                case 'rejected': echo 'Ditolak'; break;
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
    <script>
    // Toggle sidebar for mobile
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        
        if (window.innerWidth <= 992 && 
            !sidebar.contains(event.target) && 
            !sidebarToggle.contains(event.target) && 
            sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth > 992) {
            sidebar.classList.remove('active');
        }
    });

    // Filter functionality
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Get filter value
            const filter = this.getAttribute('data-filter');
            
            // Filter table rows
            const rows = document.querySelectorAll('#borrowTable tbody tr');
            rows.forEach(row => {
                if (filter === 'all' || row.getAttribute('data-status') === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
    </script>
</body>
</html>