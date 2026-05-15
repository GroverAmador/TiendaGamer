<?php
// ============================================================
// NexusGear - Admin: Users List + Delete
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

    if ($action === 'delete_user') {
        $id = (int)($_POST['id_usuario'] ?? 0);
        if ($id === (int)$_SESSION['id_usuario']) {
            echo json_encode(['success' => false, 'message' => 'No puedes eliminarte a ti mismo.']); exit;
        }
        // Cascade-safe: delete related records first
        mysqli_query($conn, "DELETE FROM Favorito WHERE id_usuario=$id");
        mysqli_query($conn, "DELETE FROM Resena WHERE id_usuario=$id");
        // Ventas: set id_usuario NULL (preserve order history)
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
        /* ---- Tabla de usuarios: colores correctos ---- */
        .user-table td,
        .user-table th {
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
        .user-table tbody tr {
            background: transparent;
            transition: background 0.2s;
        }
        .user-table tbody tr:hover {
            background: rgba(0, 245, 255, 0.04);
        }
        .user-name   { font-weight: 600; color: var(--text-primary) !important; font-size: 0.92rem; }
        .user-email  { color: var(--text-muted) !important; font-size: 0.78rem; margin-top: 2px; }
        .user-date   { color: var(--text-secondary) !important; font-size: 0.82rem; }
        .user-stat   { color: var(--text-secondary) !important; font-size: 0.85rem; text-align: center; }
        .role-select {
            background: rgba(255,255,255,0.05) !important;
            border: 1px solid var(--border-subtle) !important;
            color: var(--text-primary) !important;
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 0.82rem;
            cursor: pointer;
            transition: border-color 0.2s;
            min-width: 120px;
        }
        .role-select:focus {
            outline: none;
            border-color: var(--neon-cyan) !important;
            color: var(--text-primary) !important;
        }
        .role-select option {
            background: #1a1a2e;
            color: var(--text-primary);
        }
        .user-avatar-circle {
            width: 38px; height: 38px; border-radius: 50%;
            background: var(--gradient-brand);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.95rem; color: #fff;
            flex-shrink: 0;
            box-shadow: 0 0 10px rgba(0,245,255,0.15);
        }
        .admin-badge {
            background: rgba(123,47,255,0.2);
            color: var(--neon-violet);
            border: 1px solid rgba(123,47,255,0.35);
            border-radius: 4px; padding: 2px 8px; font-size: 0.72rem; font-weight: 600;
        }
        .own-label {
            background: rgba(0,245,255,0.12);
            color: var(--neon-cyan);
            border: 1px solid rgba(0,245,255,0.25);
            border-radius: 4px; padding: 2px 8px; font-size: 0.68rem; font-weight: 600;
        }
        /* Stats pill */
        .stat-pill {
            display: inline-flex; align-items: center; gap: 4px;
            background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle);
            border-radius: 20px; padding: 3px 10px; font-size: 0.78rem;
            color: var(--text-secondary) !important;
        }
        .stat-pill .bi { font-size: 0.75rem; }
    </style>
</head>
<body style="background:var(--bg-primary);">
<div class="admin-layout">
<?php include __DIR__ . '/../views/admin_sidebar.php'; ?>
<div class="admin-main">

    <!-- Topbar -->
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-2">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn">
                <i class="bi bi-list"></i>
            </button>
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
            $totalVentasSum = array_sum(array_column($usuarios, 'total_ventas'));
            ?>
            <div class="col-sm-4">
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
            <div class="col-sm-4">
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
            <div class="col-sm-4">
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
        </div>

        <!-- Table -->
        <div class="admin-table-card">
            <div class="admin-table-header">
                <h6 class="admin-table-title">
                    <i class="bi bi-person-lines-fill me-2" style="color:var(--neon-cyan);"></i>
                    Lista de Usuarios
                </h6>
                <!-- Search filter (client-side) -->
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
                        <?php $isMe = (int)$u['id_usuario'] === (int)$_SESSION['id_usuario']; ?>
                        <tr data-name="<?= strtolower(htmlspecialchars($u['nombre'])) ?>"
                            data-email="<?= strtolower(htmlspecialchars($u['correo'])) ?>">

                            <!-- Avatar + name + email -->
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar-circle">
                                        <?= strtoupper(substr($u['nombre'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="user-name d-flex align-items-center gap-2">
                                            <?= htmlspecialchars($u['nombre']) ?>
                                            <?php if ($isMe): ?>
                                                <span class="own-label">
                                                    <i class="bi bi-person-check me-1"></i>Tú
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($u['rol'] === 'admin'): ?>
                                                <span class="admin-badge">
                                                    <i class="bi bi-shield-fill me-1"></i>Admin
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-email">
                                            <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($u['correo']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Role selector -->
                            <td>
                                <?php if ($isMe): ?>
                                    <span style="color:var(--neon-violet);font-size:0.85rem;font-weight:600;">
                                        <i class="bi bi-shield-fill me-1"></i>Administrador
                                    </span>
                                <?php else: ?>
                                    <select class="role-select"
                                            data-id="<?= $u['id_usuario'] ?>"
                                            onchange="changeRole(this)">
                                        <option value="cliente" <?= $u['rol']==='cliente'?'selected':'' ?>>
                                            Cliente
                                        </option>
                                        <option value="admin" <?= $u['rol']==='admin'?'selected':'' ?>>
                                            Administrador
                                        </option>
                                    </select>
                                <?php endif; ?>
                            </td>

                            <!-- Stats -->
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

                            <!-- Date -->
                            <td class="user-date">
                                <i class="bi bi-calendar3 me-1" style="color:var(--text-muted);"></i>
                                <?= fechaEs($u['fecha_registro']) ?>
                            </td>

                            <!-- Actions -->
                            <td class="text-center">
                                <?php if ($isMe): ?>
                                    <span style="color:var(--text-muted);font-size:0.78rem;">
                                        <i class="bi bi-lock me-1"></i>No modificable
                                    </span>
                                <?php else: ?>
                                    <button class="action-btn action-btn-delete"
                                            onclick="confirmDeleteUser(<?= $u['id_usuario'] ?>, '<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>')"
                                            title="Eliminar usuario">
                                        <i class="bi bi-person-dash-fill"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Empty search state -->
            <div id="no-results" style="display:none;text-align:center;padding:40px;color:var(--text-muted);">
                <i class="bi bi-search" style="font-size:2rem;display:block;margin-bottom:12px;opacity:0.4;"></i>
                No se encontraron usuarios con ese criterio.
            </div>
        </div>

        <!-- Warning note -->
        <div class="alert alert-warning mt-3" style="font-size:0.85rem;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Nota:</strong> Al eliminar un usuario se borran sus favoritos y reseñas.
            Sus pedidos se conservan en el historial de ventas.
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
                    <div style="
                        width:64px;height:64px;border-radius:50%;
                        background:rgba(255,45,120,0.15);border:2px solid rgba(255,45,120,0.3);
                        display:flex;align-items:center;justify-content:center;
                        margin:0 auto 16px;
                        animation:neonPulse 2s ease-in-out infinite;
                    ">
                        <i class="bi bi-exclamation-triangle-fill"
                           style="font-size:1.8rem;color:var(--neon-pink);"></i>
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

                <div style="
                    background:rgba(255,45,120,0.08);
                    border:1px solid rgba(255,45,120,0.2);
                    border-radius:8px;padding:12px 16px;font-size:0.82rem;
                    color:var(--text-secondary);
                ">
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
window.csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
let pendingDeleteId = null;

function changeRole(sel) {
    const id  = sel.dataset.id;
    const rol = sel.value;
    fetch('/nexusgear/admin/usuarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=change_role&id_usuario=${id}&rol=${rol}&csrf_token=${window.csrfToken}`
    })
    .then(r => r.json())
    .then(d => showToast(d.message, d.success ? 'success' : 'error'));
}

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
    })
    .then(r => r.json())
    .then(d => {
        deleteModal.hide();
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) {
            // Animate row removal
            const rows = document.querySelectorAll('#users-tbody tr');
            rows.forEach(row => {
                const nameEl = row.querySelector('.user-name');
                if (nameEl) {
                    const delBtn = row.querySelector('[onclick*="' + pendingDeleteId + '"]');
                    if (delBtn) {
                        row.style.transition = 'all 0.4s ease';
                        row.style.opacity    = '0';
                        row.style.transform  = 'translateX(20px)';
                        setTimeout(() => row.remove(), 400);
                    }
                }
            });
        }
        pendingDeleteId = null;
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-dash-fill me-1"></i>Sí, eliminar usuario';
    })
    .catch(() => {
        showToast('Error de conexión.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-dash-fill me-1"></i>Sí, eliminar usuario';
    });
});

// Client-side search filter
document.getElementById('user-search')?.addEventListener('input', function() {
    const q    = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#users-tbody tr');
    let visible = 0;
    rows.forEach(row => {
        const name  = row.dataset.name  || '';
        const email = row.dataset.email || '';
        const match = !q || name.includes(q) || email.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('no-results').style.display = visible === 0 ? 'block' : 'none';
});
</script>
</body>
</html>
