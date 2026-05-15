<?php
// ============================================================
// NexusGear - Favorites Page
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario'])) { header('Location: /nexusgear/auth/login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$uid  = (int)$_SESSION['id_usuario'];
$stmt = mysqli_prepare($conn,
    "SELECT p.*,c.nombre_categoria,c.icono,COALESCE(AVG(r.puntuacion),0) AS avg_rating,COUNT(DISTINCT r.id_resena) AS review_count
     FROM Favorito f JOIN Producto p ON f.id_producto=p.id_producto
     LEFT JOIN Categoria c ON p.id_categoria=c.id_categoria
     LEFT JOIN Resena r ON p.id_producto=r.id_producto
     WHERE f.id_usuario=? AND p.estado=1 GROUP BY p.id_producto ORDER BY f.fecha DESC");
mysqli_stmt_bind_param($stmt,'i',$uid);
mysqli_stmt_execute($stmt);
$favResult = mysqli_stmt_get_result($stmt); // guardar resultado ANTES del while
$favorites  = [];
while ($r = mysqli_fetch_assoc($favResult)) $favorites[] = $r;
mysqli_stmt_free_result($stmt);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favoritos — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/client.css">
</head>
<body class="client-page">
<?php include __DIR__.'/../views/header.php'; ?>
<?php include __DIR__.'/../views/toast.php'; ?>
<?php include __DIR__.'/../views/quickview_modal.php'; ?>

<div class="page-header">
    <div class="container-xl">
        <h1><i class="bi bi-heart-fill me-2" style="color:var(--neon-pink);"></i>Mis Favoritos</h1>
    </div>
</div>

<div class="container-xl py-4">
    <?php if (empty($favorites)): ?>
    <div class="empty-state">
        <i class="bi bi-heart-break" style="font-size:5rem;opacity:0.25;display:block;margin-bottom:24px;color:var(--neon-pink);"></i>
        <h3>No tienes favoritos aún</h3>
        <p>Agrega productos a tus favoritos para verlos aquí.</p>
        <a href="/nexusgear/client/productos.php" class="btn btn-neon mt-3">
            <i class="bi bi-grid-3x3-gap me-2"></i>Explorar Productos
        </a>
    </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4" id="fav-grid">
        <?php foreach ($favorites as $p): ?>
        <?php $inStock=(int)$p['stock']>0;$lowStock=(int)$p['stock']<=5&&$inStock;$avg=(float)$p['avg_rating']; ?>
        <div class="col animate-on-scroll" id="fav-card-<?= $p['id_producto'] ?>">
            <div class="product-card" onclick="handleCardClick(event,<?= $p['id_producto'] ?>)">
                <div class="position-relative overflow-hidden">
                    <img src="<?= htmlspecialchars($p['imagen']??'') ?>"
                         alt="<?= htmlspecialchars($p['nombre']) ?>"
                         class="card-img-top" style="height:200px;object-fit:cover;">
                    <span class="badge-cyan" style="position:absolute;top:12px;left:12px;font-size:0.72rem;">
                        <?= htmlspecialchars($p['icono']??'') ?> <?= htmlspecialchars($p['nombre_categoria']??'') ?>
                    </span>
                    <button class="btn-fav active btn-remove-fav"
                            data-product-id="<?= $p['id_producto'] ?>"
                            style="position:absolute;top:12px;right:12px;"
                            onclick="event.stopPropagation()" title="Quitar de favoritos">
                        <i class="bi bi-heart-fill"></i>
                    </button>
                    <?php if (!$inStock): ?>
                    <div class="stock-overlay"><span>AGOTADO</span></div>
                    <?php endif; ?>
                    <div class="quickview-overlay" onclick="event.stopPropagation();openQuickView(<?= $p['id_producto'] ?>)">
                        <button class="quickview-btn">
                            <i class="bi bi-eye"></i> Vista Rápida
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="brand-tag mb-1"><?= htmlspecialchars($p['marca']??'') ?></div>
                    <h6 style="font-family:'Oxanium',sans-serif;color:var(--text-primary);margin-bottom:6px;font-size:0.92rem;">
                        <?= htmlspecialchars($p['nombre']) ?>
                    </h6>
                    <div class="d-flex align-items-center gap-1 mb-2">
                        <?php for($i=1;$i<=5;$i++): ?>
                        <i class="bi <?= $i<=round($avg)?'bi-star-fill':'bi-star' ?>"
                           style="color:<?= $i<=round($avg)?'#ffd700':'var(--text-muted)' ?>;font-size:0.82rem;"></i>
                        <?php endfor; ?>
                        <span style="color:var(--text-muted);font-size:0.75rem;">(<?= (int)$p['review_count'] ?>)</span>
                    </div>
                    <?php if ($lowStock): ?>
                    <div class="badge-orange mb-2" style="font-size:0.72rem;">
                        <i class="bi bi-exclamation-triangle me-1"></i>Últimas <?= $p['stock'] ?> uds.
                    </div>
                    <?php endif; ?>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <span class="price-tag" style="font-size:1.2rem;">$<?= number_format((float)$p['precio'],2) ?></span>
                        <?php if ($inStock): ?>
                        <button class="btn btn-neon btn-sm btn-add-to-cart" data-product-id="<?= $p['id_producto'] ?>"
                                onclick="event.stopPropagation()" style="font-size:0.8rem;padding:7px 14px;">
                            <i class="bi bi-cart3 me-1"></i>Agregar
                        </button>
                        <?php else: ?>
                        <button class="btn btn-secondary btn-sm" disabled style="font-size:0.8rem;">
                            <i class="bi bi-x-circle me-1"></i>Agotado
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__.'/../views/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script src="/nexusgear/assets/js/carrito.js"></script>
<script>
window.csrfToken='<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
window.userFavIds=<?= json_encode(array_column($favorites,'id_producto')) ?>;

document.querySelectorAll('.btn-remove-fav').forEach(btn=>{
    btn.addEventListener('click',function(){
        const pid=this.dataset.productId;
        const card=document.getElementById('fav-card-'+pid);
        fetch('/nexusgear/controllers/favorito_controller.php',{
            method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=remove&id_producto=${pid}&csrf_token=${window.csrfToken}`
        }).then(r=>r.json()).then(d=>{
            if(d.success){
                showToast('Eliminado de favoritos.','info');
                if(card){card.style.transition='all 0.4s ease';card.style.opacity='0';card.style.transform='scale(0.8)';
                    setTimeout(()=>{card.remove();if(!document.querySelector('#fav-grid .col')){
                        document.getElementById('fav-grid').innerHTML='<div class="col-12"><div class="empty-state"><i class="bi bi-heart-break" style="font-size:5rem;opacity:0.25;display:block;margin-bottom:24px;color:var(--neon-pink);"></i><h3>No tienes favoritos aún</h3><a href=\"/nexusgear/client/productos.php\" class=\"btn btn-neon mt-3\"><i class=\"bi bi-grid-3x3-gap me-2\"></i>Explorar Productos</a></div></div>';}},400);}
            }
        });
    });
});
</script>
</body>
</html>
