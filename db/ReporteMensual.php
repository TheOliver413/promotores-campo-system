<?php
require_once __DIR__ . '/../config/database.php';

class ReporteMensual
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        $existing = $this->getByProyectoMesAnio($data['proyecto_id'], $data['mes'], $data['anio']);

        if ($existing) {
            // Update existing report
            return $this->update($existing['id'], $data);
        }

        $stmt = $this->db->prepare("
            INSERT INTO reportes_mensuales 
            (proyecto_id, mes, anio, total_jornadas, total_actividades, 
             total_horas, kpi_cumplimiento, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $data['proyecto_id'],
            $data['mes'],
            $data['anio'],
            $data['total_jornadas'] ?? 0,
            $data['total_actividades'] ?? 0,
            $data['total_horas'] ?? 0,
            $data['kpi_cumplimiento'] ?? 0,
            $data['observaciones'] ?? null
        ]);
    }

    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE reportes_mensuales 
            SET total_jornadas = ?,
                total_actividades = ?,
                total_horas = ?,
                kpi_cumplimiento = ?,
                observaciones = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['total_jornadas'] ?? 0,
            $data['total_actividades'] ?? 0,
            $data['total_horas'] ?? 0,
            $data['kpi_cumplimiento'] ?? 0,
            $data['observaciones'] ?? null,
            $id
        ]);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT rm.*, p.nombre_proyecto, c.nombre_empresa
            FROM reportes_mensuales rm
            JOIN proyectos p ON rm.proyecto_id = p.id
            LEFT JOIN proyecto_clientes pc ON p.id = pc.proyecto_id
            LEFT JOIN clientes c ON pc.cliente_id = c.id
            WHERE rm.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getByProyectoMesAnio($proyectoId, $mes, $anio)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM reportes_mensuales 
            WHERE proyecto_id = ? AND mes = ? AND anio = ?
        ");
        $stmt->execute([$proyectoId, $mes, $anio]);
        return $stmt->fetch();
    }

    public function getByProyecto($proyectoId, $mes = null, $anio = null)
    {
        $sql = "SELECT * FROM reportes_mensuales WHERE proyecto_id = ?";
        $params = [$proyectoId];

        if ($mes) {
            $sql .= " AND mes = ?";
            $params[] = $mes;
        }

        if ($anio) {
            $sql .= " AND anio = ?";
            $params[] = $anio;
        }

        $sql .= " ORDER BY anio DESC, mes DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getByCliente($clienteId, $mes = null, $anio = null)
    {
        $sql = "SELECT rm.*, p.nombre_proyecto 
                FROM reportes_mensuales rm
                JOIN proyectos p ON rm.proyecto_id = p.id
                JOIN proyecto_clientes pc ON p.id = pc.proyecto_id
                WHERE pc.cliente_id = ?";

        $params = [$clienteId];

        if ($mes) {
            $sql .= " AND rm.mes = ?";
            $params[] = $mes;
        }

        if ($anio) {
            $sql .= " AND rm.anio = ?";
            $params[] = $anio;
        }

        $sql .= " ORDER BY rm.anio DESC, rm.mes DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAll($mes = null, $anio = null)
    {
        $sql = "SELECT rm.*, p.nombre_proyecto 
                FROM reportes_mensuales rm
                JOIN proyectos p ON rm.proyecto_id = p.id
                WHERE 1=1";

        $params = [];

        if ($mes) {
            $sql .= " AND rm.mes = ?";
            $params[] = $mes;
        }

        if ($anio) {
            $sql .= " AND rm.anio = ?";
            $params[] = $anio;
        }

        $sql .= " ORDER BY rm.anio DESC, rm.mes DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function generarReporte($proyectoId, $mes, $anio)
    {
        // Calcular métricas del mes
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT j.id) as total_jornadas,
                COUNT(DISTINCT a.id) as total_actividades,
                SUM(j.horas_calculadas) as total_horas,
                (COUNT(CASE WHEN j.estado_validacion = 'aprobado' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)) as kpi_cumplimiento
            FROM jornadas j
            LEFT JOIN actividades a ON j.id = a.jornada_id
            WHERE j.proyecto_id = ?
            AND MONTH(j.fecha_jornada) = ?
            AND YEAR(j.fecha_jornada) = ?
        ");

        $stmt->execute([$proyectoId, $mes, $anio]);
        $metricas = $stmt->fetch();

        // Crear o actualizar reporte
        $this->create([
            'proyecto_id' => $proyectoId,
            'mes' => $mes,
            'anio' => $anio,
            'total_jornadas' => $metricas['total_jornadas'] ?? 0,
            'total_actividades' => $metricas['total_actividades'] ?? 0,
            'total_horas' => $metricas['total_horas'] ?? 0,
            'kpi_cumplimiento' => round($metricas['kpi_cumplimiento'] ?? 0, 2)
        ]);

        return $metricas;
    }
}
