<?php
require_once __DIR__ . '/../config/database.php';

class Cliente
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO clientes (nombre_empresa, contacto_email, telefono, activo)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['nombre_empresa'],
            $data['contacto_email'] ?? null,
            $data['telefono'] ?? null,
            $data['activo'] ?? true
        ]);

        return $this->db->lastInsertId();
    }

    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM clientes ORDER BY nombre_empresa");
        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE clientes 
            SET nombre_empresa = ?, contacto_email = ?, telefono = ?, activo = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['nombre_empresa'],
            $data['contacto_email'] ?? null,
            $data['telefono'] ?? null,
            $data['activo'] ?? true,
            $id
        ]);
    }

    public function toggle($id)
    {
        $cliente = $this->getById($id);
        if (!$cliente) {
            return false;
        }

        $newStatus = !$cliente['activo'];

        $stmt = $this->db->prepare("UPDATE clientes SET activo = ? WHERE id = ?");
        return $stmt->execute([$newStatus, $id]);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM clientes WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getActivos()
    {
        $stmt = $this->db->query("SELECT * FROM clientes WHERE activo = 1 ORDER BY nombre_empresa");
        return $stmt->fetchAll();
    }
}
