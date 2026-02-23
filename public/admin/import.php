<?php
require_once __DIR__ . '/../../inc/auth.php';
require_role('admin');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/import.php';

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
    $message = 'Token inválido.';
  } else {
    if (!empty($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
        $importsDir = __DIR__ . '/../../uploads/imports';
        if (!is_dir($importsDir)) mkdir($importsDir, 0755, true);
        $csvName = bin2hex(random_bytes(6)) . '-' . basename($_FILES['csv']['name']);
        $csvPath = $importsDir . '/' . $csvName;
        if (move_uploaded_file($_FILES['csv']['tmp_name'], $csvPath)) {
          $vendor_id = $_POST['vendor_id'] ?: null;
          // create job
          $stmt = $pdo->prepare('INSERT INTO product_import_jobs (vendor_id, filename, status, created_at) VALUES (?,?,"pending",NOW())');
          $stmt->execute([$vendor_id, $csvName]);
          $jobId = $pdo->lastInsertId();
          $message = 'CSV subido y trabajo encolado (Job ID: ' . $jobId . ').';
        } else {
          $message = 'Error guardando CSV.';
        }
    } else {
        $message = 'CSV no enviado.';
    }
}

$vendors = $pdo->query('SELECT id,store_name FROM vendors ORDER BY store_name')->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Importar productos</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="container auth">
    <h1>Importar CSV</h1>
    <?php if ($message): ?><div class="errors"><?=htmlspecialchars($message)?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <label>Vendedor (opcional) - asigna los productos a este vendedor
        <select name="vendor_id">
          <option value="">Administrar</option>
          <?php foreach($vendors as $v): ?>
            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['store_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Archivo CSV<br><input type="file" name="csv" accept=".csv" required></label>
      <p class="muted">Opcional: sube también un ZIP a <code>/uploads/imports</code> con las imágenes referenciadas por nombre en las columnas <strong>hero</strong> y <strong>cover</strong>.</p>
      <button class="btn" type="submit">Importar</button>
    </form>
  </div>
</body>
</html>
