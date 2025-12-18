<?php
require_once __DIR__ . '/../config/database.php';

class ActaVisita
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO actas_visita 
            (promotor_user_id, ruta_promotor_id, punto_visita_nombre, punto_visita_direccion,
             receptor_nombre, receptor_telefono, receptor_email, receptor_direccion,
             observacion, firma_digital, huella_digital, latitud, longitud)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['promotor_user_id'],
            $data['ruta_promotor_id'] ?? null,
            $data['punto_visita_nombre'],
            $data['punto_visita_direccion'] ?? null,
            $data['receptor_nombre'],
            $data['receptor_telefono'] ?? null,
            $data['receptor_email'] ?? null,
            $data['receptor_direccion'] ?? null,
            $data['observacion'] ?? null,
            $data['firma_digital'] ?? null,
            $data['huella_digital'] ?? null,
            $data['latitud'] ?? null,
            $data['longitud'] ?? null
        ]);

        if ($result) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    public function agregarFotografia($actaId, $urlFoto, $latitud = null, $longitud = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO actas_fotografias 
            (acta_id, url_foto, latitud, longitud)
            VALUES (?, ?, ?, ?)
        ");

        return $stmt->execute([$actaId, $urlFoto, $latitud, $longitud]);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT av.*, u.nombre_completo as promotor_nombre
            FROM actas_visita av
            LEFT JOIN usuarios u ON av.promotor_user_id = u.id
            WHERE av.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getFotografias($actaId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM actas_fotografias 
            WHERE acta_id = ? 
            ORDER BY fecha_captura ASC
        ");
        $stmt->execute([$actaId]);
        return $stmt->fetchAll();
    }

    public function getByPromotor($promotorId, $limit = null)
    {
        $sql = "
            SELECT av.*, 
                   (SELECT COUNT(*) FROM actas_fotografias WHERE acta_id = av.id) as num_fotos
            FROM actas_visita av
            WHERE av.promotor_user_id = ?
            ORDER BY av.fecha_visita DESC
        ";

        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$promotorId]);
        return $stmt->fetchAll();
    }

    public function getBySupervisor($supervisorId, $limit = null)
    {
        $sql = "
            SELECT av.*, 
                   u.nombre_completo as promotor_nombre,
                   (SELECT COUNT(*) FROM actas_fotografias WHERE acta_id = av.id) as num_fotos
            FROM actas_visita av
            INNER JOIN usuarios u ON av.promotor_user_id = u.id
            INNER JOIN supervisor_promotores sp ON u.id = sp.promotor_id
            WHERE sp.supervisor_id = ?
            ORDER BY av.fecha_visita DESC
        ";

        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$supervisorId]);
        return $stmt->fetchAll();
    }
}
