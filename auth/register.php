<?php
// ============================================================
// NexusGear — Register v2 (con selección de método 2FA)
// ============================================================
session_start();
if (isset($_SESSION['id_usuario'])) { header('Location: /nexusgear/client/productos.php'); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../controllers/auth_controller.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <style>
        body{background:var(--bg-primary);min-height:100vh;display:flex;align-items:stretch;}
        .auth-left{background:linear-gradient(155deg,#1a0d3e 0%,#0d1a2e 60%,#080e1a 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px;position:relative;overflow:hidden;}
        .auth-left::before{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(124,58,237,.12) 1px,transparent 1px);background-size:26px 26px;}
        .auth-left::after{content:'';position:absolute;width:320px;height:320px;border-radius:50%;border:1px solid rgba(124,58,237,.15);top:-80px;right:-80px;}
        .auth-right{display:flex;align-items:center;justify-content:center;padding:40px;background:var(--bg-primary);}
        .auth-card{width:100%;max-width:440px;}
        /* Método 2FA selector */
        .method-option{border:2px solid var(--border-subtle);border-radius:var(--radius-md);padding:14px 16px;cursor:pointer;transition:border-color .2s,background .2s;display:flex;align-items:flex-start;gap:12px;}
        .method-option:hover{border-color:rgba(0,216,240,.3);background:rgba(0,216,240,.04);}
        .method-option input[type=radio]{display:none;}
        .method-option.selected{border-color:var(--neon-cyan);background:rgba(0,216,240,.07);}
        .method-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    </style>
</head>
<body>
<div id="toast-container"></div>

<div class="auth-left col-lg-5 d-none d-lg-flex">
    <div style="position:relative;z-index:1;text-align:center;">
        <div style="font-family:'Oxanium',sans-serif;font-size:2rem;font-weight:800;color:#fff;margin-bottom:12px;">
            <i class="bi bi-lightning-charge-fill" style="color:#00d8f0;margin-right:6px;"></i>NexusGear
        </div>
        <p style="color:rgba(255,255,255,.75);font-size:.95rem;line-height:1.7;max-width:300px;">
            Únete a miles de gamers.<br>Hardware premium al mejor precio.
        </p>
        <div style="margin-top:32px;display:flex;gap:24px;justify-content:center;">
            <?php foreach ([['500+','Productos'],['10k+','Clientes'],['99%','Satisfacción']] as $s): ?>
            <div style="text-align:center;">
                <div style="font-family:'Oxanium',sans-serif;font-size:1.5rem;font-weight:800;color:#fff;"><?= $s[0] ?></div>
                <div style="color:rgba(255,255,255,.6);font-size:.78rem;"><?= $s[1] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="auth-right col-lg-7">
    <div class="auth-card">
        <div class="d-lg-none text-center mb-4">
            <a href="/nexusgear/index.php" style="font-family:'Oxanium',sans-serif;font-size:1.4rem;font-weight:800;color:var(--text-primary);">
                <i class="bi bi-lightning-charge-fill" style="color:var(--neon-cyan);margin-right:4px;"></i>NexusGear
            </a>
        </div>

        <h1 style="font-family:'Oxanium',sans-serif;font-size:1.7rem;color:var(--text-primary);margin-bottom:4px;">Crear Cuenta</h1>
        <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:24px;">Regístrate para acceder a los mejores equipos gaming</p>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-3" style="font-size:.84rem;">
            <i class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="register">

            <div class="mb-3">
                <label class="form-label"><i class="bi bi-person me-1"></i>Nombre completo</label>
                <input type="text" name="nombre" class="form-control" placeholder="Tu nombre completo" required
                       value="<?= htmlspecialchars($_POST['nombre']??'') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-envelope me-1"></i>Correo electrónico</label>
                <input type="email" name="correo" class="form-control" placeholder="correo@ejemplo.com" required
                       value="<?= htmlspecialchars($_POST['correo']??'') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-lock me-1"></i>Contraseña</label>
                <div class="pw-wrap">
                    <input type="password" name="contrasena" id="pw" class="form-control" placeholder="Mínimo 8 caracteres" required>
                    <button type="button" class="pw-toggle" onclick="togglePw('pw',this)">👁</button>
                </div>
                <div class="strength-bar mt-2"><div class="strength-fill" id="sf"></div></div>
                <span class="strength-label" id="sl"></span>
            </div>
            <div class="mb-4">
                <label class="form-label"><i class="bi bi-lock-fill me-1"></i>Confirmar contraseña</label>
                <div class="pw-wrap">
                    <input type="password" name="confirmar_contrasena" id="pw2" class="form-control" placeholder="Repite tu contraseña" required>
                    <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)">👁</button>
                </div>
            </div>

            <!-- Selección método 2FA -->
            <div class="mb-4">
                <label class="form-label" style="font-weight:600;color:var(--text-primary);">
                    <i class="bi bi-shield-lock me-1" style="color:var(--neon-cyan);"></i>
                    Método de verificación en 2 pasos
                </label>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <label class="method-option selected" id="opt-email" onclick="selectMethod('email')">
                        <input type="radio" name="metodo_2fa" value="email" checked>
                        <div class="method-icon" style="background:rgba(0,216,240,.12);">
                            <i class="bi bi-envelope-fill" style="color:var(--neon-cyan);font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight:600;color:var(--text-primary);font-size:.9rem;">Correo electrónico</div>
                            <div style="font-size:.78rem;color:var(--text-muted);">Recibirás un código de 6 dígitos en tu correo al iniciar sesión.</div>
                        </div>
                    </label>
                    <label class="method-option" id="opt-totp" onclick="selectMethod('totp')">
                        <input type="radio" name="metodo_2fa" value="totp">
                        <div class="method-icon" style="background:rgba(124,58,237,.12);">
                            <i class="bi bi-qr-code-scan" style="color:var(--neon-violet);font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight:600;color:var(--text-primary);font-size:.9rem;">
                                Aplicación Autenticadora
                                <span style="background:rgba(132,204,22,.12);color:var(--neon-green);border:1px solid rgba(132,204,22,.2);border-radius:10px;padding:1px 7px;font-size:.65rem;font-weight:600;margin-left:5px;">RECOMENDADO</span>
                            </div>
                            <div style="font-size:.78rem;color:var(--text-muted);">Google Authenticator, Authy o Microsoft Authenticator. Funciona sin internet.</div>
                        </div>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-neon w-100" style="padding:13px;">
                <i class="bi bi-person-plus-fill me-2"></i>Crear mi cuenta
            </button>
        </form>

        <p style="text-align:center;margin-top:20px;color:var(--text-muted);font-size:.86rem;">
            ¿Ya tienes cuenta?
            <a href="/nexusgear/auth/login.php" style="color:var(--neon-cyan);font-weight:600;">Inicia sesión</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}

// Password strength
document.getElementById('pw')?.addEventListener('input', function() {
    const v=this.value;
    let score=0;
    if(v.length>=8)score++;if(v.length>=12)score++;
    if(/[A-Z]/.test(v))score++;if(/[0-9]/.test(v))score++;if(/[^A-Za-z0-9]/.test(v))score++;
    const lvls=[['0%',''],['20%','#f43f8e','Muy débil'],['40%','#f59e0b','Débil'],['60%','#f59e0b','Regular'],['80%','#84cc16','Buena'],['100%','#00d8f0','Excelente']];
    const l=lvls[Math.min(score,5)];
    const sf=document.getElementById('sf'),sl=document.getElementById('sl');
    if(sf){sf.style.width=l[0];sf.style.background=l[1]||'transparent';}
    if(sl){sl.textContent=l[2]||'';sl.style.color=l[1]||'';}
});

// Selector de método 2FA
function selectMethod(m) {
    document.querySelectorAll('input[name="metodo_2fa"]').forEach(r => r.value===m ? (r.checked=true) : null);
    document.getElementById('opt-email').classList.toggle('selected', m==='email');
    document.getElementById('opt-totp').classList.toggle('selected', m==='totp');
}
</script>
</body>
</html>
