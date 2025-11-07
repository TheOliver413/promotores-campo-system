<?php
require_once __DIR__ . '/../config/database.php';

class SupervisorPromotor
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function agregarPromotor($supervisorId, $promotorId)
    {
        try {
            // Verificar si ya existe la relación
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM supervisor_promotores 
                WHERE supervisor_id = ? AND promotor_id = ?
            ");
            $stmt->execute([$supervisorId, $promotorId]);
            $result = $stmt->fetch();

            // Si no existe, insertar
            if ($result['count'] == 0) {
                $stmt = $this->db->prepare("
                    INSERT INTO supervisor_promotores (supervisor_id, promotor_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$supervisorId, $promotorId]);
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    // Asignar múltiples promotores a un supervisor
    public function asignarPromotores($supervisorId, $promotorIds)
    {
        try {
            $this->db->beginTransaction();

            // Eliminar asignaciones previas
            $stmt = $this->db->prepare("DELETE FROM supervisor_promotores WHERE supervisor_id = ?");
            $stmt->execute([$supervisorId]);

            // Insertar nuevas asignaciones
            if (!empty($promotorIds)) {
                $stmt = $this->db->prepare("INSERT INTO supervisor_promotores (supervisor_id, promotor_id) VALUES (?, ?)");
                foreach ($promotorIds as $promotorId) {
                    $stmt->execute([$supervisorId, $promotorId]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    // Obtener promotores de un supervisor
    public function getPromotoresBySupervisor($supervisorId)
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre as role_name 
            FROM usuarios u
            INNER JOIN supervisor_promotores sp ON u.id = sp.promotor_id
            INNER JOIN roles r ON u.role_id = r.id
            WHERE sp.supervisor_id = ? AND u.estado = 'activo'
            ORDER BY u.nombre_completo
        ");
        $stmt->execute([$supervisorId]);
        return $stmt->fetchAll();
    }

    // Obtener supervisores de un promotor
    public function getSupervisoresByPromotor($promotorId)
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre as role_name 
            FROM usuarios u
            INNER JOIN supervisor_promotores sp ON u.id = sp.supervisor_id
            INNER JOIN roles r ON u.role_id = r.id
            WHERE sp.promotor_id = ? AND u.estado = 'activo'
            ORDER BY u.nombre_completo
        ");
        $stmt->execute([$promotorId]);
        return $stmt->fetchAll();
    }

    // Verificar si un supervisor gestiona a un promotor
    public function esGestionado($supervisorId, $promotorId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM supervisor_promotores 
            WHERE supervisor_id = ? AND promotor_id = ?
        ");
        $stmt->execute([$supervisorId, $promotorId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    // Obtener todos los promotores disponibles (sin asignar a un supervisor específico)
    public function getPromotoresDisponibles($supervisorId = null)
    {
        $sql = "
            SELECT u.*, r.nombre as role_name 
            FROM usuarios u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE r.nombre = 'Promotor' AND u.estado = 'activo'
        ";

        if ($supervisorId) {
            $sql .= " AND u.id NOT IN (
                SELECT promotor_id FROM supervisor_promotores WHERE supervisor_id = ?
            )";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$supervisorId]);
        } else {
            $stmt = $this->db->query($sql);
        }

        return $stmt->fetchAll();
    }
}
