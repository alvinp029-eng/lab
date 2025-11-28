<?php
// view_maintenance.php
session_start();

// Cek login dan role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'kepala_lab') {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// Ambil ID dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['flash_error'] = "ID pemeliharaan tidak valid.";
    header('Location: manage_maintenance.php');
    exit();
}

 $maintenance_id = (int)$_GET['id'];

// Ambil data pemeliharaan dari database
try {
    $sql = "SELECT 
                cm.*,
                c.computer_name,
                l.name AS lab_name,
                u_tech.name AS technician_name,
                u_creator.name AS created_by_name
            FROM computer_maintenance cm
            JOIN computers c ON cm.computer_id = c.id
            JOIN laboratories l ON c.lab_id = l.id
            LEFT JOIN users u_tech ON cm.technician_id = u_tech.id
            LEFT JOIN users u_creator ON cm.created_by = u_creator.id
            WHERE cm.id = :id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $maintenance_id, PDO::PARAM_INT);
    $stmt->execute();
    $maintenance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$maintenance) {
        $_SESSION['flash_error'] = "Data pemeliharaan tidak ditemukan.";
        header('Location: manage_maintenance.php');
        exit();
    }

} catch (PDOException $e) {
    die("Terjadi kesalahan saat memuat data: " . $e->getMessage());
}

// Fungsi penerjemahan (sama seperti di file lain)
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
    <title>Detail Pemeliharaan - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Sidebar (sama seperti manage_maintenance.php) -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><h3>SILABKOM</h3></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <div class="menu-item" id="labManagementMenu"><a href="#" class="nav-link" onclick="toggleSubmenu('labManagementMenu'); return false;"><div><i class="bi bi-building"></i><span>Manajemen Lab</span></div><i class="bi bi-chevron-down dropdown-icon"></i></a><div class="submenu"><a href="laboratories.php" class="nav-link"><i class="bi bi-house-door"></i> Laboratorium</a><a href="computers.php" class="nav-link"><i class="bi bi-pc-display"></i> Komputer</a><a href="inventory.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventaris</a></div></div>
            <div class="menu-item" id="borrowManagementMenu"><a href="#" class="nav-link" onclick="toggleSubmenu('borrowManagementMenu'); return false;"><div><i class="bi bi-clipboard-check"></i><span>Activity</span></div><i class="bi bi-chevron-down dropdown-icon"></i></a><div class="submenu"><a href="manage_borrows.php" class="nav-link"><i class="bi bi-list-check"></i> Kelola Peminjaman</a><a href="manage_bookings.php" class="nav-link"><i class="bi bi-calendar-check"></i> Pemesanan Lab</a></div></div>
            <div class="menu-item" id="maintenanceMenu"><a href="#" class="nav-link" onclick="toggleSubmenu('maintenanceMenu'); return false;"><div><i class="bi bi-tools"></i><span>Pemeliharaan</span></div><i class="bi bi-chevron-down dropdown-icon"></i></a><div class="submenu"><a href="manage_maintenance.php" class="nav-link active"><i class="bi bi-list-ul"></i> Daftar Pemeliharaan</a><a href="add_maintenance.php" class="nav-link"><i class="bi bi-plus-circle"></i> Tambah Pemeliharaan</a></div></div>
            <a href="reports.php" class="nav-link"><i class="bi bi-graph-up"></i> Laporan</a>
            <a href="manage_users.php" class="nav-link"><i class="bi bi-people"></i> Kelola Pengguna</a>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info"><div class="user-avatar"><i class="bi bi-person-fill"></i></div><div class="user-details"><p class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p><p class="user-role">Kepala Lab</p></div></div>
            <a href="logout.php" class="nav-link" style="margin-top: 15px;"><i class="bi bi-box-arrow-right"></i> Keluar</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <h1 class="page-title">Detail Pemeliharaan: <?php echo htmlspecialchars($maintenance['computer_name']); ?></h1>
            <div>
                <!-- Aksi Cepat jika status masih berjalan -->
                <?php if ($maintenance['status'] === 'in_progress'): ?>
                    <a href="process_maintenance.php?action=complete&id=<?php echo $maintenance['id']; ?>" class="btn btn-success" onclick="return confirm('Tandai pemeliharaan ini sebagai selesai?');">
                        <i class="bi bi-check-circle"></i> Tandai Selesai
                    </a>
                    <a href="process_maintenance.php?action=cancel&id=<?php echo $maintenance['id']; ?>" class="btn btn-danger" onclick="return confirm('Batalkan pemeliharaan ini?');">
                        <i class="bi bi-x-circle"></i> Batalkan
                    </a>
                <?php endif; ?>
                <a href="manage_maintenance.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                </a>
            </div>
        </div>

        <!-- Detail Card -->
        <div class="content-card">
            <div class="row">
                <div class="col-md-8">
                    <h5 class="mb-3">Informasi Pemeliharaan</h5>
                    <table class="table table-borderless table-detail">
                        <tr>
                            <td width="25%"><strong>Komputer</strong></td>
                            <td><?php echo htmlspecialchars($maintenance['computer_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Laboratorium</strong></td>
                            <td><?php echo htmlspecialchars($maintenance['lab_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Jenis Pemeliharaan</strong></td>
                            <td><?php echo translateMaintenanceType($maintenance['maintenance_type']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td><span class="badge-status badge-<?php echo $maintenance['status']; ?>"><?php echo translateStatus($maintenance['status']); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Deskripsi</strong></td>
                            <td><?php echo nl2br(htmlspecialchars($maintenance['description'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Teknisi</strong></td>
                            <td><?php echo htmlspecialchars($maintenance['technician_name'] ?? 'Belum Ditugaskan'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Tanggal Mulai</strong></td>
                            <td><?php echo date('d F Y, H:i', strtotime($maintenance['start_date'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Estimasi Selesai</strong></td>
                            <td><?php echo $maintenance['end_date'] ? date('d F Y, H:i', strtotime($maintenance['end_date'])) : '-'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Biaya</strong></td>
                            <td><?php echo $maintenance['cost'] ? 'Rp ' . number_format($maintenance['cost'], 2, ',', '.') : '-'; ?></td>
                        </tr>
                        <?php if (!empty($maintenance['notes'])): ?>
                        <tr>
                            <td><strong>Catatan Tambahan</strong></td>
                            <td><?php echo nl2br(htmlspecialchars($maintenance['notes'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Informasi Sistem</h5>
                    <table class="table table-borderless table-detail">
                        <tr>
                            <td width="40%"><strong>Dibuat oleh</strong></td>
                            <td><?php echo htmlspecialchars($maintenance['created_by_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Tanggal Dibuat</strong></td>
                            <td><?php echo date('d F Y, H:i', strtotime($maintenance['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Terakhir Diubah</strong></td>
                            <td><?php echo date('d F Y, H:i', strtotime($maintenance['updated_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>