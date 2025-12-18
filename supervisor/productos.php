<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/Producto.php';
require_once __DIR__ . '/../db/User.php';
require_once __DIR__ . '/../db/SupervisorPromotor.php';

requireRole(['Supervisor']);

$productoModel = new Producto();
$userModel = new User();
$supervisorPromotorModel = new SupervisorPromotor();

$supervisorId = getUserId();
$productos = $productoModel->getProductosDisponiblesParaSupervisor($supervisorId);
$promotores = $supervisorPromotorModel->getPromotoresBySupervisor($supervisorId);

$pageTitle = 'Asignar Productos a Promotores';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Asignar Productos a Promotores</h1>
            <p class="text-muted">Asigna productos a tus promotores para que puedan realizar ventas</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?php echo $_SESSION['success'];
                                                    unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Productos Disponibles</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($productos)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No hay productos disponibles</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($productos as $producto): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                            <p class="mb-1 small text-muted">
                                                <strong>Código:</strong> <?php echo htmlspecialchars($producto['codigo']); ?>
                                                <?php if ($producto['sku']): ?>
                                                    | <strong>SKU:</strong> <?php echo htmlspecialchars($producto['sku']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <p class="mb-0 small">
                                                <span class="badge bg-info"><?php echo htmlspecialchars($producto['nombre_empresa']); ?></span>
                                                <span class="badge bg-success">Stock: <?php echo $producto['cantidad_stock']; ?></span>
                                                <span class="badge bg-primary">$<?php echo number_format($producto['precio'], 2); ?></span>
                                            </p>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary ms-2" onclick='asignarProducto(<?php echo json_encode($producto); ?>)'>
                                            <i class="bi bi-person-plus"></i> Asignar
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Mis Promotores</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($promotores)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No tienes promotores asignados</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($promotores as $promotor): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($promotor['nombre_completo']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($promotor['email']); ?></small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-info" onclick="verProductosPromotor(<?php echo $promotor['id']; ?>, '<?php echo htmlspecialchars($promotor['nombre_completo']); ?>')">
                                            <i class="bi bi-eye"></i> Ver Productos
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Asignar Producto -->
<div class="modal fade" id="asignarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Asignar Producto a Promotor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="asignar_producto_id">

                <div class="mb-3">
                    <label class="form-label">Producto</label>
                    <input type="text" class="form-control" id="asignar_producto_nombre" readonly>
                </div>

                <div class="mb-3">
                    <label for="asignar_promotor" class="form-label">Seleccionar Promotor *</label>
                    <select class="form-select" id="asignar_promotor" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($promotores as $promotor): ?>
                            <option value="<?php echo $promotor['id']; ?>">
                                <?php echo htmlspecialchars($promotor['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="asignar_cantidad" class="form-label">Cantidad a Asignar *</label>
                    <input type="number" class="form-control" id="asignar_cantidad" min="1" value="1" required>
                    <small class="text-muted">Stock disponible: <span id="stock_disponible" class="fw-bold text-success"></span></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarAsignacion()">
                    <i class="bi bi-check-circle me-2"></i>Asignar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ver Productos de Promotor -->
<div class="modal fade" id="verProductosModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Productos Asignados</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 id="promotor_nombre_header"></h6>
                <div id="productos_list" class="mt-3">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let productoActual = null;

    function asignarProducto(producto) {
        productoActual = producto;
        document.getElementById('asignar_producto_id').value = producto.id;
        document.getElementById('asignar_producto_nombre').value = producto.nombre;
        document.getElementById('stock_disponible').textContent = producto.cantidad_stock + ' unidades';
        document.getElementById('asignar_cantidad').max = producto.cantidad_stock;
        document.getElementById('asignar_cantidad').value = Math.min(1, producto.cantidad_stock);

        const modal = new bootstrap.Modal(document.getElementById('asignarModal'));
        modal.show();
    }

    function guardarAsignacion() {
        const productoId = document.getElementById('asignar_producto_id').value;
        const promotorId = document.getElementById('asignar_promotor').value;
        const cantidad = parseInt(document.getElementById('asignar_cantidad').value);

        if (!promotorId || !cantidad) {
            alert('Por favor completa todos los campos');
            return;
        }

        if (productoActual && cantidad > productoActual.cantidad_stock) {
            alert(`La cantidad no puede exceder el stock disponible (${productoActual.cantidad_stock})`);
            return;
        }

        fetch('/promotores-campo-system/api/producto_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'asignar',
                    producto_id: productoId,
                    promotor_id: promotorId,
                    supervisor_id: <?php echo $supervisorId; ?>,
                    cantidad: cantidad
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Producto asignado exitosamente');
                    bootstrap.Modal.getInstance(document.getElementById('asignarModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('[v0] Error:', error);
                alert('Error al asignar producto');
            });
    }

    function verProductosPromotor(promotorId, nombrePromotor) {
        document.getElementById('promotor_nombre_header').textContent = 'Promotor: ' + nombrePromotor;
        document.getElementById('productos_list').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

        fetch('/promotores-campo-system/api/producto_crud.php?promotor_id=' + promotorId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    let html = '<div class="list-group">';
                    data.data.forEach(producto => {
                        html += `
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1">${producto.nombre}</h6>
                                        <p class="mb-0 small text-muted">
                                            Código: ${producto.codigo} | 
                                            Cantidad asignada: ${producto.cantidad_asignada}
                                        </p>
                                    </div>
                                    <span class="badge bg-primary">$${parseFloat(producto.precio).toFixed(2)}</span>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    document.getElementById('productos_list').innerHTML = html;
                } else {
                    document.getElementById('productos_list').innerHTML = '<div class="alert alert-info">No hay productos asignados</div>';
                }
            })
            .catch(error => {
                console.error('[v0] Error:', error);
                document.getElementById('productos_list').innerHTML = '<div class="alert alert-danger">Error al cargar productos</div>';
            });

        const modal = new bootstrap.Modal(document.getElementById('verProductosModal'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>