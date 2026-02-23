<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>StockDirecta</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php if (file_exists(__DIR__ . '/../inc/theme.php')) { require_once __DIR__ . '/../inc/theme.php'; maybe_render_theme_from_request(); } ?>
</head>
<body>
  <header class="site-header">
    <div class="container">
      <h1>StockDirecta</h1>
      <nav class="main-nav">
        <a href="/login.php">Login</a>
        <?php $me = current_user(); if ($me && $me['role']==='vendor') {
          $vs = $pdo->prepare('SELECT slug FROM vendors WHERE user_id = ? LIMIT 1'); $vs->execute([$me['id']]); $vslug = $vs->fetchColumn();
          if ($vslug) echo ' <a href="/store/'.htmlspecialchars(urlencode($vslug)).'">Mi tienda</a>';
        } ?>
      </nav>
        <?php if (file_exists(__DIR__ . '/../inc/theme.php')) { require_once __DIR__ . '/../inc/theme.php'; echo render_currency_toggle(); } ?>
    </div>
  </header>

  <main class="container">
    <section class="hero">
      <h2>Marketplace de productos digitales</h2>
      <p>Catálogo inicial: PlayStation, Xbox, Nintendo, Steam, Cards y más.</p>
    </section>

    <section class="features">
      <article>
        <h3>Para administradores</h3>
        <p>Crear vendedores, subir productos y administrar catálogo.</p>
      </article>
      <article>
        <h3>Para vendedores</h3>
        <p>Gestionar tienda, precios, pedidos y personalización visual.</p>
      </article>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">&copy; StockDirecta</div>
  </footer>
</body>
</html>