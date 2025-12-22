<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/Cotizacion.php';
require_once __DIR__ . '/../db/Notificacion.php';
require_once __DIR__ . '/../db/Producto.php';

ob_clean();
header('Content-Type: application/json');

requireLogin();

$cotizacionModel = new Cotizacion();
$notifModel = new Notificacion();
$productoModel = new Producto();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Datos no válidos']);
            exit;
        }

        if (isset($data['productos']) && is_array($data['productos'])) {
            foreach ($data['productos'] as $producto) {
                $stockDisponible = $productoModel->getStockDisponibleParaPromotor(
                    $producto['id'],
                    $data['promotor_user_id']
                );

                if ($stockDisponible < $producto['cantidad']) {
                    ob_clean();
                    echo json_encode([
                        'success' => false,
                        'message' => "Stock insuficiente para producto ID {$producto['id']}. " .
                            "Disponible: {$stockDisponible}, Requerido: {$producto['cantidad']}"
                    ]);
                    exit;
                }
            }
        }

        // Create cotizacion
        $cotizacionId = $cotizacionModel->create([
            'acta_id' => $data['acta_id'] ?? null,
            'promotor_user_id' => $data['promotor_user_id'],
            'supervisor_user_id' => $data['supervisor_user_id'] ?? null,
            'cliente_id' => $data['cliente_id'],
            'tipo' => $data['tipo'] ?? 'cotizacion',
            'subtotal' => $data['subtotal'] ?? 0,
            'impuestos' => $data['impuestos'] ?? 0,
            'total' => $data['total'] ?? 0,
            'estado' => 'enviada',
            'notas' => $data['notas'] ?? null
        ]);

        if ($cotizacionId) {
            if (isset($data['productos']) && is_array($data['productos'])) {
                // Get database connection from cotizacion model
                $db = $cotizacionModel->db ?? Database::getInstance()->getConnection();

                foreach ($data['productos'] as $producto) {
                    $cotizacionModel->agregarDetalle(
                        $cotizacionId,
                        $producto['id'],
                        $producto['cantidad'],
                        $producto['precio']
                    );

                    $stmt = $db->prepare("
                        UPDATE producto_asignaciones 
                        SET cantidad_asignada = cantidad_asignada - ? 
                        WHERE producto_id = ? AND promotor_user_id = ?
                    ");
                    $stmt->execute([
                        $producto['cantidad'],
                        $producto['id'],
                        $data['promotor_user_id']
                    ]);

                    $stmt = $db->prepare("
                        INSERT INTO producto_historial 
                        (producto_id, tipo_movimiento, cantidad, usuario_id, promotor_id, referencia_tipo, referencia_id, notas)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $tipoMovimiento = $data['tipo'] === 'venta' ? 'venta' : 'cotizacion';
                    $notas = $data['tipo'] === 'venta'
                        ? "Venta registrada - Cotización #{$cotizacionId}"
                        : "Producto reservado para cotización #{$cotizacionId}";

                    $stmt->execute([
                        $producto['id'],
                        $tipoMovimiento,
                        -$producto['cantidad'], // Negativo porque es salida
                        $data['promotor_user_id'],
                        $data['promotor_user_id'],
                        'cotizacion',
                        $cotizacionId,
                        $notas
                    ]);
                }
            }

            // Notify supervisor
            if ($data['supervisor_user_id']) {
                $notifModel->create(
                    $data['supervisor_user_id'],
                    'Nueva cotización generada por promotor',
                    'mensaje',
                    $cotizacionId
                );
            }

            ob_clean();
            echo json_encode(['success' => true, 'cotizacion_id' => $cotizacionId]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Error al crear cotización']);
        }
    } elseif ($method === 'GET') {
        if (isset($_GET['id'])) {
            $cotizacion = $cotizacionModel->getById($_GET['id']);
            $detalles = $cotizacionModel->getDetalles($_GET['id']);
            echo json_encode([
                'success' => true,
                'cotizacion' => $cotizacion,
                'detalles' => $detalles
            ]);
        } elseif (isset($_GET['promotor_id'])) {
            $cotizaciones = $cotizacionModel->getByPromotor($_GET['promotor_id']);
            echo json_encode(['success' => true, 'data' => $cotizaciones]);
        } elseif (isset($_GET['supervisor_id'])) {
            $cotizaciones = $cotizacionModel->getBySupervisor($_GET['supervisor_id']);
            echo json_encode(['success' => true, 'data' => $cotizaciones]);
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
