/**
 * SISTEMA DE ADMINISTRACIÓN - JAVASCRIPT
 * Funciones interactivas y validaciones del lado del cliente
 * Versión: 2.0.0
 * Soporte: ES6+ con compatibilidad legacy
 */

'use strict';

// ====================================
// CONFIGURACIÓN GLOBAL
// ====================================
const AdminConfig = {
    // Configuración de validación
    validation: {
        cedula: {
            min: 6,
            max: 15,
            pattern: /^[0-9]+$/
        },
        nombre: {
            min: 2,
            max: 100,
            pattern: /^[\p{L}\s'.-]+$/u
        },
        usuario: {
            min: 3,
            max: 50,
            pattern: /^[a-zA-Z0-9_]+$/
        },
        password: {
            min: 6,
            max: 255
        }
    },
    // Mensajes de error
    messages: {
        required: 'Este campo es obligatorio',
        cedula: {
            invalid: 'La cédula debe contener solo números',
            length: 'La cédula debe tener entre 6 y 15 dígitos'
        },
        nombre: {
            invalid: 'El nombre solo debe contener letras y espacios',
            length: 'El nombre debe tener al menos 2 caracteres'
        },
        usuario: {
            invalid: 'El usuario solo debe contener letras, números y guión bajo',
            length: 'El usuario debe tener al menos 3 caracteres'
        },
        password: {
            length: 'La contraseña debe tener al menos 8 caracteres'
        }
    },
    // Configuración de interfaz
    ui: {
        loadingDelay: 300,
        animationDuration: 300,
        tooltipDelay: 500
    }
};

// ====================================
// CLASE PRINCIPAL DE ADMINISTRACIÓN
// ====================================
class AdminManager {
    constructor() {
        this.isInitialized = false;
        this.validators = {};
        this.eventListeners = new Map();
        
        // Bind methods
        this.init = this.init.bind(this);
        this.setupEventListeners = this.setupEventListeners.bind(this);
        this.handleFormSubmission = this.handleFormSubmission.bind(this);
        
        // Auto-inicializar cuando el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', this.init);
        } else {
            this.init();
        }
    }
    
    /**
     * Inicializa el sistema de administración
     */
    init() {
        try {
            if (this.isInitialized) return;
            
            console.log('Inicializando sistema de administración...');
            
            // Configurar validadores
            this.setupValidators();
            
            // Configurar event listeners
            this.setupEventListeners();
            
            // Configurar tooltips
            this.setupTooltips();
            
            // Configurar formularios
            this.setupForms();
            
            // Configurar tablas
            this.setupTables();
            
            // Configurar modales
            this.setupModals();
            
            // Marcar como inicializado
            this.isInitialized = true;
            
            console.log('Sistema de administración inicializado correctamente');
            
        } catch (error) {
            console.error('Error al inicializar el sistema:', error);
            this.showNotification('Error al cargar el sistema', 'error');
        }
    }
    
    /**
     * Configura los validadores de formularios
     */
    setupValidators() {
        const { validation, messages } = AdminConfig;
        
        this.validators = {
            cedula: (value) => {
                if (!value) return messages.required;
                if (!validation.cedula.pattern.test(value)) return messages.cedula.invalid;
                if (value.length < validation.cedula.min || value.length > validation.cedula.max) {
                    return messages.cedula.length;
                }
                return null;
            },
            
            nombre: (value) => {
                if (!value) return messages.required;
                if (!validation.nombre.pattern.test(value)) return messages.nombre.invalid;
                if (value.trim().length < validation.nombre.min) return messages.nombre.length;
                return null;
            },
            
            usuario: (value) => {
                if (!value) return messages.required;
                if (!validation.usuario.pattern.test(value)) return messages.usuario.invalid;
                if (value.length < validation.usuario.min) return messages.usuario.length;
                return null;
            },
            
            password: (value, required = true) => {
                if (!value && required) return messages.required;
                if (value && value.length < validation.password.min) return messages.password.length;
                return null;
            },
            
            modulo: (value) => {
                const validModules = ['Vendedor', 'Agendamiento', 'Aprovisionamiento'];
                if (!value) return messages.required;
                if (!validModules.includes(value)) return 'Módulo no válido';
                return null;
            }
        };
    }
    
    /**
     * Configura todos los event listeners
     */
    setupEventListeners() {
        // Listeners de formularios
        this.addEventListenerSafely('formCrear', 'submit', this.handleFormSubmission);
        this.addEventListenerSafely('formEditar', 'submit', this.handleFormSubmission);
        
        // Listeners de botones de contraseña
        this.addEventListenerSafely('togglePassword', 'click', this.togglePasswordVisibility);
        this.addEventListenerSafely('toggleEditPassword', 'click', this.togglePasswordVisibility);
        
        // Listeners de validación en tiempo real
        this.setupRealTimeValidation();
        
        // Listeners de navegación por tabs
        this.setupTabNavigation();
        
        // Listeners de acciones de tabla
        this.setupTableActions();
        
        // Listener para cerrar alertas automáticamente
        this.setupAutoCloseAlerts();
        
        // Listener para manejar el redimensionamiento de ventana
        this.addEventListenerSafely(window, 'resize', this.debounce(this.handleResize, 250));
        
        // Listener para manejar teclas de escape
        this.addEventListenerSafely(document, 'keydown', this.handleKeydown);
    }
    
    /**
     * Añade un event listener de forma segura
     */
    addEventListenerSafely(elementOrId, event, handler, options = {}) {
        try {
            const element = typeof elementOrId === 'string' ? 
                document.getElementById(elementOrId) : elementOrId;
                
            if (!element) return;
            
            // Remover listener anterior si existe
            const key = `${element.id || element.tagName}-${event}`;
            if (this.eventListeners.has(key)) {
                element.removeEventListener(event, this.eventListeners.get(key), options);
            }
            
            // Añadir nuevo listener
            element.addEventListener(event, handler, options);
            this.eventListeners.set(key, handler);
            
        } catch (error) {
            console.warn('Error al añadir event listener:', error);
        }
    }
    
    /**
     * Configura la validación en tiempo real
     */
    setupRealTimeValidation() {
        const inputs = document.querySelectorAll('.needs-validation input, .needs-validation select');
        
        inputs.forEach(input => {
            // Validar al perder el foco
            this.addEventListenerSafely(input, 'blur', (e) => {
                this.validateField(e.target);
            });
            
            // Validar mientras se escribe (con debounce)
            this.addEventListenerSafely(input, 'input', this.debounce((e) => {
                this.validateField(e.target);
            }, 300));
        });
    }
    
    /**
     * Valida un campo individual
     */
    validateField(field) {
        if (!field || !field.name) return true;
        
        const validator = this.validators[field.name];
        if (!validator) return true;
        
        const isRequired = field.hasAttribute('required');
        const errorMessage = validator(field.value, isRequired);
        
        // Limpiar estados anteriores
        field.classList.remove('is-valid', 'is-invalid');
        
        const feedbackElement = field.parentNode.querySelector('.invalid-feedback') || 
                               field.nextElementSibling?.classList.contains('invalid-feedback') ? 
                               field.nextElementSibling : null;
        
        if (errorMessage) {
            field.classList.add('is-invalid');
            if (feedbackElement) {
                feedbackElement.textContent = errorMessage;
            }
            return false;
        } else {
            field.classList.add('is-valid');
            return true;
        }
    }
    
    /**
     * Maneja el envío de formularios
     */
    handleFormSubmission(event) {
        event.preventDefault();
        
        const form = event.target;
        const isValid = this.validateForm(form);
        
        if (!isValid) {
            this.showNotification('Por favor, corrija los errores en el formulario', 'warning');
            return false;
        }
        
        // Mostrar loading
        this.showFormLoading(form, true);
        
        // Simular delay y enviar
        setTimeout(() => {
            form.submit();
        }, AdminConfig.ui.loadingDelay);
        
        return false;
    }
    
    /**
     * Valida un formulario completo
     */
    validateForm(form) {
        if (!form) return false;
        
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        // Marcar el formulario como validado
        form.classList.add('was-validated');
        
        return isValid;
    }
    
    /**
     * Muestra/oculta el estado de carga en formularios
     */
    showFormLoading(form, show = true) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (!submitBtn) return;
        
        const spinner = submitBtn.querySelector('.spinner-border');
        const btnText = submitBtn.querySelector('i:not(.spinner-border)');
        
        if (show) {
            submitBtn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (btnText) btnText.style.opacity = '0.7';
        } else {
            submitBtn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (btnText) btnText.style.opacity = '1';
        }
    }
    
    /**
     * Alterna la visibilidad de las contraseñas
     */
    togglePasswordVisibility(event) {
        event.preventDefault();
        
        const button = event.target.closest('button');
        const input = button.parentElement.querySelector('input[type="password"], input[type="text"]');
        const icon = button.querySelector('i');
        
        if (!input || !icon) return;
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
            button.setAttribute('aria-label', 'Ocultar contraseña');
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
            button.setAttribute('aria-label', 'Mostrar contraseña');
        }
        
        // Añadir feedback visual
        button.classList.add('btn-success');
        setTimeout(() => {
            button.classList.remove('btn-success');
        }, 200);
    }
    
    /**
     * Configura la navegación por tabs
     */
    setupTabNavigation() {
        const tabButtons = document.querySelectorAll('[data-bs-toggle="pill"]');
        
        tabButtons.forEach(button => {
            this.addEventListenerSafely(button, 'shown.bs.tab', (e) => {
                const targetTab = e.target.getAttribute('data-bs-target');
                this.onTabChange(targetTab);
            });
        });
    }
    
    /**
     * Maneja el cambio de tabs
     */
    onTabChange(targetTab) {
        // Lógica específica para cada tab
        switch(targetTab) {
            case '#listar-panel':
                this.refreshUserTable();
                break;
            case '#crear-panel':
                this.resetCreateForm();
                break;
        }
    }
    
    /**
     * Actualiza la tabla de usuarios
     */
    refreshUserTable() {
        const table = document.getElementById('tablaUsuarios');
        if (table) {
            // Añadir clase de carga
            table.classList.add('table-loading');
            
            // Simular actualización (en implementación real sería una llamada AJAX)
            setTimeout(() => {
                table.classList.remove('table-loading');
            }, 1000);
        }
    }
    
    /**
     * Resetea el formulario de creación
     */
    resetCreateForm() {
        const form = document.getElementById('formCrear');
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
            
            // Limpiar estados de validación
            const inputs = form.querySelectorAll('.form-control, .form-select');
            inputs.forEach(input => {
                input.classList.remove('is-valid', 'is-invalid');
            });
        }
    }
    
    /**
     * Configura las acciones de tabla
     */
    setupTableActions() {
        // Los botones de acción se manejan con onclick inline
        // Aquí podríamos añadir funcionalidad adicional como confirmaciones
    }
    
    /**
     * Configura tooltips
     */
    setupTooltips() {
        try {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    delay: { show: AdminConfig.ui.tooltipDelay, hide: 100 }
                });
            });
        } catch (error) {
            console.warn('Error configurando tooltips:', error);
        }
    }
    
    /**
     * Configura formularios
     */
    setupForms() {
        // Prevenir envío múltiple
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            let isSubmitting = false;
            
            this.addEventListenerSafely(form, 'submit', (e) => {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
                
                // Resetear después de un tiempo
                setTimeout(() => {
                    isSubmitting = false;
                }, 3000);
            });
        });
    }
    
    /**
     * Configura tablas
     */
    setupTables() {
        const tables = document.querySelectorAll('.table');
        tables.forEach(table => {
            // Añadir clases para mejor UX
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 50}ms`;
            });
        });
    }
    
    /**
     * Configura modales
     */
    setupModals() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            // Limpiar formulario al cerrar modal
            modal.addEventListener('hidden.bs.modal', () => {
                const form = modal.querySelector('form');
                if (form) {
                    form.classList.remove('was-validated');
                }
            });
        });
    }
    
    /**
     * Configura el auto-cierre de alertas
     */
    setupAutoCloseAlerts() {
        const alerts = document.querySelectorAll('.alert[role="alert"]');
        alerts.forEach(alert => {
            if (!alert.querySelector('.btn-close')) return;
            
            // Auto-cerrar después de 5 segundos
            setTimeout(() => {
                if (alert.classList.contains('show')) {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) closeBtn.click();
                }
            }, 5000);
        });
    }
    
    /**
     * Maneja el redimensionamiento de ventana
     */
    handleResize() {
        // Ajustar tablas responsivas
        const responsiveTables = document.querySelectorAll('.table-responsive');
        responsiveTables.forEach(table => {
            if (window.innerWidth < 768) {
                table.classList.add('table-sm');
            } else {
                table.classList.remove('table-sm');
            }
        });
    }
    
    /**
     * Maneja teclas de escape
     */
    handleKeydown(event) {
        if (event.key === 'Escape') {
            // Cerrar modales abiertos
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                const closeBtn = openModal.querySelector('.btn-close, [data-bs-dismiss="modal"]');
                if (closeBtn) closeBtn.click();
            }
        }
    }
    
    /**
     * Muestra notificaciones
     */
    showNotification(message, type = 'info', duration = 4000) {
        const container = document.body;
        const notification = document.createElement('div');
        
        const typeClasses = {
            success: 'alert-success',
            error: 'alert-danger',
            warning: 'alert-warning',
            info: 'alert-info'
        };
        
        notification.className = `alert ${typeClasses[type]} alert-dismissible fade show position-fixed`;
        notification.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        notification.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        container.appendChild(notification);
        
        // Auto-remover
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 150);
            }
        }, duration);
    }
    
    /**
     * Función debounce para optimizar eventos
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Destructor para limpiar recursos
     */
    destroy() {
        // Remover todos los event listeners
        this.eventListeners.forEach((handler, key) => {
            const [elementId, eventType] = key.split('-');
            const element = document.getElementById(elementId);
            if (element) {
                element.removeEventListener(eventType, handler);
            }
        });
        
        this.eventListeners.clear();
        this.isInitialized = false;
    }
}

// ====================================
// FUNCIONES GLOBALES (compatibilidad)
// ====================================

/**
 * Confirma eliminación de usuario
 */
function confirmarEliminacion(cedula, nombre) {
    const modal = document.getElementById('modalEliminar');
    if (!modal) return;
    
    // Actualizar contenido del modal
    const nombreSpan = document.getElementById('nombreUsuarioEliminar');
    const cedulaSpan = document.getElementById('cedulaUsuarioEliminar');
    const confirmarBtn = document.getElementById('confirmarEliminarBtn');
    
    if (nombreSpan) nombreSpan.textContent = nombre;
    if (cedulaSpan) cedulaSpan.textContent = cedula;
    if (confirmarBtn) {
        confirmarBtn.href = `?eliminar=${encodeURIComponent(cedula)}`;
    }
    
    // Mostrar modal
    try {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    } catch (error) {
        console.error('Error mostrando modal:', error);
        // Fallback: confirmación nativa
        if (confirm(`¿Está seguro de eliminar al usuario ${nombre} (${cedula})?`)) {
            window.location.href = `?eliminar=${encodeURIComponent(cedula)}`;
        }
    }
}

/**
 * Actualiza los validadores con las nuevas reglas para email
 */
function updateValidatorsForEmail() {
    if (window.adminManager && window.adminManager.validators) {
        // Actualizar validator de email (reemplaza usuario)
        window.adminManager.validators.email = (value) => {
            if (!value) return AdminConfig.messages.required;
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                return 'El correo electrónico no es válido';
            }
            
            if (value.length > 100) {
                return 'El correo electrónico no debe exceder 100 caracteres';
            }
            
            return null;
        };
        
        // Remover validator de usuario (ya no se usa)
        if (window.adminManager.validators.usuario) {
            delete window.adminManager.validators.usuario;
        }
    }
}

// ====================================
// INICIALIZACIÓN
// ====================================

// Crear instancia global
window.adminManager = new AdminManager();

// Actualizar validadores para email
updateValidatorsForEmail();

// Funciones de utilidad global
window.AdminUtils = {
    formatDate: (dateString) => {
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (error) {
            return dateString;
        }
    },
    
    validateEmail: (email) => {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    sanitizeHtml: (str) => {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },
    
    showLoading: (show = true) => {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.toggle('show', show);
        }
    }
};

// Log de inicialización
console.log('Sistema de administración cargado correctamente');
console.log('Versión: 1.0.0 - Con soporte para correo electrónico');