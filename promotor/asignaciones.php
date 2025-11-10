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
<!-- Added Sortable.js for drag and drop reordering -->
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
        /* Removed gradient, using solid color */
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
        /* Added cursor for drag and drop */
        cursor: move;
    }

    .punto-item:hover {
        background: #f8f9fa;
        transform: translateX(5px);
    }

    /* Added styles for sortable ghost element */
    .sortable-ghost {
        opacity: 0.4;
        background: #e9ecef;
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
                    <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i> Mapa de Ruta</h5>
                </div>
                <div class="card-body p-0">
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
                    <!-- Added info about drag and drop reordering -->
                    <small class="d-block mt-1"><i class="bi bi-grip-vertical me-1"></i>Arrastra los puntos para reordenar</small>
                </div>
                <div class="card-body p-3">
                    <div id="listaPuntos"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let map;
    let ubicacionActual;
    let marcadores = [];
    let rutaActual = null;
    let polyline = null;
    let sortableInstance = null;
    const jornadaActiva = <?php echo $jornadaActiva ? 'true' : 'false'; ?>;

    map = L.map('map', {
        center: [4.6097, -74.0817],
        zoom: 13,
        maxBounds: [
            [-90, -180],
            [90, 180]
        ],
        maxBoundsViscosity: 1.0
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
        minZoom: 2
    }).addTo(map);

    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(
            position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                    return;
                }

                if (ubicacionActual) {
                    map.removeLayer(ubicacionActual);
                }

                ubicacionActual = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: '<div style="background: #1e40af; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.5);"></div>',
                        iconSize: [20, 20]
                    })
                }).addTo(map).bindPopup('Mi ubicación');

                if (!rutaActual) {
                    map.setView([lat, lng], 15);
                }
            },
            error => {}, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
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
                    return response.text().then(text => {
                        throw new TypeError("Response is not JSON");
                    });
                }

                return response.json();
            })
            .then(data => {
                hideLoading();

                if (data.success && data.ruta) {
                    rutaActual = data.ruta;
                    dibujarRuta(data.ruta);
                    mostrarDetalles(data.ruta);
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
                hideLoading();
                showToast('Error al cargar ruta: ' + error.message, 'error');
            });
    }

    async function calcularRutaOptimizada(puntos) {
        try {
            const puntosFormateados = puntos.map(p => ({
                latitud: parseFloat(p.latitud || p.lat),
                longitud: parseFloat(p.longitud || p.lng || p.lon)
            })).filter(p =>
                !isNaN(p.latitud) && !isNaN(p.longitud) &&
                p.latitud >= -90 && p.latitud <= 90 &&
                p.longitud >= -180 && p.longitud <= 180
            );

            if (puntosFormateados.length < 2) {
                return null;
            }

            const response = await fetch('../api/ruta_crud.php?action=calcular_ruta', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    puntos: puntosFormateados
                })
            });

            if (!response.ok) {
                return null;
            }

            const data = await response.json();

            if (data.success && data.data && data.data.geometry) {
                return data.data;
            }

            return null;
        } catch (error) {
            return null;
        }
    }

    async function dibujarRuta(ruta) {
        marcadores.forEach(m => map.removeLayer(m));
        marcadores = [];
        if (polyline) {
            map.removeLayer(polyline);
            polyline = null;
        }

        if (!ruta.puntos_ruta || ruta.puntos_ruta.length === 0) {
            showToast('Esta ruta no tiene puntos definidos', 'warning');
            return;
        }

        const bounds = [];
        const puntos = ruta.puntos_ruta;

        puntos.forEach((punto, index) => {
            const lat = parseFloat(punto.latitud || punto.lat);
            const lng = parseFloat(punto.longitud || punto.lng || punto.lon);

            if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                return;
            }

            const marker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: `<div class="punto-marcador" style="background: ${punto.completado || punto.visitado ? '#dc3545' : '#059669'}; color: white;">${index + 1}</div>`,
                    iconSize: [40, 40]
                })
            }).addTo(map);

            marker.bindPopup(`
                <div class="p-2">
                    <strong>${punto.nombre || 'Punto ' + (index + 1)}</strong><br>
                    ${punto.direccion || 'Sin dirección'}<br>
                    <span class="badge bg-${punto.completado || punto.visitado ? 'danger' : 'success'} mt-1">
                        ${punto.completado || punto.visitado ? 'Visitado' : 'Pendiente'}
                    </span>
                </div>
            `);

            marcadores.push(marker);
            bounds.push([lat, lng]);
        });

        if (puntos.length >= 2) {
            const rutaData = await calcularRutaOptimizada(puntos);

            if (rutaData && rutaData.geometry && rutaData.geometry.coordinates) {

                const coordinates = rutaData.geometry.coordinates
                    .map(coord => {
                        const lat = parseFloat(coord[1]);
                        const lng = parseFloat(coord[0]);

                        if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                            return null;
                        }
                        return [lat, lng];
                    })
                    .filter(coord => coord !== null);

                if (coordinates.length > 0) {
                    polyline = L.polyline(coordinates, {
                        color: '#667eea',
                        weight: 4,
                        opacity: 0.8
                    }).addTo(map);

                    if (rutaData.distancia_km && rutaData.tiempo_minutos) {
                        showToast(`Ruta: ${rutaData.distancia_km} km, ${rutaData.tiempo_minutos} min`, 'info');
                    }
                }
            } else {
                if (bounds.length > 1) {
                    polyline = L.polyline(bounds, {
                        color: '#667eea',
                        weight: 4,
                        opacity: 0.7,
                        dashArray: '10, 10'
                    }).addTo(map);
                }
            }
        }

        if (bounds.length > 0) {
            try {
                const validBounds = bounds.every(coord =>
                    !isNaN(coord[0]) && !isNaN(coord[1]) &&
                    coord[0] >= -90 && coord[0] <= 90 &&
                    coord[1] >= -180 && coord[1] <= 180
                );

                if (validBounds) {
                    map.fitBounds(bounds, {
                        padding: [50, 50],
                        maxZoom: 16
                    });
                } else {
                    const validPoint = bounds.find(coord =>
                        !isNaN(coord[0]) && !isNaN(coord[1]) &&
                        coord[0] >= -90 && coord[0] <= 90 &&
                        coord[1] >= -180 && coord[1] <= 180
                    );
                    if (validPoint) {
                        map.setView(validPoint, 13);
                    }
                }
            } catch (error) {
                if (bounds[0]) {
                    map.setView(bounds[0], 13);
                }
            }
        }
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
            }
        } catch (error) {
            // Silent fail
        }
    }

    function verPuntoDetalle(index) {
        if (!rutaActual || !rutaActual.puntos_ruta || !rutaActual.puntos_ruta[index]) return;

        const punto = rutaActual.puntos_ruta[index];
        const lat = parseFloat(punto.latitud || punto.lat);
        const lng = parseFloat(punto.longitud || punto.lng || punto.lon);

        if (!isNaN(lat) && !isNaN(lng)) {
            map.setView([lat, lng], 17);
            marcadores[index]?.openPopup();
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

        new bootstrap.Modal(document.getElementById('modalGestionarPunto')).show();
    }

    document.getElementById('punto_evidencias')?.addEventListener('change', function(e) {
        const preview = document.getElementById('preview_evidencias');
        preview.innerHTML = '';

        const files = Array.from(e.target.files);

        files.forEach((file, index) => {
            const col = document.createElement('div');
            col.className = 'col-md-4 col-6';

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    col.innerHTML = `
                        <div class="border rounded p-2 text-center">
                            <img src="${e.target.result}" class="img-fluid rounded mb-2" style="max-height: 100px; object-fit: cover;">
                            <small class="d-block text-truncate">${file.name}</small>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                col.innerHTML = `
                    <div class="border rounded p-2 text-center">
                        <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 3rem;"></i>
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
        const files = document.getElementById('punto_evidencias').files;

        if (!rutaActual || !rutaActual.puntos_ruta || !rutaActual.puntos_ruta[index]) {
            showToast('Error: Punto no encontrado', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'actualizar_punto');
        formData.append('ruta_id', rutaActual.id || rutaActual.ruta_id);
        formData.append('punto_index', index);
        formData.append('estado', estado);
        formData.append('notas', notas);

        for (let i = 0; i < files.length; i++) {
            formData.append('evidencias[]', files[i]);
        }

        try {
            const response = await fetch('../api/punto_ruta_crud.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showToast('Punto actualizado exitosamente', 'success');

                rutaActual.puntos_ruta[index].estado = estado;
                rutaActual.puntos_ruta[index].notas = notas;
                rutaActual.puntos_ruta[index].visitado = (estado !== 'pendiente');
                rutaActual.puntos_ruta[index].completado = (estado !== 'pendiente');

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
            map.setView(ubicacionActual.getLatLng(), 15);
        }
    }

    <?php if (count($rutas) > 0): ?>
        if (jornadaActiva) {
            seleccionarRuta(<?= $rutas[0]['ruta_promotor_id'] ?>);
        }
    <?php endif; ?>

    function showLoading() {
        // Implement loading state logic here
    }

    function hideLoading() {
        // Implement hide loading state logic here
    }

    function showToast(message, type) {
        if (type === 'error') {
            alert('Error: ' + message);
        } else if (type === 'warning') {
            alert('Advertencia: ' + message);
        }
        // Success and info are silent
    }
</script>

<?php include '../includes/footer.php'; ?>