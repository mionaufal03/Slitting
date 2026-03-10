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

/**
 * ✅ Parent list (recoiling_product)
 */
$result = $conn->query("
    SELECT *
    FROM recoiling_product
    ORDER BY
      CASE status
        WHEN 'pending' THEN 1
        WHEN 'completed' THEN 2
        ELSE 3
      END,
      id ASC
");


$childRes = $conn->query("
    SELECT recoiling_id, lot_no, coil_no, roll_no, width, length, actual_length
    FROM slitting_product
    WHERE recoiling_id IS NOT NULL
    ORDER BY recoiling_id ASC, id ASC
");

$children = [];
if ($childRes) {
    while ($c = $childRes->fetch_assoc()) {
        $children[(int)$c['recoiling_id']][] = $c;
    }
}

// kira total pending/completed
$pending = 0;
$completed = 0;

$resPending = $conn->query("SELECT COUNT(*) AS c FROM recoiling_product WHERE status='pending'");
if ($resPending) $pending = (int)($resPending->fetch_assoc()['c'] ?? 0);

$resCompleted = $conn->query("SELECT COUNT(*) AS c FROM recoiling_product WHERE status='completed'");
if ($resCompleted) $completed = (int)($resCompleted->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recoiling Cut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container { max-width: 1400px; }
        h2 { font-weight: 700; color: #212529; margin-bottom: 30px; }

        .status-cards { display:flex; gap:15px; margin-bottom:30px; }
        .status-card { flex:1; border-radius:8px; padding:20px; text-align:center; color:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.1); transition:transform .2s; }
        .status-card:hover { transform: translateY(-5px); }
        .status-card h5 { font-size:1rem; font-weight:600; margin-bottom:10px; text-transform:uppercase; }
        .status-card h2 { font-size:2.5rem; font-weight:700; margin:0; color:#fff; }
        .status-card.pending { background: linear-gradient(135deg, #ffc107, #ff9800); }
        .status-card.completed { background: linear-gradient(135deg, #28a745, #20c997); }

        .table-responsive { background:#fff; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); padding:20px; }
        table thead th { background:#212529 !important; color:#fff !important; font-weight:600; text-transform:uppercase; font-size:.85rem; padding:15px 10px; border:none; white-space:nowrap; text-align:center; }
        table tbody td { vertical-align:middle; padding:12px 10px; text-align:center; }
        table tbody tr:hover { background:#f8f9fa; }
        .badge { padding:6px 12px; font-size:.75rem; font-weight:600; }
        .btn { border-radius:6px; font-weight:500; padding:6px 12px; font-size:.875rem; }
        .btn i { margin-right:4px; }

        .info-box { background:#f0f9ff; border-left:4px solid #0d6efd; border-radius:6px; padding:15px; margin-bottom:20px; }

        .download-btn { background:#28a745; color:#fff; border:none; }
        .download-btn:hover { background:#218838; color:#fff; }

        .roll-box { border:1px solid #ddd; padding:15px; border-radius:8px; margin-bottom:15px; background:#f9f9f9; }
        .defect-input { border:2px solid #dc3545; }
        .defect-input:focus { border-color:#dc3545; box-shadow:0 0 0 .2rem rgba(220,53,69,.25); }

        /* ✅ child row style */
        .child-row td { background: #f8f9fa; }
        .child-indent { display:flex; align-items:center; gap:8px; justify-content:center; }
        .child-indent .icon { font-size: 1.1rem; color:#6c757d; }
    </style>
</head>

<body class="p-4">
<div class="container">
    <h2><i class="bi bi-arrow-repeat"></i> Recoiling Cut</h2>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i>
            <?= ($_GET['success'] === 'completed')
                ? 'Recoiling completed! Product added to stock.'
                : 'Product successfully added to recoiling list!' ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($_GET['error']) ?>
            <?php if(isset($_GET['msg'])): ?>
                <div class="mt-2 small text-muted"><?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <a href="?download=excel" class="btn download-btn">
            <i class="bi bi-download"></i> Download
        </a>
    </div>

    <div class="status-cards">
        <div class="status-card pending">
            <h5>Pending</h5>
            <h2><?= (int)$pending ?></h2>
        </div>
        <div class="status-card completed">
            <h5>Completed</h5>
            <h2><?= (int)$completed ?></h2>
        </div>
    </div>

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
                    <th>New Length</th>
                    <th>Date In</th>
                    <th>Completed At</th>
                    <th>Remark</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                      $rid = (int)$row['id'];
                      $isPending = (($row['status'] ?? '') === 'pending');
                      $kids = $children[$rid] ?? [];
                    ?>

                    <!-- ===== PARENT ROW ===== -->
                    <tr>
                      <td><strong><?= $rid ?></strong></td>

                      <td>
                        <?php if ($isPending): ?>
                          <span class="badge bg-warning text-dark">PENDING</span>
                        <?php else: ?>
                          <span class="badge bg-success">COMPLETED</span>
                        <?php endif; ?>
                      </td>

                      <td><?= htmlspecialchars($row['product'] ?? '-') ?></td>
                      <td><?= htmlspecialchars((($kids[0]['lot_no'] ?? $row['lot_no'] ?? '-') . ' ' . ($kids[0]['coil_no'] ?? $row['coil_no'] ?? ''))) ?></td>
                      <td>
                        <strong><?= htmlspecialchars($kids[0]['roll_no'] ?? ($row['roll_no'] ?? '-')) ?></strong>
                      </td>

                      <td><?= isset($row['width']) ? number_format((float)$row['width']) : '-' ?></td>
                      <td><?= isset($row['actual_length']) ? number_format((float)$row['actual_length']) : '-' ?></td>

                      <td>
                        <?php
                          $nl = isset($kids[0]['actual_length']) ? (float)$kids[0]['actual_length'] : (float)($row['new_length'] ?? 0);
                          echo $nl > 0 ? '<strong style="color:#28a745;">' . number_format($nl) . '</strong>' : '-';
                        ?>
                      </td>

                      <td><?= htmlspecialchars($row['date_in'] ?? '-') ?></td>
                      <td><?= !empty($row['completed_at']) ? date('d/m/Y H:i', strtotime($row['completed_at'])) : '-' ?></td>

                      <td>
                        <span style="font-size:0.85rem; color:#6c757d; font-style:italic;">
                          <?= !empty($row['remark']) ? htmlspecialchars($row['remark']) : '-' ?>
                        </span>
                      </td>

                      <td>
                        <?php if ($isPending): ?>
                          <button class="btn btn-primary btn-sm mb-1"
                            onclick='showRecoilingModal(
                              <?= (int)$row["id"] ?>,
                              <?= json_encode($row["product"] ?? "") ?>,
                              <?= json_encode($row["lot_no"] ?? "") ?>,
                              <?= json_encode($row["coil_no"] ?? "") ?>,
                              <?= json_encode($row["roll_no"] ?? "") ?>,
                              <?= json_encode((float)($row["width"] ?? 0)) ?>,
                              <?= json_encode((float)($row["actual_length"] ?? 0)) ?>
                            )'>
                            <i class="bi bi-play-circle"></i> Recoiling Now
                          </button>
                        <?php else: ?>
                          <span class="badge bg-success">Done</span>
                        <?php endif; ?>
                      </td>
                    </tr>

                    <!-- ===== CHILD ROWS ===== -->
                    <?php if (count($kids) > 1): ?>
                      <?php for ($i = 1; $i < count($kids); $i++): ?>
                        <tr class="child-row">
                          <td>
                            <div class="child-indent">
                              <span class="icon"><i class="bi bi-arrow-return-right"></i></span>
                            </div>
                          </td>

                          <td>
                            <?php if ($isPending): ?>
                              <span class="badge bg-warning text-dark">PENDING</span>
                            <?php else: ?>
                              <span class="badge bg-success">COMPLETED</span>
                            <?php endif; ?>
                          </td>

                          <td><?= htmlspecialchars($row['product'] ?? '-') ?></td>
                          <td><?= htmlspecialchars(($kids[$i]['lot_no'] ?? '-') . ' ' . ($kids[$i]['coil_no'] ?? ($row['coil_no'] ?? ''))) ?></td>                          <td><strong><?= htmlspecialchars($kids[$i]['roll_no'] ?? '-') ?></strong></td>
                          <td><?= isset($kids[$i]['width']) ? number_format((float)$kids[$i]['width']) : '-' ?></td>
                          <td><?= isset($kids[$i]['length']) ? number_format((float)$kids[$i]['length']) : '-' ?></td>

                          <td>
                            <?php
                              $childLen = isset($kids[$i]['actual_length']) ? (float)$kids[$i]['actual_length'] : 0;
                              echo $childLen > 0 ? '<strong style="color:#28a745;">' . number_format($childLen) . '</strong>' : '-';
                            ?>
                          </td>

                          <!-- ✅ tak ulang -->
                          <td>-</td>
                          <td>-</td>

                          <td><span style="font-size:0.85rem; color:#6c757d; font-style:italic;">-</span></td>
                          <td><span class="badge bg-success">Done</span></td>
                        </tr>
                      <?php endfor; ?>
                    <?php endif; ?>

                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="12" class="text-center text-muted py-5">No records found.</td></tr>
            <?php endif; ?>
            </tbody>

        </table>
    </div>

    <a href="index.php" class="btn btn-secondary mt-3">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<!-- Modal (kekalkan modal + JS awak yang sedia ada) -->
<div class="modal fade" id="recoilingModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Start Recoiling Process</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="post" action="recoiling_handler.php" id="recoilingForm">
        <input type="hidden" name="action" value="start_and_complete_recoiling">
        <input type="hidden" name="id" id="recoil_id">

        <div class="modal-body">
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
              <div class="col-6"><strong>Original Length:</strong> <span id="modal_length">-</span> m</div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label"><strong>Step 1: Select Cut Type</strong></label>

            <div class="form-check">
              <input class="form-check-input" type="radio" name="cut_type" id="cutNormal" value="normal" onchange="handleCutTypeChange()">
              <label class="form-check-label" for="cutNormal">Normal</label>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="radio" name="cut_type" id="cutInto2" value="cut_into_2" onchange="handleCutTypeChange()">
              <label class="form-check-label" for="cutInto2">Cut Into 2</label>
            </div>
          </div>

          <div id="rollDetailsForm" style="display:none;">
            <hr>
            <h6 class="mb-3"><strong>Step 2: Enter Details</strong></h6>
            <div id="rollsContainer"></div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success" id="submitBtn" style="display:none;">
            <i class="bi bi-check-circle"></i> Complete Recoiling
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- ✅ Paste balik JS awak yang sedia ada di sini (yang modal + form validation) -->
<!-- Saya tak ubah JS sebab code awak panjang & dah ok -->
</body>
</html>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let productData = {};

function showRecoilingModal(id, product, lot_no, coil_no, roll_no, width, length) {
  productData = {
    id,
    product,
    lot_no,
    coil_no,
    roll_no,
    width: parseFloat(width),
    length: parseFloat(length)
  };

  document.getElementById('recoil_id').value = id;
  document.getElementById('modal_product').textContent = product;
  document.getElementById('modal_lot').textContent = lot_no + ' ' + coil_no;
  document.getElementById('modal_roll').textContent = roll_no;
  document.getElementById('modal_width').textContent = width;
  document.getElementById('modal_length').textContent = length;

  document.getElementById('cutNormal').checked = false;
  document.getElementById('cutInto2').checked = false;

  document.getElementById('rollsContainer').innerHTML = '';
  document.getElementById('rollDetailsForm').style.display = 'none';
  document.getElementById('submitBtn').style.display = 'none';

  new bootstrap.Modal(document.getElementById('recoilingModal')).show();
}

function letterOptionsHTML(){
  return `
    <option value="">-- None --</option>
    <option value="a">a</option>
    <option value="b">b</option>
    <option value="c">c</option>
    <option value="d">d</option>
  `;
}

function handleCutTypeChange(){
  const selected = document.querySelector('input[name="cut_type"]:checked');
  if(!selected) return;

  const type = selected.value;
  const container = document.getElementById('rollsContainer');
  container.innerHTML = '';

  document.getElementById('rollDetailsForm').style.display = 'block';
  document.getElementById('submitBtn').style.display = 'inline-block';

  if(type === 'normal'){
    container.appendChild(buildNormalForm());
    updateNormalActual();
  } else {
    container.appendChild(buildCutInto2FormA());
    container.appendChild(buildCutInto2FormB());
    updateCutInto2ActualB();
  }
}

/* ===== NORMAL ===== */
function buildNormalForm(){
  const div = document.createElement('div');
  div.className = 'roll-box';
  div.id = 'normalBox';

  div.innerHTML = `
    <input type="hidden" name="new_width[]" value="${productData.width}">
    <input type="hidden" name="roll_number[]" value="1">
    <input type="hidden" name="length[]" value="${productData.length}">

    <div class="mb-3">
      <span class="badge bg-secondary">Roll: ${productData.roll_no}</span>
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold">Cut Letter (optional)</label>
      <select class="form-select" name="letter[]">
        ${letterOptionsHTML()}
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Defect (meter)</label>
      <input type="number" step="0.01" name="defect[]" id="normal_defect" class="form-control defect-input" value="0">
    </div>

    <div class="mb-3">
      <label class="form-label">Remark</label>
      <input type="text" name="remark[]" class="form-control" placeholder="Defect type / note...">
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold text-success">
        <i class="bi bi-check-circle"></i> Actual Length (meter)
      </label>
      <input type="number" step="0.01" name="actual_length[]" id="normal_actual" class="form-control">
      <small class="text-muted">Auto: Original Length - Defect</small>
    </div>
  `;

  setTimeout(() => {
    const def = document.getElementById('normal_defect');
    const act = document.getElementById('normal_actual');

    act.dataset.manual = "0";
    def.addEventListener('input', () => { act.dataset.manual = "0"; updateNormalActual(); });
    act.addEventListener('input', () => { act.dataset.manual = "1"; });

    updateNormalActual();
  }, 0);

  return div;
}

function updateNormalActual(){
  const def = parseFloat(document.getElementById('normal_defect')?.value) || 0;
  const act = document.getElementById('normal_actual');
  if(!act) return;

  const autoVal = (productData.length || 0) - def;
  if(act.dataset.manual !== "1"){
    act.value = (autoVal >= 0 ? autoVal : 0).toFixed(2);
  }
}

/* ===== CUT INTO 2 =====*/
function buildCutInto2FormA(){
  const div = document.createElement('div');
  div.className = 'roll-box';
  div.id = 'cutA';

  div.innerHTML = `
    <input type="hidden" name="new_width[]" value="${productData.width}">
    <input type="hidden" name="roll_number[]" value="1">
    <input type="hidden" name="length[]" value="${productData.length}">

    <div class="mb-3">
      <span class="badge bg-secondary">Roll: ${productData.roll_no}</span>
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold">Cut Letter (optional)</label>
      <select class="form-select" name="letter[]">
        ${letterOptionsHTML()}
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Defect (meter)</label>
      <input type="number" step="0.01" name="defect[]" id="defectA" class="form-control defect-input" value="0">
    </div>

    <div class="mb-3">
      <label class="form-label">Remark</label>
      <input type="text" name="remark[]" class="form-control" placeholder="Defect note...">
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold text-success">
        <i class="bi bi-check-circle"></i> Actual Length Row 1 (meter)
      </label>
      <input type="number" step="0.01" name="actual_length[]" id="actualA" class="form-control">
    </div>
  `;

  setTimeout(() => {
    document.getElementById('defectA').addEventListener('input', updateCutInto2ActualB);
    document.getElementById('actualA').addEventListener('input', updateCutInto2ActualB);
  }, 0);

  return div;
}

function buildCutInto2FormB(){
  const div = document.createElement('div');
  div.className = 'roll-box';
  div.id = 'cutB';

  div.innerHTML = `
    <input type="hidden" name="new_width[]" value="${productData.width}">
    <input type="hidden" name="roll_number[]" value="1">
    <input type="hidden" name="length[]" value="${productData.length}">
    <input type="hidden" name="defect[]" value="0">

    <div class="mb-3">
      <span class="badge bg-secondary">Roll: ${productData.roll_no}</span>
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold">Cut Letter (optional)</label>
      <select class="form-select" name="letter[]">
        ${letterOptionsHTML()}
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Defect</label>
      <input type="text" class="form-control" value="-" readonly>
    </div>

    <div class="mb-3">
      <label class="form-label">Remark</label>
      <input type="text" name="remark[]" class="form-control" placeholder="Optional...">
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold text-success">
        <i class="bi bi-check-circle"></i> Actual Length Row 2 (meter)
      </label>
      <input type="number" step="0.01" name="actual_length[]" id="actualB" class="form-control">
    </div>
  `;

  setTimeout(() => {
    const b = document.getElementById('actualB');
    b.dataset.manual = "0";
    b.addEventListener('input', () => { b.dataset.manual = "1"; });
  }, 0);

  return div;
}

function updateCutInto2ActualB(){
  const original = productData.length || 0;
  const defectA = parseFloat(document.getElementById('defectA')?.value) || 0;
  const actualA = parseFloat(document.getElementById('actualA')?.value) || 0;

  const actualBInput = document.getElementById('actualB');
  if(!actualBInput) return;

  const computedB = original - defectA - actualA;

  if(actualBInput.dataset.manual !== "1"){
    actualBInput.value = (computedB >= 0 ? computedB : 0).toFixed(2);
  }
}

/* ===== SUBMIT: confirm & ensure really submit ===== */
document.getElementById('recoilingForm').addEventListener('submit', function(e){
  e.preventDefault();

  const selected = document.querySelector('input[name="cut_type"]:checked');
  if(!selected){
    alert('Sila pilih Cut Type!');
    return;
  }

  const actuals = document.querySelectorAll('#rollsContainer input[name="actual_length[]"]');
  for(const a of actuals){
    if(a.value === '' || isNaN(parseFloat(a.value)) || parseFloat(a.value) < 0){
      alert('Sila pastikan Actual Length diisi dengan betul.');
      a.focus();
      return;
    }
  }

  if(selected.value === 'cut_into_2'){
    const original = productData.length || 0;
    const defectA = parseFloat(document.getElementById('defectA')?.value) || 0;
    const actualA = parseFloat(document.getElementById('actualA')?.value) || 0;
    const actualB = parseFloat(document.getElementById('actualB')?.value) || 0;

    const sum = actualA + defectA + actualB;
    if(Math.abs(sum - original) > 0.05){
      alert(`Jumlah tak sama!\n\nActualA + Defect + ActualB mesti = ${original}\nSekarang: ${sum.toFixed(2)}`);
      return;
    }
  }

  if(confirm('Complete recoiling, product will be added to finish product stock.')){
    this.submit();
  }
});
</script>

</body>
</html>