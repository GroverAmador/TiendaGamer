<?php
// ============================================================
// NexusGear — Admin Dashboard (SQL errors fixed)
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /nexusgear/unauthorized.php'); exit;
}
require_once __DIR__ . '/../config/database.php';

// ---- Helper: ejecuta query y devuelve valor escalar ----
function qs(mysqli $c, string $sql): string {
    $r = mysqli_query($c, $sql);
    if (!$r) return '0';
    $row = mysqli_fetch_row($r);
    return (string)($row[0] ?? '0');
}

// ---- Estadísticas ----
$totalVentas    = (int)qs($conn, "SELECT COUNT(*) FROM Venta");
$totalProductos = (int)qs($conn, "SELECT COUNT(*) FROM Producto WHERE estado=1");
$totalClientes  = (int)qs($conn, "SELECT COUNT(*) FROM Usuario WHERE rol='cliente'");
$ingresosMes    = (float)qs($conn,
    "SELECT COALESCE(SUM(total),0) FROM Venta
     WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE())");

// ---- Ventas por mes (últimos 6) ----
$salesChart = [];
for ($i = 5; $i >= 0; $i--) {
    $r = mysqli_query($conn,
        "SELECT DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL $i MONTH),'%b %Y') AS mes,
                COUNT(*) AS ventas
         FROM Venta
         WHERE MONTH(fecha)=MONTH(DATE_SUB(CURDATE(),INTERVAL $i MONTH))
           AND YEAR(fecha)=YEAR(DATE_SUB(CURDATE(),INTERVAL $i MONTH))");
    $salesChart[] = $r ? mysqli_fetch_assoc($r) : ['mes'=>'','ventas'=>0];
}

// ---- Top 5 productos ----
$topProducts = [];
$r = mysqli_query($conn,
    "SELECT p.nombre, COALESCE(SUM(dv.cantidad),0) AS total_vendido
     FROM Detalle_Venta dv
     JOIN Producto p ON dv.id_producto=p.id_producto
     GROUP BY dv.id_producto
     ORDER BY total_vendido DESC LIMIT 5");
if ($r) while ($row = mysqli_fetch_assoc($r)) $topProducts[] = $row;

// ---- Ventas recientes — LEFT JOIN para usuarios eliminados ----
$recentSales = [];
$r = mysqli_query($conn,
    "SELECT v.*, COALESCE(u.nombre,'(Usuario eliminado)') AS usuario
     FROM Venta v
     LEFT JOIN Usuario u ON v.id_usuario=u.id_usuario
     ORDER BY v.fecha DESC LIMIT 10");
if ($r) while ($row = mysqli_fetch_assoc($r)) $recentSales[] = $row;

// ---- Stock bajo ----
$lowStock = [];
$r = mysqli_query($conn,
    "SELECT p.*, c.nombre_categoria
     FROM Producto p
     LEFT JOIN Categoria c ON p.id_categoria=c.id_categoria
     WHERE p.stock<=5 AND p.estado=1
     ORDER BY p.stock ASC LIMIT 15");
if ($r) while ($row = mysqli_fetch_assoc($r)) $lowStock[] = $row;

function sB(string $s): string {
    $m=['pendiente'=>['status-pendiente','Pendiente'],'pagado'=>['status-pagado','Pagado'],'entregado'=>['status-entregado','Entregado']];
    $i=$m[$s]??['badge-cyan',$s];
    return '<span class="order-status-badge '.$i[0].'">'.$i[1].'</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Dashboard — NexusGear Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body style="background:var(--bg-primary);">
<div class="admin-layout">
<?php include __DIR__.'/../views/admin_sidebar.php'; ?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-2">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="bi bi-list"></i></button>
            <div>
                <h1 class="admin-page-title">Dashboard</h1>
                <div class="admin-breadcrumb">Admin / Dashboard</div>
            </div>
        </div>
        <div style="color:var(--text-muted);font-size:.82rem;">
            <i class="bi bi-clock me-1" style="color:var(--neon-cyan);"></i><?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <div class="admin-content">
        <?php include __DIR__.'/../views/toast.php'; ?>

        <!-- Stat cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card stat-card-cyan">
                    <i class="bi bi-bag-check-fill stat-card-icon" style="color:var(--neon-cyan);"></i>
                    <div class="stat-card-value" data-counter="<?= $totalVentas ?>">0</div>
                    <div class="stat-card-label">Total Ventas</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card stat-card-green">
                    <i class="bi bi-currency-dollar stat-card-icon" style="color:var(--neon-green);"></i>
                    <div class="stat-card-value">$<?= number_format($ingresosMes,0) ?></div>
                    <div class="stat-card-label">Ingresos del Mes</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card stat-card-violet">
                    <i class="bi bi-controller stat-card-icon" style="color:var(--neon-violet);"></i>
                    <div class="stat-card-value" data-counter="<?= $totalProductos ?>">0</div>
                    <div class="stat-card-label">Productos</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card stat-card-pink">
                    <i class="bi bi-people-fill stat-card-icon" style="color:var(--neon-pink);"></i>
                    <div class="stat-card-value" data-counter="<?= $totalClientes ?>">0</div>
                    <div class="stat-card-label">Clientes</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row g-3 mb-4">
            <div class="col-lg-7">
                <div class="chart-card">
                    <div class="chart-card-title">
                        <i class="bi bi-graph-up-arrow me-2" style="color:var(--neon-cyan);"></i>Ventas por Mes
                    </div>
                    <canvas id="salesChart" height="115"></canvas>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="chart-card">
                    <div class="chart-card-title">
                        <i class="bi bi-trophy-fill me-2" style="color:var(--neon-green);"></i>Top 5 Productos
                    </div>
                    <canvas id="topChart" height="155"></canvas>
                </div>
            </div>
        </div>

        <!-- Tables -->
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="admin-table-card">
                    <div class="admin-table-header">
                        <h6 class="admin-table-title">
                            <i class="bi bi-clock-history me-2" style="color:var(--neon-cyan);"></i>Ventas Recientes
                        </h6>
                        <a href="/nexusgear/admin/ventas.php" class="btn btn-outline-cyan btn-sm" style="font-size:.78rem;">Ver todas</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>ID</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Fecha</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentSales as $s): ?>
                            <tr>
                                <td><span class="badge-cyan" style="font-size:.72rem;">#<?= str_pad($s['id_venta'],4,'0',STR_PAD_LEFT) ?></span></td>
                                <td style="color:var(--text-primary);font-size:.88rem;"><?= htmlspecialchars($s['usuario']) ?></td>
                                <td style="color:var(--neon-cyan);font-weight:600;">$<?= number_format((float)$s['total'],2) ?></td>
                                <td><?= sB($s['estado_venta']) ?></td>
                                <td style="color:var(--text-muted);font-size:.8rem;"><?= date('d/m/Y',strtotime($s['fecha'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentSales)): ?>
                            <tr><td colspan="5" class="text-center" style="color:var(--text-muted);padding:24px;">Sin ventas aún</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="admin-table-card">
                    <div class="admin-table-header">
                        <h6 class="admin-table-title">
                            <i class="bi bi-exclamation-triangle-fill me-2" style="color:var(--neon-amber);"></i>Stock Bajo
                        </h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Producto</th><th>Categoría</th><th>Stock</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($lowStock as $p): ?>
                            <tr>
                                <td style="color:var(--text-primary);font-size:.85rem;"><?= htmlspecialchars($p['nombre']) ?></td>
                                <td style="color:var(--text-muted);font-size:.78rem;"><?= htmlspecialchars($p['nombre_categoria']??'') ?></td>
                                <td><span class="<?= (int)$p['stock']===0?'stock-empty':'stock-low' ?>"><?= (int)$p['stock'] ?></span></td>
                                <td><a href="/nexusgear/admin/productos.php" class="action-btn action-btn-edit"><i class="bi bi-pencil-fill"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($lowStock)): ?>
                            <tr><td colspan="4" class="text-center" style="color:var(--neon-green);padding:20px;">
                                <i class="bi bi-check-circle-fill me-1"></i>Todo en stock
                            </td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /.admin-content -->
</div><!-- /.admin-main -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script>
Chart.defaults.color = '#8b8ba8';
Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';

new Chart(document.getElementById('salesChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($salesChart,'mes')) ?>,
        datasets: [{
            label: 'Ventas',
            data:  <?= json_encode(array_column($salesChart,'ventas')) ?>,
            borderColor: '#00d8f0',
            backgroundColor: 'rgba(0,216,240,0.07)',
            borderWidth: 2, fill: true, tension: 0.4,
            pointBackgroundColor: '#00d8f0', pointRadius: 4, pointHoverRadius: 6
        }]
    },
    options: { responsive:true,plugins:{legend:{labels:{color:'#8b8ba8'}}},
        scales:{
            x:{ticks:{color:'#8b8ba8'},grid:{color:'rgba(255,255,255,0.04)'}},
            y:{ticks:{color:'#8b8ba8',stepSize:1},grid:{color:'rgba(255,255,255,0.04)'},beginAtZero:true}
        }
    }
});

<?php
$topNames = array_map(fn($p) => mb_substr($p['nombre'],0,16).'…', $topProducts);
$topVals  = array_column($topProducts,'total_vendido');
?>
new Chart(document.getElementById('topChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($topNames) ?: '[]' ?>,
        datasets: [{
            label: 'Unidades',
            data: <?= json_encode($topVals) ?: '[]' ?>,
            backgroundColor: ['#7c3aed','#00d8f0','#84cc16','#f43f8e','#f59e0b'],
            borderRadius: 5
        }]
    },
    options: { responsive:true,indexAxis:'y',plugins:{legend:{display:false}},
        scales:{
            x:{ticks:{color:'#8b8ba8'},grid:{color:'rgba(255,255,255,0.04)'},beginAtZero:true},
            y:{ticks:{color:'#8b8ba8',font:{size:11}},grid:{display:false}}
        }
    }
});
</script>
</body>
</html>
