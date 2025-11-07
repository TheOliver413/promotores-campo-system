<?php
require_once 'config/session.php';
require_once 'db/Auditoria.php';

if (isLoggedIn()) {
    $auditoria = new Auditoria();
    $auditoria->registrar(getUserId(), 'LOGOUT', 'usuarios', getUserId());
}

session_destroy();
header('Location: login.php');
exit();
