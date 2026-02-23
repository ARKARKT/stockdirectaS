<?php
require_once __DIR__ . '/db.php';
$config = require __DIR__ . '/../config.php';

// send_email: usa PHPMailer si está disponible y la configuración SMTP está rellenada,
// si no, usa fallback a mail(). Registra en la tabla email_logs.
function send_email(string $to, string $subject, string $html, $fromEmail = null, $fromName = null, $related_order_id = null, $debug = false) {
    global $pdo, $config;
    $fromEmail = $fromEmail ?: $config['mail']['from_email'];
    $fromName = $fromName ?: $config['mail']['from_name'];

    $sent = false;
    $errorMessage = null;

    // Try PHPMailer if installed and SMTP host configured
    if (!empty($config['smtp']['host']) && class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            // Configurar SMTP
            if (!empty($config['smtp']['host'])) {
                $mail->isSMTP();
                $mail->Host = $config['smtp']['host'];
                $mail->SMTPAuth = $config['smtp']['auth'] ?? true;
                $mail->Username = $config['smtp']['username'] ?? '';
                $mail->Password = $config['smtp']['password'] ?? '';
                $mail->SMTPSecure = $config['smtp']['secure'] ?? '';
                $mail->Port = $config['smtp']['port'] ?? 587;
            }
            if ($debug) {
                // show SMTP conversation in the response (HTML-safe)
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = function($str, $level) {
                    echo '<pre style="white-space:pre-wrap;background:#f6f8fa;border:1px solid #e1e4e8;padding:8px;margin:8px 0;">' . htmlspecialchars($str) . '</pre>';
                };
            }
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $sent = $mail->send();
        } catch (Exception $e) {
            $sent = false;
            $errorMessage = $e->getMessage();
        }
    } else {
        // fallback to native mail()
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: ' . $fromName . ' <' . $fromEmail . '>' . "\r\n";
        $sent = mail($to, $subject, $html, $headers);
    }

    $status = $sent ? 'sent' : 'failed';
    $stmt = $pdo->prepare('INSERT INTO email_logs (to_email,subject,body,sent_at,status,related_order_id) VALUES (?,?,?,?,NOW(),?)');
    $stmt->execute([$to,$subject,$html,$status,$related_order_id]);

    if (!$sent && $errorMessage) error_log('Email error: ' . $errorMessage);
    return $sent;
}

// Helpers para plantillas de email
function _site_header_html() {
    global $config;
    $siteName = $config['site']['name'] ?? 'Mi tienda';
    $logo = $config['site']['logo_url'] ?? ($config['site']['logo_path'] ?? null);
    if ($logo) {
        return '<div style="text-align:center;margin-bottom:18px;"><img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($siteName) . '" style="max-height:80px;"></div>';
    }
    return '<h2 style="text-align:center;margin-bottom:18px;">' . htmlspecialchars($siteName) . '</h2>';
}

function build_order_status_change_buyer_html(array $order, array $items, array $vendor_info = [], string $new_status = null) {
    $status = $new_status ?? ($order['status'] ?? 'actualizado');
    $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pedido #' . htmlspecialchars($order['id']) . '</title></head><body style="font-family:Arial,Helvetica,sans-serif;color:#222;line-height:1.4;">';
    $html .= _site_header_html();
    $html .= '<div style="max-width:680px;margin:0 auto;padding:12px;">';
    $html .= '<p>Hola ' . htmlspecialchars($order['buyer_name'] ?? 'cliente') . ',</p>';
    $html .= '<p>El estado de tu pedido <strong>#' . htmlspecialchars($order['id']) . '</strong> ha cambiado a: <strong>' . htmlspecialchars($status) . '</strong>.</p>';

    $html .= '<h4 style="margin-bottom:6px">Detalles del pedido</h4>';
    $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:12px">';
    $html .= '<thead><tr><th style="text-align:left;border-bottom:1px solid #ddd;padding:6px">Producto</th><th style="text-align:right;border-bottom:1px solid #ddd;padding:6px">Cantidad</th><th style="text-align:right;border-bottom:1px solid #ddd;padding:6px">ARS</th><th style="text-align:right;border-bottom:1px solid #ddd;padding:6px">USD</th></tr></thead><tbody>';
    $total_ars = 0; $total_usd = 0;
    foreach ($items as $it) {
        $q = intval($it['qty']);
        $p_ars = floatval($it['price_ars'] ?? 0);
        $p_usd = floatval($it['price_usd'] ?? 0);
        $total_ars += $q * $p_ars;
        $total_usd += $q * $p_usd;
        $html .= '<tr><td style="padding:6px;border-bottom:1px solid #f2f2f2">' . htmlspecialchars($it['title_snapshot']) . '</td><td style="padding:6px;text-align:right;border-bottom:1px solid #f2f2f2">' . $q . '</td><td style="padding:6px;text-align:right;border-bottom:1px solid #f2f2f2">' . number_format($p_ars,2) . '</td><td style="padding:6px;text-align:right;border-bottom:1px solid #f2f2f2">' . number_format($p_usd,2) . '</td></tr>';
    }
    $html .= '</tbody><tfoot><tr><td style="padding:6px"></td><td style="padding:6px;text-align:right"><strong>Total</strong></td><td style="padding:6px;text-align:right"><strong>' . number_format($total_ars,2) . '</strong></td><td style="padding:6px;text-align:right"><strong>' . number_format($total_usd,2) . '</strong></td></tr></tfoot></table>';

    if (!empty($vendor_info['bank_details'])) {
        $bd = json_decode($vendor_info['bank_details'], true);
        if (is_array($bd)) {
            $html .= '<h4>Datos bancarios del vendedor</h4><ul style="padding-left:18px;">';
            foreach ($bd as $k=>$v) {
                $html .= '<li>' . htmlspecialchars((string)$k) . ': ' . htmlspecialchars((string)$v) . '</li>';
            }
            $html .= '</ul>';
        }
    }

    $html .= '<p>Si tienes consultas, responde este correo o contacta al vendedor.</p>';
    $html .= '<hr style="border:none;border-top:1px solid #eee;margin:18px 0;">';
    $html .= '<p style="font-size:13px;color:#666">Gracias por usar ' . htmlspecialchars($GLOBALS['config']['site']['name'] ?? 'nuestra tienda') . '.</p>';
    $html .= '</div></body></html>';
    return $html;
}

function build_order_status_change_vendor_html(array $order, array $items, array $buyer_info = [], string $new_status = null) {
    $status = $new_status ?? ($order['status'] ?? 'actualizado');
    $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pedido #' . htmlspecialchars($order['id']) . '</title></head><body style="font-family:Arial,Helvetica,sans-serif;color:#222;line-height:1.4;">';
    $html .= _site_header_html();
    $html .= '<div style="max-width:680px;margin:0 auto;padding:12px;">';
    $html .= '<p>Hola ' . htmlspecialchars($GLOBALS['config']['site']['name'] ?? 'vendedor') . ',</p>';
    $html .= '<p>El pedido <strong>#' . htmlspecialchars($order['id']) . '</strong> cambió su estado a: <strong>' . htmlspecialchars($status) . '</strong>.</p>';
    $html .= '<h4>Datos del comprador</h4>';
    $html .= '<p>' . htmlspecialchars($buyer_info['buyer_name'] ?? $order['buyer_name'] ?? '') . '<br>' . htmlspecialchars($buyer_info['buyer_email'] ?? $order['buyer_email'] ?? '') . '</p>';
    $html .= '<h4>Items</h4><ul>';
    foreach ($items as $it) {
        $html .= '<li>' . htmlspecialchars($it['title_snapshot']) . ' x' . intval($it['qty']) . ' — ARS ' . number_format($it['price_ars'] ?? 0,2) . ' / USD ' . number_format($it['price_usd'] ?? 0,2) . '</li>';
    }
    $html .= '</ul>';
    $html .= '<p>Accede al panel para ver más detalles.</p>';
    $html .= '<hr style="border:none;border-top:1px solid #eee;margin:18px 0;">';
    $html .= '</div></body></html>';
    return $html;
}
