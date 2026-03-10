<?php
include 'config.php';

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    // Get QR code path before deleting
    $result = $conn->query("SELECT qr_code FROM recoiling_product WHERE id=$id");
    $row = $result->fetch_assoc();
    
    // Delete from database
    $stmt = $conn->prepare("DELETE FROM recoiling_product WHERE id=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Delete QR code file if exists
        if (!empty($row['qr_code']) && file_exists($row['qr_code'])) {
            unlink($row['qr_code']);
        }
        
        $stmt->close();
        header("Location: recoiling.php?success=deleted");
        exit;
    } else {
        $stmt->close();
        header("Location: recoiling.php?error=delete_failed");
        exit;
    }
} else {
    header("Location: recoiling.php?error=invalid_id");
    exit;
}
?>