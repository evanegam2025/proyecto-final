<?php
/**
 * API para gestión de permisos de módulos - 
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en producción
ini_set('log_errors', 1);

// Headers ANTES de cualquier salida
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Función para enviar respuesta JSON y terminar
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}



// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    sendJSON([
        'success' => false,
        'message' => 'Usuario no autenticado',
        'code' => 'UNAUTHORIZED'
    ], 401);
}

// Configuración de base de datos con collation correcta
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=proyecto-final;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci"
        ]
    );
} catch(PDOException $e) {
    sendJSON([
        'success' => false,
        'message' => 'Error de conexión a la base de datos',
        'code' => 'DB_CONNECTION_ERROR',
        'error' => $e->getMessage()
    ], 500);
}

$usuario_id = $_SESSION['user_id'];

/**
 * Obtener permisos del usuario de forma segura
 */
function obtenerPermisosUsuario($pdo, $usuario_id) {
    try {
        // Obtener datos del usuario
        $stmt = $pdo->prepare("
            SELECT id, cedula, nombre, email, modulo 
            FROM administrador 
            WHERE id = ?
        ");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            return [
                'error' => 'Usuario no encontrado',
                'usuario' => null,
                'permisos' => []
            ];
        }
        
        // Obtener permisos usando JOIN directo (sin procedimiento almacenado)
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                p.id as permiso_id,
                p.nombre as permiso_nombre,
                p.descripcion as permiso_descripcion
            FROM modulo_permisos mp
            INNER JOIN permisos p ON mp.permiso_id = p.id
            WHERE mp.modulo = ?
            ORDER BY p.nombre
        ");
        $stmt->execute([$usuario['modulo']]);
        $permisos = $stmt->fetchAll();
        
        return [
            'error' => null,
            'usuario' => $usuario,
            'permisos' => $permisos
        ];
        
    } catch(PDOException $e) {
        error_log("Error en obtenerPermisosUsuario: " . $e->getMessage());
        return [
            'error' => 'Error al obtener permisos: ' . $e->getMessage(),
            'usuario' => null,
            'permisos' => []
        ];
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
        $resultado = obtenerPermisosUsuario($pdo, $usuario_id);
        
        if ($resultado['error']) {
            sendJSON([
                'success' => false,
                'message' => $resultado['error'],
                'code' => 'PERMISSION_ERROR'
            ], 500);
        }
        
        $usuario = $resultado['usuario'];
        $permisos = $resultado['permisos'];
        $nombres_permisos = array_column($permisos, 'permiso_nombre');
        
        // Filtrar módulos según permisos
        $modulos_permitidos = [];
        foreach($modulos_sistema as $key => $modulo) {
            if (in_array($modulo['permiso_requerido'], $nombres_permisos)) {
                $modulos_permitidos[$key] = $modulo;
            }
        }
        
        // Ordenar módulos
        uasort($modulos_permitidos, function($a, $b) {
            return $a['orden'] <=> $b['orden'];
        });
        
        sendJSON([
            'success' => true,
            'data' => [
                'modulos' => $modulos_permitidos,
                'total_modulos' => count($modulos_permitidos),
                'usuario_rol' => $usuario['modulo'],
                'usuario_nombre' => $usuario['nombre'],
                'permisos' => $nombres_permisos
            ],
            'message' => 'Módulos obtenidos exitosamente'
        ]);
        break;
        
    case 'verificar_permiso':
        $permiso = $_GET['permiso'] ?? '';
        
        if (empty($permiso)) {
            sendJSON([
                'success' => false,
                'message' => 'Permiso no especificado'
            ], 400);
        }
        
        $resultado = obtenerPermisosUsuario($pdo, $usuario_id);
        
        if ($resultado['error']) {
            sendJSON([
                'success' => false,
                'message' => $resultado['error']
            ], 500);
        }
        
        $nombres_permisos = array_column($resultado['permisos'], 'permiso_nombre');
        $tiene_permiso = in_array($permiso, $nombres_permisos);
        
        sendJSON([
            'success' => true,
            'data' => [
                'tiene_permiso' => $tiene_permiso,
                'permiso' => $permiso,
                'rol' => $resultado['usuario']['modulo']
            ]
        ]);
        break;
        
    case 'obtener_info_usuario':
        $resultado = obtenerPermisosUsuario($pdo, $usuario_id);
        
        if ($resultado['error']) {
            sendJSON([
                'success' => false,
                'message' => $resultado['error']
            ], 500);
        }
        
        $usuario = $resultado['usuario'];
        
        sendJSON([
            'success' => true,
            'data' => [
                'usuario_id' => $usuario_id,
                'usuario_rol' => $usuario['modulo'],
                'usuario_nombre' => $usuario['nombre'],
                'usuario_email' => $usuario['email']
            ]
        ]);
        break;
        
    case 'test_connection':
        sendJSON([
            'success' => true,
            'message' => 'API funcionando correctamente',
            'data' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'usuario_activo' => true,
                'usuario_id' => $usuario_id,
                'usuario_nombre' => $_SESSION['user_name'] ?? 'No definido'
            ]
        ]);
        break;
        
    default:
        sendJSON([
            'success' => false,
            'message' => 'Acción no válida',
            'code' => 'INVALID_ACTION'
        ], 400);
}
?>