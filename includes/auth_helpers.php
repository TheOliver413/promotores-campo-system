<?php

function checkAuth()
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
}

function checkRole($allowedRoles)
{
    if (!isset($_SESSION['role_name'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Rol no definido']);
        exit;
    }

    if (!in_array($_SESSION['role_name'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
}

function getDB()
{
    return Database::getInstance()->getConnection();
}
