<?php
// ============================================================
// NexusGear - Admin: Product CRUD
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
$res=mysqli_query($conn,"SELECT p.*,c.nombre_categoria FROM Producto p LEFT JOIN Categoria c ON p.id_categoria=c.id_categoria $where ORDER BY p.fecha_creacion DESC");
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
                            <td style="color:var(--text-muted);font-size:0.85rem;">
                                <?= htmlspecialchars($p['nombre_categoria']??'') ?>
                            </td>
                            <td style="color:var(--text-muted);font-size:0.85rem;">
                                <?= htmlspecialchars($p['marca']??'') ?>
                            </td>
                            <td style="color:var(--neon-cyan);font-weight:600;">$<?= number_format((float)$p['precio'],2) ?></td>
                            <td>
                                <span class="<?= (int)$p['stock']===0?'stock-empty':((int)$p['stock']<=5?'stock-low':'stock-ok') ?>">
                                    <?= (int)$p['stock'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if($p['destacado']): ?>
                                <span class="badge-cyan" style="font-size:0.7rem;">
                                    <i class="bi bi-star-fill me-1" style="color:#ffd700;"></i>Sí
                                </span>
                                <?php else: ?>
                                <span style="color:var(--text-muted);font-size:0.82rem;">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input toggle-estado" type="checkbox"
                                           data-id="<?= $p['id_producto'] ?>" <?= $p['estado']?'checked':'' ?>>
                                </div>
                            </td>
                            <td>
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
                                <i class="bi bi-file-text me-1"></i>Descripción detallada
                                <span style="color:var(--text-muted);font-size:0.78rem;">(se muestra en la vista rápida y en el detalle del producto)</span>
                            </label>
                            <textarea name="descripcion" id="p-desc" class="form-control" rows="4"
                                      placeholder="Describe el producto en detalle: especificaciones técnicas, características principales, compatibilidad, etc."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-link-45deg me-1"></i>URL de imagen</label>
                            <input type="text" name="imagen_url" id="p-imagen-url" class="form-control"
                                   placeholder="https://placehold.co/400x300/13131f/00f5ff?text=Producto">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-upload me-1"></i>Subir imagen</label>
                            <input type="file" name="imagen" id="p-imagen" class="form-control" accept="image/*">
                            <div class="img-preview-box mt-2" id="img-preview-box">
                                <img src="" id="img-preview" style="display:none;width:100%;height:100%;object-fit:cover;">
                                <span id="img-preview-placeholder" style="color:var(--text-muted);">
                                    <i class="bi bi-image" style="font-size:1.5rem;"></i><br>Vista previa
                                </span>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script>
const productModal = new bootstrap.Modal(document.getElementById('productModal'));
window.csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';

function openProductModal(product=null) {
    document.getElementById('form-product').reset();
    document.getElementById('img-preview').style.display='none';
    document.getElementById('img-preview-placeholder').style.display='block';
    if(product){
        document.getElementById('productModalTitle').innerHTML='<i class="bi bi-pencil-square me-2"></i>Editar Producto';
        document.getElementById('product-action').value='update_product';
        document.getElementById('product-id').value=product.id_producto;
        document.getElementById('p-nombre').value=product.nombre||'';
        document.getElementById('p-cat').value=product.id_categoria||'';
        document.getElementById('p-marca').value=product.marca||'';
        document.getElementById('p-precio').value=product.precio||'';
        document.getElementById('p-stock').value=product.stock||0;
        document.getElementById('p-desc').value=product.descripcion||'';
        document.getElementById('p-imagen-url').value=product.imagen||'';
        document.getElementById('p-destacado').checked=product.destacado==1;
        document.getElementById('p-estado').value=product.estado;
        if(product.imagen){
            document.getElementById('img-preview').src=product.imagen;
            document.getElementById('img-preview').style.display='block';
            document.getElementById('img-preview-placeholder').style.display='none';
        }
    } else {
        document.getElementById('productModalTitle').innerHTML='<i class="bi bi-plus-circle me-2"></i>Nuevo Producto';
        document.getElementById('product-action').value='create_product';
        document.getElementById('product-id').value='';
    }
    productModal.show();
}

function submitProduct() {
    const form=document.getElementById('form-product');
    const data=new FormData(form);
    if(document.getElementById('p-destacado').checked) data.set('destacado','1'); else data.delete('destacado');
    fetch('/nexusgear/controllers/producto_controller.php',{method:'POST',body:data})
        .then(r=>r.json())
        .then(d=>{
            showToast(d.message,d.success?'success':'error');
            if(d.success){productModal.hide();setTimeout(()=>location.reload(),1000);}
        });
}

function deleteProduct(id,nombre){
    showConfirmModal('Eliminar Producto',`¿Eliminar "${nombre}"? Esta acción es irreversible.`)
        .then(ok=>{
            if(!ok) return;
            fetch('/nexusgear/controllers/producto_controller.php',{
                method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=delete_product&id_producto=${id}&csrf_token=${window.csrfToken}`
            }).then(r=>r.json()).then(d=>{
                showToast(d.message,d.success?'success':'error');
                if(d.success) setTimeout(()=>location.reload(),1000);
            });
        });
}

document.getElementById('p-imagen')?.addEventListener('change',function(){
    const file=this.files[0];
    if(file){
        const reader=new FileReader();
        reader.onload=e=>{
            document.getElementById('img-preview').src=e.target.result;
            document.getElementById('img-preview').style.display='block';
            document.getElementById('img-preview-placeholder').style.display='none';
        };
        reader.readAsDataURL(file);
    }
});

document.querySelectorAll('.toggle-estado').forEach(sw=>{
    sw.addEventListener('change',function(){
        fetch('/nexusgear/controllers/producto_controller.php',{
            method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=toggle_estado&id_producto=${this.dataset.id}&estado=${this.checked?1:0}&csrf_token=${window.csrfToken}`
        }).then(r=>r.json()).then(d=>showToast(d.success?'Estado actualizado.':'Error.',d.success?'success':'error'));
    });
});
</script>
</body>
</html>
