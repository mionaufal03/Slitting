<?php
session_start();

// must login
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// ONLY admin can access raw material
if ($_SESSION['role'] !== 'slitting') {
    die("Access denied");
}

include 'config.php';
require 'vendor/autoload.php'; // composer autoload (PhpSpreadsheet)

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Ambil bulan & tahun dari URL
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

$monthName = strtoupper(date("F", mktime(0,0,0,$month,1))); // contoh: OCTOBER

// Query data
$result = $conn->query("
    SELECT product, lot_no, code, nominal, effective, length, status, date
    FROM raw_material_log
    WHERE MONTH(date) = $month AND YEAR(date) = $year
    ORDER BY date ASC
");

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Raw Material");

// ========== TAJUK BESAR ==========
$title = "METAKOTE DEPARTMENT - RAW MATERIAL LIST $monthName $year";
$sheet->mergeCells("A1:M1"); // gabung ikut jumlah column (tambah QR jadi 9 column)
$sheet->setCellValue("A1", $title);

// Style tajuk
$sheet->getStyle("A1")->getFont()->setBold(true)->setSize(22);
$sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// ========== HEADER TABLE ==========
$headers = ["Product", "Lot No.", "Code", "Nominal", "Effective", "Length", "Status", "Date", "QR"];
$sheet->fromArray($headers, NULL, 'A3');

// Style header
$sheet->getStyle("A3:M3")->getFont()->setBold(true);
$sheet->getStyle("A3:M3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// ========== DATA ==========
$rowNum = 4;
while($row = $result->fetch_assoc()){
    $qrLink = "https://chart.googleapis.com/chart?chs=100x100&cht=qr&chl=" . urlencode($row['code']); 
    // 👉 kalau nak gambar QR sebenar dalam cell, kena extra library (susun image)
    // buat simple: letak URL/link dulu
    
    $sheet->fromArray([
        $row['product'],
        $row['lot_no'],
        $row['code'],
        $row['nominal'],
        $row['effective'],
        $row['length'],
        $row['status'],
        $row['date'],
        $qrLink
    ], NULL, "A{$rowNum}");
    $rowNum++;
}

// ========== TABLE BORDER & ALIGN ==========
$lastRow = $rowNum - 1;
$sheet->getStyle("A3:I{$lastRow}")->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THICK
        ]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER
    ]
]);

// Auto size columns
foreach (range('A','I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ========== DOWNLOAD ==========
$filename = "raw_material_{$year}_{$month}.xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;

