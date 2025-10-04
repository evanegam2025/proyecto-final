<?php
// ======================================================================
// INTERFAZ PRINCIPAL - REGISTRO DE VENTAS
// ======================================================================

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "proyecto-final";

// Crear conexión y establecer charset a UTF-8mb4
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

session_start();

// VERIFICACIÓN CONSISTENTE CON MODULOS.PHP
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    header("Location: index.php");
    exit();
}

// Obtener información completa del usuario de la base de datos
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, cedula, nombre, email, modulo FROM administrador WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Usuario no encontrado, cerrar sesión
    session_destroy();
    header("Location: index.php?error=usuario_no_encontrado");
    exit();
}

$user_data = $result->fetch_assoc();

// Establecer variables sanitizadas
$user_id = htmlspecialchars($user_data['id'], ENT_QUOTES, 'UTF-8');
$cedula_usuario = htmlspecialchars($user_data['cedula'], ENT_QUOTES, 'UTF-8');
$nombre_usuario = htmlspecialchars($user_data['nombre'], ENT_QUOTES, 'UTF-8');
$email_usuario = htmlspecialchars($user_data['email'], ENT_QUOTES, 'UTF-8');
$modulo_usuario = htmlspecialchars($user_data['modulo'], ENT_QUOTES, 'UTF-8');

// Actualizar variables de sesión para consistencia
$_SESSION['cedula'] = $cedula_usuario;
$_SESSION['usuario'] = $cedula_usuario;
$_SESSION['nombre'] = $nombre_usuario;
$_SESSION['modulo'] = $modulo_usuario;

// Configurar zona horaria de Colombia
date_default_timezone_set('America/Bogota');

// Obtener fecha y hora actual
$fecha_actual = date('d/m/Y');
$hora_actual = date('h:i:s A');

// Traducir día de la semana al español
$dias_semana = [
    'Monday' => 'Lunes',
    'Tuesday' => 'Martes', 
    'Wednesday' => 'Miércoles',
    'Thursday' => 'Jueves',
    'Friday' => 'Viernes',
    'Saturday' => 'Sábado',
    'Sunday' => 'Domingo'
];

$meses = [
    'January' => 'Enero',
    'February' => 'Febrero',
    'March' => 'Marzo',
    'April' => 'Abril',
    'May' => 'Mayo',
    'June' => 'Junio',
    'July' => 'Julio',
    'August' => 'Agosto',
    'September' => 'Septiembre',
    'October' => 'Octubre',
    'November' => 'Noviembre',
    'December' => 'Diciembre'
];

$dia_actual = date('l');
$mes_actual = date('F');
$fecha_completa = date('l, d \d\e F \d\e Y');

$fecha_formateada = str_replace(
    [$dia_actual, $mes_actual], 
    [$dias_semana[$dia_actual], $meses[$mes_actual]], 
    $fecha_completa
);

// OBTENER ESTADÍSTICAS INICIALES
$stmt_stats = $conn->prepare("SELECT COUNT(*) as total_ventas, COUNT(CASE WHEN DATE(fecha) = CURDATE() THEN 1 END) as ventas_hoy, COUNT(CASE WHEN YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) as ventas_semana, COUNT(CASE WHEN MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) THEN 1 END) as ventas_mes FROM ventas WHERE vendedor_cedula = ?");

$statsData = ['ventas_hoy' => 0, 'ventas_semana' => 0, 'ventas_mes' => 0, 'total_ventas' => 0];

if ($stmt_stats) {
    $stmt_stats->bind_param("s", $cedula_usuario);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    if ($result_stats->num_rows > 0) {
        $statsData = $result_stats->fetch_assoc();
    }
    $stmt_stats->close();
}

// OBTENER LAS ÚLTIMAS 5 VENTAS DEL VENDEDOR PARA LA TABLA CRUD
$stmt_ventas = $conn->prepare("SELECT id, fecha, nombre, cedula, telefono1, plan, tecnologia FROM ventas WHERE vendedor_cedula = ? ORDER BY fecha DESC LIMIT 5");
$mis_ventas = null;

if ($stmt_ventas) {
    $stmt_ventas->bind_param("s", $cedula_usuario);
    $stmt_ventas->execute();
    $mis_ventas = $stmt_ventas->get_result();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISTEMA DE VENTAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/registroventas-styles.css" rel="stylesheet">
</head>
<body>
    <!-- Contenedor para Alertas Dinámicas -->
    <div id="alert-container"></div>

    <div class="container-fluid py-4">
        <!-- Barra superior con información del usuario -->
        <div class="user-info-bar d-flex justify-content-between align-items-center mb-4">
            <div class="user-welcome">
                <div class="user-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="user-details">
                    <h6>Bienvenido, <?php echo $nombre_usuario; ?></h6>
                    <div class="user-role">
                        <i class="bi bi-shield-check me-1"></i>
                        <?php echo $modulo_usuario; ?>
                    </div>
                    <small class="d-block mt-1 opacity-75">
                        <i class="bi bi-person-check-fill me-1"></i>
                        Sesión activa verificada
                    </small>
                </div>
            </div>
            <div class="user-actions d-flex align-items-center gap-3">
                <div class="datetime-info">
                    <div class="current-datetime">
                        <i class="bi bi-calendar-event me-1"></i>
                        <span class="date-text"><?php echo $fecha_actual; ?></span>
                    </div>
                    <div class="current-time">
                        <i class="bi bi-clock me-1"></i>
                        <span class="time-text" id="currentTime"><?php echo $hora_actual; ?></span>
                    </div>
                    <small class="full-date d-block"><?php echo $fecha_formateada; ?></small>
                </div>
                <div class="action-buttons">
                    <a href="modulos.php" class="btn btn-back-modules btn-sm" title="Volver a Módulos">
                        <i class="bi bi-arrow-left-circle me-1"></i>
                        Módulos
                    </a>
                    <a href="descargar_bd.php" class="btn btn-outline-info btn-sm" title="Descargar Base de Datos">
                        <i class="bi bi-download"></i>
                    </a>
                    <button class="btn btn-outline-danger btn-sm" onclick="logout()" title="Cerrar Sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Header -->
        <header class="text-center mb-4">
            <h1 class="display-4 text-primary">
                <i class="bi bi-cart-check-fill"></i> 
                GESTIÓN DE VENTAS
            </h1>
            <p class="lead text-muted">Sistema de registro y administración de ventas</p>
        </header>

        <!-- Estadísticas del Vendedor -->
        <div class="card border-success mb-4 shadow-sm" id="stats-container">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Mis Estadísticas de Ventas</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-2 mb-md-0">
                        <div class="bg-primary bg-opacity-75 text-white p-3 rounded">
                            <h4 id="stats-hoy"><?php echo $statsData['ventas_hoy'] ?? 0; ?></h4>
                            <small>Ventas Hoy</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2 mb-md-0">
                        <div class="bg-info bg-opacity-75 text-white p-3 rounded">
                            <h4 id="stats-semana"><?php echo $statsData['ventas_semana'] ?? 0; ?></h4>
                            <small>Esta Semana</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2 mb-md-0">
                        <div class="bg-warning text-dark p-3 rounded">
                            <h4 id="stats-mes"><?php echo $statsData['ventas_mes'] ?? 0; ?></h4>
                            <small>Este Mes</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-success text-white p-3 rounded">
                            <h4 id="stats-total"><?php echo $statsData['total_ventas'] ?? 0; ?></h4>
                            <small>Total Ventas</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gestión de Ventas (Tabla CRUD) -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-table"></i> Mis Ventas Registradas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Cédula</th>
                                <th>Teléfono</th>
                                <th>Plan</th>
                                <th>Tecnología</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($mis_ventas && $mis_ventas->num_rows > 0): ?>
                                <?php while ($venta = $mis_ventas->fetch_assoc()): ?>
                                <tr id="venta-row-<?php echo htmlspecialchars($venta['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                    <td><?php echo htmlspecialchars($venta['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($venta['cedula'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($venta['telefono1'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($venta['plan'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($venta['tecnologia'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="handleEdit(<?php echo htmlspecialchars($venta['id'], ENT_QUOTES, 'UTF-8'); ?>)" title="Editar Venta">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="handleDelete(<?php echo htmlspecialchars($venta['id'], ENT_QUOTES, 'UTF-8'); ?>)" title="Eliminar Venta">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted p-4">
                                        <i class="bi bi-inbox-fill display-4"></i>
                                        <p class="mt-2">Aún no tienes ventas registradas.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Checkbox para mostrar formulario de NUEVA venta -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <div class="checkbox-container">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="toggleFormulario" onchange="toggleVentaForm()">
                        <label class="form-check-label fw-bold" for="toggleFormulario">
                            <i class="bi bi-plus-circle-dotted text-primary"></i> Ingresar Nueva Venta
                        </label>
                        <small class="text-muted d-block mt-1">Marque esta opción para mostrar/ocultar el formulario de registro.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulario de NUEVA venta (Oculto) -->
        <div class="row formulario-venta" id="formularioVenta">
            <div class="col-12">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clipboard-data-fill"></i> Formulario de Registro de Venta</h5>
                        <button type="button" class="btn btn-sm btn-outline-light" onclick="limpiarFormulario()">
                            <i class="bi bi-eraser-fill"></i> Limpiar
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <form id="ventaForm">
                            <fieldset class="mb-4 border p-3 rounded">
                                <legend class="float-none w-auto px-2 h6 text-success">
                                    <i class="bi bi-person-badge-fill"></i> Vendedor Responsable
                                </legend>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label">Cédula:</label>
                                        <input type="text" class="form-control" value="<?php echo $cedula_usuario; ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Nombre:</label>
                                        <input type="text" class="form-control" value="<?php echo $nombre_usuario; ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Módulo:</label>
                                        <input type="text" class="form-control" value="<?php echo $modulo_usuario; ?>" readonly>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="mb-4 border p-3 rounded">
                                <legend class="float-none w-auto px-2 h6 text-primary">
                                    <i class="bi bi-person-fill"></i> Información del Cliente
                                </legend>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Cédula:</label>
                                        <input type="number" class="form-control" name="cedula" placeholder="Número de documento" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Nombre Completo:</label>
                                        <input type="text" class="form-control" name="nombre" placeholder="Nombre y apellidos" required maxlength="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Teléfono Principal:</label>
                                        <input type="tel" class="form-control" name="telefono1" placeholder="3XX XXX XXXX" required maxlength="15">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Teléfono Secundario:</label>
                                        <input type="tel" class="form-control" name="telefono2" placeholder="(Opcional)" maxlength="15">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Correo Electrónico:</label>
                                        <input type="email" class="form-control" name="email" placeholder="correo@ejemplo.com" required maxlength="100">
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="mb-4 border p-3 rounded">
                                <legend class="float-none w-auto px-2 h6 text-primary">
                                    <i class="bi bi-gear-fill"></i> Detalles y Ubicación
                                </legend>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Tecnología:</label>
                                        <select class="form-select" name="tecnologia" required>
                                            <option value="">Seleccionar...</option>
                                            <option value="Fibra Óptica">Fibra Óptica</option>
                                            <option value="Radio Enlace">Radio Enlace</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Plan Contratado:</label>
                                        <select class="form-select" name="plan" required>
                                            <option value="">Seleccionar...</option>
                                            <option value="2 Megas">2 Megas</option>
                                            <option value="4 Megas">4 Megas</option>
                                            <option value="6 Megas">6 Megas</option>
                                            <option value="10 Megas">10 Megas</option>
                                            <option value="40 Megas">40 Megas</option>
                                            <option value="80 Megas">80 Megas</option>
                                            <option value="100 Megas">100 Megas</option>
                                            <option value="200 Megas">200 Megas</option>
                                            <option value="300 Megas">300 Megas</option>
                                            <option value="500 Megas">500 Megas</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Número de Servicio:</label>
                                        <select class="form-select" name="num_servicio" required>
                                            <option value="">Seleccionar...</option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Municipio:</label>
                                        <input type="text" class="form-control" name="municipio" placeholder="Municipio de instalación" required maxlength="50">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Vereda/Dirección:</label>
                                        <input type="text" class="form-control" name="vereda" placeholder="Dirección, barrio o vereda" required maxlength="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Coordenadas GPS:</label>
                                        <input type="text" class="form-control" name="coordenadas" placeholder="Ej: 6.244, -75.581" maxlength="50">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Fecha de Venta:</label>
                                        <input type="datetime-local" class="form-control" name="fecha" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Indicaciones de Llegada:</label>
                                        <textarea class="form-control" name="indicaciones" rows="2" placeholder="Puntos de referencia, color de la casa, etc." required maxlength="500"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Notas Adicionales:</label>
                                        <textarea class="form-control" name="notas" rows="2" placeholder="(Opcional)" maxlength="500"></textarea>
                                    </div>
                                </div>
                            </fieldset>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-custom btn-lg">
                                    <i class="bi bi-save-fill"></i> Guardar Venta
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div> <!-- Fin container-fluid -->

    <!-- ======================== MODAL PARA EDITAR VENTA ======================== -->
    <div class="modal fade" id="editVentaModal" tabindex="-1" aria-labelledby="editVentaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editVentaModalLabel">
                        <i class="bi bi-pencil-fill"></i> Editar Venta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editVentaForm">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <fieldset class="mb-4 border p-3 rounded">
                            <legend class="float-none w-auto px-2 h6 text-primary">
                                <i class="bi bi-person-fill"></i> Información del Cliente
                            </legend>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Cédula:</label>
                                    <input type="number" class="form-control" name="cedula" id="edit_cedula" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Nombre Completo:</label>
                                    <input type="text" class="form-control" name="nombre" id="edit_nombre" required maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Teléfono Principal:</label>
                                    <input type="tel" class="form-control" name="telefono1" id="edit_telefono1" required maxlength="15">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Teléfono Secundario:</label>
                                    <input type="tel" class="form-control" name="telefono2" id="edit_telefono2" maxlength="15">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Correo Electrónico:</label>
                                    <input type="email" class="form-control" name="email" id="edit_email" required maxlength="100">
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="mb-4 border p-3 rounded">
                            <legend class="float-none w-auto px-2 h6 text-primary">
                                <i class="bi bi-gear-fill"></i> Detalles y Ubicación
                            </legend>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Tecnología:</label>
                                    <select class="form-select" name="tecnologia" id="edit_tecnologia" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="Fibra Óptica">Fibra Óptica</option>
                                        <option value="Radio Enlace">Radio Enlace</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Plan Contratado:</label>
                                    <select class="form-select" name="plan" id="edit_plan" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="2 Megas">2 Megas</option>
                                        <option value="4 Megas">4 Megas</option>
                                        <option value="6 Megas">6 Megas</option>
                                        <option value="10 Megas">10 Megas</option>
                                        <option value="40 Megas">40 Megas</option>
                                        <option value="80 Megas">80 Megas</option>
                                        <option value="100 Megas">100 Megas</option>
                                        <option value="200 Megas">200 Megas</option>
                                        <option value="300 Megas">300 Megas</option>
                                        <option value="500 Megas">500 Megas</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Número de Servicio:</label>
                                    <select class="form-select" name="num_servicio" id="edit_num_servicio" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Municipio:</label>
                                    <input type="text" class="form-control" name="municipio" id="edit_municipio" required maxlength="50">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Vereda/Dirección:</label>
                                    <input type="text" class="form-control" name="vereda" id="edit_vereda" required maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Coordenadas GPS:</label>
                                    <input type="text" class="form-control" name="coordenadas" id="edit_coordenadas" maxlength="50">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Fecha de Venta:</label>
                                    <input type="datetime-local" class="form-control" name="fecha" id="edit_fecha" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Indicaciones de Llegada:</label>
                                    <textarea class="form-control" name="indicaciones" id="edit_indicaciones" rows="2" required maxlength="500"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Notas Adicionales:</label>
                                    <textarea class="form-control" name="notas" id="edit_notas" rows="2" maxlength="500"></textarea>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="editVentaForm" class="btn btn-warning">
                        <i class="bi bi-save-fill"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/registroventas.js"></script>
</body>
</html>
<?php
// No cerrar la conexión aquí ya que se cerró anteriormente
?>