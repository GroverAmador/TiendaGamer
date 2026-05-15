// ============================================================
// NexusGear main.js v4
// Toast 3s • Notificación carrito • Favorito emoji • Quick-view
// ============================================================

// ── Carga Bootstrap Icons en el <head> si no está presente ──
(function() {
    if (!document.querySelector('link[href*="bootstrap-icons"]')) {
        var l = document.createElement('link');
        l.rel  = 'stylesheet';
        l.href = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css';
        document.head.insertBefore(l, document.head.firstChild);
    }
})();

// ============================================================
// TOAST — máx 3, 2.8 segundos
// ============================================================
const _toastSeconds = 2800;

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const colorMap = {
        success: { bar: '#84cc16', icon: '✅' },
        error:   { bar: '#f43f8e', icon: '❌' },
        warning: { bar: '#f59e0b', icon: '⚠️' },
        info:    { bar: '#00d8f0', icon: 'ℹ️' },
    };
    const m = colorMap[type] || colorMap.info;

    // Máx 3 toasts visibles
    const existing = container.querySelectorAll('.ng-toast');
    if (existing.length >= 3) _dismissToast(existing[0], true);

    const t = document.createElement('div');
    t.className = 'ng-toast';
    t.style.cssText = `
        background:#15152a;border-radius:10px;padding:11px 15px;
        display:flex;align-items:center;gap:9px;
        box-shadow:0 4px 24px rgba(0,0,0,.6),0 0 0 1px rgba(255,255,255,.06);
        border-left:3px solid ${m.bar};position:relative;overflow:hidden;
        animation:toastIn .3s cubic-bezier(.34,1.56,.64,1) forwards;
        cursor:pointer;max-width:320px;width:100%;
    `;
    t.innerHTML = `
        <span style="font-size:1rem;flex-shrink:0;">${m.icon}</span>
        <span style="color:#e2e2f0;font-size:.85rem;font-weight:500;line-height:1.4;flex:1;">${message}</span>
        <div style="position:absolute;bottom:0;left:0;height:2px;background:${m.bar};width:100%;animation:toastProg ${_toastSeconds}ms linear forwards;"></div>
    `;

    if (!document.getElementById('ng-toast-kf')) {
        const st = document.createElement('style');
        st.id = 'ng-toast-kf';
        st.textContent = `
            @keyframes toastIn  {from{opacity:0;transform:translateX(110%)}to{opacity:1;transform:translateX(0)}}
            @keyframes toastOut {from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(110%)}}
            @keyframes toastProg{from{width:100%}to{width:0%}}
        `;
        document.head.appendChild(st);
    }

    t.addEventListener('click', () => _dismissToast(t));
    container.appendChild(t);
    setTimeout(() => _dismissToast(t), _toastSeconds);
}

function _dismissToast(t, immediate = false) {
    if (!t || t._gone) return;
    t._gone = true;
    if (immediate) { t.remove(); return; }
    t.style.animation = 'toastOut .25s ease forwards';
    t.addEventListener('animationend', () => t.remove(), { once: true });
}

// ============================================================
// NOTIFICACIÓN FLOTANTE DE CARRITO
// Muestra una tarjeta con info del producto al agregar al carrito
// ============================================================
function showCartNotification(productName, productImg, qty) {
    // Eliminar notificación anterior si existe
    const prev = document.getElementById('cart-notification');
    if (prev) { prev.classList.add('hide'); setTimeout(() => prev.remove(), 250); }

    const notif = document.createElement('div');
    notif.id = 'cart-notification';
    notif.innerHTML = `
        <div style="padding:12px 14px;">
            <div style="display:flex;align-items:center;gap:7px;color:#84cc16;font-size:.82rem;font-weight:700;margin-bottom:10px;">
                ✅ ¡Agregado al carrito!
            </div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <img src="${productImg || 'https://placehold.co/48x48/11111e/00d8f0?text=?'}"
                     style="width:52px;height:52px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,.08);flex-shrink:0;"
                     onerror="this.src='https://placehold.co/48x48/11111e/00d8f0?text=?'">
                <div style="min-width:0;">
                    <div style="color:#e2e2f0;font-size:.84rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        ${_esc(productName)}
                    </div>
                    <div style="color:#8b8ba8;font-size:.75rem;margin-top:2px;">
                        Cantidad: <strong style="color:#00d8f0;">${qty}</strong>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:7px;">
                <button onclick="document.getElementById('cart-notification').classList.add('hide');setTimeout(()=>document.getElementById('cart-notification')?.remove(),250)"
                        style="flex:1;padding:7px 10px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
                               color:#8b8ba8;border-radius:7px;cursor:pointer;font-size:.78rem;font-family:'Exo 2',sans-serif;">
                    Seguir comprando
                </button>
                <a href="/nexusgear/client/carrito.php"
                   style="flex:1;padding:7px 10px;background:linear-gradient(135deg,#7c3aed,#00d8f0);
                          color:#080810;border-radius:7px;text-align:center;text-decoration:none;
                          font-size:.78rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:4px;">
                    Ver carrito →
                </a>
            </div>
        </div>
        <div class="cart-notif-progress"></div>
    `;

    document.body.appendChild(notif);

    // Auto-cerrar en 3 segundos
    setTimeout(() => {
        const n = document.getElementById('cart-notification');
        if (n) { n.classList.add('hide'); setTimeout(() => n.remove(), 250); }
    }, 3000);
}

// ============================================================
// FAVORITO — toggle con emoji y mensaje flotante
// ============================================================
function showFavTooltip(btn, text, isAdded) {
    // Limpiar tooltip anterior
    document.querySelectorAll('.fav-tooltip').forEach(t => t.remove());

    const rect = btn.getBoundingClientRect();
    const tip  = document.createElement('div');
    tip.className = 'fav-tooltip' + (isAdded ? '' : ' green');
    // Si se agrega → color pink, si se quita → color muted (usamos clases invertidas para semántica)
    if (isAdded) {
        tip.style.borderColor = 'rgba(244,63,142,0.4)';
        tip.style.color       = '#f43f8e';
    } else {
        tip.style.borderColor = 'rgba(139,139,168,0.3)';
        tip.style.color       = '#8b8ba8';
    }
    tip.textContent = text;
    tip.style.top  = (rect.bottom + window.scrollY + 6) + 'px';
    tip.style.left = (rect.left + rect.width / 2 + window.scrollX) + 'px';
    tip.style.transform = 'translateX(-50%)';
    document.body.appendChild(tip);

    setTimeout(() => {
        tip.style.transition = 'opacity .2s ease, transform .2s ease';
        tip.style.opacity    = '0';
        tip.style.transform  = 'translateX(-50%) translateY(-6px)';
        setTimeout(() => tip.remove(), 200);
    }, 1600);
}

function _toggleFav(btn) {
    const pid      = btn.dataset.productId;
    const isActive = btn.classList.contains('active');
    const emojiEl  = btn.querySelector('.fav-emoji');
    if (!pid) return;

    fetch('/nexusgear/controllers/favorito_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${isActive ? 'remove' : 'add'}&id_producto=${pid}&csrf_token=${window.csrfToken || ''}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const nowActive = !isActive;
            btn.classList.toggle('active', nowActive);
            if (emojiEl) emojiEl.textContent = nowActive ? '❤️' : '🤍';

            // Mensaje flotante pequeño
            showFavTooltip(btn, nowActive ? 'Agregado a favoritos ❤️' : 'Eliminado de favoritos', nowActive);

            // Sincronizar estado en otros botones del mismo producto
            document.querySelectorAll(`.card-fav-btn[data-product-id="${pid}"]`).forEach(b => {
                if (b !== btn) {
                    b.classList.toggle('active', nowActive);
                    const e2 = b.querySelector('.fav-emoji');
                    if (e2) e2.textContent = nowActive ? '❤️' : '🤍';
                }
            });

            // También el botón del quickview
            const qvFav = document.getElementById('qv-fav-btn');
            if (qvFav && qvFav.dataset.productId === pid) {
                qvFav.classList.toggle('active', nowActive);
                const qe = qvFav.querySelector('.fav-emoji');
                if (qe) qe.textContent = nowActive ? '❤️' : '🤍';
            }

            // Actualizar lista userFavIds
            if (window.userFavIds) {
                const id = parseInt(pid);
                if (isActive) window.userFavIds = window.userFavIds.filter(x => x !== id);
                else          window.userFavIds.push(id);
            }
        } else {
            showToast(d.message || 'Inicia sesión para usar favoritos.', 'warning');
        }
    })
    .catch(() => showToast('Error de conexión.', 'error'));
}

// ============================================================
// QUICK-VIEW MODAL
// ============================================================
function openQuickView(productId) {
    const modal = document.getElementById('quickview-modal');
    if (!modal) return;
    const bm = bootstrap.Modal.getOrCreateInstance(modal);
    document.getElementById('qv-loading').style.display = 'flex';
    document.getElementById('qv-content').style.display  = 'none';
    document.getElementById('qv-error').style.display    = 'none';
    bm.show();

    fetch(`/nexusgear/controllers/producto_controller.php?action=quickview&id_producto=${productId}`)
        .then(r => r.json())
        .then(d => { if (d.success) _fillQV(d.product); else _showQvError(); })
        .catch(_showQvError);
}
function _showQvError() {
    document.getElementById('qv-loading').style.display = 'none';
    document.getElementById('qv-error').style.display   = 'flex';
}
function _fillQV(p) {
    document.getElementById('qv-img').src           = p.imagen || '';
    document.getElementById('qv-name').textContent  = p.nombre || '';
    document.getElementById('qv-brand').textContent = p.marca  || '';
    document.getElementById('qv-cat').textContent   = (p.icono||'') + ' ' + (p.nombre_categoria||'');
    document.getElementById('qv-price').textContent = '$' + parseFloat(p.precio).toFixed(2);
    document.getElementById('qv-desc').textContent  = p.descripcion || 'Sin descripción.';
    document.getElementById('qv-detail-link').href  = `/nexusgear/client/producto_detalle.php?id=${p.id_producto}`;

    // Estrellas con texto
    const avg = parseFloat(p.avg_rating || 0);
    let sh = '';
    for (let i=1;i<=5;i++) sh += `<span style="color:${i<=Math.round(avg)?'#f59e0b':'var(--text-muted)'};">${i<=Math.round(avg)?'★':'☆'}</span>`;
    document.getElementById('qv-stars').innerHTML = sh + `<span style="color:var(--text-muted);font-size:.78rem;margin-left:5px;">(${p.review_count||0})</span>`;

    // Stock
    const stk = parseInt(p.stock);
    const sEl = document.getElementById('qv-stock');
    if (stk<=0)      sEl.innerHTML = '<span class="badge-pink">✗ Agotado</span>';
    else if (stk<=5) sEl.innerHTML = `<span class="badge-orange">⚠ Últimas ${stk} unidades</span>`;
    else             sEl.innerHTML = `<span class="badge-green">✓ En stock (${stk})</span>`;

    // Cart btn
    const cBtn = document.getElementById('qv-cart-btn');
    cBtn.dataset.productId = p.id_producto;
    if (stk<=0) { cBtn.disabled=true; cBtn.textContent='Sin stock'; cBtn.className='btn btn-secondary'; }
    else        { cBtn.disabled=false; cBtn.innerHTML='🛒 Agregar al carrito'; cBtn.className='btn btn-neon'; }

    // Fav btn
    const fBtn = document.getElementById('qv-fav-btn');
    fBtn.dataset.productId = p.id_producto;
    const isFav = window.userFavIds && window.userFavIds.includes(parseInt(p.id_producto));
    fBtn.classList.toggle('active', isFav);
    const fe = fBtn.querySelector('.fav-emoji');
    if (fe) fe.textContent = isFav ? '❤️' : '🤍';

    const qEl = document.getElementById('qv-qty');
    if (qEl) { qEl.value=1; qEl.max=stk; }

    // Reseñas
    const rEl = document.getElementById('qv-reviews');
    if (rEl) {
        if (!p.reviews || !p.reviews.length) {
            rEl.innerHTML = '<p style="color:var(--text-muted);font-size:.82rem;text-align:center;padding:8px 0;">Sin reseñas aún.</p>';
        } else {
            rEl.innerHTML = p.reviews.map(r => {
                let s='';for(let i=1;i<=5;i++) s+=`<span style="color:${i<=r.puntuacion?'#f59e0b':'var(--text-muted)'}">${i<=r.puntuacion?'★':'☆'}</span>`;
                return `<div style="border-bottom:1px solid var(--border-subtle);padding:8px 0;display:flex;flex-direction:column;gap:3px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div style="width:22px;height:22px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;color:#fff;flex-shrink:0;">${(r.nombre||'?').charAt(0).toUpperCase()}</div>
                        <span style="font-size:.8rem;font-weight:600;color:var(--text-primary);">${_esc(r.nombre)}</span>
                        <span style="margin-left:auto;font-size:.8rem;">${s}</span>
                    </div>
                    <p style="color:var(--text-secondary);font-size:.78rem;margin:0;line-height:1.5;padding-left:28px;">${_esc(r.comentario)}</p>
                </div>`;
            }).join('');
        }
    }

    document.getElementById('qv-loading').style.display = 'none';
    document.getElementById('qv-content').style.display  = 'flex';
}
function _esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ============================================================
// CONFIRM MODAL
// ============================================================
function showConfirmModal(title, message, confirmLabel = 'Eliminar') {
    return new Promise(resolve => {
        document.getElementById('_ngcm')?.remove();
        const overlay = document.createElement('div');
        overlay.id = '_ngcm';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.75);display:flex;align-items:center;justify-content:center;z-index:9999;padding:20px;backdrop-filter:blur(5px);animation:fadeInUp .2s ease';
        overlay.innerHTML = `
        <div style="background:#11111e;border:1px solid rgba(0,216,240,.25);border-radius:18px;padding:30px;max-width:380px;width:100%;box-shadow:0 0 40px rgba(0,216,240,.1);animation:scaleBounce .3s ease;">
            <div style="text-align:center;margin-bottom:14px;font-size:2rem;">⚠️</div>
            <h5 style="font-family:'Oxanium',sans-serif;color:#e2e2f0;text-align:center;margin-bottom:8px;">${title}</h5>
            <p style="color:#8b8ba8;font-size:.87rem;text-align:center;margin-bottom:22px;line-height:1.6;">${message}</p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button id="_cm_cancel" style="padding:9px 22px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#8b8ba8;border-radius:8px;cursor:pointer;font-size:.87rem;">Cancelar</button>
                <button id="_cm_ok" style="padding:9px 22px;background:linear-gradient(135deg,#7c3aed,#f43f8e);border:none;color:#fff;border-radius:8px;cursor:pointer;font-weight:600;font-size:.87rem;">${confirmLabel}</button>
            </div>
        </div>`;
        document.body.appendChild(overlay);
        overlay.querySelector('#_cm_ok').onclick     = () => { overlay.remove(); resolve(true);  };
        overlay.querySelector('#_cm_cancel').onclick = () => { overlay.remove(); resolve(false); };
        overlay.onclick = e => { if (e.target === overlay) { overlay.remove(); resolve(false); } };
    });
}

// ============================================================
// NAVBAR SCROLL
// ============================================================
function initNavbar() {
    const nav = document.getElementById('mainNav');
    if (!nav) return;
    window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 40), { passive: true });
}

// ============================================================
// ADMIN SIDEBAR
// ============================================================
function initSidebar() {
    const btn  = document.getElementById('sidebarToggleBtn');
    const side = document.getElementById('adminSidebar');
    if (!btn || !side) return;
    btn.addEventListener('click', () => side.classList.toggle('open'));
    document.addEventListener('click', e => { if (!side.contains(e.target) && e.target !== btn) side.classList.remove('open'); });
    side.querySelectorAll('.sidebar-nav-item').forEach((el, i) => {
        el.style.opacity='0'; el.style.transform='translateX(-14px)';
        setTimeout(() => { el.style.transition=`opacity .35s ease,transform .35s cubic-bezier(.34,1.56,.64,1)`; el.style.opacity='1'; el.style.transform='translateX(0)'; }, 80+i*55);
    });
}

// ============================================================
// SCROLL ANIMATIONS & COUNTERS
// ============================================================
function initScrollAnimations() {
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } });
    }, { threshold: 0.1, rootMargin:'0px 0px -20px 0px' });
    document.querySelectorAll('.animate-on-scroll').forEach(el => obs.observe(el));
}
function initCounters() {
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) { countUp(e.target); obs.unobserve(e.target); } });
    }, { threshold: 0.5 });
    document.querySelectorAll('[data-counter]').forEach(el => obs.observe(el));
}
function countUp(el) {
    const target=parseInt(el.dataset.counter,10), dur=1800, start=performance.now();
    const tick=now=>{const p=Math.min((now-start)/dur,1);el.textContent=Math.floor((p*(2-p))*target).toLocaleString();if(p<1)requestAnimationFrame(tick);else el.textContent=target.toLocaleString();};
    requestAnimationFrame(tick);
}

// ============================================================
// CARD CLICK — navega al detalle si no es botón
// ============================================================
function handleCardClick(e, pid) {
    if (e.target.closest('button,.card-fav-btn,.qv-overlay,.qv-btn,.btn-add-to-cart')) return;
    window.location.href = `/nexusgear/client/producto_detalle.php?id=${pid}`;
}

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    initScrollAnimations();
    initCounters();
    initNavbar();
    initSidebar();

    // Ripple en botones
    document.querySelectorAll('.btn-neon,.btn-outline-cyan,.btn-outline-violet').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const r=this.getBoundingClientRect(),size=Math.max(r.width,r.height),x=e.clientX-r.left-size/2,y=e.clientY-r.top-size/2;
            const rip=document.createElement('span');
            rip.style.cssText=`width:${size}px;height:${size}px;left:${x}px;top:${y}px;position:absolute;border-radius:50%;background:rgba(255,255,255,.2);transform:scale(0);animation:ripple-out .55s linear;pointer-events:none;`;
            this.appendChild(rip);
            rip.addEventListener('animationend',()=>rip.remove());
        });
    });

    // Quick-view: botones del modal
    document.getElementById('qv-cart-btn')?.addEventListener('click', function () {
        const qty = parseInt(document.getElementById('qv-qty')?.value || 1);
        if (this.dataset.productId) addToCart(this.dataset.productId, qty);
    });
    document.getElementById('qv-qty-plus')?.addEventListener('click', () => {
        const q=document.getElementById('qv-qty'); if(q&&+q.value<+q.max) q.value++;
    });
    document.getElementById('qv-qty-minus')?.addEventListener('click', () => {
        const q=document.getElementById('qv-qty'); if(q&&+q.value>1) q.value--;
    });
    document.getElementById('qv-fav-btn')?.addEventListener('click', function() { _toggleFav(this); });

    // EVENT DELEGATION — favorito en cards (cubre cards dinámicas del buscador AJAX)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.card-fav-btn');
        if (btn && btn.id !== 'qv-fav-btn') {
            e.preventDefault();
            e.stopPropagation();
            _toggleFav(btn);
        }
    });
});
