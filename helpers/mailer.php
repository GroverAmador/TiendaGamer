<?php
// ============================================================
// NexusGear - Mailer (SMTP via SSL socket, sin librerías externas)
// ============================================================

class NexusMailer {

    private static function cfg(): array {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/../config/email_config.php';
        }
        return $config;
    }

    // ---- Enviar código 2FA por email ----
    public static function send2FA(string $toEmail, string $toName, string $code): bool {
        $subject = 'NexusGear — Código de verificación';
        $html    = self::template2FA($toName, $code);
        return self::send($toEmail, $toName, $subject, $html);
    }

    // ---- Función genérica de envío ----
    public static function send(string $to, string $toName, string $subject, string $html): bool {
        $cfg = self::cfg();

        // Modo demo: no envía pero devuelve true
        if (!$cfg['enabled']) {
            error_log("[NexusMailer DEMO] Para: $to | Asunto: $subject");
            return true;
        }

        try {
            $enc = strtolower($cfg['encryption'] ?? 'ssl');
            if ($enc === 'ssl') {
                $host = 'ssl://' . $cfg['host'];
            } else {
                $host = $cfg['host'];
            }

            // Contexto SSL
            $ctx = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            $socket = @stream_socket_client(
                $host . ':' . $cfg['port'],
                $errno, $errstr, 15,
                STREAM_CLIENT_CONNECT, $ctx
            );

            if (!$socket) {
                error_log("[NexusMailer] No se pudo conectar: $errstr ($errno)");
                return false;
            }

            stream_set_timeout($socket, 10);

            // Leer saludo
            self::smtpRead($socket);

            // EHLO
            self::smtpCmd($socket, 'EHLO ' . gethostname());

            // Si es TLS (STARTTLS en puerto 587)
            if ($enc === 'tls') {
                self::smtpCmd($socket, 'STARTTLS');
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                self::smtpCmd($socket, 'EHLO ' . gethostname());
            }

            // AUTH LOGIN
            self::smtpCmd($socket, 'AUTH LOGIN');
            self::smtpCmd($socket, base64_encode($cfg['user']));
            $authResp = self::smtpCmd($socket, base64_encode($cfg['pass']));

            if (strpos($authResp, '235') === false) {
                error_log('[NexusMailer] Auth fallida: ' . $authResp);
                fclose($socket);
                return false;
            }

            // MAIL FROM
            self::smtpCmd($socket, "MAIL FROM: <{$cfg['from_email']}>");
            self::smtpCmd($socket, "RCPT TO: <{$to}>");
            self::smtpCmd($socket, 'DATA');

            // Construir mensaje
            $boundary = 'NXG_' . md5(uniqid());
            $msg  = "From: =?UTF-8?B?" . base64_encode($cfg['from_name']) . "?= <{$cfg['from_email']}>\r\n";
            $msg .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$to}>\r\n";
            $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n";
            $msg .= "\r\n";
            $msg .= chunk_split(base64_encode($html), 76, "\r\n");
            $msg .= "\r\n.\r\n";

            fwrite($socket, $msg);
            self::smtpRead($socket);

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            return true;
        } catch (Throwable $e) {
            error_log('[NexusMailer] Exception: ' . $e->getMessage());
            return false;
        }
    }

    private static function smtpCmd($socket, string $cmd): string {
        fwrite($socket, $cmd . "\r\n");
        return self::smtpRead($socket);
    }

    private static function smtpRead($socket): string {
        $resp = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $resp .= $line;
            // Línea final del bloque: 4º carácter es espacio
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $resp;
    }

    // ---- Plantilla HTML del email de 2FA ----
    private static function template2FA(string $name, string $code): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Código de verificación NexusGear</title></head>
<body style="margin:0;padding:0;background:#0d0d1a;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0d0d1a;padding:40px 20px;">
<tr><td align="center">
<table width="580" cellpadding="0" cellspacing="0" style="background:#11111e;border-radius:16px;border:1px solid rgba(0,216,240,0.2);overflow:hidden;max-width:100%;">
  <!-- Header -->
  <tr><td style="background:linear-gradient(135deg,#7c3aed,#00d8f0);padding:32px 40px;text-align:center;">
    <h1 style="margin:0;color:#fff;font-size:28px;font-weight:800;letter-spacing:2px;">⚡ NEXUSGEAR</h1>
    <p style="margin:8px 0 0;color:rgba(255,255,255,0.85);font-size:14px;">El equipo gaming que mereces</p>
  </td></tr>
  <!-- Body -->
  <tr><td style="padding:40px;">
    <p style="color:#a0a0c0;font-size:16px;margin:0 0 8px;">Hola, <strong style="color:#e2e2f0;">{$name}</strong></p>
    <p style="color:#8b8ba8;font-size:15px;line-height:1.6;margin:0 0 28px;">
      Recibimos una solicitud de inicio de sesión. Tu código de verificación es:
    </p>
    <!-- Code box -->
    <div style="background:rgba(0,216,240,0.08);border:2px solid rgba(0,216,240,0.3);border-radius:12px;padding:24px;text-align:center;margin-bottom:28px;">
      <div style="font-size:48px;font-weight:900;letter-spacing:12px;color:#00d8f0;font-family:monospace;">{$code}</div>
      <p style="color:#8b8ba8;font-size:13px;margin:12px 0 0;">
        Este código expira en <strong style="color:#f59e0b;">5 minutos</strong>
      </p>
    </div>
    <p style="color:#4a4a65;font-size:13px;line-height:1.6;margin:0;">
      Si no solicitaste este código, ignora este mensaje. Tu cuenta permanece segura.
    </p>
  </td></tr>
  <!-- Footer -->
  <tr><td style="background:rgba(255,255,255,0.02);padding:20px 40px;border-top:1px solid rgba(255,255,255,0.06);">
    <p style="color:#4a4a65;font-size:12px;margin:0;text-align:center;">
      © 2025 NexusGear. Este es un mensaje automático, no respondas a este correo.
    </p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }
}
