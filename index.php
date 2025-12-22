<?php
require_once 'config/session.php';

// Redirect based on login status
if (isLoggedIn()) {
    $roleName = getRoleName();
    
    switch($roleName) {
        case 'Administrador':
            header('Location: /promotores-campo-system/admin/dashboard.php');
            break;
        case 'Supervisor':
            header('Location: /promotores-campo-system/supervisor/dashboard.php');
            break;
        case 'Promotor':
            header('Location: /promotores-campo-system/promotor/dashboard.php');
            break;
        case 'Cliente':
            header('Location: /promotores-campo-system  /cliente/dashboard.php');
            break;
        default:
            header('Location: login.php');
    }
} else {
    header('Location: login.php');
}
exit();
