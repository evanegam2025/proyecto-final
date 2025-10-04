// Configuración para manejo de formularios con API
document.addEventListener('DOMContentLoaded', function() {
    
    // Manejo del formulario de consulta
    const formConsulta = document.getElementById('formConsulta');
    if (formConsulta) {
        formConsulta.addEventListener('submit', function(e) {
            e.preventDefault();
            const cedula = document.getElementById('cedula_consulta').value;
            if (cedula.length < 6 || cedula.length > 12) {
                alert('La cédula debe tener entre 6 y 12 dígitos');
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
        formAprovisionamiento.addEventListener('submit', function(e) {
            e.preventDefault();
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
        formEditarAprovisionamiento.addEventListener('submit', function(e) {
            e.preventDefault();
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

    // Limpiar el campo de búsqueda si hay parámetros en la URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('cedula_consulta')) {
        const cedulaInput = document.getElementById('cedula_consulta');
        if (cedulaInput) {
            // Opcional: limpiar el campo después de una consulta exitosa
            // cedulaInput.value = '';
        }
    }
});

// Función para eliminar aprovisionamiento
function eliminarAprovisionamiento(id) {
    if (confirm('¿Está seguro de que desea eliminar este aprovisionamiento? Esta acción no se puede deshacer.')) {
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

// Función para enviar formularios a la API
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
        mostrarMensaje('Error de conexión: ' + error.message, 'danger');
    });
}

// Función para mostrar mensajes
function mostrarMensaje(mensaje, tipo) {
    const container = document.querySelector('.container');
    if (!container) {
        console.error('No se encontró el contenedor para mostrar el mensaje');
        alert(mensaje); // Fallback
        return;
    }
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${tipo} alert-dismissible fade show`;
    alert.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Insertar al principio del container o después del primer elemento
    if (container.children.length > 1) {
        container.insertBefore(alert, container.children[1]);
    } else {
        container.appendChild(alert);
    }
    
    // Auto-remover la alerta después de 5 segundos
    setTimeout(() => {
        if (alert && alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

// Función para toggle de detalles
function toggleDetails(checkboxId, containerId) {
    const checkbox = document.getElementById(checkboxId);
    const container = document.getElementById(containerId);
    if (checkbox && container) {
        container.style.display = checkbox.checked ? 'block' : 'none';
    }
}

// Función para confirmar cierre de sesión
function confirmarCerrarSesion() {
    if (confirm('¿Está seguro de que desea cerrar sesión?')) {
        window.location.href = '?logout=1';
    }
}
        