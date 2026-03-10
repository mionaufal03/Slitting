<?php
include 'config.php';

if (!isset($_GET['id'])) {
    die("Product ID required");
}

$id = intval($_GET['id']);

// Fetch product details
$result = $conn->query("SELECT * FROM slitting_product WHERE id=$id");
$product = $result->fetch_assoc();

// ===== PRODUCT -> COLOR MAP =====
$PRODUCT_COLOR = [
  'DS-3020'=>'GREEN','DS-3825'=>'GREEN','DS-4525'=>'GREEN','DS-5030'=>'GREEN','DS-8460'=>'GREEN',
  'GB-6440'=>'YELLOW','GB-6440-S101'=>'YELLOW','KB-6440'=>'YELLOW',
  'RS-3020'=>'BLUE','RS-3825'=>'BLUE','RS-3825-04'=>'BLUE','RS-4020'=>'BLUE','RS-4025'=>'BLUE',
  'RS-4525'=>'BLUE','RS-5030'=>'BLUE','RS-6040'=>'BLUE','RS-7050'=>'BLUE',
  'RU-5040-1'=>'BLUE','RU-5040-1-S101'=>'BLUE','RV-3825'=>'BLUE',

  'JV-3825'=>'WHITE','JZ-2520'=>'WHITE','JZ-2520-2C'=>'WHITE','JZ-2820'=>'WHITE','JZ-3020'=>'WHITE','JZ-4020'=>'WHITE',
  'L1N2-2520-02'=>'WHITE','LN-1715-1'=>'WHITE','LN-2520'=>'WHITE','LN-2520-04'=>'WHITE',
  'LZ-2420'=>'WHITE','LZ-2520'=>'WHITE','MV-4020'=>'WHITE',
  'PS-6020'=>'WHITE','PS-8525'=>'WHITE',
  'TS-2620'=>'WHITE','TS-3020'=>'WHITE','TS-3525'=>'WHITE','TS-4025'=>'WHITE','TS-4525'=>'WHITE','TS-5030'=>'WHITE','TS-9080'=>'WHITE',
  'TU-2620'=>'WHITE','TU-2620-C'=>'WHITE','TU-3020'=>'WHITE','TU-4020'=>'WHITE','YW-2520'=>'WHITE',
];

function stickerBgColor(string $productCode, array $map): string {
  $code = strtoupper(trim($productCode));
  $name = $map[$code] ?? 'WHITE'; // default kalau tak jumpa
  return match($name){
    'GREEN'  => '#129e16',
    'YELLOW' => '#FFFF00',
    'BLUE'   => '#0099ff',
    default  => '#ffffff',
  };
}

$stickerBg = stickerBgColor($product['product'] ?? '', $PRODUCT_COLOR);

if (!$product) {
    die("Product not found");
}

// Gabungkan Lot No + Coil No
$lotCoil = trim($product['lot_no']) . ' ' . trim($product['coil_no']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Print Sticker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 40px 20px;
        }

        .preview-container {
            max-width: 650px;
            margin: 0 auto;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
        }

        .preview-table {
            width: 100%;
            margin-bottom: 30px;
        }

        .preview-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }

        .preview-table td:first-child {
            font-weight: bold;
            width: 130px;
            color: #333;
        }

        .preview-table td:nth-child(2) {
            width: 20px;
            text-align: center;
        }

        .preview-table td:last-child {
            color: #000;
            font-size: 18px;
        }

        .qr-preview {
            position: absolute;
            top: 40px;
            right: 40px;
            text-align: center;
            background: transparent !important;
        }

        .badge-text {
            display: inline-block;
            background: #e3f2fd;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-bottom: 10px;
        }

        .qr-preview img {
            width: 120px;
            height: 120px;
            border: none;
            background: transparent;
            display: block;
            margin: 0 auto;
        }

        .roll-number {
            font-size: 48px;
            font-weight: bold;
            color: #333;
            margin-top: -10px;
        }

        .editable-row {
            background: #f8f9fa;
        }

        .form-control, .form-select {
            font-size: 16px;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 12px 30px;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-print {
            background: #0aa80a;
            color: white;
        }

        .btn-print:hover {
            background: #0aa80a;
        }

        .btn-back {
            background: #757575;
            color: white;
            text-decoration: none;
            display: inline-block;
        }

        .btn-back:hover {
            background: #616161;
            color: white;
        }

       @media print {
    body {
        background: white !important;
        padding: 0 !important;
    }

    .preview-container {
        background: <?= $stickerBg ?> !important;

        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .action-buttons,
    .btn,
    .badge-text {
        display: none !important;
    }
}
    </style>
</head>
<body>

<!-- Preview Container -->
<div class="preview-container" style="background: white;">
<!-- QR Code -->
    <div class="qr-preview">
        <div class="badge-text">INTERNAL USE</div>
        <img src="generate_qr.php?id=<?= $id ?>&type=slitting" alt="QR Code">
        <div class="roll-number"><?= htmlspecialchars($product['roll_no']) ?></div>
    </div>

    <form method="POST" action="print_product.php">
        <input type="hidden" name="id" value="<?= $id ?>">
        
        <table class="preview-table">
            <tr>
                <td>TOMBO No.</td>
                <td>:</td>
                <td><strong><?= htmlspecialchars($product['tombo_no'] ?? '1600 (METAKOTE)') ?></strong></td>
            </tr>
            <tr>
                <td>Grade</td>
                <td>:</td>
                <td><strong><?= htmlspecialchars($product['product']) ?></strong></td>
            </tr>
            <tr>
                <td>Size</td>
                <td>:</td>
                <td><strong><?= number_format($product['width'], 0) ?> mm x <?= number_format($product['actual_length'] ?? $product['length'], 0) ?> Mtr</strong></td>
            </tr>
            <tr>
                <td>Lot No.</td>
                <td>:</td>
                <td><strong><?= htmlspecialchars($lotCoil) ?></strong></td>
            </tr>
            
            <!-- Customer - Editable -->
            <tr class="editable-row">
                <td>Customer</td>
                <td>:</td>
                <td>
                    <select name="customer" class="form-select" required>
                        <option value="">-- Select Customer --</option>
                        <option value="NAE">NICHIAS AUTOPARTS EUROPE (NAE)</option>
                        <option value="NAX">NAX MFG, SA.DE C.V</option>
                        <option value="NCI MFG">NCI MFG., INC.</option>
                        <option value="TAIHO">TAIHO MFG OF TN. INC</option>
                        <option value="NRI">PT NICHIAS ROCKWOOL IND.</option>
                        <option value="ASHUKA">ASHUKA TECHNOLOGIES SDN. BHD.</option>
                        <option value="NIPPON">NTC(NIPPON GASKET)</option>
                        <option value="NTC">NICHIAS THAILAND</option>
                        <option value="SGC">SHANGHAI XINGSHENG</option>
                        <option value="STAMPING">MK STAMPING</option>
                        <option value="YANTAI">NICHIAS (SHANGHAI) AUTOPARTS TRADING</option>
                        <option value="NIP">NICHIAS IND.PRODUCTS PVT. LTD.</option>
                        <option value="NCI 2">NCI 2</option>
                        <option value="STOCK" selected>STOCK</option>
                        <option value="TRIAL">TRIAL</option>
                    </select>
                </td>
            </tr>
            
            <!-- Ref No - Editable -->
            <tr class="editable-row">
                <td>Ref. No.</td>
                <td>:</td>
                <td>
                    <input type="text" name="ref_no" class="form-control" value="STOCK" required>
                </td>
            </tr>
        </table>

        <div class="action-buttons">
            <button type="submit" class="btn-action btn-print">
                <i class="bi bi-printer-fill"></i> Print Sticker
            </button>
            <a href="finish_product.php" class="btn-action btn-back">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>