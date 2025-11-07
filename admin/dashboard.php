<?php
$pageTitle = 'Dashboard - Administrador';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/User.php';
require_once __DIR__ . '/../db/Proyecto.php';

requireRole(['Administrador']);

$userModel = new User();
$proyectoModel = new Proyecto();

// Get statistics
$db = Database::getInstance()->getConnection();

$stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE estado = 'activo'");
$totalUsuarios = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM proyectos WHERE estado = 'activo'");
$totalProyectos = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM clientes WHERE activo = true");
$totalClientes = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM jornadas WHERE fecha_jornada = CURDATE()");
$jornadasHoy = $stmt->fetch()['total'];
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Dashboard Administrador</h1>
            <p class="text-muted">Bienvenido, <?php echo htmlspecialchars(getUserName()); ?></p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-white-50">Usuarios Activos</h6>
                            <h2 class="card-title mb-0"><?php echo $totalUsuarios; ?></h2>
                        </div>
                        <i class="bi bi-people stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-white-50">Proyectos Activos</h6>
                            <h2 class="card-title mb-0"><?php echo $totalProyectos; ?></h2>
                        </div>
                        <i class="bi bi-briefcase stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-white-50">Clientes</h6>
                            <h2 class="card-title mb-0"><?php echo $totalClientes; ?></h2>
                        </div>
                        <i class="bi bi-building stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-white-50">Jornadas Hoy</h6>
                            <h2 class="card-title mb-0"><?php echo $jornadasHoy; ?></h2>
                        </div>
                        <i class="bi bi-calendar-check stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Accesos Rápidos</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="usuarios.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-people text-primary"></i> Gestión de Usuarios
                        </a>
                        <a href="proyectos.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-briefcase text-success"></i> Gestión de Proyectos
                        </a>
                        <a href="clientes.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-building text-info"></i> Gestión de Clientes
                        </a>
                        <a href="roles.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-shield-check text-warning"></i> Gestión de Roles
                        </a>
                        <a href="auditoria.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-clock-history text-secondary"></i> Auditoría del Sistema
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Actividad Reciente</h5>
                </div>
                <div class="card-body">
                    <?php
                    require_once __DIR__ . '/../db/Auditoria.php';
                    $auditoriaModel = new Auditoria();
                    $recentActivity = $auditoriaModel->getAll();
                    $recentActivity = array_slice($recentActivity, 0, 5);
                    ?>

                    <?php if (empty($recentActivity)): ?>
                        <p class="text-muted">No hay actividad reciente</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="timeline-item">
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($activity['timestamp_accion'])); ?></small>
                                    <p class="mb-0">
                                        <strong><?php echo htmlspecialchars($activity['usuario_nombre'] ?? 'Sistema'); ?></strong>
                                        - <?php echo htmlspecialchars($activity['accion']); ?>
                                        <?php if ($activity['tabla_afectada']): ?>
                                            en <em><?php echo htmlspecialchars($activity['tabla_afectada']); ?></em>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>