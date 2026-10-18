<?php
session_start();

// 1. Candado de sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Candado de ROL (Insensible a mayúsculas)
if (!isset($_SESSION['user_rol']) || strtolower($_SESSION['user_rol']) !== 'admin') {
    header("Location: catalogo.php");
    exit();
}

// 3. Destruir la sesión después de 2 minutos (120 segundos) de inactividad
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

// 4. Generar Token CSRF si no existe en la sesión
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- CONEXIÓN A BASE DE DATOS ---
$conexion = new mysqli("mysql_primary", "app_user", "PasswordSeguro123!", "ecommerce");
if ($conexion->connect_error) {
    http_response_code(500);
    die("Error crítico de conexión a la base de datos.");
}
$conexion->set_charset("utf8mb4");

// Detectar qué tabla estamos viendo (Muebles, Zapatería o Usuarios)
$tablaUrl = $_GET['tabla'] ?? 'muebles';
if ($tablaUrl === 'zapateria') {
    $tablaActiva = 'catalogo_zapateria';
} elseif ($tablaUrl === 'usuarios') {
    $tablaActiva = 'usuarios';
} else {
    $tablaActiva = 'catalogo_muebles';
    $tablaUrl = 'muebles';
}

// Lógica de Eliminación (Delete)
if (isset($_GET['eliminar'])) {
    $tokenGet = $_GET['csrf_token'] ?? '';

    if (empty($tokenGet) || !hash_equals($_SESSION['csrf_token'], $tokenGet)) {
        header("Location: crud.php?tabla=" . $tablaUrl . "&error=csrf");
        exit();
    }

    $idEliminar = intval($_GET['eliminar']);
    
    // Evitar que el admin se elimine a sí mismo
    if ($tablaActiva === 'usuarios' && $idEliminar === intval($_SESSION['user_id'])) {
        header("Location: crud.php?tabla=usuarios&error=self_delete");
        exit();
    }

    $stmtDel = $conexion->prepare("DELETE FROM $tablaActiva WHERE id = ?");
    if ($stmtDel) {
        $stmtDel->bind_param("i", $idEliminar);
        $stmtDel->execute();
        $stmtDel->close();
    }

    header("Location: crud.php?tabla=" . $tablaUrl);
    exit();
}

// Lógica de Inserción (Create) y Actualización (Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $tokenPost = $_POST['csrf_token'] ?? '';

    if (empty($tokenPost) || !hash_equals($_SESSION['csrf_token'], $tokenPost)) {
        header("Location: crud.php?tabla=" . $tablaUrl . "&error=csrf");
        exit();
    }

    if ($_POST['accion'] === 'crear') {
        $precio = floatval($_POST['precio'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);

        if ($tablaActiva === 'catalogo_muebles') {
            $articulo = trim($_POST['articulo'] ?? '');
            $stmtIns = $conexion->prepare("INSERT INTO catalogo_muebles (articulo, precio, stock) VALUES (?, ?, ?)");
            if ($stmtIns) {
                $stmtIns->bind_param("sdi", $articulo, $precio, $stock);
                $stmtIns->execute();
                $stmtIns->close();
            }
        } elseif ($tablaActiva === 'catalogo_zapateria') {
            $modelo = trim($_POST['modelo'] ?? '');
            $talla = trim($_POST['talla'] ?? '');
            $stmtIns = $conexion->prepare("INSERT INTO catalogo_zapateria (modelo, tALLA, precio, stock) VALUES (?, ?, ?, ?)");
            if ($stmtIns) {
                $stmtIns->bind_param("ssdi", $modelo, $talla, $precio, $stock);
                $stmtIns->execute();
                $stmtIns->close();
            }
        }
    } elseif ($_POST['accion'] === 'editar') {
        $idUpdate = intval($_POST['id'] ?? 0);

        if ($tablaActiva === 'usuarios') {
            $rol = trim($_POST['rol'] ?? 'cliente');
            $confirmado = intval($_POST['confirmado'] ?? 0);
            
            // Evitar que el admin se quite su propio rol de admin accidentalmente
            if ($idUpdate === intval($_SESSION['user_id']) && $rol !== 'admin') {
                $rol = 'admin'; 
            }

            $stmtUp = $conexion->prepare("UPDATE usuarios SET rol = ?, confirmado = ? WHERE id = ?");
            if ($stmtUp) {
                $stmtUp->bind_param("sii", $rol, $confirmado, $idUpdate);
                $stmtUp->execute();
                $stmtUp->close();
            }
        } else {
            $precio = floatval($_POST['precio'] ?? 0);
            $stock = intval($_POST['stock'] ?? 0);

            if ($tablaActiva === 'catalogo_muebles') {
                $articulo = trim($_POST['articulo'] ?? '');
                $stmtUp = $conexion->prepare("UPDATE catalogo_muebles SET articulo = ?, precio = ?, stock = ? WHERE id = ?");
                if ($stmtUp) {
                    $stmtUp->bind_param("sdii", $articulo, $precio, $stock, $idUpdate);
                    $stmtUp->execute();
                    $stmtUp->close();
                }
            } elseif ($tablaActiva === 'catalogo_zapateria') {
                $modelo = trim($_POST['modelo'] ?? '');
                $talla = trim($_POST['talla'] ?? '');
                $stmtUp = $conexion->prepare("UPDATE catalogo_zapateria SET modelo = ?, tALLA = ?, precio = ?, stock = ? WHERE id = ?");
                if ($stmtUp) {
                    $stmtUp->bind_param("ssdii", $modelo, $talla, $precio, $stock, $idUpdate);
                    $stmtUp->execute();
                    $stmtUp->close();
                }
            }
        }
    }
    header("Location: crud.php?tabla=" . $tablaUrl);
    exit();
}

// Lógica para cargar datos a Editar
$registroEditar = null;
if (isset($_GET['editar'])) {
    $idEditar = intval($_GET['editar']);
    $resEdit = $conexion->query("SELECT * FROM $tablaActiva WHERE id = $idEditar");
    if ($resEdit && $resEdit->num_rows > 0) {
        $registroEditar = $resEdit->fetch_assoc();
    }
}

$resultado = $conexion->query("SELECT * FROM $tablaActiva");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel Admin CRUD - CETI Shop</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
    header { background-color: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .brand { font-size: 20px; font-weight: 700; color: #2563eb; display: flex; align-items: center; gap: 8px; }
    .brand span { background: #dbeafe; color: #1e40af; font-size: 11px; padding: 3px 8px; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    .user-info { font-size: 14px; color: #64748b; display: flex; align-items: center; gap: 15px; }
    .user-email { font-weight: 600; color: #334155; }
    .btn-logout { color: #ef4444; text-decoration: none; font-weight: 600; padding: 6px 12px; border-radius: 6px; background-color: #fef2f2; transition: background 0.2s; }
    .btn-logout:hover { background-color: #fee2e2; }
    .main-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
    .mensaje-error { background: #fee2e2; color: #b91c1c; padding: 12px; text-align: center; font-weight: 600; margin-bottom: 25px; border-radius: 8px; border: 1px solid #fca5a5; }
    .tabs-container { display: flex; justify-content: center; gap: 12px; margin-bottom: 25px; flex-wrap: wrap; }
    .tab-btn { padding: 10px 24px; text-decoration: none; background-color: #ffffff; color: #64748b; border: 1px solid #cbd5e1; border-radius: 30px; font-weight: 600; font-size: 15px; transition: all 0.2s ease; }
    .tab-btn:hover { border-color: #2563eb; color: #2563eb; }
    .tab-btn.activo { background-color: #2563eb; color: #ffffff; border-color: #2563eb; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
    .card-form { background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 28px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
    .card-form h3 { font-size: 18px; color: #0f172a; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .grupo-campo { display: flex; flex-direction: column; gap: 6px; }
    .grupo-campo label { font-size: 13px; font-weight: 600; color: #475569; }
    .grupo-campo input, .grupo-campo select { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s; background: #fff; }
    .grupo-campo input:focus, .grupo-campo select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }
    .actions-form { margin-top: 20px; display: flex; align-items: center; gap: 12px; }
    .btn-submit { padding: 10px 20px; background-color: #16a34a; color: #ffffff; border: none; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; transition: background-color 0.2s; }
    .btn-submit:hover { background-color: #15803d; }
    .btn-submit.btn-edit { background-color: #2563eb; }
    .btn-submit.btn-edit:hover { background-color: #1d4ed8; }
    .btn-cancelar { color: #ef4444; text-decoration: none; font-size: 14px; font-weight: 600; }
    .btn-cancelar:hover { text-decoration: underline; }
    .card-table { background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
    .card-table-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; background-color: #ffffff; }
    .card-table-header h3 { font-size: 18px; color: #0f172a; }
    table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
    th { background-color: #f8fafc; color: #64748b; font-weight: 700; padding: 12px 20px; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
    td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; color: #334155; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background-color: #f8fafc; }
    .badge-stock { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: 700; }
    .badge-stock.ok { background: #dcfce7; color: #15803d; }
    .badge-stock.low { background: #fee2e2; color: #b91c1c; }
    .badge-stock.admin { background: #e0e7ff; color: #4338ca; }
    .action-btn { display: inline-block; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none; transition: opacity 0.2s; }
    .action-btn:hover { opacity: 0.85; }
    .btn-tabla-editar { background-color: #fef3c7; color: #d97706; margin-right: 6px; }
    .btn-tabla-eliminar { background-color: #fee2e2; color: #dc2626; }
</style>
</head>
<body>

<header>
    <div class="brand">
        ⚙️ Admin Panel <span>CRUD</span>
    </div>
    <div class="user-info">
        <span>Admin: <strong class="user-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></strong></span>
        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</header>

<div class="main-container">

    <?php if (isset($_GET['error']) && $_GET['error'] === 'csrf'): ?>
        <div class="mensaje-error">⚠️ Error de seguridad: Token CSRF inválido o ausente.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'self_delete'): ?>
        <div class="mensaje-error">⚠️ Acción bloqueada: No puedes eliminar tu propia cuenta de administrador.</div>
    <?php endif; ?>

    <div class="tabs-container">
        <a href="crud.php?tabla=muebles" class="tab-btn <?php echo $tablaActiva === 'catalogo_muebles' ? 'activo' : ''; ?>">🛋️ Muebles</a>
        <a href="crud.php?tabla=zapateria" class="tab-btn <?php echo $tablaActiva === 'catalogo_zapateria' ? 'activo' : ''; ?>">👟 Zapatería</a>
        <a href="crud.php?tabla=usuarios" class="tab-btn <?php echo $tablaActiva === 'usuarios' ? 'activo' : ''; ?>">👥 Usuarios</a>
    </div>

    <!-- Formulario Agregar/Editar -->
    <?php if ($tablaActiva !== 'usuarios' || ($tablaActiva === 'usuarios' && $registroEditar)): ?>
    <div class="card-form">
        <h3>
            <?php 
            if ($tablaActiva === 'usuarios') echo '✏️ Editar Usuario: ' . htmlspecialchars($registroEditar['email'], ENT_QUOTES, 'UTF-8');
            elseif ($registroEditar) echo '✏️ Editar Registro'; 
            else echo '➕ Agregar a ' . ($tablaActiva === 'catalogo_muebles' ? 'Catálogo de Muebles' : 'Zapatería'); 
            ?>
        </h3>

        <form method="POST" action="crud.php?tabla=<?php echo $tablaUrl; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="accion" value="<?php echo $registroEditar ? 'editar' : 'crear'; ?>">
            <?php if ($registroEditar): ?>
                <input type="hidden" name="id" value="<?php echo intval($registroEditar['id']); ?>">
            <?php endif; ?>

            <div class="form-grid">
                <?php if ($tablaActiva === 'usuarios'): ?>
                    <div class="grupo-campo">
                        <label for="rol">Rol de Usuario</label>
                        <select id="rol" name="rol" required>
                            <option value="cliente" <?php echo ($registroEditar['rol'] === 'cliente') ? 'selected' : ''; ?>>Cliente</option>
                            <option value="admin" <?php echo ($registroEditar['rol'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                        </select>
                    </div>
                    <div class="grupo-campo">
                        <label for="confirmado">Estado de Cuenta</label>
                        <select id="confirmado" name="confirmado" required>
                            <option value="1" <?php echo ($registroEditar['confirmado'] == 1) ? 'selected' : ''; ?>>1 - Confirmado</option>
                            <option value="0" <?php echo ($registroEditar['confirmado'] == 0) ? 'selected' : ''; ?>>0 - Bloqueado / Sin Confirmar</option>
                        </select>
                    </div>
                <?php else: ?>
                    <?php if ($tablaActiva === 'catalogo_muebles'): ?>
                        <div class="grupo-campo">
                            <label for="articulo">Nombre del Artículo</label>
                            <input type="text" id="articulo" name="articulo" value="<?php echo $registroEditar ? htmlspecialchars($registroEditar['articulo'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                    <?php else: ?>
                        <div class="grupo-campo">
                            <label for="modelo">Modelo del Zapato</label>
                            <input type="text" id="modelo" name="modelo" value="<?php echo $registroEditar ? htmlspecialchars($registroEditar['modelo'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                        <div class="grupo-campo">
                            <label for="talla">Talla</label>
                            <input type="text" id="talla" name="talla" value="<?php echo $registroEditar ? htmlspecialchars($registroEditar['tALLA'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                    <?php endif; ?>

                    <div class="grupo-campo">
                        <label for="precio">Precio ($)</label>
                        <input type="number" step="0.01" id="precio" name="precio" value="<?php echo $registroEditar ? htmlspecialchars($registroEditar['precio'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                    </div>
                    <div class="grupo-campo">
                        <label for="stock">Stock Disponible</label>
                        <input type="number" id="stock" name="stock" value="<?php echo $registroEditar ? htmlspecialchars($registroEditar['stock'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                    </div>
                <?php endif; ?>
            </div>

            <div class="actions-form">
                <button type="submit" class="btn-submit <?php echo $registroEditar ? 'btn-edit' : ''; ?>">
                    <?php echo $registroEditar ? 'Actualizar Registro' : 'Guardar Registro'; ?>
                </button>
                <?php if ($registroEditar): ?>
                    <a href="crud.php?tabla=<?php echo $tablaUrl; ?>" class="btn-cancelar">Cancelar Edición</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Tabla de Registros -->
    <div class="card-table">
        <div class="card-table-header">
            <h3>
                <?php 
                if ($tablaActiva === 'usuarios') echo '👥 Gestión de Usuarios Registrados';
                else echo '📦 Inventario Actual de ' . ($tablaActiva === 'catalogo_muebles' ? 'Muebles' : 'Zapatería'); 
                ?>
            </h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if ($tablaActiva === 'usuarios'): ?>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                    <?php elseif ($tablaActiva === 'catalogo_muebles'): ?>
                        <th>Artículo</th>
                        <th>Precio</th>
                        <th>Stock</th>
                    <?php else: ?>
                        <th>Modelo</th>
                        <th>Talla</th>
                        <th>Precio</th>
                        <th>Stock</th>
                    <?php endif; ?>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultado && $resultado->num_rows > 0): ?>
                    <?php while($row = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?php echo intval($row['id']); ?></strong></td>
                        
                        <?php if ($tablaActiva === 'usuarios'): ?>
                            <td><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="badge-stock <?php echo $row['rol'] === 'admin' ? 'admin' : ''; ?>">
                                    <?php echo strtoupper(htmlspecialchars($row['rol'], ENT_QUOTES, 'UTF-8')); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-stock <?php echo $row['confirmado'] == 1 ? 'ok' : 'low'; ?>">
                                    <?php echo $row['confirmado'] == 1 ? 'Activo' : 'Bloqueado'; ?>
                                </span>
                            </td>
                        <?php else: ?>
                            <?php if ($tablaActiva === 'catalogo_muebles'): ?>
                                <td><?php echo htmlspecialchars($row['articulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php else: ?>
                                <td><?php echo htmlspecialchars($row['modelo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['tALLA'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php endif; ?>
                            <td><strong>$<?php echo number_format($row['precio'], 2); ?></strong></td>
                            <td>
                                <span class="badge-stock <?php echo $row['stock'] > 0 ? 'ok' : 'low'; ?>">
                                    <?php echo intval($row['stock']); ?> unid.
                                </span>
                            </td>
                        <?php endif; ?>

                        <td style="text-align: center;">
                            <a href="crud.php?tabla=<?php echo $tablaUrl; ?>&editar=<?php echo intval($row['id']); ?>" class="action-btn btn-tabla-editar">Editar</a>
                            <a href="crud.php?tabla=<?php echo $tablaUrl; ?>&eliminar=<?php echo intval($row['id']); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>" class="action-btn btn-tabla-eliminar" onclick="return confirm('¿Seguro que deseas eliminar este registro?');">Eliminar</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">No hay registros en esta tabla.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
