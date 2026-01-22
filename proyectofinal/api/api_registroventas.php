<?php
// ======================================================================
// API EXTERNA PARA GESTION DE VENTAS
// Codificacion: UTF-8 sin BOM
// Compatible con Windows Defender
// ======================================================================

// Deshabilitar salida de errores para no contaminar JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Limpiar cualquier salida previa
if (ob_get_level()) ob_end_clean();
ob_start();

// Configurar headers para JSON y UTF-8
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Iniciar sesion ANTES de cualquier salida
session_start();

// ======================================================================
// CONFIGURACION DE BASE DE DATOS
// ======================================================================

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "proyecto-final";

// Crear conexion y establecer charset a UTF-8mb4
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexion");
    }
    
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexion a la base de datos'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ======================================================================
// FUNCIONES DE SEGURIDAD Y VALIDACION
// ======================================================================

function validarSesion() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['cedula'])) {
        return ['success' => false, 'message' => 'Acceso denegado. No ha iniciado sesion.'];
    }
    return ['success' => true];
}

function sanitizarDatos($data) {
    if (is_array($data)) {
        return array_map(function($value) {
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }, $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validarEntrada($data, $campos_requeridos = []) {
    foreach ($campos_requeridos as $campo) {
        if (!isset($data[$campo]) || empty(trim($data[$campo]))) {
            return ['success' => false, 'message' => "El campo '$campo' es obligatorio."];
        }
    }
    return ['success' => true];
}

// ======================================================================
// FUNCIONES DE LA API
// ======================================================================

function guardarVenta($conn) {
    $campos_requeridos = ['fecha', 'cedula', 'nombre', 'telefono1', 'email', 'municipio', 'vereda', 'indicaciones', 'num_servicio', 'tecnologia', 'plan'];
    $validacion = validarEntrada($_POST, $campos_requeridos);
    if (!$validacion['success']) {
        return $validacion;
    }

    if (!isset($_SESSION['cedula']) || !isset($_SESSION['nombre'])) {
        return ['success' => false, 'message' => 'Error de sesion. Por favor, inicie sesion nuevamente.'];
    }

    $vendedor_usuario = $_SESSION['cedula'];
    $vendedor_nombre = $_SESSION['nombre'];
    $vendedor_cedula = $_SESSION['cedula'];

    $data = sanitizarDatos($_POST);

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El email no tiene un formato valido.'];
    }

    if (!is_numeric($data['cedula'])) {
        return ['success' => false, 'message' => 'La cedula debe ser numerica.'];
    }

    $sql = "INSERT INTO ventas (fecha, cedula, nombre, telefono1, telefono2, email, municipio, vereda, coordenadas, indicaciones, notas, num_servicio, tecnologia, plan, vendedor_usuario, vendedor_nombre, vendedor_cedula) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error en la preparacion de la consulta.'];
    }

    $stmt->bind_param("sssssssssssssssss", 
        $data['fecha'], $data['cedula'], $data['nombre'], 
        $data['telefono1'], $data['telefono2'], $data['email'], 
        $data['municipio'], $data['vereda'], $data['coordenadas'], 
        $data['indicaciones'], $data['notas'], $data['num_servicio'], 
        $data['tecnologia'], $data['plan'], 
        $vendedor_usuario, $vendedor_nombre, $vendedor_cedula
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Venta registrada exitosamente.'];
    } else {
        $stmt->close();
        return ['success' => false, 'message' => 'Error al registrar la venta.'];
    }
}

function getVenta($conn) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        return ['success' => false, 'message' => 'ID de venta no valido.'];
    }
    
    if (!isset($_SESSION['cedula'])) {
        return ['success' => false, 'message' => 'Error de sesion.'];
    }
    
    $stmt = $conn->prepare("SELECT * FROM ventas WHERE id = ? AND vendedor_cedula = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error en la preparacion de la consulta.'];
    }

    $stmt->bind_param("is", $id, $_SESSION['cedula']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $venta = $result->fetch_assoc();
        $stmt->close();
        return ['success' => true, 'venta' => $venta];
    } else {
        $stmt->close();
        return ['success' => false, 'message' => 'Venta no encontrada o no pertenece a este vendedor.'];
    }
}

function updateVenta($conn) {
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$id) {
        return ['success' => false, 'message' => 'ID de venta no valido.'];
    }

    $campos_requeridos = ['fecha', 'cedula', 'nombre', 'telefono1', 'email', 'municipio', 'vereda', 'indicaciones', 'num_servicio', 'tecnologia', 'plan'];
    $validacion = validarEntrada($_POST, $campos_requeridos);
    if (!$validacion['success']) {
        return $validacion;
    }

    $data = sanitizarDatos($_POST);

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El email no tiene un formato valido.'];
    }

    if (!is_numeric($data['cedula'])) {
        return ['success' => false, 'message' => 'La cedula debe ser numerica.'];
    }

    if (!isset($_SESSION['cedula'])) {
        return ['success' => false, 'message' => 'Error de sesion.'];
    }

    $sql = "UPDATE ventas SET fecha=?, cedula=?, nombre=?, telefono1=?, telefono2=?, email=?, municipio=?, vereda=?, coordenadas=?, indicaciones=?, notas=?, num_servicio=?, tecnologia=?, plan=? WHERE id=? AND vendedor_cedula=?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error en la preparacion de la consulta.'];
    }

    $stmt->bind_param("ssssssssssssssis", 
        $data['fecha'], $data['cedula'], $data['nombre'], 
        $data['telefono1'], $data['telefono2'], $data['email'], 
        $data['municipio'], $data['vereda'], $data['coordenadas'], 
        $data['indicaciones'], $data['notas'], $data['num_servicio'], 
        $data['tecnologia'], $data['plan'], 
        $id, $_SESSION['cedula']
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Venta actualizada correctamente.'];
    } else {
        $stmt->close();
        return ['success' => false, 'message' => 'Error al actualizar la venta.'];
    }
}

function deleteVenta($conn) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        return ['success' => false, 'message' => 'ID de venta no valido.'];
    }
    
    if (!isset($_SESSION['cedula'])) {
        return ['success' => false, 'message' => 'Error de sesion.'];
    }
    
    $stmt = $conn->prepare("DELETE FROM ventas WHERE id = ? AND vendedor_cedula = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error en la preparacion de la consulta.'];
    }

    $stmt->bind_param("is", $id, $_SESSION['cedula']);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Venta eliminada correctamente.'];
    } else {
        $stmt->close();
        return ['success' => false, 'message' => 'Error al eliminar la venta.'];
    }
}

function getStats($conn) {
    if (!isset($_SESSION['cedula'])) {
        return ['success' => false, 'message' => 'Error de sesion.'];
    }
    
    $cedula_usuario = $_SESSION['cedula'];
    $stmt_stats = $conn->prepare("SELECT COUNT(*) as total_ventas, COUNT(CASE WHEN DATE(fecha) = CURDATE() THEN 1 END) as ventas_hoy, COUNT(CASE WHEN YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) as ventas_semana, COUNT(CASE WHEN MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) THEN 1 END) as ventas_mes FROM ventas WHERE vendedor_cedula = ?");
    
    if (!$stmt_stats) {
        return ['success' => false, 'message' => 'Error en la consulta de estadisticas.'];
    }

    $stmt_stats->bind_param("s", $cedula_usuario);
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
    $stmt_stats->close();
    return ['success' => true, 'stats' => $stats];
}

function logout() {
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    
    session_destroy();
    
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true, 
        'message' => 'Sesion cerrada correctamente.',
        'redirect' => '/proyectofinal/index.php'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}


// ======================================================================
// CONTROLADOR PRINCIPAL
// ======================================================================

$action = $_GET['action'] ?? '';

$auth = validarSesion();
if (!$auth['success']) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode($auth, JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit();
}

$response = [];

switch ($action) {
    case 'guardar_venta':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response = ['success' => false, 'message' => 'Metodo no permitido.'];
            http_response_code(405);
        } else {
            $response = guardarVenta($conn);
        }
        break;
        
    case 'get_venta':
        $response = getVenta($conn);
        break;
        
    case 'update_venta':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response = ['success' => false, 'message' => 'Metodo no permitido.'];
            http_response_code(405);
        } else {
            $response = updateVenta($conn);
        }
        break;
        
    case 'delete_venta':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response = ['success' => false, 'message' => 'Metodo no permitido.'];
            http_response_code(405);
        } else {
            $response = deleteVenta($conn);
        }
        break;
        
    case 'get_stats':
        $response = getStats($conn);
        break;
        
    case 'logout':
        logout();
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Accion no valida.'];
        http_response_code(404);
        break;
}

$conn->close();

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>