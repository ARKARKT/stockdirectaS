<?php
require_once __DIR__ . '/../../inc/auth.php';
require_role('admin');
$user = current_user();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Dashboard</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <header class="site-header">
    <div class="container">
      <h1>Admin</h1>
      <nav class="main-nav">
        <a href="/logout.php">Salir</a>
      </nav>
    </div>
  </header>

  <main class="container admin-grid">
    <h2>Bienvenido, <?=htmlspecialchars($user['name'])?></h2>
    <ul class="admin-actions">
      <li><a href="#">Crear vendedor</a></li>
      <li><a href="#">Gestionar vendedores</a></li>
      <li><a href="#">Subir productos (CSV/ZIP)</a></li>
      <li><a href="#">Galería de imágenes</a></li>
      <li><a href="#">Ver pedidos</a></li>
    </ul>
  </main>
</body>
</html>