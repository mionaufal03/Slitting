<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// ONLY slitting can access
if ($_SESSION['role'] !== 'slitting') {
    die("Access denied");
}

include 'config.php';

$from_stock = isset($_GET['stock_id']);
$source_data = null;
$source_type = '';

if ($from_stock) {
    // From Stock After Cut
    $stock_id = intval($_GET['stock_id']);
    $source_data = $conn->query("SELECT * FROM raw_material_log WHERE id=$stock_id AND status='IN' AND action='cut_into_2'")->fetch_assoc();
    $source_type = 'stock';

    if (!$source_data) {
        die("Stock not found or already used.");
    }
} else {
    // From Mother Coil
    $mother_id = intval($_GET['mother_id']);
    $source_data = $conn->query("SELECT * FROM mother_coil WHERE id=$mother_id")->fetch_assoc();
    $source_type = 'mother';

    if (!$source_data) {
        die("Mother coil not found.");
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Slitting Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { padding:20px; }
        .slitting-box { border:1px solid #ddd; padding:15px; border-radius:8px; margin-bottom:15px; background:#f9f9f9; }
        .info-badge { background:#e3f2fd; padding:8px; border-radius:5px; font-size:0.9em; margin-top:10px; }
    </style>
</head>
<body>

<div class="container">
    <h3 class="mb-4">
        <i class="bi bi-scissors"></i> Add Slitting Product
        <?php if($from_stock): ?>
            <span class="badge bg-success">From Stock After Cut</span>
        <?php endif; ?>
    </h3>

    <div class="mb-3 p-3 border rounded bg-light">
        <p class="mb-1"><strong>Product:</strong> <?= htmlspecialchars($source_data['product']) ?></p>
        <p class="mb-1"><strong>Lot No:</strong> <?= htmlspecialchars($source_data['lot_no']) ?></p>
        <p class="mb-1"><strong>Coil No:</strong> <?= htmlspecialchars($source_data['coil_no']) ?></p>
        <p class="mb-0"><strong><?= $from_stock ? 'Available' : 'Original' ?> Length:</strong> <?= htmlspecialchars($source_data['length']) ?> meter</p>
    </div>

    <form method="post" action="save_slitting.php">
        <input type="hidden" name="source_type" value="<?= $source_type ?>">
        <?php if($from_stock): ?>
            <input type="hidden" name="stock_id" value="<?= $source_data['id'] ?>">
        <?php else: ?>
            <input type="hidden" name="mother_id" value="<?= $source_data['id'] ?>">
        <?php endif; ?>

        <input type="hidden" name="product" value="<?= htmlspecialchars($source_data['product']) ?>">
        <input type="hidden" name="lot_no" value="<?= htmlspecialchars($source_data['lot_no']) ?>">
        <input type="hidden" name="coil_no" value="<?= htmlspecialchars($source_data['coil_no']) ?>">
        <input type="hidden" name="original_length" value="<?= htmlspecialchars($source_data['length']) ?>">

        <div class="mb-4">
            <label class="form-label"><strong>Select Cut Type</strong></label>
            <div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="cut_type" id="cutNormal" value="normal" onchange="handleCutTypeChange()" required>
                    <label class="form-check-label" for="cutNormal">Normal</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="cut_type" id="cutInto2" value="cut_into_2" onchange="handleCutTypeChange()" required>
                    <label class="form-check-label" for="cutInto2">Cut Into 2</label>
                </div>
            </div>
        </div>

        <div id="cutInto2Section" style="display:none;" class="mb-4">
            <h5>Slit Information</h5>
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label">Slit Quantity</label>
                    <input type="number" step="0.1" name="slit_quantity" id="slitQuantity" class="form-control"
                           placeholder="Enter quantity to use" oninput="calculateStock()">
                    <small class="text-muted">Enter how many meters use for slit</small>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">Stock</label>
                    <input type="number" step="0.1" name="stock" id="stock" class="form-control"
                           placeholder="Auto calculated" readonly
                           style="background:#e9ecef; font-weight:bold; color:#0d6efd;">
                </div>
            </div>
        </div>

        <!-- Step 3: Number of Rolls -->
        <div id="rollCountSection" style="display:none;" class="mb-4">
            <label class="form-label"><strong>How many rolls?</strong></label>
            <select name="total" id="total" class="form-select w-auto" onchange="generateForm()" required>
                <option value="">Select number of rolls</option>
                <?php for($i=1;$i<=10;$i++): ?>
                    <option value="<?= $i ?>"><?= $i ?> Roll<?= $i>1?'s':'' ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <!-- Step 4: Roll Details Form -->
        <div id="slittingForm"></div>

        <button type="submit" class="btn btn-primary mt-3" id="submitBtn" style="display:none;">
            <i class="bi bi-check-circle"></i> Save
        </button>
        <a href="<?= $from_stock ? 'raw_material.php' : 'index.php' ?>" class="btn btn-danger mt-3">Cancel</a>
    </form>
</div>

<script>
const sourceData = {
    lotNo: '<?= htmlspecialchars($source_data['lot_no']) ?>',
    coilNo: '<?= htmlspecialchars($source_data['coil_no']) ?>',
    originalLength: <?= floatval($source_data['length']) ?>,
    fromStock: <?= $from_stock ? 'true' : 'false' ?>
};

function calculateStock() {
    const slitQty = parseFloat(document.getElementById('slitQuantity').value) || 0;
    const originalLength = sourceData.originalLength;
    const stock = originalLength - slitQty;

    document.getElementById('stock').value = stock >= 0 ? stock.toFixed(2) : 0;

    const stockField = document.getElementById('stock');
    if (stock < 0) {
        stockField.style.color = '#dc3545';
        stockField.style.fontWeight = 'bold';
    } else if (stock === 0) {
        stockField.style.color = '#ffc107';
        stockField.style.fontWeight = 'bold';
    } else {
        stockField.style.color = '#0d6efd';
        stockField.style.fontWeight = 'bold';
    }
}

function handleCutTypeChange(){
    const cutType = document.querySelector('input[name="cut_type"]:checked')?.value;
    const cutInto2Section = document.getElementById('cutInto2Section');
    const rollCountSection = document.getElementById('rollCountSection');
    const stepLabel = document.getElementById('stepLabel');
    const slittingForm = document.getElementById('slittingForm');
    const submitBtn = document.getElementById('submitBtn');
    const totalSelect = document.getElementById('total');

    // Reset form
    slittingForm.innerHTML = '';
    submitBtn.style.display = 'none';
    totalSelect.value = '';

    if (cutType !== 'cut_into_2') {
        document.getElementById('slitQuantity').value = '';
        document.getElementById('stock').value = '';
        document.getElementById('slitQuantity').required = false;
    }

    if (cutType === 'normal') {
        cutInto2Section.style.display = 'none';
        rollCountSection.style.display = 'block';
        stepLabel.textContent = 'Step 2';
    } else if (cutType === 'cut_into_2') {
        cutInto2Section.style.display = 'block';
        rollCountSection.style.display = 'block';
        stepLabel.textContent = 'Step 3';
        document.getElementById('slitQuantity').required = true;
        calculateStock();
    } else {
        cutInto2Section.style.display = 'none';
        rollCountSection.style.display = 'none';
    }
}

function updateLotNoDisplay(rollIndex) {
    const cutSelects = document.querySelectorAll('select[name="cut_letter[]"]');
    const cutLetter = cutSelects[rollIndex]?.value || '';
    const infoBadge = document.getElementById(`infoBadge${rollIndex}`);

    let lotNoDisplay = sourceData.lotNo;
    if (cutLetter) lotNoDisplay = sourceData.lotNo + cutLetter;

    const lengthLabel = sourceData.fromStock ? 'Available length' : 'Original length';
    infoBadge.innerHTML = `
        ${lotNoDisplay} ${sourceData.coilNo}-R${rollIndex + 1} | ${lengthLabel}: ${sourceData.originalLength.toFixed(2)} meter
    `;
}

function generateForm(){
    const total = parseInt(document.getElementById('total').value);
    const container = document.getElementById('slittingForm');
    const submitBtn = document.getElementById('submitBtn');
    const cutType = document.querySelector('input[name="cut_type"]:checked')?.value;

    container.innerHTML = "";

    if (!cutType) {
        alert('Please select Cut Type first');
        document.getElementById('total').value = '';
        return;
    }

    // Validation for Cut Into 2
    if (cutType === 'cut_into_2') {
        const slitQty = parseFloat(document.getElementById('slitQuantity').value) || 0;
        const stock = parseFloat(document.getElementById('stock').value) || 0;

        if (slitQty <= 0) {
            alert('Please enter Slit Quantity first');
            document.getElementById('total').value = '';
            return;
        }
        if (stock < 0) {
            alert('Stock calculation error.');
            document.getElementById('total').value = '';
            return;
        }
    }

    if (total > 0) {
        submitBtn.style.display = 'inline-block';
    } else {
        submitBtn.style.display = 'none';
        return;
    }

    let autoLength = sourceData.originalLength;
    let lengthReadonly = true;

    if (cutType === 'cut_into_2') {
        autoLength = parseFloat(document.getElementById('slitQuantity').value) || sourceData.originalLength;
        lengthReadonly = false; //editable
    }

    const lengthLabel = sourceData.fromStock ? 'Available length' : 'Original length';

    let html = "";
    for (let i = 1; i <= total; i++) {
        html += `
            <div class="slitting-box">
                <h5>Roll ${i}</h5>

                <input type="hidden" name="roll_number[]" value="R${i}">

                <div class="mb-2">
                    <label class="form-label">Roll No</label>
                    <input type="text" name="roll_no[]" class="form-control"
                           value="R${i}" readonly style="background:#e9ecef;">
                </div>

                <div class="mb-2">
                    <label class="form-label">Cut Letter (Optional)</label>
                    <select name="cut_letter[]" class="form-select" onchange="updateLotNoDisplay(${i-1})">
                        <option value="">-- None --</option>
                        <option value="a">a</option>
                        <option value="b">b</option>
                        <option value="c">c</option>
                        <option value="d">d</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Length (meter)</label>
                    <input type="number" step="0.1" name="length[]" class="form-control"
                           value="${autoLength.toFixed(1)}"
                           ${lengthReadonly ? 'readonly style="background:#e9ecef;"' : ''}
                           required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Width (mm)</label>
                    <input type="number" step="0.1" name="width[]" class="form-control" required>
                </div>

                <div class="info-badge" id="infoBadge${i-1}">
                    ${sourceData.lotNo} ${sourceData.coilNo}-R${i} | ${lengthLabel}: ${sourceData.originalLength.toFixed(2)} meter
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
}
</script>

</body>
</html>
