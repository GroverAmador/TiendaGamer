<?php http_response_code(404); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <style>
        body{background:var(--bg-primary);display:flex;align-items:center;justify-content:center;min-height:100vh;}
        .error-code{font-family:'Oxanium',sans-serif;font-size:clamp(6rem,20vw,12rem);font-weight:900;line-height:1;
            background:var(--gradient-brand);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
        .glitch{position:relative;}
        .glitch::before,.glitch::after{content:attr(data-text);position:absolute;top:0;left:0;width:100%;height:100%;
            background:var(--gradient-brand);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
        .glitch::before{left:2px;animation:glitch1 2s infinite;}
        .glitch::after{left:-2px;animation:glitch2 2s infinite;}
        @keyframes glitch1{0%,100%{clip-path:inset(0 0 95% 0)}10%{clip-path:inset(40% 0 50% 0)}30%{clip-path:inset(70% 0 20% 0)}}
        @keyframes glitch2{0%,100%{clip-path:inset(95% 0 0 0)}10%{clip-path:inset(50% 0 40% 0)}30%{clip-path:inset(20% 0 70% 0)}}
    </style>
</head>
<body>
<div style="position:fixed;top:30%;left:50%;transform:translate(-50%,-50%);
            width:500px;height:500px;border-radius:50%;
            background:radial-gradient(circle,rgba(123,47,255,0.08),transparent 70%);pointer-events:none;"></div>
<div style="text-align:center;padding:40px 20px;">
    <div class="error-code glitch" data-text="404">404</div>
    <h1 style="font-family:'Oxanium',sans-serif;font-size:1.8rem;color:var(--text-primary);margin-bottom:16px;">
        Página no encontrada
    </h1>
    <p style="color:var(--text-secondary);max-width:380px;margin:0 auto 32px;line-height:1.7;">
        Esta página se perdió en el metaverso. El contenido que buscas no existe o fue movido.
    </p>
    <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
        <a href="/nexusgear/index.php" class="btn btn-neon btn-lg">
            <i class="bi bi-lightning-charge-fill me-2"></i>Ir al Inicio
        </a>
        <a href="/nexusgear/client/productos.php" class="btn btn-outline-cyan btn-lg">
            <i class="bi bi-grid-3x3-gap me-2"></i>Ver Productos
        </a>
    </div>
    <div style="margin-top:40px;">
        <a href="javascript:history.back()"
           style="color:var(--text-muted);font-size:0.9rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="bi bi-arrow-left"></i>Volver atrás
        </a>
    </div>
</div>
<script src="/nexusgear/assets/js/main.js"></script>
</body>
</html>
