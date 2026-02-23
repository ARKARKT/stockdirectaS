<?php
require_once __DIR__ . '/../inc/db.php';
$order_id = $_GET['order_id'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Pedido recibido</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php if (file_exists(__DIR__ . '/../inc/theme.php')) { require_once __DIR__ . '/../inc/theme.php'; maybe_render_theme_from_request(); } ?>
</head>
<body>
  <div class="container">
    <div class="card" style="padding:1rem;margin-top:2rem">
      <h2>Gracias — Pedido recibido</h2>
      <?php if ($order_id): ?>
        <p>Tu pedido fue creado correctamente. ID: <strong><?=htmlspecialchars($order_id)?></strong></p>
      <?php else: ?>
        <p>Tu pedido fue creado correctamente.</p>
      <?php endif; ?>
      <p>Revisa tu email para las instrucciones de pago. Si no llega, contacta a soporte.</p>
      <p><a class="btn" href="/products.php">Volver al catálogo</a></p>
    </div>
  </div>
</body>
</html>
