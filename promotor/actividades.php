<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/Actividad.php';
require_once '../db/TipoActividad.php';
require_once '../db/Jornada.php';

checkAuth();
checkRole(['Promotor']);

$user_id = $_SESSION['user_id'];
$db = getDB();

$jornadaModel = new Jornada();
$jornadaActiva = $jornadaModel->getJornadaActiva($user_id);

$tipos_actividad = TipoActividad::getAll($db);

$pageTitle = 'Registro de Actividades';
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .card-actividad {
        transition: all 0.3s ease;
        border-left: 4px solid #667eea;
    }

    .card-actividad:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .preview-image {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
    }

    .badge-estado {
        font-size: 0.85rem;
        padding: 0.4rem 0.8rem;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h3 mb-1"><i class="bi bi-clipboard-check me-2"></i>Mis Actividades</h2>
                    <p class="text-muted mb-0">Registra y gestiona tus actividades diarias</p>
                </div>
                <?php if ($jornadaActiva): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalActividad">
                        <i class="bi bi-plus-circle me-2"></i>Nueva Actividad
                    </button>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled title="Debes iniciar jornada primero">
                        <i class="bi bi-lock me-2"></i>Nueva Actividad
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!$jornadaActiva): ?>
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Jornada no iniciada.</strong> Debes hacer check-in desde el <a href="dashboard.php" class="alert-link">dashboard</a> para registrar actividades.
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm" style="border-radius: 15px;">
                <div class="card-body p-0">
                    <div id="listaActividades" class="p-3">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-3 text-muted">Cargando actividades...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Actividad -->
<div class="modal fade" id="modalActividad" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px;">
            <div class="modal-header bg-primary text-white" style="border-radius: 15px 15px 0 0;">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Registrar Actividad</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formActividad">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tipo de Actividad *</label>
                            <select class="form-select" name="tipo_actividad_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($tipos_actividad as $tipo): ?>
                                    <option value="<?= $tipo['tipo_actividad_id'] ?>">
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tiempo Invertido (minutos)</label>
                            <input type="number" class="form-control" name="tiempo_minutos" min="1" value="30">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Descripción *</label>
                        <textarea class="form-control" name="descripcion" rows="4" required
                            placeholder="Describe la actividad realizada..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Evidencias (Fotos/Videos)</label>
                        <input type="file" class="form-control" id="evidencias" multiple
                            accept="image/*,video/*" capture="environment">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>Puedes seleccionar múltiples archivos
                        </small>
                    </div>

                    <div id="previewEvidencias" class="mb-3 d-flex flex-wrap gap-2"></div>

                    <div class="alert alert-info mb-0">
                        <i class="bi bi-geo-alt me-2"></i>
                        <strong>Ubicación GPS:</strong> <span id="gpsStatus">Obteniendo coordenadas...</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarActividad()">
                    <i class="bi bi-save me-2"></i>Guardar Actividad
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle Actividad -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 15px;">
            <div class="modal-header bg-info text-white" style="border-radius: 15px 15px 0 0;">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Detalle de Actividad</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleActividad">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver imagen completa -->
<div class="modal fade" id="modalImagenCompleta" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark">
            <div class="modal-header border-0">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imagenCompleta" src="/placeholder.svg" style="max-width: 100%; max-height: 80vh;">
            </div>
        </div>
    </div>
</div>

<script>
    let coordenadas = null;
    let evidenciasFiles = [];

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                coordenadas = {
                    latitud: position.coords.latitude,
                    longitud: position.coords.longitude
                };
                document.getElementById('gpsStatus').innerHTML =
                    `<span class="text-success"><i class="bi bi-check-circle me-1"></i>Coordenadas obtenidas</span>`;
            },
            error => {
                console.error('[v0] Error obteniendo ubicación:', error);
                document.getElementById('gpsStatus').innerHTML =
                    `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Error obteniendo ubicación</span>`;
            }
        );
    }

    document.getElementById('evidencias').addEventListener('change', function(e) {
        evidenciasFiles = Array.from(e.target.files);
        const preview = document.getElementById('previewEvidencias');
        preview.innerHTML = '';

        evidenciasFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'position-relative';
                div.innerHTML = `
                    <img src="${e.target.result}" class="preview-image" 
                         onclick="verImagenCompleta('${e.target.result}')"
                         title="Click para ver completa">
                    <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                            onclick="eliminarEvidencia(${index})" style="padding: 2px 8px; border-radius: 50%;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                `;
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    });

    function eliminarEvidencia(index) {
        evidenciasFiles.splice(index, 1);

        // Recreate FileList
        const dt = new DataTransfer();
        evidenciasFiles.forEach(file => dt.items.add(file));
        document.getElementById('evidencias').files = dt.files;

        // Re-render preview
        document.getElementById('evidencias').dispatchEvent(new Event('change'));
    }

    function verImagenCompleta(src) {
        document.getElementById('imagenCompleta').src = src;
        new bootstrap.Modal(document.getElementById('modalImagenCompleta')).show();
    }

    function guardarActividad() {
        if (!coordenadas) {
            showToast('Esperando ubicación GPS. Por favor intenta nuevamente.', 'warning');
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

        showLoading();

        fetch('../api/actividad_crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();

                if (data.success) {
                    showToast('Actividad registrada exitosamente', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalActividad')).hide();
                    form.reset();
                    evidenciasFiles = [];
                    document.getElementById('previewEvidencias').innerHTML = '';
                    cargarActividades();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('[v0] Error:', error);
                showToast('Error al guardar actividad', 'error');
            });
    }

    function cargarActividades() {
        fetch('../api/actividad_crud.php?action=list')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('listaActividades');

                if (data.success && data.actividades && data.actividades.length > 0) {
                    container.innerHTML = data.actividades.map(a => `
                        <div class="card card-actividad mb-3" onclick="verDetalle(${a.actividad_id})" 
                             style="cursor: pointer;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-clipboard-check text-primary me-2" style="font-size: 1.5rem;"></i>
                                            <h5 class="mb-0">${a.tipo_actividad}</h5>
                                        </div>
                                        <p class="mb-2 text-muted">${a.descripcion}</p>
                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>${a.fecha_hora}
                                            </small>
                                            ${a.evidencias_count > 0 ? `
                                                <small class="text-muted">
                                                    <i class="bi bi-paperclip me-1"></i>${a.evidencias_count} evidencia(s)
                                                </small>
                                            ` : ''}
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge badge-estado bg-${
                                            a.estado_validacion === 'Aprobado' ? 'success' : 
                                            a.estado_validacion === 'Rechazado' ? 'danger' : 
                                            'warning'
                                        }">
                                            ${a.estado_validacion}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                            <p class="mt-3 text-muted">No hay actividades registradas</p>
                            ${<?= $jornadaActiva ? 'true' : 'false' ?> ? 
                                '<button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#modalActividad"><i class="bi bi-plus-circle me-2"></i>Registrar Primera Actividad</button>' : 
                                '<p class="text-muted">Inicia tu jornada para registrar actividades</p>'
                            }
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('[v0] Error al cargar actividades:', error);
                document.getElementById('listaActividades').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>Error al cargar actividades
                    </div>
                `;
            });
    }

    function verDetalle(id) {
        showLoading();

        fetch(`../api/actividad_crud.php?action=detail&id=${id}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();

                if (data.success) {
                    const a = data.actividad;
                    document.getElementById('detalleActividad').innerHTML = `
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-2"><strong><i class="bi bi-tag me-2"></i>Tipo:</strong> ${a.tipo_actividad}</p>
                                <p class="mb-2"><strong><i class="bi bi-calendar3 me-2"></i>Fecha:</strong> ${a.fecha_hora}</p>
                                <p class="mb-2"><strong><i class="bi bi-geo-alt me-2"></i>Ubicación:</strong> ${a.latitud}, ${a.longitud}</p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-2"><strong>Estado:</strong></p>
                                <span class="badge badge-estado bg-${
                                    a.estado_validacion === 'Aprobado' ? 'success' : 
                                    a.estado_validacion === 'Rechazado' ? 'danger' : 
                                    'warning'
                                }">${a.estado_validacion}</span>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <h6 class="fw-bold"><i class="bi bi-card-text me-2"></i>Descripción:</h6>
                            <p class="text-muted">${a.descripcion}</p>
                        </div>
                        
                        ${a.evidencias && a.evidencias.length > 0 ? `
                            <hr>
                            <div class="mb-3">
                                <h6 class="fw-bold"><i class="bi bi-images me-2"></i>Evidencias:</h6>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    ${a.evidencias.map(e => `
                                        <img src="${e.url}" class="preview-image" 
                                             onclick="verImagenCompleta('${e.url}')"
                                             title="Click para ver completa">
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        ${a.motivo_rechazo ? `
                            <hr>
                            <div class="alert alert-danger">
                                <h6 class="fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Motivo de rechazo:</h6>
                                <p class="mb-0">${a.motivo_rechazo}</p>
                            </div>
                        ` : ''}
                    `;
                    new bootstrap.Modal(document.getElementById('modalDetalle')).show();
                } else {
                    showToast('Error al cargar detalle: ' + data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('[v0] Error:', error);
                showToast('Error al cargar detalle de actividad', 'error');
            });
    }

    // Cargar actividades al iniciar
    cargarActividades();
</script>

<?php include '../includes/footer.php'; ?>