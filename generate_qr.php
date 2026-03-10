<?php
require __DIR__ . '/vendor/autoload.php';
include 'config.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;

$type = $_GET['type'] ?? 'mother';

$size   = 500;   // besar sikit
$margin = 30;    // quiet zone besar

$qrText = '';

if ($type === 'slitting') {

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) die('Invalid ID');

    $stmt = $conn->prepare("SELECT lot_no, coil_no, roll_no FROM slitting_product WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) die('Slitting product not found');

    // ✅ 1 line sahaja (paling senang scanner)
    $qrText = "LOT={$row['lot_no']};COIL={$row['coil_no']};ROLL={$row['roll_no']}";

} else {

    $lot  = trim($_GET['lot'] ?? '');
    $coil = trim($_GET['coil'] ?? '');

    if ($lot === '' || $coil === '') die('Invalid QR data');

    // ✅ 1 line sahaja
    $qrText = "LOT={$lot};COIL={$coil}";
}

$writer = new PngWriter();

$qr = QrCode::create($qrText)
    ->setSize($size)
    ->setMargin(0)
    ->setForegroundColor(new Color(0, 0, 0))
    ->setBackgroundColor(new Color(255, 255, 255,0));// alpha = 0 = transparent

header('Content-Type: image/png');
header('Cache-Control: no-cache, must-revalidate');
header('Content-Disposition: inline; filename="qr.png"');

echo $writer->write($qr)->getString();
exit;