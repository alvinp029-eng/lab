<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'siswa') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

 $message = '';
 $message_type = '';

// Ambil data inventaris yang bisa dipinjam
 $items = [];
try {
    // Query sekarang mengambil quantity dan image_path
    $sql = "SELECT id, item_name, item_type, quantity, image_path, borrow_duration_days FROM inventory WHERE is_borrowable = 1 ORDER BY item_name ASC";
    $stmt = $pdo->query($sql);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal memuat data inventaris.");
}

// Proses form submission (peminjaman)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inventory_id'])) {
    $inventory_id = $_POST['inventory_id'];
    $borrow_purpose = $_POST['borrow_purpose'];
    $notes = $_POST['notes'];

    if (empty($inventory_id) || empty($borrow_purpose)) {
        $message = 'Barang dan tujuan peminjaman wajib diisi.';
        $message_type = 'danger';
    } else {
        // Gunakan transaksi untuk memastikan integritas data
        $pdo->beginTransaction();
        try {
            // 1. Kunci baris inventaris untuk mencegah race condition (dua orang meminjam barang terakhir bersamaan)
            $sql_lock = "SELECT quantity, borrow_duration_days FROM inventory WHERE id = :id FOR UPDATE";
            $stmt_lock = $pdo->prepare($sql_lock);
            $stmt_lock->bindParam(':id', $inventory_id, PDO::PARAM_INT);
            $stmt_lock->execute();
            $item_to_borrow = $stmt_lock->fetch(PDO::FETCH_ASSOC);

            if (!$item_to_borrow || $item_to_borrow['quantity'] <= 0) {
                $pdo->rollBack();
                $message = 'Barang tidak tersedia atau stok habis.';
                $message_type = 'warning';
            } else {
                // 2. Kurangi kuantitas barang
                $new_quantity = $item_to_borrow['quantity'] - 1;
                $sql_update = "UPDATE inventory SET quantity = :quantity WHERE id = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':quantity', $new_quantity, PDO::PARAM_INT);
                $stmt_update->bindParam(':id', $inventory_id, PDO::PARAM_INT);
                $stmt_update->execute();

                // 3. Tambahkan record peminjaman
                $borrow_duration = (int)$item_to_borrow['borrow_duration_days'];
                $expected_return_date = date('Y-m-d H:i:s', strtotime("+$borrow_duration days"));

                $sql_insert = "INSERT INTO item_borrows (user_id, inventory_id, borrow_date, expected_return_date, borrow_purpose, notes, status, created_at, updated_at)
                                VALUES (:user_id, :inventory_id, NOW(), :expected_return_date, :borrow_purpose, :notes, 'pending', NOW(), NOW())";
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt_insert->bindParam(':inventory_id', $inventory_id, PDO::PARAM_INT);
                $stmt_insert->bindParam(':expected_return_date', $expected_return_date);
                $stmt_insert->bindParam(':borrow_purpose', $borrow_purpose);
                $stmt_insert->bindParam(':notes', $notes);
                $stmt_insert->execute();

                // 4. Jika semua berhasil, commit transaksi
                $pdo->commit();
                header('Location: borrow_items.php?status=success');
                exit();
            }
        } catch (Exception $e) {
            // Jika ada error, rollback semua perubahan
            $pdo->rollBack();
            error_log("Borrow item error: " . $e->getMessage());
            $message = 'Terjadi kesalahan saat memproses peminjaman.';
            $message_type = 'danger';
        }
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $message = 'Peminjaman berhasil diajukan! Menunggu persetujuan dari Kepala Lab.';
    $message_type = 'success';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pinjam Barang - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
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
        }
        .btn-borrow {
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
            <a href="borrow_items.php" class="nav-link active"><i class="bi bi-box-arrow-up-right"></i> Pinjam Barang</a>
            <a href="return_items.php" class="nav-link">
                <i class="bi bi-box-arrow-in-down-left"></i> Pengembalian Barang
            </a>
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
            <h1 class="page-title">Peminjaman Barang</h1>
            <span><?php echo date('d F Y'); ?></span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if (empty($items)): ?>
                <div class="col-12">
                    <div class="content-card">
                        <p class="text-center text-muted">Tidak ada barang yang tersedia untuk dipinjam saat ini.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="item-card">
                            <img src="uploads/inventory/<?php echo htmlspecialchars($item['image_path'] ?? 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                            <div class="item-card-body">
                                <h5 class="item-card-title"><?php echo htmlspecialchars($item['item_name']); ?></h5>
                                <p class="item-card-text"><i class="bi bi-tag"></i> <?php echo htmlspecialchars($item['item_type']); ?></p>
                                <p class="item-card-text"><i class="bi bi-calendar-week"></i> Durasi: <?php echo htmlspecialchars($item['borrow_duration_days']); ?> hari</p>
                            </div>
                            <div class="item-card-footer">
                                <?php if ($item['quantity'] > 0): ?>
                                    <form action="borrow_items.php" method="POST" onsubmit="return showBorrowForm('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['item_name']); ?>')">
                                        <input type="hidden" name="inventory_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-primary-custom btn-borrow">
                                            <i class="bi bi-box-arrow-up-right"></i> Pinjam (Tersedia: <?php echo $item['quantity']; ?>)
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-borrow" disabled>
                                        <i class="bi bi-x-circle"></i> Tidak Tersedia
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal untuk form peminjaman -->
    <div class="modal fade" id="borrowModal" tabindex="-1" aria-labelledby="borrowModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="borrow_items.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="borrowModalLabel">Form Peminjaman</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Anda akan meminjam: <strong id="modal-item-name"></strong></p>
                        <input type="hidden" name="inventory_id" id="modal-inventory-id">
                        
                        <div class="mb-3">
                            <label for="borrow_purpose" class="form-label">Tujuan Peminjaman <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="borrow_purpose" name="borrow_purpose" rows="3" placeholder="Jelaskan untuk apa Anda meminjam barang ini..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Catatan Tambahan (Opsional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Masukkan catatan jika ada..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary-custom">Ajukan Peminjaman</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        function showBorrowForm(itemId, itemName) {
            document.getElementById('modal-inventory-id').value = itemId;
            document.getElementById('modal-item-name').textContent = itemName;
            const modal = new bootstrap.Modal(document.getElementById('borrowModal'));
            modal.show();
            return false; // Mencegah form submit langsung
        }
    </script>
</body>
</html>