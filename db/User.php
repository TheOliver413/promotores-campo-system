<?php
require_once __DIR__ . '/../config/database.php';

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function authenticate($email, $password)
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre as role_name 
            FROM usuarios u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ? AND u.estado = 'activo'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last access
            $this->updateLastAccess($user['id']);
            return $user;
        }

        return false;
    }

    public function create($data)
    {
        if ($this->emailExists($data['email'])) {
            throw new Exception('El email ya está registrado en el sistema');
        }

        if (!empty($data['telefono']) && $this->telefonoExists($data['telefono'])) {
            throw new Exception('El teléfono ya está registrado en el sistema');
        }

        $stmt = $this->db->prepare("
            INSERT INTO usuarios (nombre_completo, email, telefono, password_hash, role_id, estado)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $passwordHash = isset($data['password_hash']) ? $data['password_hash'] : password_hash($data['password'], PASSWORD_DEFAULT);

        $result = $stmt->execute([
            $data['nombre_completo'],
            $data['email'],
            $data['telefono'] ?? null,
            $passwordHash,
            $data['role_id'],
            $data['estado'] ?? 'activo'
        ]);

        if ($result) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    public function getAll($filters = [])
    {
        $sql = "SELECT u.*, r.nombre as role_name,
                GROUP_CONCAT(DISTINCT c.nombre_empresa SEPARATOR ', ') as clientes_nombres,
                GROUP_CONCAT(DISTINCT s.nombre_completo SEPARATOR ', ') as supervisores_nombres
                FROM usuarios u 
                JOIN roles r ON u.role_id = r.id 
                LEFT JOIN usuario_clientes uc ON u.id = uc.usuario_id
                LEFT JOIN clientes c ON uc.cliente_id = c.id
                LEFT JOIN supervisor_promotores sp ON u.id = sp.promotor_id
                LEFT JOIN usuarios s ON sp.supervisor_id = s.id
                WHERE 1=1";

        $params = [];

        if (!empty($filters['role_id'])) {
            $sql .= " AND u.role_id = ?";
            $params[] = $filters['role_id'];
        }

        if (!empty($filters['estado'])) {
            $sql .= " AND u.estado = ?";
            $params[] = $filters['estado'];
        }

        $sql .= " GROUP BY u.id ORDER BY u.nombre_completo";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre as role_name 
            FROM usuarios u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update($id, $data)
    {
        if (isset($data['email']) && $this->emailExists($data['email'], $id)) {
            throw new Exception('El email ya está registrado en el sistema');
        }

        if (isset($data['telefono']) && !empty($data['telefono']) && $this->telefonoExists($data['telefono'], $id)) {
            throw new Exception('El teléfono ya está registrado en el sistema');
        }

        $fields = [];
        $params = [];

        if (isset($data['nombre_completo'])) {
            $fields[] = "nombre_completo = ?";
            $params[] = $data['nombre_completo'];
        }

        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }

        if (isset($data['telefono'])) {
            $fields[] = "telefono = ?";
            $params[] = $data['telefono'];
        }

        if (isset($data['role_id'])) {
            $fields[] = "role_id = ?";
            $params[] = $data['role_id'];
        }

        if (isset($data['estado'])) {
            $fields[] = "estado = ?";
            $params[] = $data['estado'];
        }

        if (!empty($data['password'])) {
            $fields[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateStatus($id, $status)
    {
        $stmt = $this->db->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM usuarios WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function updateLastAccess($userId)
    {
        $stmt = $this->db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    public function createResetToken($email)
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET reset_token = ?, token_expires = ? 
            WHERE email = ?
        ");

        if ($stmt->execute([$token, $expires, $email])) {
            return $token;
        }

        return false;
    }

    public function verifyResetToken($token)
    {
        $stmt = $this->db->prepare("
            SELECT id FROM usuarios 
            WHERE reset_token = ? AND token_expires > NOW()
        ");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    public function resetPassword($token, $newPassword)
    {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET password_hash = ?, reset_token = NULL, token_expires = NULL 
            WHERE reset_token = ?
        ");

        return $stmt->execute([$passwordHash, $token]);
    }

    public function getPromotores()
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre as role_name 
            FROM usuarios u 
            JOIN roles r ON u.role_id = r.id 
            WHERE r.nombre = 'Promotor' AND u.estado = 'activo'
            ORDER BY u.nombre_completo
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getSupervisores()
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre as role_name 
            FROM usuarios u 
            JOIN roles r ON u.role_id = r.id 
            WHERE r.nombre = 'Supervisor' AND u.estado = 'activo'
            ORDER BY u.nombre_completo
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPromotoresBySupervisor($supervisorId, $limit = null, $offset = null, $filtroNombre = '', $filtroEstado = '')
    {
        $sql = "SELECT u.*, r.nombre as role_name,
                   GROUP_CONCAT(DISTINCT c.nombre_empresa ORDER BY c.nombre_empresa SEPARATOR ', ') as clientes_nombres
            FROM usuarios u 
            JOIN roles r ON u.role_id = r.id 
            LEFT JOIN supervisor_promotores sp ON u.id = sp.promotor_id
            LEFT JOIN proyecto_promotores pp ON u.id = pp.promotor_user_id
            LEFT JOIN proyecto_clientes pc ON pp.proyecto_id = pc.proyecto_id
            LEFT JOIN clientes c ON pc.cliente_id = c.id
            WHERE sp.supervisor_id = ?";

        $params = [$supervisorId];

        // Add filters
        if (!empty($filtroNombre)) {
            $sql .= " AND u.nombre_completo LIKE ?";
            $params[] = "%{$filtroNombre}%";
        }

        if (!empty($filtroEstado)) {
            $sql .= " AND u.estado = ?";
            $params[] = $filtroEstado;
        } else {
            // Default to active only if no filter specified
            $sql .= " AND u.estado = 'activo'";
        }

        $sql .= " GROUP BY u.id ORDER BY u.nombre_completo";

        // Add pagination if provided
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = (int)$limit;

            if ($offset !== null) {
                $sql .= " OFFSET ?";
                $params[] = (int)$offset;
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getPromotoresWithSupervisors()
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre as role_name,
                   GROUP_CONCAT(DISTINCT s.nombre_completo SEPARATOR ', ') as supervisores_nombres,
                   GROUP_CONCAT(DISTINCT c.nombre_empresa SEPARATOR ', ') as clientes_nombres
            FROM usuarios u 
            JOIN roles r ON u.role_id = r.id 
            LEFT JOIN supervisor_promotores sp ON u.id = sp.promotor_id
            LEFT JOIN usuarios s ON sp.supervisor_id = s.id
            LEFT JOIN usuario_clientes uc ON u.id = uc.usuario_id
            LEFT JOIN clientes c ON uc.cliente_id = c.id
            WHERE r.nombre = 'Promotor' AND u.estado = 'activo'
            GROUP BY u.id
            ORDER BY u.nombre_completo
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function emailExists($email, $excludeId = null)
    {
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $params = [$email];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    public function telefonoExists($telefono, $excludeId = null)
    {
        if (empty($telefono)) {
            return false;
        }

        $sql = "SELECT id FROM usuarios WHERE telefono = ?";
        $params = [$telefono];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }
}
