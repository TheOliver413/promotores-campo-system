<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/auth_helpers.php';
require_once '../db/ActaVisita.php';
require_once '../db/RutaPromotor.php';

checkAuth();
checkRole(['Promotor']);

$user_id = $_SESSION['user_id'];
$actaModel = new ActaVisita();
$rutaModel = new RutaPromotor();

$rutasAsignadas = $rutaModel->getByPromotor($user_id);
$rutasPendientes = array_filter($rutasAsignadas, function ($ruta) {
    return in_array($ruta['estado'], ['pendiente', 'en_progreso']);
});

// Get punto_id and ruta_id from URL parameters if creating new acta
$rutaId = $_GET['ruta_id'] ?? null;
$puntoNombre = $_GET['punto_nombre'] ?? '';
$puntoDireccion = $_GET['punto_direccion'] ?? '';

$pageTitle = 'Registrar Acta de Visita';
include '../includes/header.php';
?>

<style>
    .signature-pad {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        cursor: crosshair;
        background: white;
    }

    .photo-preview {
        position: relative;
        display: inline-block;
        margin: 5px;
    }

    .photo-preview img {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid #dee2e6;
    }

    .photo-preview .remove-photo {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .photo-info {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 5px;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="h3"><i class="bi bi-file-earmark-text me-2"></i>Registrar Acta de Visita</h2>
            <p class="text-muted">Documenta tu visita con información del receptor y evidencias</p>
        </div>
    </div>

    <form id="actaForm" enctype="multipart/form-data">
        <input type="hidden" name="ruta_promotor_id" value="<?php echo htmlspecialchars($rutaId ?? ''); ?>">
        <input type="hidden" name="promotor_user_id" value="<?php echo $user_id; ?>">
        <input type="hidden" id="firma_digital" name="firma_digital">
        <input type="hidden" id="latitud" name="latitud">
        <input type="hidden" id="longitud" name="longitud">

        <div class="row">
            <div class="col-lg-6 mb-4">
                <!-- Información del Punto de Visita -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Información del Punto de Visita</h5>
                    </div>
                    <div class="card-body">
                        <!-- Added route selector for pending/in-progress routes -->
                        <div class="mb-3">
                            <label for="ruta_promotor_id" class="form-label">Ruta Asignada</label>
                            <select class="form-select" id="ruta_promotor_id" name="ruta_promotor_id" onchange="cargarPuntosRuta()">
                                <option value="">Seleccionar ruta...</option>
                                <?php foreach ($rutasPendientes as $ruta): ?>
                                    <option value="<?php echo $ruta['ruta_promotor_id']; ?>"
                                        data-puntos='<?php echo htmlspecialchars($ruta['puntos_ruta']); ?>'
                                        <?php echo ($rutaId == $ruta['ruta_promotor_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ruta['nombre_ruta']); ?> -
                                        <?php echo htmlspecialchars($ruta['nombre_proyecto']); ?>
                                        (<?php echo ucfirst($ruta['estado']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Selecciona una ruta para cargar sus puntos de visita</small>
                        </div>

                        <!-- Added punto selector that populates from selected route -->
                        <div class="mb-3">
                            <label for="punto_visita_select" class="form-label">Punto de Visita *</label>
                            <select class="form-select" id="punto_visita_select" onchange="seleccionarPunto()">
                                <option value="">Seleccionar punto o escribir nuevo...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="punto_visita_nombre" class="form-label">Nombre del Punto *</label>
                            <input type="text" class="form-control" id="punto_visita_nombre" name="punto_visita_nombre"
                                value="<?php echo htmlspecialchars($puntoNombre); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="punto_visita_direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="punto_visita_direccion" name="punto_visita_direccion"
                                rows="2"><?php echo htmlspecialchars($puntoDireccion); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Información del Receptor -->
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-person me-2"></i>Información del Receptor</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="receptor_nombre" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="receptor_nombre" name="receptor_nombre" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="receptor_telefono" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="receptor_telefono" name="receptor_telefono">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="receptor_email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="receptor_email" name="receptor_email">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="receptor_direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="receptor_direccion" name="receptor_direccion" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="observacion" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observacion" name="observacion" rows="3"
                                placeholder="Agrega notas sobre la visita..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <!-- Firma Digital -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-pen me-2"></i>Firma Digital *</h5>
                            <button type="button" class="btn btn-sm btn-light" onclick="clearSignature()">
                                <i class="bi bi-x-circle"></i> Limpiar
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="signature-pad" class="signature-pad" width="500" height="200"></canvas>
                        <small class="text-muted">Dibuja la firma del receptor arriba</small>
                    </div>
                </div>

                <!-- Fotografías Geo-referenciadas -->
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-camera me-2"></i>Evidencias Fotográficas (3 requeridas) *</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="fotografias" class="form-label">Capturar Fotos</label>
                            <input type="file" class="form-control" id="fotografias"
                                accept="image/*" capture="environment" multiple
                                onchange="handleFileSelect(event)">
                            <small class="text-muted">
                                Las fotos serán geo-referenciadas automáticamente con tu ubicación actual
                            </small>
                        </div>
                        <div id="photos-preview" class="mt-3"></div>
                        <div id="photo-count" class="alert alert-info mt-2">
                            <i class="bi bi-info-circle me-2"></i>
                            <span id="count-text">0 de 3 fotos capturadas</span>
                        </div>
                    </div>
                </div>

                <!-- Ubicación -->
                <div class="card shadow-sm mt-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-geo-alt-fill text-primary me-2" style="font-size: 1.5rem;"></i>
                            <div>
                                <strong>Ubicación Actual:</strong><br>
                                <small id="location-status" class="text-muted">Obteniendo ubicación...</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <button type="button" class="btn btn-secondary me-2" onclick="window.history.back()">
                            <i class="bi bi-arrow-left me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-check-circle me-2"></i>Guardar Acta de Visita
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
    // Signature Pad
    const canvas = document.getElementById('signature-pad');
    const signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(0, 0, 0)'
    });

    function clearSignature() {
        signaturePad.clear();
    }

    // Photo handling
    let capturedPhotos = [];
    let currentLocation = null;

    function handleFileSelect(event) {
        const files = Array.from(event.target.files);

        files.forEach(file => {
            if (capturedPhotos.length >= 3) {
                alert('Máximo 3 fotos permitidas');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const photoData = {
                    file: file,
                    dataUrl: e.target.result,
                    latitude: currentLocation?.latitude || null,
                    longitude: currentLocation?.longitude || null,
                    timestamp: new Date().toISOString()
                };

                capturedPhotos.push(photoData);
                updatePhotosPreview();
            };
            reader.readAsDataURL(file);
        });
    }

    function updatePhotosPreview() {
        const container = document.getElementById('photos-preview');
        container.innerHTML = '';

        capturedPhotos.forEach((photo, index) => {
            const div = document.createElement('div');
            div.className = 'photo-preview';
            div.innerHTML = `
                <img src="${photo.dataUrl}" alt="Foto ${index + 1}">
                <button type="button" class="remove-photo" onclick="removePhoto(${index})">
                    <i class="bi bi-x"></i>
                </button>
                <div class="photo-info">
                    ${photo.latitude && photo.longitude ? 
                        `<i class="bi bi-geo-alt"></i> ${photo.latitude.toFixed(6)}, ${photo.longitude.toFixed(6)}` : 
                        '<i class="bi bi-geo-alt-fill text-danger"></i> Sin ubicación'}
                </div>
            `;
            container.appendChild(div);
        });

        updatePhotoCount();
    }

    function removePhoto(index) {
        capturedPhotos.splice(index, 1);
        updatePhotosPreview();
    }

    function updatePhotoCount() {
        const countText = document.getElementById('count-text');
        const countDiv = document.getElementById('photo-count');
        countText.textContent = `${capturedPhotos.length} de 3 fotos capturadas`;

        if (capturedPhotos.length >= 3) {
            countDiv.className = 'alert alert-success mt-2';
        } else {
            countDiv.className = 'alert alert-info mt-2';
        }
    }

    // Geolocation
    function getCurrentLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                position => {
                    currentLocation = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    };

                    document.getElementById('latitud').value = currentLocation.latitude;
                    document.getElementById('longitud').value = currentLocation.longitude;
                    document.getElementById('location-status').innerHTML =
                        `<span class="text-success">Lat: ${currentLocation.latitude.toFixed(6)}, Lng: ${currentLocation.longitude.toFixed(6)}</span>`;

                    console.log('[v0] Location obtained:', currentLocation);
                },
                error => {
                    console.error('[v0] Geolocation error:', error);
                    document.getElementById('location-status').innerHTML =
                        '<span class="text-danger">No se pudo obtener la ubicación</span>';
                }
            );
        } else {
            document.getElementById('location-status').innerHTML =
                '<span class="text-danger">Geolocalización no soportada</span>';
        }
    }

    // Form submission
    document.getElementById('actaForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validations
        if (signaturePad.isEmpty()) {
            alert('Por favor, captura la firma digital del receptor');
            return;
        }

        if (capturedPhotos.length < 3) {
            alert('Se requieren 3 fotografías para completar el acta');
            return;
        }

        if (!currentLocation) {
            alert('No se ha obtenido la ubicación. Por favor, espera un momento.');
            return;
        }

        // Get signature as base64
        const signatureData = signaturePad.toDataURL();
        document.getElementById('firma_digital').value = signatureData;

        // Prepare form data
        const formData = new FormData(this);

        // Add photos
        capturedPhotos.forEach((photo, index) => {
            formData.append(`foto_${index}`, photo.file);
            formData.append(`foto_${index}_lat`, photo.latitude);
            formData.append(`foto_${index}_lng`, photo.longitude);
        });

        // Disable submit button
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

        try {
            const response = await fetch('/promotores-campo-system/api/acta_crud.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                alert('Acta de visita registrada exitosamente');
                window.location.href = '/promotores-campo-system/promotor/historial.php';
            } else {
                alert('Error: ' + data.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Guardar Acta de Visita';
            }
        } catch (error) {
            console.error('[v0] Error:', error);
            alert('Error al guardar el acta de visita');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Guardar Acta de Visita';
        }
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        getCurrentLocation();
        // Load points if route is pre-selected
        if (document.getElementById('ruta_promotor_id').value) {
            cargarPuntosRuta();
        }
    });

    function cargarPuntosRuta() {
        const rutaSelect = document.getElementById('ruta_promotor_id');
        const puntoSelect = document.getElementById('punto_visita_select');
        const selectedOption = rutaSelect.options[rutaSelect.selectedIndex];

        // Clear previous options
        puntoSelect.innerHTML = '<option value="">Seleccionar punto o escribir nuevo...</option>';

        if (selectedOption.value) {
            const puntosData = selectedOption.getAttribute('data-puntos');
            if (puntosData) {
                try {
                    const puntos = JSON.parse(puntosData);
                    puntos.forEach(punto => {
                        const option = document.createElement('option');
                        option.value = JSON.stringify(punto);
                        option.textContent = punto.nombre || punto.direccion || 'Sin nombre';
                        puntoSelect.appendChild(option);
                    });
                } catch (e) {
                    console.error('[v0] Error parsing puntos:', e);
                }
            }
        }
    }

    function seleccionarPunto() {
        const puntoSelect = document.getElementById('punto_visita_select');
        const selectedValue = puntoSelect.value;

        if (selectedValue) {
            try {
                const punto = JSON.parse(selectedValue);
                document.getElementById('punto_visita_nombre').value = punto.nombre || '';
                document.getElementById('punto_visita_direccion').value = punto.direccion || '';
            } catch (e) {
                console.error('[v0] Error parsing punto:', e);
            }
        }
    }
</script>

<?php include '../includes/footer.php'; ?>