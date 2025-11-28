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
        $sql = "UPDATE item_borrows SET status = 'return_requested', updated_at = NOW() WHERE id = :id AND user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $borrow_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $message = "Pengajuan pengembalian berhasil! Menunggu konfirmasi dari Kepala Lab.";
            $message_type = "success";
        } else {
            $message = "Gagal mengajukan pengembalian. Data tidak ditemukan.";
            $message_type = "danger";
        }
    } catch (PDOException $e) {
        error_log("Return request error: " . $e->getMessage());
        $message = "Terjadi kesalahan pada server.";
        $message_type = "danger";
    }
}

// Ambil data peminjaman
 $active_borrows = [];
 $borrow_history = [];
try {
    // Peminjaman Aktif (yang bisa dikembalikan)
    $sql_active = "SELECT ib.id, i.item_name, i.item_type, ib.borrow_date, ib.expected_return_date, ib.status
                   FROM item_borrows ib
                   JOIN inventory i ON ib.inventory_id = i.id
                   WHERE ib.user_id = :user_id AND ib.status IN ('approved', 'borrowed')
                   ORDER BY ib.expected_return_date ASC";
    $stmt_active = $pdo->prepare($sql_active);
    $stmt_active->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt_active->execute();
    $active_borrows = $stmt_active->fetchAll();

    // Riwayat Peminjaman (yang sudah selesai)
    $sql_history = "SELECT ib.id, i.item_name, i.item_type, ib.borrow_date, ib.expected_return_date, ib.actual_return_date, ib.status, ib.notes
                    FROM item_borrows ib
                    JOIN inventory i ON ib.inventory_id = i.id
                    WHERE ib.user_id = :user_id AND ib.status IN ('returned', 'rejected', 'lost', 'return_requested')
                    ORDER BY ib.updated_at DESC";
    $stmt_history = $pdo->prepare($sql_history);
    $stmt_history->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt_history->execute();
    $borrow_history = $stmt_history->fetchAll();

} catch (PDOException $e) {
    die("Gagal memuat data peminjaman.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Peminjaman Saya - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar khusus siswa -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>SILABKOM</h3>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard_student.php" class="nav-link">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="borrow_items.php" class="nav-link">
                <i class="bi bi-box-arrow-up-right"></i> Pinjam Barang
            </a>
            <a href="return_items.php" class="nav-link">
                <i class="bi bi-box-arrow-in-down-left"></i> Pengembalian Barang
            </a>
            <a href="my_borrows.php" class="nav-link"><i class="bi bi-clock-history"></i> Riwayat Peminjaman</a>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="user-role">Siswa</p>
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
            <h1 class="page-title">Riwayat Peminjaman Saya</h1>
            <span><?php echo date('d F Y'); ?></span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <!-- Nav Tabs -->
            <ul class="nav nav-tabs" id="borrowTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                        <i class="bi bi-box-arrow-up-right"></i> Peminjaman Aktif (<?php echo count($active_borrows); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                        <i class="bi bi-clock-history"></i> Riwayat
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content pt-3" id="borrowTabContent">
                <!-- Active Borrows Tab -->
                <div class="tab-pane fade show active" id="active" role="tabpanel">
                    <?php if (empty($active_borrows)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">Anda tidak memiliki peminjaman yang aktif.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Barang</th>
                                        <th>Tipe</th>
                                        <th>Tanggal Pinjam</th>
                                        <th>Harus Kembali</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_borrows as $borrow): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($borrow['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($borrow['item_type']); ?></td>
                                            <td><?php echo date('d M Y, H:i', strtotime($borrow['borrow_date'])); ?></td>
                                            <td><?php echo date('d M Y', strtotime($borrow['expected_return_date'])); ?></td>
                                            <td>
                                                <span class="badge-status badge-<?php echo $borrow['status']; ?>">
                                                    <?php echo ($borrow['status'] == 'approved') ? 'Disetujui' : 'Dipinjam'; ?>
                                                </span>
                                            </td>
                                           
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- History Tab -->
                <div class="tab-pane fade" id="history" role="tabpanel">
                    <?php if (empty($borrow_history)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clock-history" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">Belum ada riwayat peminjaman.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Barang</th>
                                        <th>Tipe</th>
                                        <th>Tanggal Pinjam</th>
                                        <th>Tanggal Dikembalikan/Ditolak</th>
                                        <th>Status Akhir</th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($borrow_history as $hist): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($hist['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($hist['item_type']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($hist['borrow_date'])); ?></td>
                                            <td>
                                                <?php 
                                                if ($hist['actual_return_date']) {
                                                    echo date('d M Y, H:i', strtotime($hist['actual_return_date']));
                                                } else {
                                                    echo date('d M Y, H:i', strtotime($hist['updated_at']));
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge-status badge-<?php echo $hist['status']; ?>">
                                                    <?php 
                                                    switch($hist['status']){
                                                        case 'returned': echo 'Dikembalikan'; break;
                                                        case 'rejected': echo 'Ditolak'; break;
                                                        case 'lost': echo 'Hilang'; break;
                                                        case 'return_requested': echo 'Menunggu Konfirmasi Pengembalian'; break;
                                                        default: echo $hist['status'];
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($hist['notes'] ?? '-'); ?></td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>