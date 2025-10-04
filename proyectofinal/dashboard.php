<?php
session_start();
require_once 'conex_bd.php';

// Verificar si el usuario está logueado (opcional - ajustar según tu sistema de autenticación)
/*
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
*/

try {
    $conn = getDBConnection();
    
    // Obtener datos iniciales para el dashboard
    $current_month = date('Y-m');
    $current_year = date('Y');
    
} catch (Exception $e) {
    error_log("Error en dashboard: " . $e->getMessage());
    $error_message = "Error al cargar el dashboard";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Ventas Conekto</title>
    <link rel="stylesheet" href="css/dashboard-styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <div class="header-left">
                    <h1><i class="fas fa-tachometer-alt"></i> Dashboard Ventas</h1>
                    <span class="subtitle">Sistema de Ventas Conekto</span>
                </div>
                <header class="navbar navbar-expand-lg navbar-dark bg-gradient-primary shadow-sm sticky-top">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <button id="export-btn" class="btn btn-secondary" onclick="exportData()">
                        <i class="fas fa-download"></i> Exportar Dashboard
                    </button>
                <!-- Botón de volver -->
                <a href="modulos.php" class="btn btn-back btn-sm me-3" title="Volver a Módulos">
                    <i class="fas fa-arrow-left me-1"></i>
                    <span class="d-none d-sm-inline">Modulos</span>
                </a>
                <div class="header-actions">
                    <button id="refresh-btn" class="btn btn-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                    
                </div>
            </div>
        </header>

        <!-- Filtros -->
        <section class="filters-section">
            <div class="filters-container">
                <div class="filter-group">
                    <label for="date-filter"><i class="fas fa-calendar"></i> Período:</label>
                    <select id="date-filter" class="filter-select" onchange="toggleCustomDateRange()">
                        <option value="current_month">Mes Actual</option>
                        <option value="last_month">Mes Anterior</option>
                        <option value="current_year">Año Actual</option>
                        <option value="last_year">Año Anterior</option>
                        <option value="custom">Rango Personalizado</option>
                        <option value="all">Histórico Completo</option>
                    </select>
                </div>
                
                <div class="filter-group custom-date-range" id="custom-date-range" style="display: none;">
                    <label for="start-date">Desde:</label>
                    <input type="date" id="start-date" class="filter-input" value="<?php echo date('Y-m-01'); ?>">
                    
                    <label for="end-date">Hasta:</label>
                    <input type="date" id="end-date" class="filter-input" value="<?php echo date('Y-m-t'); ?>">
                </div>

                <div class="filter-group">
                    <label for="tecnologia-filter"><i class="fas fa-wifi"></i> Tecnología:</label>
                    <select id="tecnologia-filter" class="filter-select">
                        <option value="all">Todas</option>
                        <option value="Fibra Óptica">Fibra Óptica</option>
                        <option value="Radio Enlace">Radio Enlace</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="municipio-filter"><i class="fas fa-map-marker-alt"></i> Municipio:</label>
                    <select id="municipio-filter" class="filter-select">
                        <option value="all">Todos</option>
                        <!-- Se cargarán dinámicamente -->
                    </select>
                </div>

                <button id="apply-filters" class="btn btn-primary" onclick="applyFilters()">
                    <i class="fas fa-filter"></i> Aplicar Filtros
                </button>
                
                <button id="clear-filters" class="btn btn-outline" onclick="clearFilters()">
                    <i class="fas fa-eraser"></i> Limpiar
                </button>
            </div>
        </section>

        <!-- Loading Spinner -->
        <div id="loading-spinner" class="loading-spinner" style="display: none;">
            <div class="spinner"></div>
            <p>Cargando datos...</p>
        </div>

        <!-- Mensaje de Error -->
        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo escapeHtml($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Resumen General -->
        <section class="summary-section">
            <div class="summary-card">
                <div class="summary-item">
                    <div class="summary-icon ventas">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="summary-info">
                        <div class="summary-value" id="summary-total-ventas">0</div>
                        <div class="summary-label">Total Ventas</div>
                    </div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-icon agendamiento">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="summary-info">
                        <div class="summary-value" id="summary-agendadas">0</div>
                        <div class="summary-label">Agendadas</div>
                    </div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-icon aprovisionamiento">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="summary-info">
                        <div class="summary-value" id="summary-cumplidas">0</div>
                        <div class="summary-label">Cumplidas</div>
                    </div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-icon efectividad">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="summary-info">
                        <div class="summary-value" id="summary-efectividad">0%</div>
                        <div class="summary-label">Efectividad</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tarjetas del Dashboard -->
        <section class="cards-section">
            <div class="cards-grid">
                
                <!-- Tarjeta Total Ventas -->
                <div class="dashboard-card" id="total-sales-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-shopping-cart"></i>
                            Total de Ventas
                        </div>
                        <button class="card-toggle" onclick="toggleCard('total-sales-card')" data-visible="true">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="card-content">
                        <div class="metric-container">
                            <div class="metric-value" id="total-sales-value">
                                <div class="loading-skeleton"></div>
                            </div>
                            <div class="metric-label">Ventas Registradas</div>
                        </div>
                        <div class="metric-details">
                            <div class="detail-item">
                                <span class="detail-label">Este mes:</span>
                                <span class="detail-value" id="sales-this-month">0</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Mes anterior:</span>
                                <span class="detail-value" id="sales-last-month">0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta Estados de Ventas -->
                <div class="dashboard-card" id="sales-status-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-list-check"></i>
                            Estados de Ventas
                        </div>
                        <button class="card-toggle" onclick="toggleCard('sales-status-card')" data-visible="true">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="card-content">
                        <div class="status-grid" id="status-grid">
                            <div class="loading-skeleton"></div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta Agendamiento -->
                <div class="dashboard-card" id="scheduling-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-calendar-alt"></i>
                            Agendamiento
                        </div>
                        <button class="card-toggle" onclick="toggleCard('scheduling-card')" data-visible="true">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="card-content">
                        <div class="scheduling-stats" id="scheduling-stats">
                            <div class="loading-skeleton"></div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta Aprovisionamiento -->
                <div class="dashboard-card" id="provisioning-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-cogs"></i>
                            Aprovisionamiento
                        </div>
                        <button class="card-toggle" onclick="toggleCard('provisioning-card')" data-visible="true">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="card-content">
                        <div class="provisioning-stats" id="provisioning-stats">
                            <div class="loading-skeleton"></div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta Efectividad Mensual -->
                <div class="dashboard-card" id="effectiveness-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-chart-line"></i>
                            Efectividad Mensual
                        </div>
                        <button class="card-toggle" onclick="toggleCard('effectiveness-card')" data-visible="true">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="card-content">
                        <div class="effectiveness-container">
                            <div class="effectiveness-meter">
                                <div class="meter-circle">
                                    <div class="meter-fill" id="effectiveness-fill"></div>
                                    <div class="meter-center">
                                        <span class="meter-percentage" id="effectiveness-percentage">0%</span>
                                        <span class="meter-label">Efectividad</span>
                                    </div>
                                </div>
                            </div>
                            <div class="effectiveness-details" id="effectiveness-details">
                                <div class="loading-skeleton"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta Tecnologías -->
                <div class="dashboard-card" id="technology-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-wifi"></i>
                            Distribución por Tecnología
                        </div>
                        <button class="card-toggle" onclick="toggleCard('technology-card')" data-visible="true">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="card-content">
                        <div class="technology-stats" id="technology-stats">
                            <div class="loading-skeleton"></div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Modal de Configuración -->
        <div id="settings-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-cog"></i> Configuración del Dashboard</h3>
                    <button class="modal-close" onclick="closeSettingsModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="setting-group">
                        <label>Auto-actualización:</label>
                        <select id="auto-refresh" onchange="setAutoRefresh()">
                            <option value="0">Desactivado</option>
                            <option value="30">30 segundos</option>
                            <option value="60">1 minuto</option>
                            <option value="300" selected>5 minutos</option>
                            <option value="600">10 minutos</option>
                        </select>
                    </div>
                    <div class="setting-group">
                        <label>Meta de efectividad:</label>
                        <input type="number" id="effectiveness-goal" value="90" min="1" max="100" onchange="setEffectivenessGoal()">
                        <span>%</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="dashboard-footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Gestión Conecto. Todos los derechos reservados.</p>
            <p>Última actualización: <span id="last-update">--</span></p>
        </div>
    </footer>

    <script src="js/dashboard.js"></script>
    <script>
        // Inicializar dashboard al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            initDashboard();
        });
    </script>
</body>
</html>