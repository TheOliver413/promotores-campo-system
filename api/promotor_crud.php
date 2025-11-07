<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/User.php';
require_once '../db/SupervisorPromotor.php';
require_once '../db/Auditoria.php';

header('Content-Type: application/json');

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$spModel = new SupervisorPromotor();
$auditoriaModel = new Auditoria();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Obtener un promotor específico
            $id = $_GET['id'] ?? 0;
            $promotor = $userModel->getById($id);

            if (!$promotor) {
                throw new Exception('Promotor no encontrado');
            }

            // Verificar que el promotor esté bajo supervisión
            $stmt = $db->prepare("
                SELECT 1 FROM supervisor_promotores 
                WHERE supervisor_id = ? AND promotor_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $id]);

            if (!$stmt->fetch()) {
                throw new Exception('No tiene permisos para ver este promotor');
            }

            echo json_encode($promotor);
            break;

        case 'create':
            $data = [
                'nombre_completo' => $_POST['nombre_completo'] ?? '',
                'email' => $_POST['email'] ?? '',
                'telefono' => $_POST['telefono'] ?? '',
                'role_id' => 3, // Promotor
                'estado' => 'activo'
            ];

            if (empty($_POST['password'])) {
                throw new Exception('La contraseña es requerida');
            }

            $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);

            // Create will throw exception if email or telefono already exists
            $promotorId = $userModel->create($data);

            $spModel->agregarPromotor($_SESSION['user_id'], $promotorId);

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'INSERT',
                'usuarios',
                $promotorId,
                'Promotor creado exitosamente'
            );

            echo json_encode(['success' => true, 'message' => 'Promotor creado exitosamente']);
            break;

        case 'update':
            $promotorId = $_POST['promotor_id'] ?? null;

            if (!$promotorId) {
                throw new Exception('ID de promotor requerido');
            }

            $data = [
                'nombre_completo' => $_POST['nombre_completo'] ?? '',
                'email' => $_POST['email'] ?? '',
                'telefono' => $_POST['telefono'] ?? '',
                'role_id' => 3, // Promotor
                'estado' => 'activo'
            ];

            if (!empty($_POST['password'])) {
                $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }

            // Update will throw exception if email or telefono already exists
            $userModel->update($promotorId, $data);

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'UPDATE',
                'usuarios',
                $promotorId,
                'Promotor actualizado exitosamente'
            );

            echo json_encode(['success' => true, 'message' => 'Promotor actualizado exitosamente']);
            break;

        case 'toggle_estado':
            // Cambiar estado del promotor
            $promotorId = $_POST['promotor_id'] ?? 0;
            $nuevoEstado = $_POST['estado'] ?? 'activo';

            // Verificar que el promotor esté bajo supervisión
            $stmt = $db->prepare("SELECT 1 FROM supervisor_promotores WHERE supervisor_id = ? AND promotor_id = ?");
            $stmt->execute([$_SESSION['user_id'], $promotorId]);

            if (!$stmt->fetch()) {
                throw new Exception('No tiene permisos para modificar este promotor');
            }

            $userModel->updateStatus($promotorId, $nuevoEstado);

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'UPDATE',
                'usuarios',
                $promotorId,
                "Estado cambiado a: $nuevoEstado"
            );

            echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
            break;

        case 'proyectos':
            $promotorId = $_GET['id'] ?? 0;

            $stmt = $db->prepare("
                SELECT p.id, p.nombre_proyecto
                FROM proyectos p
                INNER JOIN proyecto_promotores pp ON p.id = pp.proyecto_id
                WHERE pp.promotor_user_id = ?
                ORDER BY p.nombre_proyecto
            ");
            $stmt->execute([$promotorId]);
            $proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($proyectos ?: []);
            break;

        case 'proyectos_detalle':
            $promotorId = $_GET['id'] ?? 0;

            // Get projects with their clients
            $stmt = $db->prepare("
                SELECT 
                    p.id as proyecto_id,
                    p.nombre_proyecto,
                    p.descripcion as proyecto_descripcion,
                    p.fecha_inicio,
                    p.fecha_fin,
                    p.estado as proyecto_estado,
                    GROUP_CONCAT(DISTINCT c.nombre_empresa ORDER BY c.nombre_empresa SEPARATOR '|') as clientes_nombres,
                    GROUP_CONCAT(DISTINCT c.id ORDER BY c.nombre_empresa SEPARATOR '|') as clientes_ids,
                    GROUP_CONCAT(DISTINCT c.contacto_email ORDER BY c.nombre_empresa SEPARATOR '|') as clientes_contactos,
                    GROUP_CONCAT(DISTINCT c.telefono ORDER BY c.nombre_empresa SEPARATOR '|') as clientes_telefonos
                FROM proyectos p
                INNER JOIN proyecto_promotores pp ON p.id = pp.proyecto_id
                LEFT JOIN proyecto_clientes pc ON p.id = pc.proyecto_id
                LEFT JOIN clientes c ON pc.cliente_id = c.id
                WHERE pp.promotor_user_id = ?
                GROUP BY p.id
                ORDER BY p.nombre_proyecto
            ");
            $stmt->execute([$promotorId]);
            $proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format the data
            $resultado = [];
            foreach ($proyectos as $proyecto) {
                $clientes = [];
                if (!empty($proyecto['clientes_nombres'])) {
                    $nombres = explode('|', $proyecto['clientes_nombres']);
                    $ids = explode('|', $proyecto['clientes_ids']);
                    $contactos = explode('|', $proyecto['clientes_contactos']);
                    $telefonos = explode('|', $proyecto['clientes_telefonos']);

                    for ($i = 0; $i < count($nombres); $i++) {
                        $clientes[] = [
                            'id' => $ids[$i] ?? '',
                            'nombre_empresa' => $nombres[$i] ?? '',
                            'contacto_principal' => $contactos[$i] ?? '',
                            'telefono' => $telefonos[$i] ?? ''
                        ];
                    }
                }

                $resultado[] = [
                    'proyecto_id' => $proyecto['proyecto_id'],
                    'nombre_proyecto' => $proyecto['nombre_proyecto'],
                    'descripcion' => $proyecto['proyecto_descripcion'],
                    'fecha_inicio' => $proyecto['fecha_inicio'],
                    'fecha_fin' => $proyecto['fecha_fin'],
                    'estado' => $proyecto['proyecto_estado'],
                    'clientes' => $clientes
                ];
            }

            echo json_encode($resultado);
            break;

        case 'asignar_proyectos':
            // Asignar proyectos a un promotor
            $promotorId = $_POST['promotor_id'] ?? 0;
            $proyectos = $_POST['proyectos'] ?? [];

            // Verificar que el promotor esté bajo supervisión
            $stmt = $db->prepare("
                SELECT 1 FROM supervisor_promotores 
                WHERE supervisor_id = ? AND promotor_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $promotorId]);

            if (!$stmt->fetch()) {
                throw new Exception('No tiene permisos para asignar proyectos a este promotor');
            }

            if (!empty($proyectos)) {
                $placeholders = implode(',', array_fill(0, count($proyectos), '?'));
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM proyectos WHERE id IN ($placeholders)");
                $stmt->execute($proyectos);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result['total'] != count($proyectos)) {
                    throw new Exception('Uno o más proyectos no existen. Por favor, contacte al administrador para verificar los proyectos disponibles.');
                }

                $stmt = $db->prepare("
                    SELECT COUNT(DISTINCT pc.proyecto_id) as total
                    FROM proyecto_clientes pc
                    WHERE pc.proyecto_id IN ($placeholders)
                ");
                $stmt->execute($proyectos);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result['total'] != count($proyectos)) {
                    throw new Exception('Uno o más proyectos no tienen clientes asignados. Por favor, contacte al administrador para configurar los clientes del proyecto.');
                }
            }

            $db->beginTransaction();

            try {
                // Eliminar asignaciones anteriores
                $stmt = $db->prepare("DELETE FROM proyecto_promotores WHERE promotor_user_id = ?");
                $stmt->execute([$promotorId]);

                if (!empty($proyectos)) {
                    $stmt = $db->prepare("
                        INSERT INTO proyecto_promotores 
                        (proyecto_id, promotor_user_id, fecha_registro, fecha_actualizacion) 
                        VALUES (?, ?, NOW(), NOW())
                    ");

                    foreach ($proyectos as $proyectoId) {
                        $stmt->execute([$proyectoId, $promotorId]);
                    }
                }

                $db->commit();

                // Auditoría
                $auditoriaModel->registrar(
                    $_SESSION['user_id'],
                    'UPDATE',
                    'proyecto_promotores',
                    $promotorId,
                    'Proyectos asignados: ' . count($proyectos)
                );

                echo json_encode([
                    'success' => true,
                    'message' => 'Proyectos asignados exitosamente. El promotor ahora tiene acceso a los clientes asociados a estos proyectos.'
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        default:
            throw new Exception('Acción no válida: ' . $action);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
