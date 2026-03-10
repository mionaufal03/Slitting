<?php
include 'config.php';

if(isset($_POST['qrcode'])){
    $qrcode = $_POST['qrcode'];
    $data = explode("|", $qrcode);

    $status = strtoupper($data[0]); // IN / OUT
    $code   = $data[1] ?? '';

    if($status == "IN"){
        $product   = $data[2] ?? '';
        $lot_no    = $data[3] ?? '';
        $nominal   = $data[4] ?? 0;
        $effective = $data[5] ?? 0;
        $length    = $data[6] ?? 0;

        // Insert record baru ke Raw Material (status IN)
        $check = $conn->query("SELECT * FROM raw_material_log WHERE code='$code' AND status='IN'");
        if($check->num_rows == 0){
            $conn->query("INSERT INTO raw_material_log
                         (product, lot_no, code, nominal, effective, length, status, date_in) 
                         VALUES
                         ('$product','$lot_no','$code','$nominal','$effective','$length','IN',NOW())");
            $msg = "Mother coil $code berjaya dimasukkan (IN).";
        }else{
            $msg = "Mother coil $code sudah ada dalam stok!";
        }

    }elseif($status == "OUT"){
        // Cari coil dalam Raw Material
        $check = $conn->query("SELECT * FROM raw_material_log WHERE code='$code' AND status='IN'");
        if($check->num_rows > 0){
            $coil = $check->fetch_assoc();

            // Update Raw Material jadi OUT
            $conn->query("UPDATE raw_material_log 
                          SET status='OUT', date_out=NOW() 
                          WHERE id=".$coil['id']);

            // Masukkan juga ke Finish Product (status jadi IN)
            $conn->query("INSERT INTO finish_product_log
                         (product, lot_no, code, nominal, effective, length, status, date) 
                         VALUES
                         ('".$coil['product']."','".$coil['lot_no']."','".$coil['code']."',
                          '".$coil['nominal']."','".$coil['effective']."','".$coil['length']."',
                          'IN', NOW())");

            $msg = "Mother coil $code berjaya dikeluarkan (OUT) & dimasukkan ke Finish Product (IN).";
        }else{
            $msg = "Mother coil $code tidak dijumpai dalam stok!";
        }
    }else{
        $msg = "QR tidak sah!";
    }

    echo "<script>alert('$msg'); window.location='raw_material.php';</script>";
}
?>
