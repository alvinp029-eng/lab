<?php
// all_borrows.php (VERSI PERBAIKAN)
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
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
    // Tampilkan error untuk debugging. NONAKTIFKAN di lingkungan produksi!
    die("Error loading user data: " . $e->getMessage());
}

// Pagination
 $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
 $perPage = 10;
 $offset = ($page - 1) * $perPage;

// Filter
 $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
 $search = isset($_GET['search']) ? $_GET['search'] : '';

// Ambil data peminjaman
try {
    // --- Query untuk Menghitung Total Data ---
    $count_sql = "SELECT COUNT(*) as total FROM item_borrows ib
                  JOIN users u ON ib.user_id = u.id
                  JOIN inventory i ON ib.inventory_id = i.id WHERE 1=1";

    // Jika role siswa/pengunjung, hanya tampilkan data mereka
    if ($user['role'] === 'siswa' || $user['role'] === 'pengunjung') {
        $count_sql .= " AND ib.user_id = :user_id";
    }
    
    // Tambahkan filter status
    if (!empty($status_filter)) {
        $count_sql .= " AND ib.status = :status";
    }
    
    // Tambahkan filter pencarian
    if (!empty($search)) {
        $count_sql .= " AND (i.item_name LIKE :search OR u.name LIKE :search)";
    }

    $stmt = $pdo->prepare($count_sql);
    
    // Bind parameter untuk query count
    if ($user['role'] === 'siswa' || $user['role'] === 'pengunjung') {
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    }
    if (!empty($status_filter)) {
        $stmt->bindParam(':status', $status_filter);
    }
    if (!empty($search)) {
        $search_param = "%$search%";
        $stmt->bindParam(':search', $search_param);
    }

    $stmt->execute();
    $total_records = $stmt->fetch()['total'];
    $pages = ceil($total_records / $perPage);


    // --- Query untuk Mengambil Data dengan Pagination ---
    // Hanya mengambil kolom yang sudah pasti ada
    $sql = "SELECT 
                ib.id, 
                ib.borrow_date, 
                ib.expected_return_date, 
                ib.status,
                u.name AS user_name,
                u.role AS user_role,
                i.item_name,
                i.item_type
            FROM item_borrows ib
            JOIN users u ON ib.user_id = u.id
            JOIN inventory i ON ib.inventory_id = i.id WHERE 1=1";

    // Jika role siswa/pengunjung, hanya tampilkan data mereka
    if ($user['role'] === 'siswa' || $user['role'] === 'pengunjung') {
        $sql .= " AND ib.user_id = :user_id";
    }
    
    // Tambahkan filter status
    if (!empty($status_filter)) {
        $sql .= " AND ib.status = :status";
    }
    
    // Tambahkan filter pencarian
    if (!empty($search)) {
        $sql .= " AND (i.item_name LIKE :search OR u.name LIKE :search)";
    }
    
    $sql .= " ORDER BY ib.borrow_date DESC LIMIT :offset, :perPage";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameter untuk query data
    if ($user['role'] === 'siswa' || $user['role'] === 'pengunjung') {
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    }
    if (!empty($status_filter)) {
        $stmt->bindParam(':status', $status_filter);
    }
    if (!empty($search)) {
        $search_param = "%$search%";
        $stmt->bindParam(':search', $search_param);
    }
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
    
    $stmt->execute();
    $borrows = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Tampilkan error untuk debugging. NONAKTIFKAN di lingkungan produksi!
    die("SQL Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Peminjaman - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* Salin semua CSS dari file sebelumnya di sini */
        :root { 
            --primary-color: #0d6efd; 
            --secondary-color: #0a58ca; 
            --light-color: #f8f9fa; 
            --dark-color: #212529; 
            --success-color: #198754; 
            --danger-color: #dc3545; 
            --warning-color: #ffc107; 
            --info-color: #0dcaf0; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f5f7fb; 
            color: var(--dark-color); 
        }
        
        .sidebar { 
            position: fixed; 
            top: 0; 
            left: 0; 
            height: 100vh; 
            width: 250px; 
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); 
            padding: 20px 0; 
            z-index: 1000; 
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); 
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        .sidebar-header { 
            text-align: center; 
            padding: 0 20px 30px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.2); 
        }
        
        .sidebar-header h3 { 
            color: white; 
            font-weight: bold; 
            margin: 0; 
        }
        
        .sidebar-menu { 
            padding: 20px 0; 
        }
        
        .sidebar-menu .nav-link { 
            color: rgba(255, 255, 255, 0.8); 
            padding: 12px 25px; 
            display: flex; 
            align-items: center; 
            transition: all 0.3s; 
            text-decoration: none;
            position: relative;
        }
        
        .sidebar-menu .nav-link:hover, .sidebar-menu .nav-link.active { 
            color: white; 
            background-color: rgba(255, 255, 255, 0.1); 
        }
        
        .sidebar-menu .nav-link i { 
            margin-right: 10px; 
            font-size: 1.2rem; 
            width: 20px;
            text-align: center;
        }
        
        .menu-item {
            position: relative;
        }
        
        .menu-item .nav-link {
            justify-content: space-between;
        }
        
        .menu-item .dropdown-icon {
            transition: transform 0.3s ease;
            font-size: 0.8rem;
        }
        
        .menu-item.open .dropdown-icon {
            transform: rotate(180deg);
        }
        
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .menu-item.open .submenu {
            max-height: 500px;
        }
        
        .submenu .nav-link {
            padding-left: 55px;
            font-size: 0.9rem;
        }
        
        .sidebar-footer { 
            position: absolute; 
            bottom: 0; 
            left: 0; 
            right: 0; 
            padding: 20px; 
            border-top: 1px solid rgba(255, 255, 255, 0.2); 
        }
        
        .user-info { 
            display: flex; 
            align-items: center; 
            color: white; 
        }
        
        .user-avatar { 
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            background-color: rgba(255, 255, 255, 0.2); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-right: 10px; 
        }
        
        .user-details { 
            flex: 1; 
        }
        
        .user-name { 
            font-weight: bold; 
            margin: 0; 
            font-size: 0.9rem; 
        }
        
        .user-role { 
            margin: 0; 
            font-size: 0.8rem; 
            opacity: 0.8; 
        }
        
        .main-content { 
            margin-left: 250px; 
            padding: 20px; 
            min-height: 100vh; 
            transition: margin-left 0.3s ease;
        }
        
        .top-header { 
            background-color: white; 
            border-radius: 10px; 
            padding: 15px 25px; 
            margin-bottom: 25px; 
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .page-title { 
            font-size: 1.5rem; 
            font-weight: bold; 
            color: var(--primary-color); 
            margin: 0; 
        }
        
        .content-card { 
            background-color: white; 
            border-radius: 10px; 
            padding: 25px; 
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); 
            margin-bottom: 25px; 
        }
        
        .card-header-custom { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            padding-bottom: 15px; 
            border-bottom: 1px solid #eee; 
        }
        
        .card-title { 
            font-size: 1.2rem; 
            font-weight: bold; 
            margin: 0; 
            color: var(--primary-color); 
        }
        
        .btn-custom { 
            padding: 8px 16px; 
            border-radius: 6px; 
            font-weight: 500; 
            text-decoration: none; 
            transition: all 0.3s; 
        }
        
        .btn-primary-custom { 
            background-color: var(--primary-color); 
            color: white; 
        }
        
        .btn-primary-custom:hover { 
            background-color: var(--secondary-color); 
            color: white; 
        }
        
        .table-custom { 
            width: 100%; 
        }
        
        .table-custom th { 
            border-bottom: 2px solid #eee; 
            font-weight: 600; 
            color: #666; 
        }
        
        .table-custom td { 
            border-bottom: 1px solid #f5f5f5; 
            vertical-align: middle; 
        }
        
        .badge-status { 
            padding: 5px 10px; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: 500; 
        }
        
        .badge-pending { background-color: #fff3cd; color: #856404; }
        .badge-approved { background-color: #d1e7dd; color: #0f5132; }
        .badge-borrowed { background-color: #cff4fc; color: #055160; }
        .badge-returned { background-color: #d1e7dd; color: #0f5132; }
        .badge-overdue { background-color: #f8d7da; color: #721c24; }
        .badge-lost { background-color: #f8d7da; color: #721c24; }
        .badge-rejected { background-color: #f8d7da; color: #721c24; }
        
        .mobile-toggle { 
            display: none; 
            position: fixed; 
            top: 20px; 
            left: 20px; 
            z-index: 1001; 
            background-color: var(--primary-color); 
            color: white; 
            border: none; 
            border-radius: 5px; 
            padding: 10px; 
            font-size: 1.2rem; 
        }
        
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .pagination {
            justify-content: center;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        @media (max-width: 992px) { 
            .sidebar { 
                transform: translateX(-100%); 
                transition: transform 0.3s; 
            } 
            .sidebar.active { 
                transform: translateX(0); 
            } 
            .main-content { 
                margin-left: 0; 
            } 
            .mobile-toggle { 
                display: block; 
            } 
        }
        
        @media (min-width: 768px) and (max-width: 992px) {
            .sidebar {
                width: 200px;
            }
            .main-content {
                margin-left: 200px;
            }
        }
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
            
            <?php if ($user['role'] === 'kepala_lab' || $user['role'] === 'pj_lab'): ?>
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
                
                <div class="menu-item open" id="borrowManagementMenu">
                    <a href="#" class="nav-link" onclick="toggleSubmenu('borrowManagementMenu'); return false;">
                        <div>
                            <i class="bi bi-clipboard-check"></i>
                            <span> Activity</span>
                        </div>
                        <i class="bi bi-chevron-down dropdown-icon"></i>
                    </a>
                    <div class="submenu">
                        <a href="manage_borrows.php" class="nav-link">
                            <i class="bi bi-list-check"></i> Kelola Peminjaman
                        </a>
                        <a href="all_borrows.php" class="nav-link active">
                            <i class="bi bi-list-ul"></i> Semua Peminjaman
                        </a>
                        <a href="manage_bookings.php" class="nav-link">
                            <i class="bi bi-calendar-check"></i> Pemesanan Lab
                        </a>
                    </div>
                </div>
                
                <a href="reports.php" class="nav-link">
                    <i class="bi bi-graph-up"></i> Laporan
                </a>
                
                <?php if ($user['role'] === 'kepala_lab'): ?>
                    <a href="manage_users.php" class="nav-link">
                        <i class="bi bi-people"></i> Kelola Pengguna
                    </a>
                <?php endif; ?>
                
            <?php elseif ($user['role'] === 'siswa' || $user['role'] === 'pengunjung'): ?>
                <a href="borrow_items.php" class="nav-link">
                    <i class="bi bi-box-arrow-up-right"></i> Peminjaman Barang
                </a>
                <a href="all_borrows.php" class="nav-link active">
                    <i class="bi bi-list-ul"></i> Riwayat Peminjaman
                </a>
                <a href="return_items.php" class="nav-link">
                    <i class="bi bi-box-arrow-in-down-left"></i> Pengembalian Barang
                </a>
                
            <?php else: ?>
                <a href="manage_bookings.php" class="nav-link">
                    <i class="bi bi-calendar-check"></i> Pemesanan
                </a>
            <?php endif; ?>
            
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
                    <p class="user-role"><?php 
                        switch($user['role']) {
                            case 'kepala_lab': echo 'Kepala Lab'; break;
                            case 'pj_lab': echo 'PJ Lab'; break;
                            case 'guru': echo 'Guru'; break;
                            case 'teknisi': echo 'Teknisi'; break;
                            case 'siswa': echo 'Siswa'; break;
                            case 'pengunjung': echo 'Pengunjung'; break;
                            default: echo ucfirst(htmlspecialchars($user['role']));
                        }
                    ?></p>
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
            <h1 class="page-title">Semua Peminjaman</h1>
            <div>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="content-card">
            <div class="filter-section">
                <form method="GET" action="all_borrows.php" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Cari</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Nama barang atau peminjam" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Menunggu Persetujuan</option>
                            <option value="approved" <?php echo ($status_filter === 'approved') ? 'selected' : ''; ?>>Disetujui</option>
                            <option value="borrowed" <?php echo ($status_filter === 'borrowed') ? 'selected' : ''; ?>>Dipinjam</option>
                            <option value="returned" <?php echo ($status_filter === 'returned') ? 'selected' : ''; ?>>Dikembalikan</option>
                            <option value="overdue" <?php echo ($status_filter === 'overdue') ? 'selected' : ''; ?>>Terlambat</option>
                            <option value="lost" <?php echo ($status_filter === 'lost') ? 'selected' : ''; ?>>Hilang</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Cari
                        </button>
                        <a href="all_borrows.php" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <?php if ($user['role'] === 'kepala_lab' || $user['role'] === 'pj_lab'): ?>
                                <th>Peminjam</th>
                            <?php endif; ?>
                            <th>Barang</th>
                            <th>Tipe</th>
                            <th>Tanggal Pinjam</th>
                            <th>Harus Kembali</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($borrows)): ?>
                            <tr>
                                <td colspan="<?php echo ($user['role'] === 'kepala_lab' || $user['role'] === 'pj_lab') ? '8' : '7'; ?>" class="text-center py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                    <p class="mt-3 text-muted">Tidak ada data peminjaman</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($borrows as $borrow): ?>
                                <tr>
                                    <td><?php echo $borrow['id']; ?></td>
                                    <?php if ($user['role'] === 'kepala_lab' || $user['role'] === 'pj_lab'): ?>
                                        <td>
                                            <?php echo htmlspecialchars($borrow['user_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($borrow['user_role']); ?></small>
                                        </td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($borrow['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($borrow['item_type']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($borrow['borrow_date'])); ?></td>
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
                                                default: echo ucfirst($borrow['status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- Link ke detail peminjaman (anda perlu membuat file view_borrow.php) -->
                                            <a href="view_borrow.php?id=<?php echo $borrow['id']; ?>" class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($user['role'] === 'kepala_lab' || $user['role'] === 'pj_lab'): ?>
                                                <!-- Link untuk proses (anda perlu membuat file process_borrow.php) -->
                                                <?php if ($borrow['status'] === 'pending'): ?>
                                                    <a href="process_borrow.php?id=<?php echo $borrow['id']; ?>&action=approve" class="btn btn-sm btn-outline-success" title="Setujui">
                                                        <i class="bi bi-check-circle"></i>
                                                    </a>
                                                    <a href="process_borrow.php?id=<?php echo $borrow['id']; ?>&action=reject" class="btn btn-sm btn-outline-danger" title="Tolak">
                                                        <i class="bi bi-x-circle"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
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
    
    // Toggle submenu
    function toggleSubmenu(menuId) {
        const menuItem = document.getElementById(menuId);
        
        // Close all other submenus
        const allMenuItems = document.querySelectorAll('.menu-item');
        allMenuItems.forEach(item => {
            if (item.id !== menuId && item.classList.contains('open')) {
                item.classList.remove('open');
            }
        });
        
        // Toggle current submenu
        menuItem.classList.toggle('open');
    }
    
    // Set active menu based on current page
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const allLinks = document.querySelectorAll('.sidebar-menu .nav-link');
        
        allLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath.split('/').pop()) {
                // Remove active class from all links
                allLinks.forEach(l => l.classList.remove('active'));
                // Add active class to current link
                link.classList.add('active');
                
                // If link is in submenu, open the submenu
                const parentSubmenu = link.closest('.submenu');
                if (parentSubmenu) {
                    const parentMenuItem = parentSubmenu.closest('.menu-item');
                    parentMenuItem.classList.add('open');
                }
            }
        });
    });
    </script>
</body>
</html>