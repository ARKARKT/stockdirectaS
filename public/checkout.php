<?php
require_once __DIR__ . '/../inc/cart.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/email.php';
require_once __DIR__ . '/../inc/db.php';

$cart = get_cart();
if (empty($cart)) { header('Location: /public/cart.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) $errors[] = 'Token inválido.';
    $buyer_name = trim($_POST['buyer_name'] ?? '');
    $buyer_email = trim($_POST['buyer_email'] ?? '');
    $buyer_phone = trim($_POST['buyer_phone'] ?? '');
    $currency = in_array($_POST['currency'] ?? 'ARS', ['ARS','USD']) ? $_POST['currency'] : 'ARS';
    if (!$buyer_name || !$buyer_email) $errors[] = 'Nombre y email son requeridos.';

    if (empty($errors)) {
        $summary = cart_summary();
        $created_orders = [];
        // create one order per vendor
        foreach ($summary['by_vendor'] as $vendor_id => $group) {
            $total_ars = $group['total_ars'];
            $total_usd = $group['total_usd'];
            $stmt = $pdo->prepare('INSERT INTO orders (buyer_name,buyer_email,buyer_phone,vendor_id,total_ars,total_usd,currency,status,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            $stmt->execute([$buyer_name,$buyer_email,$buyer_phone,$vendor_id,$total_ars,$total_usd,$currency,'pendiente']);
            $order_id = $pdo->lastInsertId();
            foreach ($group['items'] as $it) {
                $stmt = $pdo->prepare('INSERT INTO order_items (order_id,product_id,title_snapshot,qty,price_ars,price_usd,currency) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$order_id,$it['product_id'],$it['title'],$it['qty'],$it['price_ars'],$it['price_usd'],$currency]);
            }
            $created_orders[] = $order_id;

            // send email to vendor (use template)
            $vstmt = $pdo->prepare('SELECT contact_info,bank_details,store_name FROM vendors WHERE id = ? LIMIT 1');
            $vstmt->execute([$vendor_id]);
            $v = $vstmt->fetch();
            $vendorContact = $v['contact_info'] ?? '';
            $vendorEmail = null;
            if ($vendorContact) {
              $ci = json_decode($vendorContact, true);
              $vendorEmail = $ci['email'] ?? null;
            }
            $orderMeta = ['id' => $order_id, 'buyer_name' => $buyer_name, 'buyer_email' => $buyer_email, 'total_ars' => $total_ars, 'total_usd' => $total_usd, 'status' => 'pendiente'];
            $subject = 'Nuevo pedido #' . $order_id;
            $vendorHtml = build_order_status_change_vendor_html($orderMeta, $group['items'], ['buyer_name'=>$buyer_name,'buyer_email'=>$buyer_email], 'pendiente');
            if ($vendorEmail) send_email($vendorEmail, $subject, $vendorHtml, null, null, $order_id);
        }

        // send per-order email to buyer using templates
        foreach ($created_orders as $oid) {
          // load order and items
          $ost = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
          $ost->execute([$oid]);
          $o = $ost->fetch();
          $itst = $pdo->prepare('SELECT title_snapshot,qty,price_ars,price_usd FROM order_items WHERE order_id = ?');
          $itst->execute([$oid]);
          $its = $itst->fetchAll();
          $vstmt = $pdo->prepare('SELECT store_name,bank_details FROM vendors WHERE id = ? LIMIT 1');
          $vstmt->execute([$o['vendor_id']]);
          $vinfo = $vstmt->fetch();
          $subject = 'Pedido recibido #' . $oid;
          $buyerHtml = build_order_status_change_buyer_html($o, $its, $vinfo, $o['status'] ?? 'pendiente');
          send_email($buyer_email, $subject, $buyerHtml, null, null, $oid);
        }

        // clear cart
        clear_cart();

        // redirect to confirmation (show first order id)
        header('Location: /public/order_confirmation.php?order_id=' . urlencode($created_orders[0] ?? '')); exit;
    }
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Checkout - StockDirecta</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php if (file_exists(__DIR__ . '/../inc/theme.php')) { require_once __DIR__ . '/../inc/theme.php'; maybe_render_theme_from_request(); } ?>
</head>
<body>
  <header class="site-header"><div class="container"><h1>Checkout</h1>
    <?php echo render_currency_toggle(); ?>
  </div></header>
  <main class="container auth">
    <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
      <label>Nombre completo<br><input name="buyer_name" required></label>
      <label>Email<br><input type="email" name="buyer_email" required></label>
      <label>Teléfono (opcional)<br><input name="buyer_phone"></label>
      <label>Moneda preferida<br>
        <select name="currency">
          <option value="ARS">ARS</option>
          <option value="USD">USD</option>
        </select>
      </label>
      <h3>Resumen</h3>
      <ul>
        <?php foreach ($cart as $it): ?>
          <li><?=htmlspecialchars($it['title'])?> x<?=intval($it['qty'])?></li>
        <?php endforeach; ?>
      </ul>
      <button class="btn" type="submit">Confirmar pedido</button>
    </form>
  </main>
</body>
</html>
