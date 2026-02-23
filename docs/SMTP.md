# Configurar SMTP con PHPMailer

Esta guía explica cómo instalar y configurar PHPMailer en el proyecto para enviar emails fiables mediante SMTP.

Requisitos
- PHP 7.4+ (recomendado PHP 8+)
- Extensión `openssl` habilitada si usas `ssl`/`tls`
- Acceso para ejecutar Composer en el servidor (o subir el `vendor/` generado desde tu equipo)

1) Instalar PHPMailer (recomendado con Composer)

En el directorio del proyecto ejecuta:

```bash
composer require phpmailer/phpmailer
```

Si no puedes usar Composer en el servidor, ejecuta `composer require` en tu máquina y sube la carpeta `vendor/` al servidor.

2) Rellenar `config.php`

Abre `config.php` y coloca los parámetros SMTP que te proporcione tu proveedor (Gmail, SendGrid, un servidor propio, etc.):

- `host`: servidor SMTP (p.ej. `smtp.gmail.com`)
- `port`: 587 para TLS, 465 para SSL (o 25/587 según proveedor)
- `username`: usuario SMTP
- `password`: contraseña o API key
- `secure`: `tls`, `ssl` o `""` según proveedor

Ejemplo:

```php
'smtp' => [
    'host' => 'smtp.example.com',
    'port' => 587,
    'username' => 'usuario@example.com',
    'password' => 'secret',
    'secure' => 'tls',
    'auth' => true
]
```

3) Probar envío desde la app (script de prueba)

He añadido `public/smtp_test.php` que usa la función `send_email()` del proyecto para enviar un correo de prueba. Para usarlo:

- Sube los cambios al servidor y asegúrate de que `vendor/` (PHPMailer) esté presente.
- Abre en el navegador: `https://tu-dominio/smtp_test.php` o ejecútalo vía CLI.

El script mostrará si el envío se realizó correctamente o no, y registrará el intento en la tabla `email_logs`.

4) Notas y problemas comunes
- Gmail: puede requerir crear una contraseña de aplicación o usar OAuth2; para Gmail SMTP tradicional muchas cuentas requieren habilitar "Less secure apps" (no recomendado).
- Firewalls: asegúrate de que el servidor tenga salida al puerto SMTP (25/587/465).
- Registro: los fallos se registran en `email_logs` y en el `error_log` del servidor.

5) Siguiente pasos
- Si quieres, puedo añadir soporte para variables de entorno (`.env`) para no guardar credenciales en el repo, y crear un script de diagnóstico más detallado.
