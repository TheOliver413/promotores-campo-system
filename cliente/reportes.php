<?php
$pageTitle = 'Reportes';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/ReporteMensual.php';
require_once __DIR__ . '/../db/Proyecto.php';

requireRole(['Cliente']);

$userId = getUserId();
$proyectoModel = new Proyecto();

$proyectos = $proyectoModel->getByCliente($userId);
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Reportes Mensuales</h2>
                    <p class="text-muted mb-0">
                        <i class="bi bi-calendar-check me-2"></i>
                        <?php echo strftime('%A, %d de %B de %Y'); ?>
                    </p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Actualizar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Updated filter card to match dashboard aesthetic -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-funnel me-2 text-primary"></i>
                        Filtros de Búsqueda
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-briefcase me-2"></i>Proyecto
                            </label>
                            <select class="form-select" id="filtroProyecto" onchange="cargarReportes()">
                                <option value="">Todos los proyectos</option>
                                <?php foreach ($proyectos as $proyecto): ?>
                                    <option value="<?= $proyecto['id'] ?>">
                                        <?= htmlspecialchars($proyecto['nombre_proyecto']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-calendar-month me-2"></i>Mes
                            </label>
                            <input type="month" class="form-control" id="filtroMes" onchange="cargarReportes()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-calendar-range me-2"></i>Año
                            </label>
                            <input type="number" class="form-control" id="filtroAnio" value="<?= date('Y') ?>" onchange="cargarReportes()">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-success w-100" onclick="exportarReportes()">
                                <i class="bi bi-download"></i> Exportar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Updated results container to match dashboard styling -->
    <div class="row" id="listaReportes">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    </div>
</div>

<script>
    function cargarReportes() {
        const proyecto = document.getElementById('filtroProyecto').value;
        const mes = document.getElementById('filtroMes').value;
        const anio = document.getElementById('filtroAnio').value;

        const mesNumero = mes ? mes.split('-')[1] : '';

        const params = new URLSearchParams({
            action: 'list',
            proyecto_id: proyecto,
            mes: mesNumero,
            anio: anio
        });

        fetch(`../api/reporte_crud.php?${params}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('La respuesta del servidor no es JSON válido. Por favor, revise los logs del servidor.');
                }

                return response.json();
            })
            .then(data => {
                const container = document.getElementById('listaReportes');
                if (data.success && data.reportes.length > 0) {
                    container.innerHTML = data.reportes.map(r => `
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 py-3">
                                <h6 class="mb-0 fw-bold">${r.proyecto_nombre}</h6>
                                <small class="text-muted">
                                    <i class="bi bi-calendar me-1"></i>
                                    ${r.mes}/${r.anio}
                                </small>
                            </div>
                            <div class="card-body">
                                <div class="row text-center g-3">
                                    <div class="col-6">
                                        <div class="bg-primary bg-opacity-10 rounded p-3">
                                            <h3 class="text-primary mb-1 fw-bold">${r.total_jornadas}</h3>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-calendar-check me-1"></i>
                                                Jornadas
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-success bg-opacity-10 rounded p-3">
                                            <h3 class="text-success mb-1 fw-bold">${r.total_actividades}</h3>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-list-check me-1"></i>
                                                Actividades
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-info bg-opacity-10 rounded p-3">
                                            <h3 class="text-info mb-1 fw-bold">${parseFloat(r.horas_trabajadas).toFixed(2)}h</h3>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-clock me-1"></i>
                                                Horas
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-warning bg-opacity-10 rounded p-3">
                                            <h3 class="text-warning mb-1 fw-bold">${parseFloat(r.cumplimiento_ruta || 0).toFixed(0)}%</h3>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-award me-1"></i>
                                                Cumplimiento
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-0 py-3">
                                <button class="btn btn-outline-primary w-100" onclick="verDetalleReporte('${r.proyecto_id}_${r.mes}_${r.anio}')">
                                    <i class="bi bi-eye me-2"></i> Ver Detalle
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
                } else if (data.success && data.reportes.length === 0) {
                    container.innerHTML = `
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center py-5">
                                <div class="bg-light rounded-circle d-inline-flex p-4 mb-3">
                                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                </div>
                                <h5 class="text-muted mb-2">No hay reportes disponibles</h5>
                                <p class="text-muted mb-0">
                                    No se encontraron reportes con los filtros seleccionados.
                                </p>
                                <p class="text-muted small mt-2">
                                    Intente ajustar los filtros o seleccionar otro período.
                                </p>
                            </div>
                        </div>
                    </div>
                `;
                } else {
                    throw new Error(data.message || 'Error desconocido al cargar reportes');
                }
            })
            .catch(error => {
                console.error('[v0] Error loading reports:', error);
                const container = document.getElementById('listaReportes');

                container.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger border-0 shadow-sm">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 2rem;"></i>
                                <div>
                                    <h5 class="mb-1">Error al Cargar los Reportes</h5>
                                    <p class="mb-0 small">No se pudieron cargar los datos del servidor</p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Posibles causas:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>No tiene proyectos asignados o permisos para verlos</li>
                                    <li>Error de conexión con la base de datos</li>
                                    <li>El servidor está temporalmente no disponible</li>
                                    <li>Sesión expirada - intente recargar la página</li>
                                </ul>
                            </div>
                            
                            <div class="bg-white p-3 rounded">
                                <small class="text-muted d-block mb-1"><strong>Error técnico:</strong></small>
                                <code class="text-danger small">${error.message}</code>
                            </div>
                            
                            <div class="mt-3">
                                <button class="btn btn-outline-danger" onclick="cargarReportes()">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reintentar
                                </button>
                                <button class="btn btn-outline-secondary" onclick="location.reload()">
                                    <i class="bi bi-arrow-repeat me-2"></i>Recargar Página
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
    }

    function exportarReportes() {
        const proyecto = document.getElementById('filtroProyecto').value;
        const mes = document.getElementById('filtroMes').value;
        const anio = document.getElementById('filtroAnio').value;

        const mesNumero = mes ? mes.split('-')[1] : '';

        window.location.href = `../api/exportar_reporte.php?action=exportar_excel&proyecto_id=${proyecto}&mes=${mesNumero}&anio=${anio}`;
    }

    function verDetalleReporte(id) {
        window.location.href = `detalle_reporte.php?id=${id}`;
    }

    cargarReportes();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>