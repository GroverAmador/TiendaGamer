<?php
// ============================================================
// NexusGear - Login Page
// ============================================================
session_start();
if (isset($_SESSION['id_usuario'])) {
    header('Location: ' . ($_SESSION['rol']==='admin'?'/nexusgear/admin/dashboard.php':'/nexusgear/client/productos.php'));
    exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../controllers/auth_controller.php';
}
$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/auth.css">
</head>
<body>
<div id="toast-container"></div>

<div class="auth-page">
    <!-- Left panel -->
    <div class="col-lg-5 d-none d-lg-flex auth-split-left">
        <div class="text-center" style="position:relative;z-index:1;">
            <div class="auth-brand-logo">
                <i class="bi bi-lightning-charge-fill" style="color:#fff;margin-right:6px;"></i>NexusGear
            </div>
            <p class="auth-brand-tagline">
                El rendimiento que mereces.<br>
                Equipo de alto nivel para gamers serios.
            </p>
            <div class="auth-deco" style="font-size:0;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-controller" style="font-size:5rem;color:rgba(255,255,255,0.9);"></i>
            </div>
            <!-- Animated stats -->
            <div class="mt-4 d-flex gap-4 justify-content-center">
                <div style="text-align:center;">
                    <div style="font-family:'Oxanium',sans-serif;font-size:1.5rem;font-weight:800;color:#fff;">500+</div>
                    <div style="color:rgba(255,255,255,0.7);font-size:0.8rem;">Productos</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-family:'Oxanium',sans-serif;font-size:1.5rem;font-weight:800;color:#fff;">10k+</div>
                    <div style="color:rgba(255,255,255,0.7);font-size:0.8rem;">Clientes</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-family:'Oxanium',sans-serif;font-size:1.5rem;font-weight:800;color:#fff;">99%</div>
                    <div style="color:rgba(255,255,255,0.7);font-size:0.8rem;">Satisfacción</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right panel -->
    <div class="col-lg-7 auth-split-right">
        <div class="auth-form-card">
            <div class="d-lg-none text-center mb-4">
                <a href="/nexusgear/index.php" style="font-family:'Oxanium',sans-serif;font-size:1.5rem;font-weight:800;color:var(--text-primary);">
                    <i class="bi bi-lightning-charge-fill" style="color:var(--neon-cyan);margin-right:4px;"></i>NexusGear
                </a>
            </div>

            <h1 class="auth-title">Bienvenido de vuelta</h1>
            <p class="auth-subtitle">Inicia sesión para acceder a tu cuenta</p>

            <?php if ($error): ?>
            <div class="alert alert-danger mb-3">
                <i class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form id="form-login" method="POST" action="" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="login">

                <div class="mb-3">
                    <label class="form-label" for="correo">
                        <i class="bi bi-envelope me-1"></i>Correo electrónico
                    </label>
                    <input type="email" id="correo" name="correo" class="form-control"
                           placeholder="correo@ejemplo.com" required
                           value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
                    <div class="invalid-feedback"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="contrasena">
                        <i class="bi bi-lock me-1"></i>Contraseña
                    </label>
                    <div class="pw-wrap">
                        <input type="password" id="contrasena" name="contrasena"
                               class="form-control" placeholder="Tu contraseña" required>
                        <button type="button" class="pw-toggle"
                                onclick="togglePw('contrasena',this)">👁</button>
                    </div>
                    <div class="invalid-feedback" style="display:block;"></div>
                </div>

                <div class="mb-4 form-check">
                    <input class="form-check-input" type="checkbox" id="recordarme" name="recordarme">
                    <label class="form-check-label" for="recordarme">Recordarme</label>
                </div>

                <button type="submit" class="btn btn-neon w-100" style="padding:14px;">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                </button>
            </form>

            <p class="text-center mt-4" style="color:var(--text-muted);font-size:0.9rem;">
                ¿No tienes cuenta?
                <a href="/nexusgear/auth/register.php" style="color:var(--neon-cyan);font-weight:600;">
                    Regístrate gratis
                </a>
            </p>

            <!-- Demo hint -->
            <div style="margin-top:20px;padding:12px 16px;background:rgba(123,47,255,0.08);
                        border:1px solid rgba(123,47,255,0.2);border-radius:8px;">
                <p style="margin:0;font-size:0.8rem;color:var(--text-muted);">
                    <i class="bi bi-info-circle-fill me-1" style="color:var(--neon-violet);"></i>
                    <strong style="color:var(--neon-violet);">Demo:</strong><br>
                    Admin: <code style="color:var(--neon-cyan);">admin@nexusgear.com</code> /
                    <code style="color:var(--neon-cyan);">Admin123!</code><br>
                    Cliente: <code style="color:var(--neon-cyan);">cliente1@test.com</code> /
                    <code style="color:var(--neon-cyan);">Cliente123!</code>
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script src="/nexusgear/assets/js/validaciones.js"></script>
<script>
// Toggle ojo con emoji
function togglePw(inputId, btn) {
    var inp = document.getElementById(inputId);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}
<?php if ($flash): ?>
document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($flash) ?>,<?= json_encode($flashType) ?>));
<?php endif; ?>
</script>
</body>
</html>
