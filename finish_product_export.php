<?php
include 'config.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Ambil bulan & tahun dari URL
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

if($month < 1 || $month > 12){ $month = (int)date('m'); }
if($year < 2000 || $year > 2100){ $year = (int)date('Y'); }

// Query data finish_product
$result = $conn->query("
    SELECT id, product, lot_no, coil_no, roll_no, width, length, status, date_created, date_out, delivered_at
    FROM finish_product
    WHERE MONTH(date_created) = $month AND YEAR(date_created) = $year
    ORDER BY date_created ASC
");

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Finish Product");

// Header
$headers = ["ID","Product","Lot No.","Coil No.","Roll No.","Width","Length","Status","Date Created","Date Out","Delivered At","QR Code"];
$sheet->fromArray($headers, NULL, 'A1');

// Temporary folder untuk QR codes (akan delete lepas export)
$tempQrDir = sys_get_temp_dir() . '/qr_temp_' . uniqid();
if(!is_dir($tempQrDir)) mkdir($tempQrDir, 0777, true);

// Data
$rowNum = 2;
$writer = new PngWriter();

while($row = $result->fetch_assoc()){
    // Masukkan text data
    $sheet->fromArray([
        $row['id'],
        $row['product'],
        $row['lot_no'],
        $row['coil_no'],
        $row['roll_no'],
        $row['width'],
        $row['length'],
        $row['status'],
        $row['date_created'],
        $row['date_out'],
        $row['delivered_at']
    ], NULL, "A{$rowNum}");

    // Generate QR code dynamically untuk Excel
    $qrTempPath = $tempQrDir . "/finish_" . $row['id'] . ".png";
    
    $qr = QrCode::create($BASE_URL . "/scan_finish.php?id=" . $row['id'])
        ->setSize(300)
        ->setMargin(10);
    
    // Save temporarily untuk Excel
    $writer->write($qr)->saveToFile($qrTempPath);

    if(file_exists($qrTempPath)){
        $drawing = new Drawing();
        $drawing->setName('QR Code');
        $drawing->setDescription('QR Code');
        $drawing->setPath($qrTempPath);
        $drawing->setHeight(60); // tinggi QR
        $drawing->setCoordinates("L{$rowNum}"); // column L untuk QR
        $drawing->setOffsetX(5);
        $drawing->setOffsetY(5);
        $drawing->setWorksheet($sheet);

        // Tinggikan row ikut QR
        $sheet->getRowDimension($rowNum)->setRowHeight(60);
    }

    $rowNum++;
}

// Auto size untuk column A-K
foreach (range('A','K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
// Column L untuk QR code
$sheet->getColumnDimension('L')->setWidth(15);

// Border semua sel
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ],
    ],
];
$sheet->getStyle("A1:L".($rowNum-1))->applyFromArray($styleArray);

// Header bold + background kelabu
$sheet->getStyle("A1:L1")->getFont()->setBold(true);
$sheet->getStyle("A1:L1")->getFill()
      ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
      ->getStartColor()->setARGB('FFD9D9D9');

// Download fail Excel
$filename = "finish_product_{$year}_{$month}.xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
$xlsxWriter = new Xlsx($spreadsheet);
$xlsxWriter->save("php://output");

// Clean up temporary QR files
array_map('unlink', glob("$tempQrDir/*.png"));
rmdir($tempQrDir);

exit;
?>