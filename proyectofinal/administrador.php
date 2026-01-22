<?php
// Configuración de headers y encoding
header('Content-Type: text/html; charset=UTF-8', true);
mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clase de Configuración de Base de Datos
require_once 'conex_bd.php';
require_once 'session_manager.php';

// Clase para manejar usuarios
class UsuarioManager {
    private $db;
    private $table = "administrador";
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    private function sanitizeInput($data) {
        if (is_array($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $result[$key] = $this->sanitizeInput($value);
            }
            return $result;
        }
        
        if ($data === null || $data === '') {
            return '';
        }
        
        $cleanData = trim((string)$data);
        $cleanData = stripslashes($cleanData);
        return $cleanData;
    }
    
    private function validarCedula($cedula) {
        $pattern = '/^[0-9]+$/';
        $cedula = (string)$cedula;
        return preg_match($pattern, $cedula) && strlen($cedula) >= 6 && strlen($cedula) <= 15;
    }
    
    private function validarNombre($nombre) {
        $pattern = '/^[\p{L}\s\'-\.]+$/u';
        $nombre = (string)$nombre;
        $length = mb_strlen($nombre, 'UTF-8');
        return preg_match($pattern, $nombre) && $length >= 2 && $length <= 100;
    }
    
    private function validarEmail($email) {
        $email = (string)$email;
        $length = mb_strlen($email, 'UTF-8');
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && $length <= 100;
    }
    
    private function validarModulo($modulo) {
        $modulos_validos = array('Administrador', 'Vendedor', 'Agendamiento', 'Aprovisionamiento');
        return in_array($modulo, $modulos_validos);
    }
    
    public function crear($datos) {
        try {
            $datos = $this->sanitizeInput($datos);
            
            $campos_requeridos = array('cedula', 'nombre', 'email', 'contrasena', 'modulo');
            foreach ($campos_requeridos as $campo) {
                if (empty($datos[$campo])) {
                    return array('success' => false, 'message' => 'Todos los campos son obligatorios');
                }
            }
            
            if (!$this->validarCedula($datos['cedula'])) {
                return array('success' => false, 'message' => 'La cedula debe contener solo numeros (6-15 digitos)');
            }
            
            if (!$this->validarNombre($datos['nombre'])) {
                return array('success' => false, 'message' => 'El nombre solo debe contener letras y espacios');
            }
            
            if (!$this->validarEmail($datos['email'])) {
                return array('success' => false, 'message' => 'El correo electronico no es valido');
            }
            
            $passwordLength = mb_strlen($datos['contrasena'], 'UTF-8');
            if ($passwordLength < 8) {
                return array('success' => false, 'message' => 'La contrasena debe tener al menos 8 caracteres');
            }
            
            if (!$this->validarModulo($datos['modulo'])) {
                return array('success' => false, 'message' => 'Modulo no valido');
            }
            
            // Verificar cedula duplicada
            $query = "SELECT cedula FROM " . $this->table . " WHERE cedula = :cedula LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cedula', $datos['cedula'], PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return array('success' => false, 'message' => 'Ya existe un usuario con esta cedula');
            }
            
            // Verificar email duplicado
            $query = "SELECT email FROM " . $this->table . " WHERE email = :email LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $datos['email'], PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return array('success' => false, 'message' => 'Ya existe un usuario con este correo electronico');
            }
            
            $contrasena_hash = password_hash($datos['contrasena'], PASSWORD_DEFAULT);
            
            $query = "INSERT INTO " . $this->table . " 
                     (cedula, nombre, email, contrasena, modulo) 
                     VALUES (:cedula, :nombre, :email, :contrasena, :modulo)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cedula', $datos['cedula'], PDO::PARAM_STR);
            $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $datos['email'], PDO::PARAM_STR);
            $stmt->bindParam(':contrasena', $contrasena_hash, PDO::PARAM_STR);
            $stmt->bindParam(':modulo', $datos['modulo'], PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                return array('success' => true, 'message' => 'Usuario creado exitosamente');
            } else {
                return array('success' => false, 'message' => 'Error al crear el usuario');
            }
            
        } catch(PDOException $e) {
            error_log("Error crear usuario: " . $e->getMessage());
            return array('success' => false, 'message' => 'Error en la base de datos');
        } catch(Exception $e) {
            error_log("Error general crear usuario: " . $e->getMessage());
            return array('success' => false, 'message' => 'Error interno del sistema');
        }
    }
    
    public function listar() {
        try {
            $query = "SELECT cedula, nombre, email, modulo, fecha_creacion 
                     FROM " . $this->table . " 
                     ORDER BY fecha_creacion DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch(PDOException $e) {
            error_log("Error listar usuarios: " . $e->getMessage());
            return array();
        }
    }
    
    public function obtenerPorCedula($cedula) {
        try {
            $cedula = $this->sanitizeInput($cedula);
            
            if (!$this->validarCedula($cedula)) {
                return null;
            }
            
            $query = "SELECT cedula, nombre, email, modulo, fecha_creacion 
                     FROM " . $this->table . " 
                     WHERE cedula = :cedula LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cedula', $cedula, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetch();
            
        } catch(PDOException $e) {
            error_log("Error obtener usuario: " . $e->getMessage());
            return null;
        }
    }
    
    public function actualizar($cedula, $datos) {
        try {
            $cedula = $this->sanitizeInput($cedula);
            $datos = $this->sanitizeInput($datos);
            
            if (!$this->validarCedula($cedula)) {
                return array('success' => false, 'message' => 'Cedula no valida');
            }
            
            $query = "SELECT cedula FROM " . $this->table . " WHERE cedula = :cedula LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cedula', $cedula, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return array('success' => false, 'message' => 'Usuario no encontrado');
            }
            
            $campos = array();
            $valores = array();
            
            if (!empty($datos['nombre'])) {
                if (!$this->validarNombre($datos['nombre'])) {
                    return array('success' => false, 'message' => 'El nombre solo debe contener letras y espacios');
                }
                $campos[] = "nombre = :nombre";
                $valores[':nombre'] = $datos['nombre'];
            }
            
            if (!empty($datos['email'])) {
                if (!$this->validarEmail($datos['email'])) {
                    return array('success' => false, 'message' => 'El correo electronico no es valido');
                }
                
                $query = "SELECT cedula FROM " . $this->table . " WHERE email = :email AND cedula != :cedula_check LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':email', $datos['email'], PDO::PARAM_STR);
                $stmt->bindParam(':cedula_check', $cedula, PDO::PARAM_STR);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    return array('success' => false, 'message' => 'Ya existe otro usuario con este correo electronico');
                }
                
                $campos[] = "email = :email";
                $valores[':email'] = $datos['email'];
            }
            
            if (!empty($datos['modulo'])) {
                if (!$this->validarModulo($datos['modulo'])) {
                    return array('success' => false, 'message' => 'Modulo no valido');
                }
                
                $campos[] = "modulo = :modulo";
                $valores[':modulo'] = $datos['modulo'];
            }
            
            if (!empty($datos['contrasena'])) {
                $passwordLength = mb_strlen($datos['contrasena'], 'UTF-8');
                if ($passwordLength < 8) {
                    return array('success' => false, 'message' => 'La contrasena debe tener al menos 8 caracteres');
                }
                $campos[] = "contrasena = :contrasena";
                $valores[':contrasena'] = password_hash($datos['contrasena'], PASSWORD_DEFAULT);
            }
            
            if (empty($campos)) {
                return array('success' => false, 'message' => 'No hay datos para actualizar');
            }
            
            $query = "UPDATE " . $this->table . " SET " . implode(", ", $campos) . " WHERE cedula = :cedula";
            $valores[':cedula'] = $cedula;
            
            $stmt = $this->db->prepare($query);
            
            if ($stmt->execute($valores)) {
                return array('success' => true, 'message' => 'Usuario actualizado exitosamente');
            } else {
                return array('success' => false, 'message' => 'Error al actualizar usuario');
            }
            
        } catch(PDOException $e) {
            error_log("Error actualizar usuario: " . $e->getMessage());
            return array('success' => false, 'message' => 'Error en la base de datos');
        }
    }
    
    public function eliminar($cedula) {
        try {
            $cedula = $this->sanitizeInput($cedula);
            
            if (!$this->validarCedula($cedula)) {
                return array('success' => false, 'message' => 'Cedula no valida');
            }
            
            $query = "DELETE FROM " . $this->table . " WHERE cedula = :cedula";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cedula', $cedula, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return array('success' => true, 'message' => 'Usuario eliminado exitosamente');
                } else {
                    return array('success' => false, 'message' => 'Usuario no encontrado');
                }
            } else {
                return array('success' => false, 'message' => 'Error al eliminar usuario');
            }
            
        } catch(PDOException $e) {
            error_log("Error eliminar usuario: " . $e->getMessage());
            return array('success' => false, 'message' => 'Error en la base de datos');
        }
    }
}

// Funcion para escapar salida
function h($text) {
    if ($text === null || $text === '') {
        return '';
    }
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Inicializar variables
$mensaje = '';
$tipo_mensaje = '';
$usuarios = array();
$mostrar_modal = false;
$usuario_editar = null;

try {
    $usuarioManager = new UsuarioManager();
    
    // Procesar creacion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
        $resultado = $usuarioManager->crear($_POST);
        $mensaje = $resultado['message'];
        $tipo_mensaje = $resultado['success'] ? 'success' : 'danger';
        
        if ($resultado['success']) {
            $_POST = array();
        }
    }
    
    // Procesar actualizacion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar' && isset($_POST['edit_cedula'])) {
        $cedula = $_POST['edit_cedula'];
        unset($_POST['accion'], $_POST['edit_cedula']);
        $resultado = $usuarioManager->actualizar($cedula, $_POST);
        $mensaje = $resultado['message'];
        $tipo_mensaje = $resultado['success'] ? 'success' : 'danger';
    }
    
    // Procesar eliminacion
    if (isset($_GET['eliminar']) && !empty($_GET['eliminar'])) {
        $resultado = $usuarioManager->eliminar($_GET['eliminar']);
        $mensaje = $resultado['message'];
        $tipo_mensaje = $resultado['success'] ? 'success' : 'danger';
        
        if ($resultado['success']) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=eliminado", true, 303);
            exit;
        }
    }
    
    // Cargar usuario para editar
    if (isset($_GET['editar']) && !empty($_GET['editar'])) {
        $usuario_editar = $usuarioManager->obtenerPorCedula($_GET['editar']);
        $mostrar_modal = ($usuario_editar !== null);
        if (!$mostrar_modal) {
            $mensaje = 'Usuario no encontrado';
            $tipo_mensaje = 'danger';
        }
    }
    
    // Mensaje de eliminacion exitosa
    if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado') {
        $mensaje = 'Usuario eliminado exitosamente';
        $tipo_mensaje = 'success';
    }
    
    // Listar usuarios
    $usuarios = $usuarioManager->listar();
    
} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    $mensaje = 'Ha ocurrido un error en el sistema';
    $tipo_mensaje = 'danger';
}

$hasMessage = !empty($mensaje);
$hasUsers = !empty($usuarios);
$userCount = count($usuarios);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Sistema de administracion de usuarios - Conecto">
    <meta name="author" content="Sistema Conecto">
    <title>Administrador de Usuarios - Sistema Conecto</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <link href="css/stylos_admon.css" rel="stylesheet">
    
    <style>
        .loading-overlay {
            display: none !important;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 9998;
            justify-content: center;
            align-items: center;
        }
        
        .loading-overlay.show {
            display: flex !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a href="http://localhost/proyectofinal/modulos.php" class="btn btn-outline-primary me-3">
                <i class="bi bi-arrow-left me-2"></i><span class="d-none d-sm-inline">Volver al Inicio</span>
            </a>
            <span class="navbar-brand mb-0 h1 ms-auto">Sistema de Administracion</span>
        </div>
    </nav>

    <div class="container-fluid main-wrapper">
        <div class="main-container">
            <header class="text-center mb-4">
                <div class="logo-container mb-3">
                    <img src="imagenes/logo1.JPG" alt="Logo Conecto" class="logo img-fluid" style="max-width: 120px;">
                </div>
                <h1 class="main-title">Sistema de Ventas e Instalaciones</h1>
                <h2 class="subtitle">Internet para Todos</h2>
                <h3 class="section-title">Administrador de Usuarios</h3>
            </header>

            <?php if ($hasMessage): ?>
            <div class="alert alert-<?php echo h($tipo_mensaje); ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $tipo_mensaje === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
                <?php echo h($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
            <?php endif; ?>

            <ul class="nav nav-pills nav-justified mb-4" id="adminTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="crear-tab" data-bs-toggle="pill" data-bs-target="#crear-panel" 
                            type="button" role="tab" aria-controls="crear-panel" aria-selected="true">
                        <i class="bi bi-person-plus me-2"></i>
                        <span class="d-none d-sm-inline">Crear Usuario</span>
                        <span class="d-sm-none">Crear</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="listar-tab" data-bs-toggle="pill" data-bs-target="#listar-panel" 
                            type="button" role="tab" aria-controls="listar-panel" aria-selected="false">
                        <i class="bi bi-people me-2"></i>
                        <span class="d-none d-sm-inline">Listar Usuarios</span>
                        <span class="d-sm-none">Listar</span>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="adminTabContent">
                <div class="tab-pane fade show active" id="crear-panel" role="tabpanel" aria-labelledby="crear-tab">
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            <div class="card shadow">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Crear Nuevo Usuario</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="formCrear" class="needs-validation" novalidate>
                                        <input type="hidden" name="accion" value="crear">
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="cedula" class="form-label">Numero de Cedula <span class="text-danger">*</span></label>
                                                <input type="text" id="cedula" name="cedula" class="form-control" 
                                                       pattern="[0-9]{6,15}" maxlength="15" required>
                                                <div class="invalid-feedback">
                                                    Solo numeros de 6 a 15 digitos
                                                </div>
                                                <small class="form-text text-muted">Solo numeros, entre 6 y 15 digitos</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="nombre" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                                                <input type="text" id="nombre" name="nombre" class="form-control" 
                                                       maxlength="100" required>
                                                <div class="invalid-feedback">
                                                    El nombre solo debe contener letras y espacios
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label">Correo Electronico <span class="text-danger">*</span></label>
                                                <input type="email" id="email" name="email" class="form-control" 
                                                       maxlength="100" required>
                                                <div class="invalid-feedback">
                                                    Ingrese un correo electronico valido
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="contrasena" class="form-label">Contrasena <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="password" id="contrasena" name="contrasena" class="form-control" 
                                                           minlength="8" maxlength="255" required>
                                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                                <small class="form-text text-muted">Minimo 8 caracteres</small>
                                                <div class="invalid-feedback d-block">
                                                    La contrasena debe tener al menos 8 caracteres
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="modulo" class="form-label">Modulo <span class="text-danger">*</span></label>
                                            <select id="modulo" name="modulo" class="form-select" required>
                                                <option value="">-- Seleccione un modulo --</option>
                                                <option value="Administrador">Administrador</option>
                                                <option value="Vendedor">Vendedor</option>
                                                <option value="Agendamiento">Agendamiento</option>
                                                <option value="Aprovisionamiento">Aprovisionamiento</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Debe seleccionar un modulo
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-success btn-lg w-100">
                                            <i class="bi bi-check-circle me-2"></i>Crear Usuario
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="listar-panel" role="tabpanel" aria-labelledby="listar-tab">
                    <div class="card shadow">
                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center flex-wrap">
                            <h5 class="mb-0">
                                <i class="bi bi-people me-2"></i>Lista de Usuarios 
                                <span class="badge bg-light text-dark"><?php echo $userCount; ?></span>
                            </h5>
                            <button class="btn btn-light btn-sm" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                <span class="d-none d-sm-inline">Actualizar</span>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th scope="col">Cedula</th>
                                            <th scope="col">Nombre</th>
                                            <th scope="col" class="d-none d-md-table-cell">Correo Electronico</th>
                                            <th scope="col">Modulo</th>
                                            <th scope="col" class="d-none d-lg-table-cell">Fecha Creacion</th>
                                            <th scope="col" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$hasUsers): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <div>
                                                    <i class="bi bi-inbox display-4 d-block mb-3"></i>
                                                    <h5>No hay usuarios registrados</h5>
                                                    <p class="text-muted">Use la pestana Crear Usuario para agregar el primer usuario</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td class="fw-bold text-primary"><?php echo h($usuario['cedula']); ?></td>
                                            <td><?php echo h($usuario['nombre']); ?></td>
                                            <td class="d-none d-md-table-cell"><small><?php echo h($usuario['email']); ?></small></td>
                                            <td><span class="badge bg-primary"><?php echo h($usuario['modulo']); ?></span></td>
                                            <td class="d-none d-lg-table-cell"><small class="text-muted">
                                                <?php echo $usuario['fecha_creacion'] ? date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])) : 'N/A'; ?>
                                            </small></td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="?editar=<?php echo urlencode($usuario['cedula']); ?>" 
                                                       class="btn btn-warning" title="Editar">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-danger" 
                                                            onclick="confirmarEliminacion('<?php echo h($usuario['cedula']); ?>', '<?php echo h($usuario['nombre']); ?>')"
                                                            title="Eliminar">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="modalEditarLabel">
                        <i class="bi bi-pencil-square me-2"></i>Editar Usuario
                    </h5>
                    <a href="?" class="btn-close btn-close-white"></a>
                </div>
                <div class="modal-body">
                    <?php if ($usuario_editar): ?>
                    <form method="POST" id="formEditar" class="needs-validation" novalidate>
                        <input type="hidden" name="accion" value="actualizar">
                        <input type="hidden" name="edit_cedula" value="<?php echo h($usuario_editar['cedula']); ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editCedula" class="form-label">Cedula</label>
                                <input type="text" id="editCedula" class="form-control" 
                                       value="<?php echo h($usuario_editar['cedula']); ?>" disabled>
                                <small class="form-text text-muted">La cedula no se puede modificar</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editNombre" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" id="editNombre" name="nombre" class="form-control" 
                                       value="<?php echo h($usuario_editar['nombre']); ?>"
                                       maxlength="100" required>
                                <div class="invalid-feedback">
                                    El nombre solo debe contener letras y espacios
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editEmail" class="form-label">Correo Electronico <span class="text-danger">*</span></label>
                                <input type="email" id="editEmail" name="email" class="form-control" 
                                       value="<?php echo h($usuario_editar['email']); ?>"
                                       maxlength="100" required>
                                <div class="invalid-feedback">
                                    Ingrese un correo electronico valido
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editModulo" class="form-label">Modulo <span class="text-danger">*</span></label>
                                <select id="editModulo" name="modulo" class="form-select" required>
                                    <option value="Administrador" <?php echo $usuario_editar['modulo'] == 'Administrador' ? 'selected' : ''; ?>>Administrador</option>
                                    <option value="Vendedor" <?php echo $usuario_editar['modulo'] == 'Vendedor' ? 'selected' : ''; ?>>Vendedor</option>
                                    <option value="Agendamiento" <?php echo $usuario_editar['modulo'] == 'Agendamiento' ? 'selected' : ''; ?>>Agendamiento</option>
                                    <option value="Aprovisionamiento" <?php echo $usuario_editar['modulo'] == 'Aprovisionamiento' ? 'selected' : ''; ?>>Aprovisionamiento</option>
                                </select>
                                <div class="invalid-feedback">
                                    Debe seleccionar un modulo
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editContrasena" class="form-label">Nueva Contrasena (opcional)</label>
                            <div class="input-group">
                                <input type="password" id="editContrasena" name="contrasena" class="form-control" 
                                       minlength="8" maxlength="255">
                                <button class="btn btn-outline-secondary" type="button" id="toggleEditPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Dejar vacio para mantener la contrasena actual. Minimo 8 caracteres si se cambia.</small>
                            <div class="invalid-feedback d-block">
                                La contrasena debe tener al menos 8 caracteres
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <a href="?" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-1"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-warning text-white">
                                <i class="bi bi-check-circle me-1"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        No se pudo cargar la informacion del usuario.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($mostrar_modal): ?>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <div class="modal fade" id="modalEliminar" tabindex="-1" aria-labelledby="modalEliminarLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalEliminarLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirmar Eliminacion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-trash3 text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <div class="text-center mb-3">
                        <h6>Esta seguro de que desea eliminar al usuario?</h6>
                    </div>
                    <div class="alert alert-warning border-0">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-info-circle me-2"></i>
                            <div>
                                <strong>Usuario:</strong> <span id="nombreUsuarioEliminar" class="fw-bold"></span><br>
                                <strong>Cedula:</strong> <span id="cedulaUsuarioEliminar" class="fw-bold"></span>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-danger border-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Advertencia:</strong> Esta accion no se puede deshacer.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                    <a href="#" id="confirmarEliminarBtn" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Eliminar Usuario
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" 
            crossorigin="anonymous"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var modalEditar = document.getElementById('modalEditar');
        var formEditar = document.getElementById('formEditar');
        
        <?php if ($mostrar_modal): ?>
        if (modalEditar && formEditar) {
            try {
                var bsModal = new bootstrap.Modal(modalEditar);
                bsModal.show();
            } catch(e) {
                console.warn('Error al abrir modal: ' + e.message);
            }
        }
        <?php endif; ?>
        
        var togglePassword = document.getElementById('togglePassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                togglePasswordVisibility('contrasena', this);
            });
        }
        
        var toggleEditPassword = document.getElementById('toggleEditPassword');
        if (toggleEditPassword) {
            toggleEditPassword.addEventListener('click', function() {
                togglePasswordVisibility('editContrasena', this);
            });
        }
        
        var formCrear = document.getElementById('formCrear');
        if (formCrear) {
            formCrear.addEventListener('submit', function(e) {
                e.preventDefault();
                if (this.checkValidity() === false) {
                    e.stopPropagation();
                }
                this.classList.add('was-validated');
                if (this.checkValidity() === true) {
                    this.submit();
                }
            });
        }
        
        var formEditar = document.getElementById('formEditar');
        if (formEditar) {
            formEditar.addEventListener('submit', function(e) {
                e.preventDefault();
                if (this.checkValidity() === false) {
                    e.stopPropagation();
                }
                this.classList.add('was-validated');
                if (this.checkValidity() === true) {
                    this.submit();
                }
            });
        }
    });
    
    function togglePasswordVisibility(inputId, button) {
        var input = document.getElementById(inputId);
        if (!input) return;
        
        var icon = button.querySelector('i');
        if (!icon) return;
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
    
    function confirmarEliminacion(cedula, nombre) {
        var modal = document.getElementById('modalEliminar');
        if (!modal) return;
        
        var nombreSpan = document.getElementById('nombreUsuarioEliminar');
        var cedulaSpan = document.getElementById('cedulaUsuarioEliminar');
        var confirmarBtn = document.getElementById('confirmarEliminarBtn');
        
        if (nombreSpan) nombreSpan.textContent = nombre;
        if (cedulaSpan) cedulaSpan.textContent = cedula;
        if (confirmarBtn) {
            confirmarBtn.href = '?eliminar=' + encodeURIComponent(cedula);
        }
        
        try {
            var bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        } catch(e) {
            console.warn('Error al abrir modal de eliminacion: ' + e.message);
        }
    }
    </script>
</body>
</html>