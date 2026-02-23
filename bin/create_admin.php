<?php
// Uso: php bin/create_admin.php admin@example.com Password123 "Admin Name"
$cfg = require __DIR__ . '/../config.php';
$db = $cfg['db'];
$host = $db['host'] ?? '127.0.0.1';
$port = getenv('DB_PORT') ?: 3306;
$name = $argv[3] ?? 'Admin';
$email = $argv[1] ?? 'admin@example.com';
$password = $argv[2] ?? 'admin123';
$charset = $db['charset'] ?? 'utf8mb4';
$dsn = "mysql:host={$host};port={$port};dbname={$db['name']};charset={$charset}";
try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "Fallo al conectar a la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,role,created_at) VALUES (?,?,?,?,NOW())');
try {
    $stmt->execute([$name, $email, $hash, 'admin']);
    echo "Usuario admin creado correctamente: {$email}\n";
} catch (Exception $e) {
    echo "Error al crear admin: " . $e->getMessage() . "\n";
    exit(1);
}
