<?php
// ======================================================================
// DESCARGAR BASE DE DATOS UNIFICADA COMPLETA (VENTAS + AGENDAMIENTO + APROVISIONAMIENTO)
// ======================================================================

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "proyecto-final";

// Crear conexión y establecer charset a UTF-8mb4
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

session_start();

// VERIFICACIÓN DE SESIÓN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    header("Location: index.php");
    exit();
}

// Obtener información del usuario logueado
$cedula_usuario_logueado = $_SESSION['cedula'];
$nombre_usuario_logueado = $_SESSION['nombre'];

// Configurar zona horaria de Colombia
date_default_timezone_set('America/Bogota');

// Headers para forzar descarga como archivo Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="Reporte_Completo_Unificado_' . $nombre_usuario_logueado . '_' . date('Y-m-d_H-i-s') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM para UTF-8
echo "\xEF\xBB\xBF";

// Función para limpiar datos para Excel
function limpiarParaExcel($data) {
    if (is_null($data) || $data === '') {
        return '';
    }
    // Reemplazar caracteres problemáticos
    $data = str_replace(['"', "'", "\r\n", "\r", "\n"], ['""', "''", " ", " ", " "], $data);
    // Sanitizar para evitar inyección de fórmulas
    if (strpos($data, '=') === 0 || strpos($data, '+') === 0 || strpos($data, '-') === 0 || strpos($data, '@') === 0) {
        $data = "'" . $data;
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Función para determinar el estado completo del cliente
function determinarEstadoCompleto($tiene_venta, $tiene_agendamiento, $tiene_aprovisionamiento) {
    if ($tiene_venta && $tiene_agendamiento && $tiene_aprovisionamiento) {
        return 'PROCESO COMPLETO';
    } elseif ($tiene_venta && $tiene_agendamiento && !$tiene_aprovisionamiento) {
        return 'PENDIENTE APROVISIONAMIENTO';
    } elseif ($tiene_venta && !$tiene_agendamiento && !$tiene_aprovisionamiento) {
        return 'SOLO VENTA';
    } elseif (!$tiene_venta && $tiene_agendamiento && !$tiene_aprovisionamiento) {
        return 'SOLO AGENDAMIENTO';
    } elseif (!$tiene_venta && !$tiene_agendamiento && $tiene_aprovisionamiento) {
        return 'SOLO APROVISIONAMIENTO';
    } elseif ($tiene_venta && !$tiene_agendamiento && $tiene_aprovisionamiento) {
        return 'VENTA Y APROVISIONAMIENTO';
    } elseif (!$tiene_venta && $tiene_agendamiento && $tiene_aprovisionamiento) {
        return 'AGENDAMIENTO Y APROVISIONAMIENTO';
    }
    return 'ESTADO INDEFINIDO';
}

// OBTENER TODAS LAS CÉDULAS ÚNICAS DE TODAS LAS TABLAS
$cedulas_todas = [];

// Obtener cédulas de ventas
$result_cedulas_ventas = $conn->query("SELECT DISTINCT cedula FROM ventas ORDER BY cedula");
while ($row = $result_cedulas_ventas->fetch_assoc()) {
    $cedulas_todas[] = $row['cedula'];
}

// Obtener cédulas de agendamiento
$result_cedulas_agenda = $conn->query("SELECT DISTINCT cedula_cliente FROM agendamiento ORDER BY cedula_cliente");
while ($row = $result_cedulas_agenda->fetch_assoc()) {
    if (!in_array($row['cedula_cliente'], $cedulas_todas)) {
        $cedulas_todas[] = $row['cedula_cliente'];
    }
}

// Obtener cédulas de aprovisionamiento
$result_cedulas_aprovisionamiento = $conn->query("SELECT DISTINCT cedula_cliente FROM aprovisionamiento ORDER BY cedula_cliente");
while ($row = $result_cedulas_aprovisionamiento->fetch_assoc()) {
    if (!in_array($row['cedula_cliente'], $cedulas_todas)) {
        $cedulas_todas[] = $row['cedula_cliente'];
    }
}

// Ordenar las cédulas
sort($cedulas_todas);

// PROCESAR CADA CÉDULA Y UNIFICAR LA INFORMACIÓN
$datos_completos = [];

foreach ($cedulas_todas as $cedula_cliente) {
    // Buscar información de venta
    $stmt_venta = $conn->prepare("SELECT * FROM ventas WHERE cedula = ? ORDER BY fecha DESC LIMIT 1");
    $stmt_venta->bind_param("s", $cedula_cliente);
    $stmt_venta->execute();
    $result_venta = $stmt_venta->get_result();
    $venta = $result_venta->fetch_assoc();
    
    // Buscar información de agendamiento
    $stmt_agenda = $conn->prepare("SELECT * FROM agendamiento WHERE cedula_cliente = ? ORDER BY fecha_registro DESC LIMIT 1");
    $stmt_agenda->bind_param("s", $cedula_cliente);
    $stmt_agenda->execute();
    $result_agenda = $stmt_agenda->get_result();
    $agendamiento = $result_agenda->fetch_assoc();
    
    // Buscar información de aprovisionamiento
    $stmt_aprovisionamiento = $conn->prepare("SELECT * FROM aprovisionamiento WHERE cedula_cliente = ? ORDER BY fecha_registro DESC LIMIT 1");
    $stmt_aprovisionamiento->bind_param("s", $cedula_cliente);
    $stmt_aprovisionamiento->execute();
    $result_aprovisionamiento = $stmt_aprovisionamiento->get_result();
    $aprovisionamiento = $result_aprovisionamiento->fetch_assoc();
    
    // Determinar estados
    $tiene_venta = ($venta !== null);
    $tiene_agendamiento = ($agendamiento !== null);
    $tiene_aprovisionamiento = ($aprovisionamiento !== null);
    $estado_proceso = determinarEstadoCompleto($tiene_venta, $tiene_agendamiento, $tiene_aprovisionamiento);
    
    // Crear registro unificado
    $registro = [
        // DATOS DE VENTA
        'fecha_venta' => $venta['fecha'] ?? null,
        'cedula' => $cedula_cliente,
        'nombre' => $venta['nombre'] ?? ($agendamiento ? 'CLIENTE AGENDADO' : ($aprovisionamiento ? 'CLIENTE APROVISIONADO' : 'CLIENTE SIN DATOS')),
        'telefono1' => $venta['telefono1'] ?? null,
        'telefono2' => $venta['telefono2'] ?? null,
        'email' => $venta['email'] ?? null,
        'municipio' => $venta['municipio'] ?? null,
        'vereda' => $venta['vereda'] ?? null,
        'coordenadas' => $venta['coordenadas'] ?? null,
        'indicaciones' => $venta['indicaciones'] ?? null,
        'notas_venta' => $venta['notas'] ?? null,
        'num_servicio' => $venta['num_servicio'] ?? null,
        'tecnologia' => $venta['tecnologia'] ?? null,
        'plan' => $venta['plan'] ?? null,
        'vendedor_nombre' => $venta['vendedor_nombre'] ?? $nombre_usuario_logueado,
        'vendedor_cedula' => $venta['vendedor_cedula'] ?? $cedula_usuario_logueado,
        
        // DATOS DE AGENDAMIENTO
        'fecha_visita' => $agendamiento['fecha_visita'] ?? null,
        'franja_visita' => $agendamiento['franja_visita'] ?? null,
        'tecnico_asignado' => $agendamiento['tecnico_asignado'] ?? null,
        'estado_visita' => $agendamiento['estado_visita'] ?? null,
        'notas_agendamiento' => $agendamiento['notas'] ?? null,
        'fecha_registro_agendamiento' => $agendamiento['fecha_registro'] ?? null,
        
        // DATOS DE APROVISIONAMIENTO
        'tipo_radio' => $aprovisionamiento['tipo_radio'] ?? null,
        'mac_serial_radio' => $aprovisionamiento['mac_serial_radio'] ?? null,
        'tipo_router_onu' => $aprovisionamiento['tipo_router_onu'] ?? null,
        'mac_serial_router' => $aprovisionamiento['mac_serial_router'] ?? null,
        'ip_navegacion' => $aprovisionamiento['ip_navegacion'] ?? null,
        'ip_gestion' => $aprovisionamiento['ip_gestion'] ?? null,
        'metros_cable' => $aprovisionamiento['metros_cable'] ?? null,
        'tipo_cable' => $aprovisionamiento['tipo_cable'] ?? null,
        'notas_aprovisionamiento' => $aprovisionamiento['notas_aprovisionamiento'] ?? null,
        'estado_aprovisionamiento' => $aprovisionamiento['estado_aprovisionamiento'] ?? null,
        'fecha_registro_aprovisionamiento' => $aprovisionamiento['fecha_registro'] ?? null,
        
        // ESTADO DEL PROCESO
        'estado_proceso' => $estado_proceso,
        'tiene_venta' => $tiene_venta,
        'tiene_agendamiento' => $tiene_agendamiento,
        'tiene_aprovisionamiento' => $tiene_aprovisionamiento
    ];
    
    $datos_completos[] = $registro;
    
    $stmt_venta->close();
    $stmt_agenda->close();
    $stmt_aprovisionamiento->close();
}

// CALCULAR ESTADÍSTICAS
$total_registros = count($datos_completos);
$estadisticas = [
    'PROCESO COMPLETO' => 0,
    'PENDIENTE APROVISIONAMIENTO' => 0,
    'SOLO VENTA' => 0,
    'SOLO AGENDAMIENTO' => 0,
    'SOLO APROVISIONAMIENTO' => 0,
    'VENTA Y APROVISIONAMIENTO' => 0,
    'AGENDAMIENTO Y APROVISIONAMIENTO' => 0,
    'ESTADO INDEFINIDO' => 0
];

foreach ($datos_completos as $registro) {
    $estado = $registro['estado_proceso'];
    if (isset($estadisticas[$estado])) {
        $estadisticas[$estado]++;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte Completo Unificado - Ventas + Agendamiento + Aprovisionamiento</title>
</head>
<body>
    <table border="1">
        <!-- ENCABEZADO DEL REPORTE -->
        <tr>
            <td colspan="31" style="text-align:center; font-weight:bold; font-size:18px; background-color:#1565C0; color:white;">
                REPORTE COMPLETO UNIFICADO - VENTAS + AGENDAMIENTO + APROVISIONAMIENTO
            </td>
        </tr>
        <tr>
            <td colspan="31" style="text-align:center; font-weight:bold; font-size:14px;">
                Usuario: <?php echo htmlspecialchars($nombre_usuario_logueado, ENT_QUOTES, 'UTF-8'); ?>
            </td>
        </tr>
        <tr>
            <td colspan="31" style="text-align:center; font-size:12px;">
                Generado el: <?php echo date('d/m/Y H:i:s'); ?>
            </td>
        </tr>
        
        <!-- ESTADÍSTICAS RESUMIDAS -->
        <tr></tr>
        <tr style="background-color:#E3F2FD;">
            <td colspan="15" style="font-weight:bold; text-align:center; font-size:14px;">ESTADÍSTICAS DEL REPORTE</td>
            <td colspan="16"></td>
        </tr>
        <tr style="background-color:#F1F8E9;">
            <td style="font-weight:bold;">Total Registros:</td>
            <td style="font-weight:bold; color:#1565C0;"><?php echo $total_registros; ?></td>
            <td style="font-weight:bold;">Proceso Completo:</td>
            <td style="font-weight:bold; color:#2E7D32;"><?php echo $estadisticas['PROCESO COMPLETO']; ?></td>
            <td style="font-weight:bold;">Pendiente Aprovisionamiento:</td>
            <td style="font-weight:bold; color:#FF9800;"><?php echo $estadisticas['PENDIENTE APROVISIONAMIENTO']; ?></td>
            <td style="font-weight:bold;">Solo Ventas:</td>
            <td style="font-weight:bold; color:#D32F2F;"><?php echo $estadisticas['SOLO VENTA']; ?></td>
            <td colspan="23"></td>
        </tr>
        <tr style="background-color:#F1F8E9;">
            <td style="font-weight:bold;">Solo Agendamiento:</td>
            <td style="font-weight:bold; color:#9C27B0;"><?php echo $estadisticas['SOLO AGENDAMIENTO']; ?></td>
            <td style="font-weight:bold;">Solo Aprovisionamiento:</td>
            <td style="font-weight:bold; color:#795548;"><?php echo $estadisticas['SOLO APROVISIONAMIENTO']; ?></td>
            <td style="font-weight:bold;">Venta + Aprovisionamiento:</td>
            <td style="font-weight:bold; color:#607D8B;"><?php echo $estadisticas['VENTA Y APROVISIONAMIENTO']; ?></td>
            <td style="font-weight:bold;">Agenda + Aprovisionamiento:</td>
            <td style="font-weight:bold; color:#3F51B5;"><?php echo $estadisticas['AGENDAMIENTO Y APROVISIONAMIENTO']; ?></td>
            <td colspan="23"></td>
        </tr>
        
        <!-- SEPARADOR -->
        <tr></tr>
        <tr></tr>
        
        <!-- ENCABEZADOS DE COLUMNAS -->
        <tr style="background-color:#1976D2; color:white; font-weight:bold;">
            <td>Estado del Proceso</td>
            <!-- INFORMACIÓN DE VENTA -->
            <td>Fecha Venta</td>
            <td>Cédula Cliente</td>
            <td>Nombre Cliente</td>
            <td>Teléfono Principal</td>
            <td>Teléfono Secundario</td>
            <td>Email</td>
            <td>Municipio</td>
            <td>Vereda/Dirección</td>
            <td>Coordenadas GPS</td>
            <td>Indicaciones</td>
            <td>Notas Venta</td>
            <td>Número Servicio</td>
            <td>Tecnología</td>
            <td>Plan</td>
            <!-- INFORMACIÓN DE AGENDAMIENTO -->
            <td>Fecha Visita</td>
            <td>Franja Visita</td>
            <td>Técnico Asignado</td>
            <td>Estado Visita</td>
            <td>Notas Agendamiento</td>
            <!-- INFORMACIÓN DE APROVISIONAMIENTO -->
            <td>Tipo Radio</td>
            <td>MAC/Serial Radio</td>
            <td>Tipo Router/ONU</td>
            <td>MAC/Serial Router</td>
            <td>IP Navegación</td>
            <td>IP Gestión</td>
            <td>Metros Cable</td>
            <td>Tipo Cable</td>
            <td>Notas Aprovisionamiento</td>
            <td>Estado Aprovisionamiento</td>
            <td>Vendedor</td>
        </tr>
        
        <?php
        if (!empty($datos_completos)) {
            foreach ($datos_completos as $row) {
                // Determinar color de fila según estado del proceso
                $color_fila = "";
                switch ($row['estado_proceso']) {
                    case 'PROCESO COMPLETO':
                        $color_fila = "background-color:#C8E6C9;"; // Verde claro
                        break;
                    case 'PENDIENTE APROVISIONAMIENTO':
                        $color_fila = "background-color:#FFECB3;"; // Amarillo claro
                        break;
                    case 'SOLO VENTA':
                        $color_fila = "background-color:#FFCDD2;"; // Rojo claro
                        break;
                    case 'SOLO AGENDAMIENTO':
                        $color_fila = "background-color:#E1BEE7;"; // Morado claro
                        break;
                    case 'SOLO APROVISIONAMIENTO':
                        $color_fila = "background-color:#D7CCC8;"; // Café claro
                        break;
                    case 'VENTA Y APROVISIONAMIENTO':
                        $color_fila = "background-color:#CFD8DC;"; // Gris azulado
                        break;
                    case 'AGENDAMIENTO Y APROVISIONAMIENTO':
                        $color_fila = "background-color:#C5CAE9;"; // Azul claro
                        break;
                    default:
                        $color_fila = "background-color:#F5F5F5;"; // Gris claro
                        break;
                }
                
                echo "<tr style='$color_fila'>";
                
                // ESTADO DEL PROCESO
                echo "<td style='font-weight:bold;'>" . limpiarParaExcel($row['estado_proceso']) . "</td>";
                
                // DATOS DE VENTA
                echo "<td>" . ($row['fecha_venta'] ? limpiarParaExcel(date('d/m/Y H:i', strtotime($row['fecha_venta']))) : '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['cedula']) . "</td>";
                echo "<td>" . limpiarParaExcel($row['nombre']) . "</td>";
                echo "<td>" . limpiarParaExcel($row['telefono1'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['telefono2'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['email'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['municipio'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['vereda'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['coordenadas'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['indicaciones'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['notas_venta'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['num_servicio'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['tecnologia'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['plan'] ?? '-') . "</td>";
                
                // DATOS DE AGENDAMIENTO
                echo "<td>" . ($row['fecha_visita'] ? limpiarParaExcel(date('d/m/Y', strtotime($row['fecha_visita']))) : '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['franja_visita'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['tecnico_asignado'] ?? '-') . "</td>";
                echo "<td style='font-weight:bold;'>" . limpiarParaExcel($row['estado_visita'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['notas_agendamiento'] ?? '-') . "</td>";
                
                // DATOS DE APROVISIONAMIENTO
                echo "<td>" . limpiarParaExcel($row['tipo_radio'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['mac_serial_radio'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['tipo_router_onu'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['mac_serial_router'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['ip_navegacion'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['ip_gestion'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['metros_cable'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['tipo_cable'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['notas_aprovisionamiento'] ?? '-') . "</td>";
                echo "<td style='font-weight:bold;'>" . limpiarParaExcel($row['estado_aprovisionamiento'] ?? '-') . "</td>";
                echo "<td>" . limpiarParaExcel($row['vendedor_nombre']) . "</td>";
                
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='31' style='text-align:center; background-color:#FFF3E0;'>No hay registros disponibles</td></tr>";
        }
        ?>
        
        <!-- PIE DEL REPORTE -->
        <tr></tr>
        <tr style="background-color:#F5F5F5;">
            <td colspan="20" style="text-align:left; font-style:italic; font-weight:bold;">
                Resumen del Reporte Completo:
            </td>
            <td colspan="11" style="text-align:right; font-style:italic;">
                Total de registros: <?php echo count($datos_completos); ?>
            </td>
        </tr>
        <tr style="background-color:#F5F5F5;">
            <td colspan="31" style="text-align:center; font-size:10px; color:#666;">
                Reporte generado automáticamente por el Sistema Unificado de Gestión de Ventas, Agendamiento y Aprovisionamiento
            </td>
        </tr>
    </table>
</body>
</html>

<?php
$conn->close();
?>