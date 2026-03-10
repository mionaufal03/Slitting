<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// QC only (admin boleh masuk kalau nak)
if (!in_array($_SESSION['role'], ['qc', 'admin'], true)) {
    die("Access denied");
}

include 'config.php';

/* =========================
   TABLE 1: WAITING
========================= */
$stmtWaiting = $conn->prepare("
    SELECT id, product, lot_no, coil_no, roll_no, width, length, actual_length
    FROM slitting_product
    WHERE status='WAITING'
    ORDER BY id DESC
");
$stmtWaiting->execute();
$resWaiting = $stmtWaiting->get_result();

$totalWaiting = 0;
$resCount = $conn->query("SELECT COUNT(*) AS total FROM slitting_product WHERE status='WAITING'");
if ($resCount) {
    $totalWaiting = (int)($resCount->fetch_assoc()['total'] ?? 0);
}

/* =========================
   TABLE 2: APPROVED
========================= */
$stmtApproved = $conn->prepare("
    SELECT id, product, lot_no, coil_no, roll_no, width, length, actual_length, date_out
    FROM slitting_product
    WHERE status='APPROVED'
    ORDER BY id ASC
    LIMIT 200
");
$stmtApproved->execute();
$resApproved = $stmtApproved->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>QC Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0">QC Dashboard</h2>
            <small class="text-muted">Products waiting for QC approval</small>
        </div>
        <div>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>

    <div class="alert alert-warning">
        <b>WAITING APPROVAL:</b> <?= $totalWaiting ?>
    </div>

    <!-- ================= TABLE 1: WAITING ================= -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3">Waiting Approval List</h5>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Lot No.</th>
                            <th>Roll No.</th>
                            <th>Width</th>
                            <th>Length</th>
                            <th>Actual Length</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($resWaiting && $resWaiting->num_rows > 0): ?>
                        <?php while ($row = $resWaiting->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int)$row['id'] ?></td>
                                <td><?= htmlspecialchars($row['product'] ?? '') ?></td>
                                <td><?= htmlspecialchars(($row['lot_no'] ?? '-') . ' ' . ($row['coil_no'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($row['roll_no'] ?? '-') ?></td>
                                <td><?= $row['width'] !== null ? number_format((float)$row['width']) : '-' ?></td>
                                <td><?= $row['length'] !== null ? number_format((float)$row['length']) : '-' ?></td>
                                <td><?= $row['actual_length'] !== null ? number_format((float)$row['actual_length']) : '-' ?></td>
                                <td>
                                    <form method="post" action="qc_approve.php" class="d-inline">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm"
                                            onclick="return confirm('Approve this product?')">
                                            Approve
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-muted">No products</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- ================= TABLE 2: APPROVED ================= -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="mb-3">Approval List</h5>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Lot No.</th>
                            <th>Roll No.</th>
                            <th>Width</th>
                            <th>Length</th>
                            <th>Actual Length</th>
                            <th>Date Approve</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($resApproved && $resApproved->num_rows > 0): ?>
                        <?php while ($row = $resApproved->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int)$row['id'] ?></td>
                                <td><?= htmlspecialchars($row['product'] ?? '') ?></td>
                                <td><?= htmlspecialchars(($row['lot_no'] ?? '-') . ' ' . ($row['coil_no'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($row['roll_no'] ?? '-') ?></td>
                                <td><?= $row['width'] !== null ? number_format((float)$row['width']) : '-' ?></td>
                                <td><?= $row['length'] !== null ? number_format((float)$row['length']) : '-' ?></td>
                                <td><?= $row['actual_length'] !== null ? number_format((float)$row['actual_length']) : '-' ?></td>
                                <td><?= $row['date_out'] ? date('d/m/Y H:i', strtotime($row['date_out'])) : '-' ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-muted">No products</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>
</body>
</html>
<?php
$stmtWaiting->close();
$stmtApproved->close();
?>