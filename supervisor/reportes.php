<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Reportes de Desempeño';
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2>Reportes de Desempeño</h2>
        </div>
    </div>

    <!-- Filtros -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Promotor</label>
                            <select class="form-select" id="filtroPromotor">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Proyecto</label>
                            <select class="form-select" id="filtroProyecto">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Mes</label>
                            <select class="form-select" id="filtroMes">
                                <option value="1">Enero</option>
                                <option value="2">Febrero</option>
                                <option value="3">Marzo</option>
                                <option value="4">Abril</option>
                                <option value="5">Mayo</option>
                                <option value="6">Junio</option>
                                <option value="7">Julio</option>
                                <option value="8">Agosto</option>
                                <option value="9">Septiembre</option>
                                <option value="10">Octubre</option>
                                <option value="11">Noviembre</option>
                                <option value="12">Diciembre</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Año</label>
                            <input type="number" class="form-control" id="filtroAnio" value="2025">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-primary w-100" onclick="generarReporte()">
                                <i class="bi bi-bar-chart"></i> Generar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen de Métricas -->
    <div class="row mb-4" id="metricas" style="display: none;">
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">Total Jornadas</h6>
                    <h2 class="mb-0" id="metricaJornadas">0</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">Total Actividades</h6>
                    <h2 class="mb-0" id="metricaActividades">0</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">Horas Trabajadas</h6>
                    <h2 class="mb-0" id="metricaHoras">0</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">% Aprobación</h6>
                    <h2 class="mb-0 text-success" id="metricaAprobacion">0%</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Reporte -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Detalle de Reporte</h5>
                    <div>
                        <button class="btn btn-light btn-sm" onclick="exportarCSV()">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
                        </button>
                        <button class="btn btn-light btn-sm" onclick="exportarExcel()">
                            <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaReporte">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Promotor</th>
                                    <th>Proyecto</th>
                                    <th>Jornadas</th>
                                    <th>Actividades</th>
                                    <th>Horas</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="reporteBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        Seleccione los filtros y haga clic en "Generar" para ver el reporte
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
    let reporteActual = [];

    document.addEventListener('DOMContentLoaded', () => {
        cargarPromotores();
        cargarProyectos();

        // Establecer mes y año actual
        const hoy = new Date();
        document.getElementById('filtroMes').value = hoy.getMonth() + 1;
        document.getElementById('filtroAnio').value = hoy.getFullYear();
    });

    async function cargarPromotores() {
        try {
            const response = await fetch('../api/validacion_crud.php?action=promotores');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const promotores = await response.json();

            if (!Array.isArray(promotores)) {
                console.error('Error: La respuesta no es un array de promotores', promotores);
                return;
            }

            const select = document.getElementById('filtroPromotor');
            promotores.forEach(p => {
                const option = document.createElement('option');
                option.value = p.user_id;
                option.textContent = p.nombre_completo;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Error al cargar promotores:', error);
        }
    }

    async function cargarProyectos() {
        try {
            const response = await fetch('../api/proyecto_asignaciones.php?action=list');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const proyectos = await response.json();

            if (!Array.isArray(proyectos)) {
                console.error('Error: La respuesta no es un array de proyectos', proyectos);
                return;
            }

            const select = document.getElementById('filtroProyecto');
            proyectos.forEach(p => {
                const option = document.createElement('option');
                option.value = p.proyecto_id;
                option.textContent = p.nombre_proyecto;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Error al cargar proyectos:', error);
        }
    }

    async function generarReporte() {
        const params = new URLSearchParams({
            action: 'reporte',
            promotor: document.getElementById('filtroPromotor').value,
            proyecto: document.getElementById('filtroProyecto').value,
            mes: document.getElementById('filtroMes').value,
            anio: document.getElementById('filtroAnio').value
        });

        try {
            const response = await fetch(`../api/exportar_reporte.php?${params}`);
            const data = await response.json();

            reporteActual = data.detalle;

            // Actualizar métricas
            document.getElementById('metricaJornadas').textContent = data.metricas.total_jornadas;
            document.getElementById('metricaActividades').textContent = data.metricas.total_actividades;
            document.getElementById('metricaHoras').textContent = data.metricas.total_horas.toFixed(2);
            document.getElementById('metricaAprobacion').textContent = data.metricas.porcentaje_aprobacion + '%';
            document.getElementById('metricas').style.display = 'flex';

            // Actualizar tabla
            const tbody = document.getElementById('reporteBody');
            tbody.innerHTML = '';

            if (reporteActual.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No se encontraron datos</td></tr>';
                return;
            }

            reporteActual.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>${item.fecha}</td>
                <td>${item.promotor}</td>
                <td>${item.proyecto}</td>
                <td>${item.jornadas}</td>
                <td>${item.actividades}</td>
                <td>${item.horas}</td>
                <td>
                    <span class="badge bg-${item.estado === 'Aprobado' ? 'success' : item.estado === 'Pendiente' ? 'warning' : 'danger'}">
                        ${item.estado}
                    </span>
                </td>
            `;
                tbody.appendChild(tr);
            });

        } catch (error) {
            alert('Error al generar reporte');
            console.error(error);
        }
    }

    async function exportarCSV() {
        if (reporteActual.length === 0) {
            alert('Debe generar un reporte primero');
            return;
        }

        const params = new URLSearchParams({
            action: 'exportar_csv',
            promotor: document.getElementById('filtroPromotor').value,
            proyecto: document.getElementById('filtroProyecto').value,
            mes: document.getElementById('filtroMes').value,
            anio: document.getElementById('filtroAnio').value
        });

        window.open(`../api/exportar_reporte.php?${params}`, '_blank');
    }

    async function exportarExcel() {
        if (reporteActual.length === 0) {
            alert('Debe generar un reporte primero');
            return;
        }

        const params = new URLSearchParams({
            action: 'exportar_excel',
            promotor: document.getElementById('filtroPromotor').value,
            proyecto: document.getElementById('filtroProyecto').value,
            mes: document.getElementById('filtroMes').value,
            anio: document.getElementById('filtroAnio').value
        });

        window.open(`../api/exportar_reporte.php?${params}`, '_blank');
    }
</script>

<?php include '../includes/footer.php'; ?>