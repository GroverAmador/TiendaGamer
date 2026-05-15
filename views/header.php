<?php
// ============================================================
// NexusGear - Global Navbar
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

$cartCount = 0;
if (isset($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $item) $cartCount += (int)($item['cantidad'] ?? 0);
}

$loggedIn    = isset($_SESSION['id_usuario']);
$userName    = $loggedIn ? htmlspecialchars($_SESSION['nombre'] ?? '') : '';
$userInitial = $loggedIn ? strtoupper(substr($userName, 0, 1)) : '';
$userRol     = $loggedIn ? ($_SESSION['rol'] ?? 'cliente') : '';
?>
<nav class="nexus-navbar navbar navbar-expand-lg fixed-top" id="mainNav">
    <div class="container-xl">

        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="/nexusgear/index.php">
            <i class="bi bi-lightning-charge-fill brand-icon" style="font-size:1.3rem;"></i>
            <span>NexusGear</span>
        </a>

        <!-- Hamburger -->
        <button class="navbar-toggler border-0" type="button"
                data-bs-toggle="collapse" data-bs-target="#navMain"
                style="color:var(--text-secondary);">
            <i class="bi bi-list" style="font-size:1.5rem;color:var(--text-secondary);"></i>
        </button>

        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto ms-3 gap-1">
                <li class="nav-item">
                    <a class="nav-link" href="/nexusgear/index.php">
                        <i class="bi bi-house-fill me-1"></i>Inicio
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/nexusgear/client/productos.php">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i>Productos
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-folder2-open me-1"></i>Categorías
                    </a>
                    <ul class="dropdown-menu">
                        <?php
                        if (file_exists(__DIR__ . '/../config/database.php')) {
                            require_once __DIR__ . '/../config/database.php';
                            $catRes = mysqli_query($conn, "SELECT id_categoria, nombre_categoria, icono FROM Categoria ORDER BY nombre_categoria");
                            if ($catRes) {
                                while ($cat = mysqli_fetch_assoc($catRes)) {
                                    $link = '/nexusgear/client/productos.php?id_categoria=' . $cat['id_categoria'];
                                    echo '<li><a class="dropdown-item" href="' . htmlspecialchars($link) . '">'
                                         . '<i class="bi bi-tag me-1" style="color:var(--neon-violet);"></i>'
                                         . htmlspecialchars($cat['icono']) . ' ' . htmlspecialchars($cat['nombre_categoria'])
                                         . '</a></li>';
                                }
                            }
                        }
                        ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="/nexusgear/client/productos.php">
                                <i class="bi bi-search me-1"></i>Ver todos
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/nexusgear/index.php#about">
                        <i class="bi bi-info-circle me-1"></i>Nosotros
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if ($loggedIn): ?>
                    <!-- Cart -->
                    <a href="/nexusgear/client/carrito.php"
                       class="btn btn-outline-cyan btn-sm position-relative"
                       style="padding:8px 14px;">
                        <i class="bi bi-cart3"></i>
                        <?php if ($cartCount > 0): ?>
                        <span class="cart-badge" id="cart-badge"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- User dropdown -->
                    <div class="dropdown">
                        <button class="btn d-flex align-items-center gap-2 px-3 py-2"
                                style="background:rgba(255,255,255,0.05);border:1px solid var(--border-subtle);border-radius:var(--radius-md);"
                                type="button" data-bs-toggle="dropdown">
                            <div style="width:30px;height:30px;border-radius:50%;background:var(--gradient-brand);
                                        display:flex;align-items:center;justify-content:center;
                                        font-weight:700;color:#fff;font-size:0.8rem;flex-shrink:0;">
                                <?= $userInitial ?>
                            </div>
                            <span style="color:var(--text-primary);font-size:0.9rem;font-weight:500;
                                         max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= $userName ?>
                            </span>
                            <i class="bi bi-chevron-down" style="color:var(--text-muted);font-size:0.75rem;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="min-width:190px;">
                            <?php if ($userRol === 'admin'): ?>
                            <li>
                                <a class="dropdown-item" href="/nexusgear/admin/dashboard.php">
                                    <i class="bi bi-bar-chart-fill" style="color:var(--neon-violet);"></i>Panel Admin
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="/nexusgear/client/perfil.php">
                                <i class="bi bi-person-fill" style="color:var(--neon-cyan);"></i>Mi Perfil
                            </a></li>
                            <li><a class="dropdown-item" href="/nexusgear/client/carrito.php">
                                <i class="bi bi-cart3" style="color:var(--neon-cyan);"></i>Carrito
                            </a></li>
                            <li><a class="dropdown-item" href="/nexusgear/client/favoritos.php">
                                <i class="bi bi-heart-fill" style="color:var(--neon-pink);"></i>Favoritos
                            </a></li>
                            <li><a class="dropdown-item" href="/nexusgear/client/historial.php">
                                <i class="bi bi-list-check" style="color:var(--neon-cyan);"></i>Mis Pedidos
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/nexusgear/auth/logout.php"
                                   style="color:var(--neon-pink);">
                                <i class="bi bi-box-arrow-right" style="color:var(--neon-pink);"></i>Cerrar Sesión
                            </a></li>
                        </ul>
                    </div>

                <?php else: ?>
                    <a href="/nexusgear/auth/login.php" class="btn btn-outline-cyan btn-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar Sesión
                    </a>
                    <a href="/nexusgear/auth/register.php" class="btn btn-neon btn-sm">
                        <i class="bi bi-person-plus-fill me-1"></i>Registrarse
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
