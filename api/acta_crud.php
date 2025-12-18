<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/ActaVisita.php';
require_once __DIR__ . '/../db/Notificacion.php';

ob_clean();
header('Content-Type: application/json');

requireLogin();

$actaModel = new ActaVisita();
$notifModel = new Notificacion();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        // Create new acta de visita
        $actaId = $actaModel->create([
            'promotor_user_id' => $_POST['promotor_user_id'],
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

            // Get supervisor to notify
            require_once __DIR__ . '/../db/SupervisorPromotor.php';
            $spModel = new SupervisorPromotor();
            $supervisores = $spModel->getSupervisoresByPromotor($_POST['promotor_user_id']);
            
            if (!empty($supervisores)) {
                foreach ($supervisores as $supervisor) {
                    $notifModel->create(
                        $supervisor['supervisor_id'],
                        'Nueva acta de visita registrada',
                        'mensaje',
                        $actaId
                    );
                }
            }

            ob_clean();
            echo json_encode(['success' => true, 'acta_id' => $actaId]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Error al crear acta']);
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
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

ob_end_flush();
?>
