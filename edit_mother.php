<?php
include 'config.php';

if(!isset($_GET['id'])){
    die("Missing ID");
}

$id = intval($_GET['id']);

$res = $conn->query("SELECT * FROM mother_coil WHERE id=$id");
if($res->num_rows===0) die("Mother coil not found");
$data = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Mother Coil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h2>Edit Mother Coil</h2>
    <form method="post" action="update_mother.php">
        <input type="hidden" name="id" value="<?= $data['id'] ?>">
        <div class="mb-3">
            <label>Product</label>
            <input type="text" name="product" class="form-control" value="<?= htmlspecialchars($data['product']) ?>" required>
        </div>
         <div class="mb-3">
            <label>Lot No</label>
            <input type="text" name="lot_no" class="form-control" value="<?= htmlspecialchars($data['lot_no']) ?>">
        </div>
        <div class="mb-3">
            <label>Coil No</label>
            <input type="text" name="coil_no" class="form-control" value="<?= htmlspecialchars($data['coil_no']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Length (mtr)</label>
            <input type="text" name="length" class="form-control" value="<?= htmlspecialchars($data['length']) ?>">
        </div>
        <div class="mb-3">
            <label>Width</label>
            <input type="text" name="width" class="form-control" value="<?= htmlspecialchars($data['width']) ?>">
        </div>

        <button type="submit" class="btn btn-success">Update</button>
        <a href="mother_coil.php" class="btn btn-danger">Cancel</a>
    </form>
</div>
</body>
</html>