<?php
session_start();

// Cek login dan role
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Hanya pengunjung, siswa, dan guru yang boleh akses
if (!in_array($_SESSION['user_role'], ['pengunjung', 'siswa', 'guru'])) {
    header('Location: dashboard.php');
    exit();
}

require_once 'config.php';

// Ambil data laboratorium yang aktif
 $laboratories = [];
try {
    $sql = "SELECT id, name, capacity FROM laboratories WHERE status = 'active' ORDER BY name ASC";
    $stmt = $pdo->query($sql);
    $laboratories = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal memuat data laboratorium.");
}

 $message = '';
 $message_type = '';

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_id = $_POST['lab_id'];
    $purpose = $_POST['purpose'];
    $participants = $_POST['participants'];
    $start_datetime = $_POST['start_date'] . ' ' . $_POST['start_time'];
    $end_datetime = $_POST['end_date'] . ' ' . $_POST['end_time'];
    $notes = $_POST['notes'];

    // Validasi dasar
    if (empty($lab_id) || empty($purpose) || empty($start_datetime) || empty($end_datetime)) {
        $message = 'Semua field wajib diisi kecuali catatan.';
        $message_type = 'danger';
    } elseif (strtotime($start_datetime) >= strtotime($end_datetime)) {
        $message = 'Waktu selesai harus setelah waktu mulai.';
        $message_type = 'danger';
    } elseif (strtotime($start_datetime) <= time()) {
        $message = 'Tidak bisa memesan untuk waktu yang telah lewat.';
        $message_type = 'danger';
    } else {
        try {
            // CEK KONFLIK JADWAL
            $sql_conflict = "SELECT COUNT(*) FROM reservations 
                             WHERE lab_id = :lab_id 
                             AND status = 'approved' 
                             AND (
                                 (start_time < :end_datetime AND end_time > :start_datetime)
                             )";
            $stmt_conflict = $pdo->prepare($sql_conflict);
            $stmt_conflict->bindParam(':lab_id', $lab_id);
            $stmt_conflict->bindParam(':start_datetime', $start_datetime);
            $stmt_conflict->bindParam(':end_datetime', $end_datetime);
            $stmt_conflict->execute();
            $conflict_count = $stmt_conflict->fetchColumn();

            if ($conflict_count > 0) {
                $message = 'Laboratorium sudah dipesan pada waktu tersebut. Silakan pilih waktu lain.';
                $message_type = 'warning';
            } else {
                // TIDAK ADA KONFLIK, LANJUTKAN INSERT
                $sql_insert = "INSERT INTO reservations (user_id, lab_id, purpose, participants, start_time, end_time, notes, status, created_at, updated_at) 
                                VALUES (:user_id, :lab_id, :purpose, :participants, :start_time, :end_time, :notes, 'pending', NOW(), NOW())";
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->bindParam(':user_id', $_SESSION['user_id']);
                $stmt_insert->bindParam(':lab_id', $lab_id);
                $stmt_insert->bindParam(':purpose', $purpose);
                $stmt_insert->bindParam(':participants', $participants);
                $stmt_insert->bindParam(':start_time', $start_datetime);
                $stmt_insert->bindParam(':end_time', $end_datetime);
                $stmt_insert->bindParam(':notes', $notes);

                if ($stmt_insert->execute()) {
                    // Redirect untuk mencegah resubmission
                    header('Location: book_lab.php?status=success');
                    exit();
                } else {
                    $message = 'Gagal membuat pemesanan. Silakan coba lagi.';
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            error_log("Reservation error: " . $e->getMessage());
            $message = 'Terjadi kesalahan pada server.';
            $message_type = 'danger';
        }
    }
}

// Cek parameter dari URL
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $message = 'Pemesanan berhasil dibuat! Menunggu konfirmasi dari Kepala Lab.';
    $message_type = 'success';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemesanan Laboratorium - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>SILABKOM</h3>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="book_lab.php" class="nav-link active">
                <i class="bi bi-calendar-plus"></i> Pesan Lab
            </a>
            <?php if ($_SESSION['user_role'] === 'siswa' || $_SESSION['user_role'] === 'pengunjung'): ?>
                <a href="borrow_items.php" class="nav-link">
                    <i class="bi bi-box-arrow-up-right"></i> Peminjaman Barang
                </a>
            <?php endif; ?>
            <a href="profile.php" class="nav-link">
                <i class="bi bi-person-circle"></i> Profil
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="user-role"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $_SESSION['user_role']))); ?></p>
                </div>
            </div>
            <a href="logout.php" class="nav-link" style="margin-top: 15px;">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <h1 class="page-title">Pemesanan Laboratorium</h1>
            <span><?php echo date('d F Y'); ?></span>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Formulir Pemesanan</h3>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="book_lab.php" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="lab_id" class="form-label">Laboratorium <span class="text-danger">*</span></label>
                        <select class="form-select" id="lab_id" name="lab_id" required>
                            <option value="">-- Pilih Laboratorium --</option>
                            <?php foreach ($laboratories as $lab): ?>
                                <option value="<?php echo $lab['id']; ?>" data-capacity="<?php echo $lab['capacity']; ?>">
                                    <?php echo htmlspecialchars($lab['name']); ?> (Kapasitas: <?php echo $lab['capacity']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="participants" class="form-label">Jumlah Peserta <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="participants" name="participants" min="1" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="purpose" class="form-label">Tujuan Pemesanan <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="purpose" name="purpose" rows="3" required></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="start_date" class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="start_time" class="form-label">Waktu Mulai <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="start_time" name="start_time" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="end_date" class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="end_time" class="form-label">Waktu Selesai <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="end_time" name="end_time" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Catatan (Opsional)</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="dashboard.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary-custom">Ajukan Pemesanan</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            startDateInput.setAttribute('min', today);
            endDateInput.setAttribute('min', today);

            // Update end date minimum when start date changes
            startDateInput.addEventListener('change', function() {
                endDateInput.setAttribute('min', this.value);
            });
        });
    </script>
</body>
</html>