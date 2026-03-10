<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'qc') {
    die("Access denied");
}

include 'config.php';

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header("Location: qc_dashboard.php");
    exit;
}

// UPDATE WAITING -> APPROVED
$stmt = $conn->prepare("UPDATE slitting_product SET status='APPROVED' WHERE id=? AND status='WAITING'");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// balik dashboard lepas approve
header("Location: qc_dashboard.php?approved=1");
exit;
