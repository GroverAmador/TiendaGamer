// ============================================================
// NexusGear — carrito.js v3
// Event delegation correcta + validación de stock frontend
// ============================================================

function updateCartBadge(count) {
    document.querySelectorAll('#cart-badge, .cart-badge').forEach(b => {
        b.textContent = count > 0 ? count : '';
        if (count > 0) { b.classList.add('pop'); setTimeout(() => b.classList.remove('pop'), 350); }
    });
}

// ---- Agregar al carrito con validación de stock ----
function addToCart(productId, quantity = 1) {
    return fetch('/nexusgear/controllers/venta_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add_to_cart&id_producto=${productId}&cantidad=${quantity}&csrf_token=${window.csrfToken || ''}`
    })
    .then(r => r.json())
    .then(data => {
        const type = data.success ? 'success' : (data.warning ? 'warning' : 'error');
        showToast(data.message || (data.success ? 'Agregado al carrito' : 'Error'), type);
        if (data.success) {
            updateCartBadge(data.cart_count);
            // Actualizar indicador de stock en la card si existe
            if (data.stock !== undefined) {
                _updateStockBadgeOnCard(productId, data.stock, data.en_carrito);
            }
        }
        return data;
    })
    .catch(() => { showToast('Error de conexión.', 'error'); });
}

// ---- Actualizar badge de stock en la card ----
function _updateStockBadgeOnCard(pid, stock, enCarrito) {
    const stockLeft = stock - (enCarrito || 0);
    // Buscar el badge de precio/stock en la card
    document.querySelectorAll(`[data-product-id="${pid}"]`).forEach(el => {
        const card = el.closest('.product-card');
        if (!card) return;
        // Actualizar el badge de stock bajo si existe
        const oldBadge = card.querySelector('.badge-orange.stock-low-badge');
        if (oldBadge) {
            if (stock <= 0) oldBadge.textContent = 'Agotado';
            else if (stock <= 5) oldBadge.textContent = `Últimas ${stock} uds.`;
        }
        // Deshabilitar botón si agotado
        const cartBtn = card.querySelector('.btn-add-to-cart');
        if (cartBtn && stock <= 0) {
            cartBtn.disabled = true;
            cartBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i>Agotado';
            cartBtn.className = cartBtn.className.replace('btn-neon', 'btn-secondary');
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
    });
}

// ---- Actualizar cantidad con validación de stock ----
function updateQty(pid, qty, row) {
    fetch('/nexusgear/controllers/venta_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_qty&id_producto=${pid}&cantidad=${qty}&csrf_token=${window.csrfToken || ''}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.removed) {
            // El producto ya no está disponible, eliminar la fila
            showToast(d.message, 'warning');
            row.style.transition = 'opacity .3s'; row.style.opacity = '0';
            setTimeout(() => { row.remove(); recalcTotals(); checkEmpty(); updateCartBadge(d.cart_count); }, 300);
            return;
        }
        if (d.success) {
            updateCartBadge(d.cart_count);
            // Si la cantidad fue ajustada al stock disponible
            if (d.warning && d.adjusted_qty !== undefined) {
                const input = row.querySelector('.qty-input');
                if (input) input.value = d.adjusted_qty;
                showToast(d.message, 'warning');
            }
            const price = parseFloat(row.dataset.price || 0);
            const realQty = parseInt(row.querySelector('.qty-input')?.value || qty);
            const sub   = row.querySelector('.subtotal-cell');
            if (sub) sub.textContent = '$' + (price * realQty).toFixed(2);
            recalcTotals();
        }
    });
}

// ---- Recalcular totales en DOM ----
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

// ---- Verificar si el carrito quedó vacío ----
function checkEmpty() {
    if (!document.querySelectorAll('.cart-item-row').length) {
        document.querySelector('.cart-table-wrapper')?.remove();
        const es = document.getElementById('empty-cart');
        if (es) es.style.display = 'block';
    }
}

// ============================================================
// EVENT DELEGATION — funciona para cards dinámicas (AJAX) y estáticas
// ============================================================
document.addEventListener('DOMContentLoaded', function () {

    // ---- "Agregar al carrito" (delegado, cubre cards dinámicas) ----
    document.addEventListener('click', function (e) {
        const addBtn = e.target.closest('.btn-add-to-cart');
        if (addBtn && !addBtn.disabled) {
            e.preventDefault();
            e.stopPropagation();
            const pid = addBtn.dataset.productId;
            if (!pid) return;
            // Buscar selector de cantidad en la misma card o página de detalle
            const card = addBtn.closest('.product-card, .col');
            const qtyInput = card?.querySelector('.qty-input') || document.getElementById('qty-selector');
            const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
            addToCart(pid, qty);
        }
    });

    // ---- Favorito (delegado, cubre cards dinámicas) ----
    // Usa event delegation para que funcione en cards cargadas por AJAX
    document.addEventListener('click', function (e) {
        const favBtn = e.target.closest('.card-fav-btn');
        // Excluir el botón de favorito del quick-view modal (tiene id propio)
        if (favBtn && favBtn.id !== 'qv-fav-btn') {
            e.preventDefault();
            e.stopPropagation();
            if (typeof _toggleFav === 'function') {
                _toggleFav(favBtn);
            }
        }
    });

    // ---- Carrito: botones +/- ----
    document.addEventListener('click', function (e) {
        if (e.target.closest('.qty-btn-plus')) {
            const row   = e.target.closest('.cart-item-row');
            const input = row?.querySelector('.qty-input');
            if (!input) return;
            const max = parseInt(input.max || 999);
            const cur = parseInt(input.value);
            if (cur < max) {
                input.value = cur + 1;
                updateQty(row.dataset.productId, cur + 1, row);
            } else {
                showToast(`Máximo disponible: ${max} unidades.`, 'warning');
            }
        }
        if (e.target.closest('.qty-btn-minus')) {
            const row   = e.target.closest('.cart-item-row');
            const input = row?.querySelector('.qty-input');
            if (!input) return;
            const cur = parseInt(input.value);
            if (cur > 1) { input.value = cur - 1; updateQty(row.dataset.productId, cur - 1, row); }
        }
        if (e.target.closest('.btn-remove-cart')) {
            const row = e.target.closest('.cart-item-row');
            const pid = row?.dataset.productId;
            if (!pid) return;
            showConfirmModal('Eliminar producto', '¿Quitar este producto del carrito?', 'Quitar')
                .then(ok => { if (ok) removeFromCart(pid, row); });
        }
    });

    // ---- Cantidad directa ----
    document.querySelectorAll('.qty-input').forEach(inp => {
        inp.addEventListener('change', function () {
            const row = this.closest('.cart-item-row');
            if (!row) return;
            let v   = parseInt(this.value) || 1;
            const mx = parseInt(this.max || 999);
            v = Math.min(Math.max(v, 1), mx);
            this.value = v;
            updateQty(row.dataset.productId, v, row);
        });
    });
});
