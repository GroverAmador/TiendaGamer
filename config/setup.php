<?php
// ============================================================
// NexusGear - Setup v3 (categorías sin duplicados, UNIQUE constraints)
// ============================================================
$conn = @mysqli_connect('localhost', 'root', '', '');
if (!$conn) die('<div style="font-family:sans-serif;color:red;padding:40px">Error MySQL: ' . mysqli_connect_error() . '</div>');

$log = []; $errors = [];

function run(mysqli $c, string $sql, string $label): void {
    global $log, $errors;
    if (mysqli_query($c, $sql)) $log[] = "✓ $label";
    else $errors[] = "✗ $label: " . mysqli_error($c);
}

// 1. Crear base de datos
run($conn, "CREATE DATABASE IF NOT EXISTS nexusgear_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", "Crear nexusgear_db");
mysqli_select_db($conn, 'nexusgear_db');

// 2. Tablas (con ON DELETE correctos)
run($conn, "CREATE TABLE IF NOT EXISTS Usuario (
    id_usuario     INT AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL,
    correo         VARCHAR(150) UNIQUE NOT NULL,
    contrasena     VARCHAR(255) NOT NULL,
    rol            ENUM('admin','cliente') DEFAULT 'cliente',
    metodo_2fa     ENUM('email','totp') DEFAULT 'email',
    totp_secret    VARCHAR(32)  DEFAULT NULL,
    codigo_2fa     VARCHAR(10)  DEFAULT NULL,
    expira_2fa     DATETIME     DEFAULT NULL,
    foto_perfil    VARCHAR(255) DEFAULT 'default.png',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tabla Usuario");

@mysqli_query($conn, "ALTER TABLE Usuario ADD COLUMN IF NOT EXISTS metodo_2fa ENUM('email','totp') DEFAULT 'email' AFTER rol");
@mysqli_query($conn, "ALTER TABLE Usuario ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(32) DEFAULT NULL AFTER metodo_2fa");

// Categoria con UNIQUE en nombre para evitar duplicados
run($conn, "CREATE TABLE IF NOT EXISTS Categoria (
    id_categoria     INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL,
    descripcion      TEXT,
    icono            VARCHAR(50),
    UNIQUE KEY uq_nombre_cat (nombre_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tabla Categoria");

// Agregar UNIQUE si ya existe la tabla sin él
@mysqli_query($conn, "ALTER TABLE Categoria ADD UNIQUE KEY uq_nombre_cat (nombre_categoria)");

run($conn, "CREATE TABLE IF NOT EXISTS Producto (
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
    FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tabla Producto");

run($conn, "CREATE TABLE IF NOT EXISTS Venta (
    id_venta           INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario         INT NULL,
    fecha              DATETIME DEFAULT CURRENT_TIMESTAMP,
    total              DECIMAL(10,2),
    estado_venta       ENUM('pendiente','pagado','entregado') DEFAULT 'pendiente',
    cupon_aplicado     VARCHAR(50),
    descuento_aplicado DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tabla Venta");

run($conn, "CREATE TABLE IF NOT EXISTS Detalle_Venta (
    id_detalle      INT AUTO_INCREMENT PRIMARY KEY,
    id_venta        INT,
    id_producto     INT,
    cantidad        INT,
    precio_unitario DECIMAL(10,2),
    subtotal        DECIMAL(10,2),
    FOREIGN KEY (id_venta)    REFERENCES Venta(id_venta) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tabla Detalle_Venta");

run($conn, "CREATE TABLE IF NOT EXISTS Favorito (
    id_favorito INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario  INT,
    id_producto INT,
    fecha       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario)  REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto) ON DELETE CASCADE,
    UNIQUE KEY unique_favorito (id_usuario, id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tabla Favorito");

run($conn, "CREATE TABLE IF NOT EXISTS Resena (
    id_resena   INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario  INT,
    id_producto INT,
    puntuacion  TINYINT CHECK (puntuacion BETWEEN 1 AND 5),
    comentario  TEXT,
    fecha       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario)  REFERENCES Usuario(id_usuario) ON DELETE SET NULL,
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tabla Resena");

run($conn, "CREATE TABLE IF NOT EXISTS Cupon (
    id_cupon         INT AUTO_INCREMENT PRIMARY KEY,
    codigo           VARCHAR(50) UNIQUE NOT NULL,
    descuento        DECIMAL(5,2) NOT NULL,
    activo           TINYINT(1) DEFAULT 1,
    fecha_expiracion DATE,
    usos_maximos     INT DEFAULT 100,
    usos_actuales    INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tabla Cupon");

// 3. Limpiar duplicados de categorías primero
$r = mysqli_query($conn, "SELECT COUNT(*) FROM Categoria"); $c = mysqli_fetch_row($r);
if ($c[0] > 5) {
    // Hay más de 5 categorías = hay duplicados, limpiar
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");
    mysqli_query($conn, "TRUNCATE TABLE Categoria");
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
    $log[] = "✓ Categorías duplicadas limpiadas";
}

// Insertar categorías con INSERT IGNORE (evita duplicados por UNIQUE KEY)
$cats = [
    ['Laptops Gamer',   'Laptops de alto rendimiento para gaming',                '💻'],
    ['Monitores',       'Monitores con alta tasa de refresco y resolución 4K',    '🖥️'],
    ['Mouse',           'Ratones gaming con alta precisión y DPI ajustable',      '🖱️'],
    ['Teclados',        'Teclados mecánicos con switches personalizables',        '⌨️'],
    ['Consolas',        'Consolas de última generación y accesorios',             '🎮'],
];
$stmt = mysqli_prepare($conn, "INSERT IGNORE INTO Categoria (nombre_categoria,descripcion,icono) VALUES (?,?,?)");
foreach ($cats as $cat) {
    mysqli_stmt_bind_param($stmt, 'sss', $cat[0], $cat[1], $cat[2]);
    mysqli_stmt_execute($stmt);
}
mysqli_stmt_close($stmt);
$log[] = "✓ Categorías (INSERT IGNORE — sin duplicados)";

// 4. Productos (solo si no hay)
$r = mysqli_query($conn, "SELECT COUNT(*) FROM Producto"); $c = mysqli_fetch_row($r);
if ($c[0] == 0) {
    // Obtener IDs de categorías
    $catIds = [];
    $cr = mysqli_query($conn, "SELECT id_categoria, nombre_categoria FROM Categoria ORDER BY id_categoria");
    while ($row = mysqli_fetch_assoc($cr)) $catIds[$row['nombre_categoria']] = $row['id_categoria'];

    $lap = $catIds['Laptops Gamer'] ?? 1;
    $mon = $catIds['Monitores']     ?? 2;
    $mou = $catIds['Mouse']         ?? 3;
    $tec = $catIds['Teclados']      ?? 4;
    $con = $catIds['Consolas']      ?? 5;

    $prods = [
        [$lap,'ASUS ROG Zephyrus G16','ASUS','Laptop gaming con RTX 4080, i9-13900H, 32GB RAM, pantalla QHD 240Hz. Diseño ultradelgado con rendimiento brutal. Perfecta para gaming profesional y producción de contenido.',2499.99,15,'https://placehold.co/400x300/11111e/00d8f0?text=ROG+Zephyrus',1],
        [$lap,'Razer Blade 16','Razer','Laptop premium con RTX 4090, pantalla Mini-LED 4K 240Hz, 32GB DDR5. La cima absoluta del gaming portátil con chasis de aluminio CNC y teclado RGB per-key.',3299.99,8,'https://placehold.co/400x300/11111e/00d8f0?text=Razer+Blade',1],
        [$lap,'MSI Titan GT77 HX','MSI','Laptop de escritorio portátil con Intel Core i9 de 13a gen, RTX 4090 y 64GB RAM. Máxima potencia sin compromisos.',2899.99,5,'https://placehold.co/400x300/11111e/00d8f0?text=MSI+Titan',0],
        [$mon,'Samsung Odyssey Neo G9','Samsung','Monitor gaming 49 pulgadas DQHD Dual, 240Hz, 1ms GtG, HDR2000, Mini LED con 2048 zonas de iluminación local.',1299.99,10,'https://placehold.co/400x300/11111e/00d8f0?text=Odyssey+G9',1],
        [$mon,'ASUS ROG Swift PG32UQX','ASUS','Monitor 4K 32 pulgadas IPS 144Hz con G-Sync Ultimate y HDR1400. Mini-LED con 1152 zonas locales.',1999.99,3,'https://placehold.co/400x300/11111e/00d8f0?text=ROG+Swift',0],
        [$mon,'LG UltraGear 27GP950-B','LG','Monitor 4K 27 pulgadas Nano IPS 144Hz con HDMI 2.1 para PS5 y Xbox Series X. Compatible con NVIDIA G-Sync y AMD FreeSync.',699.99,20,'https://placehold.co/400x300/11111e/00d8f0?text=LG+UltraGear',0],
        [$mou,'Logitech G Pro X Superlight 2','Logitech','Mouse inalámbrico ultraligero 60g con sensor HERO 2 de 25,600 DPI. Hasta 95 horas de batería.',149.99,30,'https://placehold.co/400x300/11111e/00d8f0?text=G+Pro+X2',1],
        [$mou,'Razer DeathAdder V3 HyperSpeed','Razer','Mouse gaming inalámbrico ergonómico con sensor Focus Pro 30K DPI. Batería de 90 horas para sesiones largas.',99.99,25,'https://placehold.co/400x300/11111e/00d8f0?text=DeathAdder+V3',0],
        [$mou,'SteelSeries Aerox 9 Wireless','SteelSeries','Mouse MMO inalámbrico con 18 botones programables y sensor TrueMove Air de 18,000 DPI.',129.99,2,'https://placehold.co/400x300/11111e/00d8f0?text=Aerox+9',0],
        [$tec,'HyperX Alloy Origins 65','HyperX','Teclado mecánico compacto 65% con switches HyperX Red lineales, RGB por tecla y construcción en aluminio aeronáutico.',89.99,40,'https://placehold.co/400x300/11111e/00d8f0?text=HyperX+Origins',1],
        [$tec,'Razer BlackWidow V4 Pro','Razer','Teclado mecánico inalámbrico full-size con switches Razer Yellow y Chroma RGB. La experiencia wireless definitiva.',229.99,0,'https://placehold.co/400x300/11111e/00d8f0?text=BlackWidow+V4',0],
        [$con,'PlayStation 5 Slim Digital','Sony','Consola PS5 Slim edición digital. SSD NVMe de 825GB, DualSense con retroalimentación háptica. 4K a 120fps.',449.99,4,'https://placehold.co/400x300/11111e/00d8f0?text=PS5+Slim',0],
        [$con,'Xbox Series X 2TB','Microsoft','Xbox Series X con 2TB de almacenamiento SSD. Controlador inalámbrico y Game Pass Ultimate. 4K a 120fps.',599.99,7,'https://placehold.co/400x300/11111e/00d8f0?text=Xbox+SeriesX',0],
        [$tec,'Corsair K100 RGB Optical','Corsair','Teclado mecánico premium con switches ópticos OPX, rueda iCUE y 44 zonas RGB de alta resolución.',199.99,18,'https://placehold.co/400x300/11111e/00d8f0?text=K100+RGB',0],
    ];
    $stmt = mysqli_prepare($conn, "INSERT INTO Producto (id_categoria,nombre,marca,descripcion,precio,stock,imagen,destacado,estado) VALUES (?,?,?,?,?,?,?,?,1)");
    foreach ($prods as $p) {
        mysqli_stmt_bind_param($stmt,'isssdiis',$p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6],$p[7]);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
    $log[] = "✓ Insertar 14 productos";
} else {
    $log[] = "ℹ Productos ya existen (" . $c[0] . ")";
}

// 5. Cupones
$r = mysqli_query($conn, "SELECT COUNT(*) FROM Cupon"); $c = mysqli_fetch_row($r);
if ($c[0] == 0) {
    mysqli_query($conn, "INSERT IGNORE INTO Cupon (codigo,descuento,activo,fecha_expiracion,usos_maximos,usos_actuales) VALUES ('NEXUS10',10.00,1,'2026-12-31',100,5),('GAMER20',20.00,1,'2026-06-30',50,2)");
    $log[] = "✓ Insertar cupones";
} else $log[] = "ℹ Cupones ya existen";

// 6. Usuarios
$r = mysqli_query($conn, "SELECT COUNT(*) FROM Usuario"); $c = mysqli_fetch_row($r);
if ($c[0] == 0) {
    $adminH   = password_hash('Admin123!',   PASSWORD_BCRYPT);
    $clienteH = password_hash('Cliente123!', PASSWORD_BCRYPT);
    $stmt = mysqli_prepare($conn, "INSERT INTO Usuario (nombre,correo,contrasena,rol,metodo_2fa,foto_perfil) VALUES (?,?,?,?,?,'default.png')");
    $users = [
        ['NexusAdmin','admin@nexusgear.com',$adminH,'admin','email'],
        ['Carlos Mendoza','cliente1@test.com',$clienteH,'cliente','email'],
        ['Ana García','cliente2@test.com',$clienteH,'cliente','email'],
        ['Luis Torres','cliente3@test.com',$clienteH,'cliente','email'],
        ['María López','cliente4@test.com',$clienteH,'cliente','email'],
        ['Javier Ruiz','cliente5@test.com',$clienteH,'cliente','email'],
    ];
    foreach ($users as $u) { mysqli_stmt_bind_param($stmt,'sssss',$u[0],$u[1],$u[2],$u[3],$u[4]); mysqli_stmt_execute($stmt); }
    mysqli_stmt_close($stmt);
    $log[] = "✓ Insertar 6 usuarios";
} else $log[] = "ℹ Usuarios ya existen (" . $c[0] . ")";

// 7. Ventas y reseñas de ejemplo
$r = mysqli_query($conn,"SELECT COUNT(*) FROM Venta"); $c = mysqli_fetch_row($r);
if ($c[0] == 0) {
    $ids = []; $res = mysqli_query($conn,"SELECT id_usuario FROM Usuario WHERE rol='cliente' LIMIT 3");
    while ($row = mysqli_fetch_row($res)) $ids[] = $row[0];
    if (count($ids) >= 3) {
        mysqli_query($conn,"INSERT INTO Venta (id_usuario,total,estado_venta,cupon_aplicado,descuento_aplicado,fecha) VALUES ({$ids[0]},2649.98,'entregado','NEXUS10',10.00,NOW()-INTERVAL 30 DAY),({$ids[1]},1299.99,'pagado',NULL,0,NOW()-INTERVAL 15 DAY),({$ids[2]},269.97,'pendiente','GAMER20',20.00,NOW()-INTERVAL 5 DAY)");
        $vr=mysqli_query($conn,"SELECT id_venta FROM Venta ORDER BY id_venta LIMIT 3");
        $vids=[];while($vrow=mysqli_fetch_row($vr))$vids[]=$vrow[0];
        if(count($vids)>=3){
            mysqli_query($conn,"INSERT INTO Detalle_Venta(id_venta,id_producto,cantidad,precio_unitario,subtotal) VALUES ({$vids[0]},1,1,2499.99,2499.99),({$vids[0]},7,1,149.99,149.99)");
            mysqli_query($conn,"INSERT INTO Detalle_Venta(id_venta,id_producto,cantidad,precio_unitario,subtotal) VALUES ({$vids[1]},4,1,1299.99,1299.99)");
            mysqli_query($conn,"INSERT INTO Detalle_Venta(id_venta,id_producto,cantidad,precio_unitario,subtotal) VALUES ({$vids[2]},10,2,89.99,179.98),({$vids[2]},8,1,99.99,99.99)");
        }
        $log[] = "✓ Ventas de ejemplo";
    }
}
$r = mysqli_query($conn,"SELECT COUNT(*) FROM Resena"); $c = mysqli_fetch_row($r);
if ($c[0] == 0) {
    $ids=[];$res=mysqli_query($conn,"SELECT id_usuario FROM Usuario WHERE rol='cliente' LIMIT 3");
    while($row=mysqli_fetch_row($res))$ids[]=$row[0];
    if(count($ids)>=3){
        $stmt=mysqli_prepare($conn,"INSERT INTO Resena(id_usuario,id_producto,puntuacion,comentario)VALUES(?,?,?,?)");
        foreach([[$ids[0],1,5,'Increíble laptop, el rendimiento es brutal.'],[$ids[0],7,5,'El mejor mouse que he usado.'],[$ids[1],4,5,'Monitor espectacular.'],[$ids[1],10,4,'Excelente teclado mecánico.'],[$ids[2],8,4,'Muy buen mouse.'],[$ids[2],1,5,'La pantalla QHD 240Hz es impresionante.']] as $rv){
            mysqli_stmt_bind_param($stmt,'iiis',$rv[0],$rv[1],$rv[2],$rv[3]);mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        $log[] = "✓ Reseñas de ejemplo";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>NexusGear Setup</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#080810;color:#e2e2f0;font-family:'Segoe UI',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
.box{background:#11111e;border:1px solid rgba(0,216,240,0.2);border-radius:16px;padding:40px;max-width:620px;width:100%;}
h1{font-size:1.7rem;font-weight:800;color:#00d8f0;margin-bottom:4px;}
h2{font-size:.88rem;color:#4a4a65;font-weight:400;margin-bottom:22px;}
.item{padding:7px 12px;margin:3px 0;background:rgba(0,216,240,0.04);border-left:3px solid #00d8f0;border-radius:4px;font-size:.85rem;}
.err{border-left-color:#f43f8e;background:rgba(244,63,142,0.05);}
.creds{background:rgba(124,58,237,0.08);border:1px solid rgba(124,58,237,0.25);border-radius:10px;padding:16px;margin-top:18px;}
.creds h3{color:#7c3aed;margin-bottom:8px;font-size:.9rem;}
code{color:#00d8f0;font-family:monospace;font-size:.82rem;}
.btn{display:inline-flex;align-items:center;gap:8px;margin-top:22px;padding:12px 26px;background:linear-gradient(135deg,#7c3aed,#00d8f0);color:#080810;text-decoration:none;border-radius:8px;font-weight:700;}
</style>
</head>
<body>
<div class="box">
    <h1><i class="bi bi-lightning-charge-fill" style="margin-right:6px;"></i>NexusGear Setup v3</h1>
    <h2>Inicialización — categorías sin duplicados</h2>
    <?php foreach($log as $l) echo "<div class='item'>$l</div>"; ?>
    <?php foreach($errors as $e) echo "<div class='item err'>$e</div>"; ?>
    <?php if(empty($errors)): ?>
    <div class="creds">
        <h3><i class="bi bi-key-fill" style="margin-right:6px;"></i>Credenciales</h3>
        <div style="display:flex;flex-direction:column;gap:5px;">
            <div>Admin: <code>admin@nexusgear.com</code> / <code>Admin123!</code></div>
            <div>Cliente: <code>cliente1@test.com</code> / <code>Cliente123!</code></div>
        </div>
    </div>
    <a href="/nexusgear/index.php" class="btn"><i class="bi bi-rocket-takeoff-fill"></i>Ir a NexusGear</a>
    <?php else: ?>
    <p style="color:#f43f8e;margin-top:16px;font-size:.88rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Verifica que MySQL esté corriendo en XAMPP.</p>
    <?php endif; ?>
</div>
</body>
</html>
