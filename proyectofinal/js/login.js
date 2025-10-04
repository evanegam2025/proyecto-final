/**
 * LOGIN.JS - Sistema de Login con Validación y Timeout
 * Versión: 1.0
 * Autor: SVI
 * Descripción: Manejo de formulario de login, validaciones y timeout de sesión
 */

// ===== VARIABLES GLOBALES =====
let loginForm;
let togglePasswordBtn;
let loadingSpinner;
let loginBtn;
let usuarioInput;
let contrasenaInput;

// Configuración de timeout (10 minutos)
const SESSION_TIMEOUT = 10 * 60 * 1000; // 10 minutos en milisegundos
let sessionTimer;
let warningTimer;
let lastActivity;

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    initializeElements();
    initializeEventListeners();
    initializeValidation();
    setupSessionTimeout();
    
    // Focus automático en el campo usuario
    if (usuarioInput && !usuarioInput.value) {
        usuarioInput.focus();
    }
});

// ===== INICIALIZACIÓN DE ELEMENTOS =====
function initializeElements() {
    loginForm = document.getElementById('loginForm');
    togglePasswordBtn = document.getElementById('togglePassword');
    loadingSpinner = document.getElementById('loadingSpinner');
    loginBtn = document.getElementById('loginBtn');
    usuarioInput = document.getElementById('usuario');
    contrasenaInput = document.getElementById('contrasena');
}

// ===== CONFIGURACIÓN DE EVENT LISTENERS =====
function initializeEventListeners() {
    // Toggle password visibility
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', togglePasswordVisibility);
    }

    // Form submission
    if (loginForm) {
        loginForm.addEventListener('submit', handleFormSubmission);
    }

    // Input validation en tiempo real
    if (usuarioInput) {
        usuarioInput.addEventListener('input', validateUsuarioField);
        usuarioInput.addEventListener('blur', validateUsuarioField);
        usuarioInput.addEventListener('keypress', handleUsuarioKeypress);
    }

    if (contrasenaInput) {
        contrasenaInput.addEventListener('input', validateContrasenaField);
        contrasenaInput.addEventListener('blur', validateContrasenaField);
        contrasenaInput.addEventListener('keypress', handlePasswordKeypress);
    }

    // Detectar actividad del usuario para timeout
    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(event => {
        document.addEventListener(event, resetActivityTimer, true);
    });

    // Manejo de visibilidad de la página
    document.addEventListener('visibilitychange', handleVisibilityChange);

    // Prevenir copiar/pegar en campos sensibles (opcional)
    if (contrasenaInput) {
        contrasenaInput.addEventListener('paste', function(e) {
            // Permitir paste pero limpiar después de un delay
            setTimeout(() => {
                validateContrasenaField();
            }, 100);
        });
    }
}

// ===== TOGGLE PASSWORD VISIBILITY =====
function togglePasswordVisibility() {
    const type = contrasenaInput.getAttribute('type') === 'password' ? 'text' : 'password';
    const icon = togglePasswordBtn.querySelector('i');
    
    contrasenaInput.setAttribute('type', type);
    
    if (type === 'password') {
        icon.className = 'bi bi-eye';
        togglePasswordBtn.setAttribute('aria-label', 'Mostrar contraseña');
    } else {
        icon.className = 'bi bi-eye-slash';
        togglePasswordBtn.setAttribute('aria-label', 'Ocultar contraseña');
    }
}

// ===== VALIDACIÓN DE CAMPOS =====
function validateUsuarioField() {
    const value = usuarioInput.value.trim();
    const isValid = validateUsuario(value);
    
    updateFieldValidation(usuarioInput, isValid, 'Usuario válido', 'Usuario debe tener entre 3 y 50 caracteres y solo contener letras, números, puntos, guiones y @');
    
    return isValid;
}

function validateContrasenaField() {
    const value = contrasenaInput.value;
    const isValid = validateContrasena(value);
    
    updateFieldValidation(contrasenaInput, isValid, 'Contraseña válida', 'La contraseña debe tener al menos 6 caracteres');
    
    return isValid;
}

function validateUsuario(usuario) {
    if (!usuario || usuario.length < 3 || usuario.length > 50) {
        return false;
    }
    
    // Permitir letras, números, puntos, guiones, guiones bajos y @
    const pattern = /^[a-zA-Z0-9._@-]+$/;
    return pattern.test(usuario);
}

function validateContrasena(contrasena) {
    return contrasena && contrasena.length >= 6;
}

function updateFieldValidation(field, isValid, validMessage, invalidMessage) {
    const feedback = field.parentNode.querySelector('.invalid-feedback') || 
                    field.parentNode.querySelector('.valid-feedback');
    
    field.classList.remove('is-valid', 'is-invalid');
    
    if (field.value.trim() !== '') {
        if (isValid) {
            field.classList.add('is-valid');
            if (feedback) {
                feedback.textContent = validMessage;
                feedback.className = 'valid-feedback';
            }
        } else {
            field.classList.add('is-invalid');
            if (feedback) {
                feedback.textContent = invalidMessage;
                feedback.className = 'invalid-feedback';
            }
        }
    }
}

// ===== MANEJO DE TECLADO =====
function handleUsuarioKeypress(e) {
    // Prevenir caracteres especiales no permitidos
    const allowedChars = /[a-zA-Z0-9._@-]/;
    const char = String.fromCharCode(e.which);
    
    if (!allowedChars.test(char) && !isControlKey(e)) {
        e.preventDefault();
        showTemporaryTooltip(usuarioInput, 'Solo se permiten letras, números, puntos, guiones y @');
    }
}

function handlePasswordKeypress(e) {
    // Enter key submits form
    if (e.key === 'Enter') {
        e.preventDefault();
        handleFormSubmission(e);
    }
}

function isControlKey(e) {
    // Permitir teclas de control (Backspace, Delete, Tab, Enter, etc.)
    return e.which === 8 || e.which === 9 || e.which === 13 || e.which === 46 || 
           e.which === 37 || e.which === 39 || e.which === 38 || e.which === 40 ||
           e.ctrlKey || e.altKey;
}

// ===== MANEJO DE FORMULARIO =====
function handleFormSubmission(e) {
    e.preventDefault();
    
    // Validar campos
    const isUsuarioValid = validateUsuarioField();
    const isContrasenaValid = validateContrasenaField();
    
    if (!isUsuarioValid || !isContrasenaValid) {
        loginForm.classList.add('was-validated');
        showAlert('Por favor, corrija los errores en el formulario', 'danger');
        
        // Focus en el primer campo inválido
        const firstInvalidField = loginForm.querySelector('.is-invalid');
        if (firstInvalidField) {
            firstInvalidField.focus();
        }
        
        return false;
    }
    
    // Mostrar loading
    showLoading(true);
    
    // Simular validación adicional antes de envío
    setTimeout(() => {
        // Enviar formulario
        loginForm.submit();
    }, 500);
}

// ===== INICIALIZACIÓN DE VALIDACIÓN =====
function initializeValidation() {
    // Crear elementos de feedback si no existen
    [usuarioInput, contrasenaInput].forEach(input => {
        if (input && !input.parentNode.querySelector('.invalid-feedback')) {
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.parentNode.appendChild(feedback);
        }
    });
}

// ===== MANEJO DE LOADING =====
function showLoading(show) {
    if (loadingSpinner && loginBtn) {
        if (show) {
            loadingSpinner.classList.remove('d-none');
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Iniciando...';
        } else {
            loadingSpinner.classList.add('d-none');
            loginBtn.disabled = false;
            loginBtn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión';
        }
    }
}

// ===== SISTEMA DE ALERTAS =====
function showAlert(message, type = 'info', duration = 5000) {
    // Remover alertas existentes
    const existingAlerts = document.querySelectorAll('.alert:not([role="alert"])');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    
    const icon = getAlertIcon(type);
    
    alertDiv.innerHTML = `
        <i class="bi bi-${icon}"></i>
        ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    `;
    
    // Insertar antes del formulario
    const container = document.querySelector('.login-container');
    const form = document.getElementById('loginForm');
    container.insertBefore(alertDiv, form);
    
    // Auto-dismiss después de duration
    if (duration > 0) {
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, duration);
    }
}

function getAlertIcon(type) {
    const icons = {
        'success': 'check-circle',
        'danger': 'exclamation-triangle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// ===== TOOLTIP TEMPORAL =====
function showTemporaryTooltip(element, message, duration = 3000) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip-temp';
    tooltip.textContent = message;
    tooltip.style.cssText = `
        position: absolute;
        background: #dc3545;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        font-size: 0.875rem;
        z-index: 1000;
        animation: fadeInUp 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    // Posicionar tooltip
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + 'px';
    tooltip.style.top = (rect.bottom + 5) + 'px';
    
    document.body.appendChild(tooltip);
    
    setTimeout(() => {
        if (tooltip.parentNode) {
            tooltip.remove();
        }
    }, duration);
}

// ===== TIMEOUT DE SESIÓN =====
function setupSessionTimeout() {
    lastActivity = Date.now();
    resetSessionTimer();
}

function resetActivityTimer() {
    lastActivity = Date.now();
    resetSessionTimer();
}

function resetSessionTimer() {
    clearTimeout(sessionTimer);
    clearTimeout(warningTimer);
    
    // Warning 2 minutos antes del timeout
    warningTimer = setTimeout(showSessionWarning, SESSION_TIMEOUT - (2 * 60 * 1000));
    
    // Timeout completo
    sessionTimer = setTimeout(handleSessionTimeout, SESSION_TIMEOUT);
}

function showSessionWarning() {
    const warning = confirm('Su sesión expirará en 2 minutos por inactividad. ¿Desea continuar?');
    
    if (warning) {
        resetActivityTimer();
        showAlert('Sesión extendida', 'success', 3000);
    } else {
        handleSessionTimeout();
    }
}

function handleSessionTimeout() {
    alert('Su sesión ha expirado por inactividad. Será redirigido a la página de login.');
    window.location.href = 'logout.php';
}

function handleVisibilityChange() {
    if (document.hidden) {
        // Página oculta - pausar timers
        clearTimeout(sessionTimer);
        clearTimeout(warningTimer);
    } else {
        // Página visible - reanudar timers
        const inactiveTime = Date.now() - lastActivity;
        
        if (inactiveTime >= SESSION_TIMEOUT) {
            handleSessionTimeout();
        } else {
            resetSessionTimer();
        }
    }
}

// ===== UTILIDADES =====
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// ===== ACCESIBILIDAD =====
function announceToScreenReader(message) {
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', 'polite');
    announcement.setAttribute('aria-atomic', 'true');
    announcement.className = 'sr-only';
    announcement.textContent = message;
    
    document.body.appendChild(announcement);
    
    setTimeout(() => {
        document.body.removeChild(announcement);
    }, 1000);
}

// ===== MANEJO DE ERRORES GLOBALES =====
window.addEventListener('error', function(e) {
    console.error('Error en login.js:', e.error);
    showAlert('Ha ocurrido un error inesperado. Por favor, recargue la página.', 'danger');
});

// ===== VALIDACIÓN DE CONECTIVIDAD =====
function checkNetworkStatus() {
    if (!navigator.onLine) {
        showAlert('Sin conexión a internet. Verifique su conexión.', 'warning');
        loginBtn.disabled = true;
    } else {
        loginBtn.disabled = false;
    }
}

window.addEventListener('online', function() {
    showAlert('Conexión restaurada', 'success', 3000);
    loginBtn.disabled = false;
});

window.addEventListener('offline', function() {
    showAlert('Sin conexión a internet', 'warning');
    loginBtn.disabled = true;
});

// ===== PREVENCIÓN DE ATAQUES =====
function preventBruteForce() {
    const maxAttempts = 5;
    const blockTime = 15 * 60 * 1000; // 15 minutos
    
    let attempts = parseInt(sessionStorage.getItem('loginAttempts') || '0');
    const lastAttempt = parseInt(sessionStorage.getItem('lastLoginAttempt') || '0');
    
    // Reset attempts if block time has passed
    if (Date.now() - lastAttempt > blockTime) {
        attempts = 0;
        sessionStorage.removeItem('loginAttempts');
        sessionStorage.removeItem('lastLoginAttempt');
    }
    
    if (attempts >= maxAttempts) {
        const remainingTime = Math.ceil((blockTime - (Date.now() - lastAttempt)) / 1000 / 60);
        showAlert(`Demasiados intentos fallidos. Inténtelo de nuevo en ${remainingTime} minutos.`, 'danger');
        loginBtn.disabled = true;
        return false;
    }
    
    return true;
}

// ===== EXPORTAR FUNCIONES PARA USO GLOBAL =====
window.LoginManager = {
    showAlert,
    showLoading,
    resetActivityTimer,
    validateUsuario,
    validateContrasena
};

// ===== LOG DE DEBUGGING (SOLO EN DESARROLLO) =====
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    console.log('Login.js cargado correctamente');
    console.log('Timeout de sesión configurado:', SESSION_TIMEOUT / 1000 / 60, 'minutos');
}