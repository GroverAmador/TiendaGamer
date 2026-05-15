<?php
// ============================================================
// NexusGear - Purchase History with Review Links
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /nexusgear/auth/login.php'); exit;
}
require_once __DIR__ . '/../config/database.php';

$uid = (int)$_SESSION['id_usuario'];
// PASO 1: obtener TODAS las ventas primero y cerrar el statement
// (evita "Commands out of sync" al hacer queries anidadas)
$stmt = mysqli_prepare($conn, "SELECT * FROM Venta WHERE id_usuario=? ORDER BY fecha DESC");
mysqli_stmt_bind_param($stmt,'i',$uid);
mysqli_stmt_execute($stmt);
$ventasResult = mysqli_stmt_get_result($stmt);
$ventas = [];
while ($v = mysqli_fetch_assoc($ventasResult)) $ventas[] = $v;
mysqli_stmt_free_result($stmt);
mysqli_stmt_close($stmt);   // cerrar ANTES de hacer queries internas

// PASO 2: para cada venta, obtener sus items (ya sin conflicto)
foreach ($ventas as &$v) {
    $dStmt = mysqli_prepare($conn,
        "SELECT dv.*, p.nombre, p.marca, p.imagen, p.id_producto FROM Detalle_Venta dv
         LEFT JOIN Producto p ON dv.id_producto=p.id_producto WHERE dv.id_venta=?");
    mysqli_stmt_bind_param($dStmt,'i',$v['id_venta']);
    mysqli_stmt_execute($dStmt);
    $dResult = mysqli_stmt_get_result($dStmt);
    $items   = [];
    while ($d = mysqli_fetch_assoc($dResult)) $items[] = $d;
    mysqli_stmt_free_result($dStmt);
    mysqli_stmt_close($dStmt);
    $v['items'] = $items;
}
unset($v); // limpiar referencia del foreach

// Get product IDs the user already reviewed
$reviewedIds = [];
$rStmt = mysqli_prepare($conn, "SELECT id_producto FROM Resena WHERE id_usuario=?");
mysqli_stmt_bind_param($rStmt,'i',$uid);
mysqli_stmt_execute($rStmt);
$rRes = mysqli_stmt_get_result($rStmt);
while ($r = mysqli_fetch_assoc($rRes)) $reviewedIds[] = (int)$r['id_producto'];
mysqli_stmt_close($rStmt);

function fechaEs(string $d): string {
    $m=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $t=strtotime($d);
    return date('d',$t).' de '.$m[date('n',$t)-1].' de '.date('Y',$t);
}

function statusBadge(string $s): string {
    $map=[
        'pendiente'  => ['status-pendiente','bi-clock-fill','Pendiente'],
        'pagado'     => ['status-pagado','bi-check-circle-fill','Pagado'],
        'entregado'  => ['status-entregado','bi-box-seam-fill','Entregado'],
    ];
    $i=$map[$s]??['badge-cyan','bi-question-circle',$s];
    return '<span class="order-status-badge '.$i[0].'"><i class="bi '.$i[1].' me-1"></i>'.$i[2].'</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/client.css">
</head>
<body class="client-page">
<?php include __DIR__ . '/../views/header.php'; ?>
<?php include __DIR__ . '/../views/toast.php'; ?>

<div class="page-header">
    <div class="container-xl">
        <h1><i class="bi bi-list-check me-2" style="color:var(--neon-cyan);"></i>Mis Pedidos</h1>
    </div>
</div>

<div class="container-xl py-4">
    <?php if (empty($ventas)): ?>
    <div class="empty-state">
        <i class="bi bi-box-seam" style="font-size:5rem;opacity:0.3;display:block;margin-bottom:24px;color:var(--neon-cyan);"></i>
        <h3>Aún no tienes pedidos</h3>
        <p>Realiza tu primera compra para verla aquí.</p>
        <a href="/nexusgear/client/productos.php" class="btn btn-neon mt-3">
            <i class="bi bi-grid-3x3-gap me-2"></i>Ver Productos
        </a>
    </div>
    <?php else: ?>
    <div class="accordion" id="ordersAccordion" style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($ventas as $i => $v): ?>
        <div class="accordion-item animate-on-scroll">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>"
                        type="button" data-bs-toggle="collapse"
                        data-bs-target="#order-<?= $v['id_venta'] ?>">
                    <div class="d-flex align-items-center gap-3 w-100 flex-wrap" style="margin-right:16px;">
                        <span style="font-family:'Oxanium',sans-serif;font-weight:700;color:var(--neon-cyan);">
                            <i class="bi bi-receipt me-1"></i>#<?= str_pad($v['id_venta'],5,'0',STR_PAD_LEFT) ?>
                        </span>
                        <?= statusBadge($v['estado_venta']) ?>
                        <span style="color:var(--text-secondary);font-size:0.85rem;">
                            <i class="bi bi-calendar3 me-1"></i><?= fechaEs($v['fecha']) ?>
                        </span>
                        <span style="margin-left:auto;font-family:'Oxanium',sans-serif;
                                     font-size:1.1rem;font-weight:700;color:var(--neon-cyan);">
                            $<?= number_format((float)$v['total'],2) ?>
                        </span>
                    </div>
                </button>
            </h2>
            <div id="order-<?= $v['id_venta'] ?>" class="accordion-collapse collapse <?= $i===0?'show':'' ?>">
                <div class="accordion-body">
                    <!-- Product table -->
                    <div class="table-responsive mb-3">
                        <table class="table table-sm">
                            <thead><tr>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-center">Opinión</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($v['items'] as $item): ?>
                                <?php $pid = (int)$item['id_producto']; ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= htmlspecialchars($item['imagen']??'') ?>"
                                                 style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid var(--border-subtle);">
                                            <div>
                                                <a href="/nexusgear/client/producto_detalle.php?id=<?= $pid ?>"
                                                   style="font-weight:600;font-size:0.88rem;color:var(--text-primary);text-decoration:none;">
                                                    <?= htmlspecialchars($item['nombre']) ?>
                                                </a>
                                                <div class="brand-tag"><?= htmlspecialchars($item['marca']??'') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center" style="color:var(--text-secondary);">
                                        <?= (int)$item['cantidad'] ?>
                                    </td>
                                    <td class="text-end" style="color:var(--text-secondary);">
                                        $<?= number_format((float)$item['precio_unitario'],2) ?>
                                    </td>
                                    <td class="text-end" style="font-weight:600;color:var(--neon-cyan);">
                                        $<?= number_format((float)$item['subtotal'],2) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (in_array($pid, $reviewedIds)): ?>
                                            <span style="color:var(--neon-green);font-size:0.82rem;">
                                                <i class="bi bi-check-circle-fill me-1"></i>Reseñado
                                            </span>
                                        <?php elseif ($v['estado_venta'] === 'entregado' || $v['estado_venta'] === 'pagado'): ?>
                                            <a href="/nexusgear/client/producto_detalle.php?id=<?= $pid ?>#opiniones"
                                               class="btn btn-outline-cyan btn-sm"
                                               style="font-size:0.75rem;padding:4px 10px;">
                                                <i class="bi bi-pencil-square me-1"></i>Opinar
                                            </a>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);font-size:0.8rem;">
                                                <i class="bi bi-clock me-1"></i>Pendiente
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="d-flex flex-column align-items-end gap-1">
                        <?php if ($v['cupon_aplicado']): ?>
                        <div style="font-size:0.88rem;color:var(--text-muted);">
                            <i class="bi bi-ticket-perforated me-1" style="color:var(--neon-cyan);"></i>
                            Cupón: <strong style="color:var(--neon-cyan);"><?= htmlspecialchars($v['cupon_aplicado']) ?></strong>
                            — <?= number_format((float)$v['descuento_aplicado'],1) ?>% descuento
                        </div>
                        <?php endif; ?>
                        <div style="font-family:'Oxanium',sans-serif;font-size:1.1rem;font-weight:700;color:var(--text-primary);">
                            Total: <span style="color:var(--neon-cyan);">$<?= number_format((float)$v['total'],2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Info box about reviews -->
    <div class="alert alert-info mt-4" style="animation:fadeInUp 0.5s ease;">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>¿Quieres dejar tu opinión?</strong>
        Haz clic en el botón <strong>"Opinar"</strong> junto a cualquier producto de tus pedidos pagados o entregados.
    </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
</body>
</html>
