<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/theme.php';

$slug = $_GET['slug'] ?? null;
if (!$slug) { echo 'Tienda no especificada.'; exit; }

$vstmt = $pdo->prepare('SELECT id,store_name,theme_settings,logo_path,banner_path FROM vendors WHERE slug = ? AND is_active = 1 LIMIT 1');
$vstmt->execute([$slug]);
$vendor = $vstmt->fetch();
if (!$vendor) { echo 'Tienda no encontrada.'; exit; }

$vendor_id = $vendor['id'];
$store_name = $vendor['store_name'];

// allow theme render in head
$_GET['vendor_slug'] = $slug;

// filters
$filter_cat = isset($_GET['category']) ? intval($_GET['category']) : 0;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$allowed_cols = [3,4,5];
$cols = isset($_GET['cols']) ? intval($_GET['cols']) : 3;
if (!in_array($cols, $allowed_cols)) $cols = 3;
$allowed_per = [10,20,30];
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
if (!in_array($per_page, $allowed_per)) $per_page = 10;
$allowed_sorts = ['price_asc','price_desc','newest'];
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
if (!in_array($sort, $allowed_sorts)) $sort = 'newest';

// currency and price max filter (persistent via cookie)
$currency = current_currency();
$price_max = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? floatval($_GET['price_max']) : 0;

$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$where = ' WHERE p.vendor_id = :vid AND p.available = 1';
$params = [':vid' => $vendor_id];
if ($filter_cat) { $where .= ' AND p.category_id = :cid'; $params[':cid'] = $filter_cat; }
if ($q !== '') { $where .= ' AND p.title LIKE :q'; $params[':q'] = '%'.$q.'%'; }
if ($price_max > 0) {
  if ($currency === 'USD') {
    $where .= ' AND p.price_usd <= :price_max';
  } else {
    $where .= ' AND p.price_ars <= :price_max';
  }
  $params[':price_max'] = $price_max;
}

$countSql = 'SELECT COUNT(*) FROM products p' . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$orderSql = 'p.created_at DESC';
if ($sort === 'price_asc') {
  $orderSql = ($currency === 'USD') ? 'p.price_usd ASC' : 'p.price_ars ASC';
} elseif ($sort === 'price_desc') {
  $orderSql = ($currency === 'USD') ? 'p.price_usd DESC' : 'p.price_ars DESC';
}

$sql = 'SELECT p.id,p.title,p.slug,p.price_ars,p.price_usd,p.cover_image_id, i.path AS image_path FROM products p LEFT JOIN images i ON p.cover_image_id = i.id' . $where . ' ORDER BY ' . $orderSql . ' LIMIT :lim OFFSET :off';
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

$total_pages = max(1, (int)ceil($total / $per_page));

// load categories for selector
$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?=htmlspecialchars($store_name)?> - StockDirecta</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php maybe_render_theme_from_request(); ?>
</head>
<body class="store-theme">
  <header class="site-header"><div class="container"><h1><?=htmlspecialchars($store_name)?></h1>
    <?php echo render_currency_toggle(); ?>
  </div></header>
  <main class="container">
    <form method="get" style="margin:12px 0;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="slug" value="<?=htmlspecialchars($slug)?>">
      <label>Moneda
        <select name="currency">
          <option value="ARS" <?= ($currency=='ARS') ? 'selected' : '' ?>>ARS</option>
          <option value="USD" <?= ($currency=='USD') ? 'selected' : '' ?>>USD</option>
        </select>
      </label>
      <label>Precio hasta
        <input type="number" step="0.01" name="price_max" placeholder="0.00" value="<?= $price_max>0 ? htmlspecialchars(number_format($price_max,2,'.','')) : '' ?>">
      </label>
      <select name="category">
        <option value="0">Todas las categorías</option>
        <?php foreach($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($filter_cat== $c['id']) ? 'selected' : '' ?>><?=htmlspecialchars($c['name'])?></option>
        <?php endforeach; ?>
      </select>
      <input type="search" name="q" placeholder="Buscar por título" value="<?=htmlspecialchars($q)?>">
      <label>Orden
        <select name="sort">
          <option value="newest" <?= ($sort=='newest') ? 'selected' : '' ?>>Más recientes</option>
          <option value="price_asc" <?= ($sort=='price_asc') ? 'selected' : '' ?>>Precio: menor a mayor</option>
          <option value="price_desc" <?= ($sort=='price_desc') ? 'selected' : '' ?>>Precio: mayor a menor</option>
        </select>
      </label>
      <label>Columnas
        <select name="cols">
          <?php foreach([3,4,5] as $cc): ?>
            <option value="<?= $cc ?>" <?= ($cols==$cc) ? 'selected' : '' ?>><?= $cc ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Por página
        <select name="per_page">
          <?php foreach([10,20,30] as $pp): ?>
            <option value="<?= $pp ?>" <?= ($per_page==$pp) ? 'selected' : '' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit">Aplicar</button>
    </form>

    <section class="products-grid" style="display:grid;grid-template-columns:repeat(<?= intval($cols)?>,1fr);gap:1rem;margin-top:1rem">
      <?php foreach($products as $p): ?>
        <article class="card">
          <?php if ($p['image_path']): ?>
            <a href="/public/product.php?slug=<?=urlencode($p['slug'])?>&currency=<?=urlencode($currency)?>"><img src="<?=htmlspecialchars($p['image_path'])?>" alt="<?=htmlspecialchars($p['title'])?>"></a>
          <?php else: ?>
            <a href="/public/product.php?slug=<?=urlencode($p['slug'])?>&currency=<?=urlencode($currency)?>"><div style="height:160px;background:#eee"></div></a>
          <?php endif; ?>
          <div class="meta"><a href="/public/product.php?slug=<?=urlencode($p['slug'])?>&currency=<?=urlencode($currency)?>"><strong><?=htmlspecialchars($p['title'])?></strong></a>
              <div class="muted">
                <?php if ($currency === 'USD'): ?>
                  USD <?=number_format($p['price_usd'],2)?>
                <?php else: ?>
                  ARS <?=number_format($p['price_ars'],2)?>
                <?php endif; ?>
              </div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <?php if ($total_pages > 1): ?>
      <nav class="pagination" style="margin:1rem 0;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <?php
        $baseParams = ['slug'=>$slug];
        if ($filter_cat) $baseParams['category'] = $filter_cat;
        if ($q !== '') $baseParams['q'] = $q;
        if ($price_max > 0) $baseParams['price_max'] = $price_max;
        if ($currency) $baseParams['currency'] = $currency;
        $baseParams['cols'] = $cols;
        $baseParams['per_page'] = $per_page;
        $baseParams['sort'] = $sort;
        $buildLink = function($pnum) use ($baseParams) {
          $params = $baseParams; $params['page'] = $pnum; return '?'.http_build_query($params);
        };
        ?>
        <?php if ($page > 1): ?><a class="btn" href="<?= $buildLink($page-1) ?>">« Prev</a><?php endif; ?>
        <?php for($i=1;$i<=$total_pages;$i++): ?><a class="btn" href="<?= $buildLink($i) ?>" style="<?= $i==$page ? 'font-weight:bold;opacity:0.9' : '' ?>"><?= $i ?></a><?php endfor; ?>
        <?php if ($page < $total_pages): ?><a class="btn" href="<?= $buildLink($page+1) ?>">Next »</a><?php endif; ?>
      </nav>
    <?php endif; ?>

  </main>
</body>
</html>
