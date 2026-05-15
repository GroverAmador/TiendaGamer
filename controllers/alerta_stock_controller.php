<?php
// ============================================================
// NexusGear — Alerta Stock Controller (AJAX)
// Permite suscribirse/cancelar alertas de reposición de stock
// ============================================================
session_start();
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para activar alertas.']);
    exit;
}

$action  = $_POST['action'] ?? '';
$uid     = (int)$_SESSION['id_usuario'];
$pid     = (int)($_POST['id_producto'] ?? 0);

// ============================================================
// SUSCRIBIRSE A ALERTA
// ============================================================
if ($action === 'subscribe') {
    if ($pid <= 0) { echo json_encode(['success'=>false,'message'=>'Producto inválido.']); exit; }

    // Verificar que el producto existe y está agotado
    $st = mysqli_prepare($conn, "SELECT stock, nombre FROM Producto WHERE id_producto=? AND estado=1");
    mysqli_stmt_bind_param($st,'i',$pid); mysqli_stmt_execute($st);
    $prod = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);

    if (!$prod) { echo json_encode(['success'=>false,'message'=>'Producto no encontrado.']); exit; }

    if ((int)$prod['stock'] > 0) {
        echo json_encode(['success'=>false,'message'=>'¡El producto ya tiene stock disponible! Puedes comprarlo ahora.']);
        exit;
    }

    // Insertar o reactivar alerta existente
    $st = mysqli_prepare($conn,
        "INSERT INTO Alerta_Stock (id_usuario, id_producto, activa, notificada)
         VALUES (?, ?, 1, 0)
         ON DUPLICATE KEY UPDATE activa=1, notificada=0, fecha_solicitud=NOW(), fecha_notificacion=NULL");
    mysqli_stmt_bind_param($st,'ii',$uid,$pid);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);

    echo json_encode([
        'success' => $ok,
        'message' => $ok
            ? "¡Listo! Te avisaremos cuando «{$prod['nombre']}» vuelva a estar disponible."
            : 'Error al registrar la alerta.',
    ]);
    exit;
}

// ============================================================
// CANCELAR ALERTA
// ============================================================
if ($action === 'unsubscribe') {
    if ($pid <= 0) { echo json_encode(['success'=>false,'message'=>'Producto inválido.']); exit; }

    $st = mysqli_prepare($conn,
        "UPDATE Alerta_Stock SET activa=0 WHERE id_usuario=? AND id_producto=?");
    mysqli_stmt_bind_param($st,'ii',$uid,$pid);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);

    echo json_encode(['success'=>$ok, 'message'=>$ok?'Alerta cancelada.':'Error.']);
    exit;
}

// ============================================================
// LISTAR ALERTAS DEL USUARIO (para perfil)
// ============================================================
if ($action === 'list') {
    $st = mysqli_prepare($conn,
        "SELECT a.*, p.nombre, p.imagen, p.stock, p.precio, c.nombre_categoria, c.icono
         FROM Alerta_Stock a
         JOIN Producto p ON a.id_producto=p.id_producto
         LEFT JOIN Categoria c ON p.id_categoria=c.id_categoria
         WHERE a.id_usuario=? AND a.activa=1
         ORDER BY a.fecha_solicitud DESC");
    mysqli_stmt_bind_param($st,'i',$uid);
    mysqli_stmt_execute($st);
    $res    = mysqli_stmt_get_result($st);
    $alerts = [];
    while ($r = mysqli_fetch_assoc($res)) $alerts[] = $r;
    mysqli_stmt_close($st);

    echo json_encode(['success'=>true,'alerts'=>$alerts]);
    exit;
}

// ============================================================
// ADMIN: notificar alertas cuando se repone stock (llama al actualizar producto)
// ============================================================
if ($action === 'notify_restock') {
    if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
        echo json_encode(['success'=>false,'message'=>'Acceso denegado.']); exit;
    }
    if ($pid <= 0) { echo json_encode(['success'=>false,'message'=>'Producto inválido.']); exit; }

    // Contar alertas pendientes
    $st = mysqli_prepare($conn,
        "SELECT COUNT(*) FROM Alerta_Stock WHERE id_producto=? AND activa=1 AND notificada=0");
    mysqli_stmt_bind_param($st,'i',$pid);
    mysqli_stmt_execute($st);
    $res   = mysqli_stmt_get_result($st);
    $count = (int)mysqli_fetch_row($res)[0];
    mysqli_stmt_close($st);

    if ($count > 0) {
        // Marcar como notificadas
        $st = mysqli_prepare($conn,
            "UPDATE Alerta_Stock SET notificada=1, fecha_notificacion=NOW(), activa=0
             WHERE id_producto=? AND activa=1 AND notificada=0");
        mysqli_stmt_bind_param($st,'i',$pid);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }

    echo json_encode([
        'success' => true,
        'notified' => $count,
        'message' => $count > 0 ? "$count usuario(s) fueron notificados del restock." : 'No había alertas pendientes.',
    ]);
    exit;
}

// ============================================================
// ADMIN: listar productos con alertas pendientes
// ============================================================
if ($action === 'admin_list') {
    if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
        echo json_encode(['success'=>false,'message'=>'Acceso denegado.']); exit;
    }
    $res = mysqli_query($conn,
        "SELECT p.id_producto, p.nombre, p.stock, p.imagen,
                COUNT(a.id_alerta) AS usuarios_esperando
         FROM Alerta_Stock a
         JOIN Producto p ON a.id_producto=p.id_producto
         WHERE a.activa=1 AND a.notificada=0
         GROUP BY a.id_producto
         ORDER BY usuarios_esperando DESC LIMIT 20");
    $list = [];
    if ($res) while ($r = mysqli_fetch_assoc($res)) $list[] = $r;
    echo json_encode(['success'=>true,'list'=>$list]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Acción inválida.']);
?>
