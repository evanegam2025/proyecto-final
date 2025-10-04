<?php
// Configurar la codificación UTF-8
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

session_start();

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'proyecto-final';
$username = 'root';
$password = '';

$mensaje = '';
$tipo_mensaje = '';
$usuario_input = '';

// Verificar si hay mensaje de sesión cerrada
if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'sesion_cerrada') {
    $mensaje = 'Sesión cerrada correctamente';
    $tipo_mensaje = 'success';
} elseif (isset($_GET['mensaje']) && $_GET['mensaje'] === 'sesion_expirada') {
    $mensaje = 'Su sesión ha expirado. Por favor, inicie sesión nuevamente.';
    $tipo_mensaje = 'warning';
}

// Redirigir si ya está logueado
if (isset($_SESSION['user_id']) && isset($_SESSION['user_name'])) {
    header('Location: modulos.php');
    exit;
}

// Función para limpiar y validar entrada
function limpiarEntrada($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Función para validar formato de usuario
function validarUsuario($usuario) {
    return preg_match('/^[a-zA-Z0-9._@-]+$/', $usuario);
}

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $usuario = limpiarEntrada($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';
        $usuario_input = $usuario;

        // Validaciones
        if (empty($usuario) || empty($contrasena)) {
            throw new Exception('Usuario y contraseña son obligatorios');
        }

        if (!validarUsuario($usuario)) {
            throw new Exception('El formato del usuario no es válido');
        }

        if (strlen($usuario) < 3 || strlen($usuario) > 50) {
            throw new Exception('El usuario debe tener entre 3 y 50 caracteres');
        }

        if (strlen($contrasena) < 8) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres');
        }

        // Conexión a la base de datos
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $username, $password, $options);

        // Buscar usuario en la tabla administrador
        $stmt = $pdo->prepare("SELECT id, cedula, nombre, email, contrasena, modulo 
                              FROM administrador 
                              WHERE (cedula = ? OR email = ?) 
                              LIMIT 1");
        $stmt->execute([$usuario, $usuario]);
        $user_data = $stmt->fetch();

        if ($user_data && password_verify($contrasena, $user_data['contrasena'])) {
            // Validar módulos permitidos
            $modulos_validos = ['Administrador', 'Vendedor', 'Agendamiento', 'Aprovisionamiento'];
            
            if (!in_array($user_data['modulo'], $modulos_validos)) {
                throw new Exception('Módulo de usuario no válido');
            }

            // Login exitoso - configurar sesión con nombres consistentes
            session_regenerate_id(true);
            
            // VARIABLES CONSISTENTES CON modulos.php
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['user_name'] = $user_data['nombre'];
            $_SESSION['user_role'] = $user_data['modulo'];
            $_SESSION['user_email'] = $user_data['email'];
            $_SESSION['cedula'] = $user_data['cedula'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['timeout_duration'] = 600; // 10 minutos

            // Actualizar fecha de último acceso
            $stmt = $pdo->prepare("UPDATE administrador SET fecha_actualizacion = NOW() WHERE id = ?");
            $stmt->execute([$user_data['id']]);

            // Redirigir a módulos
            header("Location: modulos.php");
            exit;
        } else {
            throw new Exception('Credenciales incorrectas');
        }

    } catch (PDOException $e) {
        error_log("Error de base de datos: " . $e->getMessage());
        $mensaje = 'Error de conexión. Intente nuevamente.';
        $tipo_mensaje = 'danger';
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de Inicio de Sesión - SVI">
    <meta name="author" content="SVI">
    <title>Iniciar Sesión - SVI</title>
    
    <!-- Bootstrap CSS - SIN integrity hash para evitar errores SRI -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="css/stylos_login.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="imagenes/favicon.ico">
</head>
<body>
    <div class="container-fluid login-wrapper">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-4">
                <div class="login-container">
                    <!-- Logo -->
                    <div class="logo-container text-center mb-4">
                        <img src="imagenes/logo1.JPG" alt="Logo SVI" class="logo-img">
                    </div>

                    <!-- Título -->
                    <h1 class="login-title">INICIAR SESIÓN</h1>

                    <!-- Mensajes de alerta -->
                    <?php if (!empty($mensaje)): ?>
                        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                            <i class="bi bi-<?php echo $tipo_mensaje === 'danger' ? 'exclamation-triangle' : ($tipo_mensaje === 'success' ? 'check-circle' : 'info-circle'); ?>"></i>
                            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Formulario de login -->
                    <form method="POST" action="" id="loginForm" novalidate>
                        <div class="mb-3">
                            <label for="usuario" class="form-label">
                                <i class="bi bi-person"></i> Usuario o Email
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" 
                                       id="usuario" 
                                       name="usuario" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($usuario_input, ENT_QUOTES, 'UTF-8'); ?>" 
                                       placeholder="Ingrese su usuario o email"
                                       autocomplete="username"
                                       required>
                                <div class="invalid-feedback">
                                    Por favor ingrese un usuario válido.
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="contrasena" class="form-label">
                                <i class="bi bi-lock"></i> Contraseña
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" 
                                       id="contrasena" 
                                       name="contrasena" 
                                       class="form-control" 
                                       placeholder="Ingrese su contraseña"
                                       autocomplete="current-password"
                                       required>
                                <button type="button" class="btn btn-outline-secondary toggle-password" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="invalid-feedback">
                                    La contraseña debe tener al menos 8 caracteres.
                                </div>
                            </div>
                        </div>

                        <!-- Loading spinner -->
                        <div class="text-center mb-3 d-none" id="loadingSpinner">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-login w-100 mb-3" id="loginBtn">
                            <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                        </button>
                    </form>

                    <!-- Información del sistema -->
                    <div class="system-info text-center mt-4">
                        <small class="text-muted">
                            <i class="bi bi-shield-check"></i> 
                            Conexión segura • Tiempo de sesión: 10 minutos
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS - SIN integrity hash -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    
    <!-- JavaScript personalizado -->
    <script src="js/login.js"></script>
</body>
</html>