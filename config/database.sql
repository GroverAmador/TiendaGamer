-- ============================================================
-- NexusGear Database Schema & Sample Data
-- Run this via phpMyAdmin or MySQL CLI AFTER running setup.php
-- For initial setup: visit http://localhost/nexusgear/config/setup.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS nexusgear_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nexusgear_db;

-- ============================================================
-- TABLE: Usuario
-- Stores all registered users (admins and clients)
-- ============================================================
CREATE TABLE IF NOT EXISTS Usuario (
    id_usuario     INT AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL,
    correo         VARCHAR(150) UNIQUE NOT NULL,
    contrasena     VARCHAR(255) NOT NULL,
    rol            ENUM('admin','cliente') DEFAULT 'cliente',
    codigo_2fa     VARCHAR(10),
    expira_2fa     DATETIME,
    foto_perfil    VARCHAR(255) DEFAULT 'default.png',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: Categoria
-- Product categories with emoji icons
-- ============================================================
CREATE TABLE IF NOT EXISTS Categoria (
    id_categoria     INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL,
    descripcion      TEXT,
    icono            VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: Producto
-- All gamer equipment products
-- ============================================================
CREATE TABLE IF NOT EXISTS Producto (
    id_producto    INT AUTO_INCREMENT PRIMARY KEY,
    id_categoria   INT,
    nombre         VARCHAR(150) NOT NULL,
    marca          VARCHAR(100),
    descripcion    TEXT,
    precio         DECIMAL(10,2) NOT NULL,
    stock          INT DEFAULT 0,
    imagen         VARCHAR(255),
    destacado      TINYINT(1) DEFAULT 0,
    estado         TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: Venta
-- Sales / orders placed by users
-- ============================================================
CREATE TABLE IF NOT EXISTS Venta (
    id_venta           INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario         INT,
    fecha              DATETIME DEFAULT CURRENT_TIMESTAMP,
    total              DECIMAL(10,2),
    estado_venta       ENUM('pendiente','pagado','entregado') DEFAULT 'pendiente',
    cupon_aplicado     VARCHAR(50),
    descuento_aplicado DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: Detalle_Venta
-- Line items for each order
-- ============================================================
CREATE TABLE IF NOT EXISTS Detalle_Venta (
    id_detalle      INT AUTO_INCREMENT PRIMARY KEY,
    id_venta        INT,
    id_producto     INT,
    cantidad        INT,
    precio_unitario DECIMAL(10,2),
    subtotal        DECIMAL(10,2),
    FOREIGN KEY (id_venta)    REFERENCES Venta(id_venta),
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: Favorito
-- User wishlist / favorites
-- ============================================================
CREATE TABLE IF NOT EXISTS Favorito (
    id_favorito INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario  INT,
    id_producto INT,
    fecha       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario)  REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto),
    UNIQUE KEY unique_favorito (id_usuario, id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: Resena
-- Product reviews from verified buyers
-- ============================================================
CREATE TABLE IF NOT EXISTS Resena (
    id_resena   INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario  INT,
    id_producto INT,
    puntuacion  TINYINT CHECK (puntuacion BETWEEN 1 AND 5),
    comentario  TEXT,
    fecha       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario)  REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: Cupon
-- Discount coupons
-- ============================================================
CREATE TABLE IF NOT EXISTS Cupon (
    id_cupon         INT AUTO_INCREMENT PRIMARY KEY,
    codigo           VARCHAR(50) UNIQUE NOT NULL,
    descuento        DECIMAL(5,2) NOT NULL,
    activo           TINYINT(1) DEFAULT 1,
    fecha_expiracion DATE,
    usos_maximos     INT DEFAULT 100,
    usos_actuales    INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SAMPLE DATA: Categories
-- ============================================================
INSERT INTO Categoria (nombre_categoria, descripcion, icono) VALUES
('Laptops Gamer',   'Laptops de alto rendimiento para gaming',                '💻'),
('Monitores',       'Monitores con alta tasa de refresco y resolución 4K',    '🖥️'),
('Mouse',           'Ratones gaming con alta precisión y DPI ajustable',      '🖱️'),
('Teclados',        'Teclados mecánicos con switches personalizables',        '⌨️'),
('Consolas',        'Consolas de última generación y accesorios',             '🎮');

-- ============================================================
-- SAMPLE DATA: Products (12+ products)
-- NOTE: User passwords are inserted via setup.php (bcrypt hashed)
-- ============================================================
INSERT INTO Producto (id_categoria, nombre, marca, descripcion, precio, stock, imagen, destacado, estado) VALUES
(1, 'ASUS ROG Zephyrus G16',      'ASUS',     'Laptop gaming con RTX 4080, i9-13900H, 32GB RAM, pantalla QHD 240Hz. Diseño ultradelgado con rendimiento brutal.', 2499.99, 15, 'https://placehold.co/400x300/13131f/00f5ff?text=ROG+Zephyrus', 1, 1),
(1, 'Razer Blade 16',             'Razer',    'Laptop premium con RTX 4090, pantalla Mini-LED 4K 240Hz, 32GB DDR5. La cima del gaming portátil.', 3299.99,  8, 'https://placehold.co/400x300/13131f/00f5ff?text=Razer+Blade', 1, 1),
(1, 'MSI Titan GT77 HX',          'MSI',      'Laptop de escritorio portátil con Intel Core i9 de 13a gen, RTX 4090 y 64GB RAM. Máxima potencia.', 2899.99,  5, 'https://placehold.co/400x300/13131f/00f5ff?text=MSI+Titan', 0, 1),
(2, 'Samsung Odyssey Neo G9',     'Samsung',  'Monitor gaming 49" DQHD, 240Hz, 1ms, HDR2000, Mini LED. La experiencia inmersiva definitiva.', 1299.99, 10, 'https://placehold.co/400x300/13131f/00f5ff?text=Odyssey+G9', 1, 1),
(2, 'ASUS ROG Swift PG32UQX',    'ASUS',     'Monitor 4K IPS 144Hz con G-Sync Ultimate, HDR1400, Mini-LED. Para el jugador exigente.', 1999.99,  3, 'https://placehold.co/400x300/13131f/00f5ff?text=ROG+Swift', 0, 1),
(2, 'LG UltraGear 27GP950-B',    'LG',       'Monitor 4K Nano IPS 144Hz, HDMI 2.1, compatible con PS5 y Xbox Series X. Versatilidad total.', 699.99,  20, 'https://placehold.co/400x300/13131f/00f5ff?text=LG+UltraGear', 0, 1),
(3, 'Logitech G Pro X Superlight 2', 'Logitech', 'Mouse inalámbrico ultraligero 60g, sensor HERO 2 25K DPI, batería 95h. El mouse de los pros.', 149.99, 30, 'https://placehold.co/400x300/13131f/00f5ff?text=G+Pro+X2', 1, 1),
(3, 'Razer DeathAdder V3 HyperSpeed', 'Razer','Mouse gaming inalámbrico asimétrico, sensor Focus Pro 30K, 90h batería. Ergonomía perfecta.', 99.99,  25, 'https://placehold.co/400x300/13131f/00f5ff?text=DeathAdder+V3', 0, 1),
(3, 'SteelSeries Aerox 9 Wireless', 'SteelSeries','Mouse MMO/Battle Royale, 18 botones programables, sensor TrueMove Air 18K DPI.', 129.99,  2, 'https://placehold.co/400x300/13131f/00f5ff?text=Aerox+9', 0, 1),
(4, 'HyperX Alloy Origins 65',   'HyperX',  'Teclado mecánico compacto 65%, switches HyperX Red, RGB por tecla, construcción aluminio.', 89.99,  40, 'https://placehold.co/400x300/13131f/00f5ff?text=HyperX+Origins', 1, 1),
(4, 'Razer BlackWidow V4 Pro',   'Razer',   'Teclado mecánico inalámbrico, switches Razer Yellow, Chroma RGB, reposamuñecas magnético.', 229.99,  0, 'https://placehold.co/400x300/13131f/00f5ff?text=BlackWidow+V4', 0, 1),
(5, 'PlayStation 5 Slim Digital','Sony',    'Consola PS5 Slim edición digital, SSD ultrarrápido, DualSense incluido. Sin lectora de disco.', 449.99,  4, 'https://placehold.co/400x300/13131f/00f5ff?text=PS5+Slim', 0, 1),
(5, 'Xbox Series X 2TB',         'Microsoft','Xbox Series X con 2TB de almacenamiento, controlador inalámbrico, compatible con Game Pass.', 599.99,  7, 'https://placehold.co/400x300/13131f/00f5ff?text=Xbox+Series+X', 0, 1),
(4, 'Corsair K100 RGB Optical',  'Corsair', 'Teclado mecánico premium con switches ópticos, rueda iCUE, 44 zonas RGB. El teclado definitivo.', 199.99, 18, 'https://placehold.co/400x300/13131f/00f5ff?text=K100+RGB', 0, 1);

-- ============================================================
-- SAMPLE DATA: Coupons
-- ============================================================
INSERT INTO Cupon (codigo, descuento, activo, fecha_expiracion, usos_maximos, usos_actuales) VALUES
('NEXUS10', 10.00, 1, '2026-12-31', 100, 5),
('GAMER20', 20.00, 1, '2026-06-30', 50,  2);

-- ============================================================
-- NOTE: User data with hashed passwords is inserted by setup.php
-- Run http://localhost/nexusgear/config/setup.php to complete setup
-- Passwords:
--   admin@nexusgear.com  / Admin123!
--   cliente1@test.com ... cliente5@test.com / Cliente123!
-- ============================================================

-- ============================================================
-- SAMPLE DATA: Reviews (inserted after setup.php creates users)
-- These are inserted by setup.php
-- ============================================================

-- ============================================================
-- SAMPLE DATA: Sales (inserted by setup.php)
-- ============================================================
