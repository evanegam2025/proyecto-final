// js/dashboard.js


        let chartInstances = {};

        document.addEventListener('DOMContentLoaded', function() {
            actualizarDashboard();
        });

        async function actualizarDashboard() {
            const periodo = document.getElementById('periodoFilter').value;
            mostrarLoading(true);

            try {
                const response = await fetch(`api/api_dashboard.php?periodo=${periodo}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json; charset=UTF-8'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();

                if (data.success) {
                    if (data.data.usuario) {
                        document.getElementById('userName').textContent = data.data.usuario.nombre || '';
                        document.getElementById('userRole').textContent = data.data.usuario.modulo || '';
                    }
                    
                    if (data.data.periodo) {
                        const periodoTexto = `${data.data.periodo.fecha_inicio} a ${data.data.periodo.fecha_fin}`;
                        document.getElementById('periodoInfo').textContent = periodoTexto;
                    }
                    
                    renderizarStats(data.data.stats);
                    renderizarCharts(data.data);
                } else {
                    mostrarError('Error al cargar datos: ' + (data.message || 'Error desconocido'));
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarError('Error de conexión con el servidor');
            } finally {
                mostrarLoading(false);
            }
        }

        function renderizarStats(stats) {
            const container = document.getElementById('statsContainer');
            const statsConfig = [
                { key: 'total_ventas', label: 'Total Ventas', icon: 'bi-cart-check', color: 'primary', class: 'activado' },
                { key: 'total_agendados', label: 'Agendados', icon: 'bi-calendar-check', color: 'info', class: 'agendado' },
                { key: 'total_pendientes', label: 'Pendientes', icon: 'bi-clock-history', color: 'warning', class: 'pendiente' },
                { key: 'total_reprogramar', label: 'Reprogramar', icon: 'bi-arrow-repeat', color: 'warning', class: 'pendiente' },
                { key: 'total_cumplidos', label: 'Cumplidos', icon: 'bi-check-circle', color: 'success', class: 'cumplido' },
                { key: 'total_cancelados', label: 'Cancelados', icon: 'bi-x-circle', color: 'danger', class: 'cancelado' },
                { key: 'total_no_asignados', label: 'No Asignados', icon: 'bi-dash-circle', color: 'secondary', class: 'pendiente' },
                { key: 'fibra_optica', label: 'Fibra Óptica', icon: 'bi-router', color: 'info', class: 'aprobado' },
                { key: 'radio_enlace', label: 'Radio Enlace', icon: 'bi-broadcast', color: 'info', class: 'aprobado' }
            ];

            container.innerHTML = statsConfig.map(config => `
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="stats-card fade-in ${config.class}">
                        <div class="card-icon bg-${config.color} bg-opacity-10 text-${config.color}">
                            <i class="bi ${config.icon}"></i>
                        </div>
                        <div class="card-value">${stats[config.key] || 0}</div>
                        <div class="card-label">${config.label}</div>
                    </div>
                </div>
            `).join('');
        }

        // Paleta de colores pastel empresariales
        const coloresPastel = [
            'rgba(147, 197, 253, 0.85)',  // Azul pastel (Bootstrap primary light)
            'rgba(167, 243, 208, 0.85)',  // Verde pastel (Bootstrap success light)
            'rgba(253, 230, 138, 0.85)',  // Amarillo pastel (Bootstrap warning light)
            'rgba(252, 165, 165, 0.85)',  // Rojo pastel (Bootstrap danger light)
            'rgba(196, 181, 253, 0.85)',  // Púrpura pastel (Bootstrap secondary light)
            'rgba(165, 243, 252, 0.85)',  // Cyan pastel (Bootstrap info light)
            'rgba(254, 202, 202, 0.85)',  // Rosa pastel
            'rgba(191, 219, 254, 0.85)',  // Azul cielo pastel
            'rgba(233, 213, 255, 0.85)',  // Lavanda pastel
            'rgba(187, 247, 208, 0.85)',  // Menta pastel
            'rgba(254, 240, 138, 0.85)',  // Lima pastel
            'rgba(251, 207, 232, 0.85)',  // Magenta pastel
            'rgba(209, 213, 219, 0.85)',  // Gris pastel
            'rgba(254, 215, 170, 0.85)',  // Naranja pastel
            'rgba(186, 230, 253, 0.85)'   // Azul agua pastel
        ];

        function obtenerColoresDinamicos(cantidad) {
            const colores = [];
            for (let i = 0; i < cantidad; i++) {
                colores.push(coloresPastel[i % coloresPastel.length]);
            }
            return colores;
        }

        function renderizarCharts(data) {
            Chart.defaults.font.family = "'Poppins', sans-serif";
            Chart.defaults.responsive = true;
            Chart.defaults.maintainAspectRatio = false;

            // Estados Chart - Colores según estado
            if (chartInstances.estados) chartInstances.estados.destroy();
            const ctxEstados = document.getElementById('estadosChartCanvas').getContext('2d');
            chartInstances.estados = new Chart(ctxEstados, {
                type: 'bar',
                data: {
                    labels: data.estados.labels,
                    datasets: [{
                        label: 'Cantidad',
                        data: data.estados.values,
                        backgroundColor: [
                            'rgba(147, 197, 253, 0.85)',  // AGENDADO - Azul pastel
                            'rgba(253, 230, 138, 0.85)',  // PENDIENTE - Amarillo pastel
                            'rgba(167, 243, 208, 0.85)',  // CUMPLIDO - Verde pastel
                            'rgba(254, 215, 170, 0.85)',  // REPROGRAMAR - Naranja pastel
                            'rgba(252, 165, 165, 0.85)',  // CANCELADO - Rojo pastel
                            'rgba(209, 213, 219, 0.85)'   // NO ASIGNADO - Gris pastel
                        ],
                        borderRadius: 8,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.95)',
                            padding: 12,
                            titleFont: { size: 14, weight: '600' },
                            bodyFont: { size: 13 },
                            cornerRadius: 8,
                            displayColors: true,
                            boxPadding: 6
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                font: { size: 11 },
                                color: '#64748b'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            }
                        },
                        x: {
                            ticks: {
                                font: { size: 10 },
                                color: '#64748b',
                                maxRotation: 45,
                                minRotation: 0
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Tecnologías Chart
            if (chartInstances.tecnologias) chartInstances.tecnologias.destroy();
            const ctxTec = document.getElementById('tecnologiasChartCanvas').getContext('2d');
            const coloresTecnologias = obtenerColoresDinamicos(data.tecnologias.labels.length);
            
            chartInstances.tecnologias = new Chart(ctxTec, {
                type: 'doughnut',
                data: {
                    labels: data.tecnologias.labels,
                    datasets: [{
                        data: data.tecnologias.values,
                        backgroundColor: coloresTecnologias,
                        borderWidth: 3,
                        borderColor: '#fff',
                        hoverBorderWidth: 4,
                        hoverBorderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: { size: 12 },
                                usePointStyle: true,
                                pointStyle: 'circle',
                                color: '#64748b',
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const value = data.datasets[0].data[i];
                                            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                            return {
                                                text: `${label} (${percentage}%)`,
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.95)',
                            padding: 12,
                            titleFont: { size: 14, weight: '600' },
                            bodyFont: { size: 13 },
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return ` ${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Planes Chart - Cada barra con color diferente
            if (chartInstances.planes) chartInstances.planes.destroy();
            const ctxPlanes = document.getElementById('planesChartCanvas').getContext('2d');
            const coloresPlanes = obtenerColoresDinamicos(data.planes.labels.length);
            
            chartInstances.planes = new Chart(ctxPlanes, {
                type: 'bar',
                data: {
                    labels: data.planes.labels,
                    datasets: [{
                        label: 'Ventas por Plan',
                        data: data.planes.values,
                        backgroundColor: coloresPlanes,
                        borderRadius: 8,
                        borderWidth: 0,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.95)',
                            padding: 12,
                            titleFont: { size: 14, weight: '600' },
                            bodyFont: { size: 13 },
                            cornerRadius: 8,
                            displayColors: true,
                            boxPadding: 6,
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.x || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return ` ${label}: ${value} ventas (${percentage}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                font: { size: 11 },
                                color: '#64748b'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            }
                        },
                        y: {
                            ticks: {
                                font: { size: 11 },
                                color: '#64748b',
                                autoSkip: false
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        function toggleChart(canvasId) {
            const canvas = document.getElementById(canvasId);
            const wrapper = canvas.parentElement;
            const button = wrapper.previousElementSibling.querySelector('.toggle-btn i');
            
            if (wrapper.style.display === 'none') {
                wrapper.style.display = 'block';
                button.className = 'bi bi-chevron-up';
                setTimeout(() => {
                    Object.values(chartInstances).forEach(chart => {
                        if (chart && typeof chart.resize === 'function') {
                            chart.resize();
                        }
                    });
                }, 300);
            } else {
                wrapper.style.display = 'none';
                button.className = 'bi bi-chevron-down';
            }
        }

        function mostrarLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            overlay.classList.toggle('active', show);
        }

        function mostrarError(mensaje) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: mensaje,
                confirmButtonColor: '#2563eb',
                confirmButtonText: 'Aceptar'
            });
        }

        function cerrarSesion() {
            Swal.fire({
                title: '¿Cerrar Sesión?',
                text: '¿Estás seguro que deseas salir?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Sí, cerrar sesión',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        // Redimensionar charts en cambio de orientación o tamaño
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                Object.values(chartInstances).forEach(chart => {
                    if (chart && typeof chart.resize === 'function') {
                        chart.resize();
                    }
                });
            }, 250);
        });
    