<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

require_once '../conex_bd.php';

function enviarRespuesta($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    $response = array(
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    );
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    enviarRespuesta(false, null, 'No autorizado', 401);
}

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes_actual';
    $usuario_cedula = isset($_SESSION['user_cedula']) ? $_SESSION['user_cedula'] : '';
    $usuario_modulo = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
    
    $fecha_inicio = '';
    $fecha_fin = date('Y-m-d');
    
    switch ($periodo) {
        case 'mes_actual':
            $fecha_inicio = date('Y-m-01');
            break;
        case 'mes_anterior':
            $fecha_inicio = date('Y-m-01', strtotime('-1 month'));
            $fecha_fin = date('Y-m-t', strtotime('-1 month'));
            break;
        case 'ultimos_30':
            $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
            break;
        case 'ultimos_90':
            $fecha_inicio = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'anio_actual':
            $fecha_inicio = date('Y-01-01');
            break;
        default:
            $fecha_inicio = date('Y-m-01');
    }
    
    // Total ventas en el periodo
    $sql_ventas = "SELECT COUNT(*) as total FROM ventas WHERE DATE(created_at) BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql_ventas);
    $stmt->execute(array($fecha_inicio, $fecha_fin));
    $total_ventas = intval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
    
    // Query optimizada que obtiene el estado prioritario por cedula
    // Prioridad: Aprovisionamiento > Agendamiento > Venta (solo si no existe en los otros)
    $sql_estados_unicos = "
    SELECT 
        v.cedula,
        CASE 
            WHEN ap.estado_aprovisionamiento IS NOT NULL THEN ap.estado_aprovisionamiento
            WHEN ag.estado_visita IS NOT NULL THEN ag.estado_visita
            ELSE 'SIN ASIGNAR'
        END as estado_actual,
        CASE 
            WHEN ap.estado_aprovisionamiento IS NOT NULL THEN 'APROVISIONAMIENTO'
            WHEN ag.estado_visita IS NOT NULL THEN 'AGENDAMIENTO'
            ELSE 'VENTA'
        END as modulo_actual
    FROM ventas v
    LEFT JOIN agendamiento ag ON v.cedula = ag.cedula_cliente
    LEFT JOIN aprovisionamiento ap ON v.cedula = ap.cedula_cliente
    WHERE DATE(v.created_at) BETWEEN ? AND ?
    ";
    
    $stmt = $pdo->prepare($sql_estados_unicos);
    $stmt->execute(array($fecha_inicio, $fecha_fin));
    $estados_por_cedula = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Inicializar contadores
    $total_agendados = 0;
    $total_pendientes = 0;
    $total_cumplidos = 0;
    $total_cancelados = 0;
    $total_reprogramar = 0;
    $total_no_asignados = 0;
    
    // Contar estados unicos por cedula
    foreach ($estados_por_cedula as $registro) {
        $estado = mb_strtoupper(trim($registro['estado_actual']), 'UTF-8');
        
        switch ($estado) {
            case 'AGENDADO':
                $total_agendados++;
                break;
            case 'PENDIENTE':
                $total_pendientes++;
                break;
            case 'CUMPLIDO':
                $total_cumplidos++;
                break;
            case 'CANCELADO':
                $total_cancelados++;
                break;
            case 'REPROGRAMAR':
                $total_reprogramar++;
                break;
            case 'NO ASIGNADO':
            case 'NO Asignado':
            case 'SIN ASIGNAR':
                $total_no_asignados++;
                break;
        }
    }
    
    // Tecnologias
    $sql_tecnologias = "SELECT tecnologia, COUNT(*) as total 
                        FROM ventas 
                        WHERE DATE(created_at) BETWEEN ? AND ? 
                        GROUP BY tecnologia";
    $stmt = $pdo->prepare($sql_tecnologias);
    $stmt->execute(array($fecha_inicio, $fecha_fin));
    $tecnologias_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fibra_optica = 0;
    $radio_enlace = 0;
    $tecnologias_labels = array();
    $tecnologias_values = array();
    
    foreach ($tecnologias_data as $tec) {
        $tec_nombre = trim($tec['tecnologia']);
        $total = intval($tec['total']);
        
        $tecnologias_labels[] = $tec_nombre;
        $tecnologias_values[] = $total;
        
        if (mb_stripos($tec_nombre, 'Fibra', 0, 'UTF-8') !== false || 
            mb_stripos($tec_nombre, 'ptica', 0, 'UTF-8') !== false) {
            $fibra_optica += $total;
        } elseif (mb_stripos($tec_nombre, 'Radio', 0, 'UTF-8') !== false) {
            $radio_enlace += $total;
        }
    }
    
    // Planes
    $sql_planes = "SELECT plan, COUNT(*) as total 
                   FROM ventas 
                   WHERE DATE(created_at) BETWEEN ? AND ? 
                   GROUP BY plan 
                   ORDER BY total DESC";
    $stmt = $pdo->prepare($sql_planes);
    $stmt->execute(array($fecha_inicio, $fecha_fin));
    $planes_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $planes_labels = array();
    $planes_values = array();
    
    foreach ($planes_data as $plan) {
        $planes_labels[] = trim($plan['plan']);
        $planes_values[] = intval($plan['total']);
    }
    
    // Municipios
    $sql_municipios = "SELECT municipio, COUNT(*) as total 
                       FROM ventas 
                       WHERE DATE(created_at) BETWEEN ? AND ? 
                       GROUP BY municipio 
                       ORDER BY total DESC 
                       LIMIT 5";
    $stmt = $pdo->prepare($sql_municipios);
    $stmt->execute(array($fecha_inicio, $fecha_fin));
    $municipios_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $municipios_labels = array();
    $municipios_values = array();
    
    foreach ($municipios_data as $mun) {
        $municipios_labels[] = trim($mun['municipio']);
        $municipios_values[] = intval($mun['total']);
    }
    
    // Info usuario
    $info_usuario = array(
        'nombre' => isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '',
        'modulo' => $usuario_modulo,
        'cedula' => $usuario_cedula
    );
    
    // Porcentajes
    $porcentaje_cumplidos = $total_ventas > 0 ? round(($total_cumplidos / $total_ventas) * 100, 1) : 0;
    $porcentaje_pendientes = $total_ventas > 0 ? round(($total_pendientes / $total_ventas) * 100, 1) : 0;
    
    // Respuesta
    $response_data = array(
        'stats' => array(
            'total_ventas' => $total_ventas,
            'total_agendados' => $total_agendados,
            'total_pendientes' => $total_pendientes,
            'total_cumplidos' => $total_cumplidos,
            'total_cancelados' => $total_cancelados,
            'total_reprogramar' => $total_reprogramar,
            'total_no_asignados' => $total_no_asignados,
            'fibra_optica' => $fibra_optica,
            'radio_enlace' => $radio_enlace,
            'porcentaje_cumplidos' => $porcentaje_cumplidos,
            'porcentaje_pendientes' => $porcentaje_pendientes
        ),
        'estados' => array(
            'labels' => array('AGENDADO', 'PENDIENTE', 'CUMPLIDO', 'REPROGRAMAR', 'CANCELADO', 'NO ASIGNADO'),
            'values' => array(
                $total_agendados, 
                $total_pendientes, 
                $total_cumplidos, 
                $total_reprogramar, 
                $total_cancelados, 
                $total_no_asignados
            )
        ),
        'tecnologias' => array(
            'labels' => $tecnologias_labels,
            'values' => $tecnologias_values
        ),
        'planes' => array(
            'labels' => $planes_labels,
            'values' => $planes_values
        ),
        'municipios' => array(
            'labels' => $municipios_labels,
            'values' => $municipios_values
        ),
        'usuario' => $info_usuario,
        'periodo' => array(
            'tipo' => $periodo,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin
        ),
        'debug' => array(
            'total_registros_procesados' => count($estados_por_cedula),
            'charset' => 'UTF-8'
        )
    );
    
    enviarRespuesta(true, $response_data, 'Datos cargados correctamente', 200);
    
} catch (PDOException $e) {
    error_log("Error PDO: " . $e->getMessage());
    enviarRespuesta(false, null, 'Error de base de datos', 500);
} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    enviarRespuesta(false, null, 'Error del servidor', 500);
}
?>