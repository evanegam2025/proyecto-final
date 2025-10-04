<?php
// ======================================================================
// SISTEMA DE CONSULTA DE CÉDULAS - VISTA PRINCIPAL
// ======================================================================

// Configuración de errores y codificación
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
header('Content-Type: text/html; charset=UTF-8');

// Incluir archivo de conexión
require_once 'conex_bd.php';

// Verificar conexión a la base de datos
if (!testDBConnection()) {
    die('<div class="alert alert-danger">Error: No se puede conectar a la base de datos.</div>');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de Ventas e Instalaciones - Consulta de Cédulas">
    <meta name="author" content="SVI System">
    
    <title>Sistema de Consulta SVI</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="css/consultas-styles.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="imagenes/favicon.ico">
</head>

<body class="bg-gradient-custom">
    <!-- Loading Screen -->
<div id="loading-screen" class="loading-screen">
    <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <div class="loading-text mt-3">Cargando Sistema SVI...</div>
    </div>
</div>

    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-glass sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="#">
                <i class="bi bi-gear-fill me-2"></i>
                SVI System
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="ms-auto">
                    <a href="modulos.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-left-circle me-1"></i>
                        Volver al Inicio
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Header Principal -->
        <div class="text-center mb-5">
            <div class="logo-container mb-4">
                <img src="imagenes/logo1.JPG" alt="Logo Conecto" class="logo-img">
            </div>
            <h1 class="main-title">
                <i class="bi bi-search me-2"></i>
                Sistema de Ventas e Instalaciones
            </h1>
            <p class="subtitle">Consulta Completa de Cédulas</p>
        </div>

        <!-- Formulario de búsqueda -->
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="search-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-person-search me-2"></i>
                            Consulta de Cliente
                        </h5>
                    </div>
                    
                    <div class="card-body">
                        <form id="form-consulta" novalidate>
                            <div class="mb-4">
                                <label for="cedula" class="form-label fw-semibold">
                                    <i class="bi bi-card-text me-1"></i>
                                    Número de Cédula del Cliente
                                </label>
                                
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-hash text-muted"></i>
                                    </span>
                                    <input 
                                        type="text" 
                                        id="cedula" 
                                        name="cedula"
                                        class="form-control" 
                                        placeholder="Ej: 12345678" 
                                        required
                                        pattern="[0-9\-]+"
                                        title="Solo números y guiones permitidos"
                                        autocomplete="off"
                                        maxlength="20"
                                    >
                                    <button 
                                        type="button" 
                                        id="btn-limpiar-input" 
                                        class="btn btn-outline-secondary" 
                                        title="Limpiar campo"
                                        tabindex="-1"
                                    >
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    El sistema verificará el estado del cliente en todas las tablas
                                </div>
                                
                                <div class="invalid-feedback" id="cedula-error">
                                    Por favor ingrese una cédula válida
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg btn-search">
                                    <span class="spinner-border spinner-border-sm d-none me-2" id="loading-spinner"></span>
                                    <i class="bi bi-search me-2" id="search-icon"></i>
                                    <span id="btn-text">Consultar Estado</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div id="resultado" class="mt-4"></div>

        <!-- Botón para nueva consulta -->
        <div id="nueva-consulta-container" class="mt-4 d-none">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8 col-lg-6">
                    <div class="d-grid">
                        <button id="btn-nueva-consulta" class="btn btn-success btn-lg btn-new-search">
                            <i class="bi bi-arrow-clockwise me-2"></i>
                            Nueva Consulta
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formularios dinámicos -->
        <div id="formularios-dinamicos" class="mt-4"></div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="toast-notification" class="toast" role="alert">
            <div class="toast-header">
                <i class="bi bi-bell-fill me-2 text-primary"></i>
                <strong class="me-auto">Notificación</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body" id="toast-message">
                <!-- Mensaje dinámico -->
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer-custom mt-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0 text-muted">
                        <i class="bi bi-shield-check me-1"></i>
                        © <?php echo date('Y'); ?> Sistema SVI - Todos los derechos reservados
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript Personalizado -->
    <script src="js/consultas.js"></script>
    
    <script>
        // Ocultar loading screen cuando la página esté lista
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.getElementById('loading-screen').style.display = 'none';
            }, 500);
        });
    </script>
</body>
</html>