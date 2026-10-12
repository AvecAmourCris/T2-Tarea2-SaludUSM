<?php
require_once __DIR__ . '/comun.php';
exigirRol([1]);
actualizarVencidas($pdo);

$error = '';

// Solo ofrecer especialidades para las cuales hay médicos asignados a algún centro.
// Las especialidades sin profesionales siguen existiendo en el catálogo de MySQL.
$especialidades = $pdo->query(
    'SELECT DISTINCT e.ID_Especialidad, e.Nombre
     FROM Especialidad e
     INNER JOIN Medico_Especialidad me ON me.ID_Especialidad = e.ID_Especialidad
     INNER JOIN Medico_Centro mc ON mc.RUT_Medico = me.RUT_Medico
     ORDER BY e.Nombre'
)->fetchAll(PDO::FETCH_ASSOC);

$esp = (int)($_POST['especialidad'] ?? $_GET['especialidad'] ?? 0);
$centro = (string)($_POST['centro'] ?? $_GET['centro'] ?? '');
$medico = (string)($_POST['medico'] ?? $_GET['medico'] ?? '');
$fecha = (string)($_POST['fecha'] ?? $_GET['fecha'] ?? date('Y-m-d', strtotime('+1 day')));

// Validar la fecha recibida y evitar fechas mal formadas.
$validaFecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
if (!$validaFecha || $validaFecha->format('Y-m-d') !== $fecha) {
    $fecha = date('Y-m-d');
}

// No conservar una especialidad antigua o inexistente en la URL.
$idsDisponibles = array_map('intval', array_column($especialidades, 'ID_Especialidad'));
if (!in_array($esp, $idsDisponibles, true)) {
    $esp = 0;
    $centro = '';
    $medico = '';
}

// Cuando cambia la especialidad, listar SOLO centros en que atiende algún especialista.
$centros = [];
if ($esp > 0) {
    $stmtCentros = $pdo->prepare(
        'SELECT DISTINCT c.Codigo_Centro, c.Nombre
         FROM Centro_Medico c
         INNER JOIN Medico_Centro mc ON mc.Codigo_Centro = c.Codigo_Centro
         INNER JOIN Medico_Especialidad me ON me.RUT_Medico = mc.RUT_Medico
         WHERE me.ID_Especialidad = ?
         ORDER BY c.Nombre'
    );
    $stmtCentros->execute([$esp]);
    $centros = $stmtCentros->fetchAll(PDO::FETCH_ASSOC);
}

// Invalidar centros que ya no correspondan a la especialidad seleccionada.
$centrosDisponibles = array_column($centros, 'Codigo_Centro');
if (!in_array($centro, $centrosDisponibles, true)) {
    $centro = '';
    $medico = '';
}

// Mostrar SOLO los médicos que tienen la especialidad Y atienden en ese centro.
$opciones = [];
if ($esp > 0 && $centro !== '') {
    $stmtMedicos = $pdo->prepare(
        'SELECT DISTINCT m.RUT_Medico, m.Nombre_Completo
         FROM Medico m
         INNER JOIN Medico_Especialidad me ON me.RUT_Medico = m.RUT_Medico
         INNER JOIN Medico_Centro mc ON mc.RUT_Medico = m.RUT_Medico
         WHERE me.ID_Especialidad = ? AND mc.Codigo_Centro = ?
         ORDER BY m.Nombre_Completo'
    );
    $stmtMedicos->execute([$esp, $centro]);
    $opciones = $stmtMedicos->fetchAll(PDO::FETCH_ASSOC);
}

// Evitar que quede guardado un médico de la selección anterior (URL vieja).
$medicosDisponibles = array_column($opciones, 'RUT_Medico');
if (!in_array($medico, $medicosDisponibles, true)) {
    $medico = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verificarCsrf();
        if (($_POST['accion'] ?? '') !== 'agendar') {
            throw new RuntimeException('Acción no permitida.');
        }
        if ($esp === 0 || $centro === '' || $medico === '') {
            throw new RuntimeException('Selecciona una especialidad, un centro y un médico válidos.');
        }
        $hora = trim((string)($_POST['hora'] ?? ''));
        $fechaHora = fechaMySQL($fecha . 'T' . $hora);
        $resultado = procedimiento(
            $pdo,
            'sp_agendar_cita',
            [rutActual(), $medico, $centro, $esp, $fechaHora],
            ['nueva_cita', 'operacion_ok', 'mensaje_operacion']
        );
        if ((int)$resultado['operacion_ok'] !== 1) {
            throw new RuntimeException($resultado['mensaje_operacion'] ?? 'No se pudo agendar.');
        }
        aviso('Reserva creada. Número de cita: ' . $resultado['nueva_cita']);
        salto('mis_citas.php');
    } catch (Throwable $e) {
        $error = salidaError($e);
    }
}

$horarios = [];
if ($esp > 0 && $centro !== '' && $medico !== '' && $fecha >= date('Y-m-d')) {
    $consultaHora = $pdo->prepare('SELECT fn_validar_horario(?, ?, ?, ?, ?, NULL)');
    for ($minutos = 8 * 60; $minutos <= 17 * 60 + 45; $minutos += 15) {
        $hora = sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
        $consultaHora->execute([rutActual(), $medico, $centro, $esp, $fecha . ' ' . $hora . ':00']);
        if ($consultaHora->fetchColumn() === null) {
            $horarios[] = $hora;
        }
        $consultaHora->closeCursor();
    }
}

mostrarCabecera('Agendar hora médica');
if ($error): ?>
    <p class="error"><?= e($error) ?></p>
<?php endif; ?>

<section>
    <p class="hint">Citas de 15 minutos, de lunes a viernes, entre 08:00 y 18:00. Selecciona una especialidad para ver los centros donde realmente hay médicos disponibles.</p>
    <form method="get" class="grid" id="filtros-agenda">
        <div class="field">
            <label for="especialidad">Especialidad</label>
            <select name="especialidad" id="especialidad">
                <option value="">Seleccionar especialidad</option>
                <?php foreach ($especialidades as $v): ?>
                    <option value="<?= e($v['ID_Especialidad']) ?>" <?= $esp === (int)$v['ID_Especialidad'] ? 'selected' : '' ?>><?= e($v['Nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="centro">Centro</label>
            <select name="centro" id="centro" <?= $esp === 0 ? 'disabled' : '' ?>>
                <option value=""><?= $esp > 0 ? 'Seleccionar centro' : 'Primero elige una especialidad' ?></option>
                <?php foreach ($centros as $v): ?>
                    <option value="<?= e($v['Codigo_Centro']) ?>" <?= $centro === $v['Codigo_Centro'] ? 'selected' : '' ?>><?= e($v['Nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="medico">Médico</label>
            <select name="medico" id="medico" <?= $centro === '' ? 'disabled' : '' ?>>
                <option value=""><?= $centro !== '' ? 'Seleccionar médico' : 'Primero elige un centro' ?></option>
                <?php foreach ($opciones as $v): ?>
                    <option value="<?= e($v['RUT_Medico']) ?>" <?= $medico === $v['RUT_Medico'] ? 'selected' : '' ?>><?= e($v['Nombre_Completo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="fecha">Fecha</label>
            <input type="date" name="fecha" id="fecha" min="<?= date('Y-m-d') ?>" value="<?= e($fecha) ?>" required>
        </div>
        <div><button type="submit">Ver disponibilidad</button></div>
    </form>
    <?php if ($esp > 0 && $centro === ''): ?>
        <p class="hint">Elige uno de los centros de la lista: todos cuentan con al menos un médico de esta especialidad.</p>
    <?php elseif ($esp > 0 && $centro !== '' && !$opciones): ?>
        <p class="error">No hay médicos asignados a esta combinación. Selecciona otro centro.</p>
    <?php elseif ($esp > 0 && $centro !== '' && $medico === ''): ?>
        <p class="hint">Selecciona un médico para ver sus horarios disponibles.</p>
    <?php endif; ?>
</section>

<?php if ($esp > 0 && $centro !== '' && $medico !== ''): ?>
    <section>
        <h2>Horas disponibles el <?= e($fecha) ?></h2>
        <?php if ($horarios): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="accion" value="agendar">
                <input type="hidden" name="especialidad" value="<?= e($esp) ?>">
                <input type="hidden" name="centro" value="<?= e($centro) ?>">
                <input type="hidden" name="medico" value="<?= e($medico) ?>">
                <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
                <div class="row">
                    <label for="hora">Horario</label>
                    <select name="hora" id="hora" required>
                        <?php foreach ($horarios as $h): ?>
                            <option value="<?= e($h) ?>"><?= e($h) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Confirmar reserva</button>
                </div>
            </form>
        <?php else: ?>
            <p>No existen horarios disponibles para esta combinación. Puedes cambiar fecha, médico o centro.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<script>
// Los filtros se actualizan automáticamente; no hay que pulsar «Ver disponibilidad»
// entre seleccionar especialidad, centro y médico.
const filtrosAgenda = document.getElementById('filtros-agenda');
const campoEspecialidad = document.getElementById('especialidad');
const campoCentro = document.getElementById('centro');
const campoMedico = document.getElementById('medico');

campoEspecialidad.addEventListener('change', function () {
    campoCentro.value = '';
    campoMedico.value = '';
    filtrosAgenda.submit();
});
campoCentro.addEventListener('change', function () {
    campoMedico.value = '';
    filtrosAgenda.submit();
});
campoMedico.addEventListener('change', function () {
    filtrosAgenda.submit();
});
</script>

<?php mostrarPie(); ?>
