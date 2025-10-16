// Configuracion para manejo de formularios con API
document.addEventListener('DOMContentLoaded', function() {
    
    // Manejo del formulario de consulta
    const formConsulta = document.getElementById('formConsulta');
    if (formConsulta) {
        formConsulta.addEventListener('submit', function(e) {
            e.preventDefault();
            const cedula = document.getElementById('cedula_consulta').value;
            if (cedula.length < 6 || cedula.length > 12) {
                alert('La cedula debe tener entre 6 y 12 digitos');
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

    // Manejo del formulario de aprovisionamiento
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
                    window.location.href = '?cedula_consulta=' + encodeURIComponent(response.data.cedula_cliente) + '&mensaje=' + encodeURIComponent(response.message) + '&tipo=success';
                } else {
                    mostrarMensaje(response.message, 'danger');
                }
            });
        });
    }

    // Manejo del formulario de editar venta
    const formEditarVenta = document.getElementById('formEditarVenta');
    if (formEditarVenta) {
        formEditarVenta.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'actualizar_venta');
            
            enviarFormulario(formData, function(response) {
                if (response.success) {
                    window.location.href = '?cedula_consulta=' + encodeURIComponent(response.data.cedula_edit) + '&mensaje=' + encodeURIComponent(response.message) + '&tipo=success';
                } else {
                    mostrarMensaje(response.message, 'danger');
                }
            });
        });
    }

    // Manejo del formulario de editar aprovisionamiento
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
                    window.location.href = '?cedula_consulta=' + encodeURIComponent(response.data.cedula_original) + '&mensaje=' + encodeURIComponent(response.message) + '&tipo=success';
                } else {
                    mostrarMensaje(response.message, 'danger');
                }
            });
        });
    }
});

// Funcion para eliminar aprovisionamiento
function eliminarAprovisionamiento(id) {
    if (confirm('Esta seguro de que desea eliminar este aprovisionamiento? Esta accion no se puede deshacer.')) {
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

// Funcion para enviar formularios a la API
function enviarFormulario(formData, callback) {
    fetch('api/api_registroaprovisionamiento.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor: ' + response.status);
        }
        return response.json();
    })
    .then(data => callback(data))
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('Error de conexion: ' + error.message, 'danger');
    });
}

// Funcion para mostrar mensajes
function mostrarMensaje(mensaje, tipo) {
    const container = document.querySelector('.container');
    if (!container) {
        console.error('No se encontro el contenedor para mostrar el mensaje');
        alert(mensaje);
        return;
    }
    
    const alert = document.createElement('div');
    alert.className = 'alert alert-' + tipo + ' alert-dismissible fade show';
    alert.innerHTML = mensaje + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    
    if (container.children.length > 1) {
        container.insertBefore(alert, container.children[1]);
    } else {
        container.appendChild(alert);
    }
    
    setTimeout(function() {
        if (alert && alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

// Funcion para toggle de detalles
function toggleDetails(checkboxId, containerId) {
    const checkbox = document.getElementById(checkboxId);
    const container = document.getElementById(containerId);
    if (checkbox && container) {
        container.style.display = checkbox.checked ? 'block' : 'none';
    }
}

// Funcion para confirmar cierre de sesion
function confirmarCerrarSesion() {
    if (confirm('Esta seguro de que desea cerrar sesion?')) {
        window.location.href = '?logout=1';
    }
}

// Funcion para limpiar formulario de aprovisionamiento
function limpiarFormularioAprovisionamiento() {
    const form = document.getElementById('formAprovisionamiento');
    if (form) {
        form.reset();
        const estadoSelect = document.getElementById('estado_aprovisionamiento');
        if (estadoSelect) {
            estadoSelect.value = 'PENDIENTE';
            estadoSelect.dispatchEvent(new Event('change'));
        }
    }
}