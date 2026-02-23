<?php
require_once __DIR__ . '/../inc/cart.php';
require_once __DIR__ . '/../inc/auth.php';

$cart = get_cart();
$summary = cart_summary();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Carrito - StockDirecta</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php if (file_exists(__DIR__ . '/../inc/theme.php')) { require_once __DIR__ . '/../inc/theme.php'; maybe_render_theme_from_request(); } ?>
</head>
<body>
  <header class="site-header"><div class="container"><h1>Carrito</h1><nav class="main-nav"><a href="/products.php">Seguir comprando</a></nav>
    <?php echo render_currency_toggle(); ?>
  </div></header>
  <main class="container">
    <?php if (empty($cart)): ?>
      <div class="card" style="padding:1rem">Tu carrito está vacío.</div>
    <?php else: ?>
      <form method="post" action="/public/cart_update.php">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
        <table style="width:100%">
          <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio ARS</th><th>Precio USD</th><th>Subtotal ARS</th><th>Subtotal USD</th><th>Quitar</th></tr></thead>
          <tbody>
          <?php foreach($cart as $id => $it): ?>
            <tr style="background:#fff;border-bottom:1px solid #eee">
              <td><?=htmlspecialchars($it['title'])?></td>
              <td><input type="number" name="qty[<?= $id ?>]" value="<?= $it['qty'] ?>" min="1" style="width:72px"></td>
              <td><?=number_format($it['price_ars'],2)?></td>
              <td><?=number_format($it['price_usd'],2)?></td>
              <td><?=number_format(($it['price_ars'] ?? 0) * $it['qty'],2)?></td>
              <td><?=number_format(($it['price_usd'] ?? 0) * $it['qty'],2)?></td>
              <td><label><input type="checkbox" name="remove[]" value="<?= $id ?>"> Sí</label></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <p class="muted">Total ARS: <strong><?=number_format($summary['total_ars'],2)?></strong> — Total USD: <strong><?=number_format($summary['total_usd'],2)?></strong></p>
        <button class="btn" type="submit">Actualizar carrito</button>
      </form>
      <p><a class="btn" href="/public/checkout.php">Ir a checkout</a></p>
    <?php endif; ?>
  </main>
</body>
</html>
