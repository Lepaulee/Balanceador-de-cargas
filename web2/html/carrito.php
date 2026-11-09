<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// 1. Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Generar Token CSRF si no existe (Mitigación CSRF)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Cargar carrito desde sesión o cookie de forma segura
if (empty($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
    if (isset($_COOKIE['carrito_guardado'])) {
        $recuperado = json_decode($_COOKIE['carrito_guardado'], true);
        $_SESSION['carrito'] = is_array($recuperado) ? $recuperado : [];
    } else {
        $_SESSION['carrito'] = [];
    }
}

// 4. Control de inactividad (120 segundos)
$tiempo_limite = 120;
if (isset($_SESSION['ultima_actividad'])) {
    $tiempo_transcurrido = time() - $_SESSION['ultima_actividad'];
    if ($tiempo_transcurrido > $tiempo_limite) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
}
$_SESSION['ultima_actividad'] = time();

// 5. Procesar acciones (Eliminar, Vaciar, Finalizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar CSRF estrictamente
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: carrito.php?error=csrf");
        exit();
    }

    $accion = $_POST['accion'] ?? '';

    if ($accion === 'eliminar') {
        $llave = htmlspecialchars($_POST['llave'] ?? '', ENT_QUOTES, 'UTF-8');
        if (isset($_SESSION['carrito'][$llave])) {
            unset($_SESSION['carrito'][$llave]);
        }
    } elseif ($accion === 'vaciar') {
        $_SESSION['carrito'] = [];
    } elseif ($accion === 'finalizar') {

        // Conexión corregida con guion medio (mysql-primary)
        $conexion = new mysqli("mysql_primary", "app_user", "PasswordSeguro123!", "ecommerce");
        if ($conexion->connect_error) {
            error_log("Error de BD: " . $conexion->connect_error);
            die("Error crítico de conexión a la base de datos.");
        }
        $conexion->set_charset("utf8mb4");

        // --- 1. Preparar total y ticket HTML para el correo ---
        $total_venta = 0;
        $ticket_html = "<h2 style='color: #2563eb;'>Nueva Venta Registrada</h2>";
        $ticket_html .= "<table style='border-collapse: collapse; width: 100%; font-family: Arial;' border='1' cellpadding='8'>";
        $ticket_html .= "<tr style='background-color: #f2f2f2;'><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Subtotal</th></tr>";

        foreach ($_SESSION['carrito'] as $item) {
            $precio_seguro = (float)($item['precio'] ?? 0);
            $cantidad_segura = (int)($item['cantidad'] ?? 0);
            $subtotal = $precio_seguro * $cantidad_segura;
            $total_venta += $subtotal;

            $nombre_seguro_email = htmlspecialchars($item['nombre'] ?? '', ENT_QUOTES, 'UTF-8');

            $ticket_html .= "<tr>
                                <td>{$nombre_seguro_email}</td>
                                <td>$" . number_format($precio_seguro, 2) . "</td>
                                <td>{$cantidad_segura}</td>
                                <td>$" . number_format($subtotal, 2) . "</td>
                             </tr>";
        }
        $ticket_html .= "</table>";
        $ticket_html .= "<h3>Total de la orden: $" . number_format($total_venta, 2) . "</h3>";

        // --- 2. Guardar la venta general en BD ---
        $stmt_venta = $conexion->prepare("INSERT INTO ventas (usuario_id, total) VALUES (?, ?)");
        $stmt_venta->bind_param("id", $_SESSION['user_id'], $total_venta);
        $stmt_venta->execute();
        $id_venta = $conexion->insert_id;
        $stmt_venta->close();

        // --- 3. Guardar detalles y descontar stock ---
        $stmt_detalle = $conexion->prepare("INSERT INTO detalle_ventas (venta_id, producto, cantidad, precio, subtotal) VALUES (?, ?, ?, ?, ?)");

        foreach ($_SESSION['carrito'] as $item) {
            $id_producto = (int)($item['id'] ?? 0);
            $cantidad_comprada = (int)($item['cantidad'] ?? 0);
            $precio = (float)($item['precio'] ?? 0);
            $subtotal = $precio * $cantidad_comprada;
            $nombre_producto = htmlspecialchars($item['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
            $categoria = ($item['categoria'] ?? '') === 'zapateria' ? 'zapateria' : 'muebles';

            // Guardar detalle
            $stmt_detalle->bind_param("isidd", $id_venta, $nombre_producto, $cantidad_comprada, $precio, $subtotal);
            $stmt_detalle->execute();

            // Descontar stock dinámicamente de forma segura
            $tabla = ($categoria === 'zapateria') ? "catalogo_zapateria" : "catalogo_muebles";
            $stmt_stock = $conexion->prepare("UPDATE $tabla SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmt_stock->bind_param("iii", $cantidad_comprada, $id_producto, $cantidad_comprada);
            $stmt_stock->execute();
            $stmt_stock->close();
        }
        $stmt_detalle->close();

        // --- 4. Buscar el correo real del ADMIN ---
        $resultado_admin = $conexion->query("SELECT email FROM usuarios WHERE LOWER(rol) = 'admin' LIMIT 1");
        $datos_admin = $resultado_admin ? $resultado_admin->fetch_assoc() : null;
        $correo_admin = $datos_admin ? $datos_admin['email'] : 'paulo291204@gmail.com';
        $conexion->close();

        // --- 5. Mandar el correo con PHPMailer ---
        require_once 'PHPMailer/src/Exception.php';
        require_once 'PHPMailer/src/PHPMailer.php';
        require_once 'PHPMailer/src/SMTP.php';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'a23310011@ceti.mx';
            $mail->Password   = 'pzib hwwy soyg hopc';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('a23310011@ceti.mx', 'CETI Shop');
            $mail->addAddress($correo_admin, 'Admin CETI Shop');

            $mail->isHTML(true);
            $mail->Subject = 'Ticket de Compra #' . $id_venta . ' - Nuevo Pedido';
            $mail->Body    = $ticket_html;

            $mail->send();
        } catch (Exception $e) {
            error_log("No se pudo enviar el ticket: {$mail->ErrorInfo}");
        }

        // --- 6. Limpiar carrito y redirigir ---
        $_SESSION['carrito'] = [];
        setcookie('carrito_guardado', '', time() - 3600, "/");

        header("Location: carrito.php?exito=1");
        exit();
    }

    setcookie('carrito_guardado', json_encode($_SESSION['carrito']), time() + (86400 * 30), "/");
    header("Location: carrito.php");
    exit();
}

$carrito = $_SESSION['carrito'] ?? [];
$total_general = 0;
foreach ($carrito as $item) {
    $total_general += (float)($item['precio'] ?? 0) * (int)($item['cantidad'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Carrito - CETI Shop</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
    header { background-color: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .brand { font-size: 20px; font-weight: 700; color: #2563eb; text-decoration: none; }

    .main-container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); }
    h2 { margin-bottom: 25px; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; }

    table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
    th, td { padding: 15px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
    th { background: #f8fafc; color: #475569; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
    td { font-size: 15px; }

    .total-row { font-size: 20px; font-weight: 800; text-align: right; padding: 20px 0; border-top: 2px solid #e2e8f0; }
    .total-precio { color: #16a34a; }

    .actions { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }

    .btn { padding: 12px 20px; border-radius: 6px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; display: inline-block; font-size: 15px; transition: all 0.2s; }
    .btn-secundario { background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
    .btn-secundario:hover { background-color: #e2e8f0; }
    .btn-comprar { background-color: #10b981; color: white; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); }
    .btn-comprar:hover { background-color: #059669; }
    .btn-eliminar { background-color: #fee2e2; color: #ef4444; padding: 6px 12px; font-size: 13px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; transition: background 0.2s; }
    .btn-eliminar:hover { background-color: #fca5a5; color: #991b1b; }

    .mensaje-exito { background: #dcfce7; color: #15803d; padding: 15px; border-radius: 8px; text-align: center; font-weight: 600; margin-bottom: 20px; border: 1px solid #bbf7d0; font-size: 16px; }
    .mensaje-error { background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 8px; text-align: center; font-weight: 600; margin-bottom: 20px; border: 1px solid #fca5a5; font-size: 16px; }

    .empty-cart { text-align: center; padding: 40px 0; color: #64748b; font-size: 16px; }
</style>
</head>
<body>

<header>
    <a href="catalogo.php" class="brand">🛍️ CETI Shop</a>
    <div>
        <a href="catalogo.php" class="btn btn-secundario">← Volver al Catálogo</a>
    </div>
</header>

<div class="main-container">

    <?php if (isset($_GET['exito'])): ?>
        <div class="mensaje-exito">🎉 ¡Compra realizada con éxito! El ticket fue enviado al administrador y el stock ha sido actualizado.</div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'csrf'): ?>
        <div class="mensaje-error">⚠️ Error de seguridad: Se detectó una alteración en la petición (Token inválido). La acción fue bloqueada.</div>
    <?php endif; ?>

    <div class="card">
        <h2>🛒 Tu Carrito de Compras</h2>

        <?php if (empty($carrito)): ?>
            <div class="empty-cart">
                <p style="margin-bottom: 20px; font-size: 18px;">Tu carrito está vacío en este momento.</p>
                <a href="catalogo.php" class="btn btn-comprar">Ver Productos</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carrito as $llave => $item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo ucfirst(htmlspecialchars($item['categoria'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                            <td>$<?php echo number_format((float)($item['precio'] ?? 0), 2); ?></td>
                            <td><?php echo (int)($item['cantidad'] ?? 0); ?></td>
                            <td><strong>$<?php echo number_format((float)($item['precio'] ?? 0) * (int)($item['cantidad'] ?? 0), 2); ?></strong></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="llave" value="<?php echo htmlspecialchars($llave, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="btn-eliminar">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-row">
                Total a pagar: <span class="total-precio">$<?php echo number_format($total_general, 2); ?></span>
            </div>

            <div class="actions">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="accion" value="vaciar">
                    <button type="submit" class="btn btn-secundario" onclick="return confirm('¿Estás seguro de vaciar todo el carrito?');">🗑️ Vaciar Carrito</button>
                </form>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="accion" value="finalizar">
                    <button type="submit" class="btn btn-comprar">💳 Finalizar Compra</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
