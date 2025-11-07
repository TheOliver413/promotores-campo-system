<?php
require_once __DIR__ . '/../config/database.php';

class Evidencia
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($actividadId, $data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO evidencias 
            (actividad_id, tipo_archivo, url_archivo, nombre_archivo, peso_kb)
            VALUES (?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $actividadId,
            $data['tipo_archivo'],
            $data['url_archivo'],
            $data['nombre_archivo'] ?? null,
            $data['peso_kb'] ?? null
        ]);
    }

    public function getByActividad($actividadId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM evidencias 
            WHERE actividad_id = ? 
            ORDER BY fecha_carga DESC
        ");
        $stmt->execute([$actividadId]);
        return $stmt->fetchAll();
    }
}
