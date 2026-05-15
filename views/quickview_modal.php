<!-- ============================================================
     NexusGear — Quick-View Modal
     Sin CSS que interfiera con Bootstrap position:fixed
     El centrado se logra via modal-dialog-centered de Bootstrap
     ============================================================ -->

<div class="modal fade" id="quickview-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"
         style="max-height:90vh;">
        <div class="modal-content" style="max-height:90vh;overflow:hidden;">

            <!-- Loading -->
            <div id="qv-loading"
                 style="display:flex;align-items:center;justify-content:center;
                        flex-direction:column;gap:14px;min-height:280px;">
                <div style="width:36px;height:36px;border-radius:50%;
                            border:3px solid var(--border-subtle);
                            border-top-color:var(--neon-cyan);
                            animation:spinIcon .8s linear infinite;"></div>
                <span style="color:var(--text-muted);font-size:.84rem;">Cargando...</span>
            </div>

            <!-- Error -->
            <div id="qv-error"
                 style="display:none;align-items:center;justify-content:center;
                        flex-direction:column;gap:12px;min-height:280px;">
                <span style="font-size:2.2rem;">❌</span>
                <span style="color:var(--text-muted);font-size:.85rem;">
                    No se pudo cargar el producto.
                </span>
            </div>

            <!-- Content -->
            <div id="qv-content" style="display:none;flex-direction:column;overflow:hidden;max-height:90vh;">

                <!-- Header -->
                <div class="modal-header" style="flex-shrink:0;padding:14px 20px;">
                    <h5 class="modal-title" style="font-size:.95rem;">👁 Vista Rápida</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <!-- Body scrollable -->
                <div class="modal-body" style="padding:0;overflow-y:auto;flex:1;">

                    <!-- 2 columnas -->
                    <div style="display:flex;min-height:340px;">

                        <!-- Imagen izquierda -->
                        <div style="width:42%;min-width:42%;background:var(--bg-card-2);
                                    overflow:hidden;position:relative;flex-shrink:0;">
                            <img id="qv-img" src="" alt=""
                                 style="width:100%;height:100%;object-fit:cover;
                                        min-height:300px;display:block;
                                        transition:transform .45s ease;"
                                 onmouseover="this.style.transform='scale(1.05)'"
                                 onmouseout="this.style.transform='scale(1)'">
                            <div style="position:absolute;top:10px;left:10px;">
                                <span class="badge-cyan" id="qv-cat" style="font-size:.7rem;"></span>
                            </div>
                        </div>

                        <!-- Detalles derecha -->
                        <div style="flex:1;padding:22px 24px;display:flex;
                                    flex-direction:column;gap:12px;overflow-y:auto;
                                    min-width:0;">

                            <div class="brand-tag" id="qv-brand"></div>

                            <h3 id="qv-name"
                                style="font-family:'Oxanium',sans-serif;font-size:1.3rem;
                                       color:var(--text-primary);margin:0;line-height:1.25;"></h3>

                            <!-- Estrellas + reseñas -->
                            <div id="qv-stars"
                                 style="font-size:.95rem;letter-spacing:1px;
                                        display:flex;align-items:center;gap:6px;"></div>

                            <!-- Precio -->
                            <div id="qv-price"
                                 style="font-family:'Oxanium',sans-serif;font-size:1.8rem;
                                        font-weight:800;color:var(--neon-cyan);"></div>

                            <!-- Stock -->
                            <div id="qv-stock"></div>

                            <!-- Descripción -->
                            <div style="background:rgba(255,255,255,.025);
                                        border:1px solid var(--border-subtle);
                                        border-radius:var(--radius-sm);padding:12px;flex:1;">
                                <div style="font-size:.7rem;color:var(--text-muted);
                                            text-transform:uppercase;letter-spacing:.8px;
                                            font-weight:600;margin-bottom:5px;">
                                    📋 Descripción
                                </div>
                                <p id="qv-desc"
                                   style="color:var(--text-secondary);font-size:.83rem;
                                          line-height:1.65;margin:0;
                                          max-height:100px;overflow-y:auto;"></p>
                            </div>

                            <!-- Qty + Cart + Fav -->
                            <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
                                <div class="qty-group">
                                    <button class="qty-btn" id="qv-qty-minus">−</button>
                                    <input type="number" class="qty-input" id="qv-qty"
                                           value="1" min="1" max="99">
                                    <button class="qty-btn" id="qv-qty-plus">+</button>
                                </div>
                                <button id="qv-cart-btn" class="btn btn-neon"
                                        style="flex:1;font-size:.85rem;padding:9px 14px;">
                                    🛒 Agregar al Carrito
                                </button>
                                <button id="qv-fav-btn" class="card-fav-btn"
                                        data-product-id=""
                                        style="position:static;width:38px;height:38px;"
                                        title="Favoritos">
                                    <span class="fav-emoji" style="font-size:1.05rem;">🤍</span>
                                </button>
                            </div>

                            <!-- Link página completa -->
                            <a id="qv-detail-link" href="#"
                               class="btn btn-outline-cyan btn-sm"
                               style="text-align:center;font-size:.8rem;">
                                Ver página completa →
                            </a>
                        </div>
                    </div>

                    <!-- Mini reseñas -->
                    <div style="padding:14px 22px;border-top:1px solid var(--border-subtle);
                                background:rgba(8,8,16,.3);">
                        <div style="display:flex;align-items:center;
                                    justify-content:space-between;margin-bottom:10px;">
                            <span style="font-family:'Oxanium',sans-serif;
                                         color:var(--neon-cyan);font-size:.88rem;font-weight:700;">
                                💬 Reseñas recientes
                            </span>
                            <a id="qv-all-reviews" href="#"
                               style="font-size:.76rem;color:var(--text-muted);">
                                Ver todas →
                            </a>
                        </div>
                        <div id="qv-reviews"></div>
                    </div>

                </div><!-- /.modal-body -->
            </div><!-- /#qv-content -->
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /#quickview-modal -->

<style>
/* Responsive para móvil */
@media (max-width: 767px) {
    #quickview-modal .modal-dialog { margin: 0.5rem; }
    #quickview-modal [style*="width:42%"] {
        width: 100% !important;
        min-width: 100% !important;
    }
    #quickview-modal > .modal-dialog > .modal-content > #qv-content > .modal-body > div:first-child {
        flex-direction: column !important;
    }
}
</style>
