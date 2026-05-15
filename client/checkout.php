<?php
// ============================================================
// NexusGear - Checkout Page
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario'])) { header('Location: /nexusgear/auth/login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='confirm_order') {
    require_once __DIR__.'/../controllers/venta_controller.php'; exit;
}

$carrito = $_SESSION['carrito'] ?? [];
if (empty($carrito)) { header('Location: /nexusgear/client/carrito.php'); exit; }

$cartItems = [];
$subtotal  = 0;
foreach ($carrito as $pid => $item) {
    $pid  = (int)$pid;
    $stmt = mysqli_prepare($conn,"SELECT * FROM Producto WHERE id_producto=? AND estado=1");
    mysqli_stmt_bind_param($stmt,'i',$pid);
    mysqli_stmt_execute($stmt);
    $prod = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($prod) {
        $qty=$item['cantidad'];$sub=(float)$prod['precio']*$qty;$subtotal+=$sub;
        $cartItems[]=['producto'=>$prod,'cantidad'=>$qty,'subtotal'=>$sub];
    }
}
$couponCode     = $_SESSION['cupon_codigo']   ?? null;
$couponDiscount = (float)($_SESSION['cupon_descuento'] ?? 0);
$discountAmount = $subtotal * ($couponDiscount / 100);
$total          = $subtotal - $discountAmount;
$userName       = htmlspecialchars($_SESSION['nombre'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — NexusGear</title>
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
        <h1><i class="bi bi-credit-card-2-front-fill me-2" style="color:var(--neon-cyan);"></i>Checkout</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/nexusgear/client/carrito.php"><i class="bi bi-cart3 me-1"></i>Carrito</a></li>
                <li class="breadcrumb-item active">Checkout</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container-xl py-4">
    <div class="row g-4">

        <!-- Order summary -->
        <div class="col-lg-5 order-lg-2">
            <div class="order-summary-box" style="position:sticky;top:88px;">
                <h5 style="font-family:'Oxanium',sans-serif;color:var(--text-primary);margin-bottom:20px;">
                    <i class="bi bi-box-seam me-2" style="color:var(--neon-cyan);"></i>Resumen del pedido
                </h5>
                <?php foreach ($cartItems as $item): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="<?= htmlspecialchars($item['producto']['imagen']??'') ?>"
                         style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--border-subtle);">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars($item['producto']['nombre']) ?>
                        </div>
                        <div style="font-size:0.78rem;color:var(--text-muted);">
                            x<?= $item['cantidad'] ?> × $<?= number_format((float)$item['producto']['precio'],2) ?>
                        </div>
                    </div>
                    <div style="font-weight:700;color:var(--neon-cyan);font-size:0.9rem;">
                        $<?= number_format($item['subtotal'],2) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <hr style="border-color:var(--border-subtle);">
                <div class="summary-row">
                    <span>Subtotal</span><span>$<?= number_format($subtotal,2) ?></span>
                </div>
                <?php if ($couponCode): ?>
                <div class="summary-row">
                    <span>Cupón <span style="color:var(--neon-cyan);"><?= htmlspecialchars($couponCode) ?></span></span>
                    <span class="summary-discount">-$<?= number_format($discountAmount,2) ?></span>
                </div>
                <?php endif; ?>
                <div class="summary-row" style="border:none;">
                    <span style="font-weight:700;color:var(--text-primary);">Total</span>
                    <span class="summary-total">$<?= number_format($total,2) ?></span>
                </div>
            </div>
        </div>

        <!-- Payment form -->
        <div class="col-lg-7 order-lg-1">
            <!-- Card preview -->
            <div class="checkout-card-preview mb-4">
                <i class="bi bi-wifi" style="font-size:1.4rem;"></i>
                <div class="card-number-display" id="card-number-display">•••• •••• •••• ••••</div>
                <div class="card-bottom">
                    <div>
                        <div style="font-size:0.7rem;opacity:0.7;margin-bottom:2px;">TITULAR</div>
                        <div id="card-name-display"><?= strtoupper($userName) ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;opacity:0.7;margin-bottom:2px;">EXPIRA</div>
                        <div id="card-expiry-display">MM/YY</div>
                    </div>
                </div>
            </div>

            <form id="form-checkout" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="confirm_order">

                <h5 style="font-family:'Oxanium',sans-serif;color:var(--neon-cyan);margin-bottom:20px;">
                    <i class="bi bi-credit-card me-2"></i>Datos de pago <span style="font-size:0.75rem;color:var(--text-muted);font-weight:400;">(simulación)</span>
                </h5>

                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-credit-card me-1"></i>Número de tarjeta</label>
                    <input type="text" id="card-number" class="form-control"
                           placeholder="1234 5678 9012 3456" maxlength="19" autocomplete="cc-number">
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-person me-1"></i>Nombre del titular</label>
                    <input type="text" id="card-name" class="form-control"
                           placeholder="Como aparece en la tarjeta" value="<?= $userName ?>" autocomplete="cc-name">
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <label class="form-label"><i class="bi bi-calendar3 me-1"></i>Vencimiento</label>
                        <input type="text" id="card-expiry" class="form-control"
                               placeholder="MM/YY" maxlength="5" autocomplete="cc-exp">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><i class="bi bi-lock me-1"></i>CVV</label>
                        <input type="password" class="form-control"
                               placeholder="•••" maxlength="4" autocomplete="cc-csc">
                    </div>
                </div>

                <div style="background:rgba(0,245,255,0.05);border:1px solid rgba(0,245,255,0.15);
                            border-radius:8px;padding:12px;margin-bottom:20px;font-size:0.82rem;color:var(--text-muted);">
                    <i class="bi bi-shield-fill-check me-1" style="color:var(--neon-cyan);"></i>
                    Entorno de demostración. No ingreses datos bancarios reales.
                </div>

                <button type="submit" class="btn btn-neon w-100" style="padding:16px;font-size:1.1rem;">
                    <i class="bi bi-check-circle-fill me-2"></i>Confirmar Pedido —
                    $<?= number_format($total,2) ?>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Success modal -->
<div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4">
            <div id="success-icon" style="font-size:4rem;margin-bottom:16px;">
                <div style="width:80px;height:80px;border-radius:50%;background:rgba(0,245,255,0.1);
                            border:2px solid rgba(0,245,255,0.3);display:flex;align-items:center;
                            justify-content:center;margin:0 auto;animation:iconSpin 0.8s linear infinite;">
                    <i class="bi bi-hourglass-split" style="color:var(--neon-cyan);font-size:2rem;"></i>
                </div>
            </div>
            <h4 style="font-family:'Oxanium',sans-serif;color:var(--neon-cyan);" id="success-title">
                Procesando pedido...
            </h4>
            <p style="color:var(--text-secondary);" id="success-msg">Por favor espera.</p>
            <a href="/nexusgear/client/historial.php" class="btn btn-neon mt-3" id="success-link" style="display:none;">
                <i class="bi bi-list-check me-2"></i>Ver mis pedidos
            </a>
        </div>
    </div>
</div>

<?php include __DIR__.'/../views/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script src="/nexusgear/assets/js/validaciones.js"></script>
<script>
window.csrfToken='<?= htmlspecialchars($_SESSION['csrf_token']) ?>';

document.getElementById('card-expiry')?.addEventListener('input',function(){
    let v=this.value.replace(/\D/g,'').slice(0,4);
    if(v.length>2) v=v.slice(0,2)+'/'+v.slice(2);
    this.value=v;
    const d=document.getElementById('card-expiry-display');
    if(d) d.textContent=this.value||'MM/YY';
});

document.getElementById('form-checkout').addEventListener('submit',function(e){
    e.preventDefault();
    const modal=new bootstrap.Modal(document.getElementById('successModal'));
    modal.show();
    const formData=new FormData(this);
    fetch('/nexusgear/controllers/venta_controller.php',{method:'POST',body:formData})
        .then(r=>r.json())
        .then(data=>{
            const iconEl=document.getElementById('success-icon');
            if(data.success){
                iconEl.innerHTML='<div style="width:80px;height:80px;border-radius:50%;background:rgba(200,255,0,0.1);border:2px solid rgba(200,255,0,0.3);display:flex;align-items:center;justify-content:center;margin:0 auto;"><i class="bi bi-check-circle-fill" style="color:var(--neon-green);font-size:2.5rem;"></i></div>';
                document.getElementById('success-title').textContent='¡Pedido confirmado!';
                document.getElementById('success-title').style.color='var(--neon-green)';
                document.getElementById('success-msg').textContent=data.message+(data.order_id?' Pedido #'+data.order_id:'');
                document.getElementById('success-link').style.display='inline-block';
            } else {
                iconEl.innerHTML='<div style="width:80px;height:80px;border-radius:50%;background:rgba(255,45,120,0.1);border:2px solid rgba(255,45,120,0.3);display:flex;align-items:center;justify-content:center;margin:0 auto;"><i class="bi bi-x-circle-fill" style="color:var(--neon-pink);font-size:2.5rem;"></i></div>';
                document.getElementById('success-title').textContent='Error';
                document.getElementById('success-title').style.color='var(--neon-pink)';
                document.getElementById('success-msg').textContent=data.message;
            }
        });
});
</script>
</body>
</html>
