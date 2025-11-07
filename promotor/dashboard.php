<?php
$pageTitle = 'Mi Jornada - Promotor';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/Jornada.php';
require_once __DIR__ . '/../db/Proyecto.php';

requireRole(['Promotor']);

$jornadaModel = new Jornada();
$proyectoModel = new Proyecto();

$promotorId = getUserId();
$jornadaActiva = $jornadaModel->getJornadaActiva($promotorId);
$jornadasRecientes = $jornadaModel->getByPromotor($promotorId, 5);
$proyectosAsignados = $proyectoModel->getByPromotor($promotorId);
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h4 mb-0">Mi Jornada</h1>
            <p class="text-muted">Bienvenido, <?php echo htmlspecialchars(getUserName()); ?></p>
        </div>
    </div>

    <?php if ($jornadaActiva): ?>
        <!-- Active Journey -->
        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-clock-fill"></i> Jornada Activa</h5>
            </div>
            <div class="card-body">
                <p><strong>Proyecto:</strong> <?php echo htmlspecialchars($jornadaActiva['nombre_proyecto'] ?? 'N/A'); ?></p>
                <p><strong>Check-in:</strong> <?php echo date('H:i', strtotime($jornadaActiva['check_in_time'])); ?></p>
                <p class="mb-3"><strong>Ubicación:</strong> <?php echo $jornadaActiva['check_in_lat']; ?>, <?php echo $jornadaActiva['check_in_lon']; ?></p>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-checkout btn-lg" onclick="realizarCheckout()">
                        <i class="bi bi-box-arrow-right"></i> Realizar Check-out
                    </button>
                    <a href="/promotor/actividades.php?jornada_id=<?php echo $jornadaActiva['id']; ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Registrar Actividad
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- No Active Journey -->
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Iniciar Jornada</h5>
            </div>
            <div class="card-body">
                <?php if (empty($proyectosAsignados)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> No tienes proyectos asignados. Contacta a tu supervisor.
                    </div>
                <?php else: ?>
                    <form id="checkinForm">
                        <div class="mb-3">
                            <label for="proyecto_id" class="form-label">Seleccionar Proyecto</label>
                            <select class="form-select" id="proyecto_id" name="proyecto_id" required>
                                <option value="">-- Seleccione un proyecto --</option>
                                <?php foreach ($proyectosAsignados as $proyecto): ?>
                                    <option value="<?php echo $proyecto['id']; ?>">
                                        <?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="check_in_foto" class="form-label">Foto de Check-in (Opcional)</label>
                            <input type="file" class="form-control" id="check_in_foto" accept="image/*" capture="environment">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-checkin btn-lg">
                                <i class="bi bi-geo-alt-fill"></i> Realizar Check-in
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Journeys -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Jornadas Recientes</h5>
        </div>
        <div class="card-body">
            <?php if (empty($jornadasRecientes)): ?>
                <p class="text-muted">No hay jornadas registradas</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($jornadasRecientes as $jornada): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($jornada['nombre_proyecto']); ?></h6>
                                    <p class="mb-1 small text-muted">
                                        <?php echo date('d/m/Y', strtotime($jornada['fecha_jornada'])); ?>
                                        - <?php echo date('H:i', strtotime($jornada['check_in_time'])); ?>
                                        <?php if ($jornada['check_out_time']): ?>
                                            a <?php echo date('H:i', strtotime($jornada['check_out_time'])); ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($jornada['horas_calculadas']): ?>
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> <?php echo $jornada['horas_calculadas']; ?> horas
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <span class="badge badge-<?php echo $jornada['estado_validacion']; ?>">
                                    <?php echo ucfirst($jornada['estado_validacion']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="spinner-overlay">
    <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Cargando...</span>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="position-fixed bottom-0 end-0 p-3" style="z-index: 11"></div>

<script>
    // Check-in functionality
    document.getElementById('checkinForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        try {
            showLoading();

            // Get current position
            const position = await getCurrentPosition();

            const formData = new FormData();
            formData.append('proyecto_id', document.getElementById('proyecto_id').value);
            formData.append('check_in_lat', position.lat);
            formData.append('check_in_lon', position.lon);

            // Handle photo upload (simulated)
            const photoFile = document.getElementById('check_in_foto').files[0];
            if (photoFile) {
                // In production, upload to server
                formData.append('check_in_foto_url', '/uploads/checkin_' + Date.now() + '.jpg');
            }

            const response = await fetch('/api/checkin.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            hideLoading();

            if (data.success) {
                showToast('Check-in realizado exitosamente', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Error al realizar check-in', 'error');
            }
        } catch (error) {
            hideLoading();
            showToast(error.message, 'error');
        }
    });

    // Check-out functionality
    async function realizarCheckout() {
        if (!confirm('¿Desea realizar el check-out?')) return;

        try {
            showLoading();

            const position = await getCurrentPosition();

            const formData = new FormData();
            formData.append('jornada_id', <?php echo $jornadaActiva['id'] ?? 0; ?>);
            formData.append('check_out_lat', position.lat);
            formData.append('check_out_lon', position.lon);

            const response = await fetch('/api/checkout.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            hideLoading();

            if (data.success) {
                showToast('Check-out realizado exitosamente', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Error al realizar check-out', 'error');
            }
        } catch (error) {
            hideLoading();
            showToast(error.message, 'error');
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>