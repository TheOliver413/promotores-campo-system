<?php
require_once __DIR__ . '/../config/database.php';

class Auditoria
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function registrar($usuarioId, $accion, $tablaAfectada, $registroId = null, $detalles = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO auditoria 
            (usuario_id, accion, tabla_afectada, registro_afectado_id, detalles, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $detallesJson = $detalles ? json_encode($detalles) : null;

        return $stmt->execute([
            $usuarioId,
            $accion,
            $tablaAfectada,
            $registroId,
            $detallesJson,
            $ipAddress
        ]);
    }

    public function getAll($filters = [])
    {
        $sql = "SELECT a.*, u.nombre_completo as usuario_nombre 
                FROM auditoria a 
                LEFT JOIN usuarios u ON a.usuario_id = u.id 
                WHERE 1=1";

        $params = [];

        if (!empty($filters['usuario_id'])) {
            $sql .= " AND a.usuario_id = ?";
            $params[] = $filters['usuario_id'];
        }

        if (!empty($filters['tabla_afectada'])) {
            $sql .= " AND a.tabla_afectada = ?";
            $params[] = $filters['tabla_afectada'];
        }

        if (!empty($filters['fecha_desde'])) {
            $sql .= " AND DATE(a.timestamp_accion) >= ?";
            $params[] = $filters['fecha_desde'];
        }

        if (!empty($filters['fecha_hasta'])) {
            $sql .= " AND DATE(a.timestamp_accion) <= ?";
            $params[] = $filters['fecha_hasta'];
        }

        $sql .= " ORDER BY a.timestamp_accion DESC LIMIT 1000";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
