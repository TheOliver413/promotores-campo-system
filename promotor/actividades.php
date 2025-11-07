<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/Actividad.php';
require_once '../db/TipoActividad.php';

checkAuth();
checkRole(['Promotor']);

$user_id = $_SESSION['user_id'];
$db = getDB();

$tipos_actividad = TipoActividad::getAll($db);

$pageTitle = 'Registro de Actividades';
include '../includes/header.php';
?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> Mis Actividades</h5>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalActividad">
                        <i class="bi bi-plus-lg"></i> Nueva Actividad
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="listaActividades">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Actividad -->
<div class="modal fade" id="modalActividad" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Registrar Actividad</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formActividad">
                    <div class="mb-3">
                        <label class="form-label">Tipo de Actividad *</label>
                        <select class="form-select" name="tipo_actividad_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($tipos_actividad as $tipo): ?>
                                <option value="<?= $tipo['tipo_actividad_id'] ?>">
                                    <?= htmlspecialchars($tipo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción *</label>
                        <textarea class="form-control" name="descripcion" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Evidencias (Fotos/Videos)</label>
                        <input type="file" class="form-control" id="evidencias" multiple accept="image/*,video/*" capture="environment">
                        <small class="text-muted">Puedes seleccionar múltiples archivos</small>
                    </div>

                    <div id="previewEvidencias" class="mb-3"></div>

                    <div class="alert alert-info">
                        <small><i class="bi bi-info-circle"></i> La ubicación GPS se registrará automáticamente</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarActividad()">
                    <i class="bi bi-save"></i> Guardar Actividad
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle Actividad -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye"></i> Detalle de Actividad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleActividad">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>
</div>

<script>
    let coordenadas = null;
    let evidenciasFiles = [];

    // Obtener ubicación
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                coordenadas = {
                    latitud: position.coords.latitude,
                    longitud: position.coords.longitude
                };
            },
            error => {
                console.error('[v0] Error obteniendo ubicación:', error);
            }
        );
    }

    // Preview de evidencias
    document.getElementById('evidencias').addEventListener('change', function(e) {
        evidenciasFiles = Array.from(e.target.files);
        const preview = document.getElementById('previewEvidencias');
        preview.innerHTML = '';

        evidenciasFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'd-inline-block position-relative m-1';
                div.innerHTML = `
                <img src="${e.target.result}" class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                        onclick="eliminarEvidencia(${index})" style="padding: 2px 6px;">
                    <i class="bi bi-x"></i>
                </button>
            `;
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    });

    function eliminarEvidencia(index) {
        evidenciasFiles.splice(index, 1);
        document.getElementById('evidencias').files = null;
        document.getElementById('previewEvidencias').innerHTML = '';
    }

    function guardarActividad() {
        if (!coordenadas) {
            alert('Esperando ubicación GPS...');
            return;
        }

        const form = document.getElementById('formActividad');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData(form);
        formData.append('action', 'create');
        formData.append('latitud', coordenadas.latitud);
        formData.append('longitud', coordenadas.longitud);

        // Agregar evidencias
        evidenciasFiles.forEach((file, index) => {
            formData.append(`evidencia_${index}`, file);
        });

        fetch('../api/actividad_crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Actividad registrada exitosamente');
                    bootstrap.Modal.getInstance(document.getElementById('modalActividad')).hide();
                    form.reset();
                    evidenciasFiles = [];
                    document.getElementById('previewEvidencias').innerHTML = '';
                    cargarActividades();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('[v0] Error:', error);
                alert('Error al guardar actividad');
            });
    }

    function cargarActividades() {
        fetch('../api/actividad_crud.php?action=list')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('listaActividades');
                if (data.success && data.actividades.length > 0) {
                    container.innerHTML = data.actividades.map(a => `
                    <div class="list-group-item list-group-item-action" onclick="verDetalle(${a.actividad_id})">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${a.tipo_actividad}</h6>
                                <p class="mb-1 text-muted small">${a.descripcion}</p>
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i> ${a.fecha_hora}
                                    ${a.evidencias_count > 0 ? `<i class="bi bi-paperclip ms-2"></i> ${a.evidencias_count}` : ''}
                                </small>
                            </div>
                            <span class="badge bg-${a.estado_validacion === 'Aprobado' ? 'success' : a.estado_validacion === 'Rechazado' ? 'danger' : 'warning'}">
                                ${a.estado_validacion}
                            </span>
                        </div>
                    </div>
                `).join('');
                } else {
                    container.innerHTML = '<div class="text-center py-4 text-muted">No hay actividades registradas</div>';
                }
            });
    }

    function verDetalle(id) {
        fetch(`../api/actividad_crud.php?action=detail&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const a = data.actividad;
                    document.getElementById('detalleActividad').innerHTML = `
                    <div class="mb-3">
                        <strong>Tipo:</strong> ${a.tipo_actividad}<br>
                        <strong>Fecha:</strong> ${a.fecha_hora}<br>
                        <strong>Ubicación:</strong> ${a.latitud}, ${a.longitud}<br>
                        <strong>Estado:</strong> <span class="badge bg-${a.estado_validacion === 'Aprobado' ? 'success' : a.estado_validacion === 'Rechazado' ? 'danger' : 'warning'}">${a.estado_validacion}</span>
                    </div>
                    <div class="mb-3">
                        <strong>Descripción:</strong>
                        <p>${a.descripcion}</p>
                    </div>
                    ${a.evidencias && a.evidencias.length > 0 ? `
                        <div class="mb-3">
                            <strong>Evidencias:</strong>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                ${a.evidencias.map(e => `
                                    <img src="${e.url}" class="rounded" style="width: 100px; height: 100px; object-fit: cover;">
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                    ${a.motivo_rechazo ? `
                        <div class="alert alert-danger">
                            <strong>Motivo de rechazo:</strong> ${a.motivo_rechazo}
                        </div>
                    ` : ''}
                `;
                    new bootstrap.Modal(document.getElementById('modalDetalle')).show();
                }
            });
    }

    cargarActividades();
</script>

<?php include '../includes/footer.php'; ?>