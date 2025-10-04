const API_BASE_URL = 'api/api_registroagenda.php';



        function toggleDetalles() {
            const checkbox = document.getElementById('mostrar_detalles');
            const detalles = document.getElementById('detalles_venta');
            if (checkbox && detalles) {
                detalles.style.display = checkbox.checked ? 'block' : 'none';
            }
        }

        function toggleDetallesAgenda() {
            const checkbox = document.getElementById('mostrar_detalles_agenda');
            const detalles = document.getElementById('detalles_agendamiento');
            if (checkbox && detalles) {
                detalles.style.display = checkbox.checked ? 'block' : 'none';
            }
        }

        // Configurar fecha mínima para los campos de fecha
        document.addEventListener('DOMContentLoaded', function() {
            const fechaInputs = document.querySelectorAll('input[type="date"]');
            const hoy = new Date().toISOString().split('T')[0];
            
            fechaInputs.forEach(function(input) {
                input.min = hoy;
            });

            // Validación del formulario de consulta
            const formConsulta = document.getElementById('formConsulta');
            if (formConsulta) {
                formConsulta.addEventListener('submit', function(e) {
                    const cedula = document.getElementById('cedula_consulta').value.trim();
                    if (!cedula || isNaN(cedula)) {
                        e.preventDefault();
                        alert('Por favor ingrese un número de cédula válido');
                        return false;
                    }
                });
            }

            // Validación del formulario de editar venta
            const formEditarVenta = document.getElementById('formEditarVenta');
            if (formEditarVenta) {
                formEditarVenta.addEventListener('submit', function(e) {
                    // Validar email
                    const email = document.getElementById('email_edit').value;
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Por favor ingrese un email válido');
                        document.getElementById('email_edit').focus();
                        return false;
                    }
                    
                    // Validar teléfonos (solo números)
                    const telefono1 = document.getElementById('telefono1_edit').value;
                    const telefono2 = document.getElementById('telefono2_edit').value;
                    
                    if (!/^\d+$/.test(telefono1)) {
                        e.preventDefault();
                        alert('El teléfono 1 debe contener solo números');
                        document.getElementById('telefono1_edit').focus();
                        return false;
                    }
                    
                    if (telefono2 && !/^\d+$/.test(telefono2)) {
                        e.preventDefault();
                        alert('El teléfono 2 debe contener solo números');
                        document.getElementById('telefono2_edit').focus();
                        return false;
                    }
                    
                    // Validar cédula
                    const cedula = document.getElementById('cedula_edit').value;
                    if (!/^\d+$/.test(cedula)) {
                        e.preventDefault();
                        alert('La cédula debe contener solo números');
                        document.getElementById('cedula_edit').focus();
                        return false;
                    }
                });
            }

            // Auto-focus en campo de cédula
            const cedulaInput = document.getElementById('cedula_consulta');
            if (cedulaInput && !cedulaInput.value) {
                cedulaInput.focus();
            }

            // Prevenir envío múltiple de formularios
            let formSubmitting = false;
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    if (formSubmitting) {
                        e.preventDefault();
                        return false;
                    }
                    formSubmitting = true;
                    
                    setTimeout(function() {
                        formSubmitting = false;
                    }, 2000);
                });
            });

            // Limpiar formulario al cerrar modales
            document.querySelectorAll('.modal').forEach(function(modal) {
                modal.addEventListener('hidden.bs.modal', function() {
                    // No hay errores al cancelar - los formularios mantienen sus valores originales
                });
            });
        });

        // Validación en tiempo real para campos numéricos
        document.addEventListener('input', function(e) {
            if (e.target.type === 'number' && e.target.name === 'cedula_consulta') {
                // Remover caracteres no numéricos
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            }
            
            // Limpiar caracteres peligrosos en tiempo real para campos de texto
            if (e.target.type === 'text' || e.target.type === 'email' || e.target.tagName === 'TEXTAREA') {
                e.target.value = e.target.value.replace(/[<>'"\\]/g, '');
            }
        });
    