<?php
$pageTitle = 'Detalle del Reporte';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../db/UsuarioCliente.php';

requireRole(['Cliente']);

$reporteId = $_GET['id'] ?? null;

if (!$reporteId) {
    header('Location: reportes.php');
    exit;
}

$userId = getUserId();
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="reportes.php">Reportes</a></li>
                            <li class="breadcrumb-item active">Detalle</li>
                        </ol>
                    </nav>
                    <h2 class="mb-1">Detalle del Reporte</h2>
                    <p class="text-muted mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>
                        Información detallada del período
                    </p>
                </div>
                <div>
                    <button class="btn btn-outline-secondary me-2" onclick="window.history.back()">
                        <i class="bi bi-arrow-left"></i> Volver
                    </button>
                    <button class="btn btn-success" onclick="exportarDetalle()">
                        <i class="bi bi-download"></i> Exportar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Métricas del Reporte -->
    <div class="row mb-4" id="metricasReporte">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    </div>

    <!-- Detalle de Jornadas -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-calendar-check me-2 text-primary"></i>
                        Detalle de Jornadas
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tablaDetalle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="px-4 py-3">Fecha</th>
                                    <th class="py-3">Promotor</th>
                                    <th class="py-3">Proyecto</th>
                                    <th class="py-3 text-center">Jornadas</th>
                                    <th class="py-3 text-center">Actividades</th>
                                    <th class="py-3 text-center">Horas</th>
                                    <th class="py-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const reporteId = <?= json_encode($reporteId) ?>;

    function cargarDetalle() {
        const [proyectoId, mes, anio] = reporteId.split('_');

        const params = new URLSearchParams({
            action: 'reporte',
            proyecto_id: proyectoId || '',
            mes: mes || '',
            anio: anio || ''
        });

        fetch(`../api/exportar_reporte.php?${params}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Mostrar métricas
                    const metricasHtml = `
                        <div class="col-md-3 mb-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center">
                                    <div class="bg-primary bg-opacity-10 rounded p-3 mb-2">
                                        <i class="bi bi-calendar-check text-primary" style="font-size: 2rem;"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1">${data.metricas.total_jornadas}</h3>
                                    <p class="text-muted mb-0">Total Jornadas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center">
                                    <div class="bg-success bg-opacity-10 rounded p-3 mb-2">
                                        <i class="bi bi-list-check text-success" style="font-size: 2rem;"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1">${data.metricas.total_actividades}</h3>
                                    <p class="text-muted mb-0">Total Actividades</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center">
                                    <div class="bg-info bg-opacity-10 rounded p-3 mb-2">
                                        <i class="bi bi-clock text-info" style="font-size: 2rem;"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1">${parseFloat(data.metricas.total_horas).toFixed(2)}h</h3>
                                    <p class="text-muted mb-0">Horas Trabajadas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center">
                                    <div class="bg-warning bg-opacity-10 rounded p-3 mb-2">
                                        <i class="bi bi-award text-warning" style="font-size: 2rem;"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1">${data.metricas.porcentaje_aprobacion}%</h3>
                                    <p class="text-muted mb-0">Aprobación</p>
                                </div>
                            </div>
                        </div>
                    `;
                    document.getElementById('metricasReporte').innerHTML = metricasHtml;

                    // Mostrar detalle
                    const tbody = document.querySelector('#tablaDetalle tbody');
                    if (data.detalle.length > 0) {
                        tbody.innerHTML = data.detalle.map(d => {
                            const estadoBadge = d.estado === 'aprobado' ?
                                '<span class="badge bg-success">Aprobado</span>' :
                                d.estado === 'rechazado' ?
                                '<span class="badge bg-danger">Rechazado</span>' :
                                '<span class="badge bg-warning">Pendiente</span>';

                            return `
                                <tr>
                                    <td class="px-4">${d.fecha}</td>
                                    <td>${d.promotor}</td>
                                    <td>${d.proyecto}</td>
                                    <td class="text-center">${d.jornadas}</td>
                                    <td class="text-center">${d.actividades}</td>
                                    <td class="text-center">${parseFloat(d.horas).toFixed(2)}</td>
                                    <td>${estadoBadge}</td>
                                </tr>
                            `;
                        }).join('');
                    } else {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                    <p class="mt-2">No hay registros para este período</p>
                                </td>
                            </tr>
                        `;
                    }
                } else {
                    throw new Error(data.message || 'Error al cargar el detalle');
                }
            })
            .catch(error => {
                console.error('[v0] Error loading detail:', error);
                document.getElementById('metricasReporte').innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger border-0 shadow-sm">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-exclamation-triangle-fill me-2" style="font-size: 1.5rem;"></i>
                                <h5 class="mb-0">Error al Cargar el Detalle</h5>
                            </div>
                            <p class="mb-2">No se pudo cargar la información del reporte. Esto puede deberse a:</p>
                            <ul class="mb-2">
                                <li>No hay datos disponibles para este período</li>
                                <li>El reporte seleccionado no existe</li>
                                <li>Error de conexión con el servidor</li>
                            </ul>
                            <p class="mb-0 text-muted small">Error técnico: ${error.message}</p>
                        </div>
                    </div>
                `;

                document.querySelector('#tablaDetalle tbody').innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-exclamation-circle" style="font-size: 3rem;"></i>
                            <p class="mt-2">No se pudieron cargar los datos</p>
                        </td>
                    </tr>
                `;
            });
    }

    function exportarDetalle() {
        const [proyectoId, mes, anio] = reporteId.split('_');
        window.location.href = `../api/exportar_reporte.php?action=exportar_excel&proyecto_id=${proyectoId || ''}&mes=${mes || ''}&anio=${anio || ''}`;
    }

    // Cargar al iniciar
    cargarDetalle();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>