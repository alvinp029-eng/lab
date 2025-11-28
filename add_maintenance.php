<?php
// add_maintenance.php
session_start();

// Cek login dan role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'kepala_lab') {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

 $computers = [];
 $technicians = [];
 $errors = [];
 $success_message = '';

// Ambil data komputer dan teknisi untuk dropdown
try {
    // Ambil data komputer
    $sql_computers = "SELECT c.id, c.computer_name, l.name as lab_name FROM computers c JOIN laboratories l ON c.lab_id = l.id ORDER BY l.name, c.computer_name";
    $stmt_computers = $pdo->query($sql_computers);
    $computers = $stmt_computers->fetchAll();

    // Ambil data teknisi
    $sql_technicians = "SELECT id, name FROM users WHERE role = 'teknisi' ORDER BY name ASC";
    $stmt_technicians = $pdo->query($sql_technicians);
    $technicians = $stmt_technicians->fetchAll();

} catch (PDOException $e) {
    die("Gagal memuat data yang diperlukan: " . $e->getMessage());
}

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan sanitasi input
    $computer_id = filter_input(INPUT_POST, 'computer_id', FILTER_VALIDATE_INT);
    $maintenance_type = filter_input(INPUT_POST, 'maintenance_type');
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $technician_id = filter_input(INPUT_POST, 'technician_id', FILTER_VALIDATE_INT);
    $start_date = filter_input(INPUT_POST, 'start_date');
    $end_date = filter_input(INPUT_POST, 'end_date'); // Bisa kosong
    $cost = filter_input(INPUT_POST, 'cost', FILTER_VALIDATE_FLOAT);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

    // Validasi input
    if (!$computer_id) $errors[] = "Komputer harus dipilih.";
    if (!$maintenance_type) $errors[] = "Jenis pemeliharaan harus dipilih.";
    if (empty($description)) $errors[] = "Deskripsi tidak boleh kosong.";
    if (empty($start_date)) $errors[] = "Tanggal mulai tidak boleh kosong.";
    
    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO computer_maintenance 
                    (computer_id, maintenance_type, description, technician_id, start_date, end_date, cost, notes, created_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_progress')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $computer_id,
                $maintenance_type,
                $description,
                $technician_id ?: null, // Jika 0 atau false, simpan sebagai NULL
                $start_date,
                $end_date ?: null,
                $cost ?: null,
                $notes,
                $_SESSION['user_id']
            ]);

            // Set pesan sukses dan redirect
            $_SESSION['flash_message'] = "Data pemeliharaan berhasil ditambahkan.";
            header('Location: manage_maintenance.php');
            exit();

        } catch (PDOException $e) {
            $errors[] = "Terjadi kesalahan database: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pemeliharaan - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Sidebar (sama seperti manage_maintenance.php) -->
    <div class="sidebar" id="sidebar">
        <!-- ... (copy paste sidebar dari manage_maintenance.php) ... -->
        <div class="sidebar-header"><h3>SILABKOM</h3></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <div class="menu-item" id="labManagementMenu"><a href="#" class="nav-link" onclick="toggleSubmenu('labManagementMenu'); return false;"><div><i class="bi bi-building"></i><span>Manajemen Lab</span></div><i class="bi bi-chevron-down dropdown-icon"></i></a><div class="submenu"><a href="laboratories.php" class="nav-link"><i class="bi bi-house-door"></i> Laboratorium</a><a href="computers.php" class="nav-link"><i class="bi bi-pc-display"></i> Komputer</a><a href="inventory.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventaris</a></div></div>
            <div class="menu-item" id="borrowManagementMenu"><a href="#" class="nav-link" onclick="toggleSubmenu('borrowManagementMenu'); return false;"><div><i class="bi bi-clipboard-check"></i><span>Activity</span></div><i class="bi bi-chevron-down dropdown-icon"></i></a><div class="submenu"><a href="manage_borrows.php" class="nav-link"><i class="bi bi-list-check"></i> Kelola Peminjaman</a><a href="manage_bookings.php" class="nav-link"><i class="bi bi-calendar-check"></i> Pemesanan Lab</a></div></div>
            <div class="menu-item" id="maintenanceMenu"><a href="#" class="nav-link" onclick="toggleSubmenu('maintenanceMenu'); return false;"><div><i class="bi bi-tools"></i><span>Pemeliharaan</span></div><i class="bi bi-chevron-down dropdown-icon"></i></a><div class="submenu"><a href="manage_maintenance.php" class="nav-link"><i class="bi bi-list-ul"></i> Daftar Pemeliharaan</a><a href="add_maintenance.php" class="nav-link active"><i class="bi bi-plus-circle"></i> Tambah Pemeliharaan</a></div></div>
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
            <h1 class="page-title">Tambah Pemeliharaan Baru</h1>
        </div>

        <div class="content-card">
            <!-- Tampilkan Error -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="computer_id" class="form-label">Komputer <span class="text-danger">*</span></label>
                        <select class="form-select" id="computer_id" name="computer_id" required>
                            <option value="">-- Pilih Komputer --</option>
                            <?php 
                            $current_lab = '';
                            foreach ($computers as $computer):
                                if ($current_lab !== $computer['lab_name']) {
                                    if ($current_lab !== '') echo '</optgroup>';
                                    echo '<optgroup label="' . htmlspecialchars($computer['lab_name']) . '">';
                                    $current_lab = $computer['lab_name'];
                                }
                            ?>
                                <option value="<?php echo $computer['id']; ?>" <?php echo (isset($_POST['computer_id']) && $_POST['computer_id'] == $computer['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($computer['computer_name']); ?>
                                </option>
                            <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="maintenance_type" class="form-label">Jenis Pemeliharaan <span class="text-danger">*</span></label>
                        <select class="form-select" id="maintenance_type" name="maintenance_type" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="hardware" <?php echo (isset($_POST['maintenance_type']) && $_POST['maintenance_type'] == 'hardware') ? 'selected' : ''; ?>>Perangkat Keras</option>
                            <option value="software" <?php echo (isset($_POST['maintenance_type']) && $_POST['maintenance_type'] == 'software') ? 'selected' : ''; ?>>Perangkat Lunak</option>
                            <option value="network" <?php echo (isset($_POST['maintenance_type']) && $_POST['maintenance_type'] == 'network') ? 'selected' : ''; ?>>Jaringan</option>
                            <option value="cleaning" <?php echo (isset($_POST['maintenance_type']) && $_POST['maintenance_type'] == 'cleaning') ? 'selected' : ''; ?>>Pembersihan</option>
                            <option value="other" <?php echo (isset($_POST['maintenance_type']) && $_POST['maintenance_type'] == 'other') ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Deskripsi Masalah <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="3" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="technician_id" class="form-label">Teknisi yang Ditugaskan</label>
                        <select class="form-select" id="technician_id" name="technician_id">
                            <option value="">-- Belum Ditugaskan --</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?php echo $tech['id']; ?>" <?php echo (isset($_POST['technician_id']) && $_POST['technician_id'] == $tech['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tech['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : date('Y-m-d\TH:i'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">Estimasi Selesai</label>
                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="cost" class="form-label">Biaya (Opsional)</label>
                        <input type="number" class="form-control" id="cost" name="cost" step="0.01" min="0" placeholder="Contoh: 150000" value="<?php echo isset($_POST['cost']) ? htmlspecialchars($_POST['cost']) : ''; ?>">
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Catatan Tambahan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Data Pemeliharaan
                        </button>
                        <a href="manage_maintenance.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Batal
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>