<?php
/**
 * SESSION_MANAGER.PHP - Manejo de sesiones y timeout
 * Incluir este archivo en todas las paginas protegidas
 */

// Iniciar sesion si no esta iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Funcion para verificar si el usuario esta logueado
function verificarSesion() {
    // CORREGIDO: Usar los nombres de variables que index.php establece
    return isset($_SESSION['user_id']) && isset($_SESSION['user_name']);
}

// Funcion para verificar timeout de sesion
function verificarTimeout() {
    // Verificar si existe last_activity y timeout_duration
    if (!isset($_SESSION['last_activity']) || !isset($_SESSION['timeout_duration'])) {
        return false; // Sesion invalida
    }
    
    $tiempoInactivo = time() - $_SESSION['last_activity'];
    $tiempoMaximo = $_SESSION['timeout_duration']; // 600 segundos (10 minutos)
    
    return $tiempoInactivo <= $tiempoMaximo;
}

// Funcion para actualizar la actividad de la sesion
function actualizarActividad() {
    $_SESSION['last_activity'] = time();
}

// Funcion para cerrar sesion
function cerrarSesion($redirigir = true, $mensaje = 'sesion_cerrada') {
    // Destruir todas las variables de sesion
    $_SESSION = array();
    
    // Destruir la cookie de sesion si existe
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir la sesion
    session_destroy();
    
    if ($redirigir) {
        // CORREGIDO: Ahora redirige a index.php en lugar de login.php
        header("Location: index.php?mensaje=$mensaje");
        exit;
    }
}

// Funcion para obtener informacion del usuario
function getUsuarioInfo($campo = null) {
    if (!verificarSesion()) {
        return null;
    }
    
    if ($campo) {
        return $_SESSION[$campo] ?? null;
    }
    
    // CORREGIDO: Usar los nombres de variables correctos
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'cedula' => $_SESSION['cedula'] ?? null,
        'usuario' => $_SESSION['user_email'] ?? null,
        'nombre' => $_SESSION['user_name'] ?? null,
        'modulo' => $_SESSION['user_role'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null
    ];
}

// Funcion para verificar permisos por modulo
function verificarModulo($moduloRequerido) {
    if (!verificarSesion()) {
        return false;
    }
    
    // CORREGIDO: Usar user_role en lugar de modulo
    $moduloUsuario = $_SESSION['user_role'] ?? '';
    
    // Administrador tiene acceso a todo
    if ($moduloUsuario === 'Administrador') {
        return true;
    }
    
    // Verificar modulo especifico
    return $moduloUsuario === $moduloRequerido;
}

// Funcion principal de verificacion (usar en paginas protegidas)
function protegerPagina($moduloRequerido = null) {
    // Verificar si esta logueado
    if (!verificarSesion()) {
        cerrarSesion(true, 'sesion_requerida');
        return;
    }
    
    // Verificar timeout
    if (!verificarTimeout()) {
        cerrarSesion(true, 'sesion_expirada');
        return;
    }
    
    // Verificar permisos de modulo si se especifica
    if ($moduloRequerido && !verificarModulo($moduloRequerido)) {
        header('Location: acceso_denegado.php');
        exit;
    }
    
    // Actualizar actividad
    actualizarActividad();
}

// Manejo de solicitudes AJAX para verificar sesion
if (isset($_GET['action']) && $_GET['action'] === 'check_session') {
    header('Content-Type: application/json');
    
    if (!verificarSesion() || !verificarTimeout()) {
        echo json_encode([
            'valid' => false,
            'message' => 'Sesion expirada'
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

// Auto-verificacion si no es una solicitud AJAX
if (!isset($_GET['action'])) {
    // Solo auto-proteger si no estamos en paginas publicas
    $paginasPublicas = ['login.php', 'index.php', 'registro.php'];
    $paginaActual = basename($_SERVER['PHP_SELF']);
    
    if (!in_array($paginaActual, $paginasPublicas)) {
        protegerPagina();
    }
}
?>