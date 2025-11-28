<?php
session_start();

// Cek login dan role
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Hanya kepala_lab dan pj_lab yang boleh akses
if (!in_array($_SESSION['user_role'], ['kepala_lab', 'pj_lab'])) {
    header('Location: dashboard.php');
    exit();
}

require_once 'config.php';

 $message = '';
 $message_type = '';

// Proses persetujuan atau penolakan
if (isset($_GET['action']) && isset($_GET['id'])) {
    $reservation_id = $_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        try {
            $sql = "UPDATE reservations SET status = 'approved', approved_by = :user_id, approved_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $reservation_id);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $message = "Pemesanan berhasil disetujui.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Gagal menyetujui pemesanan.";
            $message_type = "danger";
        }
    } elseif ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $rejected_reason = $_POST['rejected_reason'];
        try {
            $sql = "UPDATE reservations SET status = 'rejected', rejected_by = :user_id, rejected_at = NOW(), rejected_reason = :reason WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $reservation_id);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':reason', $rejected_reason);
            $stmt->execute();
            $message = "Pemesanan berhasil ditolak.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Gagal menolak pemesanan.";
            $message_type = "danger";
        }
    }
    // Redirect untuk menghilangkan parameter URL
    header("Location: manage_reservations.php?message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// Ambil pesan dari URL
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type']);
}

// Ambil data pemesanan
 $pending_reservations = [];
 $all_reservations = [];
try {
    // Ambil yang pending
   $sql_pending = "SELECT r.*, u.name as user_name, l.name as lab_name FROM reservations r
                JOIN users u ON r.user_id = u.id
                JOIN laboratories l ON r.lab_id = l.id
                WHERE r.status = 'pending' AND u.role = 'pengunjung' -- TAMBAHKAN FILTER INI
                ORDER BY r.start_time ASC";
    $stmt_pending = $pdo->query($sql_pending);
    $pending_reservations = $stmt_pending->fetchAll();

    // Ambil semua untuk riwayat
    $sql_all = "SELECT r.*, u.name as user_name, l.name as lab_name FROM reservations r
                JOIN users u ON r.user_id = u.id
                JOIN laboratories l ON r.lab_id = l.id
                WHERE r.status != 'pending'
                ORDER BY r.updated_at DESC";
    $stmt_all = $pdo->query($sql_all);
    $all_reservations = $stmt_all->fetchAll();

} catch (PDOException $e) {
    die("Gagal memuat data pemesanan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pemesanan Lab - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <!-- ... (Sama seperti sidebar di dashboard.php untuk role kepala_lab/pj_lab) ... -->
        <div class="sidebar-header"><h3>SILABKOM</h3></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <div class="menu-item open" id="borrowManagementMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('borrowManagementMenu'); return false;">
                    <div><i class="bi bi-clipboard-check"></i><span> Activity</span></div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu" style="max-height: 500px;">
                    <a href="manage_borrows.php" class="nav-link"><i class="bi bi-list-check"></i> Kelola Peminjaman</a>
                    <a href="manage_reservations.php" class="nav-link active"><i class="bi bi-calendar-check"></i> Pemesanan Lab</a>
                </div>
            </div>
            <a href="reports.php" class="nav-link"><i class="bi bi-graph-up"></i> Laporan</a>
            <?php if ($_SESSION['user_role'] === 'kepala_lab'): ?>
            <a href="manage_users.php" class="nav-link"><i class="bi bi-people"></i> Kelola Pengguna</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                    <div class="user-details">
                        <p class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                        <p class="user-role"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $_SESSION['user_role']))); ?></p>
                    </div>
                </div>
                <a href="logout.php" class="nav-link" style="margin-top: 15px;"><i class="bi bi-box-arrow-right"></i> Keluar</a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <h1 class="page-title">Kelola Pemesanan Laboratorium</h1>
            <span><?php echo date('d F Y'); ?></span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Permintaan Menunggu Konfirmasi -->
        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Menunggu Konfirmasi (<?php echo count($pending_reservations); ?>)</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Pemesan</th>
                            <th>Laboratorium</th>
                            <th>Tujuan</th>
                            <th>Waktu</th>
                            <th>Peserta</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_reservations)): ?>
                            <tr><td colspan="6" class="text-center">Tidak ada pemesanan yang menunggu konfirmasi.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pending_reservations as $res): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($res['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($res['lab_name']); ?></td>
                                    <td><?php echo htmlspecialchars($res['purpose']); ?></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($res['start_time'])) . ' s/d ' . date('H:i', strtotime($res['end_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($res['participants']); ?> Orang</td>
                                    <td>
                                        <a href="?action=approve&id=<?php echo $res['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Setujui pemesanan ini?')">
                                            <i class="bi bi-check-circle"></i> Setujui
                                        </a>
                                        <button class="btn btn-sm btn-danger" onclick="openRejectModal('<?php echo $res['id']; ?>')">
                                            <i class="bi bi-x-circle"></i> Tolak
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Riwayat Pemesanan -->
        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Riwayat Pemesanan</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Pemesan</th>
                            <th>Laboratorium</th>
                            <th>Waktu</th>
                            <th>Status</th>
                            <th>Dikelola oleh</th>
                            <th>Alasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_reservations)): ?>
                            <tr><td colspan="6" class="text-center">Belum ada riwayat pemesanan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_reservations as $res): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($res['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($res['lab_name']); ?></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($res['start_time'])); ?></td>
                                    <td><span class="badge-status badge-<?php echo $res['status']; ?>"><?php echo ucfirst($res['status']); ?></span></td>
                                    <td>
                                        <?php
                                        if ($res['status'] === 'approved') {
                                            $admin = $pdo->query("SELECT name FROM users WHERE id = " . $res['approved_by'])->fetch();
                                            echo htmlspecialchars($admin['name']);
                                        } elseif ($res['status'] === 'rejected') {
                                            $admin = $pdo->query("SELECT name FROM users WHERE id = " . $res['rejected_by'])->fetch();
                                            echo htmlspecialchars($admin['name']);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $res['rejected_reason'] ? htmlspecialchars($res['rejected_reason']) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Penolakan -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="?action=reject&id=" method="POST" id="rejectForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectModalLabel">Tolak Pemesanan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label for="rejected_reason" class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejected_reason" name="rejected_reason" rows="3" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Tolak</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        function openRejectModal(id) {
            const form = document.getElementById('rejectForm');
            form.action = `?action=reject&id=${id}`;
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        }
    </script>
</body>
</html>