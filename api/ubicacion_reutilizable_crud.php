<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

ob_start();

require_once '../config/session.php';
require_once '../config/database.php';

ob_end_clean();
header('Content-Type: application/json');

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();

// Obtener datos de la solicitud
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$action = $data['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $clienteId = $data['cliente_id'] ?? null;
            $nombreUbicacion = $data['nombre_ubicacion'] ?? '';
            $direccion = $data['direccion'] ?? '';
            $latitud = $data['latitud'] ?? null;
            $longitud = $data['longitud'] ?? null;
            $notas = $data['notas'] ?? '';
            $contactoNombre = $data['contacto_nombre'] ?? null;
            $contactoTelefono = $data['contacto_telefono'] ?? null;
            $contactoEmail = $data['contacto_email'] ?? null;

            if (!$clienteId || !$nombreUbicacion || !$direccion || $latitud === null || $longitud === null) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios son requeridos']);
                exit;
            }

            // Verificar que el cliente existe
            $stmt = $db->prepare("SELECT id FROM clientes WHERE id = ?");
            $stmt->execute([$clienteId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'El cliente no existe']);
                exit;
            }

            // Insertar ubicación
            $stmt = $db->prepare("
                INSERT INTO ubicaciones_clientes 
                (cliente_id, nombre_ubicacion, direccion, latitud, longitud, notas, contacto_nombre, contacto_telefono, contacto_email, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $clienteId,
                $nombreUbicacion,
                $direccion,
                floatval($latitud),
                floatval($longitud),
                $notas,
                $contactoNombre,
                $contactoTelefono,
                $contactoEmail
            ]);

            $ubicacionId = $db->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Ubicación guardada exitosamente',
                'data' => ['id' => $ubicacionId]
            ]);
            break;

        case 'list':
            $clienteId = $_GET['cliente_id'] ?? null;

            if ($clienteId) {
                $stmt = $db->prepare("
                    SELECT uc.*, c.nombre_empresa
                    FROM ubicaciones_clientes uc
                    INNER JOIN clientes c ON uc.cliente_id = c.id
                    WHERE uc.cliente_id = ? AND uc.activo = 1
                    ORDER BY uc.nombre_ubicacion
                ");
                $stmt->execute([$clienteId]);
            } else {
                // Listar todas las ubicaciones de clientes que tienen proyectos asignados al supervisor
                $stmt = $db->prepare("
                    SELECT DISTINCT uc.*, c.nombre_empresa
                    FROM ubicaciones_clientes uc
                    INNER JOIN clientes c ON uc.cliente_id = c.id
                    INNER JOIN proyecto_clientes pc ON c.id = pc.cliente_id
                    INNER JOIN proyecto_promotores pp ON pc.proyecto_id = pp.proyecto_id
                    INNER JOIN supervisor_promotores sp ON pp.promotor_user_id = sp.promotor_id
                    WHERE sp.supervisor_id = ? AND uc.activo = 1
                    ORDER BY c.nombre_empresa, uc.nombre_ubicacion
                ");
                $stmt->execute([$_SESSION['user_id']]);
            }

            $ubicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $ubicaciones
            ]);
            break;

        case 'get':
            $id = $_GET['id'] ?? 0;

            $stmt = $db->prepare("
                SELECT uc.*, c.nombre_empresa
                FROM ubicaciones_clientes uc
                INNER JOIN clientes c ON uc.cliente_id = c.id
                WHERE uc.id = ?
            ");
            $stmt->execute([$id]);
            $ubicacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ubicacion) {
                echo json_encode(['success' => false, 'message' => 'Ubicación no encontrada']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => $ubicacion
            ]);
            break;

        case 'update':
            $id = $data['id'] ?? null;
            $nombreUbicacion = $data['nombre_ubicacion'] ?? '';
            $direccion = $data['direccion'] ?? '';
            $latitud = $data['latitud'] ?? null;
            $longitud = $data['longitud'] ?? null;
            $notas = $data['notas'] ?? '';
            $contactoNombre = $data['contacto_nombre'] ?? null;
            $contactoTelefono = $data['contacto_telefono'] ?? null;
            $contactoEmail = $data['contacto_email'] ?? null;
            $activo = $data['activo'] ?? 1;

            if (!$id || !$nombreUbicacion || !$direccion || $latitud === null || $longitud === null) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios son requeridos']);
                exit;
            }

            $stmt = $db->prepare("
                UPDATE ubicaciones_clientes 
                SET nombre_ubicacion = ?, direccion = ?, latitud = ?, longitud = ?, 
                    notas = ?, contacto_nombre = ?, contacto_telefono = ?, contacto_email = ?, activo = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $nombreUbicacion,
                $direccion,
                floatval($latitud),
                floatval($longitud),
                $notas,
                $contactoNombre,
                $contactoTelefono,
                $contactoEmail,
                $activo,
                $id
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Ubicación actualizada exitosamente'
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? $data['id'] ?? 0;

            // Soft delete - marcar como inactivo
            $stmt = $db->prepare("UPDATE ubicaciones_clientes SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Ubicación eliminada exitosamente'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    error_log("Error en ubicacion_reutilizable_crud.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
    ]);
}
