<?php
require_once __DIR__ . '/../config/database.php';

class UsuarioCliente
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function asignarClientes($usuarioId, $clienteIds)
    {
        try {
            // Delete previous assignments
            $stmt = $this->db->prepare("DELETE FROM usuario_clientes WHERE usuario_id = ?");
            $stmt->execute([$usuarioId]);

            // Insert new assignments
            if (!empty($clienteIds) && is_array($clienteIds)) {
                $stmt = $this->db->prepare("INSERT INTO usuario_clientes (usuario_id, cliente_id) VALUES (?, ?)");
                foreach ($clienteIds as $clienteId) {
                    if (!empty($clienteId)) {
                        $stmt->execute([$usuarioId, $clienteId]);
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error asignando clientes: " . $e->getMessage());
            throw $e; // Importante: que suba la excepción para que el catch externo haga rollback
        }
    }

    // Get clients assigned to a user
    public function getClientesByUsuario($usuarioId)
    {
        $stmt = $this->db->prepare("
            SELECT c.* 
            FROM clientes c
            INNER JOIN usuario_clientes uc ON c.id = uc.cliente_id
            WHERE uc.usuario_id = ?
            ORDER BY c.nombre_empresa
        ");
        $stmt->execute([$usuarioId]);
        return $stmt->fetchAll();
    }

    // Get users assigned to a client
    public function getUsuariosByCliente($clienteId)
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre as role_name 
            FROM usuarios u
            INNER JOIN usuario_clientes uc ON u.id = uc.usuario_id
            INNER JOIN roles r ON u.role_id = r.id
            WHERE uc.cliente_id = ?
            ORDER BY u.nombre_completo
        ");
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll();
    }

    public function getCountUsuariosByCliente($clienteId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM usuario_clientes
            WHERE cliente_id = ?
        ");
        $stmt->execute([$clienteId]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }

    public function getCountClientesByUsuario($usuarioId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM usuario_clientes
            WHERE usuario_id = ?
        ");
        $stmt->execute([$usuarioId]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }

    // Verify if a user has access to a client
    public function tieneAcceso($usuarioId, $clienteId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM usuario_clientes 
            WHERE usuario_id = ? AND cliente_id = ?
        ");
        $stmt->execute([$usuarioId, $clienteId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    public function removerCliente($usuarioId, $clienteId)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM usuario_clientes WHERE usuario_id = ? AND cliente_id = ?");
            return $stmt->execute([$usuarioId, $clienteId]);
        } catch (Exception $e) {
            error_log("Error removiendo cliente: " . $e->getMessage());
            return false;
        }
    }

    public function getAllAsignaciones()
    {
        $stmt = $this->db->query("
            SELECT uc.*, 
                   u.nombre_completo as usuario_nombre,
                   u.email as usuario_email,
                   r.nombre as usuario_rol,
                   c.nombre_empresa as cliente_nombre
            FROM usuario_clientes uc
            INNER JOIN usuarios u ON uc.usuario_id = u.id
            INNER JOIN roles r ON u.role_id = r.id
            INNER JOIN clientes c ON uc.cliente_id = c.id
            ORDER BY u.nombre_completo, c.nombre_empresa
        ");
        return $stmt->fetchAll();
    }
}
