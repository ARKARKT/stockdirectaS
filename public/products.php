<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/theme.php';
// simple catalog listing with optional category filter and search
// load categories for selector
$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();

// default columns and per-page options
$perRow = 3;
$filter_cat = isset($_GET['category']) ? intval($_GET['category']) : 0;
$qstr = isset($_GET['q']) ? trim($_GET['q']) : '';
// sorting
$allowed_sorts = ['price_asc','price_desc','newest'];
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
if (!in_array($sort, $allowed_sorts)) $sort = 'newest';

$allowed_cols = [3,4,5];
$cols = isset($_GET['cols']) ? intval($_GET['cols']) : $perRow;
if (!in_array($cols, $allowed_cols)) $cols = $perRow;

$allowed_per = [10,20,30];
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
if (!in_array($per_page, $allowed_per)) $per_page = 10;

// currency and price max filter
$currency = current_currency();
$price_max = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? floatval($_GET['price_max']) : 0;

// pagination
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// build base where
$where = ' WHERE p.available = 1';
$params = [];
if ($filter_cat) { $where .= ' AND p.category_id = :cid'; $params[':cid'] = $filter_cat; }
if ($qstr !== '') { $where .= ' AND p.title LIKE :q'; $params[':q'] = '%'.$qstr.'%'; }
if ($price_max > 0) {
  if ($currency === 'USD') {
    $where .= ' AND p.price_usd <= :price_max';
  } else {
    $where .= ' AND p.price_ars <= :price_max';
  }
  $params[':price_max'] = $price_max;
}

// total count
$countSql = 'SELECT COUNT(*) FROM products p' . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// fetch page
$orderSql = 'p.created_at DESC';
if ($sort === 'price_asc') {
  $orderSql = ($currency === 'USD') ? 'p.price_usd ASC' : 'p.price_ars ASC';
} elseif ($sort === 'price_desc') {
  $orderSql = ($currency === 'USD') ? 'p.price_usd DESC' : 'p.price_ars DESC';
}
$sql = 'SELECT p.id,p.title,p.slug,p.price_ars,p.price_usd,p.cover_image_id, i.path AS image_path, v.slug AS vendor_slug, v.store_name AS vendor_name FROM products p LEFT JOIN images i ON p.cover_image_id = i.id LEFT JOIN vendors v ON p.vendor_id = v.id' . $where . ' ORDER BY ' . $orderSql . ' LIMIT :lim OFFSET :off';
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

$total_pages = max(1, (int)ceil($total / $per_page));
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Productos - StockDirecta</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php if (file_exists(__DIR__ . '/../inc/theme.php')) { require_once __DIR__ . '/../inc/theme.php'; maybe_render_theme_from_request(); } ?>
</head>
<body>
  <header class="site-header"><div class="container"><h1>Productos</h1><nav class="main-nav"><a href="/">Inicio</a>
    <?php $me = current_user(); if ($me && $me['role']==='vendor') {
      $vs = $pdo->prepare('SELECT slug FROM vendors WHERE user_id = ? LIMIT 1'); $vs->execute([$me['id']]); $vslug = $vs->fetchColumn();
      if ($vslug) echo ' <a href="/store/'.htmlspecialchars(urlencode($vslug)).'">Mi tienda</a>';
    } ?>
    </nav>
    <?php echo render_currency_toggle(); ?>
  </div></header>
  <main class="container">
    <form method="get" class="filter-form" style="margin:12px 0;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
      <select name="category">
        <option value="0">Todas las categorías</option>
        <?php foreach($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($filter_cat== $c['id']) ? 'selected' : '' ?>><?=htmlspecialchars($c['name'])?></option>
        <?php endforeach; ?>
      </select>
      <input type="search" name="q" placeholder="Buscar por título" value="<?=htmlspecialchars($qstr)?>">
      <label>Columnas
        <select name="cols">
          <?php foreach([3,4,5] as $cc): ?>
            <option value="<?= $cc ?>" <?= ($cols==$cc) ? 'selected' : '' ?>><?= $cc ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Orden
        <select name="sort">
          <option value="newest" <?= ($sort=='newest') ? 'selected' : '' ?>>Más recientes</option>
          <option value="price_asc" <?= ($sort=='price_asc') ? 'selected' : '' ?>>Precio: menor a mayor</option>
          <option value="price_desc" <?= ($sort=='price_desc') ? 'selected' : '' ?>>Precio: mayor a menor</option>
        </select>
      </label>
      <label>Moneda
        <select name="currency">
          <option value="ARS" <?= ($currency=='ARS') ? 'selected' : '' ?>>ARS</option>
          <option value="USD" <?= ($currency=='USD') ? 'selected' : '' ?>>USD</option>
        </select>
      </label>
      <label>Precio hasta
        <input type="number" step="0.01" name="price_max" placeholder="0.00" value="<?= $price_max>0 ? htmlspecialchars(number_format($price_max,2,'.','')) : '' ?>">
      </label>
      <label>Por página
        <select name="per_page">
          <?php foreach([10,20,30] as $pp): ?>
            <option value="<?= $pp ?>" <?= ($per_page==$pp) ? 'selected' : '' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit">Filtrar</button>
      <a class="btn" href="/public/products.php">Limpiar</a>
    </form>
    <section class="products-grid products-grid-3" style="display:grid;grid-template-columns:repeat(<?= intval($cols)?>,1fr);gap:1rem">
      <?php foreach($products as $p): ?>
        <article class="card">
          <?php if ($p['image_path']): ?>
            <a href="/public/product.php?slug=<?=urlencode($p['slug'])?>&currency=<?=urlencode($currency)?>"><img src="<?=htmlspecialchars($p['image_path'])?>" alt="<?=htmlspecialchars($p['title'])?>"></a>
          <?php else: ?>
            <a href="/public/product.php?slug=<?=urlencode($p['slug'])?>&currency=<?=urlencode($currency)?>"><div style="height:220px;background:#eee"></div></a>
          <?php endif; ?>
          <div class="meta">
            <a href="/public/product.php?slug=<?=urlencode($p['slug'])?>&currency=<?=urlencode($currency)?>"><strong><?=htmlspecialchars($p['title'])?></strong></a>
            <div class="muted">
              <?php if ($currency === 'USD'): ?>
                USD <?=number_format($p['price_usd'],2)?>
              <?php else: ?>
                ARS <?=number_format($p['price_ars'],2)?>
              <?php endif; ?>
            </div>
            <?php if (!empty($p['vendor_slug'])): ?>
              <div class="muted"><a href="/store/<?=urlencode($p['vendor_slug'])?>">Ver tienda: <?=htmlspecialchars($p['vendor_name'] ?? '')?></a></div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
    <?php if ($total_pages > 1): ?>
      <nav class="pagination" style="margin:1rem 0;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <?php
        // helper to build query preserving filters
        $baseParams = [];
        if ($filter_cat) $baseParams['category'] = $filter_cat;
        if ($qstr !== '') $baseParams['q'] = $qstr;
        if ($cols) $baseParams['cols'] = $cols;
        if ($per_page) $baseParams['per_page'] = $per_page;
        if ($sort) $baseParams['sort'] = $sort;
        if ($price_max > 0) $baseParams['price_max'] = $price_max;
        if ($currency) $baseParams['currency'] = $currency;

        $buildLink = function($pnum) use ($baseParams) {
          $params = $baseParams;
          $params['page'] = $pnum;
          return '?'.http_build_query($params);
        };
        ?>
        <?php if ($page > 1): ?><a class="btn" href="<?= $buildLink($page-1) ?>">« Prev</a><?php endif; ?>
        <?php for($i=1;$i<=$total_pages;$i++): ?>
          <a class="btn" href="<?= $buildLink($i) ?>" style="<?= $i==$page ? 'font-weight:bold;opacity:0.9' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?><a class="btn" href="<?= $buildLink($page+1) ?>">Next »</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </main>
</body>
</html>
