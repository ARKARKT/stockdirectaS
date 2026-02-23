<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

function current_user() {
    if (empty($_SESSION['user_id'])) return null;
    global $pdo;
    $stmt = $pdo->prepare('SELECT id,name,email,role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function require_role($role) {
    require_login();
    $user = current_user();
    if (!$user || $user['role'] !== $role) {
        http_response_code(403);
        echo "Acceso denegado";
        exit;
    }
}

function attempt_login($email, $password) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id,password_hash,role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u) return false;
    if (password_verify($password, $u['password_hash'])) {
        $_SESSION['user_id'] = $u['id'];
        return $u;
    }
    return false;
}

// CSRF helpers
function csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf_token'];
}

function validate_csrf($token) {
    if (empty($_SESSION['_csrf_token'])) return false;
    return hash_equals($_SESSION['_csrf_token'], (string)$token);
}
