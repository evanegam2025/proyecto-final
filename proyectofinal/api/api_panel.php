<?php
/**
 * API Gestión de Módulos y Permisos
 * Maneja operaciones CRUD para la relación módulos-permisos según la estructura de BD
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Incluir archivo de conexión
require_once '../conex_bd.php';


/**
 * Función para enviar respuesta JSON
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Función para validar entrada JSON
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Error en formato JSON: ' . json_last_error_msg()
        ], 400);
    }
    
    return $data;
}

/**
 * Validar que el módulo existe en ENUM
 */
function validarModulo($modulo) {
    $modulosValidos = ['Administrador', 'Vendedor', 'Agendamiento', 'Aprovisionamiento'];
    return in_array($modulo, $modulosValidos);
}

/**
 * Función para registrar logs de auditoría (opcional)
 */
function registrarLog($conn, $accion, $tabla, $registro_id, $detalles = '') {
    try {
        // Verificar si existe tabla de auditoría
        $stmt = $conn->prepare("SHOW TABLES LIKE 'auditoria'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $sql = "INSERT INTO auditoria (tabla, registro_id, accion, valores_nuevos, fecha_accion) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$tabla, $registro_id, $accion, $detalles]);
        }
    } catch (Exception $e) {
        // Log error pero no interrumpir el flujo principal
        error_log("Error en auditoría: " . $e->getMessage());
    }
}

/**
 * Función auxiliar para validar permisos de usuario (opcional)
 */
function validarPermisoUsuario($conn, $cedula, $permiso_requerido) {
    try {
        $stmt = $conn->prepare("SELECT UsuarioTienePermiso(?, ?) as tiene_permiso");
        $stmt->execute([$cedula, $permiso_requerido]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return (bool)$resultado['tiene_permiso'];
    } catch (Exception $e) {
        error_log("Error validando permiso: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para obtener información completa de un administrador
 */
function obtenerInfoAdministrador($conn, $cedula) {
    try {
        $stmt = $conn->prepare("
            SELECT a.*, m.nombre as modulo_nombre 
            FROM administrador a
            LEFT JOIN modulos m ON a.modulo = m.nombre
            WHERE a.cedula = ?
        ");
        $stmt->execute([$cedula]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo info administrador: " . $e->getMessage());
        return null;
    }
}

/**
 * Función para formatear respuestas de error consistentemente
 */
function formatearErrorBD($e) {
    $mensaje_usuario = "Error al procesar la solicitud";
    
    // Personalizar mensajes según el tipo de error
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $mensaje_usuario = "El registro ya existe en el sistema";
    } elseif (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $mensaje_usuario = "No se puede completar la operación debido a dependencias en el sistema";
    } elseif (strpos($e->getMessage(), 'Data too long') !== false) {
        $mensaje_usuario = "Los datos proporcionados son demasiado largos";
    } elseif (strpos($e->getMessage(), 'cannot be null') !== false) {
        $mensaje_usuario = "Faltan campos obligatorios";
    }
    
    return $mensaje_usuario;
}

try {
    // Establecer conexión
    $conn = getDBConnection();
    
    // Verificar método de request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse([
            'success' => false,
            'message' => 'Método no permitido. Use POST.'
        ], 405);
    }
    
    // Obtener datos JSON
    $requestData = getJsonInput();
    
    if (!isset($requestData['action'])) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Acción no especificada'
        ], 400);
    }
    
    $action = $requestData['action'];
    
    switch ($action) {
        
        case 'assign_permission_to_module':
            // Validar parámetros requeridos
            if (!isset($requestData['modulo']) || !isset($requestData['permiso_id'])) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Parámetros insuficientes: modulo y permiso_id requeridos'
                ], 400);
            }
            
            $modulo = $requestData['modulo'];
            $permiso_id = (int)$requestData['permiso_id'];
            
            // Validar módulo
            if (!validarModulo($modulo)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Módulo no válido'
                ], 400);
            }
            
            // Verificar que el permiso existe
            $stmt = $conn->prepare("SELECT COUNT(*) FROM permisos WHERE id = ?");
            $stmt->execute([$permiso_id]);
            if ($stmt->fetchColumn() == 0) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'El permiso especificado no existe'
                ], 404);
            }
            
            try {
                $conn->beginTransaction();
                
                // Verificar si ya está asignado
                $stmt = $conn->prepare("SELECT COUNT(*) FROM modulo_permisos WHERE modulo = ? AND permiso_id = ?");
                $stmt->execute([$modulo, $permiso_id]);
                
                if ($stmt->fetchColumn() > 0) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'El permiso ya está asignado a este módulo'
                    ], 409);
                }
                
                // Asignar permiso al módulo
                $stmt = $conn->prepare("INSERT INTO modulo_permisos (modulo, permiso_id, fecha_asignacion) VALUES (?, ?, NOW())");
                $stmt->execute([$modulo, $permiso_id]);
                
                // Registrar en auditoría
                registrarLog($conn, 'ASSIGN_PERMISSION_TO_MODULE', 'modulo_permisos', $conn->lastInsertId(), 
                           json_encode(['modulo' => $modulo, 'permiso_id' => $permiso_id]));
                
                $conn->commit();
                
                sendJsonResponse([
                    'success' => true,
                    'message' => "Permiso asignado correctamente al módulo {$modulo}"
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Error al asignar el permiso: ' . $e->getMessage()
                ], 500);
            }
            break;
            
        case 'unassign_permission_from_module':
            // Validar parámetros requeridos
            if (!isset($requestData['modulo']) || !isset($requestData['permiso_id'])) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Parámetros insuficientes: modulo y permiso_id requeridos'
                ], 400);
            }
            
            $modulo = $requestData['modulo'];
            $permiso_id = (int)$requestData['permiso_id'];
            
            // Validar módulo
            if (!validarModulo($modulo)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Módulo no válido'
                ], 400);
            }
            
            try {
                $conn->beginTransaction();
                
                // Verificar que el permiso está asignado
                $stmt = $conn->prepare("SELECT id FROM modulo_permisos WHERE modulo = ? AND permiso_id = ?");
                $stmt->execute([$modulo, $permiso_id]);
                $registro = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$registro) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'El permiso no está asignado a este módulo'
                    ], 404);
                }
                
                // Desasignar permiso del módulo
                $stmt = $conn->prepare("DELETE FROM modulo_permisos WHERE modulo = ? AND permiso_id = ?");
                $stmt->execute([$modulo, $permiso_id]);
                
                // Registrar en auditoría
                registrarLog($conn, 'UNASSIGN_PERMISSION_FROM_MODULE', 'modulo_permisos', $registro['id'], 
                           json_encode(['modulo' => $modulo, 'permiso_id' => $permiso_id]));
                
                $conn->commit();
                
                sendJsonResponse([
                    'success' => true,
                    'message' => "Permiso desasignado correctamente del módulo {$modulo}"
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Error al desasignar el permiso: ' . $e->getMessage()
                ], 500);
            }
            break;
            
        case 'get_module_permissions_detail':
            if (!isset($requestData['modulo'])) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Módulo no especificado'
                ], 400);
            }
            
            $modulo = $requestData['modulo'];
            
            if (!validarModulo($modulo)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Módulo no válido'
                ], 400);
            }
            
            try {
                // Obtener permisos asignados al módulo
                $stmt = $conn->prepare("
                    SELECT p.id, p.nombre, p.descripcion 
                    FROM permisos p 
                    INNER JOIN modulo_permisos mp ON p.id = mp.permiso_id 
                    WHERE mp.modulo = ?
                    ORDER BY p.nombre
                ");
                $stmt->execute([$modulo]);
                $permisos_asignados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Obtener permisos disponibles (no asignados)
                $stmt = $conn->prepare("
                    SELECT p.id, p.nombre, p.descripcion 
                    FROM permisos p 
                    WHERE p.id NOT IN (
                        SELECT mp.permiso_id 
                        FROM modulo_permisos mp 
                        WHERE mp.modulo = ?
                    )
                    ORDER BY p.nombre
                ");
                $stmt->execute([$modulo]);
                $permisos_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Obtener administradores del módulo
                $stmt = $conn->prepare("
                    SELECT cedula, nombre, email 
                    FROM administrador 
                    WHERE modulo = ?
                    ORDER BY nombre
                ");
                $stmt->execute([$modulo]);
                $administradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendJsonResponse([
                    'success' => true,
                    'data' => [
                        'modulo' => $modulo,
                        'permisos_asignados' => $permisos_asignados,
                        'permisos_disponibles' => $permisos_disponibles,
                        'administradores' => $administradores,
                        'total_asignados' => count($permisos_asignados),
                        'total_disponibles' => count($permisos_disponibles)
                    ]
                ]);
                
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Error al obtener detalles del módulo: ' . $e->getMessage()
                ], 500);
            }
            break;
            
        case 'get_user_permissions':
            if (!isset($requestData['cedula'])) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Cédula no especificada'
                ], 400);
            }
            
            $cedula = $requestData['cedula'];
            
            try {
                // Usar el procedimiento almacenado ObtenerPermisosUsuario
                $stmt = $conn->prepare("CALL ObtenerPermisosUsuario(?)");
                $stmt->execute([$cedula]);
                $permisos_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendJsonResponse([
                    'success' => true,
                    'data' => $permisos_usuario
                ]);
                
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Error al obtener permisos del usuario: ' . $e->getMessage()
                ], 500);
            }
            break;
            
        case 'check_user_permission':
            if (!isset($requestData['cedula']) || !isset($requestData['permiso_nombre'])) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Parámetros insuficientes: cedula y permiso_nombre requeridos'
                ], 400);
            }
            
            $cedula = $requestData['cedula'];
            $permiso_nombre = $requestData['permiso_nombre'];
            
            try {
                // Usar la función UsuarioTienePermiso
                $stmt = $conn->prepare("SELECT UsuarioTienePermiso(?, ?) as tiene_permiso");
                $stmt->execute([$cedula, $permiso_nombre]);
                $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $tiene_permiso = (bool)$resultado['tiene_permiso'];
                
                sendJsonResponse([
                    'success' => true,
                    'data' => [
                        'cedula' => $cedula,
                        'permiso' => $permiso_nombre,
                        'tiene_permiso' => $tiene_permiso
                    ]
                ]);
                
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Error al verificar permiso del usuario: ' . $e->getMessage()
                ], 500);
            }
            break;
            
        case 'get_all_modules':
            try {
                $stmt = $conn->prepare("
                    SELECT 
                        m.id,
                        m.nombre as modulo_nombre,
                        COUNT(mp.id) as total_permisos,
                        COUNT(DISTINCT a.id) as total_administradores
                    FROM modulos m
                    LEFT JOIN modulo_permisos mp ON m.nombre = mp.modulo
                    LEFT JOIN administrador a ON m.nombre = a.modulo
                    GROUP BY m.id, m.nombre
                    ORDER BY m.nombre
                ");
                $stmt->execute();
                $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendJsonResponse([
                    'success' => true,
                    'data' => $modulos
                ]);
                
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Error al obtener módulos: ' . $e->getMessage()
                ], 500);
            }
            break;
            
        case 'export_modules_config':
            try {
                // Exportar configuración completa de módulos y permisos
                $stmt = $conn->prepare("
                    SELECT 
                        m.nombre as modulo,
                        p.id as permiso_id,
                        p.nombre as permiso_nombre,
                        p.descripcion as permiso_descripcion,
                        mp.fecha_asignacion
                    FROM modulos m
                    LEFT JOIN modulo_permisos mp ON m.nombre = mp.modulo
                    LEFT JOIN permisos p ON mp.permiso_id = p.id
                    ORDER BY m.nombre, p.nombre
                ");
                $stmt->execute();
                $configuracion = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendJsonResponse([
                    'success' => true,
                    'data' => [
                        'fecha_exportacion' => date('Y-m-d H:i:s'),
                        'configuracion_modulos' => $configuracion
                    ]
                ]);
                
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Error al exportar configuración: ' . $e->getMessage()
                ], 500);
            }
            break;
            
        case 'get_module_stats':
            try {
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(DISTINCT m.nombre) as total_modulos,
                        COUNT(DISTINCT p.id) as total_permisos,
                        COUNT(mp.id) as total_asignaciones,
                        COUNT(DISTINCT a.id) as total_administradores
                    FROM modulos m
                    LEFT JOIN modulo_permisos mp ON m.nombre = mp.modulo
                    LEFT JOIN permisos p ON mp.permiso_id = p.id
                    LEFT JOIN administrador a ON m.nombre = a.modulo
                ");
                $stmt->execute();
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                sendJsonResponse([
                    'success' => true,
                    'data' => $stats
                ]);
                
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
                ], 500);
            }
            break;
            
        default:
            sendJsonResponse([
                'success' => false,
                'message' => 'Acción no reconocida: ' . $action
            ], 400);
            break;
    }
    
} catch (PDOException $e) {
    // Error específico de base de datos
    error_log("Error de BD en api_panel.php: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage(),
        'code' => 'DB_ERROR'
    ], 500);
    
} catch (Exception $e) {
    // Error general de conexión o configuración
    error_log("Error general en api_panel.php: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage(),
        'code' => 'SERVER_ERROR'
    ], 500);
} finally {
    // Cerrar conexión si está abierta
    if (isset($conn)) {
        $conn = null;
    }
}
?>