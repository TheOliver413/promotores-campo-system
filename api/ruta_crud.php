<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

ob_start();

require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/Auditoria.php';
require_once '../lib/PHPMailer.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

function sendJsonResponse($data) {
    ob_clean();
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(['success' => false, 'message' => 'No autorizado - Sesión no válida']);
}

$userRole = $_SESSION['role_name'] ?? '';
$allowedRoles = ['Supervisor', 'Promotor'];

if (!in_array($userRole, $allowedRoles)) {
    sendJsonResponse(['success' => false, 'message' => 'No autorizado - Rol no permitido']);
}

$db = Database::getInstance()->getConnection();
$auditoriaModel = new Auditoria();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (empty($action)) {
    $input = file_get_contents('php://input');
    $jsonData = json_decode($input, true);
    $action = $jsonData['action'] ?? '';
}

error_log("[v0] ruta_crud.php - Action: {$action}, User: {$_SESSION['user_id']}, Role: {$userRole}");

try {
    switch ($action) {
        case 'list':
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
            $offset = ($page - 1) * $perPage;

            $filtroPromotor = $_GET['filtro_promotor'] ?? '';
            $filtroEstado = $_GET['filtro_estado'] ?? '';
            $filtroFecha = $_GET['filtro_fecha'] ?? '';

            $whereConditions = ["sp.supervisor_id = ?"];
            $params = [$_SESSION['user_id']];

            if ($filtroPromotor) {
                $whereConditions[] = "rp.promotor_user_id = ?";
                $params[] = $filtroPromotor;
            }

            if ($filtroEstado) {
                $whereConditions[] = "rp.estado = ?";
                $params[] = $filtroEstado;
            }

            if ($filtroFecha) {
                $whereConditions[] = "DATE(rp.fecha_planificada) = ?";
                $params[] = $filtroFecha;
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Get total count
            $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM rutas_promotores rp INNER JOIN supervisor_promotores sp ON rp.promotor_user_id = sp.promotor_id WHERE {$whereClause}");
            $stmtCount->execute($params);
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            // Get paginated results
            $stmt = $db->prepare("SELECT rp.id as ruta_id, rp.promotor_user_id, rp.proyecto_id, rp.nombre_ruta, rp.fecha_planificada, rp.estado, rp.distancia_total_km, rp.tiempo_total_minutos, u.nombre_completo as nombre_promotor, p.nombre_proyecto, (SELECT COUNT(*) FROM puntos_ruta WHERE ruta_id = rp.id) as num_puntos FROM rutas_promotores rp INNER JOIN usuarios u ON rp.promotor_user_id = u.id INNER JOIN proyectos p ON rp.proyecto_id = p.id INNER JOIN supervisor_promotores sp ON rp.promotor_user_id = sp.promotor_id WHERE {$whereClause} ORDER BY rp.fecha_planificada DESC LIMIT ? OFFSET ?");
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->execute($params);
            $rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $rutas,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage)
                ]
            ]);
            break;

        case 'get':
            $id = $_GET['id'] ?? 0;

            if ($userRole === 'Promotor') {
                $stmt = $db->prepare("SELECT rp.*, u.nombre_completo as nombre_promotor, p.nombre_proyecto FROM rutas_promotores rp INNER JOIN usuarios u ON rp.promotor_user_id = u.id INNER JOIN proyectos p ON rp.proyecto_id = p.id WHERE rp.id = ? AND rp.promotor_user_id = ?");
                $stmt->execute([$id, $_SESSION['user_id']]);
            } else {
                // Supervisor can view routes of their promoters
                $stmt = $db->prepare("SELECT rp.*, u.nombre_completo as nombre_promotor, p.nombre_proyecto FROM rutas_promotores rp INNER JOIN usuarios u ON rp.promotor_user_id = u.id INNER JOIN proyectos p ON rp.proyecto_id = p.id INNER JOIN supervisor_promotores sp ON rp.promotor_user_id = sp.promotor_id WHERE rp.id = ? AND sp.supervisor_id = ?");
                $stmt->execute([$id, $_SESSION['user_id']]);
            }

            $ruta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ruta) {
                echo json_encode(['success' => false, 'message' => 'Ruta no encontrada o no autorizada']);
                exit;
            }

            $stmt = $db->prepare("SELECT pr.*, uc.nombre_ubicacion, uc.direccion as ubicacion_direccion, uc.cliente_id, c.nombre_empresa FROM puntos_ruta pr LEFT JOIN ubicaciones_clientes uc ON pr.ubicacion_cliente_id = uc.id LEFT JOIN clientes c ON uc.cliente_id = c.id WHERE pr.ruta_id = ? ORDER BY pr.orden ASC");
            $stmt->execute([$id]);
            $puntos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $ruta['puntos'] = array_map(function ($p) {
                return [
                    'punto_id' => $p['id'],
                    'id' => $p['id'],
                    'ruta_punto_id' => $p['id'],
                    'nombre' => $p['nombre'],
                    'direccion' => $p['direccion'] ?? $p['ubicacion_direccion'] ?? 'Sin dirección',
                    'latitud' => $p['latitud'],
                    'longitud' => $p['longitud'],
                    'lat' => $p['latitud'], // Alias for compatibility
                    'lng' => $p['longitud'], // Alias for compatibility
                    'lon' => $p['longitud'], // Alias for compatibility
                    'orden' => $p['orden'],
                    'visitado' => $p['visitado'] ?? 0,
                    'completado' => $p['visitado'] ?? 0,
                    'estado' => $p['estado'] ?? 'pendiente',
                    'notas' => $p['notas'],
                    'nombre_ubicacion' => $p['nombre_ubicacion'],
                    'nombre_empresa' => $p['nombre_empresa']
                ];
            }, $puntos);

            echo json_encode(['success' => true, 'data' => $ruta]);
            break;

        case 'ubicaciones_disponibles':
            $proyectoId = $_GET['proyecto_id'] ?? 0;

            $stmt = $db->prepare("SELECT uc.*, c.nombre_empresa FROM ubicaciones_clientes uc INNER JOIN clientes c ON uc.cliente_id = c.id INNER JOIN proyecto_clientes pc ON c.id = pc.cliente_id WHERE pc.proyecto_id = ? AND uc.activo = 1 ORDER BY c.nombre_empresa, uc.nombre_ubicacion");
            $stmt->execute([$proyectoId]);
            $ubicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $ubicaciones]);
            break;

        case 'calcular_ruta':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            // Soportar tanto el formato antiguo (array directo) como el nuevo (objeto con action)
            $puntos = $data['puntos'] ?? $data ?? [];

            if (!$puntos || !is_array($puntos) || count($puntos) < 2) {
                echo json_encode(['success' => false, 'message' => 'Se requieren al menos 2 puntos']);
                exit;
            }

            $coordenadas = array_map(function ($p) {
                $lng = floatval($p['longitud'] ?? $p['lng'] ?? $p['lon'] ?? 0);
                $lat = floatval($p['latitud'] ?? $p['lat'] ?? 0);
                return [$lng, $lat];
            }, $puntos);

            foreach ($coordenadas as $coord) {
                if ($coord[1] < -90 || $coord[1] > 90 || $coord[0] < -180 || $coord[0] > 180) {
                    echo json_encode(['success' => false, 'message' => 'Coordenadas inválidas detectadas']);
                    exit;
                }
            }

            $orsUrl = 'https://api.openrouteservice.org/v2/directions/driving-car/geojson';
            $orsData = [
                'coordinates' => $coordenadas,
                'instructions' => false
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $orsUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orsData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6ImJjMzA4YzlhZDNkNzQzYmU4OWViM2RiYjIzNzNiZWQ2IiwiaCI6Im11cm11cjY0In0='
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log("Error OpenRouteService: HTTP $httpCode - $response");
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al calcular ruta con OpenRouteService',
                    'http_code' => $httpCode,
                    'error' => $curlError,
                    'response' => $response
                ]);
                exit;
            }

            $rutaData = json_decode($response, true);

            if (!isset($rutaData['features'][0])) {
                echo json_encode(['success' => false, 'message' => 'No se pudo calcular la ruta']);
                exit;
            }

            $feature = $rutaData['features'][0];

            echo json_encode([
                'success' => true,
                'data' => [
                    'geometry' => $feature['geometry'],
                    'distancia_km' => round($feature['properties']['summary']['distance'] / 1000, 2),
                    'tiempo_minutos' => round($feature['properties']['summary']['duration'] / 60)
                ]
            ]);
            break;

        case 'geocode':
            $direccion = $_GET['direccion'] ?? '';
            $pais = $_GET['pais'] ?? 'Colombia';

            if (empty($direccion)) {
                echo json_encode(['success' => false, 'message' => 'Dirección requerida']);
                exit;
            }

            $query = urlencode($direccion . ', ' . $pais);
            $url = "https://nominatim.openstreetmap.org/search?q={$query}&format=json&limit=1";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'PromotoresCampoSystem/1.0');
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);

            if (empty($data)) {
                echo json_encode(['success' => false, 'message' => 'No se pudo geocodificar la dirección']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'latitud' => floatval($data[0]['lat']),
                    'longitud' => floatval($data[0]['lon']),
                    'display_name' => $data[0]['display_name']
                ]
            ]);
            break;

        case 'create':
        case 'update':
            error_log("[v0] Create/Update ruta - POST data: " . print_r($_POST, true));
            
            $rutaId = $_POST['ruta_id'] ?? null;
            $promotorId = $_POST['promotor_id'] ?? 0;
            $proyectoId = $_POST['proyecto_id'] ?? 0;
            $nombreRuta = $_POST['nombre_ruta'] ?? '';
            $fechaPlanificada = $_POST['fecha_planificada'] ?? '';
            $puntos = json_decode($_POST['puntos'] ?? '[]', true);

            if (empty($nombreRuta)) {
                sendJsonResponse(['success' => false, 'message' => 'El nombre de la ruta es requerido']);
            }
            
            if (empty($fechaPlanificada)) {
                sendJsonResponse(['success' => false, 'message' => 'La fecha planificada es requerida']);
            }
            
            if (!$promotorId || $promotorId == 0) {
                sendJsonResponse(['success' => false, 'message' => 'Debe seleccionar un promotor']);
            }
            
            if (!$proyectoId || $proyectoId == 0) {
                sendJsonResponse(['success' => false, 'message' => 'Debe seleccionar un proyecto']);
            }
            
            if (empty($puntos) || !is_array($puntos)) {
                sendJsonResponse(['success' => false, 'message' => 'Debe agregar al menos un punto a la ruta']);
            }

            error_log("[v0] Validaciones pasadas - Promotor: {$promotorId}, Proyecto: {$proyectoId}, Puntos: " . count($puntos));

            $stmt = $db->prepare("SELECT 1 FROM supervisor_promotores WHERE supervisor_id = ? AND promotor_id = ?");
            $stmt->execute([$_SESSION['user_id'], $promotorId]);

            if (!$stmt->fetch()) {
                error_log("[v0] Permiso denegado - Supervisor {$_SESSION['user_id']} no supervisa a promotor {$promotorId}");
                sendJsonResponse(['success' => false, 'message' => 'No tiene permisos para asignar rutas a este promotor']);
            }

            $stmt = $db->prepare("SELECT id, nombre_proyecto FROM proyectos WHERE id = ?");
            $stmt->execute([$proyectoId]);
            $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$proyecto) {
                error_log("[v0] Proyecto no encontrado o inactivo - ID: {$proyectoId}");
                sendJsonResponse(['success' => false, 'message' => 'El proyecto seleccionado no existe o está inactivo. Por favor seleccione otro proyecto.']);
            }

            error_log("[v0] Proyecto validado: {$proyecto['nombre_proyecto']}");

            $db->beginTransaction();

            try {
                $rutaOptimizada = null;
                $distanciaTotal = null;
                $tiempoTotal = null;

                if (count($puntos) > 1) {
                    error_log("[v0] Calculando ruta optimizada para " . count($puntos) . " puntos");
                    
                    $coordenadas = array_map(function ($p) {
                        return [floatval($p['longitud']), floatval($p['latitud'])];
                    }, $puntos);

                    $orsUrl = 'https://api.openrouteservice.org/v2/directions/driving-car/geojson';
                    $orsData = [
                        'coordinates' => $coordenadas,
                        'instructions' => false
                    ];

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $orsUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orsData));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Authorization: eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6ImJjMzA4YzlhZDNkNzQzYmU4OWViM2RiYjIzNzNiZWQ2IiwiaCI6Im11cm11cjY0In0='
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    if ($httpCode === 200) {
                        $rutaData = json_decode($response, true);
                        if (isset($rutaData['features'][0])) {
                            $rutaOptimizada = json_encode($rutaData['features'][0]['geometry']);
                            $distanciaTotal = round($rutaData['features'][0]['properties']['summary']['distance'] / 1000, 2);
                            $tiempoTotal = round($rutaData['features'][0]['properties']['summary']['duration'] / 60);
                            error_log("[v0] Ruta optimizada calculada - Distancia: {$distanciaTotal}km, Tiempo: {$tiempoTotal}min");
                        }
                    } else {
                        error_log("[v0] Warning: OpenRouteService error HTTP {$httpCode} - continuando sin ruta optimizada");
                    }
                }

                $puntosResumen = array_map(function ($p, $index) {
                    return [
                        'orden' => $index + 1,
                        'nombre' => $p['nombre'] ?? "Punto " . ($index + 1),
                        'latitud' => floatval($p['latitud']),
                        'longitud' => floatval($p['longitud'])
                    ];
                }, $puntos, array_keys($puntos));

                if ($rutaId) {
                    error_log("[v0] Actualizando ruta ID: {$rutaId}");
                    
                    $stmt = $db->prepare("UPDATE rutas_promotores SET promotor_user_id = ?, proyecto_id = ?, nombre_ruta = ?, fecha_planificada = ?, puntos_ruta = ?, ruta_optimizada = ?, distancia_total_km = ?, tiempo_total_minutos = ? WHERE id = ?");
                    $stmt->execute([
                        $promotorId,
                        $proyectoId,
                        $nombreRuta,
                        $fechaPlanificada,
                        json_encode($puntosResumen),
                        $rutaOptimizada,
                        $distanciaTotal,
                        $tiempoTotal,
                        $rutaId
                    ]);

                    // Eliminar puntos anteriores
                    $stmt = $db->prepare("DELETE FROM puntos_ruta WHERE ruta_id = ?");
                    $stmt->execute([$rutaId]);

                    $message = 'Ruta actualizada exitosamente';
                    $tipoNotificacion = 'ruta_actualizada';
                } else {
                    error_log("[v0] Creando nueva ruta");
                    
                    $stmt = $db->prepare("INSERT INTO rutas_promotores (promotor_user_id, proyecto_id, nombre_ruta, fecha_planificada, puntos_ruta, estado, ruta_optimizada, distancia_total_km, tiempo_total_minutos) VALUES (?, ?, ?, ?, ?, 'pendiente', ?, ?, ?)");
                    $stmt->execute([
                        $promotorId,
                        $proyectoId,
                        $nombreRuta,
                        $fechaPlanificada,
                        json_encode($puntosResumen),
                        $rutaOptimizada,
                        $distanciaTotal,
                        $tiempoTotal
                    ]);
                    $rutaId = $db->lastInsertId();
                    
                    error_log("[v0] Ruta creada con ID: {$rutaId}");

                    $message = 'Ruta creada exitosamente';
                    $tipoNotificacion = 'ruta_asignada';
                }

                $stmtPunto = $db->prepare("INSERT INTO puntos_ruta (ruta_id, orden, nombre, direccion, latitud, longitud, ubicacion_cliente_id, notas, tiempo_estimado_minutos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($puntos as $index => $punto) {
                    $stmtPunto->execute([
                        $rutaId,
                        $index + 1,
                        $punto['nombre'] ?? "Punto " . ($index + 1),
                        $punto['direccion'] ?? null,
                        floatval($punto['latitud']),
                        floatval($punto['longitud']),
                        $punto['ubicacion_cliente_id'] ?? null,
                        $punto['notas'] ?? null,
                        $punto['tiempo_estimado_minutos'] ?? 30
                    ]);
                }
                
                error_log("[v0] Insertados " . count($puntos) . " puntos de ruta");

                $stmt = $db->prepare("SELECT nombre_completo, email FROM usuarios WHERE id = ?");
                $stmt->execute([$promotorId]);
                $promotor = $stmt->fetch(PDO::FETCH_ASSOC);

                try {
                    $rutaData = [
                        'nombre_ruta' => $nombreRuta,
                        'fecha_planificada' => $fechaPlanificada,
                        'puntos' => $puntos,
                        'nombre_proyecto' => $proyecto['nombre_proyecto']
                    ];

                    if ($tipoNotificacion === 'ruta_asignada') {
                        // Placeholder for email sending logic
                    } else {
                        // Placeholder for email sending logic
                    }
                    
                    error_log("[v0] Email enviado a {$promotor['email']}");
                } catch (Exception $emailError) {
                    error_log("[v0] Error al enviar email: " . $emailError->getMessage());
                    // No fallar la transacción si el email falla
                }

                $stmt = $db->prepare("INSERT INTO notificaciones (usuario_id, mensaje, tipo_notificacion, referencia_id) VALUES (?, ?, ?, ?)");
                $mensajeNotif = $tipoNotificacion === 'ruta_asignada' 
                    ? "Se te ha asignado una nueva ruta: {$nombreRuta} para el {$fechaPlanificada}" 
                    : "La ruta {$nombreRuta} ha sido actualizada";

                $stmt->execute([$promotorId, $mensajeNotif, $tipoNotificacion, $rutaId]);

                // Auditoría
                $auditoriaModel->registrar(
                    $_SESSION['user_id'],
                    $rutaId ? 'UPDATE' : 'INSERT',
                    'rutas_promotores',
                    $rutaId,
                    $message
                );

                $db->commit();
                
                error_log("[v0] Ruta guardada exitosamente - ID: {$rutaId}");

                sendJsonResponse([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'ruta_id' => $rutaId,
                        'distancia_km' => $distanciaTotal,
                        'tiempo_minutos' => $tiempoTotal
                    ]
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                error_log("[v0] Error en transacción: " . $e->getMessage());
                error_log("[v0] Stack trace: " . $e->getTraceAsString());
                throw $e;
            }
            break;

        case 'delete':
            $id = $_GET['id'] ?? 0;

            // Verificar permisos
            $stmt = $db->prepare("SELECT rp.promotor_user_id FROM rutas_promotores rp INNER JOIN supervisor_promotores sp ON rp.promotor_user_id = sp.promotor_id WHERE rp.id = ? AND sp.supervisor_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);

            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar esta ruta']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM rutas_promotores WHERE id = ?");
            $stmt->execute([$id]);

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'DELETE',
                'rutas_promotores',
                $id,
                'Ruta eliminada'
            );

            echo json_encode(['success' => true, 'message' => 'Ruta eliminada exitosamente']);
            break;

        case 'config_mapa':
            $proyectoId = $_GET['proyecto_id'] ?? 0;

            $stmt = $db->prepare("SELECT c.mapa_centro_lat, c.mapa_centro_lng, c.mapa_zoom, c.pais FROM clientes c INNER JOIN proyecto_clientes pc ON c.id = pc.cliente_id WHERE pc.proyecto_id = ? LIMIT 1");
            $stmt->execute([$proyectoId]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                $config = [
                    'mapa_centro_lat' => 4.570868,
                    'mapa_centro_lng' => -74.297333,
                    'mapa_zoom' => 6,
                    'pais' => 'Colombia'
                ];
            }

            echo json_encode(['success' => true, 'data' => $config]);
            break;

        case 'get_cliente_from_proyecto':
            $proyectoId = $_GET['proyecto_id'] ?? 0;

            $stmt = $db->prepare("SELECT cliente_id FROM proyecto_clientes WHERE proyecto_id = ? LIMIT 1");
            $stmt->execute([$proyectoId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                echo json_encode(['success' => false, 'message' => 'No se encontró cliente asociado al proyecto']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => ['cliente_id' => $result['cliente_id']]]);

            break;

        case 'iniciar_ruta':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            $rutaId = $data['ruta_id'] ?? 0;
            $latitudInicio = $data['latitud_inicio'] ?? null;
            $longitudInicio = $data['longitud_inicio'] ?? null;

            if (!$rutaId) {
                echo json_encode(['success' => false, 'message' => 'ID de ruta requerido']);
                exit;
            }

            // Verificar que la ruta pertenece al promotor
            $stmt = $db->prepare("SELECT estado FROM rutas_promotores WHERE id = ? AND promotor_user_id = ?");
            $stmt->execute([$rutaId, $_SESSION['user_id']]);
            $ruta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ruta) {
                echo json_encode(['success' => false, 'message' => 'Ruta no encontrada o no autorizada']);
                exit;
            }

            if ($ruta['estado'] !== 'pendiente' && $ruta['estado'] !== 'pausada') {
                echo json_encode(['success' => false, 'message' => 'La ruta no está en estado pendiente o pausada']);
                exit;
            }

            $stmt = $db->prepare("UPDATE rutas_promotores SET estado = 'en_progreso', fecha_inicio_real = NOW(), hora_inicio_real = NOW(), latitud_inicio = ?, longitud_inicio = ? WHERE id = ?");
            $stmt->execute([$latitudInicio, $longitudInicio, $rutaId]);

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'UPDATE',
                'rutas_promotores',
                $rutaId,
                'Ruta iniciada'
            );

            echo json_encode(['success' => true, 'message' => 'Ruta iniciada exitosamente']);
            break;

        case 'pausar_ruta':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            $rutaId = $data['ruta_id'] ?? 0;

            if (!$rutaId) {
                echo json_encode(['success' => false, 'message' => 'ID de ruta requerido']);
                exit;
            }

            // Verificar que la ruta pertenece al promotor
            $stmt = $db->prepare("SELECT estado FROM rutas_promotores WHERE id = ? AND promotor_user_id = ?");
            $stmt->execute([$rutaId, $_SESSION['user_id']]);
            $ruta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ruta) {
                echo json_encode(['success' => false, 'message' => 'Ruta no encontrada o no autorizada']);
                exit;
            }

            if ($ruta['estado'] !== 'en_progreso') {
                echo json_encode(['success' => false, 'message' => 'La ruta no está en progreso']);
                exit;
            }

            $stmt = $db->prepare("UPDATE rutas_promotores SET estado = 'pausada' WHERE id = ?");
            $stmt->execute([$rutaId]);

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'UPDATE',
                'rutas_promotores',
                $rutaId,
                'Ruta pausada'
            );

            echo json_encode(['success' => true, 'message' => 'Ruta pausada exitosamente']);
            break;

        case 'reanudar_ruta':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            $rutaId = $data['ruta_id'] ?? 0;

            if (!$rutaId) {
                echo json_encode(['success' => false, 'message' => 'ID de ruta requerido']);
                exit;
            }

            // Verificar que la ruta pertenece al promotor
            $stmt = $db->prepare("SELECT estado FROM rutas_promotores WHERE id = ? AND promotor_user_id = ?");
            $stmt->execute([$rutaId, $_SESSION['user_id']]);
            $ruta = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log("[v0] DEBUG reanudar_ruta - Route ID: $rutaId, Found: " . ($ruta ? 'Yes' : 'No') . ", Estado: " . ($ruta['estado'] ?? 'N/A'));

            if (!$ruta) {
                echo json_encode(['success' => false, 'message' => 'Ruta no encontrada o no autorizada']);
                exit;
            }

            if ($ruta['estado'] !== 'pausada') {
                echo json_encode([
                    'success' => false, 
                    'message' => "La ruta no está pausada. Estado actual: {$ruta['estado']}"
                ]);
                exit;
            }

            $stmt = $db->prepare("UPDATE rutas_promotores SET estado = 'en_progreso' WHERE id = ?");
            $stmt->execute([$rutaId]);

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'UPDATE',
                'rutas_promotores',
                $rutaId,
                'Ruta reanudada'
            );

            echo json_encode(['success' => true, 'message' => 'Ruta reanudada exitosamente']);
            break;

        case 'finalizar_ruta':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            $rutaId = $data['ruta_id'] ?? 0;
            $latitudFin = $data['latitud_fin'] ?? null;
            $longitudFin = $data['longitud_fin'] ?? null;

            if (!$rutaId) {
                echo json_encode(['success' => false, 'message' => 'ID de ruta requerido']);
                exit;
            }

            // Verificar que la ruta pertenece al promotor
            $stmt = $db->prepare("SELECT estado, fecha_inicio_real FROM rutas_promotores WHERE id = ? AND promotor_user_id = ?");
            $stmt->execute([$rutaId, $_SESSION['user_id']]);
            $ruta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ruta) {
                echo json_encode(['success' => false, 'message' => 'Ruta no encontrada o no autorizada']);
                exit;
            }

            if ($ruta['estado'] !== 'en_progreso' && $ruta['estado'] !== 'pausada') {
                echo json_encode(['success' => false, 'message' => 'La ruta debe estar en progreso o pausada para finalizarla']);
                exit;
            }

            $duracionRealMinutos = null;
            if ($ruta['fecha_inicio_real']) {
                $fechaInicio = new DateTime($ruta['fecha_inicio_real']);
                $fechaFin = new DateTime();
                $intervalo = $fechaInicio->diff($fechaFin);
                $duracionRealMinutos = ($intervalo->days * 24 * 60) + ($intervalo->h * 60) + $intervalo->i;
            }

            $stmt = $db->prepare("UPDATE rutas_promotores SET estado = 'completada', fecha_fin_real = NOW(), hora_fin_real = NOW(), latitud_fin = ?, longitud_fin = ?, duracion_real_minutos = ? WHERE id = ?");
            $stmt->execute([$latitudFin, $longitudFin, $duracionRealMinutos, $rutaId]);

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'UPDATE',
                'rutas_promotores',
                $rutaId,
                'Ruta finalizada'
            );

            echo json_encode(['success' => true, 'message' => 'Ruta finalizada exitosamente']);
            break;

        default:
            error_log("[v0] Acción no válida: {$action}");
            sendJsonResponse(['success' => false, 'message' => 'Acción no válida: ' . $action]);
            break;
    }
} catch (Exception $e) {
    error_log("[v0] Error en ruta_crud.php: " . $e->getMessage());
    error_log("[v0] Stack trace: " . $e->getTraceAsString());
    
    sendJsonResponse([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
