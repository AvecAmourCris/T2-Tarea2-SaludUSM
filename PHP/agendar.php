<?php
require_once __DIR__ . '/comun.php';
exigirRol([1]);
actualizarVencidas($pdo);
$error = '';
$especialidades = $pdo->query('SELECT ID_Especialidad, Nombre FROM Especialidad ORDER BY Nombre')->fetchAll();
$centros = $pdo->query('SELECT Codigo_Centro, Nombre FROM Centro_Medico ORDER BY Nombre')->fetchAll();
$esp = (int)($_POST['especialidad'] ?? $_GET['especialidad'] ?? 0);
$centro = (string)($_POST['centro'] ?? $_GET['centro'] ?? '');
$medico = (string)($_POST['medico'] ?? $_GET['medico'] ?? '');
$fecha = (string)($_POST['fecha'] ?? $_GET['fecha'] ?? date('Y-m-d', strtotime('+1 day')));
$validaFecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
if (!$validaFecha || $validaFecha->format('Y-m-d') !== $fecha) { $fecha = date('Y-m-d'); }
$medicos = $pdo->prepare('SELECT m.RUT_Medico, m.Nombre_Completo FROM Medico m JOIN Medico_Centro mc ON mc.RUT_Medico = m.RUT_Medico JOIN Medico_Especialidad me ON me.RUT_Medico=m.RUT_Medico WHERE (? = 0 OR me.ID_Especialidad = ?) AND (? = "" OR mc.Codigo_Centro = ?) GROUP BY m.RUT_Medico, m.Nombre_Completo ORDER BY m.Nombre_Completo');
$medicos->execute([$esp,$esp,$centro,$centro]);
$opciones = $medicos->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verificarCsrf();
        if (($_POST['accion'] ?? '') !== 'agendar') { throw new RuntimeException('Acción no permitida.'); }
        $hora = trim((string)($_POST['hora'] ?? ''));
        $fechaHora = fechaMySQL($fecha . 'T' . $hora);
        $resultado = procedimiento($pdo, 'sp_agendar_cita', [rutActual(),$medico,$centro,$esp,$fechaHora], ['nueva_cita','operacion_ok','mensaje_operacion']);
        if ((int)$resultado['operacion_ok'] !== 1) { throw new RuntimeException($resultado['mensaje_operacion'] ?? 'No se pudo agendar.'); }
        aviso('Reserva creada. Número de cita: ' . $resultado['nueva_cita']);
        salto('mis_citas.php');
    } catch (Throwable $e) { $error = salidaError($e); }
}
$horarios = [];
if ($esp && $centro !== '' && $medico !== '' && $fecha >= date('Y-m-d')) {
    $p = $pdo->prepare('SELECT fn_validar_horario(?, ?, ?, ?, ?, NULL)');
    for ($minutos = 8*60; $minutos <= 17*60+45; $minutos += 15) {
        $hora = sprintf('%02d:%02d',$minutos/60,$minutos%60);
        $p->execute([rutActual(),$medico,$centro,$esp,$fecha . ' ' . $hora . ':00']);
        if ($p->fetchColumn() === null) { $horarios[] = $hora; }
        $p->closeCursor();
    }
}
mostrarCabecera('Agendar hora médica');
if ($error): ?><p class="error"><?=e($error)?></p><?php endif ?>
<section><p class="hint">Citas de 15 minutos, de lunes a viernes, entre 08:00 y 18:00. La base de datos valida la disponibilidad antes de confirmar la reserva.</p>
<form method="get" class="grid">
<div class="field"><label>Especialidad</label><select name="especialidad" required><option value="">Seleccionar</option><?php foreach($especialidades as $v): ?><option value="<?=e($v['ID_Especialidad'])?>" <?=$esp===$v['ID_Especialidad']?'selected':''?>><?=e($v['Nombre'])?></option><?php endforeach ?></select></div>
<div class="field"><label>Centro</label><select name="centro" required><option value="">Seleccionar</option><?php foreach($centros as $v): ?><option value="<?=e($v['Codigo_Centro'])?>" <?=$centro===$v['Codigo_Centro']?'selected':''?>><?=e($v['Nombre'])?></option><?php endforeach ?></select></div>
<div class="field"><label>Médico</label><select name="medico"><option value="">Seleccionar después de filtrar</option><?php foreach($opciones as $v): ?><option value="<?=e($v['RUT_Medico'])?>" <?=$medico===$v['RUT_Medico']?'selected':''?>><?=e($v['Nombre_Completo'])?></option><?php endforeach ?></select></div>
<div class="field"><label>Fecha</label><input type="date" name="fecha" min="<?=date('Y-m-d')?>" value="<?=e($fecha)?>" required></div>
<div><button>Ver disponibilidad</button></div></form></section>
<?php if($esp && $centro && $medico): ?><section><h2>Horas disponibles el <?=e($fecha)?></h2><?php if($horarios): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="agendar"><input type="hidden" name="especialidad" value="<?=e($esp)?>"><input type="hidden" name="centro" value="<?=e($centro)?>"><input type="hidden" name="medico" value="<?=e($medico)?>"><input type="hidden" name="fecha" value="<?=e($fecha)?>"><div class="row"><label for="hora">Horario</label><select name="hora" id="hora" required><?php foreach ($horarios as $h): ?><option value="<?=e($h)?>"><?=e($h)?></option><?php endforeach ?></select><button>Confirmar reserva</button></div></form><?php else: ?><p>No existen horarios disponibles para esta combinación. Puedes cambiar fecha, médico o centro.</p><?php endif ?></section><?php endif ?>
<?php mostrarPie(); ?>
