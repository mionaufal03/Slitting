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

// Bulan & Tahun dipilih
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

if ($month < 1 || $month > 12) { $month = (int)date('m'); }
if ($year < 2000 || $year > 2100) { $year = (int)date('Y'); }

// NEW: Handle QC Approve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'qc_approve') {
    $id = intval($_POST['product_id']);
    
    $stmt = $conn->prepare("UPDATE slitting_product SET status='APPROVED' WHERE id=? AND status='WAITING'");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: finish_product.php?success=qc_approved");
    } else {
        header("Location: finish_product.php?error=qc_approve_failed");
    }
    $stmt->close();
    exit;
}

// Handle Save Actual Length + OK (masuk stock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ok') {
    $id = intval($_POST['id']);
    $actual_length = trim($_POST['actual_length']);
    
    // Update actual length first
    $stmt = $conn->prepare("UPDATE slitting_product SET actual_length=?, stock_counted=1, is_completed=1 WHERE id=?");
    $stmt->bind_param("si", $actual_length, $id);
    $stmt->execute();
    $stmt->close();
    
    // NEW: Check if this is cut_into_2, then add stock back to raw material
    $product = $conn->query("SELECT * FROM slitting_product WHERE id=$id")->fetch_assoc();
    
    if($product && $product['cut_type'] === 'cut_into_2' && $product['stock'] > 0) {
        // Get mother coil data
        $mother = $conn->query("SELECT * FROM mother_coil WHERE id={$product['mother_id']}")->fetch_assoc();
        
        if($mother) {
            // Generate lot_no with 'a' suffix for stock
            $stock_lot_no = $product['lot_no'] . 'a';
            
            // Check if already exists - FIXED: Check your actual column names in stock_raw_material table
            $check = $conn->query("SELECT id, length FROM stock_raw_material 
                                  WHERE lot_no='$stock_lot_no' 
                                  AND coil_no='{$product['coil_no']}'");
            
            if($check->num_rows > 0) {
                // Update existing stock
                $existing = $check->fetch_assoc();
                $new_length = $existing['length'] + $product['stock'];
                $conn->query("UPDATE stock_raw_material 
                             SET length=$new_length, updated_at=NOW() 
                             WHERE id={$existing['id']}");
            } else {
                // Insert new stock - FIXED: Removed 'product' column if it doesn't exist
                $stmt = $conn->prepare("INSERT INTO stock_raw_material 
                    (lot_no, coil_no, width, length, status, source_type, source_id, date_in) 
                    VALUES (?, ?, ?, ?, 'IN', 'reslit', ?, NOW())");
                
                $stmt->bind_param(
                    "ssddi",
                    $stock_lot_no,
                    $product['coil_no'],
                    $mother['width'],
                    $product['stock'],
                    $product['mother_id']
                );
                
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    
header("Location: finish_product.php?month=$month&year=$year&success=stock");    exit;
}

// Handle Send to Recoiling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_to_recoiling') {
    $id = intval($_POST['product_id']);
    $actual_length = floatval($_POST['actual_length']);
    
    // Update actual length first
    $conn->query("UPDATE slitting_product SET actual_length='$actual_length' WHERE id=$id");
    
    // Get product data
    $stmt = $conn->prepare("SELECT * FROM slitting_product WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Insert into recoiling_product
        $stmt = $conn->prepare("INSERT INTO recoiling_product 
            (status, product, lot_no, coil_no, roll_no, width, length, actual_length) 
            VALUES ('pending', ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("ssssddd",
            $product['product'],
            $product['lot_no'],
            $product['coil_no'],
            $product['roll_no'],
            $product['width'],
            $product['length'],
            $actual_length
        );
        
        if ($stmt->execute()) {
            // Mark as recoiled in slitting_product
            $conn->query("UPDATE slitting_product SET is_recoiled=1 WHERE id=$id");
            header("Location: finish_product.php?success=recoiling");
            exit;
        }
    }
    header("Location: finish_product.php?error=recoiling_failed");
    exit;
}

// Handle Send to Reslit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_to_reslit') {
    $id = intval($_POST['product_id']);
    
    // Get product data
    $stmt = $conn->prepare("SELECT * FROM slitting_product WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Insert into reslit_product - NO QR PATH needed!
        $stmt = $conn->prepare("INSERT INTO reslit_product 
            (status, product, lot_no, coil_no, roll_no, width, length, date_in) 
            VALUES ('pending', ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->bind_param("ssssdd",
            $product['product'],
            $product['lot_no'],
            $product['coil_no'],
            $product['roll_no'],
            $product['width'],
            $product['length']
        );
        
        if ($stmt->execute()) {
            // Mark as reslitted in slitting_product
            $conn->query("UPDATE slitting_product SET is_reslitted=1 WHERE id=$id");
            header("Location: finish_product.php?success=reslit");
            exit;
        }
    }
    header("Location: finish_product.php?error=reslit_failed");
    exit;
}

$in = $conn->query("SELECT IFNULL(COUNT(*),0) AS total FROM slitting_product 
                    WHERE status='IN' AND is_completed=0 
                    AND (is_recoiled=0 OR is_recoiled IS NULL) 
                    AND (is_reslitted=0 OR is_reslitted IS NULL)
                    AND MONTH(date_in)=$month AND YEAR(date_in)=$year")
                    ->fetch_assoc()['total'];

$stock = $conn->query("SELECT IFNULL(COUNT(*),0) AS total FROM slitting_product 
                       WHERE status='IN' AND stock_counted=1 
                       AND (is_recoiled=0 OR is_recoiled IS NULL) 
                       AND (is_reslitted=0 OR is_reslitted IS NULL)
                       AND MONTH(date_in)=$month AND YEAR(date_in)=$year")
                       ->fetch_assoc()['total'];

$waiting = $conn->query("SELECT IFNULL(COUNT(*),0) AS total FROM slitting_product 
                         WHERE status='WAITING' 
                         AND MONTH(date_out)=$month AND YEAR(date_out)=$year")
                         ->fetch_assoc()['total'];

$deliver = $conn->query("SELECT IFNULL(COUNT(*),0) AS total FROM slitting_product
                         WHERE status='DELIVERED' 
                         AND MONTH(delivered_at)=$month AND YEAR(delivered_at)=$year")
                         ->fetch_assoc()['total'];

// Data listing - Display ikut bulan & tahun dipilih (ikut tarikh ikut status)
$monthYearFilter = "
AND (
    (status='IN' AND date_in IS NOT NULL AND MONTH(date_in)=$month AND YEAR(date_in)=$year)
 OR (status IN ('WAITING','OUT','APPROVED') AND date_out IS NOT NULL AND MONTH(date_out)=$month AND YEAR(date_out)=$year)
 OR (status='DELIVERED' AND delivered_at IS NOT NULL AND MONTH(delivered_at)=$month AND YEAR(delivered_at)=$year)
)";

$sql = "SELECT * FROM slitting_product 
        WHERE (is_recoiled=0 OR is_recoiled IS NULL) 
        AND (is_reslitted=0 OR is_reslitted IS NULL)
        $monthYearFilter
        ORDER BY id ASC";

$result = $conn->query($sql);

// Fetch data for modal if edit is requested
$editData = null;
if(isset($_GET['edit'])){
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM slitting_product WHERE id=$id");
    if($res->num_rows > 0) $editData = $res->fetch_assoc();
}

// ====================== DOWNLOAD CSV ======================
if (isset($_GET['download']) && $_GET['download'] == 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=finish_product_{$month}_{$year}.csv");
    $output = fopen('php://output', 'w');

    // header csv
    fputcsv($output, ['ID','Status','Product','Lot No + Coil No','Roll No','Width','Length','Actual Length','Date Out','Delivered At']);

    // isi data
    $data = $conn->query($sql);
    while($row = $data->fetch_assoc()){
        $statusText = match($row['status']) {
            'IN' => $row['is_completed'] == 0 ? 'IN (Pending)' : 'IN (Stock)',
            'OUT' => 'OUT',
            'WAITING' => 'WAITING QC',
            'DELIVERED' => 'DELIVERED',
            default => $row['status']
        };
        
        // Gabungkan Lot No + Coil No
        $lotCoil = trim($row['lot_no']) . ' ' . trim($row['coil_no']);
        
        fputcsv($output, [
            $row['id'],
            $statusText,
            $row['product'],
            $lotCoil,
            $row['roll_no'],
            $row['width'],
            $row['length'],
            $row['actual_length'] ?? '-',
            $row['date_out'],
            $row['delivered_at']
        ]);
    }
    fclose($output);
    exit;
}
?>
<!doctype html>
<html>
<head>
  <title>Finish Product</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    .pending-row { background-color: #fff3cd; }
    .completed-row { background-color: #d1e7dd; }
    
    /* Alert animations */
    .alert {
        animation: slideIn 0.5s ease;
    }
    
    @keyframes slideIn {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    /* FIX TABLE LAYOUT */
    table {
        table-layout: fixed;
        width: 100%;
    }
    
    table th, table td {
        word-wrap: break-word;
        vertical-align: middle;
    }
    
    /* QR Code column */
    table td img {
        max-width: 60px;
        max-height: 60px;
        display: block;
        margin: 0 auto;
    }
    
    /* Set specific column widths */
    table th:nth-child(1) { width: 50px; }   /* ID */
    table th:nth-child(2) { width: 120px; }  /* Status */
    table th:nth-child(3) { width: 100px; }  /* Product */
    table th:nth-child(4) { width: 120px; }  /* Lot No */
    table th:nth-child(5) { width: 80px; }   /* Roll No */
    table th:nth-child(6) { width: 80px; }   /* Width */
    table th:nth-child(7) { width: 80px; }   /* Length */
    table th:nth-child(8) { width: 100px; }  /* Actual Length */
    table th:nth-child(9) { width: 100px; }  /* Date Out */
    table th:nth-child(10) { width: 100px; } /* Delivered At */
    table th:nth-child(11) { width: 80px; }  /* QR */
    table th:nth-child(12) { width: 150px; } /* Action */
  </style>
</head>
<body class="p-4">
<div class="container">
    <h2>Finish Product</h2>

    <?php if(isset($_GET['scan'])): ?>
  <div class="alert alert-info py-2 my-2">Scan: <?= htmlspecialchars($_GET['scan']) ?></div>
<?php endif; ?>

<form id="scanFormProduct" method="post" action="scan_product_action.php" autocomplete="off">
  <input id="qrInputProduct" type="text" name="qr"
         style="position:fixed; left:-9999px; top:-9999px; width:1px; height:1px; opacity:0;"
         autofocus>
</form>

<script>
  const i = document.getElementById('qrInputProduct');
  const f = document.getElementById('scanFormProduct');

  function focusScanner() {
    const typingNow = ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName);
    const modalOpen = document.getElementById('updateModal') !== null;

    if (!modalOpen && !typingNow) i.focus();
  }

  window.addEventListener('load', focusScanner);
  window.addEventListener('click', focusScanner);
  window.addEventListener('focus', focusScanner);

  let t = null;
  i.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => {
      const v = i.value.trim();
      if (v !== '') f.submit();
    }, 200);
  });

  i.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === 'Tab') {
      e.preventDefault();
      if (i.value.trim() !== '') f.submit();
    }
  });

  // maintain focus every 800ms
  setInterval(focusScanner, 800);
</script>

<script>
  const i = document.getElementById('qrInputProduct');
  const f = document.getElementById('scanFormProduct');

  i.addEventListener('keydown', (e) => {
    if(e.key === 'Enter'){
      e.preventDefault();
      if(i.value.trim() !== '') f.submit();
    }
  });

  setInterval(() => {
  const modalOpen = document.getElementById('updateModal') !== null;

  const typingNow = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName);

  if(!modalOpen && !typingNow && document.activeElement !== i) {
    i.focus();
  }
}, 800);

</script>

    <!-- Success Messages -->
    <?php if(isset($_GET['success'])): ?>
        <?php if($_GET['success'] === 'qc_approved'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Product successfully approved by QC!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif($_GET['success'] === 'stock'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Product successfully added to stock!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif($_GET['success'] === 'recoiling'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Product successfully sent to recoiling!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif($_GET['success'] === 'reslit'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Product successfully sent to reslit!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle-fill"></i> 
            <?php if($_GET['error'] === 'qc_approve_failed'): ?>
                Failed to approve product!
            <?php elseif($_GET['error'] === 'recoiling_failed'): ?>
                Failed to send product to recoiling!
            <?php elseif($_GET['error'] === 'reslit_failed'): ?>
                Failed to send product to reslit!
            <?php else: ?>
                An error occurred!
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Dropdown by month year -->
    <form method="get" class="mb-3 d-flex gap-2 align-items-center">
        <label>Select Month:</label>
        <select name="month" onchange="this.form.submit()" class="form-select w-auto d-inline-block">
            <?php for($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>" <?= ($m==$month)?'selected':'' ?>>
                    <?= date("F", mktime(0,0,0,$m,1)) ?>
                </option>
            <?php endfor; ?>
        </select>

        <label>Select Year:</label>
        <select name="year" onchange="this.form.submit()" class="form-select w-auto d-inline-block">
            <?php for($y=2026; $y<=2030; $y++): ?>
                <option value="<?= $y ?>" <?= ($y==$year)?'selected':'' ?>>
                    <?= $y ?>
                </option>
            <?php endfor; ?>
        </select>
    </form>

    <!-- Button Download -->
    <div class="mb-3">
        <a href="?month=<?= $month ?>&year=<?= $year ?>&download=excel" 
           class="btn btn-success">
            <i class="bi bi-download"></i> Download 
        </a>
    </div>

    <!-- summary cards - Full flow tracking -->
    <div class="d-flex mb-4 gap-2">
        <div class="card flex-fill text-center text-bg-info"><div class="card-body"><h5>IN</h5><h2><?= (int)$in ?></h2></div></div>
        <div class="card flex-fill text-center text-bg-primary"><div class="card-body"><h5>STOCK</h5><h2><?= (int)$stock ?></h2></div></div>
        <div class="card flex-fill text-center text-bg-warning"><div class="card-body"><h5>WAITING</h5><h2><?= (int)$waiting ?></h2></div></div>
        <div class="card flex-fill text-center text-bg-success"><div class="card-body"><h5>DELIVER</h5><h2><?= (int)$deliver ?></h2></div></div>
    </div>

    <!-- Table - Updated columns -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Product</th>
                    <th>Lot No</th>
                    <th>Roll No.</th>
                    <th>Width</th>
                    <th>Length</th>
                    <th>Actual Length</th>
                    <th>Date Out</th>
                    <th>Delivered At</th>
                    <th>QR</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if($result && $result->num_rows>0): while($row=$result->fetch_assoc()): 
                // Determine row styling based on status
                $rowClass = '';
                if($row['status'] == 'IN' && $row['is_completed'] == 0) {
                    $rowClass = 'table-info'; // Light blue for IN (not completed)
                } else if($row['status'] == 'IN' && $row['stock_counted'] == 1) {
                    $rowClass = 'table-primary'; // Blue for STOCK
                } else if($row['status'] == 'OUT') {
                    $rowClass = 'table-danger'; // Red for OUT
                } else if($row['status'] == 'WAITING') {
                    $rowClass = 'table-warning'; // Yellow for WAITING
                } else if($row['status'] == 'DELIVERED') {
                    $rowClass = 'table-success'; // Green for DELIVERED
                }
                
                // Status badge
                $statusBadge = match($row['status']) {
                    'IN' => $row['is_completed'] == 0 ? '<span class="badge bg-info">IN (Pending)</span>' : '<span class="badge bg-primary">IN (Stock)</span>',
                    'OUT' => '<span class="badge bg-danger">OUT</span>',
                    'WAITING' => '<span class="badge bg-warning">WAITING</span>',
                    'DELIVERED' => '<span class="badge bg-success">DELIVERED</span>',
                    default => '<span class="badge bg-secondary">' . $row['status'] . '</span>'
                };
                
                // Gabungkan Lot No + Coil No
                $lotCoil = trim($row['lot_no']) . ' ' . trim($row['coil_no']);
            ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= $row['id'] ?></td>
                    <td><?= $statusBadge ?></td>
                    <td><?= htmlspecialchars($row['product'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($lotCoil) ?></td>
                    <td><?= htmlspecialchars($row['roll_no'] ?? '-') ?></td>
                    <td><?= isset($row['width']) && $row['width'] !== '' && $row['width'] !== null ? number_format($row['width'],1) : '-' ?></td>
                    <td><?= isset($row['length']) && $row['length'] !== '' && $row['length'] !== null ? number_format($row['length']) : '-' ?></td>
                    <td><?= isset($row['actual_length']) && $row['actual_length'] !== '' && $row['actual_length'] !== null ? number_format($row['actual_length']) : '-' ?></td>
                    <td><?= $row['date_out'] ?? '-' ?></td>
                    <td><?= $row['delivered_at'] ?? '-' ?></td>
                    <td>
                        <img src="generate_qr.php?id=<?= $row['id'] ?>&type=slitting" alt="QR">
                    </td>       
                    <td class="text-center">
                        <?php if($row['status'] == 'WAITING'): ?>
                            <span class = " btn-sm disable"><i>Waiting approval</i>
                        </span>
                        <?php elseif($row['status'] == 'IN' && $row['is_completed'] == 0): ?>
                            <!-- Pending - Show Update button only -->
                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-pencil-square"></i> Update
                            </a>
                        <?php elseif($row['status'] == 'IN' && $row['stock_counted'] == 1): ?>
                            <!-- Stock - Show Reslit & Print -->
                            <form method="post" style="display:inline;" onsubmit="return confirm('Send this product for reslit?')">
                                <input type="hidden" name="action" value="send_to_reslit">
                                <input type="hidden" name="product_id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="bi bi-scissors"></i> Reslit
                                </button>
                            </form>
                            <a href="select_customer.php?id=<?= $row['id'] ?>" class="btn btn-success btn-sm">
                                <i class="bi bi-printer"></i> Print
                            </a>
                        <?php else: ?>
                            <!-- Other status - Show Print only -->
                            <a href="select_customer.php?id=<?= $row['id'] ?>" class="btn btn-success btn-sm">
                                <i class="bi bi-printer"></i> Print
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="12">No data found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Update Product -->
    <?php if($editData): ?>
    <div class="modal fade show" id="updateModal" style="display:block;" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Update Product</h5>
            <a href="finish_product.php" class="btn-close btn-close-white"></a>
          </div>
          <div class="modal-body">
            <!-- Product Info Display -->
            <div class="card mb-3 bg-light">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2"><strong> Product:</strong> <?= htmlspecialchars($editData['product']) ?></p>
                            <p class="mb-2"><strong> Lot No:</strong> <?= htmlspecialchars(trim($editData['lot_no']) . ' ' . trim($editData['coil_no'])) ?></p>
                            <p class="mb-0"><strong> Roll No:</strong> <?= htmlspecialchars($editData['roll_no']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><strong> Width:</strong> <?= htmlspecialchars($editData['width'],1) ?> mm</p>
                            <p class="mb-0"><strong> Length:</strong> <?= htmlspecialchars($editData['length']) ?> meter</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="post" id="updateForm">
              <input type="hidden" name="action" value="update_ok">
              <input type="hidden" name="id" value="<?= $editData['id'] ?>">
              
             <div class="mb-4">
                <label class="form-label fw-bold"> Actual Length (meter)</label>

            <div class="input-group input-group-lg">
                <input type="number" step="0" name="actual_length" id="actualLengthInput"
                class="form-control"
                value="<?= htmlspecialchars($editData['actual_length'] ?? '') ?>"
                placeholder="Enter actual length" required>

            <span class="input-group-text" id="lengthWarnIcon" style="display:none;">⚠️</span>
            </div>

            <small class="text-danger" id="lengthWarnText" style="display:none;">
                Actual length is different from expected.
            </small>
            </div>
    
              <div class="row g-2">
                <div class="col-md-6">
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="bi bi-check-circle-fill"></i> OK
                    </button>
                </div>
                <div class="col-md-6">
                    <button type="button" class="btn btn-warning btn-lg w-100" id="recoilingBtn">
                        <i class="bi bi-arrow-repeat"></i> Recoiling
                    </button>
                </div>
              </div>
              
              <div class="alert alert-info mt-3 mb-0">
                  <i class="bi bi-info-circle"></i> 
                  <strong>Choose action:</strong>
                  <ul class="mb-0 mt-2">
                      <li><strong>OK:</strong> Add product to stock</li>
                      <li><strong>Recoiling:</strong> Product need to recoiling</li>
                  </ul>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <a href="finish_product.php" class="btn btn-danger">
                 Cancel
            </a>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    
    <script>
    // ===== POPUP CHECK ACTUAL LENGTH (Expected = Length - 3) =====
const ORIGINAL_LENGTH = <?= floatval($editData['length']) ?>;
const EXPECTED_LENGTH = ORIGINAL_LENGTH - 3;
const TOL = 0.5;

const input = document.getElementById('actualLengthInput');
const icon  = document.getElementById('lengthWarnIcon');
const text  = document.getElementById('lengthWarnText');

function toNum(v){
  const n = parseFloat(v);
  return isNaN(n) ? null : n;
}

function isMismatch(val){
  if(val === null) return false;
  return Math.abs(val - EXPECTED_LENGTH) > TOL;
}

function updateUI(val){
  if(isMismatch(val)){
    icon.style.display = 'inline-flex';
    text.style.display = 'block';
  } else {
    icon.style.display = 'none';
    text.style.display = 'none';
  }
}

function confirmIfMismatch(){
  const val = toNum(input.value);
  if(val === null) return true;

  updateUI(val);

  if(isMismatch(val)){
    const ok = confirm(
      `⚠️ Are you sure the length is ${val.toFixed(0)} meter?\n\nExpected (Length - 3): ${EXPECTED_LENGTH.toFixed(0)} meter`
    );

    if(!ok){
      setTimeout(() => {
        input.focus();
        input.select();
      }, 0);
      return false;
    }
  }
  return true;
}

// show icon while typing (no popup)
input.addEventListener('input', () => {
  updateUI(toNum(input.value));
});

// ✅ popup bila tekan Enter
input.addEventListener('keydown', (e) => {
  if(e.key === 'Enter'){
    e.preventDefault();
    confirmIfMismatch();
  }
});

// ✅ popup bila click OK (submit)
document.getElementById('updateForm').addEventListener('submit', function(e){
  if(!confirmIfMismatch()){
    e.preventDefault();
  }
});

// initial state
updateUI(toNum(input.value));

    // Handle Recoiling button click
    document.getElementById('recoilingBtn').addEventListener('click', function(e) {
        e.preventDefault();
        const actualLength = document.getElementById('actualLengthInput').value;
        
        if(!actualLength || actualLength <= 0) {
            alert('⚠️ Please enter actual length first!');
            document.getElementById('actualLengthInput').focus();
            return;
        }

        // popup check before recoiling
        if(!confirmIfMismatch()) return;

        
        if(confirm('Send this product for recoiling?\n\nActual Length: ' + actualLength + ' meter')) {
            // Create form to submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'send_to_recoiling';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'product_id';
            idInput.value = '<?= $editData['id'] ?>';
            
            const lengthInput = document.createElement('input');
            lengthInput.type = 'hidden';
            lengthInput.name = 'actual_length';
            lengthInput.value = actualLength;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            form.appendChild(lengthInput);
            document.body.appendChild(form);
            form.submit();
        }
    });
    
    // Auto hide alerts after 4 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-success, .alert-danger');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 4000);
    </script>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary mt-3">
        <i class="bi bi-arrow-left"></i> Back

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>   