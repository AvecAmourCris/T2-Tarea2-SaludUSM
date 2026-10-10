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

function obtenerEspecialidades($pdo, $rut) {
    $consulta = $pdo->prepare(
        'SELECT ID_Especialidad FROM Medico_Especialidad
         WHERE RUT_Medico = ?'
    );
    $consulta->execute([$rut]);

    return array_map('strval', $consulta->fetchAll(PDO::FETCH_COLUMN));
}

function obtenerCentros($pdo, $rut) {
    $consulta = $pdo->prepare(
        'SELECT Codigo_Centro FROM Medico_Centro WHERE RUT_Medico = ?'
    );
    $consulta->execute([$rut]);

    return $consulta->fetchAll(PDO::FETCH_COLUMN);
}

// Modifica solamente las especialidades que cambiaron.
// El orden evita dejar al médico sin especialidades o superar el máximo.
function actualizarEspecialidades($pdo, $rut, $actuales, $nuevas) {
    $quitar = array_values(array_diff($actuales, $nuevas));
    $agregar = array_values(array_diff($nuevas, $actuales));

    $eliminar = $pdo->prepare(
        'DELETE FROM Medico_Especialidad
         WHERE RUT_Medico = ? AND ID_Especialidad = ?'
    );
    $insertar = $pdo->prepare(
        'INSERT INTO Medico_Especialidad (RUT_Medico, ID_Especialidad)
         VALUES (?, ?)'
    );

    $cantidad = count($actuales);
    $pendiente = null;

    foreach ($quitar as $especialidad) {
        if ($cantidad > 1) {
            $eliminar->execute([$rut, $especialidad]);
            $cantidad--;
        } else {
            $pendiente = $especialidad;
        }
    }

    if ($pendiente !== null) {
        $primera = array_shift($agregar);
        $insertar->execute([$rut, $primera]);
        $eliminar->execute([$rut, $pendiente]);
    }

    foreach ($agregar as $especialidad) {
        $insertar->execute([$rut, $especialidad]);
    }
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$especialidades = $pdo->query(
    'SELECT ID_Especialidad, Nombre FROM Especialidad ORDER BY Nombre'
)->fetchAll();

$centros = $pdo->query(
    'SELECT Codigo_Centro, Nombre FROM Centro_Medico ORDER BY Nombre'
)->fetchAll();

$error = '';
$mensaje = $_SESSION['mensaje_medicos'] ?? '';
unset($_SESSION['mensaje_medicos']);

$editando = false;
$formulario = [
    'rut' => '',
    'nombre' => '',
    'email' => '',
    'especialidades' => [],
    'centros' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $rut = strtoupper(trim($_POST['rut'] ?? ''));

    try {
        if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
            throw new RuntimeException(
                'El formulario expiró. Recarga la página.'
            );
        }

        if (!in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
            throw new RuntimeException('Acción no válida.');
        }

        if ($accion !== 'eliminar') {
            $editando = $accion === 'editar';

            $seleccionEspecialidades = $_POST['especialidades'] ?? [];
            $seleccionCentros = $_POST['centros'] ?? [];

            if (!is_array($seleccionEspecialidades) ||
                !is_array($seleccionCentros)) {
                throw new RuntimeException('Selección no válida.');
            }

            foreach (array_merge(
                $seleccionEspecialidades,
                $seleccionCentros
            ) as $valor) {
                if (!is_string($valor)) {
                    throw new RuntimeException('Selección no válida.');
                }
            }

            $seleccionEspecialidades = array_values(
                array_unique($seleccionEspecialidades)
            );
            $seleccionCentros = array_values(
                array_unique($seleccionCentros)
            );

            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            $formulario = [
                'rut' => $rut,
                'nombre' => $nombre,
                'email' => $email,
                'especialidades' => $seleccionEspecialidades,
                'centros' => $seleccionCentros
            ];

            if (!preg_match('/^[0-9]{7,8}-[0-9K]$/', $rut)) {
                throw new RuntimeException(
                    'Escribe el RUT sin puntos y con guion.'
                );
            }

            if ($nombre === '' ||
                preg_match_all('/./us', $nombre) > 150) {
                throw new RuntimeException(
                    'El nombre es obligatorio y admite hasta 150 caracteres.'
                );
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($email) > 100) {
                throw new RuntimeException('Escribe un correo válido.');
            }

            if (count($seleccionEspecialidades) < 1 ||
                count($seleccionEspecialidades) > 3) {
                throw new RuntimeException(
                    'Selecciona entre 1 y 3 especialidades.'
                );
            }

            if (count($seleccionCentros) < 1) {
                throw new RuntimeException(
                    'Selecciona al menos un centro médico.'
                );
            }

            $idsValidos = array_map(
                'strval',
                array_column($especialidades, 'ID_Especialidad')
            );
            $centrosValidos = array_column($centros, 'Codigo_Centro');

            if (array_diff($seleccionEspecialidades, $idsValidos) ||
                array_diff($seleccionCentros, $centrosValidos)) {
                throw new RuntimeException(
                    'Hay especialidades o centros que no existen.'
                );
            }

            if ($accion === 'crear' || $password !== '') {
                if (strlen($password) < 8 ||
                    strlen($password) > 72 ||
                    !preg_match('/[A-Za-z]/', $password) ||
                    !preg_match('/[0-9]/', $password)) {
                    throw new RuntimeException(
                        'La contraseña debe tener entre 8 y 72 bytes, '
                        . 'e incluir letras y números.'
                    );
                }
            }
        }

        $pdo->beginTransaction();

        if ($accion !== 'crear') {
            $consulta = $pdo->prepare(
                'SELECT RUT_Medico FROM Medico
                 WHERE RUT_Medico = ? FOR UPDATE'
            );
            $consulta->execute([$rut]);

            if (!$consulta->fetch()) {
                throw new RuntimeException('El médico ya no existe.');
            }
        }

        if ($accion === 'eliminar') {
            $consulta = $pdo->prepare(
                'SELECT COUNT(*) FROM Cita WHERE RUT_Medico = ?'
            );
            $consulta->execute([$rut]);

            if ($consulta->fetchColumn() > 0) {
                throw new RuntimeException(
                    'No se puede eliminar un médico con citas registradas.'
                );
            }

            // El perfil y sus asociaciones se eliminan por las FK en cascada.
            $consulta = $pdo->prepare(
                'DELETE FROM Usuario WHERE RUT = ? AND ID_Rol = 2'
            );
            $consulta->execute([$rut]);

            if ($consulta->rowCount() !== 1) {
                throw new RuntimeException(
                    'No se encontró la cuenta de médico correspondiente.'
                );
            }

            $mensaje = 'Médico y cuenta de acceso eliminados.';
        } else {
            // El correo no debe pertenecer a otro médico ni a un paciente.
            $consulta = $pdo->prepare(
                'SELECT RUT_Medico FROM Medico
                 WHERE Email_Institucional = ? AND RUT_Medico <> ?'
            );
            $consulta->execute([$email, $rut]);

            if ($consulta->fetch()) {
                throw new RuntimeException(
                    'Ese correo ya pertenece a otro médico.'
                );
            }

            $consulta = $pdo->prepare(
                'SELECT RUT_Paciente FROM Paciente WHERE Email = ?'
            );
            $consulta->execute([$email]);

            if ($consulta->fetch()) {
                throw new RuntimeException(
                    'Ese correo ya está registrado por un paciente.'
                );
            }

            if ($accion === 'crear') {
                $consulta = $pdo->prepare(
                    'SELECT ID_Usuario FROM Usuario WHERE RUT = ?'
                );
                $consulta->execute([$rut]);

                if ($consulta->fetch()) {
                    throw new RuntimeException(
                        'Ese RUT ya tiene una cuenta registrada.'
                    );
                }

                $consulta = $pdo->prepare(
                    'INSERT INTO Usuario (RUT, Password_Hash, ID_Rol)
                     VALUES (?, ?, 2)'
                );
                $consulta->execute([
                    $rut,
                    password_hash($password, PASSWORD_DEFAULT)
                ]);

                $consulta = $pdo->prepare(
                    'INSERT INTO Medico
                     (RUT_Medico, Nombre_Completo, Email_Institucional)
                     VALUES (?, ?, ?)'
                );
                $consulta->execute([$rut, $nombre, $email]);

                $consulta = $pdo->prepare(
                    'INSERT INTO Medico_Especialidad
                     (RUT_Medico, ID_Especialidad) VALUES (?, ?)'
                );

                foreach ($seleccionEspecialidades as $especialidad) {
                    $consulta->execute([$rut, $especialidad]);
                }

                $consulta = $pdo->prepare(
                    'INSERT INTO Medico_Centro
                     (RUT_Medico, Codigo_Centro) VALUES (?, ?)'
                );

                foreach ($seleccionCentros as $centro) {
                    $consulta->execute([$rut, $centro]);
                }

                $mensaje = 'Médico creado. Ya puede iniciar sesión.';
            } else {
                $actualesEspecialidades = obtenerEspecialidades($pdo, $rut);
                $actualesCentros = obtenerCentros($pdo, $rut);

                // Los triggers de la base bloquean quitar asociaciones
                // que tengan citas futuras reservadas o confirmadas.
                actualizarEspecialidades(
                    $pdo,
                    $rut,
                    $actualesEspecialidades,
                    $seleccionEspecialidades
                );

                // Primero agregamos centros para conservar al menos uno.
                $consulta = $pdo->prepare(
                    'INSERT INTO Medico_Centro
                     (RUT_Medico, Codigo_Centro) VALUES (?, ?)'
                );

                foreach (array_diff(
                    $seleccionCentros,
                    $actualesCentros
                ) as $centro) {
                    $consulta->execute([$rut, $centro]);
                }

                $consulta = $pdo->prepare(
                    'DELETE FROM Medico_Centro
                     WHERE RUT_Medico = ? AND Codigo_Centro = ?'
                );

                foreach (array_diff(
                    $actualesCentros,
                    $seleccionCentros
                ) as $centro) {
                    $consulta->execute([$rut, $centro]);
                }

                $consulta = $pdo->prepare(
                    'UPDATE Medico
                     SET Nombre_Completo = ?, Email_Institucional = ?
                     WHERE RUT_Medico = ?'
                );
                $consulta->execute([$nombre, $email, $rut]);

                if ($password !== '') {
                    $consulta = $pdo->prepare(
                        'UPDATE Usuario SET Password_Hash = ?
                         WHERE RUT = ? AND ID_Rol = 2'
                    );
                    $consulta->execute([
                        password_hash($password, PASSWORD_DEFAULT),
                        $rut
                    ]);
                }

                $mensaje = 'Médico actualizado correctamente.';
            }
        }

        $pdo->commit();
        $_SESSION['mensaje_medicos'] = $mensaje;

        header('Location: medicos.php');
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());

        if ($e->getCode() === '45000') {
            // Mensaje definido por nuestros triggers.
            $error = $e->errorInfo[2] ?? 'La operación infringe una regla.';
        } else {
            $error = 'No se pudo guardar. Revisa los datos duplicados '
                   . 'y las asociaciones del médico.';
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['editar'])) {
    $consulta = $pdo->prepare(
        'SELECT RUT_Medico, Nombre_Completo, Email_Institucional
         FROM Medico WHERE RUT_Medico = ?'
    );
    $consulta->execute([$_GET['editar']]);
    $medico = $consulta->fetch();

    if ($medico) {
        $editando = true;
        $rut = $medico['RUT_Medico'];

        $formulario = [
            'rut' => $rut,
            'nombre' => $medico['Nombre_Completo'],
            'email' => $medico['Email_Institucional'],
            'especialidades' => obtenerEspecialidades($pdo, $rut),
            'centros' => obtenerCentros($pdo, $rut)
        ];
    } else {
        $error = 'El médico solicitado no existe.';
    }
}

// Utilizamos la vista que creó tu compañero.
$medicos = $pdo->query(
    'SELECT * FROM v_medicos_detalle ORDER BY Medico'
)->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Médicos | SaludUSM</title>
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
            margin: 24px 0;
        }
        a { color: #0056b3; }
        .campo { display: block; margin: 16px 0 6px; }
        input:not([type="checkbox"]):not([type="hidden"]) {
            box-sizing: border-box;
            width: 100%;
            padding: 10px;
            border: 1px solid #aebdce;
            border-radius: 6px;
        }
        fieldset {
            margin-top: 20px;
            border: 1px solid #ccd5df;
            border-radius: 6px;
        }
        .opciones {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
            padding: 12px 0;
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
            vertical-align: top;
        }
        th { background: #edf2f8; }
        .acciones { display: flex; gap: 12px; align-items: center; }
    </style>
</head>
<body>
<main>
    <nav>
        <a href="dashboard_admin.php">Volver al panel</a>
        |
        <a href="centros.php">Centros médicos</a>
    </nav>

    <h1>Gestión de médicos</h1>

    <?php if ($mensaje !== '' && $error === ''): ?>
        <p class="mensaje" role="status"><?= escapar($mensaje) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error" role="alert"><?= escapar($error) ?></p>
    <?php endif; ?>

    <section>
        <h2><?= $editando ? 'Editar médico' : 'Registrar médico' ?></h2>

        <form method="post" action="medicos.php">
            <input type="hidden" name="csrf"
                   value="<?= escapar($_SESSION['csrf']) ?>">
            <input type="hidden" name="accion"
                   value="<?= $editando ? 'editar' : 'crear' ?>">

            <label class="campo" for="rut">RUT sin puntos y con guion</label>
            <input id="rut" name="rut" maxlength="10" required
                   pattern="[0-9]{7,8}-[0-9Kk]"
                   value="<?= escapar($formulario['rut']) ?>"
                   <?= $editando ? 'readonly' : '' ?>>

            <label class="campo" for="nombre">Nombre completo</label>
            <input id="nombre" name="nombre" maxlength="150" required
                   value="<?= escapar($formulario['nombre']) ?>>

            <label class="campo" for="email">Correo institucional</label>
            <input id="email" name="email" type="email"
                   maxlength="100" required
                   value="<?= escapar($formulario['email']) ?>>

            <label class="campo" for="password">
                <?= $editando
                    ? 'Nueva contraseña: déjala vacía para conservar la actual'
                    : 'Contraseña de acceso' ?>
            </label>
            <input id="password" name="password" type="password"
                   minlength="8" maxlength="72" autocomplete="new-password"
                   <?= $editando ? '' : 'required' ?>>
            <small>Usa entre 8 y 72 caracteres ASCII, con letras y números.</small>

            <fieldset>
                <legend>Especialidades: selecciona entre 1 y 3</legend>
                <div class="opciones">
                    <?php foreach ($especialidades as $especialidad): ?>
                        <label>
                            <input type="checkbox" name="especialidades[]"
                                   value="<?= escapar($especialidad['ID_Especialidad']) ?>"
                                   <?= in_array(
                                       (string) $especialidad['ID_Especialidad'],
                                       $formulario['especialidades'],
                                       true
                                   ) ? 'checked' : '' ?>>
                            <?= escapar($especialidad['Nombre']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>Centros de atención: selecciona al menos uno</legend>
                <div class="opciones">
                    <?php foreach ($centros as $centro): ?>
                        <label>
                            <input type="checkbox" name="centros[]"
                                   value="<?= escapar($centro['Codigo_Centro']) ?>"
                                   <?= in_array(
                                       $centro['Codigo_Centro'],
                                       $formulario['centros'],
                                       true
                                   ) ? 'checked' : '' ?>>
                            <?= escapar($centro['Nombre']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <button class="guardar" type="submit">
                <?= $editando ? 'Guardar cambios' : 'Registrar médico' ?>
            </button>

            <?php if ($editando): ?>
                <a href="medicos.php">Cancelar edición</a>
            <?php endif; ?>
        </form>
    </section>

    <section>
        <h2>Médicos registrados</h2>
        <div class="tabla">
            <table>
                <thead>
                <tr>
                    <th>RUT</th>
                    <th>Nombre y correo</th>
                    <th>Especialidades</th>
                    <th>Centros</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($medicos as $medico): ?>
                    <tr>
                        <td><?= escapar($medico['RUT_Medico']) ?></td>
                        <td>
                            <?= escapar($medico['Medico']) ?><br>
                            <small><?= escapar($medico['Email_Institucional']) ?></small>
                        </td>
                        <td><?= escapar($medico['Especialidades']) ?></td>
                        <td><?= escapar($medico['Centros']) ?></td>
                        <td>
                            <div class="acciones">
                                <a href="medicos.php?editar=<?= rawurlencode($medico['RUT_Medico']) ?>">
                                    Editar
                                </a>

                                <form method="post" action="medicos.php"
                                      onsubmit="return confirm('¿Eliminar este médico y su cuenta de acceso?');">
                                    <input type="hidden" name="csrf"
                                           value="<?= escapar($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="rut"
                                           value="<?= escapar($medico['RUT_Medico']) ?>">
                                    <button class="eliminar" type="submit">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$medicos): ?>
                    <tr><td colspan="5">No hay médicos registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>