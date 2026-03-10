<?php
include 'config.php';

if(isset($_GET['id'])){
    $id = (int) $_GET['id'];

    $check = $conn->query("SELECT * FROM mother_coil WHERE id = $id");
    if($check->num_rows > 0){

        $conn->query("DELETE FROM mother_coil WHERE id = $id");

        header("Location: mother_coil.php?success=3");
        exit;
    }else{
        echo "Record not found!";
    }
}else{
    echo "ID not given!";
}
?>