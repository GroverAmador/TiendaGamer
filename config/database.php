<?php
// ============================================================
// NexusGear - Database Connection
// XAMPP defaults: host=localhost, user=root, no password
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'nexusgear_db');

// Establish MySQLi connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    // Show styled error page if connection fails
    die('
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error de Conexión - NexusGear</title>
        <style>
            body { background: #0a0a0f; color: #e8e8ff; font-family: sans-serif;
                   display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .box { background: #13131f; border: 1px solid rgba(0,245,255,0.3);
                   border-radius: 12px; padding: 40px; text-align: center; max-width: 500px; }
            h1 { color: #00f5ff; }
            p { color: #8888aa; }
            a { color: #7b2fff; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>⚡ Error de Conexión</h1>
            <p>No se pudo conectar a la base de datos.</p>
            <p>Error: ' . mysqli_connect_error() . '</p>
            <p>Por favor ejecuta <a href="/nexusgear/config/setup.php">setup.php</a> primero.</p>
        </div>
    </body>
    </html>
    ');
}

// Set UTF-8 character encoding
mysqli_set_charset($conn, 'utf8mb4');
?>
