/**
 * Gestión de Permisos - JavaScript
 * Archivo: js/permisos.js
 * Codificación: UTF-8
 */

// Variables globales
let permisosData = [];
let permisoEditando = null;

// Configuración de la API
const API_URL = 'api/api_permisos.php';

// Elementos DOM
const elements = {
    formCrear: document.getElementById('formCrearPermiso'),
    formEditar: document.getElementById('formEditarPermiso'),
    nombrePermiso: document.getElementById('nombrePermiso'),
    btnCrear: document.getElementById('btnCrearPermiso'),
    spinnerCrear: document.getElementById('spinnerCrear'),
    tablaPermisos: document.getElementById('tablaPermisos'),
    alertContainer: document.getElementById('alertContainer'),
    modalConfirmarEliminar: null,
    modalEditar: null,
    modalBodyEliminar: document.getElementById('modalBodyEliminar'),
    btnConfirmarEliminar: document.getElementById('btnConfirmarEliminar'),
    spinnerEliminar: document.getElementById('spinnerEliminar'),
    spinnerEditar: document.getElementById('spinnerEditar'),
    editarPermisoId: document.getElementById('editarPermisoId'),
    editarNombrePermiso: document.getElementById('editarNombrePermiso'),
    btnTogglePermiso: document.getElementById('btnTogglePermiso'),
    btnActualizarPermisos: document.getElementById('btnActualizarPermisos')
};

/**
 * Función para mostrar alertas
 * @param {string} mensaje - Mensaje de la alerta
 * @param {string} tipo - Tipo de alerta (success, danger, warning, info)
 * @param {number} duracion - Duración en milisegundos (opcional)
 */
function mostrarAlerta(mensaje, tipo = 'info', duracion = 5000) {
    const alertId = 'alert-' + Date.now();
    const iconos = {
        success: 'bi-check-circle-fill',
        danger: 'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-triangle-fill',
        info: 'bi-info-circle-fill'
    };
    
    const alertHtml = `
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert" id="${alertId}">
            <i class="bi ${iconos[tipo]} me-2"></i>
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    `;
    
    if (elements.alertContainer) {
        elements.alertContainer.insertAdjacentHTML('beforeend', alertHtml);
        
        // Auto-remover después de la duración especificada
        if (duracion > 0) {
            setTimeout(() => {
                const alertElement = document.getElementById(alertId);
                if (alertElement) {
                    const bsAlert = new bootstrap.Alert(alertElement);
                    bsAlert.close();
                }
            }, duracion);
        }
    }
}

/**
 * Función para realizar peticiones HTTP
 * @param {string} method - Método HTTP
 * @param {string} url - URL de la petición
 * @param {Object} data - Datos a enviar (opcional)
 * @returns {Promise} Promesa con la respuesta
 */
async function fetchAPI(method, url, data = null) {
    const config = {
        method: method,
        headers: {
            'Content-Type': 'application/json; charset=utf-8',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        config.body = JSON.stringify(data);
    }
    
    try {
        console.log(`Enviando ${method} a ${url}`, data);
        
        const response = await fetch(url, config);
        
        // Verificar si la respuesta es JSON válida
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const textResponse = await response.text();
            console.error('Respuesta no JSON:', textResponse);
            throw new Error(`Respuesta no válida del servidor. Content-Type: ${contentType}`);
        }
        
        const result = await response.json();
        console.log('Respuesta de la API:', result);
        
        return result;
        
    } catch (error) {
        console.error('Error en petición API:', error);
        
        // Manejar diferentes tipos de errores
        if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
            throw new Error('Error de conexión. Verifique que el servidor esté funcionando.');
        } else if (error.name === 'SyntaxError') {
            throw new Error('Error al procesar la respuesta del servidor.');
        } else {
            throw error;
        }
    }
}

/**
 * Función para cargar permisos desde la API
 */
async function cargarPermisos() {
    try {
        console.log('Cargando permisos...');
        const response = await fetchAPI('GET', API_URL);
        
        if (response && response.success) {
            permisosData = response.data || [];
            console.log('Permisos cargados:', permisosData);
            renderizarTablaPermisos();
        } else {
            const errorMsg = response ? response.error : 'Respuesta inválida del servidor';
            mostrarAlerta('Error al cargar permisos: ' + errorMsg, 'danger');
            console.error('Error API:', response);
        }
        
    } catch (error) {
        mostrarAlerta('Error de conexión al cargar permisos: ' + error.message, 'danger');
        console.error('Error al cargar permisos:', error);
        
        // Mostrar tabla vacía en caso de error
        if (elements.tablaPermisos) {
            elements.tablaPermisos.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                        Error al conectar con el servidor
                    </td>
                </tr>
            `;
        }
    }
}

/**
 * Función para renderizar la tabla de permisos
 */
function renderizarTablaPermisos() {
    if (!elements.tablaPermisos) {
        console.error('Elemento tablaPermisos no encontrado');
        return;
    }
    
    if (permisosData.length === 0) {
        elements.tablaPermisos.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4 text-muted">
                    <i class="bi bi-shield-exclamation fs-1 d-block mb-2"></i>
                    No hay permisos registrados
                </td>
            </tr>
        `;
        return;
    }
    
    elements.tablaPermisos.innerHTML = permisosData.map(permiso => `
        <tr data-permiso-id="${permiso.id}" class="fade-in">
            <td class="fw-bold">${escapeHtml(permiso.id)}</td>
            <td>
                <i class="bi bi-shield text-success me-2"></i>
                <code class="text-primary">${escapeHtml(permiso.nombre)}</code>
            </td>
            <td>
                <small class="text-muted">
                    ${formatearFecha(permiso.fecha_creacion)}
                </small>
            </td>
            <td>
                <small class="text-muted">
                    ${formatearFecha(permiso.fecha_modificacion)}
                </small>
            </td>
            <td class="text-center">
                <div class="btn-group" role="group">
                    <button 
                        class="btn btn-warning btn-sm btn-editar" 
                        data-id="${permiso.id}"
                        data-nombre="${escapeHtml(permiso.nombre)}"
                        title="Editar permiso"
                        onclick="editarPermiso(${permiso.id}, '${escapeHtml(permiso.nombre)}')"
                    >
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button 
                        class="btn btn-danger btn-sm btn-eliminar" 
                        data-id="${permiso.id}"
                        data-nombre="${escapeHtml(permiso.nombre)}"
                        title="Eliminar permiso"
                        onclick="mostrarConfirmarEliminar(${permiso.id}, '${escapeHtml(permiso.nombre)}')"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

/**
 * Función para formatear fechas
 * @param {string} fecha - Fecha en formato ISO
 * @returns {string} Fecha formateada
 */
function formatearFecha(fecha) {
    try {
        const date = new Date(fecha);
        return date.toLocaleDateString('es-CO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return 'Fecha inválida';
    }
}

/**
 * Función para escapar HTML y prevenir XSS
 * @param {string} text - Texto a escapar
 * @returns {string} Texto escapado
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Función para validar nombre de permiso
 * @param {string} nombre - Nombre a validar
 * @returns {Object} Resultado de la validación
 */
function validarNombrePermiso(nombre) {
    const errores = [];
    
    if (!nombre || nombre.trim() === '') {
        errores.push('El nombre del permiso es obligatorio');
    }
    
    if (nombre.length < 3) {
        errores.push('El nombre debe tener al menos 3 caracteres');
    }
    
    if (nombre.length > 50) {
        errores.push('El nombre no puede tener más de 50 caracteres');
    }
    
    if (!/^[a-z_]+$/.test(nombre)) {
        errores.push('Solo se permiten letras minúsculas y guiones bajos');
    }
    
    return {
        valido: errores.length === 0,
        errores: errores
    };
}

/**
 * Función para crear un nuevo permiso
 * @param {FormData} formData - Datos del formulario
 */
async function crearPermiso(formData) {
    const nombre = formData.get('nombre').trim();
    
    // Validar datos localmente
    const validacion = validarNombrePermiso(nombre);
    if (!validacion.valido) {
        mostrarAlerta('Errores de validación: ' + validacion.errores.join(', '), 'danger');
        return;
    }
    
    // Mostrar spinner
    if (elements.spinnerCrear) {
        elements.spinnerCrear.classList.remove('d-none');
    }
    if (elements.btnCrear) {
        elements.btnCrear.disabled = true;
    }
    
    try {
        const response = await fetchAPI('POST', API_URL, {
            nombre: nombre,
            descripcion: null // Se puede agregar campo descripción si es necesario
        });
        
        if (response.success) {
            mostrarAlerta('Permiso creado exitosamente', 'success');
            if (elements.formCrear) {
                elements.formCrear.reset();
                elements.formCrear.classList.remove('was-validated');
            }
            
            // Recargar la lista de permisos
            await cargarPermisos();
        } else {
            const mensaje = response.details ? 
                'Errores: ' + response.details.join(', ') : 
                response.error;
            mostrarAlerta(mensaje, 'danger');
        }
        
    } catch (error) {
        mostrarAlerta('Error al crear permiso. Intente nuevamente.', 'danger');
        console.error('Error al crear permiso:', error);
        
    } finally {
        // Ocultar spinner
        if (elements.spinnerCrear) {
            elements.spinnerCrear.classList.add('d-none');
        }
        if (elements.btnCrear) {
            elements.btnCrear.disabled = false;
        }
    }
}

/**
 * Función para mostrar el modal de edición
 * @param {number} id - ID del permiso
 * @param {string} nombre - Nombre actual del permiso
 */
function editarPermiso(id, nombre) {
    permisoEditando = { id, nombre };
    
    if (elements.editarPermisoId) {
        elements.editarPermisoId.value = id;
    }
    if (elements.editarNombrePermiso) {
        elements.editarNombrePermiso.value = nombre;
    }
    
    if (elements.modalEditar) {
        elements.modalEditar.show();
    }
}

/**
 * Función para actualizar un permiso
 * @param {FormData} formData - Datos del formulario
 */
async function actualizarPermiso(formData) {
    const id = parseInt(formData.get('id'));
    const nombre = formData.get('nombre').trim();
    
    // Validar datos
    const validacion = validarNombrePermiso(nombre);
    if (!validacion.valido) {
        mostrarAlerta('Errores de validación: ' + validacion.errores.join(', '), 'danger');
        return;
    }
    
    // Mostrar spinner
    if (elements.spinnerEditar) {
        elements.spinnerEditar.classList.remove('d-none');
    }
    
    try {
        const response = await fetchAPI('PUT', API_URL, {
            id: id,
            nombre: nombre,
            descripcion: null
        });
        
        if (response.success) {
            mostrarAlerta('Permiso actualizado exitosamente', 'success');
            if (elements.modalEditar) {
                elements.modalEditar.hide();
            }
            
            // Recargar la lista de permisos
            await cargarPermisos();
        } else {
            const mensaje = response.details ? 
                'Errores: ' + response.details.join(', ') : 
                response.error;
            mostrarAlerta(mensaje, 'danger');
        }
        
    } catch (error) {
        mostrarAlerta('Error al actualizar permiso. Intente nuevamente.', 'danger');
        console.error('Error al actualizar permiso:', error);
        
    } finally {
        if (elements.spinnerEditar) {
            elements.spinnerEditar.classList.add('d-none');
        }
    }
}

/**
 * Función para mostrar el modal de confirmación de eliminación
 * @param {number} id - ID del permiso
 * @param {string} nombre - Nombre del permiso
 */
function mostrarConfirmarEliminar(id, nombre) {
    if (elements.modalBodyEliminar) {
        elements.modalBodyEliminar.innerHTML = `
            <div class="text-center">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                <h4 class="mt-3">¿Está seguro?</h4>
                <p class="mb-0">Esta acción eliminará permanentemente el permiso:</p>
                <p class="fw-bold text-danger">${escapeHtml(nombre)}</p>
                <p class="text-muted small">Esta acción no se puede deshacer.</p>
            </div>
        `;
    }
    
    // Configurar el botón de confirmación
    if (elements.btnConfirmarEliminar) {
        elements.btnConfirmarEliminar.onclick = () => eliminarPermiso(id, nombre);
    }
    
    if (elements.modalConfirmarEliminar) {
        elements.modalConfirmarEliminar.show();
    }
}

/**
 * Función para eliminar un permiso
 * @param {number} id - ID del permiso
 * @param {string} nombre - Nombre del permiso (para logging)
 */
async function eliminarPermiso(id, nombre) {
    // Mostrar spinner
    if (elements.spinnerEliminar) {
        elements.spinnerEliminar.classList.remove('d-none');
    }
    if (elements.btnConfirmarEliminar) {
        elements.btnConfirmarEliminar.disabled = true;
    }
    
    try {
        const response = await fetchAPI('DELETE', `${API_URL}?id=${id}`);
        
        if (response.success) {
            mostrarAlerta('Permiso eliminado exitosamente', 'success');
            if (elements.modalConfirmarEliminar) {
                elements.modalConfirmarEliminar.hide();
            }
            
            // Recargar la lista de permisos
            await cargarPermisos();
        } else {
            mostrarAlerta(response.error, 'danger');
        }
        
    } catch (error) {
        mostrarAlerta('Error al eliminar permiso. Intente nuevamente.', 'danger');
        console.error('Error al eliminar permiso:', error);
        
    } finally {
        if (elements.spinnerEliminar) {
            elements.spinnerEliminar.classList.add('d-none');
        }
        if (elements.btnConfirmarEliminar) {
            elements.btnConfirmarEliminar.disabled = false;
        }
    }
}

/**
 * Función para toggle del panel de permisos
 */
function togglePanelPermisos() {
    const cardBody = elements.tablaPermisos ? elements.tablaPermisos.closest('.card-body') : null;
    const icon = elements.btnTogglePermiso ? elements.btnTogglePermiso.querySelector('i') : null;
    
    if (cardBody && icon) {
        if (cardBody.style.display === 'none') {
            cardBody.style.display = 'block';
            icon.className = 'bi bi-chevron-up';
        } else {
            cardBody.style.display = 'none';
            icon.className = 'bi bi-chevron-down';
        }
    }
}

/**
 * Función para actualizar la lista de permisos
 */
async function actualizarListaPermisos() {
    const btnIcon = elements.btnActualizarPermisos ? elements.btnActualizarPermisos.querySelector('i') : null;
    let originalClass = '';
    
    if (btnIcon) {
        originalClass = btnIcon.className;
        btnIcon.className = 'bi bi-arrow-clockwise';
        btnIcon.style.animation = 'spin 1s linear infinite';
    }
    
    if (elements.btnActualizarPermisos) {
        elements.btnActualizarPermisos.disabled = true;
    }
    
    try {
        await cargarPermisos();
        mostrarAlerta('Lista de permisos actualizada', 'info', 3000);
    } catch (error) {
        mostrarAlerta('Error al actualizar la lista', 'danger');
    } finally {
        // Restaurar botón
        if (btnIcon) {
            btnIcon.style.animation = '';
            btnIcon.className = originalClass;
        }
        if (elements.btnActualizarPermisos) {
            elements.btnActualizarPermisos.disabled = false;
        }
    }
}

/**
 * Función para inicializar modales
 */
function inicializarModales() {
    // Inicializar modal de eliminar
    const modalEliminarElement = document.getElementById('modalConfirmarEliminar');
    if (modalEliminarElement && typeof bootstrap !== 'undefined') {
        elements.modalConfirmarEliminar = new bootstrap.Modal(modalEliminarElement);
    }
    
    // Inicializar modal de editar
    const modalEditarElement = document.getElementById('modalEditarPermiso');
    if (modalEditarElement && typeof bootstrap !== 'undefined') {
        elements.modalEditar = new bootstrap.Modal(modalEditarElement);
    }
}

/**
 * Event Listeners
 */
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar modales
    inicializarModales();
    
    // Cargar permisos al iniciar
    cargarPermisos();
    
    // Formulario de crear permiso
    if (elements.formCrear) {
        elements.formCrear.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (this.checkValidity()) {
                const formData = new FormData(this);
                await crearPermiso(formData);
            }
            
            this.classList.add('was-validated');
        });
    }
    
    // Formulario de editar permiso
    if (elements.formEditar) {
        elements.formEditar.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (this.checkValidity()) {
                const formData = new FormData(this);
                await actualizarPermiso(formData);
            }
            
            this.classList.add('was-validated');
        });
    }
    
    // Validación en tiempo real para el campo nombre
    if (elements.nombrePermiso) {
        elements.nombrePermiso.addEventListener('input', function() {
            const nombre = this.value;
            const validacion = validarNombrePermiso(nombre);
            
            if (validacion.valido) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                
                // Actualizar mensaje de error
                const feedback = this.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.textContent = validacion.errores.join('. ');
                }
            }
        });
    }
    
    // Validación para el campo de edición
    if (elements.editarNombrePermiso) {
        elements.editarNombrePermiso.addEventListener('input', function() {
            const nombre = this.value;
            const validacion = validarNombrePermiso(nombre);
            
            if (validacion.valido) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                
                const feedback = this.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.textContent = validacion.errores.join('. ');
                }
            }
        });
    }
    
    // Botón toggle panel
    if (elements.btnTogglePermiso) {
        elements.btnTogglePermiso.addEventListener('click', togglePanelPermisos);
    }
    
    // Botón actualizar permisos
    if (elements.btnActualizarPermisos) {
        elements.btnActualizarPermisos.addEventListener('click', actualizarListaPermisos);
    }
    
    // Limpiar formularios al cerrar modales
    const modalEditarElement = document.getElementById('modalEditarPermiso');
    if (modalEditarElement) {
        modalEditarElement.addEventListener('hidden.bs.modal', function() {
            if (elements.formEditar) {
                elements.formEditar.classList.remove('was-validated');
            }
            if (elements.editarNombrePermiso) {
                elements.editarNombrePermiso.classList.remove('is-valid', 'is-invalid');
            }
            permisoEditando = null;
        });
    }
    
    // Resetear spinner al cerrar modal de eliminar
    const modalEliminarElement = document.getElementById('modalConfirmarEliminar');
    if (modalEliminarElement) {
        modalEliminarElement.addEventListener('hidden.bs.modal', function() {
            if (elements.spinnerEliminar) {
                elements.spinnerEliminar.classList.add('d-none');
            }
            if (elements.btnConfirmarEliminar) {
                elements.btnConfirmarEliminar.disabled = false;
            }
        });
    }
});

/**
 * Función para manejar errores globales
 */
window.addEventListener('error', function(e) {
    console.error('Error JavaScript:', e.error);
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Promise rechazada:', e.reason);
});

/**
 * Animaciones CSS adicionales
 */
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .fade-in {
        animation: fadeIn 0.5s ease-in-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .btn-group .btn {
        transition: all 0.2s ease-in-out;
    }
    
    .btn-group .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .table tbody tr {
        transition: background-color 0.2s ease-in-out;
    }
    
    .table tbody tr:hover {
        background-color: rgba(0,0,0,0.02);
    }
    
    .alert {
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from { 
            opacity: 0; 
            transform: translateX(100%); 
        }
        to { 
            opacity: 1; 
            transform: translateX(0); 
        }
    }
`;

document.head.appendChild(style);

/**
 * Utilidades adicionales
 */
const utils = {
    /**
     * Debounce para optimizar llamadas a funciones
     */
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    /**
     * Formatear texto para URL-friendly
     */
    slugify: function(text) {
        return text
            .toLowerCase()
            .replace(/[^a-z0-9_]/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_|_$/g, '');
    },
    
    /**
     * Copiar texto al portapapeles
     */
    copyToClipboard: function(text) {
        if (navigator.clipboard) {
            return navigator.clipboard.writeText(text);
        } else {
            // Fallback para navegadores antiguos
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            return Promise.resolve();
        }
    }
};

// Exportar funciones para uso global (si es necesario)
window.PermisosManager = {
    cargarPermisos,
    crearPermiso,
    editarPermiso,
    eliminarPermiso,
    utils
};