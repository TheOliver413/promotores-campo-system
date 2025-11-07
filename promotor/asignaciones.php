<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/Proyecto.php';
require_once '../db/RutaPromotor.php';

checkAuth();
checkRole(['Promotor']);

$user_id = $_SESSION['user_id'];
$db = getDB();

$proyectos = Proyecto::getByPromotor($db, $user_id);
$rutas = RutaPromotor::getByPromotor($db, $user_id);

$pageTitle = 'Mis Asignaciones';
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="container-fluid py-3">
    <div class="row">
        <div class="col-12 col-lg-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-briefcase"></i> Mis Proyectos</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (count($proyectos) > 0): ?>
                            <?php foreach ($proyectos as $proyecto): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1"><?= htmlspecialchars($proyecto['nombre']) ?></h6>
                                    <p class="mb-1 small text-muted"><?= htmlspecialchars($proyecto['descripcion']) ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i>
                                        <?= date('d/m/Y', strtotime($proyecto['fecha_inicio'])) ?> -
                                        <?= date('d/m/Y', strtotime($proyecto['fecha_fin'])) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3 text-muted">No tienes proyectos asignados</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-map"></i> Mis Rutas</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (count($rutas) > 0): ?>
                            <?php foreach ($rutas as $ruta): ?>
                                <a href="#" class="list-group-item list-group-item-action"
                                    onclick="mostrarRuta(<?= $ruta['ruta_promotor_id'] ?>); return false;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($ruta['nombre_ruta']) ?></h6>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($ruta['fecha_asignacion'])) ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?= $ruta['estado'] === 'Completado' ? 'success' : ($ruta['estado'] === 'En Progreso' ? 'warning' : 'secondary') ?>">
                                            <?= $ruta['estado'] ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3 text-muted">No tienes rutas asignadas</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Mapa de Ruta</h5>
                </div>
                <div class="card-body p-0">
                    <div id="map" style="height: 500px; width: 100%;"></div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-primary me-2">● Ubicación Actual</span>
                            <span class="badge bg-success me-2">● Puntos de Ruta</span>
                            <span class="badge bg-danger">● Visitados</span>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="centrarMapa()">
                            <i class="bi bi-crosshair"></i> Centrar
                        </button>
                    </div>
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

    // Inicializar mapa
    map = L.map('map').setView([4.6097, -74.0817], 13); // Bogotá por defecto

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Obtener ubicación actual
    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(
            position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

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
            error => console.error('[v0] Error obteniendo ubicación:', error), {
                enableHighAccuracy: true
            }
        );
    }

    function mostrarRuta(rutaId) {
        fetch(`../api/ruta_crud.php?action=detail&id=${rutaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    rutaActual = data.ruta;
                    dibujarRuta(data.ruta);
                }
            });
    }

    function dibujarRuta(ruta) {
        // Limpiar marcadores anteriores
        marcadores.forEach(m => map.removeLayer(m));
        marcadores = [];

        if (!ruta.puntos_ruta) return;

        const puntos = JSON.parse(ruta.puntos_ruta);
        const bounds = [];

        puntos.forEach((punto, index) => {
            const marker = L.marker([punto.lat, punto.lng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background: ${punto.visitado ? '#dc3545' : '#059669'}; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">${index + 1}</div>`,
                    iconSize: [30, 30]
                })
            }).addTo(map);

            marker.bindPopup(`
            <strong>${punto.nombre || 'Punto ' + (index + 1)}</strong><br>
            ${punto.direccion || ''}<br>
            ${punto.visitado ? '<span class="badge bg-danger">Visitado</span>' : '<span class="badge bg-success">Pendiente</span>'}
        `);

            marcadores.push(marker);
            bounds.push([punto.lat, punto.lng]);
        });

        // Dibujar línea de ruta
        if (bounds.length > 1) {
            L.polyline(bounds, {
                color: '#1e40af',
                weight: 3,
                opacity: 0.7,
                dashArray: '10, 10'
            }).addTo(map);
        }

        // Ajustar vista
        if (bounds.length > 0) {
            map.fitBounds(bounds, {
                padding: [50, 50]
            });
        }
    }

    function centrarMapa() {
        if (ubicacionActual) {
            map.setView(ubicacionActual.getLatLng(), 15);
        }
    }

    // Cargar primera ruta si existe
    <?php if (count($rutas) > 0): ?>
        mostrarRuta(<?= $rutas[0]['ruta_promotor_id'] ?>);
    <?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>