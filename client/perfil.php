<?php
// ============================================================
// NexusGear - User Profile Edit
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario'])) { header('Location: /nexusgear/auth/login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$uid = (int)$_SESSION['id_usuario'];
$msg = ''; $msgType = 'success';

$stmt = mysqli_prepare($conn,"SELECT * FROM Usuario WHERE id_usuario=?");
mysqli_stmt_bind_param($stmt,'i',$uid);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        $msg='Token inválido.';$msgType='error';
    } else {
        $action=$_POST['action']??'';
        if($action==='update_profile'){
            $nombre=trim($_POST['nombre']??'');
            $correo=trim($_POST['correo']??'');
            if(empty($nombre)||empty($correo)){$msg='Nombre y correo son obligatorios.';$msgType='error';}
            elseif(!filter_var($correo,FILTER_VALIDATE_EMAIL)){$msg='Correo inválido.';$msgType='error';}
            else {
                $chk=mysqli_prepare($conn,"SELECT id_usuario FROM Usuario WHERE correo=? AND id_usuario!=?");
                mysqli_stmt_bind_param($chk,'si',$correo,$uid);
                mysqli_stmt_execute($chk);mysqli_stmt_store_result($chk);
                if(mysqli_stmt_num_rows($chk)>0){$msg='Ese correo ya está en uso.';$msgType='error';}
                else {
                    $foto=$user['foto_perfil']??'default.png';
                    if(!empty($_FILES['foto_perfil']['name'])){
                        $ext=strtolower(pathinfo($_FILES['foto_perfil']['name'],PATHINFO_EXTENSION));
                        if(in_array($ext,['jpg','jpeg','png','webp','gif'])){
                            $fn='user_'.$uid.'_'.time().'.'.$ext;
                            if(move_uploaded_file($_FILES['foto_perfil']['tmp_name'],__DIR__.'/../assets/img/productos/'.$fn))
                                $foto=$fn;
                        }
                    }
                    $stmt=mysqli_prepare($conn,"UPDATE Usuario SET nombre=?,correo=?,foto_perfil=? WHERE id_usuario=?");
                    mysqli_stmt_bind_param($stmt,'sssi',$nombre,$correo,$foto,$uid);
                    if(mysqli_stmt_execute($stmt)){
                        $_SESSION['nombre']=$nombre;$_SESSION['correo']=$correo;$_SESSION['foto_perfil']=$foto;
                        $user['nombre']=$nombre;$user['correo']=$correo;$user['foto_perfil']=$foto;
                        $msg='Perfil actualizado exitosamente.';
                    } else {$msg='Error al actualizar.';$msgType='error';}
                    mysqli_stmt_close($stmt);
                }
                mysqli_stmt_close($chk);
            }
        }
        if($action==='change_password'){
            $actual=trim($_POST['contrasena_actual']??'');
            $nueva =trim($_POST['nueva_contrasena']??'');
            $conf  =trim($_POST['confirmar_nueva']??'');
            if(empty($actual)||empty($nueva)||empty($conf)){$msg='Completa todos los campos.';$msgType='error';}
            elseif(!password_verify($actual,$user['contrasena'])){$msg='La contraseña actual es incorrecta.';$msgType='error';}
            elseif($nueva!==$conf){$msg='Las contraseñas no coinciden.';$msgType='error';}
            elseif(strlen($nueva)<8||!preg_match('/[A-Z]/',$nueva)||!preg_match('/[0-9]/',$nueva)){
                $msg='La nueva contraseña debe tener 8+ caracteres, una mayúscula y un número.';$msgType='error';
            } else {
                $hash=password_hash($nueva,PASSWORD_BCRYPT);
                $stmt=mysqli_prepare($conn,"UPDATE Usuario SET contrasena=? WHERE id_usuario=?");
                mysqli_stmt_bind_param($stmt,'si',$hash,$uid);
                $msg=mysqli_stmt_execute($stmt)?'Contraseña actualizada.':'Error al cambiar.';
                if(mysqli_stmt_execute($stmt)===false) $msgType='error';
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$avatarUrl=($user['foto_perfil']&&$user['foto_perfil']!=='default.png')
    ?'/nexusgear/assets/img/productos/'.htmlspecialchars($user['foto_perfil'])
    :'https://placehold.co/120x120/13131f/00f5ff?text='.strtoupper(substr($user['nombre'],0,1));

function fechaEs(string $d):string{
    $m=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $t=strtotime($d);return date('d',$t).' de '.$m[date('n',$t)-1].' de '.date('Y',$t);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil — NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/nexusgear/assets/css/global.css">
    <link rel="stylesheet" href="/nexusgear/assets/css/client.css">
</head>
<body class="client-page">
<?php include __DIR__.'/../views/header.php'; ?>
<?php include __DIR__.'/../views/toast.php'; ?>

<div class="page-header">
    <div class="container-xl">
        <h1><i class="bi bi-person-circle me-2" style="color:var(--neon-cyan);"></i>Mi Perfil</h1>
    </div>
</div>

<div class="container-xl py-4">
    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType==='error'?'danger':'success' ?> mb-4">
        <i class="bi <?= $msgType==='error'?'bi-x-circle-fill':'bi-check-circle-fill' ?> me-2"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Avatar card -->
        <div class="col-lg-3">
            <div class="card p-4 text-center">
                <div class="avatar-upload-wrapper mx-auto mb-3" style="position:relative;display:inline-block;cursor:pointer;">
                    <img src="<?= $avatarUrl ?>" alt="Avatar" class="avatar-img" id="avatar-preview">
                    <div class="avatar-overlay">
                        <i class="bi bi-camera-fill" style="font-size:1.2rem;"></i>
                    </div>
                </div>
                <h5 style="font-family:'Oxanium',sans-serif;color:var(--text-primary);margin-bottom:4px;">
                    <?= htmlspecialchars($user['nombre']) ?>
                </h5>
                <div style="color:var(--text-muted);font-size:0.85rem;">
                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($user['correo']) ?>
                </div>
                <div class="<?= $user['rol']==='admin'?'badge-violet':'badge-cyan' ?> mt-2 d-inline-block">
                    <i class="bi <?= $user['rol']==='admin'?'bi-shield-fill':'bi-controller' ?> me-1"></i>
                    <?= $user['rol']==='admin'?'Administrador':'Cliente' ?>
                </div>
                <div style="color:var(--text-muted);font-size:0.8rem;margin-top:12px;">
                    <i class="bi bi-calendar3 me-1"></i>Miembro desde <?= fechaEs($user['fecha_registro']) ?>
                </div>
                <div class="d-flex flex-column gap-2 mt-4">
                    <a href="/nexusgear/client/historial.php" class="btn btn-outline-cyan btn-sm">
                        <i class="bi bi-list-check me-1"></i>Mis Pedidos
                    </a>
                    <a href="/nexusgear/client/favoritos.php" class="btn btn-outline-violet btn-sm">
                        <i class="bi bi-heart-fill me-1"></i>Favoritos
                    </a>
                </div>
            </div>
        </div>

        <!-- Forms -->
        <div class="col-lg-9">
            <!-- Edit profile -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 style="font-family:'Oxanium',sans-serif;margin:0;color:var(--neon-cyan);">
                        <i class="bi bi-pencil-square me-2"></i>Información Personal
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form id="form-perfil" method="POST" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><i class="bi bi-person me-1"></i>Nombre completo</label>
                                <input type="text" name="nombre" class="form-control" required
                                       value="<?= htmlspecialchars($user['nombre']) ?>">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="bi bi-envelope me-1"></i>Correo electrónico</label>
                                <input type="email" name="correo" class="form-control" required
                                       value="<?= htmlspecialchars($user['correo']) ?>">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><i class="bi bi-image me-1"></i>Foto de perfil</label>
                                <input type="file" name="foto_perfil" class="form-control" accept="image/*"
                                       id="avatar-input-form">
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-neon">
                                <i class="bi bi-floppy-fill me-2"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change password -->
            <div class="card">
                <div class="card-header">
                    <h5 style="font-family:'Oxanium',sans-serif;margin:0;color:var(--neon-violet);">
                        <i class="bi bi-key-fill me-2"></i>Cambiar Contraseña
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form id="form-change-password" method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label">🔒 Contraseña actual</label>
                            <div class="pw-wrap">
                                <input type="password" name="contrasena_actual" class="form-control"
                                       id="contrasena_actual" placeholder="Tu contraseña actual" required>
                                <button type="button" class="pw-toggle"
                                        onclick="togglePw('contrasena_actual',this)">👁</button>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">🔑 Nueva contraseña</label>
                            <div class="pw-wrap">
                                <input type="password" name="nueva_contrasena" class="form-control"
                                       id="nueva_contrasena" placeholder="Mínimo 8 caracteres" required>
                                <button type="button" class="pw-toggle"
                                        onclick="togglePw('nueva_contrasena',this)">👁</button>
                            </div>
                            <!-- Barra de fuerza -->
                            <div class="strength-bar mt-2"><div class="strength-fill" id="sf-new"></div></div>
                            <span class="strength-label" id="sl-new"></span>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">🔐 Confirmar nueva contraseña</label>
                            <div class="pw-wrap">
                                <input type="password" name="confirmar_nueva" class="form-control"
                                       id="confirmar_nueva" placeholder="Repite tu nueva contraseña" required>
                                <button type="button" class="pw-toggle"
                                        onclick="togglePw('confirmar_nueva',this)">👁</button>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                        <button type="submit" class="btn btn-outline-violet">
                            🔄 Cambiar Contraseña
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__.'/../views/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nexusgear/assets/js/main.js"></script>
<script src="/nexusgear/assets/js/validaciones.js"></script>
<script>
// Toggle ojo con emoji — no depende de Bootstrap Icons
function togglePw(inputId, btn) {
    var inp = document.getElementById(inputId);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}

// Barra de fuerza para nueva contraseña
document.getElementById('nueva_contrasena')?.addEventListener('input', function() {
    var v=this.value, score=0;
    if(v.length>=8)score++;if(v.length>=12)score++;
    if(/[A-Z]/.test(v))score++;if(/[0-9]/.test(v))score++;if(/[^A-Za-z0-9]/.test(v))score++;
    var lvls=[['0%','',''],['20%','#f43f8e','Muy débil'],['40%','#f59e0b','Débil'],
              ['60%','#f59e0b','Regular'],['80%','#84cc16','Buena'],['100%','#00d8f0','Excelente']];
    var l=lvls[Math.min(score,5)];
    var sf=document.getElementById('sf-new'),sl=document.getElementById('sl-new');
    if(sf){sf.style.width=l[0];sf.style.background=l[1]||'transparent';}
    if(sl){sl.textContent=l[2]||'';sl.style.color=l[1]||'';}
});
document.getElementById('avatar-input-form')?.addEventListener('change',function(){
    const file=this.files[0];
    if(file){const r=new FileReader();r.onload=e=>{const img=document.getElementById('avatar-preview');if(img)img.src=e.target.result;};r.readAsDataURL(file);}
});
<?php if ($msg): ?>
document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($msg) ?>,<?= json_encode($msgType==='error'?'error':'success') ?>));
<?php endif; ?>
</script>
</body>
</html>
