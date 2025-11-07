<?php
require_once 'config/session.php';

// Redirect based on login status
if (isLoggedIn()) {
    $roleName = getRoleName();
    
    switch($roleName) {
        case 'Administrador':
            header('Location: /admin/dashboard.php');
            break;
        case 'Supervisor':
            header('Location: /supervisor/dashboard.php');
            break;
        case 'Promotor':
            header('Location: /promotor/dashboard.php');
            break;
        case 'Cliente':
            header('Location: /cliente/dashboard.php');
            break;
        default:
            header('Location: login.php');
    }
} else {
    header('Location: login.php');
}
exit();
