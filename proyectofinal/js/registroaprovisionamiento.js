// ============================================================================
// REGISTROAPROVISIONAMIENTO.JS - Sistema de Aprovisionamiento
// Configuracion para manejo de formularios con API
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================================================
    // MANEJO DEL FORMULARIO DE CONSULTA
    // ========================================================================
    const formConsulta = document.getElementById('formConsulta');
    if (formConsulta) {
        formConsulta.addEventListener('submit', function(e) {
            e.preventDefault();
            const cedula = document.getElementById('cedula_consulta').value;
            
            // Validación básica de cédula
            if (cedula.length < 6 || cedula.length > 12) {
                mostrarMensaje('La cedula debe tener entre 6 y 12 digitos', 'warning');
                return false;
            }
            
            const formData = new FormData();
            formData.append('action', 'consultar_cedula');
            formData.append('cedula_consulta', cedula);
            
            enviarFormulario(formData, function(response) {
                if (response.success) {
                    window.location.href = '?cedula_consulta=' + encodeURIComponent(cedula);
                } else {
                    mostrarMensaje(response.message, 'warning');
                }
            });
        });
    }

    // ========================================================================
    // MANEJO DEL FORMULARIO DE APROVISIONAMIENTO
    // ========================================================================
    const formAprovisionamiento = document.getElementById('formAprovisionamiento');
    if (formAprovisionamiento) {
        // Control de campos segun estado
        const estadoSelect = document.getElementById('estado_aprovisionamiento');
        const camposOpcionales = [
            'tipo_radio', 'mac_serial_radio', 'tipo_router_onu', 'mac_serial_router',
            'ip_navegacion', 'ip_gestion', 'metros_cable', 'tipo_cable'
        ];

        function actualizarCamposObligatorios() {
            const estado = estadoSelect.value;
            const esCumplido = estado === 'CUMPLIDO';
            
            camposOpcionales.forEach(function(campoId) {
                const campo = document.getElementById(campoId);
                if (campo) {
                    // REMOVER completamente el atributo required
                    campo.removeAttribute('required');
                    campo.required = false;
                    // Habilitar campos solo si es CUMPLIDO
                    campo.disabled = !esCumplido;
                    if (!esCumplido) {
                        campo.value = '';
                    }
                }
            });
            
            // Las notas tampoco son obligatorias
            const notasField = document.getElementById('notas_aprovisionamiento');
            if (notasField) {
                notasField.removeAttribute('required');
                notasField.required = false;
            }
        }

        if (estadoSelect) {
            estadoSelect.addEventListener('change', actualizarCamposObligatorios);
            actualizarCamposObligatorios();
        }

        formAprovisionamiento.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Remover validación HTML5 para permitir campos vacíos
            this.setAttribute('novalidate', 'novalidate');
            
            const formData = new FormData(this);
            formData.append('action', 'guardar_aprovisionamiento');
            
            enviarFormulario(formData, function(response) {
                if (response.success) {
                    window.location.href = '?cedula_consulta=' + encodeURIComponent(response.data.cedula_cliente) + 
                                          '&mensaje=' + encodeURIComponent(response.message) + '&tipo=success';
                } else {
                    mostrarMensaje(response.message, 'danger');
                }
            });
        });
    }

    // ========================================================================
    // MANEJO DEL FORMULARIO DE EDITAR VENTA
    // ========================================================================
    const formEditarVenta = document.getElementById('formEditarVenta');
    if (formEditarVenta) {
        formEditarVenta.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'actualizar_venta');
            
            enviarFormulario(formData, function(response) {
                if (response.success) {
                    window.location.href = '?cedula_consulta=' + encodeURIComponent(response.data.cedula_edit) + 
                                          '&mensaje=' + encodeURIComponent(response.message) + '&tipo=success';
                } else {
                    mostrarMensaje(response.message, 'danger');
                }
            });
        });
    }

    // ========================================================================
    // MANEJO DEL FORMULARIO DE EDITAR APROVISIONAMIENTO
    // ========================================================================
    const formEditarAprovisionamiento = document.getElementById('formEditarAprovisionamiento');
    if (formEditarAprovisionamiento) {
        // Control de campos segun estado en modal de edicion
        const estadoEditSelect = document.querySelector('[name="estado_aprovisionamiento_edit"]');
        const camposOpcionalesEdit = [
            'tipo_radio_edit', 'mac_serial_radio_edit', 'tipo_router_onu_edit', 'mac_serial_router_edit',
            'ip_navegacion_edit', 'ip_gestion_edit', 'metros_cable_edit', 'tipo_cable_edit'
        ];

        function actualizarCamposObligatoriosEdit() {
            const estado = estadoEditSelect.value;
            const esCumplido = estado === 'CUMPLIDO';
            
            camposOpcionalesEdit.forEach(function(campoName) {
                const campo = document.querySelector('[name="' + campoName + '"]');
                if (campo) {
                    // NINGUN campo es obligatorio
                    campo.removeAttribute('required');
                    campo.required = false;
                    // Habilitar campos solo si es CUMPLIDO
                    campo.disabled = !esCumplido;
                    if (!esCumplido && campo.tagName !== 'SELECT') {
                        campo.value = '';
                    }
                }
            });
            
            // Las notas tampoco son obligatorias
            const notasEditField = document.querySelector('[name="notas_aprovisionamiento_edit"]');
            if (notasEditField) {
                notasEditField.removeAttribute('required');
                notasEditField.required = false;
            }
        }

        if (estadoEditSelect) {
            estadoEditSelect.addEventListener('change', actualizarCamposObligatoriosEdit);
            actualizarCamposObligatoriosEdit();
        }

        formEditarAprovisionamiento.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Remover validación HTML5 para permitir campos vacíos
            this.setAttribute('novalidate', 'novalidate');
            
            const formData = new FormData(this);
            formData.append('action', 'actualizar_aprovisionamiento');
            
            enviarFormulario(formData, function(response) {
                if (response.success) {
                    window.location.href = '?cedula_consulta=' + encodeURIComponent(response.data.cedula_original) + 
                                          '&mensaje=' + encodeURIComponent(response.message) + '&tipo=success';
                } else {
                    mostrarMensaje(response.message, 'danger');
                }
            });
        });
    }
});

// ============================================================================
// FUNCIONES GLOBALES
// ============================================================================

/**
 * Funcion para eliminar aprovisionamiento
 * @param {number} id - ID del aprovisionamiento a eliminar
 */
function eliminarAprovisionamiento(id) {
    if (confirm('¿Esta seguro de que desea eliminar este aprovisionamiento? Esta accion no se puede deshacer.')) {
        const formData = new FormData();
        formData.append('action', 'borrar_aprovisionamiento');
        formData.append('id_aprovisionamiento_a_borrar', id);
        
        enviarFormulario(formData, function(response) {
            if (response.success) {
                window.location.href = '?mensaje=' + encodeURIComponent(response.message) + '&tipo=success';
            } else {
                mostrarMensaje(response.message, 'danger');
            }
        });
    }
}

/**
 * Funcion para enviar formularios a la API con validacion JSON mejorada
 * @param {FormData} formData - Datos del formulario
 * @param {Function} callback - Función callback para manejar la respuesta
 */
function enviarFormulario(formData, callback) {
    // Mostrar indicador de carga
    mostrarCarga(true);
    
    fetch('api/api_registroaprovisionamiento.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Verificar si la respuesta es exitosa
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor: ' + response.status);
        }
        
        // Verificar que la respuesta sea realmente JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Respuesta no JSON recibida:', text.substring(0, 500));
                throw new Error('El servidor no devolvio JSON valido. Por favor revise los logs del servidor.');
            });
        }
        
        return response.json();
    })
    .then(data => {
        mostrarCarga(false);
        callback(data);
    })
    .catch(error => {
        mostrarCarga(false);
        console.error('Error:', error);
        mostrarMensaje('Error de conexion: ' + error.message, 'danger');
    });
}

/**
 * Funcion para mostrar mensajes de alerta
 * @param {string} mensaje - Mensaje a mostrar
 * @param {string} tipo - Tipo de alerta (success, danger, warning, info)
 */
function mostrarMensaje(mensaje, tipo) {
    const container = document.querySelector('.container');
    if (!container) {
        console.error('No se encontro el contenedor para mostrar el mensaje');
        alert(mensaje);
        return;
    }
    
    const alert = document.createElement('div');
    alert.className = 'alert alert-' + tipo + ' alert-dismissible fade show';
    alert.setAttribute('role', 'alert');
    alert.innerHTML = '<i class="bi bi-info-circle-fill me-2"></i>' + 
                      mensaje + 
                      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    
    // Insertar después del perfil
    if (container.children.length > 1) {
        container.insertBefore(alert, container.children[1]);
    } else {
        container.appendChild(alert);
    }
    
    // Auto-cerrar después de 5 segundos
    setTimeout(function() {
        if (alert && alert.parentNode) {
            alert.classList.remove('show');
            setTimeout(function() {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 150);
        }
    }, 5000);
    
    // Scroll suave hacia el mensaje
    alert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Funcion para mostrar/ocultar indicador de carga
 * @param {boolean} mostrar - true para mostrar, false para ocultar
 */
function mostrarCarga(mostrar) {
    let spinner = document.getElementById('spinner-global');
    
    if (mostrar) {
        if (!spinner) {
            spinner = document.createElement('div');
            spinner.id = 'spinner-global';
            spinner.className = 'position-fixed top-50 start-50 translate-middle';
            spinner.style.zIndex = '9999';
            spinner.innerHTML = `
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            `;
            document.body.appendChild(spinner);
            
            // Agregar overlay oscuro
            const overlay = document.createElement('div');
            overlay.id = 'overlay-global';
            overlay.className = 'position-fixed top-0 start-0 w-100 h-100';
            overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
            overlay.style.zIndex = '9998';
            document.body.appendChild(overlay);
        }
        spinner.style.display = 'block';
        document.getElementById('overlay-global').style.display = 'block';
    } else {
        if (spinner) {
            spinner.style.display = 'none';
            const overlay = document.getElementById('overlay-global');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
    }
}

/**
 * Funcion para toggle de detalles
 * @param {string} checkboxId - ID del checkbox
 * @param {string} containerId - ID del contenedor a mostrar/ocultar
 */
function toggleDetails(checkboxId, containerId) {
    const checkbox = document.getElementById(checkboxId);
    const container = document.getElementById(containerId);
    if (checkbox && container) {
        container.style.display = checkbox.checked ? 'block' : 'none';
        
        // Animación suave
        if (checkbox.checked) {
            container.style.animation = 'fadeIn 0.3s ease';
        }
    }
}

/**
 * Funcion para confirmar cierre de sesion
 */
function confirmarCerrarSesion() {
    if (confirm('¿Esta seguro de que desea cerrar sesion?')) {
        window.location.href = '?logout=1';
    }
}

/**
 * Funcion para limpiar formulario de aprovisionamiento
 */
function limpiarFormularioAprovisionamiento() {
    const form = document.getElementById('formAprovisionamiento');
    if (form) {
        if (confirm('¿Esta seguro de que desea limpiar todos los campos del formulario?')) {
            form.reset();
            const estadoSelect = document.getElementById('estado_aprovisionamiento');
            if (estadoSelect) {
                estadoSelect.value = 'PENDIENTE';
                estadoSelect.dispatchEvent(new Event('change'));
            }
            mostrarMensaje('Formulario limpiado correctamente', 'info');
        }
    }
}

// ============================================================================
// UTILIDADES ADICIONALES
// ============================================================================

/**
 * Validar formato de email
 * @param {string} email - Email a validar
 * @returns {boolean} - true si es valido
 */
function validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Validar formato de IP
 * @param {string} ip - IP a validar
 * @returns {boolean} - true si es valido
 */
function validarIP(ip) {
    const regex = /^(\d{1,3}\.){3}\d{1,3}$/;
    if (!regex.test(ip)) return false;
    
    const parts = ip.split('.');
    return parts.every(part => parseInt(part) >= 0 && parseInt(part) <= 255);
}

// Animación de fade in para elementos dinámicos
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);

// Prevenir envío duplicado de formularios
let formularioEnviando = false;
document.addEventListener('submit', function(e) {
    if (formularioEnviando) {
        e.preventDefault();
        return false;
    }
    formularioEnviando = true;
    
    setTimeout(function() {
        formularioEnviando = false;
    }, 3000);
}, true);

console.log('Sistema de Aprovisionamiento - JavaScript cargado correctamente');