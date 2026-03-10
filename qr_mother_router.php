<?php
include 'config.php';
$id = intval($_GET['id'] ?? 0);
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if (stripos($userAgent, 'Mobile') !== false) {
    // kalau scan guna fon → view detail
    header("Location: view_mother.php?id=$id");
} else {
    // kalau scan guna PC / scanner → auto insert raw material
    header("Location: scan_mother.php?id=$id");
}
exit;
