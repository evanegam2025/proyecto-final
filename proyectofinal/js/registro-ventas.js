/**
 * Sistema de Registro de Ventas - JavaScript Principal
 * Archivo: js/registro-ventas.js
 * Codificación: UTF-8
 * Descripción: Funcionalidad completa para el sistema CRUD de ventas
 */

// ======================================================================
// CONFIGURACIÓN Y VARIABLES GLOBALES
// ======================================================================

// Configuración de la aplicación
const APP_CONFIG = {
    baseUrl: window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, ''),
    endpoints: {
        create: 'api/api_registro-ventas.php?action=crear',
        read: 'api/api_registro-ventas.php?action=obtener',
        update: 'api/api_registro-ventas.php?action=actualizar',
        delete: 'api/api_registro-ventas.php?action=eliminar',
        stats: 'api/api_registro-ventas.php?action=estadisticas'
    },
    timeouts: {
        request: 15000,
        alert: 5000
    }
};

// Variables globales
let ventaEnEdicion = null;
let modalEdit = null;
let alertContainer = null;

// ======================================================================
// UTILIDADES Y FUNCIONES AUXILIARES
// ======================================================================

/**
 * Función para mostrar alertas dinámicas
 * @param {string} mensaje - Mensaje a mostrar
 * @param {string} tipo - Tipo de alerta (success, danger, warning, info)
 * @param {number} duracion - Duración en ms (por defecto 5000)
 */
function mostrarAlerta(mensaje, tipo = 'info', duracion = APP_CONFIG.timeouts.alert) {
    if (!alertContainer) {
        alertContainer = document.getElementById('alert-container');
        if (!alertContainer) {
            console.error('Contenedor de alertas no encontrado');
            return;
        }
    }

    const alertId = 'alert-' + Date.now() + Math.random().toString(36).substr(2, 9);
    const iconos = {
        success: 'bi-check-circle-fill',
        danger: 'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        info: 'bi-info-circle-fill'
    };

    const alertHTML = `
        <div id="${alertId}" class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            <i class="bi ${iconos[tipo]} me-2"></i>
            <strong>${mensaje}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    `;

    alertContainer.insertAdjacentHTML('beforeend', alertHTML);

    // Auto-remover después de la duración especificada
    setTimeout(() => {
        const alertElement = document.getElementById(alertId);
        if (alertElement) {
            const bsAlert = new bootstrap.Alert(alertElement);
            bsAlert.close();
        }
    }, duracion);
}

/**
 * Función para sanitizar cadenas y prevenir XSS
 * @param {string} str - Cadena a sanitizar
 * @return {string} Cadena sanitizada
 */
function sanitizarTexto(str) {
    if (typeof str !== 'string') return str;
    
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Función para validar formato de email
 * @param {string} email - Email a validar
 * @return {boolean} True si es válido
 */
function validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Función para validar formato de teléfono colombiano
 * @param {string} telefono - Teléfono a validar
 * @return {boolean} True si es válido
 */
function validarTelefono(telefono) {
    const regex = /^[3][0-9]{9}$/;
    return regex.test(telefono.replace(/\s/g, ''));
}

/**
 * Función para formatear fecha local a datetime-local input
 * @param {string} fechaISO - Fecha en formato ISO
 * @return {string} Fecha formateada para input datetime-local
 */
function formatearFechaParaInput(fechaISO) {
    const fecha = new Date(fechaISO);
    const year = fecha.getFullYear();
    const month = String(fecha.getMonth() + 1).padStart(2, '0');
    const day = String(fecha.getDate()).padStart(2, '0');
    const hours = String(fecha.getHours()).padStart(2, '0');
    const minutes = String(fecha.getMinutes()).padStart(2, '0');
    
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

/**
 * Función para realizar peticiones AJAX con manejo de errores
 * @param {string} url - URL del endpoint
 * @param {object} options - Opciones de la petición
 * @return {Promise} Promesa con la respuesta
 */
async function realizarPeticion(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
        },
        timeout: APP_CONFIG.timeouts.request
    };

    const finalOptions = { ...defaultOptions, ...options };

    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), finalOptions.timeout);

        const response = await fetch(url, {
            ...finalOptions,
            signal: controller.signal
        });

        clearTimeout(timeoutId);

        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status} - ${response.statusText}`);
        }

        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return await response.json();
        } else {
            const text = await response.text();
            console.warn('Respuesta no JSON recibida:', text);
            return { success: false, error: 'Respuesta del servidor no válida' };
        }

    } catch (error) {
        if (error.name === 'AbortError') {
            throw new Error('La petición ha excedido el tiempo límite');
        }
        throw error;
    }
}

// ======================================================================
// FUNCIONES DE VALIDACIÓN DE FORMULARIOS
// ======================================================================

/**
 * Validar formulario de venta
 * @param {FormData} formData - Datos del formulario
 * @return {object} Resultado de validación
 */
function validarFormularioVenta(formData) {
    const errores = [];
    const datos = {};

    // Convertir FormData a objeto
    for (let [key, value] of formData.entries()) {
        datos[key] = typeof value === 'string' ? value.trim() : value;
    }

    // Validaciones requeridas
    const camposRequeridos = [
        'cedula', 'nombre', 'telefono1', 'email', 'tecnologia', 
        'plan', 'num_servicio', 'municipio', 'vereda', 
        'coordenadas', 'indicaciones', 'fecha'
    ];

    camposRequeridos.forEach(campo => {
        if (!datos[campo] || datos[campo] === '') {
            errores.push(`El campo ${campo.replace('_', ' ')} es requerido`);
        }
    });

    // Validación específica de cédula
    if (datos.cedula && (!/^\d{6,12}$/.test(datos.cedula))) {
        errores.push('La cédula debe tener entre 6 y 12 dígitos');
    }

    // Validación de email
    if (datos.email && !validarEmail(datos.email)) {
        errores.push('El formato del email no es válido');
    }

    // Validación de teléfonos
    if (datos.telefono1 && !validarTelefono(datos.telefono1)) {
        errores.push('El teléfono principal debe ser un número colombiano válido (10 dígitos, iniciando con 3)');
    }

    if (datos.telefono2 && datos.telefono2 !== '' && !validarTelefono(datos.telefono2)) {
        errores.push('El teléfono secundario debe ser un número colombiano válido (10 dígitos, iniciando con 3)');
    }

    // Validación de coordenadas GPS
    if (datos.coordenadas) {
        const coordRegex = /^-?\d+\.?\d*,\s*-?\d+\.?\d*$/;
        if (!coordRegex.test(datos.coordenadas)) {
            errores.push('Las coordenadas deben tener el formato: latitud, longitud (ej: 6.244, -75.581)');
        }
    }

    return {
        valido: errores.length === 0,
        errores: errores,
        datos: datos
    };
}

// ======================================================================
// FUNCIONES CRUD - CREATE
// ======================================================================

/**
 * Crear nueva venta
 * @param {FormData} formData - Datos del formulario
 */
async function crearVenta(formData) {
    try {
        // Validar formulario
        const validacion = validarFormularioVenta(formData);
        if (!validacion.valido) {
            mostrarAlerta(`Errores de validación: ${validacion.errores.join(', ')}`, 'warning', 8000);
            return;
        }

        // Mostrar indicador de carga
        const btnSubmit = document.querySelector('#ventaForm button[type="submit"]');
        const textoOriginal = btnSubmit.innerHTML;
        btnSubmit.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
        btnSubmit.disabled = true;

        // Preparar datos para enviar
        const datosVenta = {
            cedula: validacion.datos.cedula,
            nombre: validacion.datos.nombre,
            telefono1: validacion.datos.telefono1,
            telefono2: validacion.datos.telefono2 || '',
            email: validacion.datos.email,
            tecnologia: validacion.datos.tecnologia,
            plan: validacion.datos.plan,
            num_servicio: validacion.datos.num_servicio,
            municipio: validacion.datos.municipio,
            vereda: validacion.datos.vereda,
            coordenadas: validacion.datos.coordenadas,
            indicaciones: validacion.datos.indicaciones,
            notas: validacion.datos.notas || '',
            fecha: validacion.datos.fecha
        };

        // Realizar petición
        const response = await realizarPeticion(APP_CONFIG.endpoints.create, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=UTF-8'
            },
            body: JSON.stringify(datosVenta)
        });

        if (response.success) {
            mostrarAlerta('¡Venta registrada exitosamente!', 'success');
            
            // Limpiar formulario
            limpiarFormulario();
            
            // Ocultar formulario
            const toggle = document.getElementById('toggleFormulario');
            if (toggle.checked) {
                toggle.click();
            }
            
            // Actualizar estadísticas y tabla
            await actualizarEstadisticas();
            await cargarTablaVentas();
            
        } else {
            mostrarAlerta(`Error al guardar: ${response.error || 'Error desconocido'}`, 'danger');
        }

    } catch (error) {
        console.error('Error al crear venta:', error);
        mostrarAlerta(`Error de conexión: ${error.message}`, 'danger');
    } finally {
        // Restaurar botón
        const btnSubmit = document.querySelector('#ventaForm button[type="submit"]');
        if (btnSubmit) {
            btnSubmit.innerHTML = textoOriginal;
            btnSubmit.disabled = false;
        }
    }
}

// ======================================================================
// FUNCIONES CRUD - READ
// ======================================================================

/**
 * Cargar tabla de ventas
 */
async function cargarTablaVentas() {
    try {
        const response = await realizarPeticion(APP_CONFIG.endpoints.read);
        
        if (response.success && response.data) {
            actualizarTablaVentas(response.data);
        } else {
            console.error('Error al cargar ventas:', response.error);
        }
    } catch (error) {
        console.error('Error al cargar tabla de ventas:', error);
    }
}

/**
 * Actualizar tabla de ventas en el DOM
 * @param {Array} ventas - Array de ventas
 */
function actualizarTablaVentas(ventas) {
    const tbody = document.querySelector('table tbody');
    if (!tbody) return;

    if (ventas.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-inbox display-4 d-block mb-3"></i>
                    <p class="mb-0">No hay ventas registradas.</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = ventas.map(venta => `
        <tr id="venta-row-${venta.id}">
            <td>
                <small>${new Date(venta.fecha).toLocaleString('es-CO')}</small>
            </td>
            <td>${sanitizarTexto(venta.nombre)}</td>
            <td>
                <code class="text-muted">${sanitizarTexto(venta.cedula)}</code>
            </td>
            <td>
                <small>${sanitizarTexto(venta.telefono1)}</small>
            </td>
            <td>
                <span class="badge bg-primary-subtle text-primary">
                    ${sanitizarTexto(venta.plan)}
                </span>
            </td>
            <td>
                <span class="badge bg-info-subtle text-info">
                    ${sanitizarTexto(venta.tecnologia)}
                </span>
            </td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-warning" 
                            onclick="handleEdit(${venta.id})" 
                            title="Editar Venta">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-outline-danger" 
                            onclick="handleDelete(${venta.id})" 
                            title="Eliminar Venta">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// ======================================================================
// FUNCIONES CRUD - UPDATE
// ======================================================================

/**
 * Manejar edición de venta
 * @param {number} idVenta - ID de la venta a editar
 */
async function handleEdit(idVenta) {
    try {
        // Obtener datos de la venta
        const response = await realizarPeticion(`${APP_CONFIG.endpoints.read}?id=${idVenta}`);
        
        if (response.success && response.data) {
            ventaEnEdicion = response.data;
            mostrarModalEdicion(response.data);
        } else {
            mostrarAlerta('Error al obtener datos de la venta', 'danger');
        }
    } catch (error) {
        console.error('Error al obtener venta para editar:', error);
        mostrarAlerta('Error de conexión al obtener los datos', 'danger');
    }
}

/**
 * Mostrar modal de edición con datos precargados
 * @param {object} venta - Datos de la venta
 */
function mostrarModalEdicion(venta) {
    const modal = document.getElementById('editVentaModal');
    if (!modal) {
        console.error('Modal de edición no encontrado');
        return;
    }

    // Precargar datos en el formulario
    const form = document.getElementById('editVentaForm');
    if (form) {
        form.querySelector('input[name="id"]').value = venta.id;
        form.querySelector('input[name="cedula"]').value = venta.cedula || '';
        form.querySelector('input[name="nombre"]').value = venta.nombre || '';
        form.querySelector('input[name="telefono1"]').value = venta.telefono1 || '';
        form.querySelector('input[name="telefono2"]').value = venta.telefono2 || '';
        form.querySelector('input[name="email"]').value = venta.email || '';
        form.querySelector('select[name="tecnologia"]').value = venta.tecnologia || '';
        form.querySelector('select[name="plan"]').value = venta.plan || '';
        form.querySelector('select[name="num_servicio"]').value = venta.num_servicio || '';
        form.querySelector('input[name="municipio"]').value = venta.municipio || '';
        form.querySelector('input[name="vereda"]').value = venta.vereda || '';
        form.querySelector('input[name="coordenadas"]').value = venta.coordenadas || '';
        form.querySelector('input[name="fecha"]').value = formatearFechaParaInput(venta.fecha);
        form.querySelector('textarea[name="indicaciones"]').value = venta.indicaciones || '';
        form.querySelector('textarea[name="notas"]').value = venta.notas || '';
    }

    // Mostrar modal
    modalEdit = new bootstrap.Modal(modal);
    modalEdit.show();
}

/**
 * Actualizar venta existente
 * @param {FormData} formData - Datos del formulario
 */
async function actualizarVenta(formData) {
    try {
        // Validar formulario
        const validacion = validarFormularioVenta(formData);
        if (!validacion.valido) {
            mostrarAlerta(`Errores de validación: ${validacion.errores.join(', ')}`, 'warning', 8000);
            return;
        }

        // Mostrar indicador de carga
        const btnSubmit = document.querySelector('#editVentaForm button[type="submit"]');
        const textoOriginal = btnSubmit.innerHTML;
        btnSubmit.innerHTML = '<i class="bi bi-hourglass-split"></i> Actualizando...';
        btnSubmit.disabled = true;

        // Preparar datos
        const datosVenta = {
            id: formData.get('id'),
            cedula: validacion.datos.cedula,
            nombre: validacion.datos.nombre,
            telefono1: validacion.datos.telefono1,
            telefono2: validacion.datos.telefono2 || '',
            email: validacion.datos.email,
            tecnologia: validacion.datos.tecnologia,
            plan: validacion.datos.plan,
            num_servicio: validacion.datos.num_servicio,
            municipio: validacion.datos.municipio,
            vereda: validacion.datos.vereda,
            coordenadas: validacion.datos.coordenadas,
            indicaciones: validacion.datos.indicaciones,
            notas: validacion.datos.notas || '',
            fecha: validacion.datos.fecha
        };

        // Realizar petición
        const response = await realizarPeticion(APP_CONFIG.endpoints.update, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=UTF-8'
            },
            body: JSON.stringify(datosVenta)
        });

        if (response.success) {
            mostrarAlerta('¡Venta actualizada exitosamente!', 'success');
            
            // Cerrar modal
            if (modalEdit) {
                modalEdit.hide();
            }
            
            // Actualizar estadísticas y tabla
            await actualizarEstadisticas();
            await cargarTablaVentas();
            
        } else {
            mostrarAlerta(`Error al actualizar: ${response.error || 'Error desconocido'}`, 'danger');
        }

    } catch (error) {
        console.error('Error al actualizar venta:', error);
        mostrarAlerta(`Error de conexión: ${error.message}`, 'danger');
    } finally {
        // Restaurar botón
        const btnSubmit = document.querySelector('#editVentaForm button[type="submit"]');
        if (btnSubmit) {
            btnSubmit.innerHTML = textoOriginal;
            btnSubmit.disabled = false;
        }
    }
}

// ======================================================================
// FUNCIONES CRUD - DELETE
// ======================================================================

/**
 * Manejar eliminación de venta
 * @param {number} idVenta - ID de la venta a eliminar
 */
async function handleDelete(idVenta) {
    // Confirmar eliminación
    if (!confirm('¿Está seguro de que desea eliminar esta venta? Esta acción no se puede deshacer.')) {
        return;
    }

    try {
        const response = await realizarPeticion(APP_CONFIG.endpoints.delete, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=UTF-8'
            },
            body: JSON.stringify({ id: idVenta })
        });

        if (response.success) {
            mostrarAlerta('Venta eliminada exitosamente', 'success');
            
            // Remover fila de la tabla
            const fila = document.getElementById(`venta-row-${idVenta}`);
            if (fila) {
                fila.remove();
            }
            
            // Actualizar estadísticas
            await actualizarEstadisticas();
            
        } else {
            mostrarAlerta(`Error al eliminar: ${response.error || 'Error desconocido'}`, 'danger');
        }

    } catch (error) {
        console.error('Error al eliminar venta:', error);
        mostrarAlerta(`Error de conexión: ${error.message}`, 'danger');
    }
}

// ======================================================================
// FUNCIONES DE ESTADÍSTICAS
// ======================================================================

/**
 * Actualizar estadísticas del vendedor
 */
async function actualizarEstadisticas() {
    try {
        const response = await realizarPeticion(APP_CONFIG.endpoints.stats);
        
        if (response.success && response.data) {
            const stats = response.data;
            document.getElementById('stats-hoy').textContent = stats.ventas_hoy || 0;
            document.getElementById('stats-semana').textContent = stats.ventas_semana || 0;
            document.getElementById('stats-mes').textContent = stats.ventas_mes || 0;
            document.getElementById('stats-total').textContent = stats.total_ventas || 0;
        }
    } catch (error) {
        console.error('Error al actualizar estadísticas:', error);
    }
}

// ======================================================================
// FUNCIONES DE INTERFAZ DE USUARIO
// ======================================================================

/**
 * Limpiar formulario de venta
 */
function limpiarFormulario() {
    const form = document.getElementById('ventaForm');
    if (form) {
        form.reset();
        
        // Establecer fecha actual
        const fechaInput = form.querySelector('input[name="fecha"]');
        if (fechaInput) {
            const ahora = new Date();
            fechaInput.value = formatearFechaParaInput(ahora.toISOString());
        }
    }
}

/**
 * Configurar toggle del formulario
 */
function configurarToggleFormulario() {
    const toggle = document.getElementById('toggleFormulario');
    const formulario = document.getElementById('formularioVenta');
    
    if (toggle && formulario) {
        toggle.addEventListener('change', function() {
            if (this.checked) {
                formulario.classList.add('mostrar');
                // Establecer fecha actual al mostrar
                const fechaInput = formulario.querySelector('input[name="fecha"]');
                if (fechaInput && !fechaInput.value) {
                    const ahora = new Date();
                    fechaInput.value = formatearFechaParaInput(ahora.toISOString());
                }
            } else {
                formulario.classList.remove('mostrar');
            }
        });
    }
}

/**
 * Configurar eventos de formularios
 */
function configurarEventosFormularios() {
    // Formulario de nueva venta
    const ventaForm = document.getElementById('ventaForm');
    if (ventaForm) {
        ventaForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            crearVenta(formData);
        });
    }

    // Formulario de edición
    const editVentaForm = document.getElementById('editVentaForm');
    if (editVentaForm) {
        editVentaForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            actualizarVenta(formData);
        });
    }
}

/**
 * Función para cerrar sesión
 */
function logout() {
    if (confirm('¿Está seguro de que desea cerrar la sesión?')) {
        window.location.href = 'logout.php';
    }
}

// ======================================================================
// INICIALIZACIÓN DE LA APLICACIÓN
// ======================================================================

/**
 * Inicializar la aplicación cuando el DOM esté listo
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando Sistema de Ventas...');
    
    try {
        // Configurar elementos de la interfaz
        configurarToggleFormulario();
        configurarEventosFormularios();
        
        // Establecer fecha actual en el formulario
        const fechaInput = document.querySelector('#ventaForm input[name="fecha"]');
        if (fechaInput) {
            const ahora = new Date();
            fechaInput.value = formatearFechaParaInput(ahora.toISOString());
        }
        
        // Cargar datos iniciales
        actualizarEstadisticas();
        cargarTablaVentas();
        
        console.log('Sistema de Ventas inicializado correctamente');
        
    } catch (error) {
        console.error('Error al inicializar la aplicación:', error);
        mostrarAlerta('Error al inicializar la aplicación', 'danger');
    }
});

// ======================================================================
// MANEJO DE ERRORES GLOBALES
// ======================================================================

// Manejar errores no capturados
window.addEventListener('error', function(e) {
    console.error('Error no capturado:', e.error);
    mostrarAlerta('Se ha producido un error inesperado', 'danger');
});

// Manejar promesas rechazadas no capturadas
window.addEventListener('unhandledrejection', function(e) {
    console.error('Promesa rechazada no manejada:', e.reason);
    e.preventDefault();
});

// ======================================================================
// EXPORTAR FUNCIONES GLOBALES
// ======================================================================

// Hacer funciones disponibles globalmente para uso en HTML
window.handleEdit = handleEdit;
window.handleDelete = handleDelete;
window.limpiarFormulario = limpiarFormulario;
window.logout = logout;