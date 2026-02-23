<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/theme.php';

$slug = $_GET['slug'] ?? null;
if (!$slug) {
    echo 'Producto no especificado.'; exit;
}

$stmt = $pdo->prepare('SELECT p.*, v.store_name, v.slug AS vendor_slug, v.theme_settings FROM products p LEFT JOIN vendors v ON p.vendor_id = v.id WHERE p.slug = ? LIMIT 1');
$stmt->execute([$slug]);
$product = $stmt->fetch();
if (!$product) { echo 'Producto no encontrado.'; exit; }

// get hero image
$hero = null;
if ($product['hero_image_id']) {
    $h = $pdo->prepare('SELECT path FROM images WHERE id = ? LIMIT 1');
    $h->execute([$product['hero_image_id']]);
    $hero = $h->fetchColumn();
}
// get cover image
$cover = null;
if ($product['cover_image_id']) {
    $c = $pdo->prepare('SELECT path FROM images WHERE id = ? LIMIT 1');
    $c->execute([$product['cover_image_id']]);
    $cover = $c->fetchColumn();
}

// currency selection for display (persistent via cookie or GET)
$currency = current_currency();

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?=htmlspecialchars($product['title'])?> - StockDirecta</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php if (!empty($product['theme_settings'])): ?>
    <?= render_theme_style(parse_theme_settings($product['theme_settings'])) ?>
  <?php endif; ?>
</head>
<body class="store-theme">
  <header class="site-header">
    <div class="container">
      <h1><?=htmlspecialchars($product['title'])?></h1>
      <nav class="main-nav"><a href="/products.php">Catálogo</a></nav>
      <?php echo render_currency_toggle(); ?>
    </div>
  </header>

  <?php if ($hero): ?>
    <section style="background:url('<?=htmlspecialchars($hero)?>') center/cover no-repeat;padding:4rem 0;margin-bottom:1rem">
      <div class="container"><h2 style="color:#fff;text-shadow:0 2px 6px rgba(0,0,0,.6)"><?=htmlspecialchars($product['title'])?></h2></div>
    </section>
  <?php elseif ($cover): ?>
    <section style="background:url('<?=htmlspecialchars($cover)?>') center/cover no-repeat;padding:3rem 0;margin-bottom:1rem">
      <div class="container"><h2 style="color:#fff;text-shadow:0 2px 6px rgba(0,0,0,.6)"><?=htmlspecialchars($product['title'])?></h2></div>
    </section>
  <?php endif; ?>

  <main class="container">
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;align-items:start">
      <div>
        <article class="card" style="padding:1rem">
          <h3>Descripción</h3>
          <div><?=nl2br(htmlspecialchars($product['description']))?></div>
        </article>
      </div>
      <aside>
        <div class="card" style="padding:1rem">
          <h3>Comprar</h3>
          <form method="get" style="margin-bottom:.5rem">
            <input type="hidden" name="slug" value="<?=htmlspecialchars($product['slug'])?>">
            <label>Moneda
              <select name="currency" onchange="this.form.submit()">
                <option value="ARS" <?= ($currency=='ARS') ? 'selected' : '' ?>>ARS</option>
                <option value="USD" <?= ($currency=='USD') ? 'selected' : '' ?>>USD</option>
              </select>
            </label>
          </form>
          <p><strong><?php if ($currency === 'USD') { echo 'USD '.number_format($product['price_usd'],2); } else { echo 'ARS '.number_format($product['price_ars'],2); } ?></strong></p>
          <form method="post" action="/public/cart_add.php" style="display:inline-block;margin-right:.5rem">
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            <input type="hidden" name="qty" value="1">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
            <button class="btn" type="submit">Agregar al carrito</button>
          </form>
          <form method="post" action="/public/checkout.php" style="display:inline-block">
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            <input type="hidden" name="qty" value="1">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
            <button class="btn" type="submit">Comprar ahora</button>
          </form>
        </div>
          <div class="card" style="padding:1rem;margin-top:1rem">
            <h4>Vendedor</h4>
            <div>
              <?php if (!empty($product['vendor_slug'])): ?>
                <a href="/store/<?=urlencode($product['vendor_slug'])?>?currency=<?=urlencode($currency)?>"><?=htmlspecialchars($product['store_name'] ?? '—')?></a>
              <?php else: ?>
                <?=htmlspecialchars($product['store_name'] ?? '—')?>
              <?php endif; ?>
            </div>
          </div>
      </aside>
    </div>
  </main>
</body>
</html>
