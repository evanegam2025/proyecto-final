<?php
/**
 * LOGOUT.PHP - Cierre de sesión seguro
 */

// Configurar la codificación UTF-8
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

session_start();

// Función para limpiar sesión completamente
function limpiarSesion() {
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
}

// Limpiar la sesión
limpiarSesion();

// Headers de seguridad para prevenir caché
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Redireccionar siempre al index (página de login)
header("Location: index.php?mensaje=sesion_cerrada");
exit;
?>
