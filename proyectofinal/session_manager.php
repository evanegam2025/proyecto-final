<?php
/**
 * SESSION_MANAGER.PHP - Manejo de sesiones y timeout
 * Incluir este archivo en todas las páginas protegidas
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función para verificar si el usuario está logueado
function verificarSesion() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario']);
}

// Función para verificar timeout de sesión
function verificarTimeout() {
    // Verificar si existe last_activity y timeout_duration
    if (!isset($_SESSION['last_activity']) || !isset($_SESSION['timeout_duration'])) {
        return false; // Sesión inválida
    }
    
    $tiempoInactivo = time() - $_SESSION['last_activity'];
    $tiempoMaximo = $_SESSION['timeout_duration']; // 600 segundos (10 minutos)
    
    return $tiempoInactivo <= $tiempoMaximo;
}

// Función para actualizar la actividad de la sesión
function actualizarActividad() {
    $_SESSION['last_activity'] = time();
}

// Función para cerrar sesión
function cerrarSesion($redirigir = true, $mensaje = 'sesion_cerrada') {
    // Destruir todas las variables de sesión
    $_SESSION = array();
    
    // Destruir la cookie de sesión si existe
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir la sesión
    session_destroy();
    
    if ($redirigir) {
        header("Location: login.php?mensaje=$mensaje");
        exit;
    }
}

// Función para obtener información del usuario
function getUsuarioInfo($campo = null) {
    if (!verificarSesion()) {
        return null;
    }
    
    if ($campo) {
        return $_SESSION[$campo] ?? null;
    }
    
    return [
        'id' => $_SESSION['usuario_id'] ?? null,
        'cedula' => $_SESSION['cedula'] ?? null,
        'usuario' => $_SESSION['usuario'] ?? null,
        'nombre' => $_SESSION['nombre'] ?? null,
        'modulo' => $_SESSION['modulo'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null
    ];
}

// Función para verificar permisos por módulo
function verificarModulo($moduloRequerido) {
    if (!verificarSesion()) {
        return false;
    }
    
    $moduloUsuario = $_SESSION['modulo'] ?? '';
    
    // Administrador tiene acceso a todo
    if ($moduloUsuario === 'Administrador') {
        return true;
    }
    
    // Verificar módulo específico
    return $moduloUsuario === $moduloRequerido;
}

// Función principal de verificación (usar en páginas protegidas)
function protegerPagina($moduloRequerido = null) {
    // Verificar si está logueado
    if (!verificarSesion()) {
        cerrarSesion(true, 'sesion_requerida');
        return;
    }
    
    // Verificar timeout
    if (!verificarTimeout()) {
        cerrarSesion(true, 'sesion_expirada');
        return;
    }
    
    // Verificar permisos de módulo si se especifica
    if ($moduloRequerido && !verificarModulo($moduloRequerido)) {
        header('Location: acceso_denegado.php');
        exit;
    }
    
    // Actualizar actividad
    actualizarActividad();
}

// Manejo de solicitudes AJAX para verificar sesión
if (isset($_GET['action']) && $_GET['action'] === 'check_session') {
    header('Content-Type: application/json');
    
    if (!verificarSesion() || !verificarTimeout()) {
        echo json_encode([
            'valid' => false,
            'message' => 'Sesión expirada'
        ]);
    } else {
        actualizarActividad();
        $tiempoRestante = $_SESSION['timeout_duration'] - (time() - $_SESSION['last_activity']);
        
        echo json_encode([
            'valid' => true,
            'remaining_time' => $tiempoRestante,
            'user_info' => getUsuarioInfo()
        ]);
    }
    exit;
}

// Auto-verificación si no es una solicitud AJAX
if (!isset($_GET['action'])) {
    // Solo auto-proteger si no estamos en páginas públicas
    $paginasPublicas = ['login.php', 'index.php', 'registro.php'];
    $paginaActual = basename($_SERVER['PHP_SELF']);
    
    if (!in_array($paginaActual, $paginasPublicas)) {
        protegerPagina();
    }
}
?>