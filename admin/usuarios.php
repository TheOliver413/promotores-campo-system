<?php
$pageTitle = 'Gestión de Usuarios';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/User.php';
require_once __DIR__ . '/../db/UsuarioCliente.php';
require_once __DIR__ . '/../db/SupervisorPromotor.php';
require_once __DIR__ . '/../db/Auditoria.php';

requireRole(['Administrador']);

$userModel = new User();
$usuarioClienteModel = new UsuarioCliente();
$supervisorPromotorModel = new SupervisorPromotor();
$auditoriaModel = new Auditoria();

// Handle CRUD operations BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        try {
            $data = [
                'nombre_completo' => $_POST['nombre_completo'],
                'email' => $_POST['email'],
                'password' => $_POST['password'],
                'telefono' => $_POST['telefono'] ?? null,
                'role_id' => $_POST['role_id'],
                'estado' => 'activo'
            ];

            $userId = $userModel->create($data);
            if ($userId) {
                if (!empty($_POST['clientes'])) {
                    $usuarioClienteModel->asignarClientes($userId, $_POST['clientes']);
                }

                if (!empty($_POST['supervisores'])) {
                    foreach ($_POST['supervisores'] as $supervisorId) {
                        $supervisorPromotorModel->asignarPromotores($supervisorId, [$userId]);
                    }
                }

                $auditoriaModel->registrar(getUserId(), 'CREATE', 'usuarios', $userId);
                $_SESSION['success'] = 'Usuario creado exitosamente';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al crear usuario: ' . $e->getMessage();
        }
    } elseif ($action === 'update') {
        $userId = $_POST['user_id'];

        try {
            $data = [
                'nombre_completo' => $_POST['nombre_completo'],
                'email' => $_POST['email'],
                'telefono' => $_POST['telefono'] ?? null,
                'role_id' => $_POST['role_id'],
                'estado' => $_POST['estado']
            ];

            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }

            if ($userModel->update($userId, $data)) {
                if (isset($_POST['clientes'])) {
                    $usuarioClienteModel->asignarClientes($userId, $_POST['clientes']);
                }

                if (isset($_POST['supervisores'])) {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("DELETE FROM supervisor_promotores WHERE promotor_id = ?");
                    $stmt->execute([$userId]);

                    if (!empty($_POST['supervisores'])) {
                        foreach ($_POST['supervisores'] as $supervisorId) {
                            $stmt = $db->prepare("INSERT INTO supervisor_promotores (supervisor_id, promotor_id) VALUES (?, ?)");
                            $stmt->execute([$supervisorId, $userId]);
                        }
                    }
                }

                $auditoriaModel->registrar(getUserId(), 'UPDATE', 'usuarios', $userId);
                $_SESSION['success'] = 'Usuario actualizado exitosamente';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al actualizar usuario: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $userId = $_POST['user_id'];
        if ($userModel->delete($userId)) {
            $auditoriaModel->registrar(getUserId(), 'DELETE', 'usuarios', $userId);
            $_SESSION['success'] = 'Usuario eliminado exitosamente (eliminación lógica)';
        }
    } elseif ($action === 'toggle_status') {
        $userId = $_POST['user_id'];
        $newStatus = $_POST['new_status'];
        if ($userModel->updateStatus($userId, $newStatus)) {
            $auditoriaModel->registrar(getUserId(), 'UPDATE_STATUS', 'usuarios', $userId);
            $_SESSION['success'] = 'Estado actualizado exitosamente';
        }
    }

    header('Location: /promotores-campo-system/admin/usuarios.php');
    exit();
}

require_once __DIR__ . '/../includes/header.php';

$filtros = [
    'nombre' => $_GET['nombre'] ?? '',
    'email' => $_GET['email'] ?? '',
    'role_id' => $_GET['role_id'] ?? '',
    'estado' => $_GET['estado'] ?? '',
];

$paginaActual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$registrosPorPagina = 20;
$offset = ($paginaActual - 1) * $registrosPorPagina;

$db = Database::getInstance()->getConnection();

// Build WHERE clause based on filters
$whereConditions = [];
$params = [];

if (!empty($filtros['nombre'])) {
    $whereConditions[] = "u.nombre_completo LIKE ?";
    $params[] = "%{$filtros['nombre']}%";
}

if (!empty($filtros['email'])) {
    $whereConditions[] = "u.email LIKE ?";
    $params[] = "%{$filtros['email']}%";
}

if (!empty($filtros['role_id'])) {
    $whereConditions[] = "u.role_id = ?";
    $params[] = $filtros['role_id'];
}

if (!empty($filtros['estado'])) {
    $whereConditions[] = "u.estado = ?";
    $params[] = $filtros['estado'];
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Count total records
$countStmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM usuarios u
    {$whereClause}
");
$countStmt->execute($params);
$totalRegistros = $countStmt->fetch()['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Get paginated results
$stmt = $db->prepare("
    SELECT u.*,
           r.nombre as role_name,
           GROUP_CONCAT(DISTINCT c.nombre_empresa ORDER BY c.nombre_empresa SEPARATOR ', ') as clientes_nombres,
           GROUP_CONCAT(DISTINCT s.nombre_completo ORDER BY s.nombre_completo SEPARATOR ', ') as supervisores_nombres
    FROM usuarios u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN usuario_clientes uc ON u.id = uc.usuario_id
    LEFT JOIN clientes c ON uc.cliente_id = c.id
    LEFT JOIN supervisor_promotores sp ON u.id = sp.promotor_id
    LEFT JOIN usuarios s ON sp.supervisor_id = s.id
    {$whereClause}
    GROUP BY u.id
    ORDER BY u.fecha_registro DESC
    LIMIT {$registrosPorPagina} OFFSET {$offset}
");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Get roles for dropdown
$rolesStmt = $db->query("SELECT id, nombre FROM roles ORDER BY nombre");
$roles = $rolesStmt->fetchAll();

$supervisores = $userModel->getSupervisores();

// Get clients for dropdown
$clientesStmt = $db->query("SELECT id, nombre_empresa FROM clientes WHERE activo = true ORDER BY nombre_empresa");
$clientes = $clientesStmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Gestión de Usuarios</h1>
            <p class="text-muted">Administra todos los usuarios del sistema</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
                <i class="bi bi-plus-circle"></i> Nuevo Usuario
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> <?php echo $_SESSION['success'];
                                                    unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $_SESSION['error'];
                                                            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters section -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="bi bi-funnel"></i> Filtros
                <button class="btn btn-sm btn-link float-end" onclick="limpiarFiltros()">
                    <i class="bi bi-x-circle"></i> Limpiar
                </button>
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="filtro_nombre" class="form-label">Nombre</label>
                    <input type="text" class="form-control" id="filtro_nombre" name="nombre"
                        value="<?php echo htmlspecialchars($filtros['nombre']); ?>"
                        placeholder="Buscar por nombre...">
                </div>
                <div class="col-md-3">
                    <label for="filtro_email" class="form-label">Email</label>
                    <input type="text" class="form-control" id="filtro_email" name="email"
                        value="<?php echo htmlspecialchars($filtros['email']); ?>"
                        placeholder="Buscar por email...">
                </div>
                <div class="col-md-2">
                    <label for="filtro_role" class="form-label">Rol</label>
                    <select class="form-select" id="filtro_role" name="role_id">
                        <option value="">Todos</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?php echo $rol['id']; ?>"
                                <?php echo $filtros['role_id'] == $rol['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rol['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filtro_estado" class="form-label">Estado</label>
                    <select class="form-select" id="filtro_estado" name="estado">
                        <option value="">Todos</option>
                        <option value="activo" <?php echo $filtros['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $filtros['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if (empty($usuarios)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people" style="font-size: 4rem; color: #dee2e6;"></i>
                    <h4 class="text-muted mt-3">No hay usuarios que coincidan con los filtros</h4>
                    <p class="text-muted mb-4">Intenta ajustar los criterios de búsqueda</p>
                </div>
            <?php else: ?>
                <!-- Results info -->
                <div class="mb-3">
                    <small class="text-muted">
                        Mostrando <?php echo count($usuarios); ?> de <?php echo $totalRegistros; ?> registros
                        <?php if ($totalPaginas > 1): ?>
                            (Página <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?>)
                        <?php endif; ?>
                    </small>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="usuariosTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Rol</th>
                                <th>Clientes Asignados</th>
                                <th>Supervisores</th>
                                <th>Estado</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo $usuario['id']; ?></td>
                                    <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['telefono'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($usuario['role_name']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($usuario['clientes_nombres'])): ?>
                                            <small class="text-success">
                                                <i class="bi bi-building"></i>
                                                <?php echo htmlspecialchars($usuario['clientes_nombres']); ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($usuario['supervisores_nombres'])): ?>
                                            <small class="text-primary">
                                                <i class="bi bi-person-badge"></i>
                                                <?php echo htmlspecialchars($usuario['supervisores_nombres']); ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($usuario['estado'] === 'activo'): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo $usuario['id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="toggleStatus(<?php echo $usuario['id']; ?>, '<?php echo $usuario['estado'] === 'activo' ? 'inactivo' : 'activo'; ?>')">
                                            <i class="bi bi-toggle-<?php echo $usuario['estado'] === 'activo' ? 'on' : 'off'; ?>"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre_completo']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de usuarios" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($paginaActual > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($filtros, ['pagina' => $paginaActual - 1])); ?>">
                                        <i class="bi bi-chevron-left"></i> Anterior
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $rangoInicio = max(1, $paginaActual - 2);
                            $rangoFin = min($totalPaginas, $paginaActual + 2);

                            if ($rangoInicio > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($filtros, ['pagina' => 1])); ?>">1</a>
                                </li>
                                <?php if ($rangoInicio > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $rangoInicio; $i <= $rangoFin; $i++): ?>
                                <li class="page-item <?php echo $i === $paginaActual ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($filtros, ['pagina' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($rangoFin < $totalPaginas): ?>
                                <?php if ($rangoFin < $totalPaginas - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($filtros, ['pagina' => $totalPaginas])); ?>">
                                        <?php echo $totalPaginas; ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if ($paginaActual < $totalPaginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($filtros, ['pagina' => $paginaActual + 1])); ?>">
                                        Siguiente <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="user_id" id="userId">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre_completo" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Contraseña <span id="passwordRequired">*</span></label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="text-muted">Dejar en blanco para mantener la actual (al editar)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role_id" class="form-label">Rol *</label>
                            <select class="form-select" id="role_id" name="role_id" required onchange="toggleRoleFields()">
                                <option value="">Seleccione un rol</option>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?php echo $rol['id']; ?>"><?php echo htmlspecialchars($rol['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <!-- Multi-select for clientes -->
                    <div class="row" id="clientesGroup" style="display: none;">
                        <div class="col-12 mb-3">
                            <label class="form-label">
                                <i class="bi bi-building text-success"></i> Empresas/Clientes Asignados *
                            </label>
                            <div class="alert alert-info py-2 mb-2">
                                <small>
                                    <i class="bi bi-info-circle"></i>
                                    Selecciona una o más empresas a las que este usuario tendrá acceso.
                                </small>
                            </div>
                            <div class="border rounded p-3" style="max-height: 250px; overflow-y: auto; background-color: #f8f9fa;">
                                <?php if (empty($clientes)): ?>
                                    <div class="alert alert-warning mb-0">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        No hay empresas disponibles.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="clientes[]"
                                                value="<?php echo $cliente['id']; ?>"
                                                id="cliente_<?php echo $cliente['id']; ?>">
                                            <label class="form-check-label" for="cliente_<?php echo $cliente['id']; ?>">
                                                <i class="bi bi-building text-success"></i>
                                                <strong><?php echo htmlspecialchars($cliente['nombre_empresa']); ?></strong>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Multi-select for supervisores -->
                    <div class="row" id="supervisoresGroup" style="display: none;">
                        <div class="col-12 mb-3">
                            <label class="form-label">
                                <i class="bi bi-person-badge text-primary"></i> Supervisores Asignados
                            </label>
                            <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto; background-color: #f8f9fa;">
                                <?php if (empty($supervisores)): ?>
                                    <p class="text-muted mb-0">
                                        <i class="bi bi-info-circle"></i>
                                        No hay supervisores disponibles
                                    </p>
                                <?php else: ?>
                                    <?php foreach ($supervisores as $supervisor): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="supervisores[]"
                                                value="<?php echo $supervisor['id']; ?>"
                                                id="supervisor_<?php echo $supervisor['id']; ?>">
                                            <label class="form-check-label" for="supervisor_<?php echo $supervisor['id']; ?>">
                                                <i class="bi bi-person-badge text-primary"></i>
                                                <?php echo htmlspecialchars($supervisor['nombre_completo']); ?>
                                                <small class="text-muted">(<?php echo htmlspecialchars($supervisor['email']); ?>)</small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar al usuario <strong id="deleteUserName"></strong>?</p>
                    <p class="text-danger"><i class="bi bi-exclamation-triangle"></i> Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Status Form -->
<form method="POST" id="toggleStatusForm" style="display: none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="user_id" id="toggleUserId">
    <input type="hidden" name="new_status" id="toggleNewStatus">
</form>

<script>
    function limpiarFiltros() {
        window.location.href = '/promotores-campo-system/admin/usuarios.php';
    }

    function toggleRoleFields() {
        const roleSelect = document.getElementById('role_id');
        const selectedRole = roleSelect.options[roleSelect.selectedIndex].text;

        const supervisoresGroup = document.getElementById('supervisoresGroup');
        const clientesGroup = document.getElementById('clientesGroup');

        if (selectedRole === 'Cliente' || selectedRole === 'Supervisor' || selectedRole === 'Promotor') {
            clientesGroup.style.display = 'block';
        } else {
            clientesGroup.style.display = 'none';
        }

        if (selectedRole === 'Promotor') {
            supervisoresGroup.style.display = 'block';
        } else {
            supervisoresGroup.style.display = 'none';
        }
    }

    function resetForm() {
        document.getElementById('userForm').reset();
        document.getElementById('formAction').value = 'create';
        document.getElementById('userId').value = '';
        document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
        document.getElementById('password').required = true;
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('supervisoresGroup').style.display = 'none';
        document.getElementById('clientesGroup').style.display = 'none';

        document.querySelectorAll('input[type="checkbox"][name="clientes[]"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[type="checkbox"][name="supervisores[]"]').forEach(cb => cb.checked = false);
    }

    async function editUser(userId) {
        try {
            const response = await fetch(`/promotores-campo-system/api/usuario_crud.php?action=get&id=${userId}`);
            const data = await response.json();

            if (!data.success) {
                alert('Error al cargar usuario: ' + (data.message || 'Error desconocido'));
                return;
            }

            document.getElementById('formAction').value = 'update';
            document.getElementById('userId').value = data.usuario.id;
            document.getElementById('nombre_completo').value = data.usuario.nombre_completo;
            document.getElementById('email').value = data.usuario.email;
            document.getElementById('telefono').value = data.usuario.telefono || '';
            document.getElementById('role_id').value = data.usuario.role_id;
            document.getElementById('estado').value = data.usuario.estado;
            document.getElementById('modalTitle').textContent = 'Editar Usuario';
            document.getElementById('password').required = false;
            document.getElementById('passwordRequired').style.display = 'none';

            toggleRoleFields();

            document.querySelectorAll('input[type="checkbox"][name="clientes[]"]').forEach(cb => {
                cb.checked = data.clientes && data.clientes.includes(parseInt(cb.value));
            });

            document.querySelectorAll('input[type="checkbox"][name="supervisores[]"]').forEach(cb => {
                cb.checked = data.supervisores && data.supervisores.includes(parseInt(cb.value));
            });

            new bootstrap.Modal(document.getElementById('userModal')).show();
        } catch (error) {
            console.error('Error loading user:', error);
            alert('Error al cargar usuario: ' + error.message);
        }
    }

    function deleteUser(userId, userName) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteUserName').textContent = userName;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    function toggleStatus(userId, newStatus) {
        if (confirm('¿Está seguro que desea cambiar el estado de este usuario?')) {
            document.getElementById('toggleUserId').value = userId;
            document.getElementById('toggleNewStatus').value = newStatus;
            document.getElementById('toggleStatusForm').submit();
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>