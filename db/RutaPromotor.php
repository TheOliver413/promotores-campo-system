<?php
require_once __DIR__ . '/../config/database.php';

class RutaPromotor
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO rutas_promotores 
            (promotor_user_id, proyecto_id, nombre_ruta, fecha_planificada, puntos_ruta, estado)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $data['promotor_user_id'],
            $data['proyecto_id'],
            $data['nombre_ruta'],
            $data['fecha_planificada'],
            json_encode($data['puntos_ruta']),
            $data['estado'] ?? 'pendiente'
        ]);
    }

    public function getByPromotor($promotorId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                r.id as ruta_promotor_id,
                r.nombre_ruta,
                r.fecha_planificada as fecha_asignacion,
                r.puntos_ruta,
                r.estado,
                r.fecha_registro,
                p.nombre_proyecto,
                p.id as proyecto_id
            FROM rutas_promotores r 
            JOIN proyectos p ON r.proyecto_id = p.id 
            WHERE r.promotor_user_id = ? 
            ORDER BY r.fecha_planificada DESC
        ");
        $stmt->execute([$promotorId]);
        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM rutas_promotores WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE rutas_promotores 
            SET nombre_ruta = ?, fecha_planificada = ?, puntos_ruta = ?, estado = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['nombre_ruta'],
            $data['fecha_planificada'],
            json_encode($data['puntos_ruta']),
            $data['estado'],
            $id
        ]);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM rutas_promotores WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
