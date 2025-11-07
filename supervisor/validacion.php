<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Validación de Jornadas';
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2>Validación de Jornadas y Actividades</h2>
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
                            <label class="form-label">Estado</label>
                            <select class="form-select" id="filtroEstado">
                                <option value="Pendiente">Pendiente</option>
                                <option value="Aprobado">Aprobado</option>
                                <option value="Rechazado">Rechazado</option>
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" id="filtroFechaDesde">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" id="filtroFechaHasta">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary" onclick="aplicarFiltros()">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                        <button class="btn btn-secondary" onclick="limpiarFiltros()">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs: Jornadas y Actividades -->
    <ul class="nav nav-tabs mb-3" id="validacionTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="jornadas-tab" data-bs-toggle="tab" data-bs-target="#jornadas" type="button">
                Jornadas Pendientes <span class="badge bg-danger" id="badgeJornadas">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="actividades-tab" data-bs-toggle="tab" data-bs-target="#actividades" type="button">
                Actividades Pendientes <span class="badge bg-danger" id="badgeActividades">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="validacionTabContent">
        <!-- Tab Jornadas -->
        <div class="tab-pane fade show active" id="jornadas" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Promotor</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Horas</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="jornadasBody">
                                <!-- Cargado dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Actividades -->
        <div class="tab-pane fade" id="actividades" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Promotor</th>
                                    <th>Tipo</th>
                                    <th>Fecha</th>
                                    <th>Ubicación</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="actividadesBody">
                                <!-- Cargado dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle Jornada -->
<div class="modal fade" id="modalDetalleJornada" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Jornada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleJornadaBody">
                <!-- Cargado dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-danger" onclick="rechazarJornada()">
                    <i class="bi bi-x-circle"></i> Rechazar
                </button>
                <button type="button" class="btn btn-success" onclick="aprobarJornada()">
                    <i class="bi bi-check-circle"></i> Aprobar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle Actividad -->
<div class="modal fade" id="modalDetalleActividad" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Actividad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleActividadBody">
                <!-- Cargado dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-danger" onclick="rechazarActividad()">
                    <i class="bi bi-x-circle"></i> Rechazar
                </button>
                <button type="button" class="btn btn-success" onclick="aprobarActividad()">
                    <i class="bi bi-check-circle"></i> Aprobar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Motivo Rechazo -->
<div class="modal fade" id="modalRechazo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Motivo de Rechazo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <textarea class="form-control" id="motivoRechazo" rows="4" placeholder="Ingrese el motivo del rechazo..." required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="confirmarRechazo()">Confirmar Rechazo</button>
            </div>
        </div>
    </div>
</div>

<script>
    let jornadaActual = null;
    let actividadActual = null;
    let tipoRechazo = null;

    document.addEventListener('DOMContentLoaded', () => {
        cargarPromotores();
        cargarJornadas();
        cargarActividades();
    });

    // Cargar promotores para filtro
    async function cargarPromotores() {
        try {
            const response = await fetch('../api/validacion_crud.php?action=promotores');
            const promotores = await response.json();

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

    // Cargar jornadas
    async function cargarJornadas() {
        try {
            const params = new URLSearchParams({
                action: 'jornadas',
                promotor: document.getElementById('filtroPromotor').value,
                estado: document.getElementById('filtroEstado').value,
                fecha_desde: document.getElementById('filtroFechaDesde').value,
                fecha_hasta: document.getElementById('filtroFechaHasta').value
            });

            const response = await fetch(`../api/validacion_crud.php?${params}`);

            const text = await response.text();
            console.log('[v0] Respuesta jornadas (raw):', text);

            let jornadas;
            try {
                jornadas = JSON.parse(text);
            } catch (e) {
                console.error('[v0] Error al parsear JSON:', e);
                console.error('[v0] Texto recibido:', text);
                throw new Error('Respuesta inválida del servidor');
            }

            const tbody = document.getElementById('jornadasBody');
            tbody.innerHTML = '';

            if (!Array.isArray(jornadas)) {
                console.error('[v0] jornadas no es un array:', jornadas);
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error: Respuesta inválida del servidor</td></tr>';
                document.getElementById('badgeJornadas').textContent = '0';
                return;
            }

            const pendientes = jornadas.filter(j => j.estado_validacion === 'Pendiente').length;
            document.getElementById('badgeJornadas').textContent = pendientes;

            if (jornadas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay jornadas para mostrar</td></tr>';
                return;
            }

            jornadas.forEach(jornada => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>${jornada.jornada_id}</td>
                <td>${jornada.nombre_promotor}</td>
                <td>${jornada.check_in_time || 'N/A'}</td>
                <td>${jornada.check_out_time || 'Pendiente'}</td>
                <td>${jornada.horas_calculadas || '0'} hrs</td>
                <td>
                    <span class="badge bg-${getEstadoColor(jornada.estado_validacion)}">
                        ${jornada.estado_validacion}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="verDetalleJornada(${jornada.jornada_id})">
                        <i class="bi bi-eye"></i> Ver
                    </button>
                </td>
            `;
                tbody.appendChild(tr);
            });
        } catch (error) {
            console.error('[v0] Error al cargar jornadas:', error);
            document.getElementById('jornadasBody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error al cargar jornadas: ' + error.message + '</td></tr>';
        }
    }

    // Cargar actividades
    async function cargarActividades() {
        try {
            const params = new URLSearchParams({
                action: 'actividades',
                promotor: document.getElementById('filtroPromotor').value,
                estado: document.getElementById('filtroEstado').value,
                fecha_desde: document.getElementById('filtroFechaDesde').value,
                fecha_hasta: document.getElementById('filtroFechaHasta').value
            });

            const response = await fetch(`../api/validacion_crud.php?${params}`);

            const text = await response.text();
            console.log('[v0] Respuesta actividades (raw):', text);

            let actividades;
            try {
                actividades = JSON.parse(text);
            } catch (e) {
                console.error('[v0] Error al parsear JSON:', e);
                console.error('[v0] Texto recibido:', text);
                throw new Error('Respuesta inválida del servidor');
            }

            const tbody = document.getElementById('actividadesBody');
            tbody.innerHTML = '';

            if (!Array.isArray(actividades)) {
                console.error('[v0] actividades no es un array:', actividades);
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error: Respuesta inválida del servidor</td></tr>';
                document.getElementById('badgeActividades').textContent = '0';
                return;
            }

            const pendientes = actividades.filter(a => a.estado_validacion === 'Pendiente').length;
            document.getElementById('badgeActividades').textContent = pendientes;

            if (actividades.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay actividades para mostrar</td></tr>';
                return;
            }

            actividades.forEach(actividad => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>${actividad.actividad_id}</td>
                <td>${actividad.nombre_promotor}</td>
                <td>${actividad.tipo_actividad}</td>
                <td>${actividad.fecha_actividad}</td>
                <td>${actividad.latitud}, ${actividad.longitud}</td>
                <td>
                    <span class="badge bg-${getEstadoColor(actividad.estado_validacion)}">
                        ${actividad.estado_validacion}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="verDetalleActividad(${actividad.actividad_id})">
                        <i class="bi bi-eye"></i> Ver
                    </button>
                </td>
            `;
                tbody.appendChild(tr);
            });
        } catch (error) {
            console.error('[v0] Error al cargar actividades:', error);
            document.getElementById('actividadesBody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error al cargar actividades: ' + error.message + '</td></tr>';
        }
    }

    // Ver detalle jornada
    async function verDetalleJornada(id) {
        try {
            const response = await fetch(`../api/validacion_crud.php?action=detalle_jornada&id=${id}`);
            const jornada = await response.json();

            jornadaActual = id;

            const body = document.getElementById('detalleJornadaBody');
            body.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Promotor:</strong> ${jornada.nombre_promotor}</p>
                    <p><strong>Check-in:</strong> ${jornada.check_in_time}</p>
                    <p><strong>Check-out:</strong> ${jornada.check_out_time || 'Pendiente'}</p>
                    <p><strong>Horas:</strong> ${jornada.horas_calculadas || '0'} hrs</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Ubicación Check-in:</strong><br>${jornada.check_in_latitud}, ${jornada.check_in_longitud}</p>
                    ${jornada.check_out_latitud ? `<p><strong>Ubicación Check-out:</strong><br>${jornada.check_out_latitud}, ${jornada.check_out_longitud}</p>` : ''}
                </div>
            </div>
            ${jornada.check_in_foto_url ? `
                <div class="mt-3">
                    <strong>Foto Check-in:</strong><br>
                    <img src="${jornada.check_in_foto_url}" class="img-fluid rounded" style="max-height: 300px;">
                </div>
            ` : ''}
        `;

            new bootstrap.Modal(document.getElementById('modalDetalleJornada')).show();
        } catch (error) {
            alert('Error al cargar detalle');
        }
    }

    // Ver detalle actividad
    async function verDetalleActividad(id) {
        try {
            const response = await fetch(`../api/validacion_crud.php?action=detalle_actividad&id=${id}`);
            const actividad = await response.json();

            actividadActual = id;

            const body = document.getElementById('detalleActividadBody');
            body.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Promotor:</strong> ${actividad.nombre_promotor}</p>
                    <p><strong>Tipo:</strong> ${actividad.tipo_actividad}</p>
                    <p><strong>Fecha:</strong> ${actividad.fecha_actividad}</p>
                    <p><strong>Ubicación:</strong> ${actividad.latitud}, ${actividad.longitud}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Descripción:</strong><br>${actividad.descripcion || 'N/A'}</p>
                </div>
            </div>
            ${actividad.evidencias && actividad.evidencias.length > 0 ? `
                <div class="mt-3">
                    <strong>Evidencias:</strong><br>
                    <div class="row g-2">
                        ${actividad.evidencias.map(e => `
                            <div class="col-md-4">
                                ${e.tipo_evidencia === 'foto' ? 
                                    `<img src="${e.url_evidencia}" class="img-fluid rounded">` :
                                    `<a href="${e.url_evidencia}" target="_blank" class="btn btn-sm btn-primary">Ver ${e.tipo_evidencia}</a>`
                                }
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
        `;

            new bootstrap.Modal(document.getElementById('modalDetalleActividad')).show();
        } catch (error) {
            alert('Error al cargar detalle');
        }
    }

    // Aprobar jornada
    async function aprobarJornada() {
        if (!confirm('¿Está seguro de aprobar esta jornada?')) return;

        try {
            const response = await fetch('../api/validacion_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'aprobar_jornada',
                    jornada_id: jornadaActual
                })
            });
            const result = await response.json();

            if (result.success) {
                alert('Jornada aprobada exitosamente');
                bootstrap.Modal.getInstance(document.getElementById('modalDetalleJornada')).hide();
                cargarJornadas();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error al aprobar jornada');
        }
    }

    // Rechazar jornada
    function rechazarJornada() {
        tipoRechazo = 'jornada';
        bootstrap.Modal.getInstance(document.getElementById('modalDetalleJornada')).hide();
        new bootstrap.Modal(document.getElementById('modalRechazo')).show();
    }

    // Aprobar actividad
    async function aprobarActividad() {
        if (!confirm('¿Está seguro de aprobar esta actividad?')) return;

        try {
            const response = await fetch('../api/validacion_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'aprobar_actividad',
                    actividad_id: actividadActual
                })
            });
            const result = await response.json();

            if (result.success) {
                alert('Actividad aprobada exitosamente');
                bootstrap.Modal.getInstance(document.getElementById('modalDetalleActividad')).hide();
                cargarActividades();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error al aprobar actividad');
        }
    }

    // Rechazar actividad
    function rechazarActividad() {
        tipoRechazo = 'actividad';
        bootstrap.Modal.getInstance(document.getElementById('modalDetalleActividad')).hide();
        new bootstrap.Modal(document.getElementById('modalRechazo')).show();
    }

    // Confirmar rechazo
    async function confirmarRechazo() {
        const motivo = document.getElementById('motivoRechazo').value.trim();

        if (!motivo) {
            alert('Debe ingresar un motivo de rechazo');
            return;
        }

        try {
            const action = tipoRechazo === 'jornada' ? 'rechazar_jornada' : 'rechazar_actividad';
            const id = tipoRechazo === 'jornada' ? jornadaActual : actividadActual;

            const response = await fetch('../api/validacion_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    id: id,
                    motivo_rechazo: motivo
                })
            });
            const result = await response.json();

            if (result.success) {
                alert('Rechazado exitosamente');
                bootstrap.Modal.getInstance(document.getElementById('modalRechazo')).hide();
                document.getElementById('motivoRechazo').value = '';

                if (tipoRechazo === 'jornada') {
                    cargarJornadas();
                } else {
                    cargarActividades();
                }
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error al rechazar');
        }
    }

    // Utilidades
    function getEstadoColor(estado) {
        switch (estado) {
            case 'Pendiente':
                return 'warning';
            case 'Aprobado':
                return 'success';
            case 'Rechazado':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    function aplicarFiltros() {
        cargarJornadas();
        cargarActividades();
    }

    function limpiarFiltros() {
        document.getElementById('filtroPromotor').value = '';
        document.getElementById('filtroEstado').value = 'Pendiente';
        document.getElementById('filtroFechaDesde').value = '';
        document.getElementById('filtroFechaHasta').value = '';
        aplicarFiltros();
    }
</script>

<?php include '../includes/footer.php'; ?>