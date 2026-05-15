<!-- ============================================================
     NexusGear — Quick-View Modal (HTML)
     JS en main.js :: openQuickView()
     ============================================================ -->
<div class="modal fade" id="quickview-modal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="max-height:88vh;">

            <!-- Loading -->
            <div id="qv-loading" style="display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;min-height:280px;">
                <div style="width:40px;height:40px;border-radius:50%;border:3px solid var(--border-subtle);border-top-color:var(--neon-cyan);animation:spinIcon .8s linear infinite;"></div>
                <span style="color:var(--text-muted);font-size:.85rem;">Cargando...</span>
            </div>

            <!-- Error -->
            <div id="qv-error" style="display:none;align-items:center;justify-content:center;flex-direction:column;gap:12px;min-height:280px;">
                <i class="bi bi-exclamation-circle" style="font-size:2.5rem;color:var(--neon-pink);"></i>
                <span style="color:var(--text-muted);">No se pudo cargar el producto.</span>
            </div>

            <!-- Content -->
            <div id="qv-content" style="display:none;flex-direction:column;">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-eye me-2"></i>Vista Rápida
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" style="padding:0;overflow-y:auto;">
                    <div style="display:flex;min-height:380px;" class="flex-lg-row flex-column">

                        <!-- Imagen -->
                        <div style="width:44%;min-width:44%;background:var(--bg-card-2);position:relative;overflow:hidden;min-height:280px;" class="qv-img-col">
                            <img id="qv-img" src="" alt="" style="width:100%;height:100%;object-fit:cover;min-height:280px;transition:transform .4s ease;">
                            <div style="position:absolute;top:10px;left:10px;">
                                <span class="badge-cyan" id="qv-cat" style="font-size:.72rem;"></span>
                            </div>
                        </div>

                        <!-- Detalles -->
                        <div style="flex:1;padding:24px 26px;display:flex;flex-direction:column;gap:13px;overflow-y:auto;">
                            <div class="brand-tag" id="qv-brand"></div>
                            <h3 id="qv-name" style="font-family:'Oxanium',sans-serif;font-size:1.4rem;color:var(--text-primary);margin:0;line-height:1.25;"></h3>

                            <!-- Rating -->
                            <div style="display:flex;align-items:center;gap:7px;">
                                <span id="qv-stars"></span>
                                <span id="qv-rev-count" style="color:var(--text-muted);font-size:.8rem;"></span>
                            </div>

                            <!-- Precio -->
                            <div id="qv-price" style="font-family:'Oxanium',sans-serif;font-size:1.85rem;font-weight:800;color:var(--neon-cyan);"></div>

                            <!-- Stock -->
                            <div id="qv-stock"></div>

                            <!-- Descripción -->
                            <div style="background:rgba(255,255,255,0.025);border:1px solid var(--border-subtle);border-radius:var(--radius-sm);padding:12px;flex:1;">
                                <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;font-weight:600;margin-bottom:5px;">
                                    <i class="bi bi-file-text me-1"></i>Descripción
                                </div>
                                <p id="qv-desc" style="color:var(--text-secondary);font-size:.85rem;line-height:1.65;margin:0;max-height:110px;overflow-y:auto;"></p>
                            </div>

                            <!-- Controles -->
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <!-- Qty -->
                                <div class="qty-group">
                                    <button class="qty-btn" id="qv-qty-minus"><i class="bi bi-dash"></i></button>
                                    <input type="number" class="qty-input" id="qv-qty" value="1" min="1" max="99">
                                    <button class="qty-btn" id="qv-qty-plus"><i class="bi bi-plus"></i></button>
                                </div>
                                <!-- Agregar al carrito -->
                                <button id="qv-cart-btn" class="btn btn-neon" style="flex:1;padding:9px 14px;font-size:.88rem;">
                                    <i class="bi bi-cart3 me-1"></i>Agregar
                                </button>
                                <!-- Favorito — usa card-fav-btn para que _toggleFav funcione -->
                                <button id="qv-fav-btn" class="card-fav-btn" data-product-id="" title="Favoritos" style="position:static;width:38px;height:38px;">
                                    <i class="bi bi-heart" style="font-size:1rem;"></i>
                                </button>
                            </div>

                            <!-- Ver página completa -->
                            <a id="qv-detail-link" href="#" class="btn btn-outline-cyan btn-sm" style="text-align:center;font-size:.82rem;">
                                <i class="bi bi-arrow-right-circle me-1"></i>Ver página completa del producto
                            </a>
                        </div>
                    </div>

                    <!-- Reseñas -->
                    <div style="padding:16px 24px;border-top:1px solid var(--border-subtle);background:rgba(8,8,16,.35);">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <h6 style="font-family:'Oxanium',sans-serif;color:var(--neon-cyan);margin:0;font-size:.9rem;">
                                <i class="bi bi-chat-square-text me-1"></i>Reseñas recientes
                            </h6>
                            <a id="qv-all-reviews" href="#" style="font-size:.78rem;color:var(--text-muted);">
                                Ver todas <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        <div id="qv-reviews"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media(max-width:767px){
    .qv-img-col{width:100%!important;min-width:100%!important;}
}
</style>
