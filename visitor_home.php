<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'pengunjung') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

 $laboratories = [];
try {
    $sql = "SELECT id, name, description, image, location, capacity FROM laboratories WHERE status = 'active' ORDER BY name ASC";
    $stmt = $pdo->query($sql);
    $laboratories = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal memuat data laboratorium.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .lab-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        .lab-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .lab-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background-color: #f0f0f0;
        }
        .lab-card-body {
            padding: 20px;
        }
        .lab-card-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><h3>SILABKOM</h3></div>
        <nav class="sidebar-menu">
            <a href="visitor_home.php" class="nav-link active"><i class="bi bi-house-door"></i> Beranda</a>
            <a href="visitor_bookings.php" class="nav-link"><i class="bi bi-calendar-check"></i> Pemesanan Saya</a>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="user-role">Pengunjung</p>
                </div>
            </div>
            <a href="logout.php" class="nav-link" style="margin-top: 15px;"><i class="bi bi-box-arrow-right"></i> Keluar</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <h1 class="page-title">Daftar Laboratorium</h1>
            <span><?php echo date('d F Y'); ?></span>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Pilih Laboratorium untuk Dipesan</h3>
            </div>
            <div class="row g-4">
                <?php if (empty($laboratories)): ?>
                    <div class="col-12">
                        <p class="text-center text-muted">Belum ada laboratorium yang tersedia.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($laboratories as $lab): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="lab-card">
                                <img src="uploads/labs/<?php echo htmlspecialchars($lab['image'] ?? 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($lab['name']); ?>">
                                <div class="lab-card-body">
                                    <h5 class="lab-card-title"><?php echo htmlspecialchars($lab['name']); ?></h5>
                                    <p class="text-muted small"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($lab['location']); ?> | <i class="bi bi-people"></i> Kapasitas: <?php echo htmlspecialchars($lab['capacity']); ?></p>
                                    <p><?php echo htmlspecialchars(substr($lab['description'], 0, 80)) . '...'; ?></p>
                                    <a href="book_lab.php?lab_id=<?php echo $lab['id']; ?>" class="btn btn-primary-custom w-100">
                                        <i class="bi bi-calendar-plus"></i> Pesan Lab Ini
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>