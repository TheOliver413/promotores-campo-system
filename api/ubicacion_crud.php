<?php

require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/Auditoria.php';

header('Content-Type: application/json');

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$auditoriaModel = new Auditoria();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $stmt = $db->prepare("
                SELECT uc.*, c.nombre_empresa
                FROM ubicaciones_clientes uc
                INNER JOIN clientes c ON uc.cliente_id = c.id
                INNER JOIN proyecto_clientes pc ON c.id = pc.cliente_id
                INNER JOIN proyectos p ON pc.proyecto_id = p.id
                INNER JOIN proyecto_promotores pp ON p.id = pp.proyecto_id
                INNER JOIN supervisor_promotores sp ON pp.promotor_user_id = sp.promotor_id
                WHERE sp.supervisor_id = ?
                GROUP BY uc.id
                ORDER BY c.nombre_empresa, uc.nombre_ubicacion
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $ubicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $ubicaciones]);
            break;

        case 'get':
            $id = $_GET['id'] ?? 0;

            $stmt = $db->prepare("
                SELECT uc.*
                FROM ubicaciones_clientes uc
                INNER JOIN clientes c ON uc.cliente_id = c.id
                INNER JOIN proyecto_clientes pc ON c.id = pc.cliente_id
                INNER JOIN proyectos p ON pc.proyecto_id = p.id
                INNER JOIN proyecto_promotores pp ON p.id = pp.proyecto_id
                INNER JOIN supervisor_promotores sp ON pp.promotor_user_id = sp.promotor_id
                WHERE uc.id = ? AND sp.supervisor_id = ?
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $ubicacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ubicacion) {
                throw new Exception('Ubicación no encontrada');
            }

            echo json_encode(['success' => true, 'data' => $ubicacion]);
            break;

        case 'create':
        case 'update':
            $ubicacionId = $_POST['ubicacion_id'] ?? null;
            $clienteId = $_POST['cliente_id'] ?? null;
            $proyectoId = $_POST['proyecto_id'] ?? null;
            $nombreUbicacion = $_POST['nombre_ubicacion'] ?? '';
            $direccion = $_POST['direccion'] ?? '';
            $latitud = $_POST['latitud'] ?? 0;
            $longitud = $_POST['longitud'] ?? 0;
            $contactoNombre = $_POST['contacto_nombre'] ?? null;
            $contactoTelefono = $_POST['contacto_telefono'] ?? null;
            $contactoEmail = $_POST['contacto_email'] ?? null;
            $notas = $_POST['notas'] ?? null;

            if (empty($nombreUbicacion) || empty($direccion) || empty($latitud) || empty($longitud)) {
                throw new Exception('Todos los campos requeridos deben ser completados');
            }

            if (!$clienteId && $proyectoId) {
                $stmt = $db->prepare("
                    SELECT c.id
                    FROM clientes c
                    INNER JOIN proyecto_clientes pc ON c.id = pc.cliente_id
                    INNER JOIN proyectos p ON pc.proyecto_id = p.id
                    INNER JOIN proyecto_promotores pp ON p.id = pp.proyecto_id
                    INNER JOIN supervisor_promotores sp ON pp.promotor_user_id = sp.promotor_id
                    WHERE p.id = ? AND sp.supervisor_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$proyectoId, $_SESSION['user_id']]);
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$cliente) {
                    throw new Exception('No se encontró un cliente asociado al proyecto o no tiene permisos');
                }

                $clienteId = $cliente['id'];
            }

            if (!$clienteId) {
                throw new Exception('Se requiere un cliente o proyecto válido');
            }

            // Verificar que el cliente esté asignado al supervisor
            $stmt = $db->prepare("
                SELECT 1 FROM clientes c
                INNER JOIN proyecto_clientes pc ON c.id = pc.cliente_id
                INNER JOIN proyectos p ON pc.proyecto_id = p.id
                INNER JOIN proyecto_promotores pp ON p.id = pp.proyecto_id
                INNER JOIN supervisor_promotores sp ON pp.promotor_user_id = sp.promotor_id
                WHERE c.id = ? AND sp.supervisor_id = ?
            ");
            $stmt->execute([$clienteId, $_SESSION['user_id']]);

            if (!$stmt->fetch()) {
                throw new Exception('No tiene permisos para gestionar ubicaciones de este cliente');
            }

            if ($ubicacionId) {
                // Actualizar
                $stmt = $db->prepare("
                    UPDATE ubicaciones_clientes 
                    SET cliente_id = ?, nombre_ubicacion = ?, direccion = ?, latitud = ?, longitud = ?,
                        contacto_nombre = ?, contacto_telefono = ?, contacto_email = ?, notas = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $clienteId,
                    $nombreUbicacion,
                    $direccion,
                    $latitud,
                    $longitud,
                    $contactoNombre,
                    $contactoTelefono,
                    $contactoEmail,
                    $notas,
                    $ubicacionId
                ]);

                $message = 'Ubicación actualizada exitosamente';
            } else {
                // Crear
                $stmt = $db->prepare("
                    INSERT INTO ubicaciones_clientes 
                    (cliente_id, nombre_ubicacion, direccion, latitud, longitud, contacto_nombre, contacto_telefono, contacto_email, notas, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $clienteId,
                    $nombreUbicacion,
                    $direccion,
                    $latitud,
                    $longitud,
                    $contactoNombre,
                    $contactoTelefono,
                    $contactoEmail,
                    $notas
                ]);

                $ubicacionId = $db->lastInsertId();
                $message = 'Ubicación creada exitosamente';
            }

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                $ubicacionId ? 'UPDATE' : 'INSERT',
                'ubicaciones_clientes',
                $ubicacionId,
                $message
            );

            echo json_encode([
                'success' => true,
                'message' => $message,
                'data' => [
                    'ubicacion_id' => $ubicacionId,
                    'cliente_id' => $clienteId
                ]
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? 0;

            // Verificar permisos
            $stmt = $db->prepare("
                SELECT uc.cliente_id
                FROM ubicaciones_clientes uc
                INNER JOIN clientes c ON uc.cliente_id = c.id
                INNER JOIN proyecto_clientes pc ON c.id = pc.cliente_id
                INNER JOIN proyectos p ON pc.proyecto_id = p.id
                INNER JOIN proyecto_promotores pp ON p.id = pp.proyecto_id
                INNER JOIN supervisor_promotores sp ON pp.promotor_user_id = sp.promotor_id
                WHERE uc.id = ? AND sp.supervisor_id = ?
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);

            if (!$stmt->fetch()) {
                throw new Exception('No tiene permisos para eliminar esta ubicación');
            }

            $stmt = $db->prepare("DELETE FROM ubicaciones_clientes WHERE id = ?");
            $stmt->execute([$id]);

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'DELETE',
                'ubicaciones_clientes',
                $id,
                'Ubicación eliminada'
            );

            echo json_encode(['success' => true, 'message' => 'Ubicación eliminada exitosamente']);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
