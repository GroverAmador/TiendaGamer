-- ============================================================
-- NexusGear — Tabla: Alerta_Stock
-- Permite a los usuarios suscribirse para recibir notificación
-- cuando un producto agotado vuelva a tener stock disponible.
--
-- USO EN EL SISTEMA:
--   • Página de detalle de producto (stock=0): botón "Notificarme"
--   • Panel Admin → Productos: columna "En espera" por producto
--   • Panel Admin → Al actualizar stock de 0 a >0: se marca notificado
--   • Perfil del cliente: sección "Mis alertas de stock"
-- ============================================================

USE nexusgear_db;

CREATE TABLE IF NOT EXISTS Alerta_Stock (
    id_alerta          INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario         INT NOT NULL,
    id_producto        INT NOT NULL,
    activa             TINYINT(1)   DEFAULT 1,
    notificada         TINYINT(1)   DEFAULT 0,
    fecha_solicitud    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    fecha_notificacion DATETIME     DEFAULT NULL,

    FOREIGN KEY (id_usuario)  REFERENCES Usuario(id_usuario)  ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto) ON DELETE CASCADE,

    -- Un usuario solo puede tener UNA alerta activa por producto
    UNIQUE KEY unique_alerta_usuario_producto (id_usuario, id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Alertas de reposición de stock para usuarios';

-- ============================================================
-- Índices para consultas frecuentes
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_alerta_producto ON Alerta_Stock (id_producto, activa);
CREATE INDEX IF NOT EXISTS idx_alerta_usuario  ON Alerta_Stock (id_usuario, activa);

-- ============================================================
-- QUERY DE EJEMPLO: cuántos usuarios esperan cada producto
-- SELECT p.nombre, COUNT(a.id_alerta) AS usuarios_esperando
-- FROM Alerta_Stock a
-- JOIN Producto p ON a.id_producto = p.id_producto
-- WHERE a.activa = 1 AND a.notificada = 0
-- GROUP BY a.id_producto
-- ORDER BY usuarios_esperando DESC;
-- ============================================================
CREATE TABLE IF NOT EXISTS Producto_Imagen (
    id_imagen   INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    url         VARCHAR(500) NOT NULL,
    orden       TINYINT DEFAULT 0,
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE Usuario ADD COLUMN IF NOT EXISTS bloqueado TINYINT(1) NOT NULL DEFAULT 0;