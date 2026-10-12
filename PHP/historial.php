<?php
require_once __DIR__ . '/comun.php';
exigirRol([1,2]);
$error='';
$pacientes=[];
if (rol()===2) {
    $q=$pdo->prepare('SELECT DISTINCT p.RUT_Paciente, p.Nombre_Completo FROM Cita c JOIN Paciente p ON p.RUT_Paciente=c.RUT_Paciente WHERE c.RUT_Medico=? ORDER BY p.Nombre_Completo');
    $q->execute([rutActual()]); $pacientes=$q->fetchAll();
}
$rutPaciente=rol()===1 ? rutActual() : strtoupper(trim((string)($_GET['paciente'] ?? '')));
if (rol()===2 && $rutPaciente!=='') {
    $q=$pdo->prepare('SELECT 1 FROM Cita WHERE RUT_Medico=? AND RUT_Paciente=? LIMIT 1');
    $q->execute([rutActual(),$rutPaciente]);
    if (!$q->fetch()) { $error='No tienes autorización para consultar ese paciente.'; $rutPaciente=''; }
}
$historial=[];
if ($rutPaciente!=='') {
    $q=$pdo->prepare('SELECT a.ID_Atencion,a.Motivo_Consulta,a.Observaciones_Clinicas,c.ID_Cita,c.Fecha_Hora,m.Nombre_Completo AS Medico,es.Nombre AS Especialidad,cm.Nombre AS Centro FROM Atencion a JOIN Cita c ON c.ID_Cita=a.ID_Cita JOIN Medico m ON m.RUT_Medico=c.RUT_Medico JOIN Especialidad es ON es.ID_Especialidad=c.ID_Especialidad JOIN Centro_Medico cm ON cm.Codigo_Centro=c.Codigo_Centro WHERE c.RUT_Paciente=? ORDER BY c.Fecha_Hora DESC');
    $q->execute([$rutPaciente]); $historial=$q->fetchAll();
    $diag=$pdo->prepare('SELECT d.Codigo_CIE10,d.Descripcion FROM Atencion_Diagnostico ad JOIN Diagnostico d ON d.Codigo_CIE10=ad.Codigo_CIE10 WHERE ad.ID_Atencion=? ORDER BY d.Codigo_CIE10');
    $rec=$pdo->prepare('SELECT Medicamento,Dosis,Dias_Tratamiento FROM Receta WHERE ID_Atencion=? ORDER BY ID_Receta');
    foreach ($historial as &$a) { $diag->execute([$a['ID_Atencion']]); $a['diagnosticos']=$diag->fetchAll(); $rec->execute([$a['ID_Atencion']]); $a['recetas']=$rec->fetchAll(); }
    unset($a);
}
mostrarCabecera('Historial clínico');
if ($error): ?><p class="error"><?=e($error)?></p><?php endif ?>
<?php if(rol()===2): ?><section><form method="get" class="row"><div class="field"><label>Paciente de tu agenda</label><select name="paciente" required><option value="">Selecciona un paciente</option><?php foreach($pacientes as $p): ?><option value="<?=e($p['RUT_Paciente'])?>" <?=$rutPaciente===$p['RUT_Paciente']?'selected':''?>><?=e($p['Nombre_Completo'].' ('.$p['RUT_Paciente'].')')?></option><?php endforeach ?></select></div><button>Ver historial</button></form><p class="hint">Puedes consultar el historial previo de pacientes asociados a tus citas, aunque lo haya registrado otro médico. Solo tus propias atenciones se pueden editar.</p></section><?php endif ?>
<?php if ($rutPaciente!==''): ?>
<p><?=count($historial)?> atenciones registradas</p>
<?php foreach($historial as $a): ?><section><h2>Atención #<?=e($a['ID_Atencion'])?> · <?=e($a['Fecha_Hora'])?></h2><p><strong>Médico:</strong> <?=e($a['Medico'])?> · <strong>Especialidad:</strong> <?=e($a['Especialidad'])?> · <strong>Centro:</strong> <?=e($a['Centro'])?></p><p><strong>Motivo:</strong> <?=nl2br(e($a['Motivo_Consulta']))?></p><p><strong>Observaciones:</strong> <?=nl2br(e($a['Observaciones_Clinicas']))?></p><h3>Diagnósticos</h3><?php if (!$a['diagnosticos']): ?><p class="muted">Sin diagnósticos.</p><?php endif ?><ul><?php foreach($a['diagnosticos'] as $d): ?><li><?=e($d['Codigo_CIE10'].' · '.$d['Descripcion'])?></li><?php endforeach ?></ul><h3>Recetas</h3><?php if (!$a['recetas']): ?><p class="muted">Sin recetas.</p><?php endif ?><ul><?php foreach($a['recetas'] as $r): ?><li><?=e($r['Medicamento'].' · '.$r['Dosis'].' · '.$r['Dias_Tratamiento'].' días')?></li><?php endforeach ?></ul></section><?php endforeach ?>
<?php if (!$historial): ?><section>Este paciente todavía no tiene atenciones registradas.</section><?php endif ?>
<?php endif; mostrarPie(); ?>
