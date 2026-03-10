<?php
// recoiling_handler.php

session_start();

// Debug file (boleh buang bila dah stable)
file_put_contents('debug_recoiling.txt', print_r($_POST, true));

include 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log debug
error_log("=== RECOILING HANDLER CALLED ===");
error_log("POST DATA: " . print_r($_POST, true));

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'start_and_complete_recoiling'
) {

    error_log("Handler condition passed");

    $id = intval($_POST['id'] ?? 0);

    // ✅ AUTO DETECT TOTAL ROLLS (tak perlu hidden total_rolls)
    $total_rolls = 0;
    if (isset($_POST['actual_length']) && is_array($_POST['actual_length'])) {
        $total_rolls = count($_POST['actual_length']);
    } elseif (isset($_POST['new_width']) && is_array($_POST['new_width'])) {
        $total_rolls = count($_POST['new_width']);
    }

    error_log("ID: $id, Total Rolls(auto): $total_rolls");

    if ($id <= 0) {
        error_log("ERROR: Invalid ID");
        header("Location: recoiling.php?error=invalid_id");
        exit;
    }

    // Get original product data
    $stmt = $conn->prepare("SELECT * FROM recoiling_product WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $original = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$original) {
        error_log("ERROR: Product not found for ID: $id");
        header("Location: recoiling.php?error=not_found");
        exit;
    }

    // Block kalau dah completed (elak double insert)
    if (($original['status'] ?? '') === 'completed') {
        error_log("ERROR: Recoiling already completed for ID: $id");
        header("Location: recoiling.php?error=already_completed&id=$id");
        exit;
    }

    if ($total_rolls <= 0) {
        error_log("ERROR: Invalid rolls detected: $total_rolls");
        header("Location: recoiling.php?error=invalid_rolls");
        exit;
    }

    // ✅ Safety check: kalau cut_into_2, mesti ada sekurang-kurangnya satu letter
    $cut_type = $_POST['cut_type'] ?? '';
    if ($cut_type === 'cut_into_2') {
        $letters = $_POST['letter'] ?? [];
        $hasAnyLetter = false;

        if (is_array($letters)) {
            foreach ($letters as $lv) {
                if (trim((string)$lv) !== '') {
                    $hasAnyLetter = true;
                    break;
                }
            }
        }

        if (!$hasAnyLetter) {
            error_log("ERROR: cut_into_2 but no letter selected");
            header("Location: recoiling.php?error=letter_required_for_cut2");
            exit;
        }
    }

    $conn->begin_transaction();

    try {
        $total_actual_length = 0.0;
        $summary_width = 0.0;
        $all_remarks = [];
        $inserted_ids = [];

        for ($i = 0; $i < $total_rolls; $i++) {

            $new_width     = floatval($_POST['new_width'][$i] ?? 0);
            $length        = floatval($_POST['length'][$i] ?? 0);
            $defect        = floatval($_POST['defect'][$i] ?? 0);

            // ✅ guna actual_length yang user isi/auto dalam form
            $actual_length = floatval($_POST['actual_length'][$i] ?? 0);

            $remark        = trim($_POST['remark'][$i] ?? '');
            $roll_number   = intval($_POST['roll_number'][$i] ?? 1);
            $letter        = trim($_POST['letter'][$i] ?? ''); // optional (a/b/c/d)

            if ($new_width <= 0) {
                throw new Exception("Invalid new_width for row index {$i}");
            }

            if ($actual_length < 0) {
                $actual_length = 0;
            }

            // ✅ REQUIREMENT AWAK:
            // - roll_no dua-dua R1 (ikut roll_number dari form; cut_into_2 form awak memang 1)
            // - letter masuk dekat LOT NO
            $new_roll_no = 'R' . $roll_number; // ✅ no letter here
            $new_lot_no  = $original['lot_no'] . ($letter !== '' ? $letter : '');

            error_log("Processing index $i | Roll=$new_roll_no | Lot=$new_lot_no | Width=$new_width | Length=$length | Defect=$defect | Actual=$actual_length");

            // remark summary (untuk recoiling_product)
            // contoh: LOT001a/R1: Defect 5m - dent
            if (!empty($remark) || $defect > 0 || $letter !== '') {
                $r = "{$new_lot_no}/{$new_roll_no}: ";
                if ($defect > 0) $r .= "Defect {$defect}m";
                if (!empty($remark)) $r .= ($defect > 0 ? " - " : "") . $remark;
                $all_remarks[] = $r;
            }

            // ✅ Insert into slitting_product (finish product stock)
            $insert_stmt = $conn->prepare("
                INSERT INTO slitting_product
                (recoiling_id, mother_id, product, lot_no, coil_no, roll_no, width, length, actual_length, status, is_completed, stock_counted)
                VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, 'IN', 1, 1)
            ");

            $insert_stmt->bind_param(
                "issssddd",
                $id,                   // ✅ recoiling_product.id
                $original['product'],
                $new_lot_no,           // ✅ lot_no ada letter
                $original['coil_no'],
                $new_roll_no,          // ✅ R1
                $new_width,
                $length,
                $actual_length
            );

            if (!$insert_stmt->execute()) {
                throw new Exception("Insert failed ({$new_lot_no} {$new_roll_no}): " . $insert_stmt->error);
            }

            $new_product_id = $insert_stmt->insert_id;
            $inserted_ids[] = $new_product_id;
            error_log("Inserted new slitting_product ID: $new_product_id (lot_no=$new_lot_no, roll_no=$new_roll_no)");
            $insert_stmt->close();

            // accumulate
            $total_actual_length += $actual_length;

            if ($i === 0) {
                $summary_width = $new_width;
            }
        }

        $combined_remark = !empty($all_remarks) ? implode(" | ", $all_remarks) : "";

        error_log("About to update recoiling_product ID: $id");
        error_log("Summary Width: $summary_width, Total Actual Length: $total_actual_length");
        error_log("Combined Remark: $combined_remark");

        // ✅ Update recoiling_product (elak double submit)
        $update_stmt = $conn->prepare("
            UPDATE recoiling_product
            SET status = 'completed',
                completed_at = NOW(),
                started_at = NOW(),
                new_width = ?,
                new_length = ?,
                remark = ?
            WHERE id = ? AND status='pending'
        ");

        $update_stmt->bind_param(
            "ddsi",
            $summary_width,
            $total_actual_length,
            $combined_remark,
            $id
        );

        if (!$update_stmt->execute()) {
            throw new Exception("Update recoiling failed: " . $update_stmt->error);
        }

        if ($update_stmt->affected_rows <= 0) {
            throw new Exception("Recoiling already completed or record not pending.");
        }

        error_log("UPDATE affected rows: " . $update_stmt->affected_rows);
        $update_stmt->close();

        // Verify update
        $verify_stmt = $conn->prepare("SELECT status, completed_at FROM recoiling_product WHERE id = ?");
        $verify_stmt->bind_param("i", $id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result()->fetch_assoc();
        $verify_stmt->close();

        error_log("Status after update: " . ($verify_result['status'] ?? 'NULL'));
        error_log("Completed at: " . ($verify_result['completed_at'] ?? 'NULL'));

        $conn->commit();
        error_log("✅ TRANSACTION COMMITTED SUCCESSFULLY");
        error_log("Inserted product IDs: " . implode(', ', $inserted_ids));

        header("Location: recoiling.php?success=completed&id=$id");
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        error_log("❌ TRANSACTION ROLLED BACK");
        error_log("Error: " . $e->getMessage());

        header("Location: recoiling.php?error=process_failed&msg=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    error_log("Handler NOT triggered - redirecting to recoiling.php");
    header("Location: recoiling.php?error=invalid_request");
    exit;
}