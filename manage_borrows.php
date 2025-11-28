<?php
// manage_borrows.php (FINAL VERSION - DISESUAIKAN DENGAN DASHBOARD)
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
 $action_message_type = 'success'; // default
 $pending_requests = [];
 $return_requests = [];
 $active_borrows = [];
 $borrow_history = [];

// PROSES PERBAIKAN: Gunakan transaksi untuk aksi yang memodifikasi beberapa tabel
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['borrow_id'])) {
    $borrow_id = intval($_POST['borrow_id']);
    
    try {
        // Mulai transaksi
        $pdo->beginTransaction();

        switch ($_POST['action']) {
            case 'approve':
                // Tidak perlu mengubah quantity, karena sudah dikurangi saat siswa mengajukan
                $sql = "UPDATE item_borrows SET status = 'approved', approved_by = :admin_id, borrow_date = NOW() WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':id', $borrow_id, PDO::PARAM_INT);
                $stmt->execute();
                $action_message = "Peminjaman disetujui.";
                break;

            case 'reject':
                // 1. Ambil inventory_id dari peminjaman yang akan ditolak
                $sql_get_borrow = "SELECT inventory_id FROM item_borrows WHERE id = :id FOR UPDATE"; // FOR UPDATE untuk mencegah race condition
                $stmt_get_borrow = $pdo->prepare($sql_get_borrow);
                $stmt_get_borrow->bindParam(':id', $borrow_id, PDO::PARAM_INT);
                $stmt_get_borrow->execute();
                $borrow = $stmt_get_borrow->fetch(PDO::FETCH_ASSOC);

                if ($borrow) {
                    // 2. Kembalikan quantity ke inventory
                    $sql_return = "UPDATE inventory SET quantity = quantity + 1 WHERE id = :id";
                    $stmt_return = $pdo->prepare($sql_return);
                    $stmt_return->bindParam(':id', $borrow['inventory_id'], PDO::PARAM_INT);
                    $stmt_return->execute();

                    // 3. Update status peminjaman
                    $sql = "UPDATE item_borrows SET status = 'rejected', rejected_by = :admin_id, rejected_at = NOW() WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
                    $stmt->bindParam(':id', $borrow_id, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $action_message = "Peminjaman ditolak dan stok dikembalikan.";
                } else {
                    throw new Exception("Data peminjaman tidak ditemukan.");
                }
                break;

            case 'confirm_return':
                // 1. Ambil inventory_id dari peminjaman yang akan dikembalikan
                $sql_get_borrow = "SELECT inventory_id FROM item_borrows WHERE id = :id FOR UPDATE";
                $stmt_get_borrow = $pdo->prepare($sql_get_borrow);
                $stmt_get_borrow->bindParam(':id', $borrow_id, PDO::PARAM_INT);
                $stmt_get_borrow->execute();
                $borrow = $stmt_get_borrow->fetch(PDO::FETCH_ASSOC);

                if ($borrow) {
                    // 2. Tambahkan quantity kembali ke inventory
                    $sql_return = "UPDATE inventory SET quantity = quantity + 1 WHERE id = :id";
                    $stmt_return = $pdo->prepare($sql_return);
                    $stmt_return->bindParam(':id', $borrow['inventory_id'], PDO::PARAM_INT);
                    $stmt_return->execute();

                    // 3. Update status peminjaman
                    $sql = "UPDATE item_borrows SET status = 'returned', actual_return_date = NOW() WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $borrow_id, PDO::PARAM_INT);
                    $stmt->execute();

                    $action_message = "Pengembalian dikonfirmasi dan stok ditambah.";
                } else {
                    throw new Exception("Data peminjaman tidak ditemukan.");
                }
                break;
            
            default:
                throw new Exception("Aksi tidak valid.");
                break;
        }

        // Jika semua query berhasil, commit transaksi
        $pdo->commit();

    } catch (Exception $e) {
        // Jika ada error, rollback semua perubahan
        $pdo->rollBack();
        error_log("Action error: " . $e->getMessage());
        $action_message = "Gagal melakukan aksi: " . $e->getMessage();
        $action_message_type = 'danger';
    }
}

// Ambil data untuk setiap tab dengan error handling
try {
    // Permintaan Menunggu Persetujuan
    $sql = "SELECT ib.*, u.name as user_name, i.item_name, i.item_type 
            FROM item_borrows ib
            JOIN users u ON ib.user_id = u.id
            JOIN inventory i ON ib.inventory_id = i.id
            WHERE ib.status = 'pending' AND u.role = 'siswa'
            ORDER BY ib.created_at DESC";
    $stmt = $pdo->query($sql);
    $pending_requests = $stmt->fetchAll();

    // Pengembalian Menunggu Konfirmasi
    $sql = "SELECT ib.*, u.name as user_name, i.item_name, i.item_type 
            FROM item_borrows ib
            JOIN users u ON ib.user_id = u.id
            JOIN inventory i ON ib.inventory_id = i.id
            WHERE ib.status = 'return_requested'
            ORDER BY ib.updated_at DESC";
    $stmt = $pdo->query($sql);
    $return_requests = $stmt->fetchAll();

    // Peminjaman Aktif
    $sql = "SELECT ib.*, u.name as user_name, i.item_name, i.item_type 
            FROM item_borrows ib
            JOIN users u ON ib.user_id = u.id
            JOIN inventory i ON ib.inventory_id = i.id
            WHERE ib.status IN ('approved', 'overdue')
            ORDER BY ib.borrow_date DESC";
    $stmt = $pdo->query($sql);
    $active_borrows = $stmt->fetchAll();

    // Riwayat Peminjaman
    $sql = "SELECT ib.*, u.name as user_name, i.item_name, i.item_type 
            FROM item_borrows ib
            JOIN users u ON ib.user_id = u.id
            JOIN inventory i ON ib.inventory_id = i.id
            WHERE ib.status IN ('returned', 'rejected', 'lost')
            ORDER BY ib.actual_return_date DESC, ib.updated_at DESC
            LIMIT 50";
    $stmt = $pdo->query($sql);
    $borrow_history = $stmt->fetchAll();

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
    <title>Kelola Peminjaman - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- PERBAIKAN: Pindahkan CSS ke file eksternal -->
    <link rel="stylesheet" href="assets/css/dashboard.css">
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
                    <a href="manage_borrows.php" class="nav-link active">
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
            <h1 class="page-title">Kelola Peminjaman Barang</h1>
            <div>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>

        <!-- Alert Message -->
        <?php if (!empty($action_message)): ?>
            <div class="alert alert-<?php echo $action_message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $action_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <!-- Nav Tabs -->
            <ul class="nav nav-tabs" id="borrowTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                        <i class="bi bi-hourglass-split"></i> Menunggu Persetujuan (<?php echo count($pending_requests); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="return-tab" data-bs-toggle="tab" data-bs-target="#return" type="button" role="tab">
                        <i class="bi bi-box-arrow-in-down-left"></i> Pengembalian Menunggu (<?php echo count($return_requests); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
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
                <!-- Pending Requests Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <?php if (empty($pending_requests)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-hourglass-split" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">Tidak ada permintaan menunggu persetujuan.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Tanggal Diajukan</th>
                                        <th>Peminjam</th>
                                        <th>Barang</th>
                                        <th>Tipe</th>
                                        <th>Tujuan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $req): ?>
                                        <tr>
                                            <td><?php echo date('d M Y, H:i', strtotime($req['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($req['user_name']); ?></td>
                                            <td><?php echo htmlspecialchars($req['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($req['item_type']); ?></td>
                                            <td><?php echo htmlspecialchars($req['borrow_purpose']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-success btn-action" onclick="approveBorrow(<?php echo $req['id']; ?>)">
                                                    <i class="bi bi-check-circle"></i> Setujui
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-action" onclick="rejectBorrow(<?php echo $req['id']; ?>)">
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

                <!-- Return Requests Tab -->
                <div class="tab-pane fade" id="return" role="tabpanel">
                    <?php if (empty($return_requests)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">Tidak ada pengembalian menunggu konfirmasi.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Tanggal Diajukan</th>
                                        <th>Peminjam</th>
                                        <th>Barang</th>
                                        <th>Tipe</th>
                                        <th>Catatan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($return_requests as $req): ?>
                                        <tr>
                                            <td><?php echo date('d M Y, H:i', strtotime($req['updated_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($req['user_name']); ?></td>
                                            <td><?php echo htmlspecialchars($req['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($req['item_type']); ?></td>
                                            <td><?php echo htmlspecialchars($req['notes'] ?? '-'); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary btn-action" onclick="confirmReturn(<?php echo $req['id']; ?>)">
                                                    <i class="bi bi-check-circle"></i> Konfirmasi
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Active Borrows Tab -->
                <div class="tab-pane fade" id="active" role="tabpanel">
                    <?php if (empty($active_borrows)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">Tidak ada peminjaman aktif.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Tanggal Pinjam</th>
                                        <th>Peminjam</th>
                                        <th>Barang</th>
                                        <th>Harus Kembali</th>
                                        <th>Sisa Hari</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_borrows as $borrow): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($borrow['borrow_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($borrow['user_name']); ?></td>
                                            <td><?php echo htmlspecialchars($borrow['item_name']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($borrow['expected_return_date'])); ?></td>
                                            <td>
                                                <?php 
                                                    $now = new DateTime();
                                                    $due = new DateTime($borrow['expected_return_date']);
                                                    $interval = $now->diff($due);
                                                    if ($borrow['status'] == 'overdue') {
                                                        echo '<span class="text-danger">Terlambat ' . $interval->days . ' hari</span>';
                                                    } else {
                                                        echo $interval->days . ' hari lagi';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge-status badge-<?php echo $borrow['status']; ?>">
                                                    <?php echo ($borrow['status'] == 'approved') ? 'Dipinjam' : 'Terlambat'; ?>
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
                                        <th>Peminjam</th>
                                        <th>Barang</th>
                                        <th>Tanggal Pinjam</th>
                                        <th>Tanggal Kembali</th>
                                        <th>Status Akhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($borrow_history as $hist): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($hist['user_name']); ?></td>
                                            <td><?php echo htmlspecialchars($hist['item_name']); ?></td>
                                            <td><?php echo $hist['borrow_date'] ? date('d M Y', strtotime($hist['borrow_date'])) : '-'; ?></td>
                                            <td><?php echo $hist['actual_return_date'] ? date('d M Y', strtotime($hist['actual_return_date'])) : '-'; ?></td>
                                            <td>
                                                <span class="badge-status badge-<?php echo $hist['status']; ?>">
                                                    <?php 
                                                    switch($hist['status']){
                                                        case 'returned': echo 'Dikembalikan'; break;
                                                        case 'rejected': echo 'Ditolak'; break;
                                                        case 'lost': echo 'Hilang'; break;
                                                        default: echo $hist['status'];
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        // PERBAIKAN: Fungsi aksi yang lebih aman dan terstruktur
        function approveBorrow(id) {
            if (confirm('Setujui permintaan peminjaman ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="borrow_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function rejectBorrow(id) {
            if (confirm('Tolak permintaan peminjaman ini? Stok akan dikembalikan.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="borrow_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function confirmReturn(id) {
            if (confirm('Konfirmasi pengembalian ini? Stok akan ditambah.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="confirm_return">
                    <input type="hidden" name="borrow_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>