
        // ======================================================================
        // JAVASCRIPT CON LÓGICA CRUD ACTUALIZADA PARA API EXTERNA
        // ======================================================================
        const API_BASE_URL = 'api/api_registroventas.php';
        const editVentaModal = new bootstrap.Modal(document.getElementById('editVentaModal'));

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
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function toggleVentaForm() {
            const formulario = document.getElementById('formularioVenta');
            formulario.classList.toggle('mostrar');
            if(formulario.classList.contains('mostrar')) {
                setTimeout(() => formulario.scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);
            }
        }

        function limpiarFormulario() {
            if (confirm('¿Desea limpiar el formulario de nueva venta?')) {
                document.getElementById('ventaForm').reset();
                establecerFechaActual();
            }
        }

        function logout() {
            if (confirm('¿Está seguro de que desea cerrar sesión?')) {
                fetch(`${API_BASE_URL}?action=logout`)
                    .then(() => {
                        window.location.href = 'index.php';
                    })
                    .catch(() => {
                        window.location.href = 'index.php';
                    });
            }
        }

        function establecerFechaActual() {
            const fechaInput = document.querySelector('#ventaForm input[name="fecha"]');
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            fechaInput.value = now.toISOString().slice(0, 16);
        }

        async function actualizarEstadisticas() {
            try {
                const response = await fetch(`${API_BASE_URL}?action=get_stats`);
                const data = await response.json();
                if (data.success) {
                    document.getElementById('stats-hoy').textContent = data.stats.ventas_hoy;
                    document.getElementById('stats-semana').textContent = data.stats.ventas_semana;
                    document.getElementById('stats-mes').textContent = data.stats.ventas_mes;
                    document.getElementById('stats-total').textContent = data.stats.total_ventas;
                }
            } catch (error) { 
                console.error('Error al actualizar estadísticas:', error); 
            }
        }

        // Actualizar reloj en tiempo real
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

        // --- MANEJADORES CRUD ACTUALIZADOS PARA API EXTERNA ---
        function handleEdit(id) {
            fetch(`${API_BASE_URL}?action=get_venta&id=${encodeURIComponent(id)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const venta = data.venta;
                        const form = document.getElementById('editVentaForm');
                        
                        // Sanitizar y asignar valores
                        for (const key in venta) {
                            if (form.elements[key]) {
                                if(key === 'fecha'){
                                    const fecha = new Date(venta.fecha);
                                    fecha.setMinutes(fecha.getMinutes() - fecha.getTimezoneOffset());
                                    form.elements[key].value = fecha.toISOString().slice(0, 16);
                                } else {
                                    form.elements[key].value = venta[key] || '';
                                }
                            }
                        }
                        editVentaModal.show();
                    } else { 
                        showAlert(data.message, 'danger'); 
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error de red al obtener datos de la venta.', 'danger');
                });
        }

        function handleDelete(id) {
            if (confirm('¿ESTÁ SEGURO de que desea eliminar esta venta? Esta acción es irreversible.')) {
                fetch(`${API_BASE_URL}?action=delete_venta`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${encodeURIComponent(id)}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        const row = document.getElementById(`venta-row-${id}`);
                        if (row) row.remove();
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

        // --- EVENT LISTENERS ACTUALIZADOS PARA API EXTERNA ---
        document.getElementById('ventaForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            
            // Validar datos antes de enviar
            const requiredFields = form.querySelectorAll('[required]');
            for (let field of requiredFields) {
                if (!field.value.trim()) {
                    showAlert(`El campo "${field.previousElementSibling.textContent}" es obligatorio.`, 'warning');
                    field.focus();
                    return;
                }
            }

            submitButton.disabled = true;
            submitButton.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Guardando...`;

            fetch(`${API_BASE_URL}?action=guardar_venta`, { 
                method: 'POST', 
                body: formData 
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                showAlert(data.message, data.success ? 'success' : 'danger');
                if(data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error de red. Inténtelo de nuevo.', 'danger');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="bi bi-save-fill"></i> Guardar Venta';
            });
        });
        
        document.getElementById('editVentaForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitButton = document.querySelector('#editVentaModal .modal-footer button[type="submit"]');
            
            // Validar datos antes de enviar
            const requiredFields = this.querySelectorAll('[required]');
            for (let field of requiredFields) {
                if (!field.value.trim()) {
                    showAlert(`El campo "${field.previousElementSibling.textContent}" es obligatorio.`, 'warning');
                    field.focus();
                    return;
                }
            }

            submitButton.disabled = true;
            submitButton.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Guardando...`;

            fetch(`${API_BASE_URL}?action=update_venta`, { 
                method: 'POST', 
                body: formData 
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                showAlert(data.message, data.success ? 'success' : 'danger');
                if(data.success) {
                    editVentaModal.hide();
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
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', () => {
            establecerFechaActual();
            updateClock();
            setInterval(updateClock, 1000);
            
            // Validación en tiempo real para campos numéricos
            document.querySelectorAll('input[type="number"]').forEach(input => {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            });

            // Validación para teléfonos
            document.querySelectorAll('input[type="tel"]').forEach(input => {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
                });
            });
        });
    