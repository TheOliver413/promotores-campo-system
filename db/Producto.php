<?php
require_once __DIR__ . '/../config/database.php';

class Producto
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO productos 
            (cliente_id, nombre, codigo, descripcion, cantidad_stock, sku, precio, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['cliente_id'],
            $data['nombre'],
            $data['codigo'],
            $data['descripcion'] ?? null,
            $data['cantidad_stock'] ?? 0,
            $data['sku'] ?? null,
            $data['precio'] ?? 0,
            $data['activo'] ?? true
        ]);

        if ($result) {
            $productoId = $this->db->lastInsertId();

            $this->registrarMovimientoStock(
                $productoId,
                $_SESSION['user_id'] ?? 1,
                'entrada',
                $data['cantidad_stock'] ?? 0,
                0,
                $data['cantidad_stock'] ?? 0,
                'Stock inicial al crear producto'
            );

            return $productoId;
        }

        return false;
    }

    public function update($id, $data)
    {
        $productoActual = $this->getById($id);
        $stockAnterior = $productoActual['cantidad_stock'];
        $stockNuevo = $data['cantidad_stock'] ?? $stockAnterior;

        $stmt = $this->db->prepare("
            UPDATE productos 
            SET cliente_id = ?, 
                nombre = ?, 
                codigo = ?, 
                descripcion = ?, 
                cantidad_stock = ?, 
                sku = ?, 
                precio = ?,
                activo = ?
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $data['cliente_id'],
            $data['nombre'],
            $data['codigo'],
            $data['descripcion'] ?? null,
            $stockNuevo,
            $data['sku'] ?? null,
            $data['precio'] ?? 0,
            $data['activo'] ?? true,
            $id
        ]);

        if ($result && $stockNuevo != $stockAnterior) {
            $diferencia = $stockNuevo - $stockAnterior;
            $tipoMovimiento = $diferencia > 0 ? 'entrada' : 'salida';
            $observaciones = $diferencia > 0
                ? "Ajuste de stock: +{$diferencia} unidades"
                : "Ajuste de stock: {$diferencia} unidades";

            $this->registrarMovimientoStock(
                $id,
                $_SESSION['user_id'] ?? 1,
                'ajuste',
                abs($diferencia),
                $stockAnterior,
                $stockNuevo,
                $observaciones
            );
        }

        return $result;
    }

    public function getAll()
    {
        $stmt = $this->db->query("
            SELECT p.*, c.nombre_empresa 
            FROM productos p 
            LEFT JOIN clientes c ON p.cliente_id = c.id 
            WHERE p.activo = true
            ORDER BY p.fecha_creacion DESC
        ");
        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.nombre_empresa 
            FROM productos p 
            LEFT JOIN clientes c ON p.cliente_id = c.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getByCliente($clienteId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM productos 
            WHERE cliente_id = ? AND activo = true 
            ORDER BY nombre ASC
        ");
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll();
    }

    public function getByPromotor($promotorId)
    {
        // Obtiene los productos asignados a un promotor específico
        $stmt = $this->db->prepare("
            SELECT p.*, c.nombre_empresa, pa.cantidad_asignada, pa.id as asignacion_id
            FROM productos p
            INNER JOIN producto_asignaciones pa ON p.id = pa.producto_id
            LEFT JOIN clientes c ON p.cliente_id = c.id
            WHERE pa.promotor_user_id = ? AND p.activo = true
            ORDER BY p.nombre ASC
        ");
        $stmt->execute([$promotorId]);
        return $stmt->fetchAll();
    }

    public function delete($id)
    {
        // Soft delete
        $stmt = $this->db->prepare("UPDATE productos SET activo = false WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function asignarAPromotor($productoId, $promotorId, $supervisorId, $cantidad)
    {
        $producto = $this->getById($productoId);
        if (!$producto) {
            throw new Exception('Producto no encontrado');
        }

        if ($producto['cantidad_stock'] < $cantidad) {
            throw new Exception(
                "Stock insuficiente. Disponible: {$producto['cantidad_stock']}, " .
                    "Intentando asignar: {$cantidad}"
            );
        }

        // Obtener cantidad ya asignada al promotor
        $checkStmt = $this->db->prepare("
            SELECT id, cantidad_asignada 
            FROM producto_asignaciones 
            WHERE producto_id = ? AND promotor_user_id = ?
        ");
        $checkStmt->execute([$productoId, $promotorId]);
        $existing = $checkStmt->fetch();

        $cantidadAnterior = $existing ? $existing['cantidad_asignada'] : 0;
        $cantidadNueva = $cantidadAnterior + $cantidad;

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE productos 
                SET cantidad_stock = cantidad_stock - ? 
                WHERE id = ? AND cantidad_stock >= ?
            ");
            $stmt->execute([$cantidad, $productoId, $cantidad]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Stock insuficiente para realizar la asignación');
            }

            if ($existing) {
                $stmt = $this->db->prepare("
                    UPDATE producto_asignaciones 
                    SET cantidad_asignada = cantidad_asignada + ?
                    WHERE id = ?
                ");
                $stmt->execute([$cantidad, $existing['id']]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO producto_asignaciones 
                    (producto_id, promotor_user_id, supervisor_user_id, cantidad_asignada)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$productoId, $promotorId, $supervisorId, $cantidad]);
            }

            $this->registrarHistorialAsignacion(
                $productoId,
                $promotorId,
                $supervisorId,
                'asignacion',
                $cantidad,
                $cantidadAnterior,
                $cantidadNueva,
                "Asignación de {$cantidad} unidades al promotor"
            );

            $stockNuevo = $producto['cantidad_stock'] - $cantidad;
            $this->registrarMovimientoStock(
                $productoId,
                $supervisorId,
                'asignacion',
                $cantidad,
                $producto['cantidad_stock'],
                $stockNuevo,
                "Asignación a promotor (ID: {$promotorId})"
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function registrarHistorialAsignacion($productoId, $promotorId, $supervisorId, $tipo, $cantidad, $cantidadAnterior, $cantidadNueva, $observaciones)
    {
        $stmt = $this->db->prepare("
            INSERT INTO producto_historial 
            (producto_id, promotor_user_id, supervisor_user_id, tipo_movimiento, cantidad, cantidad_anterior, cantidad_nueva, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $productoId,
            $promotorId,
            $supervisorId,
            $tipo,
            $cantidad,
            $cantidadAnterior,
            $cantidadNueva,
            $observaciones
        ]);
    }

    private function registrarMovimientoStock($productoId, $usuarioId, $tipoMovimiento, $cantidad, $stockAnterior, $stockNuevo, $observaciones)
    {
        $stmt = $this->db->prepare("
            INSERT INTO producto_stock_movimientos 
            (producto_id, usuario_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $productoId,
            $usuarioId,
            $tipoMovimiento,
            $cantidad,
            $stockAnterior,
            $stockNuevo,
            $observaciones
        ]);
    }

    public function getHistorialAsignaciones($productoId = null, $promotorId = null)
    {
        $where = [];
        $params = [];

        if ($productoId) {
            $where[] = "ph.producto_id = ?";
            $params[] = $productoId;
        }

        if ($promotorId) {
            $where[] = "ph.promotor_user_id = ?";
            $params[] = $promotorId;
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $this->db->prepare("
            SELECT ph.*, 
                   p.nombre as producto_nombre,
                   p.codigo as producto_codigo,
                   up.nombre_completo as promotor_nombre,
                   us.nombre_completo as supervisor_nombre
            FROM producto_historial ph
            INNER JOIN productos p ON ph.producto_id = p.id
            INNER JOIN usuarios up ON ph.promotor_user_id = up.id
            INNER JOIN usuarios us ON ph.supervisor_user_id = us.id
            {$whereClause}
            ORDER BY ph.fecha_movimiento DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getMovimientosStock($productoId = null)
    {
        $where = $productoId ? "WHERE psm.producto_id = ?" : "";
        $params = $productoId ? [$productoId] : [];

        $stmt = $this->db->prepare("
            SELECT psm.*, 
                   p.nombre as producto_nombre,
                   p.codigo as producto_codigo,
                   u.nombre_completo as usuario_nombre
            FROM producto_stock_movimientos psm
            INNER JOIN productos p ON psm.producto_id = p.id
            INNER JOIN usuarios u ON psm.usuario_id = u.id
            {$where}
            ORDER BY psm.fecha_movimiento DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function descontarStock($productoId, $cantidad)
    {
        $producto = $this->getById($productoId);
        if (!$producto) {
            throw new Exception('Producto no encontrado');
        }

        if ($producto['cantidad_stock'] < $cantidad) {
            throw new Exception(
                "Stock insuficiente. Disponible: {$producto['cantidad_stock']}, " .
                    "Requerido: {$cantidad}"
            );
        }

        $stmt = $this->db->prepare("
            UPDATE productos 
            SET cantidad_stock = cantidad_stock - ? 
            WHERE id = ?
        ");
        return $stmt->execute([$cantidad, $productoId]);
    }

    public function getStockDisponibleParaPromotor($productoId, $promotorId)
    {
        $stmt = $this->db->prepare("
            SELECT pa.cantidad_asignada
            FROM producto_asignaciones pa
            WHERE pa.producto_id = ? AND pa.promotor_user_id = ?
        ");
        $stmt->execute([$productoId, $promotorId]);
        $asignacion = $stmt->fetch();

        return $asignacion ? (int)$asignacion['cantidad_asignada'] : 0;
    }

    public function getProductosDisponiblesParaSupervisor($supervisorId)
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.*, c.nombre_empresa
            FROM productos p
            INNER JOIN clientes c ON p.cliente_id = c.id
            WHERE p.activo = true AND p.cantidad_stock > 0
            ORDER BY c.nombre_empresa, p.nombre
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
