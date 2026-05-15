<?php
// ============================================================
// NexusGear - Admin Sidebar Navigation (Bootstrap Icons)
// ============================================================
$currentPage  = basename($_SERVER['PHP_SELF'], '.php');
$adminName    = htmlspecialchars($_SESSION['nombre'] ?? 'Admin');
$adminInitial = strtoupper(substr($adminName, 0, 1));

function sidebarActive($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}
?>
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="logo-text">
            <i class="bi bi-lightning-charge-fill logo-icon" style="animation:iconPulse 3s ease-in-out infinite;"></i>NexusGear
        </div>
        <span class="panel-label">Panel de Administración</span>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <a href="/nexusgear/admin/dashboard.php" class="sidebar-nav-item <?= sidebarActive('dashboard') ?>">
            <i class="bi bi-bar-chart-fill sidebar-nav-icon"></i>
            <span>Dashboard</span>
        </a>
        <a href="/nexusgear/admin/productos.php" class="sidebar-nav-item <?= sidebarActive('productos') ?>">
            <i class="bi bi-controller sidebar-nav-icon"></i>
            <span>Productos</span>
        </a>
        <a href="/nexusgear/admin/categorias.php" class="sidebar-nav-item <?= sidebarActive('categorias') ?>">
            <i class="bi bi-folder2-open sidebar-nav-icon"></i>
            <span>Categorías</span>
        </a>
        <a href="/nexusgear/admin/ventas.php" class="sidebar-nav-item <?= sidebarActive('ventas') ?>">
            <i class="bi bi-bag-check-fill sidebar-nav-icon"></i>
            <span>Ventas</span>
        </a>
        <a href="/nexusgear/admin/usuarios.php" class="sidebar-nav-item <?= sidebarActive('usuarios') ?>">
            <i class="bi bi-people-fill sidebar-nav-icon"></i>
            <span>Usuarios</span>
        </a>
        <a href="/nexusgear/admin/cupones.php" class="sidebar-nav-item <?= sidebarActive('cupones') ?>">
            <i class="bi bi-ticket-perforated-fill sidebar-nav-icon"></i>
            <span>Cupones</span>
        </a>

        <hr style="border-color:var(--border-subtle);margin:16px 0;">

        <a href="/nexusgear/index.php" class="sidebar-nav-item" target="_blank">
            <i class="bi bi-globe sidebar-nav-icon"></i>
            <span>Ver Tienda</span>
        </a>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= $adminInitial ?></div>
            <div>
                <div class="sidebar-user-name"><?= $adminName ?></div>
                <div class="sidebar-user-role">Administrador</div>
            </div>
        </div>
        <a href="/nexusgear/auth/logout.php" class="sidebar-logout">
            <i class="bi bi-box-arrow-right me-1"></i>Cerrar Sesión
        </a>
    </div>
</aside>
