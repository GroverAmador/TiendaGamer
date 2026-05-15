<?php
// ============================================================
// NexusGear - Admin: Coupon Management
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /nexusgear/unauthorized.php'); exit;
}
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if(($_POST['csrf_token']??'')!==$_SESSION['csrf_token']){echo json_encode(['success'=>false,'message'=>'Token inválido.']);exit;}
    $action=$_POST['action']??'';

    if($action==='create'||$action==='update'){
        $codigo   =strtoupper(trim($_POST['codigo']??''));
        $descuento=(float)($_POST['descuento']??0);
        $expira   =trim($_POST['fecha_expiracion']??'');
        $maxUsos  =(int)($_POST['usos_maximos']??100);
        if(empty($codigo)||$descuento<=0){echo json_encode(['success'=>false,'message'=>'Código y descuento son obligatorios.']);exit;}
        if($action==='create'){
            $stmt=mysqli_prepare($conn,"INSERT INTO Cupon (codigo,descuento,fecha_expiracion,usos_maximos) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($stmt,'dssi',$descuento,$codigo,$expira,$maxUsos);
            // fix param order
            $stmt2=mysqli_prepare($conn,"INSERT INTO Cupon (codigo,descuento,fecha_expiracion,usos_maximos) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($stmt2,'sdsi',$codigo,$descuento,$expira,$maxUsos);
            $ok=mysqli_stmt_execute($stmt2); mysqli_stmt_close($stmt2);
            echo json_encode(['success'=>$ok,'message'=>$ok?'Cupón creado.':'Error (el código puede ya existir).']);
        } else {
            $id=(int)($_POST['id_cupon']??0);
            $stmt=mysqli_prepare($conn,"UPDATE Cupon SET codigo=?,descuento=?,fecha_expiracion=?,usos_maximos=? WHERE id_cupon=?");
            mysqli_stmt_bind_param($stmt,'sdsii',$codigo,$descuento,$expira,$maxUsos,$id);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            echo json_encode(['success'=>$ok,'message'=>$ok?'Cupón actualizado.':'Error.']);
        }
        exit;
    }
    if($action==='toggle_active'){
        $id=(int)($_POST['id_cupon']??0);$activo=(int)($_POST['activo']??0);
        $stmt=mysqli_prepare($conn,"UPDATE Cupon SET activo=? WHERE id_cupon=?");
        mysqli_stmt_bind_param($stmt,'ii',$activo,$id);$ok=mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
        echo json_encode(['success'=>$ok]);exit;
    }
    if($action==='delete'){
        $id=(int)($_POST['id_cupon']??0);
        $stmt=mysqli_prepare($conn,"DELETE FROM Cupon WHERE id_cupon=?");
        mysqli_stmt_bind_param($stmt,'i',$id);$ok=mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
        echo json_encode(['success'=>$ok,'message'=>$ok?'Cupón eliminado.':'Error.']);exit;
    }
}

$cupones=[];
$res=mysqli_query($conn,"SELECT * FROM Cupon ORDER BY id_cupon DESC");
if($res) while($r=mysqli_fetch_assoc($res)) $cupones[]=$r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupones — NexusGear Admin</title>
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
            <h1 class="admin-page-title d-inline-block ms-2">Cupones de Descuento</h1>
        </div>
        <button class="btn btn-neon btn-sm" onclick="openCouponModal()">
            <i class="bi bi-plus-circle-fill me-1"></i>Nuevo Cupón
        </button>
    </div>
    <div class="admin-content">
        <?php include __DIR__.'/../views/toast.php'; ?>
        <div class="admin-table-card">
            <div class="admin-table-header">
                <h6 class="admin-table-title">
                    <i class="bi bi-ticket-perforated-fill me-2" style="color:var(--neon-cyan);"></i>Cupones
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr>
                        <th>Código</th><th>Descuento</th><th>Expira</th><th>Usos</th><th>Estado</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach($cupones as $c): ?>
                        <?php $expired=!empty($c['fecha_expiracion'])&&strtotime($c['fecha_expiracion'])<time(); ?>
                        <tr>
                            <td>
                                <span style="font-family:'Oxanium',monospace;font-size:1rem;font-weight:700;
                                             color:var(--neon-cyan);letter-spacing:2px;">
                                    <?= htmlspecialchars($c['codigo']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-green">
                                    <i class="bi bi-percent me-1"></i><?= number_format((float)$c['descuento'],1) ?>% OFF
                                </span>
                            </td>
                            <td style="color:<?= $expired?'var(--neon-pink)':'var(--text-secondary)' ?>;font-size:0.85rem;">
                                <?php if($c['fecha_expiracion']): ?>
                                <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y',strtotime($c['fecha_expiracion'])) ?>
                                <?php if($expired): ?><span class="badge-red ms-1" style="font-size:0.68rem;">Expirado</span><?php endif; ?>
                                <?php else: ?><span style="color:var(--text-muted);">Sin límite</span><?php endif; ?>
                            </td>
                            <td>
                                <span style="color:var(--text-secondary);">
                                    <i class="bi bi-people me-1"></i><?= (int)$c['usos_actuales'] ?> / <?= (int)$c['usos_maximos'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input toggle-coupon" type="checkbox"
                                           data-id="<?= $c['id_cupon'] ?>" <?= $c['activo']?'checked':'' ?>>
                                </div>
                            </td>
                            <td>
                                <button class="action-btn action-btn-edit"
                                        onclick="openCouponModal(<?= htmlspecialchars(json_encode($c)) ?>)"
                                        title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="action-btn action-btn-delete"
                                        onclick="deleteCoupon(<?= $c['id_cupon'] ?>,'<?= htmlspecialchars($c['codigo'],ENT_QUOTES) ?>')"
                                        title="Eliminar">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($cupones)): ?>
                        <tr><td colspan="6" class="text-center" style="color:var(--text-muted);padding:32px;">Sin cupones.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="couponModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="couponModalTitle">
                    <i class="bi bi-ticket-perforated me-2"></i>Nuevo Cupón
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="coupon-id" value="">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-code-slash me-1"></i>Código *</label>
                    <input type="text" id="c-codigo" class="form-control" placeholder="NEXUS10"
                           style="text-transform:uppercase;font-family:monospace;font-size:1.1rem;">
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-percent me-1"></i>Descuento (%) *</label>
                    <input type="number" id="c-descuento" class="form-control" min="1" max="100" placeholder="10">
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-calendar3 me-1"></i>Fecha de expiración</label>
                    <input type="date" id="c-expira" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-people me-1"></i>Usos máximos</label>
                    <input type="number" id="c-usos" class="form-control" value="100" min="1">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-violet" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </button>
                <button class="btn btn-neon" onclick="submitCoupon()">
                    <i class="bi bi-floppy-fill me-1"></i>Guardar Cupón
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script>
const couponModal=new bootstrap.Modal(document.getElementById('couponModal'));
window.csrfToken='<?= htmlspecialchars($_SESSION['csrf_token']) ?>';

function openCouponModal(c=null){
    document.getElementById('couponModalTitle').innerHTML=c?'<i class="bi bi-pencil-square me-2"></i>Editar Cupón':'<i class="bi bi-ticket-perforated me-2"></i>Nuevo Cupón';
    document.getElementById('coupon-id').value=c?c.id_cupon:'';
    document.getElementById('c-codigo').value=c?c.codigo:'';
    document.getElementById('c-descuento').value=c?c.descuento:'';
    document.getElementById('c-expira').value=c?(c.fecha_expiracion||''):'';
    document.getElementById('c-usos').value=c?c.usos_maximos:100;
    couponModal.show();
}

function submitCoupon(){
    const id=document.getElementById('coupon-id').value;
    fetch('/nexusgear/admin/cupones.php',{
        method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=${id?'update':'create'}&id_cupon=${id}&csrf_token=${window.csrfToken}`
            +`&codigo=${encodeURIComponent(document.getElementById('c-codigo').value.toUpperCase())}`
            +`&descuento=${encodeURIComponent(document.getElementById('c-descuento').value)}`
            +`&fecha_expiracion=${encodeURIComponent(document.getElementById('c-expira').value)}`
            +`&usos_maximos=${encodeURIComponent(document.getElementById('c-usos').value)}`
    }).then(r=>r.json()).then(d=>{
        showToast(d.message,d.success?'success':'error');
        if(d.success){couponModal.hide();setTimeout(()=>location.reload(),1000);}
    });
}

function deleteCoupon(id,codigo){
    showConfirmModal('Eliminar Cupón',`¿Eliminar el cupón "${codigo}"?`)
        .then(ok=>{
            if(!ok) return;
            fetch('/nexusgear/admin/cupones.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=delete&id_cupon=${id}&csrf_token=${window.csrfToken}`
            }).then(r=>r.json()).then(d=>{
                showToast(d.message,d.success?'success':'error');
                if(d.success) setTimeout(()=>location.reload(),1000);
            });
        });
}

document.querySelectorAll('.toggle-coupon').forEach(sw=>{
    sw.addEventListener('change',function(){
        fetch('/nexusgear/admin/cupones.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=toggle_active&id_cupon=${this.dataset.id}&activo=${this.checked?1:0}&csrf_token=${window.csrfToken}`
        }).then(r=>r.json()).then(d=>showToast(d.success?'Estado actualizado.':'Error.',d.success?'success':'error'));
    });
});

document.getElementById('c-codigo')?.addEventListener('input',function(){this.value=this.value.toUpperCase();});
</script>
</body>
</html>
