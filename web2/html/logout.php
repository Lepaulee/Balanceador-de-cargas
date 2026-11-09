<?php
// Mantener la misma configuración estricta de cookies
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

// 1. Vaciamos el arreglo de la sesión
$_SESSION = array();

// 2. Destruimos la cookie de sesión nativa de PHP (PHPSESSID)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destruimos la sesión en el servidor
session_destroy();

// 4. Eliminamos la cookie del JWT que creamos en el login
setcookie("jwt_token", "", time() - 3600, "/", "", false, true);

// 5. Redirigimos al login enviando la variable para que muestre el mensaje de éxito
header("Location: login.php?logout=1");
exit();
?>
