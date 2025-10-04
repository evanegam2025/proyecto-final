<?php
/**
 * API para gestión de permisos
 * Archivo: api/api_permisos.php
 * Codificación: UTF-8
 */

// Configuración de codificación
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Incluir archivo de conexión
require_once __DIR__ . '/../conex_bd.php';

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Función para registrar errores
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, __DIR__ . '/../logs/api_errors.log');
}

/**
 * Función para validar nombre de permiso
 * @param string $nombre Nombre del permiso
 * @return array Array con resultado de validación
 */
function validarNombrePermiso($nombre) {
    $errores = [];
    
    // Verificar que no esté vacío
    if (empty($nombre)) {
        $errores[] = 'El nombre del permiso es obligatorio';
    }
    
    // Verificar longitud
    if (strlen($nombre) < 3) {
        $errores[] = 'El nombre debe tener al menos 3 caracteres';
    }
    
    if (strlen($nombre) > 50) {
        $errores[] = 'El nombre no puede tener más de 50 caracteres';
    }
    
    // Verificar formato (solo letras minúsculas y guiones bajos)
    if (!preg_match('/^[a-z_]+$/', $nombre)) {
        $errores[] = 'El nombre solo puede contener letras minúsculas y guiones bajos';
    }
    
    return [
        'valido' => empty($errores),
        'errores' => $errores
    ];
}

/**
 * Función para obtener todos los permisos
 * @param PDO $conn Conexión a la base de datos
 * @return array Lista de permisos
 */
function obtenerTodosLosPermisos($conn) {
    try {
        $query = "SELECT id, nombre, descripcion, fecha_creacion, fecha_modificacion 
                  FROM permisos 
                  ORDER BY nombre ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        return [
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        
    } catch (PDOException $e) {
        logError("Error al obtener permisos: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error al consultar los permisos',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Función para crear un nuevo permiso
 * @param PDO $conn Conexión a la base de datos
 * @param array $datos Datos del permiso
 * @return array Resultado de la operación
 */
function crearPermiso($conn, $datos) {
    try {
        $nombre = trim($datos['nombre']);
        
        // Validar nombre
        $validacion = validarNombrePermiso($nombre);
        if (!$validacion['valido']) {
            return [
                'success' => false,
                'error' => 'Datos de entrada inválidos',
                'details' => $validacion['errores']
            ];
        }
        
        // Verificar si el permiso ya existe
        $checkQuery = "SELECT COUNT(*) FROM permisos WHERE nombre = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$nombre]);
        
        if ($checkStmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'error' => 'Ya existe un permiso con ese nombre'
            ];
        }
        
        // Insertar nuevo permiso
        $query = "INSERT INTO permisos (nombre, descripcion) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        
        $descripcion = isset($datos['descripcion']) ? trim($datos['descripcion']) : null;
        
        $stmt->execute([
            $nombre,
            $descripcion
        ]);
        
        $permisoId = $conn->lastInsertId();
        
        // Obtener el permiso creado
        $getQuery = "SELECT id, nombre, descripcion, fecha_creacion, fecha_modificacion 
                     FROM permisos WHERE id = ?";
        $getStmt = $conn->prepare($getQuery);
        $getStmt->execute([$permisoId]);
        $permisoCreado = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        logError("Permiso creado: {$nombre} (ID: {$permisoId})");
        
        return [
            'success' => true,
            'message' => 'Permiso creado exitosamente',
            'data' => $permisoCreado
        ];
        
    } catch (PDOException $e) {
        logError("Error al crear permiso: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error al crear el permiso',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Función para actualizar un permiso
 * @param PDO $conn Conexión a la base de datos
 * @param array $datos Datos del permiso
 * @return array Resultado de la operación
 */
function actualizarPermiso($conn, $datos) {
    try {
        $id = intval($datos['id']);
        $nombre = trim($datos['nombre']);
        
        // Validar ID
        if ($id <= 0) {
            return [
                'success' => false,
                'error' => 'ID de permiso inválido'
            ];
        }
        
        // Validar nombre
        $validacion = validarNombrePermiso($nombre);
        if (!$validacion['valido']) {
            return [
                'success' => false,
                'error' => 'Datos de entrada inválidos',
                'details' => $validacion['errores']
            ];
        }
        
        // Verificar si el permiso existe
        $checkQuery = "SELECT nombre FROM permisos WHERE id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$id]);
        $permisoActual = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$permisoActual) {
            return [
                'success' => false,
                'error' => 'El permiso no existe'
            ];
        }
        
        // Verificar si el nuevo nombre ya existe en otro permiso
        $duplicateQuery = "SELECT COUNT(*) FROM permisos WHERE nombre = ? AND id != ?";
        $duplicateStmt = $conn->prepare($duplicateQuery);
        $duplicateStmt->execute([$nombre, $id]);
        
        if ($duplicateStmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'error' => 'Ya existe otro permiso con ese nombre'
            ];
        }
        
        // Actualizar permiso
        $query = "UPDATE permisos SET nombre = ?, descripcion = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        $descripcion = isset($datos['descripcion']) ? trim($datos['descripcion']) : null;
        
        $stmt->execute([
            $nombre,
            $descripcion,
            $id
        ]);
        
        // Obtener el permiso actualizado
        $getQuery = "SELECT id, nombre, descripcion, fecha_creacion, fecha_modificacion 
                     FROM permisos WHERE id = ?";
        $getStmt = $conn->prepare($getQuery);
        $getStmt->execute([$id]);
        $permisoActualizado = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        logError("Permiso actualizado: {$permisoActual['nombre']} -> {$nombre} (ID: {$id})");
        
        return [
            'success' => true,
            'message' => 'Permiso actualizado exitosamente',
            'data' => $permisoActualizado
        ];
        
    } catch (PDOException $e) {
        logError("Error al actualizar permiso: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error al actualizar el permiso',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Función para eliminar un permiso
 * @param PDO $conn Conexión a la base de datos
 * @param int $id ID del permiso
 * @return array Resultado de la operación
 */
function eliminarPermiso($conn, $id) {
    try {
        $id = intval($id);
        
        // Validar ID
        if ($id <= 0) {
            return [
                'success' => false,
                'error' => 'ID de permiso inválido'
            ];
        }
        
        // Verificar si el permiso existe
        $checkQuery = "SELECT nombre FROM permisos WHERE id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$id]);
        $permiso = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$permiso) {
            return [
                'success' => false,
                'error' => 'El permiso no existe'
            ];
        }
        
        // TODO: Verificar si el permiso está siendo usado por usuarios/roles
        // antes de eliminarlo (implementar según sea necesario)
        
        // Eliminar permiso
        $query = "DELETE FROM permisos WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            logError("Permiso eliminado: {$permiso['nombre']} (ID: {$id})");
            
            return [
                'success' => true,
                'message' => 'Permiso eliminado exitosamente'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'No se pudo eliminar el permiso'
            ];
        }
        
    } catch (PDOException $e) {
        logError("Error al eliminar permiso: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error al eliminar el permiso',
            'details' => $e->getMessage()
        ];
    }
}

// Procesar la solicitud
try {
    // Obtener método HTTP
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Obtener conexión a la base de datos
    $conn = getDBConnection();
    
    // Procesar según el método HTTP
    switch ($method) {
        case 'GET':
            // Obtener todos los permisos
            $resultado = obtenerTodosLosPermisos($conn);
            break;
            
        case 'POST':
            // Crear nuevo permiso
            $input = file_get_contents('php://input');
            $datos = json_decode($input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Datos JSON inválidos'
                ], 400);
            }
            
            $resultado = crearPermiso($conn, $datos);
            break;
            
        case 'PUT':
            // Actualizar permiso
            $input = file_get_contents('php://input');
            $datos = json_decode($input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Datos JSON inválidos'
                ], 400);
            }
            
            $resultado = actualizarPermiso($conn, $datos);
            break;
            
        case 'DELETE':
            // Eliminar permiso
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            
            if (!$id) {
                jsonResponse([
                    'success' => false,
                    'error' => 'ID de permiso requerido'
                ], 400);
            }
            
            $resultado = eliminarPermiso($conn, $id);
            break;
            
        default:
            jsonResponse([
                'success' => false,
                'error' => 'Método HTTP no permitido'
            ], 405);
            break;
    }
    
    // Enviar respuesta
    $statusCode = $resultado['success'] ? 200 : 400;
    jsonResponse($resultado, $statusCode);
    
} catch (Exception $e) {
    logError("Error general en API permisos: " . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'error' => 'Error interno del servidor',
        'details' => $e->getMessage()
    ], 500);
}
?>