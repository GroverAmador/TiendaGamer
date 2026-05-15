<?php
// ============================================================
// NexusGear - Landing Page
// ============================================================
session_start();
require_once __DIR__ . '/config/database.php';

// Fetch featured products (destacado = 1)
$featuredProducts = [];
$res = mysqli_query($conn,
    "SELECT p.*, c.nombre_categoria, c.icono,
            COALESCE(AVG(r.puntuacion), 0) AS avg_rating,
            COUNT(r.id_resena) AS review_count
     FROM Producto p
     LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
     LEFT JOIN Resena r ON p.id_producto = r.id_producto
     WHERE p.destacado = 1 AND p.estado = 1
     GROUP BY p.id_producto
     ORDER BY p.fecha_creacion DESC
     LIMIT 6");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $featuredProducts[] = $row;
    }
}

// Fetch categories with product counts
$categories = [];
$res = mysqli_query($conn,
    "SELECT c.*, COUNT(p.id_producto) AS product_count
     FROM Categoria c
     LEFT JOIN Producto p ON p.id_categoria = c.id_categoria AND p.estado = 1
     GROUP BY c.id_categoria
     ORDER BY c.nombre_categoria");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $categories[] = $row;
    }
}

// Fetch sample reviews for testimonials
$testimonials = [];
$res = mysqli_query($conn,
    "SELECT r.*, u.nombre, p.nombre AS producto_nombre
     FROM Resena r
     JOIN Usuario u ON r.id_usuario = u.id_usuario
     JOIN Producto p ON r.id_producto = p.id_producto
     WHERE r.puntuacion >= 4
     ORDER BY r.fecha DESC LIMIT 6");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $testimonials[] = $row;
    }
}

// Check if user has each featured product as favorite
$favProductIds = [];
if (isset($_SESSION['id_usuario'])) {
    $uid = (int)$_SESSION['id_usuario'];
    $favRes = mysqli_query($conn, "SELECT id_producto FROM Favorito WHERE id_usuario = $uid");
    if ($favRes) {
        while ($f = mysqli_fetch_assoc($favRes)) {
            $favProductIds[] = (int)$f['id_producto'];
        }
    }
}

// Helper: render star rating HTML
function renderStars(float $avg): string {
    $html = '<span class="stars">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= round($avg) ? '★' : '<span class="empty">★</span>';
    }
    $html .= '</span>';
    return $html;
}

// Helper: format date in Spanish
function fechaEspanol(string $date): string {
    $meses = ['enero','febrero','marzo','abril','mayo','junio',
              'julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $ts = strtotime($date);
    return date('d', $ts) . ' de ' . $meses[date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusGear — El Equipo Gaming que Mereces</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/landing.css">
</head>
<body>

<?php include __DIR__ . '/views/header.php'; ?>
<?php include __DIR__ . '/views/toast.php'; ?>
<?php include __DIR__ . '/views/quickview_modal.php'; ?>

<!-- ============================================================
     HERO SECTION
     ============================================================ -->
<section class="hero-section" id="hero">
    <div class="hero-glow-left"></div>
    <div class="hero-glow-right"></div>

    <div class="container-xl">
        <div class="row align-items-center min-vh-100">
            <!-- Text content -->
            <div class="col-lg-6 py-5">
                <div class="animate-on-scroll visible" style="animation-delay:0s;">
                    <span class="badge-cyan mb-3 d-inline-block"><i class="bi bi-rocket-takeoff-fill me-1"></i>Gaming de Alto Rendimiento</span>
                    <h1 class="hero-title">
                        El equipo que necesitas.
                        <br><span class="highlight">El rendimiento que mereces.</span>
                    </h1>
                    <p class="hero-subtitle">
                        Descubre el hardware gaming más avanzado. Laptops, monitores,
                        periféricos y consolas de las mejores marcas del mundo.
                    </p>
                    <div class="hero-cta-group">
                        <a href="/nexusgear/client/productos.php" class="btn btn-neon btn-lg">
                            <i class="bi bi-controller me-2"></i>Ver Productos
                        </a>
                        <a href="/nexusgear/auth/register.php" class="btn btn-outline-cyan btn-lg">
                            Crear Cuenta
                        </a>
                    </div>
                    <!-- Trust indicators -->
                    <div class="d-flex align-items-center gap-3 mt-4" style="flex-wrap:wrap;">
                        <span style="color:var(--text-muted);font-size:0.85rem;"><i class="bi bi-truck me-1" style="color:var(--neon-green);"></i>Envío gratis</span>
                        <span style="color:var(--text-muted);font-size:0.85rem;"><i class="bi bi-shield-fill-check me-1" style="color:var(--neon-cyan);"></i>Garantía oficial</span>
                        <span style="color:var(--text-muted);font-size:0.85rem;"><i class="bi bi-headset me-1" style="color:var(--neon-violet);"></i>Soporte 24/7</span>
                    </div>
                </div>
            </div>

            <!-- Decorative element -->
            <div class="col-lg-6 d-none d-lg-flex hero-decoration">
                <div class="neon-ring">
                    <div class="neon-ring-inner">⚡</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     STATS BAR
     ============================================================ -->
<section class="stats-section" id="stats">
    <div class="container-xl">
        <div class="row g-4">
            <div class="col-6 col-md-3">
                <div class="stat-item animate-on-scroll">
                    <span class="stat-number" data-counter="500">0</span>
                    <div class="stat-label">Productos Disponibles</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item animate-on-scroll">
                    <span class="stat-number" data-counter="10000">0</span>
                    <div class="stat-label">Clientes Satisfechos</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item animate-on-scroll">
                    <span class="stat-number" data-counter="5">0</span>
                    <div class="stat-label">Categorías</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item animate-on-scroll">
                    <span class="stat-number" data-counter="99">0</span>
                    <div class="stat-label">% Satisfacción</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     FEATURED PRODUCTS
     ============================================================ -->
<section class="featured-section" id="featured">
    <div class="container-xl">
        <div class="text-center mb-5">
            <h2 class="section-title">Productos <span>Destacados</span></h2>
            <p class="section-subtitle">Los equipos más demandados por la comunidad gamer</p>
        </div>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($featuredProducts as $product): ?>
                <?php
                $isFav    = in_array((int)$product['id_producto'], $favProductIds);
                $inStock  = (int)$product['stock'] > 0;
                $lowStock = (int)$product['stock'] <= 5 && $inStock;
                $avgRating = (float)($product['avg_rating'] ?? 0);
                ?>
                <div class="col animate-on-scroll">
                    <div class="product-card" onclick="handleCardClick(event,<?= $product['id_producto'] ?>)">
                        <!-- Image -->
                        <div class="position-relative overflow-hidden">
                            <img src="<?= htmlspecialchars($product['imagen'] ?? 'https://placehold.co/400x300/13131f/00f5ff?text=NexusGear') ?>"
                                 alt="<?= htmlspecialchars($product['nombre']) ?>"
                                 class="card-img-top" style="height:200px;object-fit:cover;">
                            <span class="badge-cyan" style="position:absolute;top:12px;left:12px;font-size:0.72rem;">
                                <?= htmlspecialchars($product['icono'] ?? '') ?>
                                <?= htmlspecialchars($product['nombre_categoria'] ?? '') ?>
                            </span>
                            <button class="btn-fav <?= $isFav ? 'active' : '' ?>"
                                    data-product-id="<?= $product['id_producto'] ?>"
                                    style="position:absolute;top:12px;right:12px;"
                                    onclick="event.stopPropagation()">
                                <i class="bi <?= $isFav ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                            </button>
                            <?php if (!$inStock): ?>
                                <div class="stock-overlay"><span>AGOTADO</span></div>
                            <?php endif; ?>
                            <!-- Quick-view overlay -->
                            <div class="quickview-overlay" onclick="event.stopPropagation();openQuickView(<?= $product['id_producto'] ?>)">
                                <button class="quickview-btn">
                                    <i class="bi bi-eye"></i> Vista Rápida
                                </button>
                            </div>
                        </div>

                        <!-- Card body -->
                        <div class="card-body">
                            <div class="brand-tag mb-1"><?= htmlspecialchars($product['marca'] ?? '') ?></div>
                            <h5 style="font-family:'Oxanium',sans-serif;color:var(--text-primary);font-size:0.95rem;margin-bottom:6px;line-height:1.3;">
                                <?= htmlspecialchars($product['nombre']) ?>
                            </h5>
                            <div class="d-flex align-items-center gap-1 mb-2">
                                <?php for ($si=1;$si<=5;$si++): ?>
                                <i class="bi <?= $si<=round($avgRating)?'bi-star-fill':'bi-star' ?>"
                                   style="color:<?= $si<=round($avgRating)?'#ffd700':'var(--text-muted)' ?>;font-size:0.85rem;"></i>
                                <?php endfor; ?>
                                <span style="color:var(--text-muted);font-size:0.75rem;margin-left:3px;">
                                    (<?= (int)$product['review_count'] ?>)
                                </span>
                            </div>
                            <?php if ($lowStock): ?>
                                <div class="badge-orange mb-2" style="font-size:0.72rem;">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Últimas <?= $product['stock'] ?> unidades
                                </div>
                            <?php endif; ?>
                            <div class="d-flex align-items-center justify-content-between mt-2">
                                <span class="price-tag" style="font-size:1.2rem;">$<?= number_format($product['precio'], 2) ?></span>
                                <?php if ($inStock): ?>
                                    <button class="btn btn-neon btn-sm btn-add-to-cart"
                                            data-product-id="<?= $product['id_producto'] ?>"
                                            onclick="event.stopPropagation()"
                                            style="font-size:0.8rem;padding:7px 14px;">
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
            <?php if (empty($featuredProducts)): ?>
                <div class="col-12 text-center">
                    <p style="color:var(--text-muted);">No hay productos destacados aún. <a href="/nexusgear/config/setup.php">Ejecuta setup.php</a> primero.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-5">
            <a href="/nexusgear/client/productos.php" class="btn btn-outline-cyan btn-lg">
                Ver todos los productos →
            </a>
        </div>
    </div>
</section>

<!-- ============================================================
     CATEGORIES
     ============================================================ -->
<section class="categories-section" id="categorias">
    <div class="container-xl">
        <div class="text-center mb-5">
            <h2 class="section-title">Explora por <span>Categoría</span></h2>
            <p class="section-subtitle">Encuentra el equipo perfecto para tu estilo de juego</p>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3">
            <?php foreach ($categories as $cat): ?>
                <div class="col animate-on-scroll">
                    <a href="/nexusgear/client/productos.php?id_categoria=<?= $cat['id_categoria'] ?>"
                       class="category-card">
                        <span class="category-icon"><?= htmlspecialchars($cat['icono'] ?? '📦') ?></span>
                        <div class="category-name"><?= htmlspecialchars($cat['nombre_categoria']) ?></div>
                        <div class="category-count"><?= (int)$cat['product_count'] ?> productos</div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     BENEFITS
     ============================================================ -->
<section class="benefits-section" id="about">
    <div class="container-xl">
        <div class="text-center mb-5">
            <h2 class="section-title">¿Por qué <span>elegirnos?</span></h2>
            <p class="section-subtitle">Comprometidos con la mejor experiencia gaming</p>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
            <div class="col animate-on-scroll">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="bi bi-rocket-takeoff-fill" style="color:var(--neon-cyan);font-size:2.2rem;"></i></div>
                    <div class="benefit-title">Envío Rápido</div>
                    <p class="benefit-desc">Recibe tu equipo en 24-48 horas directamente en tu puerta.</p>
                </div>
            </div>
            <div class="col animate-on-scroll">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="bi bi-shield-fill-check" style="color:var(--neon-violet);font-size:2.2rem;"></i></div>
                    <div class="benefit-title">Garantía Oficial</div>
                    <p class="benefit-desc">Todos los productos incluyen garantía oficial del fabricante.</p>
                </div>
            </div>
            <div class="col animate-on-scroll">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="bi bi-headset" style="color:var(--neon-cyan);font-size:2.2rem;"></i></div>
                    <div class="benefit-title">Soporte 24/7</div>
                    <p class="benefit-desc">Nuestro equipo está disponible cuando más lo necesitas.</p>
                </div>
            </div>
            <div class="col animate-on-scroll">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="bi bi-currency-dollar" style="color:var(--neon-green);font-size:2.2rem;"></i></div>
                    <div class="benefit-title">Mejores Precios</div>
                    <p class="benefit-desc">Precios competitivos y ofertas exclusivas para miembros.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     TESTIMONIALS
     ============================================================ -->
<?php if (!empty($testimonials)): ?>
<section class="testimonials-section">
    <div class="container-xl">
        <div class="text-center mb-5">
            <h2 class="section-title">Lo que dicen <span>nuestros gamers</span></h2>
            <p class="section-subtitle">Opiniones reales de nuestra comunidad</p>
        </div>
        <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-inner">
                <?php foreach (array_chunk($testimonials, 2) as $i => $group): ?>
                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                        <div class="row g-4">
                            <?php foreach ($group as $t): ?>
                                <div class="col-md-6">
                                    <div class="testimonial-card">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="testimonial-avatar">
                                                <?= strtoupper(substr($t['nombre'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="testimonial-name"><?= htmlspecialchars($t['nombre']) ?></div>
                                                <div class="testimonial-date">
                                                    <?= renderStars((float)$t['puntuacion']) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="testimonial-text">
                                            "<?= htmlspecialchars($t['comentario']) ?>"
                                        </p>
                                        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:8px;">
                                            Sobre: <?= htmlspecialchars($t['producto_nombre']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================================
     CTA BANNER
     ============================================================ -->
<section style="padding:80px 0;background:var(--bg-secondary);border-top:1px solid var(--border-subtle);">
    <div class="container-xl text-center">
        <h2 class="section-title mb-3">¿Listo para subir de nivel?</h2>
        <p style="color:var(--text-secondary);max-width:500px;margin:0 auto 32px;">
            Únete a miles de gamers que ya confían en NexusGear para su equipo de alto rendimiento.
        </p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="/nexusgear/auth/register.php" class="btn btn-neon btn-lg">
                Crear Cuenta Gratis
            </a>
            <a href="/nexusgear/client/productos.php" class="btn btn-outline-cyan btn-lg">
                Ver Catálogo
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/views/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script src="/nexusgear/assets/js/carrito.js"></script>
<script>
// CSRF token for AJAX calls
window.csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';
</script>
</body>
</html>
