<?php

require_once '../config/session.php';
require_once '../config/database.php';

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Obtener clientes asignados al supervisor a través de proyectos
$stmt = $db->prepare("
    SELECT DISTINCT c.id, c.nombre_empresa
    FROM clientes c
    INNER JOIN proyecto_clientes pc ON c.id = pc.cliente_id
    INNER JOIN proyectos p ON pc.proyecto_id = p.id
    INNER JOIN proyecto_promotores pp ON p.id = pp.proyecto_id
    INNER JOIN supervisor_promotores sp ON pp.promotor_user_id = sp.promotor_id
    WHERE sp.supervisor_id = ? AND c.activo = 1
    ORDER BY c.nombre_empresa
");
$stmt->execute([$_SESSION['user_id']]);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Gestión de Ubicaciones';
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Gestión de Ubicaciones de Clientes</h2>
                <button class="btn btn-primary" onclick="nuevaUbicacion()">
                    <i class="bi bi-plus-circle"></i> Nueva Ubicación
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Ubicaciones Registradas</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaUbicaciones">
                            <thead class="table-light">
                                <tr>
                                    <th>Cliente</th>
                                    <th>Ubicación</th>
                                    <th>Dirección</th>
                                    <th>Contacto</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
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

        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Mapa de Ubicaciones</h5>
                </div>
                <div class="card-body">
                    <div id="map" style="height: 500px; border-radius: 8px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear/Editar Ubicación -->
<div class="modal fade" id="modalUbicacion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalUbicacionTitulo">Nueva Ubicación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formUbicacion">
                <div class="modal-body">
                    <input type="hidden" id="ubicacion_id" name="ubicacion_id">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= $cliente['id'] ?>">
                                        <?= htmlspecialchars($cliente['nombre_empresa']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nombre de la Ubicación *</label>
                            <input type="text" class="form-control" id="nombre_ubicacion" name="nombre_ubicacion" required placeholder="Ej: Sede Principal">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Dirección Completa *</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" required placeholder="Ej: Calle 123 #45-67, Bogotá">
                        <button type="button" class="btn btn-sm btn-info mt-2" onclick="geocodificar()">
                            <i class="bi bi-search"></i> Buscar en Mapa
                        </button>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Latitud *</label>
                            <input type="number" step="0.000001" class="form-control" id="latitud" name="latitud" required readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Longitud *</label>
                            <input type="number" step="0.000001" class="form-control" id="longitud" name="longitud" required readonly>
                        </div>
                    </div>

                    <hr>
                    <h6>Información de Contacto (Opcional)</h6>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Nombre Contacto</label>
                            <input type="text" class="form-control" id="contacto_nombre" name="contacto_nombre">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="contacto_telefono" name="contacto_telefono">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="contacto_email" name="contacto_email">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notas</label>
                        <textarea class="form-control" id="notas" name="notas" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <div id="mapModal" style="height: 300px; border-radius: 8px;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Ubicación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let map;
    let mapModal;
    let marker;
    let ubicacionesMarkers = [];

    document.addEventListener('DOMContentLoaded', () => {
        // Mapa principal
        map = L.map('map').setView([4.570868, -74.297333], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        cargarUbicaciones();
    });

    async function cargarUbicaciones() {
        try {
            const response = await fetch('../api/ubicacion_crud.php?action=list');
            const data = await response.json();

            const tbody = document.getElementById('ubicacionesBody');
            tbody.innerHTML = '';

            // Limpiar marcadores anteriores
            ubicacionesMarkers.forEach(m => map.removeLayer(m));
            ubicacionesMarkers = [];

            if (!Array.isArray(data) || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">No hay ubicaciones registradas</td></tr>';
                return;
            }

            data.forEach(ubic => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${ubic.nombre_empresa}</strong></td>
                    <td>${ubic.nombre_ubicacion}</td>
                    <td><small>${ubic.direccion}</small></td>
                    <td><small>${ubic.contacto_nombre || '-'}<br>${ubic.contacto_telefono || ''}</small></td>
                    <td><span class="badge ${ubic.activo == 1 ? 'bg-success' : 'bg-secondary'}">${ubic.activo == 1 ? 'Activo' : 'Inactivo'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="verEnMapa(${ubic.latitud}, ${ubic.longitud}, '${ubic.nombre_ubicacion}')" title="Ver en mapa">
                            <i class="bi bi-map"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="editarUbicacion(${ubic.id})" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="eliminarUbicacion(${ubic.id})" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);

                // Agregar marcador al mapa
                const marker = L.marker([ubic.latitud, ubic.longitud])
                    .bindPopup(`<strong>${ubic.nombre_ubicacion}</strong><br>${ubic.nombre_empresa}`)
                    .addTo(map);
                ubicacionesMarkers.push(marker);
            });

            // Ajustar vista del mapa
            if (ubicacionesMarkers.length > 0) {
                const group = L.featureGroup(ubicacionesMarkers);
                map.fitBounds(group.getBounds().pad(0.1));
            }

        } catch (error) {
            console.error('Error al cargar ubicaciones:', error);
        }
    }

    function verEnMapa(lat, lng, nombre) {
        map.setView([lat, lng], 15);
        L.popup()
            .setLatLng([lat, lng])
            .setContent(`<strong>${nombre}</strong>`)
            .openOn(map);
    }

    function nuevaUbicacion() {
        document.getElementById('formUbicacion').reset();
        document.getElementById('ubicacion_id').value = '';
        document.getElementById('modalUbicacionTitulo').textContent = 'Nueva Ubicación';

        setTimeout(() => {
            if (!mapModal) {
                mapModal = L.map('mapModal').setView([4.570868, -74.297333], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapModal);

                mapModal.on('click', function(e) {
                    if (marker) {
                        mapModal.removeLayer(marker);
                    }
                    marker = L.marker(e.latlng).addTo(mapModal);
                    document.getElementById('latitud').value = e.latlng.lat.toFixed(6);
                    document.getElementById('longitud').value = e.latlng.lng.toFixed(6);
                });
            }
        }, 300);

        new bootstrap.Modal(document.getElementById('modalUbicacion')).show();
    }

    async function geocodificar() {
        const direccion = document.getElementById('direccion').value;
        if (!direccion) {
            alert('Por favor ingrese una dirección');
            return;
        }

        try {
            const response = await fetch(`../api/ruta_crud.php?action=geocode&direccion=${encodeURIComponent(direccion)}&pais=Colombia`);
            const data = await response.json();

            if (data.success) {
                document.getElementById('latitud').value = data.latitud;
                document.getElementById('longitud').value = data.longitud;

                if (mapModal) {
                    if (marker) {
                        mapModal.removeLayer(marker);
                    }
                    marker = L.marker([data.latitud, data.longitud]).addTo(mapModal);
                    mapModal.setView([data.latitud, data.longitud], 15);
                }

                alert('Ubicación encontrada en el mapa');
            } else {
                alert('No se pudo encontrar la dirección: ' + data.message);
            }
        } catch (error) {
            console.error('Error al geocodificar:', error);
            alert('Error al buscar la dirección');
        }
    }

    document.getElementById('formUbicacion').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(e.target);
        formData.append('action', document.getElementById('ubicacion_id').value ? 'update' : 'create');

        try {
            const response = await fetch('../api/ubicacion_crud.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                bootstrap.Modal.getInstance(document.getElementById('modalUbicacion')).hide();
                cargarUbicaciones();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error al guardar ubicación:', error);
            alert('Error al guardar ubicación');
        }
    });

    async function editarUbicacion(id) {
        try {
            const response = await fetch(`../api/ubicacion_crud.php?action=get&id=${id}`);
            const ubic = await response.json();

            document.getElementById('ubicacion_id').value = ubic.id;
            document.getElementById('cliente_id').value = ubic.cliente_id;
            document.getElementById('nombre_ubicacion').value = ubic.nombre_ubicacion;
            document.getElementById('direccion').value = ubic.direccion;
            document.getElementById('latitud').value = ubic.latitud;
            document.getElementById('longitud').value = ubic.longitud;
            document.getElementById('contacto_nombre').value = ubic.contacto_nombre || '';
            document.getElementById('contacto_telefono').value = ubic.contacto_telefono || '';
            document.getElementById('contacto_email').value = ubic.contacto_email || '';
            document.getElementById('notas').value = ubic.notas || '';
            document.getElementById('modalUbicacionTitulo').textContent = 'Editar Ubicación';

            setTimeout(() => {
                if (!mapModal) {
                    mapModal = L.map('mapModal').setView([ubic.latitud, ubic.longitud], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapModal);
                } else {
                    mapModal.setView([ubic.latitud, ubic.longitud], 15);
                }

                if (marker) {
                    mapModal.removeLayer(marker);
                }
                marker = L.marker([ubic.latitud, ubic.longitud]).addTo(mapModal);
            }, 300);

            new bootstrap.Modal(document.getElementById('modalUbicacion')).show();
        } catch (error) {
            console.error('Error al cargar ubicación:', error);
            alert('Error al cargar ubicación');
        }
    }

    async function eliminarUbicacion(id) {
        if (!confirm('¿Está seguro de eliminar esta ubicación?')) return;

        try {
            const response = await fetch(`../api/ubicacion_crud.php?action=delete&id=${id}`, {
                method: 'DELETE'
            });
            const result = await response.json();

            if (result.success) {
                alert('Ubicación eliminada exitosamente');
                cargarUbicaciones();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error al eliminar ubicación:', error);
            alert('Error al eliminar ubicación');
        }
    }
</script>

<?php include '../includes/footer.php'; ?>