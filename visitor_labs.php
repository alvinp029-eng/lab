<?php
// visitor_labs.php - Daftar Laboratorium untuk Pengunjung dengan Gambar dan Tombol Pesan
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// Ambil data user
try {
    $sql = "SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] !== 'pengunjung') {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    die("Terjadi kesalahan saat memuat data pengguna.");
}

// Ambil data laboratorium
try {
    $sql = "SELECT l.*, COUNT(c.id) as computer_count 
            FROM laboratories l 
            LEFT JOIN computers c ON l.id = c.lab_id 
            WHERE l.status = 'active' 
            GROUP BY l.id 
            ORDER BY l.name";
    $stmt = $pdo->query($sql);
    $laboratories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch labs error: " . $e->getMessage());
    $laboratories = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratorium - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { 
            --primary-color: #0d6efd; 
            --secondary-color: #0a58ca; 
            --light-color: #f8f9fa; 
            --dark-color: #212529; 
            --success-color: #198754; 
            --danger-color: #dc3545; 
            --warning-color: #ffc107; 
            --info-color: #0dcaf0; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f5f7fb; 
            color: var(--dark-color); 
        }
        
        .sidebar { 
            position: fixed; 
            top: 0; 
            left: 0; 
            height: 100vh; 
            width: 250px; 
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); 
            padding: 20px 0; 
            z-index: 1000; 
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); 
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        .sidebar-header { 
            text-align: center; 
            padding: 0 20px 30px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.2); 
        }
        
        .sidebar-header h3 { 
            color: white; 
            font-weight: bold; 
            margin: 0; 
        }
        
        .sidebar-menu { 
            padding: 20px 0; 
        }
        
        .sidebar-menu .nav-link { 
            color: rgba(255, 255, 255, 0.8); 
            padding: 12px 25px; 
            display: flex; 
            align-items: center; 
            transition: all 0.3s; 
            text-decoration: none;
            position: relative;
        }
        
        .sidebar-menu .nav-link:hover, .sidebar-menu .nav-link.active { 
            color: white; 
            background-color: rgba(255, 255, 255, 0.1); 
        }
        
        .sidebar-menu .nav-link i { 
            margin-right: 10px; 
            font-size: 1.2rem; 
            width: 20px;
            text-align: center;
        }
        
        .sidebar-footer { 
            position: absolute; 
            bottom: 0; 
            left: 0; 
            right: 0; 
            padding: 20px; 
            border-top: 1px solid rgba(255, 255, 255, 0.2); 
        }
        
        .user-info { 
            display: flex; 
            align-items: center; 
            color: white; 
        }
        
        .user-avatar { 
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            background-color: rgba(255, 255, 255, 0.2); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-right: 10px; 
        }
        
        .user-details { 
            flex: 1; 
        }
        
        .user-name { 
            font-weight: bold; 
            margin: 0; 
            font-size: 0.9rem; 
        }
        
        .user-role { 
            margin: 0; 
            font-size: 0.8rem; 
            opacity: 0.8; 
        }
        
        .main-content { 
            margin-left: 250px; 
            padding: 20px; 
            min-height: 100vh; 
            transition: margin-left 0.3s ease;
        }
        
        .top-header { 
            background-color: white; 
            border-radius: 10px; 
            padding: 15px 25px; 
            margin-bottom: 25px; 
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .page-title { 
            font-size: 1.5rem; 
            font-weight: bold; 
            color: var(--primary-color); 
            margin: 0; 
        }
        
        .content-card { 
            background-color: white; 
            border-radius: 10px; 
            padding: 25px; 
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); 
            margin-bottom: 25px; 
        }
        
        .lab-card {
            border: 1px solid #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 30px;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .lab-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        .lab-image-container {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .lab-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .lab-card:hover .lab-image {
            transform: scale(1.05);
        }
        
        .lab-status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: rgba(255, 255, 255, 0.9);
            color: var(--success-color);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
        }
        
        .lab-content {
            padding: 20px;
        }
        
        .lab-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .lab-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }
        
        .lab-info {
            margin-bottom: 20px;
        }
        
        .lab-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .lab-info-item i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .lab-description {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .lab-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-pesan {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            color: white;
            font-weight: 500;
            transition: all 0.3s;
            flex: 1;
        }
        
        .btn-pesan:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
            color: white;
        }
        
        .btn-detail {
            background-color: transparent;
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            padding: 10px 20px;
            color: var(--primary-color);
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-detail:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .mobile-toggle { 
            display: none; 
            position: fixed; 
            top: 20px; 
            left: 20px; 
            z-index: 1001; 
            background-color: var(--primary-color); 
            color: white; 
            border: none; 
            border-radius: 5px; 
            padding: 10px; 
            font-size: 1.2rem; 
        }
        
        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        @media (max-width: 992px) { 
            .sidebar { 
                transform: translateX(-100%); 
                transition: transform 0.3s; 
            } 
            .sidebar.active { 
                transform: translateX(0); 
            } 
            .main-content { 
                margin-left: 0; 
            } 
            .mobile-toggle { 
                display: block; 
            } 
        }
        
        @media (max-width: 768px) {
            .lab-card {
                margin-bottom: 20px;
            }
            
            .lab-actions {
                flex-direction: column;
            }
            
            .btn-pesan, .btn-detail {
                width: 100%;
            }
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
            <a href="visitor_dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="visitor_labs.php" class="nav-link active">
                <i class="bi bi-building"></i> Laboratorium
            </a>
            <a href="visitor_bookings.php" class="nav-link">
                <i class="bi bi-calendar-check"></i> Pemesanan Saya
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
                    <p class="user-role">Pengunjung</p>
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
            <h1 class="page-title">Daftar Laboratorium</h1>
            <div>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Laboratorium Tersedia</h3>
            </div>
            
            <?php if (empty($laboratories)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-building" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">Belum ada laboratorium tersedia.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($laboratories as $lab): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="lab-card">
                            <div class="lab-image-container">
                                <?php if (!empty($lab['image'])): ?>
                                    <img src="assets/images/labs/<?php echo htmlspecialchars($lab['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($lab['name']); ?>" 
                                         class="lab-image">
                                <?php else: ?>
                                    <img src="https://picsum.photos/seed/<?php echo $lab['id']; ?>/400/200.jpg" 
                                         alt="<?php echo htmlspecialchars($lab['name']); ?>" 
                                         class="lab-image">
                                <?php endif; ?>
                                <span class="lab-status-badge">
                                    <i class="bi bi-check-circle-fill"></i> Tersedia
                                </span>
                            </div>
                            <div class="lab-content">
                                <div class="lab-header">
                                    <h4 class="lab-name"><?php echo htmlspecialchars($lab['name']); ?></h4>
                                </div>
                                
                                <div class="lab-info">
                                    <div class="lab-info-item">
                                        <i class="bi bi-geo-alt-fill"></i>
                                        <span><?php echo htmlspecialchars($lab['location']); ?></span>
                                    </div>
                                    <div class="lab-info-item">
                                        <i class="bi bi-pc-display-fill"></i>
                                        <span><?php echo $lab['computer_count']; ?> Komputer</span>
                                    </div>
                                    <div class="lab-info-item">
                                        <i class="bi bi-people-fill"></i>
                                        <span>Kapasitas: <?php echo $lab['capacity']; ?> Orang</span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($lab['description'])): ?>
                                <div class="lab-description">
                                    <?php echo htmlspecialchars($lab['description']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="lab-actions">
                                    <button class="btn btn-pesan" onclick="bookLab(<?php echo $lab['id']; ?>, '<?php echo htmlspecialchars($lab['name']); ?>')">
                                        <i class="bi bi-calendar-plus"></i> Pesan
                                    </button>
                                    <button class="btn btn-detail" onclick="showLabDetail(<?php echo $lab['id']; ?>)">
                                        <i class="bi bi-info-circle"></i> Detail
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-plus"></i> 
                        Pesan Laboratorium: <span id="modalLabName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="visitor_dashboard.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="book_lab">
                        <input type="hidden" name="lab_id" id="bookingLabId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="purpose" class="form-label">Tujuan Pemesanan <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="purpose" name="purpose" rows="3" required 
                                              placeholder="Contoh: Mengerjakan tugas, belajar kelompok, presentasi, dll."></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="participants" class="form-label">Jumlah Peserta <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="participants" name="participants" 
                                           min="1" max="50" required placeholder="Masukkan jumlah peserta">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_time" class="form-label">Waktu Mulai <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="start_time" name="start_time" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_time" class="form-label">Waktu Selesai <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="end_time" name="end_time" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Catatan Tambahan</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" 
                                      placeholder="Informasi tambahan yang perlu diketahui (opsional)"></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill"></i>
                            <strong>Informasi:</strong> Pemesanan akan diproses oleh admin laboratorium. 
                            Anda akan menerima notifikasi melalui email setelah pemesanan disetujui atau ditolak.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Ajukan Pemesanan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Lab Detail Modal -->
    <div class="modal fade" id="labDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle"></i> 
                        Detail Laboratorium
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="labDetailContent">
                    <!-- Content will be loaded via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" id="bookFromDetailBtn">
                        <i class="bi bi-calendar-plus"></i> Pesan Lab Ini
                    </button>
                </div>
            </div>
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

    // Set minimum date for booking (today)
    document.addEventListener('DOMContentLoaded', function() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        
        const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
        document.getElementById('start_time').min = minDateTime;
        document.getElementById('end_time').min = minDateTime;
    });

    // Book Lab Function
    function bookLab(labId, labName) {
        document.getElementById('bookingLabId').value = labId;
        document.getElementById('modalLabName').textContent = labName;
        
        // Show booking modal
        const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
        bookingModal.show();
    }

    // Show Lab Detail Function
    function showLabDetail(labId) {
        // Fetch lab detail via AJAX (simplified for demo)
        const labs = <?php echo json_encode($laboratories); ?>;
        const lab = labs.find(l => l.id === labId);
        
        if (lab) {
            const detailContent = `
                <div class="row">
                    <div class="col-md-6">
                        ${lab.image ? `<img src="assets/images/labs/${lab.image}" class="img-fluid rounded" alt="${lab.name}">` : 
                          `<img src="https://picsum.photos/seed/${lab.id}/400/300.jpg" class="img-fluid rounded" alt="${lab.name}">`}
                    </div>
                    <div class="col-md-6">
                        <h5 class="mb-3">${lab.name}</h5>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Lokasi:</strong></td>
                                <td>${lab.location}</td>
                            </tr>
                            <tr>
                                <td><strong>Kapasitas:</strong></td>
                                <td>${lab.capacity} Orang</td>
                            </tr>
                            <tr>
                                <td><strong>Komputer:</strong></td>
                                <td>${lab.computer_count} Unit</td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td><span class="badge bg-success">Tersedia</span></td>
                            </tr>
                        </table>
                        ${lab.description ? `<p class="mt-3">${lab.description}</p>` : ''}
                    </div>
                </div>
            `;
            
            document.getElementById('labDetailContent').innerHTML = detailContent;
            
            // Set book button action
            document.getElementById('bookFromDetailBtn').onclick = function() {
                bootstrap.Modal.getInstance(document.getElementById('labDetailModal')).hide();
                bookLab(labId, lab.name);
            };
            
            // Show detail modal
            const detailModal = new bootstrap.Modal(document.getElementById('labDetailModal'));
            detailModal.show();
        }
    }

    // Update end time minimum when start time changes
    document.getElementById('start_time').addEventListener('change', function() {
        document.getElementById('end_time').min = this.value;
    });

    // Validate form before submit
    document.querySelector('#bookingModal form').addEventListener('submit', function(e) {
        const startTime = new Date(document.getElementById('start_time').value);
        const endTime = new Date(document.getElementById('end_time').value);
        
        if (endTime <= startTime) {
            e.preventDefault();
            alert('Waktu selesai harus lebih besar dari waktu mulai.');
            return false;
        }
        
        // Check if booking is at least 30 minutes
        const diffMinutes = (endTime - startTime) / (1000 * 60);
        if (diffMinutes < 30) {
            e.preventDefault();
            alert('Durasi pemesanan minimal 30 menit.');
            return false;
        }
    });
    </script>
</body>
</html>