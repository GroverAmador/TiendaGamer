<?php
// ============================================================
// NexusGear - Admin: Category CRUD
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /nexusgear/unauthorized.php'); exit;
}
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (($_POST['csrf_token']??'') !== $_SESSION['csrf_token']) {
        echo json_encode(['success'=>false,'message'=>'Token inválido.']); exit;
    }
    $action=$_POST['action']??'';
    $nombre=trim($_POST['nombre_categoria']??'');
    $desc  =trim($_POST['descripcion']??'');
    $icono =trim($_POST['icono']??'📦');

    if ($action==='create') {
        $stmt=mysqli_prepare($conn,"INSERT INTO Categoria (nombre_categoria,descripcion,icono) VALUES (?,?,?)");
        mysqli_stmt_bind_param($stmt,'sss',$nombre,$desc,$icono);
        $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        echo json_encode(['success'=>$ok,'message'=>$ok?'Categoría creada.':'Error al crear.']);
    } elseif ($action==='update') {
        $id=(int)($_POST['id_categoria']??0);
        $stmt=mysqli_prepare($conn,"UPDATE Categoria SET nombre_categoria=?,descripcion=?,icono=? WHERE id_categoria=?");
        mysqli_stmt_bind_param($stmt,'sssi',$nombre,$desc,$icono,$id);
        $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        echo json_encode(['success'=>$ok,'message'=>$ok?'Categoría actualizada.':'Error.']);
    } elseif ($action==='delete') {
        $id=(int)($_POST['id_categoria']??0);
        $cnt=(int)mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM Producto WHERE id_categoria=$id"))[0];
        if ($cnt>0) {
            echo json_encode(['success'=>false,'message'=>"No se puede eliminar: tiene $cnt producto(s) asociado(s)."]);
        } else {
            $stmt=mysqli_prepare($conn,"DELETE FROM Categoria WHERE id_categoria=?");
            mysqli_stmt_bind_param($stmt,'i',$id);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            echo json_encode(['success'=>$ok,'message'=>$ok?'Categoría eliminada.':'Error.']);
        }
    }
    exit;
}

$cats=[];
$res=mysqli_query($conn,"SELECT c.*,COUNT(p.id_producto) AS product_count FROM Categoria c LEFT JOIN Producto p ON p.id_categoria=c.id_categoria GROUP BY c.id_categoria ORDER BY c.nombre_categoria");
if($res) while($r=mysqli_fetch_assoc($res)) $cats[]=$r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías — NexusGear Admin</title>
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
            <h1 class="admin-page-title d-inline-block ms-2">Categorías</h1>
        </div>
        <button class="btn btn-neon btn-sm" onclick="openCatModal()">
            <i class="bi bi-plus-circle-fill me-1"></i>Nueva Categoría
        </button>
    </div>
    <div class="admin-content">
        <?php include __DIR__.'/../views/toast.php'; ?>
        <div class="admin-table-card">
            <div class="admin-table-header">
                <h6 class="admin-table-title">
                    <i class="bi bi-folder2-open me-2" style="color:var(--neon-cyan);"></i>Lista de Categorías
                </h6>
                <span style="color:var(--text-muted);font-size:0.85rem;"><?= count($cats) ?> categorías</span>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr>
                        <th>Icono</th><th>Nombre</th><th>Descripción</th><th>Productos</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach($cats as $c): ?>
                        <tr>
                            <td style="font-size:1.5rem;"><?= htmlspecialchars($c['icono']??'📦') ?></td>
                            <td style="color:var(--text-primary);font-weight:600;"><?= htmlspecialchars($c['nombre_categoria']) ?></td>
                            <td style="color:var(--text-muted);font-size:0.85rem;max-width:200px;"><?= htmlspecialchars($c['descripcion']??'') ?></td>
                            <td><span class="badge-cyan"><?= (int)$c['product_count'] ?> productos</span></td>
                            <td>
                                <button class="action-btn action-btn-edit"
                                        onclick="openCatModal(<?= htmlspecialchars(json_encode($c)) ?>)"
                                        title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="action-btn action-btn-delete"
                                        onclick="deleteCat(<?= $c['id_categoria'] ?>,'<?= htmlspecialchars($c['nombre_categoria'],ENT_QUOTES) ?>',<?= (int)$c['product_count'] ?>)"
                                        title="Eliminar">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="catModalTitle"><i class="bi bi-folder-plus me-2"></i>Nueva Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cat-id" value="">
                <div class="mb-3">
                    <label class="form-label">Icono (emoji)</label>
                    <input type="text" id="cat-icono" class="form-control" placeholder="💻" maxlength="5">
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-type me-1"></i>Nombre *</label>
                    <input type="text" id="cat-nombre" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-file-text me-1"></i>Descripción</label>
                    <textarea id="cat-desc" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-violet" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </button>
                <button class="btn btn-neon" onclick="submitCat()">
                    <i class="bi bi-floppy-fill me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script>
const catModal=new bootstrap.Modal(document.getElementById('catModal'));
window.csrfToken='<?= htmlspecialchars($_SESSION['csrf_token']) ?>';

function openCatModal(cat=null){
    document.getElementById('catModalTitle').innerHTML=cat?'<i class="bi bi-pencil-square me-2"></i>Editar Categoría':'<i class="bi bi-folder-plus me-2"></i>Nueva Categoría';
    document.getElementById('cat-id').value=cat?cat.id_categoria:'';
    document.getElementById('cat-icono').value=cat?(cat.icono||'📦'):'📦';
    document.getElementById('cat-nombre').value=cat?cat.nombre_categoria:'';
    document.getElementById('cat-desc').value=cat?(cat.descripcion||''):'';
    catModal.show();
}

function submitCat(){
    const id=document.getElementById('cat-id').value;
    fetch('/nexusgear/admin/categorias.php',{
        method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=${id?'update':'create'}&id_categoria=${id}&csrf_token=${window.csrfToken}`
            +`&nombre_categoria=${encodeURIComponent(document.getElementById('cat-nombre').value)}`
            +`&descripcion=${encodeURIComponent(document.getElementById('cat-desc').value)}`
            +`&icono=${encodeURIComponent(document.getElementById('cat-icono').value)}`
    }).then(r=>r.json()).then(d=>{
        showToast(d.message,d.success?'success':'error');
        if(d.success){catModal.hide();setTimeout(()=>location.reload(),1000);}
    });
}

function deleteCat(id,nombre,count){
    if(count>0){showToast(`No se puede eliminar: tiene ${count} producto(s).`,'warning');return;}
    showConfirmModal('Eliminar Categoría',`¿Eliminar la categoría "${nombre}"?`)
        .then(ok=>{
            if(!ok) return;
            fetch('/nexusgear/admin/categorias.php',{
                method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=delete&id_categoria=${id}&csrf_token=${window.csrfToken}`
            }).then(r=>r.json()).then(d=>{
                showToast(d.message,d.success?'success':'error');
                if(d.success) setTimeout(()=>location.reload(),1000);
            });
        });
}
</script>
</body>
</html>
