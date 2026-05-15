<?php
// ============================================================
// NexusGear - Favorites Controller (AJAX)
// ============================================================
session_start();
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para usar favoritos.']);
    exit;
}

$action    = $_POST['action'] ?? '';
$productId = (int)($_POST['id_producto'] ?? 0);
$userId    = (int)$_SESSION['id_usuario'];

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Producto inválido.']);
    exit;
}

if ($action === 'add') {
    // Check if already exists
    $check = mysqli_prepare($conn, "SELECT id_favorito FROM Favorito WHERE id_usuario=? AND id_producto=?");
    mysqli_stmt_bind_param($check, 'ii', $userId, $productId);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    $exists = mysqli_stmt_num_rows($check) > 0;
    mysqli_stmt_close($check);

    if ($exists) {
        echo json_encode(['success' => true, 'message' => 'Ya está en favoritos.']);
        exit;
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO Favorito (id_usuario, id_producto) VALUES (?,?)");
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $productId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Agregado a favoritos ❤️' : 'Error al agregar.']);
    exit;
}

if ($action === 'remove') {
    $stmt = mysqli_prepare($conn, "DELETE FROM Favorito WHERE id_usuario=? AND id_producto=?");
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $productId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Eliminado de favoritos.' : 'Error al eliminar.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción inválida.']);
?>
