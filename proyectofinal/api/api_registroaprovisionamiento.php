<?php
// Configurar codificación UTF-8mb4 completa
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
header('Content-Type: application/json; charset=UTF-8');
ini_set('default_charset', 'utf-8');

// Solo permitir peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit();
}

session_start();

// ======================================================================
// CONFIGURACIÓN DB
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión'], JSON_UNESCAPED_UNICODE);
    exit();
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

function limpiarEntrada($data) {
    if (is_null($data) || $data === '') return null;
    
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
    
    return empty($data) ? null : $data;
}

function validarYFormatearFecha($fecha_input) {
    if (empty($fecha_input) || $fecha_input == '0000-00-00') {
        return date('Y-m-d H:i:s');
    }
    
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_input)) {
        $partes = explode('-', $fecha_input);
        if (checkdate($partes[1], $partes[2], $partes[0])) {
            return $fecha_input . ' ' . date('H:i:s');
        }
    }
    
    return date('Y-m-d H:i:s');
}

// Verificar sesión activa
$validacion = validarSesion();
if (!$validacion['success']) {
    http_response_code(401);
    echo json_encode($validacion, JSON_UNESCAPED_UNICODE);
    exit();
}

// ======================================================================
// PROCESAMIENTO DE ACCIONES
// ======================================================================

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    // Determinar la acción a realizar
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'consultar_cedula':
            $cedula_consulta = limpiarEntrada($_POST['cedula_consulta'] ?? '');
            if (empty($cedula_consulta)) {
                $response['message'] = 'Cédula es requerida';
                break;
            }

            // 1. Consultar VENTA
            $stmt_venta = $pdo->prepare("SELECT * FROM ventas WHERE cedula = ? ORDER BY created_at DESC LIMIT 1");
            $stmt_venta->execute([$cedula_consulta]);
            $venta_encontrada = $stmt_venta->fetch();
            
            if ($venta_encontrada) {
                // 2. Consultar AGENDAMIENTO
                $stmt_agenda = $pdo->prepare("SELECT * FROM agendamiento WHERE cedula_cliente = ? ORDER BY id DESC LIMIT 1");
                $stmt_agenda->execute([$cedula_consulta]);
                $agendamiento_encontrado = $stmt_agenda->fetch();

                // 3. Consultar APROVISIONAMIENTO
                $stmt_aprov = $pdo->prepare("SELECT * FROM aprovisionamiento WHERE cedula_cliente = ? ORDER BY id DESC LIMIT 1");
                $stmt_aprov->execute([$cedula_consulta]);
                $aprovisionamiento_encontrado = $stmt_aprov->fetch();

                $response['success'] = true;
                $response['data'] = [
                    'venta' => $venta_encontrada,
                    'agendamiento' => $agendamiento_encontrado,
                    'aprovisionamiento' => $aprovisionamiento_encontrado
                ];
                $response['message'] = 'Datos consultados exitosamente';
            } else {
                $response['message'] = 'No se encontró ninguna VENTA registrada con la cédula ' . htmlspecialchars($cedula_consulta, ENT_QUOTES, 'UTF-8') . '.';
            }
            break;

        case 'guardar_aprovisionamiento':
            $cedula_cliente = limpiarEntrada($_POST['cedula_cliente'] ?? '');
            $estado = limpiarEntrada($_POST['estado_aprovisionamiento'] ?? 'PENDIENTE');

            if (empty($cedula_cliente)) {
                $response['message'] = 'Error: No se ha especificado una cédula de cliente.';
                break;
            }

            // Solo las notas son obligatorias
            $notas = limpiarEntrada($_POST['notas_aprovisionamiento'] ?? '');
            if (empty($notas)) {
                $response['message'] = 'Las notas de aprovisionamiento son obligatorias.';
                break;
            }

            // Obtener todos los campos sin validaciones adicionales
            $tipo_radio = limpiarEntrada($_POST['tipo_radio'] ?? '');
            $mac_serial_radio = limpiarEntrada($_POST['mac_serial_radio'] ?? '');
            $tipo_router_onu = limpiarEntrada($_POST['tipo_router_onu'] ?? '') ?: 'PENDIENTE DE ASIGNACIÓN';
            $mac_serial_router = limpiarEntrada($_POST['mac_serial_router'] ?? '') ?: 'PENDIENTE';
            $ip_navegacion = limpiarEntrada($_POST['ip_navegacion'] ?? '') ?: '0.0.0.0';
            $ip_gestion = limpiarEntrada($_POST['ip_gestion'] ?? '');
            
            // Manejar metros de cable: si está vacío o no es numérico, usar 0
            $metros_cable = 0;
            if (!empty($_POST['metros_cable']) && is_numeric($_POST['metros_cable'])) {
                $metros_cable = (int)$_POST['metros_cable'];
            }
            
            $tipo_cable = limpiarEntrada($_POST['tipo_cable'] ?? '');

            $sql = "INSERT INTO aprovisionamiento 
                    (cedula_cliente, tipo_radio, mac_serial_radio, tipo_router_onu, mac_serial_router, 
                     ip_navegacion, ip_gestion, metros_cable, tipo_cable, notas_aprovisionamiento, estado_aprovisionamiento) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $params = [
                $cedula_cliente,
                $tipo_radio,
                $mac_serial_radio,
                $tipo_router_onu,
                $mac_serial_router,
                $ip_navegacion,
                $ip_gestion,
                $metros_cable,
                $tipo_cable,
                $notas,
                $estado
            ];
            
            if ($stmt->execute($params)) {
                $response['success'] = true;
                $response['message'] = 'Aprovisionamiento guardado exitosamente!';
                $response['data'] = ['cedula_cliente' => $cedula_cliente];
            } else {
                $response['message'] = 'Error al guardar el aprovisionamiento.';
            }
            break;

        case 'actualizar_aprovisionamiento':
            $estado = limpiarEntrada($_POST['estado_aprovisionamiento_edit'] ?? '');
            $notas = limpiarEntrada($_POST['notas_aprovisionamiento_edit'] ?? '');

            if (empty($notas)) {
                $response['message'] = 'Las notas de aprovisionamiento son obligatorias.';
                break;
            }

            // Obtener todos los campos sin validaciones adicionales
            $tipo_radio = limpiarEntrada($_POST['tipo_radio_edit'] ?? '');
            $mac_serial_radio = limpiarEntrada($_POST['mac_serial_radio_edit'] ?? '');
            $tipo_router_onu = limpiarEntrada($_POST['tipo_router_onu_edit'] ?? '') ?: 'PENDIENTE DE ASIGNACIÓN';
            $mac_serial_router = limpiarEntrada($_POST['mac_serial_router_edit'] ?? '') ?: 'PENDIENTE';
            $ip_navegacion = limpiarEntrada($_POST['ip_navegacion_edit'] ?? '') ?: '0.0.0.0';
            $ip_gestion = limpiarEntrada($_POST['ip_gestion_edit'] ?? '');
            
            // Manejar metros de cable: si está vacío o no es numérico, usar 0
            $metros_cable = 0;
            if (!empty($_POST['metros_cable_edit']) && is_numeric($_POST['metros_cable_edit'])) {
                $metros_cable = (int)$_POST['metros_cable_edit'];
            }
            
            $tipo_cable = limpiarEntrada($_POST['tipo_cable_edit'] ?? '');

            $sql = "UPDATE aprovisionamiento SET 
                        tipo_radio = ?, mac_serial_radio = ?, tipo_router_onu = ?, mac_serial_router = ?, 
                        ip_navegacion = ?, ip_gestion = ?, metros_cable = ?, tipo_cable = ?, 
                        notas_aprovisionamiento = ?, estado_aprovisionamiento = ?
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $params = [
                $tipo_radio,
                $mac_serial_radio,
                $tipo_router_onu,
                $mac_serial_router,
                $ip_navegacion,
                $ip_gestion,
                $metros_cable,
                $tipo_cable,
                $notas,
                $estado,
                filter_var($_POST['id_aprovisionamiento_edit'] ?? 0, FILTER_VALIDATE_INT)
            ];

            if ($stmt->execute($params)) {
                $response['success'] = true;
                $response['message'] = 'Aprovisionamiento actualizado exitosamente!';
                $response['data'] = ['cedula_original' => $_POST['cedula_original'] ?? ''];
            } else {
                $response['message'] = 'Error al actualizar el aprovisionamiento.';
            }
            break;

        case 'actualizar_venta':
            // Validar y formatear fecha
            $fecha_edit = validarYFormatearFecha($_POST['fecha_edit'] ?? '');
            
            $sql = "UPDATE ventas SET 
                        fecha = ?, nombre = ?, telefono1 = ?, telefono2 = ?, email = ?, 
                        municipio = ?, vereda = ?, coordenadas = ?, indicaciones = ?, notas = ?, 
                        num_servicio = ?, tecnologia = ?, plan = ?
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);
            $params = [
                $fecha_edit,
                limpiarEntrada($_POST['nombre_edit'] ?? ''), 
                limpiarEntrada($_POST['telefono1_edit'] ?? ''), 
                limpiarEntrada($_POST['telefono2_edit'] ?? ''), 
                filter_var($_POST['email_edit'] ?? '', FILTER_VALIDATE_EMAIL) ?: '', 
                limpiarEntrada($_POST['municipio_edit'] ?? ''), 
                limpiarEntrada($_POST['vereda_edit'] ?? ''), 
                limpiarEntrada($_POST['coordenadas_edit'] ?? ''), 
                limpiarEntrada($_POST['indicaciones_edit'] ?? ''), 
                limpiarEntrada($_POST['notas_edit'] ?? ''), 
                limpiarEntrada($_POST['num_servicio_edit'] ?? ''), 
                limpiarEntrada($_POST['tecnologia_edit'] ?? ''), 
                limpiarEntrada($_POST['plan_edit'] ?? ''), 
                filter_var($_POST['id_venta_edit'] ?? 0, FILTER_VALIDATE_INT)
            ];
            
            if ($stmt->execute($params)) {
                $response['success'] = true;
                $response['message'] = 'Venta actualizada exitosamente!';
                $response['data'] = ['cedula_edit' => $_POST['cedula_edit'] ?? ''];
            } else {
                $response['message'] = 'Error al actualizar la venta.';
            }
            break;

        case 'borrar_aprovisionamiento':
            $id_a_borrar = filter_var($_POST['id_aprovisionamiento_a_borrar'] ?? 0, FILTER_VALIDATE_INT);
            if ($id_a_borrar === false || $id_a_borrar <= 0) {
                throw new InvalidArgumentException("ID de aprovisionamiento inválido");
            }
            
            $sql = "DELETE FROM aprovisionamiento WHERE id = ?";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$id_a_borrar])) {
                $response['success'] = true;
                $response['message'] = 'Aprovisionamiento eliminado exitosamente!';
            } else {
                $response['message'] = 'Error al eliminar el aprovisionamiento.';
            }
            break;

        default:
            $response['message'] = 'Acción no válida';
            break;
    }

} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Enviar respuesta JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
?>