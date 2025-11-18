<?php
$pageTitle = 'Auditoría del Sistema';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/Auditoria.php';

requireRole(['Administrador']);

$auditoriaModel = new Auditoria();

// Filters
$filtroUsuario = $_GET['usuario'] ?? '';
$filtroAccion = $_GET['accion'] ?? '';
$filtroTabla = $_GET['tabla'] ?? '';
$filtroFechaInicio = $_GET['fecha_inicio'] ?? '';
$filtroFechaFin = $_GET['fecha_fin'] ?? '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query
$db = Database::getInstance()->getConnection();
$sql = "SELECT a.*, u.nombre_completo as usuario_nombre 
        FROM auditoria a 
        LEFT JOIN usuarios u ON a.usuario_id = u.id 
        WHERE 1=1";
$params = [];

if ($filtroUsuario) {
    $sql .= " AND u.nombre_completo LIKE ?";
    $params[] = "%$filtroUsuario%";
}

if ($filtroAccion) {
    $sql .= " AND a.accion = ?";
    $params[] = $filtroAccion;
}

if ($filtroTabla) {
    $sql .= " AND a.tabla_afectada = ?";
    $params[] = $filtroTabla;
}

if ($filtroFechaInicio) {
    $sql .= " AND DATE(a.timestamp_accion) >= ?";
    $params[] = $filtroFechaInicio;
}

if ($filtroFechaFin) {
    $sql .= " AND DATE(a.timestamp_accion) <= ?";
    $params[] = $filtroFechaFin;
}

$countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as count_table";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['total'];
$totalPages = ceil($totalRecords / $perPage);

$sql .= " ORDER BY a.timestamp_accion DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

// Get unique actions and tables for filters
$accionesStmt = $db->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion");
$acciones = $accionesStmt->fetchAll();

$tablasStmt = $db->query("SELECT DISTINCT tabla_afectada FROM auditoria WHERE tabla_afectada IS NOT NULL ORDER BY tabla_afectada");
$tablas = $tablasStmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Auditoría del Sistema</h1>
            <p class="text-muted">Registro de todas las acciones realizadas en el sistema</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="usuario" name="usuario" value="<?php echo htmlspecialchars($filtroUsuario); ?>" placeholder="Nombre del usuario">
                </div>
                <div class="col-md-2">
                    <label for="accion" class="form-label">Acción</label>
                    <select class="form-select" id="accion" name="accion">
                        <option value="">Todas</option>
                        <?php foreach ($acciones as $accion): ?>
                            <option value="<?php echo $accion['accion']; ?>" <?php echo $filtroAccion === $accion['accion'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($accion['accion']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="tabla" class="form-label">Tabla</label>
                    <select class="form-select" id="tabla" name="tabla">
                        <option value="">Todas</option>
                        <?php foreach ($tablas as $tabla): ?>
                            <option value="<?php echo $tabla['tabla_afectada']; ?>" <?php echo $filtroTabla === $tabla['tabla_afectada'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tabla['tabla_afectada']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($filtroFechaInicio); ?>">
                </div>
                <div class="col-md-2">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($filtroFechaFin); ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="card">
        <div class="card-header">
            <!-- Updated header to show pagination info -->
            <h5 class="mb-0">Registros de Auditoría (<?php echo $totalRecords; ?> total, mostrando página <?php echo $page; ?> de <?php echo $totalPages; ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha/Hora</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Tabla</th>
                            <th>Registro ID</th>
                            <th>IP</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $registro): ?>
                            <tr>
                                <td><?php echo $registro['id']; ?></td>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($registro['timestamp_accion'])); ?></td>
                                <td><?php echo htmlspecialchars($registro['usuario_nombre'] ?? 'Sistema'); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = 'secondary';
                                    if (in_array($registro['accion'], ['CREATE', 'INSERT'])) $badgeClass = 'success';
                                    elseif (in_array($registro['accion'], ['UPDATE', 'EDIT'])) $badgeClass = 'info';
                                    elseif (in_array($registro['accion'], ['DELETE', 'REMOVE'])) $badgeClass = 'danger';
                                    elseif ($registro['accion'] === 'LOGIN') $badgeClass = 'primary';
                                    ?>
                                    <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($registro['accion']); ?></span>
                                </td>
                                <td><code><?php echo htmlspecialchars($registro['tabla_afectada'] ?? '-'); ?></code></td>
                                <td><?php echo $registro['registro_afectado_id'] ?? '-'; ?></td>
                                <td><small><?php echo htmlspecialchars($registro['ip_address'] ?? '-'); ?></small></td>
                                <td>
                                    <?php if (!empty($registro['detalles'])): ?>
                                        <button class="btn btn-sm btn-outline-secondary" onclick='showDetails(<?php echo htmlspecialchars($registro['detalles']); ?>)'>
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Added pagination controls -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&usuario=<?php echo urlencode($filtroUsuario); ?>&accion=<?php echo urlencode($filtroAccion); ?>&tabla=<?php echo urlencode($filtroTabla); ?>&fecha_inicio=<?php echo urlencode($filtroFechaInicio); ?>&fecha_fin=<?php echo urlencode($filtroFechaFin); ?>">Anterior</a>
                            </li>
                        <?php endif; ?>

                        <?php
                        // Show page numbers with ellipsis
                        $range = 2;
                        for ($i = 1; $i <= $totalPages; $i++) {
                            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                        ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&usuario=<?php echo urlencode($filtroUsuario); ?>&accion=<?php echo urlencode($filtroAccion); ?>&tabla=<?php echo urlencode($filtroTabla); ?>&fecha_inicio=<?php echo urlencode($filtroFechaInicio); ?>&fecha_fin=<?php echo urlencode($filtroFechaFin); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php
                            } elseif ($i == $page - $range - 1 || $i == $page + $range + 1) {
                            ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                        <?php
                            }
                        }
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&usuario=<?php echo urlencode($filtroUsuario); ?>&accion=<?php echo urlencode($filtroAccion); ?>&tabla=<?php echo urlencode($filtroTabla); ?>&fecha_inicio=<?php echo urlencode($filtroFechaInicio); ?>&fecha_fin=<?php echo urlencode($filtroFechaFin); ?>">Siguiente</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Registro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="detailsContent" class="bg-light p-3 rounded"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function showDetails(details) {
        document.getElementById('detailsContent').textContent = JSON.stringify(details, null, 2);
        new bootstrap.Modal(document.getElementById('detailsModal')).show();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>