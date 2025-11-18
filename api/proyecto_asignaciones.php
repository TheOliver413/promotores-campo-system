<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/Proyecto.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$action = $_GET['action'] ?? 'get_assignments';

if ($action === 'list') {
    // Return all projects
    $proyecto = new Proyecto();
    $proyectos = $proyecto->getAll();

    // Format the response to include only necessary fields
    $result = array_map(function ($p) {
        return [
            'proyecto_id' => $p['id'],
            'nombre_proyecto' => $p['nombre_proyecto'],
            'estado' => $p['estado']
        ];
    }, $proyectos);

    echo json_encode($result);
    exit();
}

// Original functionality for getting assignments
$proyectoId = $_GET['proyecto_id'] ?? null;

if (!$proyectoId) {
    http_response_code(400);
    echo json_encode(['error' => 'proyecto_id requerido']);
    exit();
}

$db = Database::getInstance()->getConnection();

// Get assigned clients
$stmt = $db->prepare("SELECT cliente_id FROM proyecto_clientes WHERE proyecto_id = ?");
$stmt->execute([$proyectoId]);
$clientes = array_column($stmt->fetchAll(), 'cliente_id');

// Get assigned promoters
$stmt = $db->prepare("SELECT promotor_user_id FROM proyecto_promotores WHERE proyecto_id = ?");
$stmt->execute([$proyectoId]);
$promotores = array_column($stmt->fetchAll(), 'promotor_user_id');

echo json_encode([
    'clientes' => array_map('intval', $clientes),
    'promotores' => array_map('intval', $promotores)
]);
