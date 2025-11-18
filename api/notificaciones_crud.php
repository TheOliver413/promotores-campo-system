<?php
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'enviar_notificacion':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $destinatario_id = $data['destinatario_id'] ?? 0;
            $mensaje = $data['mensaje'] ?? '';
            $tipo = $data['tipo'] ?? 'mensaje';
            $referencia_id = $data['referencia_id'] ?? null;

            if (!$destinatario_id || !$mensaje) {
                throw new Exception('Datos incompletos');
            }

            $stmt = $db->prepare("
                INSERT INTO notificaciones (usuario_id, mensaje, tipo_notificacion, referencia_id)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([$destinatario_id, $mensaje, $tipo, $referencia_id]);

            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;

        case 'obtener_notificaciones':
            $stmt = $db->prepare("
                SELECT * FROM notificaciones 
                WHERE usuario_id = ? AND leido = false
                ORDER BY fecha_creacion DESC
                LIMIT 50
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'notificaciones' => $notificaciones]);
            break;

        case 'marcar_como_leida':
            $id = $_GET['id'] ?? 0;
            $stmt = $db->prepare("UPDATE notificaciones SET leido = true WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
