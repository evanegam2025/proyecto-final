/**
 * Dashboard JavaScript - Sistema de Ventas Conekto
 * Funcionalidades principales del dashboard con manejo mejorado de errores
 */

// Variables globales
let dashboardData = {};
let autoRefreshInterval = null;
let currentFilters = {
    period: 'current_month',
    technology: 'all',
    municipality: 'all',
    startDate: null,
    endDate: null
};

// Configuración
const CONFIG = {
    API_BASE_URL: 'api/api_dashboard.php',
    AUTO_REFRESH_DEFAULT: 300000, // 5 minutos
    EFFECTIVENESS_GOAL_DEFAULT: 90,
    MAX_RETRIES: 3,
    RETRY_DELAY: 1000
};

/**
 * Inicializar dashboard al cargar la página
 */
function initDashboard() {
    try {
        console.log('Inicializando dashboard...');
        
        // Verificar que el API endpoint existe
        checkAPIEndpoint().then(() => {
            // Verificar permisos del usuario
            checkUserPermissions();
            
            // Cargar municipios
            loadMunicipalities();
            
            // Cargar datos iniciales
            loadDashboardData();
            
            // Configurar auto-refresh
            setAutoRefresh();
            
            // Actualizar timestamp
            updateLastUpdateTime();
            
            console.log('Dashboard inicializado correctamente');
        }).catch(error => {
            console.error('Error verificando API:', error);
            showError('Error de conectividad con el servidor. Verifique que api/api_dashboard.php existe y es accesible.');
        });
        
    } catch (error) {
        console.error('Error al inicializar dashboard:', error);
        showError('Error al inicializar el dashboard: ' + error.message);
    }
}

/**
 * Verificar que el API endpoint existe y responde
 */
async function checkAPIEndpoint() {
    try {
        const response = await fetch(CONFIG.API_BASE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'ping'
            })
        });
        
        if (!response.ok) {
            throw new Error(`API endpoint no disponible: ${response.status} ${response.statusText}`);
        }
        
        // Verificar que la respuesta es JSON válido
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Respuesta no JSON del servidor:', text.substring(0, 500));
            throw new Error('El servidor está devolviendo HTML en lugar de JSON. Verifique la configuración del API.');
        }
        
        return true;
    } catch (error) {
        throw new Error(`Error conectando con API: ${error.message}`);
    }
}

/**
 * Realizar petición API con manejo robusto de errores
 */
async function makeAPIRequest(action, data = {}, retries = CONFIG.MAX_RETRIES) {
    for (let attempt = 1; attempt <= retries; attempt++) {
        try {
            console.log(`Intento ${attempt} de ${retries} para acción: ${action}`);
            
            const response = await fetch(CONFIG.API_BASE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: action,
                    ...data
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            // Verificar content-type antes de parsear
            const contentType = response.headers.get('content-type') || '';
            
            if (contentType.includes('application/json')) {
                const jsonData = await response.json();
                return jsonData;
            } else {
                // Si no es JSON, obtener el texto completo para debugging
                const textResponse = await response.text();
                console.error('Respuesta no JSON del servidor:', textResponse);
                
                // Intentar encontrar JSON dentro del HTML (caso común con errores PHP)
                const jsonMatch = textResponse.match(/\{.*\}/s);
                if (jsonMatch) {
                    try {
                        return JSON.parse(jsonMatch[0]);
                    } catch (e) {
                        // No se pudo extraer JSON válido
                    }
                }
                
                throw new Error(`Servidor devolvió HTML en lugar de JSON. Respuesta: ${textResponse.substring(0, 200)}...`);
            }
            
        } catch (error) {
            console.error(`Intento ${attempt} falló:`, error);
            
            if (attempt === retries) {
                // Último intento falló
                throw error;
            }
            
            // Esperar antes del siguiente intento
            await new Promise(resolve => setTimeout(resolve, CONFIG.RETRY_DELAY * attempt));
        }
    }
}

/**
 * Verificar permisos del usuario para mostrar/ocultar botones
 */
async function checkUserPermissions() {
    try {
        const data = await makeAPIRequest('check_permissions');
        
        if (data && data.success) {
            updateUIPermissions(data.permissions || []);
        } else {
            console.error('Error verificando permisos:', data?.message || 'Respuesta inválida');
            // Continuar sin permisos específicos
            updateUIPermissions([]);
        }
        
    } catch (error) {
        console.error('Error verificando permisos:', error);
        showError('Error verificando permisos de usuario');
        // Ocultar botones por defecto
        updateUIPermissions([]);
    }
}

/**
 * Actualizar UI según permisos del usuario
 */
function updateUIPermissions(permissions) {
    const exportBtn = document.getElementById('export-btn');
    
    // Verificar si tiene permisos para exportar
    const hasExportPermission = Array.isArray(permissions) && permissions.some(p => 
        p.permiso_nombre === 'dashboard' || 
        p.permiso_nombre === 'administrar_permisos' ||
        p.permiso_nombre === 'consultas'
    );
    
    if (exportBtn) {
        if (hasExportPermission) {
            exportBtn.style.display = 'inline-block';
        } else {
            exportBtn.style.display = 'none';
        }
    }
}

/**
 * Cargar municipios para el filtro
 */
async function loadMunicipalities() {
    try {
        const data = await makeAPIRequest('get_municipalities');
        
        if (data && data.success) {
            populateMunicipalityFilter(data.municipalities || []);
        } else {
            console.error('Error cargando municipios:', data?.message || 'Respuesta inválida');
            populateMunicipalityFilter([]);
        }
        
    } catch (error) {
        console.error('Error cargando municipios:', error);
        populateMunicipalityFilter([]);
    }
}

/**
 * Poblar el filtro de municipios
 */
function populateMunicipalityFilter(municipalities) {
    const select = document.getElementById('municipio-filter');
    if (!select) return;
    
    // Limpiar opciones existentes (excepto "Todos")
    select.innerHTML = '<option value="all">Todos</option>';
    
    // Agregar municipios
    if (Array.isArray(municipalities)) {
        municipalities.forEach(municipality => {
            const option = document.createElement('option');
            option.value = municipality.municipio || '';
            option.textContent = municipality.municipio || 'N/A';
            select.appendChild(option);
        });
    }
}

/**
 * Cargar datos del dashboard
 */
async function loadDashboardData() {
    try {
        showLoading(true);
        
        const data = await makeAPIRequest('get_dashboard_data', {
            filters: currentFilters
        });
        
        if (data && data.success) {
            dashboardData = data.data || {};
            updateDashboardUI();
        } else {
            const errorMsg = data?.message || 'Error desconocido del servidor';
            console.error('Error en respuesta del dashboard:', errorMsg);
            showError(`Error cargando datos: ${errorMsg}`);
            
            // Mostrar datos vacíos para evitar interfaz rota
            dashboardData = getEmptyDashboardData();
            updateDashboardUI();
        }
        
    } catch (error) {
        console.error('Error cargando datos:', error);
        
        let errorMessage = 'Error al cargar los datos del dashboard';
        if (error.message.includes('JSON')) {
            errorMessage += '. El servidor está devolviendo HTML en lugar de datos JSON.';
        } else if (error.message.includes('HTTP')) {
            errorMessage += '. Error de conectividad con el servidor.';
        } else {
            errorMessage += `: ${error.message}`;
        }
        
        showError(errorMessage);
        
        // Mostrar datos vacíos para evitar interfaz rota
        dashboardData = getEmptyDashboardData();
        updateDashboardUI();
        
    } finally {
        showLoading(false);
        updateLastUpdateTime();
    }
}

/**
 * Obtener estructura de datos vacía para el dashboard
 */
function getEmptyDashboardData() {
    return {
        total_ventas: 0,
        total_agendadas: 0,
        total_cumplidas: 0,
        efectividad: 0,
        ventas_mes_actual: 0,
        ventas_mes_anterior: 0,
        estados_ventas: [],
        agendamiento_pendientes: 0,
        agendamiento_completadas: 0,
        aprovisionamiento_pendientes: 0,
        aprovisionamiento_proceso: 0,
        tecnologias: []
    };
}

/**
 * Actualizar UI del dashboard con los datos
 */
function updateDashboardUI() {
    try {
        // Actualizar resumen general
        updateSummaryCards();
        
        // Actualizar tarjetas individuales
        updateTotalSalesCard();
        updateSalesStatusCard();
        updateSchedulingCard();
        updateProvisioningCard();
        updateEffectivenessCard();
        updateTechnologyCard();
        
    } catch (error) {
        console.error('Error actualizando UI:', error);
        showError('Error al actualizar la interfaz');
    }
}

/**
 * Actualizar resumen general
 */
function updateSummaryCards() {
    const data = dashboardData;
    
    updateElementValue('summary-total-ventas', data.total_ventas || 0);
    updateElementValue('summary-agendadas', data.total_agendadas || 0);
    updateElementValue('summary-cumplidas', data.total_cumplidas || 0);
    updateElementValue('summary-efectividad', (data.efectividad || 0) + '%');
}

/**
 * Actualizar tarjeta de total de ventas
 */
function updateTotalSalesCard() {
    const data = dashboardData;
    
    updateElementValue('total-sales-value', data.total_ventas || 0);
    updateElementValue('sales-this-month', data.ventas_mes_actual || 0);
    updateElementValue('sales-last-month', data.ventas_mes_anterior || 0);
}

/**
 * Actualizar tarjeta de estados de ventas
 */
function updateSalesStatusCard() {
    const statusGrid = document.getElementById('status-grid');
    if (!statusGrid) return;
    
    const statusData = dashboardData.estados_ventas || [];
    
    statusGrid.innerHTML = '';
    
    if (statusData.length === 0) {
        statusGrid.innerHTML = '<div class="no-data">No hay datos de estados disponibles</div>';
        return;
    }
    
    statusData.forEach(status => {
        const statusItem = document.createElement('div');
        statusItem.className = 'status-item';
        
        const statusClass = getStatusClass(status.estado);
        
        statusItem.innerHTML = `
            <div class="status-indicator ${statusClass}"></div>
            <div class="status-info">
                <div class="status-label">${escapeHtml(status.estado || 'N/A')}</div>
                <div class="status-count">${status.cantidad || 0}</div>
            </div>
        `;
        
        statusGrid.appendChild(statusItem);
    });
}

/**
 * Actualizar tarjeta de agendamiento
 */
function updateSchedulingCard() {
    const schedulingStats = document.getElementById('scheduling-stats');
    if (!schedulingStats) return;
    
    const data = dashboardData;
    
    schedulingStats.innerHTML = `
        <div class="stat-item">
            <div class="stat-label">Total Agendadas</div>
            <div class="stat-value">${data.total_agendadas || 0}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Pendientes</div>
            <div class="stat-value">${data.agendamiento_pendientes || 0}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Completadas</div>
            <div class="stat-value">${data.agendamiento_completadas || 0}</div>
        </div>
    `;
}

/**
 * Actualizar tarjeta de aprovisionamiento
 */
function updateProvisioningCard() {
    const provisioningStats = document.getElementById('provisioning-stats');
    if (!provisioningStats) return;
    
    const data = dashboardData;
    
    provisioningStats.innerHTML = `
        <div class="stat-item">
            <div class="stat-label">Total Cumplidas</div>
            <div class="stat-value">${data.total_cumplidas || 0}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Pendientes</div>
            <div class="stat-value">${data.aprovisionamiento_pendientes || 0}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">En Proceso</div>
            <div class="stat-value">${data.aprovisionamiento_proceso || 0}</div>
        </div>
    `;
}

/**
 * Actualizar tarjeta de efectividad
 */
function updateEffectivenessCard() {
    const efectividad = parseFloat(dashboardData.efectividad) || 0;
    const meta = parseFloat(localStorage.getItem('effectiveness_goal') || CONFIG.EFFECTIVENESS_GOAL_DEFAULT);
    
    updateElementValue('effectiveness-percentage', efectividad.toFixed(1) + '%');
    
    // Actualizar medidor circular
    const fill = document.getElementById('effectiveness-fill');
    if (fill) {
        const percentage = Math.min(100, Math.max(0, efectividad));
        const rotation = (percentage / 100) * 360;
        fill.style.transform = `rotate(${rotation}deg)`;
        
        // Color según rendimiento
        if (efectividad >= meta) {
            fill.style.backgroundColor = '#28a745'; // Verde
        } else if (efectividad >= meta * 0.8) {
            fill.style.backgroundColor = '#ffc107'; // Amarillo
        } else {
            fill.style.backgroundColor = '#dc3545'; // Rojo
        }
    }
    
    // Actualizar detalles
    const detailsContainer = document.getElementById('effectiveness-details');
    if (detailsContainer) {
        detailsContainer.innerHTML = `
            <div class="effectiveness-detail">
                <span class="detail-label">Meta:</span>
                <span class="detail-value">${meta}%</span>
            </div>
            <div class="effectiveness-detail">
                <span class="detail-label">Actual:</span>
                <span class="detail-value">${efectividad.toFixed(1)}%</span>
            </div>
            <div class="effectiveness-detail">
                <span class="detail-label">Diferencia:</span>
                <span class="detail-value ${efectividad >= meta ? 'positive' : 'negative'}">
                    ${efectividad >= meta ? '+' : ''}${(efectividad - meta).toFixed(1)}%
                </span>
            </div>
        `;
    }
}

/**
 * Actualizar tarjeta de tecnologías
 */
function updateTechnologyCard() {
    const technologyStats = document.getElementById('technology-stats');
    if (!technologyStats) return;
    
    const technologies = dashboardData.tecnologias || [];
    const totalVentas = dashboardData.total_ventas || 0;
    
    technologyStats.innerHTML = '';
    
    if (technologies.length === 0) {
        technologyStats.innerHTML = '<div class="no-data">No hay datos de tecnologías disponibles</div>';
        return;
    }
    
    technologies.forEach(tech => {
        const techItem = document.createElement('div');
        techItem.className = 'technology-item';
        
        const cantidad = parseInt(tech.cantidad) || 0;
        const percentage = totalVentas > 0 
            ? ((cantidad / totalVentas) * 100).toFixed(1)
            : 0;
        
        techItem.innerHTML = `
            <div class="tech-info">
                <div class="tech-name">${escapeHtml(tech.tecnologia || 'N/A')}</div>
                <div class="tech-stats">
                    <span class="tech-count">${cantidad}</span>
                    <span class="tech-percentage">(${percentage}%)</span>
                </div>
            </div>
            <div class="tech-bar">
                <div class="tech-fill" style="width: ${percentage}%"></div>
            </div>
        `;
        
        technologyStats.appendChild(techItem);
    });
}

/**
 * Mostrar/ocultar rango de fechas personalizado
 */
function toggleCustomDateRange() {
    const dateFilter = document.getElementById('date-filter');
    const customRange = document.getElementById('custom-date-range');
    
    if (!dateFilter || !customRange) return;
    
    if (dateFilter.value === 'custom') {
        customRange.style.display = 'flex';
    } else {
        customRange.style.display = 'none';
    }
}

/**
 * Aplicar filtros
 */
function applyFilters() {
    try {
        // Obtener valores de filtros
        const dateFilter = document.getElementById('date-filter')?.value || 'current_month';
        const technologyFilter = document.getElementById('tecnologia-filter')?.value || 'all';
        const municipalityFilter = document.getElementById('municipio-filter')?.value || 'all';
        const startDate = document.getElementById('start-date')?.value || null;
        const endDate = document.getElementById('end-date')?.value || null;
        
        // Actualizar filtros actuales
        currentFilters = {
            period: dateFilter,
            technology: technologyFilter,
            municipality: municipalityFilter,
            startDate: startDate,
            endDate: endDate
        };
        
        console.log('Aplicando filtros:', currentFilters);
        
        // Recargar datos
        loadDashboardData();
        
    } catch (error) {
        console.error('Error aplicando filtros:', error);
        showError('Error al aplicar filtros');
    }
}

/**
 * Limpiar filtros
 */
function clearFilters() {
    try {
        // Resetear filtros a valores por defecto
        const dateFilter = document.getElementById('date-filter');
        const technologyFilter = document.getElementById('tecnologia-filter');
        const municipalityFilter = document.getElementById('municipio-filter');
        const startDate = document.getElementById('start-date');
        const endDate = document.getElementById('end-date');
        
        if (dateFilter) dateFilter.value = 'current_month';
        if (technologyFilter) technologyFilter.value = 'all';
        if (municipalityFilter) municipalityFilter.value = 'all';
        if (startDate) startDate.value = '';
        if (endDate) endDate.value = '';
        
        // Ocultar rango personalizado
        toggleCustomDateRange();
        
        // Resetear filtros actuales
        currentFilters = {
            period: 'current_month',
            technology: 'all',
            municipality: 'all',
            startDate: null,
            endDate: null
        };
        
        console.log('Filtros limpiados');
        
        // Recargar datos
        loadDashboardData();
        
    } catch (error) {
        console.error('Error limpiando filtros:', error);
        showError('Error al limpiar filtros');
    }
}

/**
 * Refrescar dashboard
 */
function refreshDashboard() {
    try {
        console.log('Refrescando dashboard...');
        loadDashboardData();
    } catch (error) {
        console.error('Error refrescando dashboard:', error);
        showError('Error al refrescar el dashboard');
    }
}

/**
 * Exportar datos a Excel
 */
async function exportData() {
    try {
        showLoading(true, 'Generando archivo Excel...');
        
        const response = await fetch(CONFIG.API_BASE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'export_dashboard',
                filters: currentFilters
            })
        });
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type');
        
        if (contentType && contentType.includes('application/json')) {
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Error al exportar datos');
            }
        } else if (contentType && (contentType.includes('application/vnd.openxmlformats') || contentType.includes('application/octet-stream'))) {
            // Es un archivo Excel
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = `dashboard_${new Date().toISOString().split('T')[0]}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            showSuccess('Archivo Excel exportado correctamente');
        } else {
            // Respuesta inesperada
            const text = await response.text();
            console.error('Respuesta inesperada al exportar:', text);
            throw new Error('Respuesta inesperada del servidor al exportar');
        }
        
    } catch (error) {
        console.error('Error exportando datos:', error);
        showError('Error al exportar datos: ' + error.message);
    } finally {
        showLoading(false);
    }
}

/**
 * Toggle visibilidad de tarjeta
 */
function toggleCard(cardId) {
    const card = document.getElementById(cardId);
    const toggle = card?.querySelector('.card-toggle');
    const content = card?.querySelector('.card-content');
    
    if (!card || !toggle || !content) return;
    
    const isVisible = toggle.getAttribute('data-visible') === 'true';
    
    if (isVisible) {
        content.style.display = 'none';
        toggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
        toggle.setAttribute('data-visible', 'false');
    } else {
        content.style.display = 'block';
        toggle.innerHTML = '<i class="fas fa-eye"></i>';
        toggle.setAttribute('data-visible', 'true');
    }
}

/**
 * Configurar auto-refresh
 */
function setAutoRefresh() {
    const autoRefreshSelect = document.getElementById('auto-refresh');
    const interval = parseInt(autoRefreshSelect?.value || '0');
    
    // Limpiar intervalo existente
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
    
    // Configurar nuevo intervalo si es necesario
    if (interval > 0) {
        autoRefreshInterval = setInterval(() => {
            console.log('Auto-refresh ejecutándose...');
            loadDashboardData();
        }, interval * 1000);
        
        console.log(`Auto-refresh configurado cada ${interval} segundos`);
    }
}

/**
 * Configurar meta de efectividad
 */
function setEffectivenessGoal() {
    const goalInput = document.getElementById('effectiveness-goal');
    const goal = parseFloat(goalInput?.value) || CONFIG.EFFECTIVENESS_GOAL_DEFAULT;
    
    if (goal < 1 || goal > 100) {
        showError('La meta de efectividad debe estar entre 1% y 100%');
        if (goalInput) goalInput.value = CONFIG.EFFECTIVENESS_GOAL_DEFAULT;
        return;
    }
    
    localStorage.setItem('effectiveness_goal', goal.toString());
    updateEffectivenessCard();
    console.log(`Meta de efectividad establecida en ${goal}%`);
}

/**
 * Abrir modal de configuración
 */
function openSettingsModal() {
    const modal = document.getElementById('settings-modal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

/**
 * Cerrar modal de configuración
 */
function closeSettingsModal() {
    const modal = document.getElementById('settings-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Funciones auxiliares

/**
 * Mostrar/ocultar loading spinner
 */
function showLoading(show, message = 'Cargando datos...') {
    const spinner = document.getElementById('loading-spinner');
    if (!spinner) return;
    
    const messageElement = spinner.querySelector('p');
    if (messageElement) {
        messageElement.textContent = message;
    }
    
    spinner.style.display = show ? 'flex' : 'none';
}

/**
 * Mostrar mensaje de error
 */
function showError(message) {
    console.error(message);
    
    // Crear o actualizar alerta de error
    let alert = document.querySelector('.alert-error');
    if (!alert) {
        alert = document.createElement('div');
        alert.className = 'alert alert-error';
        alert.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
        alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px 20px;
            border-radius: 4px;
            z-index: 9999;
            max-width: 400px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        `;
        
        document.body.appendChild(alert);
    }
    
    alert.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${escapeHtml(message)}`;
    alert.style.display = 'block';
    
    // Auto-ocultar después de 8 segundos
    setTimeout(() => {
        if (alert && alert.parentNode) {
            alert.remove();
        }
    }, 8000);
}

/**
 * Mostrar mensaje de éxito
 */
function showSuccess(message) {
    console.log(message);
    
    // Crear alerta de éxito
    const alert = document.createElement('div');
    alert.className = 'alert alert-success';
    alert.innerHTML = `<i class="fas fa-check-circle"></i> ${escapeHtml(message)}`;
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        padding: 12px 20px;
        border-radius: 4px;
        z-index: 9999;
        max-width: 400px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    `;
    
    document.body.appendChild(alert);
    
    // Auto-eliminar después de 3 segundos
    setTimeout(() => {
        if (alert && alert.parentNode) {
            alert.remove();
        }
    }, 3000);
}

/**
 * Actualizar valor de elemento
 */
function updateElementValue(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value;
    }
}

/**
 * Obtener clase CSS para estado
 */
function getStatusClass(estado) {
    const statusClasses = {
        'AGENDADO': 'status-scheduled',
        'CUMPLIDO': 'status-completed',
        'PENDIENTE': 'status-pending',
        'CANCELADO': 'status-cancelled',
        'REPROGRAMAR': 'status-rescheduled'
    };
    
    return statusClasses[estado] || 'status-default';
}

/**
 * Actualizar tiempo de última actualización
 */
function updateLastUpdateTime() {
    const lastUpdate = document.getElementById('last-update');
    if (lastUpdate) {
        const now = new Date();
        lastUpdate.textContent = now.toLocaleString('es-ES', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
}

/**
 * Escapar HTML para prevenir XSS
 */
function escapeHtml(text) {
    if (typeof text !== 'string') return text;
    
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Cerrar modal al hacer clic fuera de él
    window.onclick = function(event) {
        const modal = document.getElementById('settings-modal');
        if (event.target === modal) {
            closeSettingsModal();
        }
    };
    
    // Configurar filtro de fechas
    const dateFilter = document.getElementById('date-filter');
    if (dateFilter) {
        dateFilter.addEventListener('change', toggleCustomDateRange);
    }
    
    // Configurar auto-refresh al cambiar
    const autoRefresh = document.getElementById('auto-refresh');
    if (autoRefresh) {
        autoRefresh.addEventListener('change', setAutoRefresh);
    }
    
    // Configurar meta de efectividad
    const effectivenessGoal = document.getElementById('effectiveness-goal');
    if (effectivenessGoal) {
        const savedGoal = localStorage.getItem('effectiveness_goal');
        if (savedGoal) {
            effectivenessGoal.value = savedGoal;
        }
        effectivenessGoal.addEventListener('change', setEffectivenessGoal);
    }
    
    // Prevenir envío de formularios accidental
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
        });
    });
});

// Limpiar intervalos al salir de la página
window.addEventListener('beforeunload', function() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
});