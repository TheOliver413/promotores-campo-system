<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

ob_start();

require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/Auditoria.php';

ob_end_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'], ['Promotor'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$auditoriaModel = new Auditoria();

$action = $_POST['action'] ?? '';

if (empty($action)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

try {
    switch ($action) {
        case 'actualizar_punto':
            $rutaId = $_POST['ruta_id'] ?? 0;
            $puntoIndex = isset($_POST['punto_index']) ? (int)$_POST['punto_index'] : -1;
            $estado = $_POST['estado'] ?? 'pendiente';
            $notas = $_POST['notas'] ?? '';

            if ($puntoIndex < 0) {
                echo json_encode(['success' => false, 'message' => 'Índice de punto inválido']);
                exit;
            }

            // Verify that the route belongs to the promoter
            $stmt = $db->prepare("SELECT id, puntos_ruta FROM rutas_promotores WHERE id = ? AND promotor_user_id = ?");
            $stmt->execute([$rutaId, $_SESSION['user_id']]);
            $ruta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ruta) {
                echo json_encode(['success' => false, 'message' => 'Ruta no encontrada o no autorizada']);
                exit;
            }

            // Parse puntos_ruta JSON
            $puntosRuta = json_decode($ruta['puntos_ruta'], true);

            if (!is_array($puntosRuta) || !isset($puntosRuta[$puntoIndex])) {
                echo json_encode(['success' => false, 'message' => 'Punto no encontrado en la ruta']);
                exit;
            }

            // Update point data
            $puntosRuta[$puntoIndex]['estado'] = $estado;
            $puntosRuta[$puntoIndex]['notas'] = $notas;
            $puntosRuta[$puntoIndex]['visitado'] = ($estado !== 'pendiente');
            $puntosRuta[$puntoIndex]['completado'] = ($estado !== 'pendiente');
            $puntosRuta[$puntoIndex]['fecha_actualizacion'] = date('Y-m-d H:i:s');

            // Handle file uploads (evidencias)
            $evidencias = [];
            if (isset($_FILES['evidencias']) && is_array($_FILES['evidencias']['tmp_name'])) {
                $uploadDir = __DIR__ . '/../uploads/evidencias/';

                // Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                foreach ($_FILES['evidencias']['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['evidencias']['error'][$key] === UPLOAD_ERR_OK) {
                        $fileName = uniqid() . '_' . basename($_FILES['evidencias']['name'][$key]);
                        $targetPath = $uploadDir . $fileName;

                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $evidencias[] = 'uploads/evidencias/' . $fileName;
                        }
                    }
                }
            }

            // Add evidencias to point if any were uploaded
            if (!empty($evidencias)) {
                if (!isset($puntosRuta[$puntoIndex]['evidencias'])) {
                    $puntosRuta[$puntoIndex]['evidencias'] = [];
                }
                $puntosRuta[$puntoIndex]['evidencias'] = array_merge(
                    $puntosRuta[$puntoIndex]['evidencias'] ?? [],
                    $evidencias
                );
            }

            // Update the route in database
            $stmt = $db->prepare("UPDATE rutas_promotores SET puntos_ruta = ? WHERE id = ?");
            $stmt->execute([json_encode($puntosRuta), $rutaId]);

            // Also update puntos_ruta table if it exists
            $stmt = $db->prepare("SELECT id FROM puntos_ruta WHERE ruta_id = ? AND orden = ?");
            $stmt->execute([$rutaId, $puntoIndex + 1]);
            $puntoRutaId = $stmt->fetchColumn();

            if ($puntoRutaId) {
                $stmt = $db->prepare("UPDATE puntos_ruta SET estado = ?, notas = ?, visitado = ?, fecha_visita = ? WHERE id = ?");
                $stmt->execute([
                    $estado,
                    $notas,
                    ($estado !== 'pendiente') ? 1 : 0,
                    ($estado !== 'pendiente') ? date('Y-m-d H:i:s') : null,
                    $puntoRutaId
                ]);
            }

            // Register audit
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'UPDATE',
                'rutas_promotores',
                $rutaId,
                "Punto {$puntoIndex} actualizado con estado: {$estado}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'Punto actualizado exitosamente',
                'data' => [
                    'punto_index' => $puntoIndex,
                    'estado' => $estado,
                    'evidencias' => $evidencias
                ]
            ]);
            break;

        case 'reordenar_puntos':
            $input = json_decode(file_get_contents('php://input'), true);
            $rutaId = $input['ruta_id'] ?? 0;
            $puntosOrden = $input['puntos'] ?? [];

            if (empty($rutaId) || empty($puntosOrden)) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                exit;
            }

            // Verify that the route belongs to the promoter
            $stmt = $db->prepare("SELECT id, puntos_ruta FROM rutas_promotores WHERE id = ? AND promotor_user_id = ?");
            $stmt->execute([$rutaId, $_SESSION['user_id']]);
            $ruta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ruta) {
                echo json_encode(['success' => false, 'message' => 'Ruta no encontrada o no autorizada']);
                exit;
            }

            // Update orden in puntos_ruta table
            $db->beginTransaction();

            try {
                foreach ($puntosOrden as $punto) {
                    $puntoId = $punto['id'] ?? null;
                    $orden = $punto['orden'] ?? 0;

                    if ($puntoId && $orden > 0) {
                        $stmt = $db->prepare("UPDATE puntos_ruta SET orden = ? WHERE id = ? AND ruta_id = ?");
                        $stmt->execute([$orden, $puntoId, $rutaId]);
                    }
                }

                $db->commit();

                // Register audit
                $auditoriaModel->registrar(
                    $_SESSION['user_id'],
                    'UPDATE',
                    'rutas_promotores',
                    $rutaId,
                    "Orden de puntos actualizado"
                );

                echo json_encode([
                    'success' => true,
                    'message' => 'Orden de puntos actualizado exitosamente'
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    error_log("Error en punto_ruta_crud.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
