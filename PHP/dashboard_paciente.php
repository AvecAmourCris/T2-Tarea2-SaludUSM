<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Portal del Paciente</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background-color: white; padding: 20px; border-radius: 8px; max-width: 800px; margin: auto; }
        .logout-btn { padding: 10px 15px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 20px;}
    </style>
</head>
<body>
    <div class="container">
        <h1>Portal del Paciente</h1>
        <p>RUT: <strong><?php echo htmlspecialchars($_SESSION['rut']); ?></strong></p>
        <p>Desde aquí podrás buscar médicos y agendar tus horas.</p>
        <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
    </div>
</body>
</html>