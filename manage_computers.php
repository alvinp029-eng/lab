<?php
// manage_computers.php (HALAMAN CRUD KOMPUTER)
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
 $computers = [];
 $laboratories = [];

// Proses CRUD Komputer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_computer':
            $lab_id = intval($_POST['lab_id']);
            $hostname = trim($_POST['hostname']);
            $ip_address = trim($_POST['ip_address']);
            $os = trim($_POST['os']);
            $specs = trim($_POST['specs']);
            $status = $_POST['status'];
            
            try {
                $sql = "INSERT INTO computers (lab_id, hostname, ip_address, os, specs, status) 
                        VALUES (:lab_id, :hostname, :ip_address, :os, :specs, :status)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':lab_id', $lab_id, PDO::PARAM_INT);
                $stmt->bindParam(':hostname', $hostname, PDO::PARAM_STR);
                $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
                $stmt->bindParam(':os', $os, PDO::PARAM_STR);
                $stmt->bindParam(':specs', $specs, PDO::PARAM_STR);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->execute();
                $action_message = "Komputer berhasil ditambahkan.";
            } catch (PDOException $e) {
                error_log("Add computer error: " . $e->getMessage());
                $action_message = "Gagal menambahkan komputer.";
            }
            break;
            
        case 'edit_computer':
            $computer_id = intval($_POST['computer_id']);
            $lab_id = intval($_POST['lab_id']);
            $hostname = trim($_POST['hostname']);
            $ip_address = trim($_POST['ip_address']);
            $os = trim($_POST['os']);
            $specs = trim($_POST['specs']);
            $status = $_POST['status'];
            
            try {
                $sql = "UPDATE computers SET lab_id = :lab_id, hostname = :hostname, ip_address = :ip_address, 
                        os = :os, specs = :specs, status = :status 
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':lab_id', $lab_id, PDO::PARAM_INT);
                $stmt->bindParam(':hostname', $hostname, PDO::PARAM_STR);
                $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
                $stmt->bindParam(':os', $os, PDO::PARAM_STR);
                $stmt->bindParam(':specs', $specs, PDO::PARAM_STR);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->bindParam(':id', $computer_id, PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Komputer berhasil diperbarui.";
            } catch (PDOException $e) {
                error_log("Edit computer error: " . $e->getMessage());
                $action_message = "Gagal memperbarui komputer.";
            }
            break;
            
        case 'delete_computer':
            $computer_id = intval($_POST['computer_id']);
            try {
                $sql = "DELETE FROM computers WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $computer_id, PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Komputer berhasil dihapus.";
            } catch (PDOException $e) {
                error_log("Delete computer error: " . $e->getMessage());
                $action_message = "Gagal menghapus komputer.";
            }
            break;
    }
}

// Ambil data komputer dengan JOIN ke tabel laboratories
try {
    $sql = "SELECT c.*, l.name as lab_name FROM computers c 
            JOIN laboratories l ON c.lab_id = l.id 
            ORDER BY l.name, c.hostname";
    $stmt = $pdo->query($sql);
    $computers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch computers error: " . $e->getMessage());
    // Jika tabel tidak ada, $computers akan kosong
}

// Ambil data laboratorium untuk dropdown
try {
    $sql = "SELECT id, name FROM laboratories ORDER BY name";
    $stmt = $pdo->query($sql);
    $laboratories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch labs error: " . $e->getMessage());
    $laboratories = [];
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
    <style>
        :root { --primary-color: #0d6efd; --secondary-color: #0a58ca; --light-color: #f8f9fa; --dark-color: #212529; --success-color: #198754; --danger-color: #dc3545; --warning-color: #ffc107; --info-color: #0dcaf0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f7fb; color: var(--dark-color); }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 250px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 20px 0; z-index: 1000; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); }
        .sidebar-header { text-align: center; padding: 0 20px 30px; border-bottom: 1px solid rgba(255, 255, 255, 0.2); }
        .sidebar-header h3 { color: white; font-weight: bold; margin: 0; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu .nav-link { color: rgba(255, 255, 255, 0.8); padding: 12px 25px; display: flex; align-items: center; transition: all 0.3s; text-decoration: none; }
        .sidebar-menu .nav-link:hover, .sidebar-menu .nav-link.active { color: white; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar-menu .nav-link i { margin-right: 10px; font-size: 1.2rem; }
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
        .badge-baik { background-color: #d1e7dd; color: #0f5132; }
        .badge-rusak { background-color: #f8d7da; color: #721c24; }
        .badge-maintenance { background-color: #fff3cd; color: #856404; }
        .btn-action { padding: 5px 10px; font-size: 0.8rem; border-radius: 5px; }
        .form-group { margin-bottom: 15px; }
        .form-label { font-weight: 500; margin-bottom: 5px; color: #495057; }
        .form-control, .form-select { border-radius: 8px; border: 1px solid #ced4da; padding: 8px 12px; transition: all 0.3s; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25); }
        .alert { border-radius: 8px; padding: 12px 20px; margin-bottom: 20px; }
        .mobile-toggle { display: none; position: fixed; top: 20px; left: 20px; z-index: 1001; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; padding: 10px; font-size: 1.2rem; }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s; } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-toggle { display: block; } }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><h3>SILABKOM</h3></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="laboratories.php" class="nav-link"><i class="bi bi-building"></i> Laboratorium</a>
            <a href="manage_computers.php" class="nav-link active"><i class="bi bi-pc-display"></i> Komputer</a>
            <a href="inventory.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventaris</a>
            <a href="manage_borrows.php" class="nav-link"><i class="bi bi-clipboard-check"></i> Kelola Peminjaman</a>
            <a href="manage_bookings.php" class="nav-link"><i class="bi bi-calendar-check"></i> Pemesanan</a>
            <?php if ($user['role'] === 'kepala_lab'): ?>
                <a href="manage_users.php" class="nav-link"><i class="bi bi-people"></i> Kelola Pengguna</a>
                <a href="reports.php" class="nav-link"><i class="bi bi-graph-up"></i> Laporan</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
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
            <a href="logout.php" class="nav-link" style="margin-top: 15px;"><i class="bi bi-box-arrow-right"></i> Keluar</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <h1 class="page-title">Manajemen Komputer</h1>
            <div><span><?php echo date('d F Y'); ?></span></div>
        </div>

        <!-- Alert Message -->
        <?php if (!empty($action_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $action_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Daftar Komputer</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addComputerModal">
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
                                <th>Hostname</th>
                                <th>Laboratorium</th>
                                <th>IP Address</th>
                                <th>Sistem Operasi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($computers as $computer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($computer['hostname']); ?></td>
                                    <td><?php echo htmlspecialchars($computer['lab_name']); ?></td>
                                    <td><?php echo htmlspecialchars($computer['ip_address']); ?></td>
                                    <td><?php echo htmlspecialchars($computer['os']); ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $computer['status']; ?>">
                                            <?php 
                                            switch($computer['status']) {
                                                case 'baik': echo 'Baik'; break;
                                                case 'rusak': echo 'Rusak'; break;
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
                        <div class="form-group">
                            <label class="form-label">Laboratorium</label>
                            <select class="form-select" name="lab_id" required>
                                <?php foreach ($laboratories as $lab): ?>
                                    <option value="<?php echo $lab['id']; ?>"><?php echo htmlspecialchars($lab['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Hostname</label>
                            <input type="text" class="form-control" name="hostname" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">IP Address</label>
                            <input type="text" class="form-control" name="ip_address" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sistem Operasi</label>
                            <input type="text" class="form-control" name="os" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Spesifikasi</label>
                            <textarea class="form-control" name="specs" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="baik">Baik</option>
                                <option value="rusak">Rusak</option>
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
                        <div class="form-group">
                            <label class="form-label">Laboratorium</label>
                            <select class="form-select" name="lab_id" id="editLabId" required>
                                <?php foreach ($laboratories as $lab): ?>
                                    <option value="<?php echo $lab['id']; ?>"><?php echo htmlspecialchars($lab['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Hostname</label>
                            <input type="text" class="form-control" name="hostname" id="editHostname" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">IP Address</label>
                            <input type="text" class="form-control" name="ip_address" id="editIpAddress" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sistem Operasi</label>
                            <input type="text" class="form-control" name="os" id="editOs" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Spesifikasi</label>
                            <textarea class="form-control" name="specs" id="editSpecs" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="editStatus" required>
                                <option value="baik">Baik</option>
                                <option value="rusak">Rusak</option>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toggle sidebar for mobile
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });

    // Edit Computer Function
    function editComputer(computerData) {
        // Populate edit modal with computer data
        document.getElementById('editComputerId').value = computerData.id;
        document.getElementById('editLabId').value = computerData.lab_id;
        document.getElementById('editHostname').value = computerData.hostname;
        document.getElementById('editIpAddress').value = computerData.ip_address;
        document.getElementById('editOs').value = computerData.os;
        document.getElementById('editSpecs').value = computerData.specs || '';
        document.getElementById('editStatus').value = computerData.status || 'baik';
        
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
    </script>
</body>
</html>