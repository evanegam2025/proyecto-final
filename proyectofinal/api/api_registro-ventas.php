<?php
/**
 * API para Sistema de Registro de Ventas - CRUD Completo
 * Archivo: api/api_registro-ventas.php
 * Codificación: UTF-8
 * Descripción: API REST para operaciones CRUD de ventas
 */

// ======================================================================
// CONFIGURACIÓN INICIAL Y SEGURIDAD
// ======================================================================

// Configurar codificación UTF-8
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Configurar zona horaria
date_default_timezone_set('America/Bogota');

// Prevenir output no deseado
ob_start();

// Incluir configuración de base de datos
$possible_paths = [
    '../conex_bd.php',
    './conex_bd.php',
    'conex_bd.php',
    dirname(__FILE__) . '/../conex_bd.php'
];

$connection_found = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $connection_found = true;
        break;
    }
}

if (!$connection_found) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Archivo de configuración de base de datos no encontrado'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Iniciar sesión si no está activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado. Debe iniciar sesión.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ======================================================================
// FUNCIONES AUXILIARES
// ======================================================================

/**
 * Función para limpiar y sanitizar entrada UTF-8
 * @param mixed $input Entrada a limpiar
 * @return mixed Entrada limpia y válida
 */
function limpiarEntradaUTF8($input) {
    if (is_array($input)) {
        return array_map('limpiarEntradaUTF8', $input);
    }
    
    if (!is_string($input)) {
        return $input;
    }
    
    // Asegurar que sea UTF-8 válido
    if (!mb_check_encoding($input, 'UTF-8')) {
        $input = mb_convert_encoding($input, 'UTF-8', 'UTF-8//IGNORE');
    }
    
    // Eliminar caracteres de control excepto saltos de línea y tabulaciones
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
    
    // Normalizar espacios y limpiar
    $input = preg_replace('/\s+/', ' ', trim($input));
    
    return $input;
}

/**
 * Función para validar datos de entrada
 * @param array $datos Datos a validar
 * @return array Resultado de validación
 */
function validarDatosVenta($datos) {
    $errores = [];
    
    // Campos requeridos
    $camposRequeridos = [
        'cedula' => 'Cédula',
        'nombre' => 'Nombre',
        'telefono1' => 'Teléfono principal',
        'email' => 'Email',
        'tecnologia' => 'Tecnología',
        'plan' => 'Plan',
        'num_servicio' => 'Número de servicio',
        'municipio' => 'Municipio',
        'vereda' => 'Vereda',
        'coordenadas' => 'Coordenadas',
        'indicaciones' => 'Indicaciones',
        'fecha' => 'Fecha'
    ];
    
    foreach ($camposRequeridos as $campo => $nombre) {
        if (empty($datos[$campo])) {
            $errores[] = "El campo {$nombre} es requerido";
        }
    }
    
    // Validar cédula
    if (!empty($datos['cedula'])) {
        if (!preg_match('/^\d{6,12}$/', $datos['cedula'])) {
            $errores[] = 'La cédula debe tener entre 6 y 12 dígitos';
        }
    }
    
    // Validar email
    if (!empty($datos['email'])) {
        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El formato del email no es válido';
        }
    }
    
    // Validar teléfonos
    if (!empty($datos['telefono1'])) {
        $telefono = preg_replace('/\s/', '', $datos['telefono1']);
        if (!preg_match('/^3\d{9}$/', $telefono)) {
            $errores[] = 'El teléfono principal debe ser un número colombiano válido (10 dígitos, iniciando con 3)';
        }
    }
    
    if (!empty($datos['telefono2'])) {
        $telefono = preg_replace('/\s/', '', $datos['telefono2']);
        if (!preg_match('/^3\d{9}$/', $telefono)) {
            $errores[] = 'El teléfono secundario debe ser un número colombiano válido';
        }
    }
    
    // Validar coordenadas
    if (!empty($datos['coordenadas'])) {
        if (!preg_match('/^-?\d+\.?\d*,\s*-?\d+\.?\d*$/', $datos['coordenadas'])) {
            $errores[] = 'Las coordenadas deben tener el formato: latitud, longitud';
        }
    }
    
    // Validar fecha
    if (!empty($datos['fecha'])) {
        $fechaValida = DateTime::createFromFormat('Y-m-d\TH:i', $datos['fecha']);
        if (!$fechaValida) {
            $errores[] = 'El formato de fecha no es válido';
        }
    }
    
    return [
        'valido' => empty($errores),
        'errores' => $errores
    ];
}

/**
 * Función para responder con JSON
 * @param array $data Datos de respuesta
 * @param int $httpCode Código HTTP
 */
function responderJSON($data, $httpCode = 200) {
    // Limpiar cualquier output previo
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Asegurar codificación UTF-8
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code($httpCode);
    
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = json_encode([
            'success' => false,
            'error' => 'Error al codificar respuesta JSON: ' . json_last_error_msg()
        ], JSON_UNESCAPED_UNICODE);
    }
    
    echo $json;
    exit;
}

/**
 * Obtener datos del usuario actual
 * @param PDO $pdo Conexión a la base de datos
 * @return array Datos del usuario
 */
function obtenerDatosUsuario($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, usuario, nombre, cedula FROM administrador WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $usuario ? $usuario : null;
    } catch (PDOException $e) {
        error_log("Error al obtener datos del usuario: " . $e->getMessage());
        return null;
    }
}

// ======================================================================
// OPERACIONES CRUD
// ======================================================================

/**
 * Crear nueva venta
 */
function crearVenta() {
    try {
        // Obtener datos JSON
        $input = file_get_contents('php://input');
        $datos = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            responderJSON([
                'success' => false,
                'error' => 'Datos JSON no válidos'
            ], 400);
        }
        
        // Limpiar datos
        $datos = limpiarEntradaUTF8($datos);
        
        // Validar datos
        $validacion = validarDatosVenta($datos);
        if (!$validacion['valido']) {
            responderJSON([
                'success' => false,
                'error' => 'Errores de validación',
                'details' => $validacion['errores']
            ], 400);
        }
        
        // Obtener conexión
        $pdo = getDBConnection();
        $usuario = obtenerDatosUsuario($pdo);
        
        if (!$usuario) {
            responderJSON([
                'success' => false,
                'error' => 'Error al obtener datos del usuario'
            ], 500);
        }
        
        // Verificar si ya existe la cédula
        $stmt = $pdo->prepare("SELECT id FROM ventas WHERE cedula = ?");
        $stmt->execute([$datos['cedula']]);
        if ($stmt->fetch()) {
            responderJSON([
                'success' => false,
                'error' => 'Ya existe una venta registrada con esta cédula'
            ], 409);
        }
        
        // Preparar datos para inserción
        $fechaVenta = DateTime::createFromFormat('Y-m-d\TH:i', $datos['fecha']);
        if (!$fechaVenta) {
            responderJSON([
                'success' => false,
                'error' => 'Formato de fecha inválido'
            ], 400);
        }
        
        // Insertar venta
        $sql = "INSERT INTO ventas (
            fecha, cedula, nombre, telefono1, telefono2, email, municipio, 
            vereda, coordenadas, indicaciones, notas, num_servicio, 
            tecnologia, plan, vendedor_usuario, vendedor_nombre, 
            vendedor_cedula, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $resultado = $stmt->execute([
            $fechaVenta->format('Y-m-d H:i:s'),
            $datos['cedula'],
            $datos['nombre'],
            $datos['telefono1'],
            $datos['telefono2'] ?? '',
            $datos['email'],
            $datos['municipio'],
            $datos['vereda'],
            $datos['coordenadas'],
            $datos['indicaciones'],
            $datos['notas'] ?? '',
            $datos['num_servicio'],
            $datos['tecnologia'],
            $datos['plan'],
            $usuario['usuario'],
            $usuario['nombre'],
            $usuario['cedula']
        ]);
        
        if ($resultado) {
            responderJSON([
                'success' => true,
                'message' => 'Venta creada exitosamente',
                'id' => $pdo->lastInsertId()
            ]);
        } else {
            responderJSON([
                'success' => false,
                'error' => 'Error al insertar la venta'
            ], 500);
        }
        
    } catch (PDOException $e) {
        error_log("Error PDO al crear venta: " . $e->getMessage());
        responderJSON([
            'success' => false,
            'error' => 'Error de base de datos al crear la venta'
        ], 500);
    } catch (Exception $e) {
        error_log("Error general al crear venta: " . $e->getMessage());
        responderJSON([
            'success' => false,
            'error' => 'Error interno del servidor'
        ], 500);
    }
}

/**
 * Obtener ventas
 */
function obtenerVentas() {
    try {
        $pdo = getDBConnection();
        $usuario = obtenerDatosUsuario($pdo);
        
        if (!$usuario) {
            responderJSON([
                'success' => false,
                'error' => 'Error al obtener datos del usuario'
            ], 500);
        }
        
        // Si se solicita una venta específica
        if (isset($_GET['id'])) {
            $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            if (!$id) {
                responderJSON([
                    'success' => false,
                    'error' => 'ID de venta no válido'
                ], 400);
            }
            
            $stmt = $pdo->prepare("
                SELECT * FROM ventas 
                WHERE id = ? AND vendedor_cedula = ?
            ");
            $stmt->execute([$id, $usuario['cedula']]);
            $venta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($venta) {
                responderJSON([
                    'success' => true,
                    'data' => $venta
                ]);
            } else {
                responderJSON([
                    'success' => false,
                    'error' => 'Venta no encontrada'
                ], 404);
            }
        } else {
            // Obtener todas las ventas del usuario
            $stmt = $pdo->prepare("
                SELECT id, fecha, cedula, nombre, telefono1, telefono2, 
                       email, plan, tecnologia, municipio, vereda
                FROM ventas 
                WHERE vendedor_cedula = ? 
                ORDER BY fecha DESC 
                LIMIT 50
            ");
            $stmt->execute([$usuario['cedula']]);
            $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            responderJSON([
                'success' => true,
                'data' => $ventas
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Error PDO al obtener ventas: " . $e->getMessage());
        responderJSON([
            'success' => false,
            'error' => 'Error de base de datos al obtener las ventas'
        ], 500);
    } catch (Exception $e) {
        error_log("Error general al obtener ventas: " . $e->getMessage());
        responderJSON([
            'success' => false,
            'error' => 'Error interno del servidor'
        ], 500);
    }
}

/**
 * Actualizar venta
 */
function actualizarVenta() {
    try {
        // Obtener datos JSON
        $input = file_get_contents('php://input');
        $datos = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            responderJSON([
                'success' => false,
                'error' => 'Datos JSON no válidos'
            ], 400);
        }
        
        // Validar ID
        if (empty($datos['id'])) {
            responderJSON([
                'success' => false,
                'error' => 'ID de venta requerido'
            ], 400);
        }
        
        $id = filter_var($datos['id'], FILTER_VALIDATE_INT);
        if (!$id) {
            responderJSON([
                'success' => false,
                'error' => 'ID de venta no válido'
            ], 400);
        }
        
        // Limpiar datos
        $datos = limpiarEntradaUTF8($datos);
        
        // Validar datos
        $validacion = validarDatosVenta($datos);
        if (!$validacion['valido']) {
            responderJSON([
                'success' => false,
                'error' => 'Errores de validación',
                'details' => $validacion['errores']
            ], 400);
        }
        
        // Obtener conexión y usuario
        $pdo = getDBConnection();
        $usuario = obtenerDatosUsuario($pdo);
        
        if (!$usuario) {
            responderJSON([
                'success' => false,
                'error' => 'Error al obtener datos del usuario'
            ], 500);
        }
        
        // Verificar que la venta pertenece al usuario
        $stmt = $pdo->prepare("
            SELECT id FROM ventas 
            WHERE id = ? AND vendedor_cedula = ?
        ");
        $stmt->execute([$id, $usuario['cedula']]);
        if (!$stmt->fetch()) {
            responderJSON([
                'success' => false,
                'error' => 'Venta no encontrada o no autorizada'
            ], 404);
        }
        
        // Verificar si la nueva cédula ya existe (excluyendo el registro actual)
        $stmt = $pdo->prepare("
            SELECT id FROM ventas 
            WHERE cedula = ? AND id != ?
        ");
        $stmt->execute([$datos['cedula'], $id]);
        if ($stmt->fetch()) {
            responderJSON([
                'success' => false,
                'error' => 'Ya existe otra venta registrada con esta cédula'
            ], 409);
        }
        
        // Preparar fecha
        $fechaVenta = DateTime::createFromFormat('Y-m-d\TH:i', $datos['fecha']);
        if (!$fechaVenta) {
            responderJSON([
                'success' => false,
                'error' => 'Formato de fecha inválido'
            ], 400);
        }
        
        // Actualizar venta
        $sql = "UPDATE ventas SET 
            fecha = ?, cedula = ?, nombre = ?, telefono1 = ?, telefono2 = ?, 
            email = ?, municipio = ?, vereda = ?, coordenadas = ?, 
            indicaciones = ?, notas = ?, num_servicio = ?, tecnologia = ?, plan = ?
            WHERE id = ? AND vendedor_cedula = ?";
        
        $stmt = $pdo->prepare($sql);
        $resultado = $stmt->execute([
            $fechaVenta->format('Y-m-d H:i:s'),
            $datos['cedula'],
            $datos['nombre'],
            $datos['telefono1'],
            $datos['telefono2'] ?? '',
            $datos['email'],
            $datos['municipio'],
            $datos['vereda'],
            $datos['coordenadas'],
            $datos['indicaciones'],
            $datos['notas'] ?? '',
            $datos['num_servicio'],
            $datos['tecnologia'],
            $datos['plan'],
            $id,
            $usuario['cedula']
        ]);
        
        if ($resultado && $stmt->rowCount() > 0) {
            responderJSON([
                'success' => true,
                'message' => 'Venta actualizada exitosamente'
            ]);
        } else {
            responderJSON([
                'success' => false,
                'error' => 'No se pudo actualizar la venta'
            ], 500);
        }
        
    } catch (PDOException $e) {
        error_log("Error PDO al actualizar venta: " . $e->getMessage());
        responderJSON([
            'success' => false,
            'error' => 'Error de base de datos al actualizar la venta'
        ], 500);
    } catch (Exception $e) {
        error_log("Error general al actualizar venta: " . $e->getMessage());
        responderJSON([
            'success' => false,
            'error' => 'Error interno del servidor'
        ], 500);
    }
}

/**
 * Eliminar venta
 */
function eliminarVenta() {
    try {
        // Obtener datos JSON
        $input = file_get_contents('php://input');
        $datos = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            responderJSON([
                'success' => false,
                'error' => 'Datos JSON no válidos'
            ], 400);
        }
        
        // Validar ID
        if (empty($datos['id'])) {
            responderJSON([
                'success' => false,
                'error' => 'ID de venta requerido'
            ], 400);
        }
        
        $id = filter_var($datos['id'], FILTER_VALIDATE_INT);
        if (!$id) {
            responderJSON([
                'success' => false,
                'error' => 'ID de venta no válido'
            ], 400);
        }
        
        // Obtener conexión y usuario
        $pdo = getDBConnection();
        $usuario = obtenerDatosUsuario($pdo);
        
        if (!$usuario) {
            responderJSON([
                'success' => false,
                'error' => 'Error al obtener datos del usuario'
            ], 500);
        }
        
        // Verificar que la venta pertenece al usuario
        $stmt = $pdo->prepare("
            SELECT id FROM ventas 
            WHERE id = ? AND vendedor_cedula = ?
        ");
        $stmt->execute([$id, $usuario['cedula']]);
        if (!$stmt->fetch()) {
            responderJSON([
                'success' => false,
                'error' => 'Venta no encontrada o no autorizada'
            ], 404);
        }
        
        // Eliminar venta
        $stmt = $pdo->prepare("
            DELETE FROM ventas 
            WHERE id = ? AND vendedor_cedula = ?
        ");
        $resultado = $stmt->execute([$id, $usuario['cedula']]);
        
        if ($resultado && $stmt->rowCount() > 0) {
            responderJSON([
                'success' => true,
                'message' => 'Venta eliminada exitosamente'
            ]);
        } else {
            responderJSON([
                'success' => false,
                'error' => 'No se pudo eliminar la venta'
            ], 500);
        }
        
    } catch (PDOException $e) {
        error_log("Error PDO al eliminar venta: " . $e->getMessage());
        responderJSON([
            'success' => false,
            'error' => 'Error de base de datos al eliminar la venta'
        ], 500);
    } catch (Exception $e) {
        error_log("Error general al eliminar venta: " . $e->getMessage());
        responderJSON([
            'success' => false,
            'error' => 'Error interno del servidor'
        ], 500);
    }
}

/**
 * Obtener estadísticas del vendedor
 */
function obtenerEstadisticas() {
    try {
        $pdo = getDBConnection();
        $usuario = obtenerDatosUsuario($pdo);
        
        if (!$usuario) {
            responderJSON([
                'success' => false,
                'error' => 'Error al obtener datos del usuario'
            ], 500);
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN DATE(fecha) = CURDATE() THEN 1 END) as ventas_hoy,
                COUNT(CASE WHEN YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) as ventas_semana,
                COUNT(CASE WHEN MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) THEN 1 END) as ventas_mes,
                COUNT(*) as total_ventas
            FROM ventas 
            WHERE vendedor_cedula = ?
        ");
        $stmt->execute([$usuario['cedula']]);
        $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        responderJSON([
            'success' => true,
            'data' => $estadisticas
        ]);
        
    } catch (PDOException $e) {
        error_log("Error PDO al obtener estadísticas: " . $e->getMessage());
        responderJSON([
            'success' => false,
            'error' => 'Error de base de datos al obtener estadísticas'
        ], 500);
    } catch (Exception $e) {
        error_log("Error general al obtener estadísticas: " . $e->getMessage());
        responderJSON([
            'success' => false,
            'error' => 'Error interno del servidor'
        ], 500);
    }
}

// ======================================================================
// ROUTER PRINCIPAL
// ======================================================================

// Obtener método HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

// Obtener la acción desde la URL
$accion = '';
if (isset($_GET['action'])) {
    $accion = $_GET['action'];
} else {
    // Determinar acción basada en el endpoint llamado
    $script_name = basename($_SERVER['SCRIPT_NAME'], '.php');
    $uri_parts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    
    // Buscar el nombre del archivo en la URI
    foreach ($uri_parts as $part) {
        if (strpos($part, 'crear_venta') !== false) {
            $accion = 'crear';
            break;
        } elseif (strpos($part, 'obtener_ventas') !== false) {
            $accion = 'obtener';
            break;
        } elseif (strpos($part, 'actualizar_venta') !== false) {
            $accion = 'actualizar';
            break;
        } elseif (strpos($part, 'eliminar_venta') !== false) {
            $accion = 'eliminar';
            break;
        } elseif (strpos($part, 'obtener_estadisticas') !== false) {
            $accion = 'estadisticas';
            break;
        }
    }
}

// Enrutar según método y acción
switch ($metodo) {
    case 'POST':
        switch ($accion) {
            case 'crear':
                crearVenta();
                break;
            case 'actualizar':
                actualizarVenta();
                break;
            case 'eliminar':
                eliminarVenta();
                break;
            default:
                // Si no hay acción específica, determinar por el cuerpo de la petición
                $input = file_get_contents('php://input');
                $datos = json_decode($input, true);
                
                if (isset($datos['id']) && !empty($datos['nombre'])) {
                    // Tiene ID y datos completos = actualizar
                    actualizarVenta();
                } elseif (isset($datos['id']) && count($datos) === 1) {
                    // Solo tiene ID = eliminar
                    eliminarVenta();
                } elseif (!isset($datos['id'])) {
                    // No tiene ID = crear
                    crearVenta();
                } else {
                    responderJSON([
                        'success' => false,
                        'error' => 'Acción no válida'
                    ], 400);
                }
        }
        break;
        
    case 'GET':
        switch ($accion) {
            case 'obtener':
                obtenerVentas();
                break;
            case 'estadisticas':
                obtenerEstadisticas();
                break;
            default:
                obtenerVentas();
        }
        break;
        
    default:
        responderJSON([
            'success' => false,
            'error' => 'Método HTTP no permitido'
        ], 405);
}

// Si llegamos aquí, algo salió mal
responderJSON([
    'success' => false,
    'error' => 'Endpoint no encontrado'
], 404);
?>