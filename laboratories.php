<?php
// laboratories.php (FINAL VERSION - LENGKAP DENGAN FITUR TAMBAH & EDIT)
session_start();

// Cek apakah user sudah login dan memiliki role yang tepat
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['kepala_lab', 'pj_lab'])) {
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
    
    if (!$user) {
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
 $laboratories = [];
 $pj_labs = [];
 $stats = [];

// Proses CRUD Laboratorium
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_lab':
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $location = trim($_POST['location']);
            $capacity = intval($_POST['capacity']);
            $status = $_POST['status'];
            $pj_lab_id = !empty($_POST['pj_lab_id']) ? intval($_POST['pj_lab_id']) : null;
            
            // Handle upload gambar
            $image_name = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/labs/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_name = time() . '_' . basename($_FILES['image']['name']);
                $target_file = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_name = $file_name;
                }
            }
            
            try {
                $sql = "INSERT INTO laboratories (name, description, location, capacity, status, pj_lab_id, image) 
                        VALUES (:name, :description, :location, :capacity, :status, :pj_lab_id, :image)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':location', $location);
                $stmt->bindParam(':capacity', $capacity, PDO::PARAM_INT);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':pj_lab_id', $pj_lab_id, PDO::PARAM_INT);
                $stmt->bindParam(':image', $image_name);
                $stmt->execute();
                $action_message = "Laboratorium berhasil ditambahkan.";
            } catch (PDOException $e) {
                error_log("Add lab error: " . $e->getMessage());
                $action_message = "Gagal menambahkan laboratorium.";
                $action_message_type = "danger";
            }
            break;
            
        case 'edit_lab':
            $lab_id = intval($_POST['lab_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $location = trim($_POST['location']);
            $capacity = intval($_POST['capacity']);
            $status = $_POST['status'];
            $pj_lab_id = !empty($_POST['pj_lab_id']) ? intval($_POST['pj_lab_id']) : null;

            try {
                // Ambil path gambar lama sebelum update
                $sql_old_image = "SELECT image FROM laboratories WHERE id = :id";
                $stmt_old_image = $pdo->prepare($sql_old_image);
                $stmt_old_image->bindParam(':id', $lab_id, PDO::PARAM_INT);
                $stmt_old_image->execute();
                $old_lab = $stmt_old_image->fetch(PDO::FETCH_ASSOC);
                $old_image_path = $old_lab['image'];

                $image_name = $old_image_path; // Default ke gambar lama

                // Handle upload gambar baru
                if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/labs/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $new_file_name = time() . '_' . basename($_FILES['image']['name']);
                    $target_file = $upload_dir . $new_file_name;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        // Hapus gambar lama jika ada dan berhasil upload gambar baru
                        if ($old_image_path && file_exists($upload_dir . $old_image_path)) {
                            unlink($upload_dir . $old_image_path);
                        }
                        $image_name = $new_file_name;
                    }
                }

                $sql = "UPDATE laboratories SET name = :name, description = :description, 
                            location = :location, capacity = :capacity, status = :status, pj_lab_id = :pj_lab_id, image = :image
                            WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':location', $location);
                $stmt->bindParam(':capacity', $capacity, PDO::PARAM_INT);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':pj_lab_id', $pj_lab_id, PDO::PARAM_INT);
                $stmt->bindParam(':image', $image_name);
                $stmt->bindParam(':id', $lab_id, PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Laboratorium berhasil diperbarui.";
            } catch (PDOException $e) {
                error_log("Edit lab error: " . $e->getMessage());
                $action_message = "Gagal memperbarui laboratorium.";
                $action_message_type = "danger";
            }
            break;

        case 'delete_lab':
            $lab_id = intval($_POST['lab_id']);
            try {
                $has_related_data = false;
                $related_info = [];

                // Cek hubungan langsung
                $direct_related_tables = [
                    'computers' => 'komputer',
                    'reservations' => 'pemesanan',
                    'rps_tkj' => 'RPS TKJ',
                    'inventory' => 'inventaris'
                ];
                
                foreach ($direct_related_tables as $table => $label) {
                    $sql_check = "SELECT COUNT(*) as count FROM {$table} WHERE lab_id = :lab_id";
                    $stmt_check = $pdo->prepare($sql_check);
                    $stmt_check->bindParam(':lab_id', $lab_id, PDO::PARAM_INT);
                    $stmt_check->execute();
                    $count = $stmt_check->fetch()['count'];
                    
                    if ($count > 0) {
                        $has_related_data = true;
                        $related_info[] = "{$count} data {$label}";
                    }
                }

                // Cek hubungan tidak langsung melalui inventory ke item_borrows
                $sql_check_borrows = "
                    SELECT COUNT(*) as count 
                    FROM item_borrows ib
                    JOIN inventory i ON ib.inventory_id = i.id
                    WHERE i.lab_id = :lab_id
                ";
                $stmt_check_borrows = $pdo->prepare($sql_check_borrows);
                $stmt_check_borrows->bindParam(':lab_id', $lab_id, PDO::PARAM_INT);
                $stmt_check_borrows->execute();
                $count_borrows = $stmt_check_borrows->fetch()['count'];

                if ($count_borrows > 0) {
                    $has_related_data = true;
                    $related_info[] = "{$count_borrows} data riwayat peminjaman barang";
                }
                
                if ($has_related_data) {
                    $action_message = "Tidak dapat menghapus laboratorium karena masih terkait dengan: " . implode(', ', $related_info) . ". Hapus atau pindahkan data terkait terlebih dahulu.";
                    $action_message_type = "danger";
                } else {
                    // Hapus file gambar terkait sebelum menghapus data
                    $sql_get_image = "SELECT image FROM laboratories WHERE id = :id";
                    $stmt_get_image = $pdo->prepare($sql_get_image);
                    $stmt_get_image->bindParam(':id', $lab_id, PDO::PARAM_INT);
                    $stmt_get_image->execute();
                    $lab = $stmt_get_image->fetch(PDO::FETCH_ASSOC);

                    if ($lab && $lab['image']) {
                        $file_path = 'uploads/labs/' . $lab['image'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }

                    $sql = "DELETE FROM laboratories WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $lab_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $action_message = "Laboratorium berhasil dihapus.";
                }
            } catch (PDOException $e) {
                error_log("Delete lab error: " . $e->getMessage());
                $action_message = "Gagal menghapus laboratorium: " . $e->getMessage();
                $action_message_type = "danger";
            }
            break;
    }
}

// Ambil data laboratorium dengan JOIN ke tabel users
try {
    $sql = "SELECT l.*, u.name as pj_name FROM laboratories l 
            LEFT JOIN users u ON l.pj_lab_id = u.id 
            ORDER BY l.name";
    $stmt = $pdo->query($sql);
    $laboratories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch labs error: " . $e->getMessage());
    $laboratories = [];
}

// Ambil data PJ Lab untuk dropdown
try {
    $sql = "SELECT id, name FROM users WHERE role = 'pj_lab' ORDER BY name";
    $stmt = $pdo->query($sql);
    $pj_labs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch PJ Labs error: " . $e->getMessage());
    $pj_labs = [];
}

// Ambil statistik untuk dashboard
try {
    $sql = "SELECT COUNT(*) as total FROM laboratories";
    $stmt = $pdo->query($sql);
    $stats['total_labs'] = $stmt->fetch()['total'];
    
    $sql = "SELECT COUNT(*) as total FROM laboratories WHERE status = 'active'";
    $stmt = $pdo->query($sql);
    $stats['active_labs'] = $stmt->fetch()['total'];
    
    $sql = "SELECT COUNT(*) as total FROM laboratories WHERE status = 'maintenance'";
    $stmt = $pdo->query($sql);
    $stats['maintenance_labs'] = $stmt->fetch()['total'];
    
    $sql = "SELECT SUM(capacity) as total FROM laboratories";
    $stmt = $pdo->query($sql);
    $stats['total_capacity'] = $stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log("Fetch stats error: " . $e->getMessage());
    $stats = ['total_labs' => 0, 'active_labs' => 0, 'maintenance_labs' => 0, 'total_capacity' => 0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Laboratorium - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .table-thumbnail { width: 80px; height: 60px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd; }
        .table-thumbnail-placeholder { width: 80px; height: 60px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; color: #6c757d; }
        .image-preview-container { margin-top: 10px; text-align: center; }
        .image-preview-container img { max-width: 100%; max-height: 200px; border-radius: 5px; border: 1px solid #ddd; }
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
            <div class="menu-item" id="labManagementMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('labManagementMenu'); return false;">
                    <div><i class="bi bi-building"></i><span>Manajemen Lab</span></div><i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="laboratories.php" class="nav-link active"><i class="bi bi-house-door"></i> Laboratorium</a>
                    <a href="computers.php" class="nav-link"><i class="bi bi-pc-display"></i> Komputer</a>
                    <a href="inventory.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventaris</a>
                </div>
            </div>
            <div class="menu-item" id="borrowManagementMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('borrowManagementMenu'); return false;">
                    <div><i class="bi bi-clipboard-check"></i><span>Activity</span></div><i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="manage_borrows.php" class="nav-link"><i class="bi bi-list-check"></i> Kelola Peminjaman</a>
                    <a href="manage_bookings.php" class="nav-link"><i class="bi bi-calendar-check"></i> Pemesanan Lab</a>
                </div>
            </div>
            <div class="menu-item" id="maintenanceMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('maintenanceMenu'); return false;">
                    <div><i class="bi bi-tools"></i><span>Pemeliharaan</span></div><i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="manage_maintenance.php" class="nav-link"><i class="bi bi-list-ul"></i> Daftar Pemeliharaan</a>
                    <a href="add_maintenance.php" class="nav-link"><i class="bi bi-plus-circle"></i> Tambah Pemeliharaan</a>
                </div>
            </div>
            <a href="reports.php" class="nav-link"><i class="bi bi-graph-up"></i> Laporan</a>
            <?php if ($user['role'] === 'kepala_lab'): ?>
                <a href="manage_users.php" class="nav-link"><i class="bi bi-people"></i> Kelola Pengguna</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info"><div class="user-avatar"><i class="bi bi-person-fill"></i></div><div class="user-details"><p class="user-name"><?php echo htmlspecialchars($user['name']); ?></p><p class="user-role"><?php echo ($user['role'] === 'kepala_lab') ? 'Kepala Lab' : 'PJ Lab'; ?></p></div></div>
            <a href="logout.php" class="nav-link" style="margin-top: 15px;"><i class="bi bi-box-arrow-right"></i> Keluar</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <h1 class="page-title">Manajemen Laboratorium</h1>
            <div><span><?php echo date('d F Y'); ?></span></div>
        </div>

        <?php if ($action_message): ?>
            <div class="alert alert-<?php echo $action_message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $action_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card"><div class="stat-icon primary"><i class="bi bi-building"></i></div><div class="stat-value"><?php echo $stats['total_labs']; ?></div><div class="stat-label">Total Laboratorium</div></div>
            <div class="stat-card"><div class="stat-icon success"><i class="bi bi-check-circle"></i></div><div class="stat-value"><?php echo $stats['active_labs']; ?></div><div class="stat-label">Laboratorium Aktif</div></div>
            <div class="stat-card"><div class="stat-icon warning"><i class="bi bi-tools"></i></div><div class="stat-value"><?php echo $stats['maintenance_labs']; ?></div><div class="stat-label">Dalam Maintenance</div></div>
            <div class="stat-card"><div class="stat-icon info"><i class="bi bi-people"></i></div><div class="stat-value"><?php echo $stats['total_capacity']; ?></div><div class="stat-label">Total Kapasitas</div></div>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Daftar Laboratorium</h3>
                <button class="btn-custom btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addLabModal"><i class="bi bi-plus-circle"></i> Tambah Laboratorium</button>
            </div>
            <?php if (empty($laboratories)): ?>
                <div class="text-center py-4"><i class="bi bi-building" style="font-size: 3rem; color: #ccc;"></i><p class="mt-3 text-muted">Belum ada data laboratorium.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead><tr><th>Gambar</th><th>Nama Laboratorium</th><th>Lokasi</th><th>Kapasitas</th><th>PJ Lab</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($laboratories as $lab): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($lab['image'])): ?>
                                            <img src="uploads/labs/<?php echo htmlspecialchars($lab['image']); ?>" alt="<?php echo htmlspecialchars($lab['name']); ?>" class="table-thumbnail">
                                        <?php else: ?>
                                            <div class="table-thumbnail-placeholder"><i class="bi bi-image"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($lab['name']); ?></td>
                                    <td><?php echo htmlspecialchars($lab['location']); ?></td>
                                    <td><?php echo $lab['capacity']; ?> Orang</td>
                                    <td><?php echo htmlspecialchars($lab['pj_name'] ?? '-'); ?></td>
                                    <td><span class="badge-status badge-<?php echo $lab['status']; ?>"><?php echo ($lab['status'] == 'active') ? 'Aktif' : (($lab['status'] == 'inactive') ? 'Tidak Aktif' : 'Maintenance'); ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning btn-action" onclick="editLab(<?php echo htmlspecialchars(json_encode($lab)); ?>)"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-danger btn-action" onclick="deleteLab(<?php echo $lab['id']; ?>)"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Lab Modal -->
    <div class="modal fade" id="addLabModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Laboratorium</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_lab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Laboratorium</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Lokasi</label>
                                    <input type="text" class="form-control" name="location" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Kapasitas</label>
                                    <input type="number" class="form-control" name="capacity" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gambar Laboratorium</label>
                                    <input type="file" class="form-control" name="image" accept="image/*">
                                    <div class="image-preview-container" id="addImagePreview"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">PJ Lab</label>
                                    <select class="form-select" name="pj_lab_id">
                                        <option value="">-- Pilih PJ Lab --</option>
                                        <?php foreach ($pj_labs as $pj): ?>
                                            <option value="<?php echo $pj['id']; ?>"><?php echo htmlspecialchars($pj['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="active">Aktif</option>
                                        <option value="inactive">Tidak Aktif</option>
                                        <option value="maintenance">Maintenance</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
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

    <!-- Edit Lab Modal -->
    <div class="modal fade" id="editLabModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Laboratorium</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_lab">
                        <input type="hidden" name="lab_id" id="editLabId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Laboratorium</label>
                                    <input type="text" class="form-control" name="name" id="editLabName" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Lokasi</label>
                                    <input type="text" class="form-control" name="location" id="editLabLocation" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Kapasitas</label>
                                    <input type="number" class="form-control" name="capacity" id="editLabCapacity" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ganti Gambar (Opsional)</label>
                                    <input type="file" class="form-control" name="image" id="editLabImage" accept="image/*">
                                    <div class="image-preview-container" id="editImagePreview">
                                        <img src="" id="editCurrentImage" alt="Current Image">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">PJ Lab</label>
                                    <select class="form-select" name="pj_lab_id" id="editLabPjId">
                                        <option value="">-- Pilih PJ Lab --</option>
                                        <?php foreach ($pj_labs as $pj): ?>
                                            <option value="<?php echo $pj['id']; ?>"><?php echo htmlspecialchars($pj['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" id="editLabStatus" required>
                                        <option value="active">Aktif</option>
                                        <option value="inactive">Tidak Aktif</option>
                                        <option value="maintenance">Maintenance</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="description" id="editLabDescription" rows="3"></textarea>
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
    <script src="assets/js/dashboard.js"></script>
    <script>
        function editLab(labData) {
            document.getElementById('editLabId').value = labData.id;
            document.getElementById('editLabName').value = labData.name;
            document.getElementById('editLabLocation').value = labData.location;
            document.getElementById('editLabCapacity').value = labData.capacity;
            document.getElementById('editLabDescription').value = labData.description || '';
            document.getElementById('editLabPjId').value = labData.pj_lab_id || '';
            document.getElementById('editLabStatus').value = labData.status || 'active';
            
            // Tampilkan gambar saat ini di modal edit
            const currentImageContainer = document.getElementById('editCurrentImage');
            if (labData.image) {
                currentImageContainer.src = 'uploads/labs/' + labData.image;
                currentImageContainer.style.display = 'block';
            } else {
                currentImageContainer.style.display = 'none';
            }

            const editModal = new bootstrap.Modal(document.getElementById('editLabModal'));
            editModal.show();
        }

        function deleteLab(id) {
            if (confirm('Apakah Anda yakin ingin menghapus laboratorium ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="delete_lab"><input type="hidden" name="lab_id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Event listener untuk preview gambar saat memilih file
        document.addEventListener('DOMContentLoaded', function() {
            const addImageInput = document.querySelector('input[name="image"]');
            const addImagePreview = document.getElementById('addImagePreview');
            
            addImageInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        addImagePreview.innerHTML = `<img src="${e.target.result}" alt="Image Preview">`;
                    }
                    reader.readAsDataURL(file);
                } else {
                    addImagePreview.innerHTML = '';
                }
            });

            const editImageInput = document.getElementById('editLabImage');
            const editImagePreview = document.getElementById('editCurrentImage');

            editImageInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        editImagePreview.src = e.target.result;
                        editImagePreview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</body>
</html>