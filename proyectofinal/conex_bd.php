<?php



// conex_bd.php - Configuración de base de datos y funciones comunes

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'proyecto-final');
define('DB_USER', 'root');
define('DB_PASS', ''); // Cambiar por tu contraseña de MySQL
define('DB_CHARSET', 'utf8mb4');

/**
 * Función para obtener conexión a la base de datos
 * @return PDO Conexión PDO a la base de datos
 * @throws Exception Si hay error en la conexión
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci",
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    PDO::ATTR_PERSISTENT => false
];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Función para enviar respuestas JSON
 * @param array $data Datos a enviar
 * @param int $statusCode Código de estado HTTP (por defecto 200)
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Función para sanitizar entrada de datos
 * @param string $input Entrada a sanitizar
 * @return string Entrada sanitizada
 */
function sanitizeInput($input) {
    if (!is_string($input)) {
        return $input;
    }
    
    // Eliminar espacios en blanco al inicio y final
    $input = trim($input);
    
    // Convertir caracteres especiales a entidades HTML
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

/**
 * Función para manejar errores de base de datos
 * @param PDOException $e Excepción de PDO
 * @param string $operation Operación que se estaba realizando
 * @return array Array con información del error
 */
function handleDBError(PDOException $e, $operation = '') {
    $error_message = "Error en la base de datos";
    
    if (!empty($operation)) {
        $error_message .= " al " . $operation;
    }
    
    // Log del error completo
    error_log("Error PDO en $operation: " . $e->getMessage());
    
    // En desarrollo, mostrar más detalles (cambiar a false en producción)
    $debug_mode = true;
    
    $response = [
        'success' => false,
        'error' => $error_message
    ];
    
    if ($debug_mode) {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
    
    return $response;
}

/**
 * Función para verificar la conexión de base de datos
 * @return bool True si la conexión es exitosa
 */
function testDBConnection() {
    try {
        $conn = getDBConnection();
        $stmt = $conn->query("SELECT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Función para escapar HTML y prevenir XSS
 * @param string $string Cadena a escapar
 * @return string Cadena escapada
 */
function escapeHtml($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Función para validar email
 * @param string $email Email a validar
 * @return bool True si es válido
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Función para generar token seguro
 * @param int $length Longitud del token
 * @return string Token generado
 */
function generateSecureToken($length = 32) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    } else {
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    }
}

/**
 * Función para log de actividades
 * @param string $message Mensaje a registrar
 * @param string $level Nivel del log (INFO, WARNING, ERROR)
 */
function logActivity($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message" . PHP_EOL;
    
    $log_file = 'logs/activity.log';
    
    // Crear directorio de logs si no existe
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * Configuración de zona horaria
 */
date_default_timezone_set('America/Bogota');

/**
 * Configuración de errores para desarrollo
 * Cambiar a false en producción
 */
$development_mode = true;

if ($development_mode) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

/**
 * Configuración de sesión segura
 */
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Cambiar a 1 si usas HTTPS
    ini_set('session.use_strict_mode', 1);
    session_start();
}

/**
 * Función adicional para validar codificación UTF-8
 * @param string $string Cadena a validar
 * @return bool True si es UTF-8 válido
 */
function isValidUTF8($string) {
    return mb_check_encoding($string, 'UTF-8');
}

/**
 * Función para limpiar y validar entrada UTF-8
 * @param string $input Entrada a limpiar
 * @return string Entrada limpia y válida
 */
function cleanUTF8Input($input) {
    if (!is_string($input)) {
        return $input;
    }
    
    // Convertir a UTF-8 si no lo es
    if (!mb_check_encoding($input, 'UTF-8')) {
        $input = mb_convert_encoding($input, 'UTF-8', 'auto');
    }
    
    // Eliminar caracteres de control excepto saltos de línea y tabulaciones
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
    
    return trim($input);
}
?>