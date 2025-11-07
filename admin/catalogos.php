<?php
$pageTitle = 'Catálogos del Sistema';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/Auditoria.php';

requireRole(['Administrador']);

$db = Database::getInstance()->getConnection();
$auditoriaModel = new Auditoria();

// Handle CRUD for tipos_actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo_form'])) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_tipo') {
        $stmt = $db->prepare("INSERT INTO tipos_actividad (nombre, descripcion, requiere_evidencia) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['nombre'], $_POST['descripcion'], isset($_POST['requiere_evidencia']) ? 1 : 0]);
        $auditoriaModel->registrar(getUserId(), 'CREATE', 'tipos_actividad', $db->lastInsertId());
        $_SESSION['success'] = 'Tipo de actividad creado';
    } elseif ($action === 'update_tipo') {
        $stmt = $db->prepare("UPDATE tipos_actividad SET nombre = ?, descripcion = ?, requiere_evidencia = ? WHERE id = ?");
        $stmt->execute([$_POST['nombre'], $_POST['descripcion'], isset($_POST['requiere_evidencia']) ? 1 : 0, $_POST['tipo_id']]);
        $auditoriaModel->registrar(getUserId(), 'UPDATE', 'tipos_actividad', $_POST['tipo_id']);
        $_SESSION['success'] = 'Tipo de actividad actualizado';
    } elseif ($action === 'delete_tipo') {
        $stmt = $db->prepare("DELETE FROM tipos_actividad WHERE id = ?");
        $stmt->execute([$_POST['tipo_id']]);
        $auditoriaModel->registrar(getUserId(), 'DELETE', 'tipos_actividad', $_POST['tipo_id']);
        $_SESSION['success'] = 'Tipo de actividad eliminado';
    }

    header('Location: /admin/catalogos.php');
    exit();
}

// Handle CRUD for configuraciones_globales
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['config_form'])) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_config') {
        $stmt = $db->prepare("INSERT INTO configuraciones_globales (clave, valor, descripcion) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['clave'], $_POST['valor'], $_POST['descripcion']]);
        $auditoriaModel->registrar(getUserId(), 'CREATE', 'configuraciones_globales', $db->lastInsertId());
        $_SESSION['success'] = 'Configuración creada';
    } elseif ($action === 'update_config') {
        $stmt = $db->prepare("UPDATE configuraciones_globales SET clave = ?, valor = ?, descripcion = ? WHERE id = ?");
        $stmt->execute([$_POST['clave'], $_POST['valor'], $_POST['descripcion'], $_POST['config_id']]);
        $auditoriaModel->registrar(getUserId(), 'UPDATE', 'configuraciones_globales', $_POST['config_id']);
        $_SESSION['success'] = 'Configuración actualizada';
    } elseif ($action === 'delete_config') {
        $stmt = $db->prepare("DELETE FROM configuraciones_globales WHERE id = ?");
        $stmt->execute([$_POST['config_id']]);
        $auditoriaModel->registrar(getUserId(), 'DELETE', 'configuraciones_globales', $_POST['config_id']);
        $_SESSION['success'] = 'Configuración eliminada';
    }

    header('Location: /admin/catalogos.php');
    exit();
}

// Get data
$tiposStmt = $db->query("SELECT * FROM tipos_actividad ORDER BY nombre");
$tipos = $tiposStmt->fetchAll();

$configsStmt = $db->query("SELECT * FROM configuraciones_globales ORDER BY clave");
$configs = $configsStmt->fetchAll();
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Catálogos del Sistema</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?php echo $_SESSION['success'];
                                                    unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tipos de Actividad -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Tipos de Actividad</h5>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#tipoModal" onclick="resetTipoForm()">
                <i class="bi bi-plus-circle"></i> Nuevo Tipo
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Requiere Evidencia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tipos as $tipo): ?>
                            <tr>
                                <td><?php echo $tipo['id']; ?></td>
                                <td><?php echo htmlspecialchars($tipo['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($tipo['descripcion'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($tipo['requiere_evidencia']): ?>
                                        <span class="badge bg-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick='editTipo(<?php echo json_encode($tipo); ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTipo(<?php echo $tipo['id']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Configuraciones Globales -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Configuraciones Globales</h5>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#configModal" onclick="resetConfigForm()">
                <i class="bi bi-plus-circle"></i> Nueva Configuración
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Clave</th>
                            <th>Valor</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configs as $config): ?>
                            <tr>
                                <td><?php echo $config['id']; ?></td>
                                <td><code><?php echo htmlspecialchars($config['clave']); ?></code></td>
                                <td><?php echo htmlspecialchars($config['valor']); ?></td>
                                <td><?php echo htmlspecialchars($config['descripcion'] ?? '-'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick='editConfig(<?php echo json_encode($config); ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteConfig(<?php echo $config['id']; ?>, '<?php echo htmlspecialchars($config['clave']); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Tipo Actividad Modal -->
<div class="modal fade" id="tipoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="tipo_form" value="1">
                <input type="hidden" name="action" id="tipoAction" value="create_tipo">
                <input type="hidden" name="tipo_id" id="tipoId">
                <div class="modal-header">
                    <h5 class="modal-title" id="tipoModalTitle">Nuevo Tipo de Actividad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tipo_nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="tipo_nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="tipo_descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="tipo_descripcion" name="descripcion" rows="2"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="tipo_requiere_evidencia" name="requiere_evidencia">
                        <label class="form-check-label" for="tipo_requiere_evidencia">Requiere Evidencia</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Config Modal -->
<div class="modal fade" id="configModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="config_form" value="1">
                <input type="hidden" name="action" id="configAction" value="create_config">
                <input type="hidden" name="config_id" id="configId">
                <div class="modal-header">
                    <h5 class="modal-title" id="configModalTitle">Nueva Configuración</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="config_clave" class="form-label">Clave *</label>
                        <input type="text" class="form-control" id="config_clave" name="clave" required>
                    </div>
                    <div class="mb-3">
                        <label for="config_valor" class="form-label">Valor *</label>
                        <input type="text" class="form-control" id="config_valor" name="valor" required>
                    </div>
                    <div class="mb-3">
                        <label for="config_descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="config_descripcion" name="descripcion" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Tipo Modal -->
<div class="modal fade" id="deleteTipoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="tipo_form" value="1">
                <input type="hidden" name="action" value="delete_tipo">
                <input type="hidden" name="tipo_id" id="deleteTipoId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Eliminar tipo <strong id="deleteTipoName"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Config Modal -->
<div class="modal fade" id="deleteConfigModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="config_form" value="1">
                <input type="hidden" name="action" value="delete_config">
                <input type="hidden" name="config_id" id="deleteConfigId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Eliminar configuración <strong id="deleteConfigName"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function resetTipoForm() {
        document.getElementById('tipoAction').value = 'create_tipo';
        document.getElementById('tipoId').value = '';
        document.getElementById('tipo_nombre').value = '';
        document.getElementById('tipo_descripcion').value = '';
        document.getElementById('tipo_requiere_evidencia').checked = false;
        document.getElementById('tipoModalTitle').textContent = 'Nuevo Tipo de Actividad';
    }

    function editTipo(tipo) {
        document.getElementById('tipoAction').value = 'update_tipo';
        document.getElementById('tipoId').value = tipo.id;
        document.getElementById('tipo_nombre').value = tipo.nombre;
        document.getElementById('tipo_descripcion').value = tipo.descripcion || '';
        document.getElementById('tipo_requiere_evidencia').checked = tipo.requiere_evidencia;
        document.getElementById('tipoModalTitle').textContent = 'Editar Tipo de Actividad';
        new bootstrap.Modal(document.getElementById('tipoModal')).show();
    }

    function deleteTipo(id, nombre) {
        document.getElementById('deleteTipoId').value = id;
        document.getElementById('deleteTipoName').textContent = nombre;
        new bootstrap.Modal(document.getElementById('deleteTipoModal')).show();
    }

    function resetConfigForm() {
        document.getElementById('configAction').value = 'create_config';
        document.getElementById('configId').value = '';
        document.getElementById('config_clave').value = '';
        document.getElementById('config_valor').value = '';
        document.getElementById('config_descripcion').value = '';
        document.getElementById('configModalTitle').textContent = 'Nueva Configuración';
    }

    function editConfig(config) {
        document.getElementById('configAction').value = 'update_config';
        document.getElementById('configId').value = config.id;
        document.getElementById('config_clave').value = config.clave;
        document.getElementById('config_valor').value = config.valor;
        document.getElementById('config_descripcion').value = config.descripcion || '';
        document.getElementById('configModalTitle').textContent = 'Editar Configuración';
        new bootstrap.Modal(document.getElementById('configModal')).show();
    }

    function deleteConfig(id, clave) {
        document.getElementById('deleteConfigId').value = id;
        document.getElementById('deleteConfigName').textContent = clave;
        new bootstrap.Modal(document.getElementById('deleteConfigModal')).show();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>