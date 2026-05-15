<?php
// ============================================================
// NexusGear - Product Controller
// Handles: AJAX search/filter, Admin CRUD
// ============================================================
session_start();
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============================================================
// QUICK-VIEW: returns full product JSON for the modal
// ============================================================
if ($action === 'quickview') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id_producto'] ?? 0);
    if ($id <= 0) { echo json_encode(['success' => false]); exit; }

    $stmt = mysqli_prepare($conn,
        "SELECT p.*, c.nombre_categoria, c.icono,
                COALESCE(AVG(r.puntuacion), 0) AS avg_rating,
                COUNT(DISTINCT r.id_resena) AS review_count
         FROM Producto p
         LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
         LEFT JOIN Resena r    ON p.id_producto  = r.id_producto
         WHERE p.id_producto = ? AND p.estado = 1
         GROUP BY p.id_producto");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result  = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$product) { echo json_encode(['success' => false]); exit; }

    // Last 3 reviews
    $revStmt = mysqli_prepare($conn,
        "SELECT r.puntuacion, r.comentario, r.fecha, u.nombre
         FROM Resena r JOIN Usuario u ON r.id_usuario = u.id_usuario
         WHERE r.id_producto = ? ORDER BY r.fecha DESC LIMIT 3");
    mysqli_stmt_bind_param($revStmt, 'i', $id);
    mysqli_stmt_execute($revStmt);
    $revRes  = mysqli_stmt_get_result($revStmt);
    $reviews = [];
    while ($r = mysqli_fetch_assoc($revRes)) $reviews[] = $r;
    mysqli_stmt_close($revStmt);

    $product['reviews'] = $reviews;
    echo json_encode(['success' => true, 'product' => $product]);
    exit;
}

// ============================================================
// AJAX PRODUCT SEARCH (used by busqueda.js)
// ============================================================
if ($action === 'search') {
    header('Content-Type: application/json');

    // Sanitize and collect filter parameters
    $q         = trim($_GET['q'] ?? '');
    $cats      = isset($_GET['id_categoria']) ? (array)$_GET['id_categoria'] : [];
    $marcas    = isset($_GET['marca'])        ? (array)$_GET['marca']        : [];
    $precioMin = isset($_GET['precio_min']) && $_GET['precio_min'] !== '' ? (float)$_GET['precio_min'] : null;
    $precioMax = isset($_GET['precio_max']) && $_GET['precio_max'] !== '' ? (float)$_GET['precio_max'] : null;
    $rating    = isset($_GET['rating'])    && $_GET['rating'] !== ''    ? (int)$_GET['rating']    : null;
    $sort      = $_GET['sort'] ?? 'default';
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $perPage   = 8;
    $offset    = ($page - 1) * $perPage;

    // Get user's favorites for heart icons
    $favIds = [];
    if (isset($_SESSION['id_usuario'])) {
        $uid = (int)$_SESSION['id_usuario'];
        $favRes = mysqli_query($conn, "SELECT id_producto FROM Favorito WHERE id_usuario = $uid");
        if ($favRes) {
            while ($f = mysqli_fetch_assoc($favRes)) $favIds[] = (int)$f['id_producto'];
        }
    }

    // Build WHERE clause with prepared statement
    $whereParts  = ['p.estado = 1'];
    $bindTypes   = '';
    $bindValues  = [];

    if ($q !== '') {
        $whereParts[] = '(p.nombre LIKE ? OR p.marca LIKE ? OR p.descripcion LIKE ?)';
        $like = '%' . $q . '%';
        $bindTypes .= 'sss';
        $bindValues[] = $like;
        $bindValues[] = $like;
        $bindValues[] = $like;
    }

    if (!empty($cats)) {
        $catPlaceholders = implode(',', array_fill(0, count($cats), '?'));
        $whereParts[] = "p.id_categoria IN ($catPlaceholders)";
        foreach ($cats as $cat) {
            $bindTypes .= 'i';
            $bindValues[] = (int)$cat;
        }
    }

    if (!empty($marcas)) {
        $marcaPlaceholders = implode(',', array_fill(0, count($marcas), '?'));
        $whereParts[] = "p.marca IN ($marcaPlaceholders)";
        foreach ($marcas as $m) {
            $bindTypes .= 's';
            $bindValues[] = $m;
        }
    }

    if ($precioMin !== null) {
        $whereParts[] = 'p.precio >= ?';
        $bindTypes .= 'd';
        $bindValues[] = $precioMin;
    }

    if ($precioMax !== null) {
        $whereParts[] = 'p.precio <= ?';
        $bindTypes .= 'd';
        $bindValues[] = $precioMax;
    }

    if ($rating !== null) {
        $whereParts[] = 'COALESCE(AVG(r.puntuacion), 0) >= ?';
        $bindTypes .= 'i';
        $bindValues[] = $rating;
    }

    $where = implode(' AND ', $whereParts);

    // Sorting
    $orderMap = [
        'precio_asc'  => 'p.precio ASC',
        'precio_desc' => 'p.precio DESC',
        'nombre_asc'  => 'p.nombre ASC',
        'rating_desc' => 'avg_rating DESC',
        'nuevo'       => 'p.fecha_creacion DESC',
        'default'     => 'p.destacado DESC, p.fecha_creacion DESC',
    ];
    $orderBy = $orderMap[$sort] ?? $orderMap['default'];

    // Main query with HAVING for rating filter
    $havingClause = '';
    if ($rating !== null) {
        $havingClause = "HAVING avg_rating >= $rating";
    }

    $sql = "SELECT p.*, c.nombre_categoria, c.icono,
                   COALESCE(AVG(r.puntuacion), 0) AS avg_rating,
                   COUNT(DISTINCT r.id_resena) AS review_count
            FROM Producto p
            LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
            LEFT JOIN Resena r    ON p.id_producto  = r.id_producto
            WHERE $where
            GROUP BY p.id_producto
            $havingClause
            ORDER BY $orderBy";

    // Count total for pagination
    $countSql = "SELECT COUNT(*) FROM ($sql) AS sub";

    // Execute count (without rating HAVING, recalculate)
    if (!empty($bindValues)) {
        // Count
        $countStmt = mysqli_prepare($conn, $countSql);
        mysqli_stmt_bind_param($countStmt, $bindTypes, ...$bindValues);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $total    = (int)mysqli_fetch_row($countRes)[0];
        mysqli_stmt_close($countStmt);

        // Data with LIMIT
        $dataSql  = $sql . " LIMIT ? OFFSET ?";
        $stmt     = mysqli_prepare($conn, $dataSql);
        $allTypes = $bindTypes . 'ii';
        $allVals  = array_merge($bindValues, [$perPage, $offset]);
        mysqli_stmt_bind_param($stmt, $allTypes, ...$allVals);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        // Count
        $countRes = mysqli_query($conn, $countSql);
        $total    = (int)mysqli_fetch_row($countRes)[0];

        // Data
        $dataSql = $sql . " LIMIT $perPage OFFSET $offset";
        $result  = mysqli_query($conn, $dataSql);
    }

    $products = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) $products[] = $row;
    }

    // ---- Build HTML — tarjetas con íconos correctos ----
    ob_start();
    if (empty($products)) {
        echo '<div class="col-12 text-center py-5">
                <i class="bi bi-search" style="font-size:3rem;opacity:.3;display:block;margin-bottom:16px;color:var(--neon-cyan);"></i>
                <h5 style="color:var(--text-secondary);">No se encontraron productos</h5>
                <p style="color:var(--text-muted);">Intenta cambiar los filtros</p>
              </div>';
    } else {
        foreach ($products as $p) {
            $isFav    = in_array((int)$p['id_producto'], $favIds);
            $inStock  = (int)$p['stock'] > 0;
            $lowStock = (int)$p['stock'] <= 5 && $inStock;
            $avg      = (float)($p['avg_rating'] ?? 0);
            $pid      = (int)$p['id_producto'];

            // Estrellas
            $sh = '';
            for ($i=1;$i<=5;$i++) $sh .= '<i class="bi '.($i<=round($avg)?'bi-star-fill':'bi-star').'" style="color:'.($i<=round($avg)?'#f59e0b':'var(--text-muted)').';font-size:.82rem;"></i>';

            // Stock badge
            $sb = '';
            if (!$inStock) $sb = '<div class="stock-overlay"><span>AGOTADO</span></div>';
            elseif ($lowStock) $sb = '<span class="badge-orange" style="position:absolute;bottom:10px;left:10px;font-size:.7rem;"><i class="bi bi-exclamation-triangle me-1"></i>Últimas '.(int)$p['stock'].' uds.</span>';

            // Cart btn
            $cb = $inStock
                ? '<button class="btn btn-neon btn-sm btn-add-to-cart" data-product-id="'.$pid.'" style="padding:6px 13px;font-size:.78rem;" onclick="event.stopPropagation()"><i class="bi bi-cart3 me-1"></i>Agregar</button>'
                : '<button class="btn btn-secondary btn-sm" disabled style="font-size:.78rem;"><i class="bi bi-x-circle me-1"></i>Agotado</button>';

            // Favorite icon — usando card-fav-btn (el CSS de global.css lo maneja correctamente)
            $favCls  = $isFav ? 'card-fav-btn active' : 'card-fav-btn';
            $favIcCls = $isFav ? 'bi bi-heart-fill' : 'bi bi-heart';

            echo '<div class="col animate-on-scroll visible">
            <div class="product-card" onclick="handleCardClick(event,'.$pid.')">
                <div class="card-img-wrap">
                    <img src="'.htmlspecialchars($p['imagen']??'').'"
                         alt="'.htmlspecialchars($p['nombre']).'"
                         loading="lazy">
                    <div class="card-badge-top">
                        <span class="badge-cyan" style="font-size:.7rem;">'.htmlspecialchars($p['icono']??'').' '.htmlspecialchars($p['nombre_categoria']??'').'</span>
                    </div>
                    <button class="'.$favCls.'" data-product-id="'.$pid.'" onclick="event.stopPropagation();_toggleFav(this)" title="Favorito">
                        <i class="'.$favIcCls.'"></i>
                    </button>
                    '.$sb.'
                    <div class="qv-overlay" onclick="event.stopPropagation();openQuickView('.$pid.')">
                        <button class="qv-btn"><i class="bi bi-eye"></i> Vista Rápida</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="brand-tag mb-1">'.htmlspecialchars($p['marca']??'').'</div>
                    <h6 style="font-family:\'Oxanium\',sans-serif;color:var(--text-primary);margin-bottom:5px;line-height:1.3;font-size:.92rem;">
                        '.htmlspecialchars($p['nombre']).'
                    </h6>
                    <div class="stars-wrap mb-2">'.$sh.'
                        <span style="color:var(--text-muted);font-size:.75rem;margin-left:3px;">('.(int)$p['review_count'].')</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <span class="price-tag" style="font-size:1.15rem;">$'.number_format((float)$p['precio'],2).'</span>
                        '.$cb.'
                    </div>
                </div>
            </div>
            </div>';
        }
    }
    $html = ob_get_clean();

    // ---- Build Pagination ----
    $totalPages = max(1, (int)ceil($total / $perPage));
    ob_start();
    if ($totalPages > 1) {
        echo '<ul class="pagination justify-content-center flex-wrap">';
        if ($page > 1) {
            echo '<li class="page-item"><a class="page-link" data-page="' . ($page - 1) . '" href="#">&laquo;</a></li>';
        }
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = $i === $page ? 'active' : '';
            echo '<li class="page-item ' . $active . '">
                    <a class="page-link" data-page="' . $i . '" href="#">' . $i . '</a>
                  </li>';
        }
        if ($page < $totalPages) {
            echo '<li class="page-item"><a class="page-link" data-page="' . ($page + 1) . '" href="#">&raquo;</a></li>';
        }
        echo '</ul>';
    }
    $pagination = ob_get_clean();

    echo json_encode(['html' => $html, 'total' => $total, 'pagination' => $pagination]);
    exit;
}

// ============================================================
// ADMIN CRUD ACTIONS (POST)
// ============================================================

// Protect admin actions
function requireAdmin() {
    if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
        exit;
    }
}

if ($action === 'create_product' || $action === 'update_product') {
    requireAdmin();
    header('Content-Type: application/json');

    // Validate CSRF
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token inválido.']);
        exit;
    }

    $nombre      = trim($_POST['nombre'] ?? '');
    $id_cat      = (int)($_POST['id_categoria'] ?? 0);
    $marca       = trim($_POST['marca'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio      = (float)($_POST['precio'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);
    $destacado   = isset($_POST['destacado']) ? 1 : 0;
    $estado      = (int)($_POST['estado'] ?? 1);
    $imagen      = trim($_POST['imagen_url'] ?? '');

    // Handle image upload (if file provided)
    if (!empty($_FILES['imagen']['name'])) {
        $uploadDir = __DIR__ . '/../assets/img/productos/';
        $ext       = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed)) {
            $filename  = 'prod_' . time() . '_' . rand(100,999) . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $targetPath)) {
                $imagen = '/nexusgear/assets/img/productos/' . $filename;
            }
        }
    }

    if (empty($imagen)) {
        $imagen = 'https://placehold.co/400x300/13131f/00f5ff?text=' . urlencode($nombre);
    }

    if (empty($nombre) || $id_cat === 0 || $precio <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nombre, categoría y precio son obligatorios.']);
        exit;
    }

    if ($action === 'create_product') {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO Producto (id_categoria,nombre,marca,descripcion,precio,stock,imagen,destacado,estado)
             VALUES (?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'isssdiisi',
            $id_cat, $nombre, $marca, $descripcion, $precio, $stock, $imagen, $destacado, $estado);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Producto creado exitosamente.' : 'Error al crear.']);
    } else {
        $id = (int)($_POST['id_producto'] ?? 0);
        $stmt = mysqli_prepare($conn,
            "UPDATE Producto SET id_categoria=?,nombre=?,marca=?,descripcion=?,precio=?,
             stock=?,imagen=?,destacado=?,estado=? WHERE id_producto=?");
        mysqli_stmt_bind_param($stmt, 'isssdiisii',
            $id_cat, $nombre, $marca, $descripcion, $precio, $stock, $imagen, $destacado, $estado, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Producto actualizado.' : 'Error al actualizar.']);
    }
    exit;
}

if ($action === 'delete_product') {
    requireAdmin();
    header('Content-Type: application/json');
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token inválido.']);
        exit;
    }
    $id   = (int)($_POST['id_producto'] ?? 0);
    $stmt = mysqli_prepare($conn, "DELETE FROM Producto WHERE id_producto = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok   = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Producto eliminado.' : 'Error al eliminar.']);
    exit;
}

if ($action === 'toggle_estado') {
    requireAdmin();
    header('Content-Type: application/json');
    $id     = (int)($_POST['id_producto'] ?? 0);
    $estado = (int)($_POST['estado'] ?? 0);
    $stmt   = mysqli_prepare($conn, "UPDATE Producto SET estado = ? WHERE id_producto = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $estado, $id);
    $ok     = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => $ok]);
    exit;
}
?>
