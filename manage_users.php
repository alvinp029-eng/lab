<?php
// manage_users.php (FINAL VERSION - DISESUAIKAN DENGAN DASHBOARD)
session_start();

// Cek apakah user sudah login
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
    
    if (!$user || $user['role'] !== 'kepala_lab') {
        // Jika user tidak ditemukan atau bukan kepala_lab, logout
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    die("Terjadi kesalahan saat memuat data pengguna.");
}

// Inisialisasi variabel
 $add_error = '';
 $add_success = '';
 $users = [];

// Proses tambah user
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $nip_nim = trim($_POST['nip_nim']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);

    // Validasi input
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $add_error = 'Semua field wajib harus diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $add_error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $add_error = 'Password harus minimal 6 karakter.';
    } elseif (!in_array($role, ['kepala_lab', 'pj_lab', 'guru', 'teknisi'])) {
        $add_error = 'Role tidak valid.';
    } else {
        try {
            // Cek apakah email sudah terdaftar
            $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                $add_error = 'Email sudah terdaftar. Gunakan email lain.';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user baru
                $sql = "INSERT INTO users (name, email, password, role, nip_nim, phone, department, created_by) 
                        VALUES (:name, :email, :password, :role, :nip_nim, :phone, :department, :created_by)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                $stmt->bindParam(':role', $role, PDO::PARAM_STR);
                $stmt->bindParam(':nip_nim', $nip_nim, PDO::PARAM_STR);
                $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                $stmt->bindParam(':department', $department, PDO::PARAM_STR);
                $stmt->bindParam(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $add_success = 'User berhasil ditambahkan!';
                } else {
                    $add_error = 'Gagal menambahkan user. Silakan coba lagi.';
                }
            }
        } catch (PDOException $e) {
            error_log("Add user error: " . $e->getMessage());
            $add_error = 'Terjadi kesalahan server. Silakan coba lagi.';
        }
    }
}

// Ambil semua user kecuali siswa dan pengunjung
try {
    $sql = "SELECT id, name, email, role, nip_nim, phone, department, is_active, created_at 
            FROM users 
            WHERE role IN ('kepala_lab', 'pj_lab', 'guru', 'teknisi')
            ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch users error: " . $e->getMessage());
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
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
            overflow-y: auto; /* Tambahkan scroll jika menu terlalu banyak */
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
            width: 20px; /* Tambahkan lebar tetap untuk ikon */
            text-align: center;
        }
        
        /* Perbaikan untuk menu dengan submenu */
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
            padding-left: 55px; /* Indentasi untuk submenu */
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
        
        .badge-role { 
            padding: 5px 10px; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: 500; 
        }
        
        .badge-kepala_lab { background-color: #d1ecf1; color: #0c5460; }
        .badge-pj_lab { background-color: #d4edda; color: #155724; }
        .badge-guru { background-color: #fff3cd; color: #856404; }
        .badge-teknisi { background-color: #f8d7da; color: #721c24; }
        .badge-active { background-color: #d1e7dd; color: #0f5132; }
        .badge-inactive { background-color: #f8d7da; color: #721c24; }
        
        .btn-action { 
            padding: 5px 10px; 
            font-size: 0.8rem; 
            border-radius: 5px; 
        }
        
        .form-group { 
            margin-bottom: 15px; 
        }
        
        .form-label { 
            font-weight: 500; 
            margin-bottom: 5px; 
            color: #495057; 
        }
        
        .form-control, .form-select { 
            border-radius: 8px; 
            border: 1px solid #ced4da; 
            padding: 8px 12px; 
            transition: all 0.3s; 
        }
        
        .form-control:focus, .form-select:focus { 
            border-color: var(--primary-color); 
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25); 
        }
        
        .alert { 
            border-radius: 8px; 
            padding: 12px 20px; 
            margin-bottom: 20px; 
        }
        
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
        
        /* Perbaikan untuk tampilan mobile */
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
        
        /* Perbaikan untuk tampilan tablet */
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
                        <span> Activity</span>
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
            
            <!-- Menu Laporan -->
            <a href="reports.php" class="nav-link">
                <i class="bi bi-graph-up"></i> Laporan
            </a>
            
            <!-- Menu Pengguna (khusus kepala_lab) -->
            <a href="manage_users.php" class="nav-link active">
                <i class="bi bi-people"></i> Kelola Pengguna
            </a>
            
            <!-- Menu Profil (untuk semua role) -->
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
            <h1 class="page-title">Kelola Pengguna</h1>
            <div>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Tambah Pengguna Baru</h3>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($add_error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $add_error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($add_success)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill"></i> <?php echo $add_success; ?>
                </div>
            <?php endif; ?>

            <!-- Add User Form -->
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select class="form-control" name="role" required>
                                <option value="">Pilih Role</option>
                                <option value="kepala_lab">Kepala Lab</option>
                                <option value="pj_lab">PJ Lab</option>
                                <option value="guru">Guru</option>
                                <option value="teknisi">Teknisi</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">NIP/NIM</label>
                            <input type="text" class="form-control" name="nip_nim">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">No. Telepon</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Jurusan/Unit</label>
                            <input type="text" class="form-control" name="department">
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Tambah Pengguna
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Daftar Pengguna</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>NIP/NIM</th>
                            <th>Status</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-people" style="font-size: 3rem; color: #ccc;"></i>
                                    <p class="mt-3 text-muted">Belum ada pengguna.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <span class="badge-role badge-<?php echo $u['role']; ?>">
                                            <?php 
                                            switch($u['role']) {
                                                case 'kepala_lab': echo 'Kepala Lab'; break;
                                                case 'pj_lab': echo 'PJ Lab'; break;
                                                case 'guru': echo 'Guru'; break;
                                                case 'teknisi': echo 'Teknisi'; break;
                                                default: echo ucfirst(htmlspecialchars($u['role']));
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['nip_nim'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge-role badge-<?php echo $u['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $u['is_active'] ? 'Aktif' : 'Tidak Aktif'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning btn-action" onclick="editUser(<?php echo $u['id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger btn-action" onclick="deleteUser(<?php echo $u['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
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

    // Edit User Function (placeholder)
    function editUser(userId) {
        // Implement edit user functionality
        alert('Fitur edit user akan segera hadir!');
    }

    // Delete User Function (placeholder)
    function deleteUser(userId) {
        if (confirm('Apakah Anda yakin ingin menghapus pengguna ini?')) {
            // Implement delete user functionality
            alert('Fitur hapus user akan segera hadir!');
        }
    }
    </script>
</body>
</html>