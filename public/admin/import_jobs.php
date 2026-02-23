<?php
require_once __DIR__ . '/../../inc/auth.php';
require_role('admin');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/import.php';

// filters & pagination
$msg = null;
$statusFilter = $_GET['status'] ?? '';
$vendorFilter = $_GET['vendor_id'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        $msg = 'Token inválido.';
    } else {
        $jid = intval($_POST['job_id']);
        // process single job synchronously (admin-triggered)
        $job = $pdo->prepare('SELECT * FROM product_import_jobs WHERE id = ? LIMIT 1');
        $job->execute([$jid]);
        $job = $job->fetch();
        if (!$job) $msg = 'Job no encontrado.';
        else if ($job['status'] !== 'pending') $msg = 'Job no pendiente.';
        else {
            // mark running
            $pdo->prepare('UPDATE product_import_jobs SET status = ?, log = CONCAT(IFNULL(log,''),' . "'? Started: ' . date('Y-m-d H:i:s') . '\\n'" . ' ) WHERE id = ?')->execute(['running',$jid]);
            // perform import
            $csvPath = __DIR__ . '/../../uploads/imports/' . $job['filename'];
            $res = import_products_from_csv($csvPath, $job['vendor_id']);
            $log = "Created: " . ($res['created'] ?? 0) . "\nErrors:\n" . implode("\n", ($res['errors'] ?? []));
            $status = empty($res['errors']) ? 'completed' : 'failed';
            $stmt = $pdo->prepare('UPDATE product_import_jobs SET status = ?, log = CONCAT(IFNULL(log,''), ?, "\n"), processed_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $log, $jid]);
            $msg = 'Job procesado: ' . $status;
        }
    }
}

// build query with filters
$where = [];
$params = [];
if ($statusFilter) { $where[] = 'j.status = ?'; $params[] = $statusFilter; }
if ($vendorFilter) { $where[] = 'j.vendor_id = ?'; $params[] = $vendorFilter; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$offset = ($page - 1) * $perPage;
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM product_import_jobs j ' . $whereSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $pdo->prepare('SELECT j.*, v.store_name FROM product_import_jobs j LEFT JOIN vendors v ON v.id = j.vendor_id ' . $whereSql . ' ORDER BY j.created_at DESC LIMIT ? OFFSET ?');
// bind params and pagination limits
$execParams = $params;
$execParams[] = $perPage;
$execParams[] = $offset;
$stmt->execute($execParams);
$jobs = $stmt->fetchAll();

// load vendors for filter select
$vendors = $pdo->query('SELECT id,store_name FROM vendors ORDER BY store_name')->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Jobs de importación</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>.mono{font-family:monospace;font-size:13px;white-space:pre-wrap;background:#f6f8fa;padding:8px;border:1px solid #eee}</style>
</head>
<body>
  <div class="container auth">
    <h1>Import Jobs</h1>
    <?php if ($msg): ?><div class="muted"><?=htmlspecialchars($msg)?></div><?php endif; ?>

    <form method="get" style="margin-bottom:12px;display:flex;gap:8px;align-items:center">
      <label>Estado
        <select name="status">
          <option value="">Todos</option>
          <option value="pending" <?= ($statusFilter==='pending') ? 'selected' : '' ?>>pending</option>
          <option value="running" <?= ($statusFilter==='running') ? 'selected' : '' ?>>running</option>
          <option value="completed" <?= ($statusFilter==='completed') ? 'selected' : '' ?>>completed</option>
          <option value="failed" <?= ($statusFilter==='failed') ? 'selected' : '' ?>>failed</option>
        </select>
      </label>
      <label>Vendedor
        <select name="vendor_id">
          <option value="">Todos</option>
          <?php foreach($vendors as $v): ?>
            <option value="<?= $v['id'] ?>" <?= ($vendorFilter == $v['id']) ? 'selected' : '' ?>><?= htmlspecialchars($v['store_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit">Filtrar</button>
    </form>

    <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
      <thead><tr><th>ID</th><th>Archivo</th><th>Vendedor</th><th>Status</th><th>Creado</th><th>Procesado</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach($jobs as $j): ?>
          <tr>
            <td><?= $j['id'] ?></td>
            <td><?= htmlspecialchars($j['filename']) ?></td>
            <td><?= htmlspecialchars($j['store_name'] ?? $j['vendor_id']) ?></td>
            <td><?= htmlspecialchars($j['status']) ?></td>
            <td><?= htmlspecialchars($j['created_at']) ?></td>
            <td><?= htmlspecialchars($j['processed_at'] ?? '') ?></td>
            <td>
              <?php if ($j['status']==='pending'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                  <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                  <button class="btn" type="submit">Procesar</button>
                </form>
              <?php endif; ?>
              <?php if ($j['log']): ?><div class="mono"><?=htmlspecialchars($j['log'])?></div><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php
    // pagination links
    $totalPages = (int)ceil($total / $perPage);
    if ($totalPages > 1):
    ?>
      <div style="margin-bottom:18px">Páginas: 
        <?php for($p=1;$p<=$totalPages;$p++): ?>
          <?php $qs = http_build_query(array_merge($_GET, ['page'=>$p])); ?>
          <a href="?<?= $qs ?>" <?= $p== $page ? 'style="font-weight:bold"' : '' ?>><?= $p ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
