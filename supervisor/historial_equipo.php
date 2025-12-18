<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/auth_helpers.php';
require_once '../db/ActaVisita.php';
require_once '../db/Cotizacion.php';
require_once '../db/SupervisorPromotor.php';

checkAuth();
checkRole(['Supervisor']);

$user_id = $_SESSION['user_id'];
$actaModel = new ActaVisita();
$cotizacionModel = new Cotizacion();
$spModel = new SupervisorPromotor();

// Get actas and cotizaciones from team
$actas = $actaModel->getBySupervisor($user_id);
$cotizaciones = $cotizacionModel->getBySupervisor($user_id);

// Get promotores del equipo
$promotores = $spModel->getPromotoresBySupervisor($user_id);

$pageTitle = 'Historial del Equipo';
include '../includes/header.php';

// Get active tab and filters from URL
$activeTab = $_GET['tab'] ?? 'actas';
$filtroPromotor = $_GET['promotor'] ?? '';
?>

<style>
    .history-card {
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .history-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .badge-tipo {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }

    .filter-badge {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .filter-badge:hover {
        transform: scale(1.05);
    }

    /* Added tab styles from validacion.php */
    #historialTabs .nav-link {
        color: #fff !important;
        background-color: #0d6efd !important;
        border: none !important;
        margin-right: 3px;
    }

    #historialTabs .nav-link:hover {
        background-color: #0b5ed7 !important;
    }

    #historialTabs .nav-link.active {
        background-color: #0a58ca !important;
        color: #fff !important;
        font-weight: bold;
        border-bottom: 2px solid #fff !important;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="h3"><i class="bi bi-people me-2"></i>Historial del Equipo</h2>
            <p class="text-muted">Consulta las actas y cotizaciones de tus promotores</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="text-muted me-2"><i class="bi bi-filter me-1"></i>Filtrar por promotor:</span>
                <span class="badge bg-secondary filter-badge <?php echo empty($filtroPromotor) ? 'bg-primary' : ''; ?>"
                    onclick="filtrarPromotor('')">
                    Todos (<?php echo count($actas) + count($cotizaciones); ?>)
                </span>
                <?php foreach ($promotores as $promotor): ?>
                    <span class="badge bg-secondary filter-badge <?php echo $filtroPromotor == $promotor['id'] ? 'bg-primary' : ''; ?>"
                        onclick="filtrarPromotor(<?php echo $promotor['id']; ?>)">
                        <?php echo htmlspecialchars($promotor['nombre_completo']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-3 bg-light rounded-top" id="historialTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'actas' ? 'active' : ''; ?>"
                id="actas-tab" data-bs-toggle="tab" data-bs-target="#actas-pane"
                type="button" role="tab">
                <i class="bi bi-file-earmark-text me-2"></i>
                Actas de Visita
                <span class="badge rounded-pill bg-danger ms-1"><?php echo count($actas); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'cotizaciones' ? 'active' : ''; ?>"
                id="cotizaciones-tab" data-bs-toggle="tab" data-bs-target="#cotizaciones-pane"
                type="button" role="tab">
                <i class="bi bi-receipt me-2"></i>
                Cotizaciones/Ventas
                <span class="badge rounded-pill bg-danger ms-1"><?php echo count($cotizaciones); ?></span>
            </button>
        </li>
    </ul>

    <!-- Tabs Content -->
    <div class="tab-content" id="historialTabContent">
        <!-- Actas Tab -->
        <div class="tab-pane fade <?php echo $activeTab === 'actas' ? 'show active' : ''; ?>"
            id="actas-pane" role="tabpanel">
            <?php if (empty($actas)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: #dee2e6;"></i>
                        <h4 class="text-muted mt-3">No hay actas registradas</h4>
                        <p class="text-muted">Las actas de visita de tu equipo aparecerán aquí</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($actas as $acta): ?>
                        <div class="col-md-6 col-lg-4 mb-3" data-promotor-id="<?php echo $acta['promotor_user_id']; ?>">
                            <div class="card history-card h-100 shadow-sm" onclick="verActaDetalle(<?php echo $acta['id']; ?>)">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h6 class="mb-0 fw-bold">
                                            <i class="bi bi-geo-alt me-1 text-primary"></i>
                                            <?php echo htmlspecialchars($acta['punto_visita_nombre']); ?>
                                        </h6>
                                        <span class="badge bg-success badge-tipo">Acta</span>
                                    </div>

                                    <p class="mb-2 small">
                                        <span class="badge bg-info">
                                            <i class="bi bi-person me-1"></i>
                                            <?php echo htmlspecialchars($acta['promotor_nombre']); ?>
                                        </span>
                                    </p>

                                    <p class="mb-2 small text-muted">
                                        <i class="bi bi-person me-1"></i>
                                        <strong>Receptor:</strong> <?php echo htmlspecialchars($acta['receptor_nombre']); ?>
                                    </p>

                                    <p class="mb-2 small text-muted">
                                        <i class="bi bi-camera me-1"></i>
                                        <?php echo $acta['num_fotos'] ?? 0; ?> fotografías
                                    </p>

                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($acta['fecha_visita'])); ?>
                                        </small>
                                        <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); verActaDetalle(<?php echo $acta['id']; ?>)">
                                            <i class="bi bi-eye"></i> Ver
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cotizaciones Tab -->
        <div class="tab-pane fade <?php echo $activeTab === 'cotizaciones' ? 'show active' : ''; ?>"
            id="cotizaciones-pane" role="tabpanel">
            <?php if (empty($cotizaciones)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: #dee2e6;"></i>
                        <h4 class="text-muted mt-3">No hay cotizaciones registradas</h4>
                        <p class="text-muted">Las cotizaciones de tu equipo aparecerán aquí</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($cotizaciones as $cotizacion): ?>
                        <div class="col-md-6 col-lg-4 mb-3" data-promotor-id="<?php echo $cotizacion['promotor_user_id']; ?>">
                            <div class="card history-card h-100 shadow-sm" onclick="verCotizacionDetalle(<?php echo $cotizacion['id']; ?>)">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h6 class="mb-0 fw-bold">
                                            <i class="bi bi-receipt me-1 text-success"></i>
                                            <?php echo htmlspecialchars($cotizacion['numero_cotizacion']); ?>
                                        </h6>
                                        <span class="badge bg-<?php
                                                                echo $cotizacion['tipo'] === 'venta' ? 'success' : ($cotizacion['tipo'] === 'pedido' ? 'info' : 'primary');
                                                                ?> badge-tipo">
                                            <?php echo ucfirst($cotizacion['tipo']); ?>
                                        </span>
                                    </div>

                                    <p class="mb-2 small">
                                        <span class="badge bg-info">
                                            <i class="bi bi-person me-1"></i>
                                            <?php echo htmlspecialchars($cotizacion['promotor_nombre']); ?>
                                        </span>
                                    </p>

                                    <p class="mb-2 small">
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($cotizacion['cliente_nombre']); ?>
                                        </span>
                                    </p>

                                    <p class="mb-2 small text-muted">
                                        <i class="bi bi-box me-1"></i>
                                        <?php echo $cotizacion['num_items'] ?? 0; ?> productos
                                    </p>

                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted">Total:</span>
                                        <strong class="text-success">$<?php echo number_format($cotizacion['total'], 2); ?></strong>
                                    </div>

                                    <div class="mb-2">
                                        <span class="badge bg-<?php
                                                                echo $cotizacion['estado'] === 'completada' ? 'success' : ($cotizacion['estado'] === 'aprobada' ? 'info' : ($cotizacion['estado'] === 'rechazada' ? 'danger' : 'warning'));
                                                                ?>">
                                            <?php echo ucfirst($cotizacion['estado']); ?>
                                        </span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?php echo date('d/m/Y', strtotime($cotizacion['fecha_creacion'])); ?>
                                        </small>
                                        <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); verCotizacionDetalle(<?php echo $cotizacion['id']; ?>)">
                                            <i class="bi bi-eye"></i> Ver
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Ver Acta Detalle -->
<div class="modal fade" id="actaDetalleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Detalle del Acta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="actaDetalleContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ver Cotización Detalle -->
<div class="modal fade" id="cotizacionDetalleModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Detalle de Cotización</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cotizacionDetalleContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function filtrarPromotor(promotorId) {
        const url = new URL(window.location);
        if (promotorId) {
            url.searchParams.set('promotor', promotorId);
        } else {
            url.searchParams.delete('promotor');
        }
        window.location.href = url.toString();
    }

    // Apply promotor filter if set
    const filtroPromotor = '<?php echo $filtroPromotor; ?>';
    if (filtroPromotor) {
        document.querySelectorAll('[data-promotor-id]').forEach(card => {
            if (card.getAttribute('data-promotor-id') !== filtroPromotor) {
                card.style.display = 'none';
            }
        });
    }

    function verActaDetalle(actaId) {
        const modal = new bootstrap.Modal(document.getElementById('actaDetalleModal'));
        modal.show();

        fetch(`/promotores-campo-system/api/acta_crud.php?id=${actaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const acta = data.acta;
                    const fotografias = data.fotografias || [];

                    let html = `
                        <div class="alert alert-info mb-3">
                            <strong><i class="bi bi-person me-2"></i>Promotor:</strong> ${acta.promotor_nombre}
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="text-primary"><i class="bi bi-geo-alt me-2"></i>Punto de Visita</h6>
                                <p class="mb-1"><strong>${acta.punto_visita_nombre}</strong></p>
                                <p class="text-muted small">${acta.punto_visita_direccion || 'Sin dirección'}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-primary"><i class="bi bi-person me-2"></i>Receptor</h6>
                                <p class="mb-1"><strong>${acta.receptor_nombre}</strong></p>
                                ${acta.receptor_telefono ? `<p class="mb-1 small"><i class="bi bi-telephone me-1"></i>${acta.receptor_telefono}</p>` : ''}
                                ${acta.receptor_email ? `<p class="mb-1 small"><i class="bi bi-envelope me-1"></i>${acta.receptor_email}</p>` : ''}
                                ${acta.receptor_direccion ? `<p class="text-muted small">${acta.receptor_direccion}</p>` : ''}
                            </div>
                        </div>
                        
                        ${acta.observacion ? `
                            <div class="mb-3">
                                <h6 class="text-primary"><i class="bi bi-chat-left-text me-2"></i>Observaciones</h6>
                                <p class="text-muted">${acta.observacion}</p>
                            </div>
                        ` : ''}
                        
                        ${acta.firma_digital ? `
                            <div class="mb-3">
                                <h6 class="text-primary"><i class="bi bi-pen me-2"></i>Firma Digital</h6>
                                <img src="${acta.firma_digital}" alt="Firma" class="img-fluid border rounded" style="max-height: 150px;">
                            </div>
                        ` : ''}
                        
                        ${fotografias.length > 0 ? `
                            <div class="mb-3">
                                <h6 class="text-primary"><i class="bi bi-camera me-2"></i>Fotografías (${fotografias.length})</h6>
                                <div class="row g-2">
                                    ${fotografias.map(foto => `
                                        <div class="col-md-4">
                                            <img src="${foto.url_foto}" alt="Foto" class="img-fluid rounded" style="cursor: pointer;" onclick="window.open('${foto.url_foto}', '_blank')">
                                            ${foto.latitud && foto.longitud ? `
                                                <small class="d-block text-muted text-center mt-1">
                                                    <i class="bi bi-geo-alt"></i> ${parseFloat(foto.latitud).toFixed(6)}, ${parseFloat(foto.longitud).toFixed(6)}
                                                </small>
                                            ` : ''}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        <div class="text-end text-muted small">
                            <i class="bi bi-calendar me-1"></i>
                            Registrada: ${new Date(acta.fecha_visita).toLocaleString('es-MX')}
                        </div>
                    `;

                    document.getElementById('actaDetalleContent').innerHTML = html;
                } else {
                    document.getElementById('actaDetalleContent').innerHTML = `
                        <div class="alert alert-danger">Error al cargar el acta</div>
                    `;
                }
            })
            .catch(error => {
                console.error('[v0] Error:', error);
                document.getElementById('actaDetalleContent').innerHTML = `
                    <div class="alert alert-danger">Error de conexión</div>
                `;
            });
    }

    function verCotizacionDetalle(cotizacionId) {
        const modal = new bootstrap.Modal(document.getElementById('cotizacionDetalleModal'));
        modal.show();

        fetch(`/promotores-campo-system/api/cotizacion_crud.php?id=${cotizacionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cot = data.cotizacion;
                    const detalles = data.detalles || [];

                    let html = `
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="mb-3">${cot.numero_cotizacion}</h5>
                                <p class="mb-1"><strong>Promotor:</strong> 
                                    <span class="badge bg-info">${cot.promotor_nombre}</span>
                                </p>
                                <p class="mb-1"><strong>Cliente:</strong> ${cot.cliente_nombre}</p>
                                <p class="mb-1"><strong>Tipo:</strong> 
                                    <span class="badge bg-${cot.tipo === 'venta' ? 'success' : (cot.tipo === 'pedido' ? 'info' : 'primary')}">${cot.tipo.toUpperCase()}</span>
                                </p>
                                <p class="mb-1"><strong>Estado:</strong> 
                                    <span class="badge bg-${cot.estado === 'completada' ? 'success' : (cot.estado === 'aprobada' ? 'info' : (cot.estado === 'rechazada' ? 'danger' : 'warning'))}">${cot.estado.toUpperCase()}</span>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-1 text-muted">Fecha: ${new Date(cot.fecha_creacion).toLocaleDateString('es-MX')}</p>
                                ${cot.punto_visita_nombre ? `<p class="mb-1 text-muted"><i class="bi bi-geo-alt me-1"></i>${cot.punto_visita_nombre}</p>` : ''}
                            </div>
                        </div>
                        
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th>Código</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Precio Unit.</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${detalles.map(det => `
                                        <tr>
                                            <td>${det.producto_nombre}</td>
                                            <td><code>${det.codigo}</code></td>
                                            <td class="text-center">${det.cantidad}</td>
                                            <td class="text-end">$${parseFloat(det.precio_unitario).toFixed(2)}</td>
                                            <td class="text-end">$${parseFloat(det.subtotal).toFixed(2)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end">$${parseFloat(cot.subtotal).toFixed(2)}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Impuestos:</strong></td>
                                        <td class="text-end">$${parseFloat(cot.impuestos).toFixed(2)}</td>
                                    </tr>
                                    <tr class="table-success">
                                        <td colspan="4" class="text-end"><strong>TOTAL:</strong></td>
                                        <td class="text-end"><strong>$${parseFloat(cot.total).toFixed(2)}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        ${cot.notas ? `
                            <div class="alert alert-info">
                                <strong><i class="bi bi-chat-left-text me-2"></i>Notas:</strong>
                                <p class="mb-0 mt-2">${cot.notas}</p>
                            </div>
                        ` : ''}
                    `;

                    document.getElementById('cotizacionDetalleContent').innerHTML = html;
                } else {
                    document.getElementById('cotizacionDetalleContent').innerHTML = `
                        <div class="alert alert-danger">Error al cargar la cotización</div>
                    `;
                }
            })
            .catch(error => {
                console.error('[v0] Error:', error);
                document.getElementById('cotizacionDetalleContent').innerHTML = `
                    <div class="alert alert-danger">Error de conexión</div>
                `;
            });
    }
</script>

<?php include '../includes/footer.php'; ?>