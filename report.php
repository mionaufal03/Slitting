<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'slitting') {
    die("Access denied");
}

include 'config.php';

/* ================= FUNCTIONS ================= */

function tableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = ($res && $res->num_rows > 0);
    $stmt->close();
    return $ok;
}

function colExists(mysqli $conn, string $table, string $col): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $table, $col);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = ($res && $res->num_rows > 0);
    $stmt->close();
    return $ok;
}

function scalarInt(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = $conn->prepare($sql);
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $val = 0;
    if ($res && ($row = $res->fetch_row())) {
        $val = (int)($row[0] ?? 0);
    }
    $stmt->close();
    return $val;
}

function monthRangeDatetime(int $year, int $month): array
{
    $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    $end   = date('Y-m-d H:i:s', strtotime($start . ' +1 month'));
    return [$start, $end];
}

function monthRangeDate(int $year, int $month): array
{
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end   = date('Y-m-d', strtotime($start . ' +1 month'));
    return [$start, $end];
}

/* ================= YEAR ================= */

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$year = max(2000, min(2100, $year));

/* ================= MONTH ================= */

$months = [];
for ($m=1; $m<=12; $m++) {
    $months[] = ['num'=>$m, 'name'=>date('M', mktime(0,0,0,$m,1))];
}

/* ================= CATEGORY ================= */

$categories = [
    'raw_use'     => 'Raw Material Use',
    'raw_stock'   => 'Raw Material Stock',
    'slit_use'    => 'Slit Product Use',
    'slit_stock'  => 'Slit Product Stock',
    'recoiling'   => 'Recoiling',
    'reslit'      => 'Reslit',
];

$data = [];
foreach ($categories as $k => $label) $data[$k] = array_fill(0, 12, 0);

/* ================= TABLE NAMES (ikut DB awak) ================= */

$T_RAW_LOG   = 'raw_material_log';
$T_MOTHER    = 'mother_coil';
$T_SLIT      = 'slitting_product';

// selepas cut / after cut: awak ada table stock_raw_material (kalau tak guna, biar)
$T_AFTERCUT  = 'stock_raw_material';

/* ================= COLUMN DETECT (ikut DB awak) ================= */

// delivered tarikh (paling bagus guna delivered_at, fallback date_out)
$slitDeliveredCol = null;
if (colExists($conn, $T_SLIT, 'delivered_at')) $slitDeliveredCol = 'delivered_at';
else if (colExists($conn, $T_SLIT, 'date_out')) $slitDeliveredCol = 'date_out';

// after cut date column (kalau ada)
$afterDateCol = null;
if (tableExists($conn, $T_AFTERCUT)) {
    if (colExists($conn, $T_AFTERCUT, 'date_in')) $afterDateCol = 'date_in';
    else if (colExists($conn, $T_AFTERCUT, 'date_created')) $afterDateCol = 'date_created';
    else if (colExists($conn, $T_AFTERCUT, 'created_at')) $afterDateCol = 'created_at';
    else if (colExists($conn, $T_AFTERCUT, 'date')) $afterDateCol = 'date';
}

/* =====================================================
   ✅ KIRAAN BULANAN (MONTHLY ONLY)
   - Semua nilai hanya kira record yang berlaku DALAM bulan tu
   - Tak ada lagi snapshot carry-forward (jadi May/Jun/Jul tak akan muncul kalau kosong)
   ===================================================== */

for ($m=1; $m<=12; $m++) {
    $idx = $m - 1;

    // raw_material_log date_out = datetime
    [$dtStart, $dtEnd] = monthRangeDatetime($year, $m);

    // slitting_product date_in = DATE
    [$dStart, $dEnd]   = monthRangeDate($year, $m);

    /* 1) RAW MATERIAL USE = total raw_material_log status OUT dalam bulan tu */
    if (tableExists($conn, $T_RAW_LOG) && colExists($conn, $T_RAW_LOG, 'status') && colExists($conn, $T_RAW_LOG, 'date_out')) {
        $data['raw_use'][$idx] = scalarInt(
            $conn,
            "SELECT COUNT(*)
             FROM `$T_RAW_LOG`
             WHERE `status`='OUT'
               AND `date_out` IS NOT NULL
               AND `date_out` >= ?
               AND `date_out` < ?",
            "ss",
            [$dtStart, $dtEnd]
        );
    }

    /* 2) RAW MATERIAL STOCK = mother_coil IN (masuk bulan tu) + after cut IN (masuk bulan tu) */
    $rawStock = 0;

    // mother_coil date_in = datetime
    if (tableExists($conn, $T_MOTHER) && colExists($conn, $T_MOTHER, 'status') && colExists($conn, $T_MOTHER, 'date_in')) {
        $rawStock += scalarInt(
            $conn,
            "SELECT COUNT(*)
             FROM `$T_MOTHER`
             WHERE `status`='IN'
               AND `date_in` IS NOT NULL
               AND `date_in` >= ?
               AND `date_in` < ?",
            "ss",
            [$dtStart, $dtEnd]
        );
    }

    // after cut stock_raw_material (kalau ada)
    if (tableExists($conn, $T_AFTERCUT) && colExists($conn, $T_AFTERCUT, 'status') && $afterDateCol) {
        // status mungkin 'IN' / 'OUT' juga. Kita guna IN.
        $rawStock += scalarInt(
            $conn,
            "SELECT COUNT(*)
             FROM `$T_AFTERCUT`
             WHERE `status`='IN'
               AND `$afterDateCol` IS NOT NULL
               AND `$afterDateCol` >= ?
               AND `$afterDateCol` < ?",
            "ss",
            [$dtStart, $dtEnd]
        );
    }

    $data['raw_stock'][$idx] = $rawStock;

    /* 3) SLITTING PRODUCT USE = total DELIVERED dalam bulan tu (ikut delivered_at/date_out) */
    if (tableExists($conn, $T_SLIT) && colExists($conn, $T_SLIT, 'status') && $slitDeliveredCol) {
        $data['slit_use'][$idx] = scalarInt(
            $conn,
            "SELECT COUNT(*)
             FROM `$T_SLIT`
             WHERE `status`='DELIVERED'
               AND `$slitDeliveredCol` IS NOT NULL
               AND `$slitDeliveredCol` >= ?
               AND `$slitDeliveredCol` < ?",
            "ss",
            [$dtStart, $dtEnd]
        );
    }

    /* 4) SLITTING PRODUCT STOCK = total IN (yang belum keluar) MASUK bulan tu sahaja */
    if (tableExists($conn, $T_SLIT) && colExists($conn, $T_SLIT, 'status') && colExists($conn, $T_SLIT, 'date_in')) {
        $data['slit_stock'][$idx] = scalarInt(
            $conn,
            "SELECT COUNT(*)
             FROM `$T_SLIT`
             WHERE `status`='IN'
               AND `date_in` IS NOT NULL
               AND `date_in` >= ?
               AND `date_in` < ?",
            "ss",
            [$dStart, $dEnd]
        );
    }

    /* 5) RECOILING = total product yang masuk recoiling dalam bulan tu (is_recoiled=1) */
    if (tableExists($conn, $T_SLIT) && colExists($conn, $T_SLIT, 'is_recoiled') && colExists($conn, $T_SLIT, 'date_in')) {
        $data['recoiling'][$idx] = scalarInt(
            $conn,
            "SELECT COUNT(*)
             FROM `$T_SLIT`
             WHERE `is_recoiled`=1
               AND `date_in` IS NOT NULL
               AND `date_in` >= ?
               AND `date_in` < ?",
            "ss",
            [$dStart, $dEnd]
        );
    }

    /* 6) RESLIT = total product yang masuk reslit dalam bulan tu (is_reslited=1) */
    if (tableExists($conn, $T_SLIT) && colExists($conn, $T_SLIT, 'is_reslited') && colExists($conn, $T_SLIT, 'date_in')) {
        $data['reslit'][$idx] = scalarInt(
            $conn,
            "SELECT COUNT(*)
             FROM `$T_SLIT`
             WHERE `is_reslited`=1
               AND `date_in` IS NOT NULL
               AND `date_in` >= ?
               AND `date_in` < ?",
            "ss",
            [$dStart, $dEnd]
        );
    }
}

/* ================= TOTAL COLUMN (KANAN) =================
   Awak nak TOTAL = jumlah Jan–Dec (sebab semua sekarang monthly)
*/
$rightTotals = [];
foreach ($categories as $key => $label) {
    $rightTotals[$key] = array_sum($data[$key]);
}

// total per month (grand row bawah)
$monthTotals = array_fill(0, 12, 0);
for ($i=0; $i<12; $i++) {
    $sum = 0;
    foreach ($categories as $key => $label) $sum += (int)$data[$key][$i];
    $monthTotals[$i] = $sum;
}

// grand total kanan bawah
$grandRightTotal = array_sum($monthTotals);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Summary - MK Slitting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .sticky-left{
            position: sticky;
            left: 0;
            background: #f8f9fa;
            z-index: 2;
        }
        thead th.sticky-left{ z-index: 3; }
        .table thead th{ white-space: nowrap; }

        .category-label{
            text-align: left !important;
            padding-left: 15px !important;
            font-weight: 600;
        }

        .total-col{
            background-color: #88b4f6 !important;
            color: #fff !important;
            font-weight: 800;
        }
        .total-header{
            background-color: #084298 !important;
            color: #fff !important;
            font-weight: 900;
        }

        .grand-row th, .grand-row td{
            background:#0b5ed7 !important;
            color:#fff !important;
            font-weight:900 !important;
        }

        tbody tr:hover td{ background:#eef5ff; }
    </style>
</head>

<body class="p-4">
<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">Summary</h3>

        <form method="get" class="d-flex gap-2 align-items-center">
            <label class="m-0">Year:</label>
            <select name="year" class="form-select w-auto" onchange="this.form.submit()">
                <?php for($y=2025; $y<=2030; $y++): ?>
                    <option value="<?= $y ?>" <?= ($y==$year)?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center">

            <thead class="table-dark">
                <tr>
                    <th class="sticky-left">Category</th>
                    <?php foreach($months as $mm): ?>
                        <th><?= htmlspecialchars($mm['name']) ?></th>
                    <?php endforeach; ?>
                    <th class="total-header">TOTAL</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach($categories as $key => $label): ?>
                    <tr>
                        <th class="sticky-left table-secondary category-label">
                            <?= htmlspecialchars($label) ?>
                        </th>

                        <?php for($i=0; $i<12; $i++): ?>
                            <td><?= (int)$data[$key][$i] ?></td>
                        <?php endfor; ?>

                        <td class="total-col"><?= (int)$rightTotals[$key] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>

    <a href="index.php" class="btn btn-secondary mt-2">← Back</a>

</div>
</body>
</html>