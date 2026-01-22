<?php
// permisos.php - Gestión de Permisos


require_once 'conex_bd.php';
require_once 'session_manager.php';

// Función para obtener permisos de la base de datos
function obtenerPermisos($conn) {
    try {
        $query = "SELECT id, nombre, fecha_creacion, fecha_modificacion FROM permisos ORDER BY nombre ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener permisos: " . $e->getMessage());
        return [];
    }
}

// Obtener conexión a la base de datos
try {
    $conn = getDBConnection();
    $permisos = obtenerPermisos($conn);
} catch (Exception $e) {
    error_log("Error de conexión: " . $e->getMessage());
    $permisos = [];
    $error_conexion = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos - Sistema de Usuarios</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/permisos-styles.css">
</head>
<body>
    <!-- Header -->
    <header class="main-header" role="banner">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-10">
                    <h1 class="display-4">
                        <i class="bi bi-shield-lock-fill me-3"></i>
                        Lista de Permisos
                    </h1>
                    <p class="lead">Administre los permisos del sistema de usuarios</p>
                </div>
                <div class="col-md-2 text-end">
                    <div class="btn-group" role="group">
                        <a href="administrador.php" class="btn btn-outline-light">
                            <i class="bi bi-person-badge"></i> Crear Usuario
                        </a>
                        <a href="modulos.php" class="btn btn-outline-light">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="container my-5" role="main">
        <!-- Alertas -->
        <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>

        <?php if (isset($error_conexion)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Error de conexión:</strong> No se pudo conectar a la base de datos. Por favor, verifique la configuración.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Card para crear permiso -->
        <div class="card shadow-lg mb-4 fade-in">
            <div class="card-header bg-success text-white">
                <h3 class="card-title mb-0">
                    <i class="bi bi-plus-circle me-2"></i>
                    Crear Nuevo Permiso
                </h3>
            </div>
            <div class="card-body p-4">
                <form id="formCrearPermiso" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-8">
                            <label for="nombrePermiso" class="form-label fw-bold">
                                <i class="bi bi-shield me-1"></i>
                                Nombre del Permiso <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="nombrePermiso" 
                                name="nombre"
                                placeholder="Ejemplo: crear_productos" 
                                required
                                minlength="3"
                                maxlength="50"
                                pattern="^[a-z_]+$"
                            >
                            <div class="invalid-feedback">
                                Use solo letras minúsculas y guiones bajos (3-50 caracteres).
                            </div>
                            <small class="text-muted">Solo letras minúsculas y guiones bajos</small>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-success btn-lg w-100" type="submit" id="btnCrearPermiso">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="spinnerCrear"></span>
                                <i class="bi bi-plus-circle me-2"></i>
                                Crear Permiso
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Card para lista de permisos -->
        <div class="card shadow-lg fade-in">
            <div class="card-header bg-info text-white">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-list me-2"></i>
                            Permisos Existentes
                        </h3>
                    </div>
                    <div class="col-auto">
    <div class="btn-group" role="group">
        <button class="btn btn-outline-light btn-sm" onclick="location.reload();" id="btnActualizarPermisos">
            <i class="bi bi-arrow-clockwise"></i> Actualizar
        </button>
        <button class="btn btn-outline-light btn-sm" id="btnTogglePermiso">
            <i class="bi bi-chevron-up"></i>
        </button>
    </div>
</div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th width="10%">
                                    <i class="bi bi-hash me-1"></i>ID
                                </th>
                                <th width="40%">
                                    <i class="bi bi-shield me-1"></i>Nombre
                                </th>
                                <th width="25%">
                                    <i class="bi bi-calendar me-1"></i>Fecha Creación
                                </th>
                                <th width="25%">
                                    <i class="bi bi-calendar me-1"></i>Última Modificación
                                </th>
                                <th width="15%" class="text-center">
                                    <i class="bi bi-gear me-1"></i>Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tablaPermisos">
                            <?php if (empty($permisos)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="bi bi-shield-exclamation fs-1 d-block mb-2"></i>
                                        <?php if (isset($error_conexion)): ?>
                                            Error de conexión a la base de datos
                                        <?php else: ?>
                                            No hay permisos registrados
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($permisos as $permiso): ?>
                                    <tr data-permiso-id="<?php echo $permiso['id']; ?>">
                                        <td class="fw-bold"><?php echo htmlspecialchars($permiso['id']); ?></td>
                                        <td>
                                            <i class="bi bi-shield text-success me-2"></i>
                                            <code class="text-primary"><?php echo htmlspecialchars($permiso['nombre']); ?></code>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($permiso['fecha_creacion'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($permiso['fecha_modificacion'])); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <button 
                                                    class="btn btn-warning btn-sm btn-editar" 
                                                    data-id="<?php echo $permiso['id']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($permiso['nombre']); ?>"
                                                    title="Editar permiso"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button 
                                                    class="btn btn-danger btn-sm btn-eliminar" 
                                                    data-id="<?php echo $permiso['id']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($permiso['nombre']); ?>"
                                                    title="Eliminar permiso"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($permisos)): ?>
                <div class="card-footer bg-light">
                    <div class="row text-center">
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="bi bi-pie-chart text-success me-2"></i>
                                <span class="fw-bold">Total de Permisos: <?php echo count($permisos); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal de confirmación de eliminación -->
   <div class="modal fade" id="modalConfirmarEliminar" tabindex="-1" aria-labelledby="modalConfirmarEliminarLabel">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalConfirmarEliminarLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="modalBodyEliminar">
                    <!-- Contenido dinámico -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="spinnerEliminar"></span>
                        <i class="bi bi-trash me-1"></i>Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de edición -->
    <div class="modal fade" id="modalEditarPermiso" tabindex="-1" aria-labelledby="modalEditarPermisoLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalEditarPermisoLabel">
                    <i class="bi bi-pencil-square me-2"></i>
                    Editar Permiso
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarPermiso" class="needs-validation" novalidate>
                    <input type="hidden" id="editarPermisoId" name="id">
                    <div class="mb-3">
                        <label for="editarNombrePermiso" class="form-label fw-bold">
                            Nombre del Permiso <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="editarNombrePermiso" 
                            name="nombre"
                            required
                            minlength="3"
                            maxlength="50"
                            pattern="^[a-z_]+$"
                        >
                        <div class="invalid-feedback">
                            Use solo letras minúsculas y guiones bajos (3-50 caracteres).
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </button>
                <button type="submit" form="formEditarPermiso" class="btn btn-warning">
                    <span class="spinner-border spinner-border-sm me-2 d-none" id="spinnerEditar"></span>
                    <i class="bi bi-save me-1"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript personalizado -->
    <script src="js/permisos.js"></script>
</body>
</html>