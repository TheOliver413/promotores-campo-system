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

<!-- CHANGE: Agregando Leaflet CSS y JS para mapa en tiempo real -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" />
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>

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
                            <!-- Agregando background color a select -->
                            <select class="form-select bg-light" id="filtroPromotor">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <!-- Agregando background color a select -->
                            <select class="form-select bg-light" id="filtroEstado">
                                <option value="pendiente">Pendiente</option>
                                <option value="aprobado">Aprobado</option>
                                <option value="rechazado">Rechazado</option>
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
    <!-- Agregando background color a tabs -->
    <ul class="nav nav-tabs mb-3 bg-light rounded-top" id="validacionTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="jornadas-tab" data-bs-toggle="tab" data-bs-target="#jornadas" type="button">
                Jornadas Pendientes <span class="badge rounded-pill bg-danger" id="badgeJornadas">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="actividades-tab" data-bs-toggle="tab" data-bs-target="#actividades" type="button">
                Actividades Pendientes <span class="badge rounded-pill bg-danger" id="badgeActividades">0</span>
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
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Jornada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleJornadaBody">
                <!-- Cargado dinámicamente -->
                <div id="mapJornada" style="height: 400px;"></div>
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

            const pendientes = jornadas.filter(j => (j.estado_validacion || '').toLowerCase() === 'pendiente').length;
            document.getElementById('badgeJornadas').textContent = pendientes;

            if (jornadas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay jornadas para mostrar</td></tr>';
                return;
            }

            jornadas.forEach(jornada => {
                const tr = document.createElement('tr');
                const estado = (jornada.estado_validacion || 'pendiente').toLowerCase();
                tr.innerHTML = `
                    <td>${jornada.jornada_id}</td>
                    <td>${jornada.nombre_promotor}</td>
                    <td>${jornada.check_in_time || 'N/A'}</td>
                    <td>${jornada.check_out_time || 'Pendiente'}</td>
                    <td>${jornada.horas_calculadas || '0'} hrs</td>
                    <td>
                        <span class="badge bg-${getEstadoColor(estado)}">
                            ${estado.charAt(0).toUpperCase() + estado.slice(1)}
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

            const pendientes = actividades.filter(a => (a.estado_validacion || '').toLowerCase() === 'pendiente').length;
            document.getElementById('badgeActividades').textContent = pendientes;

            if (actividades.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay actividades para mostrar</td></tr>';
                return;
            }

            actividades.forEach(actividad => {
                const tr = document.createElement('tr');
                const estado = (actividad.estado_validacion || 'pendiente').toLowerCase();
                tr.innerHTML = `
                    <td>${actividad.actividad_id}</td>
                    <td>${actividad.nombre_promotor}</td>
                    <td>${actividad.tipo_actividad}</td>
                    <td>${actividad.fecha_actividad}</td>
                    <td>${actividad.latitud}, ${actividad.longitud}</td>
                    <td>
                        <span class="badge bg-${getEstadoColor(estado)}">
                            ${estado.charAt(0).toUpperCase() + estado.slice(1)}
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

            if (!response.ok) {
                throw new Error('Error al obtener detalle');
            }

            const jornada = await response.json();

            if (!jornada || typeof jornada !== 'object' || jornada.success === false) {
                console.error('[v0] Respuesta inválida:', jornada);
                alert('Error: No se pudo cargar el detalle de la jornada');
                return;
            }

            jornadaActual = id;

            const body = document.getElementById('detalleJornadaBody');
            const checkinLat = jornada.check_in_lat ?? 'N/A';
            const checkinLong = jornada.check_in_lon ?? 'N/A';
            const checkoutLat = jornada.check_out_lat ?? 'N/A';
            const checkoutLong = jornada.check_out_lon ?? 'N/A';
            const checkinTime = jornada.check_in_time ?? 'N/A';
            const checkoutTime = jornada.check_out_time ?? 'Pendiente';
            const horasCal = jornada.horas_calculadas ?? '0';
            const promotor = jornada.nombre_promotor ?? 'N/A';
            const fotoUrl = jornada.check_in_foto_url ?? null;

            body.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6 class="mb-3"><strong>Información de Jornada</strong></h6>
                    <p><strong>Promotor:</strong> ${promotor}</p>
                    <p><strong>Check-in:</strong> ${checkinTime}</p>
                    <p><strong>Check-out:</strong> ${checkoutTime}</p>
                    <p><strong>Horas:</strong> ${horasCal} hrs</p>
                    <hr>
                    <p><strong>Ubicación Check-in:</strong><br><span class="text-muted">${checkinLat}, ${checkinLong}</span></p>
                    ${jornada.check_out_lat ? `<p><strong>Ubicación Check-out:</strong><br><span class="text-muted">${checkoutLat}, ${checkoutLong}</span></p>` : ''}
                    
                    ${fotoUrl ? `
                        <hr>
                        <h6><strong>Foto Check-in</strong></h6>
                        <img src="../${fotoUrl}" class="img-fluid rounded" style="max-height: 250px; width: 100%; object-fit: cover;" 
                             onerror="this.onerror=null; this.src='/placeholder.svg?height=250&width=400'; this.alt='Imagen no disponible';">
                    ` : ''}
                </div>
                <div class="col-md-6">
                    <h6 class="mb-3"><strong>Ubicaciones en Mapa</strong></h6>
                    <!-- Contenedor para mapa Leaflet -->
                    <div id="mapJornada" style="height: 400px; border-radius: 5px; background: #e0e0e0;"></div>
                </div>
            </div>
            `;

            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalDetalleJornada'));
            modal.show();

            setTimeout(() => {
                inicializarMapaJornada(checkinLat, checkinLong, checkoutLat, checkoutLong, promotor);
            }, 500);

        } catch (error) {
            console.error('[v0] Error al cargar detalle jornada:', error);
            alert('Error al cargar detalle: ' + error.message);
        }
    }

    // Ver detalle actividad
    async function verDetalleActividad(id) {
        try {
            const response = await fetch(`../api/validacion_crud.php?action=detalle_actividad&id=${id}`);

            if (!response.ok) {
                throw new Error('Error al obtener detalle');
            }

            const actividad = await response.json();

            if (!actividad || typeof actividad !== 'object' || actividad.success === false) {
                console.error('[v0] Respuesta inválida:', actividad);
                alert('Error: No se pudo cargar el detalle de la actividad');
                return;
            }

            actividadActual = id;

            const body = document.getElementById('detalleActividadBody');
            const promotor = actividad.nombre_promotor ?? 'N/A';
            const tipo = actividad.tipo_actividad ?? 'N/A';
            const fecha = actividad.timestamp_actividad ?? 'N/A';
            const desc = actividad.descripcion ?? 'Sin descripción';
            const lat = actividad.latitud ?? 'N/A';
            const long = actividad.longitud ?? 'N/A';
            const tiempo = actividad.tiempo_minutos ?? 'N/A';
            const evidenciasCount = actividad.evidencias?.length ?? 0;

            body.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Promotor:</strong> ${promotor}</p>
                    <p><strong>Tipo:</strong> ${tipo}</p>
                    <p><strong>Fecha:</strong> ${fecha}</p>
                    <p><strong>Descripción:</strong><br>${desc}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Ubicación:</strong><br>${lat}, ${long}</p>
                    <p><strong>Tiempo:</strong> ${tiempo} minutos</p>
                    ${evidenciasCount > 0 ? `<p><strong>Evidencias:</strong> ${evidenciasCount} archivo(s)</p>` : ''}
                </div>
            </div>
            ${actividad.evidencias && actividad.evidencias.length > 0 ? `
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h6>Evidencias:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            ${actividad.evidencias.map(e => `
                                <img src="../${e.url_archivo}" class="img-thumbnail" style="max-width: 150px; max-height: 150px; object-fit: cover;" 
                                     onerror="this.onerror=null; this.src='/placeholder.svg?height=150&width=150'; this.alt='Imagen no disponible';">
                            `).join('')}
                        </div>
                    </div>
                </div>
            ` : ''}
            `;

            new bootstrap.Modal(document.getElementById('modalDetalleActividad')).show();
        } catch (error) {
            console.error('[v0] Error al cargar detalle:', error);
            alert('Error al cargar detalle');
        }
    }

    // Aprobar jornada
    async function aprobarJornada() {
        if (!jornadaActual) return;

        if (!confirm('¿Aprobar esta jornada?')) return;

        try {
            const response = await fetch('../api/validacion_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'aprobar_jornada',
                    id: jornadaActual
                })
            });
            const result = await response.json();

            if (result.success) {
                alert('Jornada aprobada exitosamente');
                bootstrap.Modal.getInstance(document.getElementById('modalDetalleJornada')).hide();
                cargarJornadas();
            } else {
                alert('Error: ' + (result.message || 'Error desconocido'));
            }
        } catch (error) {
            console.error('[v0] Error al aprobar jornada:', error);
            alert('Error al aprobar jornada');
        }
    }

    // Rechazar jornada
    function rechazarJornada() {
        if (!jornadaActual) return;
        tipoRechazo = 'jornada';
        new bootstrap.Modal(document.getElementById('modalRechazo')).show();
    }

    // Aprobar actividad
    async function aprobarActividad() {
        if (!actividadActual) return;

        if (!confirm('¿Aprobar esta actividad?')) return;

        try {
            const response = await fetch('../api/validacion_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'aprobar_actividad',
                    id: actividadActual
                })
            });
            const result = await response.json();

            if (result.success) {
                alert('Actividad aprobada exitosamente');
                bootstrap.Modal.getInstance(document.getElementById('modalDetalleActividad')).hide();
                cargarActividades();
            } else {
                alert('Error: ' + (result.message || 'Error desconocido'));
            }
        } catch (error) {
            console.error('[v0] Error al aprobar actividad:', error);
            alert('Error al aprobar actividad');
        }
    }

    // Rechazar actividad
    function rechazarActividad() {
        if (!actividadActual) return;
        tipoRechazo = 'actividad';
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
            let action, id;

            if (tipoRechazo === 'jornada') {
                action = 'rechazar_jornada';
                id = jornadaActual;
            } else {
                action = 'rechazar_actividad';
                id = actividadActual;
            }

            const response = await fetch('../api/validacion_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    id: id,
                    motivo: motivo
                })
            });
            const result = await response.json();

            if (result.success) {
                alert('Rechazado exitosamente');
                bootstrap.Modal.getInstance(document.getElementById('modalRechazo')).hide();
                bootstrap.Modal.getInstance(document.getElementById(tipoRechazo === 'jornada' ? 'modalDetalleJornada' : 'modalDetalleActividad')).hide();

                if (tipoRechazo === 'jornada') {
                    cargarJornadas();
                } else {
                    cargarActividades();
                }

                document.getElementById('motivoRechazo').value = '';
            } else {
                alert('Error: ' + (result.message || 'Error desconocido'));
            }
        } catch (error) {
            console.error('[v0] Error al rechazar:', error);
            alert('Error al rechazar');
        }
    }

    function aplicarFiltros() {
        cargarJornadas();
        cargarActividades();
    }

    function limpiarFiltros() {
        document.getElementById('filtroPromotor').value = '';
        document.getElementById('filtroEstado').value = 'pendiente';
        document.getElementById('filtroFechaDesde').value = '';
        document.getElementById('filtroFechaHasta').value = '';
        aplicarFiltros();
    }

    function getEstadoColor(estado) {
        estado = (estado || '').toLowerCase();
        switch (estado) {
            case 'aprobado':
                return 'success';
            case 'rechazado':
                return 'danger';
            case 'pendiente':
                return 'warning';
            default:
                return 'secondary';
        }
    }

    function inicializarMapaJornada(checkinLat, checkinLon, checkoutLat, checkoutLon, nombrePromotor) {
        // Validar coordenadas
        if (checkinLat === 'N/A' || checkinLon === 'N/A') {
            document.getElementById('mapJornada').innerHTML = '<div class="alert alert-warning m-3">No hay datos de ubicación disponibles</div>';
            return;
        }

        // Convertir a números
        const lat1 = parseFloat(checkinLat);
        const lon1 = parseFloat(checkinLon);
        const lat2 = parseFloat(checkoutLat);
        const lon2 = parseFloat(checkoutLon);

        if (isNaN(lat1) || isNaN(lon1)) {
            document.getElementById('mapJornada').innerHTML = '<div class="alert alert-warning m-3">Coordenadas inválidas</div>';
            return;
        }

        // Crear mapa centrado en check-in
        const map = L.map('mapJornada').setView([lat1, lon1], 15);

        // Agregar capa de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        // Marcador Check-in (verde)
        L.circleMarker([lat1, lon1], {
            radius: 8,
            fillColor: '#28a745',
            color: '#fff',
            weight: 3,
            opacity: 1,
            fillOpacity: 0.8
        }).bindPopup(`<strong>Check-in</strong><br>${nombrePromotor}`).addTo(map);

        // Si hay check-out, agregar marcador y línea
        if (!isNaN(lat2) && !isNaN(lon2)) {
            L.circleMarker([lat2, lon2], {
                radius: 8,
                fillColor: '#dc3545',
                color: '#fff',
                weight: 3,
                opacity: 1,
                fillOpacity: 0.8
            }).bindPopup('<strong>Check-out</strong>').addTo(map);

            // Línea entre puntos
            L.polyline([
                [lat1, lon1],
                [lat2, lon2]
            ], {
                color: '#007bff',
                weight: 2,
                opacity: 0.7,
                dashArray: '5, 5'
            }).addTo(map);

            // Ajustar vista para mostrar ambos puntos
            const bounds = L.latLngBounds([
                [lat1, lon1],
                [lat2, lon2]
            ]);
            map.fitBounds(bounds, {
                padding: [50, 50]
            });
        }
    }
</script>

<?php include '../includes/footer.php'; ?>