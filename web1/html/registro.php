<?php
// En producción, cambiar a 0
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

// 1. Importar librerías de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

/* =========================================================
   GENERACIÓN DEL TOKEN CSRF
   ========================================================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Conexión a la base de datos
$conexion = new mysqli("mysql_primary", "app_user", "PasswordSeguro123!", "ecommerce");

// Mitigación: Information Disclosure
if ($conexion->connect_error) {
    error_log("Error de DB en registro.php: " . $conexion->connect_error);
    http_response_code(500);
    die("Error crítico de servicio. Intenta más tarde.");
}
$conexion->set_charset("utf8mb4");

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // VALIDACIÓN CSRF
    $tokenRecibido = $_POST['csrf_token'] ?? '';
    if (empty($tokenRecibido) || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)) {
        http_response_code(403);
        die("<h1 style='color:red; text-align:center; margin-top:50px;'>❌ ERROR 403: Intento de CSRF Detectado</h1>");
    }

    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validaciones básicas
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = '<div class="alerta error">Formato de correo electrónico inválido.</div>';
    } elseif (strlen($password) < 8) {
        $mensaje = '<div class="alerta error">La contraseña debe tener al menos 8 caracteres.</div>';
    } else {
        $rol = ($email === 'ceti@final.com') ? 'admin' : 'usuario';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $secret = 'N/A';

        // Generar token único para confirmación
        $token = bin2hex(random_bytes(32));

        // Insertar usuario con "confirmado = 0"
        $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, rol, email, password, secret, confirmado, token_confirmacion) VALUES (?, ?, ?, ?, ?, 0, ?)");

        if ($stmt) {
            $stmt->bind_param("ssssss", $nombre, $rol, $email, $passwordHash, $secret, $token);

            try {
                $stmt->execute();

                // 2. Configurar y enviar correo con PHPMailer
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'a23310011@ceti.mx';
                    $mail->Password   = 'pzib hwwy soyg hopc';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('a23310011@ceti.mx', 'CETI SHOP');
                    $mail->addAddress($email, htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));

                    // Enlace generado dinámicamente según el host actual
                    $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    $enlace_confirmacion = "$protocolo://$host/confirmar.php?token=$token";

                    $mail->isHTML(true);
                    $mail->Subject = 'Confirma tu cuenta en CETI Shop';
                    $mail->Body    = "
                        <h2>¡Hola " . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "!</h2>
                        <p>Gracias por registrarte en CETI Shop. Para activar tu cuenta, haz clic en el siguiente enlace:</p>
                        <p><br><a href='$enlace_confirmacion' style='background:#2563eb; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>Confirmar mi cuenta</a><br><br></p>
                        <p><small>Si no solicitaste este registro, ignora este mensaje.</small></p>
                    ";

                    $mail->send();
                    $mensaje = '<div class="alerta exito">¡Registro exitoso! Revisa tu bandeja de entrada o spam para confirmar tu cuenta.</div>';
                } catch (Exception $e) {
                    error_log("Error de correo en registro: " . $mail->ErrorInfo);
                    $mensaje = '<div class="alerta error">Cuenta creada, pero hubo un problema interno al enviar el correo.</div>';
                }
            } catch (mysqli_sql_exception $e) {
                // Código 1062 = Duplicate entry (Correo ya existe)
                if ($e->getCode() === 1062) {
                    $mensaje = '<div class="alerta error">Este correo electrónico ya se encuentra registrado.</div>';
                } else {
                    error_log("Error DB al registrar: " . $e->getMessage());
                    $mensaje = '<div class="alerta error">Ocurrió un error interno en el servidor. Inténtalo más tarde.</div>';
                }
            }
            $stmt->close();
        } else {
            error_log("Error preparando sentencia en registro: " . $conexion->error);
            $mensaje = '<div class="alerta error">Ocurrió un error al procesar tu solicitud.</div>';
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
        <!-- CAMPO OCULTO CSRF -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

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
            <small style="color: #64748b; font-size: 12px;">Mínimo 8 caracteres.</small>
        </div>

        <button type="submit" class="btn-submit">Registrarse</button>
    </form>

    <a href="login.php" class="link-login">¿Ya tienes cuenta? Inicia sesión aquí</a>
</div>

</body>
</html>
