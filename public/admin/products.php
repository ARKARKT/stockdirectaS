<?php
require_once __DIR__ . '/../../inc/auth.php';
require_role('admin');
require_once __DIR__ . '/../../inc/db.php';

$stmt = $pdo->query('SELECT p.id,p.title,p.price_ars,p.price_usd,p.available,v.store_name FROM products p LEFT JOIN vendors v ON p.vendor_id = v.id ORDER BY p.created_at DESC');
$products = $stmt->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Productos</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="container">
    <header class="site-header"><h1>Productos</h1></header>
    <p><a class="btn" href="/public/admin/product_create.php">Crear producto</a>
    <a class="btn" href="/public/admin/import.php">Importar CSV/ZIP</a></p>

    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr><th>Título</th><th>Tienda</th><th>ARS</th><th>USD</th><th>Estado</th><th>Acciones</th></tr>
      </thead>
      <tbody>
      <?php foreach($products as $p): ?>
        <tr style="background:#fff;border-bottom:1px solid #eee">
          <td><?=htmlspecialchars($p['title'])?></td>
          <td><?=htmlspecialchars($p['store_name'] ?? '—')?></td>
          <td><?=number_format($p['price_ars'],2)?></td>
          <td><?=number_format($p['price_usd'],2)?></td>
          <td><?= $p['available'] ? 'Activo' : 'Inactivo' ?></td>
          <td>
            <a class="btn" href="/public/admin/product_edit.php?id=<?= $p['id'] ?>">Editar</a>
            <form method="post" action="/public/admin/product_delete.php" style="display:inline"> 
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
              <button class="btn" type="submit" onclick="return confirm('Eliminar producto?')">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
