<?php
require_once __DIR__ . '/../../inc/auth.php';
require_role('admin');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/upload.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
    $errors[] = 'Token inválido.';
  }
    $vendor_id = $_POST['vendor_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?: null;
    $sku = $_POST['sku'] ?: null;
    $is_primary = isset($_POST['is_primary']) ? 1 : 0;
    $is_secondary = isset($_POST['is_secondary']) ? 1 : 0;
    $price_ars = floatval($_POST['price_ars'] ?? 0);
    $price_usd = floatval($_POST['price_usd'] ?? 0);
    $available = isset($_POST['available']) ? 1 : 0;

    if (!$title || !$vendor_id) $errors[] = 'Título y vendedor son requeridos.';
    if (strlen($title) > 255) $errors[] = 'El título puede tener máximo 255 caracteres.';
    if ($price_ars < 0 || $price_usd < 0) $errors[] = 'Los precios no pueden ser negativos.';

    // validate vendor exists
    $vstmt = $pdo->prepare('SELECT id FROM vendors WHERE id = ? LIMIT 1');
    $vstmt->execute([$vendor_id]);
    if (!$vstmt->fetch()) $errors[] = 'Vendedor no encontrado.';

    if (empty($errors)) {
        if (!$slug) $slug = substr(preg_replace('/[^a-z0-9]+/','-',strtolower($title)),0,240);
      // ensure unique slug
      $baseSlug = $slug;
      $i = 1;
      while (true) {
        $chk = $pdo->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
        $chk->execute([$slug]);
        if (!$chk->fetch()) break;
        $slug = $baseSlug . '-' . $i;
        $i++;
      }

      $stmt = $pdo->prepare('INSERT INTO products (vendor_id,title,slug,description,category_id,sku,is_primary,is_secondary,price_ars,price_usd,available,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$vendor_id,$title,$slug,$description,$category_id,$sku,$is_primary,$is_secondary,$price_ars,$price_usd,$available]);
        $pid = $pdo->lastInsertId();

        // handle images
        if (!empty($_FILES['hero']) && $_FILES['hero']['error'] === UPLOAD_ERR_OK) {
          $r = upload_image($_FILES['hero'], 'products/' . $pid, true);
          if (!empty($r['error'])) $errors[] = 'Hero: '.$r['error'];
          else {
            $heroPath = $r['variants']['hero'] ?? $r['path'];
            $stmt = $pdo->prepare('INSERT INTO images (product_id,type,path) VALUES (?,?,?)');
            $stmt->execute([$pid,'hero',$heroPath]);
            $hid = $pdo->lastInsertId();
            $pdo->prepare('UPDATE products SET hero_image_id = ? WHERE id = ?')->execute([$hid,$pid]);
          }
        }
        if (!empty($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
          $r = upload_image($_FILES['cover'], 'products/' . $pid, true);
          if (!empty($r['error'])) $errors[] = 'Cover: '.$r['error'];
          else {
            // prefer large variant for cover
            $coverPath = $r['variants']['large'] ?? $r['variants']['medium'] ?? $r['path'];
            $stmt = $pdo->prepare('INSERT INTO images (product_id,type,path) VALUES (?,?,?)');
            $stmt->execute([$pid,'cover',$coverPath]);
            $cid = $pdo->lastInsertId();
            $pdo->prepare('UPDATE products SET cover_image_id = ? WHERE id = ?')->execute([$cid,$pid]);
          }
        }

        header('Location: /public/admin/products.php');
        exit;
    }
}

$vendors = $pdo->query('SELECT id,store_name FROM vendors ORDER BY store_name')->fetchAll();
$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Crear producto</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="container auth">
    <h1>Crear producto</h1>
    <?php if ($errors): ?>
      <div class="errors"><ul><?php foreach($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <label>Vendedor
        <select name="vendor_id" required>
          <option value="">Seleccionar</option>
          <?php foreach($vendors as $v): ?>
            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['store_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Título<br><input name="title" required></label>
      <label>Slug (opcional)<br><input name="slug"></label>
      <label>Descripción<br><textarea name="description"></textarea></label>
      <label>Categoría<br>
        <select name="category_id">
          <option value="">Sin categoría</option>
          <?php foreach($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>SKU<br><input name="sku"></label>
      <label>Principal <input type="checkbox" name="is_primary"></label>
      <label>Secundario <input type="checkbox" name="is_secondary"></label>
      <label>Precio ARS<br><input name="price_ars" type="number" step="0.01"></label>
      <label>Precio USD<br><input name="price_usd" type="number" step="0.01"></label>
      <label>Disponible <input type="checkbox" name="available" checked></label>
      <label>Imagen Hero<br><input type="file" name="hero" accept="image/*"></label>
      <label>Imagen Cover<br><input type="file" name="cover" accept="image/*"></label>
      <button class="btn" type="submit">Crear</button>
    </form>
  </div>
</body>
</html>
