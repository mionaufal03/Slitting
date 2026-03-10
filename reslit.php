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

$result = $conn->query("
    SELECT 
        r.*,
        COALESCE(NULLIF(s.actual_length, 0), r.length) AS effective_length
    FROM reslit_product r
    LEFT JOIN (
        SELECT sp1.*
        FROM slitting_product sp1
        INNER JOIN (
            SELECT lot_no, coil_no, roll_no, MAX(id) AS max_id
            FROM slitting_product
            GROUP BY lot_no, coil_no, roll_no
        ) sp2
          ON sp1.id = sp2.max_id
    ) s
      ON s.lot_no = r.lot_no
     AND s.coil_no = r.coil_no
     AND s.roll_no = r.roll_no
    ORDER BY r.id ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reslit Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            max-width: 1400px;
        }

        h2 {
            font-weight: 700;
            color: #212529;
            margin-bottom: 30px;
        }

        .status-cards {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .status-card {
            flex: 1;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .status-card:hover {
            transform: translateY(-5px);
        }

        .status-card h5 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .status-card h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            color: white;
        }

        .status-card.pending {
            background: linear-gradient(135deg, #ffc107, #ff9800);
        }

        .status-card.in-progress {
            background: linear-gradient(135deg, #17a2b8, #0d6efd);
        }

        .status-card.completed {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .table-responsive {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
        }

        table thead th {
            background-color: #212529 !important;
            color: white !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            padding: 15px 10px;
            border: none;
            white-space: nowrap;
            text-align: center;
        }

        table tbody td {
            vertical-align: middle;
            padding: 12px 10px;
            text-align: center;
        }

        table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .btn {
            border-radius: 6px;
            font-weight: 500;
            padding: 6px 12px;
            font-size: 0.875rem;
        }

        .modal-header {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
            border-radius: 10px 10px 0 0;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #0d6efd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .download-btn {
            background: #28a745;
            color: white;
            border: none;
        }

        .download-btn:hover {
            background: #218838;
            color: white;
        }

        .slitting-box {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }
        
        .highlight-field {
            background: #fff3cd !important;
            border: 2px solid #ffc107 !important;
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h2><i class="bi bi-scissors"></i> Reslit Product</h2>

    <!-- Success Messages -->
    <?php if(isset($_GET['success'])): ?>
        <?php if($_GET['success'] === 'added'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Product successfully added to reslit list!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif($_GET['success'] === 'started'): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <i class="bi bi-play-circle-fill"></i> Reslit process started!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif($_GET['success'] === 'completed'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Reslit completed! Product added to stock
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Download Button -->
    <div class="mb-3">
        <a href="?download=excel" class="btn download-btn">
            <i class="bi bi-download"></i> Download
        </a>
    </div>

    <!-- Status Cards -->
    <?php
    $pending = $conn->query("SELECT COUNT(*) as count FROM reslit_product WHERE status='pending'")->fetch_assoc()['count'];
    $completed = $conn->query("SELECT COUNT(*) as count FROM reslit_product WHERE status='completed'")->fetch_assoc()['count'];
    ?>
    
    <div class="status-cards">
        <div class="status-card pending">
            <h5>Pending</h5>
            <h2><?= $pending ?></h2>
        </div>
        <div class="status-card completed">
            <h5>Completed</h5>
            <h2><?= $completed ?></h2>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Product</th>
                    <th>Lot No.</th>
                    <th>Roll No.</th>
                    <th>Width</th>
                    <th>Length</th>
                    <th>Actual Length</th>
                    <th>Date In</th>
                    <th>Started At</th>
                    <th>Completed At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= $row['id'] ?></strong></td>
                            <td>
                                <?php if($row['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">PENDING</span>
                                <?php else: ?>
                                    <span class="badge bg-success">COMPLETED</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['product'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['lot_no'] ?? '-') . ' ' . htmlspecialchars($row['coil_no'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['roll_no'] ?? '-') ?></td>
                            <td><?= isset($row['width']) ? number_format($row['width']) : '-' ?></td>
                            <td><?= isset($row['effective_length']) ? number_format($row['effective_length']) : '-' ?></td>
                            <td><?= isset($row['actual_length']) ? number_format($row['actual_length']) : '-' ?></td>
                            <td><?= htmlspecialchars($row['date_in'] ?? '-') ?></td>
                            <td><?= $row['started_at'] ? date('d/m/Y H:i', strtotime($row['started_at'])) : '-' ?></td>
                            <td><?= $row['completed_at'] ? date('d/m/Y H:i', strtotime($row['completed_at'])) : '-' ?></td>
                            <td>
                                <?php if ($row['status'] === 'pending'): ?>
                                    <button class="btn btn-primary btn-sm mb-1" 
                                            onclick="showReslitModal(
                                            <?= (int)$row['id'] ?>,
                                            '<?= htmlspecialchars($row['product'] ?? '', ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($row['lot_no'] ?? '', ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($row['coil_no'] ?? '', ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($row['roll_no'] ?? '', ENT_QUOTES) ?>',
                                            <?= (float)($row['width'] ?? 0) ?>,
                                            <?= (float)($row['effective_length'] ?? ($row['length'] ?? 0)) ?>
                                        )">
                                        <i class="bi bi-play-circle"></i> Reslit Now
                                    </button>
                                    <a href="edit_reslit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm mb-1">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-success">Done</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <?php
                        $rolls = $conn->query("SELECT * FROM reslit_rolls WHERE parent_id = {$row['id']} ORDER BY id ASC");
                        if ($rolls && $rolls->num_rows > 0):
                            while ($roll = $rolls->fetch_assoc()):
                        ?>
                        <tr style="background-color: #f0f9ff;">
                            <td><small>↳ R<?= $roll['id'] ?></small></td>
                            <td>
                                <?php if($roll['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">PENDING</span>
                                <?php else: ?>
                                    <span class="badge bg-success">COMPLETED</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['product'] ?? '-') ?></td>
                            <td>
                                <?= htmlspecialchars($row['lot_no'] ?? '-') ?>
                                <?= $roll['cut_letter'] ? htmlspecialchars($roll['cut_letter']) : '' ?>
                                <?= ' ' . htmlspecialchars($row['coil_no'] ?? '') ?>
                            </td>
                            <td><?= htmlspecialchars($roll['roll_no'] ?? '-') ?></td>
                            <td><?= isset($roll['new_width']) ? number_format($roll['new_width']) : (isset($row['width']) ? number_format($row['width'], 2) : '-') ?></td>
                            <td><?= isset($roll['length']) ? number_format($roll['length']) : '-' ?></td>
                            <td><?= isset($roll['actual_length']) ? number_format($roll['actual_length']) : '-' ?></td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>
                                <span class="badge bg-success">Done</span>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        endif;
                        ?>
                        
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" class="text-center text-muted py-5">No records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Back Button -->
    <a href="index.php" class="btn btn-secondary mt-3">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<!-- Modal: Start Reslit Process - UPDATED WITH NEW WIDTH & ACTUAL LENGTH -->
<div class="modal fade" id="reslitModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-scissors"></i> Start Reslit Process</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="reslit_handler.php" id="reslitForm">
                <input type="hidden" name="action" value="complete_reslit_direct">
                <input type="hidden" name="id" id="reslit_id">
                <input type="hidden" name="cut_type" id="cut_type_value">
                
                <div class="modal-body">
                    <!-- Product Info -->
                    <div class="info-box">
                        <div class="row">
                            <div class="col-6"><strong>Product:</strong> <span id="modal_product">-</span></div>
                            <div class="col-6"><strong>Lot No:</strong> <span id="modal_lot">-</span></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6"><strong>Roll No:</strong> <span id="modal_roll">-</span></div>
                            <div class="col-6"><strong>Width:</strong> <span id="modal_width">-</span> mm</div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6"><strong>Length:</strong> <span id="modal_length">-</span>mtr</div>
                    </div>
                    
                    <!-- Step 1: Cut Type Selection -->
                    <div id="step1" class="mt-4">
                        <h6 class="mb-3"><strong>Select Cut Type</strong></h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="cut_type" id="cutNormal" value="normal" onchange="handleCutTypeChange()">
                            <label class="form-check-label" for="cutNormal">
                                <strong>Normal</strong>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="cut_type" id="cutInto2" value="cut_into_2" onchange="handleCutTypeChange()">
                            <label class="form-check-label" for="cutInto2">
                                <strong>Cut Into 2</strong>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Step 2: Number of Rolls -->
                    <div id="rollCountSection" style="display:none;" class="mb-4">
                        <hr>
                        <h6 class="mb-3"><strong>How many rolls?</strong></h6>
                        <select name="total" id="total" class="form-select w-auto" onchange="generateForm()">
                            <option value="">Select number of rolls</option>
                            <?php for($i=1;$i<=10;$i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> Roll<?= $i>1?'s':'' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <!-- Step 3: Roll Details Form -->
                    <div id="slittingForm"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="submitBtn" style="display:none;">
                        <i class="bi bi-check-circle"></i> Complete Reslit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Complete Reslit -->
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check-circle"></i> Complete Reslit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="reslit_handler.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="complete_reslit">
                    <input type="hidden" name="id" id="complete_id">
                    
                    <div class="info-box">
                        <div class="row">
                            <div class="col-6"><strong>Product:</strong> <span id="display_product">-</span></div>
                            <div class="col-6"><strong>Lot No:</strong> <span id="display_lot">-</span></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Actual Length (meters)</label>
                        <input type="number" name="actual_length" class="form-control" 
                               placeholder="Enter actual length" step="0.01" min="0" required autofocus>
                    </div>
                    
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle"></i> 
                        <small>Product will be added back to stock</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Complete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Complete Individual Roll -->
<div class="modal fade" id="completeRollModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check-circle"></i> Complete Roll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="reslit_handler.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="complete_roll">
                    <input type="hidden" name="roll_id" id="complete_roll_id">
                    
                    <div class="info-box">
                        <strong>Roll No:</strong> <span id="display_roll_no">-</span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Actual Length (meters)</label>
                        <input type="number" name="actual_length" class="form-control" 
                               placeholder="Enter actual length" step="0.01" min="0" required autofocus>
                    </div>
                    
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle"></i> 
                        <small>Roll will be added back to stock</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Complete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let rollCount = 0;
let productData = {};

function showReslitModal(id, product, lot_no, coil_no, roll_no, width, length) {
    productData = {
        id: id,
        product: product,
        lot_no: lot_no,
        coil_no: coil_no,
        roll_no: roll_no,
        width: width,
        length: length
    };
    
    document.getElementById('reslit_id').value = id;
    document.getElementById('modal_product').textContent = product;
    document.getElementById('modal_lot').textContent = lot_no + ' ' + coil_no;
    document.getElementById('modal_roll').textContent = roll_no;
    document.getElementById('modal_width').textContent = width;
    document.getElementById('modal_length').textContent = length;

    // Reset form
    document.getElementById('reslitForm').reset();
    document.getElementById('rollCountSection').style.display = 'none';
    document.getElementById('slittingForm').innerHTML = '';
    document.getElementById('submitBtn').style.display = 'none';
    document.getElementById('cut_type_value').value = '';
    rollCount = 0;
    
    new bootstrap.Modal(document.getElementById('reslitModal')).show();
}

function handleCutTypeChange() {
    const cutType = document.querySelector('input[name="cut_type"]:checked')?.value;
    const rollCountSection = document.getElementById('rollCountSection');
    const slittingForm = document.getElementById('slittingForm');
    const submitBtn = document.getElementById('submitBtn');
    const totalSelect = document.getElementById('total');
    
    document.getElementById('cut_type_value').value = cutType || '';
    
    slittingForm.innerHTML = '';
    submitBtn.style.display = 'none';
    totalSelect.value = '';
    
    if (!cutType) {
        rollCountSection.style.display = 'none';
        return;
    } 

    if(cutType === 'cut_into_2'){
        rollCountSection.style.display = 'none';
        totalSelect.value = '2';
        generateForm();
        return;
    }
        rollCountSection.style.display = 'block';
    }
function generateForm() {
    let total = parseInt(document.getElementById('total').value);
    let container = document.getElementById('slittingForm');
    let submitBtn = document.getElementById('submitBtn');
    const cutType = document.querySelector('input[name="cut_type"]:checked')?.value;
    
    container.innerHTML = "";

    if (!cutType) {
        alert('Please select Cut Type first');
        document.getElementById('total').value = '';
        return;
    }

    if (!total || total <= 0) {
        submitBtn.style.display = 'none';
        return;
    }

    // Start with header
    let formHTML = '<hr><h6 class="mb-3"><strong>Enter Roll Details</strong></h6>';

    for (let i = 1; i <= total; i++) {
        formHTML += `
            <div class="slitting-box">
                <h5>Roll ${i}</h5>
                
                <input type="hidden" name="roll_number[]" value="R${i}">
                
                <div class="mb-2">
                    <label class="form-label">Roll No</label>
                    <input type="text" name="roll_no[]" class="form-control" value="R${i}" readonly style="background:#e9ecef;">
                </div>
                
                <div class="mb-2">
                    <label class="form-label">Cut Letter (Optional)</label>
                    <select name="cut_letter[]" class="form-select" onchange="updateLotDisplay(this, ${i - 1})">
                        <option value="">-- None --</option>
                        <option value="a">a</option>
                        <option value="b">b</option>
                        <option value="c">c</option>
                        <option value="d">d</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label fw-bold">
                        New Width
                    </label>
                    <input type="number" step="0.01" name="new_width[]" class="form-control highlight-field" 
                           placeholder="Enter new width" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Length</label>
                    <input type="number" step="0.01" name="length[]" class="form-control" 
                           placeholder="e.g. 109.5" value="${productData.length}">
                    <small class="text-muted">Original length: ${productData.length} meter</small>
                </div>

                <div class="mb-2">
                    <label class="form-label fw-bold">
                        Actual Length
                    </label>
                    <input type="number" step="0.01" name="actual_length[]" class="form-control highlight-field" 
                           placeholder="Enter actual measured length" required>
                </div>

                <div class="info-box" id="lotDisplay${i - 1}">
                    <strong>Reference Info:</strong><br>
                    ${productData.lot_no} ${productData.coil_no} R${i} | ${productData.width} | ${productData.length}
                </div>
            </div>
        `;
    }
    
    container.innerHTML = formHTML;
    submitBtn.style.display = 'inline-block';
}

function updateLotDisplay(select, rollNum) {
    const cutLetter = select.value;
    const displayDiv = document.getElementById('lotDisplay' + rollNum);
    
    let lotDisplay = productData.lot_no;
    if (cutLetter) {
        lotDisplay = productData.lot_no + cutLetter;
    }
    
    displayDiv.innerHTML = `
        <strong>Reference Info:</strong><br>
        ${lotDisplay} ${productData.coil_no} R${rollNum + 1} | ${productData.width} | ${productData.length}
    `;
}

function showCompleteModal(id, product, lot_no, coil_no) {
    document.getElementById('complete_id').value = id;
    document.getElementById('display_product').textContent = product || '-';
    document.getElementById('display_lot').textContent = (lot_no || '') + ' ' + (coil_no || '');
    new bootstrap.Modal(document.getElementById('completeModal')).show();
}

function showCompleteRollModal(roll_id, roll_no) {
    document.getElementById('complete_roll_id').value = roll_id;
    document.getElementById('display_roll_no').textContent = roll_no || '-';
    new bootstrap.Modal(document.getElementById('completeRollModal')).show();
}

document.getElementById('reslitForm').addEventListener('submit', function(e) {
    const cutType = document.getElementById('cut_type_value').value;
    const total = document.getElementById('total').value;
    
    if (!cutType) {
        e.preventDefault();
        alert('Please select Cut Type');
        return false;
    }
    
    if (!total || total <= 0) {
        e.preventDefault();
        alert('Please select number of rolls');
        return false;
    }
    
    const newWidths = document.querySelectorAll('input[name="new_width[]"]');
    const actualLengths = document.querySelectorAll('input[name="actual_length[]"]');
    
    let allFilled = true;
    newWidths.forEach(input => {
        if (!input.value || input.value <= 0) {
            allFilled = false;
            input.classList.add('is-invalid');
        }
    });
    
    actualLengths.forEach(input => {
        if (!input.value || input.value <= 0) {
            allFilled = false;
            input.classList.add('is-invalid');
        }
    });
    
    if (!allFilled) {
        e.preventDefault();
        alert('⚠️ Please fill all New Width and Actual Length fields!');
        return false;
    }
    
    return confirm('Complete reslit process now? Product will be added back to stock.');
});

setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 4000);
</script>
</body>
</html>