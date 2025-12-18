<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log('[PRODUCTO_API] Fatal error: ' . print_r($error, true));
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Error fatal del servidor',
            'error' => $error['message']
        ]);
    }
});

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/session.php';
    require_once __DIR__ . '/../db/Producto.php';
    require_once __DIR__ . '/../db/Notificacion.php';

    header('Content-Type: application/json');

    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    $productoModel = new Producto();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $producto = $productoModel->getById($_GET['id']);
            echo json_encode(['success' => true, 'data' => $producto]);
        } elseif (isset($_GET['cliente_id'])) {
            $productos = $productoModel->getByCliente($_GET['cliente_id']);
            echo json_encode(['success' => true, 'data' => $productos]);
        } elseif (isset($_GET['promotor_id'])) {
            $productos = $productoModel->getByPromotor($_GET['promotor_id']);
            echo json_encode(['success' => true, 'data' => $productos]);
        } else {
            $productos = $productoModel->getAll();
            echo json_encode(['success' => true, 'data' => $productos]);
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
        }

        if (!isset($data['action'])) {
            throw new Exception('Acción no especificada');
        }

        if ($data['action'] === 'crear') {
            $productoId = $productoModel->create($data);

            if ($productoId) {
                echo json_encode(['success' => true, 'message' => 'Producto creado', 'id' => $productoId]);
            } else {
                throw new Exception('Error al crear producto');
            }
        } elseif ($data['action'] === 'editar') {
            if (empty($data['id'])) {
                throw new Exception('ID de producto no especificado');
            }

            $result = $productoModel->update($data['id'], $data);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Producto actualizado']);
            } else {
                throw new Exception('Error al actualizar producto');
            }
        } elseif ($data['action'] === 'eliminar') {
            if (empty($data['id'])) {
                throw new Exception('ID de producto no especificado');
            }

            $result = $productoModel->delete($data['id']);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Producto eliminado']);
            } else {
                throw new Exception('Error al eliminar producto');
            }
        } elseif ($data['action'] === 'asignar') {
            if (empty($data['producto_id']) || empty($data['promotor_id']) || empty($data['supervisor_id']) || empty($data['cantidad'])) {
                $missing = [];
                if (empty($data['producto_id'])) $missing[] = 'producto_id';
                if (empty($data['promotor_id'])) $missing[] = 'promotor_id';
                if (empty($data['supervisor_id'])) $missing[] = 'supervisor_id';
                if (empty($data['cantidad'])) $missing[] = 'cantidad';

                throw new Exception('Faltan campos obligatorios: ' . implode(', ', $missing));
            }

            $result = $productoModel->asignarAPromotor(
                $data['producto_id'],
                $data['promotor_id'],
                $data['supervisor_id'],
                $data['cantidad']
            );

            if ($result) {
                try {
                    $notifModel = new Notificacion();
                    $notifModel->create(
                        $data['promotor_id'],
                        'Se te ha asignado un nuevo producto',
                        'mensaje',
                        $data['producto_id']
                    );
                } catch (Exception $e) {
                    error_log('[PRODUCTO_API] Error al crear notificación: ' . $e->getMessage());
                }

                echo json_encode(['success' => true, 'message' => 'Producto asignado exitosamente']);
            } else {
                throw new Exception('Error al asignar producto');
            }
        } else {
            throw new Exception('Acción no válida: ' . $data['action']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
} catch (PDOException $e) {
    error_log('[PRODUCTO_API] PDO Error: ' . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('[PRODUCTO_API] Error: ' . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
