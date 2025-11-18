<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'db/User.php';
require_once 'includes/auth_helpers.php';

checkAuth();

$user_id = $_SESSION['user_id'];
$userModel = new User();
$db = Database::getInstance()->getConnection();

// Obtener información del usuario
$stmt = $db->prepare("
    SELECT u.*, r.nombre as rol_nombre
    FROM usuarios u
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar actualización de perfil
$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['actualizar_perfil'])) {
        $nombre = trim($_POST['nombre_completo']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono'] ?? '');

        try {
            $stmt = $db->prepare("
                UPDATE usuarios 
                SET nombre_completo = ?, email = ?, telefono = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $email, $telefono, $user_id]);

            $mensaje = 'Perfil actualizado exitosamente';
            $tipoMensaje = 'success';

            // Recargar datos
            $stmt = $db->prepare("
                SELECT u.*, r.nombre as rol_nombre
                FROM usuarios u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
            ");
            $stmt->execute([$user_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $mensaje = 'Error al actualizar perfil: ' . $e->getMessage();
            $tipoMensaje = 'danger';
        }
    }

    if (isset($_POST['cambiar_password'])) {
        $password_actual = $_POST['password_actual'];
        $password_nueva = $_POST['password_nueva'];
        $password_confirmar = $_POST['password_confirmar'];

        // Verificar password actual
        if (!password_verify($password_actual, $usuario['password_hash'])) {
            $mensaje = 'La contraseña actual es incorrecta';
            $tipoMensaje = 'danger';
        } elseif ($password_nueva !== $password_confirmar) {
            $mensaje = 'Las contraseñas nuevas no coinciden';
            $tipoMensaje = 'danger';
        } elseif (strlen($password_nueva) < 6) {
            $mensaje = 'La contraseña debe tener al menos 6 caracteres';
            $tipoMensaje = 'danger';
        } else {
            try {
                $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
                $stmt->execute([$password_hash, $user_id]);

                $mensaje = 'Contraseña actualizada exitosamente';
                $tipoMensaje = 'success';
            } catch (Exception $e) {
                $mensaje = 'Error al cambiar contraseña: ' . $e->getMessage();
                $tipoMensaje = 'danger';
            }
        }
    }
}

// Obtener estadísticas según el rol
$estadisticas = [];
$roleName = $usuario['rol_nombre'];

if ($roleName === 'Promotor') {
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT j.id) as total_jornadas,
            COALESCE(SUM(j.horas_calculadas), 0) as total_horas,
            COUNT(DISTINCT a.id) as total_actividades
        FROM jornadas j
        LEFT JOIN actividades a ON j.id = a.jornada_id
        WHERE j.promotor_user_id = ?
        AND MONTH(j.fecha_jornada) = MONTH(CURRENT_DATE())
        AND YEAR(j.fecha_jornada) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$user_id]);
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = 'Mi Perfil';
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">

            <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?= $tipoMensaje === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Header del Perfil -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 20px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-4">
                        <div class="position-relative">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"
                                style="width: 100px; height: 100px;">
                                <i class="bi bi-person-fill text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success">
                                <i class="bi bi-check-circle"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <h2 class="mb-1"><?= htmlspecialchars($usuario['nombre_completo']) ?></h2>
                            <p class="text-muted mb-2">
                                <i class="bi bi-envelope me-2"></i><?= htmlspecialchars($usuario['email']) ?>
                            </p>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge bg-primary" style="border-radius: 20px; padding: 0.5rem 1rem;">
                                    <i class="bi bi-shield-check me-1"></i>
                                    <?= htmlspecialchars($roleName) ?>
                                </span>
                                <span class="badge bg-success" style="border-radius: 20px; padding: 0.5rem 1rem;">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
                                    <?= $usuario['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas para Promotores -->
            <?php if ($roleName === 'Promotor' && !empty($estadisticas)): ?>
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                            <div class="card-body text-center p-4">
                                <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                    style="width: 60px; height: 60px;">
                                    <i class="bi bi-calendar-check-fill text-primary fs-3"></i>
                                </div>
                                <h3 class="mb-1"><?= $estadisticas['total_jornadas'] ?></h3>
                                <p class="text-muted mb-0">Jornadas este mes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                            <div class="card-body text-center p-4">
                                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                    style="width: 60px; height: 60px;">
                                    <i class="bi bi-clock-fill text-success fs-3"></i>
                                </div>
                                <h3 class="mb-1"><?= number_format($estadisticas['total_horas'], 1) ?>h</h3>
                                <p class="text-muted mb-0">Horas trabajadas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                            <div class="card-body text-center p-4">
                                <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                    style="width: 60px; height: 60px;">
                                    <i class="bi bi-list-check text-info fs-3"></i>
                                </div>
                                <h3 class="mb-1"><?= $estadisticas['total_actividades'] ?></h3>
                                <p class="text-muted mb-0">Actividades realizadas</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Información del Perfil -->
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
                        <div class="card-header bg-white border-0 p-4">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-person-fill me-2 text-primary"></i>
                                Información Personal
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-person me-1"></i> Nombre Completo
                                    </label>
                                    <input type="text" class="form-control form-control-lg" name="nombre_completo"
                                        value="<?= htmlspecialchars($usuario['nombre_completo']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-envelope me-1"></i> Correo Electrónico
                                    </label>
                                    <input type="email" class="form-control form-control-lg" name="email"
                                        value="<?= htmlspecialchars($usuario['email']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-telephone me-1"></i> Teléfono
                                    </label>
                                    <input type="tel" class="form-control form-control-lg" name="telefono"
                                        value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>">
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="actualizar_perfil" class="btn btn-primary btn-lg">
                                        <i class="bi bi-check-circle me-2"></i> Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Cambiar Contraseña -->
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
                        <div class="card-header bg-white border-0 p-4">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-shield-lock me-2 text-warning"></i>
                                Seguridad
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-lock me-1"></i> Contraseña Actual
                                    </label>
                                    <input type="password" class="form-control form-control-lg"
                                        name="password_actual" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-lock-fill me-1"></i> Nueva Contraseña
                                    </label>
                                    <input type="password" class="form-control form-control-lg"
                                        name="password_nueva" minlength="6" required>
                                    <small class="text-muted">Mínimo 6 caracteres</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-lock-fill me-1"></i> Confirmar Nueva Contraseña
                                    </label>
                                    <input type="password" class="form-control form-control-lg"
                                        name="password_confirmar" minlength="6" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="cambiar_password" class="btn btn-warning btn-lg">
                                        <i class="bi bi-key me-2"></i> Cambiar Contraseña
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información Adicional -->
            <div class="card border-0 shadow-sm" style="border-radius: 20px;">
                <div class="card-header bg-white border-0 p-4">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-info-circle me-2 text-info"></i>
                        Información Adicional
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                                <i class="bi bi-calendar-plus text-primary fs-3"></i>
                                <div>
                                    <small class="text-muted d-block">Fecha de Registro</small>
                                    <strong>
                                        <?= date('d/m/Y', strtotime($usuario['fecha_creacion'] ?? date('Y-m-d'))) ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                                <i class="bi bi-clock-history text-success fs-3"></i>
                                <div>
                                    <small class="text-muted d-block">Última Actualización</small>
                                    <strong>
                                        <?= date('d/m/Y H:i', strtotime($usuario['fecha_actualizacion'] ?? date('Y-m-d H:i:s'))) ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>