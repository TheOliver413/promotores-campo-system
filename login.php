<?php
require_once 'config/session.php';
require_once 'db/User.php';
require_once 'db/Auditoria.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        $userModel = new User();
        $user = $userModel->authenticate($email, $password);

        if ($user) {
            // Guardar datos en sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['nombre_completo'] = $user['nombre_completo'];
            $_SESSION['cliente_id'] = $user['cliente_id'] ?? null;

            // Registrar auditoría
            $auditoria = new Auditoria();
            $auditoria->registrar($user['id'], 'LOGIN', 'usuarios', $user['id']);

            // Redirección según rol
            switch (strtolower($user['role_name'])) {
                case 'administrador':
                    header('Location: admin/dashboard.php');
                    break;
                case 'supervisor':
                    header('Location: supervisor/dashboard.php');
                    break;
                case 'promotor':
                    header('Location: promotor/dashboard.php');
                    break;
                case 'cliente':
                    header('Location: cliente/reportes.php');
                    break;
                default:
                    header('Location: index.php');
                    break;
            }
            exit;
        } else {
            $error = 'Credenciales inválidas. Verifique su usuario o contraseña.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Gestión de Promotores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-geo-alt-fill text-white bg-primary rounded-circle p-3" style="font-size: 3rem;"></i>
                        <h2 class="mt-3 fw-bold">Field Sales</h2>
                        <p class="text-muted">Sistema de Gestión de Promotores</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">Recordarme</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                            <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                        </button>

                        <div class="text-center">
                            <a href="forgot_password.php" class="text-decoration-none">¿Olvidó su contraseña?</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>