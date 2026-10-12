<?php
session_start();
$error = $_SESSION['error_login'] ?? '';
unset($_SESSION['error_login']);
if (!empty($_SESSION['id_usuario'])) {
    $paginas = [1=>'dashboard_paciente.php',2=>'dashboard_medico.php',3=>'dashboard_admin.php'];
    $destino = $paginas[(int)($_SESSION['id_rol'] ?? 0)] ?? null;
    if ($destino) { header('Location: ' . $destino); exit; }
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ingresar | SaludUSM</title><link rel="stylesheet" href="../CSS/estilo.css"><style>.login{width:min(440px,92vw);margin:70px auto}.login input{width:100%;margin-bottom:12px}.login button{width:100%}.login h1{margin:0}.login .logo{text-align:center;font-size:33px;margin-bottom:8px}</style></head>
<body><main><section class="login"><div class="logo">⚕</div><h1>Bienvenido/a a SaludUSM</h1><p class="muted">Sistema de gestión de citas y atenciones médicas.</p>
<?php if ($error): ?><p class="error" role="alert"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></p><?php endif ?>
<form action="procesar_login.php" method="post"><div class="field"><label for="rut">RUT (sin puntos)</label><input id="rut" name="rut" placeholder="12345678-9" maxlength="10" autocomplete="username" required></div><div class="field"><label for="password">Contraseña</label><input type="password" id="password" name="password" autocomplete="current-password" required></div><button>Iniciar sesión</button></form><p>¿Aún no tienes cuenta? <a href="registro.php">Regístrate como paciente</a>.</p></section></main></body></html>
