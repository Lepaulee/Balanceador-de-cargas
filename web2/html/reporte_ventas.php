<?php
session_start();
// Aquí deberías validar que el usuario sea ADMIN. Por ahora solo validamos sesión.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conexion = new mysqli("mysql_primary", "app_user", "PasswordSeguro123!", "ecommerce");
if ($conexion->connect_error) {
    die("Error de conexión");
}
$conexion->set_charset("utf8mb4");

// Consulta chida usando JOIN para traer todo junto
$query = "
    SELECT v.id as ticket, v.fecha, v.total, u.correo as cliente, 
           dv.producto, dv.cantidad, dv.subtotal
    FROM ventas v
    JOIN usuarios u ON v.usuario_id = u.id
    JOIN detalle_ventas dv ON v.id = dv.venta_id
    ORDER BY v.fecha DESC, v.id DESC
";
$resultado = $conexion->query($query);

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
    <a href="catalogo.php" style="display:inline-block; margin-bottom:20px; color:#2563eb; text-decoration:none;">← Volver al sistema</a>

    <?php if (empty($ventas)): ?>
        <p>No hay ventas registradas aún.</p>
    <?php else: ?>
        <?php foreach ($ventas as $ticket => $datos): ?>
            <div class="ticket">
                <div class="ticket-header">
                    <div><strong>Ticket #<?php echo $ticket; ?></strong> | Cliente: <?php echo $datos['cliente']; ?></div>
                    <div>Fecha: <?php echo date('d/m/Y H:i', strtotime($datos['fecha'])); ?></div>
                </div>
                <table>
                    <tr><th>Producto</th><th>Cantidad</th><th>Subtotal</th></tr>
                    <?php foreach ($datos['detalles'] as $detalle): ?>
                        <tr>
                            <td><?php echo $detalle['producto']; ?></td>
                            <td><?php echo $detalle['cantidad']; ?></td>
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
