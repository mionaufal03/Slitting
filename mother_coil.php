<?php
include 'config.php';

function productFromCoil(mysqli $conn, string $coil_no): string
{
    $coil_no = strtoupper(trim($coil_no));
    if ($coil_no === '') return '';

    preg_match('/^[A-Z0-9]+/', $coil_no, $m);
    $token = $m[0] ?? '';
    if ($token === '') return '';

    for ($len = strlen($token); $len >= 1; $len--) {
        $code = substr($token, 0, $len);

        $stmt = $conn->prepare("SELECT product FROM coil_product_map WHERE coil_code = ? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            return $row['product'] ?? '';
        }
    }
    return '';
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_product') {
    header('Content-Type: application/json; charset=utf-8');

    $coil = $_GET['coil'] ?? '';
    $product = productFromCoil($conn, $coil);

    echo json_encode([
        'ok' => ($product !== ''),
        'product' => $product
    ]);
    exit;
}

session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

if (!in_array($_SESSION['role'], ['slitting','mkl3'], true)) {
    die("Access denied");
}

$success = $_GET['success'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // slitting view only
    if ($_SESSION['role'] === 'slitting') {
        die("Admin view-only. Not allowed to modify.");
    }

    $id      = intval($_POST['id'] ?? 0);
    $lot_no  = trim($_POST['lot_no'] ?? '');
    $coil_no = trim($_POST['coil_no'] ?? '');
    $grade   = trim($_POST['grade'] ?? '');
    $width   = trim($_POST['width'] ?? '');
    $length  = trim($_POST['length'] ?? '');

    if ($action === 'add') {
        $product = productFromCoil($conn, $coil_no);
    } else {
        $product = trim($_POST['product'] ?? '');
    }

    if ($lot_no === '' || $coil_no === '' || $grade === '' || $width === '' || $length === '') {
        die("Field missing");
    }

    if ($product === '') {
        die("Coil not found / product empty");
    }

    if ($action === 'add') {
        $stmt = $conn->prepare("
            INSERT INTO mother_coil (product, grade, lot_no, coil_no, width, length, date_created)
            VALUES (?,?,?,?,?,?,NOW())
        ");
        if (!$stmt) die("Prepare failed: ".$conn->error);

        $stmt->bind_param("ssssss", $product, $grade, $lot_no, $coil_no, $width, $length);
        $stmt->execute();
        $stmt->close();

        header("Location: mother_coil.php?success=1");
        exit;
    }

    if ($action === 'update' && $id > 0) {
        $stmt = $conn->prepare("
            UPDATE mother_coil
            SET product=?, grade=?, lot_no=?, coil_no=?, width=?, length=?
            WHERE id=?
        ");
        if (!$stmt) die("Prepare failed: ".$conn->error);

        $stmt->bind_param("ssssssi", $product, $grade, $lot_no, $coil_no, $width, $length, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: mother_coil.php?success=1");
        exit;
    }
}

$result = $conn->query("SELECT * FROM mother_coil ORDER BY id ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mother Coil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="p-4">
<div class="container">
    <h2 class="mb-4">Mother Coil List</h2>

    <div class="mb-3">
        <?php if ($_SESSION['role'] === 'mkl3'): ?>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addMotherModal">
                Add Mother Coil
            </button>
        <?php endif; ?>
    </div>

    <table class="table table-bordered table-striped align-middle text-center">
        <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Product</th>
            <th>Lot No.</th>
            <th>Coil No.</th>
            <th>Grade</th>
            <th>Width</th>
            <th>Length (mtr)</th>
            <th>Date Created</th>
            <th>QR Code</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): $id = (int)$row['id']; ?>
            <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= htmlspecialchars($row['product'] ?? '', ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($row['lot_no'] ?? '', ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($row['coil_no'] ?? '', ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($row['grade'] ?? '', ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($row['width'] ?? '', ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($row['length'] ?? '', ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($row['date_created'] ?? '', ENT_QUOTES) ?></td>
                <td>
                    <img
                      src="generate_qr.php?product=<?= urlencode($row['product'] ?? '') ?>&lot=<?= urlencode($row['lot_no'] ?? '') ?>&coil=<?= urlencode($row['coil_no'] ?? '') ?>&width=<?= urlencode($row['width'] ?? '') ?>&length=<?= urlencode($row['length'] ?? '') ?>&type=mother"
                      width="70"
                      alt="QR"
                    >
                </td>
                <td>
                <?php if ($_SESSION['role'] === 'mkl3'): ?>
                    <button
                        type="button"
                        class="btn btn-warning btn-sm me-1 editBtn"
                        data-id="<?= (int)$row['id'] ?>"
                        data-product="<?= htmlspecialchars($row['product'] ?? '', ENT_QUOTES) ?>"
                        data-lot_no="<?= htmlspecialchars($row['lot_no'] ?? '', ENT_QUOTES) ?>"
                        data-coil_no="<?= htmlspecialchars($row['coil_no'] ?? '', ENT_QUOTES) ?>"
                        data-grade="<?= htmlspecialchars($row['grade'] ?? '', ENT_QUOTES) ?>"
                        data-width="<?= htmlspecialchars($row['width'] ?? '', ENT_QUOTES) ?>"
                        data-length="<?= htmlspecialchars($row['length'] ?? '', ENT_QUOTES) ?>"
                        data-bs-toggle="modal" data-bs-target="#editMotherModal"
                    >Edit</button>

                    <a href="delete_mother.php?id=<?= $id ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Are you sure?')">Delete</a>

                    <a href="print_mother.php?id=<?= $id ?>"
                       class="btn btn-info btn-sm"
                       target="_blank">Print</a>
                <?php else: ?>
                    <span class="text-muted">View only</span>
                <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>


<!--Modal Add-->
<div class="modal fade" id="addMotherModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="mother_coil.php">
        <input type="hidden" name="action" value="add">

        <div class="modal-header">
          <h5 class="modal-title">Add Mother Coil</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Product (ignore this field)</label>
            <input type="text" id="add_product_display" class="form-control" readonly>
          </div>

          <div class="mb-3">
            <label class="form-label">Lot No</label>
            <input type="text" name="lot_no" id="add_lot_no"
                   class="form-control" required maxlength="6"
                   pattern="^[A-Z0-9]{6}$"
                   title="Lot No must be exactly 6 characters, uppercase letters (A-Z) and numbers only"
                   disabled>
          </div>

          <div class="mb-3">
            <label class="form-label">Coil No</label>
            <input type="text" name="coil_no" id="add_coil_no" class="form-control" required disabled>
          </div>

          <div class="mb-3">
            <label class="form-label">Grade</label>
            <input type="text" name="grade" id="add_grade" class="form-control" required disabled>
          </div>

          <div class="mb-3">
            <label class="form-label">Width</label>
            <input type="number" step="0.01" name="width" id="add_width" class="form-control" required disabled>
          </div>

          <div class="mb-3">
            <label class="form-label">Length (mtr)</label>
            <input type="number" step="0.01" name="length" id="add_length" class="form-control" required disabled>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save</button>
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal Edit -->
<div class="modal fade" id="editMotherModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="mother_coil.php">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">

        <div class="modal-header">
          <h5 class="modal-title">Edit Mother Coil</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Product</label>
            <select name="product" id="edit_product" class="form-control" required>
              <option value="">-- Select Product --</option>
              <?php
              $all = ['DS-3020','DS-3825','DS-4525','DS-5030','DS-8460','GB-6440','GB-6440-S101','GB-7640','HBV-4020','JZ-2520','JZ-2520-2C','JZ-2820','JZ-3020','JZ-4020','KB-6440','LN-2520','L1N2-2520-02','LN-2520-04','LN-3020','MV-4020','PS-8525','RS-3020','RS-3825','RS-3825-04','RS-4020','RS-4525','RS-5030','RS-6040','RS-7050','RU-5040-1','RU-5040-1-S101','TS-2620','TS-3020','TS-3525','TS-4525','TU-2620','TU-2620-C','TU-3020','TU-4020'];
              foreach ($all as $p) {
                  echo '<option value="'.htmlspecialchars($p, ENT_QUOTES).'">'.htmlspecialchars($p, ENT_QUOTES).'</option>';
              }
              ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Lot No</label>
            <input type="text" name="lot_no" id="edit_lot_no"
                   class="form-control" required maxlength="6"
                   pattern="^[A-Z0-9]{6}$"
                   title="Lot No must be exactly 6 characters, uppercase letters (A-Z) and numbers only">
          </div>

          <div class="mb-3">
            <label class="form-label">Coil No</label>
            <input type="text" name="coil_no" id="edit_coil_no" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Grade</label>
            <input type="text" name="grade" id="edit_grade" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Width</label>
            <input type="number" step="0.01" name="width" id="edit_width" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Length (mtr)</label>
            <input type="number" step="0.01" name="length" id="edit_length" class="form-control" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Update</button>
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function validateLotNo(input){
  if(!input) return;
  input.value = input.value.toUpperCase();
  const regex = /^[A-Z0-9]{6}$/;
  if(input.value !== '' && !regex.test(input.value)){
    input.setCustomValidity('Lot No must be exactly 6 characters, only uppercase letters (A-Z) and numbers allowed. Example: ABC123');
  } else {
    input.setCustomValidity('');
  }
}

function enableNextField(currentId, nextId){
  const current = document.getElementById(currentId);
  const next    = document.getElementById(nextId);
  if(!current || !next) return;

  current.addEventListener('input', function(){
    const ok = this.value.trim() !== '';
    if(ok){
      next.disabled = false;
      return;
    }
    if(document.activeElement === next) return;
    next.disabled = true;
    next.value = '';
  });
}

enableNextField('add_lot_no', 'add_coil_no');
enableNextField('add_coil_no', 'add_grade');
enableNextField('add_grade', 'add_width');
enableNextField('add_width', 'add_length');

function setAddProduct(product) {
  const display = document.getElementById('add_product_display');
  if(display) display.value = product || '';
}

document.getElementById('addMotherModal').addEventListener('shown.bs.modal', function () {
  document.getElementById('add_lot_no').disabled = false;
  document.getElementById('add_lot_no').focus();

  setAddProduct('');
  ['add_coil_no','add_grade','add_width','add_length'].forEach(id => {
    const el = document.getElementById(id);
    if(el){
      el.value = '';
      el.disabled = true;
    }
  });
});

const addCoil = document.getElementById('add_coil_no');
if (addCoil) {
  addCoil.addEventListener('blur', async function () {
    const coilValue = (this.value || '').trim();
    if (!coilValue) {
      setAddProduct('');
      return;
    }

    try {
      const res = await fetch('mother_coil.php?ajax=get_product&coil=' + encodeURIComponent(coilValue));
      const data = await res.json();

      if (data.ok && data.product) {
        setAddProduct(data.product);
      } else {
        setAddProduct('');
        alert('Coil code not found in coil_product_map!');
      }
    } catch (e) {
      console.error(e);
      alert('Error calling AJAX product lookup.');
    }
  });
}

const addLot = document.getElementById('add_lot_no');
const editLot = document.getElementById('edit_lot_no');
if(addLot) addLot.addEventListener('input', function(){ validateLotNo(this); });
if(editLot) editLot.addEventListener('input', function(){ validateLotNo(this); });

// Fill Edit Modal
document.querySelectorAll('.editBtn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('edit_id').value       = btn.dataset.id;
    document.getElementById('edit_product').value  = btn.dataset.product || '';
    document.getElementById('edit_lot_no').value   = (btn.dataset.lot_no || '').toUpperCase();
    document.getElementById('edit_coil_no').value  = btn.dataset.coil_no || '';
    document.getElementById('edit_grade').value    = btn.dataset.grade || '';
    document.getElementById('edit_width').value    = btn.dataset.width || '';
    document.getElementById('edit_length').value   = btn.dataset.length || '';

    setTimeout(()=> document.getElementById('edit_lot_no').focus(), 150);
  });
});
</script>

<?php if ($_SESSION['role'] === 'slitting'): ?>
  <div><a href="index.php" class="btn btn-secondary mt-3">← Back</a></div>
<?php else: ?>
  <div><a href="logout.php" class="btn btn-secondary mt-3">Logout</a></div>
<?php endif; ?>

</div>
</body>
</html>
