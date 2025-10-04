// js/modulos.js
class ModulosManager {
    constructor() {
        // Verificar la ruta correcta de la API
        this.apiUrl = this.determinarRutaAPI();
        this.modulosContainer = null;
        this.loadingSpinner = null;
        this.init();
    }

    /**
     * Determinar la ruta correcta de la API
     */
    determinarRutaAPI() {
        const baseUrl = window.location.origin;
        const currentPath = window.location.pathname;
        
        // Si estamos en /proyectofinal/modulos.php
        if (currentPath.includes('/proyectofinal/')) {
            return 'api/api_modulos.php';
        }
        
        // Ruta alternativa
        return '/proyectofinal/api/api_modulos.php';
    }

    /**
     * Inicializar el manager
     */
    init() {
        document.addEventListener('DOMContentLoaded', () => {
            console.log('ModulosManager iniciando...');
            console.log('URL de API:', this.apiUrl);
            
            this.loadingSpinner = document.getElementById('loadingSpinner');
            this.modulosContainer = document.querySelector('.modules-grid');
            
            if (!this.modulosContainer) {
                console.error('No se encontró el contenedor de módulos');
                return;
            }
            
            // Verificar conectividad antes de cargar módulos
            this.verificarConectividad().then(() => {
                this.cargarModulos();
            }).catch(() => {
                this.mostrarErrorConectividad();
            });
        });
    }

    /**
     * Verificar conectividad con la API
     */
    async verificarConectividad() {
        try {
            const response = await fetch(`${this.apiUrl}?action=test_connection`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const data = await response.json();
            console.log('Test de conectividad exitoso:', data);
            return data;
        } catch (error) {
            console.error('Error de conectividad:', error);
            throw error;
        }
    }

    /**
     * Mostrar error de conectividad
     */
    mostrarErrorConectividad() {
        if (!this.modulosContainer) return;

        this.ocultarLoading();
        
        const errorElement = document.createElement('div');
        errorElement.className = 'col-12 text-center py-5';
        errorElement.innerHTML = `
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-wifi-off fs-1 d-block mb-3"></i>
                <h4>Error de Conectividad</h4>
                <p>No se puede conectar con la API de módulos.</p>
                <p><strong>Ruta intentada:</strong> ${this.apiUrl}</p>
                <p><strong>URL completa:</strong> ${window.location.origin}/${this.apiUrl}</p>
                <div class="mt-3">
                    <button class="btn btn-primary me-2" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Recargar
                    </button>
                    <button class="btn btn-outline-secondary" onclick="window.ModulosManager.diagnosticar()">
                        <i class="bi bi-tools me-1"></i>
                        Diagnósticar
                    </button>
                </div>
            </div>
        `;
        
        this.modulosContainer.appendChild(errorElement);
    }

    /**
     * Mostrar loading spinner
     */
    mostrarLoading() {
        if (this.loadingSpinner) {
            this.loadingSpinner.style.display = 'flex';
        }
    }

    /**
     * Ocultar loading spinner
     */
    ocultarLoading() {
        if (this.loadingSpinner) {
            setTimeout(() => {
                this.loadingSpinner.classList.add('fade-out');
                setTimeout(() => {
                    this.loadingSpinner.style.display = 'none';
                }, 300);
            }, 500);
        }
    }

    /**
     * Realizar petición a la API
     */
    async realizarPeticion(action, params = {}) {
        try {
            const url = new URL(this.apiUrl, window.location.origin + window.location.pathname.replace('modulos.php', ''));
            url.searchParams.append('action', action);
            
            Object.keys(params).forEach(key => {
                url.searchParams.append(key, params[key]);
            });

            console.log('Realizando petición a:', url.toString());

            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Error en la respuesta del servidor');
            }

            return data;
        } catch (error) {
            console.error('Error en petición API:', error);
            throw error;
        }
    }

    /**
     * Cargar módulos desde la API
     */
    async cargarModulos() {
        try {
            this.mostrarLoading();
            
            const response = await this.realizarPeticion('obtener_modulos');
            const { modulos, total_modulos, usuario_rol, permisos } = response.data;
            
            console.log(`Módulos cargados para ${usuario_rol}:`, modulos);
            console.log('Permisos del usuario:', permisos);
            
            this.renderizarModulos(modulos);
            this.actualizarEstadisticas(total_modulos, usuario_rol);
            
        } catch (error) {
            console.error('Error cargando módulos:', error);
            this.mostrarError('No se pudieron cargar los módulos. Por favor, recarga la página.');
        } finally {
            this.ocultarLoading();
        }
    }

    /**
     * Renderizar módulos en el DOM
     */
    renderizarModulos(modulos) {
        if (!this.modulosContainer) {
            console.error('Contenedor de módulos no encontrado');
            return;
        }

        // Limpiar contenedor
        this.modulosContainer.innerHTML = '';

        if (Object.keys(modulos).length === 0) {
            this.mostrarMensajeVacio();
            return;
        }

        // Crear elementos de módulos
        Object.entries(modulos).forEach(([key, modulo]) => {
            const moduloElement = this.crearElementoModulo(key, modulo);
            this.modulosContainer.appendChild(moduloElement);
        });

        // Inicializar tooltips después de renderizar
        this.inicializarTooltips();
    }

    /**
     * Crear elemento DOM para un módulo
     */
    crearElementoModulo(key, modulo) {
        const col = document.createElement('div');
        col.className = 'col';

        const link = document.createElement('a');
        link.href = modulo.url;
        link.className = `btn btn-module ${modulo.color} w-100 h-100`;
        link.title = modulo.descripcion;
        link.setAttribute('data-bs-toggle', 'tooltip');
        link.setAttribute('data-bs-placement', 'top');
        link.setAttribute('role', 'button');
        link.setAttribute('aria-label', `Ir a ${modulo.nombre}`);
        link.setAttribute('data-module', key);

        // Agregar evento de clic para logging
        link.addEventListener('click', (e) => {
            this.logAccesoModulo(key, modulo.nombre);
        });

        const moduleContent = document.createElement('div');
        moduleContent.className = 'module-content';

        const icon = document.createElement('i');
        icon.className = `${modulo.icono} module-icon`;

        const text = document.createElement('span');
        text.className = 'module-text';
        text.textContent = modulo.nombre;

        const description = document.createElement('small');
        description.className = 'module-description d-block mt-2';
        description.textContent = modulo.descripcion;

        moduleContent.appendChild(icon);
        moduleContent.appendChild(text);
        moduleContent.appendChild(description);
        link.appendChild(moduleContent);
        col.appendChild(link);

        return col;
    }

    /**
     * Mostrar mensaje cuando no hay módulos disponibles
     */
    mostrarMensajeVacio() {
        const mensajeVacio = document.createElement('div');
        mensajeVacio.className = 'col-12 text-center py-5';
        mensajeVacio.innerHTML = `
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle fs-1 d-block mb-3"></i>
                <h4>No hay módulos disponibles</h4>
                <p class="mb-0">No tienes permisos para acceder a ningún módulo del sistema.<br>
                Contacta al administrador para obtener los permisos necesarios.</p>
            </div>
        `;
        
        this.modulosContainer.appendChild(mensajeVacio);
    }

    /**
     * Mostrar mensaje de error
     */
    mostrarError(mensaje) {
        if (!this.modulosContainer) return;

        const errorElement = document.createElement('div');
        errorElement.className = 'col-12 text-center py-5';
        errorElement.innerHTML = `
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle fs-1 d-block mb-3"></i>
                <h4>Error al cargar módulos</h4>
                <p class="mb-3">${mensaje}</p>
                <button class="btn btn-outline-danger" onclick="window.location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Recargar página
                </button>
            </div>
        `;
        
        this.modulosContainer.appendChild(errorElement);
    }

    /**
     * Actualizar estadísticas en la interfaz
     */
    actualizarEstadisticas(totalModulos, usuarioRol) {
        // Actualizar título de módulos si existe
        const modulesTitle = document.querySelector('.modules-title');
        if (modulesTitle) {
            const countSpan = modulesTitle.querySelector('.modules-count');
            if (countSpan) {
                countSpan.textContent = totalModulos;
            } else {
                modulesTitle.innerHTML += ` <small class="text-muted">(<span class="modules-count">${totalModulos}</span> disponibles)</small>`;
            }
        }

        // Agregar información adicional al footer si es necesario
        const footer = document.querySelector('footer');
        if (footer && totalModulos > 0) {
            const infoAdicional = footer.querySelector('.modules-info');
            if (!infoAdicional) {
                const infoElement = document.createElement('div');
                infoElement.className = 'modules-info mt-2';
                infoElement.innerHTML = `
                    <small class="text-muted">
                        <i class="bi bi-grid me-1"></i>
                        ${totalModulos} módulo${totalModulos !== 1 ? 's' : ''} disponible${totalModulos !== 1 ? 's' : ''} 
                        para el rol: <strong>${usuarioRol}</strong>
                    </small>
                `;
                footer.insertBefore(infoElement, footer.firstChild);
            }
        }
    }

    /**
     * Inicializar tooltips de Bootstrap
     */
    inicializarTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover focus'
            });
        });
    }

    /**
     * Verificar si el usuario tiene un permiso específico
     */
    async verificarPermiso(permiso) {
        try {
            const response = await this.realizarPeticion('verificar_permiso', { permiso });
            return response.data.tiene_permiso;
        } catch (error) {
            console.error('Error verificando permiso:', error);
            return false;
        }
    }

    /**
     * Obtener información del usuario actual
     */
    async obtenerInfoUsuario() {
        try {
            const response = await this.realizarPeticion('obtener_info_usuario');
            return response.data;
        } catch (error) {
            console.error('Error obteniendo info del usuario:', error);
            return null;
        }
    }

    /**
     * Log de acceso a módulos (para auditoría)
     */
    logAccesoModulo(moduloKey, moduloNombre) {
        console.log(`Acceso a módulo: ${moduloNombre} (${moduloKey}) - ${new Date().toLocaleString('es-CO')}`);
    }

    /**
     * Recargar módulos
     */
    async recargarModulos() {
        await this.cargarModulos();
    }

    /**
     * Diagnosticar problemas de conectividad
     */
    async diagnosticar() {
        const diagnostico = {
            url_actual: window.location.href,
            ruta_api: this.apiUrl,
            url_completa_api: `${window.location.origin}${window.location.pathname.replace('modulos.php', '')}${this.apiUrl}`
        };
        
        console.log('Diagnóstico de conectividad:', diagnostico);
        
        // Mostrar en la interfaz
        alert(`Diagnóstico:
        
URL Actual: ${diagnostico.url_actual}
Ruta API: ${diagnostico.ruta_api}  
URL Completa API: ${diagnostico.url_completa_api}

Verifica en la consola del navegador para más detalles.`);
    }
}

// Inicializar el manager de módulos
const modulosManager = new ModulosManager();

// Exponer funciones útiles globalmente
window.ModulosManager = {
    verificarPermiso: (permiso) => modulosManager.verificarPermiso(permiso),
    obtenerInfoUsuario: () => modulosManager.obtenerInfoUsuario(),
    recargarModulos: () => modulosManager.recargarModulos(),
    diagnosticar: () => modulosManager.diagnosticar()
};