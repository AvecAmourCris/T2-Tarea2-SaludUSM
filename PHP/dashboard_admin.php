<?php
session_start();


if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 3) {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px; }
        .container { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); max-width: 800px; margin: auto; }
        h1 { color: #333; }
        .logout-btn { display: inline-block; padding: 10px 15px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .logout-btn:hover { background-color: #c82333; }
    </style>
</head>
<body>

    <div class="container">
        <!-- Mostramos el RUT directamente desde la sesión -->
        <h1>Bienvenido al Panel de Administración</h1>
        <p>Has iniciado sesión correctamente con el RUT: <strong><?php echo htmlspecialchars($_SESSION['rut']); ?></strong></p>
        
        <p>Desde aquí podrás gestionar centros médicos, personal y especialidades.</p>

        <!-- Botón para cerrar sesión -->
        <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
    </div>

</body>
</html>