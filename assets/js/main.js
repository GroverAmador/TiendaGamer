// ============================================================
// NexusGear main.js v2
// Toast 3s • Ripple • 3D tilt • Quick-view • Sidebar • Counters
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
// TOAST — máx 3 visibles, 2.8 segundos, sin acumulación
// ============================================================
const _toastQueue   = [];
const _maxToasts    = 3;
const _toastSeconds = 2800;

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const colorMap = {
        success: { bar: '#84cc16', icon: 'bi-check-circle-fill', c: '#84cc16' },
        error:   { bar: '#f43f8e', icon: 'bi-x-circle-fill',     c: '#f43f8e' },
        warning: { bar: '#f59e0b', icon: 'bi-exclamation-triangle-fill', c: '#f59e0b' },
        info:    { bar: '#00d8f0', icon: 'bi-info-circle-fill',   c: '#00d8f0' },
    };
    const m = colorMap[type] || colorMap.info;

    // Si ya hay 3 toasts, eliminar el más antiguo
    const existing = container.querySelectorAll('.ng-toast');
    if (existing.length >= _maxToasts) {
        _dismissToast(existing[0], true);
    }

    const t = document.createElement('div');
    t.className = 'ng-toast';
    t.style.cssText = `
        background:#15152a;
        border-radius:10px;
        padding:12px 16px;
        display:flex;
        align-items:center;
        gap:10px;
        box-shadow:0 4px 24px rgba(0,0,0,0.6),0 0 0 1px rgba(255,255,255,0.06);
        border-left:3px solid ${m.bar};
        position:relative;
        overflow:hidden;
        animation:toastIn .32s cubic-bezier(.34,1.56,.64,1) forwards;
        cursor:pointer;
        max-width:320px;
        width:100%;
    `;
    t.innerHTML = `
        <i class="bi ${m.icon}" style="font-size:1.1rem;color:${m.c};flex-shrink:0;"></i>
        <span style="color:#e2e2f0;font-size:.86rem;font-weight:500;line-height:1.4;flex:1;">${message}</span>
        <div style="position:absolute;bottom:0;left:0;height:2px;background:${m.bar};width:100%;animation:toastProg ${_toastSeconds}ms linear forwards;"></div>
    `;

    // Inyectar keyframes si no existen
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
    if (immediate) {
        t.remove();
        return;
    }
    t.style.animation = 'toastOut .25s ease forwards';
    t.addEventListener('animationend', () => t.remove(), { once: true });
}

// ============================================================
// RIPPLE en botones .btn-neon / .btn-outline-*
// ============================================================
function _addRipple(e) {
    const btn  = e.currentTarget;
    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x    = e.clientX - rect.left - size / 2;
    const y    = e.clientY - rect.top  - size / 2;
    const r    = document.createElement('span');
    r.className = 'btn-ripple';
    r.style.cssText = `
        width:${size}px;height:${size}px;
        left:${x}px;top:${y}px;
        position:absolute;border-radius:50%;
        background:rgba(255,255,255,0.22);transform:scale(0);
        animation:ripple-out 0.55s linear;pointer-events:none;
    `;
    btn.appendChild(r);
    r.addEventListener('animationend', () => r.remove());
}
function initRipple() {
    document.querySelectorAll('.btn-neon,.btn-outline-cyan,.btn-outline-violet')
        .forEach(b => b.addEventListener('click', _addRipple));
}

// ============================================================
// 3D CARD TILT (desktop only)
// ============================================================
function init3DTilt() {
    if (window.innerWidth < 769) return;
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('mousemove', e => {
            const r  = card.getBoundingClientRect();
            const rx = ((e.clientY - r.top)  / r.height - 0.5) * -8;
            const ry = ((e.clientX - r.left) / r.width  - 0.5) *  8;
            card.style.transition = 'transform .1s ease,border-color .25s,box-shadow .25s';
            card.style.transform  = `translateY(-7px) rotateX(${rx}deg) rotateY(${ry}deg)`;
        });
        card.addEventListener('mouseleave', () => {
            card.style.transition = 'transform .4s cubic-bezier(.34,1.56,.64,1),border-color .25s,box-shadow .25s';
            card.style.transform  = '';
        });
    });
}

// ============================================================
// SCROLL ANIMATIONS
// ============================================================
function initScrollAnimations() {
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -20px 0px' });
    document.querySelectorAll('.animate-on-scroll').forEach(el => obs.observe(el));
}

// ============================================================
// ANIMATED COUNTERS
// ============================================================
function initCounters() {
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) { countUp(e.target); obs.unobserve(e.target); }
        });
    }, { threshold: 0.5 });
    document.querySelectorAll('[data-counter]').forEach(el => obs.observe(el));
}
function countUp(el) {
    const target = parseInt(el.dataset.counter, 10);
    const dur    = 1800;
    const start  = performance.now();
    const tick   = now => {
        const p = Math.min((now - start) / dur, 1);
        el.textContent = Math.floor((p * (2 - p)) * target).toLocaleString();
        if (p < 1) requestAnimationFrame(tick);
        else el.textContent = target.toLocaleString();
    };
    requestAnimationFrame(tick);
}

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
        <div style="background:#11111e;border:1px solid rgba(0,216,240,.25);border-radius:18px;padding:32px;max-width:400px;width:100%;box-shadow:0 0 40px rgba(0,216,240,.1);animation:scaleBounce .32s ease;">
            <div style="text-align:center;margin-bottom:14px;">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:2.2rem;color:#f59e0b;"></i>
            </div>
            <h5 style="font-family:'Oxanium',sans-serif;color:#e2e2f0;text-align:center;margin-bottom:8px;">${title}</h5>
            <p style="color:#8b8ba8;font-size:.88rem;text-align:center;margin-bottom:22px;line-height:1.6;">${message}</p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button id="_cm_cancel" style="padding:9px 22px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#8b8ba8;border-radius:8px;cursor:pointer;font-size:.88rem;font-family:'Exo 2',sans-serif;"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                <button id="_cm_ok" style="padding:9px 22px;background:linear-gradient(135deg,#7c3aed,#f43f8e);border:none;color:#fff;border-radius:8px;cursor:pointer;font-weight:600;font-size:.88rem;font-family:'Exo 2',sans-serif;"><i class="bi bi-trash3 me-1"></i>${confirmLabel}</button>
            </div>
        </div>`;
        document.body.appendChild(overlay);
        overlay.querySelector('#_cm_ok').onclick     = () => { overlay.remove(); resolve(true);  };
        overlay.querySelector('#_cm_cancel').onclick = () => { overlay.remove(); resolve(false); };
        overlay.onclick = e => { if (e.target === overlay) { overlay.remove(); resolve(false); } };
    });
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
        .then(d => { if (d.success) _fillQV(d.product); else showQvError(); })
        .catch(showQvError);
}
function showQvError() {
    document.getElementById('qv-loading').style.display = 'none';
    document.getElementById('qv-error').style.display   = 'flex';
}
function _fillQV(p) {
    // Imagen
    document.getElementById('qv-img').src = p.imagen || '';
    // Textos
    document.getElementById('qv-name').textContent  = p.nombre || '';
    document.getElementById('qv-brand').textContent = p.marca  || '';
    document.getElementById('qv-cat').textContent   = (p.icono||'') + ' ' + (p.nombre_categoria||'');
    document.getElementById('qv-price').textContent = '$' + parseFloat(p.precio).toFixed(2);
    document.getElementById('qv-desc').textContent  = p.descripcion || 'Sin descripción.';
    document.getElementById('qv-detail-link').href  = `/nexusgear/client/producto_detalle.php?id=${p.id_producto}`;

    // Estrellas
    const avg = parseFloat(p.avg_rating||0);
    let sh = '';
    for (let i=1;i<=5;i++) sh += `<i class="bi ${i<=Math.round(avg)?'bi-star-fill':'bi-star'}" style="color:${i<=Math.round(avg)?'#f59e0b':'var(--text-muted)'};font-size:.9rem;"></i>`;
    document.getElementById('qv-stars').innerHTML = sh;
    document.getElementById('qv-rev-count').textContent = '(' + (p.review_count||0) + ' reseñas)';

    // Stock
    const stk = parseInt(p.stock);
    const sEl = document.getElementById('qv-stock');
    if (stk<=0)     sEl.innerHTML = '<span class="badge-pink"><i class="bi bi-x-circle me-1"></i>Agotado</span>';
    else if (stk<=5) sEl.innerHTML = `<span class="badge-orange"><i class="bi bi-exclamation-triangle me-1"></i>Últimas ${stk} unidades</span>`;
    else             sEl.innerHTML = `<span class="badge-green"><i class="bi bi-check-circle me-1"></i>En stock (${stk})</span>`;

    // Cart btn
    const cBtn = document.getElementById('qv-cart-btn');
    cBtn.dataset.productId = p.id_producto;
    if (stk<=0) { cBtn.disabled=true; cBtn.innerHTML='<i class="bi bi-x-circle me-1"></i>Sin stock'; cBtn.className='btn btn-secondary'; }
    else        { cBtn.disabled=false; cBtn.innerHTML='<i class="bi bi-cart3 me-1"></i>Agregar'; cBtn.className='btn btn-neon'; }

    // Fav btn
    const fBtn = document.getElementById('qv-fav-btn');
    fBtn.dataset.productId = p.id_producto;
    const isFav = window.userFavIds && window.userFavIds.includes(parseInt(p.id_producto));
    fBtn.classList.toggle('active', isFav);
    fBtn.querySelector('.bi').className = `bi ${isFav?'bi-heart-fill':'bi-heart'}`;

    // Qty
    const qEl = document.getElementById('qv-qty');
    if (qEl) { qEl.value=1; qEl.max=stk; }

    // Mini reseñas
    const rEl = document.getElementById('qv-reviews');
    if (rEl) {
        if (!p.reviews || !p.reviews.length) {
            rEl.innerHTML = '<p style="color:var(--text-muted);font-size:.82rem;text-align:center;padding:10px 0;">Sin reseñas aún.</p>';
        } else {
            rEl.innerHTML = p.reviews.map(r => {
                let s='';for(let i=1;i<=5;i++) s+=`<i class="bi ${i<=r.puntuacion?'bi-star-fill':'bi-star'}" style="font-size:.7rem;color:${i<=r.puntuacion?'#f59e0b':'var(--text-muted)'}"></i>`;
                return `<div style="border-bottom:1px solid var(--border-subtle);padding:9px 0;display:flex;flex-direction:column;gap:4px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div style="width:24px;height:24px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#fff;flex-shrink:0;">${(r.nombre||'?').charAt(0).toUpperCase()}</div>
                        <span style="font-size:.82rem;font-weight:600;color:var(--text-primary);">${_esc(r.nombre)}</span>
                        <span style="margin-left:auto;">${s}</span>
                    </div>
                    <p style="color:var(--text-secondary);font-size:.8rem;margin:0;line-height:1.5;">${_esc(r.comentario)}</p>
                </div>`;
            }).join('');
        }
    }

    document.getElementById('qv-loading').style.display = 'none';
    document.getElementById('qv-content').style.display  = 'flex';
}
function _esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ============================================================
// NAVBAR SCROLL
// ============================================================
function initNavbar() {
    const nav = document.getElementById('mainNav');
    if (!nav) return;
    window.addEventListener('scroll', () => {
        nav.classList.toggle('scrolled', window.scrollY > 40);
    }, { passive: true });
}

// ============================================================
// ADMIN SIDEBAR TOGGLE
// ============================================================
function initSidebar() {
    const btn  = document.getElementById('sidebarToggleBtn');
    const side = document.getElementById('adminSidebar');
    if (!btn || !side) return;
    btn.addEventListener('click', () => side.classList.toggle('open'));
    document.addEventListener('click', e => {
        if (!side.contains(e.target) && e.target !== btn) side.classList.remove('open');
    });
    // Stagger animation for sidebar items
    side.querySelectorAll('.sidebar-nav-item').forEach((el, i) => {
        el.style.opacity   = '0';
        el.style.transform = 'translateX(-14px)';
        setTimeout(() => {
            el.style.transition = `opacity .35s ease,transform .35s cubic-bezier(.34,1.56,.64,1)`;
            el.style.opacity    = '1';
            el.style.transform  = 'translateX(0)';
        }, 80 + i * 55);
    });
}

// ============================================================
// CARD CLICK — navega al detalle si no es botón
// ============================================================
function handleCardClick(e, pid) {
    if (e.target.closest('button, .card-fav-btn, .qv-overlay, .qv-btn')) return;
    window.location.href = `/nexusgear/client/producto_detalle.php?id=${pid}`;
}

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    initScrollAnimations();
    initCounters();
    initRipple();
    initNavbar();
    initSidebar();
    if (window.innerWidth > 768) setTimeout(init3DTilt, 300);

    // Quick-view modal buttons
    document.getElementById('qv-cart-btn')?.addEventListener('click', function () {
        const qty = parseInt(document.getElementById('qv-qty')?.value || 1);
        if (this.dataset.productId) addToCart(this.dataset.productId, qty);
    });
    document.getElementById('qv-qty-plus')?.addEventListener('click', () => {
        const q = document.getElementById('qv-qty');
        if (q && +q.value < +q.max) q.value++;
    });
    document.getElementById('qv-qty-minus')?.addEventListener('click', () => {
        const q = document.getElementById('qv-qty');
        if (q && +q.value > 1) q.value--;
    });
    document.getElementById('qv-fav-btn')?.addEventListener('click', function () {
        _toggleFav(this);
    });
});

// Favorito toggle (reutilizable)
function _toggleFav(btn) {
    const pid      = btn.dataset.productId;
    const isActive = btn.classList.contains('active');
    if (!pid) return;
    fetch('/nexusgear/controllers/favorito_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${isActive?'remove':'add'}&id_producto=${pid}&csrf_token=${window.csrfToken||''}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            btn.classList.toggle('active', !isActive);
            const icon = btn.querySelector('.bi');
            if (icon) icon.className = `bi ${!isActive?'bi-heart-fill':'bi-heart'}`;
            showToast(d.message, isActive ? 'info' : 'success');
            if (window.userFavIds) {
                const id = parseInt(pid);
                if (isActive) window.userFavIds = window.userFavIds.filter(x=>x!==id);
                else window.userFavIds.push(id);
            }
            // Sync card fav button if quickview is open
            const cardBtn = document.querySelector(`.card-fav-btn[data-product-id="${pid}"]`);
            if (cardBtn && cardBtn !== btn) {
                cardBtn.classList.toggle('active', !isActive);
                const ci = cardBtn.querySelector('.bi');
                if (ci) ci.className = `bi ${!isActive?'bi-heart-fill':'bi-heart'}`;
            }
        } else {
            showToast(d.message || 'Inicia sesión para usar favoritos.', 'warning');
        }
    });
}
