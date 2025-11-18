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
$jornadasRecientes = $jornadaModel->getByPromotor($promotorId, 10);
$proyectosAsignados = $proyectoModel->getByPromotor($promotorId);

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("SELECT COUNT(*) as total FROM jornadas WHERE promotor_user_id = ? AND MONTH(fecha_jornada) = MONTH(CURRENT_DATE())");
$stmt->execute([$promotorId]);
$jornadasMes = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT SUM(horas_calculadas) as total FROM jornadas WHERE promotor_user_id = ? AND MONTH(fecha_jornada) = MONTH(CURRENT_DATE()) AND horas_calculadas IS NOT NULL");
$stmt->execute([$promotorId]);
$horasMes = $stmt->fetch()['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM rutas_promotores WHERE promotor_user_id = ? AND estado = 'pendiente'");
$stmt->execute([$promotorId]);
$rutasPendientes = $stmt->fetch()['total'];
?>

<div class="container-fluid py-4">
    <!-- Aplicar diseño del supervisor/dashboard.php -->
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Mi Jornada</h2>
                    <p class="text-muted mb-0">
                        <i class="bi bi-calendar-check me-2"></i>
                        <?php echo strftime('%A, %d de %B de %Y'); ?>
                    </p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Actualizar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Estadísticas -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase mb-2">Jornadas Este Mes</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $jornadasMes; ?></h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-calendar-check-fill text-primary fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase mb-2">Horas Trabajadas</h6>
                            <h2 class="mb-0 fw-bold"><?php echo number_format($horasMes, 1); ?>h</h2>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-clock-fill text-success fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase mb-2">Rutas Pendientes</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $rutasPendientes; ?></h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-map-fill text-warning fs-3"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="asignaciones.php" class="text-decoration-none small">
                            Ver rutas <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase mb-2">Estado</h6>
                            <h2 class="mb-0 fw-bold">
                                <?php echo $jornadaActiva ? 'ACTIVO' : 'INACTIVO'; ?>
                            </h2>
                        </div>
                        <div class="bg-<?php echo $jornadaActiva ? 'success' : 'secondary'; ?> bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-circle-fill text-<?php echo $jornadaActiva ? 'success' : 'secondary'; ?> fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Columna Principal -->
        <div class="col-lg-8 mb-4">
            <?php if ($jornadaActiva): ?>
                <!-- Sección de información de jornada activa -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-success text-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history me-2"></i>
                                Jornada en Curso
                            </h5>
                            <span class="badge bg-light text-success">ACTIVA</span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <h6 class="text-muted mb-2"><i class="bi bi-clock me-2"></i>Hora de Check-in</h6>
                                <p class="h5 mb-0"><?php echo date('H:i A', strtotime($jornadaActiva['check_in_time'])); ?></p>
                                <small class="text-muted"><?php echo date('d/m/Y', strtotime($jornadaActiva['check_in_time'])); ?></small>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-muted mb-2"><i class="bi bi-hourglass-split me-2"></i>Tiempo Transcurrido</h6>
                                <p class="h5 mb-0" id="tiempoTranscurrido">00:00</p>
                                <small class="text-muted">minutos</small>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-muted mb-2"><i class="bi bi-briefcase me-2"></i>Proyecto</h6>
                                <p class="h6 mb-0"><?php echo htmlspecialchars($jornadaActiva['nombre_proyecto'] ?? 'Sin proyecto'); ?></p>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex">
                            <button type="button" class="btn btn-danger flex-fill" onclick="realizarCheckout()">
                                <i class="bi bi-box-arrow-right me-2"></i> Finalizar Jornada (Check-out)
                            </button>
                            <a href="asignaciones.php" class="btn btn-outline-primary flex-fill">
                                <i class="bi bi-map me-2"></i> Ver Mis Rutas
                            </a>
                        </div>
                    </div>
                </div>

                <script>
                    function actualizarTiempo() {
                        const inicio = new Date('<?php echo $jornadaActiva['check_in_time']; ?>');
                        const ahora = new Date();
                        const diff = ahora - inicio;

                        const minutos = Math.floor(diff / 60000);

                        document.getElementById('tiempoTranscurrido').textContent = minutos;
                    }

                    actualizarTiempo();
                    setInterval(actualizarTiempo, 60000);
                </script>
            <?php else: ?>
                <!-- Iniciar Jornada -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-primary text-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-play-circle me-2"></i>
                            Iniciar Nueva Jornada
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($proyectosAsignados)): ?>
                            <div class="alert alert-warning border-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                No tienes proyectos asignados. Contacta a tu supervisor.
                            </div>
                        <?php else: ?>
                            <form id="checkinForm">
                                <div class="mb-4">
                                    <label for="proyecto_id" class="form-label fw-bold">
                                        <i class="bi bi-briefcase me-2"></i>Seleccionar Proyecto
                                    </label>
                                    <select class="form-select form-select-lg" id="proyecto_id" name="proyecto_id" required>
                                        <option value="">-- Seleccione un proyecto --</option>
                                        <?php foreach ($proyectosAsignados as $proyecto): ?>
                                            <option value="<?php echo $proyecto['id']; ?>">
                                                <?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label for="check_in_foto" class="form-label fw-bold">
                                        <i class="bi bi-camera me-2"></i>Foto de Check-in
                                    </label>
                                    <input type="file" class="form-control form-control-lg" id="check_in_foto" accept="image/*" capture="environment">
                                    <small class="text-muted">Puede ser obligatoria según configuración del proyecto</small>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-geo-alt-fill me-2"></i> Iniciar Jornada
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Historial Reciente -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-clock-history me-2 text-primary"></i>
                            Historial de Jornadas
                        </h5>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">Fecha</th>
                                    <th class="border-0">Proyecto</th>
                                    <th class="border-0">Check-in</th>
                                    <th class="border-0">Check-out</th>
                                    <th class="border-0">Horas</th>
                                    <th class="border-0">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($jornadasRecientes)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No hay jornadas registradas
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($jornadasRecientes as $jornada): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y', strtotime($jornada['fecha_jornada'])); ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($jornada['nombre_proyecto'] ?? 'N/A'); ?></td>
                                            <td>
                                                <small><?php echo date('H:i', strtotime($jornada['check_in_time'])); ?></small>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo $jornada['check_out_time'] ? date('H:i', strtotime($jornada['check_out_time'])) : '-'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo $jornada['horas_calculadas'] ? number_format($jornada['horas_calculadas'], 2) . 'h' : '-'; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeClass = 'secondary';
                                                if ($jornada['estado_validacion'] === 'pendiente') $badgeClass = 'warning';
                                                if ($jornada['estado_validacion'] === 'aprobado') $badgeClass = 'success';
                                                if ($jornada['estado_validacion'] === 'rechazado') $badgeClass = 'danger';
                                                ?>
                                                <span class="badge bg-<?php echo $badgeClass; ?>">
                                                    <?php echo ucfirst($jornada['estado_validacion']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4 mb-4">
            <!-- Proyectos Asignados -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-briefcase-fill me-2 text-primary"></i>
                        Mis Proyectos
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($proyectosAsignados)): ?>
                        <p class="text-center text-muted">No tienes proyectos asignados</p>
                    <?php else: ?>
                        <?php foreach ($proyectosAsignados as $proyecto): ?>
                            <div class="mb-3 pb-3 border-bottom">
                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?></h6>
                                <small class="text-muted">
                                    <i class="bi bi-calendar-range me-1"></i>
                                    <?php echo date('d/m/Y', strtotime($proyecto['fecha_inicio'])); ?> -
                                    <?php echo date('d/m/Y', strtotime($proyecto['fecha_fin'])); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Accesos Rápidos -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-lightning-fill me-2 text-warning"></i>
                        Accesos Rápidos
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="asignaciones.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-map me-2"></i> Mis Asignaciones
                        </a>
                        <a href="notificaciones.php" class="btn btn-outline-info text-start">
                            <i class="bi bi-bell me-2"></i> Notificaciones
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="spinner-overlay" style="display:none;">
    <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Cargando...</span>
    </div>
</div>

<script>
    document.getElementById('checkinForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        try {
            showLoading();

            const position = await getCurrentPosition();

            const formData = new FormData();
            formData.append('proyecto_id', document.getElementById('proyecto_id').value);
            formData.append('check_in_lat', position.lat);
            formData.append('check_in_lon', position.lon);

            const photoFile = document.getElementById('check_in_foto').files[0];
            if (photoFile) {
                formData.append('check_in_foto_url', '/uploads/checkin_' + Date.now() + '.jpg');
            }

            const response = await fetch('/promotores-campo-system/api/checkin.php', {
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

    async function realizarCheckout() {
        if (!confirm('¿Desea finalizar la jornada?')) return;

        try {
            showLoading();

            const position = await getCurrentPosition();

            const formData = new FormData();
            formData.append('jornada_id', <?php echo $jornadaActiva['id'] ?? 0; ?>);
            formData.append('check_out_lat', position.lat);
            formData.append('check_out_lon', position.lon);

            const response = await fetch('/promotores-campo-system/api/checkout.php', {
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

<style>
    .spinner-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>