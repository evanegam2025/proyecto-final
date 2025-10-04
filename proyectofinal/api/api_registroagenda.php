<?php

header('Content-Type: text/html; charset=utf-8');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');



// Iniciar la sesión y configurar charset
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "proyecto-final";

try {
    // Crear conexión PDO con configuración completa UTF-8mb4
    $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Usar prepared statements reales
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Asegurar UTF-8mb4 en la sesión
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET COLLATION_CONNECTION = utf8mb4_unicode_ci");
    
} catch(PDOException $e) {
    http_response_code(500);
    
    // Log del error real para desarrolladores
    error_log("Error de conexión DB: " . $e->getMessage());
    
    // Mensaje genérico para usuarios
    echo json_encode([
        'success' => false, 
        'message' => "Error de conexión a la base de datos. Por favor contacte al administrador."
    ], JSON_UNESCAPED_UNICODE);
    die();
}

session_start();

// ======================================================================
// FUNCIONES DE SEGURIDAD Y VALIDACIÓN MEJORADAS
// ======================================================================

function validarSesion() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
        return ['success' => false, 'message' => 'Acceso denegado. No ha iniciado sesión.'];
    }
    return ['success' => true];
}

function sanitizarDatos($data) {
    if (is_array($data)) {
        return array_map(function($value) {
            return sanitizarDatos($value); // Recursivo para arrays anidados
        }, $data);
    }
    
    if ($data === null) {
        return null;
    }
    
    // Convertir a string si no lo es
    $data = (string)$data;
    
    // Limpiar espacios
    $data = trim($data);
    
    // Validar y limpiar UTF-8mb4 evitando caracteres dañinos
$data = filter_var($data, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$data = mb_convert_encoding($data, 'UTF-8', 'auto');
    
    // Remover caracteres de control excepto saltos de línea y tabulaciones
    $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
    
    // Escapar caracteres HTML para prevenir XSS
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $data;
}

function validarEntrada($data, $campos_requeridos = []) {
    foreach ($campos_requeridos as $campo) {
        if (!isset($data[$campo]) || empty(trim($data[$campo]))) {
            return ['success' => false, 'message' => "El campo '$campo' es obligatorio."];
        }
        
        // Validación adicional para campos específicos
        switch ($campo) {
            case 'cedula':
            case 'cedula_edit':
            case 'cedula_cliente':
                if (!preg_match('/^\d{6,15}$/', $data[$campo])) {
                    return ['success' => false, 'message' => 'La cédula debe tener entre 6 y 15 dígitos.'];
                }
                break;
                
            case 'email':
            case 'email_edit':
                if (!filter_var($data[$campo], FILTER_VALIDATE_EMAIL)) {
                    return ['success' => false, 'message' => 'El formato del email no es válido.'];
                }
                break;
                
            case 'telefono1':
            case 'telefono1_edit':
            case 'telefono2':
            case 'telefono2_edit':
                if (!empty($data[$campo]) && !preg_match('/^\d{7,15}$/', $data[$campo])) {
                    return ['success' => false, 'message' => 'El teléfono debe tener entre 7 y 15 dígitos.'];
                }
                break;
                
            case 'coordenadas':
            case 'coordenadas_edit':
                // Validar formato básico de coordenadas (lat,long o lat, long)
                if (!preg_match('/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/', trim($data[$campo]))) {
                    return ['success' => false, 'message' => 'Las coordenadas deben tener el formato: latitud, longitud'];
                }
                break;
        }
    }
    return ['success' => true];
}

function limpiarTexto($texto) {
    if (empty($texto)) return '';
    
    // Normalizar espacios múltiples
    $texto = preg_replace('/\s+/', ' ', $texto);
    
    // Limpiar caracteres especiales peligrosos pero mantener acentos
    $texto = preg_replace('/[<>"\'\\\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $texto);
    
    return trim($texto);
}

function validarFecha($fecha) {
    $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fecha_obj) {
        return ['success' => false, 'message' => 'Formato de fecha inválido.'];
    }
    
    $hoy = new DateTime();
    if ($fecha_obj < $hoy) {
        return ['success' => false, 'message' => 'La fecha no puede ser anterior al día de hoy.'];
    }
    
    return ['success' => true];
}

// Verificar sesión activa
$validacion = validarSesion();
if (!$validacion['success']) {
    header("Location: index.php");
    exit();
}

// Obtener datos de sesión
$user_id = $_SESSION['user_id'] ?? '';
$nombre_usuario = $_SESSION['user_name'] ?? $_SESSION['nombre'] ?? 'Usuario';

// Obtener módulo dinámicamente desde la base de datos
$modulo = "Sistema de Agendamiento"; // Valor por defecto
try {
    if (!empty($user_id)) {
        $stmt = $pdo->prepare("SELECT modulo FROM administrador WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario_data = $stmt->fetch();
        
        if ($usuario_data && !empty($usuario_data['modulo'])) {
            $modulo = "Sistema de " . $usuario_data['modulo'];
        }
    }
} catch (PDOException $e) {
    // Si hay error, mantener el valor por defecto
    $modulo = "Sistema de Agendamiento";
}

$venta_encontrada = null;
$agendamiento_encontrado = null;
$aprovisionamiento_encontrado = null;
$mensaje = null;
$tipo_mensaje = '';

// --- INICIO: LÓGICA DE PROCESAMIENTO DE FORMULARIOS ---

// --- Procesar ACTUALIZACIÓN COMPLETA de la venta (Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_venta_completa'])) {
    $id_venta = $_POST['id_venta'];
    
    // Sanitizar todos los datos
    $data = [
        'nombre' => sanitizarDatos($_POST['nombre_edit']),
        'cedula' => sanitizarDatos($_POST['cedula_edit']),
        'telefono1' => sanitizarDatos($_POST['telefono1_edit']),
        'telefono2' => sanitizarDatos($_POST['telefono2_edit'] ?? ''),
        'email' => sanitizarDatos($_POST['email_edit']),
        'municipio' => sanitizarDatos($_POST['municipio_edit']),
        'vereda' => sanitizarDatos($_POST['vereda_edit']),
        'coordenadas' => sanitizarDatos($_POST['coordenadas_edit']),
        'indicaciones' => sanitizarDatos($_POST['indicaciones_edit']),
        'notas' => sanitizarDatos($_POST['notas_edit'] ?? ''),
        'num_servicio' => sanitizarDatos($_POST['num_servicio_edit']),
        'tecnologia' => sanitizarDatos($_POST['tecnologia_edit']),
        'plan' => sanitizarDatos($_POST['plan_edit'])
    ];
    
    // Validar campos requeridos
    $campos_requeridos = ['nombre', 'cedula', 'telefono1', 'email', 'municipio', 'vereda', 'coordenadas', 'indicaciones', 'num_servicio', 'tecnologia', 'plan'];
    $validacion = validarEntrada($data, $campos_requeridos);
    
    if (!$validacion['success']) {
        $mensaje = $validacion['message'];
        $tipo_mensaje = "danger";
    } else {
        // Validar formato de email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $mensaje = "El formato del email no es válido.";
            $tipo_mensaje = "danger";
        } else {
            try {
                $sql = "UPDATE ventas SET 
                        nombre = ?, cedula = ?, telefono1 = ?, telefono2 = ?, 
                        email = ?, municipio = ?, vereda = ?, coordenadas = ?, 
                        indicaciones = ?, notas = ?, num_servicio = ?, 
                        tecnologia = ?, plan = ? 
                        WHERE id = ?";
                        
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([
                    $data['nombre'], $data['cedula'], $data['telefono1'], $data['telefono2'],
                    $data['email'], $data['municipio'], $data['vereda'], $data['coordenadas'],
                    $data['indicaciones'], $data['notas'], $data['num_servicio'],
                    $data['tecnologia'], $data['plan'], $id_venta
                ])) {
                    $mensaje = "¡Venta actualizada exitosamente!";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar la venta.";
                    $tipo_mensaje = "danger";
                }
                
                // Recargar la consulta con la cédula actualizada
                $_POST['consultar_cedula'] = true;
                $_POST['cedula_consulta'] = $data['cedula'];

            } catch (PDOException $e) {
                $mensaje = "Error de base de datos al actualizar: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    }
}

// --- Procesar ACTUALIZACIÓN del agendamiento (Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_agendamiento'])) {
    $id_agendamiento = $_POST['id_agendamiento'];
    $fecha_visita = $_POST['fecha_visita_edit'];
    $franja_visita = $_POST['franja_visita_edit'];
    $tecnico_asignado = $_POST['tecnico_asignado_edit'];
    $notas = $_POST['notas_edit'];
    $estado_visita = $_POST['estado_visita_edit'];
    
    try {
        $sql = "UPDATE agendamiento SET fecha_visita = ?, franja_visita = ?, tecnico_asignado = ?, notas = ?, estado_visita = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$fecha_visita, $franja_visita, $tecnico_asignado, $notas, $estado_visita, $id_agendamiento])) {
            $mensaje = "¡Agendamiento actualizado exitosamente!";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al actualizar el agendamiento.";
            $tipo_mensaje = "danger";
        }
        // Recargar la consulta para mostrar los datos actualizados
        $_POST['consultar_cedula'] = true;
        $_POST['cedula_consulta'] = $_POST['cedula_original'];

    } catch (PDOException $e) {
        $mensaje = "Error de base de datos al actualizar: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// --- Procesar ELIMINACIÓN de un agendamiento (Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar_agendamiento'])) {
    $id_agendamiento_a_borrar = $_POST['id_agendamiento'];

    try {
        $sql = "DELETE FROM agendamiento WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$id_agendamiento_a_borrar])) {
            $mensaje = "Agendamiento cancelado/eliminado exitosamente.";
            $tipo_mensaje = "info";
        } else {
            $mensaje = "Error al eliminar el agendamiento.";
            $tipo_mensaje = "danger";
        }
    } catch (PDOException $e) {
        $mensaje = "Error de base de datos al eliminar: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Procesar guardado de agendamiento (Create)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_agendamiento'])) {
    // Sanitizar y validar datos
    $data = sanitizarDatos($_POST);
    $validacion = validarEntrada($data, ['cedula_cliente', 'fecha_visita', 'franja_visita', 'tecnico_asignado', 'estado_visita']);
    
    if (!$validacion['success']) {
        $mensaje = $validacion['message'];
        $tipo_mensaje = "danger";
    } else {
        $cedula_cliente = $data['cedula_cliente'];
        $fecha_visita = $data['fecha_visita'];
        $franja_visita = $data['franja_visita'];
        $tecnico_asignado = $data['tecnico_asignado'];
        $notas = $data['notas'] ?? '';
        $estado_visita = $data['estado_visita'];

        try {
            $sql = "INSERT INTO agendamiento (cedula_cliente, fecha_visita, franja_visita, tecnico_asignado, notas, estado_visita) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$cedula_cliente, $fecha_visita, $franja_visita, $tecnico_asignado, $notas, $estado_visita])) {
                $mensaje = "¡Agendamiento guardado exitosamente!";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al guardar el agendamiento.";
                $tipo_mensaje = "danger";
            }
        } catch (PDOException $e) {
            $mensaje = "Error de base de datos al guardar: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

// --- Procesar formulario de consulta (Read) - CONSULTA DUAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consultar_cedula'])) {
    $cedula_consulta = trim($_POST['cedula_consulta']);
    
    if (!empty($cedula_consulta)) {
        // Validar que la cédula sea numérica
        if (!is_numeric($cedula_consulta)) {
            $mensaje = "La cédula debe ser numérica.";
            $tipo_mensaje = "warning";
        } else {
            try {
                // Consultar en tabla VENTAS
                $stmt = $pdo->prepare("SELECT * FROM ventas WHERE cedula = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$cedula_consulta]);
                $venta_encontrada = $stmt->fetch();
                
                // Consultar en tabla AGENDAMIENTO
                $stmt_agenda = $pdo->prepare("SELECT * FROM agendamiento WHERE cedula_cliente = ? ORDER BY id DESC LIMIT 1");
                $stmt_agenda->execute([$cedula_consulta]);
                $agendamiento_encontrado = $stmt_agenda->fetch();

                // Consultar APROVISIONAMIENTO
                $stmt_aprov = $pdo->prepare("SELECT * FROM aprovisionamiento WHERE cedula_cliente = ? ORDER BY id DESC LIMIT 1");
                $stmt_aprov->execute([$cedula_consulta]);
                $aprovisionamiento_encontrado = $stmt_aprov->fetch();

                // Validar resultados
                if (!$venta_encontrada && !$agendamiento_encontrado) {
                    $mensaje = "No se encontró ningún registro asociado a la cédula " . htmlspecialchars($cedula_consulta) . " en ventas ni en agendamiento.";
                    $tipo_mensaje = "warning";
                }
                
            } catch (PDOException $e) {
                $mensaje = "Error al consultar la base de datos: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    } else {
        $mensaje = "Debe ingresar un número de cédula para consultar.";
        $tipo_mensaje = "warning";
    }
}

// Cerrar sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>