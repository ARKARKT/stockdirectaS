<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/cart.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /products.php'); exit; }
if (empty($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) { die('Token inválido.'); }
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$qty = isset($_POST['qty']) ? max(1,intval($_POST['qty'])) : 1;
$res = add_to_cart($product_id,$qty);
header('Location: /public/cart.php'); exit;
