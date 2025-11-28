<?php
// reports.php (ENHANCED VERSION - Refactored, Paginated & PDF Fixed)
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// Load TCPDF library
require_once 'vendor/autoload.php';

// Ambil data user dari database untuk konsistensi dan keamanan
try {
    $sql = "SELECT id, name, email, role, nip_nim, phone, department FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] !== 'kepala_lab') {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    die("Terjadi kesalahan saat memuat data pengguna.");
}

// Ambil data laboratorium untuk filter
try {
    $lab_sql = "SELECT id, name FROM laboratories WHERE status = 'active' ORDER BY name";
    $lab_stmt = $pdo->query($lab_sql);
    $laboratories = $lab_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Terjadi kesalahan saat memuat data laboratorium.");
}

// Ambil statistik dashboard (mirip dengan dashboard.php)
 $stats = [];
try {
    $stats['labs'] = $pdo->query("SELECT COUNT(*) as total FROM laboratories")->fetch()['total'];
    $stats['computers'] = $pdo->query("SELECT COUNT(*) as total FROM computers")->fetch()['total'];
    $stats['inventory'] = $pdo->query("SELECT COUNT(*) as total FROM inventory")->fetch()['total'];
    $stats['reservations'] = $pdo->query("SELECT COUNT(*) as total FROM reservations")->fetch()['total'];
    $stats['pending_requests'] = $pdo->query("SELECT COUNT(*) as total FROM item_borrows WHERE status = 'pending'")->fetch()['total'];
    $stats['active_borrows'] = $pdo->query("SELECT COUNT(*) as total FROM item_borrows WHERE status IN ('approved', 'borrowed')")->fetch()['total'];
    $stats['overdue_items'] = $pdo->query("SELECT COUNT(*) as total FROM item_borrows WHERE status = 'overdue'")->fetch()['total'];
    $stats['maintenance_in_progress'] = $pdo->query("SELECT COUNT(*) as total FROM computer_maintenance WHERE status = 'in_progress'")->fetch()['total'];
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// --- PERBAIKAN 1: PEMISAHAN LOGI LAPORAN KE DALAM FUNGSI ---

/**
 * Mendapatkan data laporan peminjaman
 */
function getPeminjamanReport($pdo, $start_date, $end_date, $lab_filter, $limit = null, $offset = 0) {
    $sql = "SELECT u.name AS peminjam, u.nip_nim, i.item_name, i.item_type, l.name AS lab_name,
                   ib.borrow_date, ib.expected_return_date, ib.return_date, ib.status
            FROM item_borrows ib
            JOIN users u ON ib.user_id = u.id
            JOIN inventory i ON ib.inventory_id = i.id
            JOIN laboratories l ON i.lab_id = l.id
            WHERE DATE(ib.borrow_date) BETWEEN :start_date AND :end_date";

    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    if ($lab_filter !== 'all') {
        $sql .= " AND i.lab_id = :lab_id";
        $params[':lab_id'] = $lab_filter;
    }
    
    $sql .= " ORDER BY ib.borrow_date DESC";

    if ($limit) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Mendapatkan data laporan pemesanan
 */
function getPemesananReport($pdo, $start_date, $end_date, $lab_filter, $limit = null, $offset = 0) {
    $sql = "SELECT u.name AS pemesan, u.nip_nim, l.name AS lab_name, r.purpose, r.start_time, r.end_time, r.status
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            JOIN laboratories l ON r.lab_id = l.id
            WHERE DATE(r.start_time) BETWEEN :start_date AND :end_date";

    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    if ($lab_filter !== 'all') {
        $sql .= " AND r.lab_id = :lab_id";
        $params[':lab_id'] = $lab_filter;
    }

    $sql .= " ORDER BY r.start_time DESC";

    if ($limit) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Mendapatkan data laporan inventaris
 */
function getInventarisReport($pdo, $lab_filter, $limit = null, $offset = 0) {
    $sql = "SELECT i.id, i.item_name, i.item_type, i.quantity, i.description, i.is_borrowable,
                   l.name AS lab_name, l.location AS lab_location,
                   CASE
                       WHEN i.is_borrowable = 0 THEN 'not_borrowable'
                       WHEN ib.id IS NOT NULL AND ib.status = 'borrowed' THEN 'in_use'
                       ELSE 'available'
                   END AS status
            FROM inventory i
            JOIN laboratories l ON i.lab_id = l.id
            LEFT JOIN item_borrows ib ON i.id = ib.inventory_id AND ib.status = 'borrowed'";

    $params = [];
    if ($lab_filter !== 'all') {
        $sql .= " WHERE i.lab_id = :lab_id";
        $params[':lab_id'] = $lab_filter;
    }

    $sql .= " ORDER BY l.name, i.item_name";

    if ($limit) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Menghitung total baris untuk pagination
 */
function getTotalReportCount($pdo, $report_type, $start_date, $end_date, $lab_filter) {
    if ($report_type === 'peminjaman') {
        $sql = "SELECT COUNT(*) as total FROM item_borrows ib JOIN inventory i ON ib.inventory_id = i.id WHERE DATE(ib.borrow_date) BETWEEN :start_date AND :end_date";
        $params = [':start_date' => $start_date, ':end_date' => $end_date];
        if ($lab_filter !== 'all') {
            $sql .= " AND i.lab_id = :lab_id";
            $params[':lab_id'] = $lab_filter;
        }
    } elseif ($report_type === 'pemesanan') {
        $sql = "SELECT COUNT(*) as total FROM reservations r WHERE DATE(r.start_time) BETWEEN :start_date AND :end_date";
        $params = [':start_date' => $start_date, ':end_date' => $end_date];
        if ($lab_filter !== 'all') {
            $sql .= " AND r.lab_id = :lab_id";
            $params[':lab_id'] = $lab_filter;
        }
    } elseif ($report_type === 'inventaris') {
        $sql = "SELECT COUNT(*) as total FROM inventory i";
        $params = [];
        if ($lab_filter !== 'all') {
            $sql .= " WHERE i.lab_id = :lab_id";
            $params[':lab_id'] = $lab_filter;
        }
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetch()['total'];
}


// --- PERBAIKAN 2: IMPLEMENTASI PAGINATION DAN PENGGUNAAN GET ---
define('RESULTS_PER_PAGE', 25); // Jumlah hasil per halaman

// Inisialisasi variabel dari GET
 $report_type = $_GET['report_type'] ?? 'peminjaman';
 $start_date = $_GET['start_date'] ?? date('Y-m-01');
 $end_date = $_GET['end_date'] ?? date('Y-m-t');
 $lab_filter = $_GET['lab_filter'] ?? 'all';
 $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
 $offset = ($page - 1) * RESULTS_PER_PAGE;

 $report_data = [];
 $total_pages = 1;
 $error = '';

// Validasi tanggal
if ($report_type !== 'inventaris' && strtotime($start_date) > strtotime($end_date)) {
    $error = "Tanggal mulai tidak boleh lebih besar dari tanggal selesai.";
} else {
    try {
        // Hitung total halaman
        $total_records = getTotalReportCount($pdo, $report_type, $start_date, $end_date, $lab_filter);
        $total_pages = ceil($total_records / RESULTS_PER_PAGE);

        // Ambil data untuk halaman saat ini
        if ($report_type === 'peminjaman') {
            $report_data = getPeminjamanReport($pdo, $start_date, $end_date, $lab_filter, RESULTS_PER_PAGE, $offset);
        } elseif ($report_type === 'pemesanan') {
            $report_data = getPemesananReport($pdo, $start_date, $end_date, $lab_filter, RESULTS_PER_PAGE, $offset);
        } elseif ($report_type === 'inventaris') {
            $report_data = getInventarisReport($pdo, $lab_filter, RESULTS_PER_PAGE, $offset);
        }

    } catch (PDOException $e) {
        error_log("Laporan error: " . $e->getMessage());
        $error = "Terjadi kesalahan saat mengambil data laporan.";
    }
}

// --- PROSES UTAMA: Ekspor CSV & PDF (POST) ---
if ($_SERVER["REQUEST_METHOD"] === 'POST') {
    // Ambil ulang data tanpa pagination untuk keperluan ekspor
    $export_report_type = $_POST['report_type'];
    $export_start_date = $_POST['start_date'];
    $export_end_date = $_POST['end_date'];
    $export_lab_filter = $_POST['lab_filter'];
    
    try {
        if ($export_report_type === 'peminjaman') {
            $report_data_export = getPeminjamanReport($pdo, $export_start_date, $export_end_date, $export_lab_filter);
        } elseif ($export_report_type === 'pemesanan') {
            $report_data_export = getPemesananReport($pdo, $export_start_date, $export_end_date, $export_lab_filter);
        } elseif ($export_report_type === 'inventaris') {
            $report_data_export = getInventarisReport($pdo, $export_lab_filter);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal mengambil data untuk ekspor.";
        header("Location: reports.php?" . http_build_query($_GET)); // Kembali dengan filter GET
        exit();
    }

    // --- TANGANI EKSPOR CSV ---
    if (isset($_POST['export_csv'])) {
        if (empty($report_data_export)) {
            $_SESSION['error'] = "Tidak ada data untuk diekspor.";
            header("Location: reports.php?" . http_build_query($_GET));
            exit();
        }
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="laporan_' . $export_report_type . '_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        if ($export_report_type === 'peminjaman') {
            fputcsv($output, ['Peminjam', 'NIP/NIM', 'Nama Barang', 'Tipe', 'Lab', 'Tgl Pinjam', 'Harus Kembali', 'Tgl Kembali', 'Status']);
        } elseif ($export_report_type === 'pemesanan') {
            fputcsv($output, ['Pemesan', 'NIP/NIM', 'Lab', 'Tujuan', 'Waktu Mulai', 'Waktu Selesai', 'Status']);
        } elseif ($export_report_type === 'inventaris') {
            fputcsv($output, ['No', 'Nama Barang', 'Spesifikasi', 'Kuantitas', 'Lokasi', 'Status']);
        }
        $rowNumber = 1;
        foreach ($report_data_export as $row) {
            $csv_row = [];
            if ($export_report_type === 'peminjaman') {
                $csv_row = [$row['peminjam'], $row['nip_nim'], $row['item_name'], $row['item_type'], $row['lab_name'], $row['borrow_date'], $row['expected_return_date'], $row['return_date'], $row['status']];
            } elseif ($export_report_type === 'pemesanan') {
                $csv_row = [$row['pemesan'], $row['nip_nim'], $row['lab_name'], $row['purpose'], $row['start_time'], $row['end_time'], $row['status']];
            } elseif ($export_report_type === 'inventaris') {
                $status_text = match($row['status']) { 'available' => 'Tersedia', 'in_use' => 'Digunakan', 'not_borrowable' => 'Tidak Bisa Dipinjam', default => ucfirst($row['status']) };
                $csv_row = [$rowNumber++, $row['item_name'], $row['description'], $row['quantity'], $row['lab_name'] . ' - ' . $row['lab_location'], $status_text];
            }
            fputcsv($output, $csv_row);
        }
        fclose($output);
        exit();
    }
    
    // --- TANGANI CETAK PDF (PERBAIKAN KRUSIAL) ---
    if (isset($_POST['print_pdf'])) {
        if (empty($report_data_export)) {
            $_SESSION['error'] = "Tidak ada data untuk dicetak.";
            header("Location: reports.php?" . http_build_query($_GET));
            exit();
        }
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('SILABKOM'); $pdf->SetAuthor('SILABKOM'); $pdf->SetTitle('Laporan ' . ucfirst($export_report_type));
            $pdf->SetMargins(15, 30, 15); $pdf->SetHeaderMargin(10); $pdf->SetFooterMargin(10);
            $pdf->SetAutoPageBreak(TRUE, 15);
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 10, 'LAPORAN ' . strtoupper($export_report_type), 0, 1, 'C');
            $pdf->Ln(5);
            if ($export_lab_filter !== 'all') {
                $lab_name = $laboratories[array_search($export_lab_filter, array_column($laboratories, 'id'))]['name'];
                $pdf->SetFont('helvetica', '', 12); $pdf->Cell(0, 8, 'Laboratorium: ' . htmlspecialchars($lab_name), 0, 1, 'C');
            }
            if ($export_report_type !== 'inventaris') {
                $pdf->Cell(0, 8, 'Periode: ' . date('d F Y', strtotime($export_start_date)) . ' - ' . date('d F Y', strtotime($export_end_date)), 0, 1, 'C');
            }
            $pdf->Cell(0, 8, 'Tanggal Cetak: ' . date('d F Y H:i:s'), 0, 1, 'C'); $pdf->Ln(10);
            
            // Set font untuk tabel
            $pdf->SetFont('helvetica', 'B', 10);
            
            // Header tabel
            if ($export_report_type === 'peminjaman') {
                $headers = ['Peminjam', 'NIP/NIM', 'Nama Barang', 'Tipe', 'Lab', 'Tgl Pinjam', 'Harus Kembali', 'Tgl Kembali', 'Status'];
                $widths = [30, 25, 35, 20, 25, 25, 25, 25, 25];
                $aligns = ['L', 'C', 'L', 'C', 'C', 'C', 'C', 'C', 'C'];
            } elseif ($export_report_type === 'pemesanan') {
                $headers = ['Pemesan', 'NIP/NIM', 'Lab', 'Tujuan', 'Waktu', 'Status'];
                $widths = [35, 25, 30, 50, 45, 25];
                $aligns = ['L', 'C', 'C', 'L', 'C', 'C'];
            } elseif ($export_report_type === 'inventaris') {
                $headers = ['No', 'Nama Barang', 'Spesifikasi', 'Kuantitas', 'Lokasi', 'Status'];
                $widths = [15, 40, 50, 20, 40, 25];
                $aligns = ['C', 'L', 'L', 'C', 'L', 'C'];
            }
            
            // Buat header tabel
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(0);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.3);
            
            for ($i = 0; $i < count($headers); $i++) {
                $pdf->Cell($widths[$i], 7, $headers[$i], 1, 0, $aligns[$i], 1);
            }
            $pdf->Ln();
            
            // Data tabel
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetFillColor(255, 255, 255);
            $fill = false;
            $rowNumber = 1;
            
            foreach ($report_data_export as $row) {
                if ($pdf->GetY() > 250) {
                    $pdf->AddPage();
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetFillColor(240, 240, 240);
                    for ($i = 0; $i < count($headers); $i++) {
                        $pdf->Cell($widths[$i], 7, $headers[$i], 1, 0, $aligns[$i], 1);
                    }
                    $pdf->Ln();
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->SetFillColor(255, 255, 255);
                }
                
                if ($export_report_type === 'peminjaman') {
                    $status_text = match($row['status']) { 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'borrowed' => 'Dipinjam', 'returned' => 'Dikembalikan', 'overdue' => 'Terlambat', 'lost' => 'Hilang', default => ucfirst($row['status']) };
                    $data = [$row['peminjam'], $row['nip_nim'], $row['item_name'], $row['item_type'], $row['lab_name'], date('d/m/Y', strtotime($row['borrow_date'])), date('d/m/Y', strtotime($row['expected_return_date'])), $row['return_date'] ? date('d/m/Y', strtotime($row['return_date'])) : '-', $status_text];
                } elseif ($export_report_type === 'pemesanan') {
                    $status_text = match($row['status']) { 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', default => ucfirst($row['status']) };
                    $start = new DateTime($row['start_time']); $end = new DateTime($row['end_time']);
                    $time_range = $start->format('d/m/Y H:i') . ' - ' . $end->format('H:i');
                    $data = [$row['pemesan'], $row['nip_nim'], $row['lab_name'], $row['purpose'], $time_range, $status_text];
                } elseif ($export_report_type === 'inventaris') {
                    $status_text = match($row['status']) { 'available' => 'Tersedia', 'in_use' => 'Digunakan', 'not_borrowable' => 'Tidak Bisa Dipinjam', default => ucfirst($row['status']) };
                    $data = [$rowNumber++, $row['item_name'], $row['description'] ?: '-', $row['quantity'], $row['lab_name'] . ' - ' . $row['lab_location'], $status_text];
                }
                
                $pdf->Cell($widths[0], 6, $data[0], 'LR', 0, $aligns[0], $fill);
                for ($i = 1; $i < count($data); $i++) {
                    if (($export_report_type === 'pemesanan' && $i == 3) || ($export_report_type === 'inventaris' && ($i == 1 || $i == 2 || $i == 4))) {
                        $x = $pdf->GetX(); $y = $pdf->GetY();
                        $pdf->MultiCell($widths[$i], 6, $data[$i], 0, $aligns[$i], $fill);
                        $pdf->SetXY($x + $widths[$i], $y);
                        $pdf->Cell($widths[$i], 6, '', 'LR', 0, $aligns[$i], $fill);
                    } else {
                        $pdf->Cell($widths[$i], 6, $data[$i], 'LR', 0, $aligns[$i], $fill);
                    }
                }
                $pdf->Ln();
                $fill = !$fill;
            }
            
            $pdf->Cell(array_sum($widths), 0, '', 'T');
            $pdf->Ln(20);
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 10, 'Mengetahui,', 0, 1, 'R');
            $pdf->Ln(30);
            $pdf->Cell(0, 10, 'Kepala Laboratorium', 0, 1, 'R');
            $pdf->Ln(20);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, '(_________________________)', 0, 1, 'R');
            
            $pdf->Output('laporan_' . $export_report_type . '_' . date('Y-m-d_H-i-s') . '.pdf', 'D');
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Terjadi kesalahan saat membuat PDF: " . $e->getMessage();
            header("Location: reports.php?" . http_build_query($_GET));
            exit();
        }
    }
}

// Hanya render HTML jika bukan PDF/CSV export
if (!isset($_POST['print_pdf']) && !isset($_POST['export_csv'])) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .date-filter-container { transition: all 0.3s ease; }
        .hidden { display: none !important; }
        .lab-filter-container { transition: all 0.3s ease; }
        .print-header { display: none; }
        @media print {
            .sidebar, .top-header, .mobile-toggle, .card-header-custom .btn-group, .no-print { display: none !important; }
            .main-content { margin-left: 0; padding: 20px; }
            .print-header { display: block; text-align: center; margin-bottom: 20px; }
            .content-card { box-shadow: none; border: 1px solid #ddd; margin-bottom: 20px; }
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><h3>SILABKOM</h3></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <div class="menu-item" id="labManagementMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('labManagementMenu'); return false;">
                    <div><i class="bi bi-building"></i><span>Manajemen Lab</span></div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="laboratories.php" class="nav-link"><i class="bi bi-house-door"></i> Laboratorium</a>
                    <a href="computers.php" class="nav-link"><i class="bi bi-pc-display"></i> Komputer</a>
                    <a href="inventory.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventaris</a>
                </div>
            </div>
            <div class="menu-item" id="borrowManagementMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('borrowManagementMenu'); return false;">
                    <div><i class="bi bi-clipboard-check"></i><span>Activity</span></div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="manage_borrows.php" class="nav-link"><i class="bi bi-list-check"></i> Kelola Peminjaman</a>
                    <a href="manage_bookings.php" class="nav-link"><i class="bi bi-calendar-check"></i> Pemesanan Lab</a>
                </div>
            </div>
            <div class="menu-item" id="maintenanceMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('maintenanceMenu'); return false;">
                    <div><i class="bi bi-tools"></i><span>Pemeliharaan</span></div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="manage_maintenance.php" class="nav-link"><i class="bi bi-list-ul"></i> Daftar Pemeliharaan</a>
                    <a href="add_maintenance.php" class="nav-link"><i class="bi bi-plus-circle"></i> Tambah Pemeliharaan</a>
                </div>
            </div>
            <a href="reports.php" class="nav-link active"><i class="bi bi-graph-up"></i> Laporan</a>
            <a href="manage_users.php" class="nav-link"><i class="bi bi-people"></i> Kelola Pengguna</a>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($user['name']); ?></p>
                    <p class="user-role">Kepala Lab</p>
                </div>
            </div>
            <a href="logout.php" class="nav-link" style="margin-top: 15px;"><i class="bi bi-box-arrow-right"></i> Keluar</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <h1 class="page-title">Laporan</h1>
            <div><span><?php echo date('d F Y'); ?></span></div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card"><div class="stat-icon primary"><i class="bi bi-building"></i></div><div class="stat-value"><?php echo $stats['labs']; ?></div><div class="stat-label">Laboratorium</div></div>
            <div class="stat-card"><div class="stat-icon success"><i class="bi bi-pc-display"></i></div><div class="stat-value"><?php echo $stats['computers']; ?></div><div class="stat-label">Komputer</div></div>
            <div class="stat-card"><div class="stat-icon warning"><i class="bi bi-hourglass-split"></i></div><div class="stat-value"><?php echo $stats['pending_requests']; ?></div><div class="stat-label">Permintaan Menunggu</div></div>
            <div class="stat-card"><div class="stat-icon info"><i class="bi bi-box-arrow-up-right"></i></div><div class="stat-value"><?php echo $stats['active_borrows']; ?></div><div class="stat-label">Peminjaman Aktif</div></div>
            <div class="stat-card"><div class="stat-icon danger"><i class="bi bi-exclamation-triangle"></i></div><div class="stat-value"><?php echo $stats['maintenance_in_progress']; ?></div><div class="stat-label">Pemeliharaan Aktif</div></div>
        </div>

        <!-- Alert Message -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="content-card">
            <div class="card-header-custom"><h3 class="card-title">Filter Laporan</h3></div>
            <form method="GET" action="" id="reportForm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="report_type" class="form-label">Jenis Laporan</label>
                        <select class="form-select" id="report_type" name="report_type" onchange="toggleDateFilters(); this.form.submit();">
                            <option value="peminjaman" <?php echo ($report_type == 'peminjaman') ? 'selected' : ''; ?>>Peminjaman Barang</option>
                            <option value="pemesanan" <?php echo ($report_type == 'pemesanan') ? 'selected' : ''; ?>>Pemesanan Lab</option>
                            <option value="inventaris" <?php echo ($report_type == 'inventaris') ? 'selected' : ''; ?>>Status Inventaris</option>
                        </select>
                    </div>
                    <div class="col-md-3 lab-filter-container" id="lab_filter_container">
                        <label for="lab_filter" class="form-label">Filter Laboratorium</label>
                        <select class="form-select" id="lab_filter" name="lab_filter" onchange="this.form.submit();">
                            <option value="all" <?php echo ($lab_filter == 'all') ? 'selected' : ''; ?>>Semua Laboratorium</option>
                            <?php foreach ($laboratories as $lab): ?>
                                <option value="<?php echo $lab['id']; ?>" <?php echo ($lab_filter == $lab['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 date-filter-container" id="start_date_container">
                        <label for="start_date" class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" onchange="this.form.submit();">
                    </div>
                    <div class="col-md-2 date-filter-container" id="end_date_container">
                        <label for="end_date" class="form-label">Tanggal Selesai</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" onchange="this.form.submit();">
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" name="generate_report" class="btn-custom btn-primary-custom"><i class="bi bi-funnel"></i> Tampilkan Laporan</button>
                        <button type="button" class="btn-custom btn-success-custom" onclick="submitFormForAction('export_csv')"><i class="bi bi-download"></i> Export ke CSV</button>
                        <button type="button" class="btn-custom btn-danger-custom" onclick="submitFormForAction('print_pdf')"><i class="bi bi-file-earmark-pdf"></i> Cetak PDF</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Form tersembunyi untuk ekspor (POST) -->
        <form id="exportForm" method="POST" action="" style="display: none;">
            <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            <input type="hidden" name="lab_filter" value="<?php echo htmlspecialchars($lab_filter); ?>">
            <input type="hidden" name="export_csv" id="export_csv_flag">
            <input type="hidden" name="print_pdf" id="print_pdf_flag">
        </form>

        <!-- Report Display -->
        <?php if ($_SERVER["REQUEST_METHOD"] === 'GET' && isset($_GET['report_type'])): ?>
            <?php if (!empty($report_data)): ?>
                <div class="content-card">
                    <div class="card-header-custom">
                        <h3 class="card-title">Hasil Laporan (Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?>)</h3>
                        <div class="btn-group no-print">
                            <button type="button" class="btn-custom btn-primary-custom" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <?php if ($report_type === 'peminjaman'): ?>
                                        <th>Peminjam</th><th>NIP/NIM</th><th>Nama Barang</th><th>Tipe</th><th>Lab</th><th>Tgl Pinjam</th><th>Harus Kembali</th><th>Tgl Kembali</th><th>Status</th>
                                    <?php elseif ($report_type === 'pemesanan'): ?>
                                        <th>Pemesan</th><th>NIP/NIM</th><th>Lab</th><th>Tujuan</th><th>Waktu</th><th>Status</th>
                                    <?php elseif ($report_type === 'inventaris'): ?>
                                        <th>No</th><th>Nama Barang</th><th>Spesifikasi</th><th>Kuantitas</th><th>Lokasi</th><th>Status</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rowNumber = 1 + $offset; foreach ($report_data as $row): ?>
                                    <tr>
                                        <?php if ($report_type === 'peminjaman'): ?>
                                            <td><?php echo htmlspecialchars($row['peminjam']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nip_nim']); ?></td>
                                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['item_type']); ?></td>
                                            <td><?php echo htmlspecialchars($row['lab_name']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($row['borrow_date'])); ?></td>
                                            <td><?php echo date('d M Y', strtotime($row['expected_return_date'])); ?></td>
                                            <td><?php echo $row['return_date'] ? date('d M Y', strtotime($row['return_date'])) : '-'; ?></td>
                                            <td><span class="badge-status badge-<?php echo $row['status']; ?>"><?php echo match($row['status']) { 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'borrowed' => 'Dipinjam', 'returned' => 'Dikembalikan', 'overdue' => 'Terlambat', 'lost' => 'Hilang', default => ucfirst($row['status']) }; ?></span></td>
                                        <?php elseif ($report_type === 'pemesanan'): ?>
                                            <td><?php echo htmlspecialchars($row['pemesan']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nip_nim']); ?></td>
                                            <td><?php echo htmlspecialchars($row['lab_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                                            <td><?php $start = new DateTime($row['start_time']); $end = new DateTime($row['end_time']); echo $start->format('d M Y, H:i') . ' - ' . $end->format('H:i'); ?></td>
                                            <td><span class="badge-status badge-<?php echo $row['status']; ?>"><?php echo match($row['status']) { 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', default => ucfirst($row['status']) }; ?></span></td>
                                        <?php elseif ($report_type === 'inventaris'): ?>
                                            <td><?php echo $rowNumber++; ?></td>
                                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                                            <td><?php echo $row['quantity']; ?></td>
                                            <td><?php echo htmlspecialchars($row['lab_name'] . ' - ' . $row['lab_location']); ?></td>
                                            <td><span class="badge-status badge-<?php echo $row['status'] === 'not_borrowable' ? 'secondary' : $row['status']; ?>"><?php echo match($row['status']) { 'available' => 'Tersedia', 'in_use' => 'Digunakan', 'not_borrowable' => 'Tidak Bisa Dipinjam', default => ucfirst($row['status']) }; ?></span></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- PERBAIKAN 4: KONTROL PAGINATION -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                                <a class="page-link" href="<?php echo buildQueryString($page - 1); ?>" tabindex="-1">Previous</a>
                            </li>
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php if($page == $i){ echo 'active'; } ?>">
                                <a class="page-link" href="<?php echo buildQueryString($i); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php if($page >= $total_pages){ echo 'disabled'; } ?>">
                                <a class="page-link" href="<?php echo buildQueryString($page + 1); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="content-card"><div class="text-center py-4"><i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i><p class="mt-3 text-muted">Tidak ada data untuk ditampilkan.</p></div></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        // PERBAIKAN 5: FUNGSI JS BARU UNTUK MENANGANI EKSPOR DAN PAGINATION
        function buildQueryString(newPage) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', newPage);
            if (newPage <= 1) { urlParams.delete('page'); }
            return '?' + urlParams.toString();
        }

        function submitFormForAction(action) {
            const form = document.getElementById('reportForm');
            const exportForm = document.getElementById('exportForm');
            exportForm.querySelector('input[name="report_type"]').value = form.report_type.value;
            exportForm.querySelector('input[name="start_date"]').value = form.start_date.value;
            exportForm.querySelector('input[name="end_date"]').value = form.end_date.value;
            exportForm.querySelector('input[name="lab_filter"]').value = form.lab_filter.value;
            if (action === 'export_csv') { exportForm.querySelector('#export_csv_flag').value = '1'; } 
            else if (action === 'print_pdf') { exportForm.querySelector('#print_pdf_flag').value = '1'; }
            exportForm.submit();
        }

        // Fungsi lainnya tetap sama
        document.getElementById('sidebarToggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('active'); });
        document.addEventListener('click', function(event) { const sidebar = document.getElementById('sidebar'); const sidebarToggle = document.getElementById('sidebarToggle'); if (window.innerWidth <= 992 && !sidebar.contains(event.target) && !sidebarToggle.contains(event.target) && sidebar.classList.contains('active')) { sidebar.classList.remove('active'); } });
        window.addEventListener('resize', function() { const sidebar = document.getElementById('sidebar'); if (window.innerWidth > 992) { sidebar.classList.remove('active'); } });
        function toggleSubmenu(menuId) { const menuItem = document.getElementById(menuId); const allMenuItems = document.querySelectorAll('.menu-item'); allMenuItems.forEach(item => { if (item.id !== menuId && item.classList.contains('open')) { item.classList.remove('open'); } }); menuItem.classList.toggle('open'); }
        function toggleDateFilters() { const reportType = document.getElementById('report_type').value; const startDateContainer = document.getElementById('start_date_container'); const endDateContainer = document.getElementById('end_date_container'); if (reportType === 'inventaris') { startDateContainer.classList.add('hidden'); endDateContainer.classList.add('hidden'); } else { startDateContainer.classList.remove('hidden'); endDateContainer.classList.remove('hidden'); } }
        document.addEventListener('DOMContentLoaded', function() { const currentPath = window.location.pathname; const allLinks = document.querySelectorAll('.sidebar-menu .nav-link'); allLinks.forEach(link => { if (link.getAttribute('href') === currentPath.split('/').pop()) { allLinks.forEach(l => l.classList.remove('active')); link.classList.add('active'); const parentSubmenu = link.closest('.submenu'); if (parentSubmenu) { const parentMenuItem = parentSubmenu.closest('.menu-item'); parentMenuItem.classList.add('open'); } } }); toggleDateFilters(); });
    </script>
</body>
</html>
<?php
} // End of HTML render condition
?>