<?php
include 'config.php';

$product_id = intval($_POST['product_id']);
$roll_no = trim($_POST['roll_no']);
$width = trim($_POST['width']);
$length = trim($_POST['length']);

// Update product dengan final details
$stmt = $conn->prepare("UPDATE slitting_product 
    SET roll_no=?, width=?, length=?, is_completed=1, stock_counted=1, date_completed=NOW() 
    WHERE id=?");
$stmt->bind_param("sssi", $roll_no, $width, $length, $product_id);
$stmt->execute();
$stmt->close();

header("Location: finish_product.php?success=1");
exit;
?>