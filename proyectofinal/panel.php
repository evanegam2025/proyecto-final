<?php
// Incluir archivo de conexión
require_once 'conex_bd.php';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Función para obtener estadísticas
function obtenerEstadisticas($conn) {
    $stats = [];
    
    // Contar usuarios administradores totales
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM administrador");
        $stmt->execute();
        $stats['usuarios'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        $stats['usuarios'] = 0;
    }
    
    // Contar módulos totales
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM modulos");
        $stmt->execute();
        $stats['modulos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        $stats['modulos'] = 0;
    }
    
    // Contar permisos totales
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM permisos");
        $stmt->execute();
        $stats['permisos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        $stats['permisos'] = 0;
    }
    
    return $stats;
}

// Función para obtener módulos con sus permisos asignados
function obtenerModulosConPermisos($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT 
                m.nombre as modulo_nombre,
                m.id as modulo_id
            FROM modulos m
            INNER JOIN modulo_permisos mp ON m.nombre = mp.modulo
            ORDER BY m.nombre
        ");
        $stmt->execute();
        $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $modulosConPermisos = [];
        
        foreach ($modulos as $modulo) {
            // Obtener permisos asignados al módulo
            $stmt = $conn->prepare("
                SELECT 
                    p.id,
                    p.nombre,
                    p.descripcion,
                    mp.fecha_asignacion
                FROM permisos p
                INNER JOIN modulo_permisos mp ON p.id = mp.permiso_id
                WHERE mp.modulo = ?
                ORDER BY p.nombre
            ");
            $stmt->execute([$modulo['modulo_nombre']]);
            $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar administradores en este módulo
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM administrador WHERE modulo = ?");
            $stmt->execute([$modulo['modulo_nombre']]);
            $totalUsuarios = $stmt->fetchColumn();
            
            $modulosConPermisos[] = [
                'modulo_id' => $modulo['modulo_id'],
                'modulo_nombre' => $modulo['modulo_nombre'],
                'permisos' => $permisos,
                'total_permisos' => count($permisos),
                'total_usuarios' => $totalUsuarios
            ];
        }
        
        return $modulosConPermisos;
    } catch (Exception $e) {
        return [];
    }
}

// Función para obtener todos los permisos disponibles (para posible asignación)
function obtenerTodosLosPermisos($conn) {
    try {
        $stmt = $conn->prepare("SELECT id, nombre, descripcion FROM permisos ORDER BY nombre");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Obtener datos
$estadisticas = obtenerEstadisticas($conn);
$modulosConPermisos = obtenerModulosConPermisos($conn);
$todosLosPermisos = obtenerTodosLosPermisos($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Módulos y Permisos</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- CSS Personalizado -->
    <link href="css/panel-styles.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-corporate shadow-sm">
        <div class="container-fluid">
            <span class="navbar-brand fw-bold">
                <i class="bi bi-shield-check me-2"></i>
                Panel de Módulos y Permisos
            </span>
            <div class="d-flex gap-2">
                <a href="modulos.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-grid-3x3-gap"></i>
                    Módulos
                </a>
                <a href="permisos.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-shield-lock"></i>
                    Permisos
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm border-0 bg-gradient-corporate text-white">
                    <div class="card-body text-center py-4">
                        <h1 class="fw-bold mb-2 text-white-shadow">Sistema de Módulos y Permisos</h1>
                        <p class="mb-0 opacity-90">Gestión integral de módulos de administradores y sus permisos asignados</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="card border-0 shadow-sm h-100 stat-card-users">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="stat-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="text-end">
                                <div class="stat-number text-primary-strong"><?php echo $estadisticas['usuarios']; ?></div>
                            </div>
                        </div>
                        <h6 class="card-title-enhanced mb-2">Administradores</h6>
                        <p class="text-muted-readable mb-0 small">Usuarios administradores del sistema</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-md-6">
                <div class="card border-0 shadow-sm h-100 stat-card-profiles">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="stat-icon">
                                <i class="bi bi-grid-3x3-gap"></i>
                            </div>
                            <div class="text-end">
                                <div class="stat-number text-primary-strong"><?php echo $estadisticas['modulos']; ?></div>
                            </div>
                        </div>
                        <h6 class="card-title-enhanced mb-2">Módulos Disponibles</h6>
                        <p class="text-muted-readable mb-0 small">Módulos del sistema configurados</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-md-6">
                <div class="card border-0 shadow-sm h-100 stat-card-permissions">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="stat-icon">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                            <div class="text-end">
                                <div class="stat-number text-primary-strong"><?php echo $estadisticas['permisos']; ?></div>
                            </div>
                        </div>
                        <h6 class="card-title-enhanced mb-2">Permisos Totales</h6>
                        <p class="text-muted-readable mb-0 small">Control de acceso del sistema</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones de Control -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="expand-collapse-buttons">
                    <button class="btn btn-expand" id="expandAllBtn">
                        <i class="bi bi-arrows-expand me-2"></i>Expandir Todos los Módulos
                    </button>
                    <button class="btn btn-collapse" id="collapseAllBtn">
                        <i class="bi bi-arrows-collapse me-2"></i>Colapsar Todos los Módulos
                    </button>
                </div>
            </div>
        </div>

        <!-- Módulos y Permisos -->
        <div class="row">
            <div class="col-12" id="modulosContainer">
                <?php if (empty($modulosConPermisos)): ?>
                    <div class="alert alert-info shadow-sm" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>No hay módulos configurados.</strong> 
                        Configure los módulos y sus permisos desde la base de datos.
                    </div>
                <?php else: ?>
                    <?php foreach ($modulosConPermisos as $modulo): ?>
                        <div class="card shadow-sm border-0 mb-4 module-card">
                            <div class="card-header bg-corporate text-white module-header" 
                                 data-bs-toggle="collapse" 
                                 data-bs-target="#collapse-modulo-<?php echo $modulo['modulo_id']; ?>" 
                                 aria-expanded="false" 
                                 role="button">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-grid-3x3-gap me-3 fs-5"></i>
                                        <div>
                                            <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($modulo['modulo_nombre']); ?></h5>
                                            <small class="opacity-80">
                                                <?php echo $modulo['total_usuarios']; ?> administrador(es) asignado(s)
                                            </small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="mb-1">
                                            <small class="fw-bold"><?php echo $modulo['total_permisos']; ?> permisos asignados</small>
                                        </div>
                                        <div class="mb-1">
                                            <span class="badge bg-light text-dark">
                                                <?php echo ucfirst(strtolower($modulo['modulo_nombre'])); ?>
                                            </span>
                                        </div>
                                        <i class="bi bi-chevron-down toggle-icon"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="collapse" id="collapse-modulo-<?php echo $modulo['modulo_id']; ?>">
                                <div class="card-body p-4">
                                    <?php if (empty($modulo['permisos'])): ?>
                                        <div class="alert alert-warning" role="alert">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            <strong>No hay permisos asignados a este módulo</strong>
                                        </div>
                                    <?php else: ?>
                                        <div class="row mb-4">
                                            <div class="col-12">
                                                <h6 class="text-primary fw-bold mb-3">
                                                    <i class="bi bi-shield-check me-2"></i>
                                                    Permisos Asignados al Módulo
                                                </h6>
                                                
                                                <div class="row g-3">
                                                    <?php foreach ($modulo['permisos'] as $permiso): ?>
                                                        <div class="col-lg-6 col-xl-4">
                                                            <div class="card border-0 bg-light-corporate permiso-item p-3">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <div class="flex-grow-1">
                                                                        <div class="permiso-nombre mb-2">
                                                                            <i class="bi bi-check-circle text-success me-2"></i>
                                                                            <?php echo htmlspecialchars($permiso['nombre']); ?>
                                                                        </div>
                                                                        <div class="permiso-descripcion mb-2">
                                                                            <?php echo htmlspecialchars($permiso['descripcion'] ?: 'Sin descripción'); ?>
                                                                        </div>
                                                                        <small class="text-muted">
                                                                            <i class="bi bi-calendar me-1"></i>
                                                                            Asignado: <?php echo date('d/m/Y', strtotime($permiso['fecha_asignacion'])); ?>
                                                                        </small>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <span class="badge bg-success">Activo</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sección para gestionar permisos -->
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="card bg-light border-0">
                                                    <div class="card-header bg-transparent">
                                                        <h6 class="text-secondary fw-bold mb-0">
                                                            <i class="bi bi-gear me-2"></i>
                                                            Gestión de Permisos del Módulo
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-bold">Agregar Permiso al Módulo</label>
                                                                <select class="form-select" id="nuevoPermiso-<?php echo $modulo['modulo_id']; ?>">
                                                                    <option value="">Seleccionar permiso...</option>
                                                                    <?php 
                                                                    $permisosAsignados = array_column($modulo['permisos'], 'id');
                                                                    foreach ($todosLosPermisos as $permiso): 
                                                                        if (!in_array($permiso['id'], $permisosAsignados)):
                                                                    ?>
                                                                        <option value="<?php echo $permiso['id']; ?>">
                                                                            <?php echo htmlspecialchars($permiso['nombre']); ?>
                                                                        </option>
                                                                    <?php 
                                                                        endif;
                                                                    endforeach; 
                                                                    ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6 d-flex align-items-end">
                                                                <button type="button" 
                                                                        class="btn btn-primary me-2" 
                                                                        onclick="asignarPermiso('<?php echo $modulo['modulo_nombre']; ?>', <?php echo $modulo['modulo_id']; ?>)">
                                                                    <i class="bi bi-plus-circle me-1"></i>
                                                                    Asignar
                                                                </button>
                                                                <button type="button" 
                                                                        class="btn btn-outline-secondary"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#modalGestionPermisos"
                                                                        onclick="cargarModalGestion('<?php echo $modulo['modulo_nombre']; ?>', <?php echo $modulo['modulo_id']; ?>)">
                                                                    <i class="bi bi-gear me-1"></i>
                                                                    Gestionar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para Gestión Avanzada de Permisos -->
    <div class="modal fade" id="modalGestionPermisos" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-gear me-2"></i>
                        Gestión de Permisos - <span id="modalModuloNombre"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="modalContent">
                        <!-- Contenido cargado dinámicamente -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCambiosPermisos()">
                        <i class="bi bi-check-lg me-1"></i>
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;">
        <div id="messageToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-medium text-secondary-strong"></div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript personalizado -->
    <script src="js/panel.js"></script>
</body>
</html>