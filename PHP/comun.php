<?php
// Utilidades compartidas. Toda página protegida valida la sesión en servidor.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
date_default_timezone_set('America/Santiago');
require_once __DIR__ . '/db.php';

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function rol(): int { return (int)($_SESSION['id_rol'] ?? 0); }
function rutActual(): string { return (string)($_SESSION['rut'] ?? ''); }
function exigirRol(array $roles): void {
    if (empty($_SESSION['id_usuario']) || !in_array(rol(), $roles, true)) {
        header('Location: login.php'); exit;
    }
}
function csrf(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(24)); }
    return $_SESSION['csrf'];
}
function verificarCsrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(csrf(), $_POST['csrf'])) {
        throw new RuntimeException('Sesión de formulario caducada: vuelve a cargar la página.');
    }
}
function aviso(string $texto): void { $_SESSION['aviso_general'] = $texto; }
function salto(string $url): void { header('Location: ' . $url); exit; }
function fechaMySQL(string $fecha): string {
    $fecha = trim($fecha);
    $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $fecha);
    if (!$d || $d->format('Y-m-d\TH:i') !== $fecha) {
        throw new RuntimeException('Ingresa una fecha y hora válidas.');
    }
    return $d->format('Y-m-d H:i:s');
}
// Implementa efectivamente el requisito de cancelar las citas vencidas al entrar en la plataforma.
function actualizarVencidas(PDO $pdo): void {
    $stmt = $pdo->query('CALL sp_cancelar_citas_vencidas(@canceladas)');
    $stmt->closeCursor();
}
function mostrarCabecera(string $titulo): void {
    $r = rol();
    $enlaces = [1 => [['dashboard_paciente.php','Inicio'],['buscar_medicos.php','Buscar médicos'],['agendar.php','Agendar'],['mis_citas.php','Mis citas'],['historial.php','Historial']],
                2 => [['dashboard_medico.php','Inicio'],['agenda.php','Agenda'],['atenciones.php','Atenciones'],['historial.php','Historial']],
                3 => [['dashboard_admin.php','Inicio'],['centros.php','Centros'],['medicos.php','Médicos'],['panel_gestion.php','Panel gestión'],['busqueda_avanzada.php','Citas']]];
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($titulo) . ' | SaludUSM</title><link rel="stylesheet" href="../CSS/estilo.css"></head><body><header><strong>⚕ SaludUSM</strong><nav>';
    foreach (($enlaces[$r] ?? []) as $item) { echo '<a href="' . e($item[0]) . '">' . e($item[1]) . '</a>'; }
    if ($r === 1 || $r === 2 || $r === 3) { echo '<a href="busqueda_avanzada.php">Búsqueda avanzada</a><a href="perfil.php">Mi perfil</a><a href="logout.php">Salir</a>'; }
    echo '</nav></header><main><h1>' . e($titulo) . '</h1>';
    if (!empty($_SESSION['aviso_general'])) {
        echo '<p class="success">' . e($_SESSION['aviso_general']) . '</p>';
        unset($_SESSION['aviso_general']);
    }
}
function mostrarPie(): void { echo '</main><footer>SaludUSM · Proyecto académico INF-239</footer></body></html>'; }
function salidaError(Throwable $e): string {
    if ($e instanceof RuntimeException) { return $e->getMessage(); }
    error_log($e->getMessage());
    if ($e instanceof PDOException && $e->getCode() === '45000') { return $e->errorInfo[2] ?? 'Regla de negocio incumplida.'; }
    return 'No fue posible completar la operación. Revisa los datos y las reglas de negocio.';
}
// Evita conexiones abiertas entre CALL y SELECT de las variables OUT.
function procedimiento(PDO $pdo, string $nombre, array $valores, array $salidas): array {
    $permitidos = ['sp_agendar_cita','sp_reprogramar_cita','sp_cancelar_cita'];
    if (!in_array($nombre, $permitidos, true)) { throw new RuntimeException('Procedimiento no admitido.'); }
    foreach ($salidas as $salida) { if (!preg_match('/^[a-z_]+$/', $salida)) { throw new RuntimeException('Variable inválida.'); } }
    $marcas = array_fill(0, count($valores), '?');
    foreach ($salidas as $salida) { $marcas[] = '@' . $salida; }
    $stmt = $pdo->prepare('CALL ' . $nombre . '(' . implode(',', $marcas) . ')');
    $stmt->execute($valores);
    $stmt->closeCursor();
    $columnas = [];
    foreach ($salidas as $salida) { $columnas[] = '@' . $salida . ' AS ' . $salida; }
    return $pdo->query('SELECT ' . implode(', ', $columnas))->fetch();
}
