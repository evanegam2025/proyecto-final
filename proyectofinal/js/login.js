/**
 * JAVASCRIPT DEL SISTEMA DE LOGIN
 * Archivo: js/login.js
 * Versión: 2.0
 * Codificación: UTF-8
 */

// ============================================
// ESPERAR A QUE EL DOM ESTÉ CARGADO
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // ============================================
    // CONSTANTES Y VARIABLES
    // ============================================
    const CONFIG = {
        MIN_USERNAME_LENGTH: 3,
        MAX_USERNAME_LENGTH: 50,
        MIN_PASSWORD_LENGTH: 8,
        ALERT_AUTO_CLOSE_TIME: 5000,
        VALIDATION_DELAY: 300
    };

    // Elementos del DOM
    const elements = {
        loginForm: document.getElementById('loginForm'),
        usuarioInput: document.getElementById('usuario'),
        contrasenaInput: document.getElementById('contrasena'),
        togglePassword: document.getElementById('togglePassword'),
        loginBtn: document.getElementById('loginBtn'),
        loadingSpinner: document.getElementById('loadingSpinner'),
        alerts: document.querySelectorAll('.alert')
    };

    // ============================================
    // FUNCIONES DE VALIDACIÓN
    // ============================================

    /**
     * Valida el formato del usuario
     * @param {string} value - Valor del campo usuario
     * @returns {boolean} - True si es válido
     */
    function validarUsuario(value) {
        const trimmedValue = value.trim();
        
        // Verificar longitud
        if (trimmedValue.length < CONFIG.MIN_USERNAME_LENGTH || 
            trimmedValue.length > CONFIG.MAX_USERNAME_LENGTH) {
            return false;
        }

        // Verificar formato (alfanumérico y caracteres permitidos)
        const regex = /^[a-zA-Z0-9._@-]+$/;
        return regex.test(trimmedValue);
    }

    /**
     * Valida la contraseña
     * @param {string} value - Valor del campo contraseña
     * @returns {boolean} - True si es válida
     */
    function validarContrasena(value) {
        return value.length >= CONFIG.MIN_PASSWORD_LENGTH;
    }

    /**
     * Muestra u oculta el feedback de validación
     * @param {HTMLElement} input - Campo de entrada
     * @param {boolean} isValid - Si es válido o no
     */
    function mostrarFeedback(input, isValid) {
        if (isValid) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
        }
    }

    /**
     * Limpia el feedback de validación
     * @param {HTMLElement} input - Campo de entrada
     */
    function limpiarFeedback(input) {
        input.classList.remove('is-invalid');
        input.classList.remove('is-valid');
    }

    // ============================================
    // VALIDACIÓN EN TIEMPO REAL DEL USUARIO
    // ============================================
    let usuarioTimeout;
    
    if (elements.usuarioInput) {
        elements.usuarioInput.addEventListener('input', function() {
            const value = this.value.trim();
            
            // Limpiar timeout anterior
            clearTimeout(usuarioTimeout);
            
            // Si está vacío, limpiar feedback
            if (value === '') {
                limpiarFeedback(this);
                return;
            }
            
            // Esperar un momento antes de validar
            usuarioTimeout = setTimeout(() => {
                const isValid = validarUsuario(value);
                
                // Solo mostrar feedback si es inválido
                if (!isValid) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.remove('is-valid');
                }
            }, CONFIG.VALIDATION_DELAY);
        });

        // Limpiar feedback al hacer focus
        elements.usuarioInput.addEventListener('focus', function() {
            if (this.value.trim() === '') {
                limpiarFeedback(this);
            }
        });
    }

    // ============================================
    // VALIDACIÓN EN TIEMPO REAL DE LA CONTRASEÑA
    // ============================================
    let contrasenaTimeout;
    
    if (elements.contrasenaInput) {
        elements.contrasenaInput.addEventListener('input', function() {
            const value = this.value;
            
            // Limpiar timeout anterior
            clearTimeout(contrasenaTimeout);
            
            // Si está vacío, limpiar feedback
            if (value === '') {
                limpiarFeedback(this);
                return;
            }
            
            // Esperar un momento antes de validar
            contrasenaTimeout = setTimeout(() => {
                const isValid = validarContrasena(value);
                
                // Solo mostrar feedback si es inválido
                if (!isValid) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.remove('is-valid');
                }
            }, CONFIG.VALIDATION_DELAY);
        });

        // Limpiar feedback al hacer focus
        elements.contrasenaInput.addEventListener('focus', function() {
            if (this.value === '') {
                limpiarFeedback(this);
            }
        });
    }

    // ============================================
    // TOGGLE MOSTRAR/OCULTAR CONTRASEÑA
    // ============================================
    if (elements.togglePassword && elements.contrasenaInput) {
        elements.togglePassword.addEventListener('click', function() {
            // Cambiar tipo de input
            const type = elements.contrasenaInput.getAttribute('type') === 'password' 
                ? 'text' 
                : 'password';
            elements.contrasenaInput.setAttribute('type', type);
            
            // Cambiar icono
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye');
                icon.classList.toggle('bi-eye-slash');
            }
            
            // Mantener el foco en el campo de contraseña
            elements.contrasenaInput.focus();
        });
    }

    // ============================================
    // VALIDACIÓN DEL FORMULARIO AL ENVIAR
    // ============================================
    if (elements.loginForm) {
        elements.loginForm.addEventListener('submit', function(e) {
            let isValid = true;
            let firstInvalidField = null;

            // Validar usuario
            if (elements.usuarioInput) {
                const usuarioValue = elements.usuarioInput.value.trim();
                
                if (usuarioValue === '' || !validarUsuario(usuarioValue)) {
                    elements.usuarioInput.classList.add('is-invalid');
                    isValid = false;
                    
                    if (!firstInvalidField) {
                        firstInvalidField = elements.usuarioInput;
                    }
                } else {
                    elements.usuarioInput.classList.remove('is-invalid');
                }
            }

            // Validar contraseña
            if (elements.contrasenaInput) {
                const contrasenaValue = elements.contrasenaInput.value;
                
                if (contrasenaValue === '' || !validarContrasena(contrasenaValue)) {
                    elements.contrasenaInput.classList.add('is-invalid');
                    isValid = false;
                    
                    if (!firstInvalidField) {
                        firstInvalidField = elements.contrasenaInput;
                    }
                } else {
                    elements.contrasenaInput.classList.remove('is-invalid');
                }
            }

            // Si no es válido, prevenir envío y hacer focus en primer campo inválido
            if (!isValid) {
                e.preventDefault();
                
                if (firstInvalidField) {
                    firstInvalidField.focus();
                    
                    // Agregar animación de shake
                    elements.loginForm.classList.add('shake');
                    setTimeout(() => {
                        elements.loginForm.classList.remove('shake');
                    }, 500);
                }
                
                return false;
            }

            // Si es válido, mostrar spinner y deshabilitar botón
            if (elements.loginBtn) {
                elements.loginBtn.disabled = true;
            }
            
            if (elements.loadingSpinner) {
                elements.loadingSpinner.classList.remove('d-none');
            }

            // El formulario se enviará normalmente
            return true;
        });
    }

    // ============================================
    // AUTO-CERRAR ALERTAS
    // ============================================
    if (elements.alerts && elements.alerts.length > 0) {
        elements.alerts.forEach(function(alert) {
            // Auto-cerrar después del tiempo configurado
            setTimeout(function() {
                // Verificar si Bootstrap está disponible
                if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                } else {
                    // Fallback manual si Bootstrap no está disponible
                    alert.style.transition = 'opacity 0.3s';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }
            }, CONFIG.ALERT_AUTO_CLOSE_TIME);
        });
    }

    // ============================================
    // PREVENIR DOBLE ENVÍO DEL FORMULARIO
    // ============================================
    let formSubmitted = false;
    
    if (elements.loginForm) {
        elements.loginForm.addEventListener('submit', function() {
            if (formSubmitted) {
                return false;
            }
            formSubmitted = true;
        });
    }

    // ============================================
    // LIMPIAR MENSAJES AL ESCRIBIR
    // ============================================
    function limpiarMensajesError() {
        const alerts = document.querySelectorAll('.alert-danger');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        });
    }

    if (elements.usuarioInput) {
        elements.usuarioInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                limpiarMensajesError();
            }
        });
    }

    if (elements.contrasenaInput) {
        elements.contrasenaInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                limpiarMensajesError();
            }
        });
    }

    // ============================================
    // MANEJO DE TECLAS ESPECIALES
    // ============================================
    
    // Enter en campo de usuario va a contraseña
    if (elements.usuarioInput && elements.contrasenaInput) {
        elements.usuarioInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                elements.contrasenaInput.focus();
            }
        });
    }

    // Espacio en contraseña (permitir pero limpiar espacios al inicio)
    if (elements.usuarioInput) {
        elements.usuarioInput.addEventListener('keyup', function() {
            // Limpiar espacios al inicio mientras escribe
            if (this.value.startsWith(' ')) {
                this.value = this.value.trimStart();
            }
        });
    }

    // ============================================
    // FOCUS AUTOMÁTICO EN EL PRIMER CAMPO
    // ============================================
    if (elements.usuarioInput && !elements.usuarioInput.value) {
        elements.usuarioInput.focus();
    }

    // ============================================
    // DETECCIÓN DE COPY/PASTE
    // ============================================
    if (elements.usuarioInput) {
        elements.usuarioInput.addEventListener('paste', function(e) {
            // Permitir paste pero validar después
            setTimeout(() => {
                this.value = this.value.trim();
                this.dispatchEvent(new Event('input'));
            }, 10);
        });
    }

    // ============================================
    // PROTECCIÓN CONTRA ATAQUES DE FUERZA BRUTA
    // (Implementación básica en frontend)
    // ============================================
    let intentosFallidos = 0;
    const MAX_INTENTOS = 5;
    const TIEMPO_BLOQUEO = 60000; // 1 minuto

    function verificarBloqueo() {
        const bloqueadoHasta = localStorage.getItem('bloqueadoHasta');
        
        if (bloqueadoHasta) {
            const ahora = Date.now();
            const tiempoBloqueo = parseInt(bloqueadoHasta);
            
            if (ahora < tiempoBloqueo) {
                return true;
            } else {
                localStorage.removeItem('bloqueadoHasta');
                localStorage.removeItem('intentosFallidos');
            }
        }
        
        return false;
    }

    function registrarIntentoFallido() {
        intentosFallidos = parseInt(localStorage.getItem('intentosFallidos') || '0') + 1;
        localStorage.setItem('intentosFallidos', intentosFallidos.toString());
        
        if (intentosFallidos >= MAX_INTENTOS) {
            const bloqueadoHasta = Date.now() + TIEMPO_BLOQUEO;
            localStorage.setItem('bloqueadoHasta', bloqueadoHasta.toString());
            
            mostrarAlerta(
                'Has excedido el número máximo de intentos. Por favor espera 1 minuto.',
                'danger'
            );
            
            if (elements.loginBtn) {
                elements.loginBtn.disabled = true;
            }
            
            setTimeout(() => {
                localStorage.removeItem('bloqueadoHasta');
                localStorage.removeItem('intentosFallidos');
                
                if (elements.loginBtn) {
                    elements.loginBtn.disabled = false;
                }
                
                location.reload();
            }, TIEMPO_BLOQUEO);
        }
    }

    // Verificar bloqueo al cargar la página
    if (verificarBloqueo()) {
        const bloqueadoHasta = parseInt(localStorage.getItem('bloqueadoHasta'));
        const tiempoRestante = Math.ceil((bloqueadoHasta - Date.now()) / 1000);
        
        mostrarAlerta(
            `Cuenta temporalmente bloqueada. Intenta nuevamente en ${tiempoRestante} segundos.`,
            'warning'
        );
        
        if (elements.loginBtn) {
            elements.loginBtn.disabled = true;
        }
    }

    // ============================================
    // FUNCIÓN PARA MOSTRAR ALERTAS DINÁMICAS
    // ============================================
    function mostrarAlerta(mensaje, tipo = 'info') {
        const iconos = {
            danger: 'exclamation-triangle',
            success: 'check-circle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };

        const alerta = document.createElement('div');
        alerta.className = `alert alert-${tipo} alert-dismissible fade show`;
        alerta.setAttribute('role', 'alert');
        alerta.innerHTML = `
            <i class="bi bi-${iconos[tipo] || 'info-circle'}"></i>
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        `;

        // Insertar antes del formulario
        if (elements.loginForm) {
            elements.loginForm.parentNode.insertBefore(alerta, elements.loginForm);
        }

        // Auto-cerrar
        setTimeout(() => {
            alerta.style.transition = 'opacity 0.3s';
            alerta.style.opacity = '0';
            setTimeout(() => alerta.remove(), 300);
        }, CONFIG.ALERT_AUTO_CLOSE_TIME);
    }

    // ============================================
    // DETECCIÓN DE CAPS LOCK
    // ============================================
    if (elements.contrasenaInput) {
        elements.contrasenaInput.addEventListener('keyup', function(e) {
            if (e.getModifierState && e.getModifierState('CapsLock')) {
                if (!document.getElementById('capsLockWarning')) {
                    const warning = document.createElement('small');
                    warning.id = 'capsLockWarning';
                    warning.className = 'text-warning';
                    warning.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Bloq Mayús activado';
                    this.parentElement.appendChild(warning);
                }
            } else {
                const warning = document.getElementById('capsLockWarning');
                if (warning) {
                    warning.remove();
                }
            }
        });
    }

    // ============================================
    // LOGS DE CONSOLA (SOLO EN DESARROLLO)
    // ============================================
    const isDevelopment = window.location.hostname === 'localhost' || 
                          window.location.hostname === '127.0.0.1';
    
    if (isDevelopment) {
        console.log('🔐 Sistema de Login inicializado');
        console.log('📋 Configuración:', CONFIG);
        console.log('🎨 Elementos DOM cargados:', elements);
    }

    // ============================================
    // MENSAJE DE BIENVENIDA EN CONSOLA
    // ============================================
    console.log('%c🔐 Sistema de Login v2.0', 'color: #2c5f7f; font-size: 16px; font-weight: bold;');
    console.log('%c✅ JavaScript cargado correctamente', 'color: #4caf50; font-size: 12px;');

});

// ============================================
// FIN DEL ARCHIVO JAVASCRIPT
// ============================================