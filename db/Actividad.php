<?php
require_once __DIR__ . '/../config/database.php';

class Actividad
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO actividades 
            (jornada_id, promotor_user_id, proyecto_id, tipo_actividad_id, latitud, longitud, notas, dentro_geocerca)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['jornada_id'],
            $data['promotor_user_id'],
            $data['proyecto_id'],
            $data['tipo_actividad_id'],
            $data['latitud'],
            $data['longitud'],
            $data['notas'] ?? null,
            $data['dentro_geocerca'] ?? null
        ]);

        if ($result) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT a.*, ta.nombre as tipo_actividad_nombre,
                   u.nombre_completo as promotor_nombre,
                   p.nombre_proyecto,
                   a.notas as descripcion
            FROM actividades a 
            JOIN tipos_actividad ta ON a.tipo_actividad_id = ta.id 
            JOIN usuarios u ON a.promotor_user_id = u.id
            LEFT JOIN proyectos p ON a.proyecto_id = p.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getByPromotor($promotorId, $limit = 20)
    {
        $stmt = $this->db->prepare("
            SELECT a.*, ta.nombre as tipo_actividad_nombre,
                   p.nombre_proyecto,
                   a.notas as descripcion
            FROM actividades a 
            JOIN tipos_actividad ta ON a.tipo_actividad_id = ta.id 
            LEFT JOIN proyectos p ON a.proyecto_id = p.id
            WHERE a.promotor_user_id = ?
            ORDER BY a.timestamp_actividad DESC
            LIMIT ?
        ");
        $stmt->execute([$promotorId, $limit]);
        return $stmt->fetchAll();
    }

    public function getActividadesPendientes($supervisorId = null)
    {
        $sql = "SELECT a.*, u.nombre_completo as promotor_nombre, 
                p.nombre_proyecto, ta.nombre as tipo_actividad_nombre,
                a.notas as descripcion
                FROM actividades a 
                JOIN usuarios u ON a.promotor_user_id = u.id 
                LEFT JOIN proyectos p ON a.proyecto_id = p.id 
                JOIN tipos_actividad ta ON a.tipo_actividad_id = ta.id 
                WHERE a.estado_validacion = 'pendiente'";

        $params = [];

        if ($supervisorId) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM supervisor_promotores sp 
                WHERE sp.supervisor_id = ? AND sp.promotor_id = a.promotor_user_id
            )";
            $params[] = $supervisorId;
        }

        $sql .= " ORDER BY a.timestamp_actividad DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function validar($actividadId, $supervisorId, $estado, $motivo = null)
    {
        $stmt = $this->db->prepare("
            UPDATE actividades 
            SET estado_validacion = ?, 
                supervisor_user_id = ?, 
                motivo_rechazo = ?
            WHERE id = ?
        ");

        return $stmt->execute([$estado, $supervisorId, $motivo, $actividadId]);
    }

    public function getByJornada($jornadaId)
    {
        $stmt = $this->db->prepare("
            SELECT a.*, ta.nombre as tipo_actividad_nombre 
            FROM actividades a 
            JOIN tipos_actividad ta ON a.tipo_actividad_id = ta.id 
            WHERE a.jornada_id = ? 
            ORDER BY a.timestamp_actividad DESC
        ");
        $stmt->execute([$jornadaId]);
        return $stmt->fetchAll();
    }
}
