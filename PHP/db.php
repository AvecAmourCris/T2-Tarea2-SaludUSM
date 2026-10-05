<?php
// Archivo: PHP/config/db.php

$host = 'localhost';
$dbname = 'salud_usm';
$user = 'root'; // Usuario por defecto en XAMPP
$pass = '';     // Contraseña por defecto en XAMPP Mac (déjalo vacío)

try {
    // Configuración DSN (Data Source Name) con codificación UTF-8 para las tildes
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    // Opciones de seguridad y manejo de errores exigidas para PDO
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza errores como excepciones
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Trae los datos como arreglos [ 'Columna' => 'Valor' ]
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Obliga a usar "prepared statements" reales (Seguridad)
    ];

    // Se crea la conexión
    $pdo = new PDO($dsn, $user, $pass, $options);
    //echo "¡Conexión exitosa a la base de datos salud_usm!";

} catch (PDOException $e) {
    // Si la conexión falla, se detiene todo y avisa el motivo
    die("Error crítico: No se pudo conectar a la base de datos. Detalle: " . $e->getMessage());
}
?>