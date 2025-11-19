<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Monitoreo en Tiempo Real';
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-broadcast"></i> Monitoreo en Tiempo Real</h2>
            <p class="text-muted">Visualiza la ubicación y estado actual de todos tus promotores</p>
        </div>
        <div class="col-md-6 text-end">
            <div class="d-inline-flex align-items-center gap-2">
                <span class="text-muted">Actualización automática:</span>
                <span class="badge bg-success" id="statusActualizacion">
                    <i class="bi bi-check-circle"></i> Activa (10s)
                </span>
                <button class="btn btn-sm btn-primary" onclick="cargarDatos()">
                    <i class="bi bi-arrow-clockwise"></i> Actualizar ahora
                </button>
            </div>
        </div>
    </div>

    <!-- Resumen general -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-success">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-person-check-fill text-success" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Activos</h6>
                            <h3 class="mb-0" id="countActivos">0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-pause-circle-fill text-warning" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">En Pausa</h6>
                            <h3 class="mb-0" id="countPausa">0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-person-x-fill text-danger" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Inactivos</h6>
                            <h3 class="mb-0" id="countInactivos">0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-geo-alt-fill text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Total Rutas</h6>
                            <h3 class="mb-0" id="countRutas">0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros rápidos -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="filtroEstado" id="filtroTodos" value="" checked>
                <label class="btn btn-outline-primary" for="filtroTodos">Todos</label>

                <input type="radio" class="btn-check" name="filtroEstado" id="filtroActivos" value="activo">
                <label class="btn btn-outline-success" for="filtroActivos">Activos</label>

                <input type="radio" class="btn-check" name="filtroEstado" id="filtroPausa" value="pausa">
                <label class="btn btn-outline-warning" for="filtroPausa">En Pausa</label>

                <input type="radio" class="btn-check" name="filtroEstado" id="filtroInactivos" value="inactivo">
                <label class="btn btn-outline-danger" for="filtroInactivos">Inactivos</label>
            </div>
        </div>
    </div>

    <!-- Lista de promotores -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-people-fill"></i> Promotores</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Promotor</th>
                                    <th>Estado</th>
                                    <th>Jornada Actual</th>
                                    <th>Ruta Activa</th>
                                    <th>Última Ubicación</th>
                                    <th>Última Actividad</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="promotoresBody">
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Cargando datos...</p>
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

<!-- Modal Detalle Promotor -->
<div class="modal fade" id="modalDetallePromotor" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Promotor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detallePromotorBody">
                <!-- Cargado dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Mapa en Tiempo Real -->
<div class="modal fade" id="modalMapaReal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mapa en Tiempo Real - <span id="nombrePromotorMapa">...</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Mapa de Leaflet integrado -->
                <div id="mapCanvas" style="width: 100%; height: 100%;"></div>
                <div id="mapaInfo" class="position-absolute bottom-0 start-0 m-3 bg-white p-3 rounded shadow" style="max-width: 300px;">
                    <h6>Información en Vivo</h6>
                    <p class="mb-1"><strong>Última ubicación:</strong> <span id="infoUbicacion">-</span></p>
                    <p class="mb-1"><strong>Batería:</strong> <span id="infoBateria">-</span></p>
                    <p class="mb-0"><strong>Actualizado:</strong> <span id="infoActualizado">-</span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="enviarNotificacion()">
                    <i class="bi bi-bell"></i> Enviar Notificación
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    let intervaloActualizacion = null;
    let datosPromotores = [];
    let mapaActual = null;
    let polilineaActual = null;
    let marcadores = [];
    let promotorActualId = null;
    let intervaloUbicacionReal = null;
    let mapaConfig = {
        lat: 4.570868,
        lng: -74.297333,
        zoom: 14
    };

    document.addEventListener('DOMContentLoaded', () => {
        cargarDatos();
        iniciarActualizacionAutomatica();

        // Listeners para filtros
        document.querySelectorAll('input[name="filtroEstado"]').forEach(radio => {
            radio.addEventListener('change', aplicarFiltros);
        });
    });

    async function cargarDatos() {
        try {
            const response = await fetch('../api/monitoreo_crud.php?action=estado_promotores');
            const data = await response.json();

            if (data.success) {
                datosPromotores = data.promotores;
                actualizarResumen(data.resumen);
                aplicarFiltros();
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('[v0] Error al cargar datos:', error);
            document.getElementById('promotoresBody').innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-danger py-4">
                        <i class="bi bi-exclamation-triangle"></i> Error al cargar datos: ${error.message}
                    </td>
                </tr>
            `;
        }
    }

    function actualizarResumen(resumen) {
        document.getElementById('countActivos').textContent = resumen.activos || 0;
        document.getElementById('countPausa').textContent = resumen.pausados || 0;
        document.getElementById('countInactivos').textContent = resumen.inactivos || 0;
        document.getElementById('countRutas').textContent = resumen.rutas_activas || 0;
    }

    function aplicarFiltros() {
        const filtroEstado = document.querySelector('input[name="filtroEstado"]:checked').value;

        let promotoresFiltrados = datosPromotores;
        if (filtroEstado) {
            promotoresFiltrados = datosPromotores.filter(p => p.estado === filtroEstado);
        }

        renderizarPromotores(promotoresFiltrados);
    }

    function renderizarPromotores(promotores) {
        const tbody = document.getElementById('promotoresBody');

        if (promotores.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="bi bi-inbox"></i> No hay promotores para mostrar
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = '';
        promotores.forEach(p => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle bg-primary text-white me-2">
                            ${p.nombre_completo.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <strong>${p.nombre_completo}</strong><br>
                            <small class="text-muted">${p.email}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-${getEstadoBadgeColor(p.estado)}">
                        <i class="bi ${getEstadoIcon(p.estado)}"></i> ${getEstadoTexto(p.estado)}
                    </span>
                </td>
                <td>
                    ${p.jornada_actual ? `
                        <div>
                            <small class="text-muted">Check-in:</small> ${p.jornada_checkin}<br>
                            <small class="text-muted">Duración:</small> ${p.jornada_duracion}
                        </div>
                    ` : '<span class="text-muted">Sin jornada</span>'}
                </td>
                <td>
                    ${p.ruta_activa ? `
                        <div>
                            <strong>${p.ruta_nombre}</strong><br>
                            <small class="text-muted">${p.ruta_progreso}</small>
                        </div>
                    ` : '<span class="text-muted">Sin ruta</span>'}
                </td>
                <td>
                    ${p.ultima_latitud && p.ultima_longitud ? `
                        <div>
                            <a href="https://www.google.com/maps?q=${p.ultima_latitud},${p.ultima_longitud}" 
                               target="_blank" class="text-primary">
                                <i class="bi bi-geo-alt-fill"></i> Ver en Maps
                            </a><br>
                            <small class="text-muted">${p.ultima_ubicacion_tiempo || 'Recientemente'}</small>
                        </div>
                    ` : '<span class="text-muted">N/A</span>'}
                </td>
                <td>
                    ${p.ultima_actividad_tipo ? `
                        <div>
                            <small>${p.ultima_actividad_tipo}</small><br>
                            <small class="text-muted">${p.ultima_actividad_tiempo || 'Recientemente'}</small>
                        </div>
                    ` : '<span class="text-muted">Sin actividad</span>'}
                </td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="verDetallePromotor(${p.user_id})">
                        <i class="bi bi-eye"></i> Detalle
                    </button>
                    <button class="btn btn-sm btn-success" onclick="verMapaReal(${p.user_id}, '${p.nombre_completo}')">
                        <i class="bi bi-map"></i> Mapa
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    async function verDetallePromotor(userId) {
        try {
            const response = await fetch(`../api/monitoreo_crud.php?action=detalle_promotor&id=${userId}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message);
            }

            const p = data.promotor;
            const body = document.getElementById('detallePromotorBody');

            body.innerHTML = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-person-circle"></i> Información General</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Nombre:</strong> ${p.nombre_completo}</p>
                                <p><strong>Email:</strong> ${p.email}</p>
                                <p><strong>Estado:</strong> 
                                    <span class="badge bg-${getEstadoBadgeColor(p.estado)}">
                                        ${getEstadoTexto(p.estado)}
                                    </span>
                                </p>
                                <p><strong>Teléfono:</strong> ${p.telefono || 'N/A'}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="bi bi-clock-history"></i> Jornada Actual</h6>
                            </div>
                            <div class="card-body">
                                ${p.jornada_actual ? `
                                    <p><strong>Check-in:</strong> ${p.jornada_checkin}</p>
                                    <p><strong>Duración:</strong> ${p.jornada_duracion}</p>
                                    <p><strong>Ubicación inicio:</strong> 
                                        ${p.jornada_lat_inicio && p.jornada_long_inicio ? 
                                            `<a href="https://www.google.com/maps?q=${p.jornada_lat_inicio},${p.jornada_long_inicio}" target="_blank">Ver mapa</a>` 
                                            : 'N/A'}
                                    </p>
                                ` : '<p class="text-muted">No hay jornada activa</p>'}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-map"></i> Ruta Activa</h6>
                            </div>
                            <div class="card-body">
                                ${p.ruta_activa ? `
                                    <p><strong>Nombre:</strong> ${p.ruta_nombre}</p>
                                    <p><strong>Progreso:</strong> ${p.ruta_progreso}</p>
                                ` : '<p class="text-muted">No hay ruta activa</p>'}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card border-warning">
                            <div class="card-header bg-warning">
                                <h6 class="mb-0"><i class="bi bi-list-task"></i> Actividades Recientes (Hoy)</h6>
                            </div>
                            <div class="card-body">
                                ${p.actividades_hoy && p.actividades_hoy.length > 0 ? `

                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Hora</th>
                                                    <th>Tipo</th>
                                                    <th>Descripción</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${p.actividades_hoy.map(a => `
                                                    <tr>
                                                        <td>${a.hora}</td>
                                                        <td>${a.tipo}</td>
                                                        <td>${a.descripcion || '-'}</td>
                                                        <td><span class="badge bg-${getEstadoValidacionColor(a.estado_validacion)}">${a.estado_validacion}</span></td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                ` : '<p class="text-muted">No hay actividades registradas hoy</p>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            new bootstrap.Modal(document.getElementById('modalDetallePromotor')).show();
        } catch (error) {
            console.error('[v0] Error al cargar detalle:', error);
            alert('Error al cargar detalle del promotor');
        }
    }

    async function verMapaReal(userId, nombrePromotor) {
        promotorActualId = userId;
        document.getElementById('nombrePromotorMapa').textContent = nombrePromotor;

        // Limpiar intervalo anterior si existe
        if (intervaloUbicacionReal) {
            clearInterval(intervaloUbicacionReal);
        }

        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('modalMapaReal'));
        modal.show();

        // Esperar a que el modal esté completamente visible
        await new Promise(resolve => setTimeout(resolve, 500));

        // Inicializar mapa
        inicializarMapa();

        // Cargar ubicaciones iniciales
        await actualizarUbicacionesReal();

        // Actualizar cada 10 segundos
        intervaloUbicacionReal = setInterval(actualizarUbicacionesReal, 10000);
    }

    function inicializarMapa() {
        if (!mapaActual) {
            const mapCanvas = document.getElementById('mapCanvas');
            mapaActual = L.map('mapCanvas').setView([mapaConfig.lat, mapaConfig.lng], mapaConfig.zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(mapaActual);
        }
    }

    async function actualizarUbicacionesReal() {
        try {
            const response = await fetch(`../api/monitoreo_crud.php?action=ubicacion_tiempo_real&id=${promotorActualId}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message);
            }

            const ubicaciones = data.ubicaciones;
            if (ubicaciones.length === 0) {
                return;
            }

            // Limpiar marcadores y polilinea anterior
            marcadores.forEach(m => mapaActual.removeLayer(m));
            marcadores = [];
            if (polilineaActual) {
                mapaActual.removeLayer(polilineaActual);
            }

            // Crear array de puntos para la polilinea [lat, lng]
            const puntos = ubicaciones.map(u => [
                parseFloat(u.latitud),
                parseFloat(u.longitud)
            ]);

            // Agregar marcador de inicio (verde)
            if (puntos.length > 0) {
                const marcadorInicio = L.marker(puntos[0], {
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: '<div style="background: #10b981; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><i class="bi bi-play-fill"></i></div>',
                        iconSize: [30, 30]
                    }),
                    title: 'Punto de inicio'
                }).bindPopup('Punto de inicio').addTo(mapaActual);
                marcadores.push(marcadorInicio);
            }

            // Agregar marcador actual (rojo)
            if (puntos.length > 0) {
                const marcadorActual = L.marker(puntos[puntos.length - 1], {
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: '<div style="background: #ef4444; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><i class="bi bi-geo-alt-fill"></i></div>',
                        iconSize: [30, 30]
                    }),
                    title: 'Ubicación actual'
                }).bindPopup('Ubicación actual').addTo(mapaActual);
                marcadores.push(marcadorActual);
            }

            // Dibujar polilinea del recorrido
            polilineaActual = L.polyline(puntos, {
                color: '#3b82f6',
                weight: 3,
                opacity: 0.8,
                dashArray: '5, 5'
            }).addTo(mapaActual);

            // Centrar mapa en ubicación actual
            if (puntos.length > 0) {
                mapaActual.setView(puntos[puntos.length - 1], mapaConfig.zoom);
            }

            // Actualizar info
            const ultimaUbicacion = ubicaciones[ubicaciones.length - 1];
            document.getElementById('infoUbicacion').textContent =
                `${parseFloat(ultimaUbicacion.latitud).toFixed(6)}, ${parseFloat(ultimaUbicacion.longitud).toFixed(6)}`;
            document.getElementById('infoBateria').textContent =
                `${ultimaUbicacion.bateria_nivel || 'N/A'}%`;
            document.getElementById('infoActualizado').textContent =
                new Date(ultimaUbicacion.timestamp_gps).toLocaleTimeString();

        } catch (error) {
            console.error('[v0] Error al cargar ubicaciones:', error);
        }
    }

    function enviarNotificacion() {
        const mensaje = prompt('Ingresa el mensaje para enviar al promotor:');
        if (!mensaje) return;

        fetch('../api/notificaciones_crud.php?action=enviar_notificacion', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    destinatario_id: promotorActualId,
                    mensaje: mensaje,
                    tipo: 'mensaje'
                })
            }).then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Notificación enviada correctamente');
                } else {
                    alert('Error al enviar notificación: ' + data.message);
                }
            }).catch(e => {
                console.error('[v0] Error:', e);
                alert('Error al enviar notificación');
            });
    }

    function iniciarActualizacionAutomatica() {
        intervaloActualizacion = setInterval(() => {
            cargarDatos();
        }, 10000); // 10 segundos
    }

    function getEstadoBadgeColor(estado) {
        switch (estado) {
            case 'activo':
                return 'success';
            case 'pausa':
                return 'warning';
            case 'inactivo':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    function getEstadoIcon(estado) {
        switch (estado) {
            case 'activo':
                return 'bi-play-circle-fill';
            case 'pausa':
                return 'bi-pause-circle-fill';
            case 'inactivo':
                return 'bi-stop-circle-fill';
            default:
                return 'bi-circle';
        }
    }

    function getEstadoTexto(estado) {
        switch (estado) {
            case 'activo':
                return 'Activo';
            case 'pausa':
                return 'En Pausa';
            case 'inactivo':
                return 'Inactivo';
            default:
                return 'Desconocido';
        }
    }

    function getEstadoValidacionColor(estado) {
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
</script>

<style>
    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.2rem;
    }
</style>

<?php include '../includes/footer.php'; ?>