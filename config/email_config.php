<?php
// ============================================================
// NexusGear - Configuración SMTP
// IMPORTANTE: Necesitas una "Contraseña de aplicación" de Google,
// NO tu contraseña normal. Ve a:
// myaccount.google.com/apppasswords → genera una de 16 chars
// ============================================================
return [
    'enabled'    => true,
    'host'       => 'smtp.gmail.com',
    'port'       => 465,
    'user'       => 'taborgarafa@gmail.com',
    'pass'       => '',          // <-- Pega aquí tu App Password de 16 chars (sin espacios)
    'from_name'  => 'NexusGear',
    'from_email' => 'taborgarafa@gmail.com',
    'encryption' => 'ssl',
];
