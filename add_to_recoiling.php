<?php
include 'config.php';

$product_id = intval($_GET['id'] ?? 0);
$actual_length = $_GET['actual_length'] ?? null;

if ($product_id <= 0) {
    die("Invalid product ID");
}

$stmt = $conn->prepare("SELECT * FROM slitting_product WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    die("Product not found");
}

$stmt = $conn->prepare("INSERT INTO recoiling_product 
    (product, lot_no, coil_no, roll_no, width, length, actual_length, status, date_in) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

$stmt->bind_param(
    "sssssss",
    $product['product'],
    $product['lot_no'],
    $product['coil_no'],
    $product['roll_no'],
    $product['width'],
    $product['length'],
    $actual_length
);

$stmt->execute();
$stmt->close();

// Redirect to recoiling list
header("Location: recoiling_cut.php?success=added");
exit;
?>