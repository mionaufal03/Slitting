<?php
include 'config.php';

$qr = $_POST['qr'] ?? '';

// =====================================================
// 1) Normalize input
// =====================================================
$qr = trim($qr);
$qr = str_replace(["\r\n", "\r"], "\n", $qr);

if ($qr === '') {
    header("Location: $BASE_URL/finish_product.php?scan=empty");
    exit;
}

// =====================================================
// 2) Ambil baris pertama sahaja (QR multi-line)
// =====================================================
$firstLine = strtok($qr, "\n");
$firstLine = trim($firstLine);

// =====================================================
// 3) Buang symbology/AIM prefix (common: ]C1, ]Q3, ]d2, etc)
// =====================================================
$firstLine = preg_replace('/^\][A-Za-z0-9]{2,3}/', '', $firstLine);

// =====================================================
// 4) Buang control chars (TAB, non-printable) + trim
// =====================================================
$firstLine = preg_replace('/[[:cntrl:]]+/', '', $firstLine);
$firstLine = trim($firstLine);

// =====================================================
// 5) Parse QR
// Support:
//  - LOT=..;COIL=..;ROLL=..
//  - LOT;COIL;ROLL (fallback)
// =====================================================
$data = [];
parse_str(str_replace(';', '&', $firstLine), $data);

// normalize keys uppercase (case-insensitive)
$upper = [];
foreach ($data as $k => $v) {
    $upper[strtoupper($k)] = $v;
}

$lot  = trim($upper['LOT']  ?? '');
$coil = trim($upper['COIL'] ?? '');
$roll = trim($upper['ROLL'] ?? '');

// fallback: LOT;COIL;ROLL
if ($lot === '' || $coil === '' || $roll === '') {
    $parts = array_values(array_filter(array_map('trim', explode(';', $firstLine)), 'strlen'));
    $lot  = $lot  !== '' ? $lot  : ($parts[0] ?? '');
    $coil = $coil !== '' ? $coil : ($parts[1] ?? '');
    $roll = $roll !== '' ? $roll : ($parts[2] ?? '');
}

// last cleanup
$lot  = trim($lot);
$coil = trim($coil);
$roll = trim($roll);

if ($lot === '' || $coil === '' || $roll === '') {
    header("Location: $BASE_URL/finish_product.php?scan=invalid");
    exit;
}

// =====================================================
// 6) Month/year (untuk kekalkan dropdown bila redirect)
// =====================================================
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('m');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

$redirBase = "$BASE_URL/finish_product.php?month=$month&year=$year";

// =====================================================
// 7) Get product by key (lot+coil+roll)
// FIX: ORDER BY id DESC supaya ambil record terbaru
// =====================================================
$stmt = $conn->prepare("
    SELECT id, status
    FROM slitting_product
    WHERE lot_no=? AND coil_no=? AND roll_no=?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->bind_param("sss", $lot, $coil, $roll);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: $redirBase&scan=notfound");
    exit;
}

$id     = (int)$product['id'];
$status = strtoupper(trim($product['status'] ?? ''));

// =====================================================
// 8) FLOW
// IN       -> WAITING (scan 1st time)
// WAITING  -> stay WAITING
// APPROVED -> DELIVERED (scan after QC approve)
// =====================================================

if ($status === 'IN') {
    $stmt = $conn->prepare("UPDATE slitting_product SET status='WAITING', date_out=NOW() WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: $redirBase&scan=waiting");
    exit;
}

if ($status === 'WAITING') {
    // still waiting QC approval, jangan tukar status
    header("Location: $redirBase&scan=waiting");
    exit;
}

if ($status === 'APPROVED') {
    $stmt = $conn->prepare("UPDATE slitting_product SET status='DELIVERED', delivered_at=NOW() WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: $redirBase&scan=delivered");
    exit;
}

if ($status === 'DELIVERED') {
    header("Location: $redirBase&scan=already_delivered");
    exit;
}

// other status
header("Location: $redirBase&scan=ignored");
exit;