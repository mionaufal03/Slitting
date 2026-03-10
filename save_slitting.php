<?php
include 'config.php';
// No need QR code library - QR generated dynamically

// Get POST data
$source_type = trim($_POST['source_type']); // 'mother' or 'stock'
$product     = trim($_POST['product']);
$lot_no      = trim($_POST['lot_no']);
$coil_no     = trim($_POST['coil_no']);
$total       = intval($_POST['total']);
$cut_type    = trim($_POST['cut_type']);

$roll_nos    = $_POST['roll_no'];
$widths      = $_POST['width'];
$lengths     = $_POST['length'];
$cut_letters = $_POST['cut_letter'] ?? [];

// Get cut_into_2 data
$slit_quantity = isset($_POST['slit_quantity']) ? floatval($_POST['slit_quantity']) : null;
$stock         = isset($_POST['stock']) ? floatval($_POST['stock']) : null;

// Determine source
$mother_id = 0;
$stock_id = 0;
$source_data = null;

if($source_type === 'mother') {
    $mother_id = intval($_POST['mother_id']);
    $source_data = $conn->query("SELECT * FROM mother_coil WHERE id=$mother_id")->fetch_assoc();
} else if($source_type === 'stock') {
    $stock_id = intval($_POST['stock_id']);
    $source_data = $conn->query("SELECT * FROM raw_material_log WHERE id=$stock_id")->fetch_assoc();
}

// NO NEED TO CREATE QR CODES DIRECTORY!

// Insert each slitting product
for($i = 0; $i < $total; $i++){
    $roll_no = trim($roll_nos[$i]);
    $width   = trim($widths[$i]);
    $length  = trim($lengths[$i]);
    
    // Handle cut coil letter
    $final_lot_no = $lot_no;    
    if(isset($cut_letters[$i]) && $cut_letters[$i] !== ''){
        $final_lot_no = $lot_no . $cut_letters[$i];
    }

    // Insert into slitting_product
    $stmt = $conn->prepare("INSERT INTO slitting_product 
        (mother_id, product, lot_no, coil_no, roll_no, width, length, cut_type, slit_quantity, stock, status, is_completed, stock_counted) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'IN', 0, 0)");

    $stmt->bind_param(
        "isssssssdd",
        $mother_id,
        $product,
        $final_lot_no,
        $coil_no,
        $roll_no,
        $width,
        $length,
        $cut_type,
        $slit_quantity,
        $stock
    );

    $stmt->execute();
    $product_id = $stmt->insert_id;
    $stmt->close();

    // NO NEED TO GENERATE QR CODE FILE!
    // QR will be generated dynamically when needed via generate_qr.php
    error_log("Slitting product $product_id created - QR will be generated dynamically");
}

// ===================== UPDATE RAW MATERIAL LOG =====================
if($source_data) {
    $action_value = $cut_type; // 'normal' or 'cut_into_2'
    
    if($source_type === 'mother') {
        // Record 1: Update original raw material to OUT (from mother coil)
        $stmt = $conn->prepare("UPDATE raw_material_log 
                               SET status='OUT', 
                                   date_out=NOW(), 
                                   action=? 
                               WHERE product=? 
                               AND lot_no=? 
                               AND coil_no=? 
                               AND status='IN'
                               LIMIT 1");
        
        $stmt->bind_param("ssss", 
            $action_value,
            $source_data['product'], 
            $source_data['lot_no'], 
            $source_data['coil_no']
        );
        
        $stmt->execute();
        $stmt->close();
        
        error_log("=== RAW MATERIAL LOG UPDATE (FROM MOTHER) ===");
        
    } else if($source_type === 'stock') {
        // Update stock after cut to OUT
        $stmt = $conn->prepare("UPDATE raw_material_log 
                               SET status='OUT', 
                                   date_out=NOW(), 
                                   action=? 
                               WHERE id=?");
        
        $stmt->bind_param("si", $action_value, $stock_id);
        $stmt->execute();
        $stmt->close();
        
        error_log("=== RAW MATERIAL LOG UPDATE (FROM STOCK AFTER CUT) ===");
        error_log("Stock ID: " . $stock_id);
    }
    
    error_log("Cut Type: " . $cut_type);
    error_log("Action: " . $action_value);
    error_log("Product: " . $source_data['product']);
    error_log("Lot No: " . $source_data['lot_no']);
    error_log("Coil No: " . $source_data['coil_no']);
    
    // Record 2: If cut_into_2 and has stock, insert NEW raw material (stock leftover)
    if($cut_type === 'cut_into_2' && !empty($stock) && $stock > 0) {
        error_log("Stock Value: " . $stock);
        error_log("Slit Quantity: " . $slit_quantity);
        error_log("Creating new raw material log for stock leftover...");
        
        $remark = ($source_type === 'stock') ? 'Stock leftover from stock after cut' : 'Stock leftover from slitting';
        
        // Insert stock leftover sebagai raw material baru (IN)
        $stmt = $conn->prepare("INSERT INTO raw_material_log 
                               (product, lot_no, coil_no, length, width, status, date_in, action, remark) 
                               VALUES (?, ?, ?, ?, ?, 'IN', NOW(), 'cut_into_2', ?)");
        
        $width_value = isset($source_data['width']) ? $source_data['width'] : 0;
        
        $stmt->bind_param("sssdds",
            $source_data['product'],
            $source_data['lot_no'],
            $source_data['coil_no'],
            $stock,
            $width_value,
            $remark
        );
        
        $stmt->execute();
        $stock_id = $stmt->insert_id;
        $stmt->close();
        
        error_log("Stock leftover inserted with ID: " . $stock_id);
    }
}

header("Location: finish_product.php?success=1");
exit;
?>