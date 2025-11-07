<?php
require_once __DIR__ . '/../config/database.php';

class Proyecto
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO proyectos 
            (nombre_proyecto, fecha_inicio, fecha_fin, kpis, configuraciones, estado)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['nombre_proyecto'] ?? null,
            $data['fecha_inicio'] ?? null,
            $data['fecha_fin'] ?? null,
            json_encode($data['kpis'] ?? []),
            json_encode($data['configuraciones'] ?? []),
            $data['estado'] ?? 'planificado'
        ]);

        if ($result) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE proyectos 
            SET nombre_proyecto = ?, 
                fecha_inicio = ?, 
                fecha_fin = ?, 
                kpis = ?, 
                configuraciones = ?, 
                estado = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['nombre_proyecto'] ?? null,
            $data['fecha_inicio'] ?? null,
            $data['fecha_fin'] ?? null,
            json_encode($data['kpis'] ?? []),
            json_encode($data['configuraciones'] ?? []),
            $data['estado'] ?? 'planificado',
            $id
        ]);
    }

    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM proyectos ORDER BY fecha_inicio DESC");
        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM proyectos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM proyectos WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function asignarClientes($proyectoId, $clienteIds)
    {
        // Delete existing assignments
        $stmt = $this->db->prepare("DELETE FROM proyecto_clientes WHERE proyecto_id = ?");
        $stmt->execute([$proyectoId]);

        // Insert new assignments
        if (!empty($clienteIds)) {
            $stmt = $this->db->prepare("INSERT INTO proyecto_clientes (proyecto_id, cliente_id) VALUES (?, ?)");
            foreach ($clienteIds as $clienteId) {
                $stmt->execute([$proyectoId, $clienteId]);
            }
        }

        return true;
    }

    public function asignarPromotores($proyectoId, $promotorIds)
    {
        // Delete existing assignments
        $stmt = $this->db->prepare("DELETE FROM proyecto_promotores WHERE proyecto_id = ?");
        $stmt->execute([$proyectoId]);

        // Insert new assignments
        if (!empty($promotorIds)) {
            $stmt = $this->db->prepare("INSERT INTO proyecto_promotores (proyecto_id, promotor_user_id) VALUES (?, ?)");
            foreach ($promotorIds as $promotorId) {
                $stmt->execute([$proyectoId, $promotorId]);
            }
        }

        return true;
    }

    public function getClientesAsignados($proyectoId)
    {
        $stmt = $this->db->prepare("
            SELECT c.* FROM clientes c 
            JOIN proyecto_clientes pc ON c.id = pc.cliente_id 
            WHERE pc.proyecto_id = ?
        ");
        $stmt->execute([$proyectoId]);
        return $stmt->fetchAll();
    }

    public function getPromotoresAsignados($proyectoId)
    {
        $stmt = $this->db->prepare("
            SELECT u.* FROM usuarios u 
            JOIN proyecto_promotores pp ON u.id = pp.promotor_user_id 
            WHERE pp.proyecto_id = ?
        ");
        $stmt->execute([$proyectoId]);
        return $stmt->fetchAll();
    }

    public function getByPromotor($promotorId)
    {
        $stmt = $this->db->prepare("
            SELECT p.* FROM proyectos p 
            JOIN proyecto_promotores pp ON p.id = pp.proyecto_id 
            WHERE pp.promotor_user_id = ?
        ");
        $stmt->execute([$promotorId]);
        return $stmt->fetchAll();
    }
}
