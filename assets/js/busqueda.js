// ============================================================
// NexusGear — busqueda.js v3
// Carga inicial + filtros AJAX con debounce
// doSearch expuesto globalmente para llamadas externas
// ============================================================

(function () {
    'use strict';

    var debounceTimer = null;
    var DEBOUNCE_MS   = 350;
    var initialized   = false;

    // ---- HTML skeleton ----
    function skeletonCard() {
        return '<div class="col"><div class="skeleton-card">'
            + '<div class="skeleton-img"></div>'
            + '<div class="skeleton-line skeleton" style="width:40%;margin-top:14px;"></div>'
            + '<div class="skeleton-line skeleton" style="width:75%;"></div>'
            + '<div class="skeleton-line skeleton" style="width:55%;"></div>'
            + '<div class="skeleton-btn skeleton"></div>'
            + '</div></div>';
    }

    function showSkeletons(n) {
        var grid = document.getElementById('product-grid');
        if (!grid) return;
        var html = '';
        for (var i = 0; i < n; i++) html += skeletonCard();
        grid.innerHTML = html;
    }

    // ---- Recolectar parámetros de los filtros ----
    function getParams() {
        var params = new URLSearchParams();
        params.set('action', 'search');

        var q = (document.getElementById('search-input') || {}).value || '';
        if (q.trim()) params.set('q', q.trim());

        document.querySelectorAll('input[name="id_categoria[]"]:checked').forEach(function(cb) {
            params.append('id_categoria[]', cb.value);
        });
        document.querySelectorAll('input[name="marca[]"]:checked').forEach(function(cb) {
            params.append('marca[]', cb.value);
        });

        var pMin = (document.getElementById('precio-min') || {}).value;
        var pMax = (document.getElementById('precio-max') || {}).value;
        if (pMin) params.set('precio_min', pMin);
        if (pMax) params.set('precio_max', pMax);

        var rating = (document.getElementById('rating-filter') || {}).value;
        if (rating) params.set('rating', rating);

        var sort = (document.getElementById('sort-select') || {}).value || 'default';
        params.set('sort', sort);

        var page = (document.getElementById('current-page') || {}).value || 1;
        params.set('page', page);

        return params;
    }

    // ---- Ejecutar búsqueda ----
    function doSearch() {
        var grid       = document.getElementById('product-grid');
        var countEl    = document.getElementById('results-count');
        var paginEl    = document.getElementById('pagination-container');

        if (!grid) return; // no estamos en la página de productos

        showSkeletons(6);

        var params = getParams();

        fetch('/nexusgear/controllers/producto_controller.php?' + params.toString())
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(data) {
                if (grid) {
                    grid.innerHTML = data.html || '<div class="col-12 text-center py-5">'
                        + '<p style="color:var(--text-muted);">No se encontraron productos.</p>'
                        + '</div>';
                    // Activar animaciones en las cards nuevas
                    grid.querySelectorAll('.animate-on-scroll').forEach(function(el) {
                        setTimeout(function() { el.classList.add('visible'); }, 50);
                    });
                }
                if (countEl) countEl.textContent = data.total || 0;
                if (paginEl) paginEl.innerHTML   = data.pagination || '';
            })
            .catch(function(err) {
                if (grid) {
                    grid.innerHTML = '<div class="col-12 text-center py-4">'
                        + '<p style="color:var(--neon-pink);">Error al cargar productos. Recarga la página.</p>'
                        + '</div>';
                }
                console.error('[busqueda] Error:', err);
            });
    }

    // ---- Trigger con debounce ----
    function triggerSearch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(doSearch, DEBOUNCE_MS);
    }

    // ---- Exponer globalmente ----
    window.doSearch = doSearch;

    // ---- Init ----
    document.addEventListener('DOMContentLoaded', function () {
        if (initialized) return;
        initialized = true;

        var searchInput  = document.getElementById('search-input');
        var sortSelect   = document.getElementById('sort-select');
        var ratingFilter = document.getElementById('rating-filter');
        var clearBtn     = document.getElementById('btn-clear-filters');
        var pageInput    = document.getElementById('current-page');

        // Solo inicializar si estamos en una página con buscador
        if (!searchInput) return;

        // Busqueda por texto
        searchInput.addEventListener('keyup', triggerSearch);
        searchInput.addEventListener('input',  triggerSearch);

        // Filtros de checkbox
        document.querySelectorAll('input[name="id_categoria[]"], input[name="marca[]"]')
            .forEach(function(cb) { cb.addEventListener('change', triggerSearch); });

        // Precio
        ['precio-min', 'precio-max'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', triggerSearch);
        });

        // Rating
        if (ratingFilter) ratingFilter.addEventListener('change', triggerSearch);

        // Sort
        if (sortSelect) sortSelect.addEventListener('change', triggerSearch);

        // Limpiar filtros
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (searchInput) searchInput.value = '';
                document.querySelectorAll('input[name="id_categoria[]"], input[name="marca[]"]')
                    .forEach(function(cb) { cb.checked = false; });
                var minEl = document.getElementById('precio-min');
                var maxEl = document.getElementById('precio-max');
                if (minEl) minEl.value = '';
                if (maxEl) maxEl.value = '';
                if (ratingFilter) ratingFilter.value = '';
                if (sortSelect) sortSelect.value = 'default';
                if (pageInput) pageInput.value = 1;
                doSearch();
            });
        }

        // Vista grid/lista
        document.querySelectorAll('.view-toggle-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-toggle-btn').forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                var grid2 = document.getElementById('product-grid');
                if (grid2) {
                    if (this.dataset.view === 'list') grid2.classList.add('list-view');
                    else grid2.classList.remove('list-view');
                }
            });
        });

        // Paginación (event delegation)
        document.addEventListener('click', function(e) {
            var pageBtn = e.target.closest('.page-link[data-page]');
            if (!pageBtn) return;
            e.preventDefault();
            if (pageInput) pageInput.value = pageBtn.dataset.page;
            doSearch();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Carga inicial — pequeño delay para que Bootstrap termine de renderizar
        setTimeout(doSearch, 80);
    });

}());
