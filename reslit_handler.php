<?php
include 'config.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_reslit_direct') {
    $id = intval($_POST['id']);
    $cut_type = trim($_POST['cut_type']);
    $total = intval($_POST['total']);
    
    // Get parent product data
    $parent = $conn->query("SELECT * FROM reslit_product WHERE id=$id")->fetch_assoc();
    
    if (!$parent) {
        die("Parent product not found!");
    }
    
    // 1. Update parent reslit_product to completed
    $stmt = $conn->prepare("UPDATE reslit_product 
                           SET status = 'completed', 
                               cut_type = ?,
                               started_at = NOW(),
                               completed_at = NOW() 
                           WHERE id = ?");
    $stmt->bind_param("si", $cut_type, $id);
    $stmt->execute();
    $stmt->close();
    
    // 2. Process each roll and insert to both reslit_rolls AND slitting_product
    $totalActualLength = 0;
    $first_new_width = 0;
    
    for($i = 0; $i < $total; $i++) {
        $roll_no = $_POST['roll_no'][$i];
        $cut_letter = !empty($_POST['cut_letter'][$i]) ? $_POST['cut_letter'][$i] : '';
        $new_width = floatval($_POST['new_width'][$i]);
        $length = floatval($_POST['length'][$i]);
        $actual_length = floatval($_POST['actual_length'][$i]);
        
        // Save first width for parent record
        if ($i == 0) {
            $first_new_width = $new_width;
        }
        
        // A. Insert roll as completed in reslit_rolls
        $stmt = $conn->prepare("INSERT INTO reslit_rolls 
            (parent_id, roll_no, cut_letter, new_width, length, actual_length, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'completed')");
        $stmt->bind_param("issddd", $id, $roll_no, $cut_letter, $new_width, $length, $actual_length);
        $stmt->execute();
        $roll_db_id = $conn->insert_id;
        $stmt->close();
        
        // Accumulate total actual length
        $totalActualLength += $actual_length;
        
        // B. Build lot_no with cut_letter
        $lot_no_display = $parent['lot_no'];
        if (!empty($cut_letter)) {
            $lot_no_display .= $cut_letter;
        }
        
        // C. Insert to slitting_product (finish product) - ADD BACK TO STOCK
        // PENTING: Guna semua data BARU dari reslit (new_width, length, actual_length)
        $stmt = $conn->prepare("INSERT INTO slitting_product 
                               (product, lot_no, coil_no, roll_no, width, length, actual_length, 
                                status, is_completed, stock_counted) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'IN', 1, 1)");
        
        // width = new_width (BARU dari reslit, BUKAN parent width)
        // length = length (dari form reslit, default ke parent length tapi boleh edit)
        // actual_length = actual_length (BARU yang user isi masa complete reslit)
        $stmt->bind_param("ssssddd",
            $parent['product'],
            $lot_no_display,
            $parent['coil_no'],
            $roll_no,
            $new_width,        // <- NEW WIDTH dari reslit
            $length,           // <- LENGTH dari form (boleh sama atau lain)
            $actual_length     // <- ACTUAL LENGTH yang user measure
        );
        
        $stmt->execute();
        $new_product_id = $stmt->insert_id;
        $stmt->close();
        
        // D. NO NEED TO GENERATE QR CODE FILE!
        // QR will be generated dynamically when needed via generate_qr.php
        error_log("Reslit product $new_product_id created - QR will be generated dynamically");
    }
    
    // 3. Update parent reslit_product with total actual length and new width
    $stmt = $conn->prepare("UPDATE reslit_product 
                           SET actual_length = ?,
                               new_width = ?
                           WHERE id = ?");
    $stmt->bind_param("ddi", $totalActualLength, $first_new_width, $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: reslit.php?success=completed");
    exit;
}

// ========================================
// OLD: Handle Start Reslit Process (KEEP for backward compatibility)
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_reslit') {
    $id = intval($_POST['id']);
    $cut_type = trim($_POST['cut_type']);
    
    // Update reslit_product status to in_progress
    $stmt = $conn->prepare("UPDATE reslit_product 
                           SET status = 'in_progress', 
                               cut_type = ?, 
                               started_at = NOW() 
                           WHERE id = ?");
    $stmt->bind_param("si", $cut_type, $id);
    
    if (!$stmt->execute()) {
        die("Error updating reslit_product: " . $stmt->error);
    }
    $stmt->close();
    
    // Save rolls data
    if (isset($_POST['roll_no']) && is_array($_POST['roll_no'])) {
        $roll_numbers = $_POST['roll_no'];
        $cut_letters = $_POST['cut_letter'] ?? [];
        $lengths = $_POST['length'] ?? [];
        
        for ($i = 0; $i < count($roll_numbers); $i++) {
            $roll_no = $roll_numbers[$i];
            $cut_letter = !empty($cut_letters[$i]) ? $cut_letters[$i] : null;
            $length = !empty($lengths[$i]) ? floatval($lengths[$i]) : null;
            
            $stmt = $conn->prepare("INSERT INTO reslit_rolls 
                                   (parent_id, roll_no, cut_letter, length, status) 
                                   VALUES (?, ?, ?, ?, 'in_progress')");
            $stmt->bind_param("issd", $id, $roll_no, $cut_letter, $length);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    header("Location: reslit.php?success=started");
    exit;
}

// ========================================
// OLD: Handle Complete Reslit (KEEP for backward compatibility)
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_reslit') {
    $id = intval($_POST['id']);
    $actual_length = floatval($_POST['actual_length']);
    
    // Get product data
    $product = $conn->query("SELECT * FROM reslit_product WHERE id = $id")->fetch_assoc();
    
    if (!$product) {
        die("Product not found!");
    }
    
    // Update reslit_product - mark as completed
    $stmt = $conn->prepare("UPDATE reslit_product 
                           SET status = 'completed', 
                               actual_length = ?, 
                               completed_at = NOW() 
                           WHERE id = ?");
    $stmt->bind_param("di", $actual_length, $id);
    
    if (!$stmt->execute()) {
        die("Error completing reslit: " . $stmt->error);
    }
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO slitting_product 
                           (product, lot_no, coil_no, roll_no, width, length, actual_length,
                            status, is_completed, stock_counted) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'IN', 1, 1)");
    
    $stmt->bind_param(
        "ssssddd",
        $product['product'],
        $product['lot_no'],
        $product['coil_no'],
        $product['roll_no'],
        $product['width'],
        $product['length'],
        $actual_length
    );
    
    if (!$stmt->execute()) {
        die("Error inserting to slitting_product: " . $stmt->error);
    }
    $new_product_id = $stmt->insert_id;
    $stmt->close();
    
    error_log("Product $new_product_id created - QR will be generated dynamically");
    
    header("Location: reslit.php?success=completed");
    exit;
}

// ========================================
// OLD: Handle Complete Individual Roll (KEEP for backward compatibility)
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_roll') {
    $roll_id = intval($_POST['roll_id']);
    $actual_length = floatval($_POST['actual_length']);
    
    // Get roll data with parent info
    $roll_query = "SELECT r.*, p.product, p.lot_no, p.coil_no, p.width 
                   FROM reslit_rolls r 
                   JOIN reslit_product p ON r.parent_id = p.id 
                   WHERE r.id = $roll_id";
    $roll = $conn->query($roll_query)->fetch_assoc();
    
    if (!$roll) {
        die("Roll not found!");
    }
    
    // Update roll status
    $stmt = $conn->prepare("UPDATE reslit_rolls 
                           SET status = 'completed', 
                               actual_length = ? 
                           WHERE id = ?");
    $stmt->bind_param("di", $actual_length, $roll_id);
    $stmt->execute();
    $stmt->close();
    
    // Build lot_no with cut_letter if exists
    $lot_no_display = $roll['lot_no'];
    if (!empty($roll['cut_letter'])) {
        $lot_no_display .= $roll['cut_letter'];
    }
    
    // Use new_width if available, otherwise use parent width
    $width_to_use = !empty($roll['new_width']) ? $roll['new_width'] : $roll['width'];
    
    // Insert to slitting_product
    $stmt = $conn->prepare("INSERT INTO slitting_product 
                           (product, lot_no, coil_no, roll_no, width, length, actual_length,
                            status, is_completed, stock_counted) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'IN', 1, 1)");
    
    $stmt->bind_param(
        "ssssddd",
        $roll['product'],
        $lot_no_display,
        $roll['coil_no'],
        $roll['roll_no'],
        $width_to_use,
        $roll['length'],
        $actual_length
    );
    
    $stmt->execute();
    $new_product_id = $stmt->insert_id;
    $stmt->close();
    
    error_log("Roll product $new_product_id created - QR will be generated dynamically");
    
    // Check if all rolls from this parent are completed
    $parent_id = $roll['parent_id'];
    $pending_rolls = $conn->query("SELECT COUNT(*) as count FROM reslit_rolls 
                                   WHERE parent_id = $parent_id AND status != 'completed'")->fetch_assoc()['count'];
    
    // If all rolls completed, update parent status
    if ($pending_rolls == 0) {
        $conn->query("UPDATE reslit_product SET status = 'completed', completed_at = NOW() WHERE id = $parent_id");
    }
    
    header("Location: reslit.php?success=completed");   
    exit;
}

// If no valid action, redirect back
header("Location: reslit.php");
exit;
?>