<?php
include 'config.php';
// No need QR code library - QR generated dynamically

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(empty($_POST['id'])){
        die("Invalid request");
    }

    $id        = int($_POST['id']);
    $coil_no   = trim($_POST['coil_no'] ?? '');
    $product   = trim($_POST['product'] ?? '');
    $lot_no    = trim($_POST['lot_no'] ??'');
    $grade     = trim($_POST['grade'] ?? '');
    $width     = trim($_POST['width'] ?? '');
    $length    = trim($_POST['length'] ?? '');

    if($coil_no === '' || $product === '' || $lot_no === '' || $width === '' || $length === ''){
        die("Required fields missing"):
    }

    $grade = ($grade === '') ? null : $grade;

    // Update mother_coil
    $stmt = $conn->prepare("UPDATE mother_coil 
        SET coil_no=?, size=?, product=?, lot_no=?, nominal=?, effective=?, length=? 
        WHERE id=?");
    
    if(!$stmt->execute()){
        die("Prepare failed: ".$conn->error);
    }

    $stmt->bind_param("ssssssi", $coil_no, $product, $lot_no, $grade, $width, $length, $id);

    if(!$stmt->execute()){
        die("SQL Error: ".$stmt->error);
    }
    
    $stmt->close();

    header("Location: mother_coil.php?success=update");
    exit;
}
?>