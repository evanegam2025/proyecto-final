<?php
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');

// Configuración de la base de datos
class Database {
    private $host = "localhost";
    private $database = "proyecto-final";
    private $username = "root";
    private $password = "";
    private $connection;
    
    public function connect() {
        $this->connection = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->database . ";charset=utf8mb4";
            $this->connection = new PDO($dsn, $this->username, $this->password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch(PDOException $e) {
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            throw new Exception('Error de conexión a la base de datos');
        }
        
        return $this->connection;
    }
}

// Clase para manejar usuarios
class UsuarioManager {
    private $db;
    private $table = "administrador";
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    // Sanitizar datos de entrada
    private function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        return trim(htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    
    // Validar cédula (solo números)
    private function validarCedula($cedula) {
        return preg_match('/^[0-9]+$/', $cedula) && strlen($cedula) >= 6 && strlen($cedula) <= 15;
    }
    
    // Validar nombre (letras, espacios y acentos)
    private function validarNombre($nombre) {
        return preg_match('/^[\p{L}\s\'.-]+$/u', $nombre) && mb_strlen($nombre, 'UTF-8') >= 2;
    }
    
    // Validar email
    private function validarEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email, 'UTF-8') <= 100;
    }
    
    public function crear($datos) {
        try {
            $datos = $this->sanitizeInput($datos);
            
            // Validar datos requeridos
            if (empty($datos['cedula']) || empty($datos['nombre']) || 
                empty($datos['email']) || empty($datos['contrasena']) || 
                empty($datos['modulo'])) {
                return ['success' => false, 'message' => 'Todos los campos son obligatorios'];
            }
            
            // Validaciones específicas
            if (!$this->validarCedula($datos['cedula'])) {
                return ['success' => false, 'message' => 'La cédula debe contener solo números (6-15 dígitos)'];
            }
            
            if (!$this->validarNombre($datos['nombre'])) {
                return ['success' => false, 'message' => 'El nombre solo debe contener letras y espacios'];
            }
            
            if (!$this->validarEmail($datos['email'])) {
                return ['success' => false, 'message' => 'El correo electrónico no es válido'];
            }
            
            // Validar longitud de contraseña
            if (mb_strlen($datos['contrasena'], 'UTF-8') < 8) {
                return ['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres'];
            }
            
            // Validar módulo
            $modulos_validos = ['Administrador', 'Vendedor', 'Agendamiento', 'Aprovisionamiento'];
            if (!in_array($datos['modulo'], $modulos_validos)) {
                return ['success' => false, 'message' => 'Módulo no válido'];
            }
            
            // Verificar si la cédula ya existe
            $query = "SELECT cedula FROM " . $this->table . " WHERE cedula = :cedula";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cedula', $datos['cedula'], PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Ya existe un usuario con esta cédula'];
            }
            
            // Verificar si el email ya existe
            $query = "SELECT email FROM " . $this->table . " WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $datos['email'], PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Ya existe un usuario con este correo electrónico'];
            }
            
            // Encriptar contraseña
            $contrasena_hash = password_hash($datos['contrasena'], PASSWORD_DEFAULT);
            
            // Insertar usuario
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
                return ['success' => true, 'message' => 'Usuario creado exitosamente'];
            } else {
                return ['success' => false, 'message' => 'Error al crear el usuario'];
            }
            
        } catch(PDOException $e) {
            error_log("Error en crear usuario: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error en la base de datos'];
        } catch(Exception $e) {
            error_log("Error general en crear usuario: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del sistema'];
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
            error_log("Error en listar usuarios: " . $e->getMessage());
            return [];
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
                     WHERE cedula = :cedula";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cedula', $cedula, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetch();
            
        } catch(PDOException $e) {
            error_log("Error en obtener usuario: " . $e->getMessage());
            return null;
        }
    }
    
    public function actualizar($cedula, $datos) {
        try {
            $cedula = $this->sanitizeInput($cedula);
            $datos = $this->sanitizeInput($datos);
            
            // Verificar que el usuario existe
            $query = "SELECT cedula FROM " . $this->table . " WHERE cedula = :cedula";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cedula', $cedula, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }
            
            // Construir query dinámicamente
            $campos = [];
            $valores = [];
            
            if (!empty($datos['nombre'])) {
                if (!$this->validarNombre($datos['nombre'])) {
                    return ['success' => false, 'message' => 'El nombre solo debe contener letras y espacios'];
                }
                $campos[] = "nombre = :nombre";
                $valores[':nombre'] = $datos['nombre'];
            }
            
            if (!empty($datos['email'])) {
                if (!$this->validarEmail($datos['email'])) {
                    return ['success' => false, 'message' => 'El correo electrónico no es válido'];
                }
                
                // Verificar que el nuevo email no existe
                $query = "SELECT cedula FROM " . $this->table . " WHERE email = :email AND cedula != :cedula_check";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':email', $datos['email'], PDO::PARAM_STR);
                $stmt->bindParam(':cedula_check', $cedula, PDO::PARAM_STR);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    return ['success' => false, 'message' => 'Ya existe otro usuario con este correo electrónico'];
                }
                
                $campos[] = "email = :email";
                $valores[':email'] = $datos['email'];
            }
            
            if (!empty($datos['modulo'])) {
                $modulos_validos = ['Vendedor', 'Administrador', 'Agendamiento', 'Aprovisionamiento'];
                if (!in_array($datos['modulo'], $modulos_validos)) {
                    return ['success' => false, 'message' => 'Módulo no válido'];
                }
                
                $campos[] = "modulo = :modulo";
                $valores[':modulo'] = $datos['modulo'];
            }
            
            if (!empty($datos['contrasena'])) {
                if (mb_strlen($datos['contrasena'], 'UTF-8') < 8) {
                    return ['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres'];
                }
                $campos[] = "contrasena = :contrasena";
                $valores[':contrasena'] = password_hash($datos['contrasena'], PASSWORD_DEFAULT);
            }
            
            if (empty($campos)) {
                return ['success' => false, 'message' => 'No hay datos para actualizar'];
            }
            
            $query = "UPDATE " . $this->table . " SET " . implode(", ", $campos) . " WHERE cedula = :cedula";
            $valores[':cedula'] = $cedula;
            
            $stmt = $this->db->prepare($query);
            
            if ($stmt->execute($valores)) {
                return ['success' => true, 'message' => 'Usuario actualizado exitosamente'];
            } else {
                return ['success' => false, 'message' => 'Error al actualizar usuario'];
            }
            
        } catch(PDOException $e) {
            error_log("Error en actualizar usuario: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error en la base de datos'];
        }
    }
    
    public function eliminar($cedula) {
        try {
            $cedula = $this->sanitizeInput($cedula);
            
            if (!$this->validarCedula($cedula)) {
                return ['success' => false, 'message' => 'Cédula no válida'];
            }
            
            $query = "DELETE FROM " . $this->table . " WHERE cedula = :cedula";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cedula', $cedula, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return ['success' => true, 'message' => 'Usuario eliminado exitosamente'];
                } else {
                    return ['success' => false, 'message' => 'Usuario no encontrado'];
                }
            } else {
                return ['success' => false, 'message' => 'Error al eliminar usuario'];
            }
            
        } catch(PDOException $e) {
            error_log("Error en eliminar usuario: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error en la base de datos'];
        }
    }
}

// Función para escapar texto de salida
function h($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Inicializar variables
$mensaje = '';
$tipo_mensaje = '';
$usuarios = [];
$mostrar_modal = false;
$usuario_editar = null;

try {
    $usuarioManager = new UsuarioManager();

    // Procesar formulario de creación
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
        $resultado = $usuarioManager->crear($_POST);
        $mensaje = $resultado['message'];
        $tipo_mensaje = $resultado['success'] ? 'success' : 'danger';
    }
    
    // Procesar actualización
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar' && isset($_POST['edit_cedula'])) {
        $cedula = $_POST['edit_cedula'];
        unset($_POST['accion'], $_POST['edit_cedula']);
        $resultado = $usuarioManager->actualizar($cedula, $_POST);
        $mensaje = $resultado['message'];
        $tipo_mensaje = $resultado['success'] ? 'success' : 'danger';
    }
    
    // Procesar eliminación
    if (isset($_GET['eliminar']) && !empty($_GET['eliminar'])) {
        $resultado = $usuarioManager->eliminar($_GET['eliminar']);
        $mensaje = $resultado['message'];
        $tipo_mensaje = $resultado['success'] ? 'success' : 'danger';
        
        // Redireccionar para evitar reenvío del formulario
        if ($resultado['success']) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=eliminado");
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
    
    // Mensaje de eliminación exitosa
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Sistema de administración de usuarios - Conecto">
    <meta name="author" content="Sistema Conecto">
    <title>Administrador de Usuarios - Sistema Conecto</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/stylos_admon.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a href="http://localhost/proyectofinal/modulos.php" class="btn btn-outline-primary me-3">
                <i class="bi bi-arrow-left me-2"></i>Volver al Inicio
            </a>
            <span class="navbar-brand mb-0 h1 ms-auto">Sistema de Administración</span>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container-fluid main-wrapper">
        <div class="main-container">
            <!-- Header -->
            <header class="text-center mb-4">
                <div class="logo-container mb-3">
                    <img src="imagenes/logo1.JPG" alt="Logo Conecto" class="logo img-fluid">
                </div>
                <h1 class="main-title">Sistema de Ventas e Instalaciones</h1>
                <h2 class="subtitle">Internet para Todos</h2>
                <h3 class="section-title">Administrador de Usuarios</h3>
            </header>

            <!-- Loading Overlay -->
            <div id="loadingOverlay" class="loading-overlay">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= h($tipo_mensaje) ?> alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="bi bi-<?= $tipo_mensaje === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
                <?= h($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
            <?php endif; ?>

            <!-- Navigation Tabs -->
            <ul class="nav nav-pills nav-justified mb-4 custom-tabs" id="adminTabs" role="tablist">
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

            <!-- Tab Content -->
            <div class="tab-content" id="adminTabContent">
                <!-- Create User Panel -->
                <div class="tab-pane fade show active" id="crear-panel" role="tabpanel" aria-labelledby="crear-tab">
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            <div class="card shadow-soft">
                                <div class="card-header bg-gradient-primary text-white">
                                    <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Crear Nuevo Usuario</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="formCrear" class="needs-validation" novalidate>
                                        <input type="hidden" name="accion" value="crear">
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="cedula" class="form-label required">Número de Cédula</label>
                                                <input type="text" id="cedula" name="cedula" class="form-control" 
                                                       pattern="[0-9]{6,15}" 
                                                       title="Solo números de 6 a 15 dígitos" 
                                                       maxlength="15"
                                                       required>
                                                <div class="invalid-feedback">
                                                    La cédula debe tener entre 6 y 15 dígitos
                                                </div>
                                                <div class="form-text">Solo números, entre 6 y 15 dígitos</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="nombre" class="form-label required">Nombre Completo</label>
                                                <input type="text" id="nombre" name="nombre" class="form-control" 
                                                       title="Solo letras y espacios" 
                                                       maxlength="100"
                                                       required>
                                                <div class="invalid-feedback">
                                                    El nombre solo debe contener letras y espacios
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label required">Correo Electrónico</label>
                                                <input type="email" id="email" name="email" class="form-control" 
                                                       maxlength="100"
                                                       required>
                                                <div class="invalid-feedback">
                                                    Ingrese un correo electrónico válido
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="contrasena" class="form-label required">Contraseña</label>
                                                <div class="input-group">
                                                    <input type="password" id="contrasena" name="contrasena" class="form-control" 
                                                           minlength="8" maxlength="255" required>
                                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" 
                                                            aria-label="Mostrar/ocultar contraseña">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                                <div class="form-text">Mínimo 8 caracteres</div>
                                                <div class="invalid-feedback">
                                                    La contraseña debe tener al menos 8 caracteres
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="modulo" class="form-label required">Módulo</label>
                                            <select id="modulo" name="modulo" class="form-select" required>
                                                <option value="">-- Seleccione un módulo --</option>
                                                <option value="Administrador">Administrador</option>
                                                <option value="Vendedor">Vendedor</option>
                                                <option value="Agendamiento">Agendamiento</option>
                                                <option value="Aprovisionamiento">Aprovisionamiento</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Debe seleccionar un módulo
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-success btn-lg w-100" id="btnCrear">
                                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                            <i class="bi bi-check-circle me-2"></i>Crear Usuario
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- List Users Panel -->
                <div class="tab-pane fade" id="listar-panel" role="tabpanel" aria-labelledby="listar-tab">
                    <div class="card shadow-soft">
                        <div class="card-header bg-gradient-info text-white d-flex justify-content-between align-items-center flex-wrap">
                            <h5 class="mb-0">
                                <i class="bi bi-people me-2"></i>Lista de Usuarios 
                                <span class="badge bg-light text-dark"><?= count($usuarios) ?></span>
                            </h5>
                            <button class="btn btn-light btn-sm" onclick="location.reload()" aria-label="Actualizar lista">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                <span class="d-none d-sm-inline">Actualizar</span>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0" id="tablaUsuarios">
                                    <thead class="table-dark">
                                        <tr>
                                            <th scope="col">Cédula</th>
                                            <th scope="col">Nombre</th>
                                            <th scope="col" class="d-none d-md-table-cell">Correo Electrónico</th>
                                            <th scope="col">Módulo</th>
                                            <th scope="col" class="d-none d-lg-table-cell">Fecha Creación</th>
                                            <th scope="col" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($usuarios)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <div class="empty-state">
                                                    <i class="bi bi-inbox display-4 d-block mb-3 text-muted"></i>
                                                    <h5>No hay usuarios registrados</h5>
                                                    <p class="text-muted">Cree el primer usuario usando la pestaña "Crear Usuario"</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($usuarios as $usuario): ?>
                                        <tr class="table-row-animated">
                                            <td class="fw-bold text-primary"><?= h($usuario['cedula']) ?></td>
                                            <td><?= h($usuario['nombre']) ?></td>
                                            <td class="d-none d-md-table-cell">
                                                <code class="bg-light text-dark p-1 rounded"><?= h($usuario['email']) ?></code>
                                            </td>
                                            <td>
                                                <span class="badge badge-module bg-primary">
                                                    <?= h($usuario['modulo']) ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-lg-table-cell">
                                                <small class="text-muted">
                                                    <?= $usuario['fecha_creacion'] ? date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])) : 'N/A' ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group" aria-label="Acciones de usuario">
                                                    <a href="?editar=<?= urlencode($usuario['cedula']) ?>" 
                                                       class="btn btn-sm btn-warning" 
                                                       title="Editar usuario <?= h($usuario['nombre']) ?>"
                                                       data-bs-toggle="tooltip">
                                                        <i class="bi bi-pencil-square"></i>
                                                        <span class="d-none d-xl-inline ms-1">Editar</span>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger" 
                                                            onclick="confirmarEliminacion('<?= h($usuario['cedula']) ?>', '<?= h($usuario['nombre']) ?>')"
                                                            title="Eliminar usuario <?= h($usuario['nombre']) ?>"
                                                            data-bs-toggle="tooltip">
                                                        <i class="bi bi-trash3"></i>
                                                        <span class="d-none d-xl-inline ms-1">Eliminar</span>
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

    <!-- Edit User Modal -->
    <div class="modal fade <?= $mostrar_modal ? 'show' : '' ?>" id="modalEditar" tabindex="-1" 
         aria-labelledby="modalEditarLabel" aria-hidden="<?= $mostrar_modal ? 'false' : 'true' ?>"
         style="<?= $mostrar_modal ? 'display: block;' : '' ?>">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-warning text-white">
                    <h5 class="modal-title" id="modalEditarLabel">
                        <i class="bi bi-pencil-square me-2"></i>Editar Usuario
                    </h5>
                    <a href="?" class="btn-close btn-close-white" aria-label="Cerrar"></a>
                </div>
                <div class="modal-body">
                    <?php if ($usuario_editar): ?>
                    <form method="POST" id="formEditar" class="needs-validation" novalidate>
                        <input type="hidden" name="accion" value="actualizar">
                        <input type="hidden" name="edit_cedula" value="<?= h($usuario_editar['cedula']) ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editCedula" class="form-label">Cédula</label>
                                <input type="text" id="editCedula" class="form-control" 
                                       value="<?= h($usuario_editar['cedula']) ?>" disabled>
                                <div class="form-text">La cédula no se puede modificar</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editNombre" class="form-label required">Nombre Completo</label>
                                <input type="text" id="editNombre" name="nombre" class="form-control" 
                                       value="<?= h($usuario_editar['nombre']) ?>"
                                       maxlength="100" required>
                                <div class="invalid-feedback">
                                    El nombre solo debe contener letras y espacios
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editEmail" class="form-label required">Correo Electrónico</label>
                                <input type="email" id="editEmail" name="email" class="form-control" 
                                       value="<?= h($usuario_editar['email']) ?>"
                                       maxlength="100" required>
                                <div class="invalid-feedback">
                                    Ingrese un correo electrónico válido
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editModulo" class="form-label required">Módulo</label>
                                <select id="editModulo" name="modulo" class="form-select" required>
                                    <option value="Administrador" <?= $usuario_editar['modulo'] == 'Administrador' ? 'selected' : '' ?>>Administrador</option>
                                    <option value="Vendedor" <?= $usuario_editar['modulo'] == 'Vendedor' ? 'selected' : '' ?>>Vendedor</option>
                                    <option value="Agendamiento" <?= $usuario_editar['modulo'] == 'Agendamiento' ? 'selected' : '' ?>>Agendamiento</option>
                                    <option value="Aprovisionamiento" <?= $usuario_editar['modulo'] == 'Aprovisionamiento' ? 'selected' : '' ?>>Aprovisionamiento</option>
                                </select>
                                <div class="invalid-feedback">
                                    Debe seleccionar un módulo
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editContrasena" class="form-label">Nueva Contraseña (opcional)</label>
                            <div class="input-group">
                                <input type="password" id="editContrasena" name="contrasena" class="form-control" 
                                       minlength="6" maxlength="255">
                                <button class="btn btn-outline-secondary" type="button" id="toggleEditPassword"
                                        aria-label="Mostrar/ocultar contraseña">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Dejar vacío para mantener la contraseña actual. Mínimo 8 caracteres si se cambia.</div>
                            <div class="invalid-feedback">
                                La contraseña debe tener al menos 8 caracteres
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <a href="?" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-1"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-warning text-white" id="btnEditar">
                                <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                <i class="bi bi-check-circle me-1"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        No se pudo cargar la información del usuario.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($mostrar_modal): ?>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="modalEliminar" tabindex="-1" aria-labelledby="modalEliminarLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-gradient-danger text-white">
                    <h5 class="modal-title" id="modalEliminarLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-trash3 text-danger display-1 mb-3"></i>
                    </div>
                    <div class="text-center mb-3">
                        <h6>¿Está seguro de que desea eliminar al usuario?</h6>
                    </div>
                    <div class="alert alert-warning border-0">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-info-circle me-2"></i>
                            <div>
                                <strong>Usuario:</strong> <span id="nombreUsuarioEliminar" class="fw-bold"></span><br>
                                <strong>Cédula:</strong> <span id="cedulaUsuarioEliminar" class="fw-bold"></span>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-danger border-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Advertencia:</strong> Esta acción no se puede deshacer.
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" 
            crossorigin="anonymous"></script>
    
    <!-- Custom JS -->
    <script src="js/admon.js"></script>
</body>
</html>