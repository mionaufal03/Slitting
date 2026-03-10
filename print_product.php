<?php
include 'config.php';

function getCoilPrefix($coil_no) {
    $coil_no = trim((string)$coil_no);
    if ($coil_no === '') return '';

    if (strpos($coil_no, '-') !== false) {
        return strtoupper(trim(explode('-', $coil_no)[0]));
    }

    preg_match('/^[A-Za-z]+/', $coil_no, $m);
    return strtoupper($m[0] ?? '');
}

function lookupCustomerPartByInternalCode($conn, $internal_code) {
    $stmt = $conn->prepare("
        SELECT customer, part_no
        FROM nci_product_mapping
        WHERE internal_code = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $internal_code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}


// ===== GET CUSTOMER & REF NO (Support both GET and POST) =====
$id = null;
$customer = 'STOCK';
$ref_no = 'STOCK';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // From form POST
    $id = intval($_POST['id']);
    $customer = $_POST['customer'] === 'OTHER' ? $_POST['custom_customer'] : $_POST['customer'];
    $ref_no = $_POST['ref_no'];
} else if (isset($_GET['id'])) {
    // From GET (for preview iframe)
    $id = intval($_GET['id']);
    $customer = isset($_GET['customer']) ? $_GET['customer'] : 'STOCK';

    // Handle custom customer
    if ($customer === 'OTHER' && isset($_GET['custom_customer'])) {
        $customer = $_GET['custom_customer'];
    }

    $ref_no = isset($_GET['ref_no']) ? $_GET['ref_no'] : 'STOCK';
} else {
    die("Product ID required");
}

// ===== GET PRODUCT DATA FROM DATABASE WITH std_wgt JOIN =====
$sql = "SELECT sp.*, mc.product as mother_product, sw.std_weight
        FROM slitting_product sp
        LEFT JOIN mother_coil mc ON sp.mother_id = mc.id
        LEFT JOIN std_wgt sw ON sp.product = sw.product_code
        WHERE sp.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Product not found");
}

$product = $result->fetch_assoc();

// ===== AUTO-FETCH NCI MAPPING DATA (for Pattern 4) =====
$nci_data = null;
if ($customer === 'NCI') {
    // Match by BOTH product (grade) AND width (size)
    $product_width = intval($product['width']); // Convert to integer for matching

    $sql_nci = "SELECT * FROM nci_product_mapping 
                WHERE grade = ? 
                AND CAST(REPLACE(size_width, ' mm', '') AS UNSIGNED) = ?
                LIMIT 1";
    $stmt_nci = $conn->prepare($sql_nci);
    $stmt_nci->bind_param("si", $product['product'], $product_width);
    $stmt_nci->execute();
    $result_nci = $stmt_nci->get_result();

    if ($result_nci->num_rows > 0) {
        $nci_data = $result_nci->fetch_assoc();

        // Auto-populate customer and ref_no if not already set
        if ($customer === 'NCI' && $nci_data['customer']) {
            $customer = $nci_data['customer'];
        }
        if ($ref_no === 'STOCK' && $nci_data['part_no']) {
            $ref_no = $nci_data['part_no'];
        }
    }
    $stmt_nci->close();
}

// ===== DETERMINE PATTERN BASED ON CUSTOMER (after auto-populate) =====
$pattern = 'pattern2'; // default

// Define customer groups for each pattern
$pattern1_customers = ['NAE', 'NRI', 'STAMPING'];
$pattern2_customers = ['NAX', 'TAIHO', 'ASHUKA', 'NTC', 'STOCK', 'NCI MFG', 'NIP'];
$pattern3_customers = ['YANTAI'];
$pattern4_customers = ['NCI 2'];

if (in_array($customer, $pattern1_customers)) {
    $pattern = 'pattern1';
} elseif (in_array($customer, $pattern2_customers)) {
    $pattern = 'pattern2';
} elseif (in_array($customer, $pattern3_customers)) {
    $pattern = 'pattern3';
} elseif (in_array($customer, $pattern4_customers)) {
    $pattern = 'pattern4';
}

// ===== PREPARE DATA FOR STICKER =====

// Determine TOMBO No based on product
$tomboNo = "1600 (METAKOTE)";
if (strpos($product['product'], 'MV') !== false) {
    $tomboNo = "1608 (METAFOAM)";
}

// Combine Lot No + Coil No
$lotNo = trim($product['lot_no']) . ' ' . trim($product['coil_no']);

// QR Code URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$currentDir = dirname($_SERVER['PHP_SELF']);
$basePath = rtrim($currentDir, '/');
$qrImageUrl = $protocol . "://" . $host . $basePath . "/generate_qr.php?id=" . $id . "&type=slitting";

// ===== LOAD PATTERN FILE =====
$patternFile = "sticker_patterns/{$pattern}.php";

if (file_exists($patternFile)) {
    include $patternFile;
    $usePattern = true;
} else {
    die("Error: Pattern file '{$pattern}.php' not found. Please create pattern files in sticker_patterns/ folder.");
}

// Check if this is preview mode
$isPreview = isset($_GET['customer']) || isset($_POST['customer']);

// ===== STICKER BACKGROUND COLOR (BASED ON PRODUCT GRADE) =====
$PRODUCT_COLOR = [
    "DS-3020"=>"GREEN",
    "DS-3825"=>"GREEN",
    "DS-4525"=>"GREEN",
    "DS-5030"=>"GREEN",
    "DS-8460"=>"GREEN",

    "GB-6440"=>"YELLOW",
    "GB-6440-S101"=>"YELLOW",
    "KB-6440"=>"YELLOW",

    "JV-3825"=>"WHITE",
    "JZ-2520"=>"WHITE",
    "JZ-2520-2C"=>"WHITE",
    "JZ-2820"=>"WHITE",
    "JZ-3020"=>"WHITE",
    "JZ-4020"=>"WHITE",
    "L1N2-2520-02"=>"WHITE",
    "LN-1715-1"=>"WHITE",
    "LN-2520"=>"WHITE",
    "LN-2520-04"=>"WHITE",
    "LZ-2420"=>"WHITE",
    "LZ-2520"=>"WHITE",
    "MV-4020"=>"WHITE",
    "PS-6020"=>"WHITE",
    "PS-8525"=>"WHITE",
    "TS-2620"=>"WHITE",
    "TS-3020"=>"WHITE",
    "TS-3525"=>"WHITE",
    "TS-4025"=>"WHITE",
    "TS-4525"=>"WHITE",
    "TS-5030"=>"WHITE",
    "TS-9080"=>"WHITE",
    "TU-2620"=>"WHITE",
    "TU-2620-C"=>"WHITE",
    "TU-3020"=>"WHITE",
    "TU-4020"=>"WHITE",
    "YW-2520"=>"WHITE",

    "RS-3020"=>"BLUE",
    "RS-3825"=>"BLUE",
    "RS-3825-04"=>"BLUE",
    "RS-4020"=>"BLUE",
    "RS-4025"=>"BLUE",
    "RS-4525"=>"BLUE",
    "RS-5030"=>"BLUE",
    "RS-6040"=>"BLUE",
    "RS-7050"=>"BLUE",
    "RU-5040-1"=>"BLUE",
    "RU-5040-1-S101"=>"BLUE",
    "RV-3825"=>"BLUE",
];

// normalize product key
$gradeKey   = strtoupper(trim($product['product'] ?? ''));
$colorName  = $PRODUCT_COLOR[$gradeKey] ?? 'WHITE';

// choose soft background (you can adjust)
$BG = [
    'BLUE'   => '#0099ff',
    'GREEN'  => '#129e16',
    'YELLOW' => '#FFFF00',
    'WHITE'  => '#ffffff',
];

$stickerBg = $BG[$colorName] ?? '#ffffff';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Print Sticker - <?= htmlspecialchars($customer) ?></title>
    <style>
        @media print {
            @page {
                size: 120mm 47mm;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }

            .qs24-floating-btn,
            [class*="floating"]{
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
            }

            /* ✅ VERY IMPORTANT: allow background color in print */
            .sticker-bg-wrap{
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            padding: 0;
            background: #f5f5f5;
        }

        @media screen {
            body {
                padding: 20px;
            }
        }

        /* ===== INJECT PATTERN CSS HERE ===== */
        <?php echo $patternCSS; ?>
        /* ✅ FORCE sticker background ikut wrapper color */
        .sticker-bg-wrap .sticker,
        .sticker-bg-wrap .sticker-container,
        .sticker-bg-wrap .sticker-wrap,
        .sticker-bg-wrap .sticker-area,
        .sticker-bg-wrap .label,
        .sticker-bg-wrap .label-container {
        background: transparent !important;
        background-color: transparent !important;
}

/* ✅ Kalau pattern guna table untuk sticker dan table bg putih */
.sticker-bg-wrap table,
.sticker-bg-wrap tr,
.sticker-bg-wrap td,
.sticker-bg-wrap th {
    background: transparent !important;
    background-color: transparent !important;
}


        /* ===== COMMON STYLES ===== */
        .no-print {
            text-align: center;
            margin: 20px 0;
        }

        .info-bar {
            max-width: 120mm;
            margin: 0 auto 10px;
            padding: 10px;
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            border-radius: 4px;
        }

        .info-bar strong {
            color: #1976D2;
        }

        .btn {
            padding: 10px 30px;
            font-size: 16px;
            cursor: pointer;
            margin: 0 10px;
            border: none;
            border-radius: 4px;
        }

        .btn-print {
            background: #4CAF50;
            color: white;
        }

        .btn-back {
            background: #666;
            color: white;
            text-decoration: none;
            display: inline-block;
        }

        /* Hide any floating elements that might appear */
        button[type="button"]:not(.btn),
        .floating-btn,
        iframe {
            display: none !important;
        }

        /* ✅ Sticker background wrapper (does NOT change your pattern design) */
        .sticker-bg-wrap{
            background: <?= $stickerBg ?> !important;
            width: 120mm;
            height: 47mm;
            margin: 0 auto;
            overflow: hidden;
            position: relative;
        }

        @media screen{
            .sticker-bg-wrap{
                box-shadow: 0 2px 10px rgba(0,0,0,0.12);
                border-radius: 6px;
            }
        }

        @media print{
            .sticker-bg-wrap{
                box-shadow: none !important;
                border-radius: 0 !important;
            }
        }
    </style>
</head>
<body>
<?php if ($isPreview): ?>
    <!-- Preview mode - show info bar -->
    <div class="no-print info-bar">
        <strong>Preview Mode</strong> |
        Pattern: <?= ucfirst($pattern) ?> |
        Customer: <?= htmlspecialchars($customer) ?> |
        Ref No: <?= htmlspecialchars($ref_no) ?> |
        Sticker Color: <?= htmlspecialchars($colorName) ?>
    </div>
<?php endif; ?>

<?php
if ($customer === 'NCI 2') {

    $coil_no = $product['coil_no'] ?? ($_POST['coil_no'] ?? '');
    $width   = (int)($product['width'] ?? 0);

    $prefix = getCoilPrefix($coil_no);

    if ($prefix !== '' && $width > 0) {
        $internal_code = $prefix . '-' . $width;

        $row = lookupCustomerPartByInternalCode($conn, $internal_code);

        if ($row) {
            $customer = $row['customer'];
            $ref_no   = $row['part_no'];
        }
    }
}

/* ✅ Wrap sticker with background container */
echo '<div class="sticker-bg-wrap">';
if (function_exists('render_sticker')) {
    echo render_sticker($product, $customer, $ref_no, $tomboNo, $lotNo, $qrImageUrl);
} else {
    echo "<div style='padding:20px; background:red; color:white;'>Error: render_sticker() function not found in pattern file!</div>";
}
echo '</div>';
?>

<div class="no-print">
    <button class="btn btn-print" onclick="window.print()">Print Sticker</button>
    <a href="select_customer.php?id=<?= $id ?>" class="btn btn-back">← Edit Customer/Ref</a>
    <a href="finish_product.php" class="btn btn-back">← Back to List</a>
</div>

</body>
</html>