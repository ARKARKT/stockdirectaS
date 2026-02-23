<?php
// Set currency cookie and redirect back
$allowed = ['ARS','USD'];
$currency = $_POST['currency'] ?? null;
$redirect = $_POST['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/');
if (!in_array($currency, $allowed)) $currency = 'ARS';
setcookie('currency', $currency, time() + (60*60*24*30), '/');
// ensure we redirect to a safe path (basic)
if (strpos($redirect, '/') !== 0 && strpos($redirect, 'http') !== 0) {
    $redirect = '/';
}
header('Location: ' . $redirect);
exit;
