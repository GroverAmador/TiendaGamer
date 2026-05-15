<?php http_response_code(403); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Denegado — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <style>
        body{background:var(--bg-primary);display:flex;align-items:center;justify-content:center;min-height:100vh;}
    </style>
</head>
<body>
<div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
            width:600px;height:600px;border-radius:50%;
            background:radial-gradient(circle,rgba(255,45,120,0.06),transparent 70%);pointer-events:none;"></div>

<div style="text-align:center;padding:40px 20px;max-width:480px;">
    <div style="width:80px;height:80px;border-radius:50%;background:rgba(255,45,120,0.12);
                border:2px solid rgba(255,45,120,0.3);display:flex;align-items:center;
                justify-content:center;margin:0 auto 24px;animation:neonPulse 2s ease-in-out infinite;">
        <i class="bi bi-shield-x-fill" style="font-size:2.5rem;color:var(--neon-pink);"></i>
    </div>

    <div style="font-family:'Oxanium',sans-serif;font-size:5rem;font-weight:900;color:var(--neon-pink);
                line-height:1;margin-bottom:8px;text-shadow:0 0 30px rgba(255,45,120,0.5);">403</div>
    <h1 style="font-family:'Oxanium',sans-serif;font-size:1.8rem;color:var(--text-primary);margin-bottom:16px;">
        Acceso Denegado
    </h1>

    <div style="background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);
                border-radius:12px;padding:20px;margin-bottom:32px;">
        <p style="color:var(--text-secondary);margin:0;line-height:1.7;">
            No tienes permisos para acceder a esta sección.<br>
            Esta área está reservada para
            <strong style="color:var(--neon-cyan);">administradores</strong> del sistema.
        </p>
    </div>

    <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
        <a href="/nexusgear/index.php" class="btn btn-neon btn-lg">
            <i class="bi bi-lightning-charge-fill me-2"></i>Ir al Inicio
        </a>
        <a href="/nexusgear/auth/login.php" class="btn btn-outline-cyan btn-lg">
            <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
        </a>
    </div>

    <div style="margin-top:32px;">
        <a href="javascript:history.back()"
           style="color:var(--text-muted);font-size:0.9rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="bi bi-arrow-left"></i>Volver atrás
        </a>
    </div>
</div>
<script src="/nexusgear/assets/js/main.js"></script>
</body>
</html>
