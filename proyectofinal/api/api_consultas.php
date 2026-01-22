<?php
// ======================================================================
// API REST - SISTEMA DE CONSULTA DE CÉDULAS - VERSIÓN CORREGIDA
// ======================================================================

// Desactivar output de errores HTML para mantener JSON limpio
ini_set('display_errors', 0);
error_reporting(0);

// Configuración de codificación UTF-8
header('Content-Type: application/json; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Configuración CORS para desarrollo
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Capturar cualquier output buffer para mantener JSON limpio
ob_start();

try {
    // Incluir archivo de conexión
    require_once '../conex_bd.php';
    
     
    // Limpiar cualquier output previo
    if (ob_get_level()) {
        ob_clean();
    }
    
} catch (Exception $e) {
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error de configuración del sistema.',
        'codigo' => 0,
        'debug' => 'Error al incluir archivos de configuración: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Función para enviar respuesta JSON y terminar ejecución
 */
function sendJsonResponse($data, $statusCode = 200) {
    // Limpiar cualquier output previo
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Función para consultar el estado de una cédula en todas las tablas
 *
 * @param PDO $pdo Objeto de conexión a la base de datos
 * @return array Respuesta para ser convertida a JSON
 */
function consultarCedulaCompleta($pdo) {
    try {
        // Sanitizar y validar entrada
        $cedula = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';
        
        // Validaciones
        if (empty($cedula)) {
            return [
                'success' => false, 
                'message' => 'El número de cédula es requerido.',
                'codigo' => 0
            ];
        }

        if (!preg_match('/^[0-9]+$/', $cedula)) {
            return [
                'success' => false, 
                'message' => 'El número de cédula solo debe contener dígitos.',
                'codigo' => 0
            ];
        }

        if (strlen($cedula) < 6 || strlen($cedula) > 15) {
            return [
                'success' => false, 
                'message' => 'El número de cédula debe tener entre 6 y 15 dígitos.',
                'codigo' => 0
            ];
        }

        // 1. Verificar si existe en ventas
        $stmt = $pdo->prepare("SELECT * FROM ventas WHERE cedula = ? LIMIT 1");
        $stmt->execute([$cedula]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Verificar si existe en agendamiento
        $stmt = $pdo->prepare("SELECT * FROM agendamiento WHERE cedula_cliente = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$cedula]);
        $agendamiento = $stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Verificar si existe en aprovisionamiento
        $stmt = $pdo->prepare("SELECT * FROM aprovisionamiento WHERE cedula_cliente = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$cedula]);
        $aprovisionamiento = $stmt->fetch(PDO::FETCH_ASSOC);

        // LÓGICA DE MENSAJES SEGÚN PRIORIDAD
        
        // Mensaje 7: Aprovisionamiento CUMPLIDO
        if ($aprovisionamiento && $aprovisionamiento['estado_aprovisionamiento'] === 'CUMPLIDO') {
            $nombre = $venta ? $venta['nombre'] : 'No disponible';
            
            return [
                'success' => true,
                'message' => 'La cédula ya existe en módulo de Aprovisionamiento en estado CUMPLIDO. La instalación fue realizada. Preguntar si el usuario requiere un nuevo servicio e ingresar nueva venta.',
                'codigo' => 7,
                'cedula' => $cedula,
                'nombre' => $nombre,
                'datos' => $aprovisionamiento
            ];
        }

        // Mensaje 6: Agendamiento o Aprovisionamiento CANCELADO
        if (($agendamiento && $agendamiento['estado_visita'] === 'CANCELADO') || 
            ($aprovisionamiento && $aprovisionamiento['estado_aprovisionamiento'] === 'CANCELADO')) {
            $nombre = $venta ? $venta['nombre'] : 'No disponible';
            
            return [
                'success' => true,
                'message' => 'La cédula ya existe en módulo de agendamiento o en Aprovisionamiento en estado CANCELADO, se debe ingresar nuevamente la venta.',
                'codigo' => 6,
                'cedula' => $cedula,
                'nombre' => $nombre,
                'datos' => $agendamiento ?: $aprovisionamiento
            ];
        }

        // Mensaje 5: Agendamiento o Aprovisionamiento PENDIENTE
        if (($agendamiento && $agendamiento['estado_visita'] === 'PENDIENTE') || 
            ($aprovisionamiento && $aprovisionamiento['estado_aprovisionamiento'] === 'PENDIENTE')) {
            $nombre = $venta ? $venta['nombre'] : 'No disponible';
            
            return [
                'success' => true,
                'message' => 'La cédula ya existe en módulo de agendamiento o en Aprovisionamiento en estado PENDIENTE, se debe agendar la visita nuevamente.',
                'codigo' => 5,
                'cedula' => $cedula,
                'nombre' => $nombre,
                'datos' => $agendamiento ?: $aprovisionamiento
            ];
        }

        // Mensaje 4: Agendamiento o Aprovisionamiento REPROGRAMAR
        if (($agendamiento && $agendamiento['estado_visita'] === 'REPROGRAMAR') || 
            ($aprovisionamiento && $aprovisionamiento['estado_aprovisionamiento'] === 'REPROGRAMAR')) {
            $nombre = $venta ? $venta['nombre'] : 'No disponible';
            
            return [
                'success' => true,
                'message' => 'La cédula ya existe en módulo de agendamiento o en Aprovisionamiento en estado REPROGRAMAR, se debe agendar la visita nuevamente.',
                'codigo' => 4,
                'cedula' => $cedula,
                'nombre' => $nombre,
                'datos' => $agendamiento ?: $aprovisionamiento
            ];
        }

        // Mensaje 3: Agendamiento AGENDADO
        if ($agendamiento && $agendamiento['estado_visita'] === 'AGENDADO') {
            $nombre = $venta ? $venta['nombre'] : 'No disponible';
            $fecha_visita = isset($agendamiento['fecha_visita']) ? date('d/m/Y', strtotime($agendamiento['fecha_visita'])) : 'No definida';
            
            return [
                'success' => true,
                'message' => 'La cédula ya existe en módulo de agendamiento en estado AGENDADO y la fecha de la agenda es: ' . $fecha_visita,
                'codigo' => 3,
                'cedula' => $cedula,
                'nombre' => $nombre,
                'datos' => $agendamiento
            ];
        }

        // Mensaje 2: Solo existe en ventas (NO ASIGNADO)
        if ($venta && !$agendamiento && !$aprovisionamiento) {
            return [
                'success' => true,
                'message' => 'La cédula existe solo en el módulo de ventas, ya cuenta con solicitud en estado NO ASIGNADO se debe agendar.',
                'codigo' => 2,
                'cedula' => $cedula,
                'nombre' => $venta['nombre'],
                'datos' => $venta
            ];
        }

        // Mensaje 1: No existe en ninguna tabla
        return [
            'success' => true,
            'message' => 'La cédula no existe, puedes seguir con el registro de venta.',
            'codigo' => 1,
            'cedula' => $cedula,
            'nombre' => null,
            'datos' => null
        ];

    } catch(PDOException $e) {
        error_log("Error en consultarCedulaCompleta: " . $e->getMessage());
        return [
            'success' => false, 
            'message' => 'Error en la consulta a la base de datos. Intente nuevamente.',
            'codigo' => 0,
            'debug' => $e->getMessage()
        ];
    } catch(Exception $e) {
        error_log("Error general en consultarCedulaCompleta: " . $e->getMessage());
        return [
            'success' => false, 
            'message' => 'Error interno del servidor. Intente nuevamente.',
            'codigo' => 0,
            'debug' => $e->getMessage()
        ];
    }
}

/**
 * Función CRUD para crear nueva venta
 */
function crearVenta($pdo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Datos JSON inválidos.'];
        }
        
        // Validaciones requeridas
        $required_fields = ['fecha', 'cedula', 'nombre', 'telefono1', 'email', 'municipio', 'vereda', 'coordenadas', 'indicaciones', 'num_servicio', 'tecnologia', 'plan', 'vendedor_usuario', 'vendedor_nombre', 'vendedor_cedula'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "El campo {$field} es requerido."];
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO ventas (fecha, cedula, nombre, telefono1, telefono2, email, municipio, vereda, coordenadas, indicaciones, notas, num_servicio, tecnologia, plan, vendedor_usuario, vendedor_nombre, vendedor_cedula) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $data['fecha'],
            $data['cedula'],
            $data['nombre'],
            $data['telefono1'],
            $data['telefono2'] ?? null,
            $data['email'],
            $data['municipio'],
            $data['vereda'],
            $data['coordenadas'],
            $data['indicaciones'],
            $data['notas'] ?? null,
            $data['num_servicio'],
            $data['tecnologia'],
            $data['plan'],
            $data['vendedor_usuario'],
            $data['vendedor_nombre'],
            $data['vendedor_cedula']
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Venta registrada correctamente.', 'id' => $pdo->lastInsertId()];
        } else {
            return ['success' => false, 'message' => 'Error al registrar la venta.'];
        }
    } catch(PDOException $e) {
        error_log("Error en crearVenta: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al registrar la venta en la base de datos.', 'debug' => $e->getMessage()];
    } catch(Exception $e) {
        error_log("Error general en crearVenta: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error interno del servidor.', 'debug' => $e->getMessage()];
    }
}

/**
 * Función CRUD para crear agendamiento
 */
function crearAgendamiento($pdo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Datos JSON inválidos.'];
        }
        
        // Validaciones requeridas
        $required_fields = ['cedula_cliente', 'fecha_visita', 'franja_visita', 'tecnico_asignado'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "El campo {$field} es requerido."];
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO agendamiento (cedula_cliente, fecha_visita, franja_visita, tecnico_asignado, notas, estado_visita) VALUES (?, ?, ?, ?, ?, 'AGENDADO')");
        
        $result = $stmt->execute([
            $data['cedula_cliente'],
            $data['fecha_visita'],
            $data['franja_visita'],
            $data['tecnico_asignado'],
            $data['notas'] ?? null
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Agendamiento registrado correctamente.', 'id' => $pdo->lastInsertId()];
        } else {
            return ['success' => false, 'message' => 'Error al registrar el agendamiento.'];
        }
    } catch(PDOException $e) {
        error_log("Error en crearAgendamiento: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al registrar el agendamiento en la base de datos.', 'debug' => $e->getMessage()];
    } catch(Exception $e) {
        error_log("Error general en crearAgendamiento: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error interno del servidor.', 'debug' => $e->getMessage()];
    }
}

/**
 * Función CRUD para actualizar estado de agendamiento
 */
function actualizarAgendamiento($pdo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Datos JSON inválidos.'];
        }
        
        // Validaciones requeridas
        $required_fields = ['cedula_cliente', 'fecha_visita', 'franja_visita', 'tecnico_asignado', 'estado_visita'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "El campo {$field} es requerido."];
            }
        }
        
        $stmt = $pdo->prepare("UPDATE agendamiento SET fecha_visita = ?, franja_visita = ?, tecnico_asignado = ?, notas = ?, estado_visita = ?, updated_at = CURRENT_TIMESTAMP WHERE cedula_cliente = ?");
        
        $result = $stmt->execute([
            $data['fecha_visita'],
            $data['franja_visita'],
            $data['tecnico_asignado'],
            $data['notas'] ?? null,
            $data['estado_visita'],
            $data['cedula_cliente']
        ]);
        
        if ($result && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Agendamiento actualizado correctamente.'];
        } else {
            return ['success' => false, 'message' => 'No se encontró el agendamiento para actualizar.'];
        }
    } catch(PDOException $e) {
        error_log("Error en actualizarAgendamiento: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar el agendamiento en la base de datos.', 'debug' => $e->getMessage()];
    } catch(Exception $e) {
        error_log("Error general en actualizarAgendamiento: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error interno del servidor.', 'debug' => $e->getMessage()];
    }
}

/**
 * Función CRUD para crear aprovisionamiento
 */
function crearAprovisionamiento($pdo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Datos JSON inválidos.'];
        }
        
        // Validaciones requeridas
        $required_fields = ['cedula_cliente', 'tipo_router_onu', 'mac_serial_router', 'ip_navegacion', 'metros_cable', 'notas_aprovisionamiento', 'estado_aprovisionamiento'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "El campo {$field} es requerido."];
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO aprovisionamiento (cedula_cliente, tipo_radio, mac_serial_radio, tipo_router_onu, mac_serial_router, ip_navegacion, ip_gestion, metros_cable, tipo_cable, notas_aprovisionamiento, estado_aprovisionamiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $data['cedula_cliente'],
            $data['tipo_radio'] ?? null,
            $data['mac_serial_radio'] ?? null,
            $data['tipo_router_onu'],
            $data['mac_serial_router'],
            $data['ip_navegacion'],
            $data['ip_gestion'] ?? null,
            $data['metros_cable'],
            $data['tipo_cable'] ?? null,
            $data['notas_aprovisionamiento'],
            $data['estado_aprovisionamiento']
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Aprovisionamiento registrado correctamente.', 'id' => $pdo->lastInsertId()];
        } else {
            return ['success' => false, 'message' => 'Error al registrar el aprovisionamiento.'];
        }
    } catch(PDOException $e) {
        error_log("Error en crearAprovisionamiento: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al registrar el aprovisionamiento en la base de datos.', 'debug' => $e->getMessage()];
    } catch(Exception $e) {
        error_log("Error general en crearAprovisionamiento: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error interno del servidor.', 'debug' => $e->getMessage()];
    }
}

// --- CONTROLADOR PRINCIPAL (ROUTER) ---
try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if (empty($action)) {
        sendJsonResponse(['success' => false, 'message' => 'Acción no especificada.'], 400);
    }

    // Establecer conexión a la base de datos
    try {
        $pdo = getDBConnection();
    } catch(Exception $e) {
        sendJsonResponse(['success' => false, 'message' => 'Error de conexión a la base de datos.', 'debug' => $e->getMessage()], 500);
    }

    $response = [];

    switch ($action) {
        case 'consultar_cedula':
            $response = consultarCedulaCompleta($pdo);
            break;
            
        case 'crear_venta':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJsonResponse(['success' => false, 'message' => 'Método no permitido. Use POST.'], 405);
            } else {
                $response = crearVenta($pdo);
            }
            break;
            
        case 'crear_agendamiento':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJsonResponse(['success' => false, 'message' => 'Método no permitido. Use POST.'], 405);
            } else {
                $response = crearAgendamiento($pdo);
            }
            break;
            
        case 'actualizar_agendamiento':
            if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJsonResponse(['success' => false, 'message' => 'Método no permitido. Use PUT o POST.'], 405);
            } else {
                $response = actualizarAgendamiento($pdo);
            }
            break;
            
        case 'crear_aprovisionamiento':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJsonResponse(['success' => false, 'message' => 'Método no permitido. Use POST.'], 405);
            } else {
                $response = crearAprovisionamiento($pdo);
            }
            break;

        default:
            sendJsonResponse(['success' => false, 'message' => 'Acción no válida.'], 404);
            break;
    }

    sendJsonResponse($response);

} catch(Exception $e) {
    error_log("Error general en el controlador: " . $e->getMessage());
    sendJsonResponse([
        'success' => false, 
        'message' => 'Error interno del servidor.',
        'debug' => $e->getMessage()
    ], 500);
}
?>