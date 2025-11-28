<?php
// dashboard_teknisi.php (DASHBOARD KHUSUS UNTUK ROLE TEKNISI)
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Cek apakah role user adalah 'teknisi'
if ($_SESSION['user_role'] !== 'teknisi') {
    // Jika bukan teknisi, redirect ke dashboard yang sesuai
    header('Location: dashboard.php');
    exit();
}

require_once 'config.php';

// Ambil data user dari database
 $user = null;
try {
    $sql = "SELECT id, name, email, nip_nim, phone, department, role, avatar, is_active FROM users WHERE id = :id LIMIT 1";
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

// Ambil statistik dashboard khusus teknisi
 $stats = [];
 $my_tasks = [];
 $recent_history = [];

try {
    // Jumlah total komputer
    $sql = "SELECT COUNT(*) as total FROM computers";
    $stmt = $pdo->query($sql);
    $stats['total_computers'] = $stmt->fetch()['total'];

    // Jumlah komputer sedang dalam maintenance
    $sql = "SELECT COUNT(*) as total FROM computers WHERE status = 'maintenance'";
    $stmt = $pdo->query($sql);
    $stats['computers_in_maintenance'] = $stmt->fetch()['total'];

    // Jumlah tugas aktif milik teknisi (in_progress)
    $sql = "SELECT COUNT(*) as total FROM computer_maintenance WHERE technician_id = :tech_id AND status = 'in_progress'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':tech_id', $user['id'], PDO::PARAM_INT);
    $stmt->execute();
    $stats['my_active_tasks'] = $stmt->fetch()['total'];

    // Jumlah maintenance selesai bulan ini oleh teknisi
    $sql = "SELECT COUNT(*) as total FROM computer_maintenance WHERE technician_id = :tech_id AND status = 'completed' AND MONTH(end_date) = MONTH(CURDATE()) AND YEAR(end_date) = YEAR(CURDATE())";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':tech_id', $user['id'], PDO::PARAM_INT);
    $stmt->execute();
    $stats['my_completed_this_month'] = $stmt->fetch()['total'];

    // Data tugas maintenance aktif milik teknisi
    $sql = "SELECT cm.id, c.computer_name, l.name as lab_name, cm.maintenance_type, cm.start_date, cm.description, cm.status
            FROM computer_maintenance cm
            JOIN computers c ON cm.computer_id = c.id
            JOIN laboratories l ON c.lab_id = l.id
            WHERE cm.technician_id = :tech_id AND cm.status IN ('pending', 'in_progress')
            ORDER BY cm.start_date DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':tech_id', $user['id'], PDO::PARAM_INT);
    $stmt->execute();
    $my_tasks = $stmt->fetchAll();

    // Data riwayat maintenance terbaru (seluruh teknisi)
    $sql = "SELECT cm.id, c.computer_name, u.name as technician_name, cm.maintenance_type, cm.end_date, cm.status
            FROM computer_maintenance cm
            JOIN computers c ON cm.computer_id = c.id
            LEFT JOIN users u ON cm.technician_id = u.id
            WHERE cm.status = 'completed'
            ORDER BY cm.end_date DESC
            LIMIT 5";
    $stmt = $pdo->query($sql);
    $recent_history = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Teknisi Dashboard error: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat data dashboard.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Teknisi - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar Dinamis Berdasarkan Role -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>SILABKOM</h3>
        </div>
        <nav class="sidebar-menu">
            <!-- Menu Khusus Teknisi -->
            <a href="dashboard_teknisi.php" class="nav-link active">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="manage_maintenance.php" class="nav-link">
                <i class="bi bi-tools"></i> Kelola Maintenance
            </a>
            <a href="computers.php" class="nav-link">
                <i class="bi bi-pc-display"></i> Daftar Komputer
            </a>
            <a href="my_maintenance_history.php" class="nav-link">
                <i class="bi bi-clock-history"></i> Riwayat Saya
            </a>
            
            <!-- Menu Profil (untuk semua role) -->
            <a href="profile.php" class="nav-link">
                <i class="bi bi-person-circle"></i> Profil
            </a>
        </nav>
        
        <!-- Sidebar Footer (sama dengan di profile.php) -->
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar role-<?php echo $user['role']; ?> <?php echo $user['is_active'] ? '' : 'inactive'; ?>">
                    <?php if ($user['avatar'] && file_exists('uploads/avatars/' . $user['avatar'])): ?>
                        <img src="uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                            <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($user['name']); ?></p>
                    <p class="user-role">
                        <i class="bi bi-wrench"></i> Teknisi
                    </p>
                </div>
            </div>
            <a href="profile.php" class="nav-link">
                <i class="bi bi-person-gear"></i> Pengaturan
            </a>
            <a href="logout.php" class="nav-link">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <h1 class="page-title">Dashboard Teknisi</h1>
            <div>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="bi bi-pc-display"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_computers']; ?></div>
                <div class="stat-label">Total Komputer</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['computers_in_maintenance']; ?></div>
                <div class="stat-label">Sedang Maintenance</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="bi bi-hammer"></i>
                </div>
                <div class="stat-value"><?php echo $stats['my_active_tasks']; ?></div>
                <div class="stat-label">Tugas Aktif Saya</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['my_completed_this_month']; ?></div>
                <div class="stat-label">Selesai Bulan Ini</div>
            </div>
        </div>

        <!-- My Active Tasks -->
        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Tugas Maintenance Saya</h3>
                <a href="manage_maintenance.php" class="btn-custom btn-primary-custom">
                    <i class="bi bi-list-task"></i> Lihat Semua Tugas
                </a>
            </div>
            <?php if (empty($my_tasks)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-clipboard-check" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Tidak ada tugas maintenance aktif untuk Anda.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Komputer</th>
                                <th>Laboratorium</th>
                                <th>Tipe Masalah</th>
                                <th>Tanggal Mulai</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['computer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($task['lab_name']); ?></td>
                                    <td><?php echo htmlspecialchars($task['maintenance_type']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($task['start_date'])); ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $task['status']; ?>">
                                            <?php 
                                            switch($task['status']) {
                                                case 'pending': echo 'Menunggu'; break;
                                                case 'in_progress': echo 'Sedang Dikerjakan'; break;
                                                case 'completed': echo 'Selesai'; break;
                                                case 'cancelled': echo 'Dibatalkan'; break;
                                                default: echo htmlspecialchars($task['status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="maintenance_detail.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Maintenance History -->
        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Riwayat Maintenance Terbaru</h3>
                <a href="maintenance_history.php" class="btn-custom btn-secondary-custom">
                    <i class="bi bi-clock-history"></i> Lihat Riwayat
                </a>
            </div>
            <?php if (empty($recent_history)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-clock-history" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Belum ada riwayat maintenance.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Komputer</th>
                                <th>Teknisi</th>
                                <th>Tipe Masalah</th>
                                <th>Tanggal Selesai</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_history as $history): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($history['computer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($history['technician_name'] ?? 'Belum ditugaskan'); ?></td>
                                    <td><?php echo htmlspecialchars($history['maintenance_type']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($history['end_date'])); ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $history['status']; ?>">
                                            <?php 
                                            switch($history['status']) {
                                                case 'completed': echo 'Selesai'; break;
                                                default: echo htmlspecialchars($history['status']);
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
    <script src="assets/js/dashboard.js"></script>
</body>
</html>