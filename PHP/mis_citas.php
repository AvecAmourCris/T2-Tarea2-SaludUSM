<?php
require_once __DIR__ . '/comun.php';
exigirRol([1]);
actualizarVencidas($pdo);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verificarCsrf();
        $id = filter_var($_POST['id_cita'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) { throw new RuntimeException('Selecciona una cita válida.'); }
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'cancelar') {
            $r = procedimiento($pdo,'sp_cancelar_cita',[$id,rutActual()],['operacion_ok','mensaje_operacion']);
        } elseif ($accion === 'reprogramar') {
            $nuevaFecha = fechaMySQL((string)($_POST['nueva_fecha'] ?? ''));
            $r = procedimiento($pdo,'sp_reprogramar_cita',[$id,rutActual(),$nuevaFecha],['operacion_ok','mensaje_operacion']);
        } else { throw new RuntimeException('Acción desconocida.'); }
        if ((int)$r['operacion_ok'] !== 1) { throw new RuntimeException($r['mensaje_operacion'] ?? 'No se pudo actualizar.'); }
        aviso($r['mensaje_operacion']); salto('mis_citas.php');
    } catch (Throwable $e) { $error = salidaError($e); }
}
$stmt = $pdo->prepare('SELECT * FROM v_citas_detalle WHERE RUT_Paciente = ? ORDER BY Fecha_Hora DESC, ID_Cita DESC');
$stmt->execute([rutActual()]);
$citas = $stmt->fetchAll();
$proximas = []; $historicas = [];
foreach ($citas as $c) {
    if ($c['Fecha_Hora'] >= date('Y-m-d H:i:s') && !in_array($c['Estado'], ['Atendida','No Asistió','Cancelada'],true)) { $proximas[] = $c; }
    else { $historicas[] = $c; }
}
mostrarCabecera('Mis citas');
if($error): ?><p class="error"><?=e($error)?></p><?php endif ?>
<p><a class="btn" href="agendar.php">+ Agendar nueva hora</a></p>
<?php foreach (['Próximas' => $proximas, 'Históricas' => $historicas] as $titulo => $grupo): ?>
<section><h2><?=e($titulo)?> (<?=count($grupo)?>)</h2><div class="tablewrap"><table><thead><tr><th>N.º</th><th>Fecha / hora</th><th>Médico / Especialidad</th><th>Centro</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($grupo as $c): ?><tr><td><?=e($c['ID_Cita'])?></td><td><?=e($c['Fecha_Hora'])?></td><td><?=e($c['Medico'])?><br><span class="muted"><?=e($c['Especialidad'])?></span></td><td><?=e($c['Centro'])?></td><td><span class="tag"><?=e($c['Estado'])?></span></td><td>
<?php if($titulo==='Próximas'): ?><form method="post" class="row"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id_cita" value="<?=e($c['ID_Cita'])?>"><input type="hidden" name="accion" value="reprogramar"><div class="field"><label>Nueva fecha/hora</label><input type="datetime-local" name="nueva_fecha" min="<?=date('Y-m-d\TH:i')?>" required></div><button>Reprogramar</button></form><form method="post" onsubmit="return confirm('¿Cancelar esta cita?')"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id_cita" value="<?=e($c['ID_Cita'])?>"><input type="hidden" name="accion" value="cancelar"><button class="danger">Cancelar</button></form><?php else: ?><span class="muted">Sin cambios</span><?php endif ?>
</td></tr><?php endforeach ?><?php if(!$grupo): ?><tr><td colspan="6">No hay citas en esta sección.</td></tr><?php endif ?></tbody></table></div></section><?php endforeach ?>
<?php mostrarPie(); ?>
