<?php
include 'config.php';

$id = intval($_GET['id'] ?? 0);
$row = $conn->query("SELECT * FROM mother_coil WHERE id=$id")->fetch_assoc();

if (!$row) {
    die("Mother Coil not found");
}

// Gabungkan Lot No + Coil No
$lotCoil = trim($row['lot_no']) . ' ' . trim($row['coil_no']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Mother Coil - <?= htmlspecialchars($row['product']) ?></title>

    <style>
        @media print {
            @page {
                size: 148mm 105mm;   /* ikut label awak */
                margin: 0;
            }

            html, body {
                margin: 0 !important;
                padding: 0 !important;
                width: 148mm;
                height: 105mm;
            }

            /* IMPORTANT: HTML awak guna .container, bukan .sticker-container */
            .container {
                width: 148mm !important;
                height: 105mm !important;
                max-width: none !important;
                margin: 0 !important;
                border: 2px solid #000;
                padding: 10mm !important;   /* boleh adjust kalau terlalu rapat */
                box-sizing: border-box;
                page-break-after: always;
            }

            .no-print {
                display: none !important;
            }
        }

        /* =========================
           SCREEN STYLE (asal)
           ========================= */
        body {
            font-family: Arial, sans-serif;
            padding: 50px;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 10px;
            box-sizing: border-box;
        }

        .header {
            display: flex;
            align-items: center;
            border-bottom: 3px solid #000;
            padding-bottom: 0px;
            margin-bottom: 20px;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin-right: 15px;
        }

        .logo img {
            width: 90%;
            height: 90%;
            object-fit: contain;
        }

        .title {
            font-size: 30px;
            font-weight: bold;
        }

        .content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .info-table {
            flex: 1;
            margin-right: 20px;
        }

        .info-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            border: 1px solid #000;
            padding: 10px;
            font-size: 16px;
        }

        .info-table td:first-child {
            font-weight: bold;
            width: 120px;
            background-color: #f0f0f0;
        }

        .qr-section {
            text-align: center;
            flex-shrink: 0;
        }

        .qr-section img {
            width: 120px;
            height: 120px;
            border: 1px solid #000;
            padding: 5px;
        }

        .print-btn {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #0066cc;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="title">MK SLITTING</div>
        </div>

        <div class="content">
            <div class="info-table">
                <table>
                    <tr>
                        <td>PRODUCT</td>
                        <td><?= htmlspecialchars($row['product']) ?></td>
                    </tr>
                    <tr>
                        <td>LOT NO.</td>
                        <td><?= htmlspecialchars($lotCoil) ?></td>
                    </tr>
                    <tr>
                        <td>GRADE</td>
                        <td><?= htmlspecialchars($row['grade']) ?></td>
                    </tr>
                    <tr>
                        <td>WIDTH</td>
                        <td><?= htmlspecialchars($row['width']) ?> mm</td>
                    </tr>
                    <tr>
                        <td>LENGTH</td>
                        <td><?= htmlspecialchars($row['length']) ?> mtr</td>
                    </tr>
                </table>
            </div>

            <div class="qr-section">
                <img
                    src="generate_qr.php?product=<?= urlencode($row['product']) ?>
                    &lot=<?= urlencode($row['lot_no']) ?>
                    &coil=<?= urlencode($row['coil_no']) ?>
                    &grade=<?=urlencode($row['grade']) ?>
                    &width=<?= urlencode($row['width']) ?>
                    &length=<?= urlencode($row['length']) ?>
                    &type=mother"
                    alt="QR Code">
            </div>
        </div>
    </div>

    <div class="no-print" style="text-align: center;">
        <button class="print-btn" onclick="window.print()">Print</button>
        <button class="print-btn" style="background-color: #666;" onclick="window.close()">Back</button>
    </div>
</body>
</html>
