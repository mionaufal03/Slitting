<?php
// save_image.php
// Simpan POSTed base64 image (JSON) ke folder /qrcodes or /sketches
// Letakkan file ini di folder yang sama dan beri permission folder tujuan boleh ditulis.
$data = json_decode(file_get_contents('php://input'), true);

if(!$data || !isset($data['image'])) {
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'error'=>'No image data']);
    exit;
}

$img = $data['image'];
// data:image/png;base64,.... remove prefix
if (preg_match('/^data:image\/png;base64,/', $img)) {
    $img = substr($img, strpos($img, ',') + 1);
}
$img = base64_decode($img);
if($img === false){
    echo json_encode(['success'=>false,'error'=>'Invalid base64']);
    exit;
}

$folder = __DIR__ . '/sketches/';
if(!is_dir($folder)) mkdir($folder, 0777, true);

$filename = 'defect_' . time() . '_' . rand(100,999) . '.png';
$path = $folder . $filename;

file_put_contents($path, $img);

// Return result
header('Content-Type: application/json');
echo json_encode(['success'=>true,'file'=>'sketches/' . $filename]);
exit;
?>
