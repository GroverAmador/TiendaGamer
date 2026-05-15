<?php
// ============================================================
// NexusGear — Verify 2FA (Email + TOTP) v2
// ============================================================
session_start();
if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: /nexusgear/auth/login.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../controllers/auth_controller.php';
}

$metodo    = $_SESSION['2fa_metodo'] ?? 'email';
$code      = $_SESSION['codigo_2fa'] ?? '';
$expira    = (int)($_SESSION['2fa_expira'] ?? 0);
$remaining = max(0, $expira - time());
$error     = $_SESSION['2fa_error'] ?? '';
$emailSent = $_SESSION['2fa_email_sent'] ?? null;
$correo    = $_SESSION['2fa_correo']   ?? 'tu correo';
unset($_SESSION['2fa_error']);

// CSRF para los formularios
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = htmlspecialchars($_SESSION['csrf_token']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación 2FA — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <style>
        body { background:var(--bg-primary); display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
        body::before { content:''; position:fixed; inset:0; background-image:radial-gradient(rgba(0,216,240,0.05) 1px,transparent 1px); background-size:28px 28px; pointer-events:none; }
        .tfa-card { background:var(--bg-card); border:1px solid var(--border-glow); border-radius:var(--radius-xl); padding:40px; width:100%; max-width:460px; box-shadow:0 0 48px rgba(0,216,240,.08); animation:scaleBounce .35s ease; text-align:center; position:relative; }
        .method-badge { display:inline-flex;align-items:center;gap:8px; background:rgba(0,216,240,.1); border:1px solid rgba(0,216,240,.25); border-radius:20px; padding:5px 14px; font-size:.82rem; color:var(--neon-cyan); margin-bottom:20px; }

        /* OTP boxes */
        .otp-row { display:flex; gap:8px; justify-content:center; margin:20px 0; }
        .otp-box { width:50px; height:60px; text-align:center; font-size:1.4rem; font-weight:700; font-family:'Oxanium',monospace; background:var(--bg-input); border:2px solid var(--border-subtle); border-radius:var(--radius-sm); color:var(--text-primary); outline:none; transition:border-color .2s,box-shadow .2s,color .2s; }
        .otp-box:focus { border-color:var(--neon-cyan); box-shadow:0 0 0 3px rgba(0,216,240,.12); color:var(--neon-cyan); }
        .otp-box.filled { border-color:var(--neon-violet); color:var(--neon-violet); }

        /* Timer */
        .timer { display:inline-flex; align-items:center; gap:6px; font-size:1rem; font-weight:600; color:var(--text-secondary); font-family:'Oxanium',monospace; margin:12px 0 20px; }
        .timer.urgent { color:var(--neon-pink); animation:pulse 1s step-end infinite; }

        /* TOTP section */
        .totp-hint { background:rgba(124,58,237,.08); border:1px solid rgba(124,58,237,.2); border-radius:var(--radius-md); padding:16px; margin:16px 0; text-align:left; }
    </style>
</head>
<body>
<div id="toast-container"></div>

<div class="tfa-card">

    <!-- Icono / método -->
    <div style="width:68px;height:68px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;animation:neonGlow 3s ease-in-out infinite;" style="<?= $metodo==='totp' ? 'background:rgba(124,58,237,.12);border:2px solid rgba(124,58,237,.3);' : 'background:rgba(0,216,240,.1);border:2px solid rgba(0,216,240,.25);' ?>">
        <?php if ($metodo === 'totp'): ?>
        <i class="bi bi-qr-code-scan" style="font-size:2rem;color:var(--neon-violet);"></i>
        <?php else: ?>
        <i class="bi bi-shield-lock-fill" style="font-size:2rem;color:var(--neon-cyan);"></i>
        <?php endif; ?>
    </div>

    <h1 style="font-family:'Oxanium',sans-serif;color:var(--text-primary);font-size:1.6rem;margin-bottom:6px;">
        Verificación en 2 pasos
    </h1>

    <!-- Método badge -->
    <div class="method-badge">
        <?php if ($metodo === 'totp'): ?>
        <i class="bi bi-phone-fill"></i> Aplicación Autenticadora
        <?php else: ?>
        <i class="bi bi-envelope-fill"></i> Código por correo
        <?php endif; ?>
    </div>

    <?php if ($metodo === 'email'): ?>
    <!-- EMAIL METHOD -->
    <p style="color:var(--text-secondary);font-size:.88rem;line-height:1.6;margin-bottom:16px;">
        Enviamos un código de 6 dígitos a<br>
        <strong style="color:var(--neon-cyan);"><?= htmlspecialchars($correo) ?></strong>
    </p>

    <!-- Mostrar código si el email no se envió (modo demo o SMTP no config) -->
    <?php if ($emailSent === false || $emailSent === null): ?>
    <div style="background:rgba(0,216,240,.07);border:1px solid rgba(0,216,240,.2);border-radius:var(--radius-md);padding:14px;margin-bottom:14px;">
        <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:4px;">
            <i class="bi bi-info-circle me-1"></i>
            <?= $emailSent === false ? 'Email no enviado (configura SMTP en email_config.php). Código de demo:' : 'Código de verificación:' ?>
        </div>
        <div style="font-family:'Oxanium',monospace;font-size:1.6rem;font-weight:900;color:var(--neon-cyan);letter-spacing:8px;">
            <?= htmlspecialchars($code) ?>
        </div>
    </div>
    <?php elseif ($emailSent === true): ?>
    <div style="background:rgba(132,204,22,.08);border:1px solid rgba(132,204,22,.2);border-radius:var(--radius-md);padding:12px;margin-bottom:14px;font-size:.82rem;color:var(--neon-green);">
        <i class="bi bi-check-circle-fill me-1"></i>
        Código enviado al correo registrado. Revisa tu bandeja de entrada.
    </div>
    <?php endif; ?>

    <?php elseif ($metodo === 'totp'): ?>
    <!-- TOTP METHOD -->
    <div class="totp-hint">
        <div style="font-weight:600;color:var(--text-primary);margin-bottom:8px;font-size:.88rem;">
            <i class="bi bi-phone me-1" style="color:var(--neon-violet);"></i>
            Cómo obtener tu código:
        </div>
        <ol style="color:var(--text-secondary);font-size:.82rem;padding-left:18px;margin:0;line-height:1.8;">
            <li>Abre tu aplicación autenticadora<br>
                <span style="color:var(--text-muted);font-size:.78rem;">(Google Authenticator, Authy, Microsoft Authenticator…)</span>
            </li>
            <li>Busca la cuenta <strong style="color:var(--neon-cyan);">NexusGear</strong></li>
            <li>Ingresa el código de 6 dígitos mostrado</li>
        </ol>
        <div style="margin-top:10px;font-size:.78rem;color:var(--text-muted);">
            <i class="bi bi-clock me-1"></i>El código se renueva cada 30 segundos.
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger mb-3 text-start" style="font-size:.84rem;">
        <i class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Formulario de verificación -->
    <form method="POST" id="form-2fa">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action"     value="verify_2fa">
        <input type="hidden" name="codigo"     id="otp-full">

        <!-- 6 cajas OTP -->
        <div class="otp-row" id="otp-row">
            <?php for ($i=0;$i<6;$i++): ?>
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]"
                   id="ob<?= $i ?>"
                   <?= ($metodo==='email' && $remaining<=0) ? 'disabled' : '' ?>>
            <?php endfor; ?>
        </div>

        <!-- Timer (solo email) -->
        <?php if ($metodo === 'email'): ?>
        <div class="timer" id="timer-wrap">
            <i class="bi bi-alarm"></i>
            Expira en: <span id="timer-val">--:--</span>
        </div>
        <?php else: ?>
        <div style="margin:10px 0 20px;font-size:.8rem;color:var(--text-muted);">
            <i class="bi bi-arrow-repeat me-1"></i>El código TOTP es válido por ~30 segundos
        </div>
        <?php endif; ?>

        <!-- Botón verificar -->
        <button type="submit" id="btn-verify" class="btn btn-neon w-100"
                style="padding:13px;font-size:.95rem;"
                <?= ($metodo==='email' && $remaining<=0) ? 'disabled' : '' ?>>
            <i class="bi bi-check-circle-fill me-2"></i>Verificar
        </button>
    </form>

    <!-- Reenviar (solo email) -->
    <?php if ($metodo === 'email'): ?>
    <div style="margin-top:16px;">
        <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action"     value="resend_2fa">
            <button type="submit" id="btn-resend"
                    style="background:none;border:none;color:var(--text-muted);font-size:.84rem;cursor:pointer;text-decoration:underline;padding:0;transition:color .2s;"
                    onmouseover="this.style.color='var(--neon-cyan)'"
                    onmouseout="this.style.color='var(--text-muted)'">
                <i class="bi bi-arrow-clockwise me-1"></i>Reenviar código
            </button>
        </form>
    </div>
    <?php endif; ?>

    <div style="margin-top:14px;">
        <a href="/nexusgear/auth/login.php"
           style="color:var(--text-muted);font-size:.82rem;display:inline-flex;align-items:center;gap:4px;">
            <i class="bi bi-arrow-left"></i>Volver al inicio de sesión
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script>
(function(){
    const boxes    = Array.from(document.querySelectorAll('.otp-box'));
    const fullInp  = document.getElementById('otp-full');
    const timerW   = document.getElementById('timer-wrap');
    const timerV   = document.getElementById('timer-val');
    const btnV     = document.getElementById('btn-verify');
    const metodo   = '<?= $metodo ?>';
    let remaining  = <?= (int)$remaining ?>;
    let ticker;

    function updateFull() {
        if (fullInp) fullInp.value = boxes.map(b => b.value).join('');
    }

    boxes.forEach((box, i) => {
        box.addEventListener('keyup', function(e) {
            this.value = this.value.replace(/\D/g,'').slice(-1);
            this.classList.toggle('filled', !!this.value);
            updateFull();
            if (this.value && i < 5) boxes[i+1].focus();
            // Auto-submit when 6 digits filled
            if (boxes.every(b => b.value) && (metodo==='totp' || remaining>0)) {
                updateFull();
                document.getElementById('form-2fa').submit();
            }
        });
        box.addEventListener('keydown', function(e) {
            if (e.key==='Backspace' && !this.value && i>0) {
                boxes[i-1].value=''; boxes[i-1].classList.remove('filled');
                boxes[i-1].focus(); updateFull();
            }
        });
        box.addEventListener('focus', function() { this.select(); });
    });

    // Paste support
    document.getElementById('otp-row')?.addEventListener('paste', e => {
        e.preventDefault();
        const digits = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
        digits.split('').forEach((d,i) => { if(boxes[i]){boxes[i].value=d;boxes[i].classList.add('filled');} });
        updateFull();
        const next = boxes.findIndex(b=>!b.value);
        (next>=0?boxes[next]:boxes[5])?.focus();
    });

    // Timer solo para email
    if (metodo === 'email') {
        function fmt(s) { return String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0'); }
        function tick() {
            if (remaining <= 0) {
                clearInterval(ticker);
                if (timerV) timerV.textContent = '00:00';
                if (timerW) timerW.classList.add('urgent');
                if (btnV) btnV.disabled = true;
                boxes.forEach(b => b.disabled = true);
                showToast('El código ha expirado. Solicita uno nuevo.', 'warning');
                return;
            }
            if (timerV) timerV.textContent = fmt(remaining);
            if (remaining <= 60 && timerW) timerW.classList.add('urgent');
            remaining--;
        }
        if (remaining > 0) { tick(); ticker = setInterval(tick, 1000); boxes[0]?.focus(); }
        else { if(timerV)timerV.textContent='00:00'; if(timerW)timerW.classList.add('urgent'); if(btnV)btnV.disabled=true; }
    } else {
        // TOTP: focus inmediato
        boxes[0]?.focus();
    }
})();
</script>
</body>
</html>
