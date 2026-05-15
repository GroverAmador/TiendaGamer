<?php
// ============================================================
// NexusGear - Sales / Cart Controller v2
// Stock validation rigurosa en cada operación
// ============================================================
session_start();
require_once __DIR__ . '/../config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

// ---- Helper: contar items totales en carrito ----
function cartCount(): int {
    $total = 0;
    foreach ($_SESSION['carrito'] ?? [] as $item) $total += (int)($item['cantidad'] ?? 0);
    return $total;
}

// ---- Helper: obtener stock actual de un producto ----
function getStock(mysqli $conn, int $pid): ?array {
    $st = mysqli_prepare($conn, "SELECT id_producto, nombre, precio, stock FROM Producto WHERE id_producto=? AND estado=1");
    mysqli_stmt_bind_param($st, 'i', $pid);
    mysqli_stmt_execute($st);
    $res  = mysqli_stmt_get_result($st);
    $prod = mysqli_fetch_assoc($res);
    mysqli_stmt_close($st);
    return $prod ?: null;
}

// ============================================================
// ADD TO CART — con validación de stock estricta
// ============================================================
if ($action === 'add_to_cart') {
    $pid = (int)($_POST['id_producto'] ?? 0);
    $qty = max(1, (int)($_POST['cantidad'] ?? 1));

    if ($pid <= 0) {
        echo json_encode(['success' => false, 'message' => 'Producto inválido.']); exit;
    }

    $prod = getStock($conn, $pid);

    if (!$prod) {
        echo json_encode(['success' => false, 'message' => 'Producto no disponible o agotado.']); exit;
    }

    $stockReal    = (int)$prod['stock'];
    $enCarrito    = (int)(($_SESSION['carrito'][$pid]['cantidad'] ?? 0));
    $totalNuevo   = $enCarrito + $qty;

    // Validación 1: producto sin stock en absoluto
    if ($stockReal <= 0) {
        echo json_encode([
            'success' => false,
            'message' => "«{$prod['nombre']}» está agotado.",
            'stock'   => 0,
        ]);
        exit;
    }

    // Validación 2: ya tienes el máximo en el carrito
    if ($enCarrito >= $stockReal) {
        echo json_encode([
            'success' => false,
            'message' => "Ya tienes el máximo disponible ({$stockReal}) de «{$prod['nombre']}» en tu carrito.",
            'stock'   => $stockReal,
        ]);
        exit;
    }

    // Validación 3: la cantidad solicitada supera el stock disponible
    if ($totalNuevo > $stockReal) {
        $disponible = $stockReal - $enCarrito;
        echo json_encode([
            'success'     => false,
            'message'     => "Solo puedes agregar $disponible unidad(es) más de «{$prod['nombre']}». Stock disponible: {$stockReal}.",
            'stock'       => $stockReal,
            'en_carrito'  => $enCarrito,
            'disponible'  => $disponible,
        ]);
        exit;
    }

    // Todo OK: actualizar carrito
    if (!isset($_SESSION['carrito'])) $_SESSION['carrito'] = [];
    $_SESSION['carrito'][$pid] = [
        'cantidad' => $totalNuevo,
        'precio'   => (float)$prod['precio'],
        'nombre'   => $prod['nombre'],
    ];

    $msg = $enCarrito > 0
        ? "«{$prod['nombre']}» actualizado (×{$totalNuevo})"
        : "«{$prod['nombre']}» agregado al carrito";

    echo json_encode([
        'success'    => true,
        'message'    => $msg,
        'cart_count' => cartCount(),
        'stock'      => $stockReal,
        'en_carrito' => $totalNuevo,
    ]);
    exit;
}

// ============================================================
// REMOVE FROM CART
// ============================================================
if ($action === 'remove_from_cart') {
    $pid = (int)($_POST['id_producto'] ?? 0);
    unset($_SESSION['carrito'][$pid]);
    echo json_encode(['success' => true, 'message' => 'Producto eliminado del carrito.', 'cart_count' => cartCount()]);
    exit;
}

// ============================================================
// UPDATE QUANTITY — validación vs stock real
// ============================================================
if ($action === 'update_qty') {
    $pid = (int)($_POST['id_producto'] ?? 0);
    $qty = max(1, (int)($_POST['cantidad'] ?? 1));

    if (!isset($_SESSION['carrito'][$pid])) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado en el carrito.']); exit;
    }

    $prod = getStock($conn, $pid);
    if (!$prod) {
        // Producto ya no existe, eliminarlo del carrito
        unset($_SESSION['carrito'][$pid]);
        echo json_encode(['success' => false, 'message' => 'Producto ya no disponible, fue eliminado del carrito.', 'removed' => true, 'cart_count' => cartCount()]);
        exit;
    }

    $stockReal = (int)$prod['stock'];

    if ($qty > $stockReal) {
        // Ajustar al máximo disponible y avisar
        $_SESSION['carrito'][$pid]['cantidad'] = $stockReal;
        echo json_encode([
            'success'     => true,
            'message'     => "Cantidad ajustada al stock disponible ({$stockReal}).",
            'adjusted_qty'=> $stockReal,
            'cart_count'  => cartCount(),
            'warning'     => true,
        ]);
        exit;
    }

    $_SESSION['carrito'][$pid]['cantidad'] = $qty;
    echo json_encode(['success' => true, 'cart_count' => cartCount()]);
    exit;
}

// ============================================================
// CONFIRM ORDER — validación final con bloqueo de filas (FOR UPDATE)
// ============================================================
if ($action === 'confirm_order') {
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']); exit;
    }

    $carrito = $_SESSION['carrito'] ?? [];
    if (empty($carrito)) {
        echo json_encode(['success' => false, 'message' => 'El carrito está vacío.']); exit;
    }

    $userId         = (int)$_SESSION['id_usuario'];
    $couponCode     = $_SESSION['cupon_codigo']   ?? null;
    $couponDiscount = (float)($_SESSION['cupon_descuento'] ?? 0);

    // Iniciar transacción
    mysqli_begin_transaction($conn);

    try {
        $subtotal  = 0;
        $itemsData = [];

        foreach ($carrito as $pid => $item) {
            $pid = (int)$pid;
            $qty = (int)$item['cantidad'];

            // Bloquear fila para verificar stock (FOR UPDATE)
            $st = mysqli_prepare($conn, "SELECT id_producto, nombre, precio, stock FROM Producto WHERE id_producto=? AND estado=1 FOR UPDATE");
            mysqli_stmt_bind_param($st, 'i', $pid);
            mysqli_stmt_execute($st);
            $prod = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);

            if (!$prod) {
                throw new Exception("El producto ya no está disponible: {$item['nombre']}.");
            }

            $stockActual = (int)$prod['stock'];

            if ($stockActual <= 0) {
                throw new Exception("«{$prod['nombre']}» está agotado. Por favor retíralo del carrito.");
            }

            if ($qty > $stockActual) {
                throw new Exception(
                    "Stock insuficiente para «{$prod['nombre']}». " .
                    "Solicitado: {$qty}, disponible: {$stockActual}. " .
                    "Ajusta la cantidad en el carrito."
                );
            }

            $precio  = (float)$prod['precio'];
            $sub     = $qty * $precio;
            $subtotal += $sub;
            $itemsData[] = ['id' => $pid, 'qty' => $qty, 'precio' => $precio, 'sub' => $sub, 'nombre' => $prod['nombre']];
        }

        // Calcular total con descuento
        $descuentoMonto = $subtotal * ($couponDiscount / 100);
        $total          = $subtotal - $descuentoMonto;

        // 1. Insertar Venta
        $st = mysqli_prepare($conn,
            "INSERT INTO Venta (id_usuario,total,estado_venta,cupon_aplicado,descuento_aplicado) VALUES (?,?,'pagado',?,?)");
        mysqli_stmt_bind_param($st, 'idsd', $userId, $total, $couponCode, $couponDiscount);
        if (!mysqli_stmt_execute($st)) throw new Exception('Error al registrar la venta.');
        $ventaId = mysqli_insert_id($conn);
        mysqli_stmt_close($st);

        // 2. Insertar Detalle_Venta y decrementar stock
        foreach ($itemsData as $item) {
            // Detalle
            $st = mysqli_prepare($conn,
                "INSERT INTO Detalle_Venta (id_venta,id_producto,cantidad,precio_unitario,subtotal) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($st, 'iiidd', $ventaId, $item['id'], $item['qty'], $item['precio'], $item['sub']);
            if (!mysqli_stmt_execute($st)) throw new Exception('Error al guardar detalle de venta.');
            mysqli_stmt_close($st);

            // Decrementar stock — SOLO la cantidad comprada
            $st = mysqli_prepare($conn, "UPDATE Producto SET stock = stock - ? WHERE id_producto = ? AND stock >= ?");
            mysqli_stmt_bind_param($st, 'iii', $item['qty'], $item['id'], $item['qty']);
            if (!mysqli_stmt_execute($st) || mysqli_affected_rows($conn) === 0) {
                throw new Exception("No se pudo actualizar el stock de «{$item['nombre']}». Es posible que otro usuario haya comprado el último stock.");
            }
            mysqli_stmt_close($st);
        }

        // 3. Incrementar uso de cupón si aplica
        if ($couponCode) {
            $st = mysqli_prepare($conn,
                "UPDATE Cupon SET usos_actuales = usos_actuales + 1 WHERE codigo = ? AND usos_actuales < usos_maximos");
            mysqli_stmt_bind_param($st, 's', $couponCode);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
        }

        // Confirmar transacción
        mysqli_commit($conn);

        // Limpiar carrito y cupón de la sesión
        unset($_SESSION['carrito'], $_SESSION['cupon_codigo'], $_SESSION['cupon_descuento']);

        echo json_encode([
            'success'  => true,
            'message'  => '¡Pedido confirmado exitosamente!',
            'order_id' => $ventaId,
        ]);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
?>
