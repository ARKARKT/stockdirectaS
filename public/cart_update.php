<?php
require_once __DIR__ . '/../inc/cart.php';
require_once __DIR__ . '/../inc/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /public/cart.php'); exit; }
if (empty($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) { die('Token inválido.'); }

if (!empty($_POST['qty']) && is_array($_POST['qty'])) {
    foreach($_POST['qty'] as $id => $q) {
        $id = intval($id); $q = max(0,intval($q));
        if ($q <= 0) remove_cart_item($id);
        else update_cart_item($id,$q);
    }
}
if (!empty($_POST['remove']) && is_array($_POST['remove'])) {
    foreach($_POST['remove'] as $rid) remove_cart_item(intval($rid));
}
header('Location: /public/cart.php'); exit;
