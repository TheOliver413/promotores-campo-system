<?php
require_once __DIR__ . '/../config/database.php';

class Jornada
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO jornadas 
            (promotor_user_id, proyecto_id, check_in_time, check_in_lat, check_in_lon, check_in_foto_url, fecha_jornada)
            VALUES (?, ?, NOW(), ?, ?, ?, CURDATE())
        ");

        $result = $stmt->execute([
            $data['promotor_user_id'],
            $data['proyecto_id'] ?? null,
            $data['check_in_lat'],
            $data['check_in_lon'],
            $data['check_in_foto_url'] ?? null
        ]);

        if ($result) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    public function update($jornadaId, $data)
    {
        $fields = [];
        $params = [];

        if (isset($data['check_out_time'])) {
            $fields[] = "check_out_time = ?";
            $params[] = $data['check_out_time'];
        }
        if (isset($data['check_out_lat'])) {
            $fields[] = "check_out_lat = ?";
            $params[] = $data['check_out_lat'];
        }
        if (isset($data['check_out_lon'])) {
            $fields[] = "check_out_lon = ?";
            $params[] = $data['check_out_lon'];
        }
        if (isset($data['check_out_foto_url'])) {
            $fields[] = "check_out_foto_url = ?";
            $params[] = $data['check_out_foto_url'];
        }
        if (isset($data['horas_calculadas'])) {
            $fields[] = "horas_calculadas = ?";
            $params[] = $data['horas_calculadas'];
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $jornadaId;
        $sql = "UPDATE jornadas SET " . implode(', ', $fields) . " WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function checkIn($data)
    {
        return $this->create($data);
    }

    public function checkOut($jornadaId, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE jornadas 
            SET check_out_time = NOW(), 
                check_out_lat = ?, 
                check_out_lon = ?, 
                check_out_foto_url = ?,
                horas_calculadas = TIMESTAMPDIFF(HOUR, check_in_time, NOW())
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['check_out_lat'],
            $data['check_out_lon'],
            $data['check_out_foto_url'] ?? null,
            $jornadaId
        ]);
    }

    public function getJornadaActiva($promotorId)
    {
        $stmt = $this->db->prepare("
            SELECT j.*, p.nombre_proyecto 
            FROM jornadas j 
            LEFT JOIN proyectos p ON j.proyecto_id = p.id 
            WHERE j.promotor_user_id = ? 
            AND j.fecha_jornada = CURDATE() 
            AND j.check_out_time IS NULL
            ORDER BY j.check_in_time DESC 
            LIMIT 1
        ");
        $stmt->execute([$promotorId]);
        return $stmt->fetch();
    }

    public function getJornadaActivaHoy($promotorId)
    {
        return $this->getJornadaActiva($promotorId);
    }

    public function getJornadasPendientes($supervisorId = null)
    {
        $sql = "SELECT j.*, u.nombre_completo as promotor_nombre, p.nombre_proyecto 
                FROM jornadas j 
                JOIN usuarios u ON j.promotor_user_id = u.id 
                LEFT JOIN proyectos p ON j.proyecto_id = p.id 
                WHERE j.estado_validacion = 'pendiente'";

        $params = [];

        if ($supervisorId) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM supervisor_promotores sp 
                WHERE sp.supervisor_id = ? AND sp.promotor_id = j.promotor_user_id
            )";
            $params[] = $supervisorId;
        }

        $sql .= " ORDER BY j.check_in_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function validar($jornadaId, $supervisorId, $estado, $motivo = null)
    {
        $stmt = $this->db->prepare("
            UPDATE jornadas 
            SET estado_validacion = ?, 
                supervisor_user_id = ?, 
                motivo_rechazo = ?
            WHERE id = ?
        ");

        return $stmt->execute([$estado, $supervisorId, $motivo, $jornadaId]);
    }

    public function getByPromotor($promotorId, $limit = 10)
    {
        $stmt = $this->db->prepare("
            SELECT j.*, p.nombre_proyecto 
            FROM jornadas j 
            LEFT JOIN proyectos p ON j.proyecto_id = p.id 
            WHERE j.promotor_user_id = ? 
            ORDER BY j.fecha_jornada DESC, j.check_in_time DESC 
            LIMIT ?
        ");
        $stmt->execute([$promotorId, $limit]);
        return $stmt->fetchAll();
    }
}
