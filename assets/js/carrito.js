// ============================================================
// NexusGear — carrito.js v4
// addToCart muestra notificación flotante con info del producto
// ============================================================

function updateCartBadge(count) {
    document.querySelectorAll('#cart-badge,.cart-badge').forEach(b => {
        b.textContent = count > 0 ? count : '';
        if (count > 0) { b.classList.add('pop'); setTimeout(() => b.classList.remove('pop'), 350); }
    });
}

// ---- addToCart con notificación visual ----
function addToCart(productId, quantity = 1, productName = null, productImg = null) {
    // Intentar obtener info del producto del DOM si no se pasó
    if (!productName || !productImg) {
        // Buscar en la card más cercana al botón que fue clickeado
        const btn = document.querySelector(`.btn-add-to-cart[data-product-id="${productId}"]`);
        if (btn) {
            const card = btn.closest('.product-card, .col');
            if (card) {
                productName = productName || card.querySelector('h5,h6')?.textContent?.trim() || '';
                productImg  = productImg  || card.querySelector('img')?.src || '';
            }
        }
    }

    return fetch('/nexusgear/controllers/venta_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add_to_cart&id_producto=${productId}&cantidad=${quantity}&csrf_token=${window.csrfToken || ''}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            updateCartBadge(data.cart_count);
            // Mostrar notificación flotante con info del producto
            showCartNotification(productName || 'Producto', productImg || '', quantity);
            // Actualizar badge de stock en la card
            if (data.stock !== undefined) {
                _syncStockOnCard(productId, data.stock);
            }
        } else {
            const type = data.warning ? 'warning' : 'error';
            showToast(data.message || 'No se pudo agregar.', type);
        }
        return data;
    })
    .catch(() => showToast('Error de conexión.', 'error'));
}

// ---- Sincronizar indicador de stock en la card ----
function _syncStockOnCard(pid, stock) {
    document.querySelectorAll(`.btn-add-to-cart[data-product-id="${pid}"]`).forEach(btn => {
        if (stock <= 0) {
            btn.disabled = true;
            btn.textContent = '✗ Agotado';
            btn.classList.remove('btn-neon');
            btn.classList.add('btn-secondary');
        }
    });
}

// ---- Eliminar del carrito ----
function removeFromCart(pid, row) {
    fetch('/nexusgear/controllers/venta_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=remove_from_cart&id_producto=${pid}&csrf_token=${window.csrfToken || ''}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            updateCartBadge(d.cart_count);
            if (row) {
                row.style.transition = 'opacity .3s,transform .3s';
                row.style.opacity    = '0';
                row.style.transform  = 'translateX(20px)';
                setTimeout(() => { row.remove(); recalcTotals(); checkEmpty(); }, 300);
            }
            showToast('Producto eliminado del carrito.', 'info');
        }
    })
    .catch(() => showToast('Error de conexión.', 'error'));
}

// ---- Actualizar cantidad ----
function updateQty(pid, qty, row) {
    fetch('/nexusgear/controllers/venta_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_qty&id_producto=${pid}&cantidad=${qty}&csrf_token=${window.csrfToken || ''}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.removed) {
            showToast(d.message, 'warning');
            row.style.transition='opacity .3s'; row.style.opacity='0';
            setTimeout(() => { row.remove(); recalcTotals(); checkEmpty(); updateCartBadge(d.cart_count); }, 300);
            return;
        }
        if (d.success) {
            updateCartBadge(d.cart_count);
            if (d.warning && d.adjusted_qty !== undefined) {
                const input = row.querySelector('.qty-input');
                if (input) input.value = d.adjusted_qty;
                showToast(d.message, 'warning');
            }
            const price  = parseFloat(row.dataset.price || 0);
            const realQty = parseInt(row.querySelector('.qty-input')?.value || qty);
            const sub    = row.querySelector('.subtotal-cell');
            if (sub) sub.textContent = '$' + (price * realQty).toFixed(2);
            recalcTotals();
        }
    })
    .catch(() => {});
}

// ---- Recalcular totales ----
function recalcTotals() {
    let sub = 0;
    document.querySelectorAll('.cart-item-row').forEach(row => {
        const qty   = parseInt(row.querySelector('.qty-input')?.value || 0);
        const price = parseFloat(row.dataset.price || 0);
        const s     = qty * price;
        sub += s;
        const el = row.querySelector('.subtotal-cell');
        if (el) el.textContent = '$' + s.toFixed(2);
    });
    const disc  = parseFloat(document.getElementById('discount-percent')?.value || 0);
    const d     = sub * (disc / 100);
    const total = sub - d;
    const sEl   = document.getElementById('cart-subtotal');
    const dEl   = document.getElementById('cart-discount');
    const tEl   = document.getElementById('cart-total');
    if (sEl) sEl.textContent = '$' + sub.toFixed(2);
    if (dEl) dEl.textContent = '-$' + d.toFixed(2);
    if (tEl) tEl.textContent = '$' + total.toFixed(2);
}

function checkEmpty() {
    if (!document.querySelectorAll('.cart-item-row').length) {
        document.querySelector('.cart-table-wrapper')?.remove();
        const es = document.getElementById('empty-cart');
        if (es) es.style.display = 'block';
    }
}

// ============================================================
// EVENT DELEGATION — cubre cards estáticas y dinámicas (AJAX)
// ============================================================
document.addEventListener('DOMContentLoaded', function () {

    // "Agregar al carrito" — delegado
    document.addEventListener('click', function (e) {
        const addBtn = e.target.closest('.btn-add-to-cart');
        if (!addBtn || addBtn.disabled) return;
        e.preventDefault(); e.stopPropagation();
        const pid = addBtn.dataset.productId;
        if (!pid) return;
        // Obtener info del producto desde el DOM
        const card = addBtn.closest('.product-card, .col');
        const name = card?.querySelector('h5,h6')?.textContent?.trim() || '';
        const img  = card?.querySelector('.card-img-wrap img, img.card-img-top, img')?.src || '';
        // Selector de cantidad (página de detalle)
        const qtyInput = document.getElementById('qty-selector');
        const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
        addToCart(pid, qty, name, img);
    });

    // Botones +/- del carrito
    document.addEventListener('click', function (e) {
        if (e.target.closest('.qty-btn-plus')) {
            const row   = e.target.closest('.cart-item-row');
            const input = row?.querySelector('.qty-input');
            if (!input) return;
            const max = parseInt(input.max || 999), cur = parseInt(input.value);
            if (cur < max) { input.value = cur+1; updateQty(row.dataset.productId, cur+1, row); }
            else showToast(`Máximo disponible: ${max} unidades.`, 'warning');
        }
        if (e.target.closest('.qty-btn-minus')) {
            const row   = e.target.closest('.cart-item-row');
            const input = row?.querySelector('.qty-input');
            if (!input) return;
            const cur = parseInt(input.value);
            if (cur > 1) { input.value = cur-1; updateQty(row.dataset.productId, cur-1, row); }
        }
        if (e.target.closest('.btn-remove-cart')) {
            const row = e.target.closest('.cart-item-row');
            const pid = row?.dataset.productId;
            if (!pid) return;
            showConfirmModal('Eliminar producto','¿Quitar este producto del carrito?','Quitar')
                .then(ok => { if (ok) removeFromCart(pid, row); });
        }
    });

    // Cantidad directa
    document.querySelectorAll('.qty-input').forEach(inp => {
        inp.addEventListener('change', function () {
            const row = this.closest('.cart-item-row');
            if (!row) return;
            let v = parseInt(this.value)||1, mx = parseInt(this.max||999);
            v = Math.min(Math.max(v,1),mx);
            this.value = v;
            updateQty(row.dataset.productId, v, row);
        });
    });
});
