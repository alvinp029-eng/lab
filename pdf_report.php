<?php
// pdf_report.php
session_start();

// Cek apakah data PDF tersedia di session
if (!isset($_SESSION['pdf_data'])) {
    header('Location: reports.php');
    exit();
}

// Ambil data dari session
 $report_data = $_SESSION['pdf_data'];
 $report_type = $_SESSION['pdf_type'];
 $lab_filter = $_SESSION['pdf_lab_filter'];
 $laboratories = $_SESSION['pdf_laboratories'];
 $start_date = $_SESSION['pdf_start_date'];
 $end_date = $_SESSION['pdf_end_date'];

// Hapus data dari session setelah diambil
unset($_SESSION['pdf_data']);
unset($_SESSION['pdf_type']);
unset($_SESSION['pdf_lab_filter']);
unset($_SESSION['pdf_laboratories']);
unset($_SESSION['pdf_start_date']);
unset($_SESSION['pdf_end_date']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan <?php echo ucfirst($report_type); ?> - SILABKOM</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header h2 {
            margin: 10px 0;
            font-size: 18px;
        }
        .header p {
            margin: 5px 0;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer {
            margin-top: 50px;
            text-align: right;
        }
        .footer p {
            margin: 5px 0;
        }
        .status-badge {
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .status-pending { background-color: #ffc107; }
        .status-approved { background-color: #28a745; color: white; }
        .status-borrowed { background-color: #007bff; color: white; }
        .status-returned { background-color: #6c757d; color: white; }
        .status-overdue { background-color: #dc3545; color: white; }
        .status-lost { background-color: #343a40; color: white; }
        .status-rejected { background-color: #dc3545; color: white; }
        .status-completed { background-color: #28a745; color: white; }
        .status-cancelled { background-color: #6c757d; color: white; }
        .status-available { background-color: #28a745; color: white; }
        .status-in_use { background-color: #007bff; color: white; }
        .status-not_borrowable { background-color: #6c757d; color: white; }
        .no-print {
            display: none;
        }
        @media print {
            .no-print {
                display: block;
                text-align: center;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Cetak</button>
        <button onclick="window.close()">Tutup</button>
    </div>
    
    <div class="header">
        <h1>LAPORAN <?php echo strtoupper($report_type); ?></h1>
        <?php if ($lab_filter !== 'all'): ?>
            <h2>Laboratorium: <?php echo htmlspecialchars($laboratories[array_search($lab_filter, array_column($laboratories, 'id'))]['name']); ?></h2>
        <?php endif; ?>
        <?php if ($report_type !== 'inventaris'): ?>
            <p>Periode: <?php echo date('d F Y', strtotime($start_date)); ?> - <?php echo date('d F Y', strtotime($end_date)); ?></p>
        <?php endif; ?>
        <p>Tanggal Cetak: <?php echo date('d F Y'); ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <?php if ($report_type === 'peminjaman'): ?>
                    <th width="12%">Peminjam</th>
                    <th width="10%">NIP/NIM</th>
                    <th width="15%">Nama Barang</th>
                    <th width="10%">Tipe</th>
                    <th width="10%">Lab</th>
                    <th width="10%">Tgl Pinjam</th>
                    <th width="10%">Harus Kembali</th>
                    <th width="10%">Tgl Kembali</th>
                    <th width="13%">Status</th>
                <?php elseif ($report_type === 'pemesanan'): ?>
                    <th width="12%">Pemesan</th>
                    <th width="10%">NIP/NIM</th>
                    <th width="15%">Lab</th>
                    <th width="25%">Tujuan</th>
                    <th width="25%">Waktu</th>
                    <th width="13%">Status</th>
                <?php elseif ($report_type === 'inventaris'): ?>
                    <th width="5%">No</th>
                    <th width="20%">Nama Barang</th>
                    <th width="25%">Spesifikasi</th>
                    <th width="8%">Kuantitas</th>
                    <th width="20%">Lokasi</th>
                    <th width="12%">Status</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $rowNumber = 1;
            foreach ($report_data as $row): 
            ?>
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
                        <td>
                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                <?php 
                                switch($row['status']) {
                                    case 'pending': echo 'Menunggu'; break;
                                    case 'approved': echo 'Disetujui'; break;
                                    case 'borrowed': echo 'Dipinjam'; break;
                                    case 'returned': echo 'Dikembalikan'; break;
                                    case 'overdue': echo 'Terlambat'; break;
                                    case 'lost': echo 'Hilang'; break;
                                    default: echo ucfirst($row['status']);
                                }
                                ?>
                            </span>
                        </td>
                    <?php elseif ($report_type === 'pemesanan'): ?>
                        <td><?php echo htmlspecialchars($row['pemesan']); ?></td>
                        <td><?php echo htmlspecialchars($row['nip_nim']); ?></td>
                        <td><?php echo htmlspecialchars($row['lab_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td>
                            <?php 
                            $start = new DateTime($row['start_time']);
                            $end = new DateTime($row['end_time']);
                            echo $start->format('d M Y, H:i') . ' - ' . $end->format('H:i');
                            ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                <?php 
                                switch($row['status']) {
                                    case 'pending': echo 'Menunggu'; break;
                                    case 'approved': echo 'Disetujui'; break;
                                    case 'rejected': echo 'Ditolak'; break;
                                    case 'completed': echo 'Selesai'; break;
                                    case 'cancelled': echo 'Dibatalkan'; break;
                                    default: echo ucfirst($row['status']);
                                }
                                ?>
                            </span>
                        </td>
                    <?php elseif ($report_type === 'inventaris'): ?>
                        <td><?php echo $rowNumber++; ?></td>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($row['lab_name'] . ' - ' . $row['lab_location']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                <?php 
                                switch($row['status']) {
                                    case 'available': echo 'Tersedia'; break;
                                    case 'in_use': echo 'Digunakan'; break;
                                    case 'not_borrowable': echo 'Tidak Bisa Dipinjam'; break;
                                    default: echo ucfirst($row['status']);
                                }
                                ?>
                            </span>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Mengetahui,</p>
        <p style="margin-top: 40px;">Kepala Laboratorium</p>
        <p style="margin-top: 20px;">(_________________________)</p>
    </div>
    
    <script>
        // Auto print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
        
        // Close window after printing
        window.onafterprint = function() {
            window.close();
        };
    </script>
</body>
</html>