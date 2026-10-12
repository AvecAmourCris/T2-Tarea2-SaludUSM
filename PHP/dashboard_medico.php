<?php
require_once __DIR__ . '/comun.php';
exigirRol([2]);
actualizarVencidas($pdo);
$q=$pdo->prepare('SELECT COUNT(*) FROM Cita WHERE RUT_Medico=? AND DATE(Fecha_Hora)=CURDATE()'); $q->execute([rutActual()]); $hoy=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT Nombre_Completo FROM Medico WHERE RUT_Medico=?'); $q->execute([rutActual()]); $nombre=$q->fetchColumn();
mostrarCabecera('Portal del médico');
?>
<p>Bienvenido/a, <strong><?=e($nombre)?></strong>. RUT <?=e(rutActual())?>.</p>
<div class="grid"><div class="card"><span class="muted">Citas del día</span><div class="num"><?=e($hoy)?></div></div><div class="card"><h2>Agenda</h2><p>Consulta las citas de cada día y actualiza su estado.</p><a class="btn" href="agenda.php">Ver agenda</a></div><div class="card"><h2>Atenciones</h2><p>Registra y edita fichas de atención, diagnósticos y recetas.</p><a class="btn" href="atenciones.php">Gestionar atenciones</a></div><div class="card"><h2>Historial clínico</h2><p>Revisa el historial de tus pacientes sin modificar registros ajenos.</p><a class="btn" href="historial.php">Ver historial</a></div></div>
<?php mostrarPie(); ?>
