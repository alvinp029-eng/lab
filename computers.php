<?php
// computers.php (FINAL VERSION - KONSISTEN DENGAN DASHBOARD)
session_start();

// Cek apakah user sudah login dan memiliki role yang tepat
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// Ambil data user dari database untuk konsistensi dan keamanan
try {
    $sql = "SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user || !in_array($user['role'], ['kepala_lab', 'pj_lab'])) {
        // Jika user tidak ditemukan atau tidak memiliki izin, logout
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
    
} catch (PDOException $e) {
    die("Terjadi kesalahan saat memuat data pengguna.");
}

// Inisialisasi variabel
 $action_message = '';
 $action_message_type = 'success';
 $computers = [];
 $maintenance_records = [];
 $laboratories = [];
 $technicians = [];
 $stats = []; // Inisialisasi variabel stats

// Proses CRUD Komputer (tidak ada perubahan)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    // ... (kode CRUD komputer tetap sama) ...
    switch ($_POST['action']) {
        case 'add_computer':
            $lab_id = intval($_POST['lab_id']);
            $computer_name = trim($_POST['computer_name']);
            $os = trim($_POST['os']);
            $specs = trim($_POST['specs']);
            $status = $_POST['status'];
            
            try {
                $sql = "INSERT INTO computers (lab_id, computer_name, os, specs, status) 
                        VALUES (:lab_id, :computer_name, :os, :specs, :status)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':lab_id', $lab_id, PDO::PARAM_INT);
                $stmt->bindParam(':computer_name', $computer_name, PDO::PARAM_STR);
                $stmt->bindParam(':os', $os, PDO::PARAM_STR);
                $stmt->bindParam(':specs', $specs, PDO::PARAM_STR);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->execute();
                $action_message = "Komputer berhasil ditambahkan.";
            } catch (PDOException $e) {
                error_log("Add computer error: " . $e->getMessage());
                $action_message = "Gagal menambahkan komputer.";
                $action_message_type = "danger";
            }
            break;
            
        case 'edit_computer':
            $computer_id = intval($_POST['computer_id']);
            $lab_id = intval($_POST['lab_id']);
            $computer_name = trim($_POST['computer_name']);
            $os = trim($_POST['os']);
            $specs = trim($_POST['specs']);
            $status = $_POST['status'];
            
            try {
                $sql = "UPDATE computers SET lab_id = :lab_id, computer_name = :computer_name, 
                        os = :os, specs = :specs, status = :status 
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':lab_id', $lab_id, PDO::PARAM_INT);
                $stmt->bindParam(':computer_name', $computer_name, PDO::PARAM_STR);
                $stmt->bindParam(':os', $os, PDO::PARAM_STR);
                $stmt->bindParam(':specs', $specs, PDO::PARAM_STR);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->bindParam(':id', $computer_id, PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Komputer berhasil diperbarui.";
            } catch (PDOException $e) {
                error_log("Edit computer error: " . $e->getMessage());
                $action_message = "Gagal memperbarui komputer.";
                $action_message_type = "danger";
            }
            break;
            
        case 'delete_computer':
            $computer_id = intval($_POST['computer_id']);
            try {
                // PERBAIKAN: Cek apakah komputer terkait dengan maintenance
                $sql_check = "SELECT COUNT(*) as count FROM computer_maintenance WHERE computer_id = :computer_id";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->bindParam(':computer_id', $computer_id, PDO::PARAM_INT);
                $stmt_check->execute();
                $count = $stmt_check->fetch()['count'];
                
                if ($count > 0) {
                    $action_message = "Tidak dapat menghapus komputer karena masih terkait dengan {$count} data maintenance.";
                    $action_message_type = "danger";
                } else {
                    $sql = "DELETE FROM computers WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $computer_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $action_message = "Komputer berhasil dihapus.";
                }
            } catch (PDOException $e) {
                error_log("Delete computer error: " . $e->getMessage());
                $action_message = "Gagal menghapus komputer.";
                $action_message_type = "danger";
            }
            break;
    }
}

// Proses CRUD Maintenance (tidak ada perubahan)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['maintenance_action'])) {
    // ... (kode CRUD maintenance tetap sama) ...
    switch ($_POST['maintenance_action']) {
        case 'add_maintenance':
            $computer_id = intval($_POST['computer_id']);
            $maintenance_type = $_POST['maintenance_type'];
            $description = trim($_POST['description']);
            $cost = !empty($_POST['cost']) ? floatval($_POST['cost']) : null;
            $technician_id = !empty($_POST['technician_id']) ? intval($_POST['technician_id']) : null;
            $start_date = $_POST['start_date'];
            $notes = trim($_POST['notes']);
            
            try {
                $sql = "INSERT INTO computer_maintenance (computer_id, maintenance_type, description, cost, technician_id, start_date, notes, created_by) 
                        VALUES (:computer_id, :maintenance_type, :description, :cost, :technician_id, :start_date, :notes, :created_by)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':computer_id', $computer_id, PDO::PARAM_INT);
                $stmt->bindParam(':maintenance_type', $maintenance_type, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':cost', $cost, PDO::PARAM_STR);
                $stmt->bindParam(':technician_id', $technician_id, PDO::PARAM_INT);
                $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
                $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
                $stmt->bindParam(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Maintenance berhasil ditambahkan.";
            } catch (PDOException $e) {
                error_log("Add maintenance error: " . $e->getMessage());
                $action_message = "Gagal menambahkan maintenance.";
                $action_message_type = "danger";
            }
            break;
            
        case 'complete_maintenance':
            $maintenance_id = intval($_POST['maintenance_id']);
            $end_date = date('Y-m-d H:i:s');
            
            try {
                $sql = "UPDATE computer_maintenance SET status = 'completed', end_date = :end_date WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
                $stmt->bindParam(':id', $maintenance_id, PDO::PARAM_INT);
                $stmt->execute();
                
                // Update last maintenance date on computer
                $sql = "UPDATE computers SET last_maintenance = :end_date WHERE id = (SELECT computer_id FROM computer_maintenance WHERE id = :id)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
                $stmt->bindParam(':id', $maintenance_id, PDO::PARAM_INT);
                $stmt->execute();
                
                $action_message = "Maintenance berhasil diselesaikan.";
            } catch (PDOException $e) {
                error_log("Complete maintenance error: " . $e->getMessage());
                $action_message = "Gagal menyelesaikan maintenance.";
                $action_message_type = "danger";
            }
            break;
            
        case 'delete_maintenance':
            $maintenance_id = intval($_POST['maintenance_id']);
            try {
                $sql = "DELETE FROM computer_maintenance WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $maintenance_id, PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Maintenance berhasil dihapus.";
            } catch (PDOException $e) {
                error_log("Delete maintenance error: " . $e->getMessage());
                $action_message = "Gagal menghapus maintenance.";
                $action_message_type = "danger";
            }
            break;
    }
}

// Ambil data untuk setiap tab dengan error handling
try {
    // Data Komputer
    $sql = "SELECT c.*, l.name as lab_name FROM computers c 
            JOIN laboratories l ON c.lab_id = l.id 
            ORDER BY l.name, c.computer_name";
    $stmt = $pdo->query($sql);
    $computers = $stmt->fetchAll();

    // Data Maintenance
    $sql = "SELECT cm.*, c.computer_name, l.name as lab_name, u.name as technician_name, cb.name as created_by_name 
            FROM computer_maintenance cm
            JOIN computers c ON cm.computer_id = c.id
            JOIN laboratories l ON c.lab_id = l.id
            LEFT JOIN users u ON cm.technician_id = u.id
            LEFT JOIN users cb ON cm.created_by = cb.id
            ORDER BY cm.start_date DESC";
    $stmt = $pdo->query($sql);
    $maintenance_records = $stmt->fetchAll();

    // Data Laboratorium untuk dropdown
    $sql = "SELECT id, name FROM laboratories ORDER BY name";
    $stmt = $pdo->query($sql);
    $laboratories = $stmt->fetchAll();

    // Data Teknisi untuk dropdown
    $sql = "SELECT id, name FROM users WHERE role IN ('teknisi', 'kepala_lab', 'pj_lab') ORDER BY name";
    $stmt = $pdo->query($sql);
    $technicians = $stmt->fetchAll();

    // PERBAIKAN: Ambil statistik untuk dashboard
    // Total komputer
    $sql = "SELECT COUNT(*) as total FROM computers";
    $stmt = $pdo->query($sql);
    $stats['total_computers'] = $stmt->fetch()['total'];
    
    // Komputer tersedia
    $sql = "SELECT COUNT(*) as total FROM computers WHERE status = 'available'";
    $stmt = $pdo->query($sql);
    $stats['available_computers'] = $stmt->fetch()['total'];
    
    // Komputer digunakan
    $sql = "SELECT COUNT(*) as total FROM computers WHERE status = 'in_use'";
    $stmt = $pdo->query($sql);
    $stats['in_use_computers'] = $stmt->fetch()['total'];
    
    // Komputer dalam maintenance
    $sql = "SELECT COUNT(*) as total FROM computers WHERE status = 'maintenance'";
    $stmt = $pdo->query($sql);
    $stats['maintenance_computers'] = $stmt->fetch()['total'];

} catch (PDOException $e) {
    error_log("Fetch data error: " . $e->getMessage());
    // Jika tabel tidak ada, variabel akan kosong dan menampilkan pesan
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Komputer - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- PERBAIKAN: Pindahkan CSS ke file eksternal -->
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
            <a href="dashboard.php" class="nav-link">
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
                    <a href="computers.php" class="nav-link active">
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
            
            <!-- Menu Pemeliharaan dengan Submenu -->
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
            
            <!-- Menu Pengguna (khusus kepala_lab) -->
            <?php if ($user['role'] === 'kepala_lab'): ?>
                <a href="manage_users.php" class="nav-link">
                    <i class="bi bi-people"></i> Kelola Pengguna
                </a>
            <?php endif; ?>
            
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
            <h1 class="page-title">Manajemen Komputer</h1>
            <div>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>

        <?php if ($action_message): ?>
            <div class="alert alert-<?php echo $action_message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $action_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- PERBAIKAN: Tambahkan Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="bi bi-pc-display"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_computers']; ?></div>
                <div class="stat-label">Total Komputer</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['available_computers']; ?></div>
                <div class="stat-label">Tersedia</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="bi bi-person"></i>
                </div>
                <div class="stat-value"><?php echo $stats['in_use_computers']; ?></div>
                <div class="stat-label">Digunakan</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="bi bi-tools"></i>
                </div>
                <div class="stat-value"><?php echo $stats['maintenance_computers']; ?></div>
                <div class="stat-label">Maintenance</div>
            </div>
        </div>

        <div class="content-card">
            <!-- Nav Tabs -->
            <ul class="nav nav-tabs" id="computerTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="computers-tab" data-bs-toggle="tab" data-bs-target="#computers" type="button" role="tab">
                        <i class="bi bi-pc-display"></i> Data Komputer
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab">
                        <i class="bi bi-tools"></i> Riwayat Maintenance
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content pt-3" id="computerTabContent">
                <!-- Computers Tab -->
                <div class="tab-pane fade show active" id="computers" role="tabpanel">
                    <div class="card-header-custom">
                        <h3 class="card-title">Daftar Komputer</h3>
                        <button class="btn-custom btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addComputerModal">
                            <i class="bi bi-plus-circle"></i> Tambah Komputer
                        </button>
                    </div>
                    
                    <?php if (empty($computers)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-pc-display" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">Belum ada data komputer.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Merk Komputer</th>
                                        <th>Laboratorium</th>
                                        <th>Spesifikasi</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($computers as $computer): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($computer['computer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($computer['lab_name']); ?></td>
                                            <td>
                                                <div class="specs-grid">
                                                    <div class="specs-item">
                                                        <div class="specs-label">OS:</div>
                                                        <div class="specs-value"><?php echo htmlspecialchars($computer['os']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge-status badge-<?php echo $computer['status']; ?>">
                                                    <?php 
                                                    switch($computer['status']) {
                                                        case 'available': echo 'Tersedia'; break;
                                                        case 'in_use': echo 'Digunakan'; break;
                                                        case 'maintenance': echo 'Maintenance'; break;
                                                        default: echo $computer['status'];
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning btn-action" onclick="editComputer(<?php echo htmlspecialchars(json_encode($computer)); ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info btn-action" onclick="addMaintenance(<?php echo $computer['id']; ?>)">
                                                    <i class="bi bi-tools"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-action" onclick="deleteComputer(<?php echo $computer['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Maintenance Tab -->
                <div class="tab-pane fade" id="maintenance" role="tabpanel">
                    <div class="card-header-custom">
                        <h3 class="card-title">Riwayat Maintenance</h3>
                    </div>
                    
                    <?php if (empty($maintenance_records)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-tools" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">Belum ada riwayat maintenance.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Komputer</th>
                                        <th>Tipe Maintenance</th>
                                        <th>Teknisi</th>
                                        <th>Tanggal Mulai</th>
                                        <th>Tanggal Selesai</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenance_records as $record): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($record['computer_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($record['lab_name']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php 
                                                    switch($record['maintenance_type']) {
                                                        case 'hardware': echo 'Hardware'; break;
                                                        case 'software': echo 'Software'; break;
                                                        case 'network': echo 'Jaringan'; break;
                                                        case 'cleaning': echo 'Cleaning'; break;
                                                        case 'other': echo 'Lainnya'; break;
                                                        default: echo $record['maintenance_type'];
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['technician_name'] ?? '-'); ?></td>
                                            <td><?php echo date('d M Y, H:i', strtotime($record['start_date'])); ?></td>
                                            <td><?php echo $record['end_date'] ? date('d M Y, H:i', strtotime($record['end_date'])) : '-'; ?></td>
                                            <td>
                                                <span class="badge-status badge-<?php echo $record['status']; ?>">
                                                    <?php 
                                                    switch($record['status']) {
                                                        case 'in_progress': echo 'Dalam Proses'; break;
                                                        case 'completed': echo 'Selesai'; break;
                                                        case 'cancelled': echo 'Dibatalkan'; break;
                                                        default: echo $record['status'];
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($record['status'] === 'in_progress'): ?>
                                                    <button class="btn btn-sm btn-success btn-action" onclick="completeMaintenance(<?php echo $record['id']; ?>)">
                                                        <i class="bi bi-check-circle"></i> Selesai
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-danger btn-action" onclick="deleteMaintenance(<?php echo $record['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Computer Modal -->
    <div class="modal fade" id="addComputerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Komputer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_computer">
                        <div class="mb-3">
                            <label class="form-label">Laboratorium</label>
                            <select class="form-select" name="lab_id" required>
                                <?php foreach ($laboratories as $lab): ?>
                                    <option value="<?php echo $lab['id']; ?>"><?php echo htmlspecialchars($lab['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Merk Komputer</label>
                            <input type="text" class="form-control" name="computer_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sistem Operasi</label>
                            <input type="text" class="form-control" name="os" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Spesifikasi</label>
                            <textarea class="form-control" name="specs" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="available">Tersedia</option>
                                <option value="in_use">Digunakan</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Computer Modal -->
    <div class="modal fade" id="editComputerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Komputer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_computer">
                        <input type="hidden" name="computer_id" id="editComputerId">
                        <div class="mb-3">
                            <label class="form-label">Laboratorium</label>
                            <select class="form-select" name="lab_id" id="editLabId" required>
                                <?php foreach ($laboratories as $lab): ?>
                                    <option value="<?php echo $lab['id']; ?>"><?php echo htmlspecialchars($lab['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Merk Komputer</label>
                            <input type="text" class="form-control" name="computer_name" id="editComputerName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sistem Operasi</label>
                            <input type="text" class="form-control" name="os" id="editOs" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Spesifikasi</label>
                            <textarea class="form-control" name="specs" id="editSpecs" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="editStatus" required>
                                <option value="available">Tersedia</option>
                                <option value="in_use">Digunakan</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Maintenance Modal -->
    <div class="modal fade" id="addMaintenanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="maintenance_action" value="add_maintenance">
                        <input type="hidden" name="computer_id" id="maintenanceComputerId">
                        <div class="mb-3">
                            <label class="form-label">Komputer</label>
                            <input type="text" class="form-control" id="maintenanceComputerName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipe Maintenance</label>
                            <select class="form-select" name="maintenance_type" required>
                                <option value="hardware">Hardware</option>
                                <option value="software">Software</option>
                                <option value="network">Jaringan</option>
                                <option value="cleaning">Cleaning</option>
                                <option value="other">Lainnya</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Biaya</label>
                                    <input type="number" class="form-control" name="cost" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Teknisi</label>
                                    <select class="form-select" name="technician_id">
                                        <option value="">-- Pilih Teknisi --</option>
                                        <?php foreach ($technicians as $tech): ?>
                                            <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="datetime-local" class="form-control" name="start_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        // Edit Computer Function
        function editComputer(computerData) {
            // Populate edit modal with computer data
            document.getElementById('editComputerId').value = computerData.id;
            document.getElementById('editLabId').value = computerData.lab_id;
            document.getElementById('editComputerName').value = computerData.computer_name;
            document.getElementById('editOs').value = computerData.os || '';
            document.getElementById('editSpecs').value = computerData.specs || '';
            document.getElementById('editStatus').value = computerData.status || 'available';
            
            // Show edit modal
            const editModal = new bootstrap.Modal(document.getElementById('editComputerModal'));
            editModal.show();
        }

        // Delete Computer Function
        function deleteComputer(id) {
            if (confirm('Apakah Anda yakin ingin menghapus komputer ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="delete_computer"><input type="hidden" name="computer_id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add Maintenance Function
        function addMaintenance(id) {
            // Find computer data from array
            const computers = <?php echo json_encode($computers); ?>;
            const computer = computers.find(c => c.id === id);
            
            if (computer) {
                // Populate maintenance modal with computer data
                document.getElementById('maintenanceComputerId').value = id;
                document.getElementById('maintenanceComputerName').value = `${computer.computer_name} (${computer.lab_name})`;
                
                // Show maintenance modal
                const maintenanceModal = new bootstrap.Modal(document.getElementById('addMaintenanceModal'));
                maintenanceModal.show();
            }
        }

        // Complete Maintenance Function
        function completeMaintenance(id) {
            if (confirm('Apakah Anda yakin maintenance ini sudah selesai?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="maintenance_action" value="complete_maintenance"><input type="hidden" name="maintenance_id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Delete Maintenance Function
        function deleteMaintenance(id) {
            if (confirm('Apakah Anda yakin ingin menghapus record maintenance ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="maintenance_action" value="delete_maintenance"><input type="hidden" name="maintenance_id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>