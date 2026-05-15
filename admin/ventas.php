<?php
// ============================================================
// NexusGear - Admin: Sales Management
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /nexusgear/unauthorized.php'); exit;
}
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action=$_POST['action']??'';
    if ($action==='update_status') {
        $id=(int)($_POST['id_venta']??0);
        $status=$_POST['estado_venta']??'';
        if (!in_array($status,['pendiente','pagado','entregado'])){echo json_encode(['success'=>false]);exit;}
        $stmt=mysqli_prepare($conn,"UPDATE Venta SET estado_venta=? WHERE id_venta=?");
        mysqli_stmt_bind_param($stmt,'si',$status,$id);
        $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        echo json_encode(['success'=>$ok]);exit;
    }
    if ($action==='get_detail') {
        $id=(int)($_POST['id_venta']??0);
        $stmt=mysqli_prepare($conn,"SELECT dv.*,p.nombre,p.marca,p.imagen FROM Detalle_Venta dv JOIN Producto p ON dv.id_producto=p.id_producto WHERE dv.id_venta=?");
        mysqli_stmt_bind_param($stmt,'i',$id);
        mysqli_stmt_execute($stmt);
        $items=[]; while($r=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) $items[]=$r;
        mysqli_stmt_close($stmt);
        echo json_encode(['success'=>true,'items'=>$items]);exit;
    }
}

$statusFilter=trim($_GET['status']??'');
$searchUser  =trim($_GET['user']??'');
$dateFrom    =$_GET['from']??'';
$dateTo      =$_GET['to']??'';
$where="WHERE 1=1";
if($statusFilter) $where.=" AND v.estado_venta='".mysqli_real_escape_string($conn,$statusFilter)."'";
if($searchUser)   $where.=" AND u.nombre LIKE '%".mysqli_real_escape_string($conn,$searchUser)."%'";
if($dateFrom)     $where.=" AND DATE(v.fecha)>='".mysqli_real_escape_string($conn,$dateFrom)."'";
if($dateTo)       $where.=" AND DATE(v.fecha)<='".mysqli_real_escape_string($conn,$dateTo)."'";

$ventas=[];
$res=mysqli_query($conn,"SELECT v.*,u.nombre AS usuario,u.correo FROM Venta v JOIN Usuario u ON v.id_usuario=u.id_usuario $where ORDER BY v.fecha DESC");
if($res) while($r=mysqli_fetch_assoc($res)) $ventas[]=$r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas — NexusGear Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/admin.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/client.css">
</head>
<body style="background:var(--bg-primary);">
<div class="admin-layout">
<?php include __DIR__.'/../views/admin_sidebar.php'; ?>
<div class="admin-main">
    <div class="admin-topbar">
        <div>
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="bi bi-list"></i></button>
            <h1 class="admin-page-title d-inline-block ms-2">Ventas</h1>
        </div>
        <span style="color:var(--text-muted);font-size:0.85rem;">
            <i class="bi bi-bag-check me-1" style="color:var(--neon-cyan);"></i><?= count($ventas) ?> ventas
        </span>
    </div>
    <div class="admin-content">
        <?php include __DIR__.'/../views/toast.php'; ?>

        <!-- Filters -->
        <form method="GET" class="admin-table-card mb-4 p-3">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <select name="status" class="form-select" style="width:auto;">
                    <option value="">Todos los estados</option>
                    <option value="pendiente" <?= $statusFilter==='pendiente'?'selected':'' ?>>Pendiente</option>
                    <option value="pagado"    <?= $statusFilter==='pagado'?'selected':'' ?>>Pagado</option>
                    <option value="entregado" <?= $statusFilter==='entregado'?'selected':'' ?>>Entregado</option>
                </select>
                <input type="text" name="user" class="form-control" style="width:200px;"
                       placeholder="Buscar cliente..." value="<?= htmlspecialchars($searchUser) ?>">
                <input type="date" name="from" class="form-control" style="width:160px;" value="<?= htmlspecialchars($dateFrom) ?>">
                <input type="date" name="to"   class="form-control" style="width:160px;" value="<?= htmlspecialchars($dateTo) ?>">
                <button type="submit" class="btn btn-outline-cyan btn-sm">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="/nexusgear/admin/ventas.php" class="btn btn-sm" style="color:var(--text-muted);">
                    <i class="bi bi-x-circle me-1"></i>Limpiar
                </a>
            </div>
        </form>

        <div class="admin-table-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr>
                        <th>ID</th><th>Cliente</th><th>Fecha</th><th>Total</th>
                        <th>Cupón</th><th>Dto.</th><th>Estado</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach($ventas as $v): ?>
                        <tr>
                            <td><span class="badge-cyan">#<?= str_pad($v['id_venta'],5,'0',STR_PAD_LEFT) ?></span></td>
                            <td>
                                <div style="font-weight:600;color:var(--text-primary);font-size:0.9rem;">
                                    <?= htmlspecialchars($v['usuario']) ?>
                                </div>
                                <div style="color:var(--text-muted);font-size:0.78rem;">
                                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($v['correo']) ?>
                                </div>
                            </td>
                            <td style="color:var(--text-muted);font-size:0.85rem;">
                                <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y H:i',strtotime($v['fecha'])) ?>
                            </td>
                            <td style="color:var(--neon-cyan);font-weight:700;">$<?= number_format((float)$v['total'],2) ?></td>
                            <td>
                                <?php if($v['cupon_aplicado']): ?>
                                <span class="badge-cyan" style="font-size:0.72rem;font-family:monospace;">
                                    <i class="bi bi-ticket-perforated me-1"></i><?= htmlspecialchars($v['cupon_aplicado']) ?>
                                </span>
                                <?php else: ?>
                                <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--neon-green);">
                                <?= $v['descuento_aplicado']>0 ? number_format((float)$v['descuento_aplicado'],1).'%' : '—' ?>
                            </td>
                            <td>
                                <select class="status-select" data-id="<?= $v['id_venta'] ?>" onchange="updateStatus(this)">
                                    <option value="pendiente" <?= $v['estado_venta']==='pendiente'?'selected':'' ?>>Pendiente</option>
                                    <option value="pagado"    <?= $v['estado_venta']==='pagado'?'selected':'' ?>>Pagado</option>
                                    <option value="entregado" <?= $v['estado_venta']==='entregado'?'selected':'' ?>>Entregado</option>
                                </select>
                            </td>
                            <td>
                                <button class="action-btn action-btn-edit" onclick="viewDetail(<?= $v['id_venta'] ?>)" title="Ver detalle">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($ventas)): ?>
                        <tr><td colspan="8" class="text-center" style="color:var(--text-muted);padding:32px;">
                            <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.4;"></i>Sin ventas.
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detail-modal-title">
                    <i class="bi bi-receipt me-2"></i>Detalle del Pedido
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detail-modal-body">
                <p style="color:var(--text-muted);">Cargando...</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script>
window.csrfToken='<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
const detailModal=new bootstrap.Modal(document.getElementById('detailModal'));

function updateStatus(sel){
    const id=sel.dataset.id;
    fetch('/nexusgear/admin/ventas.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=update_status&id_venta=${id}&estado_venta=${sel.value}&csrf_token=${window.csrfToken}`
    }).then(r=>r.json()).then(d=>showToast(d.success?'Estado actualizado.':'Error.',d.success?'success':'error'));
}

function viewDetail(id){
    document.getElementById('detail-modal-title').innerHTML=`<i class="bi bi-receipt me-2"></i>Pedido #${String(id).padStart(5,'0')}`;
    document.getElementById('detail-modal-body').innerHTML='<div class="text-center p-4"><div style="width:36px;height:36px;border-radius:50%;border:2px solid var(--border-subtle);border-top-color:var(--neon-cyan);animation:iconSpin 0.8s linear infinite;margin:0 auto;"></div></div>';
    detailModal.show();
    fetch('/nexusgear/admin/ventas.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=get_detail&id_venta=${id}&csrf_token=${window.csrfToken}`
    }).then(r=>r.json()).then(d=>{
        if(!d.success||!d.items.length){
            document.getElementById('detail-modal-body').innerHTML='<p style="color:var(--text-muted);">Sin detalles.</p>';return;
        }
        let html='<div class="table-responsive"><table class="table"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr></thead><tbody>';
        d.items.forEach(item=>{
            html+=`<tr>
                <td><div class="d-flex align-items-center gap-2">
                    <img src="${item.imagen||''}" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid var(--border-subtle);">
                    <div><div style="color:var(--text-primary);font-size:0.88rem;font-weight:600;">${item.nombre}</div>
                    <div style="color:var(--text-muted);font-size:0.78rem;">${item.marca||''}</div></div>
                </div></td>
                <td style="color:var(--text-secondary);">${item.cantidad}</td>
                <td style="color:var(--text-secondary);">$${parseFloat(item.precio_unitario).toFixed(2)}</td>
                <td style="color:var(--neon-cyan);font-weight:700;">$${parseFloat(item.subtotal).toFixed(2)}</td>
            </tr>`;
        });
        html+='</tbody></table></div>';
        document.getElementById('detail-modal-body').innerHTML=html;
    });
}
</script>
</body>
</html>
