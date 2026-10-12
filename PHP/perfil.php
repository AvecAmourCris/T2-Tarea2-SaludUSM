<?php
require_once __DIR__ . '/comun.php';
exigirRol([1,2,3]);
$error='';
$rut=rutActual();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        verificarCsrf();
        $accion=(string)($_POST['accion'] ?? '');
        $pdo->beginTransaction();
        if ($accion==='password') {
            $actual=(string)($_POST['actual'] ?? '');
            $nueva=(string)($_POST['nueva'] ?? '');
            $q=$pdo->prepare('SELECT Password_Hash FROM Usuario WHERE RUT=? FOR UPDATE'); $q->execute([$rut]);
            if (!password_verify($actual,(string)$q->fetchColumn())) { throw new RuntimeException('Tu contraseña actual es incorrecta.'); }
            if (strlen($nueva)<8 || strlen($nueva)>72 || !preg_match('/[A-Za-z]/',$nueva) || !preg_match('/[0-9]/',$nueva)) { throw new RuntimeException('La nueva contraseña requiere 8 a 72 caracteres, letras y números.'); }
            $pdo->prepare('UPDATE Usuario SET Password_Hash=? WHERE RUT=?')->execute([password_hash($nueva,PASSWORD_DEFAULT),$rut]);
            $mensaje='Contraseña actualizada.';
        } elseif ($accion==='datos') {
            $nombre=trim((string)($_POST['nombre'] ?? ''));
            $email=trim((string)($_POST['email'] ?? ''));
            if (!$nombre || strlen($nombre)>150 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>100) { throw new RuntimeException('Ingresa un nombre y un correo válidos.'); }
            if (rol()===1) {
                $telefono=trim((string)($_POST['telefono'] ?? ''));
                $comuna=filter_var($_POST['comuna'] ?? null,FILTER_VALIDATE_INT);
                $prevision=filter_var($_POST['prevision'] ?? null,FILTER_VALIDATE_INT);
                $q=$pdo->prepare('SELECT 1 FROM Comuna WHERE ID_Comuna=?'); $q->execute([$comuna]); if(!$q->fetch()) throw new RuntimeException('Comuna inválida.');
                $q=$pdo->prepare('SELECT 1 FROM Prevision WHERE ID_Prevision=?'); $q->execute([$prevision]); if(!$q->fetch()) throw new RuntimeException('Previsión inválida.');
                if(strlen($telefono)>20) throw new RuntimeException('Teléfono demasiado largo.');
                $q=$pdo->prepare('SELECT 1 FROM Medico WHERE Email_Institucional=?'); $q->execute([$email]); if($q->fetch()) throw new RuntimeException('El correo ya corresponde a un médico.');
                $pdo->prepare('UPDATE Paciente SET Nombre_Completo=?, Email=?, Telefono=?, ID_Comuna_Residencia=?, ID_Prevision=? WHERE RUT_Paciente=?')->execute([$nombre,$email,$telefono?:null,$comuna,$prevision,$rut]);
            } elseif (rol()===2) {
                $q=$pdo->prepare('SELECT 1 FROM Paciente WHERE Email=?'); $q->execute([$email]); if($q->fetch()) throw new RuntimeException('El correo ya corresponde a un paciente.');
                $pdo->prepare('UPDATE Medico SET Nombre_Completo=?, Email_Institucional=? WHERE RUT_Medico=?')->execute([$nombre,$email,$rut]);
            } else { throw new RuntimeException('La cuenta administrativa solo permite cambiar su contraseña.'); }
            $mensaje='Datos actualizados.';
        } elseif ($accion==='eliminar') {
            if(rol()!==1) { throw new RuntimeException('Solo el paciente puede eliminar su propia cuenta. Los médicos son administrados por SaludUSM.'); }
            $password=(string)($_POST['confirmar_password'] ?? '');
            $q=$pdo->prepare('SELECT Password_Hash FROM Usuario WHERE RUT=? FOR UPDATE'); $q->execute([$rut]);
            if (!password_verify($password,(string)$q->fetchColumn())) throw new RuntimeException('Confirma tu contraseña para eliminar la cuenta.');
            $pdo->prepare('DELETE FROM Usuario WHERE RUT=?')->execute([$rut]);
            $pdo->commit(); session_unset(); session_destroy(); salto('login.php');
        } else { throw new RuntimeException('Acción no admitida.'); }
        $pdo->commit(); aviso($mensaje); salto('perfil.php');
    } catch (Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); $error=salidaError($e); }
}
$datos=null;
if (rol()===1) { $q=$pdo->prepare('SELECT Nombre_Completo AS nombre,Email AS email,Telefono AS telefono,ID_Comuna_Residencia AS comuna,ID_Prevision AS prevision FROM Paciente WHERE RUT_Paciente=?'); $q->execute([$rut]); $datos=$q->fetch(); }
elseif (rol()===2) { $q=$pdo->prepare('SELECT Nombre_Completo AS nombre,Email_Institucional AS email FROM Medico WHERE RUT_Medico=?'); $q->execute([$rut]); $datos=$q->fetch(); }
$comunas=$pdo->query('SELECT c.ID_Comuna, c.Nombre, r.Nombre AS Region FROM Comuna c JOIN Region r ON r.ID_Region=c.ID_Region ORDER BY r.Nombre,c.Nombre')->fetchAll();
$previsiones=$pdo->query('SELECT ID_Prevision,Nombre FROM Prevision ORDER BY Nombre')->fetchAll();
mostrarCabecera('Mi perfil');
if($error): ?><p class="error"><?=e($error)?></p><?php endif ?>
<section class="compact"><p><strong>RUT:</strong> <?=e($rut)?> · <strong>Rol:</strong> <?=e([1=>'Paciente',2=>'Médico',3=>'Administrador'][rol()] ?? '')?></p><?php if ($datos): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="datos"><div class="field"><label>Nombre completo</label><input name="nombre" value="<?=e($datos['nombre'])?>" maxlength="150" required></div><div class="field"><label>Correo electrónico</label><input type="email" name="email" value="<?=e($datos['email'])?>" maxlength="100" required></div><?php if (rol()===1): ?><div class="field"><label>Teléfono</label><input name="telefono" maxlength="20" value="<?=e($datos['telefono'])?>"></div><div class="field"><label>Comuna</label><select name="comuna" required><?php foreach($comunas as $c): ?><option value="<?=e($c['ID_Comuna'])?>" <?=$c['ID_Comuna']==$datos['comuna']?'selected':''?>><?=e($c['Nombre'].' — '.$c['Region'])?></option><?php endforeach ?></select></div><div class="field"><label>Previsión</label><select name="prevision" required><?php foreach($previsiones as $p): ?><option value="<?=e($p['ID_Prevision'])?>" <?=$p['ID_Prevision']==$datos['prevision']?'selected':''?>><?=e($p['Nombre'])?></option><?php endforeach ?></select></div><?php endif ?><p><button>Guardar mis datos</button></p></form><?php else: ?><p>Esta cuenta no tiene ficha personal editable en el modelo actual.</p><?php endif ?></section>
<section class="compact"><h2>Cambiar contraseña</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="password"><div class="field"><label>Contraseña actual</label><input type="password" name="actual" required></div><div class="field"><label>Nueva contraseña (8+ caracteres, letras y números)</label><input type="password" name="nueva" minlength="8" required></div><p><button>Actualizar contraseña</button></p></form></section>
<?php if(rol()===1): ?><section class="compact"><h2>Eliminar cuenta</h2><p class="hint">Eliminar la cuenta paciente también elimina sus citas, atenciones, diagnósticos asociados y recetas por cascada. Esta acción es irreversible.</p><form method="post" onsubmit="return confirm('¿Eliminar permanentemente tu cuenta y los datos asociados? No se puede deshacer.')"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="eliminar"><div class="field"><label>Confirma tu contraseña</label><input name="confirmar_password" type="password" required></div><p><button class="danger">Eliminar mi cuenta</button></p></form></section><?php endif ?>
<?php mostrarPie(); ?>
