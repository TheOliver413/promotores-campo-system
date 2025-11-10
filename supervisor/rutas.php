<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/User.php';

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    header('Location: ../login.php');
    exit;
}

$userModel = new User();
$db = Database::getInstance()->getConnection();

// Obtener promotores bajo supervisión
$promotores = $userModel->getPromotoresBySupervisor($_SESSION['user_id']);

// Obtener proyectos disponibles
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.nombre_proyecto
    FROM proyectos p
    INNER JOIN proyecto_promotores pp ON p.id = pp.proyecto_id
    INNER JOIN supervisor_promotores sp ON pp.promotor_user_id = sp.promotor_id
    WHERE sp.supervisor_id = ?
    ORDER BY p.nombre_proyecto
");
$stmt->execute([$_SESSION['user_id']]);
$proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Gestión de Rutas';
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Gestión de Rutas</h2>
                <button class="btn btn-primary" onclick="nuevaRuta()">
                    <i class="bi bi-plus-circle"></i> Nueva Ruta
                </button>
            </div>
        </div>
    </div>

    <!-- Added filters section -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Promotor</label>
                            <select class="form-select" id="filtroPromotor" onchange="cargarRutas()">
                                <option value="">Todos</option>
                                <?php foreach ($promotores as $promotor): ?>
                                    <option value="<?= $promotor['id'] ?>">
                                        <?= htmlspecialchars($promotor['nombre_completo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" id="filtroEstado" onchange="cargarRutas()">
                                <option value="">Todos</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="en_progreso">En Progreso</option>
                                <option value="completada">Completada</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="filtroFecha" onchange="cargarRutas()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-secondary w-100" onclick="limpiarFiltros()">
                                <i class="bi bi-x-circle"></i> Limpiar Filtros
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Rutas Planificadas</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaRutas">
                            <thead class="table-light">
                                <tr>
                                    <th>Ruta</th>
                                    <th>Promotor</th>
                                    <th>Fecha</th>
                                    <th>Puntos</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="rutasBody">
                                <!-- Cargado dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                    <!-- Added pagination controls -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <span id="paginationInfo">Mostrando 0 de 0</span>
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="paginationControls">
                                <!-- Generado dinámicamente -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Mapa de Ruta</h5>
                </div>
                <div class="card-body">
                    <div id="map" style="height: 500px; border-radius: 8px;"></div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between">
                            <span><i class="bi bi-circle-fill text-primary"></i> Ruta Planificada</span>
                            <span><i class="bi bi-geo-alt-fill text-danger"></i> Puntos de Visita</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear/Editar Ruta -->
<div class="modal fade" id="modalRuta" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRutaTitulo">Nueva Ruta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRuta">
                <div class="modal-body">
                    <input type="hidden" id="ruta_id" name="ruta_id">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Nombre de la Ruta *</label>
                            <input type="text" class="form-control" id="nombre_ruta" name="nombre_ruta" required placeholder="Ej: Ruta Norte - Zona Industrial">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Proyecto *</label>
                            <select class="form-select" id="proyecto_id" name="proyecto_id" required onchange="cargarUbicacionesDisponibles()">
                                <option value="">Seleccionar...</option>
                                <?php foreach ($proyectos as $proyecto): ?>
                                    <option value="<?= $proyecto['id'] ?>">
                                        <?= htmlspecialchars($proyecto['nombre_proyecto']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Promotor *</label>
                            <select class="form-select" id="promotor_id" name="promotor_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($promotores as $promotor): ?>
                                    <option value="<?= $promotor['id'] ?>">
                                        <?= htmlspecialchars($promotor['nombre_completo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha Planificada *</label>
                            <input type="date" class="form-control" id="fecha_planificada" name="fecha_planificada" required>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <h6>Puntos de Ruta</h6>
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-success" onclick="agregarPuntoManual()">
                                    <i class="bi bi-plus"></i> Agregar Punto Manual
                                </button>
                                <button type="button" class="btn btn-sm btn-info" onclick="agregarDesdeUbicacion()">
                                    <i class="bi bi-building"></i> Desde Ubicación Guardada
                                </button>
                            </div>

                            <div id="listaPuntos" class="list-group" style="max-height: 400px; overflow-y: auto;">
                                <!-- Puntos agregados dinámicamente -->
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6>Vista Previa del Mapa</h6>
                            <div id="mapPreview" style="height: 400px; border-radius: 8px;"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Ruta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Agregar Punto Manual -->
<div class="modal fade" id="modalPuntoManual" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Punto Manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Nombre del Punto *</label>
                            <input type="text" class="form-control" id="punto_nombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dirección *</label>
                            <input type="text" class="form-control" id="punto_direccion" required>
                            <small class="text-muted">Ej: Calle 123 #45-67, Bogotá</small>
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-info" onclick="geocodificarDireccion()">
                                <i class="bi bi-search"></i> Buscar Coordenadas
                            </button>
                            <small class="text-muted d-block mt-1">O haga clic en el mapa para seleccionar la ubicación</small>
                        </div>
                        <!-- Hacer editables los campos de latitud y longitud -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Latitud *</label>
                                <input type="number" step="0.000001" class="form-control" id="punto_latitud"
                                    placeholder="-90 a 90" min="-90" max="90" required>
                                <small class="text-muted">Ej: 4.710989</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Longitud *</label>
                                <input type="number" step="0.000001" class="form-control" id="punto_longitud"
                                    placeholder="-180 a 180" min="-180" max="180" required>
                                <small class="text-muted">Ej: -74.072092</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tiempo Estimado (minutos)</label>
                            <input type="number" class="form-control" id="punto_tiempo" value="30" min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" id="punto_notas" rows="2"></textarea>
                        </div>
                        <!-- Agregar checkbox para guardar como ubicación reutilizable -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="guardar_como_ubicacion">
                                <label class="form-label" for="guardar_como_ubicacion">
                                    Guardar como ubicación reutilizable
                                </label>
                                <small class="text-muted d-block">Esta ubicación estará disponible para futuras rutas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-2">Seleccionar en el Mapa</h6>
                        <div id="mapPuntoManual" style="height: 400px; border-radius: 8px; border: 2px solid #dee2e6;"></div>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle"></i> Haga clic en el mapa para seleccionar la ubicación exacta
                        </small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarPuntoManual()">
                    <i class="bi bi-check-circle"></i> Agregar Punto
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Seleccionar Ubicación -->
<div class="modal fade" id="modalUbicaciones" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Seleccionar Ubicación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Ubicación</th>
                                <th>Dirección</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="ubicacionesBody">
                            <!-- Cargado dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let map;
    let mapPreview;
    let rutaPlanificadaLayer;
    let rutaOptimizadaLayer;
    let puntosRuta = [];
    let ubicacionesDisponibles = [];
    let mapaConfig = {
        lat: 4.570868,
        lng: -74.297333,
        zoom: 6
    };
    let mapPuntoManual = null;
    let tempMarkerPuntoManual = null;
    let currentPage = 1;
    let totalPages = 1;
    let editingPointIndex = null;

    document.addEventListener('DOMContentLoaded', () => {
        // Mapa principal
        map = L.map('map').setView([mapaConfig.lat, mapaConfig.lng], mapaConfig.zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        rutaPlanificadaLayer = L.layerGroup().addTo(map);
        rutaOptimizadaLayer = L.layerGroup().addTo(map);

        cargarRutas();
    });

    async function cargarRutas(page = 1) {
        try {
            const filtroPromotor = document.getElementById('filtroPromotor')?.value || '';
            const filtroEstado = document.getElementById('filtroEstado')?.value || '';
            const filtroFecha = document.getElementById('filtroFecha')?.value || '';

            const params = new URLSearchParams({
                action: 'list',
                page: page,
                per_page: 10,
                filtro_promotor: filtroPromotor,
                filtro_estado: filtroEstado,
                filtro_fecha: filtroFecha
            });

            const response = await fetch(`../api/ruta_crud.php?${params}`);
            const result = await response.json();

            console.log('[v0] Respuesta de rutas:', result);

            const tbody = document.getElementById('rutasBody');
            tbody.innerHTML = '';

            if (!result.success) {
                console.error('[v0] Error en la respuesta:', result.message);
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Error: ${result.message}</td></tr>`;
                return;
            }

            const rutas = result.data || [];

            if (!Array.isArray(rutas)) {
                console.error('[v0] Los datos no son un array:', rutas);
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar rutas</td></tr>';
                return;
            }

            if (rutas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">No hay rutas registradas</td></tr>';
                updatePaginationInfo(0, 0, 0);
                return;
            }

            rutas.forEach(ruta => {
                const estadoBadge = {
                    'pendiente': 'bg-warning',
                    'en_progreso': 'bg-info',
                    'completada': 'bg-success'
                } [ruta.estado] || 'bg-secondary';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${ruta.nombre_ruta}</strong></td>
                    <td>${ruta.nombre_promotor}</td>
                    <td>${ruta.fecha_planificada}</td>
                    <td><span class="badge bg-primary">${ruta.num_puntos} puntos</span></td>
                    <td><span class="badge ${estadoBadge}">${ruta.estado}</span></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="verRutaEnMapa(${ruta.ruta_id})" title="Ver en mapa">
                            <i class="bi bi-map"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="editarRuta(${ruta.ruta_id})" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="eliminarRuta(${ruta.ruta_id})" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            if (result.pagination) {
                currentPage = result.pagination.page;
                totalPages = result.pagination.total_pages;
                updatePaginationInfo(result.pagination.total, result.pagination.page, result.pagination.per_page);
                renderPaginationControls(result.pagination.page, result.pagination.total_pages);
            }

        } catch (error) {
            console.error('[v0] Error al cargar rutas:', error);
            document.getElementById('rutasBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar rutas</td></tr>';
        }
    }

    function updatePaginationInfo(total, page, perPage) {
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);
        document.getElementById('paginationInfo').textContent = `Mostrando ${start}-${end} de ${total}`;
    }

    function renderPaginationControls(currentPage, totalPages) {
        const controls = document.getElementById('paginationControls');
        controls.innerHTML = '';

        if (totalPages <= 1) return;

        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" onclick="cargarRutas(${currentPage - 1}); return false;">Anterior</a>`;
        controls.appendChild(prevLi);

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" onclick="cargarRutas(${i}); return false;">${i}</a>`;
                controls.appendChild(li);
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                const li = document.createElement('li');
                li.className = 'page-item disabled';
                li.innerHTML = '<a class="page-link" href="#">...</a>';
                controls.appendChild(li);
            }
        }

        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" onclick="cargarRutas(${currentPage + 1}); return false;">Siguiente</a>`;
        controls.appendChild(nextLi);
    }

    function limpiarFiltros() {
        document.getElementById('filtroPromotor').value = '';
        document.getElementById('filtroEstado').value = '';
        document.getElementById('filtroFecha').value = '';
        cargarRutas(1);
    }

    async function verRutaEnMapa(rutaId) {
        try {
            const response = await fetch(`../api/ruta_crud.php?action=get&id=${rutaId}`);
            const result = await response.json();

            if (!result.success || !result.data) {
                alert('Error al cargar la ruta');
                return;
            }

            const ruta = result.data;

            if (!ruta.puntos || ruta.puntos.length === 0) {
                alert('Esta ruta no tiene puntos definidos');
                return;
            }

            rutaPlanificadaLayer.clearLayers();

            const latlngs = ruta.puntos.map(p => [parseFloat(p.latitud), parseFloat(p.longitud)]);

            if (ruta.puntos.length > 1) {
                try {
                    const routeResponse = await fetch('/promotores-campo-system/api/ruta_crud.php?action=calcular_ruta', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            puntos: ruta.puntos
                        })
                    });

                    if (routeResponse.ok) {
                        const routeResult = await routeResponse.json();

                        if (routeResult.success && routeResult.data && routeResult.data.geometry) {
                            // Convertir coordenadas de [lng, lat] a [lat, lng] para Leaflet
                            const routeCoordinates = routeResult.data.geometry.coordinates.map(coord => [coord[1], coord[0]]);

                            L.polyline(routeCoordinates, {
                                color: '#1e40af',
                                weight: 4,
                                opacity: 0.8
                            }).addTo(rutaPlanificadaLayer);

                            console.log(`[v0] Ruta calculada: ${routeResult.data.distancia_km} km, ${routeResult.data.tiempo_minutos} min`);
                        } else {
                            // Fallback: usar líneas rectas
                            L.polyline(latlngs, {
                                color: '#1e40af',
                                weight: 4,
                                dashArray: '10, 5'
                            }).addTo(rutaPlanificadaLayer);
                            console.warn('[v0] Usando líneas rectas como fallback');
                        }
                    } else {
                        // Fallback: usar líneas rectas si la respuesta no fue ok
                        L.polyline(latlngs, {
                            color: '#1e40af',
                            weight: 4,
                            dashArray: '10, 5'
                        }).addTo(rutaPlanificadaLayer);
                        console.warn('[v0] Fallback: Usando líneas rectas (respuesta HTTP no OK)');
                    }
                } catch (error) {
                    console.error('[v0] Error al calcular ruta:', error);
                    // Fallback: usar líneas rectas
                    L.polyline(latlngs, {
                        color: '#1e40af',
                        weight: 4,
                        dashArray: '10, 5'
                    }).addTo(rutaPlanificadaLayer);
                }
            }

            // Agregar marcadores
            ruta.puntos.forEach((punto, index) => {
                const marker = L.marker([parseFloat(punto.latitud), parseFloat(punto.longitud)], {
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: `<div style="background: #dc2626; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold;">${index + 1}</div>`,
                        iconSize: [30, 30]
                    })
                });

                marker.bindPopup(`
                    <strong>${punto.nombre}</strong><br>
                    ${punto.direccion || 'Sin dirección'}<br>
                    ${punto.nombre_empresa ? '<em>' + punto.nombre_empresa + '</em>' : ''}
                `);

                marker.addTo(rutaPlanificadaLayer);
            });

            map.fitBounds(latlngs);

        } catch (error) {
            console.error('[v0] Error al cargar ruta:', error);
            alert('Error al cargar la ruta');
        }
    }

    function nuevaRuta() {
        document.getElementById('formRuta').reset();
        document.getElementById('ruta_id').value = '';
        document.getElementById('modalRutaTitulo').textContent = 'Nueva Ruta';
        puntosRuta = [];
        actualizarListaPuntos();

        // Inicializar mapa de vista previa
        setTimeout(() => {
            if (!mapPreview) {
                mapPreview = L.map('mapPreview').setView([mapaConfig.lat, mapaConfig.lng], mapaConfig.zoom);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapPreview);

                // Agregar evento de clic para seleccionar ubicación manualmente
                mapPreview.on('click', function(e) {
                    // Si el modal de punto manual está abierto, actualizar sus coordenadas
                    const modalPuntoManual = document.getElementById('modalPuntoManual');
                    if (modalPuntoManual && bootstrap.Modal.getInstance(modalPuntoManual)) {
                        document.getElementById('punto_latitud').value = e.latlng.lat.toFixed(6);
                        document.getElementById('punto_longitud').value = e.latlng.lng.toFixed(6);

                        // Agregar marcador temporal
                        if (window.tempMarker) {
                            mapPreview.removeLayer(window.tempMarker);
                        }
                        window.tempMarker = L.marker(e.latlng).addTo(mapPreview);
                    }
                });
            } else {
                mapPreview.invalidateSize();
            }
        }, 300);

        new bootstrap.Modal(document.getElementById('modalRuta')).show();
    }

    async function cargarUbicacionesDisponibles() {
        const proyectoId = document.getElementById('proyecto_id').value;
        if (!proyectoId) return;

        try {
            const configResponse = await fetch(`/promotores-campo-system/api/ruta_crud.php?action=config_mapa&proyecto_id=${proyectoId}`);
            const configResult = await configResponse.json();

            if (configResult.success && configResult.data) {
                const config = configResult.data;
                mapaConfig = {
                    lat: parseFloat(config.mapa_centro_lat) || 4.570868,
                    lng: parseFloat(config.mapa_centro_lng) || -74.297333,
                    zoom: parseInt(config.mapa_zoom) || 6
                };

                console.log('[v0] Configuración del mapa cargada:', mapaConfig);

                if (mapPreview && !isNaN(mapaConfig.lat) && !isNaN(mapaConfig.lng)) {
                    mapPreview.setView([mapaConfig.lat, mapaConfig.lng], mapaConfig.zoom);
                }
            }

            const ubicResponse = await fetch(`/promotores-campo-system/api/ruta_crud.php?action=ubicaciones_disponibles&proyecto_id=${proyectoId}`);
            const ubicResult = await ubicResponse.json();

            if (ubicResult.success && Array.isArray(ubicResult.data)) {
                ubicacionesDisponibles = ubicResult.data;
                console.log('[v0] Ubicaciones disponibles cargadas:', ubicacionesDisponibles.length);
            } else {
                ubicacionesDisponibles = [];
                console.warn('[v0] No se pudieron cargar ubicaciones');
            }

        } catch (error) {
            console.error('[v0] Error al cargar ubicaciones:', error);
            ubicacionesDisponibles = [];
        }
    }

    function actualizarListaPuntos() {
        const lista = document.getElementById('listaPuntos');
        lista.innerHTML = '';

        if (puntosRuta.length === 0) {
            lista.innerHTML = '<div class="alert alert-info">No hay puntos agregados</div>';
            return;
        }

        puntosRuta.forEach((punto, index) => {
            const div = document.createElement('div');
            div.className = 'list-group-item';
            div.setAttribute('data-index', index);
            div.style.cursor = 'move';
            div.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <i class="bi bi-grip-vertical me-2"></i>
                        <strong>${index + 1}. ${punto.nombre}</strong><br>
                        <small class="text-muted">${punto.direccion}</small><br>
                        <small>Tiempo: ${punto.tiempo_estimado_minutos} min</small>
                    </div>
                    <div>
                        <!-- Add edit button for each point -->
                        <button class="btn btn-sm btn-warning me-1" onclick="editarPunto(${index})" title="Editar punto">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="eliminarPunto(${index})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            lista.appendChild(div);
        });

        new Sortable(lista, {
            animation: 150,
            handle: '.bi-grip-vertical',
            onEnd: function(evt) {
                const item = puntosRuta.splice(evt.oldIndex, 1)[0];
                puntosRuta.splice(evt.newIndex, 0, item);
                actualizarListaPuntos();
                actualizarMapaPreview();
            }
        });
    }

    async function actualizarMapaPreview() {
        if (!mapPreview) return;

        mapPreview.eachLayer(layer => {
            if (layer instanceof L.Marker || layer instanceof L.Polyline) {
                mapPreview.removeLayer(layer);
            }
        });

        if (puntosRuta.length === 0) return;

        const latlngs = puntosRuta.map(p => [p.latitud, p.longitud]);

        if (puntosRuta.length > 1) {
            try {
                const routeResponse = await fetch('/promotores-campo-system/api/ruta_crud.php?action=calcular_ruta', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        puntos: puntosRuta
                    })
                });

                if (routeResponse.ok) {
                    const routeResult = await routeResponse.json();

                    if (routeResult.success && routeResult.data && routeResult.data.geometry) {
                        // Convertir coordenadas de [lng, lat] a [lat, lng] para Leaflet
                        const routeCoordinates = routeResult.data.geometry.coordinates.map(coord => [coord[1], coord[0]]);

                        L.polyline(routeCoordinates, {
                            color: '#1e40af',
                            weight: 4,
                            opacity: 0.8
                        }).addTo(mapPreview);

                        console.log(`[v0] Ruta calculada: ${routeResult.data.distancia_km} km, ${routeResult.data.tiempo_minutos} min`);
                    } else {
                        // Fallback: usar líneas rectas
                        L.polyline(latlngs, {
                            color: '#1e40af',
                            weight: 4,
                            dashArray: '10, 5'
                        }).addTo(mapPreview);
                        console.warn('[v0] Usando líneas rectas como fallback');
                    }
                } else {
                    // Fallback: usar líneas rectas si la respuesta no fue ok
                    L.polyline(latlngs, {
                        color: '#1e40af',
                        weight: 4,
                        dashArray: '10, 5'
                    }).addTo(mapPreview);
                    console.warn('[v0] Fallback: Usando líneas rectas (respuesta HTTP no OK)');
                }
            } catch (error) {
                console.error('[v0] Error al calcular ruta:', error);
                // Fallback: usar líneas rectas
                L.polyline(latlngs, {
                    color: '#1e40af',
                    weight: 4,
                    dashArray: '10, 5'
                }).addTo(mapPreview);
            }
        }

        puntosRuta.forEach((punto, index) => {
            const marker = L.marker([punto.latitud, punto.longitud], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background: #dc2626; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">${index + 1}</div>`,
                    iconSize: [30, 30]
                })
            });

            marker.bindPopup(`
                <strong>${punto.nombre}</strong><br>
                <small>${punto.direccion}</small><br>
                <em>Tiempo estimado: ${punto.tiempo_estimado_minutos} min</em>
            `);

            marker.addTo(mapPreview);
        });

        if (latlngs.length > 0) {
            mapPreview.fitBounds(latlngs, {
                padding: [50, 50]
            });
        }
    }

    function eliminarPunto(index) {
        if (confirm('¿Eliminar este punto?')) {
            puntosRuta.splice(index, 1);
            actualizarListaPuntos();
            actualizarMapaPreview();
        }
    }

    document.getElementById('formRuta').addEventListener('submit', async (e) => {
        e.preventDefault();

        if (puntosRuta.length === 0) {
            alert('Debe agregar al menos un punto a la ruta');
            return;
        }

        const formData = new FormData(e.target);
        formData.append('action', document.getElementById('ruta_id').value ? 'update' : 'create');
        formData.append('puntos', JSON.stringify(puntosRuta));

        try {
            const response = await fetch('../api/ruta_crud.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                bootstrap.Modal.getInstance(document.getElementById('modalRuta')).hide();
                cargarRutas();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('[v0] Error al guardar ruta:', error);
            alert('Error al guardar ruta');
        }
    });

    // Editar ruta
    async function editarRuta(rutaId) { // Renamed parameter to match internal logic
        try {
            const response = await fetch(`../api/ruta_crud.php?action=get&id=${rutaId}`);
            const result = await response.json();

            if (!result.success || !result.data) {
                alert('Error al cargar la ruta');
                return;
            }

            const ruta = result.data;

            // Cargar datos del formulario
            document.getElementById('ruta_id').value = ruta.ruta_id || ruta.id; // Use ruta_id if available, otherwise fallback to id
            document.getElementById('nombre_ruta').value = ruta.nombre_ruta;
            document.getElementById('proyecto_id').value = ruta.proyecto_id;
            document.getElementById('promotor_id').value = ruta.promotor_user_id;
            document.getElementById('fecha_planificada').value = ruta.fecha_planificada;
            document.getElementById('modalRutaTitulo').textContent = 'Editar Ruta';

            // Cargar ubicaciones disponibles del proyecto
            await cargarUbicacionesDisponibles();

            // Cargar puntos de la ruta
            puntosRuta = [];
            if (ruta.puntos && Array.isArray(ruta.puntos)) {
                ruta.puntos.forEach(punto => {
                    puntosRuta.push({
                        nombre: punto.nombre,
                        direccion: punto.direccion || '',
                        latitud: parseFloat(punto.latitud),
                        longitud: parseFloat(punto.longitud),
                        tiempo_estimado_minutos: parseInt(punto.tiempo_estimado_minutos) || 30,
                        notas: punto.notas || '',
                        ubicacion_cliente_id: punto.ubicacion_cliente_id || null,
                        ruta_punto_id: punto.ruta_punto_id || null // Keep track of point ID if it exists
                    });
                });
            }

            actualizarListaPuntos();

            // Inicializar/actualizar mapa de vista previa
            const modalElement = document.getElementById('modalRuta');
            const modal = new bootstrap.Modal(modalElement);

            // Evento para inicializar mapa cuando el modal se muestre
            const shownListener = function initMapPreview() {
                console.log('[v0] Modal de edición mostrado, inicializando mapa');

                setTimeout(() => {
                    if (!mapPreview) {
                        console.log('[v0] Creando mapa de vista previa');
                        mapPreview = L.map('mapPreview').setView([mapaConfig.lat, mapaConfig.lng], mapaConfig.zoom);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap contributors'
                        }).addTo(mapPreview);

                        // Agregar evento de clic
                        mapPreview.on('click', function(e) {
                            const modalPuntoManual = document.getElementById('modalPuntoManual');
                            if (modalPuntoManual && bootstrap.Modal.getInstance(modalPuntoManual)) {
                                document.getElementById('punto_latitud').value = e.latlng.lat.toFixed(6);
                                document.getElementById('punto_longitud').value = e.latlng.lng.toFixed(6);

                                if (window.tempMarker) {
                                    mapPreview.removeLayer(window.tempMarker);
                                }
                                window.tempMarker = L.marker(e.latlng).addTo(mapPreview);
                            }
                        });
                    } else {
                        mapPreview.invalidateSize();
                    }

                    // Actualizar mapa con los puntos cargados
                    actualizarMapaPreview();
                }, 300);

                // Remover el listener después de usarlo
                modalElement.removeEventListener('shown.bs.modal', shownListener);
            };

            modalElement.addEventListener('shown.bs.modal', shownListener, {
                once: true
            });

            modal.show();

        } catch (error) {
            console.error('[v0] Error al cargar ruta para editar:', error);
            alert('Error al cargar la ruta');
        }
    }

    // Eliminar ruta
    async function eliminarRuta(id) {
        if (!confirm('¿Está seguro de eliminar esta ruta?')) return;

        try {
            const response = await fetch(`../api/ruta_crud.php?action=delete&id=${id}`, {
                method: 'DELETE'
            });
            const result = await response.json();

            if (result.success) {
                alert('Ruta eliminada exitosamente');
                cargarRutas();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('[v0] Error al eliminar ruta:', error);
            alert('Error al eliminar ruta');
        }
    }

    function agregarPuntoManual() {
        editingPointIndex = null;

        document.getElementById('punto_nombre').value = '';
        document.getElementById('punto_direccion').value = '';
        document.getElementById('punto_latitud').value = '';
        document.getElementById('punto_longitud').value = '';
        document.getElementById('punto_tiempo').value = '30';
        document.getElementById('punto_notas').value = '';
        if (document.getElementById('guardar_como_ubicacion')) {
            document.getElementById('guardar_como_ubicacion').checked = false;
        }

        document.querySelector('#modalPuntoManual .modal-title').textContent = 'Agregar Punto Manual';

        const modalElement = document.getElementById('modalPuntoManual');
        const modal = new bootstrap.Modal(modalElement);

        if (window.mapInitListener) {
            modalElement.removeEventListener('shown.bs.modal', window.mapInitListener);
        }

        window.mapInitListener = function() {
            setTimeout(() => {
                const mapContainer = document.getElementById('mapPuntoManual');

                if (!mapContainer) {
                    console.error('[v0] Error: Contenedor mapPuntoManual no encontrado en el DOM');
                    alert('Error: No se pudo inicializar el mapa. Por favor cierre y abra el modal nuevamente.');
                    return;
                }

                const rect = mapContainer.getBoundingClientRect();

                if (rect.width === 0 || rect.height === 0) {
                    console.error('[v0] Error: Contenedor del mapa no tiene dimensiones válidas');
                    alert('Error: El contenedor del mapa no está visible. Por favor intente nuevamente.');
                    return;
                }

                if (window.mapPuntoManual) {
                    try {
                        window.mapPuntoManual.off();
                        window.mapPuntoManual.remove();
                        window.mapPuntoManual = null;
                    } catch (e) {
                        console.error('[v0] Error al destruir mapa anterior:', e);
                    }
                }

                window.tempMarkerPuntoManual = null;

                try {
                    window.mapPuntoManual = L.map('mapPuntoManual', {
                        center: [mapaConfig.lat, mapaConfig.lng],
                        zoom: mapaConfig.zoom,
                        scrollWheelZoom: true
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(window.mapPuntoManual);

                    window.mapPuntoManual.on('click', function(e) {
                        document.getElementById('punto_latitud').value = e.latlng.lat.toFixed(6);
                        document.getElementById('punto_longitud').value = e.latlng.lng.toFixed(6);

                        if (window.tempMarkerPuntoManual) {
                            window.mapPuntoManual.removeLayer(window.tempMarkerPuntoManual);
                        }

                        window.tempMarkerPuntoManual = L.marker(e.latlng, {
                            icon: L.divIcon({
                                className: 'custom-marker',
                                html: '<div style="background: #059669; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold;"><i class="bi bi-geo-alt-fill"></i></div>',
                                iconSize: [30, 30]
                            })
                        }).addTo(window.mapPuntoManual);
                    });

                    setTimeout(() => {
                        if (window.mapPuntoManual) {
                            window.mapPuntoManual.invalidateSize();
                        }
                    }, 100);

                } catch (error) {
                    console.error('[v0] Error al crear mapa:', error);
                    alert('Error al inicializar el mapa: ' + error.message);
                }
            }, 300);
        };

        modalElement.addEventListener('shown.bs.modal', window.mapInitListener);
        modal.show();
    }

    function editarPunto(index) {
        if (index < 0 || index >= puntosRuta.length) {
            alert('Punto no válido');
            return;
        }

        editingPointIndex = index;
        const punto = puntosRuta[index];

        // Pre-fill form with existing point data
        document.getElementById('punto_nombre').value = punto.nombre || '';
        document.getElementById('punto_direccion').value = punto.direccion || '';
        document.getElementById('punto_latitud').value = punto.latitud || '';
        document.getElementById('punto_longitud').value = punto.longitud || '';
        document.getElementById('punto_tiempo').value = punto.tiempo_estimado_minutos || 30;
        document.getElementById('punto_notas').value = punto.notas || '';
        if (document.getElementById('guardar_como_ubicacion')) {
            document.getElementById('guardar_como_ubicacion').checked = false;
        }

        document.querySelector('#modalPuntoManual .modal-title').textContent = 'Editar Punto';

        const modalElement = document.getElementById('modalPuntoManual');
        const modal = new bootstrap.Modal(modalElement);

        if (window.mapInitListener) {
            modalElement.removeEventListener('shown.bs.modal', window.mapInitListener);
        }

        window.mapInitListener = function() {
            setTimeout(() => {
                const mapContainer = document.getElementById('mapPuntoManual');

                if (!mapContainer) {
                    console.error('[v0] Error: Contenedor mapPuntoManual no encontrado en el DOM');
                    alert('Error: No se pudo inicializar el mapa.');
                    return;
                }

                if (window.mapPuntoManual) {
                    try {
                        window.mapPuntoManual.off();
                        window.mapPuntoManual.remove();
                        window.mapPuntoManual = null;
                    } catch (e) {
                        console.error('[v0] Error al destruir mapa anterior:', e);
                    }
                }

                window.tempMarkerPuntoManual = null;

                try {
                    window.mapPuntoManual = L.map('mapPuntoManual', {
                        center: [punto.latitud, punto.longitud],
                        zoom: 15,
                        scrollWheelZoom: true
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(window.mapPuntoManual);

                    // Add marker for existing point
                    window.tempMarkerPuntoManual = L.marker([punto.latitud, punto.longitud], {
                        icon: L.divIcon({
                            className: 'custom-marker',
                            html: '<div style="background: #059669; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold;"><i class="bi bi-geo-alt-fill"></i></div>',
                            iconSize: [30, 30]
                        })
                    }).addTo(window.mapPuntoManual);

                    window.mapPuntoManual.on('click', function(e) {
                        document.getElementById('punto_latitud').value = e.latlng.lat.toFixed(6);
                        document.getElementById('punto_longitud').value = e.latlng.lng.toFixed(6);

                        if (window.tempMarkerPuntoManual) {
                            window.mapPuntoManual.removeLayer(window.tempMarkerPuntoManual);
                        }

                        window.tempMarkerPuntoManual = L.marker(e.latlng, {
                            icon: L.divIcon({
                                className: 'custom-marker',
                                html: '<div style="background: #059669; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold;"><i class="bi bi-geo-alt-fill"></i></div>',
                                iconSize: [30, 30]
                            })
                        }).addTo(window.mapPuntoManual);
                    });

                    setTimeout(() => {
                        if (window.mapPuntoManual) {
                            window.mapPuntoManual.invalidateSize();
                        }
                    }, 100);

                } catch (error) {
                    console.error('[v0] Error al crear mapa:', error);
                    alert('Error al inicializar el mapa: ' + error.message);
                }
            }, 300);
        };

        modalElement.addEventListener('shown.bs.modal', window.mapInitListener);
        modal.show();
    }

    async function geocodificarDireccion() {
        const direccion = document.getElementById('punto_direccion').value;
        if (!direccion) {
            alert('Por favor ingrese una dirección');
            return;
        }

        if (!window.mapPuntoManual || typeof window.mapPuntoManual.setView !== 'function') {
            alert('El mapa aún no está inicializado. Por favor espere un momento e intente nuevamente.');
            return;
        }

        try {
            const proyectoId = document.getElementById('proyecto_id').value;
            let pais = 'Colombia';

            if (proyectoId) {
                const configResponse = await fetch(`/promotores-campo-system/api/ruta_crud.php?action=config_mapa&proyecto_id=${proyectoId}`);
                const configResult = await configResponse.json();
                if (configResult.success && configResult.data) {
                    pais = configResult.data.pais || 'Colombia';
                }
            }

            const response = await fetch(`/promotores-campo-system/api/ruta_crud.php?action=geocode&direccion=${encodeURIComponent(direccion)}&pais=${pais}`);
            const result = await response.json();

            console.log('[v0] Resultado de geocodificación:', result);

            if (result.success && result.data && result.data.latitud && result.data.longitud) {
                document.getElementById('punto_latitud').value = result.data.latitud;
                document.getElementById('punto_longitud').value = result.data.longitud;

                if (window.mapPuntoManual && typeof window.mapPuntoManual.removeLayer === 'function') {
                    if (window.tempMarkerPuntoManual) {
                        window.mapPuntoManual.removeLayer(window.tempMarkerPuntoManual);
                    }

                    const lat = parseFloat(result.data.latitud);
                    const lng = parseFloat(result.data.longitud);

                    window.tempMarkerPuntoManual = L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: 'custom-marker',
                            html: '<div style="background: #059669; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold;"><i class="bi bi-geo-alt-fill"></i></div>',
                            iconSize: [30, 30]
                        })
                    }).addTo(window.mapPuntoManual);

                    window.mapPuntoManual.setView([lat, lng], 15);
                }

                alert('Coordenadas encontradas correctamente');
            } else {
                alert('No se pudo geocodificar la dirección: ' + (result.message || 'Error desconocido'));
            }
        } catch (error) {
            console.error('[v0] Error al geocodificar:', error);
            alert('Error al buscar coordenadas');
        }
    }

    function agregarDesdeUbicacion() {
        if (ubicacionesDisponibles.length === 0) {
            alert('No hay ubicaciones disponibles. Por favor seleccione un proyecto primero.');
            return;
        }

        const tbody = document.getElementById('ubicacionesBody');
        tbody.innerHTML = '';

        ubicacionesDisponibles.forEach(ubic => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${ubic.nombre_empresa || 'N/A'}</td>
                <td>${ubic.nombre_ubicacion}</td>
                <td>${ubic.direccion}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="seleccionarUbicacion(${ubic.id})">
                        Seleccionar
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        new bootstrap.Modal(document.getElementById('modalUbicaciones')).show();
    }

    function seleccionarUbicacion(ubicacionId) {
        const ubic = ubicacionesDisponibles.find(u => u.id == ubicacionId);
        if (!ubic) return;

        puntosRuta.push({
            nombre: ubic.nombre_ubicacion,
            direccion: ubic.direccion,
            latitud: parseFloat(ubic.latitud),
            longitud: parseFloat(ubic.longitud),
            tiempo_estimado_minutos: 30, // Valor por defecto, se puede hacer editable
            notas: `Cliente: ${ubic.nombre_empresa}`,
            ubicacion_cliente_id: ubic.id
        });

        actualizarListaPuntos();
        actualizarMapaPreview();

        bootstrap.Modal.getInstance(document.getElementById('modalUbicaciones')).hide();
    }

    async function guardarPuntoManual() {
        const nombre = document.getElementById('punto_nombre').value.trim();
        const direccion = document.getElementById('punto_direccion').value.trim();
        const latitud = parseFloat(document.getElementById('punto_latitud').value);
        const longitud = parseFloat(document.getElementById('punto_longitud').value);
        const tiempo = parseInt(document.getElementById('punto_tiempo').value);
        const notas = document.getElementById('punto_notas').value.trim();
        const guardarComoUbicacion = document.getElementById('guardar_como_ubicacion')?.checked || false;

        if (!nombre || !direccion || isNaN(latitud) || isNaN(longitud)) {
            alert('Por favor complete todos los campos obligatorios y asegúrese de que las coordenadas sean válidas');
            return;
        }

        if (latitud < -90 || latitud > 90 || longitud < -180 || longitud > 180) {
            alert('Las coordenadas no son válidas. Latitud debe estar entre -90 y 90, Longitud entre -180 y 180');
            return;
        }

        const punto = {
            nombre: nombre,
            direccion: direccion,
            latitud: latitud,
            longitud: longitud,
            tiempo_estimado_minutos: tiempo || 30,
            notas: notas
        };

        if (editingPointIndex !== null && editingPointIndex >= 0 && editingPointIndex < puntosRuta.length) {
            // Update existing point
            puntosRuta[editingPointIndex] = {
                ...puntosRuta[editingPointIndex],
                ...punto
            };
            editingPointIndex = null;
        } else {
            // Add new point
            puntosRuta.push(punto);
        }

        if (guardarComoUbicacion) {
            const proyectoId = document.getElementById('proyecto_id').value;
            if (!proyectoId) {
                alert('Por favor seleccione un proyecto primero');
                return;
            }

            try {
                // Obtener el cliente_id desde proyecto_clientes
                const proyectoClienteResponse = await fetch(`../api/ruta_crud.php?action=get_cliente_from_proyecto&proyecto_id=${proyectoId}`);
                const proyectoClienteResult = await proyectoClienteResponse.json();

                console.log('[v0] Respuesta get_cliente_from_proyecto:', proyectoClienteResult);

                if (proyectoClienteResult.success && proyectoClienteResult.data && proyectoClienteResult.data.cliente_id) {
                    const response = await fetch('../api/ubicacion_reutilizable_crud.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'create',
                            cliente_id: proyectoClienteResult.data.cliente_id,
                            nombre_ubicacion: nombre,
                            direccion: direccion,
                            latitud: latitud,
                            longitud: longitud,
                            notas: notas
                        })
                    });

                    const result = await response.json();
                    console.log('[v0] Respuesta de guardar ubicación:', result);

                    if (result.success) {
                        console.log('[v0] Ubicación guardada como reutilizable con ID:', result.data?.id);
                        // Reload available locations
                        await cargarUbicacionesDisponibles();
                        alert('Ubicación guardada exitosamente y estará disponible para futuras rutas');
                    } else {
                        console.warn('[v0] No se pudo guardar como ubicación reutilizable:', result.message);
                        alert('Advertencia: No se pudo guardar como ubicación reutilizable. ' + result.message);
                    }
                } else {
                    console.warn('[v0] No se pudo obtener el cliente_id del proyecto');
                    alert('Advertencia: No se pudo guardar como ubicación reutilizable porque no se encontró el cliente asociado al proyecto.');
                }
            } catch (error) {
                console.error('[v0] Error al guardar ubicación reutilizable:', error);
                alert('Error al guardar ubicación reutilizable: ' + error.message);
            }
        }

        actualizarListaPuntos();
        actualizarMapaPreview();

        bootstrap.Modal.getInstance(document.getElementById('modalPuntoManual')).hide();
    }
</script>

<?php include '../includes/footer.php'; ?>