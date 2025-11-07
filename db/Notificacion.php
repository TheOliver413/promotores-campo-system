<?php
require_once __DIR__ . '/../config/database.php';

class Notificacion
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($usuarioId, $mensaje, $tipo, $referenciaId = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO notificaciones 
            (usuario_id, mensaje, tipo_notificacion, referencia_id)
            VALUES (?, ?, ?, ?)
        ");

        return $stmt->execute([$usuarioId, $mensaje, $tipo, $referenciaId]);
    }

    public function getByUsuario($usuarioId, $soloNoLeidas = false)
    {
        $sql = "SELECT * FROM notificaciones WHERE usuario_id = ?";

        if ($soloNoLeidas) {
            $sql .= " AND leido = false";
        }

        $sql .= " ORDER BY fecha_creacion DESC LIMIT 50";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$usuarioId]);
        return $stmt->fetchAll();
    }

    public function marcarComoLeida($notificacionId)
    {
        $stmt = $this->db->prepare("UPDATE notificaciones SET leido = true WHERE id = ?");
        return $stmt->execute([$notificacionId]);
    }

    public function contarNoLeidas($usuarioId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total 
            FROM notificaciones 
            WHERE usuario_id = ? AND leido = false
        ");
        $stmt->execute([$usuarioId]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
}
