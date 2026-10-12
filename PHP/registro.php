<?php
session_start();
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/db.php';

function escapar($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$comunas = $pdo->query(
    'SELECT c.ID_Comuna, c.Nombre, r.Nombre AS Region
     FROM Comuna c
     JOIN Region r ON r.ID_Region = c.ID_Region
     ORDER BY r.Nombre, c.Nombre'
)->fetchAll();

$previsiones = $pdo->query(
    'SELECT ID_Prevision, Nombre FROM Prevision ORDER BY Nombre'
)->fetchAll();

$datos = [
    'rut' => '',
    'nombre' => '',
    'nacimiento' => '',
    'sexo' => '',
    'telefono' => '',
    'email' => '',
    'comuna' => '',
    'prevision' => ''
];

$error = '';
$registrado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($datos as $campo => $valor) {
            if (!isset($_POST[$campo]) || !is_string($_POST[$campo])) {
                throw new RuntimeException('El formulario no es válido.');
            }

            $datos[$campo] = trim($_POST[$campo]);
        }

        if (!is_string($_POST['csrf'] ?? null) ||
            !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            throw new RuntimeException(
                'El formulario expiró. Recarga la página.'
            );
        }

        $datos['rut'] = strtoupper($datos['rut']);
        $password = $_POST['password'] ?? '';
        $confirmacion = $_POST['confirmacion'] ?? '';

        if (!is_string($password) || !is_string($confirmacion)) {
            throw new RuntimeException('La contraseña no es válida.');
        }

        if (!preg_match('/^[0-9]{7,8}-[0-9K]$/', $datos['rut'])) {
            throw new RuntimeException(
                'Escribe el RUT sin puntos y con guion.'
            );
        }

        if ($datos['nombre'] === '' ||
            preg_match_all('/./us', $datos['nombre']) > 150) {
            throw new RuntimeException(
                'Ingresa un nombre de hasta 150 caracteres.'
            );
        }

        $nacimiento = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $datos['nacimiento']
        );

        if (!$nacimiento ||
            $nacimiento->format('Y-m-d') !== $datos['nacimiento'] ||
            $datos['nacimiento'] < '1000-01-01' ||
            $nacimiento > new DateTimeImmutable('today')) {
            throw new RuntimeException(
                'Ingresa una fecha de nacimiento válida, no futura.'
            );
        }

        if (!in_array($datos['sexo'], ['M', 'F', 'Otro'], true)) {
            throw new RuntimeException('Selecciona una opción de sexo.');
        }

        if (strlen($datos['telefono']) > 20) {
            throw new RuntimeException(
                'El teléfono admite hasta 20 caracteres.'
            );
        }

        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL) ||
            strlen($datos['email']) > 100) {
            throw new RuntimeException('Ingresa un correo válido.');
        }

        $comunasValidas = array_map(
            'strval',
            array_column($comunas, 'ID_Comuna')
        );

        $previsionesValidas = array_map(
            'strval',
            array_column($previsiones, 'ID_Prevision')
        );

        if (!in_array($datos['comuna'], $comunasValidas, true) ||
            !in_array($datos['prevision'], $previsionesValidas, true)) {
            throw new RuntimeException(
                'Selecciona una comuna y una previsión válidas.'
            );
        }

        if (strlen($password) < 8 ||
            strlen($password) > 72 ||
            !preg_match('/[A-Za-z]/', $password) ||
            !preg_match('/[0-9]/', $password)) {
            throw new RuntimeException(
                'Usa una contraseña de entre 8 y 72 bytes, '
                . 'con letras y números.'
            );
        }

        if ($password !== $confirmacion) {
            throw new RuntimeException('Las contraseñas no coinciden.');
        }

        $pdo->beginTransaction();

        $consulta = $pdo->prepare(
            'SELECT ID_Usuario FROM Usuario WHERE RUT = ?'
        );
        $consulta->execute([$datos['rut']]);

        if ($consulta->fetch()) {
            throw new RuntimeException('Ese RUT ya está registrado.');
        }

        $consulta = $pdo->prepare(
            'SELECT RUT_Paciente FROM Paciente WHERE Email = ?'
        );
        $consulta->execute([$datos['email']]);

        if ($consulta->fetch()) {
            throw new RuntimeException('Ese correo ya está registrado.');
        }

        $consulta = $pdo->prepare(
            'SELECT RUT_Medico FROM Medico WHERE Email_Institucional = ?'
        );
        $consulta->execute([$datos['email']]);

        if ($consulta->fetch()) {
            throw new RuntimeException('Ese correo ya está registrado.');
        }

        // El rol se fija en el servidor: este formulario solo crea pacientes.
        $consulta = $pdo->prepare(
            'INSERT INTO Usuario (RUT, Password_Hash, ID_Rol)
             VALUES (?, ?, 1)'
        );
        $consulta->execute([
            $datos['rut'],
            password_hash($password, PASSWORD_DEFAULT)
        ]);

        $consulta = $pdo->prepare(
            'INSERT INTO Paciente
             (RUT_Paciente, Nombre_Completo, Fecha_Nacimiento,
              Sexo, Telefono, Email, ID_Comuna_Residencia, ID_Prevision)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $consulta->execute([
            $datos['rut'],
            $datos['nombre'],
            $datos['nacimiento'],
            $datos['sexo'],
            $datos['telefono'] !== '' ? $datos['telefono'] : null,
            $datos['email'],
            $datos['comuna'],
            $datos['prevision']
        ]);

        $pdo->commit();

        $_SESSION['registro_correcto'] = true;
        header('Location: registro.php');
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());
        $error = 'No se pudo crear la cuenta. Revisa si el RUT o correo '
               . 'ya están registrados.';
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

$registrado = $_SESSION['registro_correcto'] ?? false;
unset($_SESSION['registro_correcto']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro de pacientes | SaludUSM</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f6fa;
            color: #203047;
            margin: 0;
            padding: 24px;
        }
        main {
            max-width: 650px;
            margin: auto;
            background: white;
            padding: 28px;
            border-radius: 12px;
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
            margin-top: 24px;
            padding: 12px 20px;
            background: #0056b3;
            color: white;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
        }
        .error { background: #ffe5e5; padding: 14px; }
        .mensaje { background: #e1f4e8; padding: 14px; }
    </style>
</head>
<body>
<main>
    <a href="../login.php">Volver al inicio de sesión</a>
    <h1>Crear cuenta de paciente</h1>

    <?php if ($registrado): ?>
        <p class="mensaje" role="status">
            Cuenta creada correctamente.
            <a href="../login.php">Inicia sesión aquí</a>.
        </p>
    <?php else: ?>

        <?php if ($error !== ''): ?>
            <p class="error" role="alert"><?php echo escapar($error); ?></p>
        <?php endif; ?>

        <form method="post" action="registro.php">
            <input type="hidden" name="csrf"
                   value="<?php echo escapar($_SESSION['csrf']); ?>">

            <label for="rut">RUT sin puntos y con guion</label>
            <input id="rut" name="rut" maxlength="10"
                   pattern="[0-9]{7,8}-[0-9Kk]" required
                   value="<?php echo escapar($datos['rut']); ?>">

            <label for="nombre">Nombre completo</label>
            <input id="nombre" name="nombre" maxlength="150" required
                   value="<?php echo escapar($datos['nombre']); ?>">

            <label for="nacimiento">Fecha de nacimiento</label>
            <input id="nacimiento" name="nacimiento" type="date"
                   min="1000-01-01"
                   max="<?php echo date('Y-m-d'); ?>" required
                   value="<?php echo escapar($datos['nacimiento']); ?>">

            <label for="sexo">Sexo</label>
            <select id="sexo" name="sexo" required>
                <option value="">Selecciona</option>
                <?php foreach ([
                    'M' => 'Masculino',
                    'F' => 'Femenino',
                    'Otro' => 'Otro'
                ] as $valor => $etiqueta): ?>
                    <option value="<?php echo escapar($valor); ?>"
                        <?php echo $datos['sexo'] === $valor ? 'selected' : ''; ?>>
                        <?php echo escapar($etiqueta); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="telefono">Teléfono (opcional)</label>
            <input id="telefono" name="telefono" type="tel" maxlength="20"
                   value="<?php echo escapar($datos['telefono']); ?>">

            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" maxlength="100" required
                   value="<?php echo escapar($datos['email']); ?>">

            <label for="comuna">Comuna de residencia</label>
            <select id="comuna" name="comuna" required>
                <option value="">Selecciona</option>
                <?php foreach ($comunas as $comuna): ?>
                    <option value="<?php echo escapar($comuna['ID_Comuna']); ?>"
                        <?php echo $datos['comuna'] === (string) $comuna['ID_Comuna']
                            ? 'selected' : ''; ?>>
                        <?php echo escapar($comuna['Nombre'] . ' — ' . $comuna['Region']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="prevision">Previsión</label>
            <select id="prevision" name="prevision" required>
                <option value="">Selecciona</option>
                <?php foreach ($previsiones as $prevision): ?>
                    <option value="<?php echo escapar($prevision['ID_Prevision']); ?>"
                        <?php echo $datos['prevision'] === (string) $prevision['ID_Prevision']
                            ? 'selected' : ''; ?>>
                        <?php echo escapar($prevision['Nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password"
                   minlength="8" maxlength="72" autocomplete="new-password" required>
            <small>Usa letras y números; por ejemplo, Paciente2026!</small>

            <label for="confirmacion">Repite la contraseña</label>
            <input id="confirmacion" name="confirmacion" type="password"
                   minlength="8" maxlength="72" autocomplete="new-password" required>

            <button type="submit">Crear cuenta</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>