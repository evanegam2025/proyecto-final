/**
 * SISTEMA DE ADMINISTRACION - JAVASCRIPT
 * Version: 2.0 - Optimizado y sin problemas de carga
 * Codificacion: UTF-8
 */

'use strict';

const AdminConfig = {
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
        email: {
            pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
            max: 100
        },
        password: {
            min: 8,
            max: 255
        }
    },
    messages: {
        required: 'Este campo es obligatorio',
        cedula: {
            invalid: 'La cedula debe contener solo numeros',
            length: 'La cedula debe tener entre 6 y 15 digitos'
        },
        nombre: {
            invalid: 'El nombre solo debe contener letras y espacios',
            length: 'El nombre debe tener al menos 2 caracteres'
        },
        email: {
            invalid: 'El correo electronico no es valido',
            length: 'El correo no debe exceder 100 caracteres'
        },
        password: {
            length: 'La contrasena debe tener al menos 8 caracteres'
        }
    }
};

class AdminManager {
    constructor() {
        this.isInitialized = false;
        this.validators = {};
        this.eventListeners = new Map();
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }
    
    init() {
        try {
            if (this.isInitialized) return;
            
            console.log('Inicializando sistema...');
            
            this.hideLoadingOverlay();
            this.setupValidators();
            this.setupEventListeners();
            this.setupTooltips();
            this.setupForms();
            this.setupModals();
            this.setupAutoCloseAlerts();
            
            this.isInitialized = true;
            console.log('Sistema inicializado correctamente');
            
        } catch (error) {
            console.error('Error al inicializar:', error);
            this.hideLoadingOverlay();
        }
    }
    
    hideLoadingOverlay() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
            overlay.classList.remove('show');
        }
    }
    
    setupValidators() {
        const v = AdminConfig.validation;
        const m = AdminConfig.messages;
        
        this.validators = {
            cedula: (value) => {
                if (!value) return m.required;
                if (!v.cedula.pattern.test(value)) return m.cedula.invalid;
                if (value.length < v.cedula.min || value.length > v.cedula.max) {
                    return m.cedula.length;
                }
                return null;
            },
            
            nombre: (value) => {
                if (!value) return m.required;
                if (!v.nombre.pattern.test(value)) return m.nombre.invalid;
                if (value.trim().length < v.nombre.min) return m.nombre.length;
                return null;
            },
            
            email: (value) => {
                if (!value) return m.required;
                if (!v.email.pattern.test(value)) return m.email.invalid;
                if (value.length > v.email.max) return m.email.length;
                return null;
            },
            
            contrasena: (value, required) => {
                if (required === undefined) required = true;
                if (!value && required) return m.required;
                if (value && value.length < v.password.min) return m.password.length;
                return null;
            },
            
            modulo: (value) => {
                const validModules = ['Administrador', 'Vendedor', 'Agendamiento', 'Aprovisionamiento'];
                if (!value) return m.required;
                if (!validModules.includes(value)) return 'Modulo no valido';
                return null;
            }
        };
    }
    
    setupEventListeners() {
        this.addListener('formCrear', 'submit', (e) => this.handleFormSubmit(e));
        this.addListener('formEditar', 'submit', (e) => this.handleFormSubmit(e));
        this.addListener('togglePassword', 'click', (e) => this.togglePassword(e));
        this.addListener('toggleEditPassword', 'click', (e) => this.togglePassword(e));
        
        this.setupFieldValidation();
        this.setupTabNavigation();
    }
    
    addListener(elementId, eventName, handler) {
        try {
            const element = typeof elementId === 'string' ? 
                document.getElementById(elementId) : elementId;
                
            if (!element) return;
            
            element.addEventListener(eventName, handler);
            this.eventListeners.set(elementId + '-' + eventName, handler);
            
        } catch (error) {
            console.warn('Error al anadir listener:', error);
        }
    }
    
    setupFieldValidation() {
        const inputs = document.querySelectorAll('.needs-validation input, .needs-validation select');
        
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            
            input.addEventListener('input', () => {
                if (input.classList.contains('is-invalid') || input.classList.contains('is-valid')) {
                    this.validateField(input);
                }
            });
        });
    }
    
    validateField(field) {
        if (!field || !field.name) return true;
        
        const validator = this.validators[field.name];
        if (!validator) return true;
        
        const isRequired = field.hasAttribute('required');
        const errorMessage = validator(field.value, isRequired);
        
        field.classList.remove('is-valid', 'is-invalid');
        
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        
        if (errorMessage) {
            field.classList.add('is-invalid');
            if (feedback) feedback.textContent = errorMessage;
            return false;
        } else if (field.value) {
            field.classList.add('is-valid');
        }
        
        return true;
    }
    
    handleFormSubmit(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const form = event.target;
        const isValid = this.validateForm(form);
        
        if (!isValid) {
            this.showAlert('Por favor, corrija los errores en el formulario', 'warning');
            
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            return false;
        }
        
        this.showFormLoading(form, true);
        
        setTimeout(() => {
            form.submit();
        }, 300);
        
        return false;
    }
    
    validateForm(form) {
        if (!form) return false;
        
        const inputs = form.querySelectorAll('input, select, textarea');
        let isValid = true;
        
        inputs.forEach(input => {
            if (input.hasAttribute('required') || input.value) {
                if (!this.validateField(input)) {
                    isValid = false;
                }
            }
        });
        
        form.classList.add('was-validated');
        
        return isValid;
    }
    
    showFormLoading(form, show) {
        const btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        
        const spinner = btn.querySelector('.spinner-border');
        const icon = btn.querySelector('i.bi');
        
        if (show) {
            btn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (icon && !icon.classList.contains('spinner-border')) {
                icon.style.opacity = '0.5';
            }
        } else {
            btn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (icon) icon.style.opacity = '1';
        }
    }
    
    togglePassword(event) {
        event.preventDefault();
        
        const button = event.target.closest('button');
        if (!button) return;
        
        const input = button.parentElement.querySelector('input');
        const icon = button.querySelector('i');
        
        if (!input || !icon) return;
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
    
    setupTabNavigation() {
        const tabs = document.querySelectorAll('[data-bs-toggle="pill"]');
        
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                const target = e.target.getAttribute('data-bs-target');
                if (target === '#crear-panel') {
                    this.resetCreateForm();
                }
            });
        });
    }
    
    resetCreateForm() {
        const form = document.getElementById('formCrear');
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
            
            const inputs = form.querySelectorAll('.form-control, .form-select');
            inputs.forEach(input => {
                input.classList.remove('is-valid', 'is-invalid');
            });
        }
    }
    
    setupTooltips() {
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltips.forEach(el => new bootstrap.Tooltip(el));
            }
        } catch (error) {
            console.warn('Error configurando tooltips:', error);
        }
    }
    
    setupForms() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.setAttribute('novalidate', 'novalidate');
        });
    }
    
    setupModals() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('hidden.bs.modal', () => {
                const form = modal.querySelector('form');
                if (form) {
                    form.classList.remove('was-validated');
                    const inputs = form.querySelectorAll('.form-control, .form-select');
                    inputs.forEach(input => {
                        input.classList.remove('is-valid', 'is-invalid');
                    });
                }
            });
        });
    }
    
    setupAutoCloseAlerts() {
        const alerts = document.querySelectorAll('.alert[role="alert"]');
        alerts.forEach(alert => {
            if (!alert.querySelector('.btn-close')) return;
            
            setTimeout(() => {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn && alert.parentElement) closeBtn.click();
            }, 5000);
        });
    }
    
    showAlert(message, type) {
        const container = document.body;
        const alert = document.createElement('div');
        
        const classes = {
            success: 'alert-success',
            error: 'alert-danger',
            warning: 'alert-warning',
            info: 'alert-info'
        };
        
        const icons = {
            success: 'check-circle-fill',
            error: 'exclamation-triangle-fill',
            warning: 'exclamation-triangle-fill',
            info: 'info-circle-fill'
        };
        
        alert.className = 'alert ' + classes[type] + ' alert-dismissible fade show position-fixed';
        alert.style.cssText = 'top:80px;right:20px;z-index:9999;max-width:400px;box-shadow:0 4px 12px rgba(0,0,0,0.15)';
        alert.setAttribute('role', 'alert');
        
        const icon = document.createElement('i');
        icon.className = 'bi bi-' + icons[type] + ' me-2';
        
        const text = document.createTextNode(message);
        
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn-close';
        closeBtn.setAttribute('data-bs-dismiss', 'alert');
        
        alert.appendChild(icon);
        alert.appendChild(text);
        alert.appendChild(closeBtn);
        
        container.appendChild(alert);
        
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 4000);
    }
}

function confirmarEliminacion(cedula, nombre) {
    const modal = document.getElementById('modalEliminar');
    if (!modal) return;
    
    const nombreSpan = document.getElementById('nombreUsuarioEliminar');
    const cedulaSpan = document.getElementById('cedulaUsuarioEliminar');
    const confirmarBtn = document.getElementById('confirmarEliminarBtn');
    
    if (nombreSpan) nombreSpan.textContent = nombre;
    if (cedulaSpan) cedulaSpan.textContent = cedula;
    if (confirmarBtn) {
        confirmarBtn.href = '?eliminar=' + encodeURIComponent(cedula);
    }
    
    try {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    } catch (error) {
        if (confirm('Esta seguro de eliminar al usuario ' + nombre + '?')) {
            window.location.href = '?eliminar=' + encodeURIComponent(cedula);
        }
    }
}

window.adminManager = new AdminManager();

console.log('Sistema cargado correctamente - v2.0');