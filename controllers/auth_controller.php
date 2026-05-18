<?php
// ============================================================
// NexusGear — Auth Controller v2 (Email + TOTP 2FA)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/totp.php';
require_once __DIR__ . '/../helpers/mailer.php';

$action = $_POST['action'] ?? '';

// ============================================================
// REGISTER
// ============================================================
if ($action === 'register') {
    if (($_POST['csrf_token']??'') !== ($_SESSION['csrf_token']??'')) {
        $error = 'Token de seguridad inválido.'; return;
    }
    $nombre    = trim($_POST['nombre']    ?? '');
    $correo    = trim($_POST['correo']    ?? '');
    $contrasena= $_POST['contrasena']     ?? '';
    $confirmar = $_POST['confirmar_contrasena'] ?? '';
    $metodo    = ($_POST['metodo_2fa']??'email')==='totp' ? 'totp' : 'email';

    if (!$nombre || !$correo || !$contrasena || !$confirmar) { $error='Todos los campos son obligatorios.'; return; }
    if (!filter_var($correo,FILTER_VALIDATE_EMAIL)) { $error='Correo inválido.'; return; }
    if (strlen($contrasena)<8) { $error='Contraseña: mínimo 8 caracteres.'; return; }
    if (!preg_match('/[A-Z]/',$contrasena)) { $error='La contraseña necesita al menos una mayúscula.'; return; }
    if (!preg_match('/[0-9]/',$contrasena)) { $error='La contraseña necesita al menos un número.'; return; }
    if ($contrasena !== $confirmar) { $error='Las contraseñas no coinciden.'; return; }

    // Verificar email único
    $st = mysqli_prepare($conn,"SELECT id_usuario FROM Usuario WHERE correo=?");
    mysqli_stmt_bind_param($st,'s',$correo); mysqli_stmt_execute($st); mysqli_stmt_store_result($st);
    if (mysqli_stmt_num_rows($st)>0) { mysqli_stmt_close($st); $error='Este correo ya está registrado.'; return; }
    mysqli_stmt_close($st);

    $hash    = password_hash($contrasena, PASSWORD_BCRYPT);
    $totpSec = ($metodo==='totp') ? TOTP::generateSecret() : null;

    $st = mysqli_prepare($conn,"INSERT INTO Usuario (nombre,correo,contrasena,rol,metodo_2fa,totp_secret,foto_perfil) VALUES (?,?,?,'cliente',?,?,'default.png')");
    mysqli_stmt_bind_param($st,'sssss',$nombre,$correo,$hash,$metodo,$totpSec);
    if (!mysqli_stmt_execute($st)) { mysqli_stmt_close($st); $error='Error al crear la cuenta.'; return; }
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($st);

    if ($metodo === 'totp') {
        // Guardar datos para la página de setup TOTP
        $_SESSION['totp_setup_id']     = $newId;
        $_SESSION['totp_setup_secret'] = $totpSec;
        $_SESSION['totp_setup_email']  = $correo;
        $_SESSION['totp_setup_name']   = $nombre;
        header('Location: /nexusgear/auth/setup_totp.php');
    } else {
        $_SESSION['flash_message'] = '¡Cuenta creada! Ahora inicia sesión.';
        $_SESSION['flash_type']    = 'success';
        header('Location: /nexusgear/auth/login.php');
    }
    exit;
}

// ============================================================
// LOGIN
// ============================================================
if ($action === 'login') {
    if (($_POST['csrf_token']??'') !== ($_SESSION['csrf_token']??'')) {
        $error='Token inválido.'; return;
    }
    $correo    = trim($_POST['correo']    ?? '');
    $contrasena= $_POST['contrasena']     ?? '';
    if (!$correo || !$contrasena) { $error='Completa todos los campos.'; return; }

    $st = mysqli_prepare($conn,"SELECT id_usuario,nombre,correo,contrasena,rol,metodo_2fa,totp_secret,foto_perfil,bloqueado FROM Usuario WHERE correo=?");
    mysqli_stmt_bind_param($st,'s',$correo); mysqli_stmt_execute($st);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);

    if (!$user || !password_verify($contrasena,$user['contrasena'])) {
        $error='Correo o contraseña incorrectos.'; return;
    }
    if (!empty($user['bloqueado'])) {
        $error='Tu cuenta ha sido bloqueada. Contacta al administrador.'; return;
    }

    // Preparar sesión 2FA
    $_SESSION['2fa_user_id']  = $user['id_usuario'];
    $_SESSION['2fa_metodo']   = $user['metodo_2fa'] ?? 'email';
    $_SESSION['csrf_token']   = bin2hex(random_bytes(32));

    if (($user['metodo_2fa']??'email') === 'totp') {
        // TOTP: solo redirigir, no se envía nada
        $_SESSION['2fa_totp_secret'] = $user['totp_secret'];
        header('Location: /nexusgear/auth/verify_2fa.php');
    } else {
        // Email: generar y enviar código
        $code   = str_pad((string)random_int(0,999999), 6, '0', STR_PAD_LEFT);
        $expira = time() + 300;
        $_SESSION['codigo_2fa'] = $code;
        $_SESSION['2fa_expira'] = $expira;
        $_SESSION['2fa_nombre'] = $user['nombre'];
        $_SESSION['2fa_correo'] = $user['correo'];

        // Persistir en BD
        $exp = date('Y-m-d H:i:s', $expira);
        $st2 = mysqli_prepare($conn,"UPDATE Usuario SET codigo_2fa=?,expira_2fa=? WHERE id_usuario=?");
        mysqli_stmt_bind_param($st2,'ssi',$code,$exp,$user['id_usuario']);
        mysqli_stmt_execute($st2); mysqli_stmt_close($st2);

        // Enviar email
        $emailSent = NexusMailer::send2FA($user['correo'], $user['nombre'], $code);
        $_SESSION['2fa_email_sent'] = $emailSent;

        header('Location: /nexusgear/auth/verify_2fa.php');
    }
    exit;
}

// ============================================================
// VERIFY 2FA
// ============================================================
if ($action === 'verify_2fa') {
    if (!isset($_SESSION['2fa_user_id'])) { header('Location: /nexusgear/auth/login.php'); exit; }

    $inputCode = preg_replace('/\D/','',trim($_POST['codigo']??''));
    $metodo    = $_SESSION['2fa_metodo'] ?? 'email';
    $valid     = false;

    if ($metodo === 'totp') {
        $secret = $_SESSION['2fa_totp_secret'] ?? '';
        $valid  = $secret && TOTP::verify($secret, $inputCode);
    } else {
        $stored = $_SESSION['codigo_2fa'] ?? '';
        $expira = (int)($_SESSION['2fa_expira'] ?? 0);
        if (time() > $expira) {
            $_SESSION['2fa_error'] = 'El código ha expirado. Solicita uno nuevo.';
            header('Location: /nexusgear/auth/verify_2fa.php'); exit;
        }
        $valid = hash_equals($stored, str_pad($inputCode,6,'0',STR_PAD_LEFT));
    }

    if (!$valid) {
        $_SESSION['2fa_error'] = 'Código incorrecto. Intenta nuevamente.';
        header('Location: /nexusgear/auth/verify_2fa.php'); exit;
    }

    // Código correcto — completar login
    $uid = (int)$_SESSION['2fa_user_id'];
    $st  = mysqli_prepare($conn,"SELECT id_usuario,nombre,correo,rol,foto_perfil FROM Usuario WHERE id_usuario=?");
    mysqli_stmt_bind_param($st,'i',$uid); mysqli_stmt_execute($st);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$user) { header('Location: /nexusgear/auth/login.php'); exit; }

    // Limpiar sesión 2FA
    foreach(['2fa_user_id','2fa_metodo','2fa_totp_secret','codigo_2fa','2fa_expira','2fa_nombre','2fa_correo','2fa_email_sent'] as $k) unset($_SESSION[$k]);

    // Limpiar código en BD
    mysqli_query($conn,"UPDATE Usuario SET codigo_2fa=NULL,expira_2fa=NULL WHERE id_usuario=$uid");

    session_regenerate_id(true);
    $_SESSION['id_usuario']  = $user['id_usuario'];
    $_SESSION['nombre']      = $user['nombre'];
    $_SESSION['correo']      = $user['correo'];
    $_SESSION['rol']         = $user['rol'];
    $_SESSION['foto_perfil'] = $user['foto_perfil'];
    $_SESSION['csrf_token']  = bin2hex(random_bytes(32));

    header('Location: ' . ($user['rol']==='admin' ? '/nexusgear/admin/dashboard.php' : '/nexusgear/client/productos.php'));
    exit;
}

// ============================================================
// RESEND 2FA (solo email)
// ============================================================
if ($action === 'resend_2fa') {
    if (!isset($_SESSION['2fa_user_id'])) { header('Location: /nexusgear/auth/login.php'); exit; }
    $code   = str_pad((string)random_int(0,999999), 6, '0', STR_PAD_LEFT);
    $expira = time() + 300;
    $_SESSION['codigo_2fa'] = $code;
    $_SESSION['2fa_expira'] = $expira;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $uid = (int)$_SESSION['2fa_user_id'];
    $exp = date('Y-m-d H:i:s', $expira);
    $st  = mysqli_prepare($conn,"UPDATE Usuario SET codigo_2fa=?,expira_2fa=? WHERE id_usuario=?");
    mysqli_stmt_bind_param($st,'ssi',$code,$exp,$uid); mysqli_stmt_execute($st); mysqli_stmt_close($st);

    $correo = $_SESSION['2fa_correo'] ?? '';
    $nombre = $_SESSION['2fa_nombre'] ?? '';
    $_SESSION['2fa_email_sent'] = NexusMailer::send2FA($correo,$nombre,$code);

    header('Location: /nexusgear/auth/verify_2fa.php'); exit;
}
?>
