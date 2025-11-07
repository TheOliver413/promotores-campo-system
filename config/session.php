<?php

/**
 * Session Management
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['role_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
}

function requireRole($allowedRoles)
{
    requireLogin();

    if (!in_array($_SESSION['role_name'], $allowedRoles)) {
        header('Location: /unauthorized.php');
        exit();
    }
}

function getUserId()
{
    return $_SESSION['user_id'] ?? null;
}

function getRoleId()
{
    return $_SESSION['role_id'] ?? null;
}

function getRoleName()
{
    return $_SESSION['role_name'] ?? null;
}

function getUserName()
{
    return $_SESSION['nombre_completo'] ?? '';
}
