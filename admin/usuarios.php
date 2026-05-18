<?php
// ============================================================
// NexusGear - Admin: Users List + Delete + Block
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /nexusgear/unauthorized.php'); exit;
}
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// AJAX handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Token inválido.']); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'change_role') {
        $id  = (int)($_POST['id_usuario'] ?? 0);
        $rol = $_POST['rol'] ?? '';
        if ($id === (int)$_SESSION['id_usuario']) {
            echo json_encode(['success' => false, 'message' => 'No puedes cambiar tu propio rol.']); exit;
        }
        if (!in_array($rol, ['admin', 'cliente'])) {
            echo json_encode(['success' => false, 'message' => 'Rol inválido.']); exit;
        }
        $stmt = mysqli_prepare($conn, "UPDATE Usuario SET rol=? WHERE id_usuario=?");
        mysqli_stmt_bind_param($stmt, 'si', $rol, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Rol actualizado.' : 'Error.']);
        exit;
    }

    if ($action === 'block_user' || $action === 'unblock_user') {
        $id      = (int)($_POST['id_usuario'] ?? 0);
        $blocked = $action === 'block_user' ? 1 : 0;
        if ($id === (int)$_SESSION['id_usuario']) {
            echo json_encode(['success' => false, 'message' => 'No puedes bloquearte a ti mismo.']); exit;
        }
        $stmt = mysqli_prepare($conn, "UPDATE Usuario SET bloqueado=? WHERE id_usuario=?");
        mysqli_stmt_bind_param($stmt, 'ii', $blocked, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $msg = $blocked ? 'Usuario bloqueado.' : 'Usuario desbloqueado.';
        echo json_encode(['success' => $ok, 'message' => $ok ? $msg : 'Error al actualizar.']);
        exit;
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id_usuario'] ?? 0);
        if ($id === (int)$_SESSION['id_usuario']) {
            echo json_encode(['success' => false, 'message' => 'No puedes eliminarte a ti mismo.']); exit;
        }
        mysqli_query($conn, "DELETE FROM Favorito WHERE id_usuario=$id");
        mysqli_query($conn, "DELETE FROM Resena WHERE id_usuario=$id");
        mysqli_query($conn, "UPDATE Venta SET id_usuario=NULL WHERE id_usuario=$id");
        $stmt = mysqli_prepare($conn, "DELETE FROM Usuario WHERE id_usuario=?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Usuario eliminado.' : 'Error al eliminar.']);
        exit;
    }
}

// Fetch users
$usuarios = [];
$res = mysqli_query($conn,
    "SELECT u.*,
            COUNT(DISTINCT v.id_venta) AS total_ventas,
            COUNT(DISTINCT f.id_favorito) AS total_favoritos
     FROM Usuario u
     LEFT JOIN Venta v ON v.id_usuario = u.id_usuario
     LEFT JOIN Favorito f ON f.id_usuario = u.id_usuario
     GROUP BY u.id_usuario
     ORDER BY u.fecha_registro DESC");
if ($res) while ($r = mysqli_fetch_assoc($res)) $usuarios[] = $r;

function fechaEs(string $d): string {
    $m=['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t=strtotime($d);
    return date('d',$t).' '.$m[date('n',$t)-1].'. '.date('Y',$t);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios — NexusGear Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/admin.css">
    <style>
        .user-table td, .user-table th {
            color: var(--text-primary) !important;
            border-color: var(--border-subtle) !important;
            vertical-align: middle;
        }
        .user-table thead th {
            background: var(--bg-secondary);
            color: var(--text-secondary) !important;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 600;
            padding: 12px 16px;
        }
        .user-table tbody tr { background: transparent; transition: background 0.2s; }
        .user-table tbody tr:hover { background: rgba(0,245,255,0.04); }
        .user-table tbody tr.blocked-row { background: rgba(244,63,142,0.04); }
        .user-name   { font-weight: 600; color: var(--text-primary) !important; font-size: 0.92rem; }
        .user-email  { color: var(--text-muted) !important; font-size: 0.78rem; margin-top: 2px; }
        .user-date   { color: var(--text-secondary) !important; font-size: 0.82rem; }
        .user-stat   { color: var(--text-secondary) !important; font-size: 0.85rem; text-align: center; }
        .role-select {
            background: rgba(255,255,255,0.05) !important;
            border: 1px solid var(--border-subtle) !important;
            color: var(--text-primary) !important;
            border-radius: 6px; padding: 5px 10px;
            font-size: 0.82rem; cursor: pointer;
            transition: border-color 0.2s; min-width: 120px;
        }
        .role-select:focus { outline:none; border-color:var(--neon-cyan) !important; }
        .role-select option { background: #1a1a2e; color: var(--text-primary); }
        .user-avatar-circle {
            width:38px;height:38px;border-radius:50%;
            background:var(--gradient-brand);
            display:flex;align-items:center;justify-content:center;
            font-weight:700;font-size:.95rem;color:#fff;
            flex-shrink:0;box-shadow:0 0 10px rgba(0,245,255,0.15);
        }
        .user-avatar-circle.blocked-avatar { background:linear-gradient(135deg,#6b1f3a,#3a0d1e); filter:grayscale(.5); }
        .admin-badge {
            background:rgba(123,47,255,0.2);color:var(--neon-violet);
            border:1px solid rgba(123,47,255,0.35);
            border-radius:4px;padding:2px 8px;font-size:.72rem;font-weight:600;
        }
        .blocked-badge {
            background:rgba(244,63,142,0.15);color:#f43f8e;
            border:1px solid rgba(244,63,142,0.3);
            border-radius:4px;padding:2px 8px;font-size:.7rem;font-weight:700;
            letter-spacing:.3px;
        }
        .own-label {
            background:rgba(0,245,255,0.12);color:var(--neon-cyan);
            border:1px solid rgba(0,245,255,0.25);
            border-radius:4px;padding:2px 8px;font-size:.68rem;font-weight:600;
        }
        .stat-pill {
            display:inline-flex;align-items:center;gap:4px;
            background:rgba(255,255,255,0.04);border:1px solid var(--border-subtle);
            border-radius:20px;padding:3px 10px;font-size:.78rem;color:var(--text-secondary) !important;
        }
        .stat-pill .bi { font-size:.75rem; }
        .action-btn-block {
            width:32px;height:32px;border-radius:6px;border:none;cursor:pointer;
            display:inline-flex;align-items:center;justify-content:center;
            background:rgba(245,158,11,.12);color:#f59e0b;
            transition:all .2s;font-size:.85rem;
        }
        .action-btn-block:hover { background:rgba(245,158,11,.25); }
        .action-btn-unblock {
            width:32px;height:32px;border-radius:6px;border:none;cursor:pointer;
            display:inline-flex;align-items:center;justify-content:center;
            background:rgba(132,204,22,.12);color:#84cc16;
            transition:all .2s;font-size:.85rem;
        }
        .action-btn-unblock:hover { background:rgba(132,204,22,.25); }
    </style>
</head>
<body style="background:var(--bg-primary);">
<div class="admin-layout">
<?php include __DIR__ . '/../views/admin_sidebar.php'; ?>
<div class="admin-main">

    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-2">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="bi bi-list"></i></button>
            <div>
                <h1 class="admin-page-title">Usuarios</h1>
                <div class="admin-breadcrumb">
                    <a href="/nexusgear/admin/dashboard.php">Admin</a> / Usuarios
                </div>
            </div>
        </div>
        <div style="color:var(--text-muted);font-size:0.85rem;">
            <i class="bi bi-people-fill me-1" style="color:var(--neon-cyan);"></i>
            <?= count($usuarios) ?> usuarios registrados
        </div>
    </div>

    <div class="admin-content">
        <?php include __DIR__ . '/../views/toast.php'; ?>

        <!-- Summary cards -->
        <div class="row g-3 mb-4">
            <?php
            $totalAdmins   = count(array_filter($usuarios, fn($u) => $u['rol'] === 'admin'));
            $totalClientes = count(array_filter($usuarios, fn($u) => $u['rol'] === 'cliente'));
            $totalBloqueados = count(array_filter($usuarios, fn($u) => !empty($u['bloqueado'])));
            $totalVentasSum  = array_sum(array_column($usuarios, 'total_ventas'));
            ?>
            <div class="col-sm-3">
                <div class="stat-card stat-card-cyan" style="padding:16px 20px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <i class="bi bi-people-fill" style="font-size:1.8rem;color:var(--neon-cyan);"></i>
                        <div>
                            <div class="stat-card-value" style="font-size:1.5rem;"><?= count($usuarios) ?></div>
                            <div class="stat-card-label">Total usuarios</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="stat-card stat-card-violet" style="padding:16px 20px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <i class="bi bi-controller" style="font-size:1.8rem;color:var(--neon-violet);"></i>
                        <div>
                            <div class="stat-card-value" style="font-size:1.5rem;"><?= $totalClientes ?></div>
                            <div class="stat-card-label">Clientes</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="stat-card stat-card-green" style="padding:16px 20px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <i class="bi bi-bag-check-fill" style="font-size:1.8rem;color:var(--neon-green);"></i>
                        <div>
                            <div class="stat-card-value" style="font-size:1.5rem;"><?= $totalVentasSum ?></div>
                            <div class="stat-card-label">Compras totales</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="stat-card" style="padding:16px 20px;border-color:rgba(244,63,142,.2);">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <i class="bi bi-slash-circle-fill" style="font-size:1.8rem;color:#f43f8e;"></i>
                        <div>
                            <div class="stat-card-value" style="font-size:1.5rem;color:#f43f8e;"><?= $totalBloqueados ?></div>
                            <div class="stat-card-label">Bloqueados</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="admin-table-card">
            <div class="admin-table-header">
                <h6 class="admin-table-title">
                    <i class="bi bi-person-lines-fill me-2" style="color:var(--neon-cyan);"></i>Lista de Usuarios
                </h6>
                <div class="admin-search">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="user-search" class="form-control"
                           placeholder="Buscar usuario..." style="padding-left:36px;">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table mb-0 user-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th class="text-center">Compras</th>
                            <th class="text-center">Favoritos</th>
                            <th>Registro</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="users-tbody">
                        <?php foreach ($usuarios as $u): ?>
                        <?php
                        $isMe      = (int)$u['id_usuario'] === (int)$_SESSION['id_usuario'];
                        $bloqueado = !empty($u['bloqueado']);
                        ?>
                        <tr class="<?= $bloqueado ? 'blocked-row' : '' ?>"
                            data-name="<?= strtolower(htmlspecialchars($u['nombre'])) ?>"
                            data-email="<?= strtolower(htmlspecialchars($u['correo'])) ?>">

                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar-circle <?= $bloqueado ? 'blocked-avatar' : '' ?>">
                                        <?= strtoupper(substr($u['nombre'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="user-name d-flex align-items-center gap-2 flex-wrap">
                                            <?= htmlspecialchars($u['nombre']) ?>
                                            <?php if ($isMe): ?>
                                                <span class="own-label"><i class="bi bi-person-check me-1"></i>Tú</span>
                                            <?php endif; ?>
                                            <?php if ($u['rol'] === 'admin'): ?>
                                                <span class="admin-badge"><i class="bi bi-shield-fill me-1"></i>Admin</span>
                                            <?php endif; ?>
                                            <?php if ($bloqueado): ?>
                                                <span class="blocked-badge"><i class="bi bi-slash-circle me-1"></i>BLOQUEADO</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-email">
                                            <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($u['correo']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?php if ($isMe): ?>
                                    <span style="color:var(--neon-violet);font-size:0.85rem;font-weight:600;">
                                        <i class="bi bi-shield-fill me-1"></i>Administrador
                                    </span>
                                <?php else: ?>
                                    <select class="role-select" data-id="<?= $u['id_usuario'] ?>" onchange="changeRole(this)">
                                        <option value="cliente" <?= $u['rol']==='cliente'?'selected':'' ?>>Cliente</option>
                                        <option value="admin"   <?= $u['rol']==='admin'  ?'selected':'' ?>>Administrador</option>
                                    </select>
                                <?php endif; ?>
                            </td>

                            <td class="user-stat">
                                <span class="stat-pill">
                                    <i class="bi bi-bag-check" style="color:var(--neon-cyan);"></i>
                                    <?= (int)$u['total_ventas'] ?>
                                </span>
                            </td>
                            <td class="user-stat">
                                <span class="stat-pill">
                                    <i class="bi bi-heart" style="color:var(--neon-pink);"></i>
                                    <?= (int)$u['total_favoritos'] ?>
                                </span>
                            </td>

                            <td class="user-date">
                                <i class="bi bi-calendar3 me-1" style="color:var(--text-muted);"></i>
                                <?= fechaEs($u['fecha_registro']) ?>
                            </td>

                            <td class="text-center">
                                <?php if ($isMe): ?>
                                    <span style="color:var(--text-muted);font-size:0.78rem;">
                                        <i class="bi bi-lock me-1"></i>No modificable
                                    </span>
                                <?php else: ?>
                                <div class="d-flex gap-1 justify-content-center">
                                    <?php if ($bloqueado): ?>
                                    <button class="action-btn-unblock"
                                            onclick="confirmBlockUser(<?= $u['id_usuario'] ?>,'<?= htmlspecialchars($u['nombre'],ENT_QUOTES) ?>',false)"
                                            title="Desbloquear usuario">
                                        <i class="bi bi-unlock-fill"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="action-btn-block"
                                            onclick="confirmBlockUser(<?= $u['id_usuario'] ?>,'<?= htmlspecialchars($u['nombre'],ENT_QUOTES) ?>',true)"
                                            title="Bloquear usuario">
                                        <i class="bi bi-slash-circle-fill"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="action-btn action-btn-delete"
                                            onclick="confirmDeleteUser(<?= $u['id_usuario'] ?>,'<?= htmlspecialchars($u['nombre'],ENT_QUOTES) ?>')"
                                            title="Eliminar usuario">
                                        <i class="bi bi-person-dash-fill"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="no-results" style="display:none;text-align:center;padding:40px;color:var(--text-muted);">
                <i class="bi bi-search" style="font-size:2rem;display:block;margin-bottom:12px;opacity:0.4;"></i>
                No se encontraron usuarios con ese criterio.
            </div>
        </div>

        <div class="alert alert-warning mt-3" style="font-size:0.85rem;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Nota:</strong> Los usuarios bloqueados no podrán iniciar sesión.
            Al eliminar un usuario se borran sus favoritos y reseñas; sus pedidos se conservan.
        </div>
    </div>
</div>
</div>

<!-- Block Confirmation Modal -->
<div class="modal fade" id="blockUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom-color:rgba(245,158,11,.2);">
                <h5 class="modal-title" id="block-modal-title"
                    style="color:#f59e0b;font-family:'Oxanium',sans-serif;">
                    <i class="bi bi-slash-circle-fill me-2"></i>Bloquear Usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;text-align:center;">
                <div id="block-modal-icon"
                     style="width:60px;height:60px;border-radius:50%;margin:0 auto 16px;
                            background:rgba(245,158,11,.15);border:2px solid rgba(245,158,11,.3);
                            display:flex;align-items:center;justify-content:center;">
                    <i id="block-modal-icon-i" class="bi bi-slash-circle-fill"
                       style="font-size:1.8rem;color:#f59e0b;"></i>
                </div>
                <p id="block-modal-body"
                   style="color:var(--text-secondary);font-size:.88rem;line-height:1.6;margin:0;">
                </p>
            </div>
            <div class="modal-footer" style="gap:10px;border-top-color:rgba(245,158,11,.2);">
                <button type="button" class="btn btn-outline-violet" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </button>
                <button type="button" id="confirm-block-btn"
                        style="border:none;padding:10px 20px;border-radius:8px;font-weight:600;color:#fff;cursor:pointer;">
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom-color:rgba(255,45,120,0.2);">
                <h5 class="modal-title" style="color:var(--neon-pink);font-family:'Oxanium',sans-serif;">
                    <i class="bi bi-person-x-fill me-2"></i>Eliminar Usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <div style="text-align:center;margin-bottom:20px;">
                    <div style="width:64px;height:64px;border-radius:50%;
                                background:rgba(255,45,120,0.15);border:2px solid rgba(255,45,120,0.3);
                                display:flex;align-items:center;justify-content:center;
                                margin:0 auto 16px;animation:neonPulse 2s ease-in-out infinite;">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size:1.8rem;color:var(--neon-pink);"></i>
                    </div>
                    <h6 style="color:var(--text-primary);font-weight:700;margin-bottom:8px;">
                        ¿Eliminar este usuario?
                    </h6>
                    <p style="color:var(--text-secondary);font-size:0.88rem;line-height:1.6;margin:0;">
                        Estás a punto de eliminar a
                        <strong id="delete-user-name" style="color:var(--neon-pink);"></strong>.
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div style="background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);
                            border-radius:8px;padding:12px 16px;font-size:0.82rem;color:var(--text-secondary);">
                    <div style="margin-bottom:4px;">
                        <i class="bi bi-x-circle me-1" style="color:var(--neon-pink);"></i>
                        Se eliminarán sus favoritos y reseñas
                    </div>
                    <div>
                        <i class="bi bi-check-circle me-1" style="color:var(--neon-green);"></i>
                        Sus pedidos se conservarán en el historial
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top-color:rgba(255,45,120,0.2);gap:10px;">
                <button type="button" class="btn btn-outline-violet" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </button>
                <button type="button" class="btn" id="confirm-delete-btn"
                        style="background:linear-gradient(135deg,#7b2fff,#ff2d78);color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:600;">
                    <i class="bi bi-person-dash-fill me-1"></i>Sí, eliminar usuario
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script>
window.csrfToken   = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
const deleteModal  = new bootstrap.Modal(document.getElementById('deleteUserModal'));
const blockModal   = new bootstrap.Modal(document.getElementById('blockUserModal'));
let pendingDeleteId = null;
let pendingBlockId  = null;
let pendingBlockAction = null;

// ---- Change role ----
function changeRole(sel) {
    const id  = sel.dataset.id;
    const rol = sel.value;
    fetch('/nexusgear/admin/usuarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=change_role&id_usuario=${id}&rol=${rol}&csrf_token=${window.csrfToken}`
    }).then(r => r.json()).then(d => showToast(d.message, d.success ? 'success' : 'error'));
}

// ---- Block / Unblock ----
function confirmBlockUser(id, nombre, isBlocking) {
    pendingBlockId     = id;
    pendingBlockAction = isBlocking ? 'block_user' : 'unblock_user';

    const title  = isBlocking ? 'Bloquear Usuario' : 'Desbloquear Usuario';
    const icon   = isBlocking ? 'bi-slash-circle-fill' : 'bi-unlock-fill';
    const color  = isBlocking ? '#f59e0b'              : '#84cc16';
    const body   = isBlocking
        ? `Estás a punto de bloquear a <strong style="color:#f59e0b;">${nombre}</strong>. No podrá iniciar sesión hasta que lo desbloquees.`
        : `Estás a punto de desbloquear a <strong style="color:#84cc16;">${nombre}</strong>. Podrá iniciar sesión normalmente.`;
    const btnText  = isBlocking ? 'Sí, bloquear' : 'Sí, desbloquear';
    const btnColor = isBlocking ? 'rgba(245,158,11,1)' : 'rgba(132,204,22,1)';

    document.getElementById('block-modal-title').innerHTML = `<i class="bi ${icon} me-2"></i>${title}`;
    document.getElementById('block-modal-title').style.color = color;
    document.getElementById('block-modal-icon').style.background = `${color}22`;
    document.getElementById('block-modal-icon').style.borderColor = `${color}55`;
    document.getElementById('block-modal-icon-i').className = `bi ${icon}`;
    document.getElementById('block-modal-icon-i').style.color = color;
    document.getElementById('block-modal-body').innerHTML = body;
    const btn = document.getElementById('confirm-block-btn');
    btn.textContent = btnText;
    btn.style.background = btnColor;
    btn.style.color = '#080810';

    blockModal.show();
}

document.getElementById('confirm-block-btn').addEventListener('click', function() {
    if (!pendingBlockId || !pendingBlockAction) return;
    const btn = this;
    btn.disabled = true;

    fetch('/nexusgear/admin/usuarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${pendingBlockAction}&id_usuario=${pendingBlockId}&csrf_token=${window.csrfToken}`
    }).then(r => r.json()).then(d => {
        blockModal.hide();
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) setTimeout(() => location.reload(), 900);
        btn.disabled = false;
        pendingBlockId = null; pendingBlockAction = null;
    }).catch(() => {
        showToast('Error de conexión.', 'error');
        btn.disabled = false;
    });
});

// ---- Delete ----
function confirmDeleteUser(id, nombre) {
    pendingDeleteId = id;
    document.getElementById('delete-user-name').textContent = nombre;
    deleteModal.show();
}

document.getElementById('confirm-delete-btn').addEventListener('click', function() {
    if (!pendingDeleteId) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1 icon-spin"></i>Eliminando...';

    fetch('/nexusgear/admin/usuarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_user&id_usuario=${pendingDeleteId}&csrf_token=${window.csrfToken}`
    }).then(r => r.json()).then(d => {
        deleteModal.hide();
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) {
            document.querySelectorAll('#users-tbody tr').forEach(row => {
                const delBtn = row.querySelector(`[onclick*="${pendingDeleteId}"]`);
                if (delBtn) {
                    row.style.transition = 'all 0.4s ease';
                    row.style.opacity    = '0';
                    row.style.transform  = 'translateX(20px)';
                    setTimeout(() => row.remove(), 400);
                }
            });
        }
        pendingDeleteId = null;
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-dash-fill me-1"></i>Sí, eliminar usuario';
    }).catch(() => {
        showToast('Error de conexión.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-dash-fill me-1"></i>Sí, eliminar usuario';
    });
});

// ---- Search ----
document.getElementById('user-search')?.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#users-tbody tr');
    let visible = 0;
    rows.forEach(row => {
        const match = !q || (row.dataset.name||'').includes(q) || (row.dataset.email||'').includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('no-results').style.display = visible === 0 ? 'block' : 'none';
});
</script>
</body>
</html>
