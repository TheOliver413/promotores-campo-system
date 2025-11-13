<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

ob_start();

require_once '../config/session.php';
require_once '../config/database.php';

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role_name'] ?? '';

// Solo permitir a promotores
if ($userRole !== 'Promotor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Solo permitir POST para guardar ubicaciones
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'JSON inválido']);
        exit;
    }

    if (!isset($data['latitud']) || !isset($data['longitud'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Latitud y longitud son requeridos']);
        exit;
    }

    $latitud = floatval($data['latitud']);
    $longitud = floatval($data['longitud']);
    $bateriaNivel = isset($data['bateria_nivel']) ? intval($data['bateria_nivel']) : null;

    // Validar coordenadas
    if ($latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Coordenadas inválidas']);
        exit;
    }

    // Obtener conexión de base de datos
    $db = Database::getInstance()->getConnection();

    // Insertar ubicación en la base de datos
    $stmt = $db->prepare("
        INSERT INTO ubicaciones_tracking 
        (promotor_user_id, latitud, longitud, timestamp_gps, bateria_nivel)
        VALUES (?, ?, ?, NOW(), ?)
    ");

    if ($stmt->execute([$userId, $latitud, $longitud, $bateriaNivel])) {
        echo json_encode([
            'success' => true,
            'message' => 'Ubicación guardada correctamente',
            'id' => $db->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al guardar ubicación en la base de datos']);
    }
} catch (Exception $e) {
    error_log("Error en tracking_ubicacion.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor', 'message' => $e->getMessage()]);
}
