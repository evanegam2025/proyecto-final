// ======================================================================
// CONSULTAS.JS - Sistema de Consulta de Cédulas - VERSIÓN MEJORADA
// ======================================================================

// Configuración global
const CONFIG = {
    API_URL: 'api/api_consultas.php',
    TIMEOUT: 10000,
    RETRY_ATTEMPTS: 2 // Reducido para evitar demasiados reintentos
};

// Variables globales de estado
let elements = {};
let currentRequest = null;
let retryCount = 0;
let isInitialized = false;

// ======================================================================
// INICIALIZACIÓN SEGURA DE ELEMENTOS DOM
// ======================================================================

function safeGetElement(id) {
    try {
        const element = document.getElementById(id);
        if (!element) {
            console.warn(`Elemento no encontrado: ${id}`);
        }
        return element;
    } catch (error) {
        console.error(`Error al obtener elemento ${id}:`, error);
        return null;
    }
}

function initializeElements() {
    try {
        console.log('Inicializando elementos DOM...');
        
        elements = {
            formConsulta: safeGetElement('form-consulta'),
            inputCedula: safeGetElement('cedula'),
            btnLimpiarInput: safeGetElement('btn-limpiar-input'),
            loadingSpinner: safeGetElement('loading-spinner'),
            resultadoDiv: safeGetElement('resultado'),
            formulariosDiv: safeGetElement('formularios-dinamicos'),
            nuevaConsultaContainer: safeGetElement('nueva-consulta-container'),
            btnNuevaConsulta: safeGetElement('btn-nueva-consulta'),
            searchIcon: safeGetElement('search-icon'),
            btnText: safeGetElement('btn-text')
        };

        // Crear elementos críticos si no existen
        if (!elements.loadingSpinner && elements.formConsulta) {
            const submitBtn = elements.formConsulta.querySelector('button[type="submit"]');
            if (submitBtn) {
                const spinner = document.createElement('span');
                spinner.id = 'loading-spinner';
                spinner.className = 'spinner-border spinner-border-sm d-none me-2';
                spinner.setAttribute('role', 'status');
                
                const firstChild = submitBtn.firstChild;
                if (firstChild) {
                    submitBtn.insertBefore(spinner, firstChild);
                    elements.loadingSpinner = spinner;
                    console.log('Elemento loading-spinner creado dinámicamente');
                }
            }
        }

        // Verificar elementos críticos
        const criticalElements = ['formConsulta', 'inputCedula', 'resultadoDiv'];
        const missingCritical = criticalElements.filter(key => !elements[key]);
        
        if (missingCritical.length > 0) {
            console.error('Elementos críticos faltantes:', missingCritical);
            showError('Error de inicialización: elementos DOM requeridos no encontrados.');
            return false;
        }

        console.log('Elementos DOM inicializados correctamente');
        return true;
        
    } catch (error) {
        console.error('Error en initializeElements:', error);
        return false;
    }
}

// ======================================================================
// FUNCIONES UTILITARIAS SEGURAS
// ======================================================================

function sanitizeInput(text) {
    try {
        if (typeof text !== 'string') {
            return '';
        }
        return text.trim().replace(/[^0-9]/g, '').substring(0, 15);
    } catch (error) {
        console.error('Error en sanitizeInput:', error);
        return '';
    }
}

function validarCedula(cedula) {
    try {
        const cedulaLimpia = sanitizeInput(cedula);
        
        if (!cedulaLimpia) {
            return { valid: false, message: 'La cédula es requerida.' };
        }
        
        if (cedulaLimpia.length < 6) {
            return { valid: false, message: 'La cédula debe tener al menos 6 dígitos.' };
        }
        
        if (cedulaLimpia.length > 15) {
            return { valid: false, message: 'La cédula no puede tener más de 15 dígitos.' };
        }
        
        return { valid: true, cedula: cedulaLimpia };
        
    } catch (error) {
        console.error('Error en validarCedula:', error);
        return { valid: false, message: 'Error en la validación de la cédula.' };
    }
}

function showError(message) {
    try {
        if (!elements.resultadoDiv) {
            console.error('No se puede mostrar error: resultadoDiv no disponible');
            alert(message); // Fallback para mostrar el error
            return;
        }
        
        elements.resultadoDiv.innerHTML = `
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="alert alert-danger fade-in" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Error:</strong> ${message}
                    </div>
                </div>
            </div>
        `;
        
        toggleNuevaConsulta(true);
        
    } catch (error) {
        console.error('Error en showError:', error);
        alert(message); // Fallback final
    }
}

function showLoading(message = 'Consultando...') {
    try {
        if (!elements.resultadoDiv) {
            console.log('Cargando:', message);
            return;
        }
        
        elements.resultadoDiv.innerHTML = `
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="alert alert-info fade-in" role="alert">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm me-3" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <span>${message}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
    } catch (error) {
        console.error('Error en showLoading:', error);
    }
}

function toggleNuevaConsulta(mostrar) {
    try {
        if (!elements.nuevaConsultaContainer) {
            return;
        }
        
        if (mostrar) {
            elements.nuevaConsultaContainer.classList.remove('d-none');
            elements.nuevaConsultaContainer.classList.add('fade-in');
        } else {
            elements.nuevaConsultaContainer.classList.add('d-none');
            elements.nuevaConsultaContainer.classList.remove('fade-in');
        }
        
    } catch (error) {
        console.error('Error en toggleNuevaConsulta:', error);
    }
}

function resetForm() {
    try {
        // Resetear spinner de carga
        if (elements.loadingSpinner) {
            elements.loadingSpinner.classList.add('d-none');
        }
        
        // Resetear botón de envío
        if (elements.formConsulta) {
            const submitBtn = elements.formConsulta.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
        
        // Limpiar estado de error del input
        if (elements.inputCedula) {
            elements.inputCedula.classList.remove('is-invalid');
        }
        
    } catch (error) {
        console.error('Error en resetForm:', error);
    }
}

function clearAll() {
    try {
        // Cancelar request actual
        if (currentRequest) {
            currentRequest.abort();
            currentRequest = null;
        }
        
        // Limpiar campos
        if (elements.inputCedula) {
            elements.inputCedula.value = '';
            elements.inputCedula.focus();
        }
        
        // Limpiar resultados
        if (elements.resultadoDiv) {
            elements.resultadoDiv.innerHTML = '';
        }
        
        if (elements.formulariosDiv) {
            elements.formulariosDiv.innerHTML = '';
        }
        
        toggleNuevaConsulta(false);
        resetForm();
        retryCount = 0;
        
    } catch (error) {
        console.error('Error en clearAll:', error);
    }
}

// ======================================================================
// FUNCIÓN DE CONSULTA MEJORADA
// ======================================================================

async function consultarCedula(cedula) {
    try {
        showLoading('Consultando estado de la cédula...');
        
        const url = `${CONFIG.API_URL}?action=consultar_cedula&cedula=${encodeURIComponent(cedula)}&_t=${Date.now()}`;
        
        // Crear AbortController para timeout
        const controller = new AbortController();
        currentRequest = controller;
        
        // Timeout
        const timeoutId = setTimeout(() => {
            controller.abort();
        }, CONFIG.TIMEOUT);
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        currentRequest = null;
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
        }
        
        // Verificar que la respuesta sea JSON válido
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('La respuesta del servidor no es JSON válido');
        }
        
        const data = await response.json();
        
        // Validar estructura de datos
        if (typeof data !== 'object' || data === null) {
            throw new Error('Respuesta del servidor inválida');
        }
        
        if (data.success) {
            mostrarResultado(data);
            retryCount = 0; // Reset en caso de éxito
        } else {
            const errorMessage = data.message || 'Error desconocido en la consulta';
            showError(errorMessage);
        }
        
    } catch (error) {
        currentRequest = null;
        console.error('Error en consultarCedula:', error);
        
        let errorMessage = 'Error de conexión. ';
        
        if (error.name === 'AbortError') {
            errorMessage = 'Operación cancelada por timeout o por el usuario.';
        } else if (error.message.includes('HTTP')) {
            errorMessage += 'El servidor no está disponible o hay un problema de configuración.';
        } else if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
            errorMessage += 'Verifique su conexión a internet.';
        } else if (error.message.includes('JSON')) {
            errorMessage += 'Respuesta del servidor inválida. Contacte al administrador.';
        } else {
            errorMessage += 'Intente nuevamente en unos momentos.';
        }
        
        // Reintentar solo en casos específicos
        if (retryCount < CONFIG.RETRY_ATTEMPTS && 
            (error.message.includes('Failed to fetch') || error.message.includes('NetworkError'))) {
            retryCount++;
            console.log(`Reintentando (${retryCount}/${CONFIG.RETRY_ATTEMPTS})...`);
            
            // Esperar antes de reintentar
            setTimeout(() => {
                consultarCedula(cedula);
            }, Math.pow(2, retryCount) * 1000);
            
        } else {
            showError(errorMessage);
            retryCount = 0;
        }
    }
}

function mostrarResultado(data) {
    try {
        if (!elements.resultadoDiv) {
            console.error('No se puede mostrar resultado: resultadoDiv no disponible');
            return;
        }
        
        // Determinar clase de alerta e icono
        let alertClass = 'alert-info';
        let iconClass = 'bi-info-circle-fill';
        
        switch(parseInt(data.codigo)) {
            case 1: 
                alertClass = 'alert-success'; 
                iconClass = 'bi-check-circle-fill'; 
                break;
            case 2: 
                alertClass = 'alert-warning'; 
                iconClass = 'bi-exclamation-triangle-fill'; 
                break;
            case 3: 
                alertClass = 'alert-info'; 
                iconClass = 'bi-calendar-check-fill'; 
                break;
            case 4: 
            case 5: 
                alertClass = 'alert-warning'; 
                iconClass = 'bi-arrow-repeat'; 
                break;
            case 6: 
                alertClass = 'alert-danger'; 
                iconClass = 'bi-x-circle-fill'; 
                break;
            case 7: 
                alertClass = 'alert-success'; 
                iconClass = 'bi-check-circle-fill'; 
                break;
        }
        
        // Información del cliente
        let nombreInfo = '';
        if (data.nombre && data.nombre !== 'No disponible' && data.nombre !== null) {
            nombreInfo = `
                <div class="mt-3 p-3 bg-light rounded">
                    <i class="bi bi-person-fill me-2"></i>
                    <strong>Cliente:</strong> ${data.nombre}
                </div>
            `;
        }
        
        elements.resultadoDiv.innerHTML = `
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-8">
                    <div class="alert ${alertClass} shadow-sm fade-in" role="alert">
                        <div class="d-flex align-items-start">
                            <i class="${iconClass} me-3 mt-1" style="font-size: 1.5rem;"></i>
                            <div class="flex-grow-1">
                                <h6 class="alert-heading mb-2">
                                    Estado de la Cédula ${data.cedula || 'N/A'}
                                </h6>
                                <p class="mb-0">${data.message || 'Sin mensaje'}</p>
                                ${nombreInfo}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        toggleNuevaConsulta(true);
        
        // Limpiar campo después de mostrar resultado
        setTimeout(() => {
            if (elements.inputCedula) {
                elements.inputCedula.value = '';
            }
        }, 500);
        
    } catch (error) {
        console.error('Error en mostrarResultado:', error);
        showError('Error al mostrar el resultado de la consulta.');
    }
}

// ======================================================================
// EVENT LISTENERS
// ======================================================================

function setupEventListeners() {
    try {
        // Formulario principal
        if (elements.formConsulta) {
            elements.formConsulta.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                try {
                    const cedulaRaw = elements.inputCedula ? elements.inputCedula.value.trim() : '';
                    const validacion = validarCedula(cedulaRaw);
                    
                    if (!validacion.valid) {
                        if (elements.inputCedula) {
                            elements.inputCedula.classList.add('is-invalid');
                            elements.inputCedula.focus();
                        }
                        showError(validacion.message);
                        return;
                    }
                    
                    // Limpiar estado de error
                    if (elements.inputCedula) {
                        elements.inputCedula.classList.remove('is-invalid');
                        elements.inputCedula.value = validacion.cedula;
                    }
                    
                    // Mostrar loading
                    if (elements.loadingSpinner) {
                        elements.loadingSpinner.classList.remove('d-none');
                    }
                    
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                    }
                    
                    // Limpiar resultados anteriores
                    if (elements.resultadoDiv) {
                        elements.resultadoDiv.innerHTML = '';
                    }
                    if (elements.formulariosDiv) {
                        elements.formulariosDiv.innerHTML = '';
                    }
                    
                    toggleNuevaConsulta(false);
                    
                    await consultarCedula(validacion.cedula);
                    
                } catch (error) {
                    console.error('Error en submit handler:', error);
                    showError('Error al procesar la consulta.');
                } finally {
                    resetForm();
                }
            });
        }
        
        // Botón limpiar
        if (elements.btnLimpiarInput) {
            elements.btnLimpiarInput.addEventListener('click', function() {
                if (elements.inputCedula) {
                    elements.inputCedula.value = '';
                    elements.inputCedula.classList.remove('is-invalid');
                    elements.inputCedula.focus();
                }
            });
        }
        
        // Botón nueva consulta
        if (elements.btnNuevaConsulta) {
            elements.btnNuevaConsulta.addEventListener('click', function() {
                clearAll();
            });
        }
        
        // Input de cédula
        if (elements.inputCedula) {
            elements.inputCedula.addEventListener('input', function(e) {
                const sanitized = sanitizeInput(e.target.value);
                if (e.target.value !== sanitized) {
                    e.target.value = sanitized;
                }
                e.target.classList.remove('is-invalid');
            });
            
            elements.inputCedula.addEventListener('keydown', function(e) {
                // Permitir teclas de control
                if ([8, 9, 27, 13, 46].includes(e.keyCode) ||
                    (e.ctrlKey && [65, 67, 86, 88].includes(e.keyCode))) {
                    return;
                }
                
                // Solo números
                if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && 
                    (e.keyCode < 96 || e.keyCode > 105)) {
                    e.preventDefault();
                }
            });
        }
        
        console.log('Event listeners configurados correctamente');
        
    } catch (error) {
        console.error('Error en setupEventListeners:', error);
    }
}

// ======================================================================
// INICIALIZACIÓN PRINCIPAL
// ======================================================================

document.addEventListener('DOMContentLoaded', function() {
    try {
        console.log('=== Iniciando Sistema de Consultas ===');
        
        // Inicializar elementos
        if (!initializeElements()) {
            console.error('Falló la inicialización de elementos DOM');
            return;
        }
        
        // Configurar event listeners
        setupEventListeners();
        
        // Enfocar campo de cédula
        if (elements.inputCedula) {
            elements.inputCedula.focus();
        }
        
        // Configurar manejo de errores globales
        window.addEventListener('error', function(e) {
            console.error('Error global capturado:', e.error);
            if (currentRequest) {
                currentRequest.abort();
                currentRequest = null;
            }
            resetForm();
        });
        
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Promesa rechazada capturada:', e.reason);
            e.preventDefault();
            
            if (currentRequest) {
                currentRequest.abort();
                currentRequest = null;
            }
            
            showError('Error en la comunicación con el servidor.');
            resetForm();
        });
        
        // Detectar cambios de conectividad
        window.addEventListener('online', function() {
            console.log('Conexión restablecida');
        });
        
        window.addEventListener('offline', function() {
            console.log('Conexión perdida');
            showError('Se perdió la conexión a internet.');
        });
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                clearAll();
            }
            
            if (e.key === 'Escape' && currentRequest) {
                currentRequest.abort();
                currentRequest = null;
                resetForm();
                showError('Operación cancelada.');
            }
        });
        
        isInitialized = true;
        console.log('=== Sistema de Consultas Inicializado Correctamente ===');
        
    } catch (error) {
        console.error('Error crítico en la inicialización:', error);
        alert('Error crítico al inicializar el sistema. Recargue la página.');
    }
});

// Función de debug para desarrollo
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    window.debugConsultas = function() {
        console.group('Debug Sistema Consultas');
        console.log('Inicializado:', isInitialized);
        console.log('Elementos:', elements);
        console.log('Request actual:', currentRequest);
        console.log('Retry count:', retryCount);
        console.groupEnd();
    };
}