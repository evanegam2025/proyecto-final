<?php
// Iniciar sesión y configurar codificación
session_start();

// Configurar codificación UTF-8 para la salida
header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');




// Verificar si el usuario está autenticado - VARIABLES CONSISTENTES
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    // Redirigir al inicio si no hay sesión activa
    header('Location: index.php');
    exit();
}

// Verificar timeout de sesión
if (isset($_SESSION['last_activity']) && isset($_SESSION['timeout_duration'])) {
    $tiempoInactivo = time() - $_SESSION['last_activity'];
    if ($tiempoInactivo > $_SESSION['timeout_duration']) {
        // Sesión expirada, redirigir al login
        session_destroy();
        header('Location: index.php?mensaje=sesion_expirada');
        exit();
    }
    // Actualizar último tiempo de actividad
    $_SESSION['last_activity'] = time();
}

// Obtener información del usuario de la sesión
$usuario_nombre = htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8');
$usuario_rol = isset($_SESSION['user_role']) ? htmlspecialchars($_SESSION['user_role'], ENT_QUOTES, 'UTF-8') : 'Usuario';
$usuario_id = $_SESSION['user_id'];

// Configurar zona horaria de Colombia
date_default_timezone_set('America/Bogota');

// Obtener fecha y hora actual de ingreso
$fecha_ingreso = date('d/m/Y');
$hora_ingreso = date('h:i:s A');
$fecha_completa = date('l, d \d\e F \d\e Y');

// Traducir día de la semana al español
$dias_semana = [
    'Monday' => 'Lunes',
    'Tuesday' => 'Martes', 
    'Wednesday' => 'Miércoles',
    'Thursday' => 'Jueves',
    'Friday' => 'Viernes',
    'Saturday' => 'Sábado',
    'Sunday' => 'Domingo'
];

$meses = [
    'January' => 'Enero',
    'February' => 'Febrero',
    'March' => 'Marzo',
    'April' => 'Abril',
    'May' => 'Mayo',
    'June' => 'Junio',
    'July' => 'Julio',
    'August' => 'Agosto',
    'September' => 'Septiembre',
    'October' => 'Octubre',
    'November' => 'Noviembre',
    'December' => 'Diciembre'
];

$dia_actual = date('l');
$mes_actual = date('F');
$fecha_formateada = str_replace(
    [$dia_actual, $mes_actual], 
    [$dias_semana[$dia_actual], $meses[$mes_actual]], 
    $fecha_completa
);

// Configuración del sistema
$config = [
    'nombre_sistema' => 'SISTEMA DE VENTAS E INSTALACIONES',
    'subtitulo' => 'Internet para Todos',
    'version' => '2.0.'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Sistema de Ventas e Instalaciones - Internet para Todos">
    <meta name="author" content="Tu Empresa">
    
    <title><?php echo $config['nombre_sistema']; ?></title>

    <!-- Preconnect para mejorar rendimiento -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Bootstrap CSS - SIN integrity hash para evitar errores SRI -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- SweetAlert2 para confirmaciones -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Estilos personalizados -->
    <link href="css/stylos_modulos.css" rel="stylesheet">
</head>

<body>
    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando módulos...</span>
        </div>
        <p class="mt-2 text-muted">Cargando permisos y módulos...</p>
    </div>

    <!-- Contenedor principal -->
    <div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center">
        <div class="main-container fade-in">
            
            <!-- Barra superior con información del usuario -->
            <div class="user-info-bar d-flex justify-content-between align-items-center mb-4">
                <div class="user-welcome">
                    <i class="bi bi-person-circle me-2"></i>
                    <span class="user-greeting">Bienvenido, <strong><?php echo $usuario_nombre; ?></strong></span>
                    <small class="user-role d-block">
                        <i class="bi bi-shield-check me-1"></i>
                        <?php echo $usuario_rol; ?>
                    </small>
                </div>
                <div class="user-actions d-flex align-items-center gap-3">
                    <div class="datetime-info text-end">
                        <div class="current-datetime">
                            <i class="bi bi-calendar-event me-1"></i>
                            <span class="date-text"><?php echo $fecha_ingreso; ?></span>
                        </div>
                        <div class="current-time">
                            <i class="bi bi-clock me-1"></i>
                            <span class="time-text" id="currentTime"><?php echo $hora_ingreso; ?></span>
                        </div>
                        <small class="full-date text-muted"><?php echo $fecha_formateada; ?></small>
                    </div>
                    <div class="user-actions-buttons">
                        <button type="button" class="btn btn-outline-info btn-sm me-2" id="refreshModulesBtn" title="Actualizar módulos">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="logoutBtn" title="Cerrar Sesión">
                            <i class="bi bi-box-arrow-right me-1"></i>
                            Cerrar Sesión
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Header con logo y títulos -->
            <header class="text-center mb-4">
                <div class="logo-container mb-3">
                    <img src="imagenes/logo1.JPG" 
                         alt="Logo <?php echo $config['nombre_sistema']; ?>" 
                         class="logo-img img-fluid"
                         loading="lazy"
                         onerror="this.style.display='none'">
                </div>

                <h1 class="system-title mb-2">
                    <?php echo $config['nombre_sistema']; ?>
                </h1>
                
                <h2 class="system-subtitle mb-2">
                    <?php echo $config['subtitulo']; ?>
                </h2>
                
                <small class="text-muted version-info">
                    Versión <?php echo $config['version']; ?>
                </small>
            </header>

            <!-- Sección de módulos -->
            <main>
                <h3 class="modules-title text-center mb-4">
                    <i class="bi bi-grid-3x3-gap me-2"></i>
                    MÓDULOS DEL SISTEMA
                </h3>

                <!-- Alert para mensajes del sistema -->
                <div id="systemAlert" class="alert alert-info alert-dismissible fade" role="alert" style="display: none;">
                    <div id="systemAlertContent"></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <div class="row justify-content-center">
                    <div class="col-12 col-lg-10">
                        <!-- Contenedor de módulos que será llenado por JavaScript -->
                        <div class="modules-grid">
                            <!-- Los módulos se cargarán dinámicamente aquí -->
                        </div>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="text-center mt-5 pt-3">
                <small class="text-muted">
                    © <?php echo date('Y'); ?> - Sistema de Ventas e Instalaciones
                    <br>
                    <i class="bi bi-shield-check me-1"></i>
                    Sesión segura - Usuario ID: <?php echo $usuario_id; ?>
                </small>
            </footer>
        </div>
    </div>

    <!-- Bootstrap JS Bundle - SIN integrity hash -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

    <!-- Script del manager de módulos -->
    <script src="js/modulos.js"></script>

    <!-- Scripts personalizados -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Funcionalidad del botón de cerrar sesión
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: '¿Cerrar Sesión?',
                        text: '¿Estás seguro que deseas salir del sistema?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, cerrar sesión',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Mostrar loading antes de salir
                            Swal.fire({
                                title: 'Cerrando sesión...',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            
                            setTimeout(() => {
                                window.location.href = 'logout.php';
                            }, 1000);
                        }
                    });
                });
            }

            // Funcionalidad del botón de actualizar módulos
            const refreshModulesBtn = document.getElementById('refreshModulesBtn');
            if (refreshModulesBtn) {
                refreshModulesBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    
                    // Deshabilitar botón mientras se actualiza
                    this.disabled = true;
                    this.innerHTML = '<i class="bi bi-arrow-clockwise spinner-border spinner-border-sm"></i>';
                    
                    try {
                        await window.ModulosManager.recargarModulos();
                        
                        // Mostrar mensaje de éxito
                        mostrarAlertaSistema('Módulos actualizados correctamente', 'success');
                        
                    } catch (error) {
                        console.error('Error al actualizar módulos:', error);
                        mostrarAlertaSistema('Error al actualizar módulos. Intenta nuevamente.', 'danger');
                    } finally {
                        // Restaurar botón
                        setTimeout(() => {
                            this.disabled = false;
                            this.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
                        }, 1000);
                    }
                });
            }

            // Actualizar reloj en tiempo real
            function updateClock() {
                const now = new Date();
                const timeOptions = {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true,
                    timeZone: 'America/Bogota'
                };
                const currentTimeElement = document.getElementById('currentTime');
                if (currentTimeElement) {
                    currentTimeElement.textContent = now.toLocaleTimeString('es-CO', timeOptions);
                }
            }

            // Actualizar el reloj cada segundo
            updateClock();
            setInterval(updateClock, 1000);
        });

        /**
         * Mostrar alerta del sistema
         */
        function mostrarAlertaSistema(mensaje, tipo = 'info', duracion = 5000) {
            const alertElement = document.getElementById('systemAlert');
            const alertContent = document.getElementById('systemAlertContent');
            
            if (!alertElement || !alertContent) return;
            
            // Configurar el tipo de alerta
            alertElement.className = `alert alert-${tipo} alert-dismissible fade show`;
            alertContent.innerHTML = `
                <i class="bi bi-${tipo === 'success' ? 'check-circle' : tipo === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${mensaje}
            `;
            
            // Mostrar alerta
            alertElement.style.display = 'block';
            
            // Auto-ocultar después del tiempo especificado
            if (duracion > 0) {
                setTimeout(() => {
                    if (alertElement.classList.contains('show')) {
                        const closeButton = alertElement.querySelector('.btn-close');
                        if (closeButton) closeButton.click();
                    }
                }, duracion);
            }
        }

        /**
         * Manejar errores de la aplicación
         */
        window.addEventListener('error', function(e) {
            console.error('Error en la aplicación:', e.error);
        });

        /**
         * Manejar errores de promesas no capturadas
         */
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Promise rechazada:', e.reason);
        });
    </script>
</body>
</html>