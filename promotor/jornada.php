<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/Jornada.php';
require_once '../db/User.php';
require_once '../includes/auth_helpers.php';

checkAuth();
checkRole(['Promotor']);

$user_id = $_SESSION['user_id'];
$db = getDB();

$jornadaModel = new Jornada();
$jornada_activa = $jornadaModel->getJornadaActivaHoy($user_id);
$puede_checkin = !$jornada_activa;
$puede_checkout = $jornada_activa && !$jornada_activa['check_out_time'];

$pageTitle = 'Mi Jornada';
include '../includes/header.php';
?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Mi Jornada</h5>
                </div>
                <div class="card-body">
                    <?php if ($jornada_activa): ?>
                        <div class="alert alert-success">
                            <h6 class="alert-heading"><i class="bi bi-check-circle"></i> Jornada Activa</h6>
                            <p class="mb-1"><strong>Check-in:</strong> <?= date('H:i', strtotime($jornada_activa['check_in_time'])) ?></p>
                            <p class="mb-0"><strong>Ubicación:</strong> <?= $jornada_activa['check_in_lat'] ?? 'N/A' ?>, <?= $jornada_activa['check_in_lon'] ?? 'N/A' ?></p>
                        </div>

                        <?php if ($jornada_activa['check_in_foto_url']): ?>
                            <div class="text-center mb-3">
                                <img src="<?= htmlspecialchars($jornada_activa['check_in_foto_url']) ?>"
                                    alt="Foto Check-in" class="img-fluid rounded" style="max-height: 200px;">
                            </div>
                        <?php endif; ?>

                        <?php if ($puede_checkout): ?>
                            <button type="button" class="btn btn-danger btn-lg w-100" onclick="realizarCheckout()">
                                <i class="bi bi-box-arrow-right"></i> Realizar Check-out
                            </button>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <p class="mb-1"><strong>Check-out:</strong> <?= date('H:i', strtotime($jornada_activa['check_out_time'])) ?></p>
                                <p class="mb-0"><strong>Horas trabajadas:</strong> <?= $jornada_activa['horas_calculadas'] ?> hrs</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> No hay jornada activa. Realiza tu check-in para comenzar.
                        </div>

                        <button type="button" class="btn btn-success btn-lg w-100" onclick="realizarCheckin()">
                            <i class="bi bi-box-arrow-in-right"></i> Realizar Check-in
                        </button>
                    <?php endif; ?>

                    <!-- Preview de foto -->
                    <div id="fotoPreview" class="mt-3 text-center" style="display: none;">
                        <img id="fotoImg" src="/placeholder.svg" alt="Preview" class="img-fluid rounded" style="max-height: 200px;">
                    </div>

                    <!-- Input oculto para captura de foto -->
                    <input type="file" id="fotoInput" accept="image/*" capture="environment" style="display: none;">
                </div>
            </div>

            <!-- Historial de jornadas -->
            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar3"></i> Historial de Jornadas</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="historialJornadas">
                        <div class="text-center py-3">
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

<script>
    let coordenadas = null;
    let fotoBlob = null;

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                coordenadas = {
                    latitud: position.coords.latitude,
                    longitud: position.coords.longitude
                };
                console.log('[v0] Ubicación obtenida:', coordenadas);
            },
            error => {
                console.error('[v0] Error obteniendo ubicación:', error);
                alert('No se pudo obtener la ubicación. Verifica los permisos del navegador.');
            }
        );
    }

    function realizarCheckin() {
        if (!coordenadas) {
            alert('Esperando ubicación GPS...');
            return;
        }

        document.getElementById('fotoInput').click();
        document.getElementById('fotoInput').onchange = function(e) {
            const file = e.target.files[0];
            if (!file) return;

            fotoBlob = file;

            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('fotoImg').src = e.target.result;
                document.getElementById('fotoPreview').style.display = 'block';
            };
            reader.readAsDataURL(file);

            if (confirm('¿Confirmar check-in con esta foto?')) {
                enviarCheckin();
            }
        };
    }

    function enviarCheckin() {
        const formData = new FormData();
        formData.append('action', 'checkin');
        formData.append('latitud', coordenadas.latitud);
        formData.append('longitud', coordenadas.longitud);
        formData.append('foto', fotoBlob);

        fetch('../api/jornada_crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Check-in realizado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('[v0] Error:', error);
                alert('Error al realizar check-in');
            });
    }

    function realizarCheckout() {
        if (!coordenadas) {
            alert('Esperando ubicación GPS...');
            return;
        }

        if (!confirm('¿Confirmar check-out?')) return;

        fetch('../api/jornada_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'checkout',
                    latitud: coordenadas.latitud,
                    longitud: coordenadas.longitud
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Check-out realizado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('[v0] Error:', error);
                alert('Error al realizar check-out');
            });
    }

    function cargarHistorial() {
        fetch('../api/jornada_crud.php?action=historial')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('historialJornadas');
                if (data.success && data.jornadas.length > 0) {
                    container.innerHTML = data.jornadas.map(j => `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">${j.fecha_jornada || 'Sin fecha'}</h6>
                                <small class="text-muted">
                                    ${j.check_in_time ? new Date(j.check_in_time).toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'}) : 'N/A'} - ${j.check_out_time ? new Date(j.check_out_time).toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'}) : 'En curso'}
                                </small>
                            </div>
                            <span class="badge bg-${j.estado_validacion === 'Aprobado' ? 'success' : j.estado_validacion === 'Rechazado' ? 'danger' : 'warning'}">
                                ${j.estado_validacion || 'Pendiente'}
                            </span>
                        </div>
                        ${j.horas_calculadas ? `<small class="text-muted">Horas: ${j.horas_calculadas}</small>` : ''}
                    </div>
                `).join('');
                } else {
                    container.innerHTML = '<div class="text-center py-3 text-muted">No hay jornadas registradas</div>';
                }
            });
    }

    cargarHistorial();
</script>

<?php include '../includes/footer.php'; ?>