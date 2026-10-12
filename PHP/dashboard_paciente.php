<?php
require_once __DIR__ . '/comun.php';
exigirRol([1]);
actualizarVencidas($pdo);
$q=$pdo->prepare('SELECT Nombre_Completo FROM Paciente WHERE RUT_Paciente=?'); $q->execute([rutActual()]); $nombre=$q->fetchColumn();
$q=$pdo->prepare("SELECT COUNT(*) FROM Cita c JOIN Estado_Cita e ON e.ID_Estado_Cita=c.ID_Estado_Cita WHERE c.RUT_Paciente=? AND c.Fecha_Hora>=NOW() AND e.Nombre IN ('Reservada','Confirmada')");
$q->execute([rutActual()]); $proximas=(int)$q->fetchColumn();
mostrarCabecera('Portal del paciente');
?>
<p>Bienvenido/a, <strong><?=e($nombre)?></strong>. ¿En qué podemos ayudarte?</p>
<div class="grid"><div class="card"><span class="muted">Próximas citas</span><div class="num"><?=e($proximas)?></div><a href="mis_citas.php">Revisar mis citas</a></div><div class="card"><h2>Buscar especialistas</h2><form action="buscar_medicos.php" method="get" class="row"><div class="field"><input name="q" placeholder="Nombre o especialidad" aria-label="Buscar médico"></div><button>Buscar</button></form></div><div class="card"><h2>Reservar hora</h2><p>Encuentra un bloque disponible y confirma tu reserva.</p><a class="btn" href="agendar.php">Agendar</a></div><div class="card"><h2>Historial clínico</h2><p>Consulta tus atenciones, diagnósticos y recetas.</p><a class="btn" href="historial.php">Ver historial</a></div></div>
<?php mostrarPie(); ?>
