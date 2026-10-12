<?php
require_once __DIR__ . '/comun.php';
exigirRol([3]);
actualizarVencidas($pdo);
$centros = $pdo->query('SELECT Codigo_Centro, Centro, Total_Citas, Citas_No_Asistio, Pct_Inasistencia FROM v_panel_centros ORDER BY Centro')->fetchAll();
$diagnosticos = $pdo->query('SELECT d.Codigo_CIE10, d.Descripcion, COUNT(*) AS Frecuencia FROM Atencion_Diagnostico ad JOIN Diagnostico d ON d.Codigo_CIE10 = ad.Codigo_CIE10 GROUP BY d.Codigo_CIE10, d.Descripcion ORDER BY Frecuencia DESC, d.Codigo_CIE10 LIMIT 5')->fetchAll();
mostrarCabecera('Panel de gestión');
?>
<section><h2>Estadísticas por centro médico</h2><div class="tablewrap"><table><thead><tr><th>Centro</th><th>Total citas</th><th>No asistió</th><th>Inasistencia</th></tr></thead><tbody><?php foreach($centros as $c): ?><tr><td><?=e($c['Centro'])?> <span class="muted"><?=e($c['Codigo_Centro'])?></span></td><td><?=e($c['Total_Citas'])?></td><td><?=e($c['Citas_No_Asistio'])?></td><td><strong><?=e($c['Pct_Inasistencia'])?>%</strong></td></tr><?php endforeach ?></tbody></table></div><p class="hint">Porcentaje calculado sobre todas las citas registradas del centro mediante fn_pct_inasistencia (función SQL y vista).</p></section>
<section><h2>Top 5 diagnósticos de la red</h2><div class="tablewrap"><table><thead><tr><th>CIE-10</th><th>Diagnóstico</th><th>Registros</th></tr></thead><tbody><?php foreach($diagnosticos as $d): ?><tr><td><?=e($d['Codigo_CIE10'])?></td><td><?=e($d['Descripcion'])?></td><td><?=e($d['Frecuencia'])?></td></tr><?php endforeach ?></tbody></table></div></section>
<?php mostrarPie(); ?>
