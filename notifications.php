<?php
// notifications.php (HALAMAN NOTIFIKASI)
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Hanya siswa dan pengunjung yang bisa mengakses halaman ini
if (!in_array($_SESSION['user_role'], ['siswa', 'pengunjung'])) {
    header('Location: dashboard.php');
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
    error_log("Notifications error: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat data pengguna.");
}

// Tandai semua notifikasi sebagai dibaca
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'mark_all_read') {
    try {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Redirect untuk menghindari form resubmission
        header("Location: notifications.php");
        exit();
    } catch (PDOException $e) {
        error_log("Mark notifications read error: " . $e->getMessage());
    }
}

// Ambil semua notifikasi user
try {
    $sql = "SELECT n.*, ib.item_name 
            FROM notifications n
            LEFT JOIN item_borrows ib ON n.borrow_id = ib.id
            WHERE n.user_id = :user_id
            ORDER BY n.created_at DESC
            LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch notifications error: " . $e->getMessage());
    $notifications = [];
}

// Hitung notifikasi belum dibaca
 $unread_count = 0;
foreach ($notifications as $notif) {
    if (!$notif['is_read']) {
        $unread_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --primary-color: #0d6efd; --secondary-color: #0a58ca; --light-color: #f8f9fa; --dark-color: #212529; --success-color: #198754; --danger-color: #dc3545; --warning-color: #ffc107; --info-color: #0dcaf0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f7fb; color: var(--dark-color); }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 250px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 20px 0; z-index: 1000; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); }
        .sidebar-header { text-align: center; padding: 0 20px 30px; border-bottom: 1px solid rgba(255, 255, 255, 0.2); }
        .sidebar-header h3 { color: white; font-weight: bold; margin: 0; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu .nav-link { color: rgba(255, 255, 255, 0.8); padding: 12px 25px; display: flex; align-items: center; transition: all 0.3s; position: relative; }
        .sidebar-menu .nav-link:hover, .sidebar-menu .nav-link.active { color: white; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar-menu .nav-link i { margin-right: 10px; font-size: 1.2rem; }
        .notification-badge { position: absolute; top: 8px; right: 20px; background-color: var(--danger-color); color: white; border-radius: 10px; padding: 2px 6px; font-size: 0.7rem; font-weight: bold; }
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
        .notification-item { padding: 15px; border-bottom: 1px solid #eee; transition: all 0.3s; }
        .notification-item:hover { background-color: #f8f9fa; }
        .notification-item.unread { background-color: #f0f7ff; border-left: 4px solid var(--primary-color); }
        .notification-title { font-weight: 600; margin-bottom: 5px; display: flex; align-items: center; gap: 10px; }
        .notification-message { color: #666; font-size: 0.9rem; margin-bottom: 5px; }
        .notification-time { color: #999; font-size: 0.8rem; }
        .notification-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; margin-right: 15px; }
        .icon-info { background-color: var(--info-color); }
        .icon-success { background-color: var(--success-color); }
        .icon-warning { background-color: var(--warning-color); }
        .icon-danger { background-color: var(--danger-color); }
        .btn-mark-read { background-color: var(--primary-color); color: white; border: none; border-radius: 6px; padding: 8px 16px; font-weight: 500; transition: all 0.3s; }
        .btn-mark-read:hover { background-color: var(--secondary-color); color: white; }
        .mobile-toggle { display: none; position: fixed; top: 20px; left: 20px; z-index: 1001; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; padding: 10px; font-size: 1.2rem; }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s; } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-toggle { display: block; } }
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
            <a href="borrow_items.php" class="nav-link">
                <i class="bi bi-box-arrow-up-right"></i> Peminjaman Barang
            </a>
            <a href="return_items.php" class="nav-link">
                <i class="bi bi-box-arrow-in-down-left"></i> Pengembalian Barang
            </a>
            <a href="borrow_history.php" class="nav-link">
                <i class="bi bi-clock-history"></i> History Peminjaman
            </a>
            <a href="notifications.php" class="nav-link active">
                <i class="bi bi-bell"></i> Notifikasi
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
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
                    <p class="user-role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></p>
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
            <h1 class="page-title">Notifikasi</h1>
            <div>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>

        <!-- Notifications -->
        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Notifikasi Anda</h3>
                <?php if ($unread_count > 0): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn-mark-read">
                            <i class="bi bi-check-all"></i> Tandai Semua Dibaca
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-bell-slash" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Tidak ada notifikasi</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                        <div class="d-flex align-items-start">
                            <div class="notification-icon icon-<?php echo $notif['type']; ?>">
                                <?php 
                                switch($notif['type']) {
                                    case 'info': echo '<i class="bi bi-info-lg"></i>'; break;
                                    case 'success': echo '<i class="bi bi-check-lg"></i>'; break;
                                    case 'warning': echo '<i class="bi bi-exclamation-lg"></i>'; break;
                                    case 'danger': echo '<i class="bi bi-x-lg"></i>'; break;
                                    default: echo '<i class="bi bi-info-lg"></i>';
                                }
                                ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="notification-title">
                                    <?php echo htmlspecialchars($notif['title']); ?>
                                    <?php if ($notif['item_name']): ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($notif['item_name']); ?>)</small>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notif['message']); ?>
                                </div>
                                <div class="notification-time">
                                    <?php 
                                    $time = strtotime($notif['created_at']);
                                    $now = time();
                                    $diff = $now - $time;
                                    
                                    if ($diff < 60) {
                                        echo 'Baru saja';
                                    } elseif ($diff < 3600) {
                                        echo floor($diff / 60) . ' menit yang lalu';
                                    } elseif ($diff < 86400) {
                                        echo floor($diff / 3600) . ' jam yang lalu';
                                    } else {
                                        echo date('d M Y, H:i', $time);
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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
    </script>
</body>
</html>