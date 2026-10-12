<?php
require_once __DIR__ . '/comun.php';
exigirRol([1,2,3]);
actualizarVencidas($pdo);
$campos = [
 'desde' => trim((string)($_GET['desde'] ?? '')),
 'hasta' => trim((string)($_GET['hasta'] ?? '')),
 'centro' => trim((string)($_GET['centro'] ?? '')),
 'region' => trim((string)($_GET['region'] ?? '')),
 'especialidad' => trim((string)($_GET['especialidad'] ?? '')),
 'medico' => trim((string)($_GET['medico'] ?? '')),
 'estado' => trim((string)($_GET['estado'] ?? '')),
 'prevision' => trim((string)($_GET['prevision'] ?? '')),
];
$opciones = [
 'centro' => $pdo->query('SELECT Codigo_Centro AS id, Nombre AS nombre FROM Centro_Medico ORDER BY Nombre')->fetchAll(),
 'region' => $pdo->query('SELECT ID_Region AS id, Nombre AS nombre FROM Region ORDER BY Nombre')->fetchAll(),
 'especialidad' => $pdo->query('SELECT ID_Especialidad AS id, Nombre AS nombre FROM Especialidad ORDER BY Nombre')->fetchAll(),
 'medico' => $pdo->query('SELECT RUT_Medico AS id, Nombre_Completo AS nombre FROM Medico ORDER BY Nombre_Completo')->fetchAll(),
 'estado' => $pdo->query('SELECT ID_Estado_Cita AS id, Nombre AS nombre FROM Estado_Cita ORDER BY ID_Estado_Cita')->fetchAll(),
 'prevision' => $pdo->query('SELECT ID_Prevision AS id, Nombre AS nombre FROM Prevision ORDER BY Nombre')->fetchAll(),
];
$where=[]; $params=[];
if (rol()===1) { $where[]='RUT_Paciente = ?'; $params[]=rutActual(); }
elseif (rol()===2) { $where[]='RUT_Medico = ?'; $params[]=rutActual(); }
$mapa = ['centro'=>'Codigo_Centro','region'=>'ID_Region','especialidad'=>'ID_Especialidad','medico'=>'RUT_Medico','estado'=>'ID_Estado_Cita','prevision'=>'ID_Prevision'];
foreach ($mapa as $campo=>$columna) {
    if ($campos[$campo] !== '') { $where[] = $columna . ' = ?'; $params[] = $campos[$campo]; }
}
$error = '';
foreach (['desde','hasta'] as $campo) {
    if ($campos[$campo] !== '') {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d',$campos[$campo]);
        if (!$d || $d->format('Y-m-d') !== $campos[$campo]) { $error = 'Fecha inválida.'; break; }
        $where[] = 'Fecha ' . ($campo==='desde' ? '>=' : '<=') . ' ?'; $params[] = $campos[$campo];
    }
}
$citas=[];
if ($error === '') {
    $sql = 'SELECT ID_Cita, Fecha_Hora, Paciente, Medico, Especialidad, Centro, Estado FROM v_citas_detalle';
    if ($where) { $sql .= ' WHERE ' . implode(' AND ',$where); }
    $sql .= ' ORDER BY Fecha_Hora DESC, ID_Cita DESC LIMIT 250';
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $citas = $stmt->fetchAll();
}
mostrarCabecera('Búsqueda avanzada de citas');
if($error): ?><p class="error"><?=e($error)?></p><?php endif ?>
<section><form method="get"><div class="grid"><div class="field"><label>Desde</label><input type="date" name="desde" value="<?=e($campos['desde'])?>"></div><div class="field"><label>Hasta</label><input type="date" name="hasta" value="<?=e($campos['hasta'])?>"></div>
<?php foreach ($opciones as $campo => $valores): ?><div class="field"><label><?=e(ucfirst($campo))?></label><select name="<?=e($campo)?>"><option value="">Todos</option><?php foreach($valores as $v): ?><option value="<?=e($v['id'])?>" <?=$campos[$campo]===(string)$v['id']?'selected':''?>><?=e($v['nombre'])?></option><?php endforeach ?></select></div><?php endforeach ?></div><p class="row"><button>Filtrar</button><a class="btn secondary" href="busqueda_avanzada.php">Limpiar filtros</a></p></form></section>
<section><h2>Citas encontradas: <?=count($citas)?></h2><p class="hint">Se muestran hasta 250 resultados. Pacientes y médicos ven únicamente sus propias citas; el administrador puede ver todas.</p><div class="tablewrap"><table><thead><tr><th>Cita</th><th>Fecha y hora</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Centro</th><th>Estado</th></tr></thead><tbody><?php foreach ($citas as $c): ?><tr><td><?=e($c['ID_Cita'])?></td><td><?=e($c['Fecha_Hora'])?></td><td><?=e($c['Paciente'])?></td><td><?=e($c['Medico'])?></td><td><?=e($c['Especialidad'])?></td><td><?=e($c['Centro'])?></td><td><span class="tag"><?=e($c['Estado'])?></span></td></tr><?php endforeach ?><?php if (!$citas): ?><tr><td colspan="7">No hay resultados.</td></tr><?php endif ?></tbody></table></div></section>
<?php mostrarPie(); ?>
