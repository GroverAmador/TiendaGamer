// ============================================================
// NexusGear - AJAX Product Search with Debounce & Skeletons
// ============================================================

(function () {
    'use strict';

    let debounceTimer = null;
    const DEBOUNCE_MS = 300;

    // ---- Skeleton loader HTML ----
    function skeletonCard() {
        return `
        <div class="col">
            <div class="skeleton-card">
                <div class="skeleton-img"></div>
                <div class="skeleton-text skeleton" style="width:40%;margin-top:16px;"></div>
                <div class="skeleton-text skeleton" style="width:80%;"></div>
                <div class="skeleton-text skeleton" style="width:60%;"></div>
                <div class="skeleton-btn skeleton"></div>
            </div>
        </div>`;
    }

    function showSkeletons(count = 6) {
        const grid = document.getElementById('product-grid');
        if (!grid) return;
        grid.innerHTML = Array(count).fill(skeletonCard()).join('');
    }

    // ---- Collect all current filter values ----
    function getFilterParams() {
        const params = new URLSearchParams();

        const q = document.getElementById('search-input')?.value.trim();
        if (q) params.set('q', q);

        // Category checkboxes
        document.querySelectorAll('input[name="id_categoria[]"]:checked').forEach(cb => {
            params.append('id_categoria[]', cb.value);
        });

        // Brand checkboxes
        document.querySelectorAll('input[name="marca[]"]:checked').forEach(cb => {
            params.append('marca[]', cb.value);
        });

        const priceMin = document.getElementById('precio-min')?.value;
        const priceMax = document.getElementById('precio-max')?.value;
        if (priceMin) params.set('precio_min', priceMin);
        if (priceMax) params.set('precio_max', priceMax);

        const rating = document.getElementById('rating-filter')?.value;
        if (rating) params.set('rating', rating);

        const sort = document.getElementById('sort-select')?.value;
        if (sort) params.set('sort', sort);

        const page = document.getElementById('current-page')?.value || 1;
        params.set('page', page);

        params.set('action', 'search');
        return params;
    }

    // ---- Execute search request ----
    function doSearch() {
        const params = getFilterParams();
        showSkeletons(6);

        fetch('/nexusgear/controllers/producto_controller.php?' + params.toString())
            .then(res => res.json())
            .then(data => {
                const grid = document.getElementById('product-grid');
                const countEl = document.getElementById('results-count');

                if (grid) {
                    grid.innerHTML = data.html || '<div class="col-12 text-center py-5"><p style="color:var(--text-muted);">No se encontraron productos.</p></div>';
                    // Re-init scroll animations on newly inserted cards
                    grid.querySelectorAll('.animate-on-scroll').forEach(el => {
                        setTimeout(() => el.classList.add('visible'), 50);
                    });
                }

                if (countEl) {
                    countEl.textContent = data.total || 0;
                }

                // Update pagination
                const paginationEl = document.getElementById('pagination-container');
                if (paginationEl) paginationEl.innerHTML = data.pagination || '';

                // Re-bind add-to-cart and favorite buttons for new cards
                bindNewCardButtons(grid);
            })
            .catch(() => {
                const grid = document.getElementById('product-grid');
                if (grid) {
                    grid.innerHTML = '<div class="col-12 text-center py-5"><p style="color:var(--neon-pink);">Error al cargar productos. Intenta de nuevo.</p></div>';
                }
            });
    }

    // ---- Re-bind buttons on dynamically loaded cards ----
    function bindNewCardButtons(container) {
        if (!container) return;

        container.querySelectorAll('.btn-add-to-cart').forEach(btn => {
            btn.addEventListener('click', function () {
                const productId = this.dataset.productId;
                if (productId) addToCart(productId, 1);
            });
        });

        container.querySelectorAll('.btn-fav').forEach(btn => {
            btn.addEventListener('click', function () {
                const pid      = this.dataset.productId;
                const isActive = this.classList.contains('active');
                const self     = this;

                fetch('/nexusgear/controllers/favorito_controller.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${isActive ? 'remove' : 'add'}&id_producto=${pid}&csrf_token=${window.csrfToken || ''}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        self.classList.toggle('active');
                        showToast(data.message, isActive ? 'info' : 'success');
                    } else {
                        showToast(data.message || 'Inicia sesión para usar favoritos.', 'warning');
                    }
                });
            });
        });
    }

    // ---- Debounced trigger ----
    function triggerSearch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(doSearch, DEBOUNCE_MS);
    }

    // ---- Init ----
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('search-input');
        if (!searchInput) return; // Not on a search page

        // Search input keyup
        searchInput.addEventListener('keyup', triggerSearch);

        // Filter checkboxes
        document.querySelectorAll('input[name="id_categoria[]"], input[name="marca[]"]')
            .forEach(cb => cb.addEventListener('change', triggerSearch));

        // Price inputs
        ['precio-min', 'precio-max'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', triggerSearch);
        });

        // Rating filter
        const ratingFilter = document.getElementById('rating-filter');
        if (ratingFilter) ratingFilter.addEventListener('change', triggerSearch);

        // Sort dropdown
        const sortSelect = document.getElementById('sort-select');
        if (sortSelect) sortSelect.addEventListener('change', triggerSearch);

        // Clear filters button
        const clearBtn = document.getElementById('btn-clear-filters');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (searchInput) searchInput.value = '';
                document.querySelectorAll('input[name="id_categoria[]"], input[name="marca[]"]')
                    .forEach(cb => cb.checked = false);
                const minEl = document.getElementById('precio-min');
                const maxEl = document.getElementById('precio-max');
                if (minEl) minEl.value = '';
                if (maxEl) maxEl.value = '';
                if (ratingFilter) ratingFilter.value = '';
                if (sortSelect) sortSelect.value = 'default';
                doSearch();
            });
        }

        // View toggle (grid / list)
        document.querySelectorAll('.view-toggle-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const grid = document.getElementById('product-grid');
                if (grid) {
                    if (this.dataset.view === 'list') grid.classList.add('list-view');
                    else grid.classList.remove('list-view');
                }
            });
        });

        // Pagination links (event delegation)
        document.addEventListener('click', function (e) {
            const pageBtn = e.target.closest('.page-link[data-page]');
            if (pageBtn) {
                e.preventDefault();
                const pageInput = document.getElementById('current-page');
                if (pageInput) pageInput.value = pageBtn.dataset.page;
                doSearch();
            }
        });
    });
}());
