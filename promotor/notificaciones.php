<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/Notificacion.php';

checkAuth();
checkRole(['Promotor']);

$user_id = $_SESSION['user_id'];
$db = getDB();

$notificaciones = Notificacion::getByUser($db, $user_id);

// Marcar como leídas
Notificacion::marcarComoLeidas($db, $user_id);

$pageTitle = 'Notificaciones';
include '../includes/header.php';
?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-bell"></i> Mis Notificaciones</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (count($notificaciones) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notificaciones as $notif): ?>
                                <div class="list-group-item <?= $notif['leido'] ? '' : 'bg-light' ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="badge bg-<?= $notif['tipo'] === 'Aprobación' ? 'success' : ($notif['tipo'] === 'Rechazo' ? 'danger' : 'info') ?> me-2">
                                                    <?= htmlspecialchars($notif['tipo']) ?>
                                                </span>
                                                <?php if (!$notif['leido']): ?>
                                                    <span class="badge bg-primary">Nuevo</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-1"><?= htmlspecialchars($notif['mensaje']) ?></p>
                                            <small class="text-muted">
                                                <i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($notif['fecha_envio'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-bell-slash text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">No tienes notificaciones</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>