<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/User.php';
require_once '../db/Proyecto.php';
require_once '../db/Auditoria.php';

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$proyectoModel = new Proyecto();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$filtroNombre = $_GET['filtro_nombre'] ?? '';
$filtroEstado = $_GET['filtro_estado'] ?? '';

// Build WHERE clause
$whereConditions = ["sp.supervisor_id = ?"];
$params = [$_SESSION['user_id']];

if ($filtroNombre) {
    $whereConditions[] = "u.nombre_completo LIKE ?";
    $params[] = "%{$filtroNombre}%";
}

if ($filtroEstado) {
    $whereConditions[] = "u.estado = ?";
    $params[] = $filtroEstado;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$stmtCount = $db->prepare("
    SELECT COUNT(DISTINCT u.id) as total
    FROM usuarios u
    INNER JOIN supervisor_promotores sp ON u.id = sp.promotor_id
    WHERE {$whereClause}
");
$stmtCount->execute($params);
$totalPromotores = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalPromotores / $perPage);

// Get paginated promotores
$promotores = $userModel->getPromotoresBySupervisor($_SESSION['user_id'], $perPage, $offset, $filtroNombre, $filtroEstado);
$proyectos = $proyectoModel->getAll();

$pageTitle = 'Gestión de Promotores';
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Gestión de Promotores</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPromotor">
                    <i class="bi bi-plus-circle"></i> Nuevo Promotor
                </button>
            </div>
        </div>
    </div>

    <!-- Added filters section -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Buscar por Nombre</label>
                            <input type="text" class="form-control" name="filtro_nombre"
                                value="<?= htmlspecialchars($filtroNombre) ?>"
                                placeholder="Nombre del promotor...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="filtro_estado">
                                <option value="">Todos</option>
                                <option value="activo" <?= $filtroEstado === 'activo' ? 'selected' : '' ?>>Activo</option>
                                <option value="inactivo" <?= $filtroEstado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                                <a href="promotores.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaPromotores">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre Completo</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Clientes</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($promotores)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No hay promotores registrados</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($promotores as $promotor): ?>
                                        <tr>
                                            <td><?= $promotor['id'] ?></td>
                                            <td><?= htmlspecialchars($promotor['nombre_completo']) ?></td>
                                            <td><?= htmlspecialchars($promotor['email']) ?></td>
                                            <td><?= htmlspecialchars($promotor['telefono'] ?? 'N/A') ?></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($promotor['clientes_nombres'] ?? 'Sin asignar') ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $promotor['estado'] === 'activo' ? 'success' : 'danger' ?>">
                                                    <?= ucfirst($promotor['estado']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editarPromotor(<?= $promotor['id'] ?>)" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-primary" onclick="asignarProyectos(<?= $promotor['id'] ?>)" title="Asignar Proyectos">
                                                    <i class="bi bi-briefcase"></i>
                                                </button>
                                                <button class="btn btn-sm btn-<?= $promotor['estado'] === 'activo' ? 'danger' : 'success' ?>"
                                                    onclick="toggleEstado(<?= $promotor['id'] ?>, '<?= $promotor['estado'] === 'activo' ? 'inactivo' : 'activo' ?>')"
                                                    title="<?= $promotor['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="bi bi-<?= $promotor['estado'] === 'activo' ? 'x-circle' : 'check-circle' ?>"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" onclick="verProyectos(<?= $promotor['id'] ?>)" title="Ver Proyectos">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Added pagination controls -->
                    <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <span>Mostrando <?= min($offset + 1, $totalPromotores) ?>-<?= min($offset + $perPage, $totalPromotores) ?> de <?= $totalPromotores ?></span>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>&filtro_nombre=<?= urlencode($filtroNombre) ?>&filtro_estado=<?= urlencode($filtroEstado) ?>">Anterior</a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>&filtro_nombre=<?= urlencode($filtroNombre) ?>&filtro_estado=<?= urlencode($filtroEstado) ?>"><?= $i ?></a>
                                            </li>
                                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>&filtro_nombre=<?= urlencode($filtroNombre) ?>&filtro_estado=<?= urlencode($filtroEstado) ?>">Siguiente</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear/Editar Promotor -->
<div class="modal fade" id="modalPromotor" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPromotorTitle">Nuevo Promotor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPromotor">
                <div class="modal-body">
                    <input type="hidden" id="promotor_id" name="promotor_id">

                    <div class="mb-3">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono">
                    </div>

                    <div class="mb-3" id="passwordGroup">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small class="text-muted">Dejar en blanco para mantener la contraseña actual</small>
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

<!-- Modal Asignar Proyectos -->
<div class="modal fade" id="modalAsignarProyectos" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asignar Proyectos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAsignarProyectos">
                <div class="modal-body">
                    <input type="hidden" id="asignar_promotor_id" name="promotor_id">

                    <div class="mb-3">
                        <label class="form-label">Seleccionar Proyectos</label>
                        <select class="form-select" id="proyectos_asignar" name="proyectos[]" multiple size="8">
                            <?php foreach ($proyectos as $proyecto): ?>
                                <option value="<?= $proyecto['id'] ?>">
                                    <?= htmlspecialchars($proyecto['nombre_proyecto']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Mantén presionado Ctrl/Cmd para seleccionar múltiples</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Asignar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver Proyectos Detalle -->
<div class="modal fade" id="modalVerProyectos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Proyectos y Clientes Asignados</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="contenidoProyectos">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelector('[data-bs-target="#modalPromotor"]').addEventListener('click', function() {
        resetFormPromotor();
    });

    document.getElementById('modalPromotor').addEventListener('hidden.bs.modal', function() {
        resetFormPromotor();
    });

    function resetFormPromotor() {
        document.getElementById('formPromotor').reset();
        document.getElementById('promotor_id').value = '';
        document.getElementById('password').required = true;
        document.getElementById('modalPromotorTitle').textContent = 'Nuevo Promotor';
    }

    // Crear/Editar Promotor
    document.getElementById('formPromotor').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);

        const promotorId = document.getElementById('promotor_id').value;
        formData.append('action', promotorId ? 'update' : 'create');

        try {
            const response = await fetch('../api/promotor_crud.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('[v0] Error:', error);
            alert('Error al guardar promotor');
        }
    });

    // Editar Promotor
    async function editarPromotor(id) {
        try {
            const response = await fetch(`../api/promotor_crud.php?action=get&id=${id}`);

            if (!response.ok) {
                throw new Error('Error al cargar promotor');
            }

            const promotor = await response.json();

            if (promotor.success === false) {
                throw new Error(promotor.message);
            }

            document.getElementById('promotor_id').value = promotor.id;
            document.getElementById('nombre_completo').value = promotor.nombre_completo;
            document.getElementById('email').value = promotor.email;
            document.getElementById('telefono').value = promotor.telefono || '';
            document.getElementById('password').required = false;
            document.getElementById('modalPromotorTitle').textContent = 'Editar Promotor';

            new bootstrap.Modal(document.getElementById('modalPromotor')).show();
        } catch (error) {
            console.error('[v0] Error:', error);
            alert('Error al cargar promotor: ' + error.message);
        }
    }

    // Asignar Proyectos
    function asignarProyectos(promotorId) {
        document.getElementById('asignar_promotor_id').value = promotorId;

        // Cargar proyectos actuales
        fetch(`../api/promotor_crud.php?action=proyectos&id=${promotorId}`)
            .then(r => r.json())
            .then(proyectos => {
                const select = document.getElementById('proyectos_asignar');
                const proyectosIds = Array.isArray(proyectos) ? proyectos.map(p => parseInt(p.id)) : [];

                Array.from(select.options).forEach(option => {
                    option.selected = proyectosIds.includes(parseInt(option.value));
                });
            })
            .catch(error => {
                console.error('[v0] Error al cargar proyectos:', error);
                alert('Error al cargar proyectos asignados');
            });

        new bootstrap.Modal(document.getElementById('modalAsignarProyectos')).show();
    }

    // Guardar asignación de proyectos
    document.getElementById('formAsignarProyectos').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.append('action', 'asignar_proyectos');

        try {
            const response = await fetch('../api/promotor_crud.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert('Proyectos asignados exitosamente');
                bootstrap.Modal.getInstance(document.getElementById('modalAsignarProyectos')).hide();
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error al asignar proyectos');
        }
    });

    // Toggle Estado
    async function toggleEstado(id, nuevoEstado) {
        if (!confirm('¿Está seguro de cambiar el estado del promotor?')) return;

        const formData = new FormData();
        formData.append('action', 'toggle_estado');
        formData.append('promotor_id', id);
        formData.append('estado', nuevoEstado);

        try {
            const response = await fetch('../api/promotor_crud.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error al cambiar estado');
        }
    }

    async function verProyectos(id) {
        try {
            const modal = new bootstrap.Modal(document.getElementById('modalVerProyectos'));
            modal.show();

            const contenedor = document.getElementById('contenidoProyectos');
            contenedor.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            `;

            console.log('[v0] Fetching projects for promotor ID:', id);
            const response = await fetch(`../api/promotor_crud.php?action=proyectos_detalle&id=${id}`);
            console.log('[v0] Response status:', response.status);

            const text = await response.text();
            console.log('[v0] Raw response:', text);

            let proyectos;
            try {
                proyectos = JSON.parse(text);
            } catch (e) {
                console.error('[v0] JSON parse error:', e);
                contenedor.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> Error al procesar la respuesta del servidor
                    </div>
                `;
                return;
            }

            console.log('[v0] Proyectos parsed:', proyectos);
            console.log('[v0] Is array?', Array.isArray(proyectos));

            if (proyectos.success === false) {
                contenedor.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> ${proyectos.message || 'Error al cargar proyectos'}
                    </div>
                `;
                return;
            }

            if (!Array.isArray(proyectos) || proyectos.length === 0) {
                contenedor.innerHTML = `
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Este promotor no tiene proyectos asignados
                    </div>
                `;
                return;
            }

            let html = '';
            proyectos.forEach((proyecto, index) => {
                const estadoBadge = proyecto.estado === 'activo' ? 'success' :
                    proyecto.estado === 'completado' ? 'primary' : 'secondary';

                html += `
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="bi bi-briefcase"></i> ${proyecto.nombre_proyecto}
                                </h6>
                                <span class="badge bg-${estadoBadge}">${proyecto.estado}</span>
                            </div>
                        </div>
                        <div class="card-body">
                            ${proyecto.descripcion ? `<p class="text-muted mb-2">${proyecto.descripcion}</p>` : ''}
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <small class="text-muted">Fecha Inicio:</small>
                                    <div>${proyecto.fecha_inicio || 'No especificada'}</div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">Fecha Fin:</small>
                                    <div>${proyecto.fecha_fin || 'No especificada'}</div>
                                </div>
                            </div>

                            <h6 class="mb-2"><i class="bi bi-people"></i> Clientes Asignados:</h6>
                            ${proyecto.clientes && proyecto.clientes.length > 0 ? `
                                <div class="list-group">
                                    ${proyecto.clientes.map(cliente => `
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">${cliente.nombre_empresa}</h6>
                                                    ${cliente.contacto_principal ? `
                                                        <small class="text-muted">
                                                            <i class="bi bi-person"></i> ${cliente.contacto_principal}
                                                        </small>
                                                    ` : ''}
                                                    ${cliente.telefono ? `
                                                        <small class="text-muted ms-2">
                                                            <i class="bi bi-telephone"></i> ${cliente.telefono}
                                                        </small>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : `
                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    No hay clientes asignados a este proyecto
                                </div>
                            `}
                        </div>
                    </div>
                `;
            });

            contenedor.innerHTML = html;
        } catch (error) {
            console.error('[v0] Error al cargar proyectos:', error);
            document.getElementById('contenidoProyectos').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> Error al cargar proyectos: ${error.message}
                </div>
            `;
        }
    }
</script>

<?php include '../includes/footer.php'; ?>