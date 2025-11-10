<?php
$pageTitle = 'Dashboard - Administrador';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/User.php';
require_once __DIR__ . '/../db/Proyecto.php';

requireRole(['Administrador']);

$userModel = new User();
$proyectoModel = new Proyecto();

$db = Database::getInstance()->getConnection();

$stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE estado = 'activo'");
$totalUsuarios = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM proyectos WHERE estado = 'activo'");
$totalProyectos = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM clientes WHERE activo = true");
$totalClientes = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM jornadas WHERE fecha_jornada = CURDATE()");
$jornadasHoy = $stmt->fetch()['total'];

// Obtener actividad reciente
$stmt = $db->prepare("
    SELECT 
        u.nombre_completo,
        a.accion,
        a.tabla_afectada,
        a.timestamp_accion
    FROM auditoria a
    INNER JOIN usuarios u ON a.usuario_id = u.id
    ORDER BY a.timestamp_accion DESC
    LIMIT 10
");
$stmt->execute();
$actividadReciente = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Dashboard Administrador</h2>
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
                            <h6 class="text-muted text-uppercase mb-2">Usuarios Activos</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $totalUsuarios; ?></h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-people-fill text-primary fs-3"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="usuarios.php" class="text-decoration-none small">
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
                        <a href="proyectos.php" class="text-decoration-none small">
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
                            <h6 class="text-muted text-uppercase mb-2">Clientes</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $totalClientes; ?></h2>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-building-fill text-info fs-3"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="clientes.php" class="text-decoration-none small">
                            Ver clientes <i class="bi bi-arrow-right"></i>
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
                            <h6 class="text-muted text-uppercase mb-2">Jornadas Hoy</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $jornadasHoy; ?></h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-calendar-check-fill text-warning fs-3"></i>
                        </div>
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
                        <a href="auditoria.php" class="btn btn-sm btn-outline-primary">
                            Ver todas
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">Usuario</th>
                                    <th class="border-0">Acción</th>
                                    <th class="border-0">Tabla</th>
                                    <th class="border-0">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($actividadReciente)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
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
                                            <td><?php echo htmlspecialchars($actividad['accion']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($actividad['tabla_afectada']); ?></span></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($actividad['timestamp_accion'])); ?>
                                                </small>
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

        <!-- Accesos Rápidos -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-lightning-fill me-2 text-warning"></i>
                        Accesos Rápidos
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="usuarios.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-people me-2"></i> Gestión de Usuarios
                        </a>
                        <a href="proyectos.php" class="btn btn-outline-success text-start">
                            <i class="bi bi-briefcase me-2"></i> Gestión de Proyectos
                        </a>
                        <a href="clientes.php" class="btn btn-outline-info text-start">
                            <i class="bi bi-building me-2"></i> Gestión de Clientes
                        </a>
                        <a href="roles.php" class="btn btn-outline-warning text-start">
                            <i class="bi bi-shield-check me-2"></i> Gestión de Roles
                        </a>
                        <a href="auditoria.php" class="btn btn-outline-secondary text-start">
                            <i class="bi bi-clock-history me-2"></i> Auditoría del Sistema
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>