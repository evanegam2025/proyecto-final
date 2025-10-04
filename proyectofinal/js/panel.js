/**
 * Panel de Módulos - Script para gestión de permisos por módulo
 * Funcionalidades: Asignar/desasignar permisos, gestión de módulos
 */

// Variables globales
let currentModule = '';

document.addEventListener('DOMContentLoaded', function() {
    
    // Inicializar componentes
    initializeExpandCollapseButtons();
    initializeCollapseEvents();
    initializeTooltips();
    
    /**
     * Inicializar botones de expandir/colapsar
     */
    function initializeExpandCollapseButtons() {
        const expandBtn = document.getElementById('expandAllBtn');
        const collapseBtn = document.getElementById('collapseAllBtn');
        
        if (expandBtn) {
            expandBtn.addEventListener('click', function() {
                expandAllModules();
                updateExpandCollapseButtons(true);
            });
        }
        
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function() {
                collapseAllModules();
                updateExpandCollapseButtons(false);
            });
        }
    }
    
    /**
     * Expandir todos los módulos
     */
    function expandAllModules() {
        const collapseElements = document.querySelectorAll('.collapse');
        
        collapseElements.forEach(element => {
            if (!element.classList.contains('show')) {
                const bsCollapse = new bootstrap.Collapse(element, { show: true });
            }
        });
        
        // Actualizar iconos
        updateToggleIcons(true);
        
        showNotification('Todos los módulos expandidos', 'info');
    }
    
    /**
     * Colapsar todos los módulos
     */
    function collapseAllModules() {
        const collapseElements = document.querySelectorAll('.collapse.show');
        
        collapseElements.forEach(element => {
            const bsCollapse = new bootstrap.Collapse(element, { hide: true });
        });
        
        // Actualizar iconos
        updateToggleIcons(false);
        
        showNotification('Todos los módulos colapsados', 'info');
    }
    
    /**
     * Actualizar iconos de toggle
     */
    function updateToggleIcons(expanded) {
        const toggleIcons = document.querySelectorAll('.toggle-icon');
        
        toggleIcons.forEach(icon => {
            icon.classList.toggle('bi-chevron-down', !expanded);
            icon.classList.toggle('bi-chevron-up', expanded);
        });
    }
    
    /**
     * Actualizar estado de botones expandir/colapsar
     */
    function updateExpandCollapseButtons(allExpanded) {
        const expandBtn = document.getElementById('expandAllBtn');
        const collapseBtn = document.getElementById('collapseAllBtn');
        
        if (expandBtn && collapseBtn) {
            expandBtn.disabled = allExpanded;
            collapseBtn.disabled = !allExpanded;
        }
    }
    
    /**
     * Escuchar eventos de colapso para actualizar iconos
     */
    function initializeCollapseEvents() {
        const collapseElements = document.querySelectorAll('.collapse');
        
        collapseElements.forEach(element => {
            element.addEventListener('show.bs.collapse', function() {
                const header = document.querySelector(`[data-bs-target="#${this.id}"]`);
                if (header) {
                    const icon = header.querySelector('.toggle-icon');
                    if (icon) {
                        icon.classList.remove('bi-chevron-down');
                        icon.classList.add('bi-chevron-up');
                    }
                }
            });
            
            element.addEventListener('hide.bs.collapse', function() {
                const header = document.querySelector(`[data-bs-target="#${this.id}"]`);
                if (header) {
                    const icon = header.querySelector('.toggle-icon');
                    if (icon) {
                        icon.classList.remove('bi-chevron-up');
                        icon.classList.add('bi-chevron-down');
                    }
                }
            });
        });
    }
    
    /**
     * Inicializar tooltips
     */
    function initializeTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
});

/**
 * Función global para mostrar notificaciones
 */
window.showNotification = function(message, type = 'info') {
    const toastElement = document.getElementById('messageToast');
    const toastBody = toastElement.querySelector('.toast-body');
    
    // Configurar el mensaje
    toastBody.textContent = message;
    
    // Remover clases anteriores
    toastElement.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-info', 'text-bg-warning');
    
    // Agregar clase según tipo
    switch (type) {
        case 'success':
            toastElement.classList.add('text-bg-success');
            break;
        case 'error':
            toastElement.classList.add('text-bg-danger');
            break;
        case 'warning':
            toastElement.classList.add('text-bg-warning');
            break;
        default:
            toastElement.classList.add('text-bg-info');
    }
    
    // Mostrar el toast
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
};

/**
 * Asignar permiso a un módulo
 */
window.asignarPermiso = async function(moduloNombre, moduloId) {
    const selectElement = document.getElementById(`nuevoPermiso-${moduloId}`);
    if (!selectElement) {
        showNotification('Error: Elemento de selección no encontrado', 'error');
        return;
    }
    
    const permisoId = selectElement.value;
    
    if (!permisoId) {
        showNotification('Debe seleccionar un permiso para asignar', 'warning');
        return;
    }
    
    const permisoTexto = selectElement.options[selectElement.selectedIndex].text;
    
    try {
        const response = await fetch('api/api_panel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'assign_permission_to_module',
                modulo: moduloNombre,
                permiso_id: parseInt(permisoId)
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Recargar la página para mostrar los cambios
            setTimeout(() => {
                location.reload();
            }, 1500);
            
        } else {
            showNotification(data.message || 'Error al asignar el permiso', 'error');
        }
        
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión. Intente nuevamente.', 'error');
    }
};

/**
 * Cargar modal de gestión avanzada
 */
window.cargarModalGestion = async function(moduloNombre, moduloId) {
    const modalTitle = document.getElementById('modalModuloNombre');
    const modalContent = document.getElementById('modalContent');
    
    if (!modalTitle || !modalContent) {
        console.error('Elementos del modal no encontrados');
        return;
    }
    
    modalTitle.textContent = moduloNombre;
    modalContent.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
    
    try {
        const response = await fetch('api/api_panel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'get_module_permissions_detail',
                modulo: moduloNombre
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderModalContent(data.data, moduloNombre);
        } else {
            modalContent.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
        }
        
    } catch (error) {
        console.error('Error:', error);
        modalContent.innerHTML = '<div class="alert alert-danger">Error al cargar los datos del módulo</div>';
    }
};

/**
 * Renderizar contenido del modal
 */
function renderModalContent(data, moduloNombre) {
    const modalContent = document.getElementById('modalContent');
    
    let html = `
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Gestione los permisos asignados al módulo <strong>${moduloNombre}</strong>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-success fw-bold mb-3">
                    <i class="bi bi-check-circle me-2"></i>
                    Permisos Asignados (${data.permisos_asignados.length})
                </h6>
                <div class="list-group" id="permisosAsignados">
    `;
    
    if (data.permisos_asignados.length > 0) {
        data.permisos_asignados.forEach(permiso => {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${permiso.nombre}</strong>
                        <small class="text-muted d-block">${permiso.descripcion || 'Sin descripción'}</small>
                    </div>
                    <button type="button" 
                            class="btn btn-outline-danger btn-sm" 
                            onclick="desasignarPermiso('${moduloNombre}', ${permiso.id}, '${permiso.nombre}')">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
            `;
        });
    } else {
        html += '<div class="text-muted text-center py-3">No hay permisos asignados</div>';
    }
    
    html += `
                </div>
            </div>
            
            <div class="col-md-6">
                <h6 class="text-primary fw-bold mb-3">
                    <i class="bi bi-plus-circle me-2"></i>
                    Permisos Disponibles (${data.permisos_disponibles.length})
                </h6>
                <div class="list-group" id="permisosDisponibles">
    `;
    
    if (data.permisos_disponibles.length > 0) {
        data.permisos_disponibles.forEach(permiso => {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${permiso.nombre}</strong>
                        <small class="text-muted d-block">${permiso.descripcion || 'Sin descripción'}</small>
                    </div>
                    <button type="button" 
                            class="btn btn-outline-primary btn-sm" 
                            onclick="asignarPermisoModal('${moduloNombre}', ${permiso.id}, '${permiso.nombre}')">
                        <i class="bi bi-plus-circle"></i>
                    </button>
                </div>
            `;
        });
    } else {
        html += '<div class="text-muted text-center py-3">Todos los permisos están asignados</div>';
    }
    
    html += `
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title fw-bold">Administradores en este módulo:</h6>
                        <div class="d-flex flex-wrap gap-2">
    `;
    
    if (data.administradores && data.administradores.length > 0) {
        data.administradores.forEach(admin => {
            html += `
                <span class="badge bg-secondary">
                    <i class="bi bi-person me-1"></i>
                    ${admin.nombre}
                </span>
            `;
        });
    } else {
        html += '<span class="text-muted">No hay administradores asignados a este módulo</span>';
    }
    
    html += `
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    modalContent.innerHTML = html;
    
    // Almacenar el módulo actual para uso posterior
    currentModule = moduloNombre;
}

/**
 * Asignar permiso desde el modal
 */
window.asignarPermisoModal = async function(moduloNombre, permisoId, permisoNombre) {
    try {
        const response = await fetch('api/api_panel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'assign_permission_to_module',
                modulo: moduloNombre,
                permiso_id: permisoId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(`Permiso "${permisoNombre}" asignado correctamente`, 'success');
            
            // Recargar contenido del modal
            setTimeout(() => {
                cargarModalGestion(moduloNombre, 0);
            }, 1000);
            
        } else {
            showNotification(data.message || 'Error al asignar el permiso', 'error');
        }
        
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión. Intente nuevamente.', 'error');
    }
};

/**
 * Desasignar permiso desde el modal
 */
window.desasignarPermiso = async function(moduloNombre, permisoId, permisoNombre) {
    if (!confirm(`¿Está seguro de que desea desasignar el permiso "${permisoNombre}" del módulo ${moduloNombre}?`)) {
        return;
    }
    
    try {
        const response = await fetch('api/api_panel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'unassign_permission_from_module',
                modulo: moduloNombre,
                permiso_id: permisoId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(`Permiso "${permisoNombre}" desasignado correctamente`, 'success');
            
            // Recargar contenido del modal
            setTimeout(() => {
                cargarModalGestion(moduloNombre, 0);
            }, 1000);
            
        } else {
            showNotification(data.message || 'Error al desasignar el permiso', 'error');
        }
        
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión. Intente nuevamente.', 'error');
    }
};

/**
 * Guardar cambios de permisos
 */
window.guardarCambiosPermisos = function() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalGestionPermisos'));
    modal.hide();
    
    showNotification('Los cambios se han aplicado automáticamente', 'info');
    
    // Recargar la página para mostrar todos los cambios
    setTimeout(() => {
        location.reload();
    }, 1500);
};

/**
 * Utilidades globales
 */
window.ModulosUtils = {
    
    /**
     * Recargar página completa
     */
    reload: function() {
        location.reload();
    },
    
    /**
     * Exportar configuración de módulos
     */
    exportModulesConfig: async function() {
        try {
            const response = await fetch('api/api_panel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'export_modules_config'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Descargar archivo
                const blob = new Blob([JSON.stringify(data.data, null, 2)], {
                    type: 'application/json'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `modulos_config_${new Date().toISOString().slice(0, 10)}.json`;
                a.click();
                URL.revokeObjectURL(url);
                
                showNotification('Configuración exportada correctamente', 'success');
            } else {
                showNotification('Error al exportar configuración', 'error');
            }
        } catch (error) {
            console.error('Error al exportar:', error);
            showNotification('Error de conexión al exportar', 'error');
        }
    },
    
    /**
     * Sincronizar permisos de usuario con procedimiento almacenado
     */
    syncUserPermissions: async function(cedula) {
        try {
            const response = await fetch('api/api_panel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_user_permissions',
                    cedula: cedula
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('Permisos del usuario:', data.data);
                return data.data;
            } else {
                showNotification('Error al obtener permisos del usuario', 'error');
                return null;
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
            return null;
        }
    }
};