<?php
session_start();

// Cek apakah user sudah login dan role-nya adalah 'siswa'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'siswa') {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

 $message = '';
 $message_type = '';

// Proses pengajuan pengembalian
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_return') {
    $borrow_id = intval($_POST['borrow_id']);

    try {
        // Update status peminjaman menjadi 'return_requested'
        $sql = "UPDATE item_borrows SET status = 'return_requested', updated_at = NOW() WHERE id = :id AND user_id = :user_id AND status IN ('approved', 'borrowed')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $borrow_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $message = "Pengajuan pengembalian berhasil! Menunggu konfirmasi dari Kepala Lab.";
            $message_type = "success";
        } else {
            $message = "Gagal mengajukan pengembalian. Barang tidak ditemukan atau sudah dalam proses pengembalian.";
            $message_type = "danger";
        }
    } catch (PDOException $e) {
        error_log("Return request error: " . $e->getMessage());
        $message = "Terjadi kesalahan pada server.";
        $message_type = "danger";
    }
}

// Ambil data peminjaman aktif untuk user ini
 $active_borrows = [];
try {
    $sql = "SELECT ib.id, i.item_name, i.item_type, i.image_path, ib.borrow_date, ib.expected_return_date, ib.status
            FROM item_borrows ib
            JOIN inventory i ON ib.inventory_id = i.id
            WHERE ib.user_id = :user_id AND ib.status IN ('approved', 'borrowed')
            ORDER BY ib.expected_return_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $active_borrows = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal memuat data peminjaman aktif.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembalian Barang - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        /* Gunakan style yang sama dengan borrow_items.php untuk konsistensi */
        .item-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .item-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background-color: #f8f9fa;
        }
        .item-card-body {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .item-card-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--dark-color);
        }
        .item-card-text {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .item-card-footer {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            margin-top: auto;
        }
        .btn-return {
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Sidebar khusus siswa -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><h3>SILABKOM</h3></div>
        <nav class="sidebar-menu">
            <a href="dashboard_student.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="borrow_items.php" class="nav-link"><i class="bi bi-box-arrow-up-right"></i> Pinjam Barang</a>
            <a href="return_items.php" class="nav-link active"><i class="bi bi-box-arrow-in-down-left"></i> Pengembalian Barang</a>
            <a href="my_borrows.php" class="nav-link"><i class="bi bi-clock-history"></i> Riwayat Peminjaman</a>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="user-role">Siswa</p>
                </div>
            </div>
            <a href="logout.php" class="nav-link" style="margin-top: 15px;"><i class="bi bi-box-arrow-right"></i> Keluar</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <h1 class="page-title">Pengembalian Barang</h1>
            <span><?php echo date('d F Y'); ?></span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Barang yang Sedang Dipinjam</h3>
            </div>
            <div class="row g-4">
                <?php if (empty($active_borrows)): ?>
                    <div class="col-12">
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle" style="font-size: 3rem; color: #198754;"></i>
                            <p class="mt-3 text-muted">Anda tidak memiliki barang yang harus dikembalikan saat ini.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($active_borrows as $borrow): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="item-card">
                                <img src="uploads/inventory/<?php echo htmlspecialchars($borrow['image_path'] ?? 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($borrow['item_name']); ?>">
                                <div class="item-card-body">
                                    <h5 class="item-card-title"><?php echo htmlspecialchars($borrow['item_name']); ?></h5>
                                    <p class="item-card-text"><i class="bi bi-tag"></i> <?php echo htmlspecialchars($borrow['item_type']); ?></p>
                                    <p class="item-card-text"><i class="bi bi-calendar-event"></i> Dipinjam: <?php echo date('d M Y', strtotime($borrow['borrow_date'])); ?></p>
                                    <p class="item-card-text"><i class="bi bi-calendar-check"></i> Harus Kembali: <strong><?php echo date('d M Y', strtotime($borrow['expected_return_date'])); ?></strong></p>
                                </div>
                                <div class="item-card-footer">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="request_return">
                                        <input type="hidden" name="borrow_id" value="<?php echo $borrow['id']; ?>">
                                        <button type="submit" class="btn btn-warning btn-return" onclick="return confirm('Ajukan pengembalian untuk barang ini?')">
                                            <i class="bi bi-box-arrow-in-down-left"></i> Ajukan Pengembalian
                                        </button>
                                    </form>
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