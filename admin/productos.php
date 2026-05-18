<?php
// ============================================================
// NexusGear - Admin: Product CRUD + Multiple Images
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /nexusgear/unauthorized.php'); exit;
}
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$categories = [];
$catRes = mysqli_query($conn,"SELECT * FROM Categoria ORDER BY nombre_categoria");
if($catRes) while($c=mysqli_fetch_assoc($catRes)) $categories[]=$c;

$search    = trim($_GET['q'] ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);
$where     = "WHERE p.estado >= 0";
if($search)    { $s=mysqli_real_escape_string($conn,$search); $where.=" AND (p.nombre LIKE '%$s%' OR p.marca LIKE '%$s%')"; }
if($catFilter) { $where.=" AND p.id_categoria=$catFilter"; }

$productos=[];
$res=mysqli_query($conn,
    "SELECT p.*, c.nombre_categoria,
            GROUP_CONCAT(CONCAT(pi.id_imagen,'|',pi.url) ORDER BY pi.orden SEPARATOR '||') AS imagenes_extra
     FROM Producto p
     LEFT JOIN Categoria c ON p.id_categoria=c.id_categoria
     LEFT JOIN Producto_Imagen pi ON pi.id_producto=p.id_producto
     $where
     GROUP BY p.id_producto
     ORDER BY p.fecha_creacion DESC");
if($res) while($r=mysqli_fetch_assoc($res)) $productos[]=$r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos — NexusGear Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/admin.css">
    <style>
        /* Thumbnails de imágenes extra en el modal */
        .extra-img-card {
            position:relative;width:80px;height:80px;flex-shrink:0;
        }
        .extra-img-card img {
            width:100%;height:100%;object-fit:cover;border-radius:6px;
            border:1px solid var(--border-subtle);display:block;
        }
        .extra-img-card .remove-img-btn {
            position:absolute;top:-7px;right:-7px;width:20px;height:20px;
            border-radius:50%;border:none;cursor:pointer;
            display:flex;align-items:center;justify-content:center;
            font-size:.65rem;font-weight:700;line-height:1;
            color:#fff;box-shadow:0 2px 6px rgba(0,0,0,.4);
        }
        .extra-img-card .badge-new {
            position:absolute;bottom:0;left:0;right:0;
            background:rgba(0,0,0,.65);font-size:.5rem;color:var(--neon-cyan);
            padding:2px;text-align:center;border-radius:0 0 6px 6px;
            text-transform:uppercase;letter-spacing:.5px;
        }
    </style>
</head>
<body style="background:var(--bg-primary);">
<div class="admin-layout">
<?php include __DIR__.'/../views/admin_sidebar.php'; ?>
<div class="admin-main">
    <div class="admin-topbar">
        <div>
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="bi bi-list"></i></button>
            <h1 class="admin-page-title d-inline-block ms-2">Gestión de Productos</h1>
        </div>
        <button class="btn btn-neon btn-sm" onclick="openProductModal()">
            <i class="bi bi-plus-circle-fill me-1"></i>Nuevo Producto
        </button>
    </div>

    <div class="admin-content">
        <?php include __DIR__.'/../views/toast.php'; ?>

        <!-- Filter bar -->
        <div class="admin-table-card mb-4">
            <div class="admin-table-header">
                <form method="GET" class="d-flex gap-2 flex-wrap align-items-center" style="flex:1;">
                    <div class="admin-search">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" name="q" class="form-control"
                               placeholder="Buscar producto..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="cat" class="form-select" style="width:auto;">
                        <option value="">Todas las categorías</option>
                        <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id_categoria'] ?>" <?= $catFilter===$c['id_categoria']?'selected':'' ?>>
                            <?= htmlspecialchars($c['nombre_categoria']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-cyan btn-sm">
                        <i class="bi bi-funnel me-1"></i>Filtrar
                    </button>
                    <a href="/nexusgear/admin/productos.php" class="btn btn-sm" style="color:var(--text-muted);">
                        <i class="bi bi-x-circle me-1"></i>Limpiar
                    </a>
                </form>
                <div style="color:var(--text-muted);font-size:0.85rem;"><?= count($productos) ?> productos</div>
            </div>

            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr>
                        <th>Imagen</th><th>Nombre</th><th>Categoría</th><th>Marca</th>
                        <th>Precio</th><th>Stock</th><th>Destacado</th><th>Estado</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach($productos as $p): ?>
                        <tr>
                            <td><img src="<?= htmlspecialchars($p['imagen']??'') ?>" class="product-thumb" alt=""></td>
                            <td style="color:var(--text-primary);font-weight:500;max-width:180px;">
                                <?= htmlspecialchars($p['nombre']) ?>
                            </td>
                            <td style="color:var(--text-muted);font-size:0.85rem;"><?= htmlspecialchars($p['nombre_categoria']??'') ?></td>
                            <td style="color:var(--text-muted);font-size:0.85rem;"><?= htmlspecialchars($p['marca']??'') ?></td>
                            <td style="color:var(--neon-cyan);font-weight:600;">$<?= number_format((float)$p['precio'],2) ?></td>
                            <td>
                                <span class="<?= (int)$p['stock']===0?'stock-empty':((int)$p['stock']<=5?'stock-low':'stock-ok') ?>">
                                    <?= (int)$p['stock'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if($p['destacado']): ?>
                                <span class="badge-cyan" style="font-size:.7rem;">
                                    <i class="bi bi-star-fill me-1" style="color:#ffd700;"></i>Sí
                                </span>
                                <?php else: ?>
                                <span style="color:var(--text-muted);font-size:.82rem;">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input toggle-estado" type="checkbox"
                                           data-id="<?= $p['id_producto'] ?>" <?= $p['estado']?'checked':'' ?>>
                                </div>
                            </td>
                            <td>
                                <a href="/nexusgear/client/producto_detalle.php?id=<?= $p['id_producto'] ?>"
                                   target="_blank"
                                   class="action-btn"
                                   style="background:rgba(0,216,240,.1);color:var(--neon-cyan);text-decoration:none;display:inline-flex;align-items:center;justify-content:center;"
                                   title="Ver como cliente">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                <button class="action-btn action-btn-edit"
                                        onclick="openProductModal(<?= htmlspecialchars(json_encode($p)) ?>)"
                                        title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="action-btn action-btn-delete"
                                        onclick="deleteProduct(<?= $p['id_producto'] ?>,'<?= htmlspecialchars($p['nombre'],ENT_QUOTES) ?>')"
                                        title="Eliminar">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($productos)): ?>
                        <tr><td colspan="9" class="text-center" style="color:var(--text-muted);padding:32px;">
                            <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.4;"></i>
                            No se encontraron productos.
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Add/Edit Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalTitle">
                    <i class="bi bi-box-seam me-2"></i>Nuevo Producto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-product" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="create_product" id="product-action">
                    <input type="hidden" name="id_producto" id="product-id" value="">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label"><i class="bi bi-type me-1"></i>Nombre *</label>
                            <input type="text" name="nombre" id="p-nombre" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-folder2-open me-1"></i>Categoría *</label>
                            <select name="id_categoria" id="p-cat" class="form-select" required>
                                <option value="">Seleccionar</option>
                                <?php foreach($categories as $c): ?>
                                <option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre_categoria']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-building me-1"></i>Marca</label>
                            <input type="text" name="marca" id="p-marca" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-currency-dollar me-1"></i>Precio *</label>
                            <input type="number" name="precio" id="p-precio" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-box-seam me-1"></i>Stock</label>
                            <input type="number" name="stock" id="p-stock" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">
                                <i class="bi bi-file-text me-1"></i>Descripción
                            </label>
                            <textarea name="descripcion" id="p-desc" class="form-control" rows="3"
                                      placeholder="Especificaciones técnicas, características, etc."></textarea>
                        </div>

                        <!-- ===== IMAGEN PRINCIPAL ===== -->
                        <div class="col-12">
                            <label class="form-label" style="color:var(--neon-cyan);">
                                <i class="bi bi-image me-1"></i>Imagen Principal
                            </label>
                            <div class="d-flex gap-2 mb-2">
                                <input type="text" name="imagen_url" id="p-imagen-url" class="form-control"
                                       placeholder="https://... URL de la imagen principal">
                                <button type="button" id="btn-clear-img"
                                        onclick="clearMainImage()"
                                        title="Quitar imagen principal"
                                        style="flex-shrink:0;background:rgba(244,63,142,.15);border:1px solid rgba(244,63,142,.3);
                                               color:#f43f8e;border-radius:8px;padding:0 14px;cursor:pointer;
                                               font-size:.8rem;white-space:nowrap;display:none;">
                                    <i class="bi bi-x-circle me-1"></i>Quitar
                                </button>
                            </div>
                            <input type="file" name="imagen" id="p-imagen" class="form-control" accept="image/*">
                            <div class="img-preview-box mt-2" id="img-preview-box">
                                <img src="" id="img-preview" style="display:none;width:100%;height:100%;object-fit:cover;">
                                <span id="img-preview-placeholder" style="color:var(--text-muted);">
                                    <i class="bi bi-image" style="font-size:1.5rem;"></i><br>Vista previa
                                </span>
                            </div>
                            <!-- Nombre del producto como fallback visible cuando no hay imagen -->
                            <div id="img-no-image-note" style="display:none;margin-top:6px;
                                 padding:8px 12px;background:rgba(255,255,255,.03);border:1px solid var(--border-subtle);
                                 border-radius:6px;font-size:.78rem;color:var(--text-muted);">
                                <i class="bi bi-info-circle me-1"></i>
                                Sin imagen: se mostrará el nombre del producto como placeholder.
                            </div>
                        </div>

                        <!-- ===== IMÁGENES ADICIONALES ===== -->
                        <div class="col-12">
                            <label class="form-label" style="color:var(--neon-violet);">
                                <i class="bi bi-images me-1"></i>Imágenes Adicionales
                                <span style="font-size:.75rem;color:var(--text-muted);font-weight:400;">
                                    (galería en la vista del producto)
                                </span>
                            </label>

                            <!-- Grid de imágenes actuales + pendientes -->
                            <div id="extra-images-grid"
                                 style="display:flex;flex-wrap:wrap;gap:10px;min-height:20px;margin-bottom:10px;">
                            </div>

                            <!-- Controles para agregar -->
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;
                                        background:rgba(255,255,255,.025);border:1px dashed var(--border-subtle);
                                        border-radius:8px;padding:12px;">
                                <button type="button" class="btn btn-sm btn-outline-violet"
                                        onclick="document.getElementById('multi-img-upload').click()">
                                    <i class="bi bi-upload me-1"></i>Subir archivo(s)
                                </button>
                                <input type="file" id="multi-img-upload" accept="image/*" multiple
                                       style="display:none" onchange="handleMultiImageUpload(this)">
                                <div class="d-flex gap-2 align-items-center" style="flex:1;min-width:220px;">
                                    <input type="text" id="multi-img-url-input" class="form-control form-control-sm"
                                           placeholder="https://... agregar por URL">
                                    <button type="button" class="btn btn-sm btn-outline-cyan"
                                            onclick="addImageByUrl()" style="white-space:nowrap;">
                                        <i class="bi bi-link-45deg me-1"></i>Agregar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="destacado" id="p-destacado">
                                <label class="form-check-label" for="p-destacado">
                                    <i class="bi bi-star-fill me-1" style="color:#ffd700;"></i>Producto Destacado
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-toggle-on me-1"></i>Estado</label>
                            <select name="estado" id="p-estado" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-violet" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-neon" onclick="submitProduct()">
                    <i class="bi bi-floppy-fill me-1"></i>Guardar Producto
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Product Confirmation Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom-color:rgba(244,63,142,.2);">
                <h5 class="modal-title" style="color:var(--neon-pink);font-family:'Oxanium',sans-serif;">
                    <i class="bi bi-trash3-fill me-2"></i>Eliminar Producto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="text-align:center;padding:28px 24px;">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:2.5rem;color:#f43f8e;display:block;margin-bottom:14px;"></i>
                <p style="color:var(--text-secondary);font-size:.9rem;line-height:1.6;margin:0;">
                    ¿Eliminar <strong id="del-prod-name" style="color:var(--neon-pink);"></strong>?<br>
                    Esta acción no se puede deshacer.
                </p>
            </div>
            <div class="modal-footer" style="border-top-color:rgba(244,63,142,.2);gap:10px;">
                <button type="button" class="btn btn-outline-violet" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="confirm-delete-prod-btn"
                        style="background:linear-gradient(135deg,#7c3aed,#f43f8e);color:#fff;
                               border:none;padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer;">
                    <i class="bi bi-trash3-fill me-1"></i>Sí, eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script>
const productModal       = new bootstrap.Modal(document.getElementById('productModal'));
const deleteProductModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
window.csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
let pendingDeleteProdId  = null;

// ============================================================
// Multiple image state
// ============================================================
let newImageFiles  = [];  // {file, preview}
let newImageUrls   = [];  // string[]
let deletedImgIds  = [];  // number[]
let existingImages = [];  // {id, url}

function renderExtraGrid() {
    const grid = document.getElementById('extra-images-grid');
    if (!grid) return;
    grid.innerHTML = '';

    existingImages.forEach(img => {
        const card = document.createElement('div');
        card.className = 'extra-img-card';
        card.innerHTML = `
            <img src="${img.url}" onerror="this.src='https://placehold.co/80x80/11111e/444?text=?'" alt="">
            <button type="button" class="remove-img-btn" onclick="removeExistingImg(${img.id}, this.closest('.extra-img-card'))"
                    style="background:#f43f8e;" title="Quitar imagen">✕</button>`;
        grid.appendChild(card);
    });

    newImageFiles.forEach((item, i) => {
        const card = document.createElement('div');
        card.className = 'extra-img-card';
        card.innerHTML = `
            <img src="${item.preview}" alt="">
            <button type="button" class="remove-img-btn" onclick="removePendingFile(${i})"
                    style="background:#f59e0b;" title="Cancelar">✕</button>
            <div class="badge-new">Nuevo</div>`;
        grid.appendChild(card);
    });

    newImageUrls.forEach((url, i) => {
        const card = document.createElement('div');
        card.className = 'extra-img-card';
        card.innerHTML = `
            <img src="${url}" onerror="this.src='https://placehold.co/80x80/11111e/444?text=?'" alt="">
            <button type="button" class="remove-img-btn" onclick="removePendingUrl(${i})"
                    style="background:#f59e0b;" title="Quitar">✕</button>
            <div class="badge-new">URL</div>`;
        grid.appendChild(card);
    });
}

function removeExistingImg(id, el) {
    deletedImgIds.push(id);
    existingImages = existingImages.filter(img => img.id !== id);
    el.remove();
}

function removePendingFile(i) {
    newImageFiles.splice(i, 1);
    renderExtraGrid();
}

function removePendingUrl(i) {
    newImageUrls.splice(i, 1);
    renderExtraGrid();
}

function handleMultiImageUpload(input) {
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            newImageFiles.push({ file, preview: e.target.result });
            renderExtraGrid();
        };
        reader.readAsDataURL(file);
    });
    input.value = '';
}

function addImageByUrl() {
    const inp = document.getElementById('multi-img-url-input');
    const url = (inp.value || '').trim();
    if (!url.startsWith('http')) {
        showToast('Ingresa una URL válida (empieza con http).', 'warning');
        return;
    }
    newImageUrls.push(url);
    inp.value = '';
    renderExtraGrid();
}

// ============================================================
// Open modal
// ============================================================
function openProductModal(product = null) {
    document.getElementById('form-product').reset();

    // Reset image state
    newImageFiles  = [];
    newImageUrls   = [];
    deletedImgIds  = [];
    existingImages = [];

    // Reset main image UI
    showMainImgPreview(null);
    document.getElementById('img-no-image-note').style.display = 'none';

    if (product) {
        document.getElementById('productModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Producto';
        document.getElementById('product-action').value  = 'update_product';
        document.getElementById('product-id').value      = product.id_producto;
        document.getElementById('p-nombre').value        = product.nombre        || '';
        document.getElementById('p-cat').value           = product.id_categoria  || '';
        document.getElementById('p-marca').value         = product.marca         || '';
        document.getElementById('p-precio').value        = product.precio        || '';
        document.getElementById('p-stock').value         = product.stock         || 0;
        document.getElementById('p-desc').value          = product.descripcion   || '';
        document.getElementById('p-imagen-url').value   = product.imagen        || '';
        document.getElementById('p-destacado').checked  = product.destacado == 1;
        document.getElementById('p-estado').value       = product.estado;

        // Show current image preview
        showMainImgPreview(product.imagen || null);

        // Parse extra images (GROUP_CONCAT format: "id|url||id|url")
        if (product.imagenes_extra) {
            product.imagenes_extra.split('||').forEach(item => {
                const parts = item.split('|');
                if (parts.length >= 2 && parts[0]) {
                    existingImages.push({ id: parseInt(parts[0]), url: parts.slice(1).join('|') });
                }
            });
        }
    } else {
        document.getElementById('productModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nuevo Producto';
        document.getElementById('product-action').value = 'create_product';
        document.getElementById('product-id').value    = '';
    }
    renderExtraGrid();
    productModal.show();
}

// ============================================================
// Submit — lee valores directamente del DOM para garantizar
// que estén correctos independientemente del estado del modal
// ============================================================
function submitProduct() {
    const data = new FormData();

    // Hidden fields
    data.set('csrf_token', document.getElementById('form-product').querySelector('[name="csrf_token"]').value);
    data.set('action',     document.getElementById('product-action').value);
    data.set('id_producto',document.getElementById('product-id').value);

    // Text fields — leídos directamente del DOM
    data.set('nombre',      document.getElementById('p-nombre').value.trim());
    data.set('id_categoria',document.getElementById('p-cat').value);
    data.set('marca',       document.getElementById('p-marca').value.trim());
    data.set('precio',      document.getElementById('p-precio').value);
    data.set('stock',       document.getElementById('p-stock').value);
    data.set('descripcion', document.getElementById('p-desc').value.trim());
    data.set('estado',      document.getElementById('p-estado').value);
    data.set('imagen_url',  document.getElementById('p-imagen-url').value.trim());

    // Checkbox
    if (document.getElementById('p-destacado').checked) data.set('destacado', '1');

    // Imagen principal — archivo
    const fileInput = document.getElementById('p-imagen');
    if (fileInput && fileInput.files && fileInput.files[0]) {
        data.set('imagen', fileInput.files[0]);
    }

    // Imágenes adicionales
    newImageFiles.forEach(item => data.append('new_images[]', item.file));
    newImageUrls.forEach(url   => data.append('new_image_urls[]', url));
    deletedImgIds.forEach(id   => data.append('delete_images[]', id.toString()));

    fetch('/nexusgear/controllers/producto_controller.php', { method: 'POST', body: data })
        .then(r => r.text())
        .then(text => {
            let d;
            try { d = JSON.parse(text); }
            catch(e) { throw new Error('Respuesta inesperada del servidor.'); }
            showToast(d.message, d.success ? 'success' : 'error');
            if (d.success) { productModal.hide(); setTimeout(() => location.reload(), 1200); }
        })
        .catch(err => showToast(err.message || 'Error de conexión.', 'error'));
}

// ============================================================
// Delete — Bootstrap modal directo (sin Promise chain)
// ============================================================
function deleteProduct(id, nombre) {
    pendingDeleteProdId = id;
    document.getElementById('del-prod-name').textContent = nombre;
    deleteProductModal.show();
}

document.getElementById('confirm-delete-prod-btn').addEventListener('click', function() {
    if (!pendingDeleteProdId) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Eliminando...';

    fetch('/nexusgear/controllers/producto_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_product&id_producto=${pendingDeleteProdId}&csrf_token=${window.csrfToken}`
    })
    .then(r => r.text())
    .then(text => {
        let d;
        try { d = JSON.parse(text); }
        catch(e) { throw new Error('Respuesta inválida del servidor.'); }
        deleteProductModal.hide();
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) setTimeout(() => location.reload(), 1000);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash3-fill me-1"></i>Sí, eliminar';
        pendingDeleteProdId = null;
    })
    .catch(err => {
        showToast(err.message || 'Error al eliminar.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash3-fill me-1"></i>Sí, eliminar';
    });
});

// ---- Helpers de imagen principal ----
function showMainImgPreview(src) {
    const prev = document.getElementById('img-preview');
    const ph   = document.getElementById('img-preview-placeholder');
    const note = document.getElementById('img-no-image-note');
    const btn  = document.getElementById('btn-clear-img');
    if (src) {
        prev.src = src;
        prev.style.display = 'block';
        ph.style.display   = 'none';
        note.style.display = 'none';
        if (btn) btn.style.display = '';
    } else {
        prev.style.display = 'none';
        ph.style.display   = 'block';
        note.style.display = 'block';
        if (btn) btn.style.display = 'none';
    }
}

function clearMainImage() {
    document.getElementById('p-imagen-url').value = '';
    document.getElementById('p-imagen').value = '';
    showMainImgPreview(null);
}

// Main image — file upload preview
document.getElementById('p-imagen')?.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => showMainImgPreview(e.target.result);
        reader.readAsDataURL(file);
    }
});

// Main image — URL field preview
document.getElementById('p-imagen-url')?.addEventListener('input', function() {
    const url = this.value.trim();
    showMainImgPreview(url || null);
});

document.querySelectorAll('.toggle-estado').forEach(sw => {
    sw.addEventListener('change', function() {
        fetch('/nexusgear/controllers/producto_controller.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_estado&id_producto=${this.dataset.id}&estado=${this.checked?1:0}&csrf_token=${window.csrfToken}`
        }).then(r => r.json()).then(d => showToast(d.success ? 'Estado actualizado.' : 'Error.', d.success ? 'success' : 'error'));
    });
});
</script>
</body>
</html>
