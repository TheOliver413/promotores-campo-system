<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../db/Producto.php';
require_once __DIR__ . '/../db/Cliente.php';
require_once __DIR__ . '/../db/Auditoria.php';

requireRole(['Administrador']);

$productoModel = new Producto();
$clienteModel = new Cliente();
$auditoriaModel = new Auditoria();

// Handle CRUD operations BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        try {
            $productoId = $productoModel->create([
                'cliente_id' => $_POST['cliente_id'],
                'nombre' => $_POST['nombre'],
                'codigo' => $_POST['codigo'],
                'descripcion' => $_POST['descripcion'] ?? null,
                'cantidad_stock' => $_POST['cantidad_stock'] ?? 0,
                'sku' => $_POST['sku'] ?? null,
                'precio' => $_POST['precio'] ?? 0,
                'activo' => isset($_POST['activo'])
            ]);

            if ($productoId) {
                $auditoriaModel->registrar(getUserId(), 'CREATE', 'productos', $productoId);
                $_SESSION['success'] = 'Producto creado exitosamente';
            } else {
                $_SESSION['error'] = 'Error al crear producto';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al crear producto: ' . $e->getMessage();
        }
    } elseif ($action === 'update') {
        $productoId = $_POST['producto_id'];
        try {
            if ($productoModel->update($productoId, [
                'cliente_id' => $_POST['cliente_id'],
                'nombre' => $_POST['nombre'],
                'codigo' => $_POST['codigo'],
                'descripcion' => $_POST['descripcion'] ?? null,
                'cantidad_stock' => $_POST['cantidad_stock'] ?? 0,
                'sku' => $_POST['sku'] ?? null,
                'precio' => $_POST['precio'] ?? 0,
                'activo' => isset($_POST['activo'])
            ])) {
                $auditoriaModel->registrar(getUserId(), 'UPDATE', 'productos', $productoId);
                $_SESSION['success'] = 'Producto actualizado exitosamente';
            } else {
                $_SESSION['error'] = 'Error al actualizar producto';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al actualizar producto: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $productoId = $_POST['producto_id'];
        try {
            if ($productoModel->delete($productoId)) {
                $auditoriaModel->registrar(getUserId(), 'DELETE', 'productos', $productoId);
                $_SESSION['success'] = 'Producto eliminado exitosamente';
            } else {
                $_SESSION['error'] = 'Error al eliminar producto';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al eliminar producto: ' . $e->getMessage();
        }
    }

    header('Location: /promotores-campo-system/admin/productos.php');
    exit();
}

$pageTitle = 'Gestión de Productos';
require_once __DIR__ . '/../includes/header.php';

$productos = $productoModel->getAll();
$clientes = $clienteModel->getAll();
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Gestión de Productos</h1>
            <p class="text-muted">Administra el catálogo de productos por cliente/empresa</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productoModal" onclick="resetForm()">
                <i class="bi bi-plus-circle"></i> Nuevo Producto
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?php echo $_SESSION['success'];
                                                    unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $_SESSION['error'];
                                                            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if (empty($productos)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-box-seam" style="font-size: 4rem; color: #dee2e6;"></i>
                    <h4 class="text-muted mt-3">No hay productos registrados</h4>
                    <p class="text-muted mb-4">Crea el primer producto para comenzar a gestionar tu inventario</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productoModal" onclick="resetForm()">
                        <i class="bi bi-plus-circle"></i> Crear Primer Producto
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>SKU</th>
                                <th>Cliente/Empresa</th>
                                <th>Stock</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td><?php echo $producto['id']; ?></td>
                                    <td><code><?php echo htmlspecialchars($producto['codigo']); ?></code></td>
                                    <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($producto['sku'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($producto['nombre_empresa'] ?? 'Sin empresa'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $producto['cantidad_stock'] > 0 ? 'success' : 'warning'; ?>">
                                            <?php echo $producto['cantidad_stock']; ?> unidades
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($producto['precio'], 2); ?></td>
                                    <td>
                                        <?php if ($producto['activo']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick='editProducto(<?php echo json_encode($producto); ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteProducto(<?php echo $producto['id']; ?>, '<?php echo htmlspecialchars($producto['nombre']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Producto Modal -->
<div class="modal fade" id="productoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="productoForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="producto_id" id="productoId">

                    <div class="mb-3">
                        <label for="cliente_id" class="form-label">Cliente/Empresa *</label>
                        <select class="form-select" id="cliente_id" name="cliente_id" required>
                            <option value="">Seleccionar cliente...</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>">
                                    <?php echo htmlspecialchars($cliente['nombre_empresa']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">El producto debe estar relacionado con un cliente específico</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre del Producto *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="codigo" class="form-label">Código *</label>
                            <input type="text" class="form-control" id="codigo" name="codigo" required>
                            <small class="text-muted">Código único del producto</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="sku" class="form-label">SKU</label>
                            <input type="text" class="form-control" id="sku" name="sku">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="cantidad_stock" class="form-label">Cantidad en Stock *</label>
                            <input type="number" class="form-control" id="cantidad_stock" name="cantidad_stock" value="0" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="precio" class="form-label">Precio *</label>
                            <input type="number" class="form-control" id="precio" name="precio" value="0" min="0" step="0.01" required>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="activo" name="activo" checked>
                        <label class="form-check-label" for="activo">Producto Activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="producto_id" id="deleteProductoId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el producto <strong id="deleteProductoName"></strong>?</p>
                    <p class="text-muted"><small>Esta acción marcará el producto como inactivo.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function resetForm() {
        document.getElementById('productoForm').reset();
        document.getElementById('formAction').value = 'create';
        document.getElementById('productoId').value = '';
        document.getElementById('modalTitle').textContent = 'Nuevo Producto';
    }

    function editProducto(producto) {
        document.getElementById('formAction').value = 'update';
        document.getElementById('productoId').value = producto.id;
        document.getElementById('cliente_id').value = producto.cliente_id;
        document.getElementById('nombre').value = producto.nombre;
        document.getElementById('codigo').value = producto.codigo;
        document.getElementById('descripcion').value = producto.descripcion || '';
        document.getElementById('sku').value = producto.sku || '';
        document.getElementById('cantidad_stock').value = producto.cantidad_stock;
        document.getElementById('precio').value = producto.precio;
        document.getElementById('activo').checked = producto.activo == 1;
        document.getElementById('modalTitle').textContent = 'Editar Producto';

        const modal = new bootstrap.Modal(document.getElementById('productoModal'));
        modal.show();
    }

    function deleteProducto(id, nombre) {
        document.getElementById('deleteProductoId').value = id;
        document.getElementById('deleteProductoName').textContent = nombre;

        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>