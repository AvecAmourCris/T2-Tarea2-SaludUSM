<?php
session_start();
require_once __DIR__ . '/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: login.php'); exit; }
$rut = strtoupper(trim((string)($_POST['rut'] ?? '')));
$password = (string)($_POST['password'] ?? '');
if (!preg_match('/^[0-9]{7,8}-[0-9K]$/', $rut) || $password==='') {
    $_SESSION['error_login']='Ingresa un RUT con formato válido y tu contraseña.';
    header('Location: login.php'); exit;
}
try {
    $stmt = $pdo->prepare('SELECT ID_Usuario,RUT,Password_Hash,ID_Rol FROM Usuario WHERE RUT=?');
    $stmt->execute([$rut]);
    $usuario=$stmt->fetch();
    if (!$usuario || !password_verify($password,$usuario['Password_Hash'])) {
        $_SESSION['error_login']='RUT o contraseña incorrectos.';
        header('Location: login.php'); exit;
    }
    session_regenerate_id(true);
    $_SESSION['id_usuario']=$usuario['ID_Usuario'];
    $_SESSION['rut']=$usuario['RUT'];
    $_SESSION['id_rol']=(int)$usuario['ID_Rol'];
    $paginas=[1=>'dashboard_paciente.php',2=>'dashboard_medico.php',3=>'dashboard_admin.php'];
    if (!isset($paginas[$_SESSION['id_rol']])) { session_unset(); session_destroy(); header('Location: login.php'); exit; }
    header('Location: '.$paginas[$_SESSION['id_rol']]); exit;
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error_login']='No fue posible iniciar sesión. Verifica la conexión a la base de datos.';
    header('Location: login.php'); exit;
}
