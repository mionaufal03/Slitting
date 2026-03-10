<?php
include 'config.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $conn->query("DELETE FROM finish_product WHERE slit_id=$id");

    $conn->query("DELETE FROM slitting_product WHERE id=$id");

}

header("Location: slitting_product.php?success=1");
exit;
?>