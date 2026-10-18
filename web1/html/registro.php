<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$conexion = new mysqli("mysql_primary", "app_user", "PasswordSeguro123!", "ecommerce");
if ($conexion->connect_error) {
    die("Error de conexión a la base de datos.");
}
$conexion->set_charset("utf8mb4");

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = '<div class="alerta error">Formato de correo no válido.</div>';
    } else {
        $rol = ($email === 'ceti@final.com') ? 'admin' : 'usuario';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $secret = 'N/A';
        
        // 1. Generar token único para confirmación
        $token = bin2hex(random_bytes(32));

        // 2. Insertar usuario con "confirmado = 0" y su token generado
        $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, rol, email, password, secret, confirmado, token_confirmacion) VALUES (?, ?, ?, ?, ?, 0, ?)");
        
        if ($stmt) {
            // Se actualizan los parámetros: 6 variables ("ssssss")
            $stmt->bind_param("ssssss", $nombre, $rol, $email, $passwordHash, $secret, $token);
            if ($stmt->execute()) {
                
                // 3. Simular el envío de correo mostrando el enlace en la pantalla
                $host = $_SERVER['HTTP_HOST'];
                $enlace_confirmacion = "http://$host/confirmar.php?token=$token";
                
                $mensaje = '
                <div class="alerta exito" style="text-align:left;">
                    <p><strong>¡Cuenta creada con éxito!</strong></p>
                    <p style="margin-top: 10px; font-size:13px;">📥 <em>Simulación de bandeja de entrada:</em><br> 
                    Para activar tu cuenta y poder iniciar sesión, haz clic en este enlace:</p>
                    <a href="'.$enlace_confirmacion.'" style="display:inline-block; margin-top:8px; background:#166534; color:white; padding:8px 12px; text-decoration:none; border-radius:4px; font-weight:bold;">Confirmar mi cuenta</a>
                </div>';
            } else {
                $mensaje = '<div class="alerta error">El correo ya se encuentra registrado.</div>';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro - CETI Shop</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
    .card-registro { background: #ffffff; width: 100%; max-width: 420px; padding: 35px 30px; border-radius: 10px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); }
    .card-registro h2 { color: #1e293b; margin-bottom: 24px; font-size: 22px; text-align: center; }
    .grupo-campo { margin-bottom: 18px; text-align: left; }
    .grupo-campo label { display: block; margin-bottom: 6px; font-size: 14px; color: #475569; font-weight: 600; }
    .grupo-campo input { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 15px; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
    .grupo-campo input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }
    .btn-submit { width: 100%; padding: 12px; background-color: #2563eb; color: #ffffff; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background-color 0.2s; margin-top: 10px; }
    .btn-submit:hover { background-color: #1d4ed8; }
    .alerta { padding: 12px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; text-align: center; }
    .alerta.error { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .alerta.exito { background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .link-login { display: block; text-align: center; margin-top: 20px; color: #2563eb; text-decoration: none; font-size: 14px; font-weight: 500; }
    .link-login:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="card-registro">
    <h2>Crear nueva cuenta</h2>

    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <form method="POST" action="registro.php">
        <div class="grupo-campo">
            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" name="nombre" placeholder="Nombre completo" required>
        </div>

        <div class="grupo-campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" placeholder="correo@ejemplo.com" required>
        </div>

        <div class="grupo-campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-submit">Registrarse</button>
    </form>

    <a href="login.php" class="link-login">¿Ya tienes cuenta? Inicia sesión aquí</a>
</div>

</body>
</html>
