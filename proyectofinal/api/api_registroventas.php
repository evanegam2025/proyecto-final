<?php
// ======================================================================
// API EXTERNA PARA GESTIÓN DE VENTAS
// ======================================================================

// Configurar headers para JSON y UTF-8
header('Content-Type: application/json; charset=utf-8');

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
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => "Error de conexión a la base de datos: " . $conn->connect_error
    ], JSON_UNESCAPED_UNICODE);
    die();
}

session_start();

// ======================================================================
// FUNCIONES DE SEGURIDAD Y VALIDACIÓN
// ======================================================================

function validarSesion() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
        return ['success' => false, 'message' => 'Acceso denegado. No ha iniciado sesión.'];
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
    // Validar datos requeridos
    $campos_requeridos = ['fecha', 'cedula', 'nombre', 'telefono1', 'email', 'municipio', 'vereda', 'indicaciones', 'num_servicio', 'tecnologia', 'plan'];
    $validacion = validarEntrada($_POST, $campos_requeridos);
    if (!$validacion['success']) {
        return $validacion;
    }

    // Obtener datos de sesión
    $vendedor_usuario = $_SESSION['cedula'];
    $vendedor_nombre = $_SESSION['nombre'];
    $vendedor_cedula = $_SESSION['cedula'];

    // Sanitizar datos de entrada
    $data = sanitizarDatos($_POST);

    // Validaciones adicionales
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El email no tiene un formato válido.'];
    }

    if (!is_numeric($data['cedula'])) {
        return ['success' => false, 'message' => 'La cédula debe ser numérica.'];
    }

    $sql = "INSERT INTO ventas (fecha, cedula, nombre, telefono1, telefono2, email, municipio, vereda, coordenadas, indicaciones, notas, num_servicio, tecnologia, plan, vendedor_usuario, vendedor_nombre, vendedor_cedula) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error en la preparación de la consulta: ' . $conn->error];
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
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Error al registrar la venta: ' . $error];
    }
}

function getVenta($conn) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        return ['success' => false, 'message' => 'ID de venta no válido.'];
    }
    
    $stmt = $conn->prepare("SELECT * FROM ventas WHERE id = ? AND vendedor_cedula = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error en la preparación de la consulta.'];
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
        return ['success' => false, 'message' => 'ID de venta no válido.'];
    }

    // Validar datos requeridos
    $campos_requeridos = ['fecha', 'cedula', 'nombre', 'telefono1', 'email', 'municipio', 'vereda', 'indicaciones', 'num_servicio', 'tecnologia', 'plan'];
    $validacion = validarEntrada($_POST, $campos_requeridos);
    if (!$validacion['success']) {
        return $validacion;
    }

    // Sanitizar datos de entrada
    $data = sanitizarDatos($_POST);

    // Validaciones adicionales
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El email no tiene un formato válido.'];
    }

    if (!is_numeric($data['cedula'])) {
        return ['success' => false, 'message' => 'La cédula debe ser numérica.'];
    }

    $sql = "UPDATE ventas SET fecha=?, cedula=?, nombre=?, telefono1=?, telefono2=?, email=?, municipio=?, vereda=?, coordenadas=?, indicaciones=?, notas=?, num_servicio=?, tecnologia=?, plan=? WHERE id=? AND vendedor_cedula=?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error en la preparación de la consulta: ' . $conn->error];
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
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Error al actualizar la venta: ' . $error];
    }
}

function deleteVenta($conn) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        return ['success' => false, 'message' => 'ID de venta no válido.'];
    }
    
    $stmt = $conn->prepare("DELETE FROM ventas WHERE id = ? AND vendedor_cedula = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error en la preparación de la consulta.'];
    }

    $stmt->bind_param("is", $id, $_SESSION['cedula']);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Venta eliminada correctamente.'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Error al eliminar la venta: ' . $error];
    }
}

function getStats($conn) {
    $cedula_usuario = $_SESSION['cedula'];
    $stmt_stats = $conn->prepare("SELECT COUNT(*) as total_ventas, COUNT(CASE WHEN DATE(fecha) = CURDATE() THEN 1 END) as ventas_hoy, COUNT(CASE WHEN YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) as ventas_semana, COUNT(CASE WHEN MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) THEN 1 END) as ventas_mes FROM ventas WHERE vendedor_cedula = ?");
    
    if (!$stmt_stats) {
        return ['success' => false, 'message' => 'Error en la consulta de estadísticas.'];
    }

    $stmt_stats->bind_param("s", $cedula_usuario);
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
    $stmt_stats->close();
    return ['success' => true, 'stats' => $stats];
}

function logout() {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Sesión cerrada correctamente.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// ======================================================================
// CONTROLADOR PRINCIPAL (ROUTER)
// ======================================================================

// Validar sesión para todas las acciones excepto logout
$action = $_GET['action'] ?? '';

// Validar sesión
$auth = validarSesion();
if (!$auth['success']) {
    http_response_code(401);
    echo json_encode($auth, JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit();
}

$response = [];

switch ($action) {
    case 'guardar_venta':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response = ['success' => false, 'message' => 'Método no permitido.'];
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
            $response = ['success' => false, 'message' => 'Método no permitido.'];
            http_response_code(405);
        } else {
            $response = updateVenta($conn);
        }
        break;
        
    case 'delete_venta':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response = ['success' => false, 'message' => 'Método no permitido.'];
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
        $response = ['success' => false, 'message' => 'Acción no válida.'];
        http_response_code(404);
        break;
}

// Cerrar conexión antes de enviar respuesta
$conn->close();

// Enviar respuesta JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>