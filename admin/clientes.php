<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/Cliente.php';
require_once __DIR__ . '/../db/User.php';
require_once __DIR__ . '/../db/Auditoria.php';
require_once __DIR__ . '/../db/UsuarioCliente.php';

requireRole(['Administrador']);

$clienteModel = new Cliente();
$userModel = new User();
$auditoriaModel = new Auditoria();
$usuarioClienteModel = new UsuarioCliente();

// Handle CRUD operations BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $transactionStarted = false;
        $db = Database::getInstance()->getConnection();

        try {
            $db->beginTransaction();
            $transactionStarted = true;

            $clienteId = $clienteModel->create([
                'nombre_empresa' => $_POST['nombre_empresa'],
                'contacto_email' => $_POST['contacto_email'],
                'telefono' => $_POST['telefono'] ?? null,
                'activo' => true
            ]);

            // Create user for client
            $roleClienteStmt = $db->query("SELECT id FROM roles WHERE nombre = 'Cliente'");
            $roleClienteId = $roleClienteStmt->fetch()['id'];

            $userId = $userModel->create([
                'nombre_completo' => $_POST['contacto_principal'],
                'email' => $_POST['contacto_email'],
                'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'telefono' => $_POST['telefono'] ?? null,
                'role_id' => $roleClienteId,
                'cliente_id' => $clienteId,
                'estado' => 'activo'
            ]);

            if ($userId) {
                $usuarioClienteModel->asignarClientes($userId, [$clienteId]);
            }

            $db->commit();
            $auditoriaModel->registrar(getUserId(), 'CREATE', 'clientes', $clienteId);
            $_SESSION['success'] = 'Cliente creado exitosamente';
        } catch (Exception $e) {
            if ($transactionStarted && $db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = 'Error al crear cliente: ' . $e->getMessage();
        }
    } elseif ($action === 'update') {
        $clienteId = $_POST['cliente_id'];
        $transactionStarted = false;
        $db = Database::getInstance()->getConnection();

        try {
            $db->beginTransaction();
            $transactionStarted = true;

            // Get current client data
            $cliente = $clienteModel->getById($clienteId);

            // Update cliente table
            if ($clienteModel->update($clienteId, [
                'nombre_empresa' => $_POST['nombre_empresa'],
                'contacto_email' => $_POST['contacto_email'],
                'telefono' => $_POST['telefono'] ?? null,
                'activo' => isset($_POST['activo'])
            ])) {
                // Get all users associated with this client via usuario_clientes table
                $stmt = $db->prepare("
                    SELECT DISTINCT u.id 
                    FROM usuarios u
                    INNER JOIN usuario_clientes uc ON u.id = uc.usuario_id
                    INNER JOIN roles r ON u.role_id = r.id
                    WHERE uc.cliente_id = ? AND r.nombre = 'Cliente'
                ");
                $stmt->execute([$clienteId]);
                $users = $stmt->fetchAll();

                // Update each user's information
                foreach ($users as $user) {
                    $userModel->update($user['id'], [
                        'email' => $_POST['contacto_email'],
                        'telefono' => $_POST['telefono'] ?? null
                    ]);
                }

                $db->commit();
                $auditoriaModel->registrar(getUserId(), 'UPDATE', 'clientes', $clienteId);
                $_SESSION['success'] = 'Cliente actualizado exitosamente';
            }
        } catch (Exception $e) {
            if ($transactionStarted && $db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = 'Error al actualizar cliente: ' . $e->getMessage();
        }
    } elseif ($action === 'toggle') {
        $clienteId = $_POST['cliente_id'];
        $db = Database::getInstance()->getConnection();

        try {
            // Get current status
            $cliente = $clienteModel->getById($clienteId);
            $newStatus = !$cliente['activo'];

            // Update client status
            $clienteModel->update($clienteId, [
                'nombre_empresa' => $cliente['nombre_empresa'],
                'contacto_email' => $cliente['contacto_email'],
                'telefono' => $cliente['telefono'],
                'activo' => $newStatus
            ]);

            $stmt = $db->prepare("
                UPDATE usuarios u
                INNER JOIN usuario_clientes uc ON u.id = uc.usuario_id
                INNER JOIN roles r ON u.role_id = r.id
                SET u.estado = ?
                WHERE uc.cliente_id = ? AND r.nombre = 'Cliente'
            ");
            $stmt->execute([
                $newStatus ? 'activo' : 'inactivo',
                $clienteId
            ]);

            $auditoriaModel->registrar(
                getUserId(),
                'UPDATE',
                'clientes',
                $clienteId,
                'Estado cambiado a ' . ($newStatus ? 'activo' : 'inactivo')
            );
            $_SESSION['success'] = 'Estado del cliente actualizado exitosamente';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al cambiar estado: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $clienteId = $_POST['cliente_id'];
        $transactionStarted = false;
        $db = Database::getInstance()->getConnection();

        try {
            $db->beginTransaction();
            $transactionStarted = true;

            // First, get all user IDs associated with this client
            $stmt = $db->prepare("
                SELECT DISTINCT u.id 
                FROM usuarios u
                INNER JOIN usuario_clientes uc ON u.id = uc.usuario_id
                INNER JOIN roles r ON u.role_id = r.id
                WHERE uc.cliente_id = ? AND r.nombre = 'Cliente'
            ");
            $stmt->execute([$clienteId]);
            $users = $stmt->fetchAll();

            // Delete from usuario_clientes junction table
            $stmt = $db->prepare("DELETE FROM usuario_clientes WHERE cliente_id = ?");
            $stmt->execute([$clienteId]);

            // Mark users as deleted (soft delete)
            foreach ($users as $user) {
                $userModel->delete($user['id']);
            }

            // Delete the client
            if ($clienteModel->delete($clienteId)) {
                $db->commit();
                $auditoriaModel->registrar(getUserId(), 'DELETE', 'clientes', $clienteId);
                $_SESSION['success'] = 'Cliente y usuario asociado eliminados exitosamente';
            } else {
                if ($transactionStarted && $db->inTransaction()) {
                    $db->rollBack();
                }
                $_SESSION['error'] = 'Error al eliminar cliente';
            }
        } catch (Exception $e) {
            if ($transactionStarted && $db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = 'Error al eliminar cliente: ' . $e->getMessage();
        }
    }

    header('Location: /promotores-campo-system/admin/clientes.php');
    exit();
}

$pageTitle = 'Gestión de Clientes';
require_once __DIR__ . '/../includes/header.php';

$clientes = $clienteModel->getAll();
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Gestión de Clientes</h1>
            <p class="text-muted">Administra los clientes del sistema</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clienteModal" onclick="resetForm()">
                <i class="bi bi-plus-circle"></i> Nuevo Cliente
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

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $_SESSION['error'];
                                                            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if (empty($clientes)): ?>
                <!-- Added empty state when no clients exist -->
                <div class="text-center py-5">
                    <i class="bi bi-building-x" style="font-size: 4rem; color: #dee2e6;"></i>
                    <h4 class="text-muted mt-3">No hay clientes registrados</h4>
                    <p class="text-muted mb-4">Crea el primer cliente para comenzar a gestionar tus empresas</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clienteModal" onclick="resetForm()">
                        <i class="bi bi-plus-circle"></i> Crear Primer Cliente
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Empresa</th>
                                <th>Email de Contacto</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td><?php echo $cliente['id']; ?></td>
                                    <td><?php echo htmlspecialchars($cliente['nombre_empresa']); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['contacto_email']); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['telefono'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($cliente['activo']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick='editCliente(<?php echo json_encode($cliente); ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <!-- Added toggle button to enable/disable client -->
                                        <button class="btn btn-sm btn-outline-<?php echo $cliente['activo'] ? 'warning' : 'success'; ?>"
                                            onclick="toggleCliente(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars($cliente['nombre_empresa']); ?>', <?php echo $cliente['activo'] ? 'true' : 'false'; ?>)"
                                            title="<?php echo $cliente['activo'] ? 'Deshabilitar' : 'Habilitar'; ?>">
                                            <i class="bi bi-<?php echo $cliente['activo'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCliente(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars($cliente['nombre_empresa']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cliente Modal -->
<div class="modal fade" id="clienteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="clienteForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="cliente_id" id="clienteId">

                    <div class="mb-3">
                        <label for="nombre_empresa" class="form-label">Nombre de la Empresa *</label>
                        <input type="text" class="form-control" id="nombre_empresa" name="nombre_empresa" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contacto_principal" class="form-label">Nombre del Contacto *</label>
                            <input type="text" class="form-control" id="contacto_principal" name="contacto_principal" required>
                            <small class="text-muted">Nombre de la persona de contacto</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contacto_email" class="form-label">Email de Contacto *</label>
                            <input type="email" class="form-control" id="contacto_email" name="contacto_email" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono">
                        </div>
                        <div class="col-md-6 mb-3" id="passwordField">
                            <label for="password" class="form-label">Contraseña de Usuario *</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="text-muted">Para acceso al sistema</small>
                        </div>
                    </div>

                    <div class="mb-3 form-check" id="activoField" style="display: none;">
                        <input type="checkbox" class="form-check-input" id="activo" name="activo" checked>
                        <label class="form-check-label" for="activo">Cliente Activo</label>
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

<!-- Added toggle confirmation modal -->
<div class="modal fade" id="toggleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="cliente_id" id="toggleClienteId">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">Confirmar Cambio de Estado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea <strong id="toggleAction"></strong> al cliente <strong id="toggleClienteName"></strong>?</p>
                    <p class="text-muted"><small>Esta acción también cambiará el estado del usuario asociado.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Confirmar</button>
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
                <input type="hidden" name="cliente_id" id="deleteClienteId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar al cliente <strong id="deleteClienteName"></strong>?</p>
                    <!-- Updated warning message to mention user deletion -->
                    <p class="text-danger"><strong>⚠️ ADVERTENCIA:</strong> Esta acción también eliminará permanentemente el usuario tipo "Cliente" asociado a este cliente.</p>
                    <p class="text-muted"><small>Esta acción no se puede deshacer.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar Permanentemente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function resetForm() {
        document.getElementById('clienteForm').reset();
        document.getElementById('formAction').value = 'create';
        document.getElementById('clienteId').value = '';
        document.getElementById('modalTitle').textContent = 'Nuevo Cliente';
        document.getElementById('password').required = true;
        document.getElementById('passwordField').style.display = 'block';
        document.getElementById('activoField').style.display = 'none';

        // Show contacto_principal field for new clients
        document.getElementById('contacto_principal').required = true;
        document.getElementById('contacto_principal').closest('.col-md-6').style.display = 'block';
    }

    function editCliente(cliente) {
        document.getElementById('formAction').value = 'update';
        document.getElementById('clienteId').value = cliente.id;
        document.getElementById('nombre_empresa').value = cliente.nombre_empresa;
        document.getElementById('contacto_email').value = cliente.contacto_email;
        document.getElementById('telefono').value = cliente.telefono || '';
        document.getElementById('activo').checked = cliente.activo;
        document.getElementById('modalTitle').textContent = 'Editar Cliente';
        document.getElementById('passwordField').style.display = 'none';
        document.getElementById('activoField').style.display = 'block';

        // Hide contacto_principal field when editing since it's not in the database
        document.getElementById('contacto_principal').value = '';
        document.getElementById('contacto_principal').required = false;
        document.getElementById('contacto_principal').closest('.col-md-6').style.display = 'none';

        new bootstrap.Modal(document.getElementById('clienteModal')).show();
    }

    function toggleCliente(clienteId, clienteName, isActive) {
        document.getElementById('toggleClienteId').value = clienteId;
        document.getElementById('toggleClienteName').textContent = clienteName;
        document.getElementById('toggleAction').textContent = isActive ? 'deshabilitar' : 'habilitar';
        new bootstrap.Modal(document.getElementById('toggleModal')).show();
    }

    function deleteCliente(clienteId, clienteName) {
        document.getElementById('deleteClienteId').value = clienteId;
        document.getElementById('deleteClienteName').textContent = clienteName;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>