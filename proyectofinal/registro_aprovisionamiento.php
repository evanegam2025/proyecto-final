<?php
// Configurar codificación UTF-8mb4 completa
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'utf-8');

session_start();

// ======================================================================
// CONFIGURACIÓN DB - MOVER AL INICIO PARA EVITAR ERRORES
// ======================================================================
$host = 'localhost';
$dbname = 'proyecto-final';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
    $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'");
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// ======================================================================
// FUNCIONES DE SEGURIDAD Y VALIDACIÓN
// ======================================================================

function validarSesion() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
        return ['success' => false, 'message' => 'Acceso denegado. No ha iniciado sesión.'];
    }
    return ['success' => true];
}

// Función para limpiar y validar entrada
function limpiarEntrada($data) {
    if (is_null($data)) return null;
    
    $data = (string) $data;
    // Normalizar UTF-8
    if (function_exists('mb_convert_encoding')) {
        $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    // Remover caracteres de control peligrosos
    $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    return $data;
}

function formatearFecha($fecha) {
    if (empty($fecha) || $fecha == '0000-00-00' || $fecha == '0000-00-00 00:00:00' || is_null($fecha)) {
        return 'Fecha no especificada';
    }
    
    try {
        $dateTime = new DateTime($fecha);
        return $dateTime->format('d/m/Y H:i');
    } catch (Exception $e) {
        return 'Fecha no especificada';
    }
}

function formatearFechaParaInput($fecha) {
    if (empty($fecha) || $fecha == '0000-00-00' || $fecha == '0000-00-00 00:00:00' || is_null($fecha)) {
        return date('Y-m-d');
    }
    
    try {
        $dateTime = new DateTime($fecha);
        return $dateTime->format('Y-m-d');
    } catch (Exception $e) {
        return date('Y-m-d');
    }
}

// Verificar sesión activa
$validacion = validarSesion();
if (!$validacion['success']) {
    header("Location: index.php");
    exit();
}

// Obtener datos de sesión con valores por defecto
$user_id = $_SESSION['user_id'] ?? '';
$nombre_usuario = $_SESSION['user_name'] ?? $_SESSION['nombre'] ?? 'Usuario';

// Obtener módulo dinámicamente desde la base de datos
$modulo = "Sistema de Aprovisionamiento"; // Valor por defecto
try {
    if (!empty($user_id)) {
        $stmt = $pdo->prepare("SELECT modulo FROM administrador WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario_data = $stmt->fetch();
        
        if ($usuario_data && !empty($usuario_data['modulo'])) {
            $modulo = "Sistema de " . $usuario_data['modulo'];
        }
    }
} catch (PDOException $e) {
    // Si hay error, mantener el valor por defecto
    $modulo = "Sistema de Aprovisionamiento";
}

// ======================================================================
// VARIABLES PARA LA VISTA
// ======================================================================
$venta_encontrada = null;
$agendamiento_encontrado = null;
$aprovisionamiento_encontrado = null;
$mensaje = null;
$tipo_mensaje = '';

// Si hay parámetros de consulta en GET, mostrar los datos
if (isset($_GET['cedula_consulta'])) {
    $cedula_consulta = limpiarEntrada($_GET['cedula_consulta']);
    if (!empty($cedula_consulta)) {
        // Consultar VENTA
        $stmt_venta = $pdo->prepare("SELECT * FROM ventas WHERE cedula = ? ORDER BY created_at DESC LIMIT 1");
        $stmt_venta->execute([$cedula_consulta]);
        $venta_encontrada = $stmt_venta->fetch();
        
        if ($venta_encontrada) {
            // Consultar AGENDAMIENTO
            $stmt_agenda = $pdo->prepare("SELECT * FROM agendamiento WHERE cedula_cliente = ? ORDER BY id DESC LIMIT 1");
            $stmt_agenda->execute([$cedula_consulta]);
            $agendamiento_encontrado = $stmt_agenda->fetch();

            // Consultar APROVISIONAMIENTO
            $stmt_aprov = $pdo->prepare("SELECT * FROM aprovisionamiento WHERE cedula_cliente = ? ORDER BY id DESC LIMIT 1");
            $stmt_aprov->execute([$cedula_consulta]);
            $aprovisionamiento_encontrado = $stmt_aprov->fetch();
        }
    }
}

// Mostrar mensajes de la API si están en los parámetros GET
if (isset($_GET['mensaje'])) {
    $mensaje = htmlspecialchars($_GET['mensaje'], ENT_QUOTES, 'UTF-8');
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}

// Cerrar sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php?logout=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Sistema de Aprovisionamiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/registroaprovisionamiento-styles.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <!-- Header y Perfil -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
            <div class="container">
                <a class="navbar-brand" href="#"><i class="bi bi-gear-wide-connected me-2"></i>Sistema de Aprovisionamiento</a>
                <div class="d-flex gap-2">
                    <a href="modulos.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver a Módulos</a>
                    <a href="descargar_bd.php" class="btn btn-outline-light btn-sm"><i class="bi bi-download"></i> Descargar BD</a>
                    <button type="button" class="btn btn-outline-light btn-sm" onclick="confirmarCerrarSesion()">
                        <i class="bi bi-box-arrow-right me-1"></i>Cerrar Sesión
                    </button>
                </div>
            </div>
        </nav>

        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="profile-section text-center">
                        <img src="imagenes/rostro.JPG" alt="Foto" class="profile-img mb-3">
                        <p><strong><?php echo htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                        <p><strong><?php echo htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    </div>
                </div>
            </div>

            <!-- Mensajes de Alerta -->
            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo htmlspecialchars($tipo_mensaje, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Sección de búsqueda -->
            <div class="search-section">
                <h4 class="mb-3"><i class="bi bi-search me-2"></i>Consultar Cliente por Cédula</h4>
                <form id="formConsulta" class="row g-3">
                    <div class="col-md-6">
                        <label for="cedula_consulta" class="form-label">Número de Cédula</label>
                        <input type="number" class="form-control" id="cedula_consulta" name="cedula_consulta" placeholder="Ingrese la cédula a consultar" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-custom w-100"><i class="bi bi-search me-1"></i>Consultar</button>
                    </div>
                </form>
            </div>
            
            <?php if ($venta_encontrada): ?>
                
                <!-- SECCIÓN DE VENTA CON BOTÓN EDITAR -->
                <div class="form-section">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="bi bi-file-earmark-person me-2"></i>Datos de la Venta</h4>
                        <div>
                            <button type="button" class="btn btn-outline-primary btn-sm me-3" data-bs-toggle="modal" data-bs-target="#modalEditarVenta">
                                <i class="bi bi-pencil-square me-1"></i> Editar Venta
                            </button>
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input" type="checkbox" role="switch" id="checkMostrarVenta" onchange="toggleDetails('checkMostrarVenta', 'detallesVentaContainer')">
                                <label class="form-check-label" for="checkMostrarVenta">Mostrar/Ocultar Detalles</label>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div id="detallesVentaContainer" style="display:none;">
                        <ul class="list-group">
                            <li class="list-group-item"><strong>ID Venta:</strong> <?php echo htmlspecialchars($venta_encontrada['id'], ENT_QUOTES, 'UTF-8'); ?></li>
                            <li class="list-group-item"><strong>Fecha Venta:</strong> 
                                <?php echo formatearFecha($venta_encontrada['fecha']); ?>
                            </li>
                            <li class="list-group-item"><strong>Cédula:</strong> <?php echo htmlspecialchars($venta_encontrada['cedula'], ENT_QUOTES, 'UTF-8'); ?></li>
                            <li class="list-group-item"><strong>Nombre Cliente:</strong> <?php echo htmlspecialchars($venta_encontrada['nombre'], ENT_QUOTES, 'UTF-8'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <?php if ($agendamiento_encontrado): ?>
                    <!-- SECCIÓN DE AGENDAMIENTO -->
                    <div class="form-section">
                        <div class="d-flex justify-content-between align-items-center">
                           <h4 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Datos del Agendamiento</h4>
                           <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="checkMostrarAgendamiento" onchange="toggleDetails('checkMostrarAgendamiento', 'detallesAgendamientoContainer')">
                                <label class="form-check-label" for="checkMostrarAgendamiento">Mostrar/Ocultar Detalles</label>
                            </div>
                        </div>
                        <hr>
                        <div id="detallesAgendamientoContainer" style="display:none;">
                            <ul class="list-group">
                                <li class="list-group-item"><strong>ID Agendamiento:</strong> <?php echo htmlspecialchars($agendamiento_encontrado['id'], ENT_QUOTES, 'UTF-8'); ?></li>
                                <li class="list-group-item"><strong>Técnico Asignado:</strong> <?php echo htmlspecialchars($agendamiento_encontrado['tecnico_asignado'], ENT_QUOTES, 'UTF-8'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <!-- SECCIÓN DE APROVISIONAMIENTO -->
                    <?php if ($aprovisionamiento_encontrado): ?>
                        <!-- MOSTRAR DATOS DEL APROVISIONAMIENTO EXISTENTE CON BOTONES EDITAR Y ELIMINAR -->
                        <div class="form-section">
                             <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0"><i class="bi bi-check-circle-fill text-success me-2"></i>Cliente Ya Aprovisionado</h4>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditarAprovisionamiento">
                                        <i class="bi bi-pencil-square me-1"></i> Editar
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarAprovisionamiento(<?php echo $aprovisionamiento_encontrado['id']; ?>)">
                                        <i class="bi bi-trash-fill me-1"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                            <hr>
                            <div class="list-group">
                                <div class="list-group-item"><strong>IP Navegación:</strong> <?php echo htmlspecialchars($aprovisionamiento_encontrado['ip_navegacion'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="list-group-item"><strong>Router/ONU:</strong> <?php echo htmlspecialchars($aprovisionamiento_encontrado['tipo_router_onu'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="list-group-item"><strong>Estado:</strong> <span class="badge bg-info fs-6"><?php echo htmlspecialchars($aprovisionamiento_encontrado['estado_aprovisionamiento'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                                <div class="list-group-item"><strong>Notas:</strong> <?php echo nl2br(htmlspecialchars($aprovisionamiento_encontrado['notas_aprovisionamiento'], ENT_QUOTES, 'UTF-8')); ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- FORMULARIO PARA CREAR APROVISIONAMIENTO -->
                        <div class="form-section">
                            <h3 class="mb-4"><i class="bi bi-tools me-2"></i>Registrar Aprovisionamiento</h3>
                            <form id="formAprovisionamiento">
                                <input type="hidden" name="cedula_cliente" value="<?php echo htmlspecialchars($venta_encontrada['cedula'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="tipo_radio" class="form-label fw-bold">Tipo Radio Instalado</label>
                                        <input type="text" class="form-control" id="tipo_radio" name="tipo_radio" placeholder="Ej: PowerBeam M5">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="mac_serial_radio" class="form-label fw-bold">MAC y Serial del Radio</label>
                                        <input type="text" class="form-control" id="mac_serial_radio" name="mac_serial_radio" placeholder="MAC/Serial">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="tipo_router_onu" class="form-label fw-bold">Tipo Router/ONU</label>
                                        <input type="text" class="form-control" id="tipo_router_onu" name="tipo_router_onu" placeholder="Ej: TP-Link Archer C6">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="mac_serial_router" class="form-label fw-bold">MAC y Serial Router/ONU</label>
                                        <input type="text" class="form-control" id="mac_serial_router" name="mac_serial_router" placeholder="MAC/Serial">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ip_navegacion" class="form-label fw-bold">IP de Navegación</label>
                                        <input type="text" class="form-control" id="ip_navegacion" name="ip_navegacion" placeholder="Ej: 192.168.10.25" >
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ip_gestion" class="form-label fw-bold">IP de Gestión</label>
                                        <input type="text" class="form-control" id="ip_gestion" name="ip_gestion" placeholder="Ej: 10.10.1.50">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="metros_cable" class="form-label fw-bold">Metros Cable Usado</label>
                                        <input type="number" class="form-control" id="metros_cable" name="metros_cable" placeholder="Ej: 25">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="tipo_cable" class="form-label fw-bold">Tipo Cable Usado</label>
                                        <select class="form-select" id="tipo_cable" name="tipo_cable">
                                            <option value="">-- Seleccione --</option>
                                            <option value="DROP">DROP</option>
                                            <option value="UTP">UTP</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label for="notas_aprovisionamiento" class="form-label fw-bold">Notas Aprovisionamiento *</label>
                                        <textarea class="form-control" id="notas_aprovisionamiento" name="notas_aprovisionamiento" rows="3" placeholder="Detalles de la instalación, materiales adicionales, etc." required></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="estado_aprovisionamiento" class="form-label fw-bold">Estado *</label>
                                        <select class="form-select" id="estado_aprovisionamiento" name="estado_aprovisionamiento" required>
                                            <option value="CUMPLIDO">CUMPLIDO</option>
                                            <option value="PENDIENTE">PENDIENTE</option>
                                            <option value="REPROGRAMAR">REPROGRAMAR</option>
                                            <option value="CANCELADO">CANCELADO</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 mt-4 text-end">
                                    <button type="submit" class="btn btn-custom btn-lg"><i class="bi bi-save me-1"></i> Guardar Aprovisionamiento</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- MENSAJE DE ADVERTENCIA -->
                    <div class="alert alert-warning">
                        <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Acción Requerida</h4>
                        <p>No es posible aprovisionar esta venta porque aún no ha sido agendada.</p>
                        <hr>
                        <p class="mb-0">Por favor, pida al módulo de <strong>Agendamiento</strong> que programe una visita para el cliente: <strong><?php echo htmlspecialchars($venta_encontrada['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODALES PARA EDITAR -->
    <?php if ($venta_encontrada): ?>
    <!-- Modal para Editar Venta -->
    <div class="modal fade" id="modalEditarVenta" tabindex="-1" aria-labelledby="modalEditarVentaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarVentaLabel"><i class="bi bi-pencil-square me-2"></i>Editar Datos de la Venta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarVenta">
                        <input type="hidden" name="id_venta_edit" value="<?php echo $venta_encontrada['id']; ?>">
                        <input type="hidden" name="cedula_edit" value="<?php echo $venta_encontrada['cedula']; ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" name="fecha_edit" 
                                       value="<?php echo formatearFechaParaInput($venta_encontrada['fecha']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nombre Cliente</label>
                                <input type="text" class="form-control" name="nombre_edit" value="<?php echo htmlspecialchars($venta_encontrada['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono 1</label>
                                <input type="text" class="form-control" name="telefono1_edit" value="<?php echo htmlspecialchars($venta_encontrada['telefono1'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono 2</label>
                                <input type="text" class="form-control" name="telefono2_edit" value="<?php echo htmlspecialchars($venta_encontrada['telefono2'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email_edit" value="<?php echo htmlspecialchars($venta_encontrada['email'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Municipio</label>
                                <input type="text" class="form-control" name="municipio_edit" value="<?php echo htmlspecialchars($venta_encontrada['municipio'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Vereda</label>
                                <input type="text" class="form-control" name="vereda_edit" value="<?php echo htmlspecialchars($venta_encontrada['vereda'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Coordenadas</label>
                                <input type="text" class="form-control" name="coordenadas_edit" value="<?php echo htmlspecialchars($venta_encontrada['coordenadas'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Número Servicio</label>
                                <input type="text" class="form-control" name="num_servicio_edit" value="<?php echo htmlspecialchars($venta_encontrada['num_servicio'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tecnología</label>
                                <input type="text" class="form-control" name="tecnologia_edit" value="<?php echo htmlspecialchars($venta_encontrada['tecnologia'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Plan</label>
                                <input type="text" class="form-control" name="plan_edit" value="<?php echo htmlspecialchars($venta_encontrada['plan'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Indicaciones</label>
                                <textarea class="form-control" name="indicaciones_edit" rows="3"><?php echo htmlspecialchars($venta_encontrada['indicaciones'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notas Venta</label>
                                <textarea class="form-control" name="notas_edit" rows="3"><?php echo htmlspecialchars($venta_encontrada['notas'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer mt-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-custom"><i class="bi bi-save me-1"></i> Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($aprovisionamiento_encontrado): ?>
    <!-- Modal para Editar Aprovisionamiento -->
    <div class="modal fade" id="modalEditarAprovisionamiento" tabindex="-1" aria-labelledby="modalEditarAprovisionamientoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarAprovisionamientoLabel"><i class="bi bi-pencil-square me-2"></i>Editar Aprovisionamiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                     <form id="formEditarAprovisionamiento">
                        <input type="hidden" name="id_aprovisionamiento_edit" value="<?php echo $aprovisionamiento_encontrado['id']; ?>">
                        <input type="hidden" name="cedula_original" value="<?php echo $aprovisionamiento_encontrado['cedula_cliente']; ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tipo Radio</label>
                                <input type="text" class="form-control" name="tipo_radio_edit" value="<?php echo htmlspecialchars($aprovisionamiento_encontrado['tipo_radio'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">MAC/Serial Radio</label>
                                <input type="text" class="form-control" name="mac_serial_radio_edit" value="<?php echo htmlspecialchars($aprovisionamiento_encontrado['mac_serial_radio'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tipo Router/ONU *</label>
                                <input type="text" class="form-control" name="tipo_router_onu_edit" value="<?php echo htmlspecialchars($aprovisionamiento_encontrado['tipo_router_onu'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">MAC/Serial Router *</label>
                                <input type="text" class="form-control" name="mac_serial_router_edit" value="<?php echo htmlspecialchars($aprovisionamiento_encontrado['mac_serial_router'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">IP Navegación *</label>
                                <input type="text" class="form-control" name="ip_navegacion_edit" value="<?php echo htmlspecialchars($aprovisionamiento_encontrado['ip_navegacion'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">IP Gestión</label>
                                <input type="text" class="form-control" name="ip_gestion_edit" value="<?php echo htmlspecialchars($aprovisionamiento_encontrado['ip_gestion'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Metros Cable *</label>
                                <input type="number" class="form-control" name="metros_cable_edit" value="<?php echo htmlspecialchars($aprovisionamiento_encontrado['metros_cable'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tipo Cable</label>
                                <select class="form-select" name="tipo_cable_edit">
                                    <option value="DROP" <?php if ($aprovisionamiento_encontrado['tipo_cable'] == 'DROP') echo 'selected'; ?>>DROP</option>
                                    <option value="UTP" <?php if ($aprovisionamiento_encontrado['tipo_cable'] == 'UTP') echo 'selected'; ?>>UTP</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Notas *</label>
                                <textarea class="form-control" name="notas_aprovisionamiento_edit" rows="3" required><?php echo htmlspecialchars($aprovisionamiento_encontrado['notas_aprovisionamiento'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Estado *</label>
                                <select class="form-select" name="estado_aprovisionamiento_edit" required>
                                    <option value="CUMPLIDO" <?php if ($aprovisionamiento_encontrado['estado_aprovisionamiento'] == 'CUMPLIDO') echo 'selected'; ?>>CUMPLIDO</option>
                                    <option value="PENDIENTE" <?php if ($aprovisionamiento_encontrado['estado_aprovisionamiento'] == 'PENDIENTE') echo 'selected'; ?>>PENDIENTE</option>
                                    <option value="REPROGRAMAR" <?php if ($aprovisionamiento_encontrado['estado_aprovisionamiento'] == 'REPROGRAMAR') echo 'selected'; ?>>REPROGRAMAR</option>
                                    <option value="CANCELADO" <?php if ($aprovisionamiento_encontrado['estado_aprovisionamiento'] == 'CANCELADO') echo 'selected'; ?>>CANCELADO</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer mt-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-custom"><i class="bi bi-save me-1"></i> Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/registroaprovisionamiento.js"></script>
</body>
</html>