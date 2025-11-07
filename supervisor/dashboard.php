<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/User.php';
require_once '../db/Proyecto.php';

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$proyectoModel = new Proyecto();

// Obtener estadísticas del supervisor
$supervisorId = $_SESSION['user_id'];

// Contar promotores bajo supervisión
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT u.id) as total
    FROM usuarios u
    JOIN roles r ON u.role_id = r.id
    WHERE r.nombre = 'Promotor' AND u.estado = 'activo'
");
$stmt->execute();
$totalPromotores = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contar proyectos activos
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM proyectos
    WHERE estado = 'activo'
");
$stmt->execute();
$totalProyectos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contar jornadas pendientes de validación
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM jornadas
    WHERE estado_validacion = 'pendiente'
");
$stmt->execute();
$jornadasPendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contar actividades pendientes de validación
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM actividades
    WHERE estado_validacion = 'pendiente'
");
$stmt->execute();
$actividadesPendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Obtener actividad reciente
$stmt = $db->prepare("
    SELECT 
        j.id as jornada_id,
        u.nombre_completo,
        j.check_in_time,
        j.check_out_time,
        j.estado_validacion,
        p.nombre_proyecto
    FROM jornadas j
    INNER JOIN usuarios u ON j.promotor_user_id = u.id
    LEFT JOIN proyectos p ON j.proyecto_id = p.id
    ORDER BY j.check_in_time DESC
    LIMIT 10
");
$stmt->execute();
$actividadReciente = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener promotores con más actividad este mes
$stmt = $db->prepare("
    SELECT 
        u.nombre_completo,
        COUNT(j.id) as total_jornadas,
        SUM(TIMESTAMPDIFF(HOUR, j.check_in_time, j.check_out_time)) as horas_trabajadas
    FROM usuarios u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN jornadas j ON u.id = j.promotor_user_id
    WHERE r.nombre = 'Promotor' 
    AND u.estado = 'activo'
    AND MONTH(j.check_in_time) = MONTH(CURRENT_DATE())
    AND YEAR(j.check_in_time) = YEAR(CURRENT_DATE())
    GROUP BY u.id, u.nombre_completo
    ORDER BY total_jornadas DESC
    LIMIT 5
");
$stmt->execute();
$topPromotores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Dashboard Supervisor';
require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Dashboard de Supervisión</h2>
                    <p class="text-muted mb-0">
                        <i class="bi bi-calendar-check me-2"></i>
                        <?php echo date('l, d \d\e F \d\e Y'); ?>
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
                            <h6 class="text-muted text-uppercase mb-2">Promotores Activos</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $totalPromotores; ?></h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-people-fill text-primary fs-3"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="promotores.php" class="text-decoration-none small">
                            Ver todos <i class="bi bi-arrow-right"></i>
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
                            <h6 class="text-muted text-uppercase mb-2">Proyectos Activos</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $totalProyectos; ?></h2>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-briefcase-fill text-success fs-3"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="../admin/proyectos.php" class="text-decoration-none small">
                            Ver proyectos <i class="bi bi-arrow-right"></i>
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
                            <h6 class="text-muted text-uppercase mb-2">Jornadas Pendientes</h6>
                            <h2 class="mb-0 fw-bold text-warning"><?php echo $jornadasPendientes; ?></h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-clock-fill text-warning fs-3"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="validacion.php" class="text-decoration-none small">
                            Validar ahora <i class="bi bi-arrow-right"></i>
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
                            <h6 class="text-muted text-uppercase mb-2">Actividades Pendientes</h6>
                            <h2 class="mb-0 fw-bold text-danger"><?php echo $actividadesPendientes; ?></h2>
                        </div>
                        <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-exclamation-triangle-fill text-danger fs-3"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="validacion.php" class="text-decoration-none small">
                            Revisar <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="row">
        <!-- Actividad Reciente -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-activity me-2 text-primary"></i>
                            Actividad Reciente
                        </h5>
                        <a href="validacion.php" class="btn btn-sm btn-outline-primary">
                            Ver todas
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">Promotor</th>
                                    <th class="border-0">Proyecto</th>
                                    <th class="border-0">Check-in</th>
                                    <th class="border-0">Check-out</th>
                                    <th class="border-0">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($actividadReciente)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No hay actividad reciente
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($actividadReciente as $actividad): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                                        <i class="bi bi-person-fill text-primary"></i>
                                                    </div>
                                                    <?php echo htmlspecialchars($actividad['nombre_completo']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($actividad['nombre_proyecto'] ?? 'N/A'); ?></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($actividad['check_in_time'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo $actividad['check_out_time'] ? date('d/m/Y H:i', strtotime($actividad['check_out_time'])) : 'En curso'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeClass = 'secondary';
                                                if ($actividad['estado_validacion'] === 'pendiente') $badgeClass = 'warning';
                                                if ($actividad['estado_validacion'] === 'aprobado') $badgeClass = 'success';
                                                if ($actividad['estado_validacion'] === 'rechazado') $badgeClass = 'danger';
                                                ?>
                                                <span class="badge bg-<?php echo $badgeClass; ?>">
                                                    <?php echo ucfirst($actividad['estado_validacion']); ?>
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

        <!-- Top Promotores -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-trophy-fill me-2 text-warning"></i>
                        Top Promotores del Mes
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($topPromotores)): ?>
                        <p class="text-center text-muted">No hay datos disponibles</p>
                    <?php else: ?>
                        <?php foreach ($topPromotores as $index => $promotor): ?>
                            <div class="d-flex align-items-center mb-3 pb-3 <?php echo $index < count($topPromotores) - 1 ? 'border-bottom' : ''; ?>">
                                <div class="me-3">
                                    <div class="bg-<?php echo $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'info'); ?> bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <span class="fw-bold text-<?php echo $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'info'); ?>">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($promotor['nombre_completo']); ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar-check me-1"></i>
                                        <?php echo $promotor['total_jornadas'] ?? 0; ?> jornadas
                                        <span class="mx-1">•</span>
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo $promotor['horas_trabajadas'] ?? 0; ?>h
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Accesos Rápidos -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-lightning-fill me-2 text-primary"></i>
                        Accesos Rápidos
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="promotores.php" class="text-decoration-none">
                                <div class="card border-0 bg-primary bg-opacity-10 h-100 hover-shadow">
                                    <div class="card-body text-center">
                                        <i class="bi bi-people-fill text-primary fs-1 mb-3"></i>
                                        <h6 class="text-primary mb-0">Gestionar Promotores</h6>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="validacion.php" class="text-decoration-none">
                                <div class="card border-0 bg-warning bg-opacity-10 h-100 hover-shadow">
                                    <div class="card-body text-center">
                                        <i class="bi bi-check-circle-fill text-warning fs-1 mb-3"></i>
                                        <h6 class="text-warning mb-0">Validar Jornadas</h6>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="reportes.php" class="text-decoration-none">
                                <div class="card border-0 bg-success bg-opacity-10 h-100 hover-shadow">
                                    <div class="card-body text-center">
                                        <i class="bi bi-bar-chart-fill text-success fs-1 mb-3"></i>
                                        <h6 class="text-success mb-0">Ver Reportes</h6>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="rutas.php" class="text-decoration-none">
                                <div class="card border-0 bg-info bg-opacity-10 h-100 hover-shadow">
                                    <div class="card-body text-center">
                                        <i class="bi bi-map-fill text-info fs-1 mb-3"></i>
                                        <h6 class="text-info mb-0">Gestionar Rutas</h6>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-shadow {
        transition: all 0.3s ease;
    }

    .hover-shadow:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
</style>

<?php require_once '../includes/footer.php'; ?>