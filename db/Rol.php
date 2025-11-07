<?php
require_once __DIR__ . '/../config/database.php';

class Rol
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO roles (nombre, permisos)
            VALUES (?, ?)
        ");

        return $stmt->execute([
            $data['nombre'],
            json_encode($data['permisos'] ?? [])
        ]);
    }

    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM roles ORDER BY nombre");
        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE roles 
            SET nombre = ?, permisos = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['nombre'],
            json_encode($data['permisos'] ?? []),
            $id
        ]);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM roles WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
