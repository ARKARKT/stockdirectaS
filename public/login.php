<?php
require_once __DIR__ . '/../inc/auth.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $errors[] = 'Email y contraseña son requeridos.';
    } else {
        $user = attempt_login($email, $password);
        if ($user) {
            // redirigir según rol
            if ($user['role'] === 'admin') header('Location: /admin/dashboard.php');
            else if ($user['role'] === 'vendor') header('Location: /vendor/dashboard.php');
            else header('Location: /');
            exit;
        } else {
            $errors[] = 'Credenciales inválidas.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login - StockDirecta</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <main class="container auth">
    <h1>Ingresar</h1>
    <?php if ($errors): ?>
      <div class="errors">
        <ul>
        <?php foreach($errors as $e): ?>
          <li><?=htmlspecialchars($e)?></li>
        <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="">
      <label>Email<br>
        <input type="email" name="email" required>
      </label>
      <label>Contraseña<br>
        <input type="password" name="password" required>
      </label>
      <button type="submit" class="btn">Entrar</button>
    </form>

    <p class="muted">Si no tienes usuario, contacta al administrador.</p>
  </main>
</body>
</html>