<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/Producto.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $productoModel = new Producto();
    $productoId = $_GET['producto_id'] ?? null;
    $promotorId = $_GET['promotor_id'] ?? null;
    $tipo = $_GET['tipo'] ?? 'asignaciones';

    if ($tipo === 'asignaciones') {
        $data = $productoModel->getHistorialAsignaciones($productoId, $promotorId);
    } else {
        $data = $productoModel->getMovimientosStock($productoId);
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
