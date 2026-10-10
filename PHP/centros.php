<?php
session_start();

if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 3) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/db.php';

function escapar($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// Token para comprobar que los formularios vienen de esta sesión.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$mensaje = $_SESSION['mensaje_centros'] ?? '';
unset($_SESSION['mensaje_centros']);

$formulario = [
    'Codigo_Centro' => '',
    'Nombre' => '',
    'ID_Comuna' => ''
];

$editando = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $codigo = trim($_POST['codigo'] ?? '');

    try {
        if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
            throw new RuntimeException(
                'El formulario expiró. Recarga la página e inténtalo otra vez.'
            );
        }

        if ($accion === 'eliminar') {
            // Conservamos los centros asociados a médicos o citas.
            $consulta = $pdo->prepare(
                'SELECT
                    (SELECT COUNT(*) FROM Medico_Centro
                     WHERE Codigo_Centro = ?) AS medicos,
                    (SELECT COUNT(*) FROM Cita
                     WHERE Codigo_Centro = ?) AS citas'
            );
            $consulta->execute([$codigo, $codigo]);
            $uso = $consulta->fetch();

            if ($uso['medicos'] > 0 || $uso['citas'] > 0) {
                throw new RuntimeException(
                    'No se puede eliminar: el centro tiene médicos o citas asociados.'
                );
            }

            $consulta = $pdo->prepare(
                'DELETE FROM Centro_Medico WHERE Codigo_Centro = ?'
            );
            $consulta->execute([$codigo]);

            if ($consulta->rowCount() === 0) {
                throw new RuntimeException('El centro ya no existe.');
            }

            $_SESSION['mensaje_centros'] = 'Centro eliminado correctamente.';
        } elseif ($accion === 'crear' || $accion === 'editar') {
            $nombre = trim($_POST['nombre'] ?? '');
            $comuna = filter_var(
                $_POST['comuna'] ?? '',
                FILTER_VALIDATE_INT
            );

            $formulario = [
                'Codigo_Centro' => $codigo,
                'Nombre' => $nombre,
                'ID_Comuna' => $comuna
            ];
            $editando = $accion === 'editar';

            if ($codigo === '' || $nombre === '' || !$comuna) {
                throw new RuntimeException('Completa todos los campos.');
            }

            $consulta = $pdo->prepare(
                'SELECT ID_Comuna FROM Comuna WHERE ID_Comuna = ?'
            );
            $consulta->execute([$comuna]);

            if (!$consulta->fetch()) {
                throw new RuntimeException('Selecciona una comuna válida.');
            }

            if ($accion === 'crear') {
                $consulta = $pdo->prepare(
                    'INSERT INTO Centro_Medico
                     (Codigo_Centro, Nombre, ID_Comuna)
                     VALUES (?, ?, ?)'
                );
                $consulta->execute([$codigo, $nombre, $comuna]);

                $_SESSION['mensaje_centros'] = 'Centro creado correctamente.';
            } else {
                $consulta = $pdo->prepare(
                    'SELECT Codigo_Centro FROM Centro_Medico
                     WHERE Codigo_Centro = ?'
                );
                $consulta->execute([$codigo]);

                if (!$consulta->fetch()) {
                    throw new RuntimeException('El centro ya no existe.');
                }

                $consulta = $pdo->prepare(
                    'UPDATE Centro_Medico
                     SET Nombre = ?, ID_Comuna = ?
                     WHERE Codigo_Centro = ?'
                );
                $consulta->execute([$nombre, $comuna, $codigo]);

                $_SESSION['mensaje_centros'] = 'Centro actualizado correctamente.';
            }
        } else {
            throw new RuntimeException('Acción no válida.');
        }

        // Evita repetir la operación al actualizar la página.
        header('Location: centros.php');
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $error = 'No se pudo guardar el cambio. Revisa que el código no esté '
               . 'repetido y que el centro no tenga registros asociados.';
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

// Cargar el centro seleccionado para editar.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['editar'])) {
    $consulta = $pdo->prepare(
        'SELECT Codigo_Centro, Nombre, ID_Comuna
         FROM Centro_Medico WHERE Codigo_Centro = ?'
    );
    $consulta->execute([$_GET['editar']]);
    $centro = $consulta->fetch();

    if ($centro) {
        $formulario = $centro;
        $editando = true;
    } else {
        $error = 'El centro solicitado no existe.';
    }
}

$comunas = $pdo->query(
    'SELECT c.ID_Comuna, c.Nombre AS Comuna, r.Nombre AS Region
     FROM Comuna c
     JOIN Region r ON r.ID_Region = c.ID_Region
     ORDER BY r.Nombre, c.Nombre'
)->fetchAll();

$centros = $pdo->query(
    'SELECT cm.Codigo_Centro, cm.Nombre,
            c.Nombre AS Comuna, r.Nombre AS Region
     FROM Centro_Medico cm
     JOIN Comuna c ON c.ID_Comuna = cm.ID_Comuna
     JOIN Region r ON r.ID_Region = c.ID_Region
     ORDER BY cm.Nombre'
)->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Centros médicos | SaludUSM</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f6fa;
            color: #203047;
            margin: 0;
            padding: 24px;
        }
        main { max-width: 1100px; margin: auto; }
        section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin: 24px 0;
        }
        a { color: #0056b3; }
        label { display: block; margin: 16px 0 6px; }
        input, select {
            box-sizing: border-box;
            width: 100%;
            padding: 10px;
            border: 1px solid #aebdce;
            border-radius: 6px;
        }
        button {
            background: #0056b3;
            color: white;
            border: 0;
            border-radius: 6px;
            padding: 10px 16px;
            cursor: pointer;
        }
        .guardar { margin-top: 20px; }
        .eliminar { background: #a52232; }
        .mensaje { background: #e1f4e8; padding: 14px; }
        .error { background: #ffe5e5; padding: 14px; }
        .tabla { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #dde4ed;
        }
        th { background: #edf2f8; }
        .acciones {
            display: flex;
            align-items: center;
            gap: 12px;
        }
    </style>
</head>
<body>
<main>
    <nav>
        <a href="dashboard_admin.php">Volver al panel</a>
    </nav>

    <h1>Centros médicos</h1>

    <?php if ($mensaje !== ''): ?>
        <p class="mensaje" role="status"><?= escapar($mensaje) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error" role="alert"><?= escapar($error) ?></p>
    <?php endif; ?>

    <section>
        <h2><?= $editando ? 'Editar centro' : 'Crear centro' ?></h2>

        <form method="post" action="centros.php">
            <input type="hidden" name="csrf"
                   value="<?= escapar($_SESSION['csrf']) ?>">

            <input type="hidden" name="accion"
                   value="<?= $editando ? 'editar' : 'crear' ?>">

            <label for="codigo">Código del centro</label>
            <input id="codigo" name="codigo" maxlength="20" required
                   value="<?= escapar($formulario['Codigo_Centro']) ?>"
                   <?= $editando ? 'readonly' : '' ?>>

            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" maxlength="100" required
                   value="<?= escapar($formulario['Nombre']) ?>>

            <label for="comuna">Comuna y región</label>
            <select id="comuna" name="comuna" required>
                <option value="">Selecciona una comuna</option>

                <?php foreach ($comunas as $comuna): ?>
                    <option
                        value="<?= escapar($comuna['ID_Comuna']) ?>"
                        <?= (string) $formulario['ID_Comuna'] ===
                            (string) $comuna['ID_Comuna'] ? 'selected' : '' ?>>
                        <?= escapar($comuna['Comuna'] . ' — ' . $comuna['Region']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="guardar" type="submit">
                <?= $editando ? 'Guardar cambios' : 'Crear centro' ?>
            </button>

            <?php if ($editando): ?>
                <a href="centros.php">Cancelar edición</a>
            <?php endif; ?>
        </form>
    </section>

    <section>
        <h2>Centros registrados</h2>

        <div class="tabla">
            <table>
                <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Comuna</th>
                    <th>Región</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($centros as $centro): ?>
                    <tr>
                        <td><?= escapar($centro['Codigo_Centro']) ?></td>
                        <td><?= escapar($centro['Nombre']) ?></td>
                        <td><?= escapar($centro['Comuna']) ?></td>
                        <td><?= escapar($centro['Region']) ?></td>
                        <td>
                            <div class="acciones">
                                <a href="centros.php?editar=<?= rawurlencode($centro['Codigo_Centro']) ?>">
                                    Editar
                                </a>

                                <form method="post" action="centros.php"
                                      onsubmit="return confirm('¿Eliminar este centro?');">
                                    <input type="hidden" name="csrf"
                                           value="<?= escapar($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="codigo"
                                           value="<?= escapar($centro['Codigo_Centro']) ?>">
                                    <button class="eliminar" type="submit">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$centros): ?>
                    <tr><td colspan="5">No hay centros registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>