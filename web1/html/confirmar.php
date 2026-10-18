<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conexion = new mysqli("mysql_primary", "app_user", "PasswordSeguro123!", "ecommerce");
if ($conexion->connect_error) {
    die("Error de conexión a la base de datos.");
}
$conexion->set_charset("utf8mb4");

$mensaje = "";
$exito = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    // Buscar si existe un usuario con ese token
    $stmt = $conexion->prepare("SELECT id, nombre FROM usuarios WHERE token_confirmacion = ? AND confirmado = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        // Si existe, actualizamos su estado a confirmado (1) y borramos el token para que no se reuse
        $usuario = $resultado->fetch_assoc();
        $update_stmt = $conexion->prepare("UPDATE usuarios SET confirmado = 1, token_confirmacion = NULL WHERE id = ?");
        $update_stmt->bind_param("i", $usuario['id']);
        
        if ($update_stmt->execute()) {
            $exito = true;
            $mensaje = "¡Excelente, " . htmlspecialchars($usuario['nombre']) . "! Tu cuenta ha sido confirmada correctamente.";
        } else {
            $mensaje = "Hubo un error al confirmar tu cuenta. Intenta de nuevo.";
        }
        $update_stmt->close();
    } else {
        $mensaje = "El enlace es inválido o la cuenta ya fue confirmada anteriormente.";
    }
    $stmt->close();
} else {
    $mensaje = "No se proporcionó ningún token de confirmación.";
}
$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmar Cuenta - CETI Shop</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
    .card { background: #ffffff; width: 100%; max-width: 420px; padding: 35px 30px; border-radius: 10px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); text-align: center; }
    .card h2 { color: #1e293b; margin-bottom: 20px; font-size: 22px; }
    .alerta { padding: 15px; border-radius: 6px; font-size: 15px; margin-bottom: 20px; line-height: 1.5; }
    .alerta.error { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .alerta.exito { background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .btn-login { display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: bold; transition: background-color 0.2s; margin-top: 10px; }
    .btn-login:hover { background-color: #1d4ed8; }
</style>
</head>
<body>

<div class="card">
    <h2>Confirmación de Cuenta</h2>

    <div class="alerta <?php echo $exito ? 'exito' : 'error'; ?>">
        <?php echo $mensaje; ?>
    </div>

    <?php if ($exito): ?>
        <a href="login.php" class="btn-login">Ir a Iniciar Sesión</a>
    <?php else: ?>
        <a href="registro.php" class="btn-login" style="background-color: #475569;">Volver al registro</a>
    <?php endif; ?>
</div>

</body>
</html>
