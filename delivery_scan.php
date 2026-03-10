<?php
include 'config.php';
$slit_id = intval($_GET['id'] ?? 0);
if(!$slit_id) die("Invalid");

$fin = $conn->query("SELECT * FROM finish_product WHERE slit_id=$slit_id ORDER BY id DESC LIMIT 1")->fetch_assoc();
if(!$fin) die("Finish product not found");

$wait = $conn->query("SELECT * FROM waiting_approval WHERE finish_id={$fin['id']} ORDER BY id DESC LIMIT 1")->fetch_assoc();

if($wait && $wait['status']=='APPROVED'){
    // mark delivered
    $del_by = $conn->real_escape_string($_GET['del_by'] ?? 'deliverer'); // or via session
    $stmt = $conn->prepare("UPDATE finish_product SET status='DELIVERED', delivered_by=?, delivered_at=NOW() WHERE id=?");
    $stmt->bind_param("si", $del_by, $fin['id']);
    $stmt->execute();
    $stmt->close();

    $stmt2 = $conn->prepare("UPDATE waiting_approval SET status='DELIVERED' WHERE id=?");
    $stmt2->bind_param("i", $wait['id']);
    $stmt2->execute();
    $stmt2->close();

    echo "<div class='alert alert-success'>Product delivered.</div>";
    echo "<a href='slitting_product.php'>Back</a>";
    exit;
} else {
    echo "<div class='alert alert-warning'>Product not approved by QC.</div>";
    echo "<a href='slitting_product.php'>Back</a>";
    exit;
}
