<?php
// ============================================================
// NexusGear - Coupon Controller (AJAX)
// ============================================================
session_start();
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ---- Validate and apply coupon ----
if ($action === 'validate') {
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));

    if (empty($codigo)) {
        echo json_encode(['success' => false, 'message' => 'Ingresa un código de cupón.']);
        exit;
    }

    // Fetch coupon from DB
    $stmt = mysqli_prepare($conn,
        "SELECT * FROM Cupon WHERE codigo=? AND activo=1");
    mysqli_stmt_bind_param($stmt, 's', $codigo);
    mysqli_stmt_execute($stmt);
    $res   = mysqli_stmt_get_result($stmt);
    $cupon = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$cupon) {
        echo json_encode(['success' => false, 'message' => 'Cupón no válido o inactivo.']);
        exit;
    }

    // Check expiration date
    if (!empty($cupon['fecha_expiracion']) && strtotime($cupon['fecha_expiracion']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Este cupón ha expirado.']);
        exit;
    }

    // Check usage limit
    if ((int)$cupon['usos_actuales'] >= (int)$cupon['usos_maximos']) {
        echo json_encode(['success' => false, 'message' => 'Este cupón ha alcanzado su límite de usos.']);
        exit;
    }

    // Apply to session
    $_SESSION['cupon_codigo']    = $cupon['codigo'];
    $_SESSION['cupon_descuento'] = (float)$cupon['descuento'];

    echo json_encode([
        'success'   => true,
        'message'   => '¡Cupón aplicado! ' . $cupon['descuento'] . '% de descuento.',
        'descuento' => (float)$cupon['descuento'],
        'codigo'    => $cupon['codigo'],
    ]);
    exit;
}

// ---- Remove coupon ----
if ($action === 'remove') {
    unset($_SESSION['cupon_codigo'], $_SESSION['cupon_descuento']);
    echo json_encode(['success' => true, 'message' => 'Cupón eliminado.']);
    exit;
}

// ---- Admin: toggle coupon active state ----
if ($action === 'toggle_active') {
    if (($_SESSION['rol'] ?? '') !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
        exit;
    }
    $id     = (int)($_POST['id_cupon'] ?? 0);
    $activo = (int)($_POST['activo'] ?? 0);
    $stmt   = mysqli_prepare($conn, "UPDATE Cupon SET activo=? WHERE id_cupon=?");
    mysqli_stmt_bind_param($stmt, 'ii', $activo, $id);
    $ok     = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => $ok]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción inválida.']);
?>
