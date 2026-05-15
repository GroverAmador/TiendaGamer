<?php
// ============================================================
// NexusGear - Shopping Cart
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario'])) { header('Location: /nexusgear/auth/login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$carrito   = $_SESSION['carrito'] ?? [];
$cartItems = [];
$subtotal  = 0;
foreach ($carrito as $pid => $item) {
    $pid  = (int)$pid;
    $stmt = mysqli_prepare($conn,"SELECT id_producto,nombre,marca,precio,stock,imagen FROM Producto WHERE id_producto=? AND estado=1");
    mysqli_stmt_bind_param($stmt,'i',$pid);
    mysqli_stmt_execute($stmt);
    $prod = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($prod) {
        // Si la cantidad en carrito supera el stock actual, ajustar automáticamente
        $qty = min((int)$item['cantidad'], (int)$prod['stock']);
        if ($qty !== (int)$item['cantidad'] && $qty > 0) {
            // Actualizar la sesión con la cantidad ajustada
            $_SESSION['carrito'][$pid]['cantidad'] = $qty;
        } elseif ($qty <= 0) {
            // Sin stock — mantener en carrito pero marcado como no disponible
            $qty = 0;
        }
        $sub = (float)$prod['precio'] * max($qty, 0);
        $subtotal += $sub;
        $cartItems[] = ['producto'=>$prod,'cantidad'=>$qty,'subtotal'=>$sub];
    }
}
$couponCode     = $_SESSION['cupon_codigo']   ?? null;
$couponDiscount = (float)($_SESSION['cupon_descuento'] ?? 0);
$discountAmount = $subtotal * ($couponDiscount / 100);
$total          = $subtotal - $discountAmount;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/client.css">
</head>
<body class="client-page">
<?php include __DIR__.'/../views/header.php'; ?>
<?php include __DIR__.'/../views/toast.php'; ?>

<div class="page-header">
    <div class="container-xl">
        <h1><i class="bi bi-cart3 me-2" style="color:var(--neon-cyan);"></i>Mi Carrito</h1>
    </div>
</div>

<div class="container-xl py-4">
    <?php if (empty($cartItems)): ?>
    <div id="empty-cart" class="empty-state">
        <i class="bi bi-cart-x" style="font-size:5rem;opacity:0.25;display:block;margin-bottom:24px;color:var(--neon-cyan);"></i>
        <h3>Tu carrito está vacío</h3>
        <p>Agrega productos desde el catálogo para comenzar.</p>
        <a href="/nexusgear/client/productos.php" class="btn btn-neon mt-3">
            <i class="bi bi-grid-3x3-gap me-2"></i>Ver Productos
        </a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <!-- Cart table -->
        <div class="col-lg-8">
            <div id="empty-cart" style="display:none;" class="empty-state">
                <i class="bi bi-cart-x" style="font-size:5rem;opacity:0.25;display:block;margin-bottom:24px;color:var(--neon-cyan);"></i>
                <h3>Tu carrito está vacío</h3>
                <a href="/nexusgear/client/productos.php" class="btn btn-neon mt-3">Ver Productos</a>
            </div>

            <div class="cart-table-wrapper">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th style="width:70px;"></th>
                                <th>Producto</th>
                                <th>Precio Unit.</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                            <?php $p=$item['producto']; ?>
                            <tr class="cart-item-row" data-product-id="<?= $p['id_producto'] ?>" data-price="<?= $p['precio'] ?>">
                                <td>
                                    <img src="<?= htmlspecialchars($p['imagen']??'') ?>"
                                         alt="<?= htmlspecialchars($p['nombre']) ?>"
                                         class="cart-img">
                                </td>
                                <td>
                                    <div style="font-weight:600;color:var(--text-primary);">
                                        <a href="/nexusgear/client/producto_detalle.php?id=<?= $p['id_producto'] ?>"
                                           style="color:inherit;text-decoration:none;">
                                            <?= htmlspecialchars($p['nombre']) ?>
                                        </a>
                                    </div>
                                    <div class="brand-tag"><?= htmlspecialchars($p['marca']??'') ?></div>
                                </td>
                                <td style="color:var(--neon-cyan);font-weight:600;">
                                    $<?= number_format((float)$p['precio'],2) ?>
                                </td>
                                <td>
                                    <?php if ((int)$p['stock'] <= 0): ?>
                                    <span class="badge-pink" style="font-size:.72rem;">
                                        <i class="bi bi-x-circle me-1"></i>Sin stock
                                    </span>
                                    <?php else: ?>
                                    <div class="qty-input-group">
                                        <button class="qty-btn qty-btn-minus" type="button">
                                            <i class="bi bi-dash"></i>
                                        </button>
                                        <input type="number" class="qty-input"
                                               value="<?= $item['cantidad'] ?>"
                                               min="1" max="<?= $p['stock'] ?>">
                                        <button class="qty-btn qty-btn-plus" type="button">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                    <div style="font-size:.68rem;color:var(--text-muted);text-align:center;margin-top:2px;">
                                        Máx: <?= (int)$p['stock'] ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="subtotal-cell" style="font-weight:700;color:var(--text-primary);">
                                    $<?= number_format($item['subtotal'],2) ?>
                                </td>
                                <td>
                                    <button class="btn-fav btn-remove-cart" title="Eliminar">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Coupon -->
            <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:12px;padding:20px;margin-top:16px;">
                <h6 style="font-family:'Oxanium',sans-serif;color:var(--text-primary);margin-bottom:12px;display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-ticket-perforated-fill" style="color:var(--neon-cyan);"></i>Cupón de descuento
                </h6>
                <?php if ($couponCode): ?>
                <div class="d-flex align-items-center gap-3">
                    <span class="coupon-chip">
                        <i class="bi bi-ticket-perforated me-1"></i>
                        <?= htmlspecialchars($couponCode) ?> — <?= $couponDiscount ?>% OFF
                        <button class="remove-coupon" onclick="removeCoupon()" title="Quitar cupón">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </span>
                    <span style="color:var(--neon-green);font-size:0.9rem;">
                        <i class="bi bi-arrow-down-circle me-1"></i>Ahorro: -$<?= number_format($discountAmount,2) ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="d-flex gap-2">
                    <input type="text" id="coupon-input" class="form-control"
                           placeholder="Código de cupón (NEXUS10, GAMER20)" style="max-width:280px;">
                    <button class="btn btn-outline-cyan btn-sm" onclick="applyCoupon()">
                        <i class="bi bi-check-lg me-1"></i>Aplicar
                    </button>
                </div>
                <div id="coupon-msg" style="margin-top:8px;font-size:0.85rem;"></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-3 mt-3">
                <a href="/nexusgear/client/productos.php" class="btn btn-outline-cyan btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Continuar comprando
                </a>
            </div>
        </div>

        <!-- Summary -->
        <div class="col-lg-4">
            <div class="order-summary-box">
                <h5 style="font-family:'Oxanium',sans-serif;color:var(--text-primary);margin-bottom:20px;">
                    <i class="bi bi-receipt me-2" style="color:var(--neon-cyan);"></i>Resumen del pedido
                </h5>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="cart-subtotal">$<?= number_format($subtotal,2) ?></span>
                </div>
                <?php if ($couponCode): ?>
                <div class="summary-row">
                    <span>Descuento (<?= $couponDiscount ?>%)</span>
                    <span class="summary-discount" id="cart-discount">-$<?= number_format($discountAmount,2) ?></span>
                </div>
                <?php else: ?>
                <div class="summary-row" id="discount-row" style="display:none;">
                    <span>Descuento</span>
                    <span class="summary-discount" id="cart-discount">$0.00</span>
                </div>
                <?php endif; ?>
                <div class="summary-row" style="border-bottom:none;padding-top:12px;">
                    <span style="font-weight:700;color:var(--text-primary);">Total</span>
                    <span class="summary-total" id="cart-total">$<?= number_format($total,2) ?></span>
                </div>
                <input type="hidden" id="discount-percent" value="<?= $couponDiscount ?>">
                <a href="/nexusgear/client/checkout.php" class="btn btn-neon w-100 mt-4">
                    <i class="bi bi-credit-card-2-front me-2"></i>Proceder al Checkout
                    <i class="bi bi-arrow-right ms-1"></i>
                </a>
                <div style="text-align:center;margin-top:12px;font-size:0.8rem;color:var(--text-muted);">
                    <i class="bi bi-lock-fill me-1"></i>Compra 100% segura y protegida
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__.'/../views/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script src="/nexusgear/assets/js/carrito.js"></script>
<script>
window.csrfToken='<?= htmlspecialchars($_SESSION['csrf_token']) ?>';

function applyCoupon(){
    const code=document.getElementById('coupon-input')?.value.trim().toUpperCase();
    if(!code){showToast('Ingresa un código de cupón.','warning');return;}
    fetch('/nexusgear/controllers/cupon_controller.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=validate&codigo=${encodeURIComponent(code)}&csrf_token=${window.csrfToken}`
    }).then(r=>r.json()).then(d=>{
        const msg=document.getElementById('coupon-msg');
        if(d.success){showToast(d.message,'success');setTimeout(()=>location.reload(),1000);}
        else{if(msg){msg.textContent=d.message;msg.style.color='var(--neon-pink)';}showToast(d.message,'error');}
    });
}

function removeCoupon(){
    fetch('/nexusgear/controllers/cupon_controller.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=remove&csrf_token=${window.csrfToken}`
    }).then(r=>r.json()).then(d=>{if(d.success){showToast('Cupón eliminado.','info');setTimeout(()=>location.reload(),800);}});
}

document.getElementById('coupon-input')?.addEventListener('keypress',function(e){if(e.key==='Enter') applyCoupon();});
</script>
</body>
</html>
