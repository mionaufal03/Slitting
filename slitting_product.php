<?php
include 'config.php';
// No need QR code library - QR generated dynamically

// === Handle Update Product ===
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    $coil_no = $_POST['coil_no'];
    $product = $_POST['product'];
    $lot_no  = $_POST['lot_no'];
    $roll_no = $_POST['roll_no'];
    $width   = $_POST['width'];
    $length  = $_POST['length'];

    // === Update Product ===
    if($_POST['action'] === 'update'){
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE slitting_product 
            SET coil_no=?, product=?, lot_no=?, roll_no=?, width=?, length=? 
            WHERE id=?");
        $stmt->bind_param("sssssi", $coil_no, $product, $lot_no, $roll_no, $width, $length, $id);
        $stmt->execute();
        $stmt->close();

        // Update finish_product ikut slit_id
        $stmt2 = $conn->prepare("UPDATE finish_product 
            SET coil_no=?, product=?, lot_no=?, roll_no=?, width=?, length=? 
            WHERE slit_id=?");
        $stmt2->bind_param("ssssssi", $coil_no, $product, $lot_no, $roll_no, $width, $length, $id);
        $stmt2->execute();
        $stmt2->close();

        // NO NEED TO GENERATE QR CODE FILE AFTER UPDATE!
        // QR will be generated dynamically when needed

        header("Location: slitting_product.php?success=update");
        exit;
    }
}

// === Delete Product ===
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM slitting_product WHERE id=$id");
    $conn->query("DELETE FROM finish_product WHERE slit_id=$id");
    // NO NEED TO DELETE QR FILE!
    header("Location: slitting_product.php?success=delete");
    exit;
}

// === Fetch All Products ===
$slitting = $conn->query("SELECT * FROM slitting_product ORDER BY id ASC");
$success = $_GET['success'] ?? null;

// === Fetch Single Product for Edit ===
$editData = null;
if(isset($_GET['edit'])){
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM slitting_product WHERE id=$id");
    if($res->num_rows > 0) $editData = $res->fetch_assoc();
    else die("Product not found!");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Slitting Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="p-4">
<div class="container">
<h2>Slitting Product</h2>

<?php if($success === 'update'): ?>
    <div class="alert alert-success">Product updated successfully!</div>
<?php elseif($success === 'delete'): ?>
    <div class="alert alert-success">Product deleted successfully!</div>
<?php endif; ?>

<table class="table table-bordered table-striped align-middle text-center">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Coil No</th>
            <th>Product</th>
            <th>Lot No</th>
            <th>Roll No</th>
            <th>Width</th>
            <th>Length</th>
            <th>QR Code</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php if($slitting->num_rows>0): while($row=$slitting->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['coil_no'] ?></td>
            <td><?= $row['product'] ?></td>
            <td><?= $row['lot_no'] ?></td>
            <td><?= $row['roll_no'] ?></td>
            <td><?= $row['width'] ?></td>
            <td><?= $row['length'] ?></td>
            <td>
                <!-- Generate QR dynamically - NO FILE CHECKING! -->
                <img src="generate_qr.php?id=<?= $row['id'] ?>&type=slitting" width="80" alt="QR Code">
            </td>
            <td>
                <a href="?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this product?')" class="btn btn-danger btn-sm">Delete</a>
                <a href="print_slitting.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-success btn-sm">Print</a>
            </td>
        </tr>
    <?php endwhile; else: ?>
        <tr><td colspan="9">No data found.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- Modal Edit Product -->
<?php if($editData): ?>
<div class="modal fade show" id="editSlittingModal" style="display:block;" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= $editData['id'] ?>">
      <div class="modal-header">
        <h5 class="modal-title">Edit Slitting Product</h5>
        <a href="slitting_product.php" class="btn-close"></a>
      </div>
      <div class="modal-body">
          <div class="mb-3">
              <label class="form-label">Coil No</label>
              <input type="text" name="coil_no" class="form-control" value="<?= htmlspecialchars($editData['coil_no']) ?>" required>
          </div>
          <div class="mb-3">
              <label class="form-label">Product</label>
              <input type="text" name="product" class="form-control" value="<?= htmlspecialchars($editData['product']) ?>" required>
          </div>
          <div class="mb-3">
              <label class="form-label">Lot No</label>
              <input type="text" name="lot_no" class="form-control" value="<?= htmlspecialchars($editData['lot_no']) ?>" required>
          </div>
          <div class="mb-3">
              <label class="form-label">Roll No</label>
              <input type="text" name="roll_no" class="form-control" value="<?= htmlspecialchars($editData['roll_no']) ?>" required>
          </div>
          <div class="mb-3">
              <label class="form-label">Width</label>
              <input type="text" name="width" class="form-control" value="<?= htmlspecialchars($editData['width']) ?>" required>
          </div>
          <div class="mb-3">
              <label class="form-label">Length</label>
              <input type="text" name="length" class="form-control" value="<?= htmlspecialchars($editData['length']) ?>" required>
          </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Update</button>
        <a href="slitting_product.php" class="btn btn-danger">Cancel</a>
      </div>
    </form>
  </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<a href="index.php" class="btn btn-secondary mt-3">← Back</a>
</div>
</body>
</html>