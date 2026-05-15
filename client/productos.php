<?php
// ============================================================
// NexusGear - Product Catalog
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /nexusgear/auth/login.php'); exit;
}
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$categories = [];
$catRes = mysqli_query($conn, "SELECT * FROM Categoria ORDER BY nombre_categoria");
if ($catRes) while ($r = mysqli_fetch_assoc($catRes)) $categories[] = $r;

$brands = [];
$brandRes = mysqli_query($conn, "SELECT DISTINCT marca FROM Producto WHERE marca IS NOT NULL AND estado=1 ORDER BY marca");
if ($brandRes) while ($r = mysqli_fetch_assoc($brandRes)) $brands[] = $r['marca'];

$currentCat = isset($_GET['id_categoria']) ? (int)$_GET['id_categoria'] : null;

$favIds = [];
$uid = (int)$_SESSION['id_usuario'];
$favRes = mysqli_query($conn, "SELECT id_producto FROM Favorito WHERE id_usuario=$uid");
if ($favRes) while ($f = mysqli_fetch_assoc($favRes)) $favIds[] = (int)$f['id_producto'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/client.css">
</head>
<body class="client-page">
<?php include __DIR__ . '/../views/header.php'; ?>
<?php include __DIR__ . '/../views/toast.php'; ?>
<?php include __DIR__ . '/../views/quickview_modal.php'; ?>

<!-- Page Header -->
<div class="page-header">
    <div class="container-xl">
        <h1><i class="bi bi-grid-3x3-gap-fill me-2" style="color:var(--neon-cyan);"></i>Catálogo de Productos</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/nexusgear/index.php"><i class="bi bi-house me-1"></i>Inicio</a></li>
                <li class="breadcrumb-item active">Productos</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container-xl py-4">
    <div class="row g-4">

        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="filter-sidebar">
                <div class="filter-title">
                    <i class="bi bi-funnel-fill me-2" style="color:var(--neon-cyan);"></i>Filtros
                </div>

                <div class="filter-group">
                    <div class="filter-group-label">
                        <i class="bi bi-search me-1"></i>Buscar
                    </div>
                    <input type="text" id="search-input" class="form-control"
                           placeholder="Nombre de producto..."
                           value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                </div>

                <div class="filter-group">
                    <div class="filter-group-label">
                        <i class="bi bi-folder2-open me-1"></i>Categoría
                    </div>
                    <?php foreach ($categories as $cat): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="id_categoria[]" value="<?= $cat['id_categoria'] ?>"
                               id="cat_<?= $cat['id_categoria'] ?>"
                               <?= ($currentCat === (int)$cat['id_categoria']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cat_<?= $cat['id_categoria'] ?>">
                            <?= htmlspecialchars($cat['icono']) ?> <?= htmlspecialchars($cat['nombre_categoria']) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="filter-group">
                    <div class="filter-group-label">
                        <i class="bi bi-tag me-1"></i>Marca
                    </div>
                    <?php foreach ($brands as $brand): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="marca[]" value="<?= htmlspecialchars($brand) ?>"
                               id="brand_<?= md5($brand) ?>">
                        <label class="form-check-label" for="brand_<?= md5($brand) ?>">
                            <?= htmlspecialchars($brand) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="filter-group">
                    <div class="filter-group-label">
                        <i class="bi bi-currency-dollar me-1"></i>Precio (USD)
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" id="precio-min" class="form-control form-control-sm" placeholder="Mín" min="0">
                        </div>
                        <div class="col-6">
                            <input type="number" id="precio-max" class="form-control form-control-sm" placeholder="Máx" min="0">
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <div class="filter-group-label">
                        <i class="bi bi-star-fill me-1" style="color:#ffd700;"></i>Calificación mínima
                    </div>
                    <select id="rating-filter" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="4">4+ estrellas</option>
                        <option value="3">3+ estrellas</option>
                        <option value="2">2+ estrellas</option>
                    </select>
                </div>

                <button id="btn-clear-filters" class="btn btn-outline-violet w-100 btn-sm">
                    <i class="bi bi-x-circle me-1"></i>Limpiar filtros
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Toolbar -->
            <div class="catalog-toolbar">
                <div class="results-count">
                    <i class="bi bi-box-seam me-1" style="color:var(--neon-cyan);"></i>
                    Mostrando <strong id="results-count">...</strong> productos
                </div>
                <div class="d-flex align-items-center gap-2">
                    <select id="sort-select" class="form-select form-select-sm" style="width:auto;">
                        <option value="default">Relevancia</option>
                        <option value="precio_asc">Precio: menor a mayor</option>
                        <option value="precio_desc">Precio: mayor a menor</option>
                        <option value="nombre_asc">Nombre A-Z</option>
                        <option value="rating_desc">Mejor calificados</option>
                        <option value="nuevo">Más nuevos</option>
                    </select>
                    <button class="view-toggle-btn active" data-view="grid" title="Cuadrícula">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </button>
                    <button class="view-toggle-btn" data-view="list" title="Lista">
                        <i class="bi bi-list-ul"></i>
                    </button>
                </div>
            </div>

            <input type="hidden" id="current-page" value="1">

            <!-- Product grid -->
            <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-4" id="product-grid">
                <?php for ($s = 0; $s < 6; $s++): ?>
                <div class="col">
                    <div class="skeleton-card">
                        <div class="skeleton-img"></div>
                        <div class="skeleton-text skeleton" style="width:40%;margin:16px 16px 8px;height:12px;"></div>
                        <div class="skeleton-text skeleton" style="width:80%;margin:0 16px 8px;height:14px;"></div>
                        <div class="skeleton-text skeleton" style="width:55%;margin:0 16px 8px;height:12px;"></div>
                        <div class="skeleton-btn skeleton" style="margin:8px 16px 16px;height:36px;border-radius:6px;"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <nav class="mt-4" id="pagination-container"></nav>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script src="/nexusgear/assets/js/carrito.js"></script>
<script src="/nexusgear/assets/js/busqueda.js"></script>
<script>
window.csrfToken  = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
window.userFavIds = <?= json_encode($favIds) ?>;

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($currentCat): ?>
    const cb = document.getElementById('cat_<?= $currentCat ?>');
    if (cb) cb.checked = true;
    <?php endif; ?>
    setTimeout(doSearch, 100);
});
</script>
</body>
</html>
