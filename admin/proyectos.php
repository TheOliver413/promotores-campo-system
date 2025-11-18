<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/Proyecto.php';
require_once __DIR__ . '/../db/Auditoria.php';

requireRole(['Administrador']);

$proyectoModel = new Proyecto();
$auditoriaModel = new Auditoria();
$db = Database::getInstance()->getConnection();

// Handle CRUD operations BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $kpis = [
            'visitas_meta' => intval($_POST['visitas_meta'] ?? 0),
            'fotos_por_visita' => intval($_POST['fotos_por_visita'] ?? 0),
            'ventas_minimas_usd' => floatval($_POST['ventas_minimas_usd'] ?? 0),
            'cobertura_tiendas_porcentaje' => intval($_POST['cobertura_tiendas_porcentaje'] ?? 0)
        ];

        $configuraciones = [
            'checkin_foto_obligatoria' => isset($_POST['checkin_foto_obligatoria']),
            'tolerancia_geocerca_metros' => intval($_POST['tolerancia_geocerca_metros'] ?? 50),
            'permitir_checkin_findesemana' => isset($_POST['permitir_checkin_findesemana']),
            'max_evidencias_por_actividad' => intval($_POST['max_evidencias_por_actividad'] ?? 5)
        ];

        $proyectoId = $proyectoModel->create([
            'nombre_proyecto' => $_POST['nombre_proyecto'],
            'descripcion' => $_POST['descripcion'] ?? null,
            'fecha_inicio' => $_POST['fecha_inicio'],
            'fecha_fin' => $_POST['fecha_fin'] ?? null,
            'kpis' => $kpis,
            'configuraciones' => $configuraciones,
            'estado' => 'activo'
        ]);

        if ($proyectoId) {
            // Assign clients
            if (!empty($_POST['clientes'])) {
                $stmt = $db->prepare("INSERT INTO proyecto_clientes (proyecto_id, cliente_id) VALUES (?, ?)");
                foreach ($_POST['clientes'] as $clienteId) {
                    $stmt->execute([$proyectoId, $clienteId]);
                }
            }

            // Assign promoters
            if (!empty($_POST['promotores'])) {
                $stmt = $db->prepare("INSERT INTO proyecto_promotores (proyecto_id, promotor_user_id) VALUES (?, ?)");
                foreach ($_POST['promotores'] as $promotorId) {
                    $stmt->execute([$proyectoId, $promotorId]);
                }
            }

            $auditoriaModel->registrar(getUserId(), 'CREATE', 'proyectos', $proyectoId);
            $_SESSION['success'] = 'Proyecto creado exitosamente';
        }
    } elseif ($action === 'update') {
        $proyectoId = $_POST['proyecto_id'];

        $kpis = [
            'visitas_meta' => intval($_POST['visitas_meta'] ?? 0),
            'fotos_por_visita' => intval($_POST['fotos_por_visita'] ?? 0),
            'ventas_minimas_usd' => floatval($_POST['ventas_minimas_usd'] ?? 0),
            'cobertura_tiendas_porcentaje' => intval($_POST['cobertura_tiendas_porcentaje'] ?? 0)
        ];

        $configuraciones = [
            'checkin_foto_obligatoria' => isset($_POST['checkin_foto_obligatoria']),
            'tolerancia_geocerca_metros' => intval($_POST['tolerancia_geocerca_metros'] ?? 50),
            'permitir_checkin_findesemana' => isset($_POST['permitir_checkin_findesemana']),
            'max_evidencias_por_actividad' => intval($_POST['max_evidencias_por_actividad'] ?? 5)
        ];

        if ($proyectoModel->update($proyectoId, [
            'nombre_proyecto' => $_POST['nombre_proyecto'],
            'descripcion' => $_POST['descripcion'] ?? null,
            'fecha_inicio' => $_POST['fecha_inicio'],
            'fecha_fin' => $_POST['fecha_fin'] ?? null,
            'kpis' => $kpis,
            'configuraciones' => $configuraciones,
            'estado' => $_POST['estado']
        ])) {
            // Update clients
            $db->prepare("DELETE FROM proyecto_clientes WHERE proyecto_id = ?")->execute([$proyectoId]);
            if (!empty($_POST['clientes'])) {
                $stmt = $db->prepare("INSERT INTO proyecto_clientes (proyecto_id, cliente_id) VALUES (?, ?)");
                foreach ($_POST['clientes'] as $clienteId) {
                    $stmt->execute([$proyectoId, $clienteId]);
                }
            }

            // Update promoters
            $db->prepare("DELETE FROM proyecto_promotores WHERE proyecto_id = ?")->execute([$proyectoId]);
            if (!empty($_POST['promotores'])) {
                $stmt = $db->prepare("INSERT INTO proyecto_promotores (proyecto_id, promotor_user_id) VALUES (?, ?)");
                foreach ($_POST['promotores'] as $promotorId) {
                    $stmt->execute([$proyectoId, $promotorId]);
                }
            }

            $auditoriaModel->registrar(getUserId(), 'UPDATE', 'proyectos', $proyectoId);
            $_SESSION['success'] = 'Proyecto actualizado exitosamente';
        }
    } elseif ($action === 'delete') {
        $proyectoId = $_POST['proyecto_id'];
        if ($proyectoModel->delete($proyectoId)) {
            $auditoriaModel->registrar(getUserId(), 'DELETE', 'proyectos', $proyectoId);
            $_SESSION['success'] = 'Proyecto eliminado exitosamente';
        }
    }

    header('Location: /promotores-campo-system/admin/proyectos.php');
    exit();
}

$pageTitle = 'Gestión de Proyectos';
require_once __DIR__ . '/../includes/header.php';

// Get all projects with assignments
$proyectos = $proyectoModel->getAll();

// Get clients and promoters for dropdowns
$clientesStmt = $db->query("SELECT id, nombre_empresa FROM clientes WHERE activo = true");
$clientes = $clientesStmt->fetchAll();

$promotoresStmt = $db->query("SELECT u.id, u.nombre_completo FROM usuarios u JOIN roles r ON u.role_id = r.id WHERE r.nombre = 'Promotor' AND u.estado = 'activo'");
$promotores = $promotoresStmt->fetchAll();
?>
<style>
    /* Estilos base para las pestañas */
    .nav-tabs .nav-link {
        color: #212529 !important;
        /* Texto negro por defecto */
        font-weight: 500;
        border: none;
        border-bottom: 3px solid transparent;
        background-color: transparent;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Hover (al pasar el mouse) */
    .nav-tabs .nav-link:hover {
        color: #0d6efd !important;
        /* Azul Bootstrap */
        background-color: rgba(13, 110, 253, 0.08);
        border-color: rgba(13, 110, 253, 0.3);
    }

    /* Activa (seleccionada) */
    .nav-tabs .nav-link.active {
        color: #0d6efd !important;
        background-color: rgba(13, 110, 253, 0.12);
        border-bottom: 3px solid #0d6efd;
        font-weight: 600;
    }

    /* Íconos */
    .nav-tabs .nav-link i {
        font-size: 1.1rem;
        color: inherit;
        /* Toma el color del texto del enlace */
        transition: color 0.3s ease;
    }

    .nav-tabs .nav-link.active i {
        color: #0d6efd;
    }

    /* Espaciado */
    .nav-tabs .nav-item {
        margin-right: 6px;
    }

    /* 🔥 Compatibilidad con modo oscuro */
    body.bg-dark .nav-tabs .nav-link {
        color: #f8f9fa !important;
        /* Blanco para fondo oscuro */
    }

    body.bg-dark .nav-tabs .nav-link.active {
        color: #0d6efd !important;
        background-color: rgba(13, 110, 253, 0.2);
        border-bottom-color: #0d6efd;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Gestión de Proyectos</h1>
            <p class="text-muted">Administra los proyectos, KPIs y configuraciones</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#proyectoModal" onclick="resetForm()">
                <i class="bi bi-plus-circle"></i> Nuevo Proyecto
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

    <!-- Added empty state when no projects exist -->
    <?php if (empty($proyectos)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-folder-x" style="font-size: 4rem; color: #dee2e6;"></i>
                <h4 class="text-muted mt-3">No hay proyectos registrados</h4>
                <p class="text-muted mb-4">Crea el primer proyecto para comenzar a gestionar tus actividades</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#proyectoModal" onclick="resetForm()">
                    <i class="bi bi-plus-circle"></i> Crear Primer Proyecto
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($proyectos as $proyecto): ?>
                <?php
                $kpis = json_decode($proyecto['kpis'] ?? '{}', true);
                if (!is_array($kpis)) $kpis = [];

                $configuraciones = json_decode($proyecto['configuraciones'] ?? '{}', true);
                if (!is_array($configuraciones)) $configuraciones = [];
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?></h5>
                            <small><?php echo $proyecto['estado'] === 'activo' ? 'Activo' : 'Inactivo'; ?></small>
                        </div>
                        <div class="card-body">
                            <p class="text-muted"><?php echo htmlspecialchars($proyecto['descripcion'] ?? 'Sin descripción'); ?></p>

                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i>
                                    <?php echo date('d/m/Y', strtotime($proyecto['fecha_inicio'])); ?>
                                    <?php if ($proyecto['fecha_fin']): ?>
                                        - <?php echo date('d/m/Y', strtotime($proyecto['fecha_fin'])); ?>
                                    <?php endif; ?>
                                </small>
                            </div>

                            <?php
                            // Get assigned clients
                            $stmt = $db->prepare("SELECT c.nombre_empresa FROM clientes c JOIN proyecto_clientes pc ON c.id = pc.cliente_id WHERE pc.proyecto_id = ?");
                            $stmt->execute([$proyecto['id']]);
                            $clientesAsignados = $stmt->fetchAll();

                            // Get assigned promoters
                            $stmt = $db->prepare("SELECT u.nombre_completo FROM usuarios u JOIN proyecto_promotores pp ON u.id = pp.promotor_user_id WHERE pp.proyecto_id = ?");
                            $stmt->execute([$proyecto['id']]);
                            $promotoresAsignados = $stmt->fetchAll();
                            ?>

                            <div class="mt-3">
                                <h6 class="small">Clientes: <span class="badge bg-info"><?php echo count($clientesAsignados); ?></span></h6>
                                <h6 class="small">Promotores: <span class="badge bg-success"><?php echo count($promotoresAsignados); ?></span></h6>
                            </div>

                            <!-- Display KPIs summary -->
                            <?php if (!empty($kpis) && array_filter($kpis)): ?>
                                <div class="mt-3 pt-3 border-top">
                                    <h6 class="small text-muted mb-2"><i class="bi bi-graph-up"></i> KPIs Configurados</h6>
                                    <?php if (!empty($kpis['visitas_meta'])): ?>
                                        <small class="d-block">Meta Visitas: <strong><?php echo $kpis['visitas_meta']; ?></strong></small>
                                    <?php endif; ?>
                                    <?php if (!empty($kpis['cobertura_tiendas_porcentaje'])): ?>
                                        <small class="d-block">Cobertura: <strong><?php echo $kpis['cobertura_tiendas_porcentaje']; ?>%</strong></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-sm btn-outline-primary" onclick='editProyecto(<?php echo json_encode($proyecto); ?>)'>
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProyecto(<?php echo $proyecto['id']; ?>, '<?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?>')">
                                <i class="bi bi-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Proyecto Modal -->
<div class="modal fade" id="proyectoModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="proyectoForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Proyecto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="proyecto_id" id="proyectoId">

                    <!-- Added tabs for better organization -->
                    <ul class="nav nav-tabs mb-3" id="proyectoTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                                <i class="bi bi-info-circle"></i> General
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="kpis-tab" data-bs-toggle="tab" data-bs-target="#kpis" type="button">
                                <i class="bi bi-graph-up"></i> KPIs
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="config-tab" data-bs-toggle="tab" data-bs-target="#config" type="button">
                                <i class="bi bi-gear"></i> Configuraciones
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="asignaciones-tab" data-bs-toggle="tab" data-bs-target="#asignaciones" type="button">
                                <i class="bi bi-people"></i> Asignaciones
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="proyectoTabContent">
                        <!-- General Tab -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="nombre_proyecto" class="form-label">Nombre del Proyecto *</label>
                                    <input type="text" class="form-control" id="nombre_proyecto" name="nombre_proyecto" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="estado" class="form-label">Estado *</label>
                                    <select class="form-select" id="estado" name="estado" required>
                                        <option value="activo">Activo</option>
                                        <option value="inactivo">Inactivo</option>
                                        <option value="completado">Completado</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_inicio" class="form-label">Fecha Inicio *</label>
                                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin">
                                </div>
                            </div>
                        </div>

                        <!-- KPIs Tab - New section for project goals -->
                        <div class="tab-pane fade" id="kpis" role="tabpanel">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Define las métricas de éxito del proyecto. Estos valores se usarán en los reportes para medir el progreso.
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="visitas_meta" class="form-label">Meta de Visitas</label>
                                    <input type="number" class="form-control" id="visitas_meta" name="visitas_meta" min="0" placeholder="Ej: 200">
                                    <small class="text-muted">Número total de visitas esperadas</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="fotos_por_visita" class="form-label">Fotos por Visita</label>
                                    <input type="number" class="form-control" id="fotos_por_visita" name="fotos_por_visita" min="0" placeholder="Ej: 3">
                                    <small class="text-muted">Mínimo de fotos requeridas por visita</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="ventas_minimas_usd" class="form-label">Ventas Mínimas (USD)</label>
                                    <input type="number" class="form-control" id="ventas_minimas_usd" name="ventas_minimas_usd" min="0" step="0.01" placeholder="Ej: 5000">
                                    <small class="text-muted">Meta de ventas en dólares</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="cobertura_tiendas_porcentaje" class="form-label">Cobertura de Tiendas (%)</label>
                                    <input type="number" class="form-control" id="cobertura_tiendas_porcentaje" name="cobertura_tiendas_porcentaje" min="0" max="100" placeholder="Ej: 90">
                                    <small class="text-muted">Porcentaje de tiendas a cubrir</small>
                                </div>
                            </div>
                        </div>

                        <!-- Configuraciones Tab - New section for project rules -->
                        <div class="tab-pane fade" id="config" role="tabpanel">
                            <div class="alert alert-warning">
                                <i class="bi bi-gear"></i> Define las reglas operativas específicas de este proyecto. Estas configuraciones sobrescriben las globales.
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tolerancia_geocerca_metros" class="form-label">Tolerancia de Geocerca (metros)</label>
                                    <input type="number" class="form-control" id="tolerancia_geocerca_metros" name="tolerancia_geocerca_metros" min="0" value="50" placeholder="50">
                                    <small class="text-muted">Distancia máxima permitida desde el punto de trabajo</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="max_evidencias_por_actividad" class="form-label">Máximo de Evidencias por Actividad</label>
                                    <input type="number" class="form-control" id="max_evidencias_por_actividad" name="max_evidencias_por_actividad" min="1" value="5" placeholder="5">
                                    <small class="text-muted">Número máximo de archivos por actividad</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="checkin_foto_obligatoria" name="checkin_foto_obligatoria">
                                        <label class="form-check-label" for="checkin_foto_obligatoria">
                                            Foto de Check-in Obligatoria
                                        </label>
                                    </div>
                                    <small class="text-muted">Requiere foto al hacer check-in</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="permitir_checkin_findesemana" name="permitir_checkin_findesemana">
                                        <label class="form-check-label" for="permitir_checkin_findesemana">
                                            Permitir Check-in en Fin de Semana
                                        </label>
                                    </div>
                                    <small class="text-muted">Habilita trabajo sábados y domingos</small>
                                </div>
                            </div>
                        </div>

                        <!-- Asignaciones Tab -->
                        <div class="tab-pane fade" id="asignaciones" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="clientes" class="form-label">Clientes Asignados</label>
                                    <select class="form-select" id="clientes" name="clientes[]" multiple size="5">
                                        <?php foreach ($clientes as $cliente): ?>
                                            <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nombre_empresa']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Mantén Ctrl/Cmd para seleccionar múltiples</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="promotores" class="form-label">Promotores Asignados</label>
                                    <select class="form-select" id="promotores" name="promotores[]" multiple size="5">
                                        <?php foreach ($promotores as $promotor): ?>
                                            <option value="<?php echo $promotor['id']; ?>"><?php echo htmlspecialchars($promotor['nombre_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Mantén Ctrl/Cmd para seleccionar múltiples</small>
                                </div>
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

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="proyecto_id" id="deleteProyectoId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el proyecto <strong id="deleteProyectoName"></strong>?</p>
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
    function resetForm() {
        document.getElementById('proyectoForm').reset();
        document.getElementById('formAction').value = 'create';
        document.getElementById('proyectoId').value = '';
        document.getElementById('modalTitle').textContent = 'Nuevo Proyecto';
        document.getElementById('estado').value = 'activo';

        // Reset KPIs fields
        document.getElementById('visitas_meta').value = '';
        document.getElementById('fotos_por_visita').value = '';
        document.getElementById('ventas_minimas_usd').value = '';
        document.getElementById('cobertura_tiendas_porcentaje').value = '';

        // Reset configuraciones fields
        document.getElementById('checkin_foto_obligatoria').checked = false;
        document.getElementById('tolerancia_geocerca_metros').value = '50';
        document.getElementById('permitir_checkin_findesemana').checked = false;
        document.getElementById('max_evidencias_por_actividad').value = '5';

        // Clear selections
        document.getElementById('clientes').selectedIndex = -1;
        document.getElementById('promotores').selectedIndex = -1;

        // Return to first tab
        document.getElementById('general-tab').click();
    }

    async function editProyecto(proyecto) {
        console.log('[v0] Editing project:', proyecto);

        document.getElementById('formAction').value = 'update';
        document.getElementById('proyectoId').value = proyecto.id;
        document.getElementById('nombre_proyecto').value = proyecto.nombre_proyecto;
        document.getElementById('descripcion').value = proyecto.descripcion || '';
        document.getElementById('fecha_inicio').value = proyecto.fecha_inicio;
        document.getElementById('fecha_fin').value = proyecto.fecha_fin || '';
        document.getElementById('estado').value = proyecto.estado;
        document.getElementById('modalTitle').textContent = 'Editar Proyecto';

        try {
            const kpis = typeof proyecto.kpis === 'string' ? JSON.parse(proyecto.kpis || '{}') : (proyecto.kpis || {});
            document.getElementById('visitas_meta').value = kpis.visitas_meta || '';
            document.getElementById('fotos_por_visita').value = kpis.fotos_por_visita || '';
            document.getElementById('ventas_minimas_usd').value = kpis.ventas_minimas_usd || '';
            document.getElementById('cobertura_tiendas_porcentaje').value = kpis.cobertura_tiendas_porcentaje || '';
        } catch (e) {
            console.error('[v0] Error parsing KPIs:', e);
        }

        try {
            const config = typeof proyecto.configuraciones === 'string' ? JSON.parse(proyecto.configuraciones || '{}') : (proyecto.configuraciones || {});
            document.getElementById('checkin_foto_obligatoria').checked = config.checkin_foto_obligatoria || false;
            document.getElementById('tolerancia_geocerca_metros').value = config.tolerancia_geocerca_metros || 50;
            document.getElementById('permitir_checkin_findesemana').checked = config.permitir_checkin_findesemana || false;
            document.getElementById('max_evidencias_por_actividad').value = config.max_evidencias_por_actividad || 5;
        } catch (e) {
            console.error('[v0] Error parsing configuraciones:', e);
        }

        // Load assigned clients and promoters
        try {
            const response = await fetch(`../api/proyecto_asignaciones.php?proyecto_id=${proyecto.id}`);
            const data = await response.json();

            // Select assigned clients
            Array.from(document.getElementById('clientes').options).forEach(option => {
                option.selected = data.clientes.includes(parseInt(option.value));
            });

            // Select assigned promoters
            Array.from(document.getElementById('promotores').options).forEach(option => {
                option.selected = data.promotores.includes(parseInt(option.value));
            });
        } catch (e) {
            console.error('[v0] Error loading assignments:', e);
        }

        new bootstrap.Modal(document.getElementById('proyectoModal')).show();
    }

    function deleteProyecto(proyectoId, proyectoName) {
        document.getElementById('deleteProyectoId').value = proyectoId;
        document.getElementById('deleteProyectoName').textContent = proyectoName;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>