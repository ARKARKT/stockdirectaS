<?php
require_once __DIR__ . '/../../inc/auth.php';
require_role('admin');
require_once __DIR__ . '/../../inc/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}
if (empty($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
    echo 'Token inválido.'; exit;
}
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if (!$id) { echo 'ID inválido.'; exit; }

// delete images files and rows
$stmt = $pdo->prepare('SELECT id,path FROM images WHERE product_id = ?');
$stmt->execute([$id]);
$imgs = $stmt->fetchAll();
foreach($imgs as $im) {
    $full = __DIR__ . '/../../' . ltrim($im['path'],'/');
    if (file_exists($full)) @unlink($full);
    $pdo->prepare('DELETE FROM images WHERE id = ?')->execute([$im['id']]);
}

// remove product folder if exists
$dir = __DIR__ . '/../../uploads/products/' . $id;
if (is_dir($dir)) {
    // remove files
    $files = glob($dir . '/*');
    foreach($files as $f) if (is_file($f)) @unlink($f);
    @rmdir($dir);
}

$pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
header('Location: /public/admin/products.php');
exit;
