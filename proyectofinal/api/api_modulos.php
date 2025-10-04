<?php
/**
 * API para gestión de permisos de módulos - VERSIÓN CORREGIDA
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// DEBUG - Información de sesión (TEMPORAL)
error_log("DEBUG API - Session data: " . print_r($_SESSION, true));

// Configurar codificación
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado',
        'code' => 'UNAUTHORIZED',
        'debug' => [
            'session_exists' => session_id() ? true : false,
            'session_data' => $_SESSION ?? []
        ]
    ]);
    exit();
}

// Configuración de base de datos
try {
    $pdo = new PDO("mysql:host=localhost;dbname=proyecto-final;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos: ' . $e->getMessage(),
        'code' => 'DB_CONNECTION_ERROR'
    ]);
    exit();
}

// Obtener información del usuario usando CEDULA en lugar de user_role
$usuario_id = $_SESSION['user_id'];

// FUNCIÓN CORREGIDA: Obtener permisos usando la cédula del usuario
function obtenerPermisosUsuarioPorCedula($pdo, $usuario_id) {
    try {
        // Primero obtener la cédula del usuario
        $query_usuario = "SELECT cedula, modulo, nombre FROM administrador WHERE id = :usuario_id";
        $stmt = $pdo->prepare($query_usuario);
        $stmt->execute(['usuario_id' => $usuario_id]);
        $usuario_data = $stmt->fetch();
        
        if (!$usuario_data) {
            error_log("Usuario no encontrado con ID: " . $usuario_id);
            return [];
        }
        
        error_log("DEBUG - Usuario encontrado: " . print_r($usuario_data, true));
        
        // Usar el procedimiento almacenado que ya tienes
        $query = "CALL ObtenerPermisosUsuario(:cedula)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['cedula' => $usuario_data['cedula']]);
        
        $permisos = $stmt->fetchAll();
        error_log("DEBUG - Permisos obtenidos: " . print_r($permisos, true));
        
        return [
            'permisos' => $permisos,
            'usuario' => $usuario_data
        ];
        
    } catch(PDOException $e) {
        error_log("Error obteniendo permisos: " . $e->getMessage());
        return [];
    }
}

// Definición de módulos del sistema
$modulos_sistema = [
    'dashboard' => [
        'nombre' => 'DASHBOARD',
        'icono' => 'bi-speedometer2',
        'url' => 'dashboard.php',
        'color' => 'btn-dashboard',
        'descripcion' => 'Panel de control principal',
        'permiso_requerido' => 'dashboard',
        'orden' => 1
    ],
    'consultas' => [
        'nombre' => 'CONSULTAS',
        'icono' => 'bi-search',
        'url' => 'consultas.php',
        'color' => 'btn-consultas',
        'descripcion' => 'Consultar información del sistema',
        'permiso_requerido' => 'consultas',
        'orden' => 2
    ],
    'ventas' => [
        'nombre' => 'VENTAS',
        'icono' => 'bi-cart-plus',
        'url' => 'registro_ventas.php',
        'color' => 'btn-ventas',
        'descripcion' => 'Gestión de ventas',
        'permiso_requerido' => 'ventas',
        'orden' => 3
    ],
    'agendamiento' => [
        'nombre' => 'AGENDAMIENTO',
        'icono' => 'bi-calendar-check',
        'url' => 'registro_agenda.php',
        'color' => 'btn-agendamiento',
        'descripcion' => 'Programación de instalaciones',
        'permiso_requerido' => 'agendamiento',
        'orden' => 4
    ],
    'aprovisionamiento' => [
        'nombre' => 'APROVISIONAMIENTO',
        'icono' => 'bi-gear',
        'url' => 'registro_aprovisionamiento.php',
        'color' => 'btn-aprovisionamiento',
        'descripcion' => 'Gestión de recursos',
        'permiso_requerido' => 'aprovisionamiento',
        'orden' => 5
    ],
    'permisos' => [
        'nombre' => 'PERMISOS',
        'icono' => 'bi-shield-check',
        'url' => 'permisos.php',
        'color' => 'btn-permisos',
        'descripcion' => 'Gestión de permisos de usuario',
        'permiso_requerido' => 'permisos',
        'orden' => 6
    ],
    'administrar_permisos' => [
        'nombre' => 'ADMINISTRAR PERMISOS',
        'icono' => 'bi-key',
        'url' => 'panel.php',
        'color' => 'btn-administrar-permisos',
        'descripcion' => 'Administración avanzada de permisos',
        'permiso_requerido' => 'administrar_permisos',
        'orden' => 7
    ],
    'usuario' => [
        'nombre' => 'CREAR USUARIO',
        'icono' => 'bi-person-plus',
        'url' => 'administrador.php',
        'color' => 'btn-usuario',
        'descripcion' => 'Administración de usuarios',
        'permiso_requerido' => 'crear_usuario',
        'orden' => 8
    ]
];

// Procesar la solicitud
$action = $_GET['action'] ?? '';

switch($action) {
    case 'obtener_modulos':
        // Obtener permisos del usuario usando la función corregida
        $datos_usuario = obtenerPermisosUsuarioPorCedula($pdo, $usuario_id);
        
        if (empty($datos_usuario)) {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudieron obtener los permisos del usuario',
                'code' => 'NO_PERMISSIONS_FOUND'
            ]);
            break;
        }
        
        $permisos_usuario = $datos_usuario['permisos'];
        $info_usuario = $datos_usuario['usuario'];
        $nombres_permisos = array_column($permisos_usuario, 'permiso_nombre');
        
        error_log("DEBUG - Nombres de permisos: " . print_r($nombres_permisos, true));
        
        // Filtrar módulos según permisos
        $modulos_permitidos = [];
        
        foreach($modulos_sistema as $key => $modulo) {
            if (in_array($modulo['permiso_requerido'], $nombres_permisos)) {
                $modulos_permitidos[$key] = $modulo;
                $modulos_permitidos[$key]['url'] = 'http://localhost/proyectofinal/' . $modulo['url'];
            }
        }
        
        // Ordenar módulos por orden
        uasort($modulos_permitidos, function($a, $b) {
            return $a['orden'] <=> $b['orden'];
        });
        
        echo json_encode([
            'success' => true,
            'data' => [
                'modulos' => $modulos_permitidos,
                'total_modulos' => count($modulos_permitidos),
                'usuario_rol' => $info_usuario['modulo'],
                'permisos' => $nombres_permisos,
                'debug' => [
                    'usuario_info' => $info_usuario,
                    'permisos_raw' => $permisos_usuario
                ]
            ],
            'message' => 'Módulos obtenidos exitosamente'
        ]);
        break;
        
    case 'verificar_permiso':
        $permiso = $_GET['permiso'] ?? '';
        
        if (empty($permiso)) {
            echo json_encode([
                'success' => false,
                'message' => 'Permiso no especificado'
            ]);
            break;
        }
        
        $datos_usuario = obtenerPermisosUsuarioPorCedula($pdo, $usuario_id);
        $permisos_usuario = $datos_usuario['permisos'] ?? [];
        $nombres_permisos = array_column($permisos_usuario, 'permiso_nombre');
        
        $tiene_permiso = in_array($permiso, $nombres_permisos);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'tiene_permiso' => $tiene_permiso,
                'permiso' => $permiso,
                'rol' => $datos_usuario['usuario']['modulo'] ?? 'No definido'
            ]
        ]);
        break;
        
    case 'obtener_info_usuario':
        $datos_usuario = obtenerPermisosUsuarioPorCedula($pdo, $usuario_id);
        $info_usuario = $datos_usuario['usuario'] ?? [];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'usuario_id' => $usuario_id,
                'usuario_rol' => $info_usuario['modulo'] ?? 'No definido',
                'usuario_nombre' => $info_usuario['nombre'] ?? $_SESSION['user_name'] ?? 'Usuario'
            ]
        ]);
        break;
        
    case 'test_connection':
        echo json_encode([
            'success' => true,
            'message' => 'API funcionando correctamente',
            'data' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'usuario_activo' => isset($_SESSION['user_id']),
                'session_data' => $_SESSION ?? []
            ]
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Acción no válida',
            'code' => 'INVALID_ACTION'
        ]);
        break;
}

$pdo = null;
?>