<?php
// inventory.php (FINAL VERSION - DISESUAIKAN DENGAN DATABASE BARU)
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
 $inventory_items = [];
 $laboratories = [];
 $stats = []; // Inisialisasi variabel stats

// Proses CRUD Inventaris
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_inventory':
            $lab_id = intval($_POST['lab_id']);
            $item_name = trim($_POST['item_name']);
            $item_type = $_POST['item_type'];
            $quantity = intval($_POST['quantity']);
            $description = trim($_POST['description']);
            $is_borrowable = isset($_POST['is_borrowable']) ? 1 : 0;
            $borrow_duration_days = intval($_POST['borrow_duration_days']);
            
            // Handle upload gambar
            $image_path = null;
            if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/inventory/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_name = time() . '_' . basename($_FILES['image_path']['name']);
                $target_file = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES['image_path']['tmp_name'], $target_file)) {
                    $image_path = $file_name;
                }
            }
            
            try {
                $sql = "INSERT INTO inventory (lab_id, item_name, item_type, quantity, description, is_borrowable, borrow_duration_days, image_path) 
                        VALUES (:lab_id, :item_name, :item_type, :quantity, :description, :is_borrowable, :borrow_duration_days, :image_path)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':lab_id', $lab_id, PDO::PARAM_INT);
                $stmt->bindParam(':item_name', $item_name, PDO::PARAM_STR);
                $stmt->bindParam(':item_type', $item_type, PDO::PARAM_STR);
                $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':is_borrowable', $is_borrowable, PDO::PARAM_INT);
                $stmt->bindParam(':borrow_duration_days', $borrow_duration_days, PDO::PARAM_INT);
                $stmt->bindParam(':image_path', $image_path, PDO::PARAM_STR);
                $stmt->execute();
                $action_message = "Inventaris berhasil ditambahkan.";
            } catch (PDOException $e) {
                error_log("Add inventory error: " . $e->getMessage());
                $action_message = "Gagal menambahkan inventaris.";
                $action_message_type = "danger";
            }
            break;
            
        case 'edit_inventory':
            $inventory_id = intval($_POST['inventory_id']);
            $lab_id = intval($_POST['lab_id']);
            $item_name = trim($_POST['item_name']);
            $item_type = $_POST['item_type'];
            $quantity = intval($_POST['quantity']);
            $description = trim($_POST['description']);
            $is_borrowable = isset($_POST['is_borrowable']) ? 1 : 0;
            $borrow_duration_days = intval($_POST['borrow_duration_days']);
            
            // Handle upload gambar baru (jika ada)
            $image_path = $_POST['existing_image_path']; // Ambil path gambar lama
            if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/inventory/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_name = time() . '_' . basename($_FILES['image_path']['name']);
                $target_file = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES['image_path']['tmp_name'], $target_file)) {
                    // Hapus gambar lama jika ada
                    if ($image_path && file_exists($upload_dir . $image_path)) {
                        unlink($upload_dir . $image_path);
                    }
                    $image_path = $file_name;
                }
            }

            try {
                $sql = "UPDATE inventory SET lab_id = :lab_id, item_name = :item_name, item_type = :item_type, 
                            quantity = :quantity, description = :description, is_borrowable = :is_borrowable, borrow_duration_days = :borrow_duration_days, image_path = :image_path
                            WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':lab_id', $lab_id, PDO::PARAM_INT);
                $stmt->bindParam(':item_name', $item_name, PDO::PARAM_STR);
                $stmt->bindParam(':item_type', $item_type, PDO::PARAM_STR);
                $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':is_borrowable', $is_borrowable, PDO::PARAM_INT);
                $stmt->bindParam(':borrow_duration_days', $borrow_duration_days, PDO::PARAM_INT);
                $stmt->bindParam(':image_path', $image_path, PDO::PARAM_STR);
                $stmt->bindParam(':id', $inventory_id, PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Inventaris berhasil diperbarui.";
            } catch (PDOException $e) {
                error_log("Edit inventory error: " . $e->getMessage());
                $action_message = "Gagal memperbarui inventaris.";
                $action_message_type = "danger";
            }
            break;
            
        case 'delete_inventory':
            $inventory_id = intval($_POST['inventory_id']);
            try {
                // Hapus file gambar terkait sebelum menghapus data
                $sql_get_image = "SELECT image_path FROM inventory WHERE id = :id";
                $stmt_get_image = $pdo->prepare($sql_get_image);
                $stmt_get_image->bindParam(':id', $inventory_id, PDO::PARAM_INT);
                $stmt_get_image->execute();
                $item = $stmt_get_image->fetch();

                if ($item && $item['image_path']) {
                    $file_path = 'uploads/inventory/' . $item['image_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }

                $sql = "DELETE FROM inventory WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $inventory_id, PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Inventaris berhasil dihapus.";
            } catch (PDOException $e) {
                error_log("Delete inventory error: " . $e->getMessage());
                $action_message = "Gagal menghapus inventaris.";
                $action_message_type = "danger";
            }
            break;
    }
}

// Ambil data inventaris dengan JOIN ke tabel laboratories
try {
    $sql = "SELECT i.*, l.name as lab_name FROM inventory i 
            JOIN laboratories l ON i.lab_id = l.id 
            ORDER BY i.item_name";
    $stmt = $pdo->query($sql);
    $inventory_items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch inventory error: " . $e->getMessage());
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

// Ambil statistik untuk dashboard
try {
    // Total inventaris
    $sql = "SELECT COUNT(*) as total FROM inventory";
    $stmt = $pdo->query($sql);
    $stats['total_inventory'] = $stmt->fetch()['total'];
    
    // Inventaris bisa dipinjam
    $sql = "SELECT COUNT(*) as total FROM inventory WHERE is_borrowable = 1";
    $stmt = $pdo->query($sql);
    $stats['borrowable_inventory'] = $stmt->fetch()['total'];
    
    // Total quantity semua item
    $sql = "SELECT SUM(quantity) as total FROM inventory";
    $stmt = $pdo->query($sql);
    $stats['total_quantity'] = $stmt->fetch()['total'];
    
    // Jenis inventory
    $sql = "SELECT COUNT(DISTINCT item_type) as total FROM inventory";
    $stmt = $pdo->query($sql);
    $stats['total_types'] = $stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log("Fetch stats error: " . $e->getMessage());
    $stats = [
        'total_inventory' => 0,
        'borrowable_inventory' => 0,
        'total_quantity' => 0,
        'total_types' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Inventaris - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        /* Style khusus untuk halaman ini */
        .table-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
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
                    <a href="inventory.php" class="nav-link active">
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
            <h1 class="page-title">Manajemen Inventaris</h1>
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

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_inventory']; ?></div>
                <div class="stat-label">Total Item</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['borrowable_inventory']; ?></div>
                <div class="stat-label">Bisa Dipinjam</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="bi bi-boxes"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_quantity']; ?></div>
                <div class="stat-label">Total Kuantitas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="bi bi-tags"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_types']; ?></div>
                <div class="stat-label">Jenis Item</div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Daftar Inventaris</h3>
                <button class="btn-custom btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                    <i class="bi bi-plus-circle"></i> Tambah Inventaris
                </button>
            </div>
            
            <?php if (empty($inventory_items)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-box-seam" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Belum ada data inventaris.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Gambar</th>
                                <th>Nama Barang</th>
                                <th>Tipe</th>
                                <th>Laboratorium</th>
                                <th>Kuantitas</th>
                                <th>Bisa Dipinjam</th>
                                <th>Lama Pinjam (Hari)</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory_items as $item): ?>
                                <tr>
                                    <td>
                                        <img src="uploads/inventory/<?php echo htmlspecialchars($item['image_path'] ?? 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" class="table-thumbnail">
                                    </td>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php 
                                            switch($item['item_type']) {
                                                case 'hardware': echo 'Hardware'; break;
                                                case 'software': echo 'Software'; break;
                                                case 'peripheral': echo 'Peripheral'; break;
                                                case 'other': echo 'Lainnya'; break;
                                                default: echo ucfirst(htmlspecialchars($item['item_type']));
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['lab_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $item['is_borrowable'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $item['is_borrowable'] ? 'Ya' : 'Tidak'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $item['borrow_duration_days']; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning btn-action" onclick="editInventory(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger btn-action" onclick="deleteInventory(<?php echo $item['id']; ?>)">
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

    <!-- Add Inventory Modal -->
    <div class="modal fade" id="addInventoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Inventaris</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_inventory">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Laboratorium</label>
                                    <select class="form-select" name="lab_id" required>
                                        <?php foreach ($laboratories as $lab): ?>
                                            <option value="<?php echo $lab['id']; ?>"><?php echo htmlspecialchars($lab['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nama Barang</label>
                                    <input type="text" class="form-control" name="item_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tipe Barang</label>
                                    <select class="form-select" name="item_type" required>
                                        <option value="hardware">Hardware</option>
                                        <option value="software">Software</option>
                                        <option value="peripheral">Peripheral</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Kuantitas</label>
                                    <input type="number" class="form-control" name="quantity" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gambar Barang</label>
                                    <input type="file" class="form-control" name="image_path" accept="image/*">
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_borrowable" id="addIsBorrowable" checked>
                                        <label class="form-check-label" for="addIsBorrowable">
                                            Bisa Dipinjam
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Lama Pinjam (Hari)</label>
                                    <input type="number" class="form-control" name="borrow_duration_days" min="1" value="7" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea class="form-control" name="description" rows="3"></textarea>
                                </div>
                            </div>
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

    <!-- Edit Inventory Modal -->
    <div class="modal fade" id="editInventoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Inventaris</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_inventory">
                        <input type="hidden" name="inventory_id" id="editInventoryId">
                        <input type="hidden" name="existing_image_path" id="editExistingImagePath">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Laboratorium</label>
                                    <select class="form-select" name="lab_id" id="editLabId" required>
                                        <?php foreach ($laboratories as $lab): ?>
                                            <option value="<?php echo $lab['id']; ?>"><?php echo htmlspecialchars($lab['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nama Barang</label>
                                    <input type="text" class="form-control" name="item_name" id="editItemName" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tipe Barang</label>
                                    <select class="form-select" name="item_type" id="editItemType" required>
                                        <option value="hardware">Hardware</option>
                                        <option value="software">Software</option>
                                        <option value="peripheral">Peripheral</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Kuantitas</label>
                                    <input type="number" class="form-control" name="quantity" id="editQuantity" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ganti Gambar (Opsional)</label>
                                    <input type="file" class="form-control" name="image_path" accept="image/*">
                                    <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah gambar.</small>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_borrowable" id="editIsBorrowable">
                                        <label class="form-check-label" for="editIsBorrowable">
                                            Bisa Dipinjam
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Lama Pinjam (Hari)</label>
                                    <input type="number" class="form-control" name="borrow_duration_days" id="editBorrowDuration" min="1" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                                </div>
                            </div>
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
        function editInventory(itemData) {
            document.getElementById('editInventoryId').value = itemData.id;
            document.getElementById('editExistingImagePath').value = itemData.image_path || '';
            document.getElementById('editLabId').value = itemData.lab_id;
            document.getElementById('editItemName').value = itemData.item_name;
            document.getElementById('editItemType').value = itemData.item_type;
            document.getElementById('editQuantity').value = itemData.quantity;
            document.getElementById('editDescription').value = itemData.description || '';
            document.getElementById('editBorrowDuration').value = itemData.borrow_duration_days;

            // Set checkbox
            const isBorrowableCheckbox = document.getElementById('editIsBorrowable');
            if (itemData.is_borrowable == 1) {
                isBorrowableCheckbox.checked = true;
            } else {
                isBorrowableCheckbox.checked = false;
            }
            
            const editModal = new bootstrap.Modal(document.getElementById('editInventoryModal'));
            editModal.show();
        }

        function deleteInventory(id) {
            if (confirm('Apakah Anda yakin ingin menghapus inventaris ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="delete_inventory"><input type="hidden" name="inventory_id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>