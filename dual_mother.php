<?php
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid ID");
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/Android|iPhone|iPad|Mobile/i', $userAgent)) {
    header("Location: view_mother.php?id=$id");
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pilih Mode Scan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow p-4 text-center">
        <h4>Scan Mother Coil</h4>
        <p>Sila pilih bagaimana QR ini discan:</p>
        <a href="view_mother.php?id=<?= $id ?>" class="btn btn-primary btn-lg mb-2">📱 Saya guna Telefon</a>
        <a href="scan_mother.php?id=<?= $id ?>" class="btn btn-success btn-lg">🖥️ Saya guna Scanner / PC</a>
    </div>
</body>
</html>
