<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Dashboard - Sistema de Gestión</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="css/dashboard-styles.css">
    
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-container">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-3 mb-0">Cargando datos del dashboard...</p>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="header-section fade-in">
            <div class="user-info">
                <div class="user-welcome">
                    <h4><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
                    <p class="user-role mb-0">
                        <i class="bi bi-person-circle me-1"></i>
                        <span id="userName"></span> - <span id="userRole"></span>
                    </p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-outline-primary btn-sm" onclick="actualizarDashboard()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.location.href='modulos.php'">
                        <i class="bi bi-house me-1"></i>Módulos
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="cerrarSesion()">
                        <i class="bi bi-box-arrow-right me-1"></i>Salir
                    </button>
                </div>
            </div>
        </div>

        <div class="filter-section fade-in">
            <div class="row align-items-end g-3">
                <div class="col-md-4 col-sm-6">
                    <label class="form-label fw-semibold mb-2">Período:</label>
                    <select class="form-select" id="periodoFilter" onchange="actualizarDashboard()">
                        <option value="mes_actual">Mes Actual</option>
                        <option value="mes_anterior">Mes Anterior</option>
                        <option value="ultimos_30">Últimos 30 días</option>
                        <option value="ultimos_90">Últimos 90 días</option>
                        <option value="anio_actual">Año Actual</option>
                    </select>
                </div>
                <div class="col-md-8 col-sm-6 text-end">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        <span id="periodoInfo">Mostrando datos actuales</span>
                    </small>
                </div>
            </div>
        </div>

        <div id="statsContainer" class="row g-3"></div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="chart-container fade-in">
                    <div class="chart-header">
                        <h5 class="chart-title mb-0">Estados por Módulo</h5>
                        <button class="toggle-btn" onclick="toggleChart('estadosChartCanvas')">
                            <i class="bi bi-chevron-up"></i>
                        </button>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="estadosChartCanvas"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="chart-container fade-in">
                    <div class="chart-header">
                        <h5 class="chart-title mb-0">Distribución por Tecnología</h5>
                        <button class="toggle-btn" onclick="toggleChart('tecnologiasChartCanvas')">
                            <i class="bi bi-chevron-up"></i>
                        </button>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="tecnologiasChartCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12">
                <div class="chart-container fade-in">
                    <div class="chart-header">
                        <h5 class="chart-title mb-0">Ventas por Plan</h5>
                        <button class="toggle-btn" onclick="toggleChart('planesChartCanvas')">
                            <i class="bi bi-chevron-up"></i>
                        </button>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="planesChartCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="js/dashboard.js"></script>
</body>
</html>