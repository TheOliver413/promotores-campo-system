<?php
require_once __DIR__ . '/../config/database.php';

class Cotizacion
{
    public $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        // Generar número de cotización único
        $numeroCotizacion = $this->generarNumeroCotizacion();

        $stmt = $this->db->prepare("
            INSERT INTO cotizaciones 
            (acta_id, promotor_user_id, supervisor_user_id, cliente_id, numero_cotizacion,
             tipo, subtotal, impuestos, total, estado, notas)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['acta_id'] ?? null,
            $data['promotor_user_id'],
            $data['supervisor_user_id'] ?? null,
            $data['cliente_id'],
            $numeroCotizacion,
            $data['tipo'] ?? 'cotizacion',
            $data['subtotal'] ?? 0,
            $data['impuestos'] ?? 0,
            $data['total'] ?? 0,
            $data['estado'] ?? 'borrador',
            $data['notas'] ?? null
        ]);

        if ($result) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    public function agregarDetalle($cotizacionId, $productoId, $cantidad, $precioUnitario)
    {
        $subtotal = $cantidad * $precioUnitario;

        $stmt = $this->db->prepare("
            INSERT INTO cotizacion_detalles 
            (cotizacion_id, producto_id, cantidad, precio_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");

        return $stmt->execute([$cotizacionId, $productoId, $cantidad, $precioUnitario, $subtotal]);
    }

    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE cotizaciones 
            SET tipo = ?, subtotal = ?, impuestos = ?, total = ?, estado = ?, notas = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['tipo'] ?? 'cotizacion',
            $data['subtotal'] ?? 0,
            $data['impuestos'] ?? 0,
            $data['total'] ?? 0,
            $data['estado'] ?? 'borrador',
            $data['notas'] ?? null,
            $id
        ]);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   u.nombre_completo as promotor_nombre,
                   cl.nombre_empresa as cliente_nombre,
                   av.punto_visita_nombre
            FROM cotizaciones c
            LEFT JOIN usuarios u ON c.promotor_user_id = u.id
            LEFT JOIN clientes cl ON c.cliente_id = cl.id
            LEFT JOIN actas_visita av ON c.acta_id = av.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getDetalles($cotizacionId)
    {
        $stmt = $this->db->prepare("
            SELECT cd.*, p.nombre as producto_nombre, p.codigo, p.sku
            FROM cotizacion_detalles cd
            INNER JOIN productos p ON cd.producto_id = p.id
            WHERE cd.cotizacion_id = ?
            ORDER BY cd.id ASC
        ");
        $stmt->execute([$cotizacionId]);
        return $stmt->fetchAll();
    }

    public function getByPromotor($promotorId, $limit = null)
    {
        $sql = "
            SELECT c.*, 
                   cl.nombre_empresa as cliente_nombre,
                   (SELECT COUNT(*) FROM cotizacion_detalles WHERE cotizacion_id = c.id) as num_items
            FROM cotizaciones c
            LEFT JOIN clientes cl ON c.cliente_id = cl.id
            WHERE c.promotor_user_id = ?
            ORDER BY c.fecha_creacion DESC
        ";

        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$promotorId]);
        return $stmt->fetchAll();
    }

    public function getBySupervisor($supervisorId, $limit = null)
    {
        $sql = "
            SELECT c.*, 
                   u.nombre_completo as promotor_nombre,
                   cl.nombre_empresa as cliente_nombre,
                   (SELECT COUNT(*) FROM cotizacion_detalles WHERE cotizacion_id = c.id) as num_items
            FROM cotizaciones c
            INNER JOIN usuarios u ON c.promotor_user_id = u.id
            LEFT JOIN clientes cl ON c.cliente_id = cl.id
            INNER JOIN supervisor_promotores sp ON u.id = sp.promotor_id
            WHERE sp.supervisor_id = ?
            ORDER BY c.fecha_creacion DESC
        ";

        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$supervisorId]);
        return $stmt->fetchAll();
    }

    private function generarNumeroCotizacion()
    {
        $prefix = 'COT';
        $date = date('Ymd');

        // Obtener el último número del día
        $stmt = $this->db->prepare("
            SELECT numero_cotizacion 
            FROM cotizaciones 
            WHERE numero_cotizacion LIKE ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$prefix . $date . '%']);
        $ultimo = $stmt->fetch();

        if ($ultimo) {
            $ultimoNumero = intval(substr($ultimo['numero_cotizacion'], -4));
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return $prefix . $date . str_pad($nuevoNumero, 4, '0', STR_PAD_LEFT);
    }

    public function eliminarDetalles($cotizacionId)
    {
        $stmt = $this->db->prepare("DELETE FROM cotizacion_detalles WHERE cotizacion_id = ?");
        return $stmt->execute([$cotizacionId]);
    }
}
