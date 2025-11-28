<?php
// manage_bookings.php (FINAL VERSION - DISESUAIKAN DENGAN DASHBOARD)
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
    
    if (!$user || !in_array($user['role'], ['kepala_lab', 'pj_lab'])) {
        // Jika user tidak ditemukan atau tidak memiliki izin, logout
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
 $pending_bookings = [];
 $approved_bookings = [];
 $booking_history = [];

// Proses aksi (setujui, tolak, selesaikan, batalkan)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['booking_id'])) {
    $booking_id = intval($_POST['booking_id']);
    
    try {
        switch ($_POST['action']) {
            case 'approve':
                $sql = "UPDATE reservations SET status = 'approved', approved_by = :admin_id, approved_at = NOW() WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Pemesanan disetujui.";
                break;
            case 'reject':
                $rejected_reason = $_POST['rejected_reason'];
                $sql = "UPDATE reservations SET status = 'rejected', rejected_by = :admin_id, rejected_at = NOW(), rejected_reason = :reason WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(':reason', $rejected_reason);
                $stmt->execute();
                $action_message = "Pemesanan ditolak.";
                break;
            case 'complete':
                $sql = "UPDATE reservations SET status = 'completed' WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Pemesanan ditandai selesai.";
                break;
            case 'cancel':
                $sql = "UPDATE reservations SET status = 'cancelled' WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Pemesanan dibatalkan.";
                break;
            default:
                throw new Exception("Aksi tidak valid.");
                break;
        }
        
        // Redirect untuk mencegah resubmission form
        header("Location: manage_bookings.php?message=" . urlencode($action_message) . "&type=" . $action_message_type);
        exit();

    } catch (Exception $e) {
        error_log("Action error: " . $e->getMessage());
        $action_message = "Gagal melakukan aksi: " . $e->getMessage();
        $action_message_type = 'danger';
    }
}

// Ambil pesan dari URL
if (isset($_GET['message'])) {
    $action_message = htmlspecialchars($_GET['message']);
    $action_message_type = htmlspecialchars($_GET['type']);
}

// Ambil data untuk setiap tab dengan error handling
try {
    // Permintaan Menunggu Persetujuan
    $sql = "SELECT r.*, u.name as user_name, l.name as lab_name 
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            JOIN laboratories l ON r.lab_id = l.id
            WHERE r.status = 'pending' AND u.role = 'pengunjung'
            ORDER BY r.created_at DESC";
    $stmt = $pdo->query($sql);
    $pending_bookings = $stmt->fetchAll();

    // Pemesanan Disetujui
    $sql = "SELECT r.*, u.name as user_name, l.name as lab_name 
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            JOIN laboratories l ON r.lab_id = l.id
            WHERE r.status = 'approved' AND u.role = 'pengunjung'
            ORDER BY r.start_time ASC";
    $stmt = $pdo->query($sql);
    $approved_bookings = $stmt->fetchAll();

    // Riwayat Pemesanan
    $sql = "SELECT r.*, u.name as user_name, l.name as lab_name 
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            JOIN laboratories l ON r.lab_id = l.id
            WHERE r.status IN ('completed', 'rejected', 'cancelled') AND u.role = 'pengunjung'
            ORDER BY r.updated_at DESC
            LIMIT 50";
    $stmt = $pdo->query($sql);
    $booking_history = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Fetch data error: " . $e->getMessage());
    // Jika tabel tidak ada, variabel akan kosong dan menampilkan pesan
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
    <style>
        /* Style khusus untuk halaman ini */
        .table-custom {
            width: 100%;
            table-layout: auto; /* Biarkan browser menentukan lebar kolom secara otomatis */
            word-wrap: break-word; /* Memastikan kata yang sangat panjang akan dipotong */
        }
        
        .table-custom th, 
        .table-custom td {
            vertical-align: top;
            padding: 12px 15px; /* Padding sedikit lebih besar untuk kenyamanan membaca */
            word-wrap: break-word; /* Pastikan teks di dalam sel juga bisa pindah baris */
        }

        /* Atur lebar minimal untuk kolom tertentu agar tidak terlalu sempit */
        .table-custom th:nth-child(2), /* Kolom Pemesan */
        .table-custom th:nth-child(3), /* Kolom Laboratorium */
        .table-custom th:nth-child(4) { /* Kolom Tujuan */
            min-width: 150px;
        }

        .table-custom th:nth-child(5) { /* Kolom Waktu */
            min-width: 200px;
            white-space: nowrap; /* Kolom waktu sebaiknya tidak pindah baris */
        }

        /* Style untuk teks yang panjang di tabel */
        .notes-text {
            display: block;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
            <div class="menu-item open" id="borrowManagementMenu">
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
                    <a href="manage_bookings.php" class="nav-link active">
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
            <h1 class="page-title">Kelola Pemesanan Laboratorium</h1>
            <div>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>

        <!-- Alert Message -->
        <?php if ($action_message): ?>
            <div class="alert alert-<?php echo $action_message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $action_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <!-- Nav Tabs -->
            <ul class="nav nav-tabs" id="bookingTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                        <i class="bi bi-hourglass-split"></i> Menunggu Persetujuan (<?php echo count($pending_bookings); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">
                        <i class="bi bi-calendar-check"></i> Disetujui (<?php echo count($approved_bookings); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                        <i class="bi bi-clock-history"></i> Riwayat
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content pt-3" id="bookingTabContent">
                <!-- Pending Bookings Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <?php if (empty($pending_bookings)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-hourglass-split" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">Tidak ada pemesanan menunggu persetujuan.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Tanggal Diajukan</th>
                                        <th>Pemesan</th>
                                        <th>Laboratorium</th>
                                        <th>Tujuan</th>
                                        <th>Waktu</th>
                                        <th>Catatan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo date('d M Y, H:i', strtotime($booking['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['lab_name']); ?></td>
                                            <td>
                                                <span class="notes-text" title="<?php echo htmlspecialchars($booking['purpose']); ?>">
                                                    <?php echo htmlspecialchars($booking['purpose']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                    $start = new DateTime($booking['start_time']);
                                                    $end = new DateTime($booking['end_time']);
                                                    echo $start->format('d M Y, H:i') . ' - ' . $end->format('H:i');
                                                ?>
                                            </td>
                                            <td>
                                                <span class="notes-text" title="<?php echo htmlspecialchars($booking['notes'] ?? ''); ?>">
                                                    <?php echo htmlspecialchars($booking['notes'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-success btn-action" onclick="openApproveModal(<?php echo $booking['id']; ?>)">
                                                    <i class="bi bi-check-circle"></i> Setujui
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-action" onclick="openRejectModal(<?php echo $booking['id']; ?>)">
                                                    <i class="bi bi-x-circle"></i> Tolak
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Approved Bookings Tab -->
                <div class="tab-pane fade" id="approved" role="tabpanel">
                    <?php if (empty($approved_bookings)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-check" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">Tidak ada pemesanan yang disetujui.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Pemesan</th>
                                        <th>Laboratorium</th>
                                        <th>Tujuan</th>
                                        <th>Waktu</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['lab_name']); ?></td>
                                            <td>
                                                <span class="notes-text" title="<?php echo htmlspecialchars($booking['purpose']); ?>">
                                                    <?php echo htmlspecialchars($booking['purpose']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                    $start = new DateTime($booking['start_time']);
                                                    $end = new DateTime($booking['end_time']);
                                                    echo $start->format('d M Y, H:i') . ' - ' . $end->format('H:i');
                                                ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary btn-action" onclick="openCompleteModal(<?php echo $booking['id']; ?>)">
                                                    <i class="bi bi-check2-square"></i> Selesai
                                                </button>
                                                <button class="btn btn-sm btn-warning btn-action" onclick="openCancelModal(<?php echo $booking['id']; ?>)">
                                                    <i class="bi bi-x-square"></i> Batalkan
                                                </button>
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
                    <?php if (empty($booking_history)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clock-history" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">Belum ada riwayat pemesanan.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Pemesan</th>
                                        <th>Laboratorium</th>
                                        <th>Tujuan</th>
                                        <th>Waktu</th>
                                        <th>Status Akhir</th>
                                        <th>Alasan/Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($booking_history as $booking): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['lab_name']); ?></td>
                                            <td>
                                                <span class="notes-text" title="<?php echo htmlspecialchars($booking['purpose']); ?>">
                                                    <?php echo htmlspecialchars($booking['purpose']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d M Y, H:i', strtotime($booking['start_time'])); ?></td>
                                            <td><span class="badge-status badge-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                            <td>
                                                <span class="notes-text" title="<?php echo htmlspecialchars($booking['rejected_reason'] ?? $booking['notes'] ?? ''); ?>">
                                                    <?php echo htmlspecialchars($booking['rejected_reason'] ?? $booking['notes'] ?? '-'); ?>
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
        </div>
    </div>

    <!-- Modals (Approve, Reject, Complete, Cancel) -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalLabel">Konfirmasi Aksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="actionForm">
                    <div class="modal-body">
                        <p id="actionModalMessage">Apakah Anda yakin?</p>
                        <div id="reasonContainer" class="mb-3" style="display:none;">
                            <label for="actionReason" class="form-label">Alasan</label>
                            <textarea class="form-control" id="actionReason" name="rejected_reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" id="actionType">
                        <input type="hidden" name="booking_id" id="booking_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="actionSubmitButton">Lanjutkan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        function openApproveModal(id) {
            document.getElementById('actionModalLabel').textContent = 'Setujui Pemesanan';
            document.getElementById('actionModalMessage').textContent = 'Apakah Anda yakin ingin menyetujui pemesanan ini?';
            document.getElementById('reasonContainer').style.display = 'none';
            document.getElementById('actionType').value = 'approve';
            document.getElementById('booking_id').value = id;
            document.getElementById('actionSubmitButton').className = 'btn btn-success';
            document.getElementById('actionSubmitButton').textContent = 'Setujui';
            new bootstrap.Modal(document.getElementById('actionModal')).show();
        }

        function openRejectModal(id) {
            document.getElementById('actionModalLabel').textContent = 'Tolak Pemesanan';
            document.getElementById('actionModalMessage').textContent = 'Harap berikan alasan penolakan.';
            document.getElementById('reasonContainer').style.display = 'block';
            document.getElementById('actionType').value = 'reject';
            document.getElementById('booking_id').value = id;
            document.getElementById('actionSubmitButton').className = 'btn btn-danger';
            document.getElementById('actionSubmitButton').textContent = 'Tolak';
            new bootstrap.Modal(document.getElementById('actionModal')).show();
        }
        
        function openCompleteModal(id) {
            document.getElementById('actionModalLabel').textContent = 'Tandai Selesai';
            document.getElementById('actionModalMessage').textContent = 'Apakah Anda yakin ingin menandai pemesanan ini selesai?';
            document.getElementById('reasonContainer').style.display = 'none';
            document.getElementById('actionType').value = 'complete';
            document.getElementById('booking_id').value = id;
            document.getElementById('actionSubmitButton').className = 'btn btn-primary';
            document.getElementById('actionSubmitButton').textContent = 'Tandai Selesai';
            new bootstrap.Modal(document.getElementById('actionModal')).show();
        }
        
        function openCancelModal(id) {
            document.getElementById('actionModalLabel').textContent = 'Batalkan Pemesanan';
            document.getElementById('actionModalMessage').textContent = 'Apakah Anda yakin ingin membatalkan pemesanan ini?';
            document.getElementById('reasonContainer').style.display = 'none';
            document.getElementById('actionType').value = 'cancel';
            document.getElementById('booking_id').value = id;
            document.getElementById('actionSubmitButton').className = 'btn btn-warning';
            document.getElementById('actionSubmitButton').textContent = 'Batalkan';
            new bootstrap.Modal(document.getElementById('actionModal')).show();
        }
    </script>
</body>
</html>