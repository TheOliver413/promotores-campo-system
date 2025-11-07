<?php
require_once __DIR__ . '/../config/database.php';

class TipoActividad
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO tipos_actividad (nombre, descripcion, requiere_evidencia)
            VALUES (?, ?, ?)
        ");

        return $stmt->execute([
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['requiere_evidencia'] ?? true
        ]);
    }

    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM tipos_actividad ORDER BY nombre");
        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM tipos_actividad WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE tipos_actividad 
            SET nombre = ?, descripcion = ?, requiere_evidencia = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['nombre'],
            $data['descripcion'],
            $data['requiere_evidencia'],
            $id
        ]);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM tipos_actividad WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
