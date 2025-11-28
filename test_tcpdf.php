<?php
// test_tcpdf.php
// Test file untuk memverifikasi instalasi TCPDF

require_once 'vendor/autoload.php';

// Cek apakah TCPDF class tersedia
if (class_exists('TCPDF')) {
    echo "✅ TCPDF berhasil diinstal!";
    
    // Test membuat PDF sederhana
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Test TCPDF Installation', 0, 1, 'C');
    $pdf->Output('test.pdf', 'I');
} else {
    echo "❌ TCPDF tidak ditemukan. Periksa instalasi Anda.";
}
?>