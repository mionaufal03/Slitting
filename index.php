<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Only ADMIN can access dashboard
if ($_SESSION['role'] !== 'slitting') {
    header("Location: index.php");
    exit;
}

include 'config.php';


// === RAW MATERIAL SUMMARY ===
$in_raw  = $conn->query("SELECT COUNT(*) AS total FROM raw_material_log WHERE status='IN'")->fetch_assoc()['total'];
$out_raw = $conn->query("SELECT COUNT(*) AS total FROM raw_material_log WHERE status='OUT'")->fetch_assoc()['total'];
$stock_raw = max(0, (int)$in_raw - (int)$out_raw);

// After Cut Stock = Stock leftover from cut_into_2
$afterCutStock_raw = $conn->query("SELECT COUNT(*) AS total FROM raw_material_log 
                                   WHERE status='IN' AND action='cut_into_2'")
                                   ->fetch_assoc()['total'];

// === FINISH PRODUCT (Follow finish_product.php) ===
$month = (int)date('m');
$year  = (int)date('Y');

// FIXED: Add exclusion for recoiled/reslitted products
$in_finish = $conn->query("SELECT COUNT(*) AS total FROM slitting_product 
                           WHERE status='IN' AND is_completed=0
                           AND (is_recoiled=0 OR is_recoiled IS NULL) 
                           AND (is_reslitted=0 OR is_reslitted IS NULL)")
                           ->fetch_assoc()['total'];

$stock_finish = $conn->query("SELECT COUNT(*) AS total FROM slitting_product 
                              WHERE status='IN' AND stock_counted=1
                              AND (is_recoiled=0 OR is_recoiled IS NULL) 
                              AND (is_reslitted=0 OR is_reslitted IS NULL)")
                              ->fetch_assoc()['total'];

$out_finish = $conn->query("SELECT COUNT(*) AS total FROM slitting_product 
                            WHERE status='OUT' 
                            AND MONTH(date_out)=$month AND YEAR(date_out)=$year")
                            ->fetch_assoc()['total'];

$waiting_finish = $conn->query("SELECT COUNT(*) AS total FROM slitting_product 
                                WHERE status='WAITING' 
                                AND MONTH(date_out)=$month AND YEAR(date_out)=$year")
                                ->fetch_assoc()['total'];

$deliver_finish = $conn->query("SELECT COUNT(*) AS total FROM slitting_product 
                                WHERE status='DELIVERED' 
                                AND MONTH(delivered_at)=$month AND YEAR(delivered_at)=$year")
                                ->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Slitting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 bg-dark text-white min-vh-100 p-3">
            <div class="d-flex align-items-center justify-content-center mb-3">
                <img src="assets/nichiaslogo.jpg" alt="Logo" style="max-width: 50px; height: auto;" class="me-2">
                <h4 class="m-0 text-white">MK SLITTING DEPARTMENT</h4>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="index.php">Dashboard</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="raw_material.php">Raw Material</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="finish_product.php">Finish Product</a>
                </li>
                 <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="recoiling.php">Recoiling Cut</a>
                </li>
                 <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="reslit.php">Reslit Product</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="mother_coil.php">Add Mother Coil</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="report.php">Report</a>
                </li>
            </ul>
        </div>

        <!-- Content -->
        <div class="col-md-10 p-4">
            <h2>Dashboard - Metakote Slitting Department</h2>

            <?php if (isset($_GET['scan'])): ?>
                <div class="alert alert-info py-2 my-2">
                    Scan status: <?= htmlspecialchars($_GET['scan']) ?>
                </div>
            <?php endif; ?>

            <!-- Hidden form for wireless scanner (invisible to user) -->
            <form id="scanForm" method="post" action="scan_mother_action.php" autocomplete="off" style="position:absolute; left:-9999px;">
                <input id="qrInput" type="text" name="qr" autofocus>
            </form>

            <div class="row g-4 mt-2">

                <!-- Raw Material -->
                <div class="col-md-6">
                    <div class="card shadow-sm border-success">
                        <div class="card-body text-center">
                            <h5 class="card-title">Raw Material</h5>
                            <div class="row">
                                <div class="col">
                                    <h6 class="text-success">IN</h6>
                                    <h4><?= $in_raw ?></h4>
                                </div>
                                <div class="col">
                                    <h6 class="text-danger">OUT</h6>
                                    <h4><?= $out_raw ?></h4>
                                </div>
                                <div class="col">
                                    <h6 class="text-primary">STOCK</h6>
                                    <h4><?= $stock_raw ?></h4>
                                </div>
                                <div class="col">
                                    <h6 class="text-warning">AFTER CUT</h6>
                                    <h4><?= $afterCutStock_raw ?></h4>
                                </div>
                            </div>
                            <a href="raw_material.php" class="btn btn-primary btn-sm mt-2">View Raw Material</a>
                        </div>
                    </div>
                </div>

                <!-- Slitting Product -->
                <div class="col-md-6">
                    <div class="card shadow-sm border-primary">
                        <div class="card-body text-center">
                            <h5 class="card-title">Slitting Product</h5>
                            <div class="row">
                                <div class="col">
                                    <h6 class="text-success">IN</h6>
                                    <h4><?= $in_finish ?></h4>
                                </div>
                                <div class="col">
                                    <h6 class="text-primary">STOCK</h6>
                                    <h4><?= $stock_finish ?></h4>
                                </div>
                                <div class="col">
                                    <h6 class="text-danger">OUT</h6>
                                    <h4><?= $out_finish ?></h4>
                                </div>
                                <div class="col">
                                    <h6 class="text-warning">DELIVER</h6>
                                    <h4><?= $deliver_finish ?></h4>
                                </div>
                            </div>
                            <a href="finish_product.php" class="btn btn-primary btn-sm mt-2">View Finish Product</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>  
</div>

</body>
</html>