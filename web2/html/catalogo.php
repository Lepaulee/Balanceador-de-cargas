<?php
session_start();

// 1. Candado de sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Generar Token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Inicializar el carrito de forma SEGURA
if (empty($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
    if (isset($_COOKIE['carrito_guardado'])) {
        $recuperado = json_decode($_COOKIE['carrito_guardado'], true);
        $_SESSION['carrito'] = is_array($recuperado) ? $recuperado : [];
    } else {
        $_SESSION['carrito'] = [];
    }
}

// 4. Control de inactividad (120s)
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

// 5. Bloqueo para administradores
if (isset($_SESSION['user_rol']) && strtolower($_SESSION['user_rol']) === 'admin') {
    header("Location: crud.php");
    exit();
}

// --- LÓGICA PARA AGREGAR AL CARRITO ---
$categoriaActiva = (isset($_GET['categoria']) && $_GET['categoria'] === 'zapateria') ? 'zapateria' : 'muebles';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: catalogo.php?categoria=" . $categoriaActiva . "&error=csrf");
        exit();
    }

    $id_prod = (int)$_POST['id_producto'];
    $precio = (float)$_POST['precio'];
    $stock_maximo = (int)$_POST['stock_disponible'];
    $nombre = htmlspecialchars($_POST['nombre'], ENT_QUOTES, 'UTF-8');
    $cat = ($_POST['categoria_prod'] === 'zapateria') ? 'zapateria' : 'muebles';

    $llave = $cat . "_" . $id_prod;
    $cantidad_actual = isset($_SESSION['carrito'][$llave]) ? $_SESSION['carrito'][$llave]['cantidad'] : 0;

    if ($cantidad_actual < $stock_maximo) {
        if (isset($_SESSION['carrito'][$llave])) {
            $_SESSION['carrito'][$llave]['cantidad']++;
        } else {
            $_SESSION['carrito'][$llave] = [
                'id' => $id_prod,
                'nombre' => $nombre,
                'precio' => $precio,
                'categoria' => $cat,
                'cantidad' => 1
            ];
        }
        setcookie('carrito_guardado', json_encode($_SESSION['carrito']), time() + (86400 * 30), "/");
        header("Location: catalogo.php?categoria=" . $categoriaActiva . "&agregado=1");
        exit();
    } else {
        header("Location: catalogo.php?categoria=" . $categoriaActiva . "&error_stock=1");
        exit();
    }
}

// --- CONEXIÓN A BASE DE DATOS (Host corregido a mysql-primary) ---
$conexion = new mysqli("mysql_primary", "app_user", "PasswordSeguro123!", "ecommerce");
if ($conexion->connect_error) {
    error_log("Error de BD en catálogo: " . $conexion->connect_error);
    http_response_code(500);
    die("Error crítico de conexión a la base de datos.");
}
$conexion->set_charset("utf8mb4");

if ($categoriaActiva === 'zapateria') {
    $sql = "SELECT id, modelo AS nombre, tALLA AS detalle, precio, stock FROM catalogo_zapateria";
} else {
    $sql = "SELECT id, articulo AS nombre, NULL AS detalle, precio, stock FROM catalogo_muebles";
}
$resultado = $conexion->query($sql);

$total_articulos = 0;
if (!empty($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
    $total_articulos = array_sum(array_column($_SESSION['carrito'], 'cantidad'));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catálogo - CETI Shop</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
    header { background-color: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .brand { font-size: 20px; font-weight: 700; color: #2563eb; display: flex; align-items: center; gap: 8px; }
    .brand span { background: #e0e7ff; color: #3730a3; font-size: 11px; padding: 3px 8px; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    .user-info { font-size: 14px; color: #64748b; display: flex; align-items: center; gap: 15px; }
    .user-email { font-weight: 600; color: #334155; }

    .btn-carrito { background-color: #10b981; color: white; text-decoration: none; font-weight: 600; padding: 6px 15px; border-radius: 6px; transition: background 0.2s; }
    .btn-carrito:hover { background-color: #059669; }

    .btn-logout { color: #ef4444; text-decoration: none; font-weight: 600; padding: 6px 12px; border-radius: 6px; background-color: #fef2f2; transition: background 0.2s; }
    .btn-logout:hover { background-color: #fee2e2; }

    .main-container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
    .mensaje-exito { background: #dcfce7; color: #15803d; padding: 12px; text-align: center; font-weight: 600; margin-bottom: 25px; border-radius: 8px; border: 1px solid #bbf7d0; }
    .mensaje-error { background: #fee2e2; color: #b91c1c; padding: 12px; text-align: center; font-weight: 600; margin-bottom: 25px; border-radius: 8px; border: 1px solid #fca5a5; }

    .tabs-container { display: flex; justify-content: center; gap: 12px; margin-bottom: 30px; }
    .tab-btn { padding: 10px 24px; text-decoration: none; background-color: #ffffff; color: #64748b; border: 1px solid #cbd5e1; border-radius: 30px; font-weight: 600; font-size: 15px; transition: all 0.2s ease; }
    .tab-btn:hover { border-color: #2563eb; color: #2563eb; }
    .tab-btn.activo { background-color: #2563eb; color: #ffffff; border-color: #2563eb; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }

    .grid-productos { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 24px; }
    .card-producto { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03); transition: transform 0.2s, box-shadow 0.2s; }
    .card-producto:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08); }
    .card-title { font-size: 17px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
    .card-subtitle { font-size: 13px; color: #64748b; margin-bottom: 8px; }
    .stock-info { font-size: 13px; font-weight: 600; color: #0284c7; margin-bottom: 16px; display: flex; align-items: center; gap: 5px;}

    .card-footer { margin-top: 15px; padding-top: 12px; border-top: 1px solid #f1f5f9; text-align: center; }
    .precio { font-size: 18px; font-weight: 800; color: #16a34a; display: block; margin-bottom: 15px; }

    .btn-comprar { background-color: #2563eb; color: white; border: none; padding: 10px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; width: 100%; font-size: 14px; transition: background 0.2s; }
    .btn-comprar:hover { background-color: #1d4ed8; }

    .badge-stock { padding: 6px 12px; border-radius: 12px; font-size: 13px; font-weight: 700; display: inline-block; width: 100%; }
    .badge-stock.ok { background: #dcfce7; color: #15803d; }
    .badge-stock.out { background: #fee2e2; color: #b91c1c; }

    .empty-state { text-align: center; padding: 60px 20px; background: #ffffff; border-radius: 12px; border: 1px dashed #cbd5e1; color: #64748b; }
</style>
</head>
<body>

<header>
    <div class="brand">
        🛍️ CETI Shop <span>Catálogo</span>
    </div>
    <div class="user-info">
        <span>Usuario: <strong class="user-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Usuario', ENT_QUOTES, 'UTF-8'); ?></strong></span>

        <a href="carrito.php" class="btn-carrito">🛒 Carrito (<?php echo $total_articulos; ?>)</a>

        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</header>

<div class="main-container">

    <?php if(isset($_GET['error']) && $_GET['error'] === 'csrf'): ?>
        <div class="mensaje-error">⚠️ Error de seguridad: Token inválido. La acción fue bloqueada para proteger tu cuenta.</div>
    <?php endif; ?>

    <?php if(isset($_GET['agregado'])): ?>
        <div class="mensaje-exito">✅ ¡Producto agregado al carrito con éxito!</div>
    <?php endif; ?>

    <?php if(isset($_GET['error_stock'])): ?>
        <div class="mensaje-error">⚠️ No puedes agregar más. ¡Alcanzaste el límite de stock disponible!</div>
    <?php endif; ?>

    <div class="tabs-container">
        <a href="catalogo.php?categoria=muebles" class="tab-btn <?php echo $categoriaActiva === 'muebles' ? 'activo' : ''; ?>">🛋️ Muebles</a>
        <a href="catalogo.php?categoria=zapateria" class="tab-btn <?php echo $categoriaActiva === 'zapateria' ? 'activo' : ''; ?>">👟 Zapatería</a>
    </div>

    <?php if ($resultado && $resultado->num_rows > 0): ?>
        <div class="grid-productos">
            <?php while($row = $resultado->fetch_assoc()): ?>
                <div class="card-producto">
                    <div>
                        <div class="card-title"><?php echo htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if ($row['detalle']): ?>
                            <div class="card-subtitle">Talla: <?php echo htmlspecialchars($row['detalle'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php else: ?>
                            <div class="card-subtitle">Mueble de alta calidad</div>
                        <?php endif; ?>

                        <div class="stock-info">
                            📦 Stock disponible: <?php echo $row['stock']; ?>
                        </div>
                    </div>

                    <div class="card-footer">
                        <span class="precio">$<?php echo number_format($row['precio'], 2); ?></span>

                        <?php if ($row['stock'] > 0): ?>
                            <form method="POST" action="catalogo.php?categoria=<?php echo $categoriaActiva; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="accion" value="agregar">
                                <input type="hidden" name="id_producto" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="nombre" value="<?php echo htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="precio" value="<?php echo $row['precio']; ?>">
                                <input type="hidden" name="categoria_prod" value="<?php echo $categoriaActiva; ?>">
                                <input type="hidden" name="stock_disponible" value="<?php echo $row['stock']; ?>">

                                <button type="submit" class="btn-comprar">🛒 Agregar al carrito</button>
                            </form>
                        <?php else: ?>
                            <span class="badge-stock out">Agotado</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>No hay productos disponibles en esta categoría.</h3>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
