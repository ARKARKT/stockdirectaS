<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

function get_cart_id() {
    if (empty($_SESSION['cart_id'])) {
        $_SESSION['cart_id'] = session_id();
    }
    return $_SESSION['cart_id'];
}

function get_cart() {
    global $pdo;
    $id = get_cart_id();
    $stmt = $pdo->prepare('SELECT data FROM carts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetchColumn();
    if (!$row) return [];
    $data = json_decode($row, true);
    return $data ?: [];
}

function save_cart(array $cart) {
    global $pdo;
    $id = get_cart_id();
    $json = json_encode($cart, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare('INSERT INTO carts (id,data,created_at,updated_at) VALUES (?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE data = ?, updated_at = NOW()');
    $stmt->execute([$id,$json,$json]);
}

function clear_cart() {
    global $pdo;
    $id = get_cart_id();
    $pdo->prepare('DELETE FROM carts WHERE id = ?')->execute([$id]);
}

function add_to_cart(int $product_id, int $qty = 1) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id,title,price_ars,price_usd,vendor_id FROM products WHERE id = ? AND available = 1 LIMIT 1');
    $stmt->execute([$product_id]);
    $p = $stmt->fetch();
    if (!$p) return ['error' => 'Producto no encontrado o no disponible.'];

    $cart = get_cart();
    // key by product_id
    if (isset($cart[$product_id])) {
        $cart[$product_id]['qty'] += $qty;
    } else {
        $cart[$product_id] = [
            'product_id' => $p['id'],
            'title' => $p['title'],
            'qty' => $qty,
            'price_ars' => (float)$p['price_ars'],
            'price_usd' => (float)$p['price_usd'],
            'vendor_id' => $p['vendor_id']
        ];
    }
    save_cart($cart);
    return ['ok' => true];
}

function update_cart_item(int $product_id, int $qty) {
    $cart = get_cart();
    if (!isset($cart[$product_id])) return;
    if ($qty <= 0) {
        unset($cart[$product_id]);
    } else {
        $cart[$product_id]['qty'] = $qty;
    }
    save_cart($cart);
}

function remove_cart_item(int $product_id) {
    $cart = get_cart();
    if (isset($cart[$product_id])) {
        unset($cart[$product_id]);
        save_cart($cart);
    }
}

function cart_summary() {
    $cart = get_cart();
    $total_ars = 0.0; $total_usd = 0.0;
    $by_vendor = [];
    foreach ($cart as $it) {
        $line_ars = ($it['price_ars'] ?? 0) * $it['qty'];
        $line_usd = ($it['price_usd'] ?? 0) * $it['qty'];
        $total_ars += $line_ars;
        $total_usd += $line_usd;
        $vid = $it['vendor_id'] ?? 0;
        if (!isset($by_vendor[$vid])) $by_vendor[$vid] = ['items'=>[],'total_ars'=>0,'total_usd'=>0];
        $by_vendor[$vid]['items'][] = $it;
        $by_vendor[$vid]['total_ars'] += $line_ars;
        $by_vendor[$vid]['total_usd'] += $line_usd;
    }
    return ['total_ars'=>$total_ars,'total_usd'=>$total_usd,'by_vendor'=>$by_vendor,'items'=>$cart];
}
