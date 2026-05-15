<?php
// ============================================================
// NexusGear — TOTP Setup Page (escanear QR tras registro)
// ============================================================
session_start();

if (!isset($_SESSION['totp_setup_id'])) {
    header('Location: /nexusgear/auth/login.php'); exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/totp.php';

$uid    = (int)$_SESSION['totp_setup_id'];
$secret = $_SESSION['totp_setup_secret'] ?? '';
$email  = $_SESSION['totp_setup_email']  ?? '';
$name   = $_SESSION['totp_setup_name']   ?? '';
$error  = '';
$ok     = false;

// Verificar código de confirmación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = preg_replace('/\D/','',trim($_POST['codigo']??''));
    if (!$code) {
        $error = 'Ingresa el código de 6 dígitos de tu aplicación.';
    } elseif (!TOTP::verify($secret, $code)) {
        $error = 'Código incorrecto. Asegúrate de haber escaneado el QR y espera el siguiente código.';
    } else {
        // Confirmado — limpiar sesión de setup, redirigir a login
        unset($_SESSION['totp_setup_id'],$_SESSION['totp_setup_secret'],$_SESSION['totp_setup_email'],$_SESSION['totp_setup_name']);
        $_SESSION['flash_message'] = '¡Cuenta creada con Autenticador! Ahora inicia sesión.';
        $_SESSION['flash_type']    = 'success';
        header('Location: /nexusgear/auth/login.php'); exit;
    }
}

$qrUrl   = TOTP::getQRImageUrl($secret, $email);
$manualUrl = TOTP::getOtpauthUrl($secret, $email);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configura tu Autenticador — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <style>
        body { background:var(--bg-primary); display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
        body::before { content:''; position:fixed; inset:0; background-image:radial-gradient(rgba(124,58,237,.06) 1px,transparent 1px); background-size:28px 28px; pointer-events:none; }
        .setup-card { background:var(--bg-card); border:1px solid var(--border-violet); border-radius:var(--radius-xl); padding:36px; width:100%; max-width:500px; box-shadow:0 0 48px rgba(124,58,237,.1); }
        .step { display:flex; gap:12px; margin-bottom:16px; }
        .step-num { width:28px; height:28px; border-radius:50%; background:var(--gradient-brand); display:flex; align-items:center; justify-content:center; font-size:.78rem; font-weight:800; color:#fff; flex-shrink:0; margin-top:2px; }
        .qr-box { background:var(--bg-card-2); border:2px solid var(--border-violet); border-radius:var(--radius-md); padding:16px; display:flex; align-items:center; justify-content:center; margin:16px 0; }
        .secret-box { background:rgba(124,58,237,.08); border:1px solid rgba(124,58,237,.2); border-radius:var(--radius-sm); padding:10px 14px; font-family:monospace; font-size:1rem; letter-spacing:3px; color:var(--neon-violet); text-align:center; word-break:break-all; margin-bottom:8px; }
        .otp-row { display:flex; gap:7px; justify-content:center; margin:16px 0; }
        .otp-box { width:48px; height:56px; text-align:center; font-size:1.3rem; font-weight:700; font-family:'Oxanium',monospace; background:var(--bg-input); border:2px solid var(--border-subtle); border-radius:var(--radius-sm); color:var(--text-primary); outline:none; transition:border-color .2s,color .2s; }
        .otp-box:focus { border-color:var(--neon-violet); color:var(--neon-violet); }
        .otp-box.filled { border-color:var(--neon-violet); color:var(--neon-violet); }
        .apps-strip { display:flex; gap:8px; flex-wrap:wrap; margin:10px 0; }
        .app-pill { background:rgba(255,255,255,.04); border:1px solid var(--border-subtle); border-radius:20px; padding:5px 12px; font-size:.78rem; color:var(--text-secondary); display:inline-flex; align-items:center; gap:5px; }
    </style>
</head>
<body>
<div id="toast-container"></div>
<div class="setup-card">

    <!-- Header -->
    <div style="text-align:center;margin-bottom:24px;">
        <div style="width:60px;height:60px;border-radius:50%;background:rgba(124,58,237,.15);border:2px solid rgba(124,58,237,.35);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
            <i class="bi bi-qr-code" style="font-size:2rem;color:var(--neon-violet);"></i>
        </div>
        <h1 style="font-family:'Oxanium',sans-serif;font-size:1.5rem;color:var(--text-primary);margin-bottom:4px;">
            Configura tu Autenticador
        </h1>
        <p style="color:var(--text-muted);font-size:.85rem;">
            Hola <strong style="color:var(--text-primary);"><?= htmlspecialchars($name) ?></strong>, escanea el QR con tu app
        </p>
    </div>

    <!-- Apps compatibles -->
    <div style="margin-bottom:18px;">
        <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:6px;">
            <i class="bi bi-phone me-1"></i>Aplicaciones compatibles:
        </div>
        <div class="apps-strip">
            <span class="app-pill"><i class="bi bi-google"></i>Google Authenticator</span>
            <span class="app-pill"><i class="bi bi-shield-check"></i>Authy</span>
            <span class="app-pill"><i class="bi bi-microsoft"></i>Microsoft Auth.</span>
            <span class="app-pill"><i class="bi bi-1-circle"></i>1Password</span>
        </div>
    </div>

    <!-- Pasos -->
    <div class="step">
        <div class="step-num">1</div>
        <div style="color:var(--text-secondary);font-size:.88rem;">
            Abre tu aplicación autenticadora y toca <strong style="color:var(--text-primary);">"+"</strong> o <strong style="color:var(--text-primary);">"Agregar cuenta"</strong>, luego selecciona <em>Escanear código QR</em>.
        </div>
    </div>

    <!-- QR -->
    <div class="qr-box">
        <img src="<?= htmlspecialchars($qrUrl) ?>"
             alt="QR NexusGear TOTP"
             style="width:200px;height:200px;border-radius:8px;"
             onerror="this.outerHTML='<div style=\'padding:20px;color:var(--neon-pink);font-size:.85rem;\'>Error cargando QR. Usa la clave manual abajo.</div>'">
    </div>

    <!-- Clave manual -->
    <div class="step">
        <div class="step-num">2</div>
        <div style="width:100%;">
            <div style="color:var(--text-secondary);font-size:.88rem;margin-bottom:8px;">
                Si no puedes escanear el QR, ingresa esta clave manualmente:
            </div>
            <div class="secret-box"><?= htmlspecialchars($secret) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted);">
                Tipo: TOTP • Algoritmo: SHA1 • Dígitos: 6 • Período: 30s
            </div>
        </div>
    </div>

    <div class="step">
        <div class="step-num">3</div>
        <div style="color:var(--text-secondary);font-size:.88rem;">
            Ingresa el código de <strong style="color:var(--text-primary);">6 dígitos</strong> que muestra tu app para confirmar la configuración:
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger mb-3 text-start" style="font-size:.84rem;">
        <i class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="form-totp-setup">
        <input type="hidden" name="codigo" id="otp-full">
        <div class="otp-row" id="otp-row">
            <?php for ($i=0;$i<6;$i++): ?>
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="ob<?= $i ?>">
            <?php endfor; ?>
        </div>
        <button type="submit" class="btn btn-neon w-100" style="padding:13px;">
            <i class="bi bi-shield-check me-2"></i>Confirmar y activar 2FA
        </button>
    </form>

    <div style="text-align:center;margin-top:16px;">
        <a href="/nexusgear/auth/login.php" style="color:var(--text-muted);font-size:.82rem;display:inline-flex;align-items:center;gap:4px;">
            <i class="bi bi-arrow-left"></i>Cancelar y volver al inicio
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script>
(function() {
    const boxes   = Array.from(document.querySelectorAll('.otp-box'));
    const fullInp = document.getElementById('otp-full');

    function updateFull() { if(fullInp) fullInp.value=boxes.map(b=>b.value).join(''); }

    boxes.forEach((box,i) => {
        box.addEventListener('keyup', function() {
            this.value=this.value.replace(/\D/g,'').slice(-1);
            this.classList.toggle('filled', !!this.value);
            updateFull();
            if (this.value && i<5) boxes[i+1].focus();
            if (boxes.every(b=>b.value)) { updateFull(); document.getElementById('form-totp-setup').submit(); }
        });
        box.addEventListener('keydown', function(e) {
            if(e.key==='Backspace'&&!this.value&&i>0){boxes[i-1].value='';boxes[i-1].classList.remove('filled');boxes[i-1].focus();updateFull();}
        });
        box.addEventListener('focus', function(){this.select();});
    });

    document.getElementById('otp-row')?.addEventListener('paste', e=>{
        e.preventDefault();
        const d=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
        d.split('').forEach((c,i)=>{if(boxes[i]){boxes[i].value=c;boxes[i].classList.add('filled');}});
        updateFull();
        const nx=boxes.findIndex(b=>!b.value);
        (nx>=0?boxes[nx]:boxes[5])?.focus();
    });

    boxes[0]?.focus();
})();
</script>
</body>
</html>
