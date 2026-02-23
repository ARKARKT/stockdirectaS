<?php
require_once __DIR__ . '/../../inc/auth.php';
require_role('admin');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/upload.php';

$errors = [];
$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
if (!$id) {
    echo 'Producto no especificado.'; exit;
}

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { echo 'Producto no encontrado.'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        $errors[] = 'Token inválido.';
    }
    $title = trim($_POST['title'] ?? '');
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?: null;
    $sku = $_POST['sku'] ?: null;
    $is_primary = isset($_POST['is_primary']) ? 1 : 0;
    $is_secondary = isset($_POST['is_secondary']) ? 1 : 0;
    $price_ars = floatval($_POST['price_ars'] ?? 0);
    $price_usd = floatval($_POST['price_usd'] ?? 0);
    $available = isset($_POST['available']) ? 1 : 0;

    if (!$title) $errors[] = 'Título requerido.';
    if (strlen($title) > 255) $errors[] = 'El título puede tener máximo 255 caracteres.';
    if ($price_ars < 0 || $price_usd < 0) $errors[] = 'Los precios no pueden ser negativos.';

    if (empty($errors)) {
        $slug = $product['slug'];
        $stmt = $pdo->prepare('UPDATE products SET title=?,slug=?,description=?,category_id=?,sku=?,is_primary=?,is_secondary=?,price_ars=?,price_usd=?,available=?,updated_at=NOW() WHERE id=?');
        $stmt->execute([$title,$slug,$description,$category_id,$sku,$is_primary,$is_secondary,$price_ars,$price_usd,$available,$id]);

        // handle new images and replace
        if (!empty($_FILES['hero']) && $_FILES['hero']['error'] === UPLOAD_ERR_OK) {
          $r = upload_image($_FILES['hero'], 'products/' . $id, true);
          if (!empty($r['error'])) $errors[] = 'Hero: '.$r['error'];
          else {
            // delete old
            if ($product['hero_image_id']) {
              $old = $pdo->prepare('SELECT path FROM images WHERE id = ? LIMIT 1');
              $old->execute([$product['hero_image_id']]);
              $oldp = $old->fetchColumn();
              if ($oldp) {
                $full = __DIR__ . '/../../' . ltrim($oldp, '/');
                if (file_exists($full)) @unlink($full);
              }
              $pdo->prepare('DELETE FROM images WHERE id = ?')->execute([$product['hero_image_id']]);
            }
            $heroPath = $r['variants']['hero'] ?? $r['path'];
            $stmt = $pdo->prepare('INSERT INTO images (product_id,type,path) VALUES (?,?,?)');
            $stmt->execute([$id,'hero',$heroPath]);
            $hid = $pdo->lastInsertId();
            $pdo->prepare('UPDATE products SET hero_image_id = ? WHERE id = ?')->execute([$hid,$id]);
          }
        }
        if (!empty($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
          $r = upload_image($_FILES['cover'], 'products/' . $id, true);
          if (!empty($r['error'])) $errors[] = 'Cover: '.$r['error'];
          else {
            if ($product['cover_image_id']) {
              $old = $pdo->prepare('SELECT path FROM images WHERE id = ? LIMIT 1');
              $old->execute([$product['cover_image_id']]);
              $oldp = $old->fetchColumn();
              if ($oldp) {
                $full = __DIR__ . '/../../' . ltrim($oldp, '/');
                if (file_exists($full)) @unlink($full);
              }
              $pdo->prepare('DELETE FROM images WHERE id = ?')->execute([$product['cover_image_id']]);
            }
            $coverPath = $r['variants']['large'] ?? $r['variants']['medium'] ?? $r['path'];
            $stmt = $pdo->prepare('INSERT INTO images (product_id,type,path) VALUES (?,?,?)');
            $stmt->execute([$id,'cover',$coverPath]);
            $cid = $pdo->lastInsertId();
            $pdo->prepare('UPDATE products SET cover_image_id = ? WHERE id = ?')->execute([$cid,$id]);
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
  <title>Editar producto</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="container auth">
    <h1>Editar producto</h1>
    <?php if ($errors): ?>
      <div class="errors"><ul><?php foreach($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <label>Título<br><input name="title" value="<?= htmlspecialchars($product['title']) ?>" required></label>
      <label>Descripción<br><textarea name="description"><?= htmlspecialchars($product['description']) ?></textarea></label>
      <label>Categoría<br>
        <select name="category_id">
          <option value="">Sin categoría</option>
          <?php foreach($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == $product['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>SKU<br><input name="sku" value="<?= htmlspecialchars($product['sku']) ?>"></label>
      <label>Principal <input type="checkbox" name="is_primary" <?= $product['is_primary'] ? 'checked' : '' ?>></label>
      <label>Secundario <input type="checkbox" name="is_secondary" <?= $product['is_secondary'] ? 'checked' : '' ?>></label>
      <label>Precio ARS<br><input name="price_ars" type="number" step="0.01" value="<?= htmlspecialchars($product['price_ars']) ?>"></label>
      <label>Precio USD<br><input name="price_usd" type="number" step="0.01" value="<?= htmlspecialchars($product['price_usd']) ?>"></label>
      <label>Disponible <input type="checkbox" name="available" <?= $product['available'] ? 'checked' : '' ?>></label>
      <p>Si subes nuevas imágenes, reemplazarán las existentes.</p>
      <label>Imagen Hero<br><input type="file" name="hero" accept="image/*"></label>
      <label>Imagen Cover<br><input type="file" name="cover" accept="image/*"></label>
      <button class="btn" type="submit">Guardar</button>
    </form>
  </div>
</body>
</html>
