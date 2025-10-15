<?php
require_once 'api/api_registroagenda.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Agendamiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/registroagenda-styles.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
            <div class="container">
                <a class="navbar-brand" href="#">
                    <i class="bi bi-calendar-check me-2"></i>Sistema de Agendamiento
                </a>
                <div class="d-flex gap-2">
                    <span class="navbar-text me-3">
                        Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?>
                    </span>
                    <a href="modulos.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver a Módulos</a>
                    <a href="?logout=1" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Cerrar Sesión
                    </a>
                </div>
            </div>
        </nav>

        <div class="container">
            <div class="d-flex justify-content-start mb-3">
                <a href="descargar_bd.php" class="btn btn-outline-danger">
                    <i class="bi bi-download"></i> Descargar BD
                </a>
            </div>

            <!-- Perfil -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="profile-section text-center">
                        <img src="imagenes/rostro.JPG" alt="Foto" class="profile-img mb-3">
                        <h4><strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></h4>
                        <p><strong><?php echo htmlspecialchars($modulo); ?></strong></p>
                    </div>
                </div>
            </div>

            <!-- Mensajes de Alerta -->
            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Sección de búsqueda -->
            <div class="search-section">
                <h4 class="mb-3"><i class="bi bi-search me-2"></i>Consultar Cliente por Cédula</h4>
                <form method="POST" action="" class="row g-3" id="formConsulta">
                    <div class="col-md-6">
                        <label for="cedula_consulta" class="form-label">Número de Cédula</label>
                        <input type="number" class="form-control" id="cedula_consulta" name="cedula_consulta" 
                               placeholder="Ingrese la cédula a consultar" required 
                               value="<?php echo (!$venta_encontrada && !$agendamiento_encontrado && isset($_POST['cedula_consulta'])) ? htmlspecialchars($_POST['cedula_consulta']) : ''; ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" name="consultar_cedula" class="btn btn-custom w-100">
                            <i class="bi bi-search me-1"></i>Consultar
                        </button>
                    </div>
                </form>
            </div>

            <!-- SECCIÓN DE RESULTADOS -->
            <?php if ($venta_encontrada || $agendamiento_encontrado): ?>
                
                <!-- CASO 1: Solo está en VENTAS -->
                <?php if ($venta_encontrada && !$agendamiento_encontrado): ?>
                    <div class="alert alert-info">
                        <h5><i class="bi bi-person-check me-2"></i>Solo Venta Encontrada</h5>
                        <p class="mb-0"><strong>Nombre:</strong> <?php echo htmlspecialchars($venta_encontrada['nombre']); ?></p>
                        <p class="mb-0"><strong>Cédula:</strong> <?php echo htmlspecialchars($venta_encontrada['cedula']); ?></p>
                        <p class="mb-0"><strong>Estado:</strong> <span class="badge bg-primary status-badge">VENTA REGISTRADA</span></p>
                    </div>

                    <!-- Opciones: Mostrar Detalles -->
                    <div class="form-section">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="mostrar_detalles" onchange="toggleDetalles()">
                                <label class="form-check-label fw-bold" for="mostrar_detalles">
                                    <i class="bi bi-eye me-1"></i> Mostrar Detalles de la Venta
                                </label>
                            </div>
                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editVentaModal">
                                <i class="bi bi-pencil-square me-1"></i> Editar Venta
                            </button>
                        </div>
                        <div id="detalles_venta" class="mt-3" style="display: none;">
                            <div class="list-group mb-3">
                                <?php foreach ($venta_encontrada as $key => $value): ?>
                                <div class="list-group-item">
                                    <strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?>:</strong> 
                                    <span><?php echo htmlspecialchars($value ?? ''); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de agendamiento (CREATE) -->
                    <div class="form-section">
                        <h3 class="mb-4"><i class="bi bi-calendar-plus me-2"></i>Crear Nuevo Agendamiento</h3>
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="cedula_cliente" value="<?php echo htmlspecialchars($venta_encontrada['cedula']); ?>">
                            <div class="col-12">
                                <label class="form-label fw-bold">Cliente a Agendar</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($venta_encontrada['nombre']) . ' - C.C. ' . htmlspecialchars($venta_encontrada['cedula']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="fecha_visita" class="form-label fw-bold">Fecha de la Visita</label>
                                <input type="date" class="form-control" id="fecha_visita" name="fecha_visita">
                            </div>
                            <div class="col-md-6">
                                <label for="franja_visita" class="form-label fw-bold">Franja de Visita</label>
                                <select class="form-select" id="franja_visita" name="franja_visita">
                                    <option value="" disabled selected>Seleccione una franja</option>
                                    <option value="AM">AM (Mañana)</option>
                                    <option value="PM">PM (Tarde)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="tecnico_asignado" class="form-label fw-bold">Técnico Asignado</label>
                                <select class="form-select" id="tecnico_asignado" name="tecnico_asignado">
                                    <option value="" disabled selected>Seleccione un técnico</option>
                                    <option value="Juan Diego Jaramillo">Juan Diego Jaramillo</option>
                                    <option value="Juan Sebastian Fierro">Juan Sebastian Fierro</option>
                                    <option value="Julian Cano">Julian Cano</option>
                                    <option value="Julian Cerquera">Julian Cerquera</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="estado_visita" class="form-label fw-bold">Estado de la Visita *</label>
                                <select class="form-select" id="estado_visita" name="estado_visita" required>
                                    <option value="NO Asignado">NO Asignado</option>
                                    <option value="AGENDADO">AGENDADO</option>
                                    <option value="CANCELADO">CANCELADO</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="notas" class="form-label fw-bold">Notas del Agendamiento *</label>
                                <textarea class="form-control" id="notas" name="notas" rows="3" 
                                          placeholder="Información adicional..." required></textarea>
                            </div>
                            <div class="col-12 mt-4 text-end">
                                <button type="submit" name="guardar_agendamiento" class="btn btn-custom btn-lg">
                                    <i class="bi bi-save me-1"></i> Guardar Agendamiento
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- CASO 2: Está en AGENDAMIENTO (con o sin venta) -->
                <?php if ($agendamiento_encontrado): ?>
                    <div class="alert alert-success">
                        <h5><i class="bi bi-calendar-check me-2"></i>Agendamiento Encontrado</h5>
                        <?php if ($venta_encontrada): ?>
                            <p class="mb-0"><strong>Nombre:</strong> <?php echo htmlspecialchars($venta_encontrada['nombre']); ?></p>
                            <p class="mb-0"><strong>Cédula:</strong> <?php echo htmlspecialchars($venta_encontrada['cedula']); ?></p>
                        <?php else: ?>
                            <p class="mb-0"><strong>Cédula:</strong> <?php echo htmlspecialchars($agendamiento_encontrado['cedula_cliente']); ?></p>
                        <?php endif; ?>
                        <p class="mb-0"><strong>Estado:</strong> 
                            <span class="badge bg-success status-badge"><?php echo htmlspecialchars($agendamiento_encontrado['estado_visita']); ?></span>
                        </p>
                        <p class="mb-0"><strong>Fecha Agendada:</strong> <?php echo htmlspecialchars($agendamiento_encontrado['fecha_visita']); ?></p>
                        <p class="mb-0"><strong>Técnico:</strong> <?php echo htmlspecialchars($agendamiento_encontrado['tecnico_asignado']); ?></p>
                    </div>

                    <!-- Botones de acción para agendamiento -->
                    <div class="form-section">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4>Acciones de Agendamiento</h4>
                            <div>
                                <button type="button" class="btn btn-warning btn-sm me-2" data-bs-toggle="modal" data-bs-target="#editAgendamientoModal">
                                    <i class="bi bi-pencil-square me-1"></i> Editar Agendamiento
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteAgendamientoModal">
                                    <i class="bi bi-trash me-1"></i> Cancelar Agendamiento
                                </button>
                            </div>
                        </div>
                        
                        <!-- Detalles del agendamiento -->
                        <div class="mt-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="mostrar_detalles_agenda" onchange="toggleDetallesAgenda()">
                                <label class="form-check-label fw-bold" for="mostrar_detalles_agenda">
                                    <i class="bi bi-eye me-1"></i> Mostrar Detalles del Agendamiento
                                </label>
                            </div>
                            <div id="detalles_agendamiento" class="mt-3" style="display: none;">
                                <div class="list-group mb-3">
                                    <?php foreach ($agendamiento_encontrado as $key => $value): ?>
                                    <div class="list-group-item">
                                        <strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?>:</strong> 
                                        <span><?php echo htmlspecialchars($value ?? ''); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Script para limpiar el campo de cédula -->
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const cedulaInput = document.getElementById('cedula_consulta');
                            if (cedulaInput) {
                                cedulaInput.value = '';
                                cedulaInput.placeholder = 'Ingrese una nueva cédula para consultar';
                            }
                        });
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL PARA EDITAR AGENDAMIENTO -->
    <?php if ($agendamiento_encontrado): ?>
    <div class="modal fade" id="editAgendamientoModal" tabindex="-1" aria-labelledby="editAgendamientoModalLabel">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAgendamientoModalLabel">Editar Agendamiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="id_agendamiento" value="<?php echo htmlspecialchars($agendamiento_encontrado['id']); ?>">
                        <input type="hidden" name="cedula_original" value="<?php echo htmlspecialchars($agendamiento_encontrado['cedula_cliente']); ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fecha_visita_edit" class="form-label">Fecha de Visita</label>
                                <input type="date" class="form-control" id="fecha_visita_edit" name="fecha_visita_edit" 
                                       value="<?php echo htmlspecialchars($agendamiento_encontrado['fecha_visita']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="franja_visita_edit" class="form-label">Franja de Visita</label>
                                <select class="form-select" id="franja_visita_edit" name="franja_visita_edit">
                                    <option value="AM" <?php echo ($agendamiento_encontrado['franja_visita'] == 'AM') ? 'selected' : ''; ?>>AM (Mañana)</option>
                                    <option value="PM" <?php echo ($agendamiento_encontrado['franja_visita'] == 'PM') ? 'selected' : ''; ?>>PM (Tarde)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tecnico_asignado_edit" class="form-label">Técnico Asignado</label>
                                <select class="form-select" id="tecnico_asignado_edit" name="tecnico_asignado_edit">
                                    <option value="Juan Diego Jaramillo" <?php echo ($agendamiento_encontrado['tecnico_asignado'] == 'Juan Diego Jaramillo') ? 'selected' : ''; ?>>Juan Diego Jaramillo</option>
                                    <option value="Juan Sebastian Fierro" <?php echo ($agendamiento_encontrado['tecnico_asignado'] == 'Juan Sebastian Fierro') ? 'selected' : ''; ?>>Juan Sebastian Fierro</option>
                                    <option value="Julian Cano" <?php echo ($agendamiento_encontrado['tecnico_asignado'] == 'Julian Cano') ? 'selected' : ''; ?>>Julian Cano</option>
                                    <option value="Julian Cerquera" <?php echo ($agendamiento_encontrado['tecnico_asignado'] == 'Julian Cerquera') ? 'selected' : ''; ?>>Julian Cerquera</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="estado_visita_edit" class="form-label">Estado de la Visita</label>
                                <select class="form-select" id="estado_visita_edit" name="estado_visita_edit">
                                    <option value="NO Asignado" <?php echo ($agendamiento_encontrado['estado_visita'] == 'NO Asignado') ? 'selected' : ''; ?>>NO Asignado</option>
                                    <option value="AGENDADO" <?php echo ($agendamiento_encontrado['estado_visita'] == 'AGENDADO') ? 'selected' : ''; ?>>AGENDADO</option>
                                    <option value="REPROGRAMAR" <?php echo ($agendamiento_encontrado['estado_visita'] == 'REPROGRAMAR') ? 'selected' : ''; ?>>REPROGRAMAR</option>
                                    <option value="PENDIENTE" <?php echo ($agendamiento_encontrado['estado_visita'] == 'PENDIENTE') ? 'selected' : ''; ?>>PENDIENTE</option>
                                    <option value="CANCELADO" <?php echo ($agendamiento_encontrado['estado_visita'] == 'CANCELADO') ? 'selected' : ''; ?>>CANCELADO</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="notas_edit" class="form-label">Notas</label>
                                <textarea class="form-control" id="notas_edit" name="notas_edit" rows="3"><?php echo htmlspecialchars($agendamiento_encontrado['notas'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="actualizar_agendamiento" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL PARA ELIMINAR AGENDAMIENTO -->
    <div class="modal fade" id="deleteAgendamientoModal" tabindex="-1" aria-labelledby="deleteAgendamientoModalLabel">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAgendamientoModalLabel">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea cancelar/eliminar este agendamiento?</p>
                    <p><strong>Cédula:</strong> <?php echo htmlspecialchars($agendamiento_encontrado['cedula_cliente']); ?></p>
                    <p><strong>Fecha:</strong> <?php echo htmlspecialchars($agendamiento_encontrado['fecha_visita']); ?></p>
                    <p><strong>Estado:</strong> <?php echo htmlspecialchars($agendamiento_encontrado['estado_visita']); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="id_agendamiento" value="<?php echo htmlspecialchars($agendamiento_encontrado['id']); ?>">
                        <button type="submit" name="borrar_agendamiento" class="btn btn-danger">Eliminar Agendamiento</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL PARA EDITAR VENTA COMPLETA -->
    <?php if ($venta_encontrada): ?>
    <div class="modal fade" id="editVentaModal" tabindex="-1" aria-labelledby="editVentaModalLabel">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editVentaModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>Editar Datos Completos de la Venta
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="formEditarVenta">
                    <div class="modal-body">
                        <input type="hidden" name="id_venta" value="<?php echo htmlspecialchars($venta_encontrada['id']); ?>">
                        
                        <!-- Información Personal -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="bi bi-person me-1"></i>Información Personal
                                </h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nombre_edit" class="form-label fw-bold">Nombre *</label>
                                <input type="text" class="form-control" id="nombre_edit" name="nombre_edit" 
                                       value="<?php echo htmlspecialchars($venta_encontrada['nombre']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cedula_edit" class="form-label fw-bold">Cédula *</label>
                                <input type="text" class="form-control" id="cedula_edit" name="cedula_edit" 
                                       value="<?php echo htmlspecialchars($venta_encontrada['cedula']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="telefono1_edit" class="form-label fw-bold">Teléfono 1 *</label>
                                <input type="text" class="form-control" id="telefono1_edit" name="telefono1_edit" 
                                       value="<?php echo htmlspecialchars($venta_encontrada['telefono1']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="telefono2_edit" class="form-label">Teléfono 2</label>
                                <input type="text" class="form-control" id="telefono2_edit" name="telefono2_edit" 
                                       value="<?php echo htmlspecialchars($venta_encontrada['telefono2'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="email_edit" class="form-label fw-bold">Email *</label>
                                <input type="email" class="form-control" id="email_edit" name="email_edit" 
                                       value="<?php echo htmlspecialchars($venta_encontrada['email']); ?>" required>
                            </div>
                        </div>

                        <!-- Información de Ubicación -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="bi bi-geo-alt me-1"></i>Ubicación
                                </h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="municipio_edit" class="form-label fw-bold">Municipio *</label>
                                <input type="text" class="form-control" id="municipio_edit" name="municipio_edit" 
                                       value="<?php echo htmlspecialchars($venta_encontrada['municipio']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vereda_edit" class="form-label fw-bold">Vereda *</label>
                                <input type="text" class="form-control" id="vereda_edit" name="vereda_edit" 
                                       value="<?php echo htmlspecialchars($venta_encontrada['vereda']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="coordenadas_edit" class="form-label fw-bold">Coordenadas *</label>
                                <input type="text" class="form-control" id="coordenadas_edit" name="coordenadas_edit" 
                                       value="<?php echo htmlspecialchars($venta_encontrada['coordenadas']); ?>" 
                                       placeholder="Lat, Long" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="indicaciones_edit" class="form-label fw-bold">Indicaciones *</label>
                                <textarea class="form-control" id="indicaciones_edit" name="indicaciones_edit" 
                                          rows="3" required><?php echo htmlspecialchars($venta_encontrada['indicaciones']); ?></textarea>
                            </div>
                        </div>

                        <!-- Información del Servicio -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="bi bi-wifi me-1"></i>Servicio
                                </h6>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="num_servicio_edit" class="form-label fw-bold">Número de Servicio *</label>
                                <input type="text" class="form-control" id="num_servicio_edit" name="num_servicio_edit" 
                                       value="<?php echo htmlspecialchars($venta_encontrada['num_servicio']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="tecnologia_edit" class="form-label fw-bold">Tecnología *</label>
                                <select class="form-select" id="tecnologia_edit" name="tecnologia_edit" required>
                                    <option value="Fibra Óptica" <?php echo ($venta_encontrada['tecnologia'] == 'Fibra Óptica') ? 'selected' : ''; ?>>Fibra Óptica</option>
                                    <option value="ADSL" <?php echo ($venta_encontrada['tecnologia'] == 'ADSL') ? 'selected' : ''; ?>>ADSL</option>
                                    <option value="Cable" <?php echo ($venta_encontrada['tecnologia'] == 'Cable') ? 'selected' : ''; ?>>Cable</option>
                                    <option value="Satelital" <?php echo ($venta_encontrada['tecnologia'] == 'Satelital') ? 'selected' : ''; ?>>Satelital</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="plan_edit" class="form-label fw-bold">Plan *</label>
                                <select class="form-select" id="plan_edit" name="plan_edit" required>
                                    <option value="10 Megas" <?php echo ($venta_encontrada['plan'] == '10 Megas') ? 'selected' : ''; ?>>10 Megas</option>
                                    <option value="20 Megas" <?php echo ($venta_encontrada['plan'] == '20 Megas') ? 'selected' : ''; ?>>20 Megas</option>
                                    <option value="50 Megas" <?php echo ($venta_encontrada['plan'] == '50 Megas') ? 'selected' : ''; ?>>50 Megas</option>
                                    <option value="100 Megas" <?php echo ($venta_encontrada['plan'] == '100 Megas') ? 'selected' : ''; ?>>100 Megas</option>
                                    <option value="200 Megas" <?php echo ($venta_encontrada['plan'] == '200 Megas') ? 'selected' : ''; ?>>200 Megas</option>
                                    <option value="500 Megas" <?php echo ($venta_encontrada['plan'] == '500 Megas') ? 'selected' : ''; ?>>500 Megas</option>
                                </select>
                            </div>
                        </div>

                        <!-- Notas Adicionales -->
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="notas_edit" class="form-label">Notas Adicionales</label>
                                <textarea class="form-control" id="notas_edit" name="notas_edit" 
                                          rows="3" placeholder="Información adicional sobre la venta..."><?php echo htmlspecialchars($venta_encontrada['notas'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" name="actualizar_venta_completa" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Guardar Todos los Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


    <script src="js/registroagenda.js"></script>

</body>
</html>