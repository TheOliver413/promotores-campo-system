<?php
// Habilitamos errores para el log interno pero no para la salida directa todavía
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/ActaVisita.php';
require_once __DIR__ . '/../db/Notificacion.php';
require_once __DIR__ . '/../db/Auditoria.php';

// Limpiar cualquier salida accidental de los require
ob_clean();
header('Content-Type: application/json');

$debugLogs = [];
function debugLog(&$logs, $tag, $data)
{
    $logs[] = "[$tag] " . (is_array($data) || is_object($data) ? json_encode($data) : $data);
}

requireLogin();

$actaModel = new ActaVisita();
$notifModel = new Notificacion();
$auditoriaModel = new Auditoria();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        // 1. Verificar Sesión
        $userId = $_SESSION['user_id'] ?? null;
        debugLog($debugLogs, 'SESSION_USER_ID', $userId);

        if (!$userId) {
            throw new Exception('Sesión expirada o usuario_id no encontrado en sesión.');
        }

        // 2. Verificar Promotor ID
        $promotorUserId = $_POST['promotor_user_id'] ?? $userId;
        debugLog($debugLogs, 'PROMOTOR_USER_ID', $promotorUserId);

        if (empty($_POST['punto_visita_nombre'])) throw new Exception('El nombre del punto de visita es requerido');
        if (empty($_POST['receptor_nombre'])) throw new Exception('El nombre del receptor es requerido');

        // 3. Intento de SET MySQL Variable
        try {
            $db = Database::getInstance()->getConnection();
            $db->exec("SET @current_user_id = " . intval($userId));
        } catch (Exception $e) {
            debugLog($debugLogs, 'DB_SET_VAR_ERROR', $e->getMessage());
        }

        // 4. Crear Acta
        debugLog($debugLogs, 'ACTA_CREATE_START', $_POST);
        $actaId = $actaModel->create([
            'promotor_user_id' => $promotorUserId,
            'ruta_promotor_id' => $_POST['ruta_promotor_id'] ?? null,
            'punto_visita_nombre' => $_POST['punto_visita_nombre'],
            'punto_visita_direccion' => $_POST['punto_visita_direccion'] ?? null,
            'receptor_nombre' => $_POST['receptor_nombre'],
            'receptor_telefono' => $_POST['receptor_telefono'] ?? null,
            'receptor_email' => $_POST['receptor_email'] ?? null,
            'receptor_direccion' => $_POST['receptor_direccion'] ?? null,
            'observacion' => $_POST['observacion'] ?? null,
            'firma_digital' => $_POST['firma_digital'] ?? null,
            'huella_digital' => $_POST['huella_digital'] ?? null,
            'latitud' => $_POST['latitud'] ?? null,
            'longitud' => $_POST['longitud'] ?? null
        ]);

        if ($actaId) {
            debugLog($debugLogs, 'ACTA_CREATED_ID', $actaId);

            // 5. Auditoría (SOSPECHOSO 1)
            debugLog($debugLogs, 'AUDITORIA_START', ['user_id' => $userId, 'acta_id' => $actaId]);
            $auditoriaModel->registrar(
                $userId, // <-- Si esto es null, fallará aquí si la tabla pide usuario_id
                'CREATE',
                'actas_visita',
                $actaId,
                ['punto_visita' => $_POST['punto_visita_nombre']]
            );

            // 6. Fotos
            // Handle photo uploads
            $uploadDir = __DIR__ . '/../uploads/actas/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            for ($i = 0; $i < 3; $i++) {
                if (isset($_FILES["foto_$i"]) && $_FILES["foto_$i"]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES["foto_$i"];
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'acta_' . $actaId . '_foto_' . $i . '_' . time() . '.' . $extension;
                    $filepath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $urlFoto = '/promotores-campo-system/uploads/actas/' . $filename;
                        $lat = $_POST["foto_{$i}_lat"] ?? null;
                        $lng = $_POST["foto_{$i}_lng"] ?? null;

                        $actaModel->agregarFotografia($actaId, $urlFoto, $lat, $lng);
                    }
                }
            }

            // 7. Notificaciones (SOSPECHOSO 2)
            require_once __DIR__ . '/../db/SupervisorPromotor.php';
            $spModel = new SupervisorPromotor();
            $supervisores = $spModel->getSupervisoresByPromotor($promotorUserId);
            debugLog($debugLogs, 'SUPERVISORES_FOUND', $supervisores);

            if (!empty($supervisores)) {
                foreach ($supervisores as $supervisor) {
                    $s_id = $supervisor['id'] ?? null;
                    debugLog($debugLogs, 'NOTIFYING_SUPERVISOR', $s_id);
                    $notifModel->create(
                        $s_id,
                        'Nueva acta de visita registrada',
                        'mensaje',
                        $actaId
                    );
                }
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'acta_id' => $actaId,
                'debug' => $debugLogs // Ver logs en éxito
            ]);
        } else {
            throw new Exception('Error al crear acta en la base de datos.');
        }
    } elseif ($method === 'GET') {
        if (isset($_GET['id'])) {
            $acta = $actaModel->getById($_GET['id']);
            $fotografias = $actaModel->getFotografias($_GET['id']);
            echo json_encode(['success' => true, 'acta' => $acta, 'fotografias' => $fotografias]);
        } elseif (isset($_GET['promotor_id'])) {
            $actas = $actaModel->getByPromotor($_GET['promotor_id']);
            echo json_encode(['success' => true, 'data' => $actas]);
        } elseif (isset($_GET['supervisor_id'])) {
            $actas = $actaModel->getBySupervisor($_GET['supervisor_id']);
            echo json_encode(['success' => true, 'data' => $actas]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Parámetros no válidos']);
        }
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $debugLogs, // AQUÍ VERÁS DONDE SE QUEDÓ EL PROCESO
        'trace' => $e->getTraceAsString()
    ]);
}
ob_end_flush();
