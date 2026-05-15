<?php
// ============================================================
// NexusGear - Reviews Controller (AJAX)
// ============================================================
session_start();
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$action    = $_POST['action'] ?? '';
$userId    = (int)$_SESSION['id_usuario'];
$productId = (int)($_POST['id_producto'] ?? 0);

if ($action === 'submit') {
    // CSRF check
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token inválido.']);
        exit;
    }

    $puntuacion = (int)($_POST['puntuacion'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');

    if ($productId <= 0 || $puntuacion < 1 || $puntuacion > 5) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
        exit;
    }

    // Verify the user has purchased this product
    $stmt = mysqli_prepare($conn,
        "SELECT dv.id_detalle FROM Detalle_Venta dv
         JOIN Venta v ON dv.id_venta = v.id_venta
         WHERE v.id_usuario=? AND dv.id_producto=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $productId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $hasPurchased = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    if (!$hasPurchased) {
        echo json_encode(['success' => false, 'message' => 'Debes comprar el producto para reseñarlo.']);
        exit;
    }

    // Check for existing review
    $check = mysqli_prepare($conn, "SELECT id_resena FROM Resena WHERE id_usuario=? AND id_producto=?");
    mysqli_stmt_bind_param($check, 'ii', $userId, $productId);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    $exists = mysqli_stmt_num_rows($check) > 0;
    mysqli_stmt_close($check);

    if ($exists) {
        // Update existing review
        $stmt = mysqli_prepare($conn,
            "UPDATE Resena SET puntuacion=?, comentario=?, fecha=NOW() WHERE id_usuario=? AND id_producto=?");
        mysqli_stmt_bind_param($stmt, 'isii', $puntuacion, $comentario, $userId, $productId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Reseña actualizada.' : 'Error al actualizar.']);
    } else {
        // Insert new review
        $stmt = mysqli_prepare($conn,
            "INSERT INTO Resena (id_usuario, id_producto, puntuacion, comentario) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'iiis', $userId, $productId, $puntuacion, $comentario);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => $ok, 'message' => $ok ? '¡Reseña publicada exitosamente!' : 'Error al publicar.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción inválida.']);
?>
