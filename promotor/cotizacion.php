<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/auth_helpers.php';
require_once '../db/Cotizacion.php';
require_once '../db/Producto.php';
require_once '../db/Cliente.php';
require_once '../db/ActaVisita.php';
require_once '../db/SupervisorPromotor.php';

checkAuth();
checkRole(['Promotor']);

$user_id = $_SESSION['user_id'];
$cotizacionModel = new Cotizacion();
$productoModel = new Producto();
$clienteModel = new Cliente();
$actaModel = new ActaVisita();
$spModel = new SupervisorPromotor();

// Get acta_id from URL if coming from acta de visita
$actaId = $_GET['acta_id'] ?? null;
$acta = null;

if ($actaId) {
    $acta = $actaModel->getById($actaId);
}

$pageTitle = 'Nueva Cotización/Venta';
include '../includes/header.php';

// Get productos asignados al promotor
$productos = $productoModel->getByPromotor($user_id);

$clientes = $clienteModel->getByPromotor($user_id);

// Get actas recientes para vincular
$actas = $actaModel->getByPromotor($user_id, 10);

$supervisores = $spModel->getSupervisoresByPromotor($user_id);
$supervisorId = !empty($supervisores) ? $supervisores[0]['id'] : null;
?>

<style>
    .product-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .product-card:hover {
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .product-card.selected {
        border-color: #059669;
        background-color: #f0fdf4;
    }

    .product-quantity {
        width: 80px;
    }

    .cotizacion-total {
        font-size: 1.5rem;
        font-weight: bold;
        color: #059669;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="h3"><i class="bi bi-receipt me-2"></i>Nueva Cotización/Venta</h2>
            <p class="text-muted">Genera una cotización o registra una venta directa</p>
            <?php if ($acta): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Cotización asociada al acta de visita: <strong><?php echo htmlspecialchars($acta['punto_visita_nombre']); ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <form id="cotizacionForm">
        <input type="hidden" name="acta_id" value="<?php echo htmlspecialchars($actaId ?? ''); ?>">
        <input type="hidden" name="promotor_user_id" value="<?php echo $user_id; ?>">
        <!-- Added supervisor_user_id field with proper value -->
        <input type="hidden" name="supervisor_user_id" value="<?php echo htmlspecialchars($supervisorId ?? ''); ?>">

        <div class="row">
            <div class="col-lg-8 mb-4">
                <!-- Información General -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Información General</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cliente_id" class="form-label">Cliente/Empresa *</label>
                                <select class="form-select" id="cliente_id" name="cliente_id" required onchange="filtrarProductosPorCliente()">
                                    <option value="">Seleccionar cliente...</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?php echo $cliente['id']; ?>">
                                            <?php echo htmlspecialchars($cliente['nombre_empresa']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tipo" class="form-label">Tipo de Documento *</label>
                                <select class="form-select" id="tipo" name="tipo" required>
                                    <option value="cotizacion">Cotización</option>
                                    <option value="venta">Venta Directa</option>
                                    <option value="pedido">Pedido</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="notas" class="form-label">Notas/Observaciones</label>
                            <textarea class="form-control" id="notas" name="notas" rows="3"
                                placeholder="Agrega notas adicionales sobre esta cotización..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Productos Disponibles -->
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Productos Disponibles</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($productos)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                No tienes productos asignados. Contacta a tu supervisor.
                            </div>
                        <?php else: ?>
                            <div class="row" id="productos-container">
                                <?php foreach ($productos as $producto): ?>
                                    <div class="col-md-6 col-lg-4 mb-3 producto-item" data-cliente-id="<?php echo $producto['cliente_id']; ?>">
                                        <div class="card product-card h-100" onclick="toggleProducto(<?php echo $producto['id']; ?>)">
                                            <div class="card-body">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input producto-checkbox" type="checkbox"
                                                        id="producto_<?php echo $producto['id']; ?>"
                                                        value="<?php echo $producto['id']; ?>"
                                                        data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                        data-precio="<?php echo $producto['precio']; ?>"
                                                        data-stock="<?php echo $producto['cantidad_asignada']; ?>"
                                                        onchange="actualizarProductoSeleccionado(this)">
                                                    <label class="form-check-label fw-bold" for="producto_<?php echo $producto['id']; ?>">
                                                        <?php echo htmlspecialchars($producto['nombre']); ?>
                                                    </label>
                                                </div>
                                                <p class="mb-1 small text-muted">
                                                    <strong>Código:</strong> <?php echo htmlspecialchars($producto['codigo']); ?>
                                                </p>
                                                <?php if ($producto['sku']): ?>
                                                    <p class="mb-1 small text-muted">
                                                        <strong>SKU:</strong> <?php echo htmlspecialchars($producto['sku']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p class="mb-2 small">
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($producto['nombre_empresa']); ?></span>
                                                    <span class="badge bg-warning text-dark">Disponible: <?php echo $producto['cantidad_asignada']; ?></span>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-success fw-bold">$<?php echo number_format($producto['precio'], 2); ?></span>
                                                    <input type="number" class="form-control form-control-sm product-quantity"
                                                        id="cantidad_<?php echo $producto['id']; ?>"
                                                        min="1" max="<?php echo $producto['cantidad_asignada']; ?>"
                                                        value="1"
                                                        onchange="actualizarCantidad(<?php echo $producto['id']; ?>)"
                                                        onclick="event.stopPropagation()"
                                                        style="display: none;">
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

            <div class="col-lg-4 mb-4">
                <!-- Resumen de Cotización -->
                <div class="card shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Resumen</h5>
                    </div>
                    <div class="card-body">
                        <div id="resumen-items" class="mb-3">
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-cart-x" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No hay productos seleccionados</p>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span id="subtotal-display">$0.00</span>
                        </div>

                        <div class="mb-3">
                            <label for="impuestos_pct" class="form-label small">Impuestos (%)</label>
                            <input type="number" class="form-control form-control-sm" id="impuestos_pct"
                                min="0" max="100" step="0.1" value="0" onchange="calcularTotal()">
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Impuestos:</span>
                            <span id="impuestos-display">$0.00</span>
                        </div>

                        <hr class="border-2">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <strong>Total:</strong>
                            <span class="cotizacion-total" id="total-display">$0.00</span>
                        </div>

                        <input type="hidden" id="subtotal" name="subtotal" value="0">
                        <input type="hidden" id="impuestos" name="impuestos" value="0">
                        <input type="hidden" id="total" name="total" value="0">

                        <button type="button" class="btn btn-secondary w-100 mb-2" onclick="window.history.back()">
                            <i class="bi bi-arrow-left me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                            <i class="bi bi-check-circle me-2"></i>Generar Cotización
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    let productosSeleccionados = [];

    function filtrarProductosPorCliente() {
        const clienteId = document.getElementById('cliente_id').value;
        const productos = document.querySelectorAll('.producto-item');

        if (!clienteId) {
            productos.forEach(p => p.style.display = 'block');
            return;
        }

        productos.forEach(producto => {
            const productoClienteId = producto.getAttribute('data-cliente-id');
            if (productoClienteId === clienteId) {
                producto.style.display = 'block';
            } else {
                producto.style.display = 'none';
                // Uncheck if hidden
                const checkbox = producto.querySelector('.producto-checkbox');
                if (checkbox && checkbox.checked) {
                    checkbox.checked = false;
                    checkbox.dispatchEvent(new Event('change'));
                }
            }
        });
    }

    function toggleProducto(productoId) {
        const checkbox = document.getElementById(`producto_${productoId}`);
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            actualizarProductoSeleccionado(checkbox);
        }
    }

    function actualizarProductoSeleccionado(checkbox) {
        const card = checkbox.closest('.product-card');
        const cantidadInput = card.querySelector('.product-quantity');
        const productoId = checkbox.value;

        if (checkbox.checked) {
            card.classList.add('selected');
            cantidadInput.style.display = 'block';

            // Add to selected products
            productosSeleccionados.push({
                id: productoId,
                nombre: checkbox.getAttribute('data-nombre'),
                precio: parseFloat(checkbox.getAttribute('data-precio')),
                cantidad: 1
            });
        } else {
            card.classList.remove('selected');
            cantidadInput.style.display = 'none';

            // Remove from selected products
            productosSeleccionados = productosSeleccionados.filter(p => p.id != productoId);
        }

        actualizarResumen();
    }

    function actualizarCantidad(productoId) {
        const cantidadInput = document.getElementById(`cantidad_${productoId}`);
        const cantidad = parseInt(cantidadInput.value) || 1;
        const maxStock = parseInt(cantidadInput.getAttribute('max'));

        if (cantidad > maxStock) {
            alert(`Stock insuficiente. Máximo disponible: ${maxStock}`);
            cantidadInput.value = maxStock;
            return;
        }

        const producto = productosSeleccionados.find(p => p.id == productoId);
        if (producto) {
            producto.cantidad = cantidad;
            actualizarResumen();
        }
    }

    function actualizarResumen() {
        const resumenDiv = document.getElementById('resumen-items');

        if (productosSeleccionados.length === 0) {
            resumenDiv.innerHTML = `
                <div class="text-center text-muted py-3">
                    <i class="bi bi-cart-x" style="font-size: 2rem;"></i>
                    <p class="mt-2 mb-0">No hay productos seleccionados</p>
                </div>
            `;
        } else {
            let html = '<div class="list-group list-group-flush">';
            productosSeleccionados.forEach(producto => {
                const subtotalProducto = producto.precio * producto.cantidad;
                html += `
                    <div class="list-group-item px-0 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <strong class="small">${producto.nombre}</strong>
                                <div class="text-muted small">
                                    ${producto.cantidad} x $${producto.precio.toFixed(2)}
                                </div>
                            </div>
                            <span class="text-success fw-bold">$${subtotalProducto.toFixed(2)}</span>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            resumenDiv.innerHTML = html;
        }

        calcularTotal();
    }

    function calcularTotal() {
        let subtotal = 0;

        productosSeleccionados.forEach(producto => {
            subtotal += producto.precio * producto.cantidad;
        });

        const impuestosPct = parseFloat(document.getElementById('impuestos_pct').value) || 0;
        const impuestos = subtotal * (impuestosPct / 100);
        const total = subtotal + impuestos;

        document.getElementById('subtotal-display').textContent = `$${subtotal.toFixed(2)}`;
        document.getElementById('impuestos-display').textContent = `$${impuestos.toFixed(2)}`;
        document.getElementById('total-display').textContent = `$${total.toFixed(2)}`;

        document.getElementById('subtotal').value = subtotal.toFixed(2);
        document.getElementById('impuestos').value = impuestos.toFixed(2);
        document.getElementById('total').value = total.toFixed(2);
    }

    document.getElementById('cotizacionForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validations
        if (productosSeleccionados.length === 0) {
            alert('Debes seleccionar al menos un producto');
            return;
        }

        const clienteId = document.getElementById('cliente_id').value;
        if (!clienteId) {
            alert('Debes seleccionar un cliente');
            return;
        }

        for (const prod of productosSeleccionados) {
            const checkbox = document.querySelector(`#producto_${prod.id}`);
            const maxStock = parseInt(checkbox.getAttribute('data-stock'));

            if (prod.cantidad > maxStock) {
                alert(`Stock insuficiente para ${prod.nombre}. Disponible: ${maxStock}`);
                return;
            }
        }

        // Prepare data
        const formData = {
            acta_id: document.querySelector('[name="acta_id"]').value || null,
            promotor_user_id: document.querySelector('[name="promotor_user_id"]').value,
            supervisor_user_id: document.querySelector('[name="supervisor_user_id"]').value,
            cliente_id: clienteId,
            tipo: document.getElementById('tipo').value,
            subtotal: document.getElementById('subtotal').value,
            impuestos: document.getElementById('impuestos').value,
            total: document.getElementById('total').value,
            notas: document.getElementById('notas').value,
            productos: productosSeleccionados
        };

        // Disable submit button
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';

        try {
            const response = await fetch('/promotores-campo-system/api/cotizacion_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                alert('Cotización creada exitosamente');
                window.location.href = 'historial.php';
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('[v0] Error:', error);
            alert('Error al procesar la cotización');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Generar Cotización';
        }
    });
</script>

<?php include '../includes/footer.php'; ?>