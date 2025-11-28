<?php
// export_report.php (EKSPOR LAPORAN KE EXCEL)
session_start();

// Cek apakah user sudah login dan role-nya admin atau kepala_lab
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'kepala_lab'])) {
    header('Location: dashboard.php');
    exit();
}

require_once 'config.php';

require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Ambil parameter filter
 $lab_id = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : 0;
 $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
 $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
 $report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'reservations';

// Ambil data user
try {
    $sql = "SELECT id, name, role, lab_id FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        die("User tidak ditemukan.");
    }
    
    // Jika role kepala_lab, gunakan lab_id-nya
    if ($user['role'] === 'kepala_lab') {
        $lab_id = $user['lab_id'];
    }
} catch (PDOException $e) {
    error_log("Export report error: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat data.");
}

// Ambil data laporan berdasarkan filter
 $report_data = [];
try {
    switch ($report_type) {
        case 'reservations':
            $sql = "SELECT r.*, u.name as user_name, l.name as lab_name, a.name as approved_by_name 
                    FROM reservations r
                    JOIN users u ON r.user_id = u.id
                    JOIN laboratories l ON r.lab_id = l.id
                    LEFT JOIN users a ON r.approved_by = a.id
                    WHERE r.created_at BETWEEN :start_date AND :end_date";
            $params = [
                ':start_date' => $start_date . ' 00:00:00',
                ':end_date' => $end_date . ' 23:59:59'
            ];
            
            if ($lab_id > 0) {
                $sql .= " AND r.lab_id = :lab_id";
                $params[':lab_id'] = $lab_id;
            }
            
            $sql .= " ORDER BY r.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'equipment':
            $sql = "SELECT e.*, l.name as lab_name 
                    FROM equipment e
                    JOIN laboratories l ON e.lab_id = l.id";
            $params = [];
            
            if ($lab_id > 0) {
                $sql .= " WHERE e.lab_id = :lab_id";
                $params[':lab_id'] = $lab_id;
            }
            
            $sql .= " ORDER BY e.lab_id, e.name";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'activities':
            $sql = "SELECT a.*, u.name as user_name, l.name as lab_name 
                    FROM activities a
                    JOIN users u ON a.user_id = u.id
                    JOIN laboratories l ON a.lab_id = l.id
                    WHERE a.created_at BETWEEN :start_date AND :end_date";
            $params = [
                ':start_date' => $start_date . ' 00:00:00',
                ':end_date' => $end_date . ' 23:59:59'
            ];
            
            if ($lab_id > 0) {
                $sql .= " AND a.lab_id = :lab_id";
                $params[':lab_id'] = $lab_id;
            }
            
            $sql .= " ORDER BY a.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'equipment_usage':
            $sql = "SELECT ri.*, e.name as equipment_name, l.name as lab_name, r.user_name, r.start_time, r.end_time 
                    FROM reservation_items ri
                    JOIN equipment e ON ri.equipment_id = e.id
                    JOIN laboratories l ON e.lab_id = l.id
                    JOIN (
                        SELECT r.id, u.name as user_name, r.start_time, r.end_time
                        FROM reservations r
                        JOIN users u ON r.user_id = u.id
                        WHERE r.status = 'completed' AND r.created_at BETWEEN :start_date AND :end_date";
            $params = [
                ':start_date' => $start_date . ' 00:00:00',
                ':end_date' => $end_date . ' 23:59:59'
            ];
            
            if ($lab_id > 0) {
                $sql .= " AND r.lab_id = :lab_id";
                $params[':lab_id'] = $lab_id;
            }
            
            $sql .= " ) r ON ri.reservation_id = r.id
                    ORDER BY l.name, e.name, r.start_time";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $report_data = $stmt->fetchAll();
            break;
    }
} catch (PDOException $e) {
    error_log("Fetch report data error: " . $e->getMessage());
    die("Terjadi kesalahan saat mengambil data laporan.");
}

// Buat spreadsheet baru
 $spreadsheet = new Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

// Set judul laporan
 $report_title = '';
switch ($report_type) {
    case 'reservations':
        $report_title = 'LAPORAN PEMESANAN';
        break;
    case 'equipment':
        $report_title = 'LAPORAN PERALATAN';
        break;
    case 'activities':
        $report_title = 'LAPORAN AKTIVITAS';
        break;
    case 'equipment_usage':
        $report_title = 'LAPORAN PENGGUNAAN PERALATAN';
        break;
}

// Header
 $sheet->mergeCells('A1:K1');
 $sheet->setCellValue('A1', $report_title);
 $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
 $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

 $sheet->mergeCells('A2:K2');
 $sheet->setCellValue('A2', 'SISTEM MANAJEMEN LABORATORIUM KOMPUTER');
 $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
 $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

 $sheet->mergeCells('A3:K3');
 $sheet->setCellValue('A3', 'Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)));
 $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

if ($lab_id > 0) {
    try {
        $sql = "SELECT name FROM laboratories WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $lab_id, PDO::PARAM_INT);
        $stmt->execute();
        $lab = $stmt->fetch();
        
        if ($lab) {
            $sheet->mergeCells('A4:K4');
            $sheet->setCellValue('A4', 'Laboratorium: ' . $lab['name']);
            $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    } catch (PDOException $e) {
        // Abaikan error
    }
}

 $sheet->mergeCells('A5:K5');
 $sheet->setCellValue('A5', 'Tanggal Cetak: ' . date('d M Y'));
 $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Tambahkan baris kosong
 $sheet->mergeCells('A6:K6');

// Set header tabel
 $row = 7;
switch ($report_type) {
    case 'reservations':
        $sheet->setCellValue('A' . $row, 'ID');
        $sheet->setCellValue('B' . $row, 'Pemesan');
        $sheet->setCellValue('C' . $row, 'Laboratorium');
        $sheet->setCellValue('D' . $row, 'Tujuan');
        $sheet->setCellValue('E' . $row, 'Waktu Mulai');
        $sheet->setCellValue('F' . $row, 'Waktu Selesai');
        $sheet->setCellValue('G' . $row, 'Status');
        $sheet->setCellValue('H' . $row, 'Disetujui Oleh');
        $sheet->setCellValue('I' . $row, 'Catatan');
        break;
        
    case 'equipment':
        $sheet->setCellValue('A' . $row, 'ID');
        $sheet->setCellValue('B' . $row, 'Nama Peralatan');
        $sheet->setCellValue('C' . $row, 'Laboratorium');
        $sheet->setCellValue('D' . $row, 'Deskripsi');
        $sheet->setCellValue('E' . $row, 'Jumlah Total');
        $sheet->setCellValue('F' . $row, 'Jumlah Tersedia');
        $sheet->setCellValue('G' . $row, 'Kondisi');
        break;
        
    case 'activities':
        $sheet->setCellValue('A' . $row, 'ID');
        $sheet->setCellValue('B' . $row, 'Pengguna');
        $sheet->setCellValue('C' . $row, 'Laboratorium');
        $sheet->setCellValue('D' . $row, 'Jenis Aktivitas');
        $sheet->setCellValue('E' . $row, 'Deskripsi');
        $sheet->setCellValue('F' . $row, 'IP Address');
        $sheet->setCellValue('G' . $row, 'Waktu');
        break;
        
    case 'equipment_usage':
        $sheet->setCellValue('A' . $row, 'ID');
        $sheet->setCellValue('B' . $row, 'Nama Peralatan');
        $sheet->setCellValue('C' . $row, 'Laboratorium');
        $sheet->setCellValue('D' . $row, 'Peminjam');
        $sheet->setCellValue('E' . $row, 'Waktu Pinjam');
        $sheet->setCellValue('F' . $row, 'Waktu Kembali');
        $sheet->setCellValue('G' . $row, 'Jumlah Pinjam');
        $sheet->setCellValue('H' . $row, 'Jumlah Kembali');
        $sheet->setCellValue('I' . $row, 'Kondisi Sebelum');
        $sheet->setCellValue('J' . $row, 'Kondisi Sesudah');
        $sheet->setCellValue('K' . $row, 'Catatan');
        break;
}

// Style header
 $sheet->getStyle('A' . $row . ':K' . $row)->getFont()->setBold(true);
 $sheet->getStyle('A' . $row . ':K' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
 $sheet->getStyle('A' . $row . ':K' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Isi data
 $row++;
foreach ($report_data as $data) {
    switch ($report_type) {
        case 'reservations':
            $sheet->setCellValue('A' . $row, $data['id']);
            $sheet->setCellValue('B' . $row, $data['user_name']);
            $sheet->setCellValue('C' . $row, $data['lab_name']);
            $sheet->setCellValue('D' . $row, $data['purpose']);
            $sheet->setCellValue('E' . $row, date('d M Y H:i', strtotime($data['start_time'])));
            $sheet->setCellValue('F' . $row, date('d M Y H:i', strtotime($data['end_time'])));
            
            $status = '';
            switch ($data['status']) {
                case 'pending': $status = 'Menunggu Persetujuan'; break;
                case 'approved': $status = 'Disetujui'; break;
                case 'rejected': $status = 'Ditolak'; break;
                case 'completed': $status = 'Selesai'; break;
                case 'cancelled': $status = 'Dibatalkan'; break;
                default: $status = $data['status'];
            }
            $sheet->setCellValue('G' . $row, $status);
            
            $sheet->setCellValue('H' . $row, $data['approved_by_name'] ?: '-');
            $sheet->setCellValue('I' . $row, $data['notes'] ?: '-');
            break;
            
        case 'equipment':
            $sheet->setCellValue('A' . $row, $data['id']);
            $sheet->setCellValue('B' . $row, $data['name']);
            $sheet->setCellValue('C' . $row, $data['lab_name']);
            $sheet->setCellValue('D' . $row, $data['description'] ?: '-');
            $sheet->setCellValue('E' . $row, $data['quantity']);
            $sheet->setCellValue('F' . $row, $data['available_quantity']);
            
            $condition = '';
            switch ($data['condition_status']) {
                case 'baik': $condition = 'Baik'; break;
                case 'rusak_ringan': $condition = 'Rusak Ringan'; break;
                case 'rusak_berat': $condition = 'Rusak Berat'; break;
                default: $condition = $data['condition_status'];
            }
            $sheet->setCellValue('G' . $row, $condition);
            break;
            
        case 'activities':
            $sheet->setCellValue('A' . $row, $data['id']);
            $sheet->setCellValue('B' . $row, $data['user_name']);
            $sheet->setCellValue('C' . $row, $data['lab_name']);
            
            $activity = '';
            switch ($data['activity_type']) {
                case 'login': $activity = 'Login'; break;
                case 'logout': $activity = 'Logout'; break;
                case 'create_reservation': $activity = 'Buat Pemesanan'; break;
                case 'approve_reservation': $activity = 'Setujui Pemesanan'; break;
                case 'reject_reservation': $activity = 'Tolak Pemesanan'; break;
                case 'complete_reservation': $activity = 'Selesaikan Pemesanan'; break;
                case 'cancel_reservation': $activity = 'Batalkan Pemesanan'; break;
                case 'borrow_equipment': $activity = 'Pinjam Peralatan'; break;
                case 'return_equipment': $activity = 'Kembalikan Peralatan'; break;
                case 'add_equipment': $activity = 'Tambah Peralatan'; break;
                case 'update_equipment': $activity = 'Update Peralatan'; break;
                case 'delete_equipment': $activity = 'Hapus Peralatan'; break;
                default: $activity = $data['activity_type'];
            }
            $sheet->setCellValue('D' . $row, $activity);
            
            $sheet->setCellValue('E' . $row, $data['description'] ?: '-');
            $sheet->setCellValue('F' . $row, $data['ip_address'] ?: '-');
            $sheet->setCellValue('G' . $row, date('d M Y H:i:s', strtotime($data['created_at'])));
            break;
            
        case 'equipment_usage':
            $sheet->setCellValue('A' . $row, $data['id']);
            $sheet->setCellValue('B' . $row, $data['equipment_name']);
            $sheet->setCellValue('C' . $row, $data['lab_name']);
            $sheet->setCellValue('D' . $row, $data['user_name']);
            $sheet->setCellValue('E' . $row, date('d M Y H:i', strtotime($data['start_time'])));
            $sheet->setCellValue('F' . $row, date('d M Y H:i', strtotime($data['end_time'])));
            $sheet->setCellValue('G' . $row, $data['quantity']);
            $sheet->setCellValue('H' . $row, $data['returned_quantity']);
            
            $condition_before = '';
            switch ($data['condition_before']) {
                case 'baik': $condition_before = 'Baik'; break;
                case 'rusak_ringan': $condition_before = 'Rusak Ringan'; break;
                case 'rusak_berat': $condition_before = 'Rusak Berat'; break;
                default: $condition_before = $data['condition_before'];
            }
            $sheet->setCellValue('I' . $row, $condition_before);
            
            if ($data['condition_after']) {
                $condition_after = '';
                switch ($data['condition_after']) {
                    case 'baik': $condition_after = 'Baik'; break;
                    case 'rusak_ringan': $condition_after = 'Rusak Ringan'; break;
                    case 'rusak_berat': $condition_after = 'Rusak Berat'; break;
                    default: $condition_after = $data['condition_after'];
                }
                $sheet->setCellValue('J' . $row, $condition_after);
            } else {
                $sheet->setCellValue('J' . $row, '-');
            }
            
            $sheet->setCellValue('K' . $row, $data['notes'] ?: '-');
            break;
    }
    
    // Style baris data
    $sheet->getStyle('A' . $row . ':K' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
}

// Auto-size kolom
foreach (range('A', 'K') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Tambahkan footer
 $row += 2;
 $sheet->mergeCells('A' . $row . ':E' . $row);
 $sheet->setCellValue('A' . $row, 'Mengetahui,');
 $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

 $row += 3;
 $sheet->mergeCells('A' . $row . ':E' . $row);
 $sheet->setCellValue('A' . $row, $user['name']);
 $sheet->getStyle('A' . $row)->getFont()->setUnderline(true);
 $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

 $row += 1;
 $sheet->mergeCells('A' . $row . ':E' . $row);
 $sheet->setCellValue('A' . $row, $user['role'] === 'kepala_lab' ? 'Kepala Laboratorium' : 'Administrator');
 $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Set nama file
 $filename = 'Laporan_' . str_replace(' ', '_', $report_title) . '_' . date('Y-m-d') . '.xlsx';

// Output file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

 $writer = new Xlsx($spreadsheet);
 $writer->save('php://output');
exit;
?>