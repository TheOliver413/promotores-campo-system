<?php
require_once __DIR__ . '/../config/session.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Sistema de Gestión de Promotores'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Leaflet (solo si usas mapas) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Estilos profesionales y empresariales completamente rediseñados -->
    <style>
        :root {
            --primary-color: #1a365d;
            --primary-dark: #0f2744;
            --primary-light: #2d4a6f;
            --accent-color: #3182ce;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --border-color: #cbd5e1;
            --footer-bg: #0f172a;
            --footer-text: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8fafc;
        }

        /* Header Profesional */
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 0.75rem 0;
            border-bottom: 3px solid var(--accent-color);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
            color: #fff !important;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
        }

        .navbar-brand i {
            font-size: 1.75rem;
            color: var(--accent-color);
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .nav-link {
            color: var(--text-light) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            margin: 0 0.25rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff !important;
        }

        .nav-link.active {
            background: var(--accent-color);
            color: #fff !important;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            padding: 0.5rem;
            margin-top: 0.5rem;
        }

        .dropdown-item {
            border-radius: 6px;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .dropdown-item:hover {
            background: var(--accent-color);
            color: #fff;
        }

        .dropdown-divider {
            margin: 0.5rem 0;
            border-color: var(--border-color);
        }

        .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-weight: 600;
        }

        .user-dropdown {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 0.25rem 0.75rem;
        }

        .user-dropdown:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* Main Content Area */
        main {
            flex: 1 0 auto;
            padding-bottom: 2rem;
        }

        /* Footer Profesional */
        footer {
            flex-shrink: 0;
            background: var(--footer-bg);
            color: var(--footer-text);
            padding: 2.5rem 0 1.5rem;
            margin-top: auto;
            border-top: 3px solid var(--accent-color);
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h5 {
            color: #fff;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .footer-section ul {
            list-style: none;
            padding: 0;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
        }

        .footer-section a {
            color: var(--footer-text);
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .footer-section a:hover {
            color: var(--accent-color);
            padding-left: 0.5rem;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1.5rem;
            text-align: center;
        }

        .footer-bottom p {
            margin: 0;
            font-size: 0.9rem;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--accent-color);
            transform: translateY(-3px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1.25rem;
            }

            .footer-content {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <?php if (isLoggedIn()): ?>
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <?php
                $roleName = getRoleName();
                switch ($roleName) {
                    case 'Administrador':
                        $homeLink = '/promotores-campo-system/admin/dashboard.php';
                        break;
                    case 'Supervisor':
                        $homeLink = '/promotores-campo-system/supervisor/dashboard.php';
                        break;
                    case 'Promotor':
                        $homeLink = '/promotores-campo-system/promotor/dashboard.php';
                        break;
                    case 'Cliente':
                        $homeLink = '/promotores-campo-system/cliente/dashboard.php';
                        break;
                    default:
                        $homeLink = '/promotores-campo-system/';
                        break;
                }
                ?>
                <a class="navbar-brand" href="<?php echo $homeLink; ?>">
                    <i class="bi bi-briefcase-fill"></i>
                    <span>Field Sales Manager</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <?php
                        $currentPage = basename($_SERVER['PHP_SELF']);

                        // ADMINISTRADOR
                        if ($roleName === 'Administrador'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="/promotores-campo-system/admin/dashboard.php">
                                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'clientes.php' ? 'active' : ''; ?>" href="/promotores-campo-system/admin/clientes.php">
                                    <i class="bi bi-building me-1"></i> Clientes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'usuarios.php' ? 'active' : ''; ?>" href="/promotores-campo-system/admin/usuarios.php">
                                    <i class="bi bi-people me-1"></i> Usuarios
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'proyectos.php' ? 'active' : ''; ?>" href="/promotores-campo-system/admin/proyectos.php">
                                    <i class="bi bi-kanban me-1"></i> Proyectos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'auditoria.php' ? 'active' : ''; ?>" href="/promotores-campo-system/admin/auditoria.php">
                                    <i class="bi bi-file-text me-1"></i> Auditoría
                                </a>
                            </li>
                            <!-- <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    <i class="bi bi-grid me-1"></i> Catálogos
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/promotores-campo-system/admin/roles.php"><i class="bi bi-shield-check me-2"></i> Roles</a></li>
                                    <li><a class="dropdown-item" href="/promotores-campo-system/admin/catalogos.php"><i class="bi bi-list-check me-2"></i> Tipos de Actividad</a></li>
                                </ul>
                            </li> -->
                        <?php elseif ($roleName === 'Supervisor'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="/promotores-campo-system/supervisor/dashboard.php">
                                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'promotores.php' ? 'active' : ''; ?>" href="/promotores-campo-system/supervisor/promotores.php">
                                    <i class="bi bi-people me-1"></i> Promotores
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'ubicaciones.php' ? 'active' : ''; ?>" href="/promotores-campo-system/supervisor/ubicaciones.php">
                                    <i class="bi bi-geo-alt me-1"></i> Ubicaciones
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'rutas.php' ? 'active' : ''; ?>" href="/promotores-campo-system/supervisor/rutas.php">
                                    <i class="bi bi-map me-1"></i> Rutas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'monitoreo.php' ? 'active' : ''; ?>" href="/promotores-campo-system/supervisor/monitoreo.php">
                                    <i class="bi bi-binoculars me-1"></i> Monitoreo
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'validacion.php' ? 'active' : ''; ?>" href="/promotores-campo-system/supervisor/validacion.php">
                                    <i class="bi bi-check-circle me-1"></i> Validación
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'reportes.php' ? 'active' : ''; ?>" href="/promotores-campo-system/supervisor/reportes.php">
                                    <i class="bi bi-graph-up me-1"></i> Reportes
                                </a>
                            </li>

                        <?php elseif ($roleName === 'Promotor'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="/promotores-campo-system/promotor/dashboard.php">
                                    <i class="bi bi-calendar-check me-1"></i> Mi Jornada
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'jornada.php' ? 'active' : ''; ?>" href="/promotores-campo-system/promotor/jornada.php">
                                    <i class="bi bi-hourglass-split me-1"></i> Jornada
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'actividades.php' ? 'active' : ''; ?>" href="/promotores-campo-system/promotor/actividades.php">
                                    <i class="bi bi-star me-1"></i> Actividades
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'asignaciones.php' ? 'active' : ''; ?>" href="/promotores-campo-system/promotor/asignaciones.php">
                                    <i class="bi bi-list-task me-1"></i> Mis Asignaciones
                                </a>
                            </li>
                            <li class="nav-item position-relative">
                                <a class="nav-link <?php echo $currentPage === 'notificaciones.php' ? 'active' : ''; ?>" href="/promotores-campo-system/promotor/notificaciones.php">
                                    <i class="bi bi-bell me-1"></i> Notificaciones
                                    <?php
                                    require_once __DIR__ . '/../db/Notificacion.php';
                                    $notifModel = new Notificacion();
                                    $unreadCount = $notifModel->contarNoLeidas(getUserId());
                                    if ($unreadCount > 0): ?>
                                        <span class="badge bg-danger ms-1"><?php echo $unreadCount; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>

                        <?php elseif ($roleName === 'Cliente'): ?>
                            <!-- <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="/promotores-campo-system/cliente/dashboard.php">
                                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                                </a>
                            </li> -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'reportes.php' ? 'active' : ''; ?>" href="/promotores-campo-system/cliente/reportes.php">
                                    <i class="bi bi-graph-up me-1"></i> Reportes
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle user-dropdown" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i>
                                <span><?php echo htmlspecialchars(getUserName()); ?></span>
                                <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($roleName); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="/promotores-campo-system/perfil.php">
                                        <i class="bi bi-person me-2"></i> Mi Perfil
                                    </a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger" href="/promotores-campo-system/logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <main class="container-fluid py-4">