// ======================================================================
// JAVASCRIPT CON LOGICA CRUD PARA API EXTERNA
// Codificacion: UTF-8 sin BOM - Compatible con Windows Defender
// SIN manipulacion de aria-hidden - Bootstrap lo maneja automaticamente
// ======================================================================

const API_BASE_URL = 'api/api_registroventas.php';
let editVentaModal;

function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alert-container');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.role = 'alert';
    alert.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    alertContainer.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

function toggleVentaForm() {
    const formulario = document.getElementById('formularioVenta');
    if (formulario) {
        formulario.classList.toggle('mostrar');
        if (formulario.classList.contains('mostrar')) {
            setTimeout(() => {
                formulario.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 300);
        }
    }
}

function limpiarFormulario() {
    if (confirm('Desea limpiar el formulario de nueva venta?')) {
        const form = document.getElementById('ventaForm');
        if (form) {
            form.reset();
            establecerFechaActual();
        }
    }
}

function logout() {
    if (confirm('Esta seguro de que desea cerrar sesion?')) {
        fetch(`${API_BASE_URL}?action=logout`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    window.location.href = 'index.php';
                }
            } catch (e) {
                console.error('Error al parsear JSON:', e);
                window.location.href = 'index.php';
            }
        })
        .catch(error => {
            console.error('Error al cerrar sesion:', error);
            window.location.href = 'index.php';
        });
    }
}

function establecerFechaActual() {
    const fechaInput = document.querySelector('#ventaForm input[name="fecha"]');
    if (fechaInput) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        fechaInput.value = now.toISOString().slice(0, 16);
    }
}

async function actualizarEstadisticas() {
    try {
        const response = await fetch(`${API_BASE_URL}?action=get_stats`);
        if (!response.ok) {
            throw new Error('Error al obtener estadisticas');
        }
        const text = await response.text();
        const data = JSON.parse(text);
        
        if (data.success && data.stats) {
            const statsElements = {
                'stats-hoy': data.stats.ventas_hoy || 0,
                'stats-semana': data.stats.ventas_semana || 0,
                'stats-mes': data.stats.ventas_mes || 0,
                'stats-total': data.stats.total_ventas || 0
            };
            
            for (const [id, value] of Object.entries(statsElements)) {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value;
                }
            }
        }
    } catch (error) { 
        console.error('Error al actualizar estadisticas:', error); 
    }
}

function updateClock() {
    const now = new Date();
    const timeOptions = {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
        timeZone: 'America/Bogota'
    };
    const currentTimeElement = document.getElementById('currentTime');
    if (currentTimeElement) {
        currentTimeElement.textContent = now.toLocaleTimeString('es-CO', timeOptions);
    }
}

function handleEdit(id) {
    if (!id || isNaN(id)) {
        showAlert('ID de venta no valido', 'danger');
        return;
    }
    
    fetch(`${API_BASE_URL}?action=get_venta&id=${encodeURIComponent(id)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Error al parsear JSON:', e);
                console.error('Respuesta recibida:', text);
                throw new Error('La respuesta del servidor no es JSON valido');
            }
            
            if (data.success && data.venta) {
                const venta = data.venta;
                const form = document.getElementById('editVentaForm');
                
                if (!form) {
                    showAlert('Formulario de edicion no encontrado', 'danger');
                    return;
                }
                
                for (const key in venta) {
                    if (form.elements[key]) {
                        if (key === 'fecha') {
                            try {
                                const fecha = new Date(venta.fecha);
                                fecha.setMinutes(fecha.getMinutes() - fecha.getTimezoneOffset());
                                form.elements[key].value = fecha.toISOString().slice(0, 16);
                            } catch (e) {
                                console.error('Error al procesar fecha:', e);
                                form.elements[key].value = '';
                            }
                        } else {
                            form.elements[key].value = venta[key] || '';
                        }
                    }
                }
                
                if (editVentaModal) {
                    editVentaModal.show();
                }
            } else { 
                showAlert(data.message || 'Error al obtener datos de la venta', 'danger'); 
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de red al obtener datos de la venta.', 'danger');
        });
}

function handleDelete(id) {
    if (!id || isNaN(id)) {
        showAlert('ID de venta no valido', 'danger');
        return;
    }
    
    if (confirm('ESTA SEGURO de que desea eliminar esta venta? Esta accion es irreversible.')) {
        fetch(`${API_BASE_URL}?action=delete_venta`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(id)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            const data = JSON.parse(text);
            if (data.success) {
                showAlert(data.message, 'success');
                const row = document.getElementById(`venta-row-${id}`);
                if (row) {
                    row.remove();
                }
                actualizarEstadisticas();
            } else { 
                showAlert(data.message, 'danger'); 
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de red al eliminar la venta.', 'danger');
        });
    }
}

function initializeFormValidation(formElement) {
    if (!formElement) return;
    
    const numberInputs = formElement.querySelectorAll('input[type="number"]');
    numberInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });

    const telInputs = formElement.querySelectorAll('input[type="tel"]');
    telInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
        });
    });
}

function setupVentaFormSubmit() {
    const ventaForm = document.getElementById('ventaForm');
    if (!ventaForm) return;
    
    ventaForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        
        if (!submitButton) {
            showAlert('Boton de envio no encontrado', 'danger');
            return;
        }
        
        const requiredFields = form.querySelectorAll('[required]');
        for (let field of requiredFields) {
            if (!field.value.trim()) {
                const label = field.previousElementSibling;
                const fieldName = label ? label.textContent : 'Este campo';
                showAlert(`${fieldName} es obligatorio.`, 'warning');
                field.focus();
                return;
            }
        }

        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

        fetch(`${API_BASE_URL}?action=guardar_venta`, { 
            method: 'POST', 
            body: formData 
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            const data = JSON.parse(text);
            showAlert(data.message, data.success ? 'success' : 'danger');
            if (data.success) {
                setTimeout(() => location.reload(), 1500);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de red. Intentelo de nuevo.', 'danger');
        })
        .finally(() => {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="bi bi-save-fill"></i> Guardar Venta';
        });
    });
    
    initializeFormValidation(ventaForm);
}

function setupEditFormSubmit() {
    const editVentaForm = document.getElementById('editVentaForm');
    if (!editVentaForm) return;
    
    editVentaForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitButton = document.querySelector('#editVentaModal .modal-footer button[type="submit"]');
        
        if (!submitButton) {
            showAlert('Boton de envio no encontrado', 'danger');
            return;
        }
        
        const requiredFields = this.querySelectorAll('[required]');
        for (let field of requiredFields) {
            if (!field.value.trim()) {
                const label = field.previousElementSibling;
                const fieldName = label ? label.textContent : 'Este campo';
                showAlert(`${fieldName} es obligatorio.`, 'warning');
                field.focus();
                return;
            }
        }

        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

        fetch(`${API_BASE_URL}?action=update_venta`, { 
            method: 'POST', 
            body: formData 
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            const data = JSON.parse(text);
            showAlert(data.message, data.success ? 'success' : 'danger');
            if (data.success) {
                if (editVentaModal) {
                    editVentaModal.hide();
                }
                setTimeout(() => location.reload(), 1500);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de red al actualizar.', 'danger');
        })
        .finally(() => {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="bi bi-save-fill"></i> Guardar Cambios';
        });
    });
    
    initializeFormValidation(editVentaForm);
}

function initializeModal() {
    const modalElement = document.getElementById('editVentaModal');
    if (modalElement) {
        editVentaModal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: true,
            focus: true
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initializeModal();
    establecerFechaActual();
    updateClock();
    setInterval(updateClock, 1000);
    setupVentaFormSubmit();
    setupEditFormSubmit();
});