<?php
$pageTitle = 'Gestión de Roles';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/Auditoria.php';

requireRole(['Administrador']);

$db = Database::getInstance()->getConnection();
$auditoriaModel = new Auditoria();

// Handle CRUD operations BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $stmt = $db->prepare("INSERT INTO roles (nombre, descripcion, permisos) VALUES (?, ?, ?)");
        $permisos = json_encode($_POST['permisos'] ?? []);
        $stmt->execute([$_POST['nombre'], $_POST['descripcion'], $permisos]);
        $auditoriaModel->registrar(getUserId(), 'CREATE', 'roles', $db->lastInsertId());
        $_SESSION['success'] = 'Rol creado exitosamente';
    } elseif ($action === 'update') {
        $stmt = $db->prepare("UPDATE roles SET nombre = ?, descripcion = ?, permisos = ? WHERE id = ?");
        $permisos = json_encode($_POST['permisos'] ?? []);
        $stmt->execute([$_POST['nombre'], $_POST['descripcion'], $permisos, $_POST['role_id']]);
        $auditoriaModel->registrar(getUserId(), 'UPDATE', 'roles', $_POST['role_id']);
        $_SESSION['success'] = 'Rol actualizado exitosamente';
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$_POST['role_id']]);
        $auditoriaModel->registrar(getUserId(), 'DELETE', 'roles', $_POST['role_id']);
        $_SESSION['success'] = 'Rol eliminado exitosamente';
    }

    header('Location: /admin/roles.php');
    exit();
}

require_once __DIR__ . '/../includes/header.php';

$rolesStmt = $db->query("SELECT * FROM roles ORDER BY nombre");
$roles = $rolesStmt->fetchAll();

// Available permissions
$availablePermisos = [
    'usuarios.view' => 'Ver Usuarios',
    'usuarios.create' => 'Crear Usuarios',
    'usuarios.edit' => 'Editar Usuarios',
    'usuarios.delete' => 'Eliminar Usuarios',
    'proyectos.view' => 'Ver Proyectos',
    'proyectos.create' => 'Crear Proyectos',
    'proyectos.edit' => 'Editar Proyectos',
    'proyectos.delete' => 'Eliminar Proyectos',
    'jornadas.view' => 'Ver Jornadas',
    'jornadas.validate' => 'Validar Jornadas',
    'reportes.view' => 'Ver Reportes',
    'reportes.export' => 'Exportar Reportes',
    'auditoria.view' => 'Ver Auditoría',
];
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Gestión de Roles</h1>
            <p class="text-muted">Administra los roles y permisos del sistema</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roleModal" onclick="resetForm()">
                <i class="bi bi-plus-circle"></i> Nuevo Rol
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?php echo $_SESSION['success'];
                                                    unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($roles as $rol): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?php echo htmlspecialchars($rol['nombre']); ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted"><?php echo htmlspecialchars($rol['descripcion'] ?? 'Sin descripción'); ?></p>

                        <h6 class="mt-3">Permisos:</h6>
                        <?php
                        $permisos = json_decode($rol['permisos'] ?? '[]', true);
                        if (empty($permisos)):
                        ?>
                            <p class="text-muted small">Sin permisos asignados</p>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($permisos as $permiso): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($permiso); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-sm btn-outline-primary" onclick='editRole(<?php echo json_encode($rol); ?>)'>
                            <i class="bi bi-pencil"></i> Editar
                        </button>
                        <?php if (!in_array($rol['nombre'], ['Administrador', 'Supervisor', 'Promotor', 'Cliente'])): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteRole(<?php echo $rol['id']; ?>, '<?php echo htmlspecialchars($rol['nombre']); ?>')">
                                <i class="bi bi-trash"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Role Modal -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="roleForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Rol</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="role_id" id="roleId">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre del Rol *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Permisos</label>
                        <div class="row">
                            <?php foreach ($availablePermisos as $key => $label): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permisos[]" value="<?php echo $key; ?>" id="perm_<?php echo $key; ?>">
                                        <label class="form-check-label" for="perm_<?php echo $key; ?>">
                                            <?php echo $label; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
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

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="role_id" id="deleteRoleId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el rol <strong id="deleteRoleName"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function resetForm() {
        document.getElementById('roleForm').reset();
        document.getElementById('formAction').value = 'create';
        document.getElementById('roleId').value = '';
        document.getElementById('modalTitle').textContent = 'Nuevo Rol';
        document.querySelectorAll('input[name="permisos[]"]').forEach(cb => cb.checked = false);
    }

    function editRole(role) {
        document.getElementById('formAction').value = 'update';
        document.getElementById('roleId').value = role.id;
        document.getElementById('nombre').value = role.nombre;
        document.getElementById('descripcion').value = role.descripcion || '';
        document.getElementById('modalTitle').textContent = 'Editar Rol';

        const permisos = JSON.parse(role.permisos || '[]');
        document.querySelectorAll('input[name="permisos[]"]').forEach(cb => {
            cb.checked = permisos.includes(cb.value);
        });

        new bootstrap.Modal(document.getElementById('roleModal')).show();
    }

    function deleteRole(roleId, roleName) {
        document.getElementById('deleteRoleId').value = roleId;
        document.getElementById('deleteRoleName').textContent = roleName;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>