<?php
session_start();
date_default_timezone_set('America/Santiago');

if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 2) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/db.php';

function escapar($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$rut = $_SESSION['rut'];
$error = '';
$mensaje = $_SESSION['mensaje_agenda'] ?? '';
unset($_SESSION['mensaje_agenda']);

$fecha = $_POST['fecha'] ?? $_GET['fecha'] ?? date('Y-m-d');

if (!is_string($fecha)) {
    $fecha = '';
}

$fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
    $fecha = date('Y-m-d');
    $error = 'La fecha no es válida. Se muestra el día actual.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    try {
        if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
            throw new RuntimeException(
                'El formulario expiró. Recarga la página.'
            );
        }

        $idCita = filter_var(
            $_POST['id_cita'] ?? '',
            FILTER_VALIDATE_INT
        );

        $nuevoEstado = $_POST['estado'] ?? '';

        if (!$idCita || !in_array(
            $nuevoEstado,
            ['Confirmada', 'Atendida', 'No Asistió'],
            true
        )) {
            throw new RuntimeException('La solicitud no es válida.');
        }

        $pdo->beginTransaction();

        // El RUT viene de la sesión, no del formulario.
        $consulta = $pdo->prepare(
            'SELECT c.ID_Cita, ec.Nombre AS Estado
             FROM Cita c
             JOIN Estado_Cita ec
               ON ec.ID_Estado_Cita = c.ID_Estado_Cita
             WHERE c.ID_Cita = ? AND c.RUT_Medico = ?
             FOR UPDATE'
        );
        $consulta->execute([$idCita, $rut]);
        $cita = $consulta->fetch();

        if (!$cita) {
            throw new RuntimeException(
                'La cita no existe o no pertenece a tu agenda.'
            );
        }

        // Supuesto: una cita cancelada no se reactiva desde la agenda.
        if ($cita['Estado'] === 'Cancelada') {
            throw new RuntimeException(
                'No se puede cambiar el estado de una cita cancelada.'
            );
        }

        $consulta = $pdo->prepare(
            'SELECT ID_Estado_Cita FROM Estado_Cita WHERE Nombre = ?'
        );
        $consulta->execute([$nuevoEstado]);
        $idEstado = $consulta->fetchColumn();

        if ($idEstado === false) {
            throw new RuntimeException(
                'El estado solicitado no existe en la base de datos.'
            );
        }

        $consulta = $pdo->prepare(
            'UPDATE Cita SET ID_Estado_Cita = ?
             WHERE ID_Cita = ? AND RUT_Medico = ?'
        );
        $consulta->execute([$idEstado, $idCita, $rut]);

        $pdo->commit();

        $_SESSION['mensaje_agenda'] = 'Estado actualizado correctamente.';
        header('Location: agenda.php?fecha=' . urlencode($fecha));
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());

        // Incluye el mensaje del trigger que protege citas con atención.
        $error = $e->getCode() === '45000'
            ? ($e->errorInfo[2] ?? 'La operación infringe una regla.')
            : 'No se pudo actualizar el estado de la cita.';
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

$consulta = $pdo->prepare(
    'SELECT ID_Cita, Hora, Paciente, Prevision,
            Especialidad, Centro, Estado
     FROM v_citas_detalle
     WHERE RUT_Medico = ? AND Fecha = ?
     ORDER BY Hora, ID_Cita'
);
$consulta->execute([$rut, $fecha]);
$citas = $consulta->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi agenda | SaludUSM</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f6fa;
            color: #203047;
            margin: 0;
            padding: 24px;
        }
        main { max-width: 1200px; margin: auto; }
        section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-top: 24px;
        }
        a { color: #0056b3; }
        input, select, button {
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #aebdce;
        }
        button {
            background: #0056b3;
            color: white;
            border: 0;
            cursor: pointer;
        }
        .mensaje { background: #e1f4e8; padding: 14px; }
        .error { background: #ffe5e5; padding: 14px; }
        .tabla { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #dde4ed;
            vertical-align: top;
        }
        th { background: #edf2f8; }
        .estado { display: flex; gap: 8px; }
    </style>
</head>
<body>
<main>
    <nav>
        <a href="dashboard_medico.php">Volver al panel</a>
    </nav>

    <h1>Mi agenda</h1>

    <?php if ($mensaje !== ''): ?>
        <p class="mensaje" role="status"><?= escapar($mensaje) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error" role="alert"><?= escapar($error) ?></p>
    <?php endif; ?>

    <section>
        <form method="get" action="agenda.php">
            <label for="fecha">Día de atención</label>
            <input id="fecha" name="fecha" type="date" required
                   value="<?= escapar($fecha) ?>">
            <button type="submit">Ver agenda</button>
        </form>
    </section>

    <section>
        <h2>Citas del <?= escapar(date('d-m-Y', strtotime($fecha))) ?></h2>

        <div class="tabla">
            <table>
                <thead>
                <tr>
                    <th>Hora</th>
                    <th>Paciente</th>
                    <th>Previsión</th>
                    <th>Especialidad</th>
                    <th>Centro</th>
                    <th>Estado</th>
                    <th>Cambiar estado</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($citas as $cita): ?>
                    <tr>
                        <td><?= escapar(substr($cita['Hora'], 0, 5)) ?></td>
                        <td><?= escapar($cita['Paciente']) ?></td>
                        <td><?= escapar($cita['Prevision']) ?></td>
                        <td><?= escapar($cita['Especialidad']) ?></td>
                        <td><?= escapar($cita['Centro']) ?></td>
                        <td><?= escapar($cita['Estado']) ?></td>
                        <td>
                            <?php if ($cita['Estado'] !== 'Cancelada'): ?>
                                <form class="estado" method="post"
                                      action="agenda.php">
                                    <input type="hidden" name="csrf"
                                           value="<?= escapar($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="fecha"
                                           value="<?= escapar($fecha) ?>">
                                    <input type="hidden" name="id_cita"
                                           value="<?= escapar($cita['ID_Cita']) ?>">

                                    <select name="estado"
                                            aria-label="Nuevo estado de la cita"
                                            required>
                                        <option value="">Selecciona</option>

                                        <?php foreach ([
                                            'Confirmada',
                                            'Atendida',
                                            'No Asistió'
                                        ] as $estado): ?>
                                            <option
                                                value="<?= escapar($estado) ?>"
                                                <?= $estado === $cita['Estado']
                                                    ? 'selected' : '' ?>>
                                                <?= escapar($estado) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit">Guardar</button>
                                </form>
                            <?php else: ?>
                                Cita cancelada
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$citas): ?>
                    <tr>
                        <td colspan="7">
                            No tienes citas para este día.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>