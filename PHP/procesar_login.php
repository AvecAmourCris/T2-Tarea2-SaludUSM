<?php
session_start();

require_once 'db.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rut = trim($_POST['rut']);
    $password = $_POST['password'] ?? '';
    
    $sql = "SELECT ID_Usuario, RUT, Password_Hash, ID_Rol FROM Usuario WHERE RUT = :rut";
            
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':rut', $rut, PDO::PARAM_STR);
        $stmt->execute();
        
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($password, $usuario['Password_Hash'])) {
            session_regenerate_id(true); 
            
            $_SESSION['id_usuario'] = $usuario['ID_Usuario'];
            $_SESSION['rut'] = $usuario['RUT'];
            $_SESSION['id_rol'] = $usuario['ID_Rol'];
            
            if ($usuario['ID_Rol'] == 3) {
                header("Location: dashboard_admin.php");
            } elseif ($usuario['ID_Rol'] == 2) {
                header("Location: dashboard_medico.php");
            } else {
                header("Location: dashboard_paciente.php");
            }
            exit();
            
        } else {
            echo "Error: RUT o contraseña incorrectos.";
        }
        
    } catch(PDOException $e) {
        die("Error en la consulta: " . $e->getMessage());
    }
} else {
    header("Location: ../login.php");
    exit();
}
?>