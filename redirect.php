<?php
$id = $_GET['id'] ?? 0;

// Detect user agent
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

// Kalau dari phone / browser mobile
if(strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false){
    header("Location: view_mother.php?id=".$id);
    exit;
}

// Default (anggap scanner PC)
header("Location: scan_mother.php?id=".$id);
exit;
