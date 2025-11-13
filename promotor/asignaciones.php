<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/auth_helpers.php';
require_once '../db/Proyecto.php';
require_once '../db/RutaPromotor.php';
require_once '../db/Jornada.php';

checkAuth();
checkRole(['Promotor']);

$user_id = $_SESSION['user_id'];
$db = getDB();

$proyectoModel = new Proyecto();
$rutaModel = new RutaPromotor();
$jornadaModel = new Jornada();

$proyectos = $proyectoModel->getByPromotor($user_id);
$rutas = $rutaModel->getByPromotor($user_id);

$jornadaActiva = $jornadaModel->getJornadaActiva($user_id);

$pageTitle = 'Mis Asignaciones';
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
    .card-ruta {
        border: none;
        border-radius: 10px;
        transition: all 0.3s ease;
        cursor: pointer;
        border-left: 4px solid #667eea;
        margin-bottom: 0.5rem;
    }

    .card-ruta:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .card-ruta.active {
        background: #667eea;
        color: white;
        border-left-color: #fff;
    }

    .card-ruta.active .text-muted {
        color: rgba(255, 255, 255, 0.9) !important;
    }

    .punto-marcador {
        background: white;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .punto-item {
        transition: all 0.3s ease;
        border-left: 3px solid #667eea;
        cursor: move;
    }

    .punto-item:hover {
        background: #f8f9fa;
        transform: translateX(5px);
    }

    .sortable-ghost {
        opacity: 0.4;
        background: #e9ecef;
    }

    /* Added styles for empty map state */
    .map-empty-state {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        z-index: 1000;
        background: rgba(255, 255, 255, 0.95);
        padding: 2rem;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        max-width: 400px;
    }

    .map-empty-state i {
        font-size: 4rem;
        color: #667eea;
        margin-bottom: 1rem;
    }

    /* Custom marker for user location */
    .custom-marker-location {
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
        background-color: #3b82f6;
        width: 20px;
        height: 20px;
    }
</style>

<!-- Modal para informar cuando no hay jornada activa -->
<div class="modal fade" id="modalSinJornada" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Jornada No Iniciada
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-clock-history text-warning" style="font-size: 4rem;"></i>
                <h5 class="mt-3">Debes iniciar una jornada primero</h5>
                <p class="text-muted">
                    Para acceder a los detalles de las rutas y comenzar a trabajar en ellas,
                    necesitas realizar el check-in e iniciar tu jornada laboral.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <a href="dashboard.php" class="btn btn-success">
                    <i class="bi bi-play-circle me-2"></i>Ir a Iniciar Jornada
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Gestionar Punto -->
<div class="modal fade" id="modalGestionarPunto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-gear me-2"></i>
                    <span id="modalPuntoTitulo">Gestionar Punto</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="punto_index">

                <!-- Point Information -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="text-primary mb-3"><i class="bi bi-info-circle me-2"></i>Información del Punto</h6>
                        <div class="row">
                            <div class="col-md-12 mb-2">
                                <strong>Nombre:</strong>
                                <p id="punto_info_nombre" class="mb-0"></p>
                            </div>
                            <div class="col-md-12 mb-2">
                                <strong>Dirección:</strong>
                                <p id="punto_info_direccion" class="mb-0 text-muted"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Status -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="text-primary mb-3"><i class="bi bi-clipboard-check me-2"></i>Estado de Visita</h6>
                        <div class="mb-3">
                            <label class="form-label">Estado del Punto</label>
                            <select class="form-select" id="punto_estado">
                                <option value="pendiente">Pendiente</option>
                                <option value="visitado">Visitado</option>
                                <option value="venta">Venta Realizada</option>
                                <option value="cotizacion">Cotización</option>
                                <option value="no_efectiva">Visita No Efectiva</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" id="punto_notas" rows="3" placeholder="Agregar observaciones sobre esta visita..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Upload Evidence -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="text-primary mb-3"><i class="bi bi-camera me-2"></i>Evidencias</h6>
                        <div class="mb-3">
                            <label class="form-label">Subir Fotos/Documentos</label>
                            <input type="file" class="form-control" id="punto_evidencias" multiple accept="image/*,application/pdf">
                            <small class="text-muted">Puedes seleccionar múltiples archivos (imágenes o PDF)</small>
                        </div>
                        <div id="preview_evidencias" class="row g-2">
                            <!-- Previews will be shown here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarGestionPunto()">
                    <i class="bi bi-save me-2"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="h3"><i class="bi bi-map me-2"></i> Mis Asignaciones</h2>
            <p class="text-muted">Gestiona tus rutas y puntos de visita</p>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar -->
        <div class="col-12 col-lg-4 mb-3">
            <!-- Projects Card -->
            <div class="card shadow-sm mb-3" style="border-radius: 15px;">
                <div class="card-header bg-primary text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="mb-0"><i class="bi bi-briefcase me-2"></i> Mis Proyectos</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (count($proyectos) > 0): ?>
                            <?php foreach ($proyectos as $proyecto): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1 fw-bold"><?= htmlspecialchars($proyecto['nombre_proyecto'] ?? 'Sin nombre') ?></h6>
                                    <p class="mb-1 small text-muted"><?= htmlspecialchars($proyecto['descripcion'] ?? 'Sin descripción') ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar-range me-1"></i>
                                        <?php if (isset($proyecto['fecha_inicio'])): ?>
                                            <?= date('d/m/Y', strtotime($proyecto['fecha_inicio'])) ?> -
                                            <?= date('d/m/Y', strtotime($proyecto['fecha_fin'])) ?>
                                        <?php else: ?>
                                            Sin fechas definidas
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No tienes proyectos asignados</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Routes Card -->
            <div class="card shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-success text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="mb-0"><i class="bi bi-map me-2"></i> Mis Rutas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="listRutas">
                        <?php if (count($rutas) > 0): ?>
                            <?php foreach ($rutas as $ruta): ?>
                                <div class="list-group-item card-ruta"
                                    data-ruta-id="<?= $ruta['ruta_promotor_id'] ?>"
                                    onclick="seleccionarRuta(<?= $ruta['ruta_promotor_id'] ?>)">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1 fw-bold">
                                                <i class="bi bi-signpost me-2"></i>
                                                <?= htmlspecialchars($ruta['nombre_ruta'] ?? 'Ruta sin nombre') ?>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar me-1"></i>
                                                <?php if (isset($ruta['fecha_asignacion'])): ?>
                                                    <?= date('d/m/Y', strtotime($ruta['fecha_asignacion'])) ?>
                                                <?php else: ?>
                                                    Sin fecha
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?= ($ruta['estado'] ?? 'pendiente') === 'completada' ? 'success' : (($ruta['estado'] ?? 'pendiente') === 'en_progreso' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($ruta['estado'] ?? 'pendiente') ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-map" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No tienes rutas asignadas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-12 col-lg-8">
            <!-- Map Card -->
            <div class="card shadow-sm mb-3" style="border-radius: 15px;">
                <div class="card-header bg-primary text-white" style="border-radius: 15px 15px 0 0;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i> Mapa de Ruta</h5>
                        <!-- Added route control buttons similar to supervisor/rutas.php -->
                        <div class="btn-group" id="rutaControles" style="display:none;">
                            <button id="btnIniciarRuta" class="btn btn-success btn-sm" style="display:none;" onclick="iniciarRuta()">
                                <i class="bi bi-play-circle me-1"></i> Iniciar Ruta
                            </button>
                            <button id="btnPausarRuta" class="btn btn-warning btn-sm" style="display:none;" onclick="pausarRuta()">
                                <i class="bi bi-pause-circle me-1"></i> Pausar
                            </button>
                            <button id="btnReanudarRuta" class="btn btn-info btn-sm" style="display:none;" onclick="reanudarRuta()">
                                <i class="bi bi-play-circle me-1"></i> Reanudar
                            </button>
                            <button id="btnFinalizarRuta" class="btn btn-danger btn-sm" style="display:none;" onclick="finalizarRuta()">
                                <i class="bi bi-stop-circle me-1"></i> Finalizar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0" style="position: relative;">
                    <!-- Added empty state message for blank map -->
                    <div id="mapEmptyState" class="map-empty-state">
                        <i class="bi bi-map"></i>
                        <h5>Selecciona una ruta</h5>
                        <p class="text-muted mb-0">Elige una ruta de la lista para ver su recorrido en el mapa</p>
                    </div>
                    <div id="map" style="height: 500px; width: 100%; border-radius: 0 0 15px 15px;"></div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="badge bg-primary me-2"><i class="bi bi-circle-fill"></i> Mi Ubicación</span>
                            <span class="badge bg-success me-2"><i class="bi bi-circle-fill"></i> Pendientes</span>
                            <span class="badge bg-danger"><i class="bi bi-circle-fill"></i> Visitados</span>
                        </div>
                        <div>
                            <span id="infoRuta" class="badge bg-info me-2" style="display:none;">
                                <i class="bi bi-clock me-1"></i><span id="tiempoRuta">0</span> min
                                <i class="bi bi-signpost ms-2 me-1"></i><span id="distanciaRuta">0</span> km
                            </span>
                            <button class="btn btn-sm btn-outline-primary" onclick="centrarMapa()">
                                <i class="bi bi-crosshair me-1"></i> Centrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Route Details Card -->
            <div class="card shadow-sm" id="detallesRuta" style="display:none; border-radius: 15px;">
                <div class="card-header bg-info text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i> Puntos de la Ruta</h5>
                    <small class="d-block mt-1"><i class="bi bi-grip-vertical me-1"></i>Arrastra los puntos para reordenar</small>
                </div>
                <div class="card-body p-3">
                    <div id="listaPuntos"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
    <!-- Toasts will be injected here -->
</div>

<script>
    let map;
    let rutaPlanificadaLayer;
    let marcadoresLayer;
    let marcadoresUbicacionLayer; // Added layer for current location marker
    let marcadores = [];
    let rutaActual = null;
    let polyline = null;
    let routeCoordinates = null;
    let sortableInstance = null;

    let ubicacionActual = null;
    let marcadorUbicacion = null;
    let watchId = null;
    let trackingInterval = null;
    let bateriaNivel = null;

    let mapaConfig = {
        lat: 4.570868,
        lng: -74.297333,
        zoom: 6
    };

    const jornadaActiva = <?php echo $jornadaActiva ? 'true' : 'false'; ?>;

    document.addEventListener('DOMContentLoaded', () => {
        map = L.map('map').setView([mapaConfig.lat, mapaConfig.lng], mapaConfig.zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        rutaPlanificadaLayer = L.layerGroup().addTo(map);
        marcadoresLayer = L.layerGroup().addTo(map);
        marcadoresUbicacionLayer = L.layerGroup().addTo(map); // Initialize location layer

        if (jornadaActiva) {
            iniciarRastreoUbicacion();
            obtenerNivelBateria();
        }
    });

    function iniciarRastreoUbicacion() {
        if (!navigator.geolocation) {
            console.warn('[v0] Geolocation not supported');
            return;
        }

        watchId = navigator.geolocation.watchPosition(
            (position) => {
                ubicacionActual = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy
                };

                console.log('[v0] Current location updated:', ubicacionActual);

                actualizarMarcadorUbicacion();

                // Save location to database if route is in progress
                if (rutaActual && rutaActual.estado === 'en_progreso') {
                    guardarUbicacionTracking();
                }
            },
            (error) => {
                console.error('[v0] Geolocation error:', error);
                if (error.code === error.PERMISSION_DENIED) {
                    showToast('Permiso de ubicación denegado. Habilita el GPS para usar esta función.', 'warning');
                }
            }, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 5000
            }
        );
    }

    function actualizarMarcadorUbicacion() {
        if (!ubicacionActual) return;

        marcadoresUbicacionLayer.clearLayers();

        marcadorUbicacion = L.marker([ubicacionActual.lat, ubicacionActual.lng], {
            icon: L.divIcon({
                className: 'custom-marker-location',
                html: `
                    <div style="
                        background: #3b82f6;
                        border: 3px solid white;
                        border-radius: 50%;
                        width: 20px;
                        height: 20px;
                        box-shadow: 0 2px 10px rgba(59, 130, 246, 0.5);
                    "></div>
                `,
                iconSize: [20, 20]
            })
        }).addTo(marcadoresUbicacionLayer);

        marcadorUbicacion.bindPopup(`
            <div class="p-2">
                <strong><i class="bi bi-geo-alt-fill text-primary"></i> Tu ubicación</strong><br>
                <small class="text-muted">Precisión: ${Math.round(ubicacionActual.accuracy)}m</small>
            </div>
        `);

        // Add accuracy circle
        L.circle([ubicacionActual.lat, ubicacionActual.lng], {
            radius: ubicacionActual.accuracy,
            color: '#3b82f6',
            fillColor: '#3b82f6',
            fillOpacity: 0.1,
            weight: 1
        }).addTo(marcadoresUbicacionLayer);
    }

    async function obtenerNivelBateria() {
        try {
            if ('getBattery' in navigator) {
                const battery = await navigator.getBattery();
                bateriaNivel = Math.round(battery.level * 100);
                console.log('[v0] Battery level:', bateriaNivel + '%');

                battery.addEventListener('levelchange', () => {
                    bateriaNivel = Math.round(battery.level * 100);
                });
            }
        } catch (error) {
            console.warn('[v0] Battery API not available:', error);
        }
    }

    async function guardarUbicacionTracking() {
        if (!ubicacionActual) return;

        try {
            const response = await fetch('../api/tracking_ubicacion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    latitud: ubicacionActual.lat,
                    longitud: ubicacionActual.lng,
                    bateria_nivel: bateriaNivel
                })
            });

            const result = await response.json();

            if (!result.success) {
                console.warn('[v0] Failed to save tracking location:', result.error);
            } else {
                console.log('[v0] Location tracking saved successfully');
            }
        } catch (error) {
            console.error('[v0] Error saving tracking location:', error);
        }
    }

    function seleccionarRuta(rutaId) {
        if (!jornadaActiva) {
            const modal = new bootstrap.Modal(document.getElementById('modalSinJornada'));
            modal.show();
            return;
        }

        document.querySelectorAll('.card-ruta').forEach(el => el.classList.remove('active'));
        document.querySelector(`[data-ruta-id="${rutaId}"]`)?.classList.add('active');

        mostrarRuta(rutaId);
    }

    function mostrarRuta(rutaId) {
        showLoading();

        fetch(`../api/ruta_crud.php?action=detail&id=${rutaId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get("content-type");

                if (!contentType || !contentType.includes("application/json")) {
                    throw new TypeError("Response is not JSON");
                }

                return response.json();
            })
            .then(data => {
                console.log('[v0] Route data received:', data);
                hideLoading();

                if (data.success && data.ruta) {
                    rutaActual = data.ruta;
                    document.getElementById('mapEmptyState').style.display = 'none';
                    dibujarRuta(data.ruta);
                    mostrarDetalles(data.ruta);
                    actualizarControlesRuta();
                } else {
                    if (data.message && data.message.includes('no autorizado')) {
                        const modal = new bootstrap.Modal(document.getElementById('modalSinJornada'));
                        modal.show();
                    } else {
                        showToast('Error al cargar la ruta: ' + (data.message || 'Desconocido'), 'error');
                    }
                }
            })
            .catch(error => {
                console.error('[v0] Error loading route:', error);
                hideLoading();
                showToast('Error al cargar ruta: ' + error.message, 'error');
            });
    }

    function actualizarControlesRuta() {
        const controlesDiv = document.getElementById('rutaControles');
        const btnIniciar = document.getElementById('btnIniciarRuta');
        const btnPausar = document.getElementById('btnPausarRuta');
        const btnReanudar = document.getElementById('btnReanudarRuta');
        const btnFinalizar = document.getElementById('btnFinalizarRuta');

        if (!rutaActual) {
            controlesDiv.style.display = 'none';
            return;
        }

        controlesDiv.style.display = 'block';

        btnIniciar.style.display = 'none';
        btnPausar.style.display = 'none';
        btnReanudar.style.display = 'none';
        btnFinalizar.style.display = 'none';

        const estado = rutaActual.estado || 'pendiente';

        switch (estado) {
            case 'pendiente':
                btnIniciar.style.display = 'inline-block';
                break;
            case 'en_progreso':
                btnPausar.style.display = 'inline-block';
                btnFinalizar.style.display = 'inline-block';
                break;
            case 'pausada':
                btnReanudar.style.display = 'inline-block';
                btnFinalizar.style.display = 'inline-block';
                break;
            case 'completada':
                controlesDiv.style.display = 'none';
                break;
        }
    }

    async function iniciarRuta() {
        if (!rutaActual) {
            showToast('No hay ruta seleccionada', 'error');
            return;
        }

        if (!ubicacionActual) {
            showToast('Esperando ubicación GPS...', 'warning');
            return;
        }

        try {
            const response = await fetch('../api/ruta_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'iniciar_ruta',
                    ruta_id: rutaActual.id || rutaActual.ruta_id,
                    latitud_inicio: ubicacionActual.lat, // Use current location
                    longitud_inicio: ubicacionActual.lng
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('Ruta iniciada exitosamente', 'success');
                rutaActual.estado = 'en_progreso';
                actualizarControlesRuta();

                iniciarTrackingPeriodico();

                dibujarRuta(rutaActual);
            } else {
                showToast('Error al iniciar ruta: ' + (result.message || 'Desconocido'), 'error');
            }
        } catch (error) {
            showToast('Error de conexión al iniciar ruta', 'error');
            console.error('[v0] Error starting route:', error);
        }
    }

    function iniciarTrackingPeriodico() {
        if (trackingInterval) {
            clearInterval(trackingInterval);
        }

        // Save location every 30 seconds
        trackingInterval = setInterval(() => {
            if (rutaActual && rutaActual.estado === 'en_progreso') {
                guardarUbicacionTracking();
            } else {
                clearInterval(trackingInterval);
                trackingInterval = null;
            }
        }, 30000);
    }

    async function pausarRuta() {
        if (!rutaActual) {
            showToast('No hay ruta seleccionada', 'error');
            return;
        }

        try {
            const response = await fetch('../api/ruta_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'pausar_ruta',
                    ruta_id: rutaActual.id || rutaActual.ruta_id
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('Ruta pausada', 'success');
                rutaActual.estado = 'pausada';
                actualizarControlesRuta();

                if (trackingInterval) {
                    clearInterval(trackingInterval);
                    trackingInterval = null;
                }
            } else {
                showToast('Error al pausar ruta: ' + (result.message || 'Desconocido'), 'error');
            }
        } catch (error) {
            showToast('Error de conexión al pausar ruta', 'error');
            console.error('[v0] Error pausing route:', error);
        }
    }

    async function reanudarRuta() {
        if (!rutaActual) {
            showToast('No hay ruta seleccionada', 'error');
            return;
        }

        try {
            const response = await fetch('../api/ruta_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'reanudar_ruta',
                    ruta_id: rutaActual.id || rutaActual.ruta_id
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('Ruta reanudada', 'success');
                rutaActual.estado = 'en_progreso';
                actualizarControlesRuta();

                iniciarTrackingPeriodico();
            } else {
                showToast('Error al reanudar ruta: ' + (result.message || 'Desconocido'), 'error');
            }
        } catch (error) {
            showToast('Error de conexión al reanudar ruta', 'error');
            console.error('[v0] Error resuming route:', error);
        }
    }

    async function finalizarRuta() {
        if (!rutaActual) {
            showToast('No hay ruta seleccionada', 'error');
            return;
        }

        if (!confirm('¿Estás seguro de finalizar esta ruta? Esta acción no se puede deshacer.')) {
            return;
        }

        const latFin = ubicacionActual ? ubicacionActual.lat : 0;
        const lngFin = ubicacionActual ? ubicacionActual.lng : 0;

        try {
            const response = await fetch('../api/ruta_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'finalizar_ruta',
                    ruta_id: rutaActual.id || rutaActual.ruta_id,
                    latitud_fin: latFin,
                    longitud_fin: lngFin
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('Ruta finalizada exitosamente', 'success');
                rutaActual.estado = 'completada';
                actualizarControlesRuta();

                if (trackingInterval) {
                    clearInterval(trackingInterval);
                    trackingInterval = null;
                }

                const rutaCard = document.querySelector(`[data-ruta-id="${rutaActual.id || rutaActual.ruta_id}"]`);
                if (rutaCard) {
                    const badge = rutaCard.querySelector('.badge');
                    if (badge) {
                        badge.className = 'badge bg-success';
                        badge.textContent = 'Completada';
                    }
                }
            } else {
                showToast('Error al finalizar ruta: ' + (result.message || 'Desconocido'), 'error');
            }
        } catch (error) {
            showToast('Error de conexión al finalizar ruta', 'error');
            console.error('[v0] Error finishing route:', error);
        }
    }

    async function calcularRutaOptimizada(puntos) {
        try {
            let puntosParaCalculo = [];

            if (ubicacionActual && rutaActual && rutaActual.estado === 'en_progreso') {
                puntosParaCalculo.push({
                    latitud: ubicacionActual.lat,
                    longitud: ubicacionActual.lng
                });
                console.log('[v0] Added current location as starting point');
            }

            const puntosFormateados = puntos.map(p => ({
                latitud: parseFloat(p.latitud || p.lat),
                longitud: parseFloat(p.longitud || p.lng || p.lon)
            })).filter(p =>
                !isNaN(p.latitud) && !isNaN(p.longitud) &&
                p.latitud >= -90 && p.latitud <= 90 &&
                p.longitud >= -180 && p.longitud <= 180
            );

            puntosParaCalculo = puntosParaCalculo.concat(puntosFormateados);

            if (puntosParaCalculo.length < 2) {
                console.log('[v0] Not enough valid points for route calculation');
                return null;
            }

            console.log('[v0] Calculating route for points:', puntosParaCalculo);

            const response = await fetch('../api/ruta_crud.php?action=calcular_ruta', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    puntos: puntosParaCalculo
                })
            });

            if (!response.ok) {
                console.error('[v0] Route calculation request failed');
                return null;
            }

            const data = await response.json();
            console.log('[v0] Route calculation response:', data);

            if (data.success && data.data && data.data.geometry) {
                return data.data;
            }

            return null;
        } catch (error) {
            console.error('[v0] Error calculating optimized route:', error);
            return null;
        }
    }

    async function dibujarRuta(ruta) {
        console.log('[v0] Drawing route:', ruta);

        rutaPlanificadaLayer.clearLayers();
        marcadoresLayer.clearLayers();
        marcadores = [];
        routeCoordinates = null;

        if (!ruta.puntos_ruta || ruta.puntos_ruta.length === 0) {
            showToast('Esta ruta no tiene puntos definidos', 'warning');
            return;
        }

        const bounds = [];
        const puntos = ruta.puntos_ruta;

        if (ubicacionActual) {
            bounds.push([ubicacionActual.lat, ubicacionActual.lng]);
        }

        puntos.forEach((punto, index) => {
            const lat = parseFloat(punto.latitud || punto.lat);
            const lng = parseFloat(punto.longitud || punto.lng || punto.lon);

            if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                console.log(`[v0] Invalid coordinates for point ${index}:`, {
                    lat,
                    lng
                });
                return;
            }

            try {
                const marker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: `<div class="punto-marcador" style="background: ${punto.completado || punto.visitado ? '#dc3545' : '#059669'}; color: white;">${index + 1}</div>`,
                        iconSize: [40, 40]
                    })
                }).addTo(marcadoresLayer);

                marker.bindPopup(`
                    <div class="p-2">
                        <strong>${punto.nombre || 'Punto ' + (index + 1)}</strong><br>
                        ${punto.direccion || 'Sin dirección'}
                        <br>
                        <span class="badge bg-${punto.completado || punto.visitado ? 'danger' : 'success'} mt-1">
                            ${punto.completado || punto.visitado ? 'Visitado' : 'Pendiente'}
                        </span>
                    </div>
                `);

                marcadores.push(marker);
                bounds.push([lat, lng]);
            } catch (error) {
                console.error(`[v0] Error creating marker ${index}:`, error);
            }
        });

        if (puntos.length >= 1) {
            try {
                const rutaData = await calcularRutaOptimizada(puntos);

                if (rutaData && rutaData.geometry && rutaData.geometry.coordinates) {
                    const coordinates = [];

                    for (let i = 0; i < rutaData.geometry.coordinates.length; i++) {
                        const coord = rutaData.geometry.coordinates[i];

                        if (!Array.isArray(coord) || coord.length < 2) {
                            continue;
                        }

                        const lng = parseFloat(coord[0]);
                        const lat = parseFloat(coord[1]);

                        if (!isFinite(lat) || !isFinite(lng) ||
                            lat < -90 || lat > 90 ||
                            lng < -180 || lng > 180) {
                            continue;
                        }

                        coordinates.push([lat, lng]);
                    }

                    if (coordinates.length >= 2) {
                        routeCoordinates = coordinates;
                        console.log('[v0] Route coordinates prepared:', coordinates.length, 'points');

                        if (rutaData.distancia_km && rutaData.tiempo_minutos) {
                            document.getElementById('distanciaRuta').textContent = rutaData.distancia_km;
                            document.getElementById('tiempoRuta').textContent = rutaData.tiempo_minutos;
                            document.getElementById('infoRuta').style.display = 'inline-block';
                        }
                    } else {
                        console.warn('[v0] Not enough valid coordinates after filtering');
                    }
                } else {
                    console.warn('[v0] No geometry data in route response');
                }
            } catch (error) {
                console.error('[v0] Error calculating route:', error);
            }
        }

        if (bounds.length > 0) {
            await ajustarVistaMapa(bounds);
        }

        if (routeCoordinates && routeCoordinates.length >= 2) {
            await new Promise(resolve => setTimeout(resolve, 500));

            try {
                console.log('[v0] Drawing polyline with coordinates:', routeCoordinates.length);
                map.invalidateSize();

                map.whenReady(() => {
                    try {
                        const validCoords = routeCoordinates.filter(coord =>
                            Array.isArray(coord) &&
                            coord.length === 2 &&
                            isFinite(coord[0]) &&
                            isFinite(coord[1]) &&
                            coord[0] >= -90 && coord[0] <= 90 &&
                            coord[1] >= -180 && coord[1] <= 180
                        );

                        console.log('[v0] Valid coordinates for polyline:', validCoords.length);

                        if (validCoords.length < 2) {
                            console.warn('[v0] Not enough valid coordinates for polyline');
                            return;
                        }

                        polyline = L.polyline(validCoords, {
                            color: '#667eea',
                            weight: 4,
                            opacity: 0.8,
                            smoothFactor: 1.0
                        }).addTo(rutaPlanificadaLayer);

                        console.log('[v0] Polyline added successfully');

                    } catch (error) {
                        console.error('[v0] Error creating polyline:', error);
                    }
                });

            } catch (error) {
                console.error('[v0] Error creating/adding polyline:', error);
            }
        }
    }

    async function ajustarVistaMapa(coordenadas) {
        return new Promise((resolve) => {
            try {
                if (!map) {
                    resolve();
                    return;
                }

                const coordenadasValidas = coordenadas.filter(coord =>
                    Array.isArray(coord) &&
                    coord.length === 2 &&
                    !isNaN(coord[0]) && !isNaN(coord[1]) &&
                    isFinite(coord[0]) && isFinite(coord[1]) &&
                    coord[0] >= -90 && coord[0] <= 90 &&
                    coord[1] >= -180 && coord[1] <= 180
                );

                if (coordenadasValidas.length === 0) {
                    resolve();
                    return;
                }

                if (coordenadasValidas.length === 1) {
                    map.setView(coordenadasValidas[0], 15);
                    setTimeout(resolve, 600);
                    return;
                }

                let sumLat = 0,
                    sumLng = 0;
                coordenadasValidas.forEach(coord => {
                    sumLat += coord[0];
                    sumLng += coord[1];
                });
                const centerLat = sumLat / coordenadasValidas.length;
                const centerLng = sumLng / coordenadasValidas.length;

                let maxDist = 0;
                coordenadasValidas.forEach(coord => {
                    const dist = Math.sqrt(
                        Math.pow(coord[0] - centerLat, 2) +
                        Math.pow(coord[1] - centerLng, 2)
                    );
                    maxDist = Math.max(maxDist, dist);
                });

                let zoom = 15;
                if (maxDist > 0.5) zoom = 11;
                else if (maxDist > 0.2) zoom = 12;
                else if (maxDist > 0.1) zoom = 13;
                else if (maxDist > 0.05) zoom = 14;

                map.setView([centerLat, centerLng], zoom, {
                    animate: true,
                    duration: 0.5
                });

                setTimeout(resolve, 800);

            } catch (error) {
                console.error('[v0] Error adjusting map view:', error);
                resolve();
            }
        });
    }

    function mostrarDetalles(ruta) {
        const puntos = ruta.puntos_ruta || [];

        let html = '<div class="list-group" id="sortable-puntos">';
        puntos.forEach((punto, index) => {
            html += `
                <div class="list-group-item punto-item" data-index="${index}" style="cursor: move;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex align-items-start flex-grow-1">
                            <i class="bi bi-grip-vertical me-2 text-muted" style="font-size: 1.2rem; cursor: grab;"></i>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-primary me-2">${index + 1}</span>
                                    <strong>${punto.nombre || 'Punto ' + (index + 1)}</strong>
                                </div>
                                <p class="mb-1 small">
                                    <i class="bi bi-geo-alt me-1 text-primary"></i>
                                    ${punto.direccion || 'Sin dirección especificada'}
                                </p>
                                ${punto.notas ? `<p class="mb-0 small text-muted"><i class="bi bi-sticky me-1"></i>${punto.notas}</p>` : ''}
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-${punto.completado || punto.visitado ? 'danger' : 'success'} mb-1">
                                ${punto.completado || punto.visitado ? 'Visitado' : 'Pendiente'}
                            </span>
                            <br>
                            <button class="btn btn-sm btn-outline-primary mt-1" onclick="event.stopPropagation(); gestionarPunto(${index})">
                                <i class="bi bi-gear"></i> Gestionar
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        document.getElementById('listaPuntos').innerHTML = html;
        document.getElementById('detallesRuta').style.display = 'block';

        const sortableElement = document.getElementById('sortable-puntos');
        if (sortableElement && typeof Sortable !== 'undefined') {
            if (sortableInstance) {
                sortableInstance.destroy();
            }

            sortableInstance = new Sortable(sortableElement, {
                animation: 150,
                handle: '.bi-grip-vertical',
                ghostClass: 'sortable-ghost',
                onEnd: function(evt) {
                    const item = rutaActual.puntos_ruta.splice(evt.oldIndex, 1)[0];
                    rutaActual.puntos_ruta.splice(evt.newIndex, 0, item);

                    dibujarRuta(rutaActual);
                    mostrarDetalles(rutaActual);

                    guardarOrdenPuntos();
                }
            });
        }
    }

    async function guardarOrdenPuntos() {
        if (!rutaActual || !rutaActual.puntos_ruta) return;

        try {
            const response = await fetch('../api/punto_ruta_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'reordenar_puntos',
                    ruta_id: rutaActual.id || rutaActual.ruta_id,
                    puntos: rutaActual.puntos_ruta.map((p, index) => ({
                        id: p.punto_id || p.id || p.ruta_punto_id,
                        orden: index + 1
                    }))
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('Orden de puntos actualizado', 'success');
            } else {
                showToast('Error al guardar el orden de puntos: ' + (result.message || 'Desconocido'), 'error');
            }
        } catch (error) {
            showToast('Error de conexión al guardar el orden de puntos', 'error');
        }
    }

    function verPuntoDetalle(index) {
        if (!rutaActual || !rutaActual.puntos_ruta || !rutaActual.puntos_ruta[index]) return;

        const punto = rutaActual.puntos_ruta[index];
        const lat = parseFloat(punto.latitud || punto.lat);
        const lng = parseFloat(punto.longitud || punto.lng || punto.lon);

        if (!isNaN(lat) && !isNaN(lng)) {
            map.setView([lat, lng], 17);
            if (marcadores[index]) {
                marcadores[index].openPopup();
            }
        }
    }

    function gestionarPunto(index) {
        if (!rutaActual || !rutaActual.puntos_ruta || !rutaActual.puntos_ruta[index]) return;

        const punto = rutaActual.puntos_ruta[index];

        document.getElementById('punto_index').value = index;
        document.getElementById('modalPuntoTitulo').textContent = `Gestionar Punto ${index + 1}`;
        document.getElementById('punto_info_nombre').textContent = punto.nombre || `Punto ${index + 1}`;
        document.getElementById('punto_info_direccion').textContent = punto.direccion || 'Sin dirección especificada';

        const estadoActual = punto.estado || (punto.completado || punto.visitado ? 'visitado' : 'pendiente');
        document.getElementById('punto_estado').value = estadoActual;
        document.getElementById('punto_notas').value = punto.notas || '';
        document.getElementById('punto_evidencias').value = '';
        document.getElementById('preview_evidencias').innerHTML = '';

        if (punto.evidencias && punto.evidencias.length > 0) {
            const preview = document.getElementById('preview_evidencias');
            punto.evidencias.forEach(evidencia => {
                const col = document.createElement('div');
                col.className = 'col-md-4 col-6';
                const fileName = evidencia.url.split('/').pop();
                const fileType = evidencia.tipo || fileName.split('.').pop().toLowerCase();

                if (fileType.startsWith('image')) {
                    col.innerHTML = `
                        <div class="border rounded p-2 text-center">
                            <img src="${evidencia.url}" class="img-fluid rounded mb-2" style="max-height: 100px; object-fit: cover;">
                            <small class="d-block text-truncate">${fileName}</small>
                        </div>
                    `;
                } else if (fileType === 'pdf') {
                    col.innerHTML = `
                        <div class="border rounded p-2 text-center">
                            <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 3rem;"></i>
                            <small class="d-block text-truncate">${fileName}</small>
                        </div>
                    `;
                }
                preview.appendChild(col);
            });
        }

        new bootstrap.Modal(document.getElementById('modalGestionarPunto')).show();
    }

    document.getElementById('punto_evidencias')?.addEventListener('change', function(e) {
        const preview = document.getElementById('preview_evidencias');
        preview.innerHTML = '';

        const files = Array.from(e.target.files);

        files.forEach((file, index) => {
            const col = document.createElement('div');
            col.className = 'col-md-4 col-6 mb-3';

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    col.innerHTML = `
                        <div class="border rounded p-2 text-center">
                            <img src="${event.target.result}" class="img-fluid rounded mb-2" style="max-height: 100px; object-fit: cover;">
                            <small class="d-block text-truncate">${file.name}</small>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                col.innerHTML = `
                    <div class="border rounded p-2 text-center">
                        <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 3rem;"></i>
                        <small class="d-block text-truncate">${file.name}</small>
                    </div>
                `;
            } else {
                col.innerHTML = `
                    <div class="border rounded p-2 text-center">
                        <i class="bi bi-file-earmark text-secondary" style="font-size: 3rem;"></i>
                        <small class="d-block text-truncate">${file.name}</small>
                    </div>
                `;
            }

            preview.appendChild(col);
        });
    });

    async function guardarGestionPunto() {
        const index = parseInt(document.getElementById('punto_index').value);
        const estado = document.getElementById('punto_estado').value;
        const notas = document.getElementById('punto_notas').value;
        const filesInput = document.getElementById('punto_evidencias');
        const files = filesInput.files;

        if (!rutaActual || !rutaActual.puntos_ruta || !rutaActual.puntos_ruta[index]) {
            showToast('Error: Punto no encontrado', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'actualizar_punto');
        formData.append('ruta_id', rutaActual.id || rutaActual.ruta_id);
        formData.append('punto_index', index);
        formData.append('punto_id', rutaActual.puntos_ruta[index].punto_id || rutaActual.puntos_ruta[index].id || rutaActual.puntos_ruta[index].ruta_punto_id);
        formData.append('estado', estado);
        formData.append('notas', notas);

        if (files.length > 0) {
            for (let i = 0; i < files.length; i++) {
                formData.append('evidencias[]', files[i]);
            }
        }

        try {
            const response = await fetch('../api/punto_ruta_crud.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showToast('Punto actualizado exitosamente', 'success');

                const puntoActualizado = result.punto_data;
                rutaActual.puntos_ruta[index] = {
                    ...rutaActual.puntos_ruta[index],
                    ...puntoActualizado,
                    estado: estado,
                    notas: notas,
                    visitado: (estado !== 'pendiente'),
                    completado: (estado !== 'pendiente'),
                    evidencias: puntoActualizado.evidencias || rutaActual.puntos_ruta[index].evidencias
                };

                dibujarRuta(rutaActual);
                mostrarDetalles(rutaActual);

                bootstrap.Modal.getInstance(document.getElementById('modalGestionarPunto')).hide();
            } else {
                showToast('Error al actualizar punto: ' + (result.message || 'Desconocido'), 'error');
            }
        } catch (error) {
            showToast('Error de conexión al guardar punto', 'error');
        }
    }

    function centrarMapa() {
        if (ubicacionActual) {
            map.setView([ubicacionActual.lat, ubicacionActual.lng], 15);
        } else if (rutaActual && rutaActual.puntos_ruta && rutaActual.puntos_ruta.length > 0) {
            const primerPunto = rutaActual.puntos_ruta[0];
            const lat = parseFloat(primerPunto.latitud || primerPunto.lat);
            const lng = parseFloat(primerPunto.longitud || primerPunto.lng || primerPunto.lon);
            if (!isNaN(lat) && !isNaN(lng)) {
                map.setView([lat, lng], 14);
            }
        } else {
            map.setView([4.570868, -74.297333], 6);
        }
    }

    function showLoading() {
        // Implementar lógica de carga
    }

    function hideLoading() {
        // Implementar lógica de ocultar carga
    }

    function showToast(message, type) {
        console.log(`[${type.toUpperCase()}] ${message}`);
        const toastContainer = document.getElementById('toast-container') || createToastContainer();
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info'} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }

    window.addEventListener('beforeunload', () => {
        if (watchId) {
            navigator.geolocation.clearWatch(watchId);
        }
        if (trackingInterval) {
            clearInterval(trackingInterval);
        }
    });
</script>

<?php include '../includes/footer.php'; ?>