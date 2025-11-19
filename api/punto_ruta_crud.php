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
            $puntoId = $_POST['punto_id'] ?? 0;
            $puntoIndex = isset($_POST['punto_index']) ? (int)$_POST['punto_index'] : -1;
            $estado = $_POST['estado'] ?? 'pendiente';
            $notas = $_POST['notas'] ?? '';

            if (!$puntoId && $puntoIndex < 0) {
                echo json_encode(['success' => false, 'message' => 'ID o índice de punto requerido']);
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

            // Find punto by ID or index
            $puntoEncontrado = false;
            $puntoActualIndex = -1;
            $puntoData = null;

            if ($puntoId > 0) {
                $stmt = $db->prepare("SELECT * FROM puntos_ruta WHERE id = ? AND ruta_id = ?");
                $stmt->execute([$puntoId, $rutaId]);
                $puntoData = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($puntoData) {
                    $puntoEncontrado = true;
                    $puntoActualIndex = $puntoData['orden'] - 1;
                }
            } elseif ($puntoIndex >= 0 && isset($puntosRuta[$puntoIndex])) {
                $puntoEncontrado = true;
                $puntoActualIndex = $puntoIndex;

                // Get punto_id from puntos_ruta table
                $stmt = $db->prepare("SELECT * FROM puntos_ruta WHERE ruta_id = ? AND orden = ?");
                $stmt->execute([$rutaId, $puntoIndex + 1]);
                $puntoData = $stmt->fetch(PDO::FETCH_ASSOC);
                $puntoId = $puntoData ? $puntoData['id'] : null;
            }

            if (!$puntoEncontrado || $puntoActualIndex < 0) {
                echo json_encode(['success' => false, 'message' => 'Punto no encontrado en la ruta']);
                exit;
            }

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
                            $evidencias[] = [
                                'url' => '../uploads/evidencias/' . $fileName,
                                'tipo' => $_FILES['evidencias']['type'][$key],
                                'fecha' => date('Y-m-d H:i:s')
                            ];
                        }
                    }
                }
            }

            // Update puntos_ruta table
            if ($puntoId) {
                $db->beginTransaction();

                try {
                    $stmt = $db->prepare("UPDATE puntos_ruta SET estado = ?, notas = ?, visitado = ?, fecha_visita = ? WHERE id = ?");
                    $stmt->execute([
                        $estado,
                        $notas,
                        ($estado !== 'pendiente') ? 1 : 0,
                        ($estado !== 'pendiente') ? date('Y-m-d H:i:s') : null,
                        $puntoId
                    ]);

                    if (!empty($evidencias)) {
                        // Get existing evidencias
                        $stmt = $db->prepare("SELECT evidencias FROM puntos_ruta WHERE id = ?");
                        $stmt->execute([$puntoId]);
                        $existingEvidencias = $stmt->fetchColumn();

                        $evidenciasArray = $existingEvidencias ? json_decode($existingEvidencias, true) : [];
                        if (!is_array($evidenciasArray)) {
                            $evidenciasArray = [];
                        }

                        $evidenciasArray = array_merge($evidenciasArray, $evidencias);

                        // Update evidencias in database
                        $stmt = $db->prepare("UPDATE puntos_ruta SET evidencias = ? WHERE id = ?");
                        $stmt->execute([json_encode($evidenciasArray), $puntoId]);
                    }

                    if (is_array($puntosRuta) && isset($puntosRuta[$puntoActualIndex])) {
                        $puntosRuta[$puntoActualIndex]['estado'] = $estado;
                        $puntosRuta[$puntoActualIndex]['notas'] = $notas;
                        $puntosRuta[$puntoActualIndex]['visitado'] = ($estado !== 'pendiente');
                        $puntosRuta[$puntoActualIndex]['completado'] = ($estado !== 'pendiente');
                        $puntosRuta[$puntoActualIndex]['fecha_actualizacion'] = date('Y-m-d H:i:s');

                        if (!empty($evidencias)) {
                            if (!isset($puntosRuta[$puntoActualIndex]['evidencias'])) {
                                $puntosRuta[$puntoActualIndex]['evidencias'] = [];
                            }
                            $puntosRuta[$puntoActualIndex]['evidencias'] = array_merge(
                                $puntosRuta[$puntoActualIndex]['evidencias'] ?? [],
                                $evidencias
                            );
                        }

                        $stmt = $db->prepare("UPDATE rutas_promotores SET puntos_ruta = ? WHERE id = ?");
                        $stmt->execute([json_encode($puntosRuta), $rutaId]);
                    }

                    if ($estado !== 'pendiente') {
                        require_once '../db/Actividad.php';
                        require_once '../db/Jornada.php';
                        require_once '../db/Evidencia.php';
                        require_once '../db/RutaPromotor.php';

                        $actividadModel = new Actividad();
                        $jornadaModel = new Jornada();
                        $evidenciaModel = new Evidencia();
                        $rutaModel = new RutaPromotor();

                        // Get active jornada
                        $jornadaActiva = $jornadaModel->getJornadaActiva($_SESSION['user_id']);

                        $stmt = $db->prepare("SELECT proyecto_id FROM rutas_promotores WHERE id = ?");
                        $stmt->execute([$rutaId]);
                        $proyectoId = $stmt->fetchColumn();

                        // Get punto coordinates
                        $latitud = $puntoData['latitud'] ?? ($puntosRuta[$puntoActualIndex]['latitud'] ?? null);
                        $longitud = $puntoData['longitud'] ?? ($puntosRuta[$puntoActualIndex]['longitud'] ?? null);
                        $nombrePunto = $puntoData['nombre'] ?? ($puntosRuta[$puntoActualIndex]['nombre'] ?? 'Punto de ruta');

                        // Get tipo_actividad_id for "Visita" or similar
                        $stmt = $db->prepare("SELECT id FROM tipos_actividad WHERE nombre LIKE '%visita%' OR nombre LIKE '%punto%' LIMIT 1");
                        $stmt->execute();
                        $tipoActividadId = $stmt->fetchColumn();

                        // If no matching activity type, use the first one
                        if (!$tipoActividadId) {
                            $stmt = $db->prepare("SELECT id FROM tipos_actividad ORDER BY id ASC LIMIT 1");
                            $stmt->execute();
                            $tipoActividadId = $stmt->fetchColumn();
                        }

                        if ($latitud && $longitud && $tipoActividadId && $proyectoId) {
                            $actividadId = $actividadModel->create([
                                'jornada_id' => $jornadaActiva ? $jornadaActiva['id'] : null,
                                'promotor_user_id' => $_SESSION['user_id'],
                                'proyecto_id' => $proyectoId,
                                'tipo_actividad_id' => $tipoActividadId,
                                'latitud' => $latitud,
                                'longitud' => $longitud,
                                'notas' => "Visita a: {$nombrePunto}. Estado: {$estado}. " . ($notas ? "Notas: {$notas}" : '')
                            ]);

                            // Link evidencias to activity if any
                            if ($actividadId && !empty($evidencias)) {
                                foreach ($evidencias as $evidencia) {
                                    $evidenciaModel->create($actividadId, [
                                        'tipo_archivo' => $evidencia['tipo'],
                                        'url_archivo' => $evidencia['url'],
                                        'nombre_archivo' => basename($evidencia['url']),
                                        'peso_kb' => 0
                                    ]);
                                }
                            }
                        }
                    }

                    $db->commit();

                    // Get updated punto data
                    $stmt = $db->prepare("SELECT * FROM puntos_ruta WHERE id = ?");
                    $stmt->execute([$puntoId]);
                    $puntoActualizado = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Parse evidencias if stored as JSON
                    if (isset($puntoActualizado['evidencias']) && is_string($puntoActualizado['evidencias'])) {
                        $puntoActualizado['evidencias'] = json_decode($puntoActualizado['evidencias'], true);
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
            }

            // Register audit
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'UPDATE',
                'rutas_promotores',
                $rutaId,
                "Punto actualizado con estado: {$estado}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'Punto actualizado exitosamente',
                'punto_data' => $puntoActualizado ?? [
                    'punto_id' => $puntoId,
                    'estado' => $estado,
                    'notas' => $notas,
                    'evidencias' => $evidencias,
                    'visitado' => ($estado !== 'pendiente')
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
