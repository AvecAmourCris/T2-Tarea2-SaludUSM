<?php
require_once __DIR__ . '/comun.php';
exigirRol([2]);
actualizarVencidas($pdo);
$error = '';
$idCita = filter_var($_POST['id_cita'] ?? $_GET['id_cita'] ?? null,FILTER_VALIDATE_INT) ?: 0;
$acciones = ['guardar_atencion','eliminar_atencion','agregar_diagnostico','quitar_diagnostico','guardar_receta','eliminar_receta'];
// Se impide que un médico manipule una atención o receta ajena, aun cambiando el ID en el HTML.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verificarCsrf();
        $accion = (string)($_POST['accion'] ?? '');
        if (!in_array($accion,$acciones,true) || $idCita<1) { throw new RuntimeException('Solicitud inválida.'); }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT c.ID_Cita, c.RUT_Medico, ec.Nombre AS Estado, a.ID_Atencion FROM Cita c JOIN Estado_Cita ec ON ec.ID_Estado_Cita=c.ID_Estado_Cita LEFT JOIN Atencion a ON a.ID_Cita=c.ID_Cita WHERE c.ID_Cita=? AND c.RUT_Medico=? FOR UPDATE');
        $stmt->execute([$idCita,rutActual()]);
        $cita = $stmt->fetch();
        if (!$cita) { throw new RuntimeException('La cita no pertenece a tu agenda.'); }
        if ($cita['Estado']!=='Atendida') { throw new RuntimeException('Solo puedes registrar atenciones en citas con estado Atendida.'); }
        $idAtencion = (int)($cita['ID_Atencion'] ?? 0);
        if ($accion==='guardar_atencion') {
            $motivo=trim((string)($_POST['motivo'] ?? ''));
            $observaciones=trim((string)($_POST['observaciones'] ?? ''));
            if ($motivo==='' || strlen($motivo)>500 || $observaciones==='') { throw new RuntimeException('El motivo y las observaciones son obligatorios; máximo 500 caracteres para el motivo.'); }
            if ($idAtencion) { $pdo->prepare('UPDATE Atencion SET Motivo_Consulta=?, Observaciones_Clinicas=? WHERE ID_Atencion=?')->execute([$motivo,$observaciones,$idAtencion]); }
            else { $pdo->prepare('INSERT INTO Atencion (ID_Cita,Motivo_Consulta,Observaciones_Clinicas) VALUES (?,?,?)')->execute([$idCita,$motivo,$observaciones]); }
            $mensaje='Ficha de atención guardada.';
        } elseif ($accion==='eliminar_atencion') {
            if (!$idAtencion) { throw new RuntimeException('La atención no existe.'); }
            $pdo->prepare('DELETE FROM Atencion WHERE ID_Atencion=?')->execute([$idAtencion]);
            $mensaje='Atención y sus diagnósticos y recetas eliminados.';
        } else {
            if (!$idAtencion) { throw new RuntimeException('Primero guarda el motivo de consulta y las observaciones.'); }
            if ($accion==='agregar_diagnostico') {
                $cie = strtoupper(trim((string)($_POST['cie10'] ?? '')));
                if ($cie==='') { throw new RuntimeException('Selecciona un diagnóstico.'); }
                $pdo->prepare('INSERT INTO Atencion_Diagnostico (ID_Atencion,Codigo_CIE10) VALUES (?,?)')->execute([$idAtencion,$cie]);
                $mensaje='Diagnóstico agregado.';
            } elseif ($accion==='quitar_diagnostico') {
                $cie = strtoupper(trim((string)($_POST['cie10'] ?? '')));
                $pdo->prepare('DELETE FROM Atencion_Diagnostico WHERE ID_Atencion=? AND Codigo_CIE10=?')->execute([$idAtencion,$cie]);
                $mensaje='Diagnóstico retirado.';
            } elseif ($accion==='guardar_receta') {
                $idReceta=filter_var($_POST['id_receta'] ?? null,FILTER_VALIDATE_INT) ?: 0;
                $medicamento=trim((string)($_POST['medicamento'] ?? ''));
                $dosis=trim((string)($_POST['dosis'] ?? ''));
                $dias=filter_var($_POST['dias'] ?? null,FILTER_VALIDATE_INT);
                if (!$medicamento || strlen($medicamento)>100 || !$dosis || strlen($dosis)>255 || !$dias || $dias<1) { throw new RuntimeException('Medicamento, dosis y días positivos son obligatorios.'); }
                if ($idReceta) {
                    $q=$pdo->prepare('UPDATE Receta SET Medicamento=?, Dosis=?, Dias_Tratamiento=? WHERE ID_Receta=? AND ID_Atencion=?');
                    $q->execute([$medicamento,$dosis,$dias,$idReceta,$idAtencion]);
                    if ($q->rowCount()===0) {
                        $q=$pdo->prepare('SELECT 1 FROM Receta WHERE ID_Receta=? AND ID_Atencion=?'); $q->execute([$idReceta,$idAtencion]);
                        if (!$q->fetch()) { throw new RuntimeException('La receta no pertenece a esta atención.'); }
                    }
                } else { $pdo->prepare('INSERT INTO Receta (Medicamento,Dosis,Dias_Tratamiento,ID_Atencion) VALUES (?,?,?,?)')->execute([$medicamento,$dosis,$dias,$idAtencion]); }
                $mensaje='Receta guardada.';
            } elseif ($accion==='eliminar_receta') {
                $idReceta=filter_var($_POST['id_receta'] ?? null,FILTER_VALIDATE_INT) ?: 0;
                $q=$pdo->prepare('DELETE FROM Receta WHERE ID_Receta=? AND ID_Atencion=?');
                $q->execute([$idReceta,$idAtencion]);
                if (!$q->rowCount()) { throw new RuntimeException('No se encontró esa receta en tu atención.'); }
                $mensaje='Receta eliminada.';
            }
        }
        $pdo->commit(); aviso($mensaje); salto('atenciones.php?id_cita='.$idCita);
    } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); $error=salidaError($e); }
}
$stmt=$pdo->prepare('SELECT c.ID_Cita, c.Fecha_Hora, p.Nombre_Completo AS Paciente, es.Nombre AS Especialidad, cm.Nombre AS Centro, a.ID_Atencion, a.Motivo_Consulta, a.Observaciones_Clinicas FROM Cita c JOIN Estado_Cita ec ON ec.ID_Estado_Cita=c.ID_Estado_Cita JOIN Paciente p ON p.RUT_Paciente=c.RUT_Paciente JOIN Especialidad es ON es.ID_Especialidad=c.ID_Especialidad JOIN Centro_Medico cm ON cm.Codigo_Centro=c.Codigo_Centro LEFT JOIN Atencion a ON a.ID_Cita=c.ID_Cita WHERE ec.Nombre="Atendida" AND c.RUT_Medico=? ORDER BY c.Fecha_Hora DESC LIMIT 150');
$stmt->execute([rutActual()]); $citas=$stmt->fetchAll();
$seleccion=null;
foreach ($citas as $c) { if ((int)$c['ID_Cita']===$idCita) { $seleccion=$c; break; } }
$diagnosticos=[]; $recetas=[];
if ($seleccion && $seleccion['ID_Atencion']) {
    $stmt=$pdo->prepare('SELECT ad.Codigo_CIE10, d.Descripcion FROM Atencion_Diagnostico ad JOIN Diagnostico d ON d.Codigo_CIE10=ad.Codigo_CIE10 WHERE ad.ID_Atencion=? ORDER BY ad.Codigo_CIE10');
    $stmt->execute([$seleccion['ID_Atencion']]); $diagnosticos=$stmt->fetchAll();
    $stmt=$pdo->prepare('SELECT ID_Receta, Medicamento, Dosis, Dias_Tratamiento FROM Receta WHERE ID_Atencion=? ORDER BY ID_Receta');
    $stmt->execute([$seleccion['ID_Atencion']]); $recetas=$stmt->fetchAll();
}
$catalogo=$pdo->query('SELECT Codigo_CIE10, Descripcion FROM Diagnostico ORDER BY Codigo_CIE10')->fetchAll();
mostrarCabecera('Atenciones, diagnósticos y recetas');
if($error): ?><p class="error"><?=e($error)?></p><?php endif ?>
<section><p class="hint">Selecciona una cita que hayas marcado como <strong>Atendida</strong> en tu agenda. Cada cita admite una atención, varios diagnósticos y recetas.</p><form method="get" class="row"><div class="field"><label>Cita atendida</label><select name="id_cita" required><option value="">Elige una cita</option><?php foreach($citas as $c): ?><option value="<?=e($c['ID_Cita'])?>" <?=$idCita===(int)$c['ID_Cita']?'selected':''?>>#<?=e($c['ID_Cita'])?> · <?=e($c['Fecha_Hora'])?> · <?=e($c['Paciente'])?><?= $c['ID_Atencion']?' (ficha existente)':'' ?></option><?php endforeach ?></select></div><button>Ver / editar atención</button></form></section>
<?php if ($seleccion): ?>
<section><h2>Ficha N.º <?=e($seleccion['ID_Cita'])?></h2><p><?=e($seleccion['Paciente'])?> · <?=e($seleccion['Especialidad'])?> · <?=e($seleccion['Centro'])?></p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id_cita" value="<?=e($idCita)?>"><input type="hidden" name="accion" value="guardar_atencion"><div class="field"><label>Motivo de consulta</label><textarea name="motivo" required maxlength="500"><?=e($seleccion['Motivo_Consulta'])?></textarea></div><div class="field"><label>Observaciones clínicas</label><textarea name="observaciones" required><?=e($seleccion['Observaciones_Clinicas'])?></textarea></div><p><button>Guardar ficha</button></p></form><?php if ($seleccion['ID_Atencion']): ?><form method="post" onsubmit="return confirm('¿Eliminar la ficha y todas sus recetas y diagnósticos?')"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id_cita" value="<?=e($idCita)?>"><input type="hidden" name="accion" value="eliminar_atencion"><button class="danger">Eliminar ficha completa</button></form><?php endif ?></section>
<?php if($seleccion['ID_Atencion']): ?>
<section><h2>Diagnósticos CIE-10</h2><div class="tablewrap"><table><thead><tr><th>Código</th><th>Descripción</th><th></th></tr></thead><tbody><?php foreach($diagnosticos as $d): ?><tr><td><?=e($d['Codigo_CIE10'])?></td><td><?=e($d['Descripcion'])?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id_cita" value="<?=e($idCita)?>"><input type="hidden" name="accion" value="quitar_diagnostico"><input type="hidden" name="cie10" value="<?=e($d['Codigo_CIE10'])?>"><button class="danger">Quitar</button></form></td></tr><?php endforeach ?></tbody></table></div><form method="post" class="row"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id_cita" value="<?=e($idCita)?>"><input type="hidden" name="accion" value="agregar_diagnostico"><div class="field"><label>Agregar diagnóstico</label><select name="cie10" required><option value="">Seleccione</option><?php foreach($catalogo as $d): ?><option value="<?=e($d['Codigo_CIE10'])?>"><?=e($d['Codigo_CIE10'].' · '.$d['Descripcion'])?></option><?php endforeach ?></select></div><button>Agregar</button></form></section>
<section><h2>Recetas</h2><div class="tablewrap"><table><thead><tr><th>Medicamento</th><th>Dosis</th><th>Días</th><th>Modificar</th></tr></thead><tbody><?php foreach($recetas as $r): ?><tr><td><?=e($r['Medicamento'])?></td><td><?=e($r['Dosis'])?></td><td><?=e($r['Dias_Tratamiento'])?></td><td><div class="actions"><form method="post" class="row"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id_cita" value="<?=e($idCita)?>"><input type="hidden" name="id_receta" value="<?=e($r['ID_Receta'])?>"><input type="hidden" name="accion" value="guardar_receta"><input aria-label="Medicamento" name="medicamento" value="<?=e($r['Medicamento'])?>" required maxlength="100"><input aria-label="Dosis" name="dosis" value="<?=e($r['Dosis'])?>" required maxlength="255"><input aria-label="Días" type="number" name="dias" value="<?=e($r['Dias_Tratamiento'])?>" min="1" required><button>Editar</button></form><form method="post" onsubmit="return confirm('¿Eliminar receta?')"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id_cita" value="<?=e($idCita)?>"><input type="hidden" name="id_receta" value="<?=e($r['ID_Receta'])?>"><input type="hidden" name="accion" value="eliminar_receta"><button class="danger">Eliminar</button></form></div></td></tr><?php endforeach ?></tbody></table></div><h3>Agregar receta</h3><form method="post" class="row"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id_cita" value="<?=e($idCita)?>"><input type="hidden" name="accion" value="guardar_receta"><div class="field"><label>Medicamento</label><input name="medicamento" required maxlength="100"></div><div class="field"><label>Dosis</label><input name="dosis" required maxlength="255"></div><div class="field"><label>Días</label><input type="number" name="dias" min="1" max="3650" required></div><button>Agregar</button></form></section>
<?php endif; endif; ?>
<?php mostrarPie(); ?>
