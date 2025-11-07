<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/ReporteMensual.php';
require_once '../db/Proyecto.php';

checkAuth();
checkRole(['Cliente']);

$user_id = $_SESSION['user_id'];
$db = getDB();

// Obtener proyectos del cliente
$proyectos = Proyecto::getByCliente($db, $user_id);

$pageTitle = 'Reportes';
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-bar-graph"></i> Reportes Mensuales</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Proyecto</label>
                            <select class="form-select" id="filtroProyecto" onchange="cargarReportes()">
                                <option value="">Todos los proyectos</option>
                                <?php foreach ($proyectos as $proyecto): ?>
                                    <option value="<?= $proyecto['proyecto_id'] ?>">
                                        <?= htmlspecialchars($proyecto['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mes</label>
                            <input type="month" class="form-control" id="filtroMes" onchange="cargarReportes()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Año</label>
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

    <div class="row" id="listaReportes">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
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

        const params = new URLSearchParams({
            action: 'list',
            proyecto_id: proyecto,
            mes: mes,
            anio: anio
        });

        fetch(`../api/reporte_crud.php?${params}`)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('listaReportes');
                if (data.success && data.reportes.length > 0) {
                    container.innerHTML = data.reportes.map(r => `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">${r.proyecto_nombre}</h6>
                                <small class="text-muted">${r.mes}/${r.anio}</small>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-2">
                                            <h4 class="text-primary mb-0">${r.total_jornadas}</h4>
                                            <small class="text-muted">Jornadas</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-2">
                                            <h4 class="text-success mb-0">${r.total_actividades}</h4>
                                            <small class="text-muted">Actividades</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2">
                                            <h4 class="text-info mb-0">${r.horas_trabajadas}h</h4>
                                            <small class="text-muted">Horas</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2">
                                            <h4 class="text-warning mb-0">${r.cumplimiento_ruta}%</h4>
                                            <small class="text-muted">Cumplimiento</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-sm btn-outline-primary w-100" onclick="verDetalleReporte(${r.reporte_mensual_id})">
                                    <i class="bi bi-eye"></i> Ver Detalle
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
                } else {
                    container.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No hay reportes disponibles con los filtros seleccionados</p>
                    </div>
                `;
                }
            });
    }

    function exportarReportes() {
        const proyecto = document.getElementById('filtroProyecto').value;
        const mes = document.getElementById('filtroMes').value;
        const anio = document.getElementById('filtroAnio').value;

        window.location.href = `../api/exportar_reporte.php?proyecto_id=${proyecto}&mes=${mes}&anio=${anio}`;
    }

    function verDetalleReporte(id) {
        window.location.href = `detalle_reporte.php?id=${id}`;
    }

    cargarReportes();
</script>

<?php include '../includes/footer.php'; ?>