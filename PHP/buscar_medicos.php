<?php
require_once __DIR__ . '/comun.php';
exigirRol([1,2,3]);
$texto = trim((string)($_GET['q'] ?? ''));
$consulta = $pdo->prepare('SELECT RUT_Medico, Medico, Email_Institucional, Especialidades, Centros FROM v_medicos_detalle WHERE Medico LIKE ? OR Especialidades LIKE ? ORDER BY Medico LIMIT 100');
$consulta->execute(['%' . $texto . '%','%' . $texto . '%']);
$medicos = $consulta->fetchAll();
mostrarCabecera('Buscar médicos');
?>
<section><form method="get" class="row"><div class="field"><label for="q">Nombre o especialidad</label><input name="q" id="q" value="<?=e($texto)?>" maxlength="100" placeholder="Ej.: cardiología"></div><button>Buscar</button></form></section>
<section><h2>Resultados (<?= count($medicos) ?>)</h2><div class="tablewrap"><table><thead><tr><th>Médico</th><th>Especialidades</th><th>Centros</th><th>Acción</th></tr></thead><tbody>
<?php foreach ($medicos as $m): ?><tr><td><?=e($m['Medico'])?></td><td><?=e($m['Especialidades'])?></td><td><?=e($m['Centros'])?></td><td><?php if(rol()===1): ?><a class="btn" href="agendar.php?medico=<?=rawurlencode($m['RUT_Medico'])?>">Agendar</a><?php endif ?></td></tr><?php endforeach; ?>
<?php if (!$medicos): ?><tr><td colspan="4">No se encontraron médicos.</td></tr><?php endif ?></tbody></table></div></section>
<?php mostrarPie(); ?>
