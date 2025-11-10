<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/auth_helpers.php';
require_once '../db/Notificacion.php';

checkAuth();
checkRole(['Promotor']);

$user_id = $_SESSION['user_id'];

$notificacionModel = new Notificacion();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_leida'])) {
    $notifId = intval($_POST['notif_id']);
    $notificacionModel->marcarComoLeida($notifId);
    header('Location: notificaciones.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_todas_leidas'])) {
    $notificaciones = $notificacionModel->getByUsuario($user_id, true);
    foreach ($notificaciones as $notif) {
        $notificacionModel->marcarComoLeida($notif['id']);
    }
    header('Location: notificaciones.php');
    exit;
}

$notificaciones = $notificacionModel->getByUsuario($user_id);
$noLeidas = $notificacionModel->contarNoLeidas($user_id);

$pageTitle = 'Notificaciones';
include '../includes/header.php';
?>

<style>
    /* Modern design for notifications */
    .notificaciones-modern {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 2rem 0;
    }

    .notif-card {
        border: none;
        border-radius: 15px;
        transition: all 0.3s ease;
        margin-bottom: 1rem;
    }

    .notif-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    .notif-nueva {
        border-left: 5px solid #667eea;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .notif-leida {
        opacity: 0.7;
    }

    .notif-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .btn-accion-notif {
        border-radius: 20px;
        padding: 0.25rem 1rem;
        font-size: 0.875rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-accion-notif:hover {
        transform: scale(1.05);
    }

    .badge-notif-tipo {
        border-radius: 20px;
        padding: 0.5rem 1rem;
        font-weight: 600;
    }
</style>

<div class="notificaciones-modern">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-10">
                <!-- Header -->
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 20px;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h2 class="h3 mb-1">
                                    <i class="bi bi-bell-fill me-2"></i> Notificaciones
                                </h2>
                                <p class="text-muted mb-0">Mantente al día con tus actividades</p>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if ($noLeidas > 0): ?>
                                    <span class="badge bg-danger badge-notif-tipo">
                                        <?= $noLeidas ?> sin leer
                                    </span>
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="marcar_todas_leidas" class="btn btn-accion-notif btn-outline-primary">
                                            <i class="bi bi-check-all me-1"></i> Marcar todas como leídas
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-success badge-notif-tipo">
                                        <i class="bi bi-check-circle me-1"></i> Todo al día
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <?php if (count($notificaciones) > 0): ?>
                    <?php foreach ($notificaciones as $notif): ?>
                        <?php
                        $tipo = $notif['tipo_notificacion'] ?? 'mensaje';
                        $badgeClass = 'info';
                        $icon = 'bi-info-circle';
                        $redirectUrl = null;

                        switch ($tipo) {
                            case 'aprobacion':
                                $badgeClass = 'success';
                                $icon = 'bi-check-circle-fill';
                                break;
                            case 'rechazo':
                                $badgeClass = 'danger';
                                $icon = 'bi-x-circle-fill';
                                break;
                            case 'ruta_asignada':
                            case 'ruta_actualizada':
                                $badgeClass = 'primary';
                                $icon = 'bi-map-fill';
                                $redirectUrl = '/promotor/asignaciones.php';
                                break;
                            case 'recordatorio':
                                $badgeClass = 'warning';
                                $icon = 'bi-clock-fill';
                                break;
                        }
                        ?>

                        <div class="card notif-card <?= !$notif['leido'] ? 'notif-nueva' : 'notif-leida' ?>">
                            <div class="card-body p-3">
                                <div class="d-flex gap-3">
                                    <!-- Icon -->
                                    <div class="notif-icon bg-<?= $badgeClass ?> text-white">
                                        <i class="bi <?= $icon ?>"></i>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <span class="badge bg-<?= $badgeClass ?> me-2">
                                                    <?= ucfirst(str_replace('_', ' ', $tipo)) ?>
                                                </span>
                                                <?php if (!$notif['leido']): ?>
                                                    <span class="badge bg-danger">Nuevo</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>
                                                <?= date('d/m/Y H:i', strtotime($notif['fecha_creacion'] ?? date('Y-m-d H:i:s'))) ?>
                                            </small>
                                        </div>

                                        <p class="mb-2"><?= htmlspecialchars($notif['mensaje'] ?? 'Sin mensaje') ?></p>

                                        <!-- Actions -->
                                        <div class="d-flex gap-2">
                                            <?php if ($redirectUrl): ?>
                                                <a href="<?= $redirectUrl ?>" class="btn btn-sm btn-accion-notif btn-primary">
                                                    <i class="bi bi-arrow-right-circle me-1"></i> Ver Detalles
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!$notif['leido']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
                                                    <button type="submit" name="marcar_leida" class="btn btn-sm btn-accion-notif btn-outline-success">
                                                        <i class="bi bi-check2 me-1"></i> Marcar como leída
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-accion-notif btn-outline-secondary" disabled>
                                                    <i class="bi bi-check-circle me-1"></i> Leída
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Footer Summary -->
                    <div class="card border-0 shadow-sm mt-3" style="border-radius: 20px;">
                        <div class="card-body text-center p-3">
                            <small class="text-muted">
                                Mostrando <?= count($notificaciones) ?> notificación<?= count($notificaciones) != 1 ? 'es' : '' ?>
                            </small>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="card border-0 shadow-sm" style="border-radius: 20px;">
                        <div class="card-body text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-bell-slash" style="font-size: 5rem; color: #ccc;"></i>
                            </div>
                            <h4 class="text-muted mb-2">No tienes notificaciones</h4>
                            <p class="text-muted">Cuando recibas notificaciones, aparecerán aquí</p>
                            <a href="/promotor/dashboard.php" class="btn btn-primary btn-accion-notif mt-3">
                                <i class="bi bi-house me-2"></i> Ir al Dashboard
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>