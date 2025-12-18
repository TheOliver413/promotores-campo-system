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
            return $this->db->lastInsertId();
        }

        return false;
    }

    public function update($id, $data)
    {
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

        return $stmt->execute([
            $data['cliente_id'],
            $data['nombre'],
            $data['codigo'],
            $data['descripcion'] ?? null,
            $data['cantidad_stock'] ?? 0,
            $data['sku'] ?? null,
            $data['precio'] ?? 0,
            $data['activo'] ?? true,
            $id
        ]);
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

        // Obtener cantidad ya asignada al promotor
        $checkStmt = $this->db->prepare("
            SELECT id, cantidad_asignada 
            FROM producto_asignaciones 
            WHERE producto_id = ? AND promotor_user_id = ?
        ");
        $checkStmt->execute([$productoId, $promotorId]);
        $existing = $checkStmt->fetch();

        // Calcular total que tendría asignado
        $cantidadActualAsignada = $existing ? $existing['cantidad_asignada'] : 0;
        $nuevaCantidadTotal = $cantidadActualAsignada + $cantidad;

        // Validar que no exceda el stock disponible
        if ($nuevaCantidadTotal > $producto['cantidad_stock']) {
            throw new Exception(
                "Stock insuficiente. Disponible: {$producto['cantidad_stock']}, " .
                    "Ya asignado: {$cantidadActualAsignada}, " .
                    "Intentando asignar: {$cantidad}"
            );
        }

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE producto_asignaciones 
                SET cantidad_asignada = ?,
                    supervisor_user_id = ?
                WHERE id = ?
            ");
            return $stmt->execute([$nuevaCantidadTotal, $supervisorId, $existing['id']]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO producto_asignaciones 
                (producto_id, promotor_user_id, supervisor_user_id, cantidad_asignada)
                VALUES (?, ?, ?, ?)
            ");
            return $stmt->execute([$productoId, $promotorId, $supervisorId, $cantidad]);
        }
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
        // Obtiene todos los productos de los clientes asociados a los proyectos del supervisor
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.*, c.nombre_empresa
            FROM productos p
            INNER JOIN clientes c ON p.cliente_id = c.id
            WHERE p.activo = true
            ORDER BY c.nombre_empresa, p.nombre
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
