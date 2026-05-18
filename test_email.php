<?php
// ============================================================
// NexusGear — Diagnóstico SMTP (BORRAR antes de entregar)
// Accede en: http://localhost/nexusgear/test_email.php
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['run'])) {
    ?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Test SMTP — NexusGear</title>
<style>body{font-family:monospace;background:#0d0d1a;color:#e2e2f0;padding:30px;max-width:700px;margin:0 auto;}
h2{color:#00d8f0;}pre{background:#11111e;border:1px solid #333;padding:16px;border-radius:8px;white-space:pre-wrap;word-break:break-all;}
.ok{color:#84cc16;}.err{color:#f43f8e;}.warn{color:#f59e0b;}
input{background:#11111e;border:1px solid #444;color:#e2e2f0;padding:8px 12px;border-radius:6px;width:320px;}
button{background:#00d8f0;color:#0d0d1a;font-weight:bold;border:none;padding:10px 24px;border-radius:8px;cursor:pointer;margin-top:10px;}
</style></head>
<body>
<h2>⚡ Diagnóstico SMTP — NexusGear</h2>
<form method="POST">
    <p>Correo de destino para la prueba:</p>
    <input type="email" name="dest" value="taborgarafa@gmail.com" required><br>
    <button type="submit">Ejecutar prueba SMTP</button>
</form>
</body></html><?php
    exit;
}

// ---- Ejecutar diagnóstico ----
$dest = filter_var($_POST['dest'] ?? 'taborgarafa@gmail.com', FILTER_SANITIZE_EMAIL);

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Test SMTP</title>
<style>body{font-family:monospace;background:#0d0d1a;color:#e2e2f0;padding:30px;max-width:700px;margin:0 auto;}
h2{color:#00d8f0;}pre{background:#11111e;border:1px solid #333;padding:16px;border-radius:8px;white-space:pre-wrap;word-break:break-all;}
.ok{color:#84cc16;}.err{color:#f43f8e;}.warn{color:#f59e0b;}.step{margin:6px 0;}
</style></head><body><h2>⚡ Diagnóstico SMTP</h2><pre>";

$cfg = require __DIR__ . '/config/email_config.php';
$log = [];

function step(string $label, bool $ok, string $detail = ''): void {
    $icon = $ok ? '✔' : '✘';
    $cls  = $ok ? 'ok' : 'err';
    echo "<span class='step $cls'>$icon $label</span>" . ($detail ? " — $detail" : '') . "\n";
    flush();
}

function warn(string $msg): void {
    echo "<span class='warn'>⚠ $msg</span>\n";
    flush();
}

// 1. Config
echo "=== 1. Configuración ===\n";
step('enabled = true',   $cfg['enabled']);
step('host = smtp.gmail.com', $cfg['host'] === 'smtp.gmail.com', $cfg['host']);
step('port = 465',       $cfg['port'] === 465, (string)$cfg['port']);
step('encryption = ssl', strtolower($cfg['encryption']) === 'ssl', $cfg['encryption']);
step('user configurado', !empty($cfg['user']), $cfg['user']);
step('pass configurado', !empty($cfg['pass']), str_repeat('*', strlen($cfg['pass'])));
echo "\n";

// 2. Conectividad TCP
echo "=== 2. Conexión TCP a smtp.gmail.com:465 ===\n";
$ctx = stream_context_create([
    'ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ],
]);

$t0 = microtime(true);
$socket = @stream_socket_client('ssl://smtp.gmail.com:465', $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
$elapsed = round((microtime(true) - $t0) * 1000);

if (!$socket) {
    step("Conexión SSL", false, "Error $errno: $errstr");
    warn("El firewall de Windows o antivirus está bloqueando el puerto 465.");
    warn("Solución: permite PHP (php.exe) en el Firewall de Windows.");
    echo "</pre></body></html>";
    exit;
}

step("Conexión SSL abierta", true, "{$elapsed}ms");
stream_set_timeout($socket, 10);

// 3. Saludo
echo "\n=== 3. Conversación SMTP ===\n";
function smtpLine($socket): string {
    $r = '';
    while (!feof($socket)) {
        $line = fgets($socket, 512);
        if ($line === false) break;
        $r .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') break;
    }
    return trim($r);
}
function smtpCmd($socket, string $cmd): string {
    fwrite($socket, $cmd . "\r\n");
    return smtpLine($socket);
}

$greeting = smtpLine($socket);
step("Saludo del servidor", str_starts_with($greeting, '220'), $greeting);

$ehlo = smtpCmd($socket, 'EHLO nexusgear.local');
step("EHLO", str_starts_with($ehlo, '250'), substr($ehlo, 0, 80));

// Auth
$r = smtpCmd($socket, 'AUTH LOGIN');
step("AUTH LOGIN", str_starts_with($r, '334'), $r);

$r = smtpCmd($socket, base64_encode($cfg['user']));
step("Usuario enviado", str_starts_with($r, '334'), $r);

$authResp = smtpCmd($socket, base64_encode($cfg['pass']));
$authOk   = str_starts_with($authResp, '235');
step("Autenticación", $authOk, $authResp);

if (!$authOk) {
    echo "\n";
    warn("AUTENTICACIÓN FALLIDA — Causas comunes:");
    warn("1. El App Password fue revocado por Google.");
    warn("   → Ve a myaccount.google.com/apppasswords y genera uno nuevo.");
    warn("2. Verificación en 2 pasos desactivada en tu cuenta Google.");
    warn("   → Sin 2FA activado, los App Passwords no funcionan.");
    warn("3. Copia incorrecta del App Password (con espacios).");
    warn("   → Debe ser exactamente 16 caracteres sin espacios.");
    fclose($socket);
    echo "</pre></body></html>";
    exit;
}

// Envío real
echo "\n=== 4. Envío de correo de prueba ===\n";
smtpCmd($socket, "MAIL FROM: <{$cfg['from_email']}>");
smtpCmd($socket, "RCPT TO: <$dest>");
$r = smtpCmd($socket, 'DATA');
step("DATA aceptado", str_starts_with($r, '354'), $r);

$body  = base64_encode("<h2 style='color:#00d8f0'>✅ NexusGear SMTP funciona</h2><p>Este es un correo de prueba del sistema de diagnóstico.</p>");
$msg   = "From: NexusGear <{$cfg['from_email']}>\r\n";
$msg  .= "To: <$dest>\r\n";
$msg  .= "Subject: =?UTF-8?B?" . base64_encode('NexusGear — Prueba SMTP OK') . "?=\r\n";
$msg  .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
$msg  .= chunk_split($body, 76, "\r\n");
$msg  .= "\r\n.\r\n";

fwrite($socket, $msg);
$sendResp = smtpLine($socket);
$sendOk   = str_starts_with($sendResp, '250');
step("Correo enviado", $sendOk, $sendResp);

smtpCmd($socket, 'QUIT');
fclose($socket);

echo "\n";
if ($sendOk) {
    echo "<span class='ok'>✔ ¡Todo OK! Revisa la bandeja de $dest (también Spam).</span>\n";
} else {
    warn("El correo fue rechazado por el servidor. Respuesta: $sendResp");
}

echo "</pre></body></html>";
