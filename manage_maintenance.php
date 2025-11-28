<?php
// manage_maintenance.php
session_start();

// Cek login dan role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'kepala_lab') {
    header('Location: login.php');
    exit();
}


require_once 'config.php';

// Tampilkan flash message jika ada
if (isset($_SESSION['flash_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['flash_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['flash_error']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['flash_error']);
}

// Inisialisasi variabel
 $maintenance_list = [];
 $filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
 $filter_technician = isset($_GET['filter_technician']) ? $_GET['filter_technician'] : '';
 $search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ambil data teknisi untuk dropdown filter
 $technicians = [];
try {
    $sql = "SELECT id, name FROM users WHERE role = 'teknisi' ORDER BY name ASC";
    $stmt = $pdo->query($sql);
    $technicians = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching technicians: " . $e->getMessage());
}

// Query dasar
 $sql = "SELECT 
            cm.id, 
            c.computer_name, 
            cm.maintenance_type, 
            cm.description, 
            u_tech.name AS technician_name, 
            cm.start_date, 
            cm.end_date, 
            cm.status, 
            cm.cost,
            l.name as lab_name
        FROM computer_maintenance cm
        JOIN computers c ON cm.computer_id = c.id
        JOIN laboratories l ON c.lab_id = l.id
        LEFT JOIN users u_tech ON cm.technician_id = u_tech.id
        WHERE 1=1";

 $params = [];

// Tambahkan filter
if (!empty($filter_status)) {
    $sql .= " AND cm.status = :status";
    $params[':status'] = $filter_status;
}
if (!empty($filter_technician)) {
    $sql .= " AND cm.technician_id = :technician_id";
    $params[':technician_id'] = $filter_technician;
}
if (!empty($search)) {
    $sql .= " AND (c.computer_name LIKE :search OR cm.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

 $sql .= " ORDER BY cm.start_date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $maintenance_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching maintenance list: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat data pemeliharaan.");
}

// Fungsi untuk menerjemahkan status
function translateStatus($status) {
    switch ($status) {
        case 'in_progress': return 'Sedang Berjalan';
        case 'completed': return 'Selesai';
        case 'cancelled': return 'Dibatalkan';
        default: return ucfirst($status);
    }
}

function translateMaintenanceType($type) {
    switch ($type) {
        case 'hardware': return 'Perangkat Keras';
        case 'software': return 'Perangkat Lunak';
        case 'network': return 'Jaringan';
        case 'cleaning': return 'Pembersihan';
        case 'other': return 'Lainnya';
        default: return ucfirst($type);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pemeliharaan - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Sidebar (sama seperti dashboard) -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>SILABKOM</h3>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <div class="menu-item" id="labManagementMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('labManagementMenu'); return false;">
                    <div><i class="bi bi-building"></i><span>Manajemen Lab</span></div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="laboratories.php" class="nav-link"><i class="bi bi-house-door"></i> Laboratorium</a>
                    <a href="computers.php" class="nav-link"><i class="bi bi-pc-display"></i> Komputer</a>
                    <a href="inventory.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventaris</a>
                </div>
            </div>
            <div class="menu-item" id="borrowManagementMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('borrowManagementMenu'); return false;">
                    <div><i class="bi bi-clipboard-check"></i><span>Activity</span></div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="manage_borrows.php" class="nav-link"><i class="bi bi-list-check"></i> Kelola Peminjaman</a>
                    <a href="manage_bookings.php" class="nav-link"><i class="bi bi-calendar-check"></i> Pemesanan Lab</a>
                </div>
            </div>
            <div class="menu-item" id="maintenanceMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('maintenanceMenu'); return false;">
                    <div><i class="bi bi-tools"></i><span>Pemeliharaan</span></div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="manage_maintenance.php" class="nav-link active"><i class="bi bi-list-ul"></i> Daftar Pemeliharaan</a>
                    <a href="add_maintenance.php" class="nav-link"><i class="bi bi-plus-circle"></i> Tambah Pemeliharaan</a>
                </div>
            </div>
            <a href="reports.php" class="nav-link"><i class="bi bi-graph-up"></i> Laporan</a>
            <a href="manage_users.php" class="nav-link"><i class="bi bi-people"></i> Kelola Pengguna</a>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="user-role">Kepala Lab</p>
                </div>
            </div>
            <a href="logout.php" class="nav-link" style="margin-top: 15px;"><i class="bi bi-box-arrow-right"></i> Keluar</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <h1 class="page-title">Kelola Pemeliharaan</h1>
            <div>
                <a href="add_maintenance.php" class="btn-custom btn-primary-custom">
                    <i class="bi bi-plus-circle"></i> Tambah Pemeliharaan Baru
                </a>
            </div>
        </div>

        <!-- Filter dan Pencarian -->
        <div class="content-card mb-4">
            <form method="GET" action="manage_maintenance.php">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Cari Komputer / Deskripsi</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Masukkan kata kunci...">
                    </div>
                    <div class="col-md-3">
                        <label for="filter_status" class="form-label">Filter Status</label>
                        <select class="form-select" id="filter_status" name="filter_status">
                            <option value="">Semua Status</option>
                            <option value="in_progress" <?php echo ($filter_status === 'in_progress') ? 'selected' : ''; ?>>Sedang Berjalan</option>
                            <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Selesai</option>
                            <option value="cancelled" <?php echo ($filter_status === 'cancelled') ? 'selected' : ''; ?>>Dibatalkan</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filter_technician" class="form-label">Filter Teknisi</label>
                        <select class="form-select" id="filter_technician" name="filter_technician">
                            <option value="">Semua Teknisi</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?php echo $tech['id']; ?>" <?php echo ($filter_technician == $tech['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tech['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Cari</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabel Data Pemeliharaan -->
        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Komputer</th>
                            <th>Lab</th>
                            <th>Jenis</th>
                            <th>Teknisi</th>
                            <th>Tanggal Mulai</th>
                            <th>Status</th>
                            <th>Biaya</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($maintenance_list)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-tools" style="font-size: 3rem; color: #ccc;"></i>
                                    <p class="mt-3 text-muted">Tidak ada data pemeliharaan yang ditemukan.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($maintenance_list as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['computer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['lab_name']); ?></td>
                                    <td><?php echo translateMaintenanceType($item['maintenance_type']); ?></td>
                                    <td><?php echo htmlspecialchars($item['technician_name'] ?? 'Belum Ditugaskan'); ?></td>
                                    <td><?php echo date('d M Y', strtotime($item['start_date'])); ?></td>
                                    <td><span class="badge-status badge-<?php echo $item['status']; ?>"><?php echo translateStatus($item['status']); ?></span></td>
                                    <td><?php echo ($item['cost']) ? 'Rp ' . number_format($item['cost'], 2, ',', '.') : '-'; ?></td>
                                    <td>
                                        <a href="view_maintenance.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <!-- Aksi untuk menyelesaikan atau membatalkan jika status 'in_progress' -->
                                        <?php if ($item['status'] === 'in_progress'): ?>
                                            <a href="process_maintenance.php?action=complete&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-success" title="Tandai Selesai" onclick="return confirm('Apakah Anda yakin ingin menandai pemeliharaan ini sebagai selesai?');">
                                                <i class="bi bi-check-circle"></i>
                                            </a>
                                            <a href="process_maintenance.php?action=cancel&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" title="Batalkan" onclick="return confirm('Apakah Anda yakin ingin membatalkan pemeliharaan ini?');">
                                                <i class="bi bi-x-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>