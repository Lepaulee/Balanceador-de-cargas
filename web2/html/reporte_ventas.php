<?php
// Configuración estricta de sesiones
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

// 1. VALIDACIÓN ESTRICTA DE ROL (Control de Acceso)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'admin') {
    http_response_code(403);
    die("<h1 style='color:red; text-align:center; margin-top:50px;'>❌ ERROR 403: Acceso Denegado</h1><p style='text-align:center;'>No tienes los permisos necesarios para ver este reporte.</p>");
}

// 2. Conexión a la base de datos segura (Prevención de fuga de información)
$conexion = new mysqli("mysql_primary", "app_user", "PasswordSeguro123!", "ecommerce");
if ($conexion->connect_error) {
    error_log("Error de DB en reporte_ventas.php: " . $conexion->connect_error);
    http_response_code(500);
    die("Error interno de servicio. Intenta más tarde.");
}
$conexion->set_charset("utf8mb4");

// 3. Consulta (Corregido u.correo a u.email basado en el esquema de registro.php)
$query = "
    SELECT v.id as ticket, v.fecha, v.total, u.email as cliente,
           dv.producto, dv.cantidad, dv.subtotal
    FROM ventas v
    JOIN usuarios u ON v.usuario_id = u.id
    JOIN detalle_ventas dv ON v.id = dv.venta_id
    ORDER BY v.fecha DESC, v.id DESC
";

$resultado = $conexion->query($query);

if (!$resultado) {
    error_log("Error en query de reporte_ventas: " . $conexion->error);
    die("Ocurrió un error al generar el reporte.");
}

$ventas = [];
while ($fila = $resultado->fetch_assoc()) {
    $ventas[$fila['ticket']]['fecha'] = $fila['fecha'];
    $ventas[$fila['ticket']]['cliente'] = $fila['cliente'];
    $ventas[$fila['ticket']]['total'] = $fila['total'];
    $ventas[$fila['ticket']]['detalles'][] = $fila;
}
$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; padding: 40px; color: #1e293b; }
        h1 { color: #2563eb; }
        .ticket { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .ticket-header { display: flex; justify-content: space-between; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; }
        .total { text-align: right; font-size: 18px; font-weight: bold; color: #16a34a; margin-top: 15px; }
    </style>
</head>
<body>
    <h1>📊 Reporte General de Ventas</h1>
    <a href="crud.php" style="display:inline-block; margin-bottom:20px; color:#2563eb; text-decoration:none;">← Volver al Panel de Control</a>

    <?php if (empty($ventas)): ?>
        <p>No hay ventas registradas aún.</p>
    <?php else: ?>
        <?php foreach ($ventas as $ticket => $datos): ?>
            <div class="ticket">
                <div class="ticket-header">
                    <!-- PROTECCIÓN XSS APLICADA A LAS SALIDAS DE DB -->
                    <div><strong>Ticket #<?php echo htmlspecialchars($ticket, ENT_QUOTES, 'UTF-8'); ?></strong> | Cliente: <?php echo htmlspecialchars($datos['cliente'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div>Fecha: <?php echo date('d/m/Y H:i', strtotime($datos['fecha'])); ?></div>
                </div>
                <table>
                    <tr><th>Producto</th><th>Cantidad</th><th>Subtotal</th></tr>
                    <?php foreach ($datos['detalles'] as $detalle): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detalle['producto'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($detalle['cantidad'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>$<?php echo number_format($detalle['subtotal'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <div class="total">Total: $<?php echo number_format($datos['total'], 2); ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
