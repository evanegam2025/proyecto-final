<?php
// Limpiar cualquier output previo
while (ob_get_level()) {
    ob_end_clean();
}

// Configurar headers JSON inmediatamente
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// Función para enviar respuesta JSON y terminar
function sendJSON($success, $data = null, $message = '') {
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Función para enviar archivo Excel
function sendExcelFile($filename, $content) {
    // Limpiar cualquier output previo
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    echo $content;
    exit;
}

// Capturar todos los errores
try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, null, 'Método no permitido');
    }

    // Obtener datos JSON
    $input = file_get_contents('php://input');
    if (empty($input)) {
        sendJSON(false, null, 'No se recibieron datos');
    }

    $data = json_decode($input, true);
    if ($data === null) {
        sendJSON(false, null, 'JSON inválido');
    }

    $action = $data['action'] ?? '';

    // Iniciar sesión solo si es necesario
    session_start();

    // Incluir conexión a BD
    require_once '../conex_bd.php';
    $conn = getDBConnection();

    switch ($action) {
        case 'ping':
            sendJSON(true, ['status' => 'ok'], 'API funcionando');
            break;

        case 'check_permissions':
            try {
                // Obtener cédula del usuario de la sesión
                $cedula_usuario = $_SESSION['cedula'] ?? null;
                
                if (!$cedula_usuario) {
                    // Si no hay sesión, devolver permisos vacíos
                    sendJSON(true, ['permissions' => []]);
                    break;
                }
                
                // Consultar permisos del usuario usando el procedimiento almacenado
                $stmt = $conn->prepare("CALL ObtenerPermisosUsuario(?)");
                $stmt->execute([$cedula_usuario]);
                $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendJSON(true, ['permissions' => $permisos]);
                
            } catch (Exception $e) {
                // En caso de error, devolver permisos vacíos
                sendJSON(true, ['permissions' => []]);
            }
            break;

        case 'get_municipalities':
            try {
                $stmt = $conn->prepare("SELECT DISTINCT municipio FROM ventas WHERE municipio IS NOT NULL ORDER BY municipio");
                $stmt->execute();
                $municipalities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                sendJSON(true, ['municipalities' => $municipalities]);
            } catch (Exception $e) {
                sendJSON(true, ['municipalities' => []]);
            }
            break;

        case 'get_dashboard_data':
            try {
                $filters = $data['filters'] ?? [];
                
                // Construir WHERE básico
                $whereClause = '';
                $params = [];
                
                // Solo filtro básico de fechas si se especifica
                if (!empty($filters['period'])) {
                    switch ($filters['period']) {
                        case 'current_month':
                            $whereClause = "WHERE DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
                            break;
                        case 'current_year':
                            $whereClause = "WHERE YEAR(fecha) = YEAR(NOW())";
                            break;
                        case 'last_month':
                            $whereClause = "WHERE DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')";
                            break;
                        case 'last_year':
                            $whereClause = "WHERE YEAR(fecha) = YEAR(DATE_SUB(NOW(), INTERVAL 1 YEAR))";
                            break;
                    }
                }
                
                // Filtro por municipio
                if (!empty($filters['municipality']) && $filters['municipality'] !== 'all') {
                    $whereClause .= empty($whereClause) ? "WHERE " : " AND ";
                    $whereClause .= "municipio = ?";
                    $params[] = $filters['municipality'];
                }
                
                // Filtro por tecnología
                if (!empty($filters['technology']) && $filters['technology'] !== 'all') {
                    $whereClause .= empty($whereClause) ? "WHERE " : " AND ";
                    $whereClause .= "tecnologia = ?";
                    $params[] = $filters['technology'];
                }
                
                // Obtener total de ventas
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ventas " . $whereClause);
                $stmt->execute($params);
                $totalVentas = (int)$stmt->fetch()['total'];
                
                // Obtener ventas por mes
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ventas WHERE DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')");
                $stmt->execute();
                $ventasActual = (int)$stmt->fetch()['total'];
                
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ventas WHERE DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')");
                $stmt->execute();
                $ventasAnterior = (int)$stmt->fetch()['total'];
                
                // Tecnologías
                $stmt = $conn->prepare("SELECT COALESCE(tecnologia, 'No especificada') as tecnologia, COUNT(*) as cantidad FROM ventas " . $whereClause . " GROUP BY tecnologia ORDER BY cantidad DESC");
                $stmt->execute($params);
                $tecnologias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Datos de agendamiento
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM agendamiento");
                $stmt->execute();
                $totalAgendadas = (int)$stmt->fetch()['total'];
                
                $stmt = $conn->prepare("SELECT COUNT(*) as pendientes FROM agendamiento WHERE estado_visita IN ('NO Asignado', 'PENDIENTE')");
                $stmt->execute();
                $agendamientoPendientes = (int)$stmt->fetch()['pendientes'];
                
                $stmt = $conn->prepare("SELECT COUNT(*) as completadas FROM agendamiento WHERE estado_visita = 'AGENDADO'");
                $stmt->execute();
                $agendamientoCompletadas = (int)$stmt->fetch()['completadas'];
                
                // Datos de aprovisionamiento
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM aprovisionamiento WHERE estado_aprovisionamiento = 'CUMPLIDO'");
                $stmt->execute();
                $totalCumplidas = (int)$stmt->fetch()['total'];
                
                $stmt = $conn->prepare("SELECT COUNT(*) as pendientes FROM aprovisionamiento WHERE estado_aprovisionamiento IN ('NO ASIGNADO', 'PENDIENTE')");
                $stmt->execute();
                $aprovisionamientoPendientes = (int)$stmt->fetch()['pendientes'];
                
                $stmt = $conn->prepare("SELECT COUNT(*) as proceso FROM aprovisionamiento WHERE estado_aprovisionamiento IN ('AGENDADO')");
                $stmt->execute();
                $aprovisionamientoProceso = (int)$stmt->fetch()['proceso'];
                
                // Calcular efectividad
                $efectividad = $totalVentas > 0 ? ($totalCumplidas / $totalVentas) * 100 : 0;
                
                $dashboardData = [
                    'total_ventas' => $totalVentas,
                    'total_agendadas' => $totalAgendadas,
                    'total_cumplidas' => $totalCumplidas,
                    'efectividad' => round($efectividad, 2),
                    'ventas_mes_actual' => $ventasActual,
                    'ventas_mes_anterior' => $ventasAnterior,
                    'estados_ventas' => [
                        ['estado' => 'VENDIDO', 'cantidad' => $totalVentas]
                    ],
                    'agendamiento_pendientes' => $agendamientoPendientes,
                    'agendamiento_completadas' => $agendamientoCompletadas,
                    'aprovisionamiento_pendientes' => $aprovisionamientoPendientes,
                    'aprovisionamiento_proceso' => $aprovisionamientoProceso,
                    'tecnologias' => $tecnologias
                ];
                
                sendJSON(true, $dashboardData);
                
            } catch (Exception $e) {
                // En caso de error, devolver datos vacíos
                $emptyData = [
                    'total_ventas' => 0,
                    'total_agendadas' => 0,
                    'total_cumplidas' => 0,
                    'efectividad' => 0,
                    'ventas_mes_actual' => 0,
                    'ventas_mes_anterior' => 0,
                    'estados_ventas' => [],
                    'agendamiento_pendientes' => 0,
                    'agendamiento_completadas' => 0,
                    'aprovisionamiento_pendientes' => 0,
                    'aprovisionamiento_proceso' => 0,
                    'tecnologias' => []
                ];
                sendJSON(true, $emptyData);
            }
            break;

        case 'export_dashboard':
            try {
                // Verificar permisos de exportación
                $cedula_usuario = $_SESSION['cedula'] ?? null;
                if (!$cedula_usuario) {
                    sendJSON(false, null, 'Usuario no autenticado');
                    break;
                }
                
                // Verificar si el usuario tiene permisos para exportar
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as tiene_permiso 
                    FROM administrador a
                    INNER JOIN modulo_permisos mp ON a.modulo = mp.modulo
                    INNER JOIN permisos p ON mp.permiso_id = p.id
                    WHERE a.cedula = ? AND p.nombre IN ('exportar_dashboard', 'dashboard', 'administrar_permisos')
                ");
                $stmt->execute([$cedula_usuario]);
                $permisos = $stmt->fetch();
                
                if (!$permisos || $permisos['tiene_permiso'] == 0) {
                    sendJSON(false, null, 'No tiene permisos para exportar dashboard');
                    break;
                }
                
                $filters = $data['filters'] ?? [];
                
                // Construir WHERE clause para filtros
                $whereClause = '';
                $params = [];
                
                if (!empty($filters['period'])) {
                    switch ($filters['period']) {
                        case 'current_month':
                            $whereClause = "WHERE DATE_FORMAT(v.fecha, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
                            break;
                        case 'current_year':
                            $whereClause = "WHERE YEAR(v.fecha) = YEAR(NOW())";
                            break;
                        case 'last_month':
                            $whereClause = "WHERE DATE_FORMAT(v.fecha, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')";
                            break;
                        case 'last_year':
                            $whereClause = "WHERE YEAR(v.fecha) = YEAR(DATE_SUB(NOW(), INTERVAL 1 YEAR))";
                            break;
                    }
                }
                
                if (!empty($filters['municipality']) && $filters['municipality'] !== 'all') {
                    $whereClause .= empty($whereClause) ? "WHERE " : " AND ";
                    $whereClause .= "v.municipio = ?";
                    $params[] = $filters['municipality'];
                }
                
                if (!empty($filters['technology']) && $filters['technology'] !== 'all') {
                    $whereClause .= empty($whereClause) ? "WHERE " : " AND ";
                    $whereClause .= "v.tecnologia = ?";
                    $params[] = $filters['technology'];
                }
                
                // Consultar datos completos para exportación
                $query = "
                    SELECT 
                        v.id as 'ID Venta',
                        DATE_FORMAT(v.fecha, '%d/%m/%Y %H:%i') as 'Fecha Venta',
                        v.cedula as 'Cédula Cliente',
                        v.nombre as 'Nombre Cliente',
                        v.telefono1 as 'Teléfono 1',
                        v.telefono2 as 'Teléfono 2',
                        v.email as 'Email',
                        v.municipio as 'Municipio',
                        v.vereda as 'Vereda',
                        v.coordenadas as 'Coordenadas',
                        v.tecnologia as 'Tecnología',
                        v.plan as 'Plan',
                        v.vendedor_nombre as 'Vendedor',
                        COALESCE(a.estado_visita, 'Sin Agendar') as 'Estado Agendamiento',
                        COALESCE(DATE_FORMAT(a.fecha_visita, '%d/%m/%Y'), '') as 'Fecha Visita',
                        COALESCE(a.tecnico_asignado, '') as 'Técnico Asignado',
                        COALESCE(ap.estado_aprovisionamiento, 'Sin Aprovisionar') as 'Estado Aprovisionamiento',
                        COALESCE(ap.tipo_router_onu, '') as 'Tipo Router/ONU',
                        COALESCE(ap.ip_navegacion, '') as 'IP Navegación'
                    FROM ventas v
                    LEFT JOIN agendamiento a ON v.cedula = a.cedula_cliente
                    LEFT JOIN aprovisionamiento ap ON v.cedula = ap.cedula_cliente
                    $whereClause
                    ORDER BY v.fecha DESC
                ";
                
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Generar archivo Excel
                $excel_content = generateExcelContent($ventas);
                $filename = 'dashboard_ventas_' . date('Y-m-d_H-i-s') . '.xlsx';
                
                sendExcelFile($filename, $excel_content);
                
            } catch (Exception $e) {
                sendJSON(false, null, 'Error al exportar dashboard: ' . $e->getMessage());
            }
            break;

        default:
            sendJSON(false, null, 'Acción no válida: ' . $action);
    }

} catch (Exception $e) {
    sendJSON(false, null, 'Error del servidor: ' . $e->getMessage());
}

/**
 * Generar contenido de Excel en formato CSV (básico)
 * En un entorno real deberías usar PhpSpreadsheet o similar
 */
function generateExcelContent($data) {
    $csv = '';
    
    if (empty($data)) {
        return "No hay datos para exportar";
    }
    
    // Agregar encabezados BOM para UTF-8
    $csv = "\xEF\xBB\xBF";
    
    // Agregar encabezados
    $headers = array_keys($data[0]);
    $csv .= '"' . implode('","', $headers) . '"' . "\n";
    
    // Agregar datos
    foreach ($data as $row) {
        $values = array_map(function($value) {
            return str_replace('"', '""', $value); // Escapar comillas dobles
        }, array_values($row));
        $csv .= '"' . implode('","', $values) . '"' . "\n";
    }
    
    return $csv;
}
?>