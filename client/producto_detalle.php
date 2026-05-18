<?php
// ============================================================
// NexusGear - Product Detail + Enhanced Review Section
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /nexusgear/auth/login.php'); exit;
}
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /nexusgear/client/productos.php'); exit; }

$stmt = mysqli_prepare($conn,
    "SELECT p.*, c.nombre_categoria, c.icono,
            COALESCE(AVG(r.puntuacion),0) AS avg_rating,
            COUNT(DISTINCT r.id_resena) AS review_count
     FROM Producto p
     LEFT JOIN Categoria c ON p.id_categoria=c.id_categoria
     LEFT JOIN Resena r    ON p.id_producto=r.id_producto
     WHERE p.id_producto=? AND p.estado=1 GROUP BY p.id_producto");
mysqli_stmt_bind_param($stmt,'i',$id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$product) { header('Location: /nexusgear/client/productos.php'); exit; }

// All reviews
$revStmt = mysqli_prepare($conn,
    "SELECT r.*, u.nombre FROM Resena r
     JOIN Usuario u ON r.id_usuario=u.id_usuario
     WHERE r.id_producto=? ORDER BY r.fecha DESC");
mysqli_stmt_bind_param($revStmt,'i',$id);
mysqli_stmt_execute($revStmt);
$reviews = [];
$revResult = mysqli_stmt_get_result($revStmt);
while ($r = mysqli_fetch_assoc($revResult)) $reviews[] = $r;
mysqli_stmt_close($revStmt);

// Extra images gallery
$extraImgs = [];
$imgStmt = mysqli_prepare($conn, "SELECT url FROM Producto_Imagen WHERE id_producto=? ORDER BY orden ASC");
mysqli_stmt_bind_param($imgStmt, 'i', $id);
mysqli_stmt_execute($imgStmt);
$imgRes = mysqli_stmt_get_result($imgStmt);
while ($img = mysqli_fetch_assoc($imgRes)) $extraImgs[] = $img['url'];
mysqli_stmt_close($imgStmt);

$uid = (int)$_SESSION['id_usuario'];

// Is favorite?
$favStmt = mysqli_prepare($conn,"SELECT id_favorito FROM Favorito WHERE id_usuario=? AND id_producto=?");
mysqli_stmt_bind_param($favStmt,'ii',$uid,$id);
mysqli_stmt_execute($favStmt);
mysqli_stmt_store_result($favStmt);
$isFav = mysqli_stmt_num_rows($favStmt) > 0;
mysqli_stmt_close($favStmt);

// Has purchased?
$purchStmt = mysqli_prepare($conn,
    "SELECT dv.id_detalle FROM Detalle_Venta dv
     JOIN Venta v ON dv.id_venta=v.id_venta
     WHERE v.id_usuario=? AND dv.id_producto=? LIMIT 1");
mysqli_stmt_bind_param($purchStmt,'ii',$uid,$id);
mysqli_stmt_execute($purchStmt);
mysqli_stmt_store_result($purchStmt);
$hasPurchased = mysqli_stmt_num_rows($purchStmt) > 0;
mysqli_stmt_close($purchStmt);

// Own review
$ownReview = null;
foreach ($reviews as $r) {
    if ((int)$r['id_usuario'] === $uid) { $ownReview = $r; break; }
}

function starsHtml(float $avg, bool $icons = false): string {
    if ($icons) {
        $h = '';
        for ($i = 1; $i <= 5; $i++) {
            $h .= '<i class="bi ' . ($i <= round($avg) ? 'bi-star-fill' : 'bi-star') . '"
                      style="color:' . ($i <= round($avg) ? '#ffd700' : 'var(--text-muted)') . ';font-size:1rem;"></i>';
        }
        return $h;
    }
    $h = '<span class="stars">';
    for ($i = 1; $i <= 5; $i++) $h .= $i <= round($avg) ? '★' : '<span class="empty">★</span>';
    return $h . '</span>';
}

function fechaEs(string $d): string {
    $m=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $t=strtotime($d);
    return date('d',$t).' de '.$m[date('n',$t)-1].' de '.date('Y',$t);
}

$avg     = (float)$product['avg_rating'];
$inStock = (int)$product['stock'] > 0;
$totalReviews = count($reviews);
$reviewAvgText = $totalReviews > 0 ? number_format($avg, 1) : 'N/A';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['nombre']) ?> — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/client.css">
</head>
<body class="client-page">
<?php include __DIR__ . '/../views/header.php'; ?>
<?php include __DIR__ . '/../views/toast.php'; ?>

<div class="page-header">
    <div class="container-xl">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/nexusgear/index.php"><i class="bi bi-house me-1"></i>Inicio</a></li>
                <li class="breadcrumb-item"><a href="/nexusgear/client/productos.php">Productos</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($product['nombre']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container-xl py-4">
    <div class="row g-5">
        <!-- Product Image -->
        <div class="col-lg-5 animate-on-scroll from-left">
            <div class="product-detail-img-container">
                <img src="<?= htmlspecialchars($product['imagen']??'') ?>"
                     alt="<?= htmlspecialchars($product['nombre']) ?>"
                     class="product-detail-img"
                     id="main-product-img">
            </div>
            <?php if (!empty($extraImgs)): ?>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
                <div class="img-thumb active"
                     onclick="switchImg('<?= htmlspecialchars($product['imagen']??'') ?>',this)">
                    <img src="<?= htmlspecialchars($product['imagen']??'') ?>" alt="Principal">
                </div>
                <?php foreach ($extraImgs as $eImg): ?>
                <div class="img-thumb"
                     onclick="switchImg('<?= htmlspecialchars($eImg) ?>',this)">
                    <img src="<?= htmlspecialchars($eImg) ?>" alt="">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Product Info -->
        <div class="col-lg-7 animate-on-scroll from-right">
            <span class="badge-cyan mb-2 d-inline-block">
                <?= htmlspecialchars($product['icono']??'') ?> <?= htmlspecialchars($product['nombre_categoria']??'') ?>
            </span>

            <h1 class="product-name"><?= htmlspecialchars($product['nombre']) ?></h1>
            <div class="brand-tag mb-3"><?= htmlspecialchars($product['marca']??'') ?></div>

            <!-- Rating bar summary -->
            <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border-subtle);
                        border-radius:var(--radius-md);padding:14px 18px;margin-bottom:20px;
                        display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
                <div style="text-align:center;">
                    <div style="font-family:'Oxanium',sans-serif;font-size:2.5rem;font-weight:800;
                                color:var(--neon-cyan);line-height:1;">
                        <?= $reviewAvgText ?>
                    </div>
                    <div style="color:var(--text-muted);font-size:0.8rem;"><?= $totalReviews ?> reseñas</div>
                </div>
                <div>
                    <div style="display:flex;gap:4px;margin-bottom:6px;font-size:1.1rem;">
                        <?= starsHtml($avg, true) ?>
                    </div>
                    <div style="color:var(--text-muted);font-size:0.82rem;">
                        <?= $reviewAvgText !== 'N/A' ? 'Calificación promedio' : 'Sin calificaciones aún' ?>
                    </div>
                </div>
            </div>

            <div class="product-price-large mb-3">$<?= number_format((float)$product['precio'], 2) ?></div>

            <!-- Stock -->
            <div class="mb-4">
                <?php if ($inStock && (int)$product['stock'] > 5): ?>
                    <span class="badge-green">
                        <i class="bi bi-check-circle-fill me-1"></i>En stock (<?= $product['stock'] ?> disponibles)
                    </span>
                <?php elseif ($inStock): ?>
                    <span class="badge-orange">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Últimas <?= $product['stock'] ?> unidades
                    </span>
                <?php else: ?>
                    <span class="badge-red">
                        <i class="bi bi-x-circle-fill me-1"></i>Agotado
                    </span>
                <?php endif; ?>
            </div>

            <p style="color:var(--text-secondary);line-height:1.8;margin-bottom:24px;">
                <?= nl2br(htmlspecialchars($product['descripcion']??'')) ?>
            </p>

            <!-- Actions -->
            <?php if ($inStock): ?>
            <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                <div class="qty-group">
                    <button class="qty-btn" id="qty-minus" type="button">−</button>
                    <input type="number" class="qty-input" id="qty-selector" value="1" min="1" max="<?= $product['stock'] ?>">
                    <button class="qty-btn" id="qty-plus" type="button">+</button>
                </div>
                <button class="btn btn-neon flex-grow-1 btn-add-to-cart"
                        data-product-id="<?= $product['id_producto'] ?>">
                    🛒 Agregar al Carrito
                </button>
                <!-- Favorito con emoji -->
                <button class="card-fav-btn <?= $isFav ? 'active' : '' ?>"
                        data-product-id="<?= $product['id_producto'] ?>"
                        style="position:static;width:42px;height:42px;"
                        title="Favoritos">
                    <span class="fav-emoji" style="font-size:1.1rem;"><?= $isFav ? '❤️' : '🤍' ?></span>
                </button>
            </div>
            <?php else: ?>
            <!-- Producto agotado: opción de notificación -->
            <?php
            // Verificar si el usuario ya tiene alerta activa
            $alertaActiva = false;
            $stAlerta = mysqli_prepare($conn,"SELECT id_alerta FROM Alerta_Stock WHERE id_usuario=? AND id_producto=? AND activa=1");
            mysqli_stmt_bind_param($stAlerta,'ii',$uid,$id);
            mysqli_stmt_execute($stAlerta); mysqli_stmt_store_result($stAlerta);
            $alertaActiva = mysqli_stmt_num_rows($stAlerta) > 0;
            mysqli_stmt_close($stAlerta);
            ?>
            <div class="d-flex flex-column gap-3 mb-3">
                <div style="background:rgba(244,63,142,.08);border:1px solid rgba(244,63,142,.2);border-radius:var(--radius-md);padding:14px 16px;">
                    <div style="font-weight:600;color:var(--neon-pink);font-size:.9rem;margin-bottom:6px;">
                        ✗ Producto sin stock
                    </div>
                    <p style="color:var(--text-muted);font-size:.83rem;margin:0;line-height:1.5;">
                        Este producto está agotado actualmente. Puedes recibir una notificación cuando vuelva a estar disponible.
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($alertaActiva): ?>
                    <button id="btn-alerta" class="btn btn-outline-cyan flex-grow-1"
                            onclick="toggleAlerta(<?= $id ?>, true)" style="font-size:.88rem;">
                        🔔 Alerta activa — Click para cancelar
                    </button>
                    <?php else: ?>
                    <button id="btn-alerta" class="btn btn-neon flex-grow-1"
                            onclick="toggleAlerta(<?= $id ?>, false)" style="font-size:.88rem;">
                        🔔 Notificarme cuando esté disponible
                    </button>
                    <?php endif; ?>
                    <!-- Favorito con emoji -->
                    <button class="card-fav-btn <?= $isFav ? 'active' : '' ?>"
                            data-product-id="<?= $product['id_producto'] ?>"
                            style="position:static;width:42px;height:42px;"
                            title="Favoritos">
                        <span class="fav-emoji" style="font-size:1.1rem;"><?= $isFav ? '❤️' : '🤍' ?></span>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Benefits strip -->
            <div class="row g-2 mt-3">
                <div class="col-6">
                    <div style="background:rgba(0,245,255,0.05);border:1px solid rgba(0,245,255,0.1);
                                border-radius:8px;padding:10px;font-size:0.82rem;color:var(--text-secondary);">
                        <i class="bi bi-rocket-takeoff-fill" style="color:var(--neon-cyan);"></i>
                        <strong style="color:var(--neon-cyan);"> Envío express</strong><br>
                        <span style="margin-left:22px;">24-48 horas</span>
                    </div>
                </div>
                <div class="col-6">
                    <div style="background:rgba(0,245,255,0.05);border:1px solid rgba(0,245,255,0.1);
                                border-radius:8px;padding:10px;font-size:0.82rem;color:var(--text-secondary);">
                        <i class="bi bi-shield-fill-check" style="color:var(--neon-cyan);"></i>
                        <strong style="color:var(--neon-cyan);"> Garantía oficial</strong><br>
                        <span style="margin-left:22px;">Del fabricante</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TABS ===== -->
    <div class="mt-5">
        <ul class="nav product-tabs border-bottom mb-4" id="productTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-desc">
                    <i class="bi bi-file-text me-1"></i>Descripción
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-reviews" id="reviews-tab-btn">
                    <i class="bi bi-chat-square-dots-fill me-1"></i>
                    Opiniones
                    <span class="badge-cyan ms-1" style="padding:2px 8px;font-size:0.7rem;"><?= $totalReviews ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Description -->
            <div class="tab-pane fade show active" id="tab-desc">
                <div style="background:var(--bg-card);border:1px solid var(--border-subtle);
                            border-radius:12px;padding:28px;color:var(--text-secondary);line-height:1.9;">
                    <?= nl2br(htmlspecialchars($product['descripcion'] ?? 'Sin descripción disponible.')) ?>
                </div>
            </div>

            <!-- ===== REVIEWS / OPINIONS TAB ===== -->
            <div class="tab-pane fade" id="tab-reviews">
                <div class="row g-4">

                    <!-- Left: Review form OR status -->
                    <div class="col-lg-5">
                        <?php if ($hasPurchased && !$ownReview): ?>
                        <!-- Can submit review -->
                        <div style="background:var(--bg-card);border:1px solid var(--border-glow);
                                    border-radius:14px;padding:24px;animation:fadeInUp 0.4s ease;">
                            <h5 style="font-family:'Oxanium',sans-serif;color:var(--neon-cyan);
                                       margin-bottom:6px;display:flex;align-items:center;gap:8px;">
                                <i class="bi bi-pencil-square"></i>Escribe tu opinión
                            </h5>
                            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:20px;">
                                Compraste este producto. ¡Cuéntanos tu experiencia!
                            </p>

                            <form id="form-resena">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="submit">
                                <input type="hidden" name="id_producto" value="<?= $product['id_producto'] ?>">
                                <input type="hidden" name="puntuacion" id="rating-value" value="0">

                                <!-- Star rating selector -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-star-fill me-1" style="color:#ffd700;"></i>Puntuación
                                    </label>
                                    <div class="star-selector" id="star-selector">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star" data-value="<?= $i ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <div style="color:var(--text-muted);font-size:0.8rem;" id="rating-label">
                                        Haz clic en las estrellas para puntuar
                                    </div>
                                </div>

                                <!-- Comment -->
                                <div class="mb-3">
                                    <label class="form-label" for="comentario">
                                        <i class="bi bi-chat-text me-1"></i>Tu comentario
                                    </label>
                                    <textarea class="form-control" id="comentario" name="comentario"
                                              rows="4"
                                              placeholder="Describe tu experiencia con el producto: calidad, rendimiento, relación calidad-precio..."
                                              maxlength="800"></textarea>
                                    <div style="text-align:right;font-size:0.75rem;color:var(--text-muted);margin-top:4px;">
                                        <span id="char-count">0</span>/800
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-neon w-100">
                                    <i class="bi bi-send-fill me-2"></i>Publicar Opinión
                                </button>
                            </form>
                        </div>

                        <?php elseif ($ownReview): ?>
                        <!-- Already reviewed -->
                        <div style="background:rgba(0,245,255,0.05);border:1px solid rgba(0,245,255,0.2);
                                    border-radius:14px;padding:24px;text-align:center;animation:fadeInUp 0.4s ease;">
                            <i class="bi bi-check-circle-fill" style="font-size:2.5rem;color:var(--neon-green);margin-bottom:12px;display:block;"></i>
                            <h6 style="font-family:'Oxanium',sans-serif;color:var(--text-primary);margin-bottom:6px;">
                                Ya dejaste tu opinión
                            </h6>
                            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                                Tu puntuación: <?= starsHtml((float)$ownReview['puntuacion'], true) ?>
                            </p>
                            <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:12px;
                                        color:var(--text-secondary);font-size:0.88rem;font-style:italic;text-align:left;">
                                "<?= htmlspecialchars($ownReview['comentario']) ?>"
                            </div>
                        </div>

                        <?php else: ?>
                        <!-- Not purchased -->
                        <div style="background:rgba(123,47,255,0.06);border:1px solid rgba(123,47,255,0.2);
                                    border-radius:14px;padding:24px;text-align:center;animation:fadeInUp 0.4s ease;">
                            <i class="bi bi-lock-fill" style="font-size:2.5rem;color:var(--neon-violet);margin-bottom:12px;display:block;"></i>
                            <h6 style="font-family:'Oxanium',sans-serif;color:var(--text-primary);margin-bottom:8px;">
                                Opiniones verificadas
                            </h6>
                            <p style="color:var(--text-muted);font-size:0.85rem;line-height:1.6;margin-bottom:16px;">
                                Solo quienes compraron este producto pueden dejar una opinión, garantizando reseñas auténticas.
                            </p>
                            <a href="/nexusgear/client/productos.php" class="btn btn-neon btn-sm">
                                <i class="bi bi-cart3 me-1"></i>Ver productos
                            </a>
                        </div>
                        <?php endif; ?>

                        <!-- Rating distribution -->
                        <?php if ($totalReviews > 0):
                            $dist = [5=>0,4=>0,3=>0,2=>0,1=>0];
                            foreach ($reviews as $rv) $dist[(int)$rv['puntuacion']]++;
                        ?>
                        <div style="margin-top:16px;background:var(--bg-card);border:1px solid var(--border-subtle);
                                    border-radius:12px;padding:16px;">
                            <div style="font-size:0.85rem;color:var(--text-secondary);font-weight:600;margin-bottom:12px;">
                                Distribución de calificaciones
                            </div>
                            <?php for ($s = 5; $s >= 1; $s--): ?>
                            <?php $cnt = $dist[$s]; $pct = $totalReviews > 0 ? round($cnt/$totalReviews*100) : 0; ?>
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                <span style="color:#ffd700;font-size:0.8rem;width:12px;"><?= $s ?></span>
                                <i class="bi bi-star-fill" style="color:#ffd700;font-size:0.75rem;"></i>
                                <div style="flex:1;height:8px;background:var(--border-subtle);border-radius:4px;overflow:hidden;">
                                    <div style="width:<?= $pct ?>%;height:100%;
                                                background:linear-gradient(90deg,#7b2fff,#00f5ff);
                                                border-radius:4px;transition:width 1s ease;"></div>
                                </div>
                                <span style="color:var(--text-muted);font-size:0.78rem;width:24px;text-align:right;">
                                    <?= $cnt ?>
                                </span>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Reviews list -->
                    <div class="col-lg-7">
                        <?php if (empty($reviews)): ?>
                        <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
                            <i class="bi bi-chat-dots" style="font-size:3rem;opacity:0.3;display:block;margin-bottom:16px;"></i>
                            <p>Aún no hay opiniones para este producto.<br>¡Sé el primero en compartir tu experiencia!</p>
                        </div>
                        <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:14px;" id="reviews-list">
                            <?php foreach ($reviews as $rev): ?>
                            <div class="review-card <?= (int)$rev['id_usuario'] === $uid ? 'own-review' : '' ?>">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="review-user-avatar">
                                        <?= strtoupper(substr($rev['nombre'], 0, 1)) ?>
                                    </div>
                                    <div style="flex:1;">
                                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                                            <span style="font-weight:600;color:var(--text-primary);">
                                                <?= htmlspecialchars($rev['nombre']) ?>
                                            </span>
                                            <?php if ((int)$rev['id_usuario'] === $uid): ?>
                                            <span class="badge-cyan" style="font-size:0.68rem;">
                                                <i class="bi bi-person-check me-1"></i>Tú
                                            </span>
                                            <?php endif; ?>
                                            <span style="margin-left:auto;color:var(--text-muted);font-size:0.78rem;">
                                                <i class="bi bi-calendar3 me-1"></i><?= fechaEs($rev['fecha']) ?>
                                            </span>
                                        </div>
                                        <!-- Stars -->
                                        <div style="display:flex;gap:2px;margin-bottom:8px;">
                                            <?= starsHtml((float)$rev['puntuacion'], true) ?>
                                            <span style="color:var(--text-muted);font-size:0.78rem;margin-left:6px;">
                                                <?= number_format((float)$rev['puntuacion'],1) ?>/5
                                            </span>
                                        </div>
                                        <p style="color:var(--text-secondary);margin:0;line-height:1.7;font-size:0.9rem;">
                                            <?= nl2br(htmlspecialchars($rev['comentario'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div><!-- /.tab-pane#tab-reviews -->
        </div>
    </div>
</div>

<!-- Open reviews tab if URL hash -->
<?php include __DIR__ . '/../views/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script src="/nexusgear/assets/js/carrito.js"></script>
<script>
window.csrfToken  = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
window.userFavIds = <?= json_encode([$id]) ?>;

// Qty controls
const qtyInput = document.getElementById('qty-selector');
document.getElementById('qty-minus')?.addEventListener('click', () => { if(parseInt(qtyInput.value)>1) qtyInput.value--; });
document.getElementById('qty-plus')?.addEventListener('click', () => { if(parseInt(qtyInput.value)<parseInt(qtyInput.max)) qtyInput.value++; });

// Override add-to-cart to use qty selector
document.querySelector('.btn-add-to-cart')?.addEventListener('click', function() {
    addToCart(<?= $product['id_producto'] ?>, parseInt(qtyInput?.value||1));
});

// ---- Star selector ----
const stars = document.querySelectorAll('.star-selector .star');
const ratingInput = document.getElementById('rating-value');
const ratingLabels = ['','Muy malo','Regular','Bueno','Muy bueno','Excelente'];

stars.forEach(star => {
    star.addEventListener('click', function() {
        const val = parseInt(this.dataset.value);
        ratingInput.value = val;
        document.getElementById('rating-label').textContent = ratingLabels[val] || '';
        document.getElementById('rating-label').style.color = 'var(--neon-cyan)';
        stars.forEach((s,i) => s.classList.toggle('selected', i < val));
    });
    star.addEventListener('mouseover', function() {
        const val = parseInt(this.dataset.value);
        stars.forEach((s,i) => s.style.color = i < val ? '#ffd700' : '');
    });
    star.addEventListener('mouseleave', function() {
        const val = parseInt(ratingInput.value);
        stars.forEach((s,i) => s.style.color = i < val ? '#ffd700' : '');
    });
});

// Char counter
document.getElementById('comentario')?.addEventListener('input', function() {
    document.getElementById('char-count').textContent = this.value.length;
});

// ---- Review form submission ----
document.getElementById('form-resena')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const rating = parseInt(ratingInput?.value || 0);
    const text   = document.getElementById('comentario')?.value.trim();

    if (rating === 0) {
        showToast('Selecciona una puntuación antes de enviar.', 'warning');
        document.getElementById('star-selector')?.scrollIntoView({ behavior:'smooth', block:'center' });
        return;
    }
    if (!text || text.length < 10) {
        showToast('Escribe al menos 10 caracteres en tu comentario.', 'warning');
        return;
    }

    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-2 icon-spin"></i>Publicando...';

    const data = new FormData(this);
    fetch('/nexusgear/controllers/resena_controller.php', { method:'POST', body: data })
        .then(r => r.json())
        .then(d => {
            showToast(d.message, d.success ? 'success' : 'error');
            if (d.success) {
                setTimeout(() => {
                    // Switch to reviews tab and reload
                    const tab = document.getElementById('reviews-tab-btn');
                    if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
                    location.reload();
                }, 1500);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Publicar Opinión';
            }
        })
        .catch(() => {
            showToast('Error de conexión. Intenta de nuevo.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Publicar Opinión';
        });
});

// Image gallery switcher
function switchImg(url, thumb) {
    const main = document.getElementById('main-product-img');
    if (main) { main.style.opacity='.4'; main.src=url; main.onload=()=>main.style.opacity='1'; }
    document.querySelectorAll('.img-thumb').forEach(t=>t.classList.remove('active'));
    if (thumb) thumb.classList.add('active');
}

// Auto-open reviews tab if URL has #opiniones
if (window.location.hash === '#opiniones') {
    const tab = document.getElementById('reviews-tab-btn');
    if (tab) setTimeout(() => bootstrap.Tab.getOrCreateInstance(tab).show(), 300);
}

// Own review card highlight
document.querySelector('.own-review')?.style && (document.querySelector('.own-review').style.borderColor = 'var(--border-glow)');

// ---- Alerta de stock ----
function toggleAlerta(pid, estaActiva) {
    const btn    = document.getElementById('btn-alerta');
    const action = estaActiva ? 'unsubscribe' : 'subscribe';
    if (btn) { btn.disabled = true; btn.textContent = 'Procesando...'; }

    fetch('/nexusgear/controllers/alerta_stock_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${action}&id_producto=${pid}&csrf_token=${window.csrfToken}`
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success && btn) {
            if (action === 'subscribe') {
                btn.textContent = '🔔 Alerta activa — Click para cancelar';
                btn.className   = 'btn btn-outline-cyan flex-grow-1';
                btn.setAttribute('onclick', `toggleAlerta(${pid}, true)`);
            } else {
                btn.textContent = '🔔 Notificarme cuando esté disponible';
                btn.className   = 'btn btn-neon flex-grow-1';
                btn.setAttribute('onclick', `toggleAlerta(${pid}, false)`);
            }
        }
        if (btn) btn.disabled = false;
    })
    .catch(() => { showToast('Error de conexión.', 'error'); if(btn) btn.disabled=false; });
}
</script>

<style>
.own-review { border-color: var(--border-glow) !important; }
.img-thumb {
    width:62px;height:62px;border-radius:6px;
    border:2px solid var(--border-subtle);
    cursor:pointer;overflow:hidden;transition:border-color .2s;
    flex-shrink:0;
}
.img-thumb.active { border-color:var(--neon-cyan); box-shadow:0 0 8px rgba(0,216,240,.4); }
.img-thumb img { width:100%;height:100%;object-fit:cover;display:block; }
</style>
</body>
</html>
